<?php
/**
 * Fika: the shop's product list is written into every shop page (window.fikaProductsData), so the sweets grids and
 * the Ready Mix bags draw at once instead of showing empty boxes for about 2 s while the Store API answers.
 * - The data is exactly what the pages fetched before: GET /wc/store/v1/products?per_page=100&orderby=menu_order&
 *   order=asc&catalog_visibility=catalog. window.fikaProducts() (synced pattern "head", 239) uses it when present and
 *   fetches as before when it is not.
 * - Kept 12 hours in a transient. Saving a product, or a product going in / out of stock, clears it and purges the
 *   page cache (LiteSpeed), so a cached page never shows an old price, photo or stock.
 * - Left out of the cart, checkout and My account (no grids there) and of WP Admin.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-products-inline.php
 */

if ( ! function_exists( 'fika_pi_json' ) ) {
	function fika_pi_json() {
		$json = get_transient( 'fika_products_json' );
		if ( false !== $json ) {
			return $json;
		}
		$req = new WP_REST_Request( 'GET', '/wc/store/v1/products' );
		$req->set_query_params( array( 'per_page' => 100, 'orderby' => 'menu_order', 'order' => 'asc', 'catalog_visibility' => 'catalog' ) );
		$res = rest_do_request( $req );
		if ( $res->is_error() || 200 !== $res->get_status() ) {
			return '';
		}
		$data = rest_get_server()->response_to_data( $res, false );
		$json = is_array( $data ) && $data ? wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
		if ( $json ) {
			set_transient( 'fika_products_json', $json, 12 * HOUR_IN_SECONDS );
		}
		return $json;
	}
	function fika_pi_clear() {
		delete_transient( 'fika_products_json' );
		do_action( 'litespeed_purge_all' );
	}
}

add_action( 'wp_head', function () {
	if ( is_admin() || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) ) {
		return;
	}
	$json = fika_pi_json();
	if ( $json ) {
		echo '<script>window.fikaProductsData=' . $json . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 1 );

// a product changed in WP Admin (price, name, photo, visibility, stock) or went in / out of stock
add_action( 'save_post_product', function ( $post_id ) {
	if ( ! wp_is_post_revision( $post_id ) ) {
		fika_pi_clear();
	}
} );
add_action( 'woocommerce_product_set_stock_status', 'fika_pi_clear' );
add_action( 'woocommerce_variation_set_stock_status', 'fika_pi_clear' );
add_action( 'trashed_post', function ( $post_id ) {
	if ( 'product' === get_post_type( $post_id ) ) {
		fika_pi_clear();
	}
} );
