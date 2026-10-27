const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const p = await (await b.newContext({ viewport: { width: 708, height: 192 }, deviceScaleFactor: 1 })).newPage();
  await p.goto('file://' + __dirname + '/hwhead.html'); await p.waitForTimeout(800);
  await p.locator('#h').screenshot({ path: __dirname + '/fika-email-hw-logo.png', omitBackground: true });
  await b.close();
})();
