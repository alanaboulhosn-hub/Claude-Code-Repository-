<?php
/**
 * Fika: checkout tweaks for Lebanon and weight labels.
 * Installed with the Code Snippets plugin (runs everywhere).
 */

// Phone number is required: orders are cash on delivery and the driver needs to call.
add_filter( 'pre_option_woocommerce_checkout_phone_field', function () {
	return 'required';
} );

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
