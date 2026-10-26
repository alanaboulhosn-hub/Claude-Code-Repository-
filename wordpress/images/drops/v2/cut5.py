# more clean single-candy cut-outs for the Fan Favourites Mix: three different Pomegranate Ovals from the BUBS photo,
# and classic sweet & sour pieces from the shop's own mix photos (same top-down light as the bag photo)
import sys, math, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi
def circ(cx, cy, rx, ry, n=24): return [(int(cx + rx * math.cos(2 * math.pi * k / n)), int(cy + ry * math.sin(2 * math.pi * k / n))) for k in range(n)]
P = {
 'pomeA': ('src/BUBS-Wild-Berry-Pomegranate-Oval.webp', [(360,420),(368,385),(395,355),(440,332),(490,322),(545,320),(600,328),(645,348),(672,378),(680,415),(672,452),(645,485),(600,510),(545,522),(490,522),(440,512),(395,492),(368,460)], 1.0),
 'pomeB': ('src/BUBS-Wild-Berry-Pomegranate-Oval.webp', [(180,640),(195,590),(225,555),(270,528),(330,514),(390,513),(445,530),(482,565),(500,610),(498,660),(478,705),(440,738),(380,756),(310,757),(250,742),(208,712),(186,680)], 1.0),
 'pomeC': ('src/BUBS-Wild-Berry-Pomegranate-Oval.webp', [(530,640),(545,585),(585,540),(650,517),(730,518),(795,545),(835,592),(846,645),(826,705),(770,750),(690,768),(610,760),(558,730),(533,690)], 1.0),
 'bear': ('src/Sweet-Mix.jpeg', [(570,700),(585,680),(605,682),(625,700),(660,715),(700,745),(740,770),(775,800),(790,830),(785,855),(765,862),(735,850),(700,830),(660,810),(620,790),(590,770),(572,740)], 0.6),
 'redfish': ('src/Sweet-Mix.jpeg', [(752,985),(772,966),(792,974),(812,1002),(840,1040),(864,1080),(866,1110),(850,1126),(826,1114),(805,1090),(780,1060),(760,1030),(750,1005)], 0.6),
 'redtube': ('src/Sweet-Mix.jpeg', [(664,864),(700,857),(780,861),(820,867),(824,900),(820,933),(780,938),(700,934),(664,928),(660,896)], 0.6),
 'bluefizz': ('src/Sweet_Sour-Mix.jpeg', [(452,945),(470,937),(560,936),(648,942),(652,1000),(645,1028),(560,1031),(470,1027),(452,1010)], 0.6),
 'wheel': ('src/Sweet_Sour-Mix.jpeg', circ(727, 687, 90, 82), 0.6),
 'orfish': ('src/Sweet_Sour-Mix.jpeg', [(668,842),(690,838),(712,846),(726,850),(722,872),(730,905),(737,960),(738,1000),(727,1030),(711,1060),(695,1074),(679,1066),(669,1040),(661,1000),(653,960),(656,920),(661,880),(665,860)], 0.6),
}
FIX = {'pomeA': (4, 26, None), 'pomeB': (4, 26, None), 'pomeC': (4, 26, None), 'bear': (3, 6, (40, 110)), 'wheel': (0, 8, (335, 25)),
       'bluefizz': (0, 6, (165, 235)), 'orfish': (3, 5, (340, 40)), 'redfish': (3, 5, None), 'redtube': (2, 4, None)}
def cut(key, inner=10, outer=10):
    src, poly, _ = P[key]
    im = np.array(Image.open(src).convert('RGB'))
    h, w = im.shape[:2]
    pm = np.zeros((h, w), np.uint8); cv2.fillPoly(pm, [np.array(poly, np.int32)], 1)
    fg = ndi.binary_erosion(pm, iterations=inner); bgm = ~ndi.binary_dilation(pm, iterations=outer)
    white = (im.min(axis=2) > 232) & (cv2.cvtColor(im, cv2.COLOR_RGB2HSV)[..., 1] < 18)
    markers = np.zeros((h, w), np.int32); markers[bgm | white] = 1; markers[fg & ~white] = 2
    sm = cv2.bilateralFilter(im, 7, 40, 7)
    cv2.watershed(sm, markers)
    m = markers == 2
    m = ndi.binary_opening(m, iterations=2); m = ndi.binary_closing(m, iterations=3)
    lab, n = ndi.label(m); m = lab == (np.argmax(ndi.sum(m, lab, range(1, n + 1))) + 1); m = ndi.binary_fill_holes(m)
    clip, ropen, hue = FIX.get(key, (None, 0, None))
    if clip is not None:                                    # never beyond the traced outline (+ clip px)
        m &= ndi.binary_dilation(pm.astype(bool), iterations=clip) if clip else pm.astype(bool)
    if hue is not None:                                     # only the candy's own colour (drops a neighbour's edge)
        hh = cv2.cvtColor(im, cv2.COLOR_RGB2HSV)[..., 0].astype(np.int32) * 2
        hm = ((hh >= hue[0]) & (hh <= hue[1])) if hue[0] <= hue[1] else ((hh >= hue[0]) | (hh <= hue[1]))
        hm &= cv2.cvtColor(im, cv2.COLOR_RGB2HSV)[..., 1] > 50
        m &= ndi.binary_fill_holes(ndi.binary_closing(hm, iterations=4))
    if ropen:                                               # round candies: bits stuck on the outline come off
        k = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (2 * ropen + 1, 2 * ropen + 1))
        m = cv2.morphologyEx(m.astype(np.uint8), cv2.MORPH_OPEN, k).astype(bool)
    lab, n = ndi.label(m); m = lab == (np.argmax(ndi.sum(m, lab, range(1, n + 1))) + 1); m = ndi.binary_fill_holes(m)
    if key.startswith('pome'):                              # the ovals are ellipses: trim to the best-fitting one
        cs, _ = cv2.findContours(m.astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
        (ex, ey), (ea, eb), ang = cv2.fitEllipse(max(cs, key=cv2.contourArea))
        e = np.zeros((h, w), np.uint8); cv2.ellipse(e, ((ex, ey), (ea * 0.985, eb * 0.985), ang), 1, -1)
        m &= e.astype(bool)
    a = cv2.GaussianBlur(m.astype(np.float32), (0, 0), 1.0)
    a = np.clip((a - 0.5) * 1.8 + 0.5, 0, 1)
    ys, xs = np.where(a > 0.02); y0, y1, x0, x1 = ys.min() - 4, ys.max() + 5, xs.min() - 4, xs.max() + 5
    rgba = np.dstack([im, (a * 255).astype(np.uint8)])[y0:y1, x0:x1]
    Image.fromarray(rgba, 'RGBA').save(f'cut/{key}.png')
    return rgba.shape
for k in (sys.argv[1:] or P):
    print(k, cut(k))
