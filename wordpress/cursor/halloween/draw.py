"""Jack-o'-lantern cursor for the Fika Halloween look: normal (40 / 80 px) and lit + tilted over links (44 / 88 px).
The stem tip, top left, is the click point (3, 2)."""
from PIL import Image, ImageDraw, ImageFilter
def pumpkin(size, lit, tilt):
    S = 8; W = size * S
    im = Image.new('RGBA', (W, W), (0, 0, 0, 0))
    u = W / 100 * (.82 if tilt else 1)
    body = [(16, 22, 62, 86), (30, 20, 84, 88), (44, 22, 92, 86)]
    stem = [(6*u, 4*u), (16*u, 6*u), (36*u, 26*u), (30*u, 32*u), (12*u, 14*u)]
    shape = Image.new('L', (W, W), 0); sd = ImageDraw.Draw(shape)
    for b in body: sd.ellipse([b[0]*u, b[1]*u, b[2]*u, b[3]*u], fill=255)
    sd.polygon(stem, fill=255)
    im.paste((255, 255, 255, 255), (0, 0), shape.filter(ImageFilter.MaxFilter(int(7*u) | 1)))  # white sticker outline
    d = ImageDraw.Draw(im)
    for i, b in enumerate(body):
        d.ellipse([b[0]*u, b[1]*u, b[2]*u, b[3]*u], fill=[(232, 98, 12, 255), (255, 128, 26, 255), (240, 108, 16, 255)][i])
    d.arc([30*u, 22*u, 70*u, 86*u], 100, 260, fill=(190, 70, 0, 255), width=int(1.6*u))
    d.arc([46*u, 22*u, 86*u, 86*u], -80, 80, fill=(190, 70, 0, 255), width=int(1.6*u))
    d.polygon(stem, fill=(78, 120, 34, 255))
    f = (255, 214, 80, 255) if lit else (40, 18, 6, 255)
    d.polygon([(32*u, 46*u), (44*u, 40*u), (44*u, 54*u)], fill=f)
    d.polygon([(76*u, 46*u), (64*u, 40*u), (64*u, 54*u)], fill=f)
    d.polygon([(26*u, 62*u), (82*u, 62*u), (72*u, 76*u), (62*u, 70*u), (54*u, 78*u), (46*u, 70*u), (36*u, 76*u)], fill=f)
    if lit:
        glow = Image.new('RGBA', (W, W), (0, 0, 0, 0)); ImageDraw.Draw(glow).ellipse([30*u, 36*u, 80*u, 80*u], fill=(255, 200, 60, 90))
        im = Image.alpha_composite(im, glow.filter(ImageFilter.GaussianBlur(4*u)))
    if tilt: im = im.rotate(14, resample=Image.BICUBIC, center=(6*u, 4*u))
    return im.resize((size, size), Image.LANCZOS)
if __name__ == '__main__':
    for name, sz, lit, tilt in [('pumpkin-40', 40, False, False), ('pumpkin-80', 80, False, False), ('pumpkin-lit-44', 44, True, True), ('pumpkin-lit-88', 88, True, True)]:
        pumpkin(sz, lit, tilt).save(name + '.png', optimize=True)
