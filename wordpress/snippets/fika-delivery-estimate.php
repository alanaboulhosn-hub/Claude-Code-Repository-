<?php
/**
 * Fika: expected delivery date at checkout, the Nabatieh notice by the delivery area, and no country field.
 * - Owner's rule, from the time the order is placed (Beirut time): Inside Beirut, before 5 pm -> the next day,
 *   from 5 pm -> in 2 days. Outside Beirut, before 5 pm -> in 2 days, from 5 pm -> in 3 days. Sundays are skipped
 *   (RT delivers Monday to Saturday), the same rule as the "Delivery" column in WooCommerce > Orders.
 * - Shown above the Place order button: the date for the chosen delivery area, or both dates until one is picked.
 * - Under the delivery area: "We can't deliver to Nabatieh and bordering cities for now." (it used to scroll in the
 *   banner on every page).
 * - The country field is hidden: the shop delivers only in Lebanon, which it is set to already (fika-checkout.php).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-delivery-estimate.php
 */

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	?>
<style>
.wc-block-components-address-form__country { display: none !important; }
.fika-eta { margin: 0 0 14px; padding: 12px 14px; border-radius: 12px; background: #e7f1fb; color: #0b2a5b; font-size: 15px; line-height: 1.45; }
.fika-eta b { color: #004aad; }
.fika-eta small { display: block; margin-top: 3px; color: #4a5878; font-size: 13px; }
.fika-nab { margin: 6px 2px 0; color: #4a5878; font-size: 13px; line-height: 1.4; }
</style>
<script>
(function () {
	// today in Beirut, and the hour there
	function beirut() {
		var parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Beirut', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', hourCycle: 'h23' }).formatToParts(new Date());
		var v = {};
		parts.forEach(function (p) { v[p.type] = parseInt(p.value, 10); });
		return v;
	}
	function due(area) {
		var n = beirut();
		var days = (n.hour >= 17 ? 2 : 1) + (area === 'BA' ? 0 : 1);
		var d = new Date(Date.UTC(n.year, n.month - 1, n.day));
		while (days > 0) {
			d.setUTCDate(d.getUTCDate() + 1);
			if (d.getUTCDay() !== 0) { days--; } // no deliveries on Sundays
		}
		return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });
	}
	function area() {
		var s = document.querySelector('#shipping-state, #billing-state');
		return s ? s.value : '';
	}
	function draw() {
		var btn = document.querySelector('.wc-block-components-checkout-place-order-button');
		if (btn) {
			var box = document.getElementById('fika-eta');
			if (!box) {
				box = document.createElement('div');
				box.id = 'fika-eta';
				box.className = 'fika-eta';
				box.setAttribute('aria-live', 'polite');
				btn.parentNode.insertBefore(box, btn);
			}
			var a = area(), late = beirut().hour >= 17;
			var html = a === 'BA' || a === 'OB'
				? '<b>Expected delivery: ' + due(a) + '</b>'
				: '<b>Expected delivery</b><br>Inside Beirut: ' + due('BA') + '<br>Outside Beirut: ' + due('OB');
			html += '<small>' + (late ? 'Ordered after 5 pm, so it leaves with tomorrow’s deliveries.' : 'Order before 5 pm for this date.') + ' No deliveries on Sundays.</small>';
			if (box.innerHTML !== html) { box.innerHTML = html; }
		}
		var sel = document.querySelector('#shipping-state, #billing-state');
		var wrap = sel ? sel.closest('.wc-block-components-state-input, .wc-blocks-components-select, .wc-block-components-select-input') : null;
		if (wrap && !wrap.parentNode.querySelector('.fika-nab')) {
			var p = document.createElement('p');
			p.className = 'fika-nab';
			p.textContent = 'We can’t deliver to Nabatieh and bordering cities for now.';
			wrap.insertAdjacentElement('afterend', p);
		}
	}
	var t = null;
	new MutationObserver(function () { clearTimeout(t); t = setTimeout(draw, 120); }).observe(document.body, { childList: true, subtree: true });
	document.addEventListener('change', function () { setTimeout(draw, 50); }, true);
	setInterval(draw, 60000); // the 5 pm cut-off passes while the page is open
	draw();
})();
</script>
	<?php
}, 50 );
