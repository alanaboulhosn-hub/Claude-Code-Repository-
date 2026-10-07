<?php
/**
 * Fika: shop pages. Mix your own (page 40, /mix-your-own/) and Ready Mix (page 38, /ready-mix/) are our own pages:
 * banner, (filters,) the same cards and bag as the home page.
 * - WooCommerce's built-in product catalogue is switched off (has_archive false) so it does not claim /shop/;
 *   single product pages (/product/...) get the Fika look from fika-store-pages.php.
 * - The old /shop/ address sends visitors to /mix-your-own/ (permanent redirect).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-shop-page.php
 */
add_filter( 'woocommerce_register_post_type_product', function ( $args ) {
	$args['has_archive'] = false;
	return $args;
} );
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
	if ( 'shop' === $path ) {
		wp_safe_redirect( home_url( '/mix-your-own/' ), 301 );
		exit;
	}
}, 1 );
