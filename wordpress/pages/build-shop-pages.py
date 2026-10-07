#!/usr/bin/env python3
"""Split the home page into shared parts (synced patterns) and compose the home + shop pages.

Input : wordpress/pages/backup/home-41.before-shop-page.html (the home page as it was, one big Custom HTML block)
Output: wordpress/pages/parts/*.html      shared parts, each saved as a WordPress synced pattern
        wordpress/pages/home-41.raw.html  home page content (pattern refs + home-only blocks)
        wordpress/pages/shop-40.raw.html  shop page content (pattern refs + shop-only blocks)
To change a shared part, edit it here (or in parts/*.html), re-run, and update the pattern (wp/v2/blocks/<id>).
Pattern ids are read from wordpress/pages/parts/ids.json ({"head": id, ...}) once the patterns exist.
"""
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'backup', 'home-41.before-shop-page.html')
PARTS = os.path.join(HERE, 'parts')
lines = open(SRC).read().split('\n')
assert lines[0] == '<!-- wp:html -->' and lines[1629] == '<!-- /wp:html -->', 'unexpected home page layout'


def L(a, b):
    """lines a..b (1-based, inclusive) of the original home page"""
    return '\n'.join(lines[a - 1:b])


def block(html):
    return '<!-- wp:html -->\n' + html.strip('\n') + '\n<!-- /wp:html -->'


# ---------- shared part A: base styles + announcement banner + header (hero photo moved out) ----------
head = L(2, 289)
photo_line = lines[39]
assert photo_line.lstrip().startswith('background: var(--fika-pink) url(data:image/jpeg;base64,'), 'hero photo line moved'
head = head.replace(photo_line + '\n', '', 1)

# ---------- home-only: hero + skull eyes (+ the hero photo, which only the home page needs) ----------
home_hero = '<style>\n.fika-hero:not(.fika-shop-hero) {\n' + photo_line + '\n}\n</style>\n' + L(290, 455)

# ---------- shared part B: fish cursor + bite + header/banner behaviour ----------
fish = L(457, 726)

# ---------- shared part C: Mix your own (cards + bag drawer + checkout hand-off) + Ready Mix ----------
mix = L(728, 1336)
rep = [
    # each page can choose which cards to show (home: favourites; shop: filtered grid with sections)
    ("""    $('mxCount').textContent = PRODUCTS.length + ' products';
    var keep = grid.scrollLeft;
    grid.innerHTML = PRODUCTS.map(function (p) {
      var g = bag[p.id] || 0;""",
     """    var view = window.fikaMxView ? window.fikaMxView(PRODUCTS) : PRODUCTS;
    $('mxCount').textContent = window.fikaMxCount ? window.fikaMxCount(PRODUCTS, view) : PRODUCTS.length + ' products';
    var keep = grid.scrollLeft;
    grid.innerHTML = view.map(function (p) {
      if (p.html) return p.html;
      var g = bag[p.id] || 0;"""),
    # products keep their categories (sweet / sour) for the shop page filters
    ("""price: price || PRICE_100G, hue: (w.id * 47) % 360 };""",
     """price: price || PRICE_100G, hue: (w.id * 47) % 360,
                   cats: (w.categories || []).map(function (c) { return c.slug; }) };"""),
    # pages can re-draw the cards (after a filter / search / sort change)
    ("""  function render() { renderGrid(); renderBag(); }""",
     """  function render() { renderGrid(); renderBag(); }
  window.fikaMxRender = render;"""),
    # pages can set the section title (home: "Fan favourites")
    ("""  var grid = document.getElementById('mxGrid');
  if (!grid) return;""",
     """  var grid = document.getElementById('mxGrid');
  if (!grid) return;
  if (window.fikaMxTitle) { var mxH1 = document.querySelector('#shop .mx-h1'); if (mxH1) mxH1.textContent = window.fikaMxTitle; }"""),
]
for a, b in rep:
    assert mix.count(a) == 1, a[:60]
    mix = mix.replace(a, b, 1)
# the Ready Mix section gets an anchor for links (/shop/#ready-mix)
assert mix.count('<div class="mx-page rm" id="rmPage">') == 1
mix = mix.replace('<div class="mx-page rm" id="rmPage">', '<span id="ready-mix" class="fk-anchor"></span>\n<div class="mx-page rm" id="rmPage">', 1)

# ---------- home-only: info sections ----------
home_sections = L(1338, 1501)

# ---------- shared part D: full-width fix + footer ----------
foot = L(1503, 1629)
for a, b in [('<li><a href="/ready-mix">Ready-Mix</a></li>', '<li><a href="/shop/#ready-mix">Ready-Mix</a></li>'),
             ('<li><a href="/shop">Mix your own</a></li>', '<li><a href="/shop/">Mix your own</a></li>')]:
    assert foot.count(a) == 1, a
    foot = foot.replace(a, b, 1)

# ---------- home-only: Fan favourites (the WooCommerce "Featured" products) ----------
home_favs = r"""<!-- FIKA home: "Fan favourites". The carousel shows the products starred as Featured in WooCommerce
     (Products list, star column), in the shop's order, followed by a "Shop all sweets" card. -->
<style>
.mx-track > .fk-all { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; padding:30px 20px; border-radius:28px; background:#fdeaf2;
  color:#004aad; text-align:center; text-decoration:none; box-sizing:border-box; min-height:100%; }
.mx-track > .fk-all .t { font-family:'NF Le Petit Cochon',cursive; font-size:34px; line-height:1.05; }
.mx-track > .fk-all .s { font-family:'Fanwood Text',Georgia,serif; font-variant:small-caps; font-size:19px; color:#1b2a4a; }
.mx-track > .fk-all .go { display:inline-flex; align-items:center; gap:8px; height:48px; padding:0 26px; border-radius:999px; background:#004aad; color:#fff;
  font:600 14px/1 'Outfit','Open Sans',Arial,sans-serif; letter-spacing:.04em; text-transform:uppercase; transition:background .15s; }
.mx-track > .fk-all:hover .go { background:#003a8a; }
.mx-track > .fk-all .toons { display:flex; gap:6px; margin-bottom:4px; }
.mx-track > .fk-all .toons i { display:block; width:46px; height:46px; }
.mx-track > .fk-all .toons svg { width:100%; height:100%; }
@media (max-width:700px) { .mx-track > .fk-all .t { font-size:28px; } }
</style>
<script>
(function () {
  var FAV = null;
  window.fikaMxTitle = 'Fan favourites';
  window.fikaMxCount = function () { return 'Our most-loved sweets. Add them straight to your bag.'; };
  function toon(slug) { return '<i>' + (window.FIKA_CARTOON ? window.FIKA_CARTOON(slug) : '') + '</i>'; }
  window.fikaMxView = function (list) {
    if (!FAV) return [];
    var out = list.filter(function (p) { return FAV.indexOf(p.id) !== -1; });
    out.push({ html: '<a class="mx-card fk-all" href="/shop/"><span class="toons">' + toon('sour-cherries') + toon('bubs-bubblegum-skull') + toon('swedish-fish') +
      '</span><span class="t">' + list.length + ' sweets to mix</span><span class="s">Sweet, sour and gelatin-free</span><span class="go">Shop all sweets &rarr;</span></a>' });
    return out;
  };
  fetch('/wp-json/wc/store/v1/products?featured=true&per_page=24&orderby=menu_order&order=asc', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (l) { FAV = (l || []).map(function (x) { return 'w' + x.id; }); if (window.fikaMxRender) window.fikaMxRender(); })
    .catch(function () { FAV = []; });
})();
</script>"""

# ---------- shop-only: banner (static photo, like the home hero) ----------
shop_hero = r"""<!-- FIKA shop page banner: candy-bowl photo that stays still while the page scrolls, pink wash, centred title -->
<div class="fika-hero fika-shop-hero">
  <div class="fsh-in">
    <h1 class="fsh-title">Mix your own &amp; Ready mix</h1>
    <p class="fsh-text">Pick your candy, your rules. Or grab a Ready Mix bag for the best of Scandinavian sweets, no decisions needed. Sweet, sour, or mixed: gelatin-free and gluten-free faves clearly marked, so everyone mixes and snacks happily.</p>
  </div>
</div>
<style>
.fika-hero.fika-shop-hero { min-height: 82vh; justify-content: center; align-items: center;
  background: linear-gradient(rgba(240, 128, 172, .30), rgba(240, 128, 172, .30)), url(/wp-content/uploads/2026/10/yes28-7vYLz0XjzMVhjx2f.jpg) center bottom / cover no-repeat, #f3b9d0; }
.fika-shop-hero .fsh-in { position: relative; z-index: 2; max-width: 1100px; margin: 0 auto; padding: 150px 6% 70px; text-align: center; }
.fika-shop-hero .fsh-title { margin: 0 0 26px; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: clamp(44px, 6.2vw, 96px); line-height: 1.02;
  color: #004aad; text-shadow: 0 2px 18px rgba(255, 255, 255, .35); }
.fika-shop-hero .fsh-text { margin: 0 auto; max-width: 980px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: clamp(18px, 1.6vw, 24px); line-height: 1.6;
  color: #fff; text-shadow: 0 1px 10px rgba(120, 30, 70, .55); }
/* the photo holds still while the page scrolls (like the home hero), framed on the bowl */
@keyframes fikaShopHold { from { background-position: 50% 62%; } to { background-position: 50% calc(62% + 100vh); } }
@supports (animation-timeline: scroll()) {
  .fika-hero.fika-shop-hero { animation: fikaShopHold linear both; animation-timeline: scroll(root block); animation-range: 0 100vh; }
}
@supports not (animation-timeline: scroll()) {
  .fika-hero.fika-shop-hero { background-attachment: fixed; background-position: 50% 62%; }
}
@media (max-width: 700px) {
  .fika-hero.fika-shop-hero { min-height: 74vh; }
  .fika-shop-hero .fsh-in { padding: 130px 20px 50px; }
}
</style>"""

# ---------- shop-only: filter bar + grid layout + view ----------
shop_bar = r"""<!-- FIKA shop page: filters (All / Sweet / Sour / Gelatin-free / Ready Mix), search and sort, over a grid of the same cards
     as the home page (same bag). Written without the logical-and operator and the less-than sign: WordPress rewrites them. -->
<div class="fika-sec fs-bar" id="fsBar">
  <div class="fs-chips" role="tablist" aria-label="Show">
    <button type="button" class="fs-chip on" data-f="all">All <small></small></button>
    <button type="button" class="fs-chip" data-f="sweet">Sweet <small></small></button>
    <button type="button" class="fs-chip" data-f="sour">Sour <small></small></button>
    <button type="button" class="fs-chip" data-f="gf">Gelatin-free <small></small></button>
    <button type="button" class="fs-chip" data-f="rm">Ready Mix <small></small></button>
  </div>
  <label class="fs-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
    <input type="search" id="fsQ" placeholder="Search sweets" aria-label="Search sweets"></label>
  <select id="fsSort" aria-label="Sort">
    <option value="pop">Most popular</option>
    <option value="az">A to Z</option>
    <option value="za">Z to A</option>
  </select>
</div>
<style>
html.fika-shop-page .fs-bar { position: sticky; top: var(--fs-top, 96px); z-index: 50; display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; box-sizing: border-box;
  padding: 18px 10%; background: rgba(255, 255, 255, .97); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); border-bottom: 1px solid #f3dbe6; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; }
.fs-bar .fs-chips { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; }
.fs-bar .fs-chip { display: inline-flex; align-items: center; gap: 6px; height: 42px; padding: 0 18px; border-radius: 999px; border: 2px solid #004aad; background: #fff; color: #004aad;
  font-family: 'Bebas Neue', Impact, sans-serif; font-size: 19px; letter-spacing: .04em; cursor: pointer; transition: background .15s, color .15s; }
.fs-bar .fs-chip small { font-family: 'Outfit', Arial, sans-serif; font-size: 12px; font-weight: 600; opacity: .7; }
.fs-bar .fs-chip:hover { background: #fdeaf2; }
.fs-bar .fs-chip.on { background: #004aad; color: #fff; }
.fs-bar .fs-search { position: relative; }
.fs-bar .fs-search input { box-sizing: border-box; height: 44px; width: 240px; padding: 0 16px 0 42px; border-radius: 999px; border: 1.5px solid #c9d4ea; font: 15px 'Outfit', Arial, sans-serif; color: #1b2a4a; background: #fff; }
.fs-bar .fs-search input:focus { outline: 0; border-color: #004aad; }
.fs-bar .fs-search svg { position: absolute; left: 15px; top: 13px; width: 18px; height: 18px; fill: none; stroke: #004aad; stroke-width: 2; stroke-linecap: round; }
.fs-bar select { height: 44px; padding: 0 38px 0 16px; border-radius: 999px; border: 1.5px solid #c9d4ea; font: 15px 'Outfit', Arial, sans-serif; color: #1b2a4a; background: #fff; }
/* the same cards, laid out as a grid instead of a sideways carousel */
html.fika-shop-page #shop { padding-top: 10px; }
html.fika-shop-page #shop .mx-head, html.fika-shop-page .mx-nav, html.fika-shop-page .mx-prog { display: none !important; }
html.fika-shop-page #shop .mx-count { padding-top: 26px; padding-bottom: 0; }
html.fika-shop-page #shop .mx-count:empty { display: none; }
html.fika-shop-page .mx-track { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 44px 40px; overflow: visible; scroll-snap-type: none; cursor: auto; padding: 10px 10% 10px; }
html.fika-shop-page .mx-track > .mx-card { flex: none; width: auto; }
html.fika-shop-page .mx-track > .fs-sec { grid-column: 1 / -1; display: flex; align-items: baseline; gap: 14px; margin: 34px 0 -14px; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps;
  font-weight: 400; font-size: clamp(34px, 3.2vw, 52px); line-height: 1; color: #004aad; }
html.fika-shop-page .mx-track > .fs-sec span { font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 19px; color: #6b7894; }
html.fika-shop-page .mx-track > .fs-none { grid-column: 1 / -1; padding: 30px 0; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 22px; color: #6b7894; }
html.fika-shop-page #rmPage { padding-top: 30px; }
html.fika-shop-page #rmPage .mx-count { display: none; }
html.fika-shop-page .fs-hide { display: none !important; }
html.fika-shop-page .fk-anchor { display: block; position: relative; top: -170px; }
@media (max-width: 1100px) { html.fika-shop-page .mx-track { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 34px 26px; } }
@media (max-width: 700px) {
  html.fika-shop-page .fs-bar { padding: 12px 16px; gap: 10px; }
  .fs-bar .fs-chips { flex: 1 1 100%; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
  .fs-bar .fs-chips::-webkit-scrollbar { display: none; }
  .fs-bar .fs-chip { flex: none; height: 38px; padding: 0 14px; font-size: 17px; }
  .fs-bar .fs-search { flex: 1; }
  .fs-bar .fs-search input { width: 100%; }
  .fs-bar select { width: 132px; }
  html.fika-shop-page .mx-track { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 26px 14px; padding: 6px 16px 10px; }
  html.fika-shop-page .mx-track > .fs-sec { font-size: 30px; margin: 22px 0 -8px; }
}
</style>
<script>
(function () {
  document.documentElement.classList.add('fika-shop-page');
  var st = { f: 'all', q: '', sort: 'pop' }, all = [];
  window.fikaMxTitle = 'Mix your own';
  function norm(s) { return String(s || '').toLowerCase(); }
  function isCat(p, c) { return (p.cats || []).indexOf(c) !== -1; }
  function keep(p) {
    if (st.q) { if (norm(p.name).indexOf(st.q) === -1) return false; }
    if (st.f === 'sweet') return isCat(p, 'sweet');
    if (st.f === 'sour') return isCat(p, 'sour');
    if (st.f === 'gf') return !p.gelatin;
    if (st.f === 'rm') return false;
    return true;
  }
  function sorted(list) {
    var out = list.slice();
    if (st.sort === 'az') out.sort(function (a, b) { return a.name.localeCompare(b.name); });
    if (st.sort === 'za') out.sort(function (a, b) { return b.name.localeCompare(a.name); });
    return out;
  }
  function head(t, sub) { return { html: '<h2 class="fs-sec">' + t + ' <span>' + sub + '</span></h2>' }; }
  window.fikaMxView = function (list) {
    all = list;
    var items = sorted(list.filter(keep)), out = [];
    if (st.q) return items.length ? items : (st.f === 'rm' ? [] : [{ html: '<div class="fs-none">No sweets match &ldquo;' + st.q.replace(/[<>&"]/g, '') + '&rdquo;.</div>' }]);
    var sweet = items.filter(function (p) { return isCat(p, 'sweet'); }), sour = items.filter(function (p) { return isCat(p, 'sour'); });
    var other = items.filter(function (p) { return !isCat(p, 'sweet') ? !isCat(p, 'sour') : false; });
    if (sweet.length) { out.push(head('Sweet', sweet.length + (sweet.length === 1 ? ' sweet' : ' sweets'))); out = out.concat(sweet); }
    if (sour.length) { out.push(head('Sour', sour.length + (sour.length === 1 ? ' sour' : ' sours'))); out = out.concat(sour); }
    return out.concat(other);
  };
  window.fikaMxCount = function (list, view) {
    counts(list);
    if (!st.q) return '';
    var n = view.filter(function (p) { return !p.html; }).length;
    if (!n) return '';
    return n + (n === 1 ? ' sweet' : ' sweets') + ' for “' + st.q + '”';
  };
  // Ready Mix section: shown with All, Gelatin-free (they are gelatin-free) and Ready Mix; follows the search
  function rmCards() { return [].slice.call(document.querySelectorAll('#rmGrid .mx-card')); }
  function applyRm() {
    var rm = document.getElementById('rmPage'), mix = document.getElementById('shop');
    if (!rm) return;
    var show = st.f === 'all' ? true : (st.f === 'gf' ? true : st.f === 'rm'), any = false;
    rmCards().forEach(function (c) {
      var name = norm((c.querySelector('.mx-name') || {}).textContent);
      var hit = st.q ? name.indexOf(st.q) !== -1 : true;
      c.classList.toggle('fs-hide', !hit);
      if (hit) any = true;
    });
    rm.classList.toggle('fs-hide', !(show ? any : false));
    if (mix) mix.classList.toggle('fs-hide', st.f === 'rm');
  }
  function counts(list) {
    var bar = document.getElementById('fsBar');
    if (!bar) return;
    var n = { all: list.length + rmCards().length, sweet: 0, sour: 0, gf: rmCards().length, rm: rmCards().length };
    list.forEach(function (p) { if (isCat(p, 'sweet')) n.sweet++; if (isCat(p, 'sour')) n.sour++; if (!p.gelatin) n.gf++; });
    [].slice.call(bar.querySelectorAll('.fs-chip')).forEach(function (b) { b.querySelector('small').textContent = n[b.getAttribute('data-f')]; });
  }
  function update(scroll) {
    if (window.fikaMxRender) window.fikaMxRender();
    applyRm();
    if (scroll) {
      var bar = document.getElementById('fsBar'), top = bar.getBoundingClientRect().top + window.pageYOffset - parseFloat(getComputedStyle(bar).top || 0);
      if (window.pageYOffset > top) window.scrollTo({ top: top, behavior: 'smooth' });
    }
  }
  function init() {
    var bar = document.getElementById('fsBar');
    if (!bar) return;
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('.fs-chip');
      if (!b) return;
      st.f = b.getAttribute('data-f');
      [].slice.call(bar.querySelectorAll('.fs-chip')).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
      update(true);
    });
    var q = document.getElementById('fsQ'), t = 0;
    q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { st.q = norm(q.value).trim(); update(false); }, 120); });
    document.getElementById('fsSort').addEventListener('change', function (e) { st.sort = e.target.value; update(false); });
    // keep the bar just under the header
    function top() {
      var h = document.querySelector('.fika-header');
      var b = h ? h.getBoundingClientRect().bottom : 96;
      document.documentElement.style.setProperty('--fs-top', Math.max(0, Math.round(b)) + 'px');
    }
    top(); window.addEventListener('resize', top); window.addEventListener('scroll', top, { passive: true }); setTimeout(top, 600);
    // the Ready Mix cards are drawn by their own script: re-apply the filter whenever they change
    var rg = document.getElementById('rmGrid');
    if (rg) new MutationObserver(function () { applyRm(); counts(all); }).observe(rg, { childList: true });
    applyRm();
    if (location.hash === '#ready-mix') { var c = bar.querySelector('.fs-chip[data-f="rm"]'); if (c) setTimeout(function () { c.click(); }, 300); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>"""

# ---------- write the parts ----------
os.makedirs(PARTS, exist_ok=True)
parts = {'head': head, 'fish': fish, 'mix': mix, 'foot': foot}
titles = {'head': 'Fika · styles, banner and header', 'fish': 'Fika · fish cursor and header behaviour',
          'mix': 'Fika · shop cards, bag and Ready Mix', 'foot': 'Fika · footer'}
for k, v in parts.items():
    open(os.path.join(PARTS, k + '.html'), 'w').write(block(v))
json.dump(titles, open(os.path.join(PARTS, 'titles.json'), 'w'), indent=1)

ids_file = os.path.join(PARTS, 'ids.json')
if not os.path.exists(ids_file):
    print('parts written; create the patterns, save their ids to', ids_file, 'and run again')
    sys.exit(0)
ids = json.load(open(ids_file))


def ref(k):
    return '<!-- wp:block {"ref":%d} /-->' % ids[k]


home = '\n\n'.join([ref('head'), block(home_hero), ref('fish'), block(home_favs), ref('mix'), block(home_sections), ref('foot')]) + '\n'
shop = '\n\n'.join([ref('head'), block(shop_hero), block(shop_bar), ref('fish'), ref('mix'), ref('foot')]) + '\n'
open(os.path.join(HERE, 'home-41.raw.html'), 'w').write(home)
open(os.path.join(HERE, 'shop-40.raw.html'), 'w').write(shop)
print('home', len(home), 'chars; shop', len(shop), 'chars')
