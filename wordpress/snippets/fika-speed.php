<?php
/**
 * Fika: lighter pages. On the home page, Mix your own, Ready Mix and About us:
 * - the theme's own font files (Open Sans, Fira Sans, Montserrat as full .ttf files, about 1.3 MB) are not loaded;
 *   our pages use Outfit, Bebas Neue, Fanwood Text and the cute font, and the few spots in Open Sans (bag drawer,
 *   buttons) get a small web copy of Open Sans from Google Fonts instead, so they look the same.
 * - the Fika fonts are requested from the top of the page (not only from the page's styles), so they arrive sooner.
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
		// the Fika fonts (Bebas Neue, Fanwood Text, Outfit) are asked for inside the page's own styles, so the browser
		// found them late and showed a plain font first for a moment: start fetching them here, without holding up the page
		foreach ( array( 'family=Bebas+Neue&family=Fanwood+Text&display=swap', 'family=Fanwood+Text&family=Outfit:wght@400;500;600&display=swap', 'family=Fanwood+Text&family=Outfit:wght@400;500&display=swap' ) as $fika_f ) {
			echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' . esc_attr( $fika_f ) . '" media="print" onload="this.media=\'all\'">' . "\n";
		}
	}, 2 );
} );
