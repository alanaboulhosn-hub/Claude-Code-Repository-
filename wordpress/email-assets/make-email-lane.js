const { chromium } = require('playwright');
const fs = require('fs');
const S = process.argv[2];
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args:['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ viewport:{width:390,height:900}, deviceScaleFactor:3, reducedMotion:'reduce', userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36' });
  const p = await ctx.newPage(); await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.goto('https://swedishfikalb.com/', {waitUntil:'domcontentloaded'}); await p.waitForTimeout(12000);
  await p.goto('https://swedishfikalb.com/', {waitUntil:'networkidle'});
  for (const k of ['0','15']) {
    const d = JSON.parse(fs.readFileSync(S+'/lane'+k+'.json','utf8'));
    await p.evaluate(({html, assets}) => {
      let ff = ''; for (const sh of document.styleSheets) { try { for (const r of sh.cssRules) if (r.cssText.startsWith('@font-face')) ff += r.cssText + '\n'; } catch (e) {} }
      document.body.innerHTML = '<style>' + ff + '</style><div id="box" style="width:370px;padding:10px 0;background:#fdeaf2">' + html + '</div>';
      document.body.style.background = '#fdeaf2';
      const t = document.createElement('div'); t.innerHTML = assets;
      t.querySelectorAll('style').forEach(s => document.head.appendChild(s.cloneNode(true)));
      t.querySelectorAll('script').forEach(s => { const n = document.createElement('script'); n.textContent = s.textContent; document.body.appendChild(n); });
      const x = document.createElement('style'); x.textContent = '.fika-swim{box-shadow:none!important;background:#fdeaf2!important;padding:0 10px!important}.fs-head,.fs-note{display:none!important}*{animation-play-state:paused!important;transition:none!important}.fs-lane{background-color:#ffffff!important;box-shadow:inset 0 0 0 2px #c9d9f2!important}.fs-ticks i.m{white-space:nowrap}.fs-ticks i[data-i="15"]::after{display:none!important}'; document.head.appendChild(x);
    }, d);
    await p.waitForTimeout(2500);
    await p.evaluate(() => { document.querySelectorAll('.fs-ticks i.m').forEach(e => { if (!/kg/.test(e.textContent)) e.textContent = e.textContent + ' kg'; }); });
    await p.evaluate((k) => { document.querySelectorAll('.fs-spin').forEach(e => { e.className = 'fs-spin ' + (k === '0' ? 'is-locked' : 'is-won'); }); }, k);
    await p.waitForTimeout(500);
    const sec = await p.$('.fika-swim');
    await p.addStyleTag({content:'.fs-fish{visibility:hidden!important}'});
    await sec.screenshot({ path: S+'/lane/lane-'+k+'.png' });
    if (k === '0') {
      await p.addStyleTag({content:'.fs-fish{visibility:visible!important}'});
      const fw = await p.evaluate(() => Math.round(document.querySelector('.fs-fish').getBoundingClientRect().width)); const fp = await ctx.newPage(); const svg = await p.evaluate(() => document.querySelector('.fs-fish svg').outerHTML);
      await fp.setContent('<html><body style="margin:0;background:transparent"><div id="f" style="width:' + fw + 'px;height:' + fw + 'px">' + svg.replace('<svg', '<svg width="' + fw + '" height="' + fw + '"') + '</div></body></html>');
      await (await fp.$('#f')).screenshot({ path: S+'/lane/fish.png', omitBackground: true }); await fp.close();
      const info = await p.evaluate(() => { const s=document.querySelector('.fika-swim').getBoundingClientRect(), l=document.querySelector('.fs-lane').getBoundingClientRect(), f=document.querySelector('.fs-fish').getBoundingClientRect(); const c=i=>{const r=document.querySelector('.fs-ticks i[data-i="'+i+'"]').getBoundingClientRect();return r.x+r.width/2-s.x;}; return {sec:[s.width,s.height], x0:c(0), x15:c(15), laneY:l.y-s.y+l.height/2, fish:[f.width,f.height]}; });
      console.log(JSON.stringify(info));
    }
  }
  await b.close();
})();
