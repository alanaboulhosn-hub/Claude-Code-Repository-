#!/usr/bin/env python3
"""Builds fika-halloween.php (the Code Snippets snippet) from fika-halloween.css and fika-halloween.js."""
import base64, hashlib, pathlib, sys
d = pathlib.Path(__file__).parent
css = (d / 'fika-halloween.css').read_text().strip()
# the jack-o'-lantern cursor pictures (wordpress/cursor/halloween, drawn by draw.py), inlined
for key, f in (('PUMPKIN_40', 'pumpkin-40'), ('PUMPKIN_80', 'pumpkin-80'), ('PUMPKIN_LIT_44', 'pumpkin-lit-44'), ('PUMPKIN_LIT_88', 'pumpkin-lit-88')):
    b64 = base64.b64encode((d.parent / 'cursor' / 'halloween' / (f + '.png')).read_bytes()).decode()
    css = css.replace('{{%s}}' % key, 'data:image/png;base64,' + b64)
assert '{{' not in css
js = (d / 'fika-halloween.js').read_text().strip()
for t, tag in ((css, 'FIKA_HW_CSS'), (js, 'FIKA_HW_JS')):
    assert tag not in t and '</script' not in t and '</style' not in t
ver = hashlib.sha1((css + js).encode()).hexdigest()[:10]
php = """<?php
/**
 * Fika: Halloween week look. Colours and decorations only: night colours, pumpkin orange, skulls, bats, cobwebs and
 * a spider over the normal site. Every page, button and checkout step works exactly as before.
 * - Installed switched off. One click on "Halloween look" in the WP admin top bar switches it on (option fika_hw_on),
 *   another click switches it off again. No end date (owner, 28 Oct 2026: on until the button turns it off);
 *   FIKA_HW_END is a far-off date only so the code that asks for an end keeps working.
 *   Deactivating the snippet removes it completely; the normal site underneath is never changed.
 * - GET /wp-json/fika/v1/halloween (shop admins): whether it is on, the end date and the version installed.
 * - Every fish (rewards lane, sign-up card, Fika Club card and pop-up) is drawn as a spider, and the fish cursor is a
 *   jack-o'-lantern (wordpress/cursor/halloween), lit and tilted over links and buttons.
 * - The page cache (LiteSpeed) is cleared at each click and when it ends, so no visitor gets a stale look.
 * - Private preview while it is off: any shop page with ?fika_preview=<FIKA_HW_PREVIEW_KEY> shows the look to whoever
 *   opens that link (no login needed), nobody else; links on the page keep the key, a small note says it is a preview.
 * - Built from wordpress/snippets/fika-halloween.css and fika-halloween.js by build-halloween.py (edit those, rebuild).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-halloween.php
 */

if ( ! defined( 'FIKA_HW_END' ) ) {
	define( 'FIKA_HW_END', '2099-12-31 00:00' ); // no real end: on until the admin-bar button turns it off
	define( 'FIKA_HW_VERSION', '%s' );
}

if ( ! defined( 'FIKA_HW_PREVIEW_KEY' ) ) {
	define( 'FIKA_HW_PREVIEW_KEY', '5d13fcd594' ); // ?fika_preview=<key>: the look for whoever opens that link only
}

if ( ! function_exists( 'fika_hw_on' ) ) {
	function fika_hw_end_ts() {
		return ( new DateTime( FIKA_HW_END, new DateTimeZone( 'Asia/Beirut' ) ) )->getTimestamp();
	}
	// the private preview link: the look on that page for whoever opens it (links on the page keep the key)
	function fika_hw_preview() {
		return time() < fika_hw_end_ts() && ! is_admin() && isset( $_GET['fika_preview'] ) && is_string( $_GET['fika_preview'] ) && hash_equals( FIKA_HW_PREVIEW_KEY, $_GET['fika_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	function fika_hw_on() {
		return time() < fika_hw_end_ts() && ! is_admin() && ( get_option( 'fika_hw_on' ) || fika_hw_preview() );
	}
}

// the preview link: links on the page keep the key, and a small note says it is a preview
add_action( 'wp_footer', function () {
	if ( ! fika_hw_preview() || get_option( 'fika_hw_on' ) ) {
		return;
	}
	echo '<script>(function () { var k = ' . wp_json_encode( FIKA_HW_PREVIEW_KEY ) . '; document.addEventListener("click", function (e) { var a = e.target.closest ? e.target.closest("a[href]") : null; if (!a || a.host !== location.host || /\\/wp-(admin|login)/.test(a.pathname)) return; try { var u = new URL(a.href); if (u.searchParams.get("fika_preview") !== k) { u.searchParams.set("fika_preview", k); a.href = u.toString(); } } catch (x) {} }, true); })();</script>' . "\n";
	echo '<div style="position:fixed;left:50%%;top:8px;transform:translateX(-50%%);white-space:nowrap;z-index:2147483000;background:#0b0714;color:#f4ecdc;border:1px solid #ff7b1c;border-radius:999px;padding:6px 12px;font:600 12px/1.2 Outfit,Arial,sans-serif;pointer-events:none">Preview of the Halloween look: only this link shows it</div>' . "\n";
}, 99 );

// the switch in the admin top bar (shop admins only, until the end date)
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'manage_options' ) || time() >= fika_hw_end_ts() ) {
		return;
	}
	$off = ! get_option( 'fika_hw_on' );
	$bar->add_node( array(
		'id'    => 'fika-hw',
		'title' => $off ? '&#127875; Halloween look: off (click to switch on)' : '&#127875; Halloween look: on (click to switch off)',
		'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=fika_hw_toggle' ), 'fika_hw_toggle' ),
	) );
}, 100 );
add_action( 'admin_post_fika_hw_toggle', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'fika_hw_toggle' );
	$on = get_option( 'fika_hw_on' ) ? 0 : 1;
	update_option( 'fika_hw_on', $on, false );
	if ( $on ) {
		update_option( 'fika_hw_live', FIKA_HW_VERSION, false );
	}
	do_action( 'litespeed_purge_all' );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
	exit;
} );

// while it is on: clear the page cache if the look itself was updated, and once more when it ends
add_action( 'init', function () {
	if ( time() >= fika_hw_end_ts() || ! get_option( 'fika_hw_on' ) ) {
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

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/halloween', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () {
			return array( 'on' => (bool) get_option( 'fika_hw_on' ), 'ends' => FIKA_HW_END . ' Beirut', 'version' => FIKA_HW_VERSION, 'ended' => time() >= fika_hw_end_ts() );
		},
	) );
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
if len(sys.argv) > 2 and sys.argv[1] == '--preview':  # the CSS and JS as served, for the test browser
    pathlib.Path(sys.argv[2], 'hw.css').write_text(css)
    pathlib.Path(sys.argv[2], 'hw.js').write_text(js)
print('built fika-halloween.php', ver, len(php), 'bytes')
