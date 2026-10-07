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

// ---------- Tell the page header who is logged in ----------
add_action( 'wp_footer', function () {
	$u    = wp_get_current_user();
	$data = array(
		'in'   => is_user_logged_in(),
		'name' => is_user_logged_in() ? ( $u->first_name ? $u->first_name : $u->display_name ) : '',
		'url'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '/my-account/',
	);
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

// ---------- Home page header: an account icon next to the bag ----------
add_action( 'wp_footer', function () {
	if ( ! is_front_page() ) {
		return;
	}
	?>
<style>
.fika-header .fika-cart { grid-column: 3; grid-row: 1; }
.fika-acct { grid-column: 3; grid-row: 1; justify-self: end; align-self: center; position: relative; display: flex; align-items: center; margin-right: 62px; padding: 8px; color: var(--fika-blue, #004aad); text-decoration: none; }
.fika-acct svg { width: 28px; height: 28px; transition: transform .3s cubic-bezier(.3, 1.6, .5, 1); }
.fika-acct:hover svg { transform: translateY(-2px) scale(1.08); }
.fika-acct .fa-tip { position: absolute; top: 100%; left: 50%; transform: translate(-50%, 4px); white-space: nowrap; padding: 5px 11px; border-radius: 999px; background: #004aad; color: #fff;
  font: 600 12.5px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s; }
.fika-acct:hover .fa-tip, .fika-acct:focus-visible .fa-tip { opacity: 1; transform: translate(-50%, 8px); }
.fika-acct.in::after { content: ''; position: absolute; right: 6px; bottom: 7px; width: 9px; height: 9px; border-radius: 50%; background: #2fb36b; border: 2px solid #fdeaf2; }
@media (max-width: 700px) {
  .fika-acct { margin-right: 74px; padding: 6px; }
  .fika-acct svg { width: 24px; height: 24px; }
  .fika-acct .fa-tip { display: none; }
}
</style>
<script>
(function () {
	var h = document.querySelector('.fika-header'), cart = h ? h.querySelector('.fika-cart') : null;
	if (!cart || h.querySelector('.fika-acct')) return;
	var u = window.FIKA_USER || { in: false, name: '', url: '/my-account/' };
	var a = document.createElement('a');
	a.className = 'fika-acct' + (u.in ? ' in' : '');
	a.href = u.url;
	var tip = u.in ? ('Hi ' + (u.name || 'there') + '! Your account') : 'Log in or sign up';
	a.setAttribute('aria-label', tip);
	a.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path></svg><span class="fa-tip"></span>';
	a.querySelector('.fa-tip').textContent = tip;
	h.insertBefore(a, cart);
})();
</script>
	<?php
}, 30 );
