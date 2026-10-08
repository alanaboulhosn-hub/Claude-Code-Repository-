<?php
/**
 * Fika: order numbers. Imported orders keep their old number; new orders continue from #1456.
 * - The import (2026-10) added the old store's orders as guest orders with their real email, date, products and
 *   status (Fulfilled = Completed, Unfulfilled = Processing, Canceled = Cancelled), meta _fika_old_order = the old
 *   order number (#1001 to #1455). No emails were sent. They join a customer's account, and count toward rewards,
 *   once the customer signs up with that email and confirms it (fika-accounts.php).
 * - New orders get the next number (meta _fika_order_number) when they are placed, from the counter in option
 *   fika_next_order_number (starts at 1456, raised atomically, so two orders at once never share a number).
 *   Unfinished checkouts (drafts) get no number, so there are no gaps from abandoned bags.
 * - The number shows everywhere WooCommerce shows one: My account, the order popup, emails, WP Admin, Meta.
 *   WP Admin > Orders search finds orders by these numbers too.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-imported-orders.php
 */

if ( ! defined( 'FIKA_FIRST_ORDER_NUMBER' ) ) {
	define( 'FIKA_FIRST_ORDER_NUMBER', 1456 );
}

if ( ! function_exists( 'fika_next_order_number' ) ) {
	// the next number, raised in one database step
	function fika_next_order_number() {
		global $wpdb;
		add_option( 'fika_next_order_number', FIKA_FIRST_ORDER_NUMBER, '', false );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID( option_value + 1 ) WHERE option_name = 'fika_next_order_number'" );
		$next = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( 'fika_next_order_number', 'options' );
		return $next > FIKA_FIRST_ORDER_NUMBER ? $next - 1 : FIKA_FIRST_ORDER_NUMBER;
	}
}

// give a placed order its number just before it is saved (before any email goes out)
add_action( 'woocommerce_before_order_object_save', function ( $order ) {
	if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
		return;
	}
	if ( in_array( $order->get_status( 'edit' ), array( 'checkout-draft', 'draft', 'auto-draft', 'trash' ), true ) ) {
		return;
	}
	if ( '' !== (string) $order->get_meta( '_fika_old_order' ) || '' !== (string) $order->get_meta( '_fika_order_number' ) ) {
		return;
	}
	$order->update_meta_data( '_fika_order_number', (string) fika_next_order_number() );
} );

add_filter( 'woocommerce_order_number', function ( $number, $order ) {
	if ( ! $order ) {
		return $number;
	}
	foreach ( array( '_fika_old_order', '_fika_order_number' ) as $k ) {
		$v = (string) $order->get_meta( $k );
		if ( '' !== $v ) {
			return $v;
		}
	}
	return $number;
}, 10, 2 );

// WP Admin > Orders: searching "1456" finds the order with that number
add_filter( 'woocommerce_order_table_search_query_meta_keys', function ( $keys ) {
	return array_merge( (array) $keys, array( '_fika_old_order', '_fika_order_number' ) );
} );
add_filter( 'woocommerce_shop_order_search_fields', function ( $fields ) {
	return array_merge( (array) $fields, array( '_fika_old_order', '_fika_order_number' ) );
} );
