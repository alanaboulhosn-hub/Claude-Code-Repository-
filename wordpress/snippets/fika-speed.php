<?php
/**
 * Fika: lighter pages. On the home page, Mix your own, Ready Mix and About us:
 * - the theme's own font files (Open Sans, Fira Sans, Montserrat as full .ttf files, about 1.3 MB) are not loaded;
 *   our pages use Outfit, Bebas Neue, Fanwood Text and the cute font, and the few spots in Open Sans (bag drawer,
 *   buttons) get a small Latin copy of the theme's Open Sans (94 KB, wordpress/fonts, media 1182) from the shop itself.
 *   (It used to come from Google Fonts, whose style sheet held up the first paint for a moment.)
 * - the Fika fonts are requested from the top of the page (not only from the page's styles), so they arrive sooner.
 * - the top photo (.fika-photo, a background in the page's styles) is asked for first thing, at high priority, the
 *   phone copy on phones: the browser otherwise only finds it once all the styles are in. Read from the page's own
 *   styles, so a new photo is picked up by itself.
 * Also product pages and the page-not-found page (they use the same header and bag).
 * Checkout, cart and account pages keep the theme fonts, but the three their text uses (Open Sans, Fira Sans, Roboto,
 * 580 KB as .ttf) come as Latin .woff2 copies of the very same files (150 KB, media 1181-1183); the full files are
 * only fetched if a letter outside Latin shows up.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-speed.php
 */
add_action( 'wp', function () {
	if ( is_admin() ) {
		return;
	}
	$fika_latin = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2190-2199, U+2212, U+2215, U+FEFF, U+FFFD';
	$fika_fonts = content_url( '/uploads/2026/10/' );
	if ( ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us', 'privacy-policy' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) ) {
		// theme fonts kept: the same families, declared again after the theme's (wp_print_font_faces, 50) for Latin only
		add_action( 'wp_head', function () use ( $fika_latin, $fika_fonts ) {
			$faces = array(
				array( '"Open Sans"', 'variable', 'fika-opensans-variable-latin.woff2' ),
				array( 'FiraSans', '400', 'fika-firasans-regular-latin.woff2' ),
				array( 'Roboto', '400', 'fika-roboto-regular-latin.woff2' ),
			);
			echo '<style id="fika-fonts-latin">';
			foreach ( $faces as $f ) {
				// same descriptors as the theme's own rules, so the text looks exactly as before
				echo '@font-face{font-family:' . $f[0] . ';font-style:normal;font-weight:' . $f[1] . ';font-display:fallback;src:url("' . esc_url( $fika_fonts . $f[2] ) . '") format("woff2");unicode-range:' . $fika_latin . ';}';
			}
			echo '</style>' . "\n";
		}, 51 );
		return;
	}
	remove_action( 'wp_head', 'wp_print_font_faces', 50 );
	remove_action( 'wp_head', 'wp_print_font_faces_from_style_variations', 50 );
	add_action( 'wp_head', function () use ( $fika_latin, $fika_fonts ) {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		echo '<style id="fika-open-sans">@font-face{font-family:"Open Sans";font-style:normal;font-weight:300 800;font-stretch:100%;font-display:swap;src:url("' . esc_url( $fika_fonts . 'fika-opensans-variable-latin.woff2' ) . '") format("woff2");unicode-range:' . $fika_latin . ';}</style>' . "\n";
		// the Fika fonts (Bebas Neue, Fanwood Text, Outfit) are asked for inside the page's own styles, so the browser
		// found them late and showed a plain font first for a moment: start fetching them here, without holding up the page
		foreach ( array( 'family=Bebas+Neue&family=Fanwood+Text&display=swap', 'family=Fanwood+Text&family=Outfit:wght@400;500;600&display=swap', 'family=Fanwood+Text&family=Outfit:wght@400;500&display=swap' ) as $fika_f ) {
			echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' . esc_attr( $fika_f ) . '" media="print" onload="this.media=\'all\'">' . "\n";
		}
	}, 2 );
	ob_start( 'fika_speed_photo_first' );
} );

// the top photo, fetched first: <link rel="preload"> at the start of <head>, from the page's own .fika-photo rules
function fika_speed_photo_first( $html ) {
	if ( false === strpos( $html, 'class="fika-photo"' ) ) {
		return $html;
	}
	$rule = '>\s*\.fika-photo\s*\{[^}]*?url\(\s*[\'"]?([^\'")]+)';
	$phone = null;
	$rest = $html;
	if ( preg_match( '/@media\s*\(\s*max-width:\s*(\d+)px\s*\)\s*\{[^{}]*' . $rule . '/', $html, $m ) ) {
		$phone = array( (int) $m[1], $m[2] );
		$rest = str_replace( $m[0], '', $html );
	}
	if ( ! preg_match( '/' . $rule . '/', $rest, $b ) ) {
		return $html;
	}
	$link = function ( $url, $media ) {
		return '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high"' . ( $media ? ' media="' . esc_attr( $media ) . '"' : '' ) . '>';
	};
	if ( $phone && $phone[1] !== $b[1] ) {
		$tags = $link( $b[1], '(min-width: ' . ( $phone[0] + 1 ) . 'px)' ) . $link( $phone[1], '(max-width: ' . $phone[0] . 'px)' );
	} else {
		$tags = $link( $b[1], '' );
	}
	return preg_replace_callback( '/<head(\s[^>]*)?>/', function ( $h ) use ( $tags ) { return $h[0] . "\n" . $tags; }, $html, 1 );
}
