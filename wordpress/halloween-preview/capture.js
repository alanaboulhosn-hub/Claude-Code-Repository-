// Snapshot of the shop with the Halloween look on, as browsable static pages (test browser only, nothing live).
// node capture.js <outdir> <hw.css> <hw.js>
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const [out, cssF, jsF, dev] = process.argv.slice(2);
const M = dev === 'phone';
const css = fs.readFileSync(cssF, 'utf8'), js = fs.readFileSync(jsF, 'utf8');
const SITE = 'https://swedishfikalb.com';
const PAGES = { '/': 'index.html', '/mix-your-own/': 'mix-your-own.html', '/ready-mix/': 'ready-mix.html', '/about-us/': 'about-us.html',
  '/my-account/': 'my-account.html', '/checkout/': 'checkout.html', '/cart/': 'cart.html' };
fs.mkdirSync(path.join(out, 'a'), { recursive: true });

// runs in the page: freeze the rendered page into plain HTML with absolute asset URLs and no scripts
function freeze() {
  const abs = (u, base) => { try { return new URL(u, base || location.href).href; } catch (e) { return u; } };
  const fixUrls = (txt, base) => txt.replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g, (m, q, u) => u.startsWith('data:') ? m : 'url("' + abs(u, base) + '")');
  // stylesheets from the site become inline <style>; Google Fonts stay links
  for (const sh of [...document.styleSheets]) {
    const node = sh.ownerNode;
    if (!node) continue;
    if (node.tagName === 'LINK' && /fonts\.googleapis/.test(node.href)) continue;
    if (node.tagName === 'STYLE') { node.textContent = fixUrls(node.textContent, location.href); continue; } // kept exactly as written
    let txt = '';
    try { txt = [...sh.cssRules].map(r => r.cssText).join('\n'); } catch (e) { if (node.tagName === 'LINK') { node.remove(); } continue; }
    const st = document.createElement('style');
    st.textContent = fixUrls(txt, sh.href || location.href);
    if (node.media && node.media.mediaText) st.media = node.media.mediaText;
    node.replaceWith(st);
  }
  document.querySelectorAll('[style]').forEach(e => e.setAttribute('style', fixUrls(e.getAttribute('style'))));
  document.querySelectorAll('img').forEach(i => { if (i.currentSrc) i.src = i.currentSrc; i.removeAttribute('srcset'); i.removeAttribute('sizes'); i.loading = 'eager'; });
  document.querySelectorAll('picture source').forEach(s => s.remove());
  document.querySelectorAll('video').forEach(v => { if (v.currentSrc) v.src = v.currentSrc; if (v.poster) v.poster = abs(v.poster); v.querySelectorAll('source').forEach(s => s.remove()); });
  document.querySelectorAll('script, noscript, iframe, link[rel=preload], link[rel=prefetch], link[rel=modulepreload], link[rel=preconnect], link[rel=dns-prefetch], link[rel=alternate], link[rel="https://api.w.org/"], link[rel=EditURI], link[rel=pingback]').forEach(e => e.remove());
  document.querySelectorAll('#wpadminbar').forEach(e => e.remove());
  document.querySelectorAll('a[href]').forEach(a => a.setAttribute('href', abs(a.getAttribute('href'))));
  return '<!doctype html>\n' + document.documentElement.outerHTML;
}

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const ctx = await b.newContext(M ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: 1400, height: 900 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36' });
  const p = await ctx.newPage();
  await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.route(/swedishfikalb\.com\/([^?#]*\/)?(\?[^#]*)?$/, async r => {
    if (r.request().resourceType() !== 'document') return r.continue();
    const res = await r.fetch(); let h = await res.text();
    h = h.replace(/<body([^>]*?)class="/, '<body$1class="fika-hw ').replace('</head>', '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Creepster&display=swap"><style id="fika-hw">' + css + '</style></head>').replace(/<\/body>(?![\s\S]*<\/body>)/, '<script>' + js + '</script></body>');
    r.fulfill({ response: res, body: h });
  });
  const settle = async () => {
    await p.waitForTimeout(2500);
    await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 150)); } window.scrollTo(0, 0); });
    await p.waitForTimeout(1500);
  };
  const html = {};
  await p.goto(SITE + '/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(12000);
  for (const u of ['/', '/mix-your-own/', '/ready-mix/', '/about-us/', '/my-account/']) {
    await p.goto(SITE + u, { waitUntil: 'networkidle', timeout: 60000 }); await settle();
    html[u] = await p.evaluate(freeze); console.log('captured', u, html[u].length);
  }
  // a bag with two sweets, then checkout and cart (nothing is ordered)
  await p.goto(SITE + '/mix-your-own/', { waitUntil: 'networkidle' }); await p.waitForTimeout(2500);
  await p.evaluate(() => window.scrollTo(0, 700)); await p.waitForTimeout(1200);
  await p.locator('.mx-add').nth(0).click(); await p.waitForTimeout(800); await p.locator('.mx-add').nth(1).click(); await p.waitForTimeout(800);
  await p.evaluate(() => window.scrollTo(0, 0)); await p.locator('.fika-cart').first().click(); await p.waitForTimeout(1200);
  await Promise.all([p.waitForURL(/checkout/, { timeout: 30000 }), p.locator('.mx-go').click()]);
  await p.waitForLoadState('networkidle'); await settle();
  html['/checkout/'] = await p.evaluate(freeze); console.log('captured /checkout/', html['/checkout/'].length);
  await p.goto(SITE + '/cart/', { waitUntil: 'networkidle' }); await settle();
  html['/cart/'] = await p.evaluate(freeze); console.log('captured /cart/', html['/cart/'].length);

  // download every asset the pages use, from the site or WordPress's image CDN
  const assets = {};
  const re = /https:\/\/(?:swedishfikalb\.com|i[0-9]\.wp\.com|[a-z0-9.-]*hostingersite\.com)\/[^\s"'()<>\\]+?\.(?:jpe?g|png|webp|gif|svg|avif|woff2?|ttf|otf|mp4|webm|mov|ico)(?:\?[^\s"'()<>\\]*)?(?=["'()\s<>\\]|&quot;)/gi;
  for (const h of Object.values(html)) for (const m of h.matchAll(re)) assets[m[0].replace(/&amp;/g, '&')] = null;
  let bytes = 0, n = 0;
  for (const u of Object.keys(assets)) {
    try {
      const res = await ctx.request.get(u, { timeout: 60000 });
      if (!res.ok()) { console.log('skip', res.status(), u.slice(0, 100)); continue; }
      const body = await res.body();
      const ext = (u.split('?')[0].match(/\.([a-z0-9]+)$/i) || [, 'bin'])[1].toLowerCase();
      const name = 'a/' + crypto.createHash('sha1').update(u).digest('hex').slice(0, 14) + '.' + ext;
      fs.writeFileSync(path.join(out, name), body); assets[u] = name; bytes += body.length; n++;
    } catch (e) { console.log('fail', u.slice(0, 100), e.message.slice(0, 60)); }
  }
  console.log('assets', n, Math.round(bytes / 1048576) + ' MB');
  fs.writeFileSync(path.join(out, M ? 'pages-phone.json' : 'pages.json'), JSON.stringify({ html, assets, PAGES }));
  await b.close();
})();
