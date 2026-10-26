# cut-outs from photos on a white table keep a pale rim: pull the candy's own colour out to the edge, trim 1 px
import sys, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi
for k in sys.argv[1:]:
    im = np.array(Image.open(f'cut/{k}.png').convert('RGBA'))
    rgb, a = im[..., :3].copy(), im[..., 3].astype(np.float32) / 255
    solid = ndi.binary_erosion(a > 0.98, iterations=3)
    band = (a > 0.01) & ~solid
    rgb = cv2.inpaint(rgb, band.astype(np.uint8), 4, cv2.INPAINT_TELEA)
    # keep the real edge shading a little (the candy's rim is darker), only the white fringe goes
    keep = (a > 0.01) & ~band
    out = im[..., :3].copy(); out[band] = rgb[band]
    a2 = cv2.erode(a, np.ones((3, 3), np.uint8), iterations=1)
    a2 = cv2.GaussianBlur(a2, (0, 0), 0.7)
    Image.fromarray(np.dstack([out, np.clip(a2 * 255, 0, 255).astype(np.uint8)]), 'RGBA').save(f'cut/{k}-d.png')
    print(k, 'ok')
