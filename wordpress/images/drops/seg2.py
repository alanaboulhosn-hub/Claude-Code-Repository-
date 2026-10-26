# the bag paper in the Sweet & Sour Mix photo (its candies stay as they are)
import numpy as np, cv2
from PIL import Image, ImageDraw
from scipy import ndimage as ndi
im = np.array(Image.open('src/Sweet_Sour-Mix.jpeg').convert('RGB')).astype(np.float32)
hsv = cv2.cvtColor(im.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
H, S, V = hsv[..., 0] * 2, hsv[..., 1] / 255, hsv[..., 2] / 255
r, g, b = im[..., 0], im[..., 1], im[..., 2]
paper = (S < 0.2) & (V > 0.5) & (r >= g - 2) & (r - b > 3)
bg = (S < 0.04) & (V > 0.8)
logo = (H > 195) & (H < 245) & (S > 0.3)
h, w = S.shape
poly = np.zeros((h, w), np.uint8)
# the bag's outline, generous at the opening; the candies there are told apart by colour
cv2.fillPoly(poly, [np.array([(0, 0), (536, 0), (770, 420), (772, 470), (560, 610), (330, 730), (205, 760), (0, 350)], np.int32)], 1)
m = (paper | logo) & ~bg & poly.astype(bool)
m = ndi.binary_opening(m, iterations=2)
lab, n = ndi.label(m); m = lab == (np.argmax(ndi.sum(m, lab, range(1, n + 1))) + 1)
m = ndi.binary_closing(m, iterations=5); m = ndi.binary_fill_holes(m) & poly.astype(bool)
np.save('bagmask2.npy', m)
vis = im.copy().astype(np.uint8); vis[m] = (vis[m] * 0.45 + np.array([0, 200, 0]) * 0.55).astype(np.uint8)
out = Image.fromarray(vis); d = ImageDraw.Draw(out)
for x in range(0, 1024, 50): d.line([(x, 0), (x, 1280)], fill=(255, 0, 0) if x % 100 == 0 else (150, 150, 150)); 
for y in range(0, 1280, 50): d.line([(0, y), (1024, y)], fill=(255, 0, 0) if y % 100 == 0 else (150, 150, 150))
for x in range(0, 1024, 100): d.text((x + 2, 2), str(x), fill=(255, 0, 0))
for y in range(0, 1280, 100): d.text((2, y + 2), str(y), fill=(255, 0, 0))
out.crop((0, 250, 900, 950)).save('seg2.jpg', quality=85)
print('bag px', m.sum())
