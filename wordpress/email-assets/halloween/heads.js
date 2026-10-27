const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const p = await (await b.newContext({ viewport: { width: 1400, height: 800 }, deviceScaleFactor: 1 })).newPage();
  await p.goto('file://' + __dirname + '/heads.html'); await p.waitForTimeout(2500); await p.evaluate(() => document.fonts.ready);
  console.log(await p.evaluate(() => document.fonts.check('60px Creepster')));
  const names = { t1: 'your-bag-is-waiting', t2: 'your-bag', t3: 'delivery', t4: 'new-this-halloween' };
  for (const [id, n] of Object.entries(names)) await p.locator('#' + id).screenshot({ path: __dirname + `/fika-email-hw-h-${n}.png`, omitBackground: true });
  await b.close();
})();
