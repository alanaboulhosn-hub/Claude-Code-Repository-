<?php
/**
 * Fika: Black Friday deal, switched on and off like the Halloween look (owner, 24 Nov 2026).
 * - Installed switched off. One click on "Black Friday deal" in the WP admin top bar switches it on (option
 *   fika_bf_on), another click switches it off. The page cache is cleared at each click.
 * - The deal: 10% off the sweets, 20% off from 1 kg in the bag (everything in the bag counts, Ready Mix bags as 500 g;
 *   the free mystery taste does not). A discount line "Black Friday 10% off" / "Black Friday 20% off" on the bag at
 *   checkout and on the order. Delivery is never discounted.
 * - Nothing else combines with it (owner, 24 Nov 2026): Fika Club rewards (SWIM codes), FIKA10 and any other code are
 *   refused with a message saying they are saved for the next order after Black Friday (refused codes are not used
 *   up); the $5 store credit (IOU codes) points to the deal instead, and the $5 email (win-back email 2) is paused
 *   while the switch is on (fika-winback.php); the free mystery taste stays out of the bag and stays won (fika-taste.php); the
 *   before-you-go popup skips its 10% offer (its question about why people leave keeps running).
 * - Every gram counts in Fika Club, so nobody loses progress. The per-order caps (fika-order-caps.php) stay.
 * - The bag drawer on the shop pages shows the Black Friday line, a progress bar to 1 kg, the lower total and what is
 *   still needed for 20%; with the bag closed, each change shows a short note ("0.9 kg in your bag · 100 g more for 20%").
 * - The checkout shows the same above its totals: "You're 100 g away from 20% off" with the bar and "Add more" (back to
 *   Mix your own), or "20% off unlocked with 1 kg".
 * - The home page hero carries an "Up to 20% off" sticker ("10% on everything / 20% from 1 kg" under it) linking to Mix your own.
 * - Private preview while it is off: any shop page with ?fika_bf_preview=<FIKA_BF_PREVIEW_KEY> switches it on for that
 *   browser only (a cookie for 3 hours, pages not cached for it), nobody else; a small note says it is a preview.
 * - GET /wp-json/fika/v1/black-friday (shop admins): whether it is on.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-black-friday.php
 */

if ( ! defined( 'FIKA_BF_PREVIEW_KEY' ) ) {
	define( 'FIKA_BF_PREVIEW_KEY', 'bf7c41e9a2' );
}

if ( ! function_exists( 'fika_bf_on' ) ) {
	// the preview cookie: set by the preview link, signed, 3 hours
	function fika_bf_preview() {
		if ( isset( $_GET['fika_bf_preview'] ) && is_string( $_GET['fika_bf_preview'] ) && hash_equals( FIKA_BF_PREVIEW_KEY, $_GET['fika_bf_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		$c = isset( $_COOKIE['fika_bf_preview'] ) ? (string) $_COOKIE['fika_bf_preview'] : '';
		$p = explode( '.', $c );
		return 2 === count( $p ) && (int) $p[0] > time() && hash_equals( wp_hash( 'fika_bf_preview|' . $p[0] ), $p[1] );
	}
	function fika_bf_on() {
		return (bool) get_option( 'fika_bf_on' ) || fika_bf_preview();
	}
	// grams and sweets value of the bag (the free mystery taste does not count)
	function fika_bf_bag( $cart ) {
		$g   = 0;
		$sub = 0.0;
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['fika_taste'] ) || empty( $item['data'] ) ) {
				continue;
			}
			$p    = $item['data'];
			$id   = $p->get_parent_id() ? $p->get_parent_id() : $p->get_id();
			$g   += (int) $item['quantity'] * ( has_term( 'ready-mix', 'product_cat', $id ) ? 500 : 100 );
			$sub += (float) $item['line_subtotal'];
		}
		return array( 'g' => $g, 'sub' => $sub );
	}
	function fika_bf_rate( $grams ) {
		return $grams >= 1000 ? 20 : 10;
	}
}

// the preview link sets the cookie, and pages are not cached for that browser
add_action( 'init', function () {
	if ( isset( $_GET['fika_bf_preview'] ) && is_string( $_GET['fika_bf_preview'] ) && hash_equals( FIKA_BF_PREVIEW_KEY, $_GET['fika_bf_preview'] ) && ! headers_sent() ) { // phpcs:ignore WordPress.Security.NonceVerification
		$until = time() + 3 * HOUR_IN_SECONDS;
		setcookie( 'fika_bf_preview', $until . '.' . wp_hash( 'fika_bf_preview|' . $until ), array( 'expires' => $until, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		$_COOKIE['fika_bf_preview'] = $until . '.' . wp_hash( 'fika_bf_preview|' . $until );
	}
	if ( ! get_option( 'fika_bf_on' ) && fika_bf_preview() ) {
		do_action( 'litespeed_control_set_nocache', 'Fika Black Friday preview' );
	}
} );

// the deal: a discount line on the bag (sweets only, never delivery)
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
	if ( ! fika_bf_on() || ! $cart instanceof WC_Cart ) {
		return;
	}
	$b = fika_bf_bag( $cart );
	if ( $b['sub'] <= 0 ) {
		return;
	}
	$rate = fika_bf_rate( $b['g'] );
	$cart->add_fee( 'Black Friday ' . $rate . '% off', -round( $b['sub'] * $rate / 100, 2 ), false );
}, 20 );

// no other discount while it is on (owner, 24 Nov 2026): Fika Club rewards, the $5 store credit, FIKA10 and any other
// code are refused with a message, so they are not used up and still work after Black Friday
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || ! fika_bf_on() || ! $coupon instanceof WC_Coupon ) {
		return $valid;
	}
	if ( function_exists( 'fika_swim_is_code' ) && fika_swim_is_code( $coupon->get_code() ) ) {
		throw new Exception( 'Black Friday is on, so your Fika Club reward is saved in your account for your next order after Black Friday. Every gram of this order still counts towards your rewards.' );
	}
	if ( function_exists( 'fika_wb_is_owe' ) && fika_wb_is_owe( $coupon ) ) {
		throw new Exception( 'Black Friday is on, so instead of your $5 you get 10% off your sweets, and 20% off from 1 kg.' );
	}
	throw new Exception( 'Black Friday is on: you already get 10% off your sweets, and 20% off from 1 kg, so codes cannot be added now. Your code still works after Black Friday.' );
}, 5, 2 );

// the shop pages: the before-you-go popup skips its 10% offer; the bag drawer shows the deal
add_action( 'wp_footer', function () {
	if ( ! fika_bf_on() || is_admin() ) {
		return;
	}
	$preview = ! get_option( 'fika_bf_on' );
	?>
<style>
.fkbf { margin: 0 0 8px; padding: 9px 12px; border-radius: 10px; background: #111827; color: #fff; font: 600 13px/1.35 'Outfit', 'Open Sans', Arial, sans-serif; }
.fkbf .r { display: flex; justify-content: space-between; gap: 10px; }
.fkbf .h { display: block; margin-top: 3px; font-weight: 500; font-size: 12px; color: #fcd34d; }
.fkbf .bar { display: block; position: relative; height: 8px; margin-top: 8px; border-radius: 99px; background: rgba(255, 255, 255, .16); overflow: hidden; }
.fkbf .bar i { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 99px; background: #fcd34d; transition: width .35s ease; }
.fkbf .ends { display: flex; justify-content: space-between; margin-top: 4px; font-size: 10.5px; font-weight: 500; color: rgba(255, 255, 255, .7); }
.fkbf.won { background: linear-gradient(135deg, #111827, #3b2f05); }
.fkbf-toast { position: fixed; left: 50%; bottom: 88px; z-index: 100000; transform: translate(-50%, 12px); opacity: 0; max-width: calc(100vw - 32px);
  padding: 10px 16px; border-radius: 999px; background: #111827; color: #fff; font: 600 13px/1.3 'Outfit', 'Open Sans', Arial, sans-serif;
  box-shadow: 0 8px 24px rgba(17, 24, 39, .3); white-space: nowrap; pointer-events: none; transition: opacity .25s ease, transform .25s ease; }
.fkbf-toast b { color: #fcd34d; }
.fkbf-co { margin: 0 0 14px; padding: 12px 14px; border-radius: 10px; background: #111827; color: #fff; font: 600 14px/1.35 'Outfit', 'Open Sans', Arial, sans-serif; }
.fkbf-co.won { background: linear-gradient(135deg, #111827, #3b2f05); }
.fkbf-co .h { display: block; color: #fcd34d; }
.fkbf-co .bar { display: block; position: relative; height: 8px; margin: 9px 0 5px; border-radius: 99px; background: rgba(255, 255, 255, .16); overflow: hidden; }
.fkbf-co .bar i { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 99px; background: #fcd34d; }
.fkbf-co .m { display: block; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, .14); font-size: 12px; font-weight: 500; color: rgba(255, 255, 255, .8); }
.fkbf-co .s { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; font-weight: 500; color: rgba(255, 255, 255, .75); }
.fkbf-co a.fkbf-more, .fkbf-co a.fkbf-more:visited, .fkbf-co a.fkbf-more:hover { color: #fcd34d !important; text-decoration: underline; text-underline-offset: 2px; font-weight: 600; white-space: nowrap; }
.fkbf-toast.on { opacity: 1; transform: translate(-50%, 0); }
.fkbf-stk { position: absolute; z-index: 3; right: 12%; bottom: 200px; width: 196px; height: 196px; border-radius: 50%; background: #111827;
  color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; text-decoration: none;
  transform: rotate(-9deg); box-shadow: 0 10px 28px rgba(17, 24, 39, .35); font-family: 'Outfit', 'Open Sans', Arial, sans-serif; transition: transform .2s ease; }
.fkbf-stk::before { content: ''; position: absolute; inset: 7px; border: 2px dashed rgba(252, 211, 77, .7); border-radius: 50%; }
.fkbf-stk:hover { transform: rotate(-4deg) scale(1.04); }
.fkbf-stk .t1, .fkbf-stk .t3 { font-weight: 700; font-size: 14px; letter-spacing: .14em; line-height: 1; }
.fkbf-stk .t2 { font-family: 'NF Le Petit Cochon', 'Outfit', cursive; font-size: 54px; line-height: .95; color: #fcd34d; margin: 2px 0 1px; }
.fkbf-stk .t4 { margin-top: 7px; font-size: 11.5px; font-weight: 600; color: #fff; letter-spacing: .02em; line-height: 1.3; }
.fkbf-stk .t4 b { color: #fcd34d; font-weight: 700; }
@media (max-width: 760px) {
  .fkbf-stk { right: 14px; bottom: auto; top: 192px; width: 140px; height: 140px; }
  .fkbf-stk::before { inset: 5px; }
  .fkbf-stk .t1, .fkbf-stk .t3 { font-size: 10px; }
  .fkbf-stk .t2 { font-size: 40px; }
  .fkbf-stk .t4 { font-size: 8.5px; margin-top: 5px; }
}
.fkbf-prev { position: fixed; left: 50%; bottom: 10px; transform: translateX(-50%); z-index: 100001; padding: 6px 12px; border-radius: 999px; background: #111827; color: #fcd34d; font: 600 12px/1.2 Arial, sans-serif; pointer-events: none; }
</style>
<script>
(function () {
	window.FIKA_FREEKG = window.FIKA_FREEKG || 'blackfriday';   // before-you-go popup: no 10% offer during Black Friday
	var KEY = 'fika_bag_v1';
	function grams() {
		try { var s = JSON.parse(localStorage.getItem(KEY) || 'null'); var b = (s ? s.bag : null) || {}, g = 0; Object.keys(b).forEach(function (k) { g += +b[k] || 0; }); return g; } catch (e) { return 0; }
	}
	function money(x) { return '$' + (Math.round(x * 100) / 100).toFixed(2); }
	function num(t) { return parseFloat(String(t || '').replace(/[^0-9.]/g, '')) || 0; }
	var busy = false;
	function paint() {
		var sub = document.getElementById('mxSub'), tot = document.getElementById('mxTot');
		if (!sub || !tot || busy) return;
		busy = true;
		var s = num(sub.textContent), g = grams(), box = document.querySelector('.fkbf');
		if (s > 0) {
			var rate = g >= 1000 ? 20 : 10, off = Math.round(s * rate) / 100;
			var hint = g >= 1000 ? '20% off unlocked' : 'Add ' + (1000 - g) + ' g more for 20% off';
			if (!box) { box = document.createElement('div'); box.className = 'fkbf'; sub.parentNode.parentNode.insertBefore(box, sub.parentNode); }
			box.className = 'fkbf' + (g >= 1000 ? ' won' : '');
			box.innerHTML = '<span class="r"><span>Black Friday ' + rate + '% off</span><span>&minus;' + money(off) + '</span></span>' +
				'<span class="bar"><i style="width:' + Math.min(100, g / 10) + '%"></i></span>' +
				'<span class="ends"><span>' + kg(g) + ' in your bag</span><span>1 kg = 20% off</span></span><span class="h">' + hint + '</span>';
			tot.textContent = money(s - off);
		} else if (box) { box.remove(); }
		// the bag is closed: a short note after each change, so the 20% is seen without opening the bag
		if (last !== null && g !== last && g > 0) {
			var open = document.querySelector('.mx-drawer.on');
			if (!open) toast(g >= 1000 ? '<b>20% off unlocked</b> · ' + kg(g) + ' in your bag' : kg(g) + ' in your bag · <b>' + (1000 - g) + ' g more for 20% off</b>');
		}
		last = g;
		busy = false;
	}
	var last = null, tt = null;
	function kg(g) { return g >= 1000 ? (g / 1000).toFixed(1).replace(/\.0$/, '') + ' kg' : g + ' g'; }
	function toast(html) {
		var t = document.querySelector('.fkbf-toast');
		if (!t) { t = document.createElement('div'); t.className = 'fkbf-toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
		t.innerHTML = html; t.classList.add('on');
		clearTimeout(tt); tt = setTimeout(function () { t.classList.remove('on'); }, 3200);
	}
	function start() {
		var sub = document.getElementById('mxSub');
		if (!sub) return;
		paint();
		new MutationObserver(function () { setTimeout(paint, 0); }).observe(sub, { childList: true, characterData: true, subtree: true });
		var tot = document.getElementById('mxTot');
		if (tot) new MutationObserver(function () { if (!busy) setTimeout(paint, 0); }).observe(tot, { childList: true, characterData: true, subtree: true });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
	<?php if ( is_front_page() ) : ?>
	// the home page hero: an "Up to 20% off" sticker, linking to Mix your own
	function sticker() {
		var h = document.querySelector('.fika-hero:not(.fika-shop-hero)');
		if (!h || h.querySelector('.fkbf-stk')) return;
		var a = document.createElement('a');
		a.className = 'fkbf-stk'; a.href = '/mix-your-own/';
		a.setAttribute('aria-label', 'Black Friday: up to 20% off. 10% off everything, 20% off from 1 kg');
		a.innerHTML = '<span class="t1">UP TO</span><span class="t2">20%</span><span class="t3">OFF</span><span class="t4"><b>10%</b> on everything<br><b>20%</b> from 1 kg</span>';
		h.appendChild(a);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sticker); else sticker();
	<?php endif; ?>
	<?php if ( function_exists( 'is_checkout' ) && is_checkout() && WC()->cart ) : $fika_bfb = fika_bf_bag( WC()->cart ); ?>
	// the checkout: how close the bag is to 20%, just above the totals
	(function () {
		var g = <?php echo (int) $fika_bfb['g']; ?>;
		if (!g) return;
		function html() {
			if (g >= 1000) return '<span class="h">20% off unlocked with ' + kg(g) + '</span><span class="bar"><i style="width:100%"></i></span><span class="s"><span>Black Friday: 20% off from 1 kg</span></span>';
			return '<span class="h">You\'re ' + (1000 - g) + ' g away from 20% off</span><span class="bar"><i style="width:' + (g / 10) + '%"></i></span>' +
				'<span class="s"><span>' + kg(g) + ' in your bag &middot; 1 kg = 20% off</span><a class="fkbf-more" href="/mix-your-own/">Add more</a></span>';
		}
		var member = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
		// every order summary that has totals (WooCommerce also keeps a hidden copy of the summary, first in the page)
		function place() {
			[].forEach.call(document.querySelectorAll('.wp-block-woocommerce-checkout-order-summary-block'), function (sum) {
				var tot = sum.querySelector('.wc-block-components-totals-wrapper');
				if (!tot || sum.querySelector('.fkbf-co')) return;
				var n = document.createElement('div');
				n.className = 'fkbf-co' + (g >= 1000 ? ' won' : ''); n.innerHTML = html() + (member ? '<span class="m">Your Fika Club rewards are saved for your next order after Black Friday. Every gram here still counts.</span>' : '');
				tot.parentNode.insertBefore(n, tot);
			});
		}
		var t = null;
		new MutationObserver(function () { clearTimeout(t); t = setTimeout(place, 120); }).observe(document.body, { childList: true, subtree: true });
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', place); else place();
	})();
	<?php endif; ?>
	<?php if ( $preview ) : ?>
	document.addEventListener('DOMContentLoaded', function () { var n = document.createElement('div'); n.className = 'fkbf-prev'; n.textContent = 'Black Friday preview (only you see this)'; document.body.appendChild(n); });
	<?php endif; ?>
})();
</script>
	<?php
}, 5 );

// the switch in the WP admin top bar
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$off = ! get_option( 'fika_bf_on' );
	$bar->add_node( array(
		'id'    => 'fika-bf',
		'title' => $off ? '&#127873; Black Friday deal: off (click to switch on)' : '&#127873; Black Friday deal: ON (click to switch off)',
		'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=fika_bf_toggle' ), 'fika_bf_toggle' ),
	) );
}, 101 );
add_action( 'admin_post_fika_bf_toggle', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'fika_bf_toggle' );
	update_option( 'fika_bf_on', get_option( 'fika_bf_on' ) ? 0 : 1, false );
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'fika_cache_purge_all' ) ) {
		fika_cache_purge_all();
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
	exit;
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/black-friday', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function () {
			return array( 'on' => (bool) get_option( 'fika_bf_on' ) );
		},
	) );
} );
