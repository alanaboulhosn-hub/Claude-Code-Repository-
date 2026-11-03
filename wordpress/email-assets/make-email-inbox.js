// Win-back email 2, "From our inbox" (owner, 3 Nov 2026: "animate it"): the checkout's Fika inbox card
// (fika-checkout-texts.php: same colours, fish avatar, bubbles, typing dots, the pink heart) played as frames:
// "a customer is typing..." -> each message pops in -> the heart -> a pause, then it starts again.
// Frames are written as PNGs (out/frames/NNN.png) with their durations (out/frames.json); make-email-inbox.py makes the GIF.
// Usage: node make-email-inbox.js <out dir> <fish svg file>
const { chromium } = require('playwright'); const fs = require('fs');
const out = process.argv[2]; const FISH = fs.readFileSync(process.argv[3], 'utf8');
const MSG = [['Im obsessed with them', '17:05'], ['I went to sleep yesterday thinking of them im not joking', '17:06'], ['Its so ridiculous but i cant', '17:06']];
const FPS = 15, TYPE = 1.1, GAP = 0.65, HEART = 0.42, HOLD = 3.6, START = 0.5;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
  const p = await (await b.newContext({ viewport: { width: 700, height: 500 }, deviceScaleFactor: 1.5 })).newPage();
  await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.goto('https://swedishfikalb.com/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(8000);
  await p.goto('https://swedishfikalb.com/', { waitUntil: 'networkidle' }); await p.waitForTimeout(2000);
  await p.evaluate(({ FISH, MSG }) => {
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
.ft-body { position: relative; height: 196px; overflow: hidden; }
.ft-thread { position: absolute; left: 0; right: 0; bottom: 0; padding: 12px 12px 14px; display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.ft-b { position: relative; max-width: 88%; padding: 6px 9px 7px; border-radius: 10px; background: #fff; color: #111b21; font-size: 15px; line-height: 1.38; overflow-wrap: anywhere; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); }
.ft-b.first { border-top-left-radius: 0; }
.ft-b.first::before { content: ''; position: absolute; left: -7px; top: 0; border-top: 9px solid #fff; border-left: 8px solid transparent; }
.ft-b time { float: right; margin: 6px 0 -4px 10px; font-size: 11px; color: #667781; }
.ft-heart { margin: -2px 0 0 8px; padding: 1px 6px; border-radius: 999px; background: #fff; box-shadow: 0 1px 2px rgba(11, 20, 26, .15); font-size: 13px; line-height: 20px; }
.ft-dots { padding: 11px 12px; border-radius: 10px; border-top-left-radius: 0; background: #fff; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); display: flex; gap: 4px; }
.ft-dots i { width: 7px; height: 7px; border-radius: 50%; background: #9aa6b3; }
`;
    document.head.appendChild(st);
    document.body.innerHTML = '<section class="fika-texts" id="card"><div class="ft-head"><span class="ft-av">' + FISH + '</span><div class="ft-who"><b>Fika inbox</b><span id="sub"></span></div></div><div class="ft-body"><div class="ft-thread" id="th"></div></div></section>';
    // the state at time t (seconds): which bubbles are there and how far their pop-in is (the site's .32 s ease)
    const ease = x => { const c = 1.3; x = Math.min(1, Math.max(0, x)); return 1 - Math.pow(1 - x, 3) * (1 - c * x * (1 - x) * 0); };
    const pop = (el, t0, t) => { const k = Math.min(1, Math.max(0, (t - t0) / 0.32)); const e = 1 - Math.pow(1 - k, 3); const over = k < 1 ? Math.sin(k * Math.PI) * 0.03 : 0; el.style.opacity = e; el.style.transform = 'translateY(' + (8 * (1 - e)).toFixed(2) + 'px) scale(' + (0.96 + 0.04 * e + over).toFixed(3) + ')'; };
    window.renderAt = (t, T) => {
      const th = document.getElementById('th'), sub = document.getElementById('sub');
      th.innerHTML = '';
      const typing = t >= T.start && t < T.b[0];
      sub.textContent = typing ? 'a customer is typing…' : 'Real texts from customers'; sub.className = typing ? 'is-typing' : '';
      MSG.forEach((m, k) => { if (t >= T.b[k]) { const d = document.createElement('div'); d.className = 'ft-b' + (k === 0 ? ' first' : ''); d.textContent = m[0]; const tm = document.createElement('time'); tm.textContent = m[1]; d.appendChild(tm); th.appendChild(d); pop(d, T.b[k], t); } });
      if (typing) { const d = document.createElement('div'); d.className = 'ft-dots'; d.innerHTML = '<i></i><i></i><i></i>'; th.appendChild(d); pop(d, T.start, t);
        [...d.children].forEach((i, n) => { const ph = ((t - T.start - n * 0.15) % 1.2 + 1.2) % 1.2 / 1.2; const up = ph < 0.3 ? ph / 0.3 : ph < 0.6 ? (0.6 - ph) / 0.3 : 0; i.style.transform = 'translateY(' + (-4 * up).toFixed(2) + 'px)'; i.style.opacity = (0.45 + 0.55 * up).toFixed(2); }); }
      if (t >= T.heart) { const h = document.createElement('span'); h.className = 'ft-heart'; h.textContent = '💗'; th.appendChild(h); pop(h, T.heart, t); }
    };
  }, { FISH, MSG });
  await p.waitForTimeout(800);
  const T = { start: START, b: [START + TYPE, START + TYPE + GAP, START + TYPE + 2 * GAP] }; T.heart = T.b[2] + HEART; T.end = T.heart + 0.32 + HOLD;
  fs.mkdirSync(out + '/frames', { recursive: true });
  const card = await p.$('#card'); const list = [];
  const N = Math.round(T.end * FPS);
  for (let i = 0; i < N; i++) {
    const t = i / FPS;
    await p.evaluate(([t, T]) => window.renderAt(t, T), [t, T]);
    const f = out + '/frames/' + String(i).padStart(3, '0') + '.png';
    await card.screenshot({ path: f }); list.push(f);
  }
  fs.writeFileSync(out + '/frames.json', JSON.stringify({ fps: FPS, frames: list }));
  console.log('frames', N, 'seconds', T.end.toFixed(2));
  await b.close();
})();
