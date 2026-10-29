<?php
/**
 * Fika: orders stay editable while they are Processing (owner, 29 Oct 2026: customers ask to change their order).
 * - WooCommerce only lets the products of "Pending payment" and "On hold" orders be changed; every cash-on-delivery
 *   order is "Processing" right away. This adds Processing, so products, quantities, delivery and coupons can be
 *   changed in WooCommerce > Orders > the order (then "Recalculate" and "Update"). Completed, failed, cancelled and
 *   refunded orders stay locked.
 * - Editing sends no email; the customer keeps the confirmation they already got. Stock is adjusted by WooCommerce
 *   for added or removed products (it notes it on the order). Fika Club grams are read from the order's products, so
 *   they follow the edit.
 * - On an order already created in RT, a notice says RT still has the old cash amount (RT is not updated from here).
 * - Admin only, no order is changed by this snippet itself.
 * Installed with the Code Snippets plugin (admin only). Source: wordpress/snippets/fika-order-edit.php
 */
add_filter( 'wc_order_is_editable', function ( $editable, $order ) {
	if ( ! $editable && $order instanceof WC_Order && 'shop_order' === $order->get_type() && $order->has_status( 'processing' ) ) {
		return true;
	}
	return $editable;
}, 10, 2 );

// the RT warning, on the order's edit page
add_action( 'admin_notices', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
		return;
	}
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$o  = $id ? wc_get_order( $id ) : null;
	if ( ! $o || ! $o->has_status( 'processing' ) ) {
		return;
	}
	$tr = (string) $o->get_meta( '_fika_rtd_tracking' );
	if ( '' === $tr ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><b>This order is already in RT</b> (' . esc_html( $tr ) . '). '
		. 'If you change the products or the total here, RT still collects the amount it was given: change the cash '
		. 'amount in the RT dashboard too.</p></div>';
} );
