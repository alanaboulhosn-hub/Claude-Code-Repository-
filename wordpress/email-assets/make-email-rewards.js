const { chromium } = require('playwright');
const out = process.argv[2];
const T = { 'mystery-taste': 'Mystery taste', '100-g-on-us': '100 g on us', '200-g-on-us': '200 g on us', '400-g-on-us': '400 g on us', 'a-whole-kilo-on-us': 'A whole kilo on us' };
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args:['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ viewport:{width:1400,height:900}, deviceScaleFactor:2, userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36' });
  const p = await ctx.newPage(); await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.goto('https://swedishfikalb.com/', {waitUntil:'domcontentloaded'}); await p.waitForTimeout(12000);
  await p.goto('https://swedishfikalb.com/', {waitUntil:'networkidle'});
  const ok = await p.evaluate(async () => { await document.fonts.ready; return document.fonts.check("40px 'NF Le Petit Cochon'"); });
  console.log('font loaded', ok);
  await p.evaluate((T) => {
    let ff = ''; for (const sh of document.styleSheets) { try { for (const r of sh.cssRules) if (r.cssText.startsWith('@font-face') && r.cssText.includes('Cochon')) ff += r.cssText + '\n'; } catch (e) {} }
    const fst = document.createElement('style'); fst.textContent = ff; document.head.appendChild(fst); window.__ff = ff.length;
    document.body.innerHTML = ''; document.body.style.background = 'transparent'; document.documentElement.style.background = 'transparent';
    const st = document.createElement('style'); st.textContent = `body{margin:0} .h{position:absolute;left:0;font-family:'NF Le Petit Cochon',cursive;font-variant:small-caps;font-size:64px;line-height:1.1;color:#004aad;padding:4px 10px;white-space:nowrap;background:transparent}`;
    document.head.appendChild(st);
    let y = 0; for (const [k, t] of Object.entries(T)) { const d = document.createElement('div'); d.className = 'h'; d.id = 'k' + k; d.style.top = y + 'px'; d.textContent = t; document.body.appendChild(d); y += 120; }
  }, T);
  await p.waitForTimeout(800); console.log('ff', await p.evaluate(()=>window.__ff), await p.evaluate(async()=>{await document.fonts.load("64px 'NF Le Petit Cochon'");return document.fonts.check("64px 'NF Le Petit Cochon'")}));
  await p.waitForTimeout(500);
  for (const k of Object.keys(T)) await (await p.$('#k' + k)).screenshot({ path: out + '/fika-email-r-' + k + '.png', omitBackground: true });
  await b.close();
})();
