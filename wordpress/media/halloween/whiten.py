# Ready Mix photos on pure white: neutral grey background and its soft shadows become white; the pink bag and
# the sweets (which all have colour) stay as they are
import sys, pathlib
import numpy as np
from PIL import Image, ImageFilter
src, dst = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
for f in sorted(src.glob('*.jpeg')):
    a = np.asarray(Image.open(f).convert('RGB')).astype(np.float32)
    mx, mn = a.max(2), a.min(2)
    chroma, light = mx - mn, a.mean(2)
    # how much a pixel looks like the grey backdrop: little colour, fairly light
    w = np.clip((14 - chroma) / 8, 0, 1) * np.clip((light - 110) / 70, 0, 1)
    # only the backdrop connected to the photo's edges, so white details on the bag or sweets are kept
    m = Image.fromarray((w * 255).astype(np.uint8))
    seed = Image.fromarray(((w > .55) * 255).astype(np.uint8))
    from scipy import ndimage
    lab, n = ndimage.label(np.asarray(seed) > 0)
    edge = set(np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]]))) - {0}
    keep = np.isin(lab, list(edge))
    zone = ndimage.binary_dilation(keep, iterations=6)
    w = np.where(zone, w, 0)
    w = np.asarray(Image.fromarray((w * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.5))).astype(np.float32) / 255
    out = a * (1 - w[..., None]) + 255 * w[..., None]
    Image.fromarray(out.clip(0, 255).astype(np.uint8)).save(dst / f.name, quality=90)
    print(f.name, 'whitened share: %.0f%%' % (w.mean() * 100))
