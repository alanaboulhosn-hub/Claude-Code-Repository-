# The Limited Edition Drop picture: the shop's real Sweet & Sour Mix photo (its classic candies spilling out of the bag
# stay exactly as photographed), the bag recoloured night purple with the orange logo and the Limited Edition
# sticker, the white table warmed to the bone table of the Skull Mix picture, and the limited candies (BUBS
# Strawberry Vanilla Diamonds and Wild Berry Pomegranate Ovals, real cut-outs) mixed into the spill with shadows.
# python3 compose2.py [full.jpg] [crop.jpg]
import sys, random, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi

FULL = sys.argv[1] if len(sys.argv) > 1 else 'out-drop2-full.jpg'
CROP = sys.argv[2] if len(sys.argv) > 2 else 'out-drop2.jpg'
src = np.array(Image.open('src/Sweet_Sour-Mix.jpeg').convert('RGB')).astype(np.float32)
H, W = src.shape[:2]
bag = np.load('bagmask2.npy')

def ramp(x, stops):
    x = x[..., None]; out = np.zeros(x.shape[:-1] + (3,), np.float32)
    for (t0, c0), (t1, c1) in zip(stops[:-1], stops[1:]):
        w = np.clip((x - t0) / (t1 - t0), 0, 1); seg = (x >= t0) & (x <= t1)
        out = np.where(seg, np.array(c0) + (np.array(c1) - np.array(c0)) * w, out)
    return out

# ---------- the paper, without the printed logo ----------
hsv = cv2.cvtColor(src.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
hue, sat = hsv[..., 0] * 2, hsv[..., 1] / 255
logo_a = np.clip((sat - 0.12) / 0.3, 0, 1) * ((hue > 195) & (hue < 245)) * bag
logo_m = ndi.binary_dilation(logo_a > 0.05, iterations=2)
L = 0.3 * src[..., 0] + 0.59 * src[..., 1] + 0.11 * src[..., 2]
L8 = cv2.inpaint(np.clip(L, 0, 255).astype(np.uint8), logo_m.astype(np.uint8), 9, cv2.INPAINT_TELEA)
Lp = L8.astype(np.float32)
Lp = cv2.bilateralFilter(Lp, 0, 7, 9)
Lp = cv2.GaussianBlur(Lp, (0, 0), 1.6) * 0.6 + Lp * 0.4
Lp = cv2.bilateralFilter(Lp, 0, 5, 6)
lo, hi = np.percentile(Lp[bag], 1.5), np.percentile(Lp[bag], 99.7)
Ln = np.clip((Lp - lo) / (hi - lo), 0, 1)
paper = ramp(Ln ** 1.15, [(0, (26, 12, 44)), (0.55, (58, 32, 92)), (0.85, (86, 54, 128)), (1.0, (130, 98, 168))])
ink = np.array([255, 126, 32], np.float32)
paper = paper * (1 - logo_a[..., None]) + (ink * (0.78 + 0.22 * Ln[..., None])) * logo_a[..., None]

# ---------- the table and the photographed candies: white table -> bone, shadows kept ----------
bgm = (hsv[..., 1] < 12) & (hsv[..., 2] > 215) & ~ndi.binary_dilation(bag, iterations=20)
bgc = np.median(src[bgm], axis=0)
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
r = np.sqrt(((xx - W * 0.58) / (W * 0.9)) ** 2 + ((yy - H * 0.62) / (H * 0.85)) ** 2)
import os
if os.environ.get('TABLE') == 'white':                       # product photos: the white table of the Ready Mix photos
    table = np.full((H, W, 3), 255, np.float32)            # pure white, like the Ready Mix photos (shadows stay)
else:
    table = ramp(np.clip(1 - r, 0, 1), [(0, (205, 192, 168)), (0.5, (232, 222, 200)), (1.0, (248, 242, 228))])
lit = src / bgc[None, None, :]                              # 1 = the bare table, below 1 = shade or candy
if os.environ.get('TABLE') == 'white':                       # the photo's grey corners white, real shadows kept
    lv = lit.mean(axis=2, keepdims=True)
    lit = lit * np.clip(1 - np.maximum(0, 0.955 - lv) * 1.3, 0, 1) / np.maximum(lv, 1e-3) * np.where(lv > 0.955, 1, lv) + np.where(lv > 0.955, 1 - lit, 0) * 0
img = np.clip(lit, 0, 1.15) * table
# candies keep their own colour (only the table and its shadows turn bone): blend back where saturated or dark
cand = np.clip((hsv[..., 1] / 255 - 0.10) / 0.18, 0, 1) * (~bag)
cand = cv2.GaussianBlur(cand, (0, 0), 1.2)
img = img * (1 - cand[..., None]) + (src * (table / bgc[None, None, :]) * 0.35 + src * 0.65) * cand[..., None]
bf = cv2.GaussianBlur(bag.astype(np.float32), (0, 0), 0.8)
img = img * (1 - bf[..., None]) + paper * bf[..., None]

def mul(base, shade):
    return base * shade[..., None]

# ---------- the sticker ----------
st = Image.open('art/sticker-drop.png').convert('RGBA')
size, cxs, cys, tilt = (205, 470, 455, 8)
st = st.resize((size, size), Image.LANCZOS).rotate(tilt, resample=Image.BICUBIC, expand=True)
sa = np.array(st).astype(np.float32); srgb, sal = sa[..., :3], sa[..., 3] / 255
x0, y0 = cxs - st.width // 2, cys - st.height // 2
sl = (slice(y0, y0 + st.height), slice(x0, x0 + st.width))
s = np.zeros((H, W), np.float32); s[sl] = sal
img = mul(img, 1 - 0.45 * cv2.GaussianBlur(np.roll(np.roll(s, 3, 0), 2, 1), (0, 0), 2.5) * (1 - s))
light = (0.86 + 0.2 * Ln[sl])[..., None]
img[sl] = img[sl] * (1 - sal[..., None]) + np.clip(srgb * light, 0, 255) * sal[..., None]

# ---------- the limited candies, in the spill ----------
cache = {}
def piece(name):
    if name not in cache: cache[name] = Image.open(f'cut/{name}.png').convert('RGBA')
    return cache[name]
def place(canvas, name, cx, cy, rot, scale, flip=False):
    p = piece(name)
    if flip: p = p.transpose(Image.FLIP_LEFT_RIGHT)
    p = p.resize((max(1, int(p.width * scale)), max(1, int(p.height * scale))), Image.LANCZOS).rotate(rot, resample=Image.BICUBIC, expand=True)
    a = np.array(p).astype(np.float32); rgb, al = a[..., :3], a[..., 3] / 255
    x0, y0 = int(cx - p.width / 2), int(cy - p.height / 2); x1, y1 = x0 + p.width, y0 + p.height
    cx0, cy0, cx1, cy1 = max(0, x0), max(0, y0), min(W, x1), min(H, y1)
    sl = (slice(cy0, cy1), slice(cx0, cx1)); ps = (slice(cy0 - y0, cy1 - y0), slice(cx0 - x0, cx1 - x0))
    s = np.zeros((H, W), np.float32); s[sl] = al[ps]
    soft = cv2.GaussianBlur(np.roll(np.roll(s, 9, 0), 7, 1), (0, 0), 7)
    tight = cv2.GaussianBlur(np.roll(np.roll(s, 3, 0), 2, 1), (0, 0), 2.2)
    canvas = mul(canvas, 1 - 0.30 * soft - 0.22 * tight * (1 - s))
    m = al[ps][..., None]
    canvas[sl] = canvas[sl] * (1 - m) + rgb[ps] * m
    return canvas

random.seed(11)
S = {'pome': 0.39, 'strawvan': 0.36}
pieces = [('strawvan', 604, 612, 24, False), ('pome', 470, 700, -16, True), ('strawvan', 700, 735, -38, False),
          ('pome', 566, 830, 30, False), ('strawvan', 410, 885, 52, True), ('pome', 712, 935, -8, False),
          ('strawvan', 528, 1016, -24, False), ('pome', 846, 862, 64, True)]
bagd = ndi.binary_dilation(bag, iterations=6)
for n, x, y, rot, fl in pieces:
    for _ in range(12):                                   # never on the bag: move down the spill until clear
        pr = piece(n); hw_ = int(max(pr.width, pr.height) * S[n] / 2)
        if not bagd[max(0, y - hw_):y + hw_, max(0, x - hw_):x + hw_].any(): break
        x, y = x + 6, y + 18
    img = place(img, n, x, y, rot + random.uniform(-6, 6), S[n] * random.uniform(0.93, 1.07), fl)

rng = np.random.default_rng(5)
img = img + rng.normal(0, 2.2, img.shape).astype(np.float32)
out = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
out.save(FULL, quality=92)
out.crop((0, 140, 1024, 1200)).save(CROP, quality=90)
print('saved', FULL, CROP)
