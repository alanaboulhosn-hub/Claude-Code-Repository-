<?php
/**
 * Fika: the shop page at /shop/ is our own page (page 40: banner, filters, the same cards and bag as the home page).
 * WooCommerce's built-in product catalogue also wants /shop/, so it is switched off (has_archive false);
 * single product pages (/product/...) are unchanged.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-shop-page.php
 */
add_filter( 'woocommerce_register_post_type_product', function ( $args ) {
	$args['has_archive'] = false;
	return $args;
} );
