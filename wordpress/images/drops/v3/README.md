Fan Favourites picture, version 3 (27 Oct 2026, preview only, not live). Owner sent owner-reference.webp ("take this
as a reference, enhance the candy shapes, make the diamonds exactly half vanilla half strawberry").
- buildref.py: the reference's bag (paper, logo, sticker, angle) kept; its pile cleared and rebuilt in the same
  arrangement (refpieces.py: each oval's centre / size / tilt, each diamond's centre / size / vanilla side, traced on
  the picture): real BUBS Wild Berry Pomegranate Ovals, the shop's own wheel, blue fizzy and fish, and Strawberry
  Vanilla Diamonds from the real BUBS diamond photo (dia2b.py: clean rounded outline, split along the long
  diagonal, line moved until both halves hold exactly the same area; sugar coat -> sv4.png; dia4.py adds the
  sugared side). Candies tuck under the bag's edge; soft shadows, light from the top left.
- python3 buildref.py owner-reference.webp final-a.jpg 4
Tried and dropped: re-colouring the reference's own wedges in place (painted-looking bands), modelled diamonds
pasted over the reference (flat tiles, dark holes).
- 27 Oct, real look (from the BUBS bag and shop photos, found with Firecrawl; those photos are not kept here):
  dia5.py models the real candy: a plump, slanted rounded diamond, BUBS raised on top (letters.html: Titan One as a
  stand-in for the BUBS logo -> bubs.png), creamy vanilla and pastel strawberry split straight across into exactly
  equal halves, fine sour sugar (d5-1..4.png). buildref5.py = buildref.py with these diamonds:
  python3 buildref5.py owner-reference.webp final-b.jpg 4
- 27 Oct (owner): sticker name FAN FAVOURITES, and no grey pieces behind the front layer.
  - stickerswap.py: the sticker re-rendered (stickers2.html: fanref = the owner's wording, to fit; fan2 = FAN
    FAVOURITES), fitted by pixel matching (stk-fit.txt: match 0.907, 243 px, 13 deg, centre 560.5, 518.5); only the
    top arc (the name) replaced, heart, rings and bottom line kept, colour matched to the sticker's paper.
    python3 stickerswap.py owner-reference.webp ref-fan.png
  - Back layer and the pieces at the opening drawn in their own colours with a gentle shade (0.88 / 0.90) instead of
    a dark tint (which turned vanilla grey); diamond sides keep their colour (dia4.py).
    python3 buildref5.py ref-fan.png final-c.jpg 4
  - Note: the bottom line on the owner's sticker reads "NIᖷ.I" (garbled), not "NR. 1"; left as is for now.
- 27 Oct (owner): more of the classic candies, naturally mixed, the new candies still the most prominent.
  buildref6.py: ten classic pieces from the shop's own mix photos (cut5.py: peach, a second blue fizzy, red fizzy
  and the rainbow wheel from the Sour Mix photo, with the licorice wheel, blue fizzy, two fish, red twist and green
  gummy); about a third of the pieces inside the opening and behind; nine in the middle layer, showing in the gaps
  under the front pieces; four at the heap's edge. The front layer stays Strawberry Vanilla Diamonds and
  Pomegranate Ovals.  python3 buildref6.py ref-fan.png final-d.jpg 4
