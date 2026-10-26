# the Strawberry Vanilla Diamond, exactly half and half: the real BUBS diamond (shape, sugar, light) from the Tutti
# Frutti Diamond photo, split along its long diagonal (the line moved until both halves hold the same area),
# strawberry on one half, vanilla on the other
import numpy as np, cv2
from PIL import Image
im = np.array(Image.open('cut/diamond.png')).astype(np.float32)
rgb, a = im[..., :3], im[..., 3] / 255
h, w = a.shape
cs, _ = cv2.findContours((a > 0.5).astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
c = max(cs, key=cv2.contourArea)
quad = None
for eps in np.linspace(0.01, 0.12, 60):
    ap = cv2.approxPolyDP(c, eps * cv2.arcLength(c, True), True)
    if len(ap) == 4: quad = ap; break
if quad is None: quad = cv2.boxPoints(cv2.minAreaRect(c)).astype(np.int32).reshape(-1, 1, 2)
rh = np.zeros((h, w), np.uint8); cv2.fillPoly(rh, [quad], 1)
kk = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (31, 31))
rh = cv2.dilate(cv2.erode(rh, kk), kk)
rgb = cv2.inpaint(rgb.astype(np.uint8), ((a < 0.9) & (rh > 0)).astype(np.uint8), 5, cv2.INPAINT_TELEA).astype(np.float32)
a = cv2.GaussianBlur(rh.astype(np.float32), (0, 0), 0.8)
m = a > 0.5
ys, xs = np.nonzero(m)
L_pt = np.array([xs.min(), ys[xs == xs.min()].mean()]); R_pt = np.array([xs.max(), ys[xs == xs.max()].mean()])
d = (R_pt - L_pt) / np.linalg.norm(R_pt - L_pt); n = np.array([-d[1], d[0]])      # n points "down" (vanilla side)
if n[1] < 0: n = -n
yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
s = (xx - L_pt[0]) * n[0] + (yy - L_pt[1]) * n[1]                                      # signed distance to the diagonal
lo, hi = -60.0, 60.0
for _ in range(60):                                                                    # equal areas (alpha-weighted)
    mid = (lo + hi) / 2
    if (a * (s > mid)).sum() > (a * (s <= mid)).sum(): lo = mid
    else: hi = mid
off = (lo + hi) / 2
t = np.clip((s - off) / 1.6 * 0.5 + 0.5, 0, 1)                                         # 0 strawberry, 1 vanilla
print('split offset %.2f px, strawberry %.4f / vanilla %.4f' % (off, (a * (s <= off)).sum() / a.sum(), (a * (s > off)).sum() / a.sum()))
# light and sugar from the photo, with its own green / yellow layers evened out first
hsv = cv2.cvtColor(rgb.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
Hh = hsv[..., 0] * 2
L = (0.3 * rgb[..., 0] + 0.59 * rgb[..., 1] + 0.11 * rgb[..., 2]) / 255
gr = cv2.GaussianBlur(((Hh >= 64) & m).astype(np.float32), (0, 0), 5); yl = cv2.GaussianBlur(((Hh < 64) & m).astype(np.float32), (0, 0), 5)
wg = gr / (gr + yl + 1e-4)
mg, my = L[(wg > 0.7) & m].mean(), L[(wg < 0.3) & m].mean()
Le = L / (wg * mg + (1 - wg) * my) * 0.5 * (mg + my)
low = cv2.GaussianBlur(Le, (0, 0), 7); det = Le - cv2.GaussianBlur(Le, (0, 0), 1.8)
Lf = np.clip(low + det * 1.25, 0, 1)
v = Lf[m]; lo_, hi_ = np.percentile(v, 2), np.percentile(v, 99.6)
x = np.clip((Lf - lo_) / (hi_ - lo_), 0, 1)
def ramp(x, stops):
    x = x[..., None]; out = np.zeros(x.shape[:-1] + (3,), np.float32)
    for (t0, c0), (t1, c1) in zip(stops[:-1], stops[1:]):
        k = np.clip((x - t0) / (t1 - t0), 0, 1); seg = (x >= t0) & (x <= t1)
        out = np.where(seg, np.array(c0) + (np.array(c1) - np.array(c0)) * k, out)
    return out
straw = ramp(x, [(0, (128, 14, 42)), (0.45, (206, 44, 80)), (0.78, (238, 104, 132)), (1.0, (255, 220, 228))])
vanil = ramp(x, [(0, (196, 172, 132)), (0.40, (243, 231, 204)), (0.75, (252, 246, 230)), (1.0, (255, 255, 251))])
out = straw * (1 - t[..., None]) + vanil * t[..., None]
Image.fromarray(np.dstack([np.clip(out, 0, 255), a * 255]).astype(np.uint8), 'RGBA').save('cut/sv3.png')
