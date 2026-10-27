const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const p = await (await b.newContext({ deviceScaleFactor: 1 })).newPage();
  await p.goto('file://' + __dirname + '/stickers2.html'); await p.waitForTimeout(2500);
  await p.evaluate(() => document.fonts.ready);
  for (const id of ['fanref', 'fan2']) await p.locator('#' + id).screenshot({ path: __dirname + '/sticker-' + id + '.png', omitBackground: true });
  await b.close();
})();
