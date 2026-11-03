# Win-back email 2, "From our inbox": the frames of make-email-inbox.js -> one looping GIF (identical frames joined,
# one shared palette so the colours do not flicker). Usage: python3 make-email-inbox.py <out dir>
import json, sys
from PIL import Image
out = sys.argv[1]
meta = json.load(open(out + '/frames.json'))
ims = [Image.open(f).convert('RGB') for f in meta['frames']]
ims = ims[meta.get('finished_first', 0):] + ims[:meta.get('finished_first', 0)]
ms = int(round(1000 / meta['fps']))
# one palette for all frames, from a frame with everything in it (the last) plus the typing frame
pal_src = Image.new('RGB', (ims[0].width, ims[0].height * 2 + 40))
pal_src.paste(ims[0], (0, 0)); pal_src.paste(ims[len(ims) // 2], (0, ims[0].height))
# the brand colours always get a slot of their own (heart pink, Fika blue, typing green, the card's pink)
for i, c in enumerate(('#f04f95', '#004aad', '#1f9d55', '#fdeaf2', '#ffffff', '#111b21')):
    pal_src.paste(Image.new('RGB', (110, 40), c), (i * 110, ims[0].height * 2))
pal = pal_src.quantize(colors=64, method=Image.Quantize.MEDIANCUT)
frames, durs = [], []
for im in ims:
    q = im.quantize(palette=pal, dither=Image.Dither.NONE)
    if frames and q.tobytes() == frames[-1].tobytes():
        durs[-1] += ms
    else:
        frames.append(q); durs.append(ms)
# start on the first customer's finished chat: email apps that show only the first frame (Outlook on Windows) then
# show a whole conversation; the GIF then plays on from there and loops
frames[0].save(out + '/fika-email-owe-inbox.gif', save_all=True, append_images=frames[1:], duration=durs, loop=0, optimize=True, disposal=1)
print(len(frames), 'distinct frames', sum(durs) / 1000, 's')
