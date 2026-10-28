# the owner's 9:16 Halloween ad (red / cream) in the website's Halloween colours: night black, the Fika wordmark,
# product names and bats orange, the small lines bone; candies untouched (their red edge fringe un-mixed)
import sys, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi
im = np.array(Image.open(sys.argv[1]).convert('RGB')).astype(np.float32); H, W = im.shape[:2]
RED = np.array([139, 13, 19], np.float32); CREAM = np.array([251, 248, 199], np.float32)
ORANGE = np.array([255, 123, 28], np.float32); BONE = np.array([244, 236, 220], np.float32)
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
r = np.sqrt(((xx - W * .5) / (W * .9)) ** 2 + ((yy - 300) / 1400) ** 2)
t = np.clip(r, 0, 1)[..., None]
bg = np.array([36, 24, 56], np.float32) * (1 - t) + np.array([12, 8, 20], np.float32) * t
# the design's rows (measured): candies in 813-1041 and 1310-1827, the bottom-left bat (x < 260) is not a candy
cand_zone = (((yy > 800) & (yy < 1055)) | ((yy > 1298) & (yy < 1840))) & ~((xx < 260) & (yy > 1600) & (yy < 1720))
small = ((yy > 450) & (yy < 505)) | ((yy > 622) & (yy < 662)) | ((yy > 738) & (yy < 783)) | ((yy > 1110) & (yy < 1156)) | ((yy > 1225) & (yy < 1271))
d = CREAM - RED
a_txt = np.clip(((im - RED) * d).sum(axis=2) / (d * d).sum(), 0, 1)
txtcol = np.where(small[..., None], BONE, ORANGE)
out = im.copy()
txt = ~cand_zone
out[txt] = (bg * (1 - a_txt[..., None]) + txtcol * a_txt[..., None])[txt]
dist = np.linalg.norm(im - RED, axis=2)
cz = cand_zone
core = ndi.binary_erosion(dist > 70, iterations=2) & cz
filled = ndi.binary_erosion(ndi.binary_fill_holes(ndi.binary_closing(dist > 70, iterations=4)), iterations=3)
# the real holes (eye holes): background-coloured patches of some size inside a candy; tiny specks are candy
lab, n = ndi.label((dist < 30) & filled)
sizes = ndi.sum(np.ones_like(dist), lab, range(1, n + 1))
holes = np.isin(lab, [i + 1 for i, sz in enumerate(sizes) if sz > 250])
solid = core | (filled & ~ndi.binary_dilation(holes, iterations=3) & cz)   # inside a candy every pixel is candy
_, (iy, ix) = ndi.distance_transform_edt(~solid, return_indices=True)
fgn = im[iy, ix]; v = fgn - RED
alpha = np.clip(((im - RED) * v).sum(axis=2) / np.maximum((v * v).sum(axis=2), 1), 0, 1)
alpha = np.where(solid, 1.0, alpha)
alpha = np.where(ndi.distance_transform_edt(~solid) > 6, np.where(dist < 12, 0.0, alpha), alpha)
fg = np.where(solid[..., None], im, fgn)
out[cz] = (fg * alpha[..., None] + bg * (1 - alpha[..., None]))[cz]
sh = cv2.GaussianBlur((alpha * cz).astype(np.float32), (0, 0), 10)
sh = np.roll(np.roll(sh, 10, 0), 6, 1) * (1 - alpha) * cz
out = out * (1 - 0.45 * sh[..., None])
glow = cv2.GaussianBlur((txt & ~small & (a_txt > .5) & (yy < 1220)).astype(np.float32), (0, 0), 13)
out = out + (ORANGE * 0.35 * glow[..., None]) * (1 - a_txt[..., None] * txt[..., None])
Image.fromarray(np.clip(out, 0, 255).astype(np.uint8)).save(sys.argv[2])
print('saved', sys.argv[2])
