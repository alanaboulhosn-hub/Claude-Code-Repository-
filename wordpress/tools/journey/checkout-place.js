const { chromium } = require('playwright');
const S = 'https://swedishfikalb.com', D = __dirname + '/';
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  for (const [dev, W, Hh] of [['phone', 390, 844], ['desk', 1440, 900]]) {
    const M = dev === 'phone';
    const ctx = await b.newContext(M ? { viewport: { width: W, height: Hh }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: W, height: Hh } });
    await ctx.route(/facebook|fika\/v1\/meta/, r => r.abort());
    let placed = 0, body = ''; await ctx.route(/wc\/store\/v1\/(checkout|batch)/, r => { const d = r.request().postData() || ''; if (/payment_method/.test(d)) { placed++; body = d; return r.abort(); } return r.continue(); });
    const p = await ctx.newPage(); const errs = [];
    p.on('pageerror', e => errs.push(e.message.slice(0, 140)));
    p.on('request', r => { if (/url\(/.test(r.url())) console.log(dev, 'ODD REQ', r.url().slice(0, 160), r.resourceType(), 'page', p.url()); });
    await p.goto(S + '/', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(12000);
    await p.evaluate(() => { sessionStorage.setItem('fika_drops_seen', '1'); localStorage.setItem('fika_bag_v1', JSON.stringify({ bag: { w120: 100, rw1200: 500 }, meta: {} })); });
    for (const u of ['/mix-your-own/', '/ready-mix/', '/ready-mix/', '/about-us/', '/ready-mix/']) { await p.goto(S + u, { waitUntil: 'load' }); await p.waitForTimeout(2500); }
    await p.evaluate(() => document.querySelector('.fika-cart').click()); await p.waitForTimeout(800);
    await Promise.all([p.waitForURL(/checkout/, { timeout: 40000 }), M ? p.locator('#mxGo').tap() : p.locator('#mxGo').click()]);
    await p.waitForLoadState('networkidle').catch(() => {}); await p.waitForTimeout(3500);
    const items = await p.evaluate(() => [...new Set([...document.querySelectorAll('.wc-block-components-order-summary-item')].filter(e => e.offsetParent).map(i => i.querySelector('.wc-block-components-product-name').textContent))]);
    const allItems = await p.evaluate(() => [...document.querySelectorAll('.wc-block-components-order-summary-item')].map(e => (e.offsetParent ? 'shown ' : 'hidden ') + e.querySelector('.wc-block-components-product-name').textContent));
    const fill = async (sel, v) => { const l = p.locator(sel).first(); if (await l.count() && await l.isVisible().catch(() => false)) { await l.fill(v); return 1; } return 0; };
    await fill('#email', 'claude.journey.test@example.com'); await fill('#contact-fika-phone', '70000000');
    await fill('#shipping-first_name', 'Test'); await fill('#shipping-last_name', 'Journey'); await fill('#shipping-address_1', 'Test street 1'); await fill('#shipping-city', 'Beirut');
    const sel = p.locator('.wc-block-checkout select:visible').first();
    const opts = await sel.evaluate(s => [...s.options].map(o => o.value + '=' + o.textContent)).catch(() => []);
    const beirut = opts.find(o => /beirut/i.test(o)); if (beirut) await sel.selectOption(beirut.split('=')[0]);
    await p.waitForTimeout(3000);
    const tot = await p.evaluate(() => [...document.querySelectorAll('.wc-block-components-totals-item')].filter(e => e.offsetParent).map(e => e.textContent.replace(/\s+/g, ' ').trim()));
    const pb = p.locator('.wc-block-components-checkout-place-order-button').first();
    await pb.scrollIntoViewIfNeeded(); await (M ? pb.tap() : pb.click()); await p.waitForTimeout(3500);
    const msgs = await p.evaluate(() => [...document.querySelectorAll('.wc-block-components-validation-error, .wc-block-store-notice, .woocommerce-error')].map(e => e.textContent.trim()).filter(Boolean));
    await p.screenshot({ path: D + dev + '-co2.png' });
    let sent = {}; try { const j = JSON.parse(body); sent = { pay: j.payment_method, area: JSON.stringify(j.shipping_address || {}).slice(0, 160) }; } catch (e) {}
    console.log(JSON.stringify({ dev, items, allItems, areaOptions: opts, chose: beirut, totals: tot, placed, sent, msgs, errs }, null, 1));
    await p.evaluate(() => { try { localStorage.clear(); } catch (e) {} });
    await ctx.close();
  }
  await b.close();
})();
