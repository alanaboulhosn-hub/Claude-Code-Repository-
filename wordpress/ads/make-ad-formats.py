# square (1:1) and horizontal (1.91:1) versions of the black-and-orange ad, laid out for each shape from the 4:5
# design's own parts (wordmark, lines, bats, candies) at full quality. Each part carries only what differs from the
# 4:5 night background, so it sits seamlessly on the new canvas's night.
import numpy as np, cv2
from PIL import Image
A = np.array(Image.open('fika-halloween-ad-orange.png').convert('RGB')).astype(np.float32)
H0, W0 = A.shape[:2]
yy, xx = np.mgrid[0:H0, 0:W0].astype(np.float32)
r = np.clip(np.sqrt(((xx - W0 * .5) / (W0 * .8)) ** 2 + ((yy - H0 * .25) / (H0 * .55)) ** 2), 0, 1)[..., None]
OLD = np.array([36, 24, 56], np.float32) * (1 - r) + np.array([12, 8, 20], np.float32) * r
DIFF = A - OLD                                            # the parts, without the background
mag = np.abs(DIFF).max(axis=2, keepdims=True)              # tiny leftovers of the old background (1-4 levels) dropped
DIFF = DIFF * np.clip((mag - 4) / 6, 0, 1)
def night(w, h, cx, cy, ax, ay):
    Y, X = np.mgrid[0:h, 0:w].astype(np.float32)
    t = np.clip(np.sqrt(((X - cx) / ax) ** 2 + ((Y - cy) / ay) ** 2), 0, 1)[..., None]
    return np.array([36, 24, 56], np.float32) * (1 - t) + np.array([12, 8, 20], np.float32) * t
def place(canvas, box, s, dx, dy, feather=24):
    x0, y0, x1, y1 = box
    d = DIFF[y0:y1, x0:x1]
    m = np.ones(d.shape[:2], np.float32); m[:feather] *= np.linspace(0, 1, feather)[:, None]; m[-feather:] *= np.linspace(1, 0, feather)[:, None]
    m[:, :feather] *= np.linspace(0, 1, feather)[None]; m[:, -feather:] *= np.linspace(1, 0, feather)[None]
    w, h = round((x1 - x0) * s), round((y1 - y0) * s)
    d = cv2.resize(d * m[..., None], (w, h), interpolation=cv2.INTER_AREA if s < 1 else cv2.INTER_CUBIC)
    dx, dy = round(dx), round(dy)
    canvas[dy:dy + h, dx:dx + w] += d
    return canvas
# the parts (4:5 coordinates)
LOGO = (400, 180, 1200, 575); SPEC = (430, 570, 1170, 665)
BAT1 = (80, 55, 300, 160); BAT2 = (1385, 295, 1595, 400); BAT3 = (20, 1615, 225, 1715)
FTXT = (150, 800, 830, 1025); FCAN = (140, 1085, 790, 1630)
STXT = (870, 800, 1480, 1025); SCAN = (880, 1065, 1450, 1760)
CONTENT = (0, 40, 1600, 1780)
# --- square 1:1, made at 2160 then shrunk to 1080: the whole design, centred, a little more room at the sides
S = 2160; sq = night(S, S, S / 2, S * .27, S * .8, S * .6)
s = 1.2; cw, ch = (CONTENT[2] - CONTENT[0]) * s, (CONTENT[3] - CONTENT[1]) * s
sq = place(sq, CONTENT, s, (S - cw) / 2, (S - ch) / 2, feather=40)
Image.fromarray(np.clip(sq, 0, 255).astype(np.uint8)).resize((1080, 1080), Image.LANCZOS).save('fika-halloween-ad-1x1.png')
# --- horizontal 1.91:1, made at 2400 x 1256 then shrunk to 1200 x 628: wordmark left, the two bags side by side
Wh, Hh = 2400, 1256; hz = night(Wh, Hh, 430, Hh * .45, 1300, 900)
hz = place(hz, LOGO, .92, 50, 300)
hz = place(hz, SPEC, .92, 72, 660)
hz = place(hz, BAT1, .8, 60, 120); hz = place(hz, BAT2, .8, 640, 190); hz = place(hz, BAT3, .8, 120, 930)
s2 = .86
hz = place(hz, FTXT, s2, 880, 175); hz = place(hz, FCAN, s2, 890, 400)
hz = place(hz, STXT, s2, 1660, 175); hz = place(hz, SCAN, s2, 1690, 380)
Image.fromarray(np.clip(hz, 0, 255).astype(np.uint8)).resize((1200, 628), Image.LANCZOS).save('fika-halloween-ad-1.91x1.png')
print('ok')
