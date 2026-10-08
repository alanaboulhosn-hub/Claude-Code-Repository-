<?php
/**
 * Fika: orders imported from the old Website Builder store.
 * - The import (2026-10) added the old store's orders as guest orders with their real email, date, products and
 *   status (Fulfilled = Completed, Unfulfilled = Processing, Canceled = Cancelled), meta _fika_old_order = the old
 *   order number. No emails were sent. They join a customer's account, and count toward rewards, once the customer
 *   signs up with that email and confirms it (fika-accounts.php).
 * - Here: imported orders keep their old number (#1001 to #1455) everywhere: My account, WP Admin, emails.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-imported-orders.php
 */

add_filter( 'woocommerce_order_number', function ( $number, $order ) {
	$old = $order ? $order->get_meta( '_fika_old_order' ) : '';
	return '' !== (string) $old ? (string) $old : $number;
}, 10, 2 );
