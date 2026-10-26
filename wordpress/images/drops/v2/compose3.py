# Halloween drop pictures from the shop's own photos (fan = the Fan Favourites Mix, 27 Oct): the Fika bag from the Sweet Mix photo, recoloured (black for the
# Skull Mix, night purple for the Limited Edition Drop), a sticker on it, and real candy cut-outs coming out of its
# opening and spilling onto the table, with their shadows. python3 compose.py skull|drop [out.jpg]
import sys, math, random, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi

KIND = sys.argv[1]
OUT = sys.argv[2] if len(sys.argv) > 2 else f'out-{KIND}.jpg'
src = np.array(Image.open('src/Sweet-Mix.jpeg').convert('RGB')).astype(np.float32)
H, W = src.shape[:2]
bag = np.load('bagmask.npy')

# ---------- where things are ----------
P0, P1, P2 = (285, 800), (675, 548), (838, 474)            # the edge of the bag's top sheet at the opening
above = np.zeros((H, W), np.uint8)
cv2.fillPoly(above, [np.array([(0, 0), (W, 0), (W, 470), P2, P1, P0, (0, 990)], np.int32)], 1)
above = above.astype(bool)
# distance to the lip line (px): the shadow the top sheet throws into the opening, and its clean edge
lip = np.zeros((H, W), np.uint8)
cv2.polylines(lip, [np.array([P0, P1, P2], np.int32)], False, 1, 2)
dist = ndi.distance_transform_edt(1 - lip)
band = np.zeros((H, W), np.uint8)
cv2.fillPoly(band, [np.array([P0, P1, P2, (P2[0] + 6, P2[1] - 90), (P1[0] + 8, P1[1] - 130), (P0[0] - 6, P0[1] - 130)], np.int32)], 1)
gap = above & band.astype(bool) & ~bag                      # where the original candies covered the bag's edge
top = (bag & above) | gap                                   # the sheet with the logo, over the candies
low = bag & ~above                                          # the bottom sheet and the inner fold, under them

# ---------- the paper: its light and shade, without the printed logo ----------
hsv = cv2.cvtColor(src.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
hue, sat = hsv[..., 0] * 2, hsv[..., 1] / 255
logo_a = np.clip((sat - 0.12) / 0.3, 0, 1) * ((hue > 195) & (hue < 245)) * bag
logo_m = ndi.binary_dilation(logo_a > 0.05, iterations=2)
L = (0.3 * src[..., 0] + 0.59 * src[..., 1] + 0.11 * src[..., 2])
L8 = np.clip(L, 0, 255).astype(np.uint8)
L8 = cv2.inpaint(L8, (logo_m | gap).astype(np.uint8), 9, cv2.INPAINT_TELEA)
Lp = L8.astype(np.float32)
# the photo's soft paper shading carries JPEG blocks that show once the paper is dark: smooth it, keep the folds
Lp = cv2.bilateralFilter(Lp, 0, 7, 9)
Lp = cv2.GaussianBlur(Lp, (0, 0), 1.6) * 0.6 + Lp * 0.4
Lp = cv2.bilateralFilter(Lp, 0, 5, 6)
lo, hi = np.percentile(Lp[top], 1.5), np.percentile(Lp[top], 99.7)
Ln = np.clip((Lp - lo) / (hi - lo), 0, 1)

def ramp(x, stops):
    x = x[..., None]; out = np.zeros(x.shape[:-1] + (3,), np.float32)
    for (t0, c0), (t1, c1) in zip(stops[:-1], stops[1:]):
        w = np.clip((x - t0) / (t1 - t0), 0, 1); seg = (x >= t0) & (x <= t1)
        out = np.where(seg, np.array(c0) + (np.array(c1) - np.array(c0)) * w, out)
    return out

if KIND == 'skull':
    paper = ramp(Ln ** 1.25, [(0, (10, 8, 14)), (0.55, (30, 26, 38)), (0.85, (56, 50, 66)), (1.0, (96, 90, 108))])
    ink = np.array([255, 126, 32], np.float32)
    inside_col = np.array([6, 5, 9], np.float32)
elif KIND == 'fan':                                          # matte deep plum paper, the Skull Mix bag's sister
    paper = ramp(Ln ** 1.2, [(0, (20, 9, 24)), (0.55, (50, 24, 56)), (0.85, (84, 48, 90)), (1.0, (132, 98, 136))])
    ink = np.array([255, 126, 32], np.float32)
    inside_col = np.array([10, 4, 12], np.float32)
else:
    paper = ramp(Ln ** 1.15, [(0, (26, 12, 44)), (0.55, (58, 32, 92)), (0.85, (86, 54, 128)), (1.0, (130, 98, 168))])
    ink = np.array([255, 126, 32], np.float32)
    inside_col = np.array([14, 7, 24], np.float32)
paper = paper * (1 - logo_a[..., None]) + (ink * (0.78 + 0.22 * Ln[..., None])) * logo_a[..., None]

# ---------- the table ----------
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
r = np.sqrt(((xx - W * 0.58) / (W * 0.9)) ** 2 + ((yy - H * 0.62) / (H * 0.85)) ** 2)
import os
if os.environ.get('TABLE') == 'white':                       # product photos: the white table of the Ready Mix photos
    table = np.full((H, W, 3), 255, np.float32)            # pure white, like the Ready Mix photos (shadows stay)
else:
    table = ramp(np.clip(1 - r, 0, 1), [(0, (205, 192, 168)), (0.5, (232, 222, 200)), (1.0, (248, 242, 228))])
img = table.copy()

def mul(base, shade):                                       # multiply a shade map (1 = no change) onto the picture
    return base * shade[..., None]

# the bag's shadow on the table
sh = cv2.GaussianBlur(bag.astype(np.float32), (0, 0), 22)
sh = np.roll(np.roll(sh, 16, axis=0), 12, axis=1)
img = mul(img, 1 - 0.34 * sh)

# ---------- candies ----------
cache = {}
def piece(name):
    if name not in cache:
        f = f'cut/{name}-d.png' if KIND == 'fan' and os.path.exists(f'cut/{name}-d.png') else f'cut/{name}.png'
        cache[name] = Image.open(f).convert('RGBA')
    return cache[name]

def place(canvas, name, cx, cy, rot, scale, flip=False, shade=1.0, shadow=True, under=None):
    p = piece(name)
    if flip: p = p.transpose(Image.FLIP_LEFT_RIGHT)
    p = p.resize((max(1, int(p.width * scale)), max(1, int(p.height * scale))), Image.LANCZOS)
    p = p.rotate(rot, resample=Image.BICUBIC, expand=True)
    a = np.array(p).astype(np.float32)
    rgb, al = a[..., :3], a[..., 3] / 255
    if scale < 0.8:
        rgb = cv2.GaussianBlur(rgb, (0, 0), SOFT)
    gy, gx = np.mgrid[0:p.height, 0:p.width].astype(np.float32)
    rgb = rgb * (1.035 - 0.07 * ((gx / max(1, p.width - 1)) * 0.5 + (gy / max(1, p.height - 1)) * 0.5))[..., None]
    x0, y0 = int(cx - p.width / 2), int(cy - p.height / 2)
    x1, y1 = x0 + p.width, y0 + p.height
    cx0, cy0, cx1, cy1 = max(0, x0), max(0, y0), min(W, x1), min(H, y1)
    if cx0 >= cx1 or cy0 >= cy1: return canvas
    sl = (slice(cy0, cy1), slice(cx0, cx1)); ps = (slice(cy0 - y0, cy1 - y0), slice(cx0 - x0, cx1 - x0))
    if shadow:                                              # a soft shadow on what is underneath, light from top left
        s = np.zeros((H, W), np.float32); s[sl] = al[ps]
        soft = cv2.GaussianBlur(np.roll(np.roll(s, 9, 0), 7, 1), (0, 0), 7)
        tight = cv2.GaussianBlur(np.roll(np.roll(s, 3, 0), 2, 1), (0, 0), 2.2)
        canvas = mul(canvas, 1 - 0.30 * soft - 0.22 * tight * (1 - s))
    c = rgb[ps] * shade
    if under is not None: c = c * under[sl][..., None]
    m = al[ps][..., None]
    canvas[sl] = canvas[sl] * (1 - m) + c * m
    return canvas

SOFT = 0.55
random.seed(7)
if KIND == 'skull':
    S = {'bubblegum': 0.50, 'cola': 0.42, 'lemras': 0.48, 'licorice': 0.37, 'fr_green': 0.52, 'fr_red': 0.52}
    inside = [('licorice', 352, 806, -38, False), ('cola', 458, 742, -24, False), ('fr_red', 540, 700, 50, False),
              ('lemras', 610, 660, 18, True), ('fr_green', 690, 612, -20, False), ('bubblegum', 760, 566, 34, False),
              ('fr_red', 410, 790, 20, False)]
    # the pile right at the opening (drawn in this order, so pieces lie on each other), then the tail on the table
    spill = [('bubblegum', 505, 806, 12, False), ('cola', 612, 752, -28, True), ('fr_green', 455, 860, 30, False),
             ('lemras', 718, 700, 40, False), ('licorice', 580, 850, 18, True), ('fr_red', 676, 806, -44, False),
             ('bubblegum', 790, 760, -18, True), ('cola', 520, 922, -8, False), ('fr_green', 646, 878, 14, False),
             ('lemras', 742, 868, -30, False), ('fr_red', 846, 822, 64, False), ('licorice', 640, 962, -14, False),
             ('bubblegum', 805, 950, 22, False), ('fr_green', 560, 1010, -36, False), ('cola', 880, 1012, 30, True),
             ('lemras', 730, 1062, 8, True), ('fr_red', 935, 920, -20, False)]
elif KIND == 'fan':
    S = {'pomeA': 0.42, 'pomeB': 0.43, 'pomeC': 0.42, 'strawvan': 0.40, 'wheel': 0.95, 'bluefizz': 0.95, 'orfish': 0.95,
         'redfish': 0.95, 'redtube': 0.95, 'bear': 0.9}
    inside = [('pomeA', 352, 806, -38, False), ('strawvan', 458, 742, -24, False), ('redtube', 540, 700, 40, False),
              ('pomeB', 610, 660, 18, True), ('strawvan', 690, 612, -20, True), ('bear', 410, 790, 20, False)]
    spill = []                                              # filled in below, once the bag's shape is known
else:
    S = {'pome': 0.46, 'strawvan': 0.42}
    inside = [('strawvan', 356, 808, -30, False), ('pome', 462, 742, -18, False), ('strawvan', 552, 700, 12, True),
              ('pome', 616, 664, 22, False), ('strawvan', 700, 612, -24, False), ('pome', 765, 566, 28, True)]
    spill = [('pome', 500, 806, 10, False), ('strawvan', 612, 752, -26, True), ('pome', 450, 862, 32, True),
             ('strawvan', 716, 702, 36, False), ('pome', 584, 850, -14, False), ('strawvan', 680, 808, 48, False),
             ('pome', 792, 758, -20, True), ('strawvan', 522, 924, -10, False), ('pome', 652, 884, 18, False),
             ('strawvan', 748, 872, -32, False), ('pome', 846, 826, 60, False), ('strawvan', 640, 964, 24, True),
             ('pome', 806, 952, -12, False), ('strawvan', 560, 1012, -38, False), ('pome', 880, 1016, 28, True),
             ('strawvan', 730, 1064, 6, False), ('pome', 934, 918, -24, False)]


LM = ((P0[0] + P2[0]) / 2, (P0[1] + P2[1]) / 2)
_l = math.hypot(P2[0] - P0[0], P2[1] - P0[1]); LD = ((P2[0] - P0[0]) / _l, (P2[1] - P0[1]) / _l); LN = (-LD[1], LD[0])
if LN[1] < 0: LN = (-LN[0], -LN[1])
OVL = float(os.environ.get('OVL', '0.56'))
def make_pile(seed, n_lim, classics):
    """a heap that pours out of the opening: dense at the lip, thinning out down the table; pieces rest on each
    other (up to ~40 % overlap), never on the bag's top sheet; the limited candies most of it"""
    rr = random.Random(seed)
    lim = ['pomeA', 'pomeB', 'pomeC', 'strawvan', 'strawvan', 'strawvan']
    kinds = [lim[i % len(lim)] for i in range(n_lim)]; rr.shuffle(kinds)
    step = len(kinds) // (len(classics) + 1)
    for j, c in enumerate(classics): kinds.insert((j + 1) * step + j + rr.randint(-1, 1), c)
    placed = []
    ab = ndi.binary_dilation(above, iterations=4)
    for k in kinds:
        p = piece(k); r = 0.5 * max(p.width, p.height) * S[k] * 0.82
        best = None
        for t in range(6000):
            x, y = rr.uniform(300, 1000), rr.uniform(520, 1170)
            if above[int(y), int(x)]: continue
            d = dist[int(y), int(x)]
            if d < r * 0.55: continue
            u = ((x - LM[0]) * LD[0] + (y - LM[1]) * LD[1])           # along the opening, from its middle
            v = ((x - LM[0]) * LN[0] + (y - LM[1]) * LN[1])           # away from the bag, the way the sweets pour
            if rr.random() > math.exp(-(u / (230 + 0.35 * max(0, v))) ** 2 - ((v - 150) / 175) ** 2): continue
            if x + r > 1005 or y + r > 1175: continue
            if ab[int(max(0, y - r)):int(y + r), int(max(0, x - r)):int(x + r)].mean() > 0.02: continue
            ok_ = all(math.hypot(x - q[1], y - q[2]) > OVL * (r + q[5]) for q in placed)
            if ok_: best = (x, y); break
        if best is None: continue
        if k.startswith('pome'): rot = rr.uniform(-55, 55); fl = rr.random() < 0.5
        elif k == 'strawvan': rot = rr.uniform(-80, 80); fl = rr.random() < 0.5
        else: rot = rr.uniform(-70, 70); fl = rr.random() < 0.3
        placed.append((k, best[0], best[1], rot, fl, r))
    return [q[:5] for q in placed]
if KIND == 'fan':
    spill = make_pile(int(os.environ.get('SEED', '5')), int(os.environ.get('NLIM', '21')),
                      ['wheel', 'bluefizz', 'orfish', 'redfish'])

# inside the opening: dark depth, the bottom sheet, candies in the bag's own shadow
mouth = np.zeros((H, W), np.uint8)
cv2.fillPoly(mouth, [np.array([(P0[0] - 8, P0[1] - 22), (420, 690), (560, 606), (P1[0], P1[1] - 22), (760, 492), (P2[0] + 4, P2[1] - 20), (850, 520), (808, 606), (712, 690), (566, 786), (420, 858), (300, 872), (262, 836)], np.int32)], 1)
mouth = cv2.GaussianBlur(mouth.astype(np.float32), (0, 0), 6)
if KIND == 'fan':                                           # the dark opening stays inside the bag's outline
    mouth *= cv2.GaussianBlur(ndi.binary_dilation(bag, iterations=6).astype(np.float32), (0, 0), 4)
img = img * (1 - mouth[..., None]) + inside_col * mouth[..., None]
lowc = paper * 0.55                                          # the inside of the bag is in shade
img[low] = lowc[low]
shadow_in = np.clip(0.42 + 0.58 * (dist / 70), 0.42, 1.0) * (~above) + above * 0.4    # dark right under the edge
for n, x, y, rot, fl in inside:
    img = place(img, n, x, y, rot + random.uniform(-4, 4), S[n] * random.uniform(0.94, 1.06), fl, shade=0.92, under=shadow_in)

# the top sheet over them, and a thin dark line where its edge meets the candies
topf = cv2.GaussianBlur(top.astype(np.float32), (0, 0), 0.8)
rim = np.clip(1 - dist / 2.2, 0, 1) * above
paper_lit = paper * (1 + 0.16 * rim[..., None])
img = img * (1 - topf[..., None]) + paper_lit * topf[..., None]
edge = cv2.GaussianBlur((lip > 0).astype(np.float32), (0, 0), 2.0) * (~above)
img = mul(img, 1 - 0.35 * edge)

# the sticker on the bag
st = Image.open(f'art/sticker-{KIND}.png').convert('RGBA')
size, cxs, cys, tilt = (205, 486, 470, -9) if KIND == 'skull' else (205, 486, 470, 7)
st = st.resize((size, size), Image.LANCZOS).rotate(tilt, resample=Image.BICUBIC, expand=True)
sa = np.array(st).astype(np.float32); srgb, sal = sa[..., :3], sa[..., 3] / 255
x0, y0 = cxs - st.width // 2, cys - st.height // 2
sl = (slice(y0, y0 + st.height), slice(x0, x0 + st.width))
s = np.zeros((H, W), np.float32); s[sl] = sal
img = mul(img, 1 - 0.45 * cv2.GaussianBlur(np.roll(np.roll(s, 3, 0), 2, 1), (0, 0), 2.5) * (1 - s))
light = (0.86 + 0.2 * Ln[sl])[..., None]                     # the paper's light falls on the sticker too
img[sl] = img[sl] * (1 - sal[..., None]) + np.clip(srgb * light, 0, 255) * sal[..., None]

# the candies on the table, back to front
for n, x, y, rot, fl in (sorted(spill, key=lambda t: t[2] + 0.25 * t[1]) if KIND == 'fan' else spill[:9] + sorted(spill[9:], key=lambda t: t[2])):
    img = place(img, n, x, y, rot + random.uniform(-4, 4), S[n] * random.uniform(0.93, 1.07), fl)

# a little grain so the cut-outs and the paper sit in one photo
rng = np.random.default_rng(3)
img = img + rng.normal(0, 2.2, img.shape).astype(np.float32)
out = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
out.save(OUT.replace('.jpg', '-full.jpg'), quality=92)          # the whole photo, 1024 x 1280 like the Ready Mix photos
out = out.crop((0, 120, 1024, 1180))
out.save(OUT, quality=90)
print('saved', OUT, out.size)
