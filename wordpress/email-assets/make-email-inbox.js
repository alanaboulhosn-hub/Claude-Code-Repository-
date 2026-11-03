// Win-back email 2, "From our inbox" (owner, 3 Nov 2026: "animate it", "flick through the texts like the checkout"): the
// checkout's Fika inbox card (fika-checkout-texts.php: same colours, fish avatar, bubbles, typing dots, the pink heart,
// the n/N counter) played as frames, one customer after another: "a customer is typing..." -> each message pops in ->
// the heart -> a pause -> fade -> the next customer. The card keeps the tallest conversation's height, like the checkout.
// Frames are written as PNGs (out/frames/NNN.png) with out/frames.json; make-email-inbox.py makes the GIF.
// Usage: node make-email-inbox.js <out dir> <fish svg file> <messages json (fika_ct_messages)> <keys, comma separated>
const { chromium } = require('playwright'); const fs = require('fs');
const out = process.argv[2]; const FISH = fs.readFileSync(process.argv[3], 'utf8');
const ALL = JSON.parse(fs.readFileSync(process.argv[4], 'utf8')); const KEYS = process.argv[5].split(',');
const GROUPS = KEYS.map(k => ALL[k]);
const FPS = 12, TYPE = 1.1, GAP = 0.65, HEART = 0.42, HOLD = 1.9, FADE = 0.26;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
  const p = await (await b.newContext({ viewport: { width: 700, height: 500 }, deviceScaleFactor: 1.5 })).newPage();
  await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.goto('https://swedishfikalb.com/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(8000);
  await p.goto('https://swedishfikalb.com/', { waitUntil: 'networkidle' }); await p.waitForTimeout(2000);
  await p.evaluate(({ FISH, GROUPS }) => {
    let ff = ''; for (const sh of document.styleSheets) { try { for (const r of sh.cssRules) if (r.cssText.startsWith('@font-face')) ff += r.cssText + '\n'; } catch (e) {} }
    document.body.innerHTML = ''; document.body.className = ''; document.documentElement.style.background = '#fff'; document.body.style.cssText = 'margin:0;background:#fff;padding:10px';
    const st = document.createElement('style');
    st.textContent = ff + `
.fika-texts { width: 440px; border-radius: 18px; overflow: hidden; border: 1px solid #f3d3e1; background: #fdeaf2; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
.ft-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border-bottom: 1px solid #f3d3e1; }
.ft-av { flex: none; width: 36px; height: 36px; border-radius: 50%; background: #fdeaf2; display: grid; place-items: center; }
.ft-av svg { width: 26px; height: 26px; transform: scaleX(-1); }
.ft-who { flex: 1; min-width: 0; line-height: 1.25; }
.ft-who b { display: block; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 19px; letter-spacing: .03em; color: #004aad; }
.ft-who span { display: block; font-size: 12.5px; color: #667781; }
.ft-who span.is-typing { color: #1f9d55; }
.ft-body { position: relative; overflow: hidden; }
.ft-n { font-size: 12px; color: #8a96b3; font-variant-numeric: tabular-nums; }
.ft-thread { position: absolute; left: 0; right: 0; bottom: 0; padding: 12px 12px 14px; display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.ft-b { position: relative; max-width: 88%; padding: 6px 9px 7px; border-radius: 10px; background: #fff; color: #111b21; font-size: 15px; line-height: 1.38; white-space: pre-line; overflow-wrap: anywhere; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); }
.ft-b.first { border-top-left-radius: 0; }
.ft-b.first::before { content: ''; position: absolute; left: -7px; top: 0; border-top: 9px solid #fff; border-left: 8px solid transparent; }
.ft-b time { float: right; margin: 6px 0 -4px 10px; font-size: 11px; color: #667781; }
.ft-heart { margin: -2px 0 0 8px; padding: 3px 7px; display: inline-flex; border-radius: 999px; background: #fff; box-shadow: 0 1px 2px rgba(11, 20, 26, .15); font-size: 13px; line-height: 20px; }
.ft-dots { padding: 11px 12px; border-radius: 10px; border-top-left-radius: 0; background: #fff; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); display: flex; gap: 4px; }
.ft-dots i { width: 7px; height: 7px; border-radius: 50%; background: #9aa6b3; }
`;
    document.head.appendChild(st);
    document.body.innerHTML = '<section class="fika-texts" id="card"><div class="ft-head"><span class="ft-av">' + FISH + '</span><div class="ft-who"><b>Fika inbox</b><span id="sub"></span></div><span class="ft-n" id="n"></span></div><div class="ft-body" id="body"><div class="ft-thread" id="th"></div></div></section>';
    // the card's height: the tallest conversation, with its heart
    const th0 = document.getElementById('th'); let max = 0;
    GROUPS.forEach(g => { th0.innerHTML = ''; th0.style.position = 'static';
      g.forEach((m, k) => { const d = document.createElement('div'); d.className = 'ft-b' + (k === 0 ? ' first' : ''); d.textContent = m[0]; const tm = document.createElement('time'); tm.textContent = m[1]; d.appendChild(tm); th0.appendChild(d); });
      const h = document.createElement('span'); h.className = 'ft-heart'; h.innerHTML = '<svg viewBox="0 0 24 22" width="15" height="14" style="display:block"><path d="M12 21 C5 15.5 1 12 1 7 C1 3.7 3.6 1.2 6.7 1.2 C8.9 1.2 10.8 2.4 12 4.2 C13.2 2.4 15.1 1.2 17.3 1.2 C20.4 1.2 23 3.7 23 7 C23 12 19 15.5 12 21Z" fill="#f04f95"/></svg>'; th0.appendChild(h); max = Math.max(max, th0.offsetHeight); });
    th0.style.position = ''; th0.innerHTML = '';
    document.getElementById('body').style.height = max + 'px';
    // the state at time t (seconds): which bubbles are there and how far their pop-in is (the site's .32 s ease)
    const ease = x => { const c = 1.3; x = Math.min(1, Math.max(0, x)); return 1 - Math.pow(1 - x, 3) * (1 - c * x * (1 - x) * 0); };
    const pop = (el, t0, t) => { const k = Math.min(1, Math.max(0, (t - t0) / 0.32)); const e = 1 - Math.pow(1 - k, 3); const over = k < 1 ? Math.sin(k * Math.PI) * 0.03 : 0; el.style.opacity = e; el.style.transform = 'translateY(' + (8 * (1 - e)).toFixed(2) + 'px) scale(' + (0.96 + 0.04 * e + over).toFixed(3) + ')'; };
    window.renderAt = (t, T, gi) => {
      const th = document.getElementById('th'), sub = document.getElementById('sub'), MSG = GROUPS[gi];
      document.getElementById('n').textContent = (gi + 1) + '/' + GROUPS.length;
      th.innerHTML = ''; th.style.opacity = t > T.end - T.fade ? Math.max(0, (T.end - t) / T.fade).toFixed(2) : 1;
      const typing = t >= T.start && t < T.b[0];
      sub.textContent = typing ? 'a customer is typing…' : 'Real texts from customers'; sub.className = typing ? 'is-typing' : '';
      MSG.forEach((m, k) => { if (t >= T.b[k]) { const d = document.createElement('div'); d.className = 'ft-b' + (k === 0 ? ' first' : ''); d.textContent = m[0]; const tm = document.createElement('time'); tm.textContent = m[1]; d.appendChild(tm); th.appendChild(d); pop(d, T.b[k], t); } });
      if (typing) { const d = document.createElement('div'); d.className = 'ft-dots'; d.innerHTML = '<i></i><i></i><i></i>'; th.appendChild(d); pop(d, T.start, t);
        [...d.children].forEach((i, n) => { const ph = ((t - T.start - n * 0.15) % 1.2 + 1.2) % 1.2 / 1.2; const up = ph < 0.3 ? ph / 0.3 : ph < 0.6 ? (0.6 - ph) / 0.3 : 0; i.style.transform = 'translateY(' + (-4 * up).toFixed(2) + 'px)'; i.style.opacity = (0.45 + 0.55 * up).toFixed(2); }); }
      if (t >= T.heart) { const h = document.createElement('span'); h.className = 'ft-heart'; h.innerHTML = '<svg viewBox="0 0 24 22" width="15" height="14" style="display:block"><path d="M12 21 C5 15.5 1 12 1 7 C1 3.7 3.6 1.2 6.7 1.2 C8.9 1.2 10.8 2.4 12 4.2 C13.2 2.4 15.1 1.2 17.3 1.2 C20.4 1.2 23 3.7 23 7 C23 12 19 15.5 12 21Z" fill="#f04f95"/></svg>'; th.appendChild(h); pop(h, T.heart, t); }
    };
  }, { FISH, GROUPS });
  await p.waitForTimeout(800);
  fs.rmSync(out + '/frames', { recursive: true, force: true }); fs.mkdirSync(out + '/frames', { recursive: true });
  const card = await p.$('#card'); const list = []; let i = 0, total = 0, firstDone = 0;
  for (let gi = 0; gi < GROUPS.length; gi++) {
    const n = GROUPS[gi].length, T = { start: 0.15, fade: FADE, b: [] };
    for (let k = 0; k < n; k++) T.b.push(T.start + TYPE + k * GAP);
    T.heart = T.b[n - 1] + HEART; T.end = T.heart + 0.32 + HOLD + FADE;
    const N = Math.round(T.end * FPS);
    for (let j = 0; j < N; j++) {
      const t = j / FPS;
      await p.evaluate(([t, T, gi]) => window.renderAt(t, T, gi), [t, T, gi]);
      const f = out + '/frames/' + String(i++).padStart(4, '0') + '.png';
      await card.screenshot({ path: f }); list.push(f);
      if (gi === 0 && t >= T.heart + 0.32 && !firstDone) firstDone = list.length; // the first customer's finished chat
    }
    total += T.end;
  }
  fs.writeFileSync(out + '/frames.json', JSON.stringify({ fps: FPS, frames: list, finished_first: firstDone - 1 }));
  console.log('frames', list.length, 'seconds', total.toFixed(1));
  await b.close();
})();
