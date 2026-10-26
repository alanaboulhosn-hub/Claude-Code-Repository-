# where the bag paper is in the Sweet Mix photo: pale low-saturation pink (and the blue logo), not the candies
import numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi
im = np.array(Image.open('src/Sweet-Mix.jpeg').convert('RGB')).astype(np.float32)
hsv = cv2.cvtColor(im.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
H, S, V = hsv[..., 0] * 2, hsv[..., 1] / 255, hsv[..., 2] / 255
r, g, b = im[..., 0], im[..., 1], im[..., 2]
paper = (S < 0.2) & (V > 0.55) & (r >= g - 2) & (r - b > 4)          # pale pink, also in shade
bg = (S < 0.045) & (V > 0.8)                                          # near-white table
logo = (H > 200) & (H < 240) & (S > 0.35)
cand = ~(paper | bg | logo)
m = (paper | logo) & ~bg
m = ndi.binary_opening(m, iterations=2)
lab, n = ndi.label(m); sizes = ndi.sum(m, lab, range(1, n + 1)); big = lab == (np.argmax(sizes) + 1)
big = ndi.binary_closing(big, iterations=6); big = ndi.binary_fill_holes(big)
np.save('bagmask.npy', big)
vis = im.copy().astype(np.uint8)
vis[big] = (vis[big] * 0.4 + np.array([0, 200, 0]) * 0.6).astype(np.uint8)
vis[cand & ~big] = (vis[cand & ~big] * 0.5 + np.array([255, 0, 0]) * 0.5).astype(np.uint8)
out = Image.fromarray(vis)
from PIL import ImageDraw
d = ImageDraw.Draw(out)
for x in range(0, 1024, 100): d.line([(x, 0), (x, 1280)], fill=(0, 0, 0), width=1); d.text((x + 2, 2), str(x), fill=(0, 0, 0))
for y in range(0, 1280, 100): d.line([(0, y), (1024, y)], fill=(0, 0, 0), width=1); d.text((2, y + 2), str(y), fill=(0, 0, 0))
out.save('seg.jpg', quality=85)
print('bag px', big.sum(), 'bbox', np.argwhere(big).min(0), np.argwhere(big).max(0))
