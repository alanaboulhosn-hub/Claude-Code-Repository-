<?php
/**
 * Fika: after placing an order the customer lands on the home page with an "Order confirmed" thank-you popup.
 * - WooCommerce's own "Order received" page is kept as it is: it still loads (so everything WooCommerce and other
 *   tools do on it still happens), then the browser moves straight on to the home page. Without JavaScript, or when
 *   opened later from a link, the page shows as normal.
 * - The popup reads the order through its private order key (the same key WooCommerce puts in the order-received
 *   link), only for orders placed in the last 24 hours, and shows once.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-order-confirmed.php
 */

if ( ! function_exists( 'fika_oc_order' ) ) {
	// the order named in the link, if the key matches and it is recent
	function fika_oc_order( $id, $key ) {
		$order = $id ? wc_get_order( absint( $id ) ) : false;
		if ( ! $order || ! $key || ! hash_equals( (string) $order->get_order_key(), (string) $key ) ) {
			return false;
		}
		$made = $order->get_date_created();
		return ( $made ? $made->getTimestamp() > time() - DAY_IN_SECONDS : false ) ? $order : false;
	}
}

// ---------- the Order received page: let it load, then go home ----------
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	$id    = absint( get_query_var( 'order-received' ) );
	$key   = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$order = fika_oc_order( $id, $key );
	if ( ! $order ) {
		return;
	}
	$to = add_query_arg( array( 'fika_order' => $order->get_id(), 'key' => rawurlencode( $key ) ), home_url( '/' ) );
	echo '<script>document.documentElement.style.visibility="hidden";location.replace(' . wp_json_encode( $to ) . ');</script>' . "\n";
}, 1 );

// ---------- home page: the thank-you popup ----------
add_action( 'wp_footer', function () {
	if ( ! is_front_page() || empty( $_GET['fika_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$order = fika_oc_order( wp_unslash( $_GET['fika_order'] ), isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $order ) {
		return;
	}
	$first  = $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name();
	$state  = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
	$days   = 'BA' === $state ? '1–2 business days' : '2–3 business days';
	$grams  = 0;
	foreach ( $order->get_items() as $item ) {
		$pid    = $item->get_product_id();
		$grams += $item->get_quantity() * ( has_term( 'ready-mix', 'product_cat', $pid ) ? 500 : 100 );
	}
	$weight = $grams >= 1000 ? rtrim( rtrim( number_format( $grams / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg' : $grams . ' g';
	$email  = $order->get_billing_email();
	$data   = array(
		'id'      => $order->get_id(),
		'number'  => $order->get_order_number(),
		'first'   => $first,
		'total'   => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
		'value'   => (float) $order->get_total(),
		'cur'     => $order->get_currency(),
		'weight'  => $weight,
		'days'    => $days,
		'email'   => $email,
		'account' => is_user_logged_in() ? wc_get_account_endpoint_url( 'orders' ) : '',
	);
	?>
<style>
.fkoc-veil { position: fixed; inset: 0; z-index: 200000; display: flex; align-items: center; justify-content: center; padding: 18px; background: rgba(253, 234, 242, .78);
  -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); opacity: 0; transition: opacity .3s; }
.fkoc-veil.on { opacity: 1; }
.fkoc-card { position: relative; width: 100%; max-width: 460px; max-height: calc(100vh - 36px); overflow: auto; box-sizing: border-box; padding: 34px 30px 26px; border-radius: 28px; background: #fff;
  box-shadow: 0 24px 60px rgba(0, 74, 173, .22); text-align: center; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a;
  transform: translateY(22px) scale(.94); transition: transform .45s cubic-bezier(.3, 1.45, .5, 1); }
.fkoc-veil.on .fkoc-card { transform: none; }
.fkoc-card:focus { outline: none; }
.fkoc-x { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: #fdeaf2; color: #004aad; font-size: 22px; line-height: 1; cursor: pointer; }
.fkoc-toons { display: flex; justify-content: center; gap: 10px; height: 58px; margin: -4px 0 10px; }
.fkoc-toons i { display: block; width: 52px; height: 52px; animation: fkocBob 1.6s ease-in-out infinite alternate; }
.fkoc-toons i:nth-child(2) { width: 58px; height: 58px; margin-top: -6px; animation-delay: .25s; }
.fkoc-toons i:nth-child(3) { animation-delay: .5s; }
.fkoc-toons svg { display: block; width: 100%; height: 100%; overflow: visible; filter: drop-shadow(0 3px 3px rgba(80, 20, 50, .2)); }
@keyframes fkocBob { from { transform: translateY(0) rotate(-6deg); } to { transform: translateY(-6px) rotate(6deg); } }
.fkoc-tick { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; margin: 0 0 6px; border-radius: 50%; background: #004aad; color: #fff;
  animation: fkocPop .6s cubic-bezier(.3, 1.6, .5, 1) .2s both; }
.fkoc-tick svg { width: 20px; height: 20px; fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
@keyframes fkocPop { from { transform: scale(0) rotate(-30deg); } to { transform: none; } }
.fkoc-h { margin: 0 0 6px; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 38px; line-height: 1.05; color: #004aad; }
.fkoc-p { margin: 0 0 18px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 19px; line-height: 1.45; }
.fkoc-sum { margin: 0 0 16px; padding: 14px 18px; border-radius: 18px; background: #fdeaf2; text-align: left; font-size: 15px; }
.fkoc-sum div { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; }
.fkoc-sum b { color: #004aad; font-weight: 600; }
.fkoc-note { margin: 0 0 18px; font-size: 13.5px; color: #6c7b9c; }
.fkoc-go { display: block; width: 100%; padding: 15px 18px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 700 15px/1.2 'Outfit', 'Open Sans', Arial, sans-serif;
  letter-spacing: .03em; text-transform: uppercase; cursor: pointer; text-decoration: none; box-sizing: border-box; }
.fkoc-go:hover { background: #003a8a; color: #fff; }
.fkoc-link { display: inline-block; margin-top: 12px; color: #004aad; font-size: 14.5px; font-weight: 500; }
.fkoc-confetti { position: absolute; left: 50%; top: 34%; width: 0; height: 0; pointer-events: none; }
.fkoc-confetti i { position: absolute; left: -15px; top: -15px; width: 30px; height: 30px; opacity: 0; animation: fkocBurst 1.3s cubic-bezier(.2, .7, .3, 1) forwards; }
.fkoc-confetti svg { width: 100%; height: 100%; display: block; overflow: visible; }
@keyframes fkocBurst { 0% { opacity: 0; transform: translate(0, 0) scale(.3); } 15% { opacity: 1; } 100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--r)) scale(1); } }
@media (max-width: 480px) { .fkoc-card { padding: 30px 20px 22px; border-radius: 22px; } .fkoc-h { font-size: 32px; } }
@media (prefers-reduced-motion: reduce) { .fkoc-toons i, .fkoc-tick, .fkoc-confetti i { animation: none !important; } .fkoc-confetti { display: none; } }
</style>
<script>
(function () {
	var D = <?php echo wp_json_encode( $data ); ?>;
	// clean the address bar, so a refresh or a shared link does not show the popup again
	try { history.replaceState(null, '', location.pathname + location.hash); } catch (e) {}
	try { if (sessionStorage.getItem('fika_oc_' + D.id)) return; sessionStorage.setItem('fika_oc_' + D.id, '1'); } catch (e) {}
	// same event ID as the server's Purchase (wordpress/snippets/fika-meta.php), so Meta counts the order once
	try { if (typeof window.fikaTrack === 'function') window.fikaTrack('Purchase', { value: D.value, currency: D.cur }, 'purchase.' + D.id); else if (typeof window.fbq === 'function') window.fbq('track', 'Purchase', { value: D.value, currency: D.cur }); } catch (e) {}
	function esc(s) { var d = document.createElement('span'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
	function toon(s) { return window.FIKA_CARTOON ? window.FIKA_CARTOON(s) : ''; }
	function show() {
		var veil = document.createElement('div');
		veil.className = 'fkoc-veil';
		veil.setAttribute('role', 'dialog'); veil.setAttribute('aria-modal', 'true'); veil.setAttribute('aria-labelledby', 'fkocTitle');
		veil.innerHTML = '<div class="fkoc-card" tabindex="-1">' +
			'<button class="fkoc-x" type="button" aria-label="Close">×</button>' +
			'<div class="fkoc-toons" aria-hidden="true"><i>' + toon('sour-cherries') + '</i><i>' + toon('bubs-bubblegum-skull') + '</i><i>' + toon('swedish-fish') + '</i></div>' +
			'<div class="fkoc-tick" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg></div>' +
			'<h2 class="fkoc-h" id="fkocTitle">Order confirmed!</h2>' +
			'<p class="fkoc-p">Thank you' + (D.first ? ', ' + esc(D.first) : '') + '! We are packing your sweets with care.</p>' +
			'<div class="fkoc-sum">' +
				'<div><span>Order</span><b>#' + esc(D.number) + '</b></div>' +
				'<div><span>Your bag</span><b>' + esc(D.weight) + '</b></div>' +
				'<div><span>Total, cash on delivery</span><b>' + esc(D.total) + '</b></div>' +
				'<div><span>Delivery</span><b>' + esc(D.days) + '</b></div>' +
			'</div>' +
			(D.email ? '<p class="fkoc-note">Your receipt is on its way to ' + esc(D.email) + '.</p>' : '') +
			'<button class="fkoc-go" type="button">Keep shopping</button>' +
			(D.account ? '<a class="fkoc-link" href="' + esc(D.account) + '">See my orders</a>' : '') +
			'</div>';
		document.body.appendChild(veil);
		var card = veil.querySelector('.fkoc-card');
		function close() { veil.classList.remove('on'); setTimeout(function () { if (veil.parentNode) veil.parentNode.removeChild(veil); }, 320); }
		veil.addEventListener('click', function (e) { if (e.target === veil || e.target.closest('.fkoc-x, .fkoc-go')) close(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
		requestAnimationFrame(function () { veil.classList.add('on'); card.focus(); });
		// a burst of candy cartoons
		if (window.FIKA_CARTOON) {
			var slugs = (window.FIKA_CARTOON_SLUGS || []).slice().sort(function () { return Math.random() - 0.5; }).slice(0, 14);
			var c = document.createElement('div'); c.className = 'fkoc-confetti';
			slugs.forEach(function (sl, i) {
				var a = (i / slugs.length) * Math.PI * 2, d = 130 + Math.random() * 80, el = document.createElement('i');
				el.style.cssText = '--dx:' + Math.round(Math.cos(a) * d) + 'px;--dy:' + Math.round(Math.sin(a) * d) + 'px;--r:' + Math.round(Math.random() * 540 - 270) + 'deg;animation-delay:' + (0.25 + i * 0.02) + 's';
				el.innerHTML = toon(sl); c.appendChild(el);
			});
			card.appendChild(c);
			setTimeout(function () { if (c.parentNode) c.parentNode.removeChild(c); }, 2000);
		}
	}
	if (document.readyState === 'complete') show(); else window.addEventListener('load', show);
})();
</script>
	<?php
}, 60 );
