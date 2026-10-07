const { chromium } = require('playwright');
const S = 'https://lightgoldenrodyellow-skunk-967361.hostingersite.com';
(async () => { const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: 1400, height: 900 }, deviceScaleFactor: 2 });
  await p.goto(S + '/', { waitUntil: 'networkidle' }); await p.waitForTimeout(1000);
  await p.evaluate((FONT) => {
    document.body.innerHTML = ''; document.body.style.background = 'transparent'; document.documentElement.style.background = 'transparent';
    const st = document.createElement('style'); st.textContent = `
      body{margin:0} .box{display:inline-flex;align-items:center;gap:14px;padding:6px 10px;position:absolute;left:0;top:0}
      .w{font-family:'NF Le Petit Cochon',cursive;font-variant:small-caps;font-size:84px;line-height:1;color:#004aad}
      .c{width:58px;height:58px;display:block} .c svg{width:100%;height:100%;overflow:visible;filter:drop-shadow(0 3px 3px rgba(80,20,50,.18))}
      .c.a{transform:rotate(-12deg) translateY(4px)} .c.b{transform:rotate(10deg) translateY(-6px)} .c.d{transform:rotate(-8deg)}
      .wave{position:absolute;left:0;top:200px;width:600px;height:28px}
      .row{position:absolute;left:0;top:300px;display:inline-flex;gap:10px;padding:6px}
      .row .c{width:40px;height:40px}`;
    document.head.appendChild(st); const ff = document.createElement('style'); ff.textContent = FONT; document.head.appendChild(ff);
    const C = s => '<i class="c ' + s[1] + '">' + window.FIKA_CARTOON(s[0]) + '</i>';
    document.body.insertAdjacentHTML('beforeend', '<div class="box" id="logo">' + C(['bubs-bubblegum-skull', 'a']) + '<span class="w">Fika</span>' + C(['swedish-fish', 'b']) + C(['sugared-strawberries', 'd']) + '</div>');
    document.body.insertAdjacentHTML('beforeend', '<svg class="wave" id="wave" viewBox="0 0 1440 56" preserveAspectRatio="none"><path fill="#004aad" d="M0,28 C120,56 240,0 360,28 C480,56 600,0 720,28 C840,56 960,0 1080,28 C1200,56 1320,0 1440,28 L1440,56 L0,56 Z"/></svg>');
  }, require('fs').readFileSync('cochon.css', 'utf8'));
  await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(800);
  await (await p.$('#logo')).screenshot({ path: 'fika-email-logo.png', omitBackground: true });
  await (await p.$('#wave')).screenshot({ path: 'fika-email-wave.png', omitBackground: true });
  await b.close(); })();
