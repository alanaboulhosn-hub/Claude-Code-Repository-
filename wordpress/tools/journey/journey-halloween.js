// the whole customer journey on the live shop with the Halloween look, phone and desktop. Nothing is ordered:
// the order request (payment_method) is stopped in the browser; Meta beacons are blocked.
const { chromium } = require('playwright');
const S = 'https://swedishfikalb.com', D = __dirname + '/';
const R = []; const ok = (dev, k, c, i = '') => R.push((c ? 'PASS ' : 'FAIL ') + dev + ' | ' + k + (i ? ' — ' + String(i).slice(0, 200) : ''));
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  for (const [dev, W, Hh] of [['phone', 390, 844], ['desk', 1440, 900]]) {
    const M = dev === 'phone';
    const ctx = await b.newContext(M ? { viewport: { width: W, height: Hh }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: W, height: Hh } });
    await ctx.route(/facebook|fika\/v1\/meta/, r => r.abort());
    let placed = 0; await ctx.route(/wc\/store\/v1\/(checkout|batch)/, r => { if (/payment_method/.test(r.request().postData() || '')) { placed++; return r.abort(); } return r.continue(); });
    const p = await ctx.newPage(); const errs = [], bad = [];
    p.on('pageerror', e => errs.push(p.url().replace(S, '') + ': ' + e.message.slice(0, 140)));
    p.on('console', m => { if (m.type() === 'error' && !/facebook|ERR_FAILED|net::/.test(m.text())) errs.push('console ' + p.url().replace(S, '') + ': ' + m.text().slice(0, 140)); });
    p.on('response', r => { if (r.status() >= 400 && r.url().startsWith(S)) bad.push(r.status() + ' ' + r.url().replace(S, '')); });
    const tap = l => M ? l.tap() : l.click();
    const page = async (path, tag) => {
      const t0 = Date.now(); await p.goto(S + path, { waitUntil: 'load' }); const ms = Date.now() - t0; await p.waitForTimeout(2500);
      const st = await p.evaluate(() => ({ hw: document.body.classList.contains('fika-hw'), over: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        broken: [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.getAttribute('src')).map(i => i.getAttribute('src').slice(-60)), title: document.title }));
      ok(dev, tag + ' loads (' + ms + ' ms)', ms < 15000, st.title);
      ok(dev, tag + ' no sideways scroll', st.over <= 1, st.over + 'px');
      ok(dev, tag + ' no broken pictures', !st.broken.length, st.broken.join(', '));
      return st;
    };
    // first visit: the CDN check
    await p.goto(S + '/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(12000); bad.length = 0;
    await p.evaluate(() => { try { sessionStorage.clear(); localStorage.removeItem('fika_bag_v1'); } catch (e) {} });
    // 1. arrival on the home page
    const h = await page('/?j=' + Date.now(), 'home');
    ok(dev, 'home Halloween look on', h.hw);
    const pop = await p.evaluate(() => { const v = document.getElementById('fkdVeil'); if (!v) return null; return { open: v.classList.contains('on'), names: [...v.querySelectorAll('.fkd-name')].map(x => x.textContent), imgs: [...v.querySelectorAll('img')].map(i => i.naturalWidth + ':' + i.getAttribute('src').split('/').pop()), badges: [...v.querySelectorAll('.fkd-badge')].map(x => x.textContent) }; });
    ok(dev, 'popup opens on arrival', pop && pop.open, JSON.stringify(pop));
    ok(dev, 'popup names Fan Favourites Mix + Skull Mix', pop && pop.names.join('|') === 'Fan Favourites Mix|Skull Mix', pop && pop.names.join('|'));
    ok(dev, 'popup pictures load (new picture)', pop && pop.imgs.every(s => !/^0:/.test(s)) && pop.imgs.some(s => /fan-favourites/.test(s)), pop && pop.imgs.join(', '));
    await p.screenshot({ path: D + dev + '-1-popup.png' });
    await tap(p.locator('.fkd-x').first()).catch(async () => { await p.keyboard.press('Escape'); }); await p.waitForTimeout(700);
    ok(dev, 'popup closes', await p.evaluate(() => !document.getElementById('fkdVeil').classList.contains('on')));
    // the look: eyes, header, pumpkin click glow
    const look = await p.evaluate(() => ({ eyes: document.querySelectorAll('.fika-eye').length, hdr: !!document.querySelector('.fika-header'), cart: !!document.querySelector('.fika-cart'), products: document.querySelectorAll('.mx-card').length }));
    ok(dev, 'home: header, bag button, eyes, products', look.hdr && look.cart && look.eyes > 0 && look.products > 0, JSON.stringify(look));
    await p.mouse.click(W / 2, Math.round(Hh * 0.55)); await p.waitForTimeout(250);
    await p.screenshot({ path: D + dev + '-2-home-click.png' });
    await p.evaluate(() => scrollTo(0, document.body.scrollHeight)); await p.waitForTimeout(1500);
    await p.screenshot({ path: D + dev + '-3-home-bottom.png' });
    await p.evaluate(() => scrollTo(0, 0));
    // popup once per visit
    await p.goto(S + '/?again=' + Date.now(), { waitUntil: 'load' }); await p.waitForTimeout(2000);
    ok(dev, 'popup not shown again in the same visit', await p.evaluate(() => { const v = document.getElementById('fkdVeil'); return !v || !v.classList.contains('on'); }));
    // 2. Mix your own: two sweets, 100 g each (+ then + and -)
    await page('/mix-your-own/?j=' + Date.now(), 'mix-your-own');
    const cards = p.locator('#mxGrid .mx-card, .mx-grid .mx-card');
    const nC = await cards.count(); ok(dev, 'mix-your-own lists sweets', nC > 5, nC + ' cards');
    for (const i of [0, 1]) { const c = cards.nth(i); await c.scrollIntoViewIfNeeded(); await tap(c.locator('button[data-a="+"]')); await p.waitForTimeout(250); }
    const c1 = cards.nth(1); await tap(c1.locator('button[data-a="+"]')); await p.waitForTimeout(250); await tap(c1.locator('button[data-a="-"]')); await p.waitForTimeout(250);
    let bag = await p.evaluate(() => JSON.parse(localStorage.getItem('fika_bag_v1') || '{}').bag || {});
    ok(dev, 'mix: + and - change the bag', Object.keys(bag).length === 2 && Object.values(bag).every(g => g === 100), JSON.stringify(bag));
    await p.screenshot({ path: D + dev + '-4-mix.png' });
    // 3. Ready Mix: the two Halloween bags first, Fan Favourites Mix added from the grid
    await page('/ready-mix/?j=' + Date.now(), 'ready-mix');
    await p.evaluate(() => { const g = document.getElementById('rmGrid'); if (g) g.scrollIntoView({ block: 'center' }); }); await p.waitForTimeout(1200);
    const grid = await p.evaluate(() => [...document.querySelectorAll('#rmGrid .mx-card')].slice(0, 3).map(c => ({ id: c.getAttribute('data-id'), name: c.querySelector('.mx-name').textContent, label: getComputedStyle(c.querySelector('.mx-img'), '::after').content, img: (c.querySelector('img') || {}).currentSrc || getComputedStyle(c.querySelector('.mx-img')).backgroundImage })));
    ok(dev, 'ready-mix: Fan Favourites Mix and Skull Mix first, labelled', grid[0] && grid[0].name === 'Fan Favourites Mix' && /Limited edition/.test(grid[0].label) && grid[1].name === 'Skull Mix' && /Halloween offer/.test(grid[1].label), JSON.stringify(grid.slice(0, 2)));
    ok(dev, 'ready-mix: Fan Favourites shows the new photo', grid[0] && /fan-favourites/.test(grid[0].img), grid[0] && grid[0].img.slice(-70));
    await p.screenshot({ path: D + dev + '-5-ready.png' });
    await tap(p.locator('#rmGrid .mx-card[data-id="rw1200"] button[data-a="+"]')); await p.waitForTimeout(400);
    bag = await p.evaluate(() => JSON.parse(localStorage.getItem('fika_bag_v1') || '{}').bag || {});
    ok(dev, 'ready-mix: Fan Favourites 500 g in the bag', bag.rw1200 === 500, JSON.stringify(bag));
    // 4. product page and old link
    await page('/product/ready-fan-favourites-mix/', 'product page');
    const pp = await p.evaluate(() => ({ h1: (document.querySelector('h1') || {}).textContent, price: (document.querySelector('.price, .wc-block-components-product-price') || {}).textContent }));
    ok(dev, 'product page: name and $14', /Fan Favourites Mix/.test(pp.h1 || '') && /14/.test(pp.price || ''), JSON.stringify(pp));
    await p.screenshot({ path: D + dev + '-6-product.png' });
    const old = await p.goto(S + '/product/ready-limited-edition-drop/', { waitUntil: 'load' });
    ok(dev, 'old product link forwards to the new one', /ready-fan-favourites-mix/.test(p.url()), old.status() + ' ' + p.url());
    // 5. About us
    await page('/about-us/?j=' + Date.now(), 'about-us');
    await p.screenshot({ path: D + dev + '-7-about.png' });
    // 6. bag drawer
    await p.evaluate(() => scrollTo(0, 0)); await p.waitForTimeout(500);
    await tap(p.locator('.fika-cart').first()); await p.waitForTimeout(1000);
    const dr = await p.evaluate(() => ({ open: document.getElementById('mxDrawer').classList.contains('on'), lines: [...document.querySelectorAll('#mxDrawer .mx-line')].map(l => l.textContent.replace(/\s+/g, ' ').trim()), sub: document.getElementById('mxSub').textContent, tot: document.getElementById('mxTot').textContent, kg: document.getElementById('mxKg').textContent }));
    ok(dev, 'bag drawer opens with 3 lines', dr.open && dr.lines.length === 3, JSON.stringify(dr));
    await p.screenshot({ path: D + dev + '-8-bag.png' });
    // remove one sweet in the drawer
    await tap(p.locator('#mxDrawer .mx-rm').nth(1)); await p.waitForTimeout(500);
    const dr2 = await p.evaluate(() => ({ lines: document.querySelectorAll('#mxDrawer .mx-line').length, sub: document.getElementById('mxSub').textContent, tot: document.getElementById('mxTot').textContent }));
    ok(dev, 'bag: remove a line updates the totals', dr2.lines === 2 && dr2.sub !== dr.sub, JSON.stringify(dr2));
    // 7. checkout
    await Promise.all([p.waitForURL(/checkout/, { timeout: 40000 }), tap(p.locator('#mxGo'))]);
    await p.waitForLoadState('networkidle').catch(() => {}); await p.waitForTimeout(3500);
    const co = await p.evaluate(() => ({ items: [...document.querySelectorAll('.wc-block-components-order-summary-item')].map(i => i.textContent.replace(/\s+/g, ' ').trim().slice(0, 70)), total: (document.querySelector('.wc-block-components-totals-footer-item') || {}).textContent, over: document.documentElement.scrollWidth - document.documentElement.clientWidth, hw: document.body.classList.contains('fika-hw'), popup: !!(document.getElementById('fkdVeil') || {}).classList?.contains('on'), fields: [...document.querySelectorAll('.wc-block-checkout input:not([type=hidden]), .wc-block-checkout select, .wc-block-checkout textarea')].filter(e => e.offsetParent).map(e => e.id || e.name) }));
    ok(dev, 'checkout: both lines from the bag', co.items.length === 2 && co.items.some(t => /Fan Favourites Mix/.test(t)), JSON.stringify(co.items));
    ok(dev, 'checkout: total = bag total', (co.total || '').replace(/[^\d.]/g, '').includes(dr2.tot.replace(/[^\d.]/g, '')), (co.total || '').trim() + ' vs bag ' + dr2.tot);
    ok(dev, 'checkout: no popup, no sideways scroll', !co.popup && co.over <= 1, JSON.stringify({ popup: co.popup, over: co.over }));
    await p.screenshot({ path: D + dev + '-9-checkout.png', fullPage: true });
    // fill in like a customer and press Place order (the order request is stopped before it leaves the browser)
    const fill = async (sel, v) => { const l = p.locator(sel).first(); if (await l.count() && await l.isVisible().catch(() => false)) { await l.fill(v); return 1; } return 0; };
    let f = 0;
    f += await fill('#email', 'claude.journey.test@example.com');
    f += await fill('#shipping-first_name, #billing-first_name', 'Test');
    f += await fill('#shipping-last_name, #billing-last_name', 'Journey');
    f += await fill('#shipping-address_1, #billing-address_1', 'Test street 1');
    f += await fill('#shipping-city, #billing-city', 'Beirut');
    f += await fill('#shipping-phone, #billing-phone', '70000000');
    await p.waitForTimeout(1500);
    const pb = p.locator('.wc-block-components-checkout-place-order-button').first();
    const placeTxt = await pb.textContent().catch(() => '');
    await pb.scrollIntoViewIfNeeded().catch(() => {}); await tap(pb).catch(() => {}); await p.waitForTimeout(3000);
    const errsCo = await p.evaluate(() => [...document.querySelectorAll('.wc-block-components-validation-error, .wc-block-store-notice, .woocommerce-error')].map(e => e.textContent.trim()).filter(Boolean).slice(0, 5));
    ok(dev, 'checkout: form fills, Place order sends the order (stopped)', placed === 1, 'filled ' + f + ' fields, button "' + (placeTxt || '').trim() + '", stopped requests ' + placed + ', messages ' + JSON.stringify(errsCo) + ', fields ' + co.fields.join(','));
    await p.screenshot({ path: D + dev + '-10-placed.png' });
    // 8. My account and a missing page
    await page('/my-account/', 'my-account');
    ok(dev, 'my-account: login form', await p.locator('#username').count() > 0);
    const r404 = await p.goto(S + '/no-such-page-' + Date.now() + '/', { waitUntil: 'load' }); await p.waitForTimeout(1500);
    ok(dev, 'missing page: 404 with the shop header', r404.status() === 404 && await p.locator('.fika-header').count() > 0, r404.status());
    await p.screenshot({ path: D + dev + '-11-404.png' });
    bad.splice(0, bad.length, ...bad.filter(x => !/no-such-page/.test(x)));
    ok(dev, 'no failed shop requests', !bad.length, bad.join(', '));
    ok(dev, 'no script errors', !errs.length, errs.join(' || '));
    // empty the test bag and cart
    await p.goto(S + '/ready-mix/', { waitUntil: 'load' });
    await p.evaluate(async () => { try { localStorage.clear(); } catch (e) {} try { const c = await (await fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' })).json(); const n = c && c.items; } catch (e) {} });
    await ctx.close();
  }
  await b.close();
  console.log(R.join('\n'));
})();
