const { chromium } = require('playwright');
const fs = require('fs');
const ICON = {
  wa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.4z"/><path d="M9 8.6c0 3.3 3 6.4 6.4 6.4l1.2-1.6-2-1-1 .8a5 5 0 0 1-2.8-2.8l.8-1-1-2z" fill="currentColor" stroke="none"/></svg>',
  mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5L12 13l8.5-6.5"/></svg>',
  ig: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>' };
(async () => { const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: 700, height: 600 }, deviceScaleFactor: 2 });
  const font = fs.readFileSync('cochon.css', 'utf8');
  const link = (id, ic, t) => `<div class="lk" id="${id}"><i>${ICON[ic]}</i><span>${t}</span></div>`;
  await p.setContent(`<html><head><link href="https://fonts.googleapis.com/css2?family=Fanwood+Text&family=Outfit:wght@400&display=swap" rel="stylesheet"><style>${font}
    body{margin:0;background:transparent} .x{width:600px}
    #top{position:relative;width:600px}
    #top svg.w{display:block;width:600px;height:28px}
    #top .band{background:#004aad;text-align:center;padding:14px 0 6px;margin-top:-1px}
    .logo{font-family:'NF Le Petit Cochon';font-variant:small-caps;font-size:40px;line-height:1;color:#fdeaf2}
    .tag{font-family:'Fanwood Text',Georgia,serif;font-variant:small-caps;font-size:17px;color:#fdeaf2;margin-top:6px}
    #links{display:flex;width:600px;background:#004aad}
    .lk{width:200px;height:64px;display:flex;align-items:center;justify-content:center;gap:10px;background:#004aad}
    .lk i{width:38px;height:38px;border-radius:50%;background:#fdeaf2;color:#004aad;display:flex;align-items:center;justify-content:center}
    .lk i svg{width:20px;height:20px}
    .lk span{font-family:'Fanwood Text',Georgia,serif;font-variant:small-caps;font-size:18px;color:#fdeaf2}
    #bottom{width:600px;background:#004aad;text-align:center;padding:4px 0 26px;font-family:'Outfit',Arial,sans-serif;font-size:12.5px;color:#c9d4ea}
  </style></head><body>
  <div id="top"><svg class="w" viewBox="0 0 1440 56" preserveAspectRatio="none"><path fill="#004aad" d="M0,28 C120,56 240,0 360,28 C480,56 600,0 720,28 C840,56 960,0 1080,28 C1200,56 1320,0 1440,28 L1440,56 L0,56 Z"/></svg>
    <div class="band"><div class="logo">Fika</div><div class="tag">Swedish pick-and-mix, delivered across Lebanon</div></div></div>
  <div id="links">${link('l1', 'wa', 'WhatsApp')}${link('l2', 'mail', 'Email us')}${link('l3', 'ig', 'Instagram')}</div>
  <div id="bottom">WhatsApp 79 411 565 &nbsp;·&nbsp; hello@swedishfikalb.com &nbsp;·&nbsp; @swedishfika.lb<br>Questions about your order? Just reply to this email.</div>
  </body></html>`, { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(500);
  for (const [sel, f] of [['#top', 'fika-email-foot-top.png'], ['#l1', 'fika-email-foot-wa.png'], ['#l2', 'fika-email-foot-mail.png'], ['#l3', 'fika-email-foot-ig.png'], ['#bottom', 'fika-email-foot-bottom.png']])
    await (await p.$(sel)).screenshot({ path: f, omitBackground: true });
  await b.close(); })();
