"""The captured home pages as a working preview of the arrival popup: local files, the popup's own script, a chip to
switch look (normal / Halloween) and screen (computer / phone) and to show the popup again; links switched off."""
import json, re, pathlib
site = pathlib.Path('site'); d = json.loads((site / 'drops.json').read_text())
inj = pathlib.Path('inject.html').read_text()
pop_js = re.search(r'<script id="fika-drops-js">[\s\S]*?</script>', inj).group(0)
hwjs = pathlib.Path('../hw/build2/hw.js').read_text()
boo = '<script>' + re.search(r'/\* boo:start[\s\S]*?/\* boo:end \*/', hwjs).group(0) + '</script>'
keep = ''.join(pathlib.Path('../hw/' + f).read_text() for f in ('keep-fit.js.html', 'keep-photo.js.html', 'keep-eyes.js.html'))
NAMES = {'desk-hw': 'index.html', 'phone-hw': 'm-index.html'}
CHIP = r"""
<style>
.dpv-chip{position:fixed;left:max(12px,env(safe-area-inset-left,0px));top:max(12px,env(safe-area-inset-top,0px));z-index:2147483000;display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;max-width:calc(100vw - 24px);
  background:#0b0714;border:1px solid #ff7b1c;color:#f4ecdc;border-radius:16px;padding:8px 12px;font:600 12px/1.3 Outfit,system-ui,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.3)}
.dpv-chip a{color:#ff7b1c!important;text-decoration:underline}
.dpv-chip b{font-weight:700}
.dpv-toast{position:fixed;left:50%;top:60px;transform:translateX(-50%);z-index:2147483001;max-width:min(360px,calc(100vw - 32px));background:#1b2a4a;color:#fff;
  border-radius:12px;padding:10px 14px;font:500 14px/1.4 Outfit,system-ui,sans-serif;text-align:center;opacity:0;transition:opacity .25s;pointer-events:none}
.dpv-toast.on{opacity:1}
</style>
<div class="dpv-chip">Preview: Halloween drops popup &middot; <a href="__OTHERDEV__?fika_drops=preview">__OTHERDEVNAME__</a> <a href="?fika_drops=preview">show it again</a></div>
<div class="dpv-toast" id="dpvToast"></div>
<script>
(function () {
  var PHONE = __PHONE__;
  try { if (PHONE !== (window.innerWidth < 760) && !sessionStorage.getItem('dpvPicked')) { location.replace('__OTHERDEV__' + location.search); return; } } catch (e) {}
  if (!/fika_drops=preview/.test(location.search)) { try { sessionStorage.removeItem('fika_drops_seen'); } catch (e) {} }
  function toast(t) { var el = document.getElementById('dpvToast'); el.textContent = t; el.classList.add('on'); clearTimeout(el.t); el.t = setTimeout(function () { el.classList.remove('on'); }, 2600); }
  document.querySelectorAll('.dpv-chip a').forEach(function (a) { a.addEventListener('click', function () { try { sessionStorage.setItem('dpvPicked', '1'); } catch (e) {} }); });
  document.addEventListener('click', function (e) {
    if (e.target.closest('.dpv-chip')) return;
    if (e.target.closest('.fkd-add')) { setTimeout(function () { toast('On the shop this puts the 500 g bag in the Fika bag.'); }, 750); return; }
    if (e.target.closest('.fkd-veil')) return;
    var a = e.target.closest('a,button,[role=button]'); if (!a) return;
    e.preventDefault(); toast('Only the popup works in this preview.');
  }, true);
})();
</script>
"""
for key, name in NAMES.items():
    dev, look = key.split('-')
    h = d['html'][key]
    for u, local in sorted(d['assets'].items(), key=lambda kv: -len(kv[0])):
        if local: h = h.replace(u, local).replace(u.replace('&', '&amp;'), local)
    for f in ('fika-drop-skull-mix.webp', 'fika-drop-limited-edition.webp'):
        h = h.replace('https://swedishfikalb.com/wp-content/uploads/2026/10/' + f, 'a/' + f)
    h = re.sub(r'(href=")(https://swedishfikalb\.com[^"]*)"', lambda m: m.group(1) + '#off"', h)
    h = re.sub(r'\saction="[^"]*"', '', h)
    h = re.sub(r'<title>.*?</title>', '<title>Fika Drops Popup</title>', h, count=1, flags=re.S)
    other_dev = NAMES[('phone' if dev == 'desk' else 'desk') + '-' + look]
    chip = (CHIP.replace('__OTHERDEVNAME__', 'computer view' if dev == 'phone' else 'phone view').replace('__OTHERDEV__', other_dev)
            .replace('__PHONE__', 'true' if dev == 'phone' else 'false'))
    extra = keep + (boo if look == 'hw' else '') + pop_js + chip
    h = re.sub(r'</body>(?![\s\S]*</body>)', lambda m: extra + '</body>', h)
    left = set(re.findall(r'https://swedishfikalb\.com/wp-content/[^\s"\'()<>]+', h))
    (site / name).write_text(h)
    print(name, round(len(h) / 1024), 'KB | popup:', 'fkdVeil' in h, '| unreplaced files:', len(left))
