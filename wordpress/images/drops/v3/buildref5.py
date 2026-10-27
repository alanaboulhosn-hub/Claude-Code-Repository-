# the Fan Favourites picture from the owner's reference (27 Oct): their bag (paper, logo, sticker, angle) kept as is,
# the pile rebuilt from clean pieces in the same arrangement: real BUBS Wild Berry Pomegranate Ovals, Strawberry
# Vanilla Diamonds from the real BUBS diamond photo split exactly half and half (dia2b.py -> cut/sv3.png), and the
# shop's own licorice wheel, blue fizzy and fish; a shaded back layer inside the opening; candies tuck under the bag's
# edge, soft shadows, light from the top left.  python3 buildref.py ref.webp out.jpg [seed]
import sys, math, random, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi
from dia4 import diamond as extrude
exec(open('refpieces.py').read())
REF, OUT = sys.argv[1], sys.argv[2]
SEED = int(sys.argv[3]) if len(sys.argv) > 3 else 4
src = np.array(Image.open(REF).convert('RGB')).astype(np.float32)
H, W = src.shape[:2]
hsv = cv2.cvtColor(src.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
Hh, Ss, Vv = hsv[..., 0] * 2, hsv[..., 1] / 255, hsv[..., 2] / 255
bag = (Hh > 235) & (Hh < 305) & (Ss > 0.16) & (Vv < 0.75)
bag = ndi.binary_opening(ndi.binary_closing(bag, iterations=3), iterations=2)
lab, n = ndi.label(bag); bag = lab == (np.argmax(ndi.sum(bag, lab, range(1, n + 1))) + 1)
bag = ndi.binary_fill_holes(bag)
# the sticker sits on the bag (cream): part of the bag
yy, xx = np.mgrid[0:H, 0:W]
# the opening's edge as traced on the picture (it curves), and everything below it
LIP = [(296, 948), (450, 840), (580, 745), (700, 690), (860, 622), (962, 576)]
below = np.zeros((H, W), np.uint8)
cv2.fillPoly(below, [np.array(LIP + [(W, 560), (W, H), (0, H), (0, 948)], np.int32)], 1)
below = below.astype(bool)
stk = ((xx - 560) ** 2 + (yy - 520) ** 2) < 122 ** 2
bag |= stk
inside = bag & (Vv < 0.17) & ~stk & below   # the dark inside of the opening, not paper
inside = ndi.binary_opening(inside, iterations=2)
bag &= ~inside
# only right inside the opening: within reach of the paper's edge
inside &= ndi.distance_transform_edt(~bag) < 45
lab_, n_ = ndi.label(inside)
if n_: inside = np.isin(lab_, [i for i in range(1, n_ + 1) if (lab_ == i).sum() > 400])
table = (Ss < 0.08) & (Vv > 0.86)
# the old pile out: everything that is not bag and not table, below the bag's opening, becomes table again
pile = ~bag & ~inside & ~table & (yy > 560)
pile = ndi.binary_dilation(ndi.binary_closing(pile, iterations=4), iterations=6) & ~bag & ~inside
# its soft shadow ring on the table goes too (the new pieces cast their own)
ring = ndi.binary_dilation(pile, iterations=45) & ~bag & ~inside & (Vv < 0.985)
pile |= ring
tb = (table & ~ndi.binary_dilation(pile, iterations=25)).astype(np.float32)   # the bare table far from the old pile
sm8 = cv2.resize(src, (W // 8, H // 8), interpolation=cv2.INTER_AREA)
hole8 = (cv2.resize(tb, (W // 8, H // 8), interpolation=cv2.INTER_AREA) < 0.95).astype(np.uint8)
fill8 = cv2.inpaint(np.clip(sm8, 0, 255).astype(np.uint8), hole8, 12, cv2.INPAINT_TELEA)
clean = cv2.GaussianBlur(cv2.resize(fill8, (W, H), interpolation=cv2.INTER_CUBIC).astype(np.float32), (0, 0), 20)
clean = np.where(tb[..., None] > 0.5, src, clean)
pf = np.clip(cv2.GaussianBlur(pile.astype(np.float32), (0, 0), 14) * 1.6, 0, 1)[..., None] * (~bag & ~inside)[..., None]
img = src * (1 - pf) + clean * pf
bagf = cv2.GaussianBlur(bag.astype(np.float32), (0, 0), 0.7)[..., None]
dbag = ndi.distance_transform_edt(~bag)

mouth = (~bag) & below & (dbag < 26) & (xx > 320) & (xx < 935)
mouth = ndi.binary_closing(mouth, iterations=3)
mf = cv2.GaussianBlur(mouth.astype(np.float32), (0, 0), 6)[..., None]
img = img * (1 - mf) + np.array([22, 10, 26], np.float32) * mf
inside = inside | mouth
cover = np.zeros((H, W), np.float32)
def put(canvas, p, cx, cy, dim=1.0, shadow=1.0):
    global cover
    a = np.array(p).astype(np.float32); rgb, al = a[..., :3] * dim, a[..., 3] / 255
    x0, y0 = int(round(cx - p.width / 2)), int(round(cy - p.height / 2))
    cx0, cy0, cx1, cy1 = max(0, x0), max(0, y0), min(W, x0 + p.width), min(H, y0 + p.height)
    if cx0 >= cx1 or cy0 >= cy1: return canvas
    sl = (slice(cy0, cy1), slice(cx0, cx1)); ps = (slice(cy0 - y0, cy1 - y0), slice(cx0 - x0, cx1 - x0))
    s = np.zeros((H, W), np.float32); s[sl] = al[ps]
    soft = cv2.GaussianBlur(np.roll(np.roll(s, 10, 0), 7, 1), (0, 0), 8)
    tight = cv2.GaussianBlur(np.roll(np.roll(s, 3, 0), 2, 1), (0, 0), 2.3)
    canvas = canvas * (1 - (0.30 * soft + 0.30 * tight * (1 - s)) * shadow)[..., None]
    m = al[ps][..., None]
    canvas[sl] = canvas[sl] * (1 - m) + rgb[ps] * m
    cover = np.maximum(cover, s)
    return canvas

def major_angle(p):
    a = np.array(p)[..., 3] > 128; ys, xs = np.nonzero(a); c = np.cov(np.vstack([xs - xs.mean(), ys - ys.mean()]))
    w_, v_ = np.linalg.eigh(c); vx, vy = v_[:, 1]; th = math.degrees(math.atan2(vy, vx))
    return th - 180 if th > 90 else th + 180 if th < -90 else th
def level(p):
    p = p.rotate(major_angle(p), resample=Image.BICUBIC, expand=True); return p.crop(p.getbbox())
POME = [level(Image.open(f'cut/{n}-d.png').convert('RGBA')) for n in ('pomeA', 'pomeB', 'pomeC')]
SV = Image.open('cut/sv4.png').convert('RGBA')
D5 = [Image.open(f'cut/d5-{i}.png').convert('RGBA') for i in (1, 2, 3, 4)]
_d5i = [0]
# the template's long diagonal and the way its vanilla half faces
_a = np.array(SV)[..., 3] > 128; _ys, _xs = np.nonzero(_a)
_L = np.array([_xs.min(), _ys[_xs == _xs.min()].mean()]); _R = np.array([_xs.max(), _ys[_xs == _xs.max()].mean()])
TH0 = math.degrees(math.atan2(_R[1] - _L[1], _R[0] - _L[0]))
LONG0 = float(np.hypot(*(_R - _L)))
def dia(vdir, size, flip=False):
    """the real-look BUBS Strawberry Vanilla Diamond (dia5.py), turned so its vanilla half faces vdir, with its side"""
    p = D5[_d5i[0] % len(D5)]; _d5i[0] += 1
    return extrude(p, vdir - 90, size * 1.38, tilt=0.88, thick=0.10, seed=int(size * 7) % 97)
def oval(i, w, h, tilt):
    return POME[i % 3].resize((int(w), int(h)), Image.LANCZOS).rotate(tilt, resample=Image.BICUBIC, expand=True)
WHEEL = Image.open('cut/wheel-d.png').convert('RGBA'); BLUE = Image.open('cut/bluefizz-d.png').convert('RGBA')
FISH = Image.open('cut/orfish-d.png').convert('RGBA')

rr = random.Random(SEED)
# 1. the back layer, inside the opening and between the pieces (in the shade): it fills every gap with candy
hull = np.zeros((H, W), np.uint8)
pts = [(c[0], c[1]) for c in OV.values()] + [(c[0], c[1]) for c in DI.values()] + [(488, 925), (690, 1000), (375, 1030), (700, 760)]
cv2.fillPoly(hull, [cv2.convexHull(np.array(pts, np.int32))], 1)
hull = cv2.erode(hull, np.ones((61, 61), np.uint8)).astype(bool) & ~bag & ~ndi.binary_dilation(inside, iterations=20)
mys, mxs = np.nonzero(ndi.binary_erosion(mouth, iterations=6))
for i in range(40):
    if False:
        j = rr.randrange(len(mys)); x, y = float(mxs[j]), float(mys[j])
    else:
        for _ in range(200):
            x, y = rr.uniform(300, 1060), rr.uniform(560, 1220)
            if hull[int(y), int(x)]: break
        else: continue
    k = rr.random()
    if k < 0.45: p = oval(i, 150, 92, rr.uniform(-40, 40))
    elif k < 0.9: p = dia(rr.uniform(0, 360), rr.uniform(130, 165), rr.random() < 0.5)
    else: p = WHEEL.resize((140, 130)).rotate(rr.uniform(0, 360), resample=Image.BICUBIC, expand=True)
    img = put(img, p, x, y, dim=np.array([0.70, 0.62, 0.66]), shadow=0.5)
# 1b. candies right at the opening, their tops under the paper's edge (the bag goes back over them at the end)
EXTRA = [Image.open(f'cut/{n}-d.png').convert('RGBA') for n in ('redtube', 'redfish', 'bear')]
lp = []
for (x0, y0), (x1, y1) in zip(LIP[:-1], LIP[1:]):
    seg = math.hypot(x1 - x0, y1 - y0); nx, ny = -(y1 - y0) / seg, (x1 - x0) / seg
    if ny < 0: nx, ny = -nx, -ny
    for t in np.arange(0.0, 1.0, 62.0 / seg):
        lp.append((x0 + (x1 - x0) * t + nx * rr.uniform(18, 40), y0 + (y1 - y0) * t + ny * rr.uniform(18, 40)))
for i, (x, y) in enumerate(lp):
    if x < 330 or x > 945: continue
    k = rr.random()
    if k < 0.38: p = oval(i, 148, 90, rr.uniform(-35, 35))
    elif k < 0.76: p = dia(rr.uniform(0, 360), rr.uniform(125, 150), rr.random() < 0.5)
    else:
        e = EXTRA[i % 3]; p = e.rotate(rr.uniform(-60, 60), resample=Image.BICUBIC, expand=True)
    img = put(img, p, x, y, dim=0.78, shadow=0.7)
# 2. the pile as in the reference, back to front
ORDER = ['O1', 'T1', 'O3', 'O2', 'O4', 'T5', 'WHEEL', 'T2', 'T3', 'T4', 'O5', 'T6', 'BLUE', 'O6', 'T7', 'T9', 'T8', 'FISH', 'T10', 'T11', 'O7']
io = 0
for k in ORDER:
    if k == 'WHEEL':
        img = put(img, WHEEL.resize((150, 178), Image.LANCZOS).rotate(6, resample=Image.BICUBIC, expand=True), 488, 925)
    elif k == 'BLUE':
        img = put(img, BLUE.resize((240, 104), Image.LANCZOS).rotate(-38, resample=Image.BICUBIC, expand=True), 688, 990)
    elif k == 'FISH':
        img = put(img, FISH.resize((112, 285), Image.LANCZOS).rotate(-6, resample=Image.BICUBIC, expand=True), 372, 1025)
    elif k in OV:
        cx, cy, w, h, tl = OV[k]
        img = put(img, oval(io, w * 1.04, h * 1.10, tl), cx, cy); io += 1
    else:
        cx, cy, s, a = DI[k]
        img = put(img, dia(a, s * 1.07, rr.random() < 0.5), cx, cy + 4)
# 3. the bag over everything that went under its edge, and the edge's shadow on the candies just below it
img = img * (1 - bagf) + src * bagf
lip = np.exp(-dbag / 16.0) * (cover > 0.3)
img = img * (1 - 0.30 * lip)[..., None]
# the table never darker than a soft real shadow where no candy lies on it
free = (cover < 0.3) & ~bag & ~inside
floor_ = clean * 0.72
img = np.where(free[..., None], np.maximum(img, floor_), img)
rng = np.random.default_rng(3)
img = img + rng.normal(0, 1.3, img.shape).astype(np.float32)
Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).save(OUT, quality=94)
print('saved', OUT)
