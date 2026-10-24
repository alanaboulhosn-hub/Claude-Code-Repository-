"""Turns the captured pages into a browsable preview: local assets, links between preview pages, a small helper script."""
import json, re, sys, pathlib
site = pathlib.Path(sys.argv[1])
HELPER = r"""
<style>
.hwp-chip{position:fixed;left:max(12px,env(safe-area-inset-left,0px));bottom:max(12px,env(safe-area-inset-bottom,0px));z-index:2147483000;display:flex;gap:8px;align-items:center;
  background:#0b0714;color:#f4ecdc;border:1px solid #ff7b1c;border-radius:999px;padding:7px 12px;font:600 12px/1.2 Outfit,system-ui,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.5)}
.hwp-chip a{color:#ff7b1c!important;text-decoration:underline}
.hwp-toast{position:fixed;left:50%;bottom:64px;transform:translateX(-50%);z-index:2147483001;max-width:min(360px,calc(100vw - 32px));background:#2b1c47;color:#f4ecdc;
  border:1px solid #ff7b1c;border-radius:12px;padding:10px 14px;font:500 14px/1.4 Outfit,system-ui,sans-serif;text-align:center;opacity:0;transition:opacity .25s;pointer-events:none}
.hwp-toast.on{opacity:1}
.fk-fade{opacity:1!important}
</style>
<div class="hwp-chip">Preview: Halloween look <a href="__OTHER__">__OTHERNAME__</a></div>
<div class="hwp-toast" id="hwpToast"></div>
<script>
(function () {
  var PHONE = __PHONE__;
  // open the version that fits this screen (only when arriving, never while reading)
  if (PHONE !== (window.innerWidth < 760) && !sessionStorage.getItem('hwpPicked')) { try { location.replace('__OTHER__'); return; } catch (e) {} }
  function toast(t) { var el = document.getElementById('hwpToast'); el.textContent = t; el.classList.add('on'); clearTimeout(el.t); el.t = setTimeout(function () { el.classList.remove('on'); }, 2600); }
  document.querySelector('.hwp-chip a').addEventListener('click', function () { try { sessionStorage.setItem('hwpPicked', '1'); } catch (e) {} });
  // the header: see-through at the very top, night bar once you scroll
  var hd = document.querySelector('.fika-header');
  function top() { if (!hd) return; var t = window.scrollY < 10; hd.classList.toggle('fika-top', t); hd.classList.toggle('fika-scrolled', !t); }
  window.addEventListener('scroll', top, { passive: true }); top();
  // phone menu
  var nav = document.querySelector('.fika-nav'), mb = document.querySelector('.fika-menu-btn');
  if (nav && mb) mb.addEventListener('click', function (e) { e.preventDefault(); nav.classList.toggle('open'); });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a,button,[role=button]');
    if (!a) return;
    if (a.closest('.fika-cart')) { e.preventDefault(); location.href = '__CHECKOUT__'; return; }
    if (a.matches('.mx-add,.mx-qb,.mx-pill button,.mx-go,.mx-fab')) { e.preventDefault(); toast('The bag is only a picture in this preview. Checkout shows a bag with two sweets.'); return; }
    if (a.getAttribute('href') === '#hwp-off') { e.preventDefault(); toast('That page is not part of the preview.'); return; }
    if (a.tagName === 'BUTTON' && a.closest('form')) { e.preventDefault(); toast('Nothing can be sent or ordered from the preview.'); }
  }, true);
  document.addEventListener('submit', function (e) { e.preventDefault(); toast('Nothing can be sent or ordered from the preview.'); }, true);
})();
</script>
"""
for dev, pre in (('desk', ''), ('phone', 'm-')):
    d = json.loads((site / ('pages.json' if dev == 'desk' else 'pages-phone.json')).read_text())
    html, assets, pages = d['html'], d['assets'], d['PAGES']
    other_pre = 'm-' if dev == 'desk' else ''
    for path, name in pages.items():
        h = html[path]
        for u, local in sorted(assets.items(), key=lambda kv: -len(kv[0])):
            if local:
                h = h.replace(u, local).replace(u.replace('&', '&amp;'), local)
        # links: preview pages stay in the preview, other shop pages say so, outside links open normally
        def link(m):
            url = m.group(2)
            mm = re.match(r'https://swedishfikalb\.com(/[^?#"]*)?', url)
            if not mm:
                return m.group(0)
            p = mm.group(1) or '/'
            if not p.endswith('/'): p += '/'
            return m.group(1) + (pre + pages[p] if p in pages else '#hwp-off') + '"'
        h = re.sub(r'(href=")([^"]*)"', link, h)
        h = re.sub(r'\saction="[^"]*"', '', h)
        helper = (HELPER.replace('__PHONE__', 'true' if dev == 'phone' else 'false')
                  .replace('__OTHER__', other_pre + name).replace('__OTHERNAME__', 'computer view' if dev == 'phone' else 'phone view')
                  .replace('__CHECKOUT__', pre + 'checkout.html'))
        # the site's own scripts that size the top photo and place the googly eyes for this screen
        keep = open(site.parent / 'keep-photo.js.html').read()
        if name == 'index.html':
            keep += open(site.parent / 'keep-eyes.js.html').read()
        h = re.sub(r'</body>(?![\s\S]*</body>)', keep + helper + '</body>', h)
        left = set(re.findall(r'https://swedishfikalb\.com/wp-content/[^\s"\'()<>]+', h))
        (site / (pre + name)).write_text(h)
        print(dev, name, round(len(h) / 1024), 'KB', 'unreplaced site files:', len(left))
