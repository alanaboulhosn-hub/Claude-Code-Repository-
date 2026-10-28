# the owner's Halloween ad (blue/pink) in the website's Halloween colours: background night black, the Fika
# wordmark, product names and bats orange, the small lines bone; candies untouched (only their blue edge fringe is
# un-mixed onto the new background)
import sys, numpy as np, cv2
from PIL import Image
im = np.array(Image.open(sys.argv[1]).convert('RGB')).astype(np.float32); H, W = im.shape[:2]
BLUE = np.array([0, 74, 172], np.float32); PINK = np.array([254, 234, 243], np.float32)
ORANGE = np.array([255, 123, 28], np.float32); BONE = np.array([244, 236, 220], np.float32)
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
# background: the website's night with a soft glow behind the title
r = np.sqrt(((xx - W * .5) / (W * .8)) ** 2 + ((yy - H * .25) / (H * .55)) ** 2)
t = np.clip(r, 0, 1)[..., None]
bg = np.array([36, 24, 56], np.float32) * (1 - t) + np.array([12, 8, 20], np.float32) * t
# text and bats: pixels on the line blue -> pink; a = how much pink
d = PINK - BLUE
a_txt = np.clip(((im - BLUE) * d).sum(axis=2) / (d * d).sum(), 0, 1)
resid = np.linalg.norm(im - (BLUE + a_txt[..., None] * d), axis=2)       # off that line = candy colour
on_line = resid < 22
cand_zone = (yy > 1060) & ~((xx < 260) & (yy > 1580))                   # candies live below the text (bottom-left bat excepted)
cand_zone &= ~((yy < 0))
# text colour by part: big names / wordmark / bats orange, small lines bone
orange_part = (yy < 570) | ((yy > 870) & (yy < 950)) | (yy > 1580) | ((yy < 400) & (xx > 1350)) | (yy < 160)
small = ((yy > 570) & (yy < 660)) | ((yy > 800) & (yy < 870)) | ((yy > 950) & (yy < 1020))
txtcol = np.where(small[..., None], BONE, ORANGE)
out = im.copy()
txt = ~cand_zone                                              # above the candies: only words, bats and background
out[txt] = (bg * (1 - a_txt[..., None]) + txtcol * a_txt[..., None])[txt]
# candy area: each edge pixel is the nearest solid candy colour mixed with blue; un-mix it onto the night
from scipy import ndimage as ndi
dist = np.linalg.norm(im - BLUE, axis=2)
solid = ndi.binary_erosion(dist > 70, iterations=2) & cz if (cz := cand_zone).any() else dist > 70
_, (iy, ix) = ndi.distance_transform_edt(~solid, return_indices=True)
fgn = im[iy, ix]                                             # nearest solid candy colour
v = fgn - BLUE
alpha = np.clip(((im - BLUE) * v).sum(axis=2) / np.maximum((v * v).sum(axis=2), 1), 0, 1)
alpha = np.where(solid, 1.0, alpha)
alpha = np.where(ndi.distance_transform_edt(~solid) > 6, np.where(dist < 12, 0.0, alpha), alpha)
fg = np.where(solid[..., None], im, fgn)
out[cz] = (fg * alpha[..., None] + bg * (1 - alpha[..., None]))[cz]
# bats in the candy zone (bottom-left one is excluded above) and any leftover pure background
pure = dist < 6
out[pure] = bg[pure]
# a soft shadow under the candies, so they sit on the night
sh = cv2.GaussianBlur((alpha * cz).astype(np.float32), (0, 0), 14)
sh = np.roll(np.roll(sh, 14, 0), 8, 1) * (1 - alpha) * cz
out = out * (1 - 0.45 * sh[..., None])
# the glow on the orange words, like the website
glow = cv2.GaussianBlur((txt & ~small & (a_txt > .5) & (yy < 950)).astype(np.float32), (0, 0), 18)
out = out + (ORANGE * 0.35 * glow[..., None]) * (1 - a_txt[..., None] * txt[..., None])
Image.fromarray(np.clip(out, 0, 255).astype(np.uint8)).save(sys.argv[2], quality=95)
print('saved', sys.argv[2])
