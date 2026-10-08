<?php
/**
 * Fika: customer accounts — the groundwork for loyalty offers (e.g. "the 8th kg is free").
 * - Sign-up form: first name and phone (both required; the phone is the one used for delivery) on top of email + password.
 * - New accounts confirm their email first: signing up sends a "Confirm your email" link (instead of the welcome
 *   email) and the account can log in once it is tapped. Then earlier guest orders with that email (including orders
 *   brought over from the old store) join the account and its rewards. A password reset also confirms.
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
	// Earlier guest orders with the same email join the account once the customer confirms the email
	// (see "confirmed email" below), so nobody can see someone else's orders by signing up with their email.
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

// ---------- WordPress Users list: phone, sign-up date, Fika orders + kg delivered ----------
add_filter( 'manage_users_columns', function ( $cols ) {
	$cols['fika_phone']  = 'Phone';
	$cols['fika_joined'] = 'Signed up';
	$cols['fika_orders'] = 'Fika orders';
	$cols['fika_kg']     = 'Kg delivered';
	return $cols;
} );
add_filter( 'manage_users_sortable_columns', function ( $cols ) {
	$cols['fika_joined'] = 'registered';
	return $cols;
} );
add_filter( 'manage_users_custom_column', function ( $out, $col, $user_id ) {
	if ( 'fika_orders' === $col || 'fika_kg' === $col ) {
		$t = fika_customer_totals( $user_id );
		return 'fika_orders' === $col ? (string) (int) $t['orders'] : esc_html( fika_kg_text( $t['grams'] ) );
	}
	if ( 'fika_phone' === $col ) {
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		return $phone ? esc_html( $phone ) : '&mdash;';
	}
	if ( 'fika_joined' === $col ) {
		$u = get_userdata( $user_id );
		return $u ? esc_html( wp_date( 'j M Y, H:i', strtotime( $u->user_registered . ' UTC' ) ) ) : '';
	}
	return $out;
}, 10, 3 );

// ---------- Log out: straight back to the home page, signed out ----------
add_filter( 'woocommerce_logout_default_redirect_url', function () {
	return home_url( '/' );
} );
add_filter( 'logout_redirect', function ( $to, $requested, $user ) {
	return ( $user instanceof WP_User && ! user_can( $user, 'edit_posts' ) ) ? home_url( '/' ) : $to;
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
			array( 'Log out', html_entity_decode( wc_logout_url( home_url( '/' ) ) ) ), // a plain URL, not HTML (&amp; broke the security check)
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
	return home_url( '/mix-your-own/' );
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

// ---------- Home, shop and About pages header: a waving account icon with a drop-down menu ----------
add_action( 'wp_footer', function () {
	if ( ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us', 'privacy-policy' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) ) {
		return;
	}
	?>
<style>
/* bag on the left, person on the far right; the bag's kg badge sits on its left so it never covers the person */
.fika-header .fika-cart { grid-column: 3; grid-row: 1; margin-right: 58px; }
.fika-header .fika-cart .fk-count { right: auto; left: calc(-34px - (var(--fk-scale, 1) - 1) * 12px); }
.fika-acct { grid-column: 3; grid-row: 1; justify-self: end; align-self: center; position: relative; margin-right: -8px; z-index: 30; }
.fika-acct .fa-btn { position: relative; display: flex; align-items: center; padding: 8px; color: var(--fika-blue, #004aad); text-decoration: none; background: none; border: 0; cursor: pointer; }
.fika-acct svg { width: 30px; height: 30px; overflow: visible; }
.fika-acct .fa-arm { transform-origin: 17px 15.5px; transform: rotate(120deg) scale(.6); opacity: 0; transition: transform .25s ease, opacity .2s ease; }
.fika-acct .fa-head { transform-origin: 12px 8px; transition: transform .25s ease; }
.fika-acct:hover .fa-arm, .fika-acct.open .fa-arm { opacity: 1; animation: faWave .9s ease-in-out infinite; }
.fika-acct:hover .fa-head, .fika-acct.open .fa-head { transform: rotate(-8deg); }
@keyframes faWave { 0%, 100% { transform: rotate(-4deg); } 25% { transform: rotate(26deg); } 50% { transform: rotate(-4deg); } 75% { transform: rotate(26deg); } }
.fika-acct.in .fa-btn::after { content: ''; position: absolute; right: 5px; bottom: 7px; width: 9px; height: 9px; border-radius: 50%; background: #2fb36b; border: 2px solid #fdeaf2; }
/* the drop-down: a white card under the icon (padding-top bridges the gap so it stays open while moving the mouse down) */
.fika-acct .fa-menu { position: absolute; top: 100%; right: -6px; padding-top: 10px; opacity: 0; visibility: hidden; transform: translateY(-6px); transition: opacity .2s ease, transform .2s ease, visibility 0s .2s; }
.fika-acct:hover .fa-menu, .fika-acct:focus-within .fa-menu, .fika-acct.open .fa-menu { opacity: 1; visibility: visible; transform: none; transition: opacity .2s ease, transform .2s ease, visibility 0s; }
.fika-acct.shut .fa-menu { opacity: 0 !important; visibility: hidden !important; }
.fika-acct .fa-card { position: relative; min-width: 210px; padding: 10px; border-radius: 18px; background: #fff; box-shadow: 0 14px 40px rgba(0, 74, 173, .18); }
.fika-acct .fa-card::before { content: ''; position: absolute; top: -6px; right: 17px; width: 14px; height: 14px; background: #fff; border-radius: 3px; transform: rotate(45deg); }
.fika-acct .fa-hi { position: relative; padding: 8px 14px 10px; margin-bottom: 4px; border-bottom: 1px solid #f3dbe6; font: 600 15px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; }
.fika-acct .fa-hi small { display: block; margin-top: 3px; font-weight: 400; font-size: 13px; color: #6b7894; }
.fika-acct .fa-card a { position: relative; display: block; padding: 10px 14px; border-radius: 12px; font-family: 'Bebas Neue', Impact, sans-serif; font-size: 21px; letter-spacing: .03em; line-height: 1.1; color: #004aad; text-decoration: none; white-space: nowrap; }
.fika-acct .fa-card a:hover, .fika-acct .fa-card a:focus-visible { background: #fdeaf2; outline: 0; }
.fika-acct .fa-card a.fa-out { color: #6b7894; }
@media (max-width: 700px) {
  .fika-header .fika-cart { margin-right: 42px; }
  .fika-acct { margin-right: -6px; }
  .fika-acct .fa-btn { padding: 6px; }
  .fika-acct svg { width: 25px; height: 25px; }
  .fika-acct .fa-menu { right: -4px; }
  .fika-acct .fa-card::before { right: 14px; }
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

// ---------- confirmed email: a new account works once the customer taps the link we email them ----------
// Signing up sends one "Confirm your email" email (instead of WooCommerce's welcome email). Until the link is tapped
// the account cannot log in. The link logs the customer in, and earlier guest orders with the same email (including
// orders imported from the old store) join the account and its rewards at once. A password reset also confirms.
if ( ! function_exists( 'fika_past_orders' ) ) {
	// guest orders (no account) placed with this email
	function fika_past_orders( $email ) {
		if ( ! is_email( $email ) ) {
			return array();
		}
		return wc_get_orders( array(
			'customer_id'   => 0,
			'billing_email' => strtolower( $email ),
			'status'        => array_keys( wc_get_order_statuses() ), // not unfinished checkout drafts
			'limit'         => -1,
			'type'          => 'shop_order',
			'return'        => 'ids',
		) );
	}
	// customers must confirm their current email; staff accounts are never held back
	function fika_email_confirmed( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return false;
		}
		if ( ! in_array( 'customer', (array) $u->roles, true ) ) {
			return true;
		}
		return strtolower( (string) get_user_meta( $user_id, 'fika_email_ok', true ) ) === strtolower( $u->user_email );
	}
	// mark the email confirmed and move the guest orders into the account; returns how many joined
	function fika_link_past_orders( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return 0;
		}
		update_user_meta( $user_id, 'fika_email_ok', strtolower( $u->user_email ) );
		delete_user_meta( $user_id, 'fika_link_token' );
		$n = 0;
		foreach ( fika_past_orders( $u->user_email ) as $oid ) {
			$o = wc_get_order( $oid );
			if ( $o && ! $o->get_customer_id() ) {
				$o->set_customer_id( $user_id );
				$o->add_order_note( 'Joined the customer\'s account when they confirmed their email.' );
				$o->save();
				$n++;
			}
		}
		fika_customer_totals( $user_id, true );
		if ( $n && function_exists( 'fika_swim_sync' ) ) {
			fika_swim_sync( $user_id );
		}
		return $n;
	}
	// email the confirmation link (a new link replaces the old one)
	function fika_send_confirm( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u || ! function_exists( 'WC' ) ) {
			return false;
		}
		$tok = wp_generate_password( 32, false );
		update_user_meta( $user_id, 'fika_link_token', array( 'h' => hash_hmac( 'sha256', $tok, wp_salt( 'auth' ) ), 'exp' => time() + 7 * DAY_IN_SECONDS, 'email' => strtolower( $u->user_email ) ) );
		update_user_meta( $user_id, 'fika_link_sent', time() );
		$url    = add_query_arg( 'fika-confirm', $user_id . '.' . $tok, wc_get_page_permalink( 'myaccount' ) );
		$name   = $u->first_name ? $u->first_name : 'there';
		$mailer = WC()->mailer();
		$body   = '<p>Hi ' . esc_html( $name ) . ',</p>'
			. '<p>Thanks for joining Fika! Tap the button to confirm this is your email, and your account is ready.</p>'
			// class "button": the email look (fika-emails.php) colours every other link blue, which hid the white text
			. '<p style="text-align:center;margin:28px 0;"><a class="button" href="' . esc_url( $url ) . '" style="display:inline-block;background:#004aad;color:#ffffff;text-decoration:none;font-weight:600;padding:14px 28px;border-radius:999px;">Verify and confirm my email</a></p>'
			. '<p>If you&rsquo;ve ordered from Fika before with this email, those orders join your account too, and every kilo counts toward your sweet rewards.</p>'
			. '<p>The link works for 7 days. Didn&rsquo;t sign up? You can safely ignore this email.</p>'
			. '<p style="font-size:13px;color:#6c7b9c;">Button not working? Copy this link into your browser:<br><a href="' . esc_url( $url ) . '" style="word-break:break-all;">' . esc_html( $url ) . '</a></p>';
		return $mailer->send( $u->user_email, 'Confirm your email for Fika', $mailer->wrap_message( 'Welcome to Fika!', $body ) );
	}
	// a signed "send it again" link for the login error (only shown after the right password)
	function fika_resend_sig( $user_id ) {
		return substr( hash_hmac( 'sha256', 'resend|' . $user_id . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) ), 0, 20 );
	}
}

// the confirmation email replaces WooCommerce's welcome email
add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false', 99 );
add_action( 'woocommerce_created_customer', function ( $customer_id ) {
	if ( ! fika_email_confirmed( $customer_id ) ) {
		fika_send_confirm( $customer_id );
	}
}, 30 );

// signing up on My account: no automatic log-in, back to the page with "check your email"
add_filter( 'woocommerce_registration_auth_new_customer', function ( $auth ) {
	return isset( $_POST['register'] ) ? false : $auth; // phpcs:ignore WordPress.Security.NonceVerification
} );
// WooCommerce's "account created, details sent" message becomes "check your email"
add_filter( 'woocommerce_add_success', function ( $msg ) {
	if ( isset( $_POST['register'] ) && false !== stripos( wp_strip_all_tags( $msg ), 'account was created' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<span><b>Almost there! Check your email.</b> We&rsquo;ve sent you a link: tap it to confirm your email, and your account is ready. It can take a minute, and may land in spam.</span>';
	}
	return $msg;
} );

// no log-in before the email is confirmed
add_filter( 'authenticate', function ( $user ) {
	if ( $user instanceof WP_User && ! fika_email_confirmed( $user->ID ) ) {
		$again = add_query_arg( 'fika-resend', $user->ID . '.' . fika_resend_sig( $user->ID ), wc_get_page_permalink( 'myaccount' ) );
		return new WP_Error( 'fika_unconfirmed', sprintf( '<span>Please confirm your email first: tap the link we sent to %s. Can&rsquo;t find it? Check spam, or <a href="%s">send it again</a>.</span>', esc_html( $user->user_email ), esc_url( $again ) ) );
	}
	return $user;
}, 99 );

// a password reset also proves the email
add_action( 'after_password_reset', function ( $user ) {
	if ( $user && ! fika_email_confirmed( $user->ID ) ) {
		fika_link_past_orders( $user->ID );
	}
} );

// a later order placed while logged out with a confirmed account's email joins that account
add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
	if ( $order->get_customer_id() ) {
		return;
	}
	$u = get_user_by( 'email', $order->get_billing_email() );
	if ( $u && in_array( 'customer', (array) $u->roles, true ) && fika_email_confirmed( $u->ID ) ) {
		$order->set_customer_id( $u->ID );
		$order->save();
		fika_customer_totals( $u->ID, true );
	}
} );

// the link from the email, "send it again", and the button for accounts made at checkout
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}
	$acct = wc_get_page_permalink( 'myaccount' );
	if ( isset( $_GET['fika-confirm'] ) ) {
		$raw = sanitize_text_field( wp_unslash( $_GET['fika-confirm'] ) );
		list( $uid, $tok ) = array_pad( explode( '.', $raw, 2 ), 2, '' );
		$uid = absint( $uid );
		$u   = $uid ? get_userdata( $uid ) : false;
		$t   = $u ? get_user_meta( $uid, 'fika_link_token', true ) : null;
		$ok  = $u && $tok && is_array( $t ) && $t['exp'] > time() && hash_equals( $t['h'], hash_hmac( 'sha256', $tok, wp_salt( 'auth' ) ) )
			&& strtolower( $t['email'] ) === strtolower( $u->user_email ) && ( ! is_user_logged_in() || get_current_user_id() === $uid );
		if ( ! $ok ) {
			// an old link opened after the email was already confirmed: nothing went wrong
			$done = $u && fika_email_confirmed( $uid ) && ( ! is_user_logged_in() || get_current_user_id() === $uid );
			wp_safe_redirect( add_query_arg( 'fika-linked', $done ? 'done' : 'expired', $acct ) );
			exit;
		}
		$n = fika_link_past_orders( $uid );
		if ( ! is_user_logged_in() ) {
			wp_set_current_user( $uid );
			wp_set_auth_cookie( $uid, true );
			do_action( 'wp_login', $u->user_login, $u );
		}
		wp_safe_redirect( add_query_arg( 'fika-linked', $n, $acct ) );
		exit;
	}
	$uid = 0;
	if ( isset( $_GET['fika-resend'] ) ) {
		list( $id, $sig ) = array_pad( explode( '.', sanitize_text_field( wp_unslash( $_GET['fika-resend'] ) ), 2 ), 2, '' );
		if ( absint( $id ) && hash_equals( fika_resend_sig( absint( $id ) ), $sig ) ) {
			$uid = absint( $id );
		}
	} elseif ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['fika_send_link'] ) && is_user_logged_in()
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_fika_link'] ?? '' ) ), 'fika_link_' . get_current_user_id() ) ) {
		$uid = get_current_user_id();
	}
	if ( isset( $_GET['fika-resend'] ) || isset( $_POST['fika_send_link'] ) ) {
		if ( $uid && ! fika_email_confirmed( $uid ) && time() - (int) get_user_meta( $uid, 'fika_link_sent', true ) >= 2 * MINUTE_IN_SECONDS ) {
			fika_send_confirm( $uid );
		}
		wp_safe_redirect( add_query_arg( 'fika-check', $uid ? '1' : '0', $acct ) );
		exit;
	}
}, 5 );

// messages on the log-in page
add_action( 'woocommerce_before_customer_login_form', function () {
	$msg = '';
	if ( isset( $_GET['fika-check'] ) && '1' === $_GET['fika-check'] ) {
		$msg = '<b>Almost there! Check your email</b><p>We&rsquo;ve sent you a link. Tap it to confirm your email, and your account is ready. It can take a minute, and may land in spam.</p>';
	} elseif ( isset( $_GET['fika-linked'] ) && 'expired' === $_GET['fika-linked'] ) {
		$msg = '<b>That link has expired</b><p>Log in below and we&rsquo;ll send you a fresh one.</p>';
	} elseif ( isset( $_GET['fika-linked'] ) && 'done' === $_GET['fika-linked'] ) {
		$msg = '<b>Your email is already confirmed</b><p>Log in below.</p>';
	}
	if ( $msg ) {
		echo '<div class="fika-past">' . $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$GLOBALS['fika_past_css'] = true;
	}
} );

// My account: "confirmed" message, or the reminder for an account made at checkout that is not confirmed yet
add_action( 'woocommerce_account_dashboard', function () {
	$uid = get_current_user_id();
	$u   = wp_get_current_user();
	$st  = isset( $_GET['fika-linked'] ) ? sanitize_key( wp_unslash( $_GET['fika-linked'] ) ) : '';
	if ( ctype_digit( $st ) ) {
		$n = (int) $st;
		echo '<div class="fika-past is-done"><b>Your account is confirmed</b><p>'
			. ( $n ? esc_html( sprintf( 'Welcome! %d earlier %s joined your account and count toward your rewards.', $n, 1 === $n ? 'order' : 'orders' ) ) : 'Welcome to Fika! Every kilo you order counts toward your sweet rewards.' )
			. '</p></div>';
		$GLOBALS['fika_past_css'] = true;
		return;
	}
	if ( fika_email_confirmed( $uid ) ) {
		return;
	}
	echo '<div class="fika-past"><b>Please confirm your email</b>';
	if ( isset( $_GET['fika-check'] ) ) {
		echo '<p>We&rsquo;ve sent a new link to <strong>' . esc_html( $u->user_email ) . '</strong>. It can take a minute, and may land in spam.</p>';
	} else {
		echo '<p>We sent a link to <strong>' . esc_html( $u->user_email ) . '</strong>. Tap it to confirm your account' . ( fika_past_orders( $u->user_email ) ? ' and bring in your earlier orders, so every kilo counts toward your rewards' : '' ) . '. You&rsquo;ll need it to log in next time.</p>';
		echo '<form method="post" action="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '"><input type="hidden" name="_fika_link" value="' . esc_attr( wp_create_nonce( 'fika_link_' . $uid ) ) . '"><button type="submit" name="fika_send_link" value="1" class="fika-past-btn">Send the link again</button></form>';
	}
	echo '</div>';
	$GLOBALS['fika_past_css'] = true;
}, 3 );
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['fika_past_css'] ) ) {
		return;
	}
	echo '<style>.fika-past{background:#fdeaf2;border-radius:18px;padding:18px 22px;margin:0 0 20px;}.fika-past b{display:block;font-family:"Fanwood Text",Georgia,serif;font-variant:small-caps;font-weight:400;font-size:22px;color:#004aad;}.fika-past p{margin:6px 0 0;}.fika-past form{margin:12px 0 0;}.fika-past-btn{background:#004aad;color:#fff;border:0;border-radius:999px;padding:11px 22px;font:600 15px Outfit,sans-serif;cursor:pointer;}.fika-past-btn:hover{background:#003a8a;}.fika-past.is-done{background:#e6f4ea;}</style>';
}, 30 );
