<?php
/**
 * Fika: tracking for the shop's own marketing emails (owner, 3 Nov 2026: "we definitely need tracking, develop that
 * very meticulously so we get the best data base").
 * - Which emails: win-back email 1 ("wb1"), win-back email 2 ("wb2", We owe you $5) and the bag reminder ("bag").
 * - Each email that goes out gets a send id ("wb2.k7q2pd8a"), saved with the email kind, the time and the address
 *   (hashed) in option fika_et_sends. fika_et_tag() then adds to every link of that email that leads to the shop:
 *   utm_source=fika, utm_medium=email, utm_campaign=winback-1 | winback-2 | bag-reminder, utm_content=<the link: the
 *   button's words or the picture's name, -2 for the second of the same> and fika_e=<send id>. Unsubscribe links and
 *   links to WhatsApp, Instagram and email are left as they are.
 * - WooCommerce's own order attribution reads the UTM tags, so the order's Origin says where it came from.
 * - A click (a page opened with fika_e) is saved in option fika_et_clicks [time, send id, link, phone 1/0], the send id
 *   is kept in a cookie for 30 days (fika_e), and the visitor is sent on to the same page without fika_e (the UTM tags
 *   stay). Link scanners are not counted: HEAD requests and bot-like browsers (Outlook Safe Links, Proofpoint, ...).
 * - An order placed afterwards is stamped (order meta): _fika_email (wb1 / wb2 / bag), _fika_email_send (send id),
 *   _fika_email_link, _fika_email_click (time of the click), _fika_email_how (click, or code: a win-back email 2 code
 *   used on any device; the code carries its send id in coupon meta _fika_et_send). One order per click.
 * - The bag reminder's "Finish my order" link opens the checkout through a short page: the UTM tags are carried on to
 *   the checkout (fika_et_carry), so WooCommerce sees them.
 * - Results: WP Admin > WooCommerce > Email results, and GET /wp-json/fika/v1/email-stats (shop managers): per email
 *   kind: sent, people who clicked, orders (from a click / with the code), sales, discounts given; clicks per link.
 * - The emails work the same without this snippet (they only call it when it is there).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-email-tracking.php
 */

if ( ! function_exists( 'fika_et_kinds' ) ) {
	// email kind => [ utm_campaign, name ]
	function fika_et_kinds() {
		return array(
			'wb1' => array( 'winback-1', 'Win-back email 1' ),
			'wb2' => array( 'winback-2', 'Win-back email 2' ),
			'bag' => array( 'bag-reminder', 'Bag reminder' ),
			'fu'  => array( 'delivery-follow-up', 'Email 0 (delivery follow-up)' ), // fika-followup.php (4 Nov 2026)
		);
	}
	// a new send: its id, saved with the kind, the time and the address (hashed)
	function fika_et_new_send( $kind, $email ) {
		$kinds = fika_et_kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			return '';
		}
		$id    = $kind . '.' . strtolower( wp_generate_password( 8, false, false ) );
		$sends = get_option( 'fika_et_sends', array() );
		$sends = is_array( $sends ) ? $sends : array();
		$sends[ $id ] = array( 't' => time(), 'e' => md5( strtolower( trim( (string) $email ) ) ) );
		if ( count( $sends ) > 20000 ) {
			$sends = array_slice( $sends, -20000, null, true );
		}
		update_option( 'fika_et_sends', $sends, false );
		return $id;
	}
	function fika_et_valid( $id ) {
		return is_string( $id ) && preg_match( '/^(wb1|wb2|bag|fu)\.[a-z0-9]{8}$/', $id );
	}
	// the email's links to the shop get the UTM tags and the send id
	function fika_et_tag( $html, $id ) {
		if ( ! fika_et_valid( $id ) ) {
			return $html;
		}
		$kinds = fika_et_kinds();
		$camp  = $kinds[ strstr( $id, '.', true ) ][0];
		$home  = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$seen  = array();
		return preg_replace_callback( '#<a\b([^>]*?)\bhref=(["\'])(.*?)\2([^>]*)>(.*?)</a>#is', function ( $m ) use ( $camp, $id, $home, &$seen ) {
			$url = html_entity_decode( $m[3], ENT_QUOTES );
			if ( wp_parse_url( $url, PHP_URL_HOST ) !== $home || preg_match( '/[?&](fika_unsub|fika_bag_stop)=/', $url ) ) {
				return $m[0];
			}
			// the link's name: the button's words, or the picture's alt text, or the page
			$text = trim( wp_strip_all_tags( $m[5] ) );
			if ( '' === $text && preg_match( '/\balt=(["\'])(.*?)\1/i', $m[5], $a ) ) {
				$text = preg_replace( '/[0-9.,]+/', '', $a[2] ); // a picture: its alt text without numbers ("Your fish at 1.7 kg ...")
			}
			if ( '' === $text ) {
				$text = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			}
			$name = substr( sanitize_title( $text ), 0, 40 );
			$name = '' === $name ? 'link' : $name;
			$seen[ $name ] = isset( $seen[ $name ] ) ? $seen[ $name ] + 1 : 1;
			if ( $seen[ $name ] > 1 ) {
				$name .= '-' . $seen[ $name ];
			}
			$url = add_query_arg( array( 'utm_source' => 'fika', 'utm_medium' => 'email', 'utm_campaign' => $camp, 'utm_content' => $name, 'fika_e' => $id ), $url );
			return '<a' . $m[1] . 'href=' . $m[2] . esc_url( $url ) . $m[2] . $m[4] . '>' . $m[5] . '</a>';
		}, $html );
	}
	// a link scanner or a bot, not a person
	function fika_et_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$m  = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		return 'GET' !== $m || '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|preview|scan|proofpoint|mimecast|barracuda|safelinks|python|curl|wget|java\/|go-http|headless|phantom|okhttp|axios|facebookexternalhit|whatsapp|skypeuripreview/', $ua );
	}
	// the UTM tags of this request, carried on to another of our pages
	function fika_et_carry( $url ) {
		$keep = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$keep[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
		return $keep ? add_query_arg( $keep, $url ) : $url;
	}
	// stamp an order with the email it came from (a click in the last 30 days, or a win-back email 2 code)
	function fika_et_order( $order ) {
		$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
		if ( ! $order instanceof WC_Order || '' !== (string) $order->get_meta( '_fika_email' ) ) {
			return;
		}
		$id = '';
		$at = 0;
		$link = '';
		$how = '';
		$c   = isset( $_COOKIE['fika_e'] ) ? explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['fika_e'] ) ) ) : array();
		if ( 3 === count( $c ) && fika_et_valid( $c[0] ) && (int) $c[1] > time() - 30 * DAY_IN_SECONDS ) {
			list( $id, $at, $link ) = array( $c[0], (int) $c[1], $c[2] );
			$how = 'click';
		}
		foreach ( $order->get_coupon_codes() as $code ) {
			$cp  = new WC_Coupon( $code );
			$sid = $cp->get_id() ? (string) $cp->get_meta( '_fika_et_send' ) : '';
			if ( fika_et_valid( $sid ) ) {
				$how  = $id === $sid ? 'click+code' : ( $id ? $how : 'code' );
				$id   = $id ? $id : $sid;
				break;
			}
		}
		if ( ! $id ) {
			return;
		}
		$order->update_meta_data( '_fika_email', strstr( $id, '.', true ) );
		$order->update_meta_data( '_fika_email_send', $id );
		$order->update_meta_data( '_fika_email_link', $link );
		$order->update_meta_data( '_fika_email_click', $at );
		$order->update_meta_data( '_fika_email_how', $how );
		$order->save_meta_data();
		if ( 'click' === substr( $how, 0, 5 ) ) { // one order per click
			wc_setcookie( 'fika_e', '', time() - HOUR_IN_SECONDS, is_ssl(), true );
		}
	}
	// the results: per email kind and per link
	function fika_et_stats() {
		$sends  = get_option( 'fika_et_sends', array() );
		$sends  = is_array( $sends ) ? $sends : array();
		$clicks = get_option( 'fika_et_clicks', array() );
		$clicks = is_array( $clicks ) ? $clicks : array();
		$out    = array();
		foreach ( fika_et_kinds() as $k => $v ) {
			$out[ $k ] = array( 'name' => $v[1], 'sent' => 0, 'clicked' => 0, 'clicks' => 0, 'phone' => 0, 'orders' => 0, 'orders_click' => 0, 'orders_code' => 0, 'sales' => 0.0, 'discounts' => 0.0, 'links' => array(), 'first_sent' => 0 );
		}
		foreach ( $sends as $id => $s ) {
			$k = strstr( $id, '.', true );
			if ( isset( $out[ $k ] ) ) {
				$out[ $k ]['sent']++;
				$out[ $k ]['first_sent'] = $out[ $k ]['first_sent'] ? min( $out[ $k ]['first_sent'], (int) $s['t'] ) : (int) $s['t'];
			}
		}
		$who = array();
		foreach ( $clicks as $c ) {
			$k = strstr( $c[1], '.', true );
			if ( ! isset( $out[ $k ] ) ) {
				continue;
			}
			$out[ $k ]['clicks']++;
			$out[ $k ]['phone'] += (int) $c[3];
			$out[ $k ]['links'][ $c[2] ] = isset( $out[ $k ]['links'][ $c[2] ] ) ? $out[ $k ]['links'][ $c[2] ] + 1 : 1;
			$who[ $k ][ $c[1] ] = 1;
		}
		foreach ( $who as $k => $ids ) {
			$out[ $k ]['clicked'] = count( $ids );
		}
		$orders = wc_get_orders( array(
			'limit'      => -1,
			'status'     => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			'meta_query' => array( array( 'key' => '_fika_email', 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( $orders as $o ) {
			$k = (string) $o->get_meta( '_fika_email' );
			if ( ! isset( $out[ $k ] ) ) {
				continue;
			}
			$out[ $k ]['orders']++;
			$out[ $k ][ 'code' === $o->get_meta( '_fika_email_how' ) ? 'orders_code' : 'orders_click' ]++;
			$out[ $k ]['sales']     += (float) $o->get_total();
			$out[ $k ]['discounts'] += (float) $o->get_discount_total();
		}
		foreach ( $out as $k => $v ) {
			arsort( $out[ $k ]['links'] );
		}
		return $out;
	}
}

// a click in one of our emails: remember it, then the same page without fika_e (before any other redirect)
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['fika_e'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$id = sanitize_text_field( wp_unslash( $_GET['fika_e'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( fika_et_valid( $id ) && ! fika_et_bot() ) {
		$link   = isset( $_GET['utm_content'] ) ? substr( sanitize_title( wp_unslash( $_GET['utm_content'] ) ), 0, 44 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$clicks = get_option( 'fika_et_clicks', array() );
		$clicks = is_array( $clicks ) ? $clicks : array();
		$clicks[] = array( time(), $id, $link, wp_is_mobile() ? 1 : 0 );
		if ( count( $clicks ) > 30000 ) {
			$clicks = array_slice( $clicks, -30000 );
		}
		update_option( 'fika_et_clicks', $clicks, false );
		wc_setcookie( 'fika_e', $id . '|' . time() . '|' . $link, time() + 30 * DAY_IN_SECONDS, is_ssl(), true );
	}
	nocache_headers();
	wp_safe_redirect( remove_query_arg( 'fika_e' ) );
	exit;
}, 1 );

// orders: stamped with the email they came from
add_action( 'woocommerce_store_api_checkout_order_processed', 'fika_et_order', 20 );
add_action( 'woocommerce_checkout_order_processed', 'fika_et_order', 20 );

// results for shop managers
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/email-stats', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => 'fika_et_stats',
	) );
} );
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Email results', 'Email results', 'manage_woocommerce', 'fika-email-results', function () {
		$m = function ( $v ) { return wp_strip_all_tags( wc_price( $v ) ); };
		$p = function ( $a, $b ) { return $b ? round( $a / $b * 100, 1 ) . '%' : '–'; };
		echo '<div class="wrap"><h1>Email results</h1><p style="max-width:760px">From the moment tracking started. A click is a person opening a link in the email (link scanners are left out); an order counts when it is placed within 30 days of a click, or with the email&rsquo;s own code. Cancelled and failed orders are left out.</p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Email</th><th>Sent</th><th>People who clicked</th><th>Orders</th><th>Orders / sent</th><th>Sales</th><th>Discounts given</th><th>Clicks per link</th></tr></thead><tbody>';
		foreach ( fika_et_stats() as $s ) {
			$links = array();
			foreach ( array_slice( $s['links'], 0, 6, true ) as $l => $n ) {
				$links[] = esc_html( $l ) . ' ' . (int) $n;
			}
			echo '<tr><td><b>' . esc_html( $s['name'] ) . '</b>' . ( $s['first_sent'] ? '<br><small>since ' . esc_html( wp_date( 'j M Y', $s['first_sent'] ) ) . '</small>' : '' ) . '</td><td>' . (int) $s['sent'] . '</td>' .
				'<td>' . (int) $s['clicked'] . ' <small>(' . esc_html( $p( $s['clicked'], $s['sent'] ) ) . ', ' . (int) $s['phone'] . ' of ' . (int) $s['clicks'] . ' clicks on a phone)</small></td>' .
				'<td>' . (int) $s['orders'] . ' <small>(' . (int) $s['orders_click'] . ' after a click, ' . (int) $s['orders_code'] . ' with the code only)</small></td><td>' . esc_html( $p( $s['orders'], $s['sent'] ) ) . '</td>' .
				'<td>' . esc_html( $m( $s['sales'] ) ) . '</td><td>' . esc_html( $m( $s['discounts'] ) ) . '</td><td><small>' . implode( '<br>', $links ) . '</small></td></tr>';
		}
		echo '</tbody></table></div>';
	} );
}, 62 );
