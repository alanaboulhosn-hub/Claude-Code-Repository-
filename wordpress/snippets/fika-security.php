<?php
/**
 * Fika: security hardening (owner, 13 Nov 2026, after an outside-in check of the live site).
 * - Account names stay private: the public user list (/wp-json/wp/v2/users) is only for signed-in staff, author pages and
 *   ?author= lookups go to the home page, the users sitemap is off, and oEmbed answers carry no author name.
 * - XML-RPC is off (an old remote-login door used for fast password guessing): xmlrpc.php answers 403; its header and
 *   link are removed.
 * - Login limits: after 8 wrong passwords for one account, or 20 from one address, within 15 minutes, logins for that
 *   account / from that address pause for 15 minutes. A correct login does not reset the count of another address.
 *   Application passwords (the shop's API access) are not affected.
 * - Standard security headers: HTTPS only (HSTS, 180 days, this domain only), no showing the site inside other sites,
 *   no content-type guessing, referrer limited to the site address, no camera / microphone / location / payment
 *   requests; the PHP version is no longer announced.
 * - LiteSpeed's page cache is cleared once when this version is first loaded, so cached pages get the headers.
 * Changes nothing in orders, customers, checkout or emails. Switch off: deactivate the snippet.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-security.php
 */

// ---------- account names stay private ----------
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
		return $endpoints;
	}
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
} );
add_action( 'template_redirect', function () {
	if ( is_author() || ( isset( $_GET['author'] ) && ! is_admin() ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 );
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

// ---------- XML-RPC off ----------
// xmlrpc.php sets XMLRPC_REQUEST before WordPress loads the snippets: answer 403 and stop (the filters below remain as
// a second line in case the snippet ever loads later than that)
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'XML-RPC is disabled on this site.';
	exit;
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', function () {
	return array();
} );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
remove_action( 'wp_head', 'rsd_link' );

// ---------- login limits ----------
if ( ! function_exists( 'fika_sec_keys' ) ) {
	// one counter per account (whatever name or email was typed) and one per address
	function fika_sec_keys( $username ) {
		$u    = trim( strtolower( (string) $username ) );
		$user = $u ? ( is_email( $u ) ? get_user_by( 'email', $u ) : get_user_by( 'login', $u ) ) : false;
		$who  = $user ? 'u' . $user->ID : 'n' . md5( $u );
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return array( 'acct' => 'fika_sec_a_' . md5( $who ), 'ip' => 'fika_sec_i_' . md5( $ip ) );
	}
	function fika_sec_blocked( $username ) {
		$k = fika_sec_keys( $username );
		return (int) get_transient( $k['acct'] ) >= 8 || (int) get_transient( $k['ip'] ) >= 20;
	}
}
add_filter( 'authenticate', function ( $user, $username ) {
	if ( '' !== (string) $username && fika_sec_blocked( $username ) ) {
		return new WP_Error( 'fika_too_many', __( 'Too many wrong passwords. Please wait 15 minutes and try again, or use "Lost your password?".', 'fika' ) );
	}
	return $user;
}, 99, 2 );
add_action( 'wp_login_failed', function ( $username ) {
	$k = fika_sec_keys( $username );
	foreach ( $k as $key ) {
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	}
} );

// ---------- security headers ----------
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header_remove( 'X-Powered-By' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=15552000' );
	}
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
} );
add_action( 'init', function () {
	if ( ! headers_sent() ) {
		header_remove( 'X-Powered-By' );
	}
	// clear the page cache once per version, so cached pages carry the new headers
	if ( get_option( 'fika_sec_live' ) !== '1' ) {
		update_option( 'fika_sec_live', '1', false );
		do_action( 'litespeed_purge_all', 'Fika: security headers' );
	}
}, 1 );
