<?php
/**
 * Fika: customer accounts — the groundwork for loyalty offers (e.g. "the 8th kg is free").
 * - Sign-up form: first name and phone (both required; the phone is the one used for delivery) on top of email + password.
 * - New accounts are linked to earlier guest orders placed with the same email.
 * - Each customer keeps a running total of delivered orders and grams (orders marked Completed),
 *   stored in user meta "fika_totals" and refreshed whenever one of their orders changes status.
 *   Candies count 100 g per unit, Ready Mix 500 g per bag.
 * - My account dashboard shows the totals; the WordPress Users list gets "Fika orders" / "Kg delivered" columns.
 * - window.FIKA_USER tells the page header whether the visitor is logged in.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-accounts.php
 */

if ( ! function_exists( 'fika_order_grams' ) ) {
	// Grams in one order: 100 g per candy unit, 500 g per Ready Mix bag
	function fika_order_grams( $order ) {
		$grams = 0;
		foreach ( $order->get_items() as $item ) {
			$qty  = (int) $item->get_quantity() - absint( $order->get_qty_refunded_for_item( $item->get_id() ) );
			$pid  = $item->get_product_id();
			$step = has_term( 'ready-mix', 'product_cat', $pid ) ? 500 : 100;
			$grams += max( 0, $qty ) * $step;
		}
		return $grams;
	}
}

if ( ! function_exists( 'fika_customer_totals' ) ) {
	// Delivered orders + grams for a customer (recomputed from their Completed orders)
	function fika_customer_totals( $user_id, $refresh = false ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return array( 'orders' => 0, 'grams' => 0 );
		}
		$cached = get_user_meta( $user_id, 'fika_totals', true );
		if ( ! $refresh && is_array( $cached ) && isset( $cached['grams'] ) ) {
			return $cached;
		}
		$orders = wc_get_orders( array(
			'customer_id' => $user_id,
			'status'      => array( 'wc-completed' ),
			'limit'       => -1,
			'type'        => 'shop_order',
		) );
		$grams = 0;
		foreach ( $orders as $o ) {
			$grams += fika_order_grams( $o );
		}
		$totals = array( 'orders' => count( $orders ), 'grams' => $grams, 'updated' => time() );
		update_user_meta( $user_id, 'fika_totals', $totals );
		return $totals;
	}
}

if ( ! function_exists( 'fika_kg_text' ) ) {
	function fika_kg_text( $grams ) {
		return rtrim( rtrim( number_format( $grams / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg';
	}
}

// Keep the totals up to date whenever an order changes status
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( $order && $order->get_customer_id() ) {
		fika_customer_totals( $order->get_customer_id(), true );
	}
}, 20, 4 );
add_action( 'woocommerce_order_refunded', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( $order && $order->get_customer_id() ) {
		fika_customer_totals( $order->get_customer_id(), true );
	}
}, 20 );

// ---------- Sign-up form: first name + phone ----------
add_action( 'woocommerce_register_form_start', function () {
	$first = isset( $_POST['fika_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fika_first_name'] ) ) : '';
	$phone = isset( $_POST['fika_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['fika_phone'] ) ) : '';
	?>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="fika_first_name">First name&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="fika_first_name" id="fika_first_name" autocomplete="given-name" value="<?php echo esc_attr( $first ); ?>" required>
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="fika_phone">Phone number&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="fika_phone" id="fika_phone" autocomplete="tel" value="<?php echo esc_attr( $phone ); ?>" required>
	</p>
	<?php
} );

add_filter( 'woocommerce_registration_errors', function ( $errors ) {
	if ( isset( $_POST['fika_first_name'] ) && '' === trim( sanitize_text_field( wp_unslash( $_POST['fika_first_name'] ) ) ) ) {
		$errors->add( 'fika_first_name', 'Please tell us your first name.' );
	}
	if ( isset( $_POST['fika_phone'] ) && strlen( preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['fika_phone'] ) ) ) < 7 ) {
		$errors->add( 'fika_phone', 'Please enter a valid phone number, so we can reach you on delivery day.' );
	}
	return $errors;
} );

add_action( 'woocommerce_created_customer', function ( $customer_id ) {
	if ( ! empty( $_POST['fika_first_name'] ) ) {
		$first = sanitize_text_field( wp_unslash( $_POST['fika_first_name'] ) );
		update_user_meta( $customer_id, 'first_name', $first );
		update_user_meta( $customer_id, 'billing_first_name', $first );
		update_user_meta( $customer_id, 'shipping_first_name', $first );
		wp_update_user( array( 'ID' => $customer_id, 'display_name' => $first ) );
	}
	if ( ! empty( $_POST['fika_phone'] ) ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['fika_phone'] ) );
		update_user_meta( $customer_id, 'billing_phone', $phone );
		update_user_meta( $customer_id, 'shipping_phone', $phone );
		// Also the checkout's contact "Phone number" field (fika/phone), so checkout and Account details are pre-filled
		update_user_meta( $customer_id, '_wc_other/fika/phone', $phone );
	}
	// Earlier guest orders with the same email now belong to this account (and count towards the totals)
	if ( function_exists( 'wc_update_new_customer_past_orders' ) ) {
		wc_update_new_customer_past_orders( $customer_id );
	}
	fika_customer_totals( $customer_id, true );
}, 20 );

// ---------- My account dashboard: the customer's totals ----------
add_action( 'woocommerce_account_dashboard', function () {
	$t = fika_customer_totals( get_current_user_id() );
	?>
	<div class="fika-acct-stats">
		<div class="fika-acct-stat"><b><?php echo esc_html( fika_kg_text( $t['grams'] ) ); ?></b><span>of sweets delivered</span></div>
		<div class="fika-acct-stat"><b><?php echo (int) $t['orders']; ?></b><span><?php echo 1 === (int) $t['orders'] ? 'order delivered' : 'orders delivered'; ?></span></div>
		<p class="fika-acct-note">Every kilo you order is counted here &mdash; Fika treats for loyal customers are on their way.</p>
	</div>
	<?php
}, 5 );

// ---------- WordPress Users list: Fika orders + kg delivered ----------
add_filter( 'manage_users_columns', function ( $cols ) {
	$cols['fika_orders'] = 'Fika orders';
	$cols['fika_kg']     = 'Kg delivered';
	return $cols;
} );
add_filter( 'manage_users_custom_column', function ( $out, $col, $user_id ) {
	if ( 'fika_orders' === $col || 'fika_kg' === $col ) {
		$t = fika_customer_totals( $user_id );
		return 'fika_orders' === $col ? (string) (int) $t['orders'] : esc_html( fika_kg_text( $t['grams'] ) );
	}
	return $out;
}, 10, 3 );

// ---------- Tell the page header who is logged in (and where the account menu links go) ----------
add_action( 'wp_footer', function () {
	$in   = is_user_logged_in();
	$u    = wp_get_current_user();
	$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	$data = array(
		'in'   => $in,
		'name' => $in ? ( $u->first_name ? $u->first_name : $u->display_name ) : '',
		'url'  => $base,
	);
	if ( $in && function_exists( 'wc_get_account_endpoint_url' ) ) {
		$t            = fika_customer_totals( $u->ID );
		$data['kg']   = fika_kg_text( $t['grams'] );
		$data['menu'] = array(
			array( 'Orders', wc_get_account_endpoint_url( 'orders' ) ),
			array( 'Delivery address', wc_get_account_endpoint_url( 'edit-address' ) ),
			array( 'Account details', wc_get_account_endpoint_url( 'edit-account' ) ),
			array( 'Log out', wc_logout_url() ),
		);
	} else {
		$data['menu'] = array(
			array( 'Log in', $base . '#login' ),
			array( 'Sign up', $base . '#register' ),
		);
	}
	echo '<script>window.FIKA_USER = ' . wp_json_encode( $data ) . ';</script>';
}, 1 );

// ---------- My account page: friendlier wording, no Downloads tab, last name optional ----------
add_filter( 'woocommerce_save_account_details_required_fields', function ( $fields ) {
	unset( $fields['account_last_name'] );
	return $fields;
} );
// "Browse products" / "Return to shop" buttons go to the shop on the home page
add_filter( 'woocommerce_return_to_shop_redirect', function () {
	return home_url( '/#shop' );
} );
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	unset( $items['downloads'] );
	if ( isset( $items['edit-address'] ) ) {
		$items['edit-address'] = 'Delivery address';
	}
	return $items;
} );
add_filter( 'gettext', function ( $text, $orig, $domain ) {
	if ( 'woocommerce' !== $domain || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return $text;
	}
	if ( 'Login' === $orig ) {
		return 'Log in';
	}
	if ( 'Register' === $orig ) {
		return 'Create an account';
	}
	if ( 'Shipping address' === $orig ) {
		return 'Delivery address';
	}
	if ( 'The following addresses will be used on the checkout page by default.' === $orig ) {
		return 'This address is filled in for you at checkout.';
	}
	if ( false !== strpos( $text, 'shipping and billing addresses' ) ) {
		return str_replace( 'shipping and billing addresses', 'delivery address', $text );
	}
	return $text;
}, 20, 3 );

// ---------- Home page header: a waving account icon with a drop-down menu ----------
add_action( 'wp_footer', function () {
	if ( ! is_front_page() ) {
		return;
	}
	?>
<style>
.fika-header .fika-cart { grid-column: 3; grid-row: 1; }
.fika-acct { grid-column: 3; grid-row: 1; justify-self: end; align-self: center; position: relative; margin-right: 62px; z-index: 30; }
.fika-acct .fa-btn { position: relative; display: flex; align-items: center; padding: 8px; color: var(--fika-blue, #004aad); text-decoration: none; background: none; border: 0; cursor: pointer; }
.fika-acct svg { width: 30px; height: 30px; overflow: visible; }
.fika-acct .fa-arm { transform-origin: 17px 15.5px; transform: rotate(120deg) scale(.6); opacity: 0; transition: transform .25s ease, opacity .2s ease; }
.fika-acct .fa-head { transform-origin: 12px 8px; transition: transform .25s ease; }
.fika-acct:hover .fa-arm, .fika-acct.open .fa-arm { opacity: 1; animation: faWave .9s ease-in-out infinite; }
.fika-acct:hover .fa-head, .fika-acct.open .fa-head { transform: rotate(-8deg); }
@keyframes faWave { 0%, 100% { transform: rotate(-4deg); } 25% { transform: rotate(26deg); } 50% { transform: rotate(-4deg); } 75% { transform: rotate(26deg); } }
.fika-acct.in .fa-btn::after { content: ''; position: absolute; right: 5px; bottom: 7px; width: 9px; height: 9px; border-radius: 50%; background: #2fb36b; border: 2px solid #fdeaf2; }
/* the drop-down: a white card under the icon (padding-top bridges the gap so it stays open while moving the mouse down) */
.fika-acct .fa-menu { position: absolute; top: 100%; right: -14px; padding-top: 10px; opacity: 0; visibility: hidden; transform: translateY(-6px); transition: opacity .2s ease, transform .2s ease, visibility 0s .2s; }
.fika-acct:hover .fa-menu, .fika-acct:focus-within .fa-menu, .fika-acct.open .fa-menu { opacity: 1; visibility: visible; transform: none; transition: opacity .2s ease, transform .2s ease, visibility 0s; }
.fika-acct.shut .fa-menu { opacity: 0 !important; visibility: hidden !important; }
.fika-acct .fa-card { position: relative; min-width: 210px; padding: 10px; border-radius: 18px; background: #fff; box-shadow: 0 14px 40px rgba(0, 74, 173, .18); }
.fika-acct .fa-card::before { content: ''; position: absolute; top: -6px; right: 26px; width: 14px; height: 14px; background: #fff; border-radius: 3px; transform: rotate(45deg); }
.fika-acct .fa-hi { position: relative; padding: 8px 14px 10px; margin-bottom: 4px; border-bottom: 1px solid #f3dbe6; font: 600 15px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; }
.fika-acct .fa-hi small { display: block; margin-top: 3px; font-weight: 400; font-size: 13px; color: #6b7894; }
.fika-acct .fa-card a { position: relative; display: block; padding: 10px 14px; border-radius: 12px; font-family: 'Bebas Neue', Impact, sans-serif; font-size: 21px; letter-spacing: .03em; line-height: 1.1; color: #004aad; text-decoration: none; white-space: nowrap; }
.fika-acct .fa-card a:hover, .fika-acct .fa-card a:focus-visible { background: #fdeaf2; outline: 0; }
.fika-acct .fa-card a.fa-main { background: #004aad; color: #fff; text-align: center; margin-top: 4px; }
.fika-acct .fa-card a.fa-main:hover { background: #003a8a; }
.fika-acct .fa-card a.fa-out { color: #6b7894; }
@media (max-width: 700px) {
  .fika-acct { margin-right: 74px; }
  .fika-acct .fa-btn { padding: 6px; }
  .fika-acct svg { width: 25px; height: 25px; }
  .fika-acct .fa-menu { right: -86px; }
  .fika-acct .fa-card::before { right: 96px; }
}
@media (prefers-reduced-motion: reduce) { .fika-acct:hover .fa-arm, .fika-acct.open .fa-arm { animation: none; } }
</style>
<script>
(function () {
	var h = document.querySelector('.fika-header'), cart = h ? h.querySelector('.fika-cart') : null;
	if (!cart || h.querySelector('.fika-acct')) return;
	var u = window.FIKA_USER || { in: false, name: '', url: '/my-account/', menu: [['Log in', '/my-account/#login'], ['Sign up', '/my-account/#register']] };
	var wrap = document.createElement('div');
	wrap.className = 'fika-acct' + (u.in ? ' in' : '');
	var btn = document.createElement('a');
	btn.className = 'fa-btn';
	btn.href = u.url;
	btn.setAttribute('aria-haspopup', 'true');
	btn.setAttribute('aria-expanded', 'false');
	btn.setAttribute('aria-label', u.in ? 'Your account' : 'Log in or sign up');
	btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
		'<g class="fa-head"><circle cx="12" cy="8" r="4"></circle></g>' +
		'<path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path>' +
		'<g class="fa-arm"><path d="M17 15.5 20.2 9.6"></path><circle cx="20.9" cy="8.2" r="1.4" fill="currentColor" stroke="none"></circle></g></svg>';
	var menu = document.createElement('div');
	menu.className = 'fa-menu';
	var card = document.createElement('div');
	card.className = 'fa-card';
	card.setAttribute('role', 'menu');
	if (u.in) {
		var hi = document.createElement('div');
		hi.className = 'fa-hi';
		hi.textContent = 'Hi ' + (u.name || 'there') + '!';
		if (u.kg) { var sm = document.createElement('small'); sm.textContent = u.kg + ' of sweets delivered'; hi.appendChild(sm); }
		card.appendChild(hi);
	}
	(u.menu || []).forEach(function (m, i) {
		var a = document.createElement('a');
		a.href = m[1];
		a.textContent = m[0];
		a.setAttribute('role', 'menuitem');
		if (!u.in && i === 1) a.className = 'fa-main';
		if (u.in && m[0] === 'Log out') a.className = 'fa-out';
		card.appendChild(a);
	});
	menu.appendChild(card);
	wrap.appendChild(btn);
	wrap.appendChild(menu);
	h.insertBefore(wrap, cart);

	function setOpen(on) {
		if (on) wrap.classList.add('open'); else wrap.classList.remove('open');
		btn.setAttribute('aria-expanded', on ? 'true' : 'false');
	}
	// Phones and tablets (no hover): the first tap opens the menu, a tap elsewhere closes it
	var noHover = window.matchMedia && window.matchMedia('(hover: none)').matches;
	btn.addEventListener('click', function (e) {
		if (noHover) {
			e.preventDefault();
			setOpen(!wrap.classList.contains('open'));
		}
	});
	document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) setOpen(false); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && wrap.classList.contains('open')) { setOpen(false); btn.focus(); }
	});
	// After choosing an item with the mouse, let the menu fold away even though the pointer is still over it
	card.addEventListener('click', function () { wrap.classList.add('shut'); setOpen(false); });
	wrap.addEventListener('mouseleave', function () { wrap.classList.remove('shut'); });
})();
</script>
	<?php
}, 30 );

// ---------- My account page: "Log in" / "Sign up" links from the header jump to the right form ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || is_user_logged_in() ) {
		return;
	}
	?>
<style>
body.woocommerce-account #customer_login > div { transition: box-shadow .4s ease; }
body.woocommerce-account #customer_login > div.fika-pick { box-shadow: 0 0 0 3px #004aad, 0 6px 24px rgba(0, 74, 173, .12); }
</style>
<script>
(function () {
	function pick() {
		var reg = location.hash === '#register', log = location.hash === '#login';
		if (!reg && !log) return;
		var col = document.querySelector(reg ? '#customer_login .u-column2' : '#customer_login .u-column1');
		var field = document.getElementById(reg ? 'fika_first_name' : 'username');
		if (!col || !field) return;
		document.querySelectorAll('#customer_login > div').forEach(function (d) { d.classList.remove('fika-pick'); });
		col.classList.add('fika-pick');
		col.scrollIntoView({ behavior: 'smooth', block: 'start' });
		setTimeout(function () { field.focus({ preventScroll: true }); }, 350);
		setTimeout(function () { col.classList.remove('fika-pick'); }, 2400);
	}
	pick();
	window.addEventListener('hashchange', pick);
})();
</script>
	<?php
}, 30 );

// Accounts created at checkout get their username as display name; use the first name once it is known
add_action( 'woocommerce_before_account_navigation', function () {
	$u = wp_get_current_user();
	if ( $u->ID && $u->first_name && $u->display_name === $u->user_login ) {
		wp_update_user( array( 'ID' => $u->ID, 'display_name' => $u->first_name ) );
		$u->display_name = $u->first_name;
	}
}, 1 );
