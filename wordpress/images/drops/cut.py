# clean single-candy cut-outs from the shop's product photos: a rough outline traced by eye, snapped to the real
# edge with watershed; eye holes made see-through (dark pockets inside, or given ellipses)
import json, sys, numpy as np, cv2
from PIL import Image
from scipy import ndimage as ndi
P = {
 'bubblegum': ('BUBS-Bubblegum-Skull', [(415,385),(450,370),(500,365),(540,372),(580,395),(608,430),(617,480),(612,530),(600,560),(607,600),(598,640),(560,655),(520,650),(480,655),(450,640),(430,615),(417,580),(403,545),(398,490),(403,440)], 'dark', []),
 'cola': ('BUBS-Cola-Skull', [(450,293),(500,288),(545,290),(600,300),(645,322),(675,355),(690,400),(693,455),(680,500),(660,547),(650,593),(628,628),(575,622),(520,613),(480,625),(440,637),(400,620),(383,553),(380,513),(393,478),(372,440),(370,405),(378,355),(412,312)], 'dark', []),
 'lemras': ('BUBS-Lemon-Raspberry-Skull', [(437,323),(470,310),(520,307),(560,312),(600,330),(630,360),(652,400),(658,440),(655,470),(645,500),(648,540),(650,580),(637,605),(610,612),(570,602),(540,590),(505,600),(470,612),(445,610),(425,575),(410,525),(405,485),(392,450),(392,410),(403,365)], 'dark', []),
 'licorice': ('BUBS-Raspberry-Salty-Licorice-Skulls', [(435,325),(470,312),(530,310),(580,318),(630,340),(670,375),(690,420),(693,470),(685,520),(668,570),(660,620),(662,660),(655,700),(635,718),(600,722),(565,705),(540,688),(505,684),(478,698),(455,720),(425,726),(395,712),(372,670),(360,620),(345,560),(338,500),(342,445),(360,400),(385,365)], 'none', [((462,492),(36,33)),((577,500),(38,35))]),
 'fr_green': ('BUBS-Fruity-Lemon-Mix-Skulls', [(235,445),(250,415),(275,395),(305,388),(335,395),(355,412),(368,440),(378,470),(381,505),(370,528),(340,543),(300,550),(268,548),(250,525),(238,495),(232,470)], 'none', []),
 'fr_red': ('BUBS-Fruity-Lemon-Mix-Skulls', [(610,318),(640,300),(670,288),(700,285),(722,295),(740,318),(748,350),(752,390),(748,425),(735,450),(712,465),(680,470),(650,460),(628,440),(615,410),(604,375),(602,345)], 'none', []),
 'pome': ('BUBS-Wild-Berry-Pomegranate-Oval', [(360,420),(368,385),(395,355),(440,332),(490,322),(545,320),(600,328),(645,348),(672,378),(680,415),(672,452),(645,485),(600,510),(545,522),(490,522),(440,512),(395,492),(368,460)], 'none', []),
 'diamond': ('BUBS-Tutti-Frutti-Diamond', [(390,640),(400,620),(450,595),(520,560),(560,545),(585,548),(640,590),(700,640),(728,672),(728,695),(700,720),(640,750),(590,780),(560,788),(535,780),(480,740),(420,700),(390,672)], 'none', []),
}
def cut(key, inner=14, outer=16):
    src, poly, holes, eyes = P[key]
    im = np.array(Image.open(f'src/{src}.webp').convert('RGB'))
    h, w = im.shape[:2]
    pm = np.zeros((h, w), np.uint8); cv2.fillPoly(pm, [np.array(poly, np.int32)], 1)
    fg = ndi.binary_erosion(pm, iterations=inner); bgm = ~ndi.binary_dilation(pm, iterations=outer)
    white = (im.min(axis=2) > 236)                      # the photo's white table is always background
    markers = np.zeros((h, w), np.int32); markers[bgm | white] = 1; markers[fg & ~white] = 2
    sm = cv2.bilateralFilter(im, 7, 40, 7)
    cv2.watershed(sm, markers)
    m = markers == 2
    m = ndi.binary_opening(m, iterations=2); m = ndi.binary_closing(m, iterations=3)
    lab, n = ndi.label(m); m = lab == (np.argmax(ndi.sum(m, lab, range(1, n + 1))) + 1); m = ndi.binary_fill_holes(m)
    hole = np.zeros_like(m)
    if holes == 'dark':
        v = cv2.cvtColor(im, cv2.COLOR_RGB2HSV)[..., 2]
        d = (v < np.percentile(v[m], 9)) & ndi.binary_erosion(m, iterations=10)
        d = ndi.binary_opening(d, iterations=2)
        lab, n = ndi.label(d)
        for i in range(1, n + 1):
            a = (lab == i).sum()
            if 120 < a < 6000: hole |= ndi.binary_dilation(lab == i, iterations=2)
    for (cx, cy), (rx, ry) in eyes:
        e = np.zeros((h, w), np.uint8); cv2.ellipse(e, (cx, cy), (rx, ry), 0, 0, 360, 1, -1); hole |= e.astype(bool)
    a = (m & ~hole).astype(np.float32)
    a = cv2.GaussianBlur(a, (0, 0), 1.1)
    a = np.clip((a - 0.5) * 1.8 + 0.5, 0, 1)                   # crisp but anti-aliased edge
    ys, xs = np.where(a > 0.02); y0, y1, x0, x1 = ys.min() - 4, ys.max() + 5, xs.min() - 4, xs.max() + 5
    rgba = np.dstack([im, (a * 255).astype(np.uint8)])[y0:y1, x0:x1]
    Image.fromarray(rgba, 'RGBA').save(f'cut/{key}.png')
    return rgba.shape
for k in (sys.argv[1:] or P):
    print(k, cut(k))
