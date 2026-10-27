const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const p = await (await b.newContext({ viewport: { width: 1400, height: 520 } })).newPage();
  await p.goto('file://' + __dirname + '/letters.html'); await p.waitForTimeout(2500); await p.evaluate(() => document.fonts.ready);
  console.log(await p.evaluate(() => document.fonts.check('400px "Titan One"')));
  await p.locator('#t').screenshot({ path: __dirname + '/bubs.png' });
  await b.close();
})();
