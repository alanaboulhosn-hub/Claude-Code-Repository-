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
# the photo is a file (media 274, a smaller copy 275 for phones) instead of being pasted into the page: lighter and cached
HERO_BIG = '/wp-content/uploads/2026/10/fika-hero-1920.jpg'
HERO_SMALL = '/wp-content/uploads/2026/10/fika-hero-1000.jpg'
home_hero = ('<style>\n.fika-hero:not(.fika-shop-hero) { background: var(--fika-pink); }\n'
             '.fika-hero:not(.fika-shop-hero) > .fika-photo { background: url(' + HERO_BIG + ') center bottom / cover no-repeat; }\n'
             '@media (max-width: 760px) { .fika-hero:not(.fika-shop-hero) > .fika-photo { background-image: url(' + HERO_SMALL + '); } }\n</style>\n' + L(290, 455))
# the photo and the googly eyes sit on one layer that stays still by itself (smooth), instead of being moved on every scroll
for a, b in [
    ('<div class="fika-hero">\n', '<div class="fika-hero">\n  <div class="fika-photo" aria-hidden="true"></div>\n'),
    ('  hero.insertBefore(layer, hero.firstChild);', '  (hero.querySelector(\'.fika-photo\') || hero).appendChild(layer);'),
    ('''    } else if (scrollAnim) {
      // The photo holds still while the page scrolls (keyframes fikaPhotoHold)
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      oy += Math.max(0, Math.min(y, vh));
    }''', '''    }'''),
    ('    var rc = hero.getBoundingClientRect();\n    var idle', '    var rc = layer.getBoundingClientRect();\n    var idle'),
    ("  window.addEventListener('scroll', function () { layout(); kick(); }, { passive: true });", "  window.addEventListener('scroll', function () { kick(); }, { passive: true });"),
]:
    assert home_hero.count(a) == 1, a
    home_hero = home_hero.replace(a, b, 1)

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
    # Checkout hand-off: the bag replaces the cart, and discount codes already on it (the 10% from the checkout popup,
    # a free kilo) are put back afterwards; a code that no longer applies is skipped without stopping the checkout
    ("""      .then(function (r) {
        if (!r.ok) throw new Error('cart ' + r.status);
        return r.headers.get('Nonce') || r.headers.get('X-WC-Store-API-Nonce');
      })
      .then(function (nonce) {""",
     """      .then(function (r) {
        if (!r.ok) throw new Error('cart ' + r.status);
        var n = r.headers.get('Nonce') || r.headers.get('X-WC-Store-API-Nonce');
        return r.json().then(function (c) { return { n: n, codes: (c.coupons || []).map(function (x) { return x.code; }) }; }, function () { return { n: n, codes: [] }; });
      })
      .then(function (st) {
        var nonce = st.n;"""),
    ("""          (function (part) { chain = chain.then(function () { return storeBatch(nonce, part); }); })(reqs.slice(i, i + 25));
        }
        return chain;""",
     """          (function (part) { chain = chain.then(function () { return storeBatch(nonce, part); }); })(reqs.slice(i, i + 25));
        }
        st.codes.forEach(function (code) {
          chain = chain.then(function () { return storeBatch(nonce, [{ method: 'POST', path: '/wc/store/v1/cart/apply-coupon', body: { code: code } }]).catch(function () {}); });
        });
        return chain;"""),
    # the bag drawer: "You may also like", candies like the ones in the bag (or "Popular picks" when it is empty)
    ("""    $('mxItems').innerHTML = html || '<div class="mx-none">Your bag is empty.</div>';""",
     """    var sugRow = $('mxItems').querySelector('.mx-sug-row'), sugX = sugRow ? sugRow.scrollLeft : 0;
    $('mxItems').innerHTML = (html || '<div class="mx-none">Your bag is empty.</div>') + suggest();
    sugRow = $('mxItems').querySelector('.mx-sug-row'); if (sugRow) sugRow.scrollLeft = sugX;"""),
    ("""  function render() { renderGrid(); renderBag(); }
  window.fikaMxRender = render;""",
     """  function render() { renderGrid(); renderBag(); }
  window.fikaMxRender = render;
  // the suggestion row's arrows (computers); phones swipe
  $('mxItems').addEventListener('click', function (e) {
    var a = e.target.closest('[data-sug]'); if (!a) return;
    var row = $('mxItems').querySelector('.mx-sug-row'); if (!row) return;
    row.scrollBy({ left: parseInt(a.getAttribute('data-sug'), 10) * row.clientWidth * 0.8, behavior: 'smooth' });
  });

  // ---- "You may also like": candies like the ones in the bag ----
  // Each candy gets traits from its WooCommerce category (sweet / sour), gelatin tag and name (brand, flavour, shape);
  // candies sharing more traits with the bag score higher; ties keep the shop order (favourites first).
  // (Written without the logical-and operator and the less-than sign: WordPress rewrites them.)
  var FAM = {
    brand: [/bubs/, /tutti frutti/, /fizzy/],
    taste: [/berr|cherr|strawberr|raspberr/, /lemon|pineapple|passion/, /cola/, /melon/, /apple|pear|peach|banana/, /licorice|salty/, /toffee|bubblegum/],
    shape: [/skull/, /oval/, /fish/, /ring/, /teeth|pacifier/]
  };
  var WHY = { brand0: 'Also BUBS', brand1: 'Also Tutti Frutti', brand2: 'Also fizzy', taste0: 'Berry flavour', taste1: 'Zesty citrus', taste2: 'Cola flavour',
    taste3: 'Melon flavour', taste4: 'Fruity', taste5: 'Salty sweet', taste6: 'Creamy sweet', shape0: 'Skull shaped', shape1: 'Oval shaped', shape2: 'Fish shaped',
    shape3: 'Ring shaped', shape4: 'Fun shapes' };
  // weights: same brand 3, same flavour or shape 2.5, same sweet / sour 2, both gelatin-free 0.5
  function traits(p) {
    var n = String(p.name || '').toLowerCase(), fam = [];
    var cats = (p.cats || []).filter(function (c) { return c === 'sweet' ? true : c === 'sour'; });
    if (!p.cats) { if (/sour/.test(n)) cats.push('sour'); if (/sweet/.test(n)) cats.push('sweet'); }
    Object.keys(FAM).forEach(function (k) { FAM[k].forEach(function (re, i) { if (re.test(n)) fam.push(k + i); }); });
    return { cats: cats, gf: !p.gelatin, fam: fam };
  }
  function suggest() {
    var ids = Object.keys(bag), mine = ids.map(function (id) { return traits(byId(id)); });
    var list = PRODUCTS.filter(function (p) { return p.name ? !bag[p.id] : false; }).map(function (p, i) {
      var t = traits(p), s = 0, best = '', bestW = 0;
      function add(w, label) { s += w; if (w > bestW) { bestW = w; best = label; } }
      mine.forEach(function (b) {
        t.fam.forEach(function (f) { if (b.fam.indexOf(f) !== -1) add(f.charAt(0) === 'b' ? 3 : (f.charAt(0) === 't' ? 2.5 : 2.5), WHY[f]); });
        t.cats.forEach(function (c) { if (b.cats.indexOf(c) !== -1) add(2, c === 'sour' ? 'Also sour' : 'Also sweet'); });
        if (t.gf ? b.gf : false) add(0.5, 'Gelatin-free');
      });
      return { p: p, s: s - i * 0.01, why: best };
    }).sort(function (a, b) { return b.s - a.s; }).slice(0, 8);
    if (!list.length) return '';
    return '<div class="mx-sug"><div class="mx-sug-h"><h4>' + (ids.length ? 'You may also like' : 'Popular picks') + '</h4>' +
      '<span class="mx-sug-nav"><button type="button" data-sug="-1" aria-label="Scroll back">&lsaquo;</button><button type="button" data-sug="1" aria-label="Scroll on">&rsaquo;</button></span></div>' +
      '<div class="mx-sug-row">' + list.map(function (x) {
      var p = x.p;
      return '<div class="mx-sc"><span class="mx-sc-img">' + img(p) + '</span><b>' + esc(p.name) + '</b><small>' + esc(x.why || money(p.price || PRICE_100G) + ' per 100 g') + '</small>' +
        '<button class="mx-qb mx-sc-add" data-q="+" data-id="' + p.id + '" type="button" aria-label="Add 100 g of ' + esc(p.name) + '">+</button></div>';
    }).join('') + '</div></div>';
  }"""),
    # no "3 products" count over the Ready Mix bags
    ("""    $('rmCount').textContent = PRODUCTS.length + ' product' + (PRODUCTS.length === 1 ? '' : 's');""",
     """    $('rmCount').textContent = ''; $('rmCount').style.display = 'none';"""),
]
for a, b in rep:
    assert mix.count(a) == 1, a[:60]
    mix = mix.replace(a, b, 1)
mix += "\n" + r"""<style>
/* bag drawer: "You may also like", a row to swipe across */
.mx-sug { margin: 22px -24px 12px; }
.mx-sug-h { display: flex; align-items: center; justify-content: space-between; padding: 0 24px; margin-bottom: 10px; }
.mx-sug h4 { margin: 0; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 24px; color: var(--fika-blue); }
.mx-sug-nav { display: none; gap: 6px; }
.mx-sug-nav button { width: 30px; height: 30px; border: 1.5px solid #c9d4ea; border-radius: 50%; background: #fff; color: var(--fika-blue); font-size: 20px; line-height: 1; cursor: pointer; padding: 0 0 2px; }
.mx-sug-nav button:hover { background: #fdeaf2; border-color: var(--fika-blue); }
@media (hover: hover) { .mx-sug-nav { display: inline-flex; } }
.mx-sug-row { display: flex; gap: 10px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-padding: 0 24px; padding: 2px 24px 10px; scrollbar-width: none; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
.mx-sug-row::-webkit-scrollbar { display: none; }
.mx-sc { position: relative; flex: 0 0 142px; scroll-snap-align: start; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 12px 8px 12px; border: 1px solid #eaeef7; border-radius: 16px;
  background: #fff; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: var(--fika-blue); }
.mx-sc-img { display: block; width: 76px; height: 76px; }
.mx-sc-img img, .mx-sc-img .mx-ph { width: 100%; height: 100%; object-fit: contain; display: block; }
.mx-sc b { margin-top: 6px; font-weight: 500; font-size: 13.5px; line-height: 1.25; }
.mx-sc small { margin-top: 3px; font-size: 12px; color: #e0447f; }
.mx-drawer .mx-sc-add { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; font-size: 17px; }
</style>"""
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
  fetch('/wp-json/wc/store/v1/products?featured=true&per_page=24&orderby=menu_order&order=asc&catalog_visibility=catalog', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (l) { FAV = (l || []).map(function (x) { return 'w' + x.id; }); if (window.fikaMxRender) window.fikaMxRender(); })
    .catch(function () { FAV = []; });
})();
</script>"""

# ---------- home-only: reviews band (real WooCommerce product reviews, 4 and 5 stars; hidden while there are none) ----------
# Reviews come from customers who bought (WooCommerce: "Reviews can only be left by verified owners"), approved in
# WP Admin > Products > Reviews. Written without the logical-and operator and the less-than sign: WordPress rewrites them.
home_reviews = r"""<section class="fika-sec fk-rev" id="fkReviews" hidden aria-label="Customer reviews">
  <h2 class="fika-h2">Loved across Lebanon</h2>
  <div class="fk-rev-band"><div class="fk-rev-track" id="fkRevTrack"></div></div>
</section>
<style>
.fk-rev { padding: 70px 0 60px; background: #fff; overflow: hidden; }
.fk-rev .fika-h2 { text-align: center; margin: 0 0 30px; }
.fk-rev-band { position: relative; overflow: hidden; width: 100vw; margin-left: calc(50% - 50vw); -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); }
.fk-rev-track { display: flex; gap: 22px; width: max-content; padding: 10px 0 18px; animation: fkRevMove var(--fk-rev-t, 60s) linear infinite; }
.fk-rev-band:hover .fk-rev-track, .fk-rev-band:focus-within .fk-rev-track { animation-play-state: paused; }
@keyframes fkRevMove { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.fk-rc { flex: none; width: 330px; box-sizing: border-box; padding: 22px 24px 20px; border-radius: 24px; background: #fdeaf2; color: #1b2a4a; display: flex; flex-direction: column; gap: 10px; }
.fk-rc .st { color: #004aad; font-size: 19px; letter-spacing: 3px; line-height: 1; }
.fk-rc .st i { font-style: normal; color: #f3b9d0; }
.fk-rc q { quotes: none; font-family: 'Fanwood Text', Georgia, serif; font-size: 19px; line-height: 1.5; }
.fk-rc .who { margin-top: auto; font: 600 14px/1.3 'Outfit', 'Open Sans', Arial, sans-serif; color: #004aad; }
.fk-rc .who small { display: block; font-weight: 400; color: #6c7b9c; }
.fk-rc .ok { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 999px; background: #fff; font-size: 11px; font-weight: 600; color: #1f8a4c; vertical-align: 1px; }
@media (max-width: 700px) { .fk-rev { padding: 50px 0 40px; } .fk-rc { width: 270px; padding: 18px 18px 16px; } .fk-rc q { font-size: 17px; } }
@media (prefers-reduced-motion: reduce) { .fk-rev-band { overflow-x: auto; } .fk-rev-track { animation: none; } }
</style>
<script>
(function () {
  var box = document.getElementById('fkReviews'), track = document.getElementById('fkRevTrack');
  if (!box) return;
  function esc(s) { var d = document.createElement('span'); d.textContent = String(s); return d.innerHTML; }
  function text(h) { var d = document.createElement('div'); d.innerHTML = h || ''; return (d.textContent || '').replace(/\s+/g, ' ').trim(); }
  function who(name) { var p = String(name || '').trim().split(/\s+/); return p.length > 1 ? p[0] + ' ' + p[p.length - 1].charAt(0) + '.' : (p[0] || 'A Fika customer'); }
  fetch('/wp-json/wc/store/v1/products/reviews?per_page=30&orderby=date_gmt&order=desc', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (list) {
      var good = (list || []).filter(function (r) { return r.rating >= 4 ? text(r.review).length > 3 : false; });
      if (!good.length) return;
      var cards = good.map(function (r) {
        var t = text(r.review); if (t.length > 190) t = t.slice(0, 187).replace(/\s+\S*$/, '') + '…';
        var stars = ''; for (var i = 1; 5 >= i; i++) stars += i > r.rating ? '<i>★</i>' : '★';
        return '<figure class="fk-rc"><div class="st" aria-label="' + r.rating + ' out of 5 stars">' + stars + '</div><q>' + esc(t) + '</q>' +
          '<figcaption class="who">' + esc(who(r.reviewer)) + (r.verified ? '<span class="ok">Verified buyer</span>' : '') + '<small>' + esc(text(r.product_name)) + '</small></figcaption></figure>';
      });
      // repeat until the band is full, then twice over for a seamless loop
      var row = cards.slice(); while (8 > row.length) row = row.concat(cards);
      track.innerHTML = row.join('') + row.join('');
      track.style.setProperty('--fk-rev-t', Math.max(30, row.length * 7) + 's');
      [].slice.call(track.children).slice(row.length).forEach(function (c) { c.setAttribute('aria-hidden', 'true'); });
      box.hidden = false;
    }).catch(function () {});
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
  <div class="fika-photo" aria-hidden="true"></div>
  <div class="fsh-in">
    %(kicker)s<h1 class="fsh-title">%(title)s</h1>
    <p class="fsh-text">%(text)s</p>
  </div>
</div>
<style>
.fika-hero.fika-%(key)s-hero { background: #f3b9d0; }
.fika-hero.fika-%(key)s-hero > .fika-photo { background: linear-gradient(rgba(240, 128, 172, .30), rgba(240, 128, 172, .30)), url(%(img)s) %(pos)s / cover no-repeat; }
</style>""" % {'key': key, 'title': title, 'text': text, 'img': img, 'pos': pos,
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
    'Pick your candy, your rules. <span class="fk-n">28</span> Swedish sweets at $2.50 per 100 g: sweet, sour, or mixed, with gelatin-free and gluten-free faves clearly marked, so everyone mixes and snacks happily.',
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
    if (window.fikaFadeReset) window.fikaFadeReset();
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

ready_cross = r"""<div class="fs-cross"><div><span class="t">Rather pick your own?</span><span class="s"><span class="fk-n">28</span> sweets at $2.50 per 100 g, mixed exactly how you like</span></div><a class="go" href="/mix-your-own/">Mix your own &rarr;</a></div>"""

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
.fika-about .fa-mys { grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); margin-bottom: 46px; }
.fika-about .fa-mys img { aspect-ratio: 4 / 3; object-fit: cover; }
.fika-about .fa-ritual .fika-card p { margin: 8px 0 0; }
.fika-about .fa-mys-go { margin: 34px 0 0; text-align: center; }
.fika-about .fa-mys-go a { display: inline-flex; align-items: center; height: 52px; padding: 0 30px; border-radius: 999px; background: #004aad; color: #fff; text-decoration: none;
  font: 600 14px/1 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.fika-about .fa-mys-go a:hover { background: #003a8a; }
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
  .fika-about .fa-story, .fika-about .fa-mys { grid-template-columns: minmax(0, 1fr); gap: 28px; }
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
      <h2 class="fika-h2">L&ouml;rdagsmys, now in Lebanon</h2>
      <div class="fa-story fa-mys">
        <img src="IMG_BOWLPIC" alt="A glass bowl full of Swedish pick-and-mix" loading="lazy">
        <div>
          <p class="fa-lede">L&ouml;rdagsmys <span class="fa-say">say &ldquo;leur-dahgs-mees&rdquo;</span> means &ldquo;Saturday cosiness&rdquo;, and in Sweden it is a little ritual of its own.</p>
          <p>When Saturday comes, Swedish families slow down. Everyone heads to the pick-and-mix wall, each person scoops a few favourites, and it all ends up in one big bowl in the middle of the table. For generations, Swedish children have saved their sweets for Saturday, and the waiting is half the fun.</p>
          <p>In Lebanon, weekends already belong to family: long lunches, cousins, neighbours dropping by. We think l&ouml;rdagsmys fits right in. Bring the bowl to the table, let everyone add their favourites, and make Saturday the sweetest day of the week.</p>
        </div>
      </div>
      <div class="fika-cards fa-ritual">
        <div class="fika-card">
          <div class="fika-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"></circle><circle cx="17" cy="9" r="2.5"></circle><path d="M2.5 20c.6-3.6 2.8-5.5 5.5-5.5s4.9 1.9 5.5 5.5"></path><path d="M14 15.2c.9-.5 1.9-.7 3-.7 2.3 0 4.1 1.6 4.6 4.5"></path></svg></div>
          <h3>Everyone picks</h3>
          <p>Each person chooses a few favourites: sour for one, sweet for another, gelatin-free for whoever needs it.</p>
        </div>
        <div class="fika-card">
          <div class="fika-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h18a9 9 0 0 1-18 0z"></path><circle cx="8" cy="8" r="1.6"></circle><circle cx="12.5" cy="6.5" r="1.6"></circle><circle cx="16.5" cy="8.5" r="1.6"></circle></svg></div>
          <h3>One big bowl</h3>
          <p>Pour it all together in the middle of the table, so everyone can reach and nobody has to share by the piece.</p>
        </div>
        <div class="fika-card">
          <div class="fika-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.7a4.3 4.3 0 0 1 7.5 2.8c0 5.4-7.5 10-7.5 10z"></path></svg></div>
          <h3>Slow down together</h3>
          <p>A film, a board game or a long chat. Phones down, sweets out, and the whole family around the bowl.</p>
        </div>
      </div>
      <p class="fa-mys-go"><a href="/mix-your-own/">Build your Saturday bowl &rarr;</a></p>
    </div>
  </section>

  <section class="fika-sec fika-whitebg">
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

  <section class="fika-sec fika-pinkbg">
    <div class="fika-inner">
      <h2 class="fika-h2">How it works</h2>
      <div class="fa-steps">
        <div class="fa-step"><h3>Pick your sweets</h3><p><a href="/mix-your-own/">Mix your own</a> from <span class="fk-n">28</span> candies at $2.50 per 100 g, or choose a 500 g <a href="/ready-mix/">Ready Mix</a> bag for $12.50.</p></div>
        <div class="fa-step"><h3>Check out</h3><p>Pick your delivery area and pay in cash when your sweets arrive. Create an account and every kilo swims you closer to a free one.</p></div>
        <div class="fa-step"><h3>Enjoy your fika</h3><p>We deliver all over Lebanon (except Nabatieh and bordering cities), Monday to Saturday.</p></div>
      </div>
    </div>
  </section>

  <section class="fika-sec fika-whitebg">
    <div class="fika-inner fa-cta">
      <h2 class="fika-h2">Start your fika</h2>
      <p>Questions about wholesale, corporate boxes or a custom pick-and-mix for your event? Send us a note, we&rsquo;d love to help.</p>
      <div class="fa-btns">
        <a class="solid" href="https://wa.me/96179411565" target="_blank" rel="noopener">WhatsApp us</a>
        <a class="line" href="mailto:hello@swedishfikalb.com">Email us</a>
      </div>
    </div>
  </section>
</div>""".replace('IMG_GANG', IMG_GANG).replace('IMG_BOWLPIC', IMG_BOWL)


# ---------- every page: blocks fade in as they scroll into view (lives in the shared header part) ----------
# Product cards are re-drawn on every bag change, so a card fades once; a filter / search / sort change fades them again.
# Written without the logical-and operator and the less-than sign: WordPress rewrites them.
head += "\n" + r"""<style>
.fk-fade { opacity: 0; }
.fk-fade.fk-in { opacity: 1; transition: opacity .8s ease var(--fk-d, 0ms); }
@media (prefers-reduced-motion: reduce) { .fk-fade { opacity: 1; } }
</style>
<script>
(function () {
  var SEL = ['.fika-content > *', '.fsh-in > *', '.mx-head', '.mx-track > .mx-card',
    '.fika-inner > *:not(.fika-cards):not(.fa-steps):not(.fika-faq)', '.fika-cards > *', '.fa-steps > *', '.fika-faq > details',
    '.fs-cross', '.fika-foot-grid > *', '.fika-foot-bottom'].join(',');
  var seen = {}, wait = [], tick = 0;
  window.fikaFadeReset = function () { seen = {}; };
  function key(e) {
    if (!e.classList.contains('mx-card')) return '';
    var t = e.parentNode;
    return (t.id || 'x') + ':' + (e.getAttribute('data-id') || '');
  }
  function mark() {
    [].slice.call(document.querySelectorAll(SEL)).forEach(function (e) {
      if (e.fkDone) return;
      e.fkDone = 1;
      var k = key(e);
      if (k) { if (seen[k]) return; }
      e.classList.add('fk-fade');
      wait.push(e);
    });
    check();
  }
  // a block fades in once its top is in the lower part of the screen, or above it (after a jump down the page)
  function check() {
    tick = 0;
    var line = window.innerHeight * 0.92, n = new Map();
    wait = wait.filter(function (e) {
      if (!e.isConnected) return false;
      if (e.getBoundingClientRect().top > line) return true;
      // blocks side by side fade one after another
      var i = n.get(e.parentNode) || 0; n.set(e.parentNode, i + 1);
      e.style.setProperty('--fk-d', (Math.min(i, 5) * 90) + 'ms');
      e.classList.add('fk-in');
      var k = key(e); if (k) seen[k] = 1;
      setTimeout(function () { e.classList.remove('fk-fade', 'fk-in'); e.style.removeProperty('--fk-d'); }, 1500);
      return false;
    });
  }
  function soon() { if (!tick) tick = requestAnimationFrame(check); }
  function init() {
    mark();
    new MutationObserver(function () { mark(); }).observe(document.body, { childList: true, subtree: true });
    window.addEventListener('scroll', soon, { passive: true });
    window.addEventListener('resize', soon);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>"""


# ---------- still photos: a fixed layer clipped to the hero, instead of moving the background on every scroll ----------
HOLD_OLD = """/* Photo stays still while the page scrolls over it (no script needed) */
@keyframes fikaPhotoHold {
  from { background-position: 50% 100%; }
  to   { background-position: 50% calc(100% + 100vh); }
}
@supports (animation-timeline: scroll()) {
  .fika-hero { animation: fikaPhotoHold linear both; animation-timeline: scroll(root block); animation-range: 0 100vh; }
}
@supports not (animation-timeline: scroll()) {
  .fika-hero { background-attachment: fixed; background-position: center bottom; }
}"""
HOLD_NEW = """/* Photo stays still while the page scrolls over it: it sits on a fixed layer that the hero clips, so the browser
   keeps it still by itself (smooth) instead of moving it on every scroll. Its top and height follow the hero. */
.fika-hero { clip-path: inset(0); }
.fika-hero > .fika-photo { position: fixed; left: 0; top: var(--fika-photo-top, 0px); width: 100%; height: var(--fika-photo-h, 100vh); z-index: 0; pointer-events: none; }"""
assert head.count(HOLD_OLD) == 1
head = head.replace(HOLD_OLD, HOLD_NEW, 1)
head += "\n" + r"""<script>
(function () {
  // the still photo layer: same place and size as its hero at the top of the page
  function size() {
    [].slice.call(document.querySelectorAll('.fika-hero')).forEach(function (h) {
      var p = h.querySelector('.fika-photo');
      if (!p) return;
      var top = h.getBoundingClientRect().top + (window.pageYOffset || 0);
      h.style.setProperty('--fika-photo-top', Math.round(top) + 'px');
      h.style.setProperty('--fika-photo-h', h.offsetHeight + 'px');
    });
  }
  function init() {
    size();
    if (window.ResizeObserver) [].slice.call(document.querySelectorAll('.fika-hero')).forEach(function (h) { new ResizeObserver(size).observe(h); });
    window.addEventListener('resize', size); window.addEventListener('load', size);
    setTimeout(size, 400); setTimeout(size, 1500);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>"""

# ---------- lighter pages ----------
# the cute font is embedded once, in the header part (on every page); the shop and footer parts carried copies
FONT_FACE = re.compile(r"@font-face\s*\{\s*font-family:\s*'NF Le Petit Cochon';[^}]*\}\s*")
assert len(FONT_FACE.findall(head)) == 1
for k in ('mix', 'foot'):
    v = globals()[k]
    assert len(FONT_FACE.findall(v)) == 1, k
    globals()[k] = FONT_FACE.sub('', v, 1)
# the product list is fetched once per page and shared (the candies, the Ready Mix bags and the flying cartoons used to
# fetch it separately)
# only products shown in the shop: one set to "Hidden" in WooCommerce (e.g. out of stock for a while) is left out
OLD_URL = '/wp-json/wc/store/v1/products?per_page=100&orderby=menu_order&order=asc'
PRODUCTS_URL = OLD_URL + '&catalog_visibility=catalog'
head += "\n" + r"""<script>
window.fikaProducts = function () {
  if (!window.fikaProductsP) window.fikaProductsP = fetch('URL', { credentials: 'same-origin' });
  return window.fikaProductsP.then(function (r) { return r.clone(); });
};
</script>""".replace('URL', PRODUCTS_URL)
OLD_FETCH = "fetch('" + OLD_URL + "', { credentials: 'same-origin' })"
assert mix.count(OLD_FETCH) == 2
mix = mix.replace(OLD_FETCH, "(window.fikaProducts ? window.fikaProducts() : fetch('" + PRODUCTS_URL + "', { credentials: 'same-origin' }))")

# the number of candies in the copy (".fk-n") follows the shop: hidden products are not counted
head += "\n" + r"""<script>
(function () {
  function fill() {
    var el = document.querySelectorAll('.fk-n');
    if (!el.length) return;
    if (!window.fikaProducts) return;
    window.fikaProducts().then(function (r) { return r.json(); }).then(function (l) {
      var n = (l || []).filter(function (p) { return !(p.categories || []).some(function (c) { return c.slug === 'ready-mix'; }); }).length;
      if (n) [].slice.call(el).forEach(function (e) { e.textContent = n; });
    }).catch(function () {});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fill); else fill();
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


home = '\n\n'.join([ref('head'), block(home_hero), ref('fish'), block(home_favs), ref('mix'), block(home_reviews), block(home_sections), ref('foot')]) + '\n'
mixp = '\n\n'.join([ref('head'), block(mix_hero), block(grid_css), block(mix_bar), ref('fish'), ref('mix'), block(mix_cross), ref('foot')]) + '\n'
ready = '\n\n'.join([ref('head'), block(ready_hero), block(grid_css), block(ready_page), ref('fish'), ref('mix'), block(ready_cross), ref('foot')]) + '\n'
about = '\n\n'.join([ref('head'), block(about_hero), ref('fish'), block(about_body), ref('mix'), ref('foot')]) + '\n'
for name, html in (('home-41', home), ('mix-your-own-40', mixp), ('ready-mix-38', ready), ('about-us', about)):
    open(os.path.join(HERE, name + '.raw.html'), 'w').write(html)
    print(name, len(html), 'chars')
