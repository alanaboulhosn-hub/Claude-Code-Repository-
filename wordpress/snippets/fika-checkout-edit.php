<?php
/**
 * Fika: "Edit my bag" at checkout (owner, 25 Nov 2026). Customers change their bag without leaving the checkout:
 * - A button "Edit my bag" at the top of the order summary opens a panel (a sheet from the bottom on phones, from the
 *   right on computers) with the bag's lines: - / + (100 g steps, Ready Mix bags 500 g), remove, the price, a few
 *   sweets to add ("You may also like") and, while Black Friday is on, how far the bag is from 20% off.
 * - Every tap changes the real checkout bag (WooCommerce's own cart, so prices, the Black Friday line, limits per
 *   order and delivery all follow by themselves); the checkout behind updates, and whatever was typed in the form
 *   stays. The shop's bag (localStorage fika_bag_v1) is kept the same, so going back to the shop shows the same bag.
 * - Limits per order (fika-order-caps.php): + stops at the limit with a short note.
 * - The free mystery taste line is shown without buttons (it follows the bag by itself, fika-taste.php).
 * - Switch: option fika_edit_on (off while previewing). Preview for the owner while it is off: any page with
 *   ?fika_edit=<FIKA_EDIT_PREVIEW_KEY> switches it on for that browser only (a cookie for 3 hours).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-checkout-edit.php
 */

if ( ! defined( 'FIKA_EDIT_PREVIEW_KEY' ) ) {
	define( 'FIKA_EDIT_PREVIEW_KEY', 'ed7a31c5' );
}

if ( ! function_exists( 'fika_edit_on' ) ) {
	function fika_edit_on() {
		if ( get_option( 'fika_edit_on' ) ) {
			return true;
		}
		if ( isset( $_GET['fika_edit'] ) && is_string( $_GET['fika_edit'] ) && hash_equals( FIKA_EDIT_PREVIEW_KEY, $_GET['fika_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		$c = isset( $_COOKIE['fika_edit_preview'] ) ? (string) $_COOKIE['fika_edit_preview'] : '';
		$p = explode( '.', $c );
		return 2 === count( $p ) && (int) $p[0] > time() && hash_equals( wp_hash( 'fika_edit_preview|' . $p[0] ), $p[1] );
	}
}

// the preview link sets the cookie (3 hours, this browser only)
add_action( 'init', function () {
	if ( isset( $_GET['fika_edit'] ) && is_string( $_GET['fika_edit'] ) && hash_equals( FIKA_EDIT_PREVIEW_KEY, $_GET['fika_edit'] ) && ! headers_sent() ) { // phpcs:ignore WordPress.Security.NonceVerification
		$until = time() + 3 * HOUR_IN_SECONDS;
		$v     = $until . '.' . wp_hash( 'fika_edit_preview|' . $until );
		setcookie( 'fika_edit_preview', $v, array( 'expires' => $until, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		$_COOKIE['fika_edit_preview'] = $v;
	}
} );

add_action( 'wp_footer', function () {
	if ( is_admin() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || ! fika_edit_on() ) {
		return;
	}
	$ready = array_map( 'intval', get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'ready-mix' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$cfg   = array(
		'ready' => $ready,
		'bf'    => function_exists( 'fika_bf_on' ) && fika_bf_on() ? 1 : 0,
		'shop'  => home_url( '/mix-your-own/' ),
	);
	echo '<script>window.FIKA_EDIT = ' . wp_json_encode( $cfg ) . ';</script>';
	?>
<style>
.fke-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; margin: 0 0 12px; padding: 12px 16px; border: 2px solid #004aad; border-radius: 999px; background: #fff; color: #004aad; font: 700 15px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .02em; cursor: pointer; transition: background .15s, color .15s; }
.fke-btn:hover { background: #004aad; color: #fff; }
.fke-btn svg { width: 18px; height: 18px; }
.fke-veil { position: fixed; inset: 0; z-index: 200050; background: rgba(27, 42, 74, .38); opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .25s ease, visibility 0s linear .25s; }
.fke-veil.on { opacity: 1; visibility: visible; pointer-events: auto; transition: opacity .25s ease; }
/* closed: nothing of the panel can catch a tap (the checkout stays fully usable) */
.fke-panel:not(.on) { visibility: hidden; pointer-events: none; transition: transform .3s cubic-bezier(.2, .8, .2, 1), visibility 0s linear .3s; }
.fke-panel { position: fixed; z-index: 200051; right: 0; top: 0; bottom: 0; width: min(440px, 100vw); display: flex; flex-direction: column; background: #fff; color: #1b2a4a; box-shadow: -12px 0 40px rgba(0, 74, 173, .18); transform: translateX(105%); transition: transform .3s cubic-bezier(.2, .8, .2, 1); font-family: 'Outfit', 'Open Sans', Arial, sans-serif; }
.fke-panel.on { transform: none; }
@media (max-width: 700px) {
  .fke-panel { top: auto; left: 0; width: 100vw; max-height: 88vh; border-radius: 22px 22px 0 0; box-shadow: 0 -12px 40px rgba(0, 74, 173, .2); transform: translateY(105%); }
}
.fke-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; background: #fde9f1; border-radius: inherit; }
.fke-head b { font-size: 22px; font-weight: 800; color: #004aad; letter-spacing: .01em; }
.fke-x { width: 40px; height: 40px; border: 0; border-radius: 50%; background: #fff; color: #004aad; font-size: 22px; line-height: 1; cursor: pointer; }
.fke-body { flex: 1; overflow-y: auto; padding: 6px 20px 12px; -webkit-overflow-scrolling: touch; }
.fke-line { display: flex; align-items: center; gap: 10px; padding: 14px 0; border-bottom: 1px solid #eef0f5; }
.fke-line .n { flex: 1; min-width: 0; color: #004aad; font-size: 15.5px; font-weight: 600; line-height: 1.25; }
.fke-line .n small { display: block; margin-top: 2px; color: #6c7b9c; font-size: 12.5px; font-weight: 500; }
.fke-line .n small.cap { color: #c0392b; }
.fke-q { display: inline-flex; align-items: center; gap: 6px; flex: none; }
.fke-q button { width: 34px; height: 34px; border: 0; border-radius: 50%; background: #004aad; color: #fff; font: 700 19px/1 Arial, sans-serif; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.fke-q button:disabled { background: #c9d4ea; cursor: default; }
.fke-q span { min-width: 54px; text-align: center; font-size: 14px; font-weight: 700; color: #1b2a4a; }
.fke-p { flex: none; width: 58px; text-align: right; font-weight: 700; color: #004aad; font-size: 14.5px; }
.fke-rm { flex: none; border: 0; background: none; color: #a2aecb; font-size: 20px; cursor: pointer; padding: 4px; }
.fke-rm:hover { color: #d13b73; }
.fke-free .fke-p { color: #2fb36b; }
.fke-empty { padding: 30px 0; text-align: center; color: #6c7b9c; }
.fke-sug h4 { margin: 14px 0 8px; font-size: 15px; font-weight: 800; color: #004aad; }
.fke-sug .row { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 6px; scroll-snap-type: x mandatory; }
.fke-card { flex: none; display: flex; align-items: center; gap: 8px; width: 210px; scroll-snap-align: start; border: 1px solid #eef0f5; border-radius: 14px; padding: 8px 10px; background: #fff; }
.fke-card img { flex: none; width: 46px; height: 46px; object-fit: contain; }
.fke-card .t { flex: 1; min-width: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 12.5px; font-weight: 600; color: #004aad; line-height: 1.3; }
.fke-card .t small { display: block; color: #6c7b9c; font-weight: 500; font-size: 11.5px; }
.fke-card button { flex: none; width: 34px; height: 34px; border: 0; border-radius: 50%; background: #004aad; color: #fff; font: 700 19px/1 Arial, sans-serif; cursor: pointer; }
.fke-foot { padding: 14px 20px calc(14px + env(safe-area-inset-bottom)); border-top: 1px solid #eef0f5; background: #fff; }
.fke-bf { margin: 0 0 12px; padding: 10px 12px; border-radius: 10px; background: #111827; color: #fff; font-size: 13px; font-weight: 600; }
.fke-bf .bar { display: block; height: 7px; margin-top: 7px; border-radius: 99px; background: rgba(255, 255, 255, .16); overflow: hidden; }
.fke-bf .bar i { display: block; height: 100%; border-radius: 99px; background: #fcd34d; transition: width .3s; }
.fke-bf b { color: #fcd34d; }
.fke-sum { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 15px; font-weight: 700; color: #1b2a4a; }
.fke-done { width: 100%; padding: 15px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 700 15px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fke-msg { margin: 0 0 8px; color: #c0392b; font-size: 13px; text-align: center; }
.fke-msg:empty { display: none; }
.fke-panel.busy .fke-body { opacity: .6; pointer-events: none; }
</style>
<script>
(function () {
  var C = window.FIKA_EDIT || {}, READY = C.ready || [], products = null;
  function store() { try { return wp.data.select('wc/store/cart'); } catch (e) { return null; } }
  function act() { try { return wp.data.dispatch('wc/store/cart'); } catch (e) { return null; } }
  function cart() { var s = store(); return s ? s.getCartData() || {} : {}; }
  function money(n) { return '$' + (Math.round(n * 100) / 100).toFixed(2); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function minor(p) { var m = p && p.currency_minor_unit != null ? p.currency_minor_unit : 2; return function (v) { return (parseInt(v || '0', 10) || 0) / Math.pow(10, m); }; }
  function isTaste(it) { return (it.item_data || []).some(function (d) { return /mystery taste/i.test(d.name || d.key || ''); }); }
  function step(id) { return READY.indexOf(id) !== -1 ? 500 : 100; }
  function grams() { var g = 0; (cart().items || []).forEach(function (it) { if (!isTaste(it)) g += it.quantity * step(it.id); }); return g; }
  function gText(g, id) { return READY.indexOf(id) !== -1 ? (g / 500) + ' × 500 g' : g >= 1000 ? (g / 1000) + ' kg' : g + ' g'; }
  // the shop's bag follows the checkout bag (same format as the shop pages: w<id> grams, rw<id> for Ready Mix bags)
  function syncShopBag() {
    try {
      var bag = {}, meta = {};
      (cart().items || []).forEach(function (it) {
        if (isTaste(it)) return;
        var k = (READY.indexOf(it.id) !== -1 ? 'rw' : 'w') + it.id, f = minor(it.prices);
        bag[k] = it.quantity * step(it.id);
        meta[k] = { name: it.name, price: READY.indexOf(it.id) !== -1 ? f(it.prices.price) / 5 : f(it.prices.price) };
      });
      localStorage.setItem('fika_bag_v1', JSON.stringify({ bag: bag, meta: meta }));
    } catch (e) {}
  }
  var veil = null, panel = null, busy = false;
  function build() {
    veil = document.createElement('div'); veil.className = 'fke-veil';
    panel = document.createElement('div'); panel.className = 'fke-panel'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); panel.setAttribute('aria-label', 'Edit my bag');
    panel.innerHTML = '<div class="fke-head"><b>Your bag</b><button type="button" class="fke-x" aria-label="Close">&times;</button></div><div class="fke-body"></div><div class="fke-foot"><p class="fke-msg" aria-live="polite"></p><div class="fke-bfw"></div><div class="fke-sum"><span class="g"></span><span class="s"></span></div><button type="button" class="fke-done">Done</button></div>';
    document.body.appendChild(veil); document.body.appendChild(panel);
    veil.addEventListener('click', close);
    panel.querySelector('.fke-x').addEventListener('click', close);
    panel.querySelector('.fke-done').addEventListener('click', close);
    panel.addEventListener('click', onClick);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('on')) close(); });
  }
  function open() {
    if (!panel) build();
    msg(''); draw(); loadProducts();
    requestAnimationFrame(function () { veil.classList.add('on'); panel.classList.add('on'); });
    document.documentElement.style.overflow = 'hidden';
  }
  function close() { if (!panel) return; veil.classList.remove('on'); panel.classList.remove('on'); document.documentElement.style.overflow = ''; syncShopBag(); }
  function msg(t) { if (panel) panel.querySelector('.fke-msg').textContent = t || ''; }
  function draw() {
    if (!panel) return;
    var c = cart(), items = c.items || [], html = '', sub = 0;
    items.forEach(function (it) {
      var f = minor(it.prices), per = f(it.prices.price), g = it.quantity * step(it.id);
      if (isTaste(it)) {
        html += '<div class="fke-line fke-free"><div class="n">' + esc(it.name) + '<small>Mystery taste, 50 g, on us</small></div><div class="fke-p">Free</div></div>';
        return;
      }
      sub += per * it.quantity;
      var lim = it.quantity_limits || {}, max = lim.maximum && lim.maximum < 9999 ? lim.maximum : 0, atMax = max ? it.quantity >= max : false;
      html += '<div class="fke-line" data-key="' + esc(it.key) + '" data-q="' + it.quantity + '" data-max="' + max + '"><div class="n">' + esc(it.name) +
        '<small' + (atMax ? ' class="cap"' : '') + '>' + (atMax ? 'Max ' + gText(max * step(it.id), it.id) + ' per order' : (READY.indexOf(it.id) !== -1 ? money(per) + ' per 500 g bag' : money(per) + ' per 100 g')) + '</small></div>' +
        '<div class="fke-q"><button type="button" data-a="-" aria-label="Less ' + esc(it.name) + '">&minus;</button><span>' + gText(g, it.id) + '</span><button type="button" data-a="+"' + (atMax ? ' disabled' : '') + ' aria-label="More ' + esc(it.name) + '">+</button></div>' +
        '<div class="fke-p">' + money(per * it.quantity) + '</div><button type="button" class="fke-rm" data-a="x" aria-label="Remove ' + esc(it.name) + '">&times;</button></div>';
    });
    if (!items.length) html = '<p class="fke-empty">Your bag is empty. <a href="' + esc(C.shop) + '">Pick your sweets</a></p>';
    html += '<div class="fke-sug"></div>';
    panel.querySelector('.fke-body').innerHTML = html;
    var g = grams();
    panel.querySelector('.fke-sum .g').textContent = g >= 1000 ? (g / 1000) + ' kg' : g + ' g';
    panel.querySelector('.fke-sum .s').textContent = money(sub);
    var bfw = panel.querySelector('.fke-bfw');
    if (C.bf && g > 0) {
      var rate = g >= 1000 ? 20 : 10;
      bfw.innerHTML = '<div class="fke-bf">' + (g >= 1000 ? '<b>20% off unlocked</b> with ' + (g / 1000) + ' kg' : 'Black Friday ' + rate + '% off &middot; <b>' + (1000 - g) + ' g more for 20% off</b>') + '<span class="bar"><i style="width:' + Math.min(100, g / 10) + '%"></i></span></div>';
    } else { bfw.innerHTML = ''; }
    drawSug();
  }
  function loadProducts() {
    if (products || !window.fetch) return;
    fetch('/wp-json/wc/store/v1/products?per_page=100&orderby=menu_order&order=asc&catalog_visibility=catalog', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (d) { products = Array.isArray(d) ? d.filter(function (p) { return p.is_in_stock; }) : []; drawSug(); }).catch(function () {});
  }
  function drawSug() {
    var box = panel ? panel.querySelector('.fke-sug') : null;
    if (!box || !products) return;
    var inBag = (cart().items || []).map(function (it) { return it.id; });
    var list = products.filter(function (p) { return inBag.indexOf(p.id) === -1; }).slice(0, 10);
    if (!list.length) { box.innerHTML = ''; return; }
    box.innerHTML = '<h4>You may also like</h4><div class="row">' + list.map(function (p) {
      var img = p.images && p.images[0] ? (p.images[0].thumbnail || p.images[0].src) : '';
      var ready = READY.indexOf(p.id) !== -1;
      return '<div class="fke-card">' + (img ? '<img src="' + esc(img) + '" alt="" loading="lazy">' : '') + '<span class="t">' + esc(p.name) + '<small>' + (ready ? '500 g bag' : '100 g') + '</small></span><button type="button" data-add="' + p.id + '" aria-label="Add ' + (ready ? 'a 500 g bag' : '100 g') + ' of ' + esc(p.name) + '">+</button></div>';
    }).join('') + '</div>';
  }
  function run(job) {
    if (busy || !job) return;
    busy = true; panel.classList.add('busy'); msg('');
    job.then(function () {}, function (e) { msg(((e && e.message) || 'That did not work, please try again.').replace(/<[^>]*>/g, '')); })
      .then(function () { busy = false; panel.classList.remove('busy'); draw(); syncShopBag(); });
  }
  function onClick(e) {
    var d = act(); if (!d) return;
    var add = e.target.closest ? e.target.closest('[data-add]') : null;
    if (add) { run(d.addItemToCart(parseInt(add.getAttribute('data-add'), 10), 1)); return; }
    var b = e.target.closest ? e.target.closest('.fke-line [data-a]') : null;
    if (!b || b.disabled) return;
    var line = b.closest('.fke-line'), key = line.getAttribute('data-key'), q = parseInt(line.getAttribute('data-q'), 10), a = b.getAttribute('data-a');
    if (a === 'x' || (a === '-' && q <= 1)) { run(d.removeItemFromCart(key)); return; }
    run(d.changeCartItemQuantity(key, a === '+' ? q + 1 : q - 1));
  }
  // the button, at the top of every order summary that has totals (WooCommerce keeps a hidden copy first)
  function place() {
    [].forEach.call(document.querySelectorAll('.wp-block-woocommerce-checkout-order-summary-block'), function (sum) {
      if (!sum.querySelector('.wc-block-components-totals-wrapper') || sum.querySelector('.fke-btn')) return;
      var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'fke-btn';
      btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Edit my bag';
      btn.addEventListener('click', open);
      sum.insertBefore(btn, sum.firstChild);
    });
  }
  var t = null;
  new MutationObserver(function () { clearTimeout(t); t = setTimeout(place, 120); }).observe(document.body, { childList: true, subtree: true });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', place); else place();
  // the cart changed (here or by the checkout itself): redraw the open panel
  (function follow(tries) {
    if (!(window.wp && wp.data && wp.data.select && wp.data.select('wc/store/cart'))) { if (tries < 100) setTimeout(function () { follow(tries + 1); }, 300); return; }
    var last = ''; wp.data.subscribe(function () { var c = cart(), k = JSON.stringify((c.items || []).map(function (i) { return [i.key, i.quantity]; })); if (k !== last) { last = k; if (panel && panel.classList.contains('on') && !busy) draw(); } });
  })(0);
})();
</script>
	<?php
}, 30 );
