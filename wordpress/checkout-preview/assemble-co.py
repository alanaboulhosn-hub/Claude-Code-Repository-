"""The captured checkout as a working preview page: local assets, the Fika inbox card playing by its own script,
links and Place order switched off with a short note, a chip to swap between the phone and computer copies."""
import json, re, sys, pathlib
site = pathlib.Path(sys.argv[1])
card = pathlib.Path(sys.argv[2]).read_text()
card_js = re.search(r'<script id="fika-texts-js">[\s\S]*?</script>', card).group(0)
card_css = re.search(r'<style id="fika-texts-css">[\s\S]*?</style>', card).group(0)
HELPER = r"""
<style>
.cop-chip{position:fixed;right:max(12px,env(safe-area-inset-right,0px));top:max(12px,env(safe-area-inset-top,0px));z-index:2147483000;display:flex;gap:8px;align-items:center;
  background:#004aad;color:#fff;border-radius:999px;padding:7px 12px;font:600 12px/1.2 Outfit,system-ui,sans-serif;box-shadow:0 4px 18px rgba(0,74,173,.3)}
.cop-chip a{color:#ffd23f!important;text-decoration:underline}
.cop-toast{position:fixed;left:50%;top:60px;transform:translateX(-50%);z-index:2147483001;max-width:min(360px,calc(100vw - 32px));background:#1b2a4a;color:#fff;
  border-radius:12px;padding:10px 14px;font:500 14px/1.4 Outfit,system-ui,sans-serif;text-align:center;opacity:0;transition:opacity .25s;pointer-events:none}
.cop-toast.on{opacity:1}
</style>
<div class="cop-chip">Preview: checkout with customer texts <a href="__OTHER__">__OTHERNAME__</a></div>
<div class="cop-toast" id="copToast"></div>
<script>
(function () {
  var PHONE = __PHONE__;
  if (PHONE !== (window.innerWidth < 760)) { try { if (!sessionStorage.getItem('copPicked')) { location.replace('__OTHER__'); return; } } catch (e) {} }
  function toast(t) { var el = document.getElementById('copToast'); el.textContent = t; el.classList.add('on'); clearTimeout(el.t); el.t = setTimeout(function () { el.classList.remove('on'); }, 2600); }
  document.querySelector('.cop-chip a').addEventListener('click', function () { try { sessionStorage.setItem('copPicked', '1'); } catch (e) {} });
  document.addEventListener('click', function (e) {
    if (e.target.closest('.fika-texts, .cop-chip')) return; // the customer texts card works as on the shop
    var a = e.target.closest('a,button,[role=button]');
    if (!a) return;
    if (a.getAttribute('href') === '#cop-off') { e.preventDefault(); toast('That page is not part of the preview.'); return; }
    if (a.tagName === 'BUTTON' || a.getAttribute('role') === 'button') { e.preventDefault(); toast('Nothing can be ordered from the preview.'); }
  }, true);
  document.addEventListener('submit', function (e) { e.preventDefault(); toast('Nothing can be ordered from the preview.'); }, true);
})();
</script>
"""
for dev, out_name, other in (('desk', 'index.html', 'm-index.html'), ('phone', 'm-index.html', 'index.html')):
    d = json.loads((site / ('co-%s.json' % dev)).read_text())
    h, assets = d['html']['/checkout/'], d['assets']
    for u, local in sorted(assets.items(), key=lambda kv: -len(kv[0])):
        if local:
            h = h.replace(u, local).replace(u.replace('&', '&amp;'), local)
    def link(m):
        url = m.group(2)
        if not url.startswith('https://swedishfikalb.com'):
            return m.group(0)
        return m.group(1) + '#cop-off"'
    h = re.sub(r'(href=")([^"]*)"', link, h)
    h = re.sub(r'\saction="[^"]*"', '', h)
    # the card's current style (the capture may hold an older one)
    h = re.sub(r'<style id="fika-texts-css">[\s\S]*?</style>', lambda m: card_css, h)
    h = re.sub(r'<title>.*?</title>', '<title>Fika Checkout Preview</title>', h, count=1, flags=re.S)
    helper = HELPER.replace('__PHONE__', 'true' if dev == 'phone' else 'false').replace('__OTHER__', other).replace('__OTHERNAME__', 'computer view' if dev == 'phone' else 'phone view')
    h = re.sub(r'</body>(?![\s\S]*</body>)', lambda m: card_js + helper + '</body>', h)  # as plain text: the script has backslashes
    left = set(re.findall(r'https://swedishfikalb\.com/wp-content/[^\s"\'()<>]+', h))
    (site / out_name).write_text(h)
    print(dev, out_name, round(len(h) / 1024), 'KB | card style kept:', 'fika-texts-css' in h, '| unreplaced files:', len(left))
