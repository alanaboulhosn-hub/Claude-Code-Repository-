<?php
/**
 * Fika: snippet guard (owner, 28 Oct 2026: "make sure the Halloween issue never happens again").
 * On 28 Oct the Code Snippets plugin silently loaded the Halloween snippet as empty (it took a comment in it for a
 * marker), so the look and the drops popup vanished while the snippet still showed as active.
 * - On every request, after the other snippets (priority 99): each watched snippet defines a function; if that
 *   function is missing although the snippet is active, its code is read straight from the snippets table and run
 *   here, so the shop keeps working. Each such rescue is logged.
 * - Every hour (and on the first rescue): all active snippets are compared, the code in the database against the code
 *   the plugin hands out; any difference is logged, and an email goes to the site's admin address (at most one per
 *   problem per 6 hours).
 * - Status for shop managers: GET /wp-json/fika/v1/guard. The log is kept in option fika_guard_log (last 50).
 * Keep this snippet free of block comments with "name:end" style markers.
 * Installed with the Code Snippets plugin, priority 99. Source: wordpress/snippets/fika-guard.php
 */
if ( ! defined( 'FIKA_GUARD_WATCH' ) ) {
	// snippet id => a function that snippet defines (so we can tell whether it ran)
	define( 'FIKA_GUARD_WATCH', wp_json_encode( array(
		184 => 'fika_hw_on',          // Halloween look
		193 => 'fika_drops_img',      // Halloween drops popup
		25  => 'fika_bags',           // bag reminder
		43  => 'fika_meta_send',      // Meta pixel + Conversions API
		11  => 'fika_swim_totals',    // Fika Club rewards
	) ) );
}
if ( ! function_exists( 'fika_guard_log' ) ) {
	function fika_guard_log( $what, $id = 0 ) {
		$log = get_option( 'fika_guard_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 'at' => time(), 'id' => (int) $id, 'what' => (string) $what ) );
		update_option( 'fika_guard_log', array_slice( $log, 0, 50 ), false );
	}
	function fika_guard_alert( $key, $subject, $body ) {
		$sent = get_option( 'fika_guard_sent', array() );
		$sent = is_array( $sent ) ? $sent : array();
		if ( isset( $sent[ $key ] ) && $sent[ $key ] > time() - 6 * HOUR_IN_SECONDS ) {
			return;
		}
		$sent[ $key ] = time(); // marked first: a failed send is not retried before the next 6 hours
		update_option( 'fika_guard_sent', $sent, false );
		wp_mail( get_option( 'admin_email' ), '[Fika] ' . $subject, $body . "\n\n" . home_url( '/' ) . "\nStatus: " . rest_url( 'fika/v1/guard' ) );
	}
	function fika_guard_db_code( $id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'snippets';
		return $wpdb->get_row( $wpdb->prepare( "SELECT code, active, name FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}
	// compare every active snippet: the code in the table against what the plugin hands out
	function fika_guard_scan() {
		global $wpdb;
		if ( ! function_exists( '\Code_Snippets\get_snippet' ) ) {
			return array();
		}
		$t    = $wpdb->prefix . 'snippets';
		$rows = $wpdb->get_results( "SELECT id, name, LENGTH(code) AS len FROM {$t} WHERE active = 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		$bad  = array();
		foreach ( (array) $rows as $r ) {
			$sn = \Code_Snippets\get_snippet( (int) $r['id'] );
			$n  = strlen( trim( (string) $sn->code ) );
			if ( (int) $r['len'] > 20 && $n < 10 ) {
				$bad[] = array( 'id' => (int) $r['id'], 'name' => $r['name'], 'db' => (int) $r['len'], 'loaded' => $n );
			}
		}
		update_option( 'fika_guard_scan', array( 'at' => time(), 'bad' => $bad ), false );
		return $bad;
	}
}

// the rescue: run a watched snippet's code ourselves when the plugin did not
$fika_guard_rescued = array();
$fika_guard_test    = get_option( 'fika_guard_test', array() ); // a throwaway test snippet id => function (no alerts)
$fika_guard_test    = is_array( $fika_guard_test ) ? $fika_guard_test : array();
foreach ( json_decode( FIKA_GUARD_WATCH, true ) + $fika_guard_test as $fika_gid => $fika_gfn ) {
	if ( function_exists( $fika_gfn ) ) {
		continue;
	}
	$fika_grow = fika_guard_db_code( (int) $fika_gid );
	if ( ! $fika_grow || ! (int) $fika_grow['active'] || strlen( trim( (string) $fika_grow['code'] ) ) < 20 ) {
		continue;
	}
	try {
		eval( $fika_grow['code'] ); // phpcs:ignore Squiz.PHP.Eval -- the same code Code Snippets would have run
		$fika_guard_rescued[] = (int) $fika_gid;
	} catch ( \Throwable $e ) {
		fika_guard_log( 'rescue failed: ' . $e->getMessage(), $fika_gid );
	}
}
$fika_guard_rescued = array_values( array_diff( $fika_guard_rescued, array_map( 'intval', array_keys( $fika_guard_test ) ) ) );
if ( $fika_guard_rescued ) {
	add_action( 'shutdown', function () use ( $fika_guard_rescued ) {
		$last = get_transient( 'fika_guard_rescue_logged' );
		if ( $last ) { // log once per hour, not on every request
			return;
		}
		set_transient( 'fika_guard_rescue_logged', 1, HOUR_IN_SECONDS );
		fika_guard_log( 'rescued (the plugin did not run them): snippet ' . implode( ', ', $fika_guard_rescued ) );
		fika_guard_alert( 'rescue', 'A snippet stopped loading; the guard is running it for now',
			"The Code Snippets plugin did not run snippet(s) " . implode( ', ', $fika_guard_rescued ) . ".\n" .
			"The guard runs them from the saved code, so the shop keeps working, but the cause should be fixed (often a plugin update)." );
	} );
}

// the hourly scan of all snippets
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'fika_guard_hourly' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'fika_guard_hourly' );
	}
} );
add_action( 'fika_guard_hourly', function () {
	$bad = fika_guard_scan();
	if ( $bad ) {
		$list = implode( "\n", array_map( function ( $b ) { return '- #' . $b['id'] . ' ' . $b['name'] . ' (' . $b['db'] . ' bytes saved, ' . $b['loaded'] . ' loaded)'; }, $bad ) );
		fika_guard_log( 'snippets not loading: ' . implode( ', ', wp_list_pluck( $bad, 'id' ) ) );
		fika_guard_alert( 'scan-' . md5( $list ), 'Snippets not loading', "These active snippets are saved but the Code Snippets plugin loads them as empty:\n" . $list );
	}
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/guard', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function ( $req ) {
			$watch = array();
			foreach ( json_decode( FIKA_GUARD_WATCH, true ) as $id => $fn ) {
				$watch[ $id ] = function_exists( $fn );
			}
			return array(
				'watched_running' => $watch,
				'scan'            => $req->get_param( 'scan' ) ? array( 'at' => time(), 'bad' => fika_guard_scan() ) : get_option( 'fika_guard_scan', null ),
				'log'             => get_option( 'fika_guard_log', array() ),
				'next_scan'       => wp_next_scheduled( 'fika_guard_hourly' ),
			);
		},
	) );
} );
