// Fika nightly site check: can a customer reach the shop, fill a bag and get to "Place order"? Phone and computer.
// Safe on the live shop: no order (the order request is stopped in the browser), no email typed at checkout (so no
// bag-reminder email), no exit-survey answer, no account; Meta beacons are blocked.
// Usage: node wordpress/tools/site-check.js [report.json]
//   Exit code 0 = all PASS (WARN lines are for the owner but not outages), 1 = at least one FAIL.
//   With WP_USER / WP_APP_PASSWORD set it also checks the shop's API side (orders, snippets, plugin versions).
// Compares plugin / WordPress versions with wordpress/tools/site-check-versions.json and reports updates as WARN.
const fs = require('fs'), path = require('path');
const { chromium, devices } = require('playwright');
const S = 'https://swedishfikalb.com';
const R = []; const res = (lvl, dev, what, info = '') => R.push({ lvl, dev, what, info: String(info).slice(0, 300) });
const pass = (d, w, i) => res('PASS', d, w, i), fail = (d, w, i) => res('FAIL', d, w, i), warn = (d, w, i) => res('WARN', d, w, i);
const check = (c, d, w, i) => (c ? pass : fail)(d, w, i);

async function api(p) {
  if (!process.env.WP_USER) return null;
  const auth = 'Basic ' + Buffer.from(process.env.WP_USER + ':' + process.env.WP_APP_PASSWORD).toString('base64');
  const r = await fetch(S + '/wp-json' + p, { headers: { Authorization: auth } });
  if (!r.ok) throw new Error(p + ' -> HTTP ' + r.status);
  return r.json();
}

async function serverChecks() {
  if (!process.env.WP_USER) { warn('api', 'API checks skipped', 'WP_USER / WP_APP_PASSWORD not set'); return; }
  try {
    const since = new Date(Date.now() - 24 * 3600e3).toISOString().slice(0, 19);
    const orders = await api('/wc/v3/orders?per_page=100&status=any&after=' + since);
    const real = orders.filter(o => ['processing', 'completed', 'on-hold'].includes(o.status));
    const failed = orders.filter(o => ['failed', 'pending', 'checkout-draft'].includes(o.status));
    pass('api', 'orders API answers', real.length + ' orders in the last 24 h' + (failed.length ? ', ' + failed.length + ' failed/pending' : ''));
    if (real.length === 0) warn('api', 'no orders in the last 24 hours', 'normal is about 5 a day: look at the site and Meta events');
    if (failed.length >= 3) warn('api', failed.length + ' failed or unfinished checkouts in 24 h', failed.map(o => o.id).join(', '));
  } catch (e) { fail('api', 'orders API', e.message); }
  try {
    const sn = await api('/code-snippets/v1/snippets');
    const list = Array.isArray(sn) ? sn : (sn.snippets || []);
    const bad = list.filter(s => s.active && !s.trashed && s.code_error);
    // every snippet that was on at the last known-good check must still be on (a failed save can switch one off)
    const vf = path.join(__dirname, 'site-check-versions.json');
    const want = (fs.existsSync(vf) ? JSON.parse(fs.readFileSync(vf, 'utf8')) : {})._active_snippets || [];
    const on = list.filter(s => s.active && !s.trashed).map(s => s.id);
    const off = want.filter(id => !on.includes(id)).map(id => { const s = list.find(x => x.id === id); return id + (s ? ' ' + s.name : ' (gone)'); });
    check(off.length === 0, 'api', 'all shop snippets switched on', off.length ? 'OFF: ' + off.join('; ') : on.length + ' on');
    module.exports.activeSnippets = on.sort((a, b) => a - b);
    check(bad.length === 0, 'api', 'active snippets without errors', bad.length ? bad.map(s => s.id + ' ' + s.name + ': ' + JSON.stringify(s.code_error)).join('; ') : list.filter(s => s.active).length + ' active');
  } catch (e) { warn('api', 'snippet list', e.message); }
  try {
    const plugins = await api('/wp/v2/plugins');
    const now = {}; plugins.filter(p => p.status === 'active').forEach(p => { now[p.plugin.split('/')[0]] = p.version; });
    const root = await (await fetch(S + '/', { headers: { 'User-Agent': 'Mozilla/5.0' } })).text();
    const wp = (root.match(/WordPress ([\d.]+)/) || [])[1]; if (wp) now.wordpress = wp;
    const f = path.join(__dirname, 'site-check-versions.json');
    const known = fs.existsSync(f) ? JSON.parse(fs.readFileSync(f, 'utf8')) : {};
    const changed = Object.keys(now).filter(k => known[k] !== now[k]).map(k => k + ' ' + (known[k] || 'new') + ' -> ' + now[k]);
    if (changed.length) warn('api', 'software updated since the last known versions', changed.join('; ') + ' (if the check passes, record them in site-check-versions.json)');
    else pass('api', 'no software updates since last check', Object.entries(now).map(([k, v]) => k + ' ' + v).join(', '));
    module.exports.versions = now;
  } catch (e) { warn('api', 'plugin versions', e.message); }
}

async function browse(b, dev, opt) {
  const ctx = await b.newContext({ ...opt, ignoreHTTPSErrors: true });
  await ctx.route(/facebook|fika\/v1\/meta|exit-reason/, r => r.abort());
  let orderTried = 0;
  await ctx.route(/wc\/store\/v1\/(checkout|batch)/, r => {
    const q = r.request(), body = q.postData() || '';
    // only the order itself: POST /checkout, or a batch that carries the checkout (payment method); delivery-area
    // updates (cart/update-customer) must go through so the delivery fee is worked out
    if (q.method() === 'POST' && (/\/wc\/store\/v1\/checkout(\?|$)/.test(q.url()) || /payment_method/.test(body))) { orderTried++; return r.abort(); }
    return r.continue();
  });
  const p = await ctx.newPage(); const errs = [], bad = [];
  p.on('pageerror', e => errs.push(p.url().replace(S, '') + ': ' + e.message.slice(0, 140)));
  p.on('response', r => { const u = r.url(); if (r.status() >= 400 && u.startsWith(S) && !/\/meta|exit-reason|this-page-does-not-exist/.test(u)) bad.push(r.status() + ' ' + u.replace(S, '').slice(0, 90)); });
  const go = async (u) => {
    let r = await p.goto(S + u, { waitUntil: 'networkidle', timeout: 60000 }).catch(e => ({ status: () => 'ERR ' + e.message.slice(0, 60) }));
    if (/checking your browser|just a moment/i.test(await p.title().catch(() => ''))) { await p.waitForTimeout(8000); r = await p.reload({ waitUntil: 'networkidle' }); }
    await p.waitForTimeout(700);
    if (await p.locator('#fkdVeil.on').isVisible().catch(() => false)) await p.locator('.fkd-later, .fkd-x').first().click().catch(() => {});
    return r && r.status ? r.status() : 0;
  };
  const closeDrawer = async () => { if (await p.locator('.mx-drawer.on').isVisible().catch(() => false)) { await p.locator('.mx-drawer .mx-x').click(); await p.waitForTimeout(300); } };
  const bag = () => p.evaluate(() => { try { return JSON.parse(localStorage.getItem('fika_bag_v1') || '{"bag":{}}').bag; } catch (e) { return {}; } });

  // 1. the pages customers land on
  const pages = [['/', '#shop .mx-card', 1], ['/mix-your-own/', '.mx-card', 10], ['/ready-mix/', '.mx-card', 1], ['/about-us/', '.fika-about, .fika-hero', 1]];
  for (const [u, sel, min] of pages) {
    const st = await go(u);
    const info = await p.evaluate(sel => ({ h: document.body.scrollHeight, cards: [...document.querySelectorAll(sel)].filter(e => e.offsetParent).length, header: !!document.querySelector('.fika-header'), archive: document.body.className.includes('page-template-archive-product'), wide: document.documentElement.scrollWidth - innerWidth, broken: [...document.images].filter(i => i.offsetParent && i.complete && i.naturalWidth === 0).length }), sel);
    check(st === 200 && info.header && info.cards >= min && info.h > 1000 && !info.archive, dev, 'page ' + u + ' shows header and content', `status ${st}, ${info.cards} items, height ${info.h}${info.archive ? ', WooCommerce catalogue template!' : ''}${info.header ? '' : ', NO HEADER'}`);
    if (info.wide > 2) fail(dev, 'page ' + u + ' scrolls sideways', info.wide + ' px');
    if (info.broken) warn(dev, 'page ' + u + ' has broken images', info.broken);
  }
  for (const [u, to] of [['/shop/', '/mix-your-own/'], ['/cart/', '/mix-your-own/']]) { await go(u); check(p.url().includes(to), dev, u + ' leads to ' + to, p.url().replace(S, '')); }
  for (const u of ['/my-account/', '/my-account/lost-password/']) { const st = await go(u); const f = await p.locator('form:visible').count(); check(st === 200 && f > 0, dev, 'page ' + u + ' has its form', 'status ' + st + ', ' + f + ' forms'); }

  // 2. fill a bag: a candy (+ one more 100 g) and a Ready Mix
  await go('/mix-your-own/');
  const card = p.locator('.mx-card:visible').first();
  const candy = (await card.locator('.mx-name').innerText().catch(() => '')).trim();
  await card.locator('.mx-add').click().catch(() => {}); await p.waitForTimeout(600); await closeDrawer();
  await card.locator('[data-a="+"]').click().catch(() => {}); await p.waitForTimeout(500);
  let b1 = await bag(); const g = Object.values(b1).reduce((a, x) => a + x, 0);
  check(g >= 100, dev, 'Add to bag works on Mix your own', candy + ': ' + g + ' g in the bag');
  const filters = await p.locator('.fs-chip').count(); check(filters >= 3, dev, 'filters show', filters + ' chips');
  await go('/ready-mix/');
  await p.locator('.mx-card:visible .mx-add').first().click().catch(() => {}); await p.waitForTimeout(600); await closeDrawer();
  const b2 = await bag(); check(Object.keys(b2).some(k => /^rw/.test(k)), dev, 'Ready Mix adds a bag', JSON.stringify(b2));
  // 3. bag -> checkout
  await p.locator('a.fika-cart').click().catch(() => {}); await p.waitForTimeout(1000);
  const drawerOk = await p.locator('.mx-drawer.on').isVisible().catch(() => false);
  check(drawerOk, dev, 'bag opens', (await p.locator('.mx-drawer').innerText().catch(() => '')).replace(/\s+/g, ' ').match(/Total \$[\d.]+/)?.[0] || '');
  await Promise.all([p.waitForURL(/\/checkout\//, { timeout: 40000 }).catch(() => {}), p.locator('.mx-drawer a.mx-go').click().catch(() => {})]);
  await p.waitForLoadState('networkidle').catch(() => {}); await p.waitForTimeout(2500);
  const onCheckout = /\/checkout\//.test(p.url());
  const items = await p.locator('.wc-block-components-order-summary-item').count();
  check(onCheckout && items >= 2, dev, 'bag Checkout opens checkout with the bag', p.url().replace(S, '') + ', ' + items + ' lines');
  if (onCheckout) {
    const place = p.locator('.wc-block-components-checkout-place-order-button:visible, button:visible:has-text("Place order")').first();
    check(await place.count() > 0, dev, 'Place order button shows');
    check(/cash on delivery/i.test(await p.locator('body').innerText()), dev, 'cash on delivery offered');
    const area = p.locator('select:visible').filter({ has: p.locator('option', { hasText: /Beirut/ }) }).first();
    for (const [o, fee] of [['Inside Beirut', '5.00'], ['Outside Beirut', '6.00']]) {
      await area.selectOption({ label: o }).catch(() => {}); await p.waitForTimeout(3000);
      // the totals fill in a few seconds after the area changes: wait for the delivery line (up to 15 s)
      await p.locator('.wc-block-components-totals-item').first().scrollIntoViewIfNeeded().catch(() => {});
      let line = '';
      for (let i = 0; i < 15 && !line.includes('$' + fee); i++) {
        line = (await p.$$eval('.wc-block-components-totals-item', a => a.map(x => x.innerText.replace(/\s+/g, ' ')).find(x => /deliver/i.test(x)) || '').catch(() => '')) || '';
        if (!line.includes('$' + fee)) await p.waitForTimeout(1000);
      }
      check(line.includes('$' + fee), dev, 'delivery fee ' + o + ' $' + fee, line || 'not shown');
    }
    await place.click().catch(() => {}); await p.waitForTimeout(2500);
    const v = await p.locator('.wc-block-components-validation-error:visible').count();
    check(v > 0, dev, 'empty form is stopped with error messages', v + ' messages');
  }
  check(orderTried === 0, dev, 'no order was sent by the check');
  // 4. 404
  const st404 = await go('/this-page-does-not-exist/');
  check(st404 === 404 && await p.locator('.fika-header').count() > 0, dev, 'unknown address shows the Fika "page not found"', 'status ' + st404);
  check(errs.length === 0, dev, 'no page script errors', errs.slice(0, 4).join(' | '));
  if (bad.length) warn(dev, 'failed requests', [...new Set(bad)].slice(0, 6).join(' | '));
  await p.screenshot({ path: path.join(process.env.CHECK_OUT || require('os').tmpdir(), 'site-check-' + dev + '.png') }).catch(() => {});
  await ctx.close();
}

(async () => {
  const t0 = Date.now();
  await serverChecks();
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined, args: ['--ignore-certificate-errors'] });
  for (const [dev, opt] of [['phone', devices['iPhone 13']], ['computer', { viewport: { width: 1366, height: 850 } }]]) {
    try { await browse(b, dev, opt); } catch (e) { fail(dev, 'check crashed', e.message); }
  }
  await b.close();
  const n = l => R.filter(r => r.lvl === l).length;
  for (const r of R) console.log(r.lvl.padEnd(5), r.dev.padEnd(8), r.what + (r.info ? ' — ' + r.info : ''));
  console.log(`\nRESULT: ${n('FAIL') ? 'PROBLEM' : 'OK'} — ${n('PASS')} pass, ${n('FAIL')} fail, ${n('WARN')} warn, ${Math.round((Date.now() - t0) / 1000)} s, ${new Date().toISOString()}`);
  if (process.argv[2]) fs.writeFileSync(process.argv[2], JSON.stringify({ at: new Date().toISOString(), results: R, versions: module.exports.versions || null, active_snippets: module.exports.activeSnippets || null }, null, 1));
  process.exit(n('FAIL') ? 1 : 0);
})();
