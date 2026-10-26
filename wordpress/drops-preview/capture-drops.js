// the live home page with the drops popup open (injected in the test browser only), frozen to HTML:
// the Halloween look (the popup drops with it), computer + phone. node capture-drops.js <out>
const { chromium } = require('playwright'); const fs = require('fs'), path = require('path'), crypto = require('crypto');
const out = process.argv[2];
const inj = fs.readFileSync('inject.html', 'utf8');
const css = fs.readFileSync('../hw/build2/hw.css', 'utf8'), js = fs.readFileSync('../hw/build2/hw.js', 'utf8');
const freeze = new Function('return ' + fs.readFileSync('../hw/capture.js', 'utf8').match(/function freeze\(\) \{[\s\S]*?\n\}/)[0])();
fs.mkdirSync(path.join(out, 'a'), { recursive: true });
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const html = {};
  for (const [dev, o] of [['desk', { viewport: { width: 1440, height: 900 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36' }],
    ['phone', { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' }]]) {
    const ctx = await b.newContext(o); const p = await ctx.newPage(); await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
    let hw = false;
    await p.route(/swedishfikalb\.com\/([^?#]*\/)?(\?[^#]*)?$/, async r => { if (r.request().resourceType() !== 'document') return r.continue(); const res = await r.fetch(); let h = await res.text();
      if (hw) h = h.replace(/<body([^>]*?)class="/, '<body$1class="fika-hw ').replace('</head>', '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Creepster&display=swap"><style id="fika-hw">' + css + '</style></head>').replace(/<\/body>(?![\s\S]*<\/body>)/, '<script>' + js + '</script></body>');
      h = h.replace(/<\/body>(?![\s\S]*<\/body>)/, inj + '</body>'); r.fulfill({ response: res, body: h }); });
    await p.goto('https://swedishfikalb.com/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(12000);
    await p.route(/fika-drop-(skull-mix|limited-edition)\.webp/, r => r.fulfill({ status: 200, contentType: 'image/webp', body: fs.readFileSync('../drops2/web/' + r.request().url().split('/').pop()) }));
    for (const look of ['hw']) {
      hw = look === 'hw';
      await p.evaluate(() => { try { sessionStorage.clear(); } catch (e) {} });
      await p.goto('https://swedishfikalb.com/?nc=' + Date.now(), { waitUntil: 'networkidle' }); await p.waitForTimeout(2500);
      // the page under the popup as it first shows (the popup's own script opens it again in the preview)
      await p.evaluate(() => { const v = document.getElementById('fkdVeil'); v.classList.remove('on'); v.hidden = true; document.documentElement.style.overflow = ''; });
      await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
      await p.waitForTimeout(1200);
      html[dev + '-' + look] = await p.evaluate(freeze); console.log('captured', dev, look);
    }
    await ctx.close();
  }
  const assets = {};
  const re = /https:\/\/(?:swedishfikalb\.com|i[0-9]\.wp\.com)\/[^\s"'()<>\\]+?\.(?:jpe?g|png|webp|gif|svg|avif|woff2?|ttf|otf|mp4|webm|mov|ico)(?:\?[^\s"'()<>\\]*)?(?=["'()\s<>\\]|&quot;)/gi;
  for (const h of Object.values(html)) for (const m of h.matchAll(re)) assets[m[0].replace(/&amp;/g, '&')] = null;
  const ctx = await b.newContext();
  for (const u of Object.keys(assets)) {
    try { const res = await ctx.request.get(u, { timeout: 60000 }); if (!res.ok()) continue; const body = await res.body();
      const ext = (u.split('?')[0].match(/\.([a-z0-9]+)$/i) || [, 'bin'])[1].toLowerCase(); const name = 'a/' + crypto.createHash('sha1').update(u).digest('hex').slice(0, 14) + '.' + ext;
      fs.writeFileSync(path.join(out, name), body); assets[u] = name; } catch (e) {}
  }
  fs.writeFileSync(path.join(out, 'drops.json'), JSON.stringify({ html, assets }));
  console.log('assets', Object.values(assets).filter(Boolean).length);
  await b.close();
})();
