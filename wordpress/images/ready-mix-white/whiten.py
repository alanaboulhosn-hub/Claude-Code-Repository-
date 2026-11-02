import sys, numpy as np
from PIL import Image
from scipy import ndimage as ndi
import cv2
src, dst = sys.argv[1], sys.argv[2]
im = np.array(Image.open(src).convert('RGB')).astype(np.float32)
chroma = im.max(2) - im.min(2)
L = im.mean(2)
def ramp(x, a, b):  # 0 at a, 1 at b
    return np.clip((x - a) / (b - a), 0, 1)
# clear studio background, connected to the edge of the photo
cand = ndi.binary_opening((chroma < 9) & (L > 175), iterations=1)
lab, n = ndi.label(cand)
border = np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]]))
bg = np.isin(lab, border[border > 0])
# the grey gradient of the backdrop, measured on the clear background only
wb = bg.astype(np.float32)
local = cv2.GaussianBlur(L * wb, (0, 0), 60) / (cv2.GaussianBlur(wb, (0, 0), 60) + 1e-4)
lift = np.clip(255 - local, 0, 80)
# how much a pixel is backdrop: colourless (the bag is pink, candies coloured) and near the clear background
near = cv2.GaussianBlur(ndi.binary_dilation(bg, iterations=40).astype(np.float32), (0, 0), 12)
w = ramp(-chroma, -16, -7) * ramp(L, 120, 175) * near
w = cv2.GaussianBlur(w, (0, 0), 1.2)
out = np.clip(im + (lift * w)[..., None], 0, 255)
# near-white backdrop -> pure white (like the Fan Favourites photo), easing in so shadows keep a soft edge
oc = out.max(2) - out.min(2); oL = out.mean(2)
t = ramp(oL, 238, 249) * ramp(-oc, -10, -5) * near
out = out + (255 - out) * t[..., None]
Image.fromarray(out.round().astype(np.uint8)).save(dst, quality=92)
