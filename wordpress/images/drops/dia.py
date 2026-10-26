# the BUBS Strawberry Vanilla Diamond stand-in, from the real Tutti Frutti Diamond photo: the green layer becomes
# strawberry pink and the yellow layer vanilla cream; light, shade and sugar come from the photo's own brightness
import numpy as np, cv2
from PIL import Image
im = np.array(Image.open('cut/diamond.png')).astype(np.float32)
rgb, a = im[..., :3], im[..., 3]
hsv = cv2.cvtColor(rgb.astype(np.uint8), cv2.COLOR_RGB2HSV).astype(np.float32)
H = hsv[..., 0] * 2
m = a > 20
g = ((H >= 64) & (H <= 150) & m).astype(np.float32)
y = ((H >= 25) & (H < 64) & m).astype(np.float32)
g, y = cv2.GaussianBlur(g, (0, 0), 7), cv2.GaussianBlur(y, (0, 0), 7)
t = g / (g + y + 1e-4)                                  # 1 = strawberry layer, 0 = vanilla layer
t = np.clip((t - 0.5) * 1.6 + 0.5, 0, 1)
L = (0.3 * rgb[..., 0] + 0.59 * rgb[..., 1] + 0.11 * rgb[..., 2]) / 255
def norm(w):
    v = L[(w > 0.6) & m]; lo, hi = np.percentile(v, 3), np.percentile(v, 99.5)
    return np.clip((L - lo) / (hi - lo), 0, 1)
def ramp(x, c0, c1, c2):                                # dark -> mid -> light
    x = x[..., None]; c0, c1, c2 = map(np.array, (c0, c1, c2))
    return np.where(x < 0.6, c0 + (c1 - c0) * (x / 0.6), c1 + (c2 - c1) * ((x - 0.6) / 0.4))
straw = ramp(norm(t), (148, 26, 52), (226, 84, 108), (255, 212, 218))
vanil = ramp(norm(1 - t), (176, 146, 104), (241, 222, 186), (255, 252, 240))
out = straw * t[..., None] + vanil * (1 - t[..., None])
Image.fromarray(np.dstack([np.clip(out, 0, 255), a]).astype(np.uint8), 'RGBA').save('cut/strawvan.png')
