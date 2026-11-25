#!/usr/bin/env python3
"""Fika video tool: look at a raw clip, transcribe it, and render a finished Reel/Story from an edit plan.

  python3 fika_video.py look IN.mp4 OUTDIR [--every 1.0]
      facts (duration, size, fps, sound), scene changes, silences, loudest moments, and contact sheets
      (one frame every N seconds, with timestamps) in OUTDIR, so the editor can SEE the footage.
  python3 fika_video.py transcribe IN.mp4 OUT.json [--model small] [--lang en]
      words with start/end times (faster-whisper). Needs the speech model (see SKILL.md if the download is blocked).
  python3 fika_video.py render PLAN.json
      builds the video described in the plan (format in SKILL.md / references/plan-format.md).
  python3 fika_video.py check OUT.mp4 OUTDIR
      the finished file: duration, size, loudness, and a contact sheet to look at before sending.

Only ffmpeg/ffprobe, Pillow and (for transcribe) faster-whisper are used.
"""
import json, math, os, re, subprocess, sys, tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
ASSETS = os.path.join(os.path.dirname(HERE), 'assets')
FONTS = os.path.join(ASSETS, 'fonts')
W, H = 1080, 1920  # 9:16


def run(cmd, quiet=True):
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit('ffmpeg failed:\n' + ' '.join(cmd) + '\n' + r.stderr[-3000:])
    return r


def probe(path):
    r = run(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', path])
    d = json.loads(r.stdout)
    v = next((s for s in d['streams'] if s['codec_type'] == 'video'), None)
    a = next((s for s in d['streams'] if s['codec_type'] == 'audio'), None)
    fps = 0
    if v and v.get('avg_frame_rate', '0/0') != '0/0':
        n, dd = v['avg_frame_rate'].split('/'); fps = round(float(n) / float(dd), 2) if float(dd) else 0
    rot = 0
    if v:
        for sd in v.get('side_data_list', []) or []:
            if 'rotation' in sd:
                rot = int(sd['rotation'])
        rot = int(v.get('tags', {}).get('rotate', rot) or rot)
    w, h = (v['width'], v['height']) if v else (0, 0)
    if abs(rot) in (90, 270):
        w, h = h, w
    return {'duration': round(float(d['format'].get('duration', 0)), 2), 'width': w, 'height': h, 'fps': fps,
            'audio': bool(a), 'rotation': rot}


# ---------------------------------------------------------------- look
def look(src, out, every=1.0):
    os.makedirs(out, exist_ok=True)
    info = probe(src)
    # scene changes
    r = subprocess.run(['ffmpeg', '-hide_banner', '-i', src, '-vf', "select='gt(scene,0.30)',showinfo", '-an', '-f', 'null', '-'],
                       capture_output=True, text=True)
    scenes = [round(float(x), 2) for x in re.findall(r'pts_time:([0-9.]+)', r.stderr)]
    silences, loud = [], []
    if info['audio']:
        r = subprocess.run(['ffmpeg', '-hide_banner', '-i', src, '-af', 'silencedetect=n=-35dB:d=0.6', '-vn', '-f', 'null', '-'],
                           capture_output=True, text=True)
        st = re.findall(r'silence_start: ([0-9.]+)', r.stderr); en = re.findall(r'silence_end: ([0-9.]+)', r.stderr)
        silences = [[round(float(a), 2), round(float(b), 2)] for a, b in zip(st, en + [str(info['duration'])] * (len(st) - len(en)))]
        r = subprocess.run(['ffmpeg', '-hide_banner', '-i', src, '-af', 'astats=metadata=1:reset=1,ametadata=print:key=lavfi.astats.Overall.RMS_level',
                            '-vn', '-f', 'null', '-'], capture_output=True, text=True)
        lv = [(float(t), float(v)) for t, v in re.findall(r'pts_time:([0-9.]+)[\s\S]*?RMS_level=(-?[0-9.]+|-inf)', r.stderr) if v != '-inf']
        # one value per half second, loudest 6
        b = {}
        for t, v in lv:
            k = round(t * 2) / 2; b[k] = max(b.get(k, -99), v)
        loud = [k for k, _ in sorted(b.items(), key=lambda x: -x[1])[:6]]
    # contact sheets: frames every N s, 4 x 3 per sheet, timestamp on each
    from PIL import Image, ImageDraw, ImageFont
    tmp = tempfile.mkdtemp()
    run(['ffmpeg', '-v', 'error', '-i', src, '-vf', f'fps=1/{every},scale=270:-2', os.path.join(tmp, 'f%05d.jpg')])
    frames = sorted(f for f in os.listdir(tmp) if f.endswith('.jpg'))
    font = ImageFont.truetype(os.path.join(FONTS, 'Outfit-ExtraBold.ttf'), 22)
    sheets = []
    for s in range(0, len(frames), 12):
        part = frames[s:s + 12]
        ims = [Image.open(os.path.join(tmp, f)) for f in part]
        fw, fh = ims[0].size
        sheet = Image.new('RGB', (fw * 4, fh * math.ceil(len(ims) / 4)), 'white')
        for i, im in enumerate(ims):
            x, y = (i % 4) * fw, (i // 4) * fh
            sheet.paste(im, (x, y)); d = ImageDraw.Draw(sheet)
            t = (s + i) * every
            d.rectangle([x, y, x + 86, y + 30], fill='black'); d.text((x + 6, y + 3), f'{t:.1f}s', fill='white', font=font)
        p = os.path.join(out, f'sheet-{len(sheets) + 1:02d}.jpg'); sheet.save(p, quality=80); sheets.append(p)
    facts = dict(info, scene_changes=scenes, silences=silences, loudest_moments=sorted(loud), contact_sheets=sheets, frame_every_s=every)
    json.dump(facts, open(os.path.join(out, 'look.json'), 'w'), indent=1)
    print(json.dumps(facts, indent=1))


# ---------------------------------------------------------------- transcribe
def transcribe(src, out, model='small', lang=None):
    try:
        from faster_whisper import WhisperModel
    except ImportError:
        sys.exit('pip install faster-whisper first')
    m = WhisperModel(model, device='cpu', compute_type='int8')
    segs, info = m.transcribe(src, word_timestamps=True, language=lang, vad_filter=True)
    words = []
    for s in segs:
        for w in s.words or []:
            words.append({'w': w.word.strip(), 'start': round(w.start, 2), 'end': round(w.end, 2), 'p': round(w.probability, 2)})
    json.dump({'language': info.language, 'words': words}, open(out, 'w'), indent=1, ensure_ascii=False)
    print(f'{len(words)} words, language {info.language}:', ' '.join(w['w'] for w in words)[:600])


# ---------------------------------------------------------------- render
def ass_time(t):
    t = max(0, t); h = int(t // 3600); m = int(t % 3600 // 60); s = t % 60
    return f'{h}:{m:02d}:{s:05.2f}'


def captions_ass(words, path, style):
    """Bold white words with a dark outline, centred, a few words at a time (the owner's choice)."""
    size = style.get('size', 82); y = style.get('y', 0.64); maxw = style.get('max_words', 3)
    head = f"""[Script Info]
ScriptType: v4.00+
PlayResX: {W}
PlayResY: {H}
WrapStyle: 2

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Cap,Outfit,{size},&H00FFFFFF,&H00FFFFFF,&H00141414,&H64000000,-1,0,0,0,100,100,0,0,1,7,2,5,60,60,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    lines = []
    # group words: up to maxw words, break on a pause > 0.35 s or end of a sentence
    group = []
    for i, w in enumerate(words):
        group.append(w)
        nxt = words[i + 1] if i + 1 < len(words) else None
        end_sentence = re.search(r'[.!?,]$', w['w'])
        if len(group) >= maxw or not nxt or nxt['start'] - w['end'] > 0.35 or end_sentence:
            a, b = group[0]['start'], max(group[-1]['end'], group[0]['start'] + 0.45)
            if nxt:
                b = min(b + 0.15, nxt['start'])
            text = ' '.join(x['w'] for x in group).strip()
            text = text.upper() if style.get('upper', True) else text
            text = text.replace('{', '(').replace('}', ')')
            lines.append(f"Dialogue: 0,{ass_time(a)},{ass_time(b)},Cap,,0,0,0,,{{\\pos({W // 2},{int(H * y)})}}{text}")
            group = []
    open(path, 'w', encoding='utf-8').write(head + '\n'.join(lines) + '\n')
    return len(lines)


def esc(p):
    return p.replace('\\', '/').replace(':', '\\:').replace("'", "\\'")


def render(plan_path):
    plan = json.load(open(plan_path))
    base = os.path.dirname(os.path.abspath(plan_path))
    P = lambda p: p if os.path.isabs(p) else os.path.join(base, p)
    out = P(plan['output'])
    os.makedirs(os.path.dirname(out) or '.', exist_ok=True)
    tmp = tempfile.mkdtemp()
    clips = plan['clips']
    # 1) each clip: cut, speed, frame for 9:16 (crop centre or at "x" 0..1, or blurred fill), constant 30 fps
    parts, timeline, t_out = [], [], 0.0
    for i, c in enumerate(clips):
        src = P(c['src']); info = probe(src)
        a, b = float(c['start']), float(c['end']); sp = float(c.get('speed', 1.0))
        frame = c.get('frame', 'crop')
        if frame == 'fill':  # whole picture, blurred copy behind it (for landscape shots that must not be cut)
            vf = (f"split[a][b];[a]scale={W}:{H}:force_original_aspect_ratio=increase,crop={W}:{H},boxblur=30:5[bg];"
                  f"[b]scale={W}:{H}:force_original_aspect_ratio=decrease[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2")
        else:
            x = float(c.get('x', 0.5))
            vf = (f"scale={W}:{H}:force_original_aspect_ratio=increase,"
                  f"crop={W}:{H}:(iw-{W})*{x}:(ih-{H})*{float(c.get('y', 0.5))}")
        vf += f",setpts=(PTS-STARTPTS)/{sp},fps=30,format=yuv420p"
        if c.get('fade_in'):
            vf += f",fade=t=in:st=0:d={c['fade_in']}"
        dur = (b - a) / sp
        if c.get('fade_out'):
            vf += f",fade=t=out:st={max(0, dur - c['fade_out'])}:d={c['fade_out']}"
        cmd = ['ffmpeg', '-v', 'error', '-y', '-ss', str(a), '-t', str(b - a), '-i', src]
        if info['audio'] and sp == 1.0:
            af = 'aresample=48000,aformat=channel_layouts=stereo'
        elif info['audio'] and 0.5 <= sp <= 2.0:
            af = f'atempo={sp},aresample=48000,aformat=channel_layouts=stereo'
        else:
            af = None
        if af:
            cmd += ['-filter_complex', f'[0:v]{vf}[v];[0:a]{af}[a]', '-map', '[v]', '-map', '[a]']
        else:
            cmd += ['-f', 'lavfi', '-t', str(dur), '-i', 'anullsrc=r=48000:cl=stereo',
                    '-filter_complex', f'[0:v]{vf}[v]', '-map', '[v]', '-map', '1:a']
        p = os.path.join(tmp, f'part{i:02d}.mp4')
        cmd += ['-t', str(dur), '-c:v', 'libx264', '-preset', 'medium', '-crf', '18', '-c:a', 'aac', '-b:a', '192k', p]
        run(cmd)
        parts.append(p); timeline.append({'src': c['src'], 'in': a, 'out': b, 'speed': sp, 'at': t_out}); t_out += dur
    # 2) join
    lst = os.path.join(tmp, 'list.txt'); open(lst, 'w').write(''.join(f"file '{p}'\n" for p in parts))
    joined = os.path.join(tmp, 'joined.mp4')
    run(['ffmpeg', '-v', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', lst, '-c', 'copy', joined])
    total = t_out
    # 3) captions: words from the transcript of each source, moved onto the new timeline
    vfx = []
    cap = plan.get('captions')
    if cap and cap.get('words_json'):
        wj = cap['words_json']
        if isinstance(wj, dict):  # one transcript per clip: {"IMG_1.MOV": "IMG_1.words.json", ...}
            by_src = {k: json.load(open(P(v))) for k, v in wj.items()}
        else:  # one transcript, for every clip cut from the same source
            tr = json.load(open(P(wj)))
            by_src = {c['src']: tr for c in clips}
        words = []
        for seg in timeline:
            src_words = by_src.get(seg['src'], {}).get('words', [])
            for w in src_words:
                if w['start'] >= seg['in'] - 0.05 and w['end'] <= seg['out'] + 0.05:
                    s = seg['at'] + (w['start'] - seg['in']) / seg['speed']; e = seg['at'] + (w['end'] - seg['in']) / seg['speed']
                    words.append({'w': w['w'], 'start': round(s, 2), 'end': round(e, 2)})
        for fix in cap.get('replace', []):  # spelling fixes, e.g. ["fika", "Fika"]
            for w in words:
                w['w'] = re.sub(fix[0], fix[1], w['w'], flags=re.I)
        assf = os.path.join(tmp, 'caps.ass'); n = captions_ass(words, assf, cap)
        vfx.append(f"subtitles='{esc(assf)}':fontsdir='{esc(FONTS)}'")
        print('captions:', n, 'lines')
    # 4) text overlays (e.g. the offer) in Bebas Neue / Outfit, and the logo
    for t in plan.get('texts', []):
        font = os.path.join(FONTS, 'BebasNeue-Regular.ttf' if t.get('font', 'bebas') == 'bebas' else 'Outfit-ExtraBold.ttf')
        col = t.get('color', 'white'); y = t.get('y', 0.18)
        box = f":box=1:boxcolor={t['box']}:boxborderw=24" if t.get('box') else ''
        tf = os.path.join(tmp, f'text{len(vfx)}.txt')
        open(tf, 'w', encoding='utf-8').write(t['text'])  # read from a file: any character works, incl. % ' :
        from PIL import ImageFont
        size = int(t.get('size', 96))
        while size > 30 and ImageFont.truetype(font, size).getlength(t['text']) > W * 0.86:
            size -= 4  # a line that is too long shrinks until it fits the screen
        vfx.append(f"drawtext=fontfile='{esc(font)}':textfile='{esc(tf)}':expansion=none:fontsize={size}:fontcolor={col}"
                   f":borderw={t.get('outline', 0)}:bordercolor=0x141414{box}:x=(w-text_w)/2:y=h*{y}-text_h/2"
                   f":enable='between(t,{t['start']},{t['end']})'")
    inputs = ['-i', joined]
    logo = plan.get('logo')
    fc = None
    vchain = ','.join(vfx) if vfx else 'null'
    if logo and logo.get('where', 'none') != 'none':
        lf = os.path.join(ASSETS, 'fika-wordmark-white.png' if logo.get('color', 'white') == 'white' else 'fika-wordmark-blue.png')
        inputs += ['-i', lf]
        lw = int(logo.get('width', 170)); pos = logo.get('where')
        xy = {'top-left': '60:150', 'top-right': 'W-w-60:150', 'bottom-left': '60:H-h-260', 'bottom-right': 'W-w-60:H-h-260',
              'top-center': '(W-w)/2:150'}.get(logo.get('corner', 'top-left'))
        en = ''
        if pos == 'start-end':
            en = f":enable='lt(t,{logo.get('seconds', 2.5)})+gt(t,{total - logo.get('seconds', 2.5)})'"
        elif pos == 'start':
            en = f":enable='lt(t,{logo.get('seconds', 2.5)})'"
        elif pos == 'end':
            en = f":enable='gt(t,{total - logo.get('seconds', 2.5)})'"
        fc = f"[0:v]{vchain}[v0];[1:v]scale={lw}:-1,format=rgba,colorchannelmixer=aa={logo.get('opacity', 0.9)}[lg];[v0][lg]overlay={xy}{en}[v]"
    else:
        fc = f"[0:v]{vchain}[v]"
    # 5) sound: original voice/sound + music underneath, music ducked while people speak, loudness for Instagram
    au = plan.get('audio', {})
    orig_vol = au.get('original_volume', 1.0)
    music = au.get('music')
    ai = 1 if not (logo and logo.get('where', 'none') != 'none') else 2
    if music:
        inputs += ['-stream_loop', '-1', '-i', P(music['file'])]
        mv = music.get('volume', 0.25); ms = music.get('start', 0)
        fade = music.get('fade_out', 2.0)
        fc += (f";[0:a]volume={orig_vol}[o];[{ai}:a]atrim=start={ms},asetpts=PTS-STARTPTS,volume={mv},"
               f"afade=t=out:st={max(0, total - fade)}:d={fade}[m0]")
        if music.get('duck', True) and orig_vol > 0:
            fc += ";[o]asplit[o1][o2];[m0][o2]sidechaincompress=threshold=0.03:ratio=8:attack=20:release=400[m];[o1][m]amix=inputs=2:duration=first:normalize=0[am]"
        else:
            fc += ";[o][m0]amix=inputs=2:duration=first:normalize=0[am]"
    else:
        fc += f";[0:a]volume={orig_vol}[am]"
    fc += f";[am]loudnorm=I={au.get('lufs', -14)}:TP=-1.5:LRA=11[a]"
    run(['ffmpeg', '-v', 'error', '-y', *inputs, '-filter_complex', fc, '-map', '[v]', '-map', '[a]', '-t', f'{total:.3f}',
         '-c:v', 'libx264', '-preset', 'slow', '-crf', '19', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', '30',
         '-c:a', 'aac', '-b:a', '192k', '-ar', '48000', '-movflags', '+faststart', out])
    json.dump(timeline, open(out + '.timeline.json', 'w'), indent=1)
    print('wrote', out, f'{total:.1f}s')


# ---------------------------------------------------------------- check
def check(path, out):
    info = probe(path)
    r = subprocess.run(['ffmpeg', '-hide_banner', '-i', path, '-af', 'loudnorm=print_format=json', '-f', 'null', '-'], capture_output=True, text=True)
    m = re.search(r'\{[^{}]*"input_i"[^{}]*\}', r.stderr)
    loud = json.loads(m.group(0)) if m else {}
    every = max(0.5, round(info['duration'] / 24, 1))
    look_dir = os.path.join(out, 'check'); os.makedirs(look_dir, exist_ok=True)
    look(path, look_dir, every)
    size = os.path.getsize(path)
    print(json.dumps({'file': path, 'size_mb': round(size / 1e6, 1), **info, 'loudness_lufs': loud.get('input_i'),
                      'true_peak': loud.get('input_tp'), 'ok_for_reels': info['width'] == W and info['height'] == H and info['duration'] <= 90}, indent=1))


if __name__ == '__main__':
    a = sys.argv[1:]
    if not a:
        sys.exit(__doc__)
    opt = lambda k, d: a[a.index(k) + 1] if k in a else d
    if a[0] == 'look':
        look(a[1], a[2], float(opt('--every', 1.0)))
    elif a[0] == 'transcribe':
        transcribe(a[1], a[2], opt('--model', 'small'), opt('--lang', None))
    elif a[0] == 'render':
        render(a[1])
    elif a[0] == 'check':
        check(a[1], a[2])
    else:
        sys.exit(__doc__)
