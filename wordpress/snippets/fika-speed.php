<?php
/**
 * Fika: lighter pages. On the home page, Mix your own, Ready Mix and About us:
 * - the theme's own font files (Open Sans, Fira Sans, Montserrat as full .ttf files, about 1.3 MB) are not loaded;
 *   our pages use Outfit, Bebas Neue, Fanwood Text and the cute font, and the few spots in Open Sans (bag drawer,
 *   buttons) get a small web copy of Open Sans from Google Fonts instead, so they look the same.
 * Also product pages and the page-not-found page (they use the same header and bag). Checkout and account pages keep the theme fonts.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-speed.php
 */
add_action( 'wp', function () {
	if ( ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us', 'privacy-policy' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) ) {
		return;
	}
	remove_action( 'wp_head', 'wp_print_font_faces', 50 );
	remove_action( 'wp_head', 'wp_print_font_faces_from_style_variations', 50 );
	add_action( 'wp_head', function () {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400..700&display=swap">' . "\n";
	}, 2 );
} );
