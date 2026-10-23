#!/usr/bin/env python3
"""Builds fika-halloween.php (the Code Snippets snippet) from fika-halloween.css and fika-halloween.js."""
import hashlib, pathlib
d = pathlib.Path(__file__).parent
css = (d / 'fika-halloween.css').read_text().strip()
js = (d / 'fika-halloween.js').read_text().strip()
for t, tag in ((css, 'FIKA_HW_CSS'), (js, 'FIKA_HW_JS')):
    assert tag not in t and '</script' not in t and '</style' not in t
ver = hashlib.sha1((css + js).encode()).hexdigest()[:10]
php = """<?php
/**
 * Fika: Halloween week look. Colours and decorations only: night colours, pumpkin orange, skulls, bats, cobwebs and
 * a spider over the normal site. Every page, button and checkout step works exactly as before.
 * - On from the moment this snippet is switched on until FIKA_HW_END (1 November 2026, 00:00 Beirut), then off by
 *   itself. Switching the snippet off ends it at once.
 * - The page cache (LiteSpeed) is cleared once when it goes on, and again when it ends, so no visitor gets a stale look.
 * - Built from wordpress/snippets/fika-halloween.css and fika-halloween.js by build-halloween.py (edit those, rebuild).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-halloween.php
 */

if ( ! defined( 'FIKA_HW_END' ) ) {
	define( 'FIKA_HW_END', '2026-11-01 00:00' ); // Beirut time
	define( 'FIKA_HW_VERSION', '%s' );
}

if ( ! function_exists( 'fika_hw_on' ) ) {
	function fika_hw_end_ts() {
		return ( new DateTime( FIKA_HW_END, new DateTimeZone( 'Asia/Beirut' ) ) )->getTimestamp();
	}
	function fika_hw_on() {
		return time() < fika_hw_end_ts() && ! is_admin();
	}
}

// clear the page cache when the look goes on or changes, and once more when it ends
add_action( 'init', function () {
	if ( time() >= fika_hw_end_ts() ) {
		return;
	}
	if ( get_option( 'fika_hw_live' ) !== FIKA_HW_VERSION ) {
		update_option( 'fika_hw_live', FIKA_HW_VERSION, false );
		do_action( 'litespeed_purge_all' );
	}
	if ( ! wp_next_scheduled( 'fika_hw_ended' ) ) {
		wp_schedule_single_event( fika_hw_end_ts() + 60, 'fika_hw_ended' );
	}
} );
add_action( 'fika_hw_ended', function () {
	do_action( 'litespeed_purge_all' );
} );

add_filter( 'body_class', function ( $classes ) {
	if ( fika_hw_on() ) {
		$classes[] = 'fika-hw';
	}
	return $classes;
} );

add_action( 'wp_head', function () {
	if ( ! fika_hw_on() ) {
		return;
	}
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Creepster&display=swap">' . "\\n";
	echo "<style id=\\"fika-hw\\">\\n" . <<<'FIKA_HW_CSS'
%s
FIKA_HW_CSS
	. "\\n</style>\\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 99 );

add_action( 'wp_footer', function () {
	if ( ! fika_hw_on() ) {
		return;
	}
	echo "<script id=\\"fika-hw-js\\">\\n" . <<<'FIKA_HW_JS'
%s
FIKA_HW_JS
	. "\\n</script>\\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 99 );
""" % (ver, css, js)
(d / 'fika-halloween.php').write_text(php)
print('built fika-halloween.php', ver, len(php), 'bytes')
