# the owner's picture, sticker text FIKA FAVOURITES -> FAN FAVOURITES (27 Oct): the sticker re-rendered (same
# design), fitted by pixel matching (size, angle, place: ../ref/stk-fit.txt), only its top arc (the name) put in, the
# heart, rings and the bottom line "LIMITED EDITION · NR. 1" stay as they were; colour matched to the sticker's paper
import sys, math, numpy as np, cv2
from PIL import Image
REF, OUT = sys.argv[1], sys.argv[2]
mx, d, ang, cx, cy = [float(x) for x in open('../ref/stk-fit.txt').read().split()]
d = int(d)
src = np.array(Image.open(REF).convert('RGB')).astype(np.float32); H, W = src.shape[:2]
p = Image.open('art/sticker-fan2.png').convert('RGBA').resize((d, d), Image.LANCZOS).rotate(ang, resample=Image.BICUBIC, expand=True)
a = np.array(p).astype(np.float32); rgb, al = a[..., :3], a[..., 3] / 255
x0, y0 = int(round(cx - p.width / 2)), int(round(cy - p.height / 2))
full = np.zeros((H, W, 3), np.float32); A = np.zeros((H, W), np.float32)
full[y0:y0 + p.height, x0:x0 + p.width] = rgb; A[y0:y0 + p.height, x0:x0 + p.width] = al
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
# in the sticker's own frame: angle (0 = straight up on the sticker) and distance from its centre
th = np.degrees(np.arctan2(xx - cx, -(yy - cy))) + ang          # the sticker is turned by ang (anticlockwise)
th = (th + 180) % 360 - 180
rr = np.hypot(xx - cx, yy - cy) / (d / 2 * 0.96)                # 1 = the sticker's edge
band = ((np.abs(th) < 84) & (rr > 0.60) & (rr < 0.885)).astype(np.float32)
band = cv2.GaussianBlur(band, (0, 0), 2.5) * (A > 0.99)
# the paper's colour and light: match the new cream to the photo's cream in the band (per channel, smooth)
cream_new = (full.mean(axis=2) > 190) & (band > 0.5)
cream_old = (src.mean(axis=2) > 170) & (band > 0.5)
gain = src[cream_old].mean(axis=0) / full[cream_new].mean(axis=0)
shade = cv2.GaussianBlur(src.mean(axis=2) * cream_old, (0, 0), 14) / np.maximum(cv2.GaussianBlur(cream_old.astype(np.float32), (0, 0), 14), 1e-3)
shade = shade / max(1e-3, src[cream_old].mean())
new = np.clip(full * gain * np.clip(shade, 0.85, 1.12)[..., None], 0, 255)
# the photo's softness: a touch of blur like the rest of the print
new = cv2.GaussianBlur(new, (0, 0), 0.55)
out = src * (1 - band[..., None]) + new * band[..., None]
Image.fromarray(np.clip(out, 0, 255).astype(np.uint8)).save(OUT, quality=95)
print('saved', OUT, 'gain', np.round(gain, 3))
