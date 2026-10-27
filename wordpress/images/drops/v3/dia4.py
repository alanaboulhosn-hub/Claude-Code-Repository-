# a Strawberry Vanilla Diamond seen like the candies in the picture (camera above and in front): the modelled top
# (dia3.py: exactly half strawberry, half vanilla along its long diagonal) turned to its place, foreshortened, then
# given its thickness: the sugared side shows below it, in its own flavour's colour, in shade (light from top left)
import numpy as np, cv2
from PIL import Image
def diamond(top, vanilla_dir, width, tilt=0.80, thick=0.11, seed=0):
    """top: RGBA template (vanilla half facing down); vanilla_dir: degrees (0 right, 90 down); width: px of the long axis"""
    rng = np.random.default_rng(seed)
    sc = width / top.width
    t = top.resize((max(1, int(top.width * sc)), max(1, int(top.height * sc))), Image.LANCZOS)
    t = t.rotate(90 - vanilla_dir, resample=Image.BICUBIC, expand=True)
    t = t.resize((t.width, max(1, int(t.height * tilt))), Image.LANCZOS)          # seen at an angle
    a = np.array(t).astype(np.float32)
    # the side takes each flavour's plain colour (the top's sugar would streak when pushed down)
    al_ = a[..., 3:4] / 255
    sm = cv2.GaussianBlur(a[..., :3] * al_, (0, 0), 5) / np.maximum(cv2.GaussianBlur(al_, (0, 0), 5), 1e-3)[..., None] if al_.ndim == 3 else None
    sm = cv2.GaussianBlur(a[..., :3] * al_, (0, 0), 5) / np.maximum(cv2.GaussianBlur(a[..., 3] / 255, (0, 0), 5), 1e-3)[..., None]
    a_side = a.copy(); a_side[..., :3] = sm
    th = max(2, int(width * thick))
    Hh, Ww = a.shape[0] + th + 2, a.shape[1]
    out = np.zeros((Hh, Ww, 4), np.float32)
    # the side: the outline pushed down step by step (furthest first), darker and duller than the top
    for k in range(th, 0, -1):
        lay = np.zeros_like(out); lay[k:k + a.shape[0]] = a_side
        f = 0.80 + 0.12 * (1 - k / th)                          # the side in soft shade, keeping its colour
        col = lay[..., :3] * f
        al = lay[..., 3:4] / 255
        out[..., :3] = out[..., :3] * (1 - al) + col * al
        out[..., 3:4] = np.maximum(out[..., 3:4], al)
    # sugar on the side
    side = out[..., 3] > 0.5
    n = int(side.sum() / 3)
    ys, xs = np.nonzero(side)
    if len(ys):
        idx = rng.integers(0, len(ys), n)
        sug = np.zeros(side.shape, np.float32)
        for y, x in zip(ys[idx], xs[idx]): cv2.ellipse(sug, (int(x), int(y)), (int(rng.integers(1, 3)), 1), float(rng.uniform(0, 180)), 0, 360, float(rng.uniform(0.2, 0.7)), -1)
        out[..., :3] = out[..., :3] * (1 - sug[..., None] * 0.5) + np.minimum(out[..., :3] * 1.45, 255) * sug[..., None] * 0.5
    # a thin bright edge where the top meets the side
    al0 = np.zeros(out.shape[:2], np.float32); al0[:a.shape[0]] = a[..., 3] / 255
    lay = np.zeros_like(out); lay[:a.shape[0]] = a
    out[..., :3] = out[..., :3] * (1 - al0[..., None]) + lay[..., :3] * al0[..., None]
    out[..., 3] = np.maximum(out[..., 3], al0)
    out[..., 3] *= 255
    return Image.fromarray(np.clip(out, 0, 255).astype(np.uint8), 'RGBA')
if __name__ == '__main__':
    top = Image.open('cut/svd1.png').convert('RGBA')
    ims = [diamond(top, a, 140, seed=i) for i, a in enumerate([90, 40, 140, -100, 180])]
    c = Image.new('RGBA', (sum(i.width for i in ims) + 60, max(i.height for i in ims)), (255, 255, 255, 255)); x = 0
    for i in ims: c.alpha_composite(i, (x, 0)); x += i.width + 15
    c.convert('RGB').resize((c.width * 2, c.height * 2)).save('dia4-look.jpg', quality=90)
