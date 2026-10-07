<?php
/**
 * Fika: a maximum per order for chosen products.
 * - Product edit page > Inventory: "Max per order (grams)". Candies in 100 g steps, Ready Mix in 500 g bags.
 *   Empty = no cap. Stored in product meta fika_max_g.
 * - Enforced by WooCommerce (cart, Store API used by the bag and checkout, and the final checkout check).
 * - Home page: capped candies show "Max 500 g per order"; + stops at the cap (card and bag drawer) with a short note;
 *   a bag that already holds more than the cap is trimmed to it.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-order-caps.php
 */

if ( ! function_exists( 'fika_cap_step' ) ) {
	// grams in one unit of the product: 500 for a Ready Mix bag, 100 for candies
	function fika_cap_step( $product ) {
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return has_term( 'ready-mix', 'product_cat', $id ) ? 500 : 100;
	}
}
if ( ! function_exists( 'fika_cap_qty' ) ) {
	// the cap in quantity units, or 0 when the product has no cap
	function fika_cap_qty( $product ) {
		if ( ! $product ) {
			return 0;
		}
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$g  = (int) get_post_meta( $id, 'fika_max_g', true );
		return $g > 0 ? max( 1, (int) floor( $g / fika_cap_step( $product ) ) ) : 0;
	}
}
if ( ! function_exists( 'fika_cap_text' ) ) {
	function fika_cap_text( $product ) {
		$q    = fika_cap_qty( $product );
		$step = fika_cap_step( $product );
		if ( 500 === $step ) {
			return $q . ( 1 === $q ? ' bag' : ' bags' );
		}
		$g = $q * $step;
		return $g >= 1000 ? rtrim( rtrim( number_format( $g / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg' : $g . ' g';
	}
}

// ---------- Product edit page: the field ----------
add_action( 'woocommerce_product_options_inventory_product_data', function () {
	echo '<div class="options_group">';
	woocommerce_wp_text_input( array(
		'id'                => 'fika_max_g',
		'label'             => 'Max per order (grams)',
		'type'              => 'number',
		'custom_attributes' => array( 'min' => '0', 'step' => '100' ),
		'desc_tip'          => true,
		'description'       => 'The most one order can include, in grams: e.g. 500 for 500 g of a candy, 1000 for two Ready Mix bags. Leave empty for no cap.',
		'placeholder'       => 'No cap',
	) );
	echo '</div>';
} );
add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
	if ( isset( $_POST['fika_max_g'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$g = absint( wp_unslash( $_POST['fika_max_g'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $g > 0 ) {
			$product->update_meta_data( 'fika_max_g', $g );
		} else {
			$product->delete_meta_data( 'fika_max_g' );
		}
	}
} );

// ---------- Enforcement ----------
// Store API (the home page bag hand-off, the block cart and checkout)
add_filter( 'woocommerce_store_api_product_quantity_maximum', function ( $max, $product ) {
	$cap = fika_cap_qty( $product );
	return $cap ? min( (int) $max, $cap ) : $max;
}, 20, 2 );
// classic add to cart / cart update
add_filter( 'woocommerce_add_to_cart_validation', function ( $ok, $product_id, $qty ) {
	$product = wc_get_product( $product_id );
	$cap     = fika_cap_qty( $product );
	if ( $ok && $cap && WC()->cart ) {
		$in = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === (int) $product_id ) {
				$in += (int) $item['quantity'];
			}
		}
		if ( $in + (int) $qty > $cap ) {
			wc_add_notice( sprintf( 'You can order up to %s of %s per order.', fika_cap_text( $product ), $product->get_name() ), 'error' );
			return false;
		}
	}
	return $ok;
}, 20, 3 );
add_filter( 'woocommerce_update_cart_validation', function ( $ok, $key, $values, $qty ) {
	$cap = fika_cap_qty( $values['data'] );
	if ( $ok && $cap && (int) $qty > $cap ) {
		wc_add_notice( sprintf( 'You can order up to %s of %s per order.', fika_cap_text( $values['data'] ), $values['data']->get_name() ), 'error' );
		return false;
	}
	return $ok;
}, 20, 4 );
// final check before an order can be placed
add_action( 'woocommerce_check_cart_items', function () {
	if ( ! WC()->cart ) {
		return;
	}
	foreach ( WC()->cart->get_cart() as $item ) {
		$cap = fika_cap_qty( $item['data'] );
		if ( $cap && (int) $item['quantity'] > $cap ) {
			wc_add_notice( sprintf( 'You can order up to %s of %s per order. Please lower it in your bag.', fika_cap_text( $item['data'] ), $item['data']->get_name() ), 'error' );
		}
	}
} );

// ---------- Home page: label, stop at the cap, trim the bag ----------
add_action( 'wp_footer', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$caps = array();
	foreach ( get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'fika_max_g' ) ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$p = wc_get_product( $pid );
		if ( $p && fika_cap_qty( $p ) ) {
			$caps[ $pid ] = array( 'g' => fika_cap_qty( $p ) * fika_cap_step( $p ), 't' => fika_cap_text( $p ) );
		}
	}
	if ( ! $caps ) {
		return;
	}
	?>
<style>
.mx-card .fk-cap { margin: -2px 0 8px; font: 600 12.5px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; color: #6b7894; letter-spacing: .01em; }
.fk-captip { position: fixed; z-index: 100000; padding: 7px 12px; border-radius: 999px; background: #1b2a4a; color: #fff; font: 600 13px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; white-space: nowrap; pointer-events: none;
  transform: translate(-50%, calc(-100% - 22px)); opacity: 0; transition: opacity .18s ease, transform .18s ease; }
.fk-captip.on { opacity: 1; transform: translate(-50%, calc(-100% - 34px)); }
.fk-capped { animation: fkNudge .35s ease; }
@keyframes fkNudge { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-3px); } 75% { transform: translateX(3px); } }
</style>
<script>
(function () {
	var CAPS = <?php echo wp_json_encode( $caps ); ?>, KEY = 'fika_bag_v1';
	function pid(id) { var m = /^(r?)w(\d+)$/.exec(id || ''); return m ? m[2] : null; }
	function read() { try { return JSON.parse(localStorage.getItem(KEY) || 'null') || { bag: {}, meta: {} }; } catch (e) { return { bag: {}, meta: {} }; } }
	// trim a bag that holds more than a cap (e.g. filled before the cap was set)
	(function trim() {
		var st = read(), changed = false;
		Object.keys(st.bag || {}).forEach(function (id) { var c = CAPS[pid(id)]; if (c ? st.bag[id] > c.g : false) { st.bag[id] = c.g; changed = true; } });
		if (changed) { try { localStorage.setItem(KEY, JSON.stringify(st)); window.dispatchEvent(new CustomEvent('fikabag', { detail: 'trim' })); } catch (e) {} }
	})();
	var tip = null, tipT = 0;
	function say(btn, text) {
		if (!tip) { tip = document.createElement('div'); tip.className = 'fk-captip'; tip.setAttribute('role', 'status'); document.body.appendChild(tip); }
		var r = btn.getBoundingClientRect();
		tip.textContent = text;
		tip.style.left = (r.left + r.width / 2) + 'px';
		tip.style.top = r.top + 'px';
		tip.classList.add('on');
		btn.classList.remove('fk-capped'); void btn.offsetWidth; btn.classList.add('fk-capped');
		clearTimeout(tipT); tipT = setTimeout(function () { tip.classList.remove('on'); }, 1800);
	}
	// stop + at the cap (window capture runs before the shop's own handlers and the flying-candy effect)
	window.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.mx-card button[data-a="+"], .mx-qb[data-q="+"]') : null;
		if (!b) return;
		var id = b.getAttribute('data-id') || (b.closest('.mx-card') ? b.closest('.mx-card').getAttribute('data-id') : null);
		var c = CAPS[pid(id)];
		if (!c) return;
		var cur = +(read().bag || {})[id] || 0, step = /^r/.test(id) ? 500 : 100;
		if (cur + step > c.g) {
			e.preventDefault(); e.stopImmediatePropagation();
			say(b, 'Max ' + c.t + ' per order');
		}
	}, true);
	// "Max 500 g per order" under the price on capped cards (cards are re-drawn often, so keep it in place)
	function label() {
		document.querySelectorAll('.mx-card[data-id]').forEach(function (card) {
			var c = CAPS[pid(card.getAttribute('data-id'))];
			if (!c || card.querySelector('.fk-cap')) return;
			var price = card.querySelector('.mx-price');
			if (!price) return;
			var d = document.createElement('div');
			d.className = 'fk-cap';
			d.textContent = 'Max ' + c.t + ' per order';
			price.parentNode.insertBefore(d, price.nextSibling);
		});
	}
	label();
	var mo = new MutationObserver(label);
	document.querySelectorAll('#shop, #rmPage').forEach(function (sec) { mo.observe(sec, { childList: true, subtree: true }); });
})();
</script>
	<?php
}, 40 );
