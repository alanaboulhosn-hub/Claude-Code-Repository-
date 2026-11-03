// Win-back email 2 pictures (owner, 3 Nov 2026: "we owe you $5 on your next kilogram"): the IOU voucher at the top,
// the row of ten scoops (1 kg), drawn with the website's own fonts and cartoon candies (window.FIKA_CARTOON), at 2x.
// Usage: node make-email-owe.js <out dir>
const { chromium } = require('playwright');
const out = process.argv[2];
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
  const ctx = await b.newContext({ viewport: { width: 1200, height: 1400 }, deviceScaleFactor: 2 });
  const p = await ctx.newPage(); await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.goto('https://swedishfikalb.com/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(9000);
  await p.goto('https://swedishfikalb.com/', { waitUntil: 'networkidle' }); await p.waitForTimeout(3000);
  const fonts = await p.evaluate(async () => { await document.fonts.ready; return ["'NF Le Petit Cochon'", "'Bebas Neue'", "'Fanwood Text'", "'Outfit'"].map(f => f + ':' + document.fonts.check('40px ' + f)); });
  console.log('fonts', fonts.join(' '), 'cartoons', await p.evaluate(() => typeof window.FIKA_CARTOON));
  await p.evaluate(() => {
    let ff = ''; for (const sh of document.styleSheets) { try { for (const r of sh.cssRules) if (r.cssText.startsWith('@font-face')) ff += r.cssText + '\n'; } catch (e) {} }
    const C = window.FIKA_CARTOON;
    document.body.innerHTML = ''; document.body.className = ''; document.documentElement.style.background = '#fff'; document.body.style.cssText = 'margin:0;background:#fff';
    const st = document.createElement('style');
    st.textContent = ff + `
      * { box-sizing: border-box; }
      .art { position: relative; background: #fff; overflow: hidden; }
      #hero { width: 520px; height: 318px; }
      .tk { position: absolute; left: 16px; right: 16px; top: 22px; bottom: 22px; background: #fdeaf2; border-radius: 28px; }
      .tk::before { content: ''; position: absolute; inset: 11px; border: 2.5px dashed #004aad; border-radius: 19px; opacity: .55; }
      .notch { position: absolute; top: 50%; width: 34px; height: 34px; margin-top: -17px; border-radius: 50%; background: #fff; }
      .n1 { left: -17px; } .n2 { right: -17px; }
      .in { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding-bottom: 6px; }
      .iou { font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 21px; letter-spacing: .32em; color: #d35d93; margin-left: .32em; }
      .owes { font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-size: 40px; line-height: 1; color: #004aad; }
      .amt { font-family: 'NF Le Petit Cochon', cursive; font-size: 138px; line-height: .86; color: #004aad; text-shadow: 6px 6px 0 #f5b3cf; margin: 10px 0 -18px; letter-spacing: -.01em; }
      .next { font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 25px; color: #1b2a4a; }
      .next b { color: #d35d93; font-weight: 400; }
      .stamp { position: absolute; right: 34px; top: 34px; width: 100px; height: 100px; transform: rotate(14deg); opacity: .92; }
      .toon { position: absolute; width: 56px; height: 56px; filter: drop-shadow(0 3px 3px rgba(80, 20, 50, .28)); }
      .toon svg { width: 100%; height: 100%; display: block; overflow: visible; }
      #kg { background: transparent; width: 520px; height: 96px; display: flex; align-items: center; justify-content: space-between; padding: 0 6px; }
      #kg .s { width: 46px; height: 46px; position: relative; filter: drop-shadow(0 2px 2px rgba(80, 20, 50, .25)); }
      #kg .s svg { width: 100%; height: 100%; display: block; overflow: visible; }
    `;
    document.head.appendChild(st);
    const toon = (slug, cls, css) => { const d = document.createElement('div'); d.className = cls; d.style.cssText = css; d.innerHTML = C(slug); return d; };
    // the voucher
    const hero = document.createElement('div'); hero.className = 'art'; hero.id = 'hero';
    hero.innerHTML = '<div class="tk"><i class="notch n1"></i><i class="notch n2"></i><div class="in">' +
      '<div class="owes">Fika owes you</div><div class="amt">$5</div><div class="next">in <b>store credit</b></div></div></div>' +
      // the stamp: a ring of words around a heart, in the shop's pink
      '<svg class="stamp" viewBox="0 0 120 120"><defs><path id="ring" d="M60,60 m-41,0 a41,41 0 1,1 82,0 a41,41 0 1,1 -82,0"/></defs>' +
      '<circle cx="60" cy="60" r="56" fill="none" stroke="#d35d93" stroke-width="3.5"/><circle cx="60" cy="60" r="31" fill="none" stroke="#d35d93" stroke-width="2" stroke-dasharray="3 4"/>' +
      '<text font-family="Outfit, Arial" font-weight="700" font-size="13" fill="#d35d93"><textPath href="#ring" startOffset="0" textLength="252" lengthAdjust="spacing">SIGNED WITH LOVE · FIKA ·</textPath></text>' +
      '<path d="M60 76 C40 62 44 46 54 47 C57 47 59 49 60 52 C61 49 63 47 66 47 C76 46 80 62 60 76Z" fill="#d35d93"/></svg>';
    [['bubs-wild-berry-pomegranate-oval', 'left:18px;top:6px;transform:rotate(-24deg)'], ['swedish-fish', 'left:10px;bottom:16px;transform:rotate(18deg);width:62px;height:62px'],
     ['bubs-tutti-frutti-diamond', 'left:118px;top:2px;transform:rotate(-12deg);width:46px;height:46px'], ['sour-cherries', 'right:20px;bottom:20px;transform:rotate(10deg)'],
     ['bubs-bubblegum-skull', 'left:96px;bottom:4px;transform:rotate(-8deg);width:46px;height:46px']].forEach(t => hero.appendChild(toon(t[0], 'toon', t[1])));
    document.body.appendChild(hero);
    // ten scoops = 1 kg
    const kg = document.createElement('div'); kg.className = 'art'; kg.id = 'kg';
    ['peaches', 'bubs-wild-berry-pomegranate-oval', 'swedish-fish', 'sour-cherries', 'bubs-bubblegum-skull', 'fizzy-blue', 'sugared-strawberries', 'bubs-tutti-frutti-diamond', 'tutti-frutti-rings', 'raspberry-bites']
      .forEach((s, i) => kg.appendChild(toon(s, 's', 'transform:rotate(' + ((i % 2 ? 1 : -1) * (6 + (i * 7) % 11)) + 'deg) translateY(' + ((i % 3) - 1) * 5 + 'px)')));
    document.body.appendChild(kg);
  });
  await p.waitForTimeout(1500);
  await (await p.$('#hero')).screenshot({ path: out + '/fika-email-owe-voucher2.png' }); // v2: "in store credit" (was "on your next kilogram"); no "I.O.U." line (owner, 3 Nov)
  await p.evaluate(() => { document.documentElement.style.background = 'transparent'; document.body.style.background = 'transparent'; });
  await (await p.$('#kg')).screenshot({ path: out + '/fika-email-owe-kilo.png', omitBackground: true }); // see-through: it sits on the light-blue box
  await b.close();
})();
