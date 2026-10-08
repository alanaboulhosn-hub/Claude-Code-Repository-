<?php
/**
 * Fika: page caching (LiteSpeed Cache plugin).
 *
 * - The Hostinger theme marks every page "do not cache" on WooCommerce's cart_updated hook, which WooCommerce fires
 *   on every page for a new visitor, so nothing was ever cached. That one hook is removed; the theme's add-to-cart
 *   hooks stay. Signed-in visitors, the checkout, My account and the REST API are never cached (LiteSpeed defaults),
 *   and the shop pages load products, prices, stock and the bag in the browser, so a cached page is never stale.
 * - The whole cache is cleared when the header/footer patterns, a snippet or the Meta pixel settings change.
 *   Anything else: WP Admin top bar > LiteSpeed Cache > Purge All.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-cache.php
 */

add_action( 'init', function () {
	global $wp_filter;
	if ( empty( $wp_filter['woocommerce_cart_updated'] ) ) {
		return;
	}
	foreach ( $wp_filter['woocommerce_cart_updated']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$f = $cb['function'];
			if ( is_array( $f ) && is_object( $f[0] ) && 'Hostinger\AiTheme\Compatibility\LiteSpeedCache' === get_class( $f[0] ) ) {
				remove_action( 'woocommerce_cart_updated', $f, $prio );
			}
		}
	}
}, 20 );

function fika_cache_purge_all() {
	do_action( 'litespeed_purge_all', 'Fika: site part changed' );
}
add_action( 'save_post_wp_block', 'fika_cache_purge_all' );
add_action( 'update_option_fika_meta', 'fika_cache_purge_all' );
add_action( 'code_snippets/create_snippet', 'fika_cache_purge_all' );
add_action( 'code_snippets/update_snippet', 'fika_cache_purge_all' );
add_action( 'code_snippets/activate_snippet', 'fika_cache_purge_all' );
add_action( 'code_snippets/deactivate_snippet', 'fika_cache_purge_all' );
