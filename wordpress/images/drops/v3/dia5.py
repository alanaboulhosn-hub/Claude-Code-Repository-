# the BUBS Sour Strawberry Vanilla Diamond as it really looks (BUBS bag and shop photos, 27 Oct): a thick, soft,
# slanted rounded diamond with BUBS raised on top, split straight across into two exactly equal halves, creamy
# vanilla and pastel strawberry, a fine sour-sugar coat; light from the top left. Rendered 3x, then shrunk.
# python3 dia5.py seed out.png   (vanilla half on the left, long axis level: buildref turns it into place)
import sys, math, numpy as np, cv2
from PIL import Image
seed = int(sys.argv[1]) if len(sys.argv) > 1 else 1; OUT = sys.argv[2] if len(sys.argv) > 2 else 'dia5.png'
rng = np.random.default_rng(seed)
K = 3; W, H = 380 * K, 270 * K; cx, cy = W / 2, H / 2
hl, hs = 150 * K, 116 * K
sk = rng.uniform(0.10, 0.22)                                   # how slanted the diamond is
poly = np.array([(cx - hl, cy + sk * hs), (cx - sk * hl, cy - hs), (cx + hl, cy - sk * hs), (cx + sk * hl, cy + hs)], np.float32)
m = np.zeros((H, W), np.uint8); cv2.fillPoly(m, [poly.astype(np.int32)], 1)
r = int(42 * K); k = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (2 * r + 1, 2 * r + 1))
m = cv2.dilate(cv2.erode(m, k), k)
dist = cv2.distanceTransform(m, cv2.DIST_L2, 5)
# the foam slab: a broad soft dome, the edges well rounded
hgt = np.sin(np.clip(dist / (46.0 * K), 0, 1) * np.pi / 2) ** 0.7 * 40 * K
hgt += cv2.GaussianBlur(rng.normal(0, 1, (H, W)).astype(np.float32), (0, 0), 12 * K) * 3 * K
# BUBS raised on top, along the long axis, a little bigger than the top (as on the real candy, letters run to the edges)
L = np.array(Image.open('bubs.png').convert('L')).astype(np.float32) / 255
L = L[:, (L.max(axis=0) > 0.5)]; L = L[(L.max(axis=1) > 0.5), :]
lw = int(2 * hl * rng.uniform(0.64, 0.70)); lh = int(lw * L.shape[0] / L.shape[1])
L = cv2.resize(L, (lw, lh), interpolation=cv2.INTER_AREA)
ang = -math.degrees(math.atan2(sk * hs * 2, 2 * hl)) * 0.6
Mrot = cv2.getRotationMatrix2D((lw / 2, lh / 2), ang, 1.0)
Mrot[0, 2] += cx - lw / 2; Mrot[1, 2] += cy - lh / 2 + rng.uniform(-6, 6) * K
Lm = cv2.warpAffine(L, Mrot, (W, H)) * np.clip(dist / (8 * K), 0, 1)
Ls = cv2.GaussianBlur(Lm, (0, 0), 2.2 * K)
groove = np.clip(cv2.GaussianBlur(cv2.dilate(Lm, np.ones((int(4 * K), int(4 * K)), np.uint8)), (0, 0), 1.5 * K) - Ls, 0, 1)
hgt = hgt + Ls * 5 * K - groove * 3.6 * K
gy, gx = np.gradient(cv2.GaussianBlur(hgt, (0, 0), 2.2 * K))
nrm = np.dstack([-gx, -gy, np.ones_like(gx)]); nrm /= np.linalg.norm(nrm, axis=2, keepdims=True)
Ld = np.array([-0.52, -0.62, 0.59]); Ld /= np.linalg.norm(Ld)
lam = np.clip((nrm * Ld).sum(axis=2), 0, 1)
hv = Ld + np.array([0, 0, 1.0]); hv /= np.linalg.norm(hv)
spec = np.clip((nrm * hv).sum(axis=2), 0, 1) ** 30
# exactly half and half: a straight line across, square to the long axis, moved until the areas match
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
tilt = math.radians(rng.uniform(-14, 14))
s = (xx - cx) * math.cos(tilt) + (yy - cy) * math.sin(tilt)
a = m.astype(np.float32); lo, hi = -40.0 * K, 40.0 * K
for _ in range(50):
    mid = (lo + hi) / 2
    if (a * (s > mid)).sum() > (a * (s <= mid)).sum(): lo = mid
    else: hi = mid
off = (lo + hi) / 2
jit = cv2.GaussianBlur(rng.normal(0, 1, (H, W)).astype(np.float32), (0, 0), 3 * K) * 3 * K
t = np.clip((s - off + jit) / (2.5 * K) * 0.5 + 0.5, 0, 1)       # 0 vanilla (left), 1 strawberry (right)
van = np.array([250, 243, 232], np.float32); stw = np.array([241, 150, 176], np.float32)
base = van * (1 - t[..., None]) + stw * t[..., None]
shade = (0.74 + 0.33 * lam)[..., None]
# soft foam: light goes in a little, so shadows stay coloured (no grey)
col = base * shade + (base * 0.10) * (1 - shade)
# fine sour sugar: small bright crystals everywhere, a few bigger
sug = np.zeros((H, W), np.float32)
ys, xs = np.nonzero(m); n = int(len(ys) / (2.2 * K * K))
for j in rng.integers(0, len(ys), n):
    rr = float(rng.choice([0.5, 0.8, 1.2, 1.8], p=[0.4, 0.35, 0.2, 0.05])) * K
    cv2.circle(sug, (int(xs[j]), int(ys[j])), max(1, int(rr)), float(rng.uniform(0.2, 0.75)), -1, cv2.LINE_AA)
sug = cv2.GaussianBlur(sug, (0, 0), 0.5 * K)
glint = np.minimum(np.array([255, 250, 252]) * (0.72 + 0.34 * lam[..., None]), 255)
col = col * (1 - 0.45 * sug[..., None]) + glint * 0.45 * sug[..., None]
col = col + 255 * 0.10 * spec[..., None]
rim = np.clip(1 - dist / (5 * K), 0, 1); col = col * (1 - 0.12 * rim[..., None])
al = cv2.GaussianBlur(a, (0, 0), 0.7 * K)
img = Image.fromarray(np.dstack([np.clip(col, 0, 255), al * 255]).astype(np.uint8), 'RGBA').resize((W // K, H // K), Image.LANCZOS)
img.save(OUT)
A = np.array(img)[..., 3].astype(np.float32); T = cv2.resize(t, (W // K, H // K))
print(OUT, 'vanilla %.3f strawberry %.3f' % ((A * (T < 0.5)).sum() / A.sum(), (A * (T >= 0.5)).sum() / A.sum()))
