// the owner's Halloween ad, in the website's Halloween colours: night black, orange, bone; the shop's fonts
const { chromium } = require('playwright'); const fs = require('fs');
const C = __dirname + '/../drops2/cut/';
const d = f => 'data:image/png;base64,' + fs.readFileSync(C + f).toString('base64');
const BAT = '<svg viewBox="0 0 64 32"><path fill="currentColor" d="M32 9c-1.2 0-2 .9-2.4 2.1L28.4 9l-.9 3.3C24 7.6 16.6 4.3 8 5.6c3.3 1.7 5 4.4 4.9 7.4C9.6 11.6 5 11.9 0 14.9c5.6.1 10.4 2.6 12.6 6.6 1.5-2.4 4.3-3.4 6.6-2.3.4-2.4 2.9-3.7 5.2-2.7 1.9.9 3.7 3.6 7.6 9.5 3.9-5.9 5.7-8.6 7.6-9.5 2.3-1 4.8.3 5.2 2.7 2.3-1.1 5.1-.1 6.6 2.3 2.2-4 7-6.5 12.6-6.6-5-3-9.6-3.3-12.9-1.9-.1-3 1.6-5.7 4.9-7.4-8.6-1.3-16 2-19.5 6.7L35.6 9l-1.2 2.1C34 9.9 33.2 9 32 9z"/></svg>';
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', proxy: { server: process.env.HTTPS_PROXY } });
  const p = await (await b.newContext({ viewport: { width: 1080, height: 1350 }, deviceScaleFactor: 1 })).newPage();
  await p.route(/facebook|fika\/v1\/meta/, r => r.abort());
  await p.goto('https://swedishfikalb.com/about-us/', { waitUntil: 'load' }); await p.waitForTimeout(13000);
  await p.goto('https://swedishfikalb.com/about-us/', { waitUntil: 'load' }); await p.waitForTimeout(2500);
  const ff = await p.evaluate(() => { let s = ''; for (const sh of document.styleSheets) { try { for (const r of sh.cssRules) if (r.cssText.startsWith('@font-face') && /Cochon|Fanwood/.test(r.cssText)) s += r.cssText + '\n'; } catch (e) {} } return s; });
  console.log('font rules:', (ff.match(/@font-face/g) || []).length);
  const html = `<style>${ff}
  html,body{margin:0} #ad{position:relative;width:1080px;height:1350px;overflow:hidden;color:#ff7b1c;
    background:radial-gradient(ellipse 80% 55% at 50% 28%, #241838 0%, #140e22 55%, #0b0714 100%)}
  .bat{position:absolute;color:#ff7b1c} .bat svg{display:block;width:100%;height:auto}
  .logo{position:absolute;top:150px;left:0;right:0;text-align:center;font-family:'NF Le Petit Cochon',cursive;font-variant:small-caps;font-size:270px;line-height:1;color:#ff7b1c;
    text-shadow:0 0 40px rgba(255,123,28,.45),0 7px 0 #5c2400}
  .sub{position:absolute;top:400px;left:0;right:0;text-align:center;font-family:'Fanwood Text',Georgia,serif;font-size:48px;font-weight:700;letter-spacing:.04em;color:#f4ecdc;-webkit-text-stroke:1px #f4ecdc}
  .col{position:absolute;top:560px;width:540px;text-align:center}
  .eb{font-family:'Fanwood Text',Georgia,serif;font-style:italic;font-size:26px;color:#f4ecdc}
  .nm{font-family:'NF Le Petit Cochon',cursive;font-variant:small-caps;font-size:56px;line-height:1.1;margin-top:6px;color:#ff7b1c;text-shadow:0 0 18px rgba(255,123,28,.35)}
  .ds{font-family:'Fanwood Text',Georgia,serif;font-size:24px;color:#f4ecdc;margin-top:10px}
  .pc{position:absolute;filter:drop-shadow(0 10px 16px rgba(0,0,0,.55))}
  </style><div id="ad">
  <div class="bat" style="left:60px;top:55px;width:130px;transform:rotate(-8deg)">${BAT}</div>
  <div class="bat" style="right:55px;top:205px;width:120px;transform:rotate(10deg)">${BAT}</div>
  <div class="bat" style="left:30px;top:1200px;width:120px;transform:rotate(-14deg)">${BAT}</div>
  <div class="logo">Fika</div>
  <div class="sub">HALLOWEEN SPECIAL</div>
  <div class="col" style="left:0"><div class="eb">Limited Edition</div><div class="nm">Fan Favourite Mix</div><div class="ds">Our best sellers, plus limited pieces!</div></div>
  <div class="col" style="left:540px"><div class="eb">Halloween Offer</div><div class="nm">Skulls Mix</div><div class="ds">All your favourite skulls in one bag!</div></div>
  <img class="pc" src="${d('pomeB-d.png')}" style="left:80px;top:800px;width:280px;transform:rotate(14deg)">
  <img class="pc" src="${d('d5-1.png')}" style="left:225px;top:965px;width:330px;transform:rotate(108deg)">
  <img class="pc" src="${d('licorice.png')}" style="left:592px;top:790px;width:215px">
  <img class="pc" src="${d('bubblegum.png')}" style="left:832px;top:798px;width:180px">
  <img class="pc" src="${d('lemras.png')}" style="left:606px;top:1035px;width:192px">
  <img class="pc" src="${d('cola.png')}" style="left:816px;top:1032px;width:205px">
  </div>`;
  await p.setContent(html, { waitUntil: 'load' });
  await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(1500);
  console.log('fonts:', await p.evaluate(() => [document.fonts.check("50px 'NF Le Petit Cochon'"), document.fonts.check("40px 'Fanwood Text'")]));
  await p.locator('#ad').screenshot({ path: __dirname + '/fika-halloween-ad.png' });
  await b.close();
})();
