import sys
from PIL import Image, ImageDraw
f, x0, y0, x1, y1 = sys.argv[1], *map(int, sys.argv[2:6]); out = sys.argv[6]; st = int(sys.argv[7]) if len(sys.argv) > 7 else 50
i = Image.open(f).convert('RGB').crop((x0, y0, x1, y1)); d = ImageDraw.Draw(i)
for x in range((x0 // st + 1) * st, x1, st):
    d.line([(x - x0, 0), (x - x0, y1 - y0)], fill=(0, 120, 255) if x % (2 * st) == 0 else (150, 200, 255)); d.text((x - x0 + 2, 2), str(x), fill=(200, 0, 0))
for y in range((y0 // st + 1) * st, y1, st):
    d.line([(0, y - y0), (x1 - x0, y - y0)], fill=(0, 120, 255) if y % (2 * st) == 0 else (150, 200, 255)); d.text((2, y - y0 + 2), str(y), fill=(200, 0, 0))
i.save(out)
