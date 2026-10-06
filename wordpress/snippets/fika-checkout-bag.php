<?php
/**
 * Fika: animated "your bag" illustration on the checkout page.
 * A pink Fika paper bag with a clear window; inside, mini versions of each product photo,
 * as many pieces per candy as its share of the grams ordered (300 g = 3x the pieces of 100 g).
 * Ready-Mix bags are shown as candies from their category (Sweet / Sour / both).
 * Reads the live cart from the WooCommerce Blocks data store, so it updates with the cart.
 * Candies are cartoon illustrations (window.FIKA_CARTOON, printed by the "Fika cartoons" snippet); products without
 * a cartoon fall back to their single-piece cut-out, then to the product photo.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-checkout-bag.php
 */

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	// Single-piece cut-outs: product meta "fika_piece" holds the image URL (falls back to the product photo)
	$pieces = array();
	$ids    = get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'fika_piece' ) );
	foreach ( $ids as $id ) {
		$url = get_post_meta( $id, 'fika_piece', true );
		if ( $url ) {
			$pieces[ $id ] = esc_url_raw( $url );
		}
	}
	echo '<script>window.FIKA_PIECES = ' . wp_json_encode( (object) $pieces ) . ';</script>';
	echo <<<'FIKA_BAG'
<style>
.fika-bagviz { --fika-blue: #004aad; --fika-pink: #fdeaf2; padding: 4px 4px 20px; margin: 0 0 18px; border-bottom: 1px solid #f0dbe5; }
.fika-bagviz .fbv-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin: 0 0 6px; }
.fika-bagviz .fbv-title { font-family: 'Bebas Neue', Impact, sans-serif; font-size: 26px; letter-spacing: .03em; color: var(--fika-blue); margin: 0; font-weight: 400; }
.fika-bagviz .fbv-kg { font-family: 'Outfit', Arial, sans-serif; font-weight: 600; color: var(--fika-blue); font-size: 16px; }
.fika-bagviz .fbv-stage { position: relative; width: 100%; max-width: 300px; margin: 0 auto; aspect-ratio: 300 / 360; }
.fika-bagviz .fbv-stage.fbv-wiggle { animation: fbvWiggle .7s ease-in-out; transform-origin: 50% 95%; }
@keyframes fbvWiggle { 0%,100% { transform: rotate(0); } 25% { transform: rotate(-1.6deg) scaleY(.985); } 60% { transform: rotate(1.2deg); } }
.fika-bagviz svg.fbv-bag { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.fika-bagviz .fbv-win {
  position: absolute; left: 20.5%; top: 45%; width: 59%; height: 44%;
  border-radius: 26px; overflow: hidden; isolation: isolate;
  background: radial-gradient(120% 90% at 50% 0%, #fffafc 0%, #fff1f7 70%, #fde6f0 100%);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, .95), 0 0 0 5px #e7a8c3, inset 0 8px 16px rgba(120, 30, 70, .10);
}
.fika-bagviz .fbv-candy { position: absolute; left: 0; top: 0; mix-blend-mode: multiply; transition: transform .6s cubic-bezier(.3, 1.3, .5, 1), opacity .3s; }
.fika-bagviz .fbv-candy.is-cartoon { mix-blend-mode: normal; filter: drop-shadow(0 1.5px 1.2px rgba(80, 20, 50, .25)); }
.fika-bagviz .fbv-candy.is-cartoon svg { display: block; width: 100%; height: 100%; overflow: visible; }
.fika-bagviz .fbv-candy.is-piece { mix-blend-mode: normal; filter: drop-shadow(0 1.5px 1.5px rgba(80, 20, 50, .22)); }
.fika-bagviz .fbv-candy.is-piece img { transform: none; }
.fika-bagviz .fbv-candy img { display: block; width: 100%; height: 100%; object-fit: contain; transform: scale(1.35); pointer-events: none; user-select: none; }
.fika-bagviz .fbv-glass { position: absolute; inset: 0; z-index: 5; pointer-events: none; border-radius: 26px;
  background: linear-gradient(118deg, rgba(255,255,255,0) 0 22%, rgba(255,255,255,.55) 26%, rgba(255,255,255,0) 33%, rgba(255,255,255,0) 58%, rgba(255,255,255,.32) 61%, rgba(255,255,255,0) 66%); }
.fika-bagviz .fbv-glint { position: absolute; inset: -20% -60%; z-index: 6; pointer-events: none;
  background: linear-gradient(110deg, rgba(255,255,255,0) 42%, rgba(255,255,255,.7) 50%, rgba(255,255,255,0) 58%);
  transform: translateX(-70%); animation: fbvGlint 5.5s ease-in-out 1.2s infinite; }
@keyframes fbvGlint { 0%, 70% { transform: translateX(-70%); } 100% { transform: translateX(70%); } }
.fika-bagviz .fbv-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 10px;
  font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; color: #b0708d; font-size: 17px; }
.fika-bagviz .fbv-note { font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 16px; line-height: 1.4; text-align: center; color: #1b2a4a; margin: 10px 0 0; }
@media (prefers-reduced-motion: reduce) { .fika-bagviz .fbv-glint { animation: none; display: none; } .fika-bagviz .fbv-candy { transition: none; } }
@media (max-width: 700px) { .fika-bagviz .fbv-stage { max-width: 240px; } }
</style>
<script>
(function () {
  if (window.__fikaBagViz) return;
  window.__fikaBagViz = true;

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var products = null;           // id -> { cats: [slugs], img: url, name }
  var lastSig = '';
  var root = null, stage = null, win = null, kgEl = null, emptyEl = null;
  var pieces = {};               // key -> { el, x, y, r, s }

  var BAG_SVG =
    '<svg class="fbv-bag" viewBox="0 0 300 360" aria-hidden="true">' +
      '<defs>' +
        '<linearGradient id="fbvPaper" x1="0" x2="1" y1="0" y2="0">' +
          '<stop offset="0" stop-color="#f3c3d8"/><stop offset=".12" stop-color="#f9dbe8"/>' +
          '<stop offset=".5" stop-color="#fbe5ef"/><stop offset=".88" stop-color="#f9dbe8"/><stop offset="1" stop-color="#efb8cf"/>' +
        '</linearGradient>' +
        '<linearGradient id="fbvFold" x1="0" x2="0" y1="0" y2="1">' +
          '<stop offset="0" stop-color="#f6cfe0"/><stop offset="1" stop-color="#eab0c9"/>' +
        '</linearGradient>' +
      '</defs>' +
      '<ellipse cx="150" cy="350" rx="118" ry="9" fill="rgba(0,74,173,.10)"/>' +
      '<path d="M44 74 L256 74 L266 334 Q267 346 254 346 L46 346 Q33 346 34 334 Z" fill="url(#fbvPaper)" stroke="#e6a9c3" stroke-width="1.5"/>' +
      '<path d="M62 78 L58 342" stroke="#eab3cb" stroke-width="2" opacity=".7"/>' +
      '<path d="M238 78 L242 342" stroke="#eab3cb" stroke-width="2" opacity=".7"/>' +
      '<path d="M40 42 L260 42 L256 80 L44 80 Z" fill="url(#fbvFold)" stroke="#e1a0bc" stroke-width="1.5"/>' +
      '<path d="M40 42 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 Z" fill="#f4c6da" stroke="#e1a0bc" stroke-width="1.2" stroke-linejoin="round"/>' +
      '<path d="M44 80 L256 80" stroke="#d98fb0" stroke-width="2" opacity=".55"/>' +
      '<text x="150" y="140" text-anchor="middle" transform="rotate(-5 150 128)" font-family="NF Le Petit Cochon, cursive" font-size="56" fill="#004aad" style="font-variant: small-caps">Fika</text>' +
    '</svg>';

  function rng(seed) {
    var a = seed >>> 0;
    return function () {
      a = (a + 0x6D2B79F5) >>> 0;
      var t = a;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  function hash(str) { var h = 2166136261; for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }

  function build() {
    root = document.createElement('div');
    root.className = 'fika-bagviz';
    root.innerHTML =
      '<div class="fbv-head"><h3 class="fbv-title">Your Fika bag</h3><span class="fbv-kg"></span></div>' +
      '<div class="fbv-stage">' + BAG_SVG +
        '<div class="fbv-win"><div class="fbv-glass"></div><div class="fbv-glint"></div><div class="fbv-empty">Your bag is empty</div></div>' +
      '</div>' +
      '<p class="fbv-note">A peek inside: every candy is shown in proportion to the grams you picked.</p>';
    stage = root.querySelector('.fbv-stage');
    win = root.querySelector('.fbv-win');
    kgEl = root.querySelector('.fbv-kg');
    emptyEl = root.querySelector('.fbv-empty');
  }

  // Keep the illustration at the top of the checkout's right-hand column
  function mount() {
    var side = document.querySelector('.wc-block-checkout__sidebar') ||
               document.querySelector('.wp-block-woocommerce-checkout-totals-block');
    if (!side) return false;
    if (!root) build();
    if (root.parentNode !== side) side.insertBefore(root, side.firstChild);
    return true;
  }

  function loadProducts() {
    if (products) return Promise.resolve(products);
    return fetch('/wp-json/wc/store/v1/products?per_page=100', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (list) {
        products = {};
        (list || []).forEach(function (p) {
          var im = p.images && p.images[0];
          products[p.id] = {
            name: p.name,
            slug: p.slug,
            cats: (p.categories || []).map(function (c) { return c.slug; }),
            img: im ? (im.thumbnail || im.src) : '',
            piece: (window.FIKA_PIECES || {})[p.id] || '',
            cartoon: window.FIKA_CARTOON ? window.FIKA_CARTOON(p.slug) : ''
          };
        });
        return products;
      })
      .catch(function () { products = {}; return products; });
  }

  function cartItems() {
    try {
      var d = window.wp && wp.data && wp.data.select('wc/store/cart');
      if (d && d.getCartData) return (d.getCartData().items || []);
    } catch (e) {}
    return null;
  }

  // Turn the cart into [{ key, img, grams }] candy entries (Ready-Mix -> its candies)
  function entries(items) {
    var out = [];
    items.forEach(function (it) {
      var p = products[it.id] || { cats: [], img: '' };
      var img = p.img || (it.images && it.images[0] ? (it.images[0].thumbnail || it.images[0].src) : '');
      if (p.cats.indexOf('ready-mix') !== -1) {
        var nm = (it.name || '').toLowerCase();
        var want = [];
        if (nm.indexOf('sweet') !== -1) want.push('sweet');
        if (nm.indexOf('sour') !== -1) want.push('sour');
        if (!want.length) want = ['sweet', 'sour'];
        var pool = Object.keys(products).filter(function (id) {
          var c = products[id].cats;
          return (products[id].cartoon || products[id].img) ? want.some(function (w) { return c.indexOf(w) !== -1; }) : false;
        });
        var rand = rng(hash('mix' + it.id));
        pool.sort(function () { return rand() - 0.5; });
        var pick = pool.slice(0, 5);
        var grams = it.quantity * 500;
        if (!pick.length) { out.push({ key: 'm' + it.id, img: img, grams: grams }); return; }
        pick.forEach(function (id) { var q = products[id]; out.push({ key: 'm' + it.id + '-' + id, img: q.piece || q.img, piece: !!q.piece, cartoon: q.cartoon, grams: grams / pick.length }); });
      } else {
        out.push({ key: 'c' + it.id, img: p.piece || img, piece: !!p.piece, cartoon: p.cartoon || '', grams: it.quantity * 100 });
      }
    });
    return out;
  }

  function render(items) {
    if (!mount()) return;
    var W = Math.floor(win.clientWidth), H = Math.floor(win.clientHeight);
    if (!W || !H) return;
    var list = entries(items);
    var total = 0;
    list.forEach(function (e) { total += e.grams; });
    var shown = 0;
    items.forEach(function (it) { var p = products[it.id] || { cats: [] }; shown += it.quantity * (p.cats.indexOf('ready-mix') !== -1 ? 500 : 100); });
    kgEl.textContent = shown ? (shown >= 1000 ? (Math.round(shown / 100) / 10) + ' kg' : shown + ' g') : '';
    emptyEl.style.display = total ? 'none' : 'flex';

    // How many pieces: up to 4 per 100 g, never more than ~46 in the window, same ratio for every candy
    var MAXP = 46, perHundred = Math.max(1, Math.min(4, Math.floor(MAXP * 100 / Math.max(total, 1))));
    var unit = 100 / perHundred;
    if (total / unit > MAXP) unit = total / MAXP;
    var want = [];
    list.forEach(function (e) {
      var n = Math.max(1, Math.round(e.grams / unit));
      for (var k = 0; k < n; k++) want.push({ key: e.key + '#' + k, img: e.img, piece: e.piece, cartoon: e.cartoon });
    });
    var N = want.length;

    // Fill more of the window as the bag gets heavier
    var fill = Math.min(0.92, 0.44 + total / 2400);
    var rand = rng(hash(want.map(function (w) { return w.key; }).join('|')));
    want.sort(function () { return rand() - 0.5; });

    // Settle the pieces like a jar: staggered rows from the bottom, each piece overlapping its neighbours
    var s = 96, dx, cols, rows, dy;
    for (; s > 22; s -= 2) {
      dx = s * 0.8;
      cols = Math.max(2, Math.floor((W - s * 0.25) / dx));
      rows = Math.ceil(N / cols);
      dy = s * 0.56;
      if (s + (rows - 1) * dy <= fill * H) break;
    }
    var used = cols * dx + s * 0.32, left0 = (W - used) / 2;
    var placed = [], idx = 0;
    for (var r = 0; idx < N; r++) {
      var inRow = Math.min(cols, N - idx);
      var startC = Math.floor((cols - inRow) / 2);
      var off = (r % 2) ? dx / 2 : 0;
      for (var c = 0; c < inRow; c++, idx++) {
        var w0 = want[idx];
        var x = left0 + (startC + c) * dx + off - (s - dx) / 2 + (rand() - 0.5) * dx * 0.36;
        x = Math.max(-s * 0.12, Math.min(W - s * 0.88, x));
        var bottom = r * dy + (rand() - 0.5) * dy * 0.4 + (r ? 0 : 2);
        bottom = Math.max(0, bottom);
        placed.push({ key: w0.key, img: w0.img, piece: w0.piece, cartoon: w0.cartoon, x: Math.round(x), y: Math.round(H - bottom - s - 2),
                      r: Math.round(rand() * 80 - 40), bottom: bottom });
      }
    }
    placed.sort(function (a, b) { return a.bottom - b.bottom; });

    var keep = {}, fresh = 0;
    placed.forEach(function (p, i) {
      keep[p.key] = true;
      var old = pieces[p.key];
      var tr = 'translate(' + p.x + 'px,' + p.y + 'px) rotate(' + p.r + 'deg)';
      if (old) {
        old.el.style.width = old.el.style.height = s + 'px';
        old.el.style.transform = tr;
        old.el.style.zIndex = String(i + 1);
        return;
      }
      var el = document.createElement('div');
      el.className = 'fbv-candy' + (p.cartoon ? ' is-cartoon' : (p.piece ? ' is-piece' : ''));
      el.style.width = el.style.height = s + 'px';
      el.style.zIndex = String(i + 1);
      if (p.cartoon) {
        el.innerHTML = p.cartoon;
      } else {
        var im = document.createElement('img');
        im.alt = ''; im.decoding = 'async'; im.src = p.img;
        el.appendChild(im);
      }
      el.style.transform = tr;
      win.insertBefore(el, win.querySelector('.fbv-glass'));
      pieces[p.key] = { el: el };
      if (!reduce) {
        var from = 'translate(' + p.x + 'px,' + (-s - 30) + 'px) rotate(' + (p.r - 60) + 'deg)';
        var up = 'translate(' + p.x + 'px,' + (p.y - 7) + 'px) rotate(' + (p.r + 4) + 'deg)';
        el.animate([
          { transform: from, offset: 0, easing: 'cubic-bezier(.5,0,.9,.5)' },
          { transform: tr, offset: 0.72 },
          { transform: up, offset: 0.86 },
          { transform: tr, offset: 1 }
        ], { duration: 720 + Math.round(rand() * 160), delay: fresh * 55, fill: 'backwards' });
      }
      fresh++;
    });
    Object.keys(pieces).forEach(function (k) {
      if (keep[k]) return;
      var el = pieces[k].el;
      el.style.opacity = '0';
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 320);
      delete pieces[k];
    });
    if (fresh) {
      if (!reduce) setTimeout(function () {
        stage.classList.remove('fbv-wiggle'); void stage.offsetWidth; stage.classList.add('fbv-wiggle');
      }, Math.min(2600, fresh * 55 + 650));
    }
  }

  function update() {
    var items = cartItems();
    if (items === null) return;
    var sig = items.map(function (i) { return i.id + 'x' + i.quantity; }).join(',');
    if (!mount()) return;
    if (sig === lastSig) return;
    lastSig = sig;
    loadProducts().then(function () { render(items); });
  }

  // Fallback when the blocks data store is not available
  function fetchCart() {
    return fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function start() {
    var n = 0;
    var timer = setInterval(function () {
      n++;
      if (mount()) {
        if (cartItems() === null) {
          fetchCart().then(function (c) { loadProducts().then(function () { render(c.items || []); }); });
        } else {
          update();
          if (window.wp && wp.data && wp.data.subscribe) wp.data.subscribe(update);
        }
        clearInterval(timer);
      } else if (n > 60) clearInterval(timer);
    }, 250);
    // React may re-render the sidebar: put the illustration back if it disappears
    new MutationObserver(function () { if (root) { if (!document.body.contains(root)) mount(); } }).observe(document.body, { childList: true, subtree: true });
    // Re-lay the pile (without dropping it again) when the window size really changes
    var lastW = 0, rt = 0;
    window.addEventListener('resize', function () {
      clearTimeout(rt);
      rt = setTimeout(function () {
        if (!win) return;
        if (Math.abs(win.clientWidth - lastW) < 2) return;
        lastW = win.clientWidth; lastSig = ''; update();
      }, 250);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
</script>
FIKA_BAG;
}, 99 );
