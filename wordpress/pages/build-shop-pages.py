#!/usr/bin/env python3
"""Split the home page into shared parts (synced patterns) and compose the home + shop pages.

Input : wordpress/pages/backup/home-41.before-shop-page.html (the home page as it was, one big Custom HTML block)
Output: wordpress/pages/parts/*.html      shared parts, each saved as a WordPress synced pattern
        wordpress/pages/home-41.raw.html  home page content (pattern refs + home-only blocks)
        wordpress/pages/mix-your-own-40.raw.html, ready-mix-38.raw.html, about-us.raw.html  (pattern refs + page blocks)
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
    # no "3 products" count over the Ready Mix bags
    ("""    $('rmCount').textContent = PRODUCTS.length + ' product' + (PRODUCTS.length === 1 ? '' : 's');""",
     """    $('rmCount').textContent = ''; $('rmCount').style.display = 'none';"""),
]
for a, b in rep:
    assert mix.count(a) == 1, a[:60]
    mix = mix.replace(a, b, 1)
# the Ready Mix section gets an anchor for links (/shop/#ready-mix)
assert mix.count('<div class="mx-page rm" id="rmPage">') == 1
mix = mix.replace('<div class="mx-page rm" id="rmPage">', '<span id="ready-mix" class="fk-anchor"></span>\n<div class="mx-page rm" id="rmPage">', 1)

# ---------- home-only: info sections ----------
home_sections = L(1338, 1401)
# "Sweets made with care" (halal / authentic / delivery cards) was removed from the home page
care = home_sections.index('<section class="fika-sec fika-pinkbg">'), home_sections.index('<section class="fika-sec fika-whitebg">')
assert 'Sweets made with care' in home_sections[care[0]:care[1]]
home_sections = home_sections[:care[0]] + home_sections[care[1]:]

# ---------- shared part D: full-width fix + footer ----------
# (the section styles 1402-1501 travel with the footer: it takes its blue, fonts and spacing from them)
foot = L(1402, 1501) + '\n' + L(1503, 1629)
for a, b in [('<li><a href="/ready-mix">Ready-Mix</a></li>', '<li><a href="/shop/#ready-mix">Ready-Mix</a></li>'),
             ('<li><a href="/shop">Mix your own</a></li>', '<li><a href="/shop/">Mix your own</a></li>')]:
    assert foot.count(a) == 1, a
    foot = foot.replace(a, b, 1)

# ---------- home-only: Fan favourites (the WooCommerce "Featured" products) ----------
home_favs = r"""<!-- FIKA home: "Mix your own" + "All pick & mix" link. The carousel shows the products starred as Featured
     in WooCommerce (Products list, star column), in the shop's order. -->
<style>
/* "All pick & mix": a hand-drawn link next to the title, wobbles on hover */
#shop .mx-head { align-items:baseline; }
html:not(.fika-shop-page) #shop .mx-count:empty { display:none; }
#shop .fk-allpm { position:relative; display:inline-block; padding:0 2px; color:#ff4f9a; text-decoration:none;
  font-family:'NF Le Petit Cochon',cursive; font-variant:small-caps; font-size:clamp(20px,1.7vw,30px); line-height:1; transition:transform .2s ease, color .2s; }
#shop .fk-allpm b { font-weight:400; color:#004aad; display:inline-block; transition:transform .3s ease; }
#shop .fk-allpm .sq { position:absolute; left:0; bottom:-14px; width:100%; height:10px; fill:none; stroke:#ffb3d1; stroke-width:3; stroke-linecap:round; stroke-dasharray:180; stroke-dashoffset:0; }
#shop .fk-allpm:hover { color:#004aad; animation:fkWob .5s ease; }
#shop .fk-allpm:hover b { transform:rotate(360deg) scale(1.25); color:#ff4f9a; }
#shop .fk-allpm:hover .sq { animation:fkDraw .6s ease; }
@keyframes fkWob { 0%,100% { transform:rotate(0); } 30% { transform:rotate(4deg) scale(1.06); } 65% { transform:rotate(-3deg); } }
@keyframes fkDraw { from { stroke-dashoffset:180; } to { stroke-dashoffset:0; } }
@media (prefers-reduced-motion:reduce) { #shop .fk-allpm:hover { animation:none; } }
</style>
<script>
(function () {
  var FAV = null;
  window.fikaMxTitle = 'Mix your own';
  function addLink() {
    var hd = document.querySelector('#shop .mx-head');
    if (!hd) return;
    if (hd.querySelector('.fk-allpm')) return;
    var a = document.createElement('a');
    a.className = 'fk-allpm'; a.href = '/mix-your-own/';
    a.innerHTML = '<span class="w">All pick <b>&amp;</b> mix</span><svg class="sq" viewBox="0 0 120 10" aria-hidden="true"><path d="M2 6c8-6 12 4 20 0s12-6 20 0 12 4 20 0 12-6 20 0 12 4 20 0 12-4 16 0"></path></svg>' ;
    hd.appendChild(a);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addLink); else addLink();
  window.fikaMxCount = function () { return ''; };
  window.fikaMxView = function (list) {
    if (!FAV) return [];
    return list.filter(function (p) { return FAV.indexOf(p.id) !== -1; });
  };
  fetch('/wp-json/wc/store/v1/products?featured=true&per_page=24&orderby=menu_order&order=asc', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (l) { FAV = (l || []).map(function (x) { return 'w' + x.id; }); if (window.fikaMxRender) window.fikaMxRender(); })
    .catch(function () { FAV = []; });
})();
</script>"""

# ---------- header menu: Home / Mix your own / Ready Mix / About us (a "Menu" button on phones) ----------
NAV_OLD = """  <nav class="fika-nav">
    <a href="/">Home</a>
    <a href="/shop">Shop</a>
  </nav>"""
NAV_NEW = """  <nav class="fika-nav" aria-label="Main">
    <button type="button" class="fika-menu-btn" aria-expanded="false" aria-controls="fikaNavLinks">Menu <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg></button>
    <div class="fika-nav-links" id="fikaNavLinks">
      <a href="/">Home</a>
      <a href="/mix-your-own/">Mix your own</a>
      <a href="/ready-mix/">Ready Mix</a>
      <a href="/about-us/">About us</a>
    </div>
  </nav>"""
assert head.count(NAV_OLD) == 1, 'header nav changed'
head = head.replace(NAV_OLD, NAV_NEW, 1)
head += r"""
<style>
/* four links: a little closer together than the old two */
.fika-nav .fika-nav-links { display: flex; align-items: center; gap: clamp(26px, 3.2vw, 56px); }
.fika-nav .fika-nav-links a { font-size: clamp(22px, 1.75vw, 28px); }
.fika-nav .fika-nav-links a.on { border-bottom-color: var(--fika-blue); }
.fika-nav .fika-menu-btn { display: none; }
@media (max-width: 1100px) { .fika-nav .fika-nav-links { gap: 22px; } .fika-nav .fika-nav-links a { font-size: 21px; } }
/* phones: one "Menu" button that drops down the links */
@media (max-width: 760px) {
  .fika-nav { position: relative; }
  .fika-nav .fika-menu-btn { display: inline-flex; align-items: center; gap: 4px; padding: 6px 4px; border: 0; background: none; cursor: pointer;
    font-family: 'Bebas Neue', Impact, sans-serif; font-size: 22px; letter-spacing: .02em; color: var(--fika-blue); }
  .fika-nav .fika-menu-btn svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; transition: transform .2s ease; }
  .fika-nav.open .fika-menu-btn svg { transform: rotate(180deg); }
  .fika-nav .fika-nav-links { position: absolute; top: calc(100% + 12px); left: 50%; z-index: 40; flex-direction: column; align-items: stretch; gap: 2px; min-width: 210px; padding: 10px;
    border-radius: 18px; background: #fff; box-shadow: 0 14px 40px rgba(0, 74, 173, .18); opacity: 0; visibility: hidden; transform: translate(-50%, -6px);
    transition: opacity .2s ease, transform .2s ease, visibility 0s .2s; }
  .fika-nav.open .fika-nav-links { opacity: 1; visibility: visible; transform: translate(-50%, 0); transition: opacity .2s ease, transform .2s ease, visibility 0s; }
  .fika-nav .fika-nav-links a { display: block; padding: 11px 16px; border: 0; border-radius: 12px; font-size: 22px; line-height: 1.1; white-space: nowrap; }
  .fika-nav .fika-nav-links a:hover, .fika-nav .fika-nav-links a.on { background: #fdeaf2; }
}
</style>
<script>
(function () {
  var nav = document.querySelector('.fika-header .fika-nav');
  if (!nav) return;
  var here = location.pathname.replace(/\/+$/, '') || '/';
  [].slice.call(nav.querySelectorAll('.fika-nav-links a')).forEach(function (a) {
    var p = a.getAttribute('href').replace(/\/+$/, '') || '/';
    if (p === here) { a.classList.add('on'); a.setAttribute('aria-current', 'page'); }
  });
  var btn = nav.querySelector('.fika-menu-btn');
  function set(on) { nav.classList.toggle('open', on); btn.setAttribute('aria-expanded', on ? 'true' : 'false'); }
  btn.addEventListener('click', function (e) { e.stopPropagation(); set(!nav.classList.contains('open')); });
  document.addEventListener('click', function (e) { if (!nav.contains(e.target)) set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
})();
</script>"""

# ---------- links that pointed at the old /shop page ----------
assert home_hero.count('href="/shop"') == 1, 'hero shop button'
home_hero = home_hero.replace('href="/shop"', 'href="/mix-your-own/"', 1)
for a, b in [('<li><a href="/shop/#ready-mix">Ready-Mix</a></li>', '<li><a href="/ready-mix/">Ready Mix</a></li>\n          <li><a href="/about-us/">About us</a></li>'),
             ('<li><a href="/shop/">Mix your own</a></li>', '<li><a href="/mix-your-own/">Mix your own</a></li>')]:
    assert foot.count(a) == 1, a
    foot = foot.replace(a, b, 1)


# ---------- banners: a photo that stays still while the page scrolls, pink wash, centred title ----------
def banner(key, title, text, img, pos, kicker=''):
    return r"""<!-- FIKA %(key)s page banner -->
<div class="fika-hero fika-shop-hero fika-%(key)s-hero">
  <div class="fsh-in">
    %(kicker)s<h1 class="fsh-title">%(title)s</h1>
    <p class="fsh-text">%(text)s</p>
  </div>
</div>
<style>
.fika-hero.fika-%(key)s-hero { background: linear-gradient(rgba(240, 128, 172, .30), rgba(240, 128, 172, .30)), url(%(img)s) %(pos)s / cover no-repeat, #f3b9d0; }
@keyframes fika-%(key)s-hold { from { background-position: %(pos)s; } to { background-position: 50%% calc(%(posy)s + 100vh); } }
@supports (animation-timeline: scroll()) {
  .fika-hero.fika-%(key)s-hero { animation: fika-%(key)s-hold linear both; animation-timeline: scroll(root block); animation-range: 0 100vh; }
}
@supports not (animation-timeline: scroll()) {
  .fika-hero.fika-%(key)s-hero { background-attachment: fixed; background-position: %(pos)s; }
}
</style>""" % {'key': key, 'title': title, 'text': text, 'img': img, 'pos': pos, 'posy': pos.split()[1],
               'kicker': ('<p class="fsh-kicker">' + kicker + '</p>\n    ') if kicker else ''}


banner_css = r"""<style>
/* shared by the Mix your own, Ready Mix and About us banners */
.fika-hero.fika-shop-hero { min-height: 82vh; justify-content: center; align-items: center; }
.fika-shop-hero .fsh-in { position: relative; z-index: 2; max-width: 1100px; margin: 0 auto; padding: 150px 6% 70px; text-align: center; }
.fika-shop-hero .fsh-kicker { margin: 0 0 6px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: clamp(20px, 1.8vw, 26px); color: #fff; text-shadow: 0 1px 10px rgba(120, 30, 70, .55); }
.fika-shop-hero .fsh-title { margin: 0 0 26px; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: clamp(44px, 6.2vw, 96px); line-height: 1.02;
  color: #004aad; text-shadow: 0 2px 18px rgba(255, 255, 255, .35); }
.fika-shop-hero .fsh-text { margin: 0 auto; max-width: 980px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: clamp(18px, 1.6vw, 24px); line-height: 1.6;
  color: #fff; text-shadow: 0 1px 10px rgba(120, 30, 70, .55); }
@media (max-width: 700px) {
  .fika-hero.fika-shop-hero { min-height: 74vh; }
  .fika-shop-hero .fsh-in { padding: 130px 20px 50px; }
}
</style>"""

IMG_BOWL = '/wp-content/uploads/2026/10/yes28-7vYLz0XjzMVhjx2f.jpg'
IMG_JARS = '/wp-content/uploads/2026/10/chatgpt-image-jul-7-2026-12_48_32-pm-X3tBK9ZWkKnIvChP.png'
IMG_POUR = '/wp-content/uploads/2026/10/1-CpGiPMPowHbUltE9.png'
IMG_GANG = '/wp-content/uploads/2026/10/chatgpt-image-jul-31-2026-03_35_52-pm-H0MVQjmp7aMZAJ4u.png'

mix_hero = banner_css + '\n' + banner('mix', 'Mix your own',
    'Pick your candy, your rules. 31 Swedish sweets at $2.50 per 100 g: sweet, sour, or mixed, with gelatin-free and gluten-free faves clearly marked, so everyone mixes and snacks happily.',
    IMG_BOWL, '50% 62%')
ready_hero = banner_css + '\n' + banner('ready', 'Ready Mix',
    'Having a hard time deciding? We got you. Ready Mix bags packed with the best of Scandinavian candy. Choose your vibe (sweet, sour, or mixed) and enjoy the perfect balance in every bite.',
    IMG_JARS, '50% 60%')
about_hero = banner_css + '\n' + banner('about', 'About Fika',
    'We bring authentic Scandinavian candy directly to your doorstep in Lebanon.', IMG_POUR, '50% 70%', kicker='Stockholm to Beirut')

# ---------- shared by the shop pages: the same cards, laid out as a grid instead of a sideways carousel ----------
grid_css = r"""<style>
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
html.fika-shop-page .fs-hide { display: none !important; }
html.fika-shop-page .fk-anchor { display: none; }
/* the other shop: a pink band at the bottom linking to it */
.fs-cross { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 14px 30px; margin: 60px auto 30px; padding: 30px 6%; max-width: 1100px; width: calc(100% - 12%);
  box-sizing: border-box; border-radius: 28px; background: #fdeaf2; text-align: center; }
.fs-cross .t { font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-size: clamp(28px, 2.8vw, 40px); line-height: 1.05; color: #004aad; }
.fs-cross .s { display: block; margin-top: 4px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 19px; color: #1b2a4a; }
.fs-cross a.go { display: inline-flex; align-items: center; gap: 8px; height: 50px; padding: 0 28px; border-radius: 999px; background: #004aad; color: #fff; text-decoration: none;
  font: 600 14px/1 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; transition: background .15s; }
.fs-cross a.go:hover { background: #003a8a; }
@media (max-width: 1100px) { html.fika-shop-page .mx-track { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 34px 26px; } }
@media (max-width: 700px) {
  html.fika-shop-page .mx-track { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 26px 14px; padding: 6px 16px 10px; }
  html.fika-shop-page .mx-track > .fs-sec { font-size: 30px; margin: 22px 0 -8px; }
  .fs-cross { width: calc(100% - 32px); margin-top: 40px; padding: 24px 18px; }
}
</style>"""

# ---------- Mix your own: the 31 candies with filters, search and sort (no Ready Mix) ----------
mix_bar = r"""<!-- FIKA Mix your own page: filters (All / Sweet / Sour / Gelatin-free), search and sort over the candies.
     Written without the logical-and operator and the less-than sign: WordPress rewrites them. -->
<div class="fika-sec fs-bar" id="fsBar">
  <div class="fs-chips" role="tablist" aria-label="Show">
    <button type="button" class="fs-chip on" data-f="all" aria-selected="true">All <small></small></button>
    <button type="button" class="fs-chip" data-f="sweet">Sweet <small></small></button>
    <button type="button" class="fs-chip" data-f="sour">Sour <small></small></button>
    <button type="button" class="fs-chip" data-f="gf">Gelatin-free <small></small></button>
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
html.fika-mix-page #rmPage { display: none !important; }
html.fika-shop-page .fs-bar { position: sticky; top: var(--fs-top, 96px); z-index: 50; display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; box-sizing: border-box;
  padding: 18px 10%; background: rgba(255, 255, 255, .97); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); border-bottom: 1px solid #f3dbe6; font-family: 'Outfit', 'Open Sans', Arial, sans-serif;
  transition: transform .25s ease, opacity .2s ease; }
html.fika-shop-page .fs-bar.fs-off { transform: translateY(-110%); opacity: 0; pointer-events: none; }
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
@media (max-width: 700px) {
  html.fika-shop-page .fs-bar { padding: 12px 16px; gap: 10px; }
  .fs-bar .fs-chips { flex: 1 1 100%; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
  .fs-bar .fs-chips::-webkit-scrollbar { display: none; }
  .fs-bar .fs-chip { flex: none; height: 38px; padding: 0 14px; font-size: 17px; }
  .fs-bar .fs-search { flex: 1; }
  .fs-bar .fs-search input { width: 100%; }
  .fs-bar select { width: 132px; }
}
</style>
<script>
(function () {
  document.documentElement.classList.add('fika-shop-page', 'fika-mix-page');
  var st = { f: 'all', q: '', sort: 'pop' };
  window.fikaMxTitle = 'Mix your own';
  function norm(s) { return String(s || '').toLowerCase(); }
  function isCat(p, c) { return (p.cats || []).indexOf(c) !== -1; }
  function keep(p) {
    if (st.q) { if (norm(p.name).indexOf(st.q) === -1) return false; }
    if (st.f === 'sweet') return isCat(p, 'sweet');
    if (st.f === 'sour') return isCat(p, 'sour');
    if (st.f === 'gf') return !p.gelatin;
    return true;
  }
  function sorted(list) {
    var out = list.slice();
    if (st.sort === 'az') out.sort(function (a, b) { return a.name.localeCompare(b.name); });
    if (st.sort === 'za') out.sort(function (a, b) { return b.name.localeCompare(a.name); });
    return out;
  }
  window.fikaMxView = function (list) {
    // one catalogue: the filters, search and sort narrow it down
    var items = sorted(list.filter(keep));
    if (items.length) return items;
    return [{ html: '<div class="fs-none">' + (st.q ? 'No sweets match &ldquo;' + st.q.replace(/[<>&"]/g, '') + '&rdquo;.' : 'No sweets here yet.') + '</div>' }];
  };
  window.fikaMxCount = function (list, view) {
    counts(list);
    if (!st.q) return '';
    var n = view.filter(function (p) { return !p.html; }).length;
    if (!n) return '';
    return n + (n === 1 ? ' sweet' : ' sweets') + ' for “' + st.q + '”';
  };
  function counts(list) {
    var bar = document.getElementById('fsBar');
    if (!bar) return;
    var n = { all: list.length, sweet: 0, sour: 0, gf: 0 };
    list.forEach(function (p) { if (isCat(p, 'sweet')) n.sweet++; if (isCat(p, 'sour')) n.sour++; if (!p.gelatin) n.gf++; });
    [].slice.call(bar.querySelectorAll('.fs-chip')).forEach(function (b) { b.querySelector('small').textContent = n[b.getAttribute('data-f')]; });
  }
  function update(scroll) {
    if (window.fikaMxRender) window.fikaMxRender();
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
    // keep the bar just under the header, and slide it away once the footer comes up
    function top() {
      var h = document.querySelector('.fika-header');
      var b = h ? h.getBoundingClientRect().bottom : 96;
      document.documentElement.style.setProperty('--fs-top', Math.max(0, Math.floor(b) - 1) + 'px');
      var f = document.querySelector('.fika-footer');
      var off = f ? f.getBoundingClientRect().top - Math.max(b + bar.offsetHeight + 20, window.innerHeight * 0.7) : 1;
      bar.classList.toggle('fs-off', 0 > off);
    }
    var again = 0;
    function later() { top(); clearTimeout(again); again = setTimeout(top, 420); }
    var hd = document.querySelector('.fika-header');
    if (hd) hd.addEventListener('transitionend', top);
    top(); window.addEventListener('resize', later); window.addEventListener('scroll', later, { passive: true }); setTimeout(top, 600);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>"""

mix_cross = r"""<div class="fs-cross"><div><span class="t">Can&rsquo;t decide?</span><span class="s">Grab a Ready Mix bag: 500 g of our favourites, $12.50</span></div><a class="go" href="/ready-mix/">See the Ready Mix &rarr;</a></div>"""

# ---------- Ready Mix: just the 3 bags, no filters ----------
ready_page = r"""<!-- FIKA Ready Mix page: the Ready Mix bags only (the candy carousel of the shared part is hidden) -->
<style>
html.fika-ready-page #shop { display: none !important; }
html.fika-ready-page #rmPage { padding-top: 60px; }
html.fika-ready-page #rmPage .mx-head, html.fika-ready-page #rmPage .mx-count { display: none !important; }
html.fika-ready-page #rmGrid { grid-template-columns: repeat(3, minmax(0, 1fr)); max-width: 1180px; margin: 0 auto; box-sizing: border-box; }
@media (max-width: 700px) {
  html.fika-ready-page #rmPage { padding-top: 34px; }
  html.fika-ready-page #rmGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
<script>document.documentElement.classList.add('fika-shop-page', 'fika-ready-page');</script>"""

ready_cross = r"""<div class="fs-cross"><div><span class="t">Rather pick your own?</span><span class="s">31 sweets at $2.50 per 100 g, mixed exactly how you like</span></div><a class="go" href="/mix-your-own/">Mix your own &rarr;</a></div>"""

# ---------- About us (draft copy, built from the wording on the old Contact page) ----------
about_body = r"""<!-- FIKA About us page. DRAFT copy: please check and edit the text. The candy carousel of the shared part is hidden
     here (it is only included so the header bag works on this page too). -->
<style>
html.fika-about-page #shop, html.fika-about-page #rmPage { display: none !important; }
.fika-about .fa-story { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 56px; align-items: center; }
.fika-about .fa-story p { margin: 0 0 16px; font-family: 'Fanwood Text', Georgia, serif; font-size: 21px; line-height: 1.6; color: #1b2a4a; }
.fika-about .fa-story p.fa-lede { font-variant: small-caps; font-size: 24px; color: #004aad; }
.fika-about .fa-story img { display: block; width: 100%; height: auto; border-radius: 28px; box-shadow: 0 14px 40px rgba(0, 74, 173, .12); }
.fika-about .fa-say { display: inline-block; padding: 2px 12px; border-radius: 999px; background: #fdeaf2; font-variant: normal; font-size: 18px; color: #004aad; }
.fika-about .fa-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 26px; counter-reset: fa; margin-top: 10px; }
.fika-about .fa-step { position: relative; padding: 30px 26px 26px; border-radius: 24px; background: #fff; box-shadow: 0 8px 28px rgba(0, 74, 173, .08); }
.fika-about .fa-step::before { counter-increment: fa; content: counter(fa); display: flex; align-items: center; justify-content: center; width: 46px; height: 46px; margin-bottom: 14px;
  border-radius: 50%; background: #004aad; color: #fff; font-family: 'Bebas Neue', Impact, sans-serif; font-size: 26px; }
.fika-about .fa-step h3 { margin: 0 0 8px; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 28px; letter-spacing: .02em; color: #004aad; }
.fika-about .fa-step p { margin: 0; font-family: 'Fanwood Text', Georgia, serif; font-size: 19px; line-height: 1.55; color: #1b2a4a; }
.fika-about .fa-step a { color: #004aad; font-weight: 700; }
.fika-about .fa-cta { text-align: center; }
.fika-about .fa-cta p { max-width: 720px; margin: 0 auto 26px; font-family: 'Fanwood Text', Georgia, serif; font-size: 21px; line-height: 1.6; color: #1b2a4a; }
.fika-about .fa-btns { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; }
.fika-about .fa-btns a { display: inline-flex; align-items: center; height: 52px; padding: 0 30px; border-radius: 999px; border: 2px solid #004aad; text-decoration: none;
  font: 600 14px/1 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; transition: background .15s, color .15s; }
.fika-about .fa-btns a.solid { background: #004aad; color: #fff; }
.fika-about .fa-btns a.solid:hover { background: #003a8a; border-color: #003a8a; }
.fika-about .fa-btns a.line { background: #fff; color: #004aad; }
.fika-about .fa-btns a.line:hover { background: #fdeaf2; }
@media (max-width: 900px) {
  .fika-about .fa-story { grid-template-columns: minmax(0, 1fr); gap: 28px; }
  .fika-about .fa-steps { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 700px) {
  .fika-about .fa-story p { font-size: 19px; }
  .fika-about .fa-story p.fa-lede { font-size: 21px; }
}
</style>
<script>document.documentElement.classList.add('fika-about-page');</script>

<div class="fika-about">
  <section class="fika-sec fika-whitebg">
    <div class="fika-inner">
      <h2 class="fika-h2">Our story</h2>
      <div class="fa-story">
        <div>
          <p class="fa-lede">Fika <span class="fa-say">say &ldquo;fee-ka&rdquo;</span> is the Swedish habit of pausing the day for something sweet and good company.</p>
          <p>Swedish Fika brings that moment, and Sweden&rsquo;s famous pick-and-mix, to Lebanon. We import authentic Scandinavian candy and deliver it straight to your doorstep, so you can scoop a little of everything, share it, and slow down for a while.</p>
          <p>Mix your own, 100 g at a time, or grab a Ready Mix bag when you can&rsquo;t decide. Sweet, sour or both, with gelatin-free favourites clearly marked so everyone can join in.</p>
        </div>
        <img src="IMG_GANG" alt="Hand-drawn Fika candy characters holding hands" loading="lazy">
      </div>
    </div>
  </section>

  <section class="fika-sec fika-pinkbg">
    <div class="fika-inner">
      <h2 class="fika-h2">Freshness guaranteed</h2>
      <div class="fika-cards">
        <div class="fika-card">
          <div class="fika-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0z"></path><path d="M12 10v6"></path></svg></div>
          <h3>Climate control</h3>
          <p>Our imported sweets travel and rest in temperature-regulated spaces, shielded from the Mediterranean heat, so every bite has the texture it should.</p>
        </div>
        <div class="fika-card">
          <div class="fika-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 6.5h11v9h-11z"></path><path d="M13.5 9.5h4l3 3v3h-7z"></path><circle cx="7" cy="17" r="1.8"></circle><circle cx="17" cy="17" r="1.8"></circle></svg></div>
          <h3>Fast dispatch</h3>
          <p>Your selection is packed in a sealed Fika bag and delivered in 1&ndash;2 business days in Beirut, 2&ndash;3 outside. Cash on delivery.</p>
        </div>
        <div class="fika-card">
          <div class="fika-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="5" rx="1"></rect><path d="M5 13v8h14v-8M12 8v13M12 8C10 4 6 4 7 7s5 1 5 1 4 2 5-1-3-3-5 1"></path></svg></div>
          <h3>Special events</h3>
          <p>Planning a gathering? We put together corporate boxes and colourful birthday assortments, tailored to your guest list.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="fika-sec fika-whitebg">
    <div class="fika-inner">
      <h2 class="fika-h2">How it works</h2>
      <div class="fa-steps">
        <div class="fa-step"><h3>Pick your sweets</h3><p><a href="/mix-your-own/">Mix your own</a> from 31 candies at $2.50 per 100 g, or choose a 500 g <a href="/ready-mix/">Ready Mix</a> bag for $12.50.</p></div>
        <div class="fa-step"><h3>Check out</h3><p>Pick your delivery area and pay in cash when your sweets arrive. Create an account and every kilo swims you closer to a free one.</p></div>
        <div class="fa-step"><h3>Enjoy your fika</h3><p>We deliver all over Lebanon (except Nabatieh and bordering cities), Monday to Saturday.</p></div>
      </div>
    </div>
  </section>

  <section class="fika-sec fika-pinkbg">
    <div class="fika-inner fa-cta">
      <h2 class="fika-h2">Start your fika</h2>
      <p>Questions about bulk orders, corporate boxes or a custom pick-and-mix for your event? Send us a note, we&rsquo;d love to help.</p>
      <div class="fa-btns">
        <a class="solid" href="/mix-your-own/">Mix your own</a>
        <a class="line" href="/ready-mix/">Ready Mix</a>
        <a class="line" href="https://wa.me/96179411565" target="_blank" rel="noopener">WhatsApp us</a>
      </div>
    </div>
  </section>
</div>""".replace('IMG_GANG', IMG_GANG)


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
mixp = '\n\n'.join([ref('head'), block(mix_hero), block(grid_css), block(mix_bar), ref('fish'), ref('mix'), block(mix_cross), ref('foot')]) + '\n'
ready = '\n\n'.join([ref('head'), block(ready_hero), block(grid_css), block(ready_page), ref('fish'), ref('mix'), block(ready_cross), ref('foot')]) + '\n'
about = '\n\n'.join([ref('head'), block(about_hero), ref('fish'), block(about_body), ref('mix'), ref('foot')]) + '\n'
for name, html in (('home-41', home), ('mix-your-own-40', mixp), ('ready-mix-38', ready), ('about-us', about)):
    open(os.path.join(HERE, name + '.raw.html'), 'w').write(html)
    print(name, len(html), 'chars')
