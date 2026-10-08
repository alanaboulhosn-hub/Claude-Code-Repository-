<?php
/**
 * Fika: nudges to create an account (visitors who are not signed in only).
 * - Home page: a speech-bubble "cloud" next to the account icon (the person waves while it shows).
 *     * the first time something goes into the bag: "Make every gram count — sign up and the 300 g in your bag
 *       starts your swim to Fika rewards" (the grams follow the bag live);
 *     * otherwise after 20 seconds of browsing: "Join the Fika crew".
 *   Perks: track orders, member-only offers, bundles. Blue "Sign up, it's free" button + "Log in" link.
 *   At most once per visit; closing it (×) hides it for 7 days; it fades by itself after 14 s (10 s on phones).
 * - Checkout: a reminder card above the form. "Create my account" ticks WooCommerce's own
 *   "Create an account with Fika" box and takes the shopper to the password field; "Log in" opens WooCommerce's login.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-signup-nudge.php
 */

// ---------- Home page cloud ----------
add_action( 'wp_footer', function () {
	if ( ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us', 'privacy-policy' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) || is_user_logged_in() ) {
		return;
	}
	$acct = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	?>
<style>
.fk-cloud { position: absolute; z-index: 40; width: 330px; padding: 18px 20px 16px; border-radius: 22px; background: #fff; box-shadow: 0 16px 44px rgba(0, 74, 173, .20);
  font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; text-align: left; opacity: 0; transform: translateY(-8px) scale(.94); transform-origin: 90% 0;
  transition: opacity .3s ease, transform .45s cubic-bezier(.3, 1.5, .5, 1); pointer-events: none; }
.fk-cloud.on { opacity: 1; transform: none; pointer-events: auto; }
.fk-cloud::before { content: ''; position: absolute; top: -9px; right: var(--tail, 24px); width: 20px; height: 20px; background: #fff; border-radius: 4px; transform: rotate(45deg); }
.fk-cloud .fkc-x { position: absolute; top: 10px; right: 12px; width: 30px; height: 30px; padding: 0; border: 0; border-radius: 50%; background: #fdeaf2; color: #004aad; font: 400 19px/30px Arial, sans-serif; cursor: pointer; }
.fk-cloud .fkc-x:hover { background: #f9d5e5; }
.fk-cloud .fkc-top { display: flex; gap: 12px; align-items: center; margin-bottom: 8px; padding-right: 30px; }
.fk-cloud .fkc-fish { flex: none; width: 56px; height: 56px; transform: scaleX(-1); animation: fkcBob 1.6s ease-in-out infinite; }
.fk-cloud .fkc-fish svg { display: block; width: 100%; height: 100%; }
.fk-cloud h3 { margin: 0; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 27px; letter-spacing: .02em; line-height: 1; color: #004aad; }
.fk-cloud p { margin: 0 0 10px; font-size: 15px; line-height: 1.45; }
.fk-cloud p b { color: #004aad; }
.fk-cloud ul { list-style: none; display: grid; gap: 5px; margin: 0 0 14px; padding: 0; font-size: 14.5px; }
.fk-cloud li { display: flex; gap: 8px; align-items: center; margin: 0; }
.fk-cloud li i { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #fdeaf2; }
.fk-cloud li svg { width: 14px; height: 14px; fill: none; stroke: #004aad; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.fk-cloud .fkc-go { display: flex; align-items: center; justify-content: center; width: 100%; height: 46px; border-radius: 999px; background: #004aad; color: #fff !important; text-decoration: none;
  font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.fk-cloud .fkc-go:hover { background: #003a8a; }
.fk-cloud .fkc-in { display: block; margin-top: 9px; text-align: center; font-size: 13.5px; color: #6b7894; }
.fk-cloud .fkc-in a { color: #004aad; font-weight: 600; }
.fika-acct.fa-wave .fa-arm { opacity: 1; animation: faWave .9s ease-in-out infinite; }
.fika-acct.fa-wave .fa-head { transform: rotate(-8deg); }
@keyframes fkcBob { 0%, 100% { transform: scaleX(-1) translateY(-2px) rotate(-3deg); } 50% { transform: scaleX(-1) translateY(2px) rotate(3deg); } }
@media (max-width: 700px) {
  .fk-cloud { width: min(300px, calc(100vw - 32px)); padding: 16px 16px 14px; }
  .fk-cloud h3 { font-size: 24px; }
  .fk-cloud .fkc-fish { width: 46px; height: 46px; }
  .fk-cloud ul { display: none; }
}
@media (prefers-reduced-motion: reduce) { .fk-cloud .fkc-fish, .fika-acct.fa-wave .fa-arm { animation: none; } }
</style>
<script>
(function () {
	var SIGNUP = <?php echo wp_json_encode( $acct . '#register' ); ?>, LOGIN = <?php echo wp_json_encode( $acct . '#login' ); ?>;
	var CLOSED = 'fika_nudge_closed_v1', SEEN = 'fika_nudge_seen_v1', WEEK = 7 * 864e5;
	function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
	function ss(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }
	function allowed() {
		if (ss(SEEN)) return false;
		var c = +ls(CLOSED) || 0;
		return !c || Date.now() - c > WEEK;
	}
	if (!allowed()) return;
	function grams() {
		var g = 0;
		try { var st = JSON.parse(localStorage.getItem('fika_bag_v1') || 'null'), bag = (st && st.bag) || {}; Object.keys(bag).forEach(function (k) { var v = +bag[k] || 0; if (v > 0) g += v; }); } catch (e) {}
		return g;
	}
	function gtext(g) { return g >= 1000 ? (Math.round(g / 100) / 10) + ' kg' : g + ' g'; }
	var FISH = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="3" stroke-linejoin="round"/><path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="#ff5a36" stroke="#a3160f" stroke-width="3" stroke-linejoin="round"/><ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/><circle cx="24" cy="46" r="5.5" fill="#fff"/><circle cx="22.5" cy="46.5" r="3" fill="#1b2a4a"/><path d="M10 56 Q14 59 18 57" fill="none" stroke="#a3160f" stroke-width="2.2" stroke-linecap="round"/></svg>';
	var IC = {
		box: '<svg viewBox="0 0 24 24"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>',
		gift: '<svg viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="5" rx="1"/><path d="M5 13v8h14v-8M12 8v13M12 8C10 4 6 4 7 7s5 1 5 1 4 2 5-1-3-3-5 1"/></svg>',
		candy: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5"/><path d="M7.8 10.2 3 7.5v9l4.8-2.7M16.2 10.2 21 7.5v9l-4.8-2.7"/></svg>'
	};
	var cloud = null, acct = null, header = null, timer = 0, kind = '', shown = false, startG = grams();
	var noHover = window.matchMedia ? window.matchMedia('(hover: none)').matches : false;

	function place() {
		if (!cloud || !acct || !header) return;
		var h = header.getBoundingClientRect(), a = acct.getBoundingClientRect(), btn = acct.querySelector('.fa-btn') || acct;
		var b = btn.getBoundingClientRect();
		var right = Math.max(8, h.right - a.right - 10);
		cloud.style.right = right + 'px';
		cloud.style.top = (b.bottom - h.top + 14) + 'px';
		var center = h.right - right - (b.left + b.width / 2);
		cloud.style.setProperty('--tail', Math.max(14, center - 10) + 'px');
	}
	function words() {
		if (!cloud) return;
		var g = grams(), bag = kind === 'bag' || g > 0;
		cloud.querySelector('h3').textContent = bag ? 'Make every gram count' : 'Join the Fika crew';
		var p = cloud.querySelector('p');
		if (bag) {
			p.innerHTML = 'Sign up and the <b class="fkc-g"></b> in your bag starts your swim to <b>sweet rewards</b>: 200 g free at 3 kg, a whole kilo at 10 kg.';
			p.querySelector('.fkc-g').textContent = gtext(g);
		} else {
			p.innerHTML = 'Create a free account and every order fills your fish&rsquo;s lane: <b>200 g free at 3 kg, 25% off at 6 kg, a whole kilo free at 10 kg</b>.';
		}
	}
	function hide(closedByUser) {
		if (!cloud) return;
		clearTimeout(timer);
		cloud.classList.remove('on');
		if (acct) acct.classList.remove('fa-wave');
		if (closedByUser) ls(CLOSED, String(Date.now()));
		setTimeout(function () { if (cloud) { cloud.remove(); cloud = null; } }, 400);
	}
	function arm(ms) { clearTimeout(timer); timer = setTimeout(function () { hide(false); }, ms); }
	function show(k) {
		if (shown || !allowed()) return;
		acct = document.querySelector('.fika-header .fika-acct');
		header = document.querySelector('.fika-header');
		if (!acct || !header) return;
		if (acct.classList.contains('open') || acct.matches(':hover')) return;
		if (document.querySelector('.mx-drawer.open, .mx-drawer.is-open, body.mx-open')) return;
		shown = true; kind = k;
		ss(SEEN, '1');
		cloud = document.createElement('div');
		cloud.className = 'fk-cloud';
		cloud.setAttribute('role', 'dialog');
		cloud.setAttribute('aria-label', 'Create a Fika account');
		cloud.innerHTML = '<button type="button" class="fkc-x" aria-label="Close">&times;</button>' +
			'<div class="fkc-top"><span class="fkc-fish">' + FISH + '</span><h3></h3></div><p></p>' +
			'<ul><li><i>' + IC.box + '</i>Track your orders</li><li><i>' + IC.gift + '</i>Member-only offers</li><li><i>' + IC.candy + '</i>Bundles before anyone else</li></ul>' +
			'<a class="fkc-go" href="' + SIGNUP + '">Sign up, it&rsquo;s free</a>' +
			'<span class="fkc-in">Already have an account? <a href="' + LOGIN + '">Log in</a></span>';
		header.appendChild(cloud);
		words();
		place();
		requestAnimationFrame(function () { requestAnimationFrame(function () { if (cloud) cloud.classList.add('on'); }); });
		acct.classList.add('fa-wave');
		cloud.querySelector('.fkc-x').addEventListener('click', function () { hide(true); });
		cloud.addEventListener('mouseenter', function () { clearTimeout(timer); });
		cloud.addEventListener('mouseleave', function () { arm(6000); });
		// opening the account menu takes over from the cloud
		acct.addEventListener('mouseenter', function () { hide(false); }, { once: true });
		acct.addEventListener('click', function () { hide(false); }, { once: true });
		arm(noHover ? 10000 : 14000);
	}
	window.addEventListener('resize', place);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && cloud) hide(true); });

	// the first time something goes into the bag
	function onBag() {
		var g = grams();
		if (cloud) { words(); return; }
		if (g > startG) setTimeout(function () { show('bag'); }, 900);
		startG = Math.min(startG, g);
	}
	window.addEventListener('fikabag', function () { setTimeout(onBag, 0); });
	setInterval(onBag, 700);
	// or after 20 seconds of browsing
	setTimeout(function () { show(grams() > 0 ? 'bag' : 'browse'); }, 20000);
})();
</script>
	<?php
}, 35 );

// ---------- Checkout reminder ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_user_logged_in() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	$rm = get_posts( array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'ready-mix' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	?>
<style>
.fika-signcard { display: flex; align-items: center; gap: 16px 18px; max-width: 1100px; margin: 0 auto 22px; padding: 16px 22px; border-radius: 20px; background: #fff; box-shadow: 0 6px 24px rgba(0, 74, 173, .08);
  font-family: 'Outfit', Arial, sans-serif; color: #1b2a4a; box-sizing: border-box; }
.fika-signcard .fsc-fish { flex: none; width: 54px; height: 54px; transform: scaleX(-1); animation: fscBob 1.6s ease-in-out infinite; }
.fika-signcard .fsc-fish svg { display: block; width: 100%; height: 100%; }
.fika-signcard .fsc-text { flex: 1; min-width: 0; }
.fika-signcard b.fsc-title { display: block; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 26px; letter-spacing: .02em; line-height: 1.05; color: #004aad; }
.fika-signcard .fsc-sub { font-size: 15px; line-height: 1.45; }
.fika-signcard .fsc-sub b { color: #004aad; }
.fika-signcard .fsc-acts { flex: none; display: flex; flex-direction: column; align-items: center; gap: 7px; }
.fika-signcard .fsc-go { height: 46px; padding: 0 24px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; white-space: nowrap; }
.fika-signcard .fsc-go:hover { background: #003a8a; }
.fika-signcard .fsc-in { font-size: 13.5px; color: #6b7894; }
.fika-signcard .fsc-in a { color: #004aad; font-weight: 600; cursor: pointer; }
.fika-signcard.is-on { border-left: 6px solid #2fb36b; }
.fika-signcard.is-on .fsc-acts { display: none; }
@keyframes fscBob { 0%, 100% { transform: scaleX(-1) translateY(-2px) rotate(-3deg); } 50% { transform: scaleX(-1) translateY(2px) rotate(3deg); } }
@media (max-width: 700px) {
  .fika-signcard { flex-wrap: wrap; padding: 16px; border-radius: 18px; }
  .fika-signcard .fsc-fish { width: 44px; height: 44px; }
  .fika-signcard .fsc-text { flex: 1 1 calc(100% - 64px); }
  .fika-signcard .fsc-acts { flex: 1 1 100%; }
  .fika-signcard .fsc-go { width: 100%; }
}
@media (prefers-reduced-motion: reduce) { .fika-signcard .fsc-fish { animation: none; } }
</style>
<script>
(function () {
	var RM = <?php echo wp_json_encode( array_map( 'intval', $rm ) ); ?>;
	var FISH = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="3" stroke-linejoin="round"/><path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="#ff5a36" stroke="#a3160f" stroke-width="3" stroke-linejoin="round"/><ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/><circle cx="24" cy="46" r="5.5" fill="#fff"/><circle cx="22.5" cy="46.5" r="3" fill="#1b2a4a"/><path d="M10 56 Q14 59 18 57" fill="none" stroke="#a3160f" stroke-width="2.2" stroke-linecap="round"/></svg>';
	function cartGrams() {
		try {
			var items = wp.data.select('wc/store/cart').getCartData().items || [], g = 0;
			items.forEach(function (it) { g += (RM.indexOf(it.id) >= 0 ? 500 : 100) * (it.quantity || 0); });
			return g;
		} catch (e) { return 0; }
	}
	function gtext(g) { return g >= 1000 ? (Math.round(g / 100) / 10) + ' kg' : g + ' g'; }
	function box() {
		var lab = [].slice.call(document.querySelectorAll('.wc-block-checkout label, .wc-block-components-checkbox label')).filter(function (l) { return /create an account/i.test(l.textContent); })[0];
		return lab ? (lab.querySelector('input[type=checkbox]') || document.getElementById(lab.getAttribute('for'))) : null;
	}
	function loginLink() {
		return [].slice.call(document.querySelectorAll('.wc-block-checkout a, .wc-block-components-checkout-step a')).filter(function (a) { return /^\s*log in\s*$/i.test(a.textContent); })[0] || null;
	}
	var card = null, last = '';
	function sync() {
		if (!card) return;
		var g = cartGrams(), cb = box(), on = cb ? cb.checked : false, key = g + ':' + on;
		if (key === last) return;
		last = key;
		var gEl;
		if (on) {
			card.classList.add('is-on');
			card.querySelector('.fsc-title').textContent = 'Your account comes with this order';
			card.querySelector('.fsc-sub').innerHTML = 'Choose a password below and this <b class="fsc-g"></b> becomes your first stretch towards <b>sweet rewards</b>: 200 g free at 3 kg, a whole kilo at 10 kg.';
		} else {
			card.classList.remove('is-on');
			card.querySelector('.fsc-title').textContent = 'Make this order count';
			card.querySelector('.fsc-sub').innerHTML = 'Create a free account and this <b class="fsc-g"></b> starts your swim to <b>sweet rewards</b> (200 g free at 3 kg, a whole kilo at 10 kg). Track your orders, and get member-only offers and bundles.';
		}
		gEl = card.querySelector('.fsc-g');
		if (gEl) gEl.textContent = g ? gtext(g) : 'order';
	}
	function mount() {
		var anchor = document.querySelector('.fika-shoptitle');
		if (!anchor || document.querySelector('.fika-signcard')) return;
		card = document.createElement('div');
		card.className = 'fika-signcard';
		card.innerHTML = '<span class="fsc-fish">' + FISH + '</span><div class="fsc-text"><b class="fsc-title"></b><div class="fsc-sub"></div></div>' +
			'<div class="fsc-acts"><button type="button" class="fsc-go">Create my account</button><span class="fsc-in">Already a member? <a class="fsc-login">Log in</a></span></div>';
		anchor.parentNode.insertBefore(card, anchor.nextSibling);
		card.querySelector('.fsc-go').addEventListener('click', function () {
			var cb = box();
			if (!cb) { location.href = <?php echo wp_json_encode( ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ) . '#register' ); ?>; return; }
			if (!cb.checked) cb.click();
			var wrap = cb.closest('.wc-block-components-checkbox') || cb;
			wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
			setTimeout(function () {
				var pw = document.querySelector('.wc-block-checkout input[type=password]');
				if (pw) pw.focus({ preventScroll: true });
				sync();
			}, 450);
		});
		card.querySelector('.fsc-login').addEventListener('click', function () {
			var a = loginLink();
			location.href = a ? a.href : <?php echo wp_json_encode( ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ) . '#login' ); ?>;
		});
		document.addEventListener('change', function (e) { if (e.target === box()) setTimeout(sync, 0); });
		sync();
		if (window.wp ? wp.data : null) wp.data.subscribe(sync);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
	setTimeout(mount, 1500);
})();
</script>
	<?php
}, 30 );
