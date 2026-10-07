<?php
/**
 * Fika: checkout tweaks for Lebanon and weight labels.
 * Installed with the Code Snippets plugin (runs everywhere).
 */

// Phone number sits in Contact information (under the email) instead of in the address.
// Orders are cash on delivery and the driver needs to call, so it is required.
add_filter( 'pre_option_woocommerce_checkout_phone_field', function () {
	return 'hidden';
} );

add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}
	woocommerce_register_additional_checkout_field( array(
		'id'                => 'fika/phone',
		'label'             => 'Phone number',
		'location'          => 'contact',
		'type'              => 'text',
		'required'          => true,
		'attributes'        => array(
			'autocomplete' => 'tel',
		),
		'sanitize_callback' => function ( $value ) {
			return trim( preg_replace( '/[^0-9+ ]/', '', (string) $value ) );
		},
		'validate_callback' => function ( $value ) {
			if ( strlen( preg_replace( '/[^0-9]/', '', (string) $value ) ) < 7 ) {
				return new WP_Error( 'fika_phone', 'Please enter a valid phone number.' );
			}
		},
	) );
} );

// Copy the contact phone onto the order's billing and delivery phone (shows in Orders and emails).
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order, $request ) {
	$fields = $request->get_param( 'additional_fields' );
	if ( is_array( $fields ) && ! empty( $fields['fika/phone'] ) ) {
		$phone = sanitize_text_field( $fields['fika/phone'] );
		$order->set_billing_phone( $phone );
		if ( method_exists( $order, 'set_shipping_phone' ) ) {
			$order->set_shipping_phone( $phone );
		}
	}
}, 10, 2 );

// No billing address: every order is cash on delivery, so the delivery address is all we need.
// Checkout hides the "Use same address for billing" box and the billing form (guests and signed-in customers alike;
// WooCommerce unticks the box for customers whose saved billing address differs), and keeps the box ticked.
// The order's billing address is a copy of the delivery address (the email and phone stay as entered).
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order ) {
	if ( ! $order->get_shipping_address_1() ) {
		return;
	}
	foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ) as $f ) {
		$order->{'set_billing_' . $f}( $order->{'get_shipping_' . $f}() );
	}
}, 20 );
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
<style>
.wc-block-checkout__use-address-for-billing, .wp-block-woocommerce-checkout-billing-address-block, .wc-block-checkout__billing-fields { display: none !important; }
</style>
<script>
(function () {
	function keep() {
		try {
			var sel = wp.data.select( 'wc/store/checkout' ), act = wp.data.dispatch( 'wc/store/checkout' );
			if ( sel.getUseShippingAsBilling() === false ) { ( act.__internalSetUseShippingAsBilling || act.setUseShippingAsBilling )( true ); }
		} catch ( e ) {}
	}
	function start() {
		if ( ! window.wp || ! wp.data ) { return setTimeout( start, 200 ); }
		keep();
		wp.data.subscribe( keep );
	}
	start();
})();
</script>
	<?php
}, 30 );

// Lebanon: no postal codes, so hide the field.
add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
	$locale['LB']['postcode'] = array(
		'required' => false,
		'hidden'   => true,
	);
	return $locale;
} );

/**
 * Weight label for a cart line: candies are sold in 100 g steps (quantity 1 = 100 g),
 * Ready Mix in 500 g bags (quantity 1 = one bag).
 */
if ( ! function_exists( 'fika_weight_label' ) ) {
function fika_weight_label( $product, $qty ) {
	if ( ! $product ) {
		return null;
	}
	$id  = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$qty = (int) $qty;
	if ( has_term( 'ready-mix', 'product_cat', $id ) ) {
		$grams = $qty * 500;
		$text  = $qty . ( 1 === $qty ? ' bag' : ' bags' ) . ' (' . fika_grams_text( $grams ) . ')';
		return array( 'key' => 'Amount', 'value' => $text );
	}
	if ( has_term( 'pick-and-mix', 'product_cat', $id ) ) {
		return array( 'key' => 'Weight', 'value' => fika_grams_text( $qty * 100 ) );
	}
	return null;
}
}

if ( ! function_exists( 'fika_grams_text' ) ) {
function fika_grams_text( $grams ) {
	if ( $grams >= 1000 ) {
		$kg = rtrim( rtrim( number_format( $grams / 1000, 1, '.', '' ), '0' ), '.' );
		return $kg . ' kg';
	}
	return $grams . ' g';
}
}

// Show the weight under each item in the cart and checkout.
add_filter( 'woocommerce_get_item_data', function ( $data, $cart_item ) {
	$label = fika_weight_label( isset( $cart_item['data'] ) ? $cart_item['data'] : null, isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 0 );
	if ( $label ) {
		$data[] = $label;
	}
	return $data;
}, 10, 2 );

// Save the weight on the order, so it shows in the order emails and in WooCommerce > Orders.
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
	$label = fika_weight_label( isset( $values['data'] ) ? $values['data'] : null, isset( $values['quantity'] ) ? $values['quantity'] : 0 );
	if ( $label ) {
		$item->add_meta_data( $label['key'], $label['value'], true );
	}
}, 10, 3 );
