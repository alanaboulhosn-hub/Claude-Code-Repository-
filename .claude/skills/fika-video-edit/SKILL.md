---
name: fika-video-edit
description: Edit Fika's videos for Instagram Reels and Stories (and Meta ads) in the owner's style - cut raw phone footage into a calm 20-45 second 9:16 video with bold word-by-word captions of what is said, the original sound with music underneath, and on-brand text such as an offer. Use whenever the owner sends a video or clip, asks to cut, trim, edit, caption, subtitle, resize, add music or text to a video, make a Reel, a Story or a video ad, or turn footage of candy, packing or deliveries into a post, even if the owner does not say "edit".
---

# Fika video editing (Reels and Stories)

Fika is a Swedish pick-and-mix shop in Lebanon (swedishfikalb.com). The owner films on a phone (candy pours, packing
bags, new candies, deliveries, talking to camera) and wants finished videos they can post as they are. You cannot
watch or hear a video the way the owner does: you see it through still frames and you know what is said through a
transcript. So you work from the facts the tool gives you, show the owner the result, and apply their notes. That loop is the
job; a first version is a draft, not a final.

## The owner's style (the owner's answers, 25 Nov 2026)

- **What:** Reels and Stories for Instagram (paid ads reuse them).
- **Length and pace:** 20-45 seconds, calm. Let a shot breathe (about 2.5-6 s per shot); no rapid-fire cuts, no
  flashy transitions. A soft fade on the very first and last shot is enough.
- **Captions:** of what is said, in **bold white with a dark outline, centred, a few words at a time** (1-3 words,
  in time with the speech). Captions matter because most people watch with the sound off. Fix names the
  transcript gets wrong (Fika, BUBS, candy names, Beirut, Lebanon).
- **Sound:** the original sound (voice, pouring, crinkling bags) with **music quietly underneath** (about 20-30 %),
  dipping while someone speaks. The owner sends the music; if none came, ask, or use the original sound only.
- **Ending:** **no end card.** The video ends on its last shot (fade out).
- **Format:** **9:16 only** (1080 x 1920, Reels and Stories). Make other sizes only when the owner asks.
- **Logo:** not settled yet (the owner answered "something else"). Ask once, then write the answer here. The tool can
  put the white or blue Fika wordmark in a corner for the whole video, at the start and end, or leave it out.
- **Words on screen** (offers, labels) follow the owner's voice: calm and sure, no exclamation marks, no emojis, British
  spelling, kilograms and grams (never "kilos", never "bags" for weight). Offer lines in Bebas Neue capitals, e.g.
  "UP TO 20% OFF", in white or Fika gold (#FCD34D). Fika blue is #004AAD, pink #FDEAF2.

## How to work

1. **Look at the footage.** For each clip:
   `python3 scripts/fika_video.py look CLIP.mp4 WORKDIR/look-CLIP --every 1`
   It prints the facts (length, size, orientation, sound) plus scene changes, silences and the loudest moments,
   and writes contact sheets (a frame per second, with timestamps). Read every sheet: this is how you see the video.
   Note the good moments (sharp, well lit, candy close up, hands in frame) and the bad ones (blur, shaky, dark,
   someone half in frame, the phone being set down at the start or end).
2. **Transcribe the speech** (when people talk):
   `python3 scripts/fika_video.py transcribe CLIP.mp4 WORKDIR/CLIP.words.json`
   Read the words. They tell you where sentences start and end, so you cut between sentences, never mid-word.
   If the speech model cannot download, see "When the speech model is missing" below.
3. **Plan the cut** in `WORKDIR/plan.json` (format below). Choose shots that tell one simple story in 20-45 s:
   open on the most beautiful shot, keep every spoken sentence whole, end on a calm shot. Portrait footage is
   cropped to fill the screen; for a landscape shot choose where the crop sits (`x`), or use `"frame": "fill"` when
   cropping would cut something important (the whole picture sits on a blurred copy of itself).
4. **Render:** `python3 scripts/fika_video.py render WORKDIR/plan.json`
5. **Check before sending:** `python3 scripts/fika_video.py check OUT.mp4 WORKDIR/check`
   Look at the new contact sheets: captions readable and not covering faces or the candy, nothing important cut
   off by the crop, the logo where it should be, no black or frozen frames, the length right. Loudness should be
   about -14 LUFS (the tool sets it). Fix and re-render if anything is off.
6. **Send it** with a short note: length, which moments you used (with their times in the owner's clips), the music
   level, and anything you were unsure of (a word the transcript may have misheard, a shot you left out). Ask for
   notes; apply them and send the new version. When a note changes the owner's style for good, update the style list above.

## The edit plan (plan.json)

Paths are relative to the plan file. Times are seconds in the source clip.

```json
{
  "output": "out/fika-reel-2026-11-26.mp4",
  "clips": [
    {"src": "IMG_1201.MOV", "start": 2.0, "end": 7.5, "fade_in": 0.4},
    {"src": "IMG_1202.MOV", "start": 0.8, "end": 6.0, "x": 0.35},
    {"src": "IMG_1203.MOV", "start": 10.0, "end": 16.0, "speed": 0.8},
    {"src": "IMG_1204.MOV", "start": 3.0, "end": 11.0, "frame": "fill", "fade_out": 0.6}
  ],
  "captions": {"words_json": {"IMG_1201.MOV": "IMG_1201.words.json", "IMG_1203.MOV": "IMG_1203.words.json"},
               "replace": [["^fika$", "Fika"], ["^bubs$", "BUBS"]], "max_words": 3, "size": 82, "y": 0.64},
  "texts": [{"text": "UP TO 20% OFF", "start": 1.0, "end": 5.0, "y": 0.18, "size": 120, "color": "0xFCD34D", "outline": 6}],
  "logo": {"where": "none"},
  "audio": {"original_volume": 1.0, "music": {"file": "music.mp3", "volume": 0.25, "start": 0, "fade_out": 2.0}}
}
```

- `clips`: in order. `speed` 0.5-2 (0.75 for a slow candy pour); the voice keeps up between 0.5 and 2.
  `x`/`y` 0-1 place the crop (0.5 = centre). `frame: "fill"` keeps the whole picture.
- `captions.words_json`: one transcript for the whole video, or a map of clip -> transcript when several clips have
  speech. Words outside the kept parts are dropped and the rest move to the new timeline by themselves.
  `y` 0.64 keeps captions below faces and above Instagram's buttons; move them (0.55-0.72) when they cover
  something. Leave out `captions` for a video without speech.
- `texts`: times are on the finished video. Bebas Neue by default (`"font": "outfit"` for Outfit ExtraBold).
  Long lines shrink to fit. Keep text out of the top 250 px and bottom 350 px (Instagram's own buttons cover them).
- `logo.where`: `none`, `corner` (whole video), `start-end`, `start` or `end`; `corner`: top-left, top-right,
  bottom-left, bottom-right, top-center; `color`: white or blue; `width` in px (about 170).
- `audio.music.duck` (default true) lowers the music under speech. `original_volume` 0 removes the original sound.

## When the speech model is missing

`transcribe` downloads the speech model (faster-whisper "small") from Hugging Face the first time. If the network
blocks it (a 403 from the proxy), the environment needs the hosts `huggingface.co` and `cdn-lfs.huggingface.co`
(and `cas-bridge.xethub.hf.co`) allowed: ask the owner, using the environment tools, rather than working around it.
Until then, ask the owner to paste what is said (or to send the captions from Instagram's auto-captions) and write the
words file by hand from the frames and the owner's text, with careful timings around the scene changes and silences that
`look` reports; tell the owner the timings are estimated.

## What this skill does not do

No motion tracking, green screen or colour grading beyond a simple fade; no new footage. Say so plainly and suggest
CapCut for those. You cannot judge how the music feels or whether a voice sounds good: say what you chose and let
the owner decide.

## Files

- `scripts/fika_video.py`: look, transcribe, render, check (ffmpeg, Pillow, faster-whisper).
- `assets/fonts/`: Outfit (captions), Bebas Neue (offer lines), free OFL fonts.
- `assets/fika-wordmark-white.png`, `assets/fika-wordmark-blue.png`: the Fika logo for videos;
  `assets/fika-email-logo.png`: the full logo with the cartoon candies.
