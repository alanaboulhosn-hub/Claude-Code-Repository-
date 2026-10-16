<?php
/**
 * Fika: RT Deliveries parcel tracking (WooCommerce > RT Deliveries).
 * Parcels are still created by hand in the RT dashboard. RT sends every status change to our webhook
 * (POST /wp-json/fika/v1/rtd-webhook, header RTD-Signature = the secret shown on the settings page), and the order
 * follows it (statuses in fika_rtd_statuses()):
 * - Delivered                                       -> order Completed (counts as delivered on Fika customers + rewards)
 * - Delivered_failed, Return_assign_to_merchant, Cancel -> order Failed
 * - everything else, including statuses not in the list (pending, pickup, warehouse, delivery man assigned,
 *   return to warehouse, checking with merchant, ...) -> order Processing
 * Cancelled and refunded orders are left alone. The RT column in Orders shows RT's exact step and tracking ID.
 * Each update adds a private order note. No order emails (customer or shop) are sent for RT's status changes.
 * A parcel is linked to its order by (1) its tracking ID saved on the order (box on the order page), or (2) the Fika
 * order number typed in RT's "Invoice no" field. Parcels that match nothing are parcels made directly in RT's
 * dashboard (e.g. a free replacement): they are filed quietly under "RT parcels not from the website" on the settings
 * page (no to-do, no menu badge, no effect on any order or reward); each can still be linked to an order with a click.
 * RT API login (settings page box "RT API login"): the API key (option fika_rtd_api_key, never shown in full), and
 * the RT dashboard email + password (fika_rtd_api_email / fika_rtd_api_password, the password never shown), which
 * RT's API needs to sign in (POST /merchant/signin -> auth_token; docs: wordpress/docs/RTD-Deliveries-API-v1.pdf).
 * "Test connection" signs in and lists the RT shops (pickup points): read-only, creates nothing in RT.
 * Sending orders to RT (switch "Send new orders to RT", Off by default): an order placed after the switch went On is
 * sent to RT as soon as it turns Processing (in the background, a few seconds later): shop 2392 (the RT shop), Inside
 * Beirut -> delivery type 3 (inside city) else 4, priority always Normal (2), special request None (4), name (RT
 * requires it) / phone (961 + local number) / address from the order, cash_collection = the order total for cash on
 * delivery (0 otherwise), USD. No invoice number and no note (owner's choice, 16 Oct). RT's tracking ID is saved on
 * the order (RT column) and is how the webhook finds the order. Bags: 1 up to 1.5 kg, then one more per started 1.5 kg
 * (1.6 kg = 2, 3.1 kg = 3); RT's API has no documented bags field yet, so an order of 2+ bags gets a private note
 * "N bags: set the bag count in RT" until RT names the field. Orders that already have a tracking ID, test orders (@example.com) and orders from
 * before the switch are never sent. A failed send is tried again twice (10 and 30 minutes later); after that the RT
 * column shows "Not sent to RT" in red and the order page has "Send to RT now". RT's API cannot cancel a parcel:
 * cancel it in the RT dashboard.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-rtd.php
 */

if ( ! function_exists( 'fika_rtd_statuses' ) ) {
	// RT status slug => array( label, order status to set or '' )
	function fika_rtd_statuses() {
		return array(
			'Pending'                   => array( 'Parcel created', 'processing' ),
			'Pickup_Assign'             => array( 'Pickup assigned', 'processing' ),
			'Pickup_Failed'             => array( 'Pickup failed', 'processing' ),
			'Collected_By_Driver'       => array( 'Picked up by driver', 'processing' ),
			'Collected_By_Pickup'       => array( 'Collected by pickup', 'processing' ),
			'Received_Warehouse'        => array( 'At RT warehouse', 'processing' ),
			'Transfer_to_hub'           => array( 'On the way to hub', 'processing' ),
			'Received_by_hub'           => array( 'At RT hub', 'processing' ),
			'Delivery_Man_Assign'       => array( 'Delivery man assigned', 'processing' ),
			'Delivery_Re_Schedule'      => array( 'Delivery rescheduled', 'processing' ),
			'Return_to_warehouse'       => array( 'Return to warehouse', 'processing' ),
			'Delivered'                 => array( 'Delivered', 'completed' ),
			'Delivered_failed'          => array( 'Delivery failed', 'failed' ),
			'Return_assign_to_merchant' => array( 'Return received by merchant', 'failed' ),
			'Return_Assign_To_Driver'   => array( 'Return assigned to driver', 'processing' ),
			'Cancel'                    => array( 'Cancelled by RT', 'failed' ),
		);
	}
	// RT's slug or display name ("Collected By Pickup", "Return received by merchant") -> our slug
	function fika_rtd_slug( $text ) {
		$flat = function ( $t ) { return preg_replace( '/[^a-z]/', '', strtolower( (string) $t ) ); };
		$want = $flat( $text );
		if ( in_array( $want, array( 'cancelled', 'canceled', 'parcelcancel', 'cancelparcel' ), true ) ) {
			return 'Cancel';
		}
		foreach ( fika_rtd_statuses() as $slug => $s ) {
			if ( $flat( $slug ) === $want || $flat( $s[0] ) === $want ) {
				return $slug;
			}
		}
		return (string) $text;
	}
	function fika_rtd_label( $slug ) {
		$s = fika_rtd_statuses();
		return isset( $s[ $slug ] ) ? $s[ $slug ][0] : str_replace( '_', ' ', (string) $slug );
	}
	// RT's API key (for calls to RT), typed in on the settings page; '' when not set
	function fika_rtd_api_key() {
		return trim( (string) get_option( 'fika_rtd_api_key', '' ) );
	}
	// the RT dashboard login the API signs in with
	function fika_rtd_api_email() {
		return trim( (string) get_option( 'fika_rtd_api_email', '' ) );
	}
	function fika_rtd_api_password() {
		return (string) get_option( 'fika_rtd_api_password', '' );
	}
	if ( ! defined( 'FIKA_RTD_API' ) ) {
		define( 'FIKA_RTD_API', 'https://app.rtdeliveries.net/api/v10' );
	}
	// a call to RT's API: array( http code, decoded body ) or WP_Error
	function fika_rtd_api_call( $method, $path, $body = null, $token = '' ) {
		$headers = array( 'apiKey' => fika_rtd_api_key(), 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
		if ( '' !== $token ) {
			$headers['Authentication'] = 'Bearer ' . $token; // the header name RT's docs use
			$headers['Authorization']  = 'Bearer ' . $token;
		}
		$args = array( 'method' => $method, 'timeout' => 25, 'headers' => $headers );
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$r = wp_remote_request( FIKA_RTD_API . $path, $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( (int) wp_remote_retrieve_response_code( $r ), json_decode( wp_remote_retrieve_body( $r ), true ) );
	}
	// RT's shop (pickup point) for parcels: "Swedish Mums", Aley (GET /get-shops, 16 Oct 2026)
	if ( ! defined( 'FIKA_RTD_SHOP' ) ) {
		define( 'FIKA_RTD_SHOP', 2392 );
	}
	// a Lebanese phone number the way RT wants it: 961 + the local number (no +, spaces or leading 0)
	function fika_rtd_phone( $raw ) {
		$d = preg_replace( '/\D/', '', (string) $raw );
		if ( 0 === strpos( $d, '00' ) ) {
			$d = substr( $d, 2 );
		}
		if ( 0 === strpos( $d, '961' ) && strlen( $d ) > 9 ) {
			$d = substr( $d, 3 );
		}
		$d = ltrim( $d, '0' );
		return '' === $d ? '' : '961' . $d;
	}
	function fika_rtd_send_on() {
		return 'on' === get_option( 'fika_rtd_send', 'off' );
	}
	function fika_rtd_cut( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}
	// what RT gets for an order (POST /parcel-create)
	function fika_rtd_parcel_data( $order ) {
		$ship = '' !== trim( $order->get_shipping_address_1() );
		$name = $ship ? trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) : '';
		if ( '' === $name ) {
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}
		$area = $ship ? $order->get_shipping_state() : $order->get_billing_state();
		$addr = $ship
			? array( $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_city() )
			: array( $order->get_billing_address_1(), $order->get_billing_address_2(), $order->get_billing_city() );
		$addr[] = 'BA' === $area ? 'Beirut' : '';
		$phone  = method_exists( $order, 'get_shipping_phone' ) && '' !== (string) $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
		return array(
			'shop_id'              => FIKA_RTD_SHOP,
			'delivery_type_id'     => 'BA' === $area ? 3 : 4,
			'delivery_priority_id' => 2, // always Normal (owner's choice)
			'special_request_id'   => 4, // None
			'customer_name'        => fika_rtd_cut( '' !== $name ? $name : 'Fika customer', 191 ),
			'customer_phone'       => fika_rtd_phone( $phone ),
			'customer_address'     => fika_rtd_cut( implode( ', ', array_filter( array_map( 'trim', $addr ) ) ), 191 ),
			'cash_collection'      => 'cod' === $order->get_payment_method() ? round( (float) $order->get_total(), 2 ) : 0,
			'currency_type'        => 1, // USD
		);
	}
	// bags for an order: 1 up to 1.5 kg, one more for each started 1.5 kg above that
	function fika_rtd_bags( $order ) {
		$g = function_exists( 'fika_order_grams' ) ? (int) fika_order_grams( $order ) : 0;
		return max( 1, (int) ceil( $g / 1500 ) );
	}
	// RT's answer -> one readable line
	function fika_rtd_api_error( $r ) {
		if ( is_wp_error( $r ) ) {
			return $r->get_error_message();
		}
		$b   = is_array( $r[1] ) ? $r[1] : array();
		$msg = isset( $b['message'] ) ? (string) $b['message'] : 'RT answered ' . $r[0];
		if ( ! empty( $b['errors'] ) && is_array( $b['errors'] ) ) {
			$all = array();
			array_walk_recursive( $b['errors'], function ( $e ) use ( &$all ) { $all[] = $e; } );
			$msg = implode( ' ', array_unique( $all ) );
		}
		return $msg;
	}
	// create the parcel in RT for an order: array( ok, message )
	function fika_rtd_send( $order ) {
		if ( '' !== (string) $order->get_meta( '_fika_rtd_tracking' ) ) {
			return array( true, 'Already has an RT parcel: ' . $order->get_meta( '_fika_rtd_tracking' ) );
		}
		$tok = fika_rtd_api_signin();
		$r   = is_wp_error( $tok ) ? $tok : fika_rtd_api_call( 'POST', '/parcel-create', fika_rtd_parcel_data( $order ), $tok );
		$t   = ! is_wp_error( $r ) && isset( $r[1]['data']['parcel']['tracking_id'] ) ? (string) $r[1]['data']['parcel']['tracking_id'] : '';
		$order = wc_get_order( $order->get_id() ); // fresh: the webhook may have written meanwhile
		if ( '' !== $t ) {
			$order->update_meta_data( '_fika_rtd_sent', time() );
			$order->delete_meta_data( '_fika_rtd_send_error' );
			$bags = fika_rtd_bags( $order );
			$order->add_order_note( 'Sent to RT: parcel ' . $t . '.' . ( $bags > 1 ? ' ' . $bags . ' bags: set the bag count in RT.' : '' ) );
			fika_rtd_link( $order, $t ); // saves, and replays any RT update that came first
			return array( true, 'Sent to RT: parcel ' . $t );
		}
		$msg = fika_rtd_api_error( $r );
		$order->update_meta_data( '_fika_rtd_send_error', $msg );
		$order->add_order_note( 'Not sent to RT: ' . $msg );
		$order->save();
		fika_rtd_log( 'Order #' . $order->get_order_number() . ' not sent to RT: ' . $msg );
		return array( false, $msg );
	}
	// sign in to RT's API: the auth_token, or WP_Error with RT's message
	function fika_rtd_api_signin() {
		if ( '' === fika_rtd_api_key() || '' === fika_rtd_api_email() || '' === fika_rtd_api_password() ) {
			return new WP_Error( 'fika_rtd', 'Save the API key, email and password first.' );
		}
		$r = fika_rtd_api_call( 'POST', '/merchant/signin', array( 'email' => fika_rtd_api_email(), 'password' => fika_rtd_api_password() ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$tok = isset( $r[1]['data']['auth_token'] ) ? (string) $r[1]['data']['auth_token'] : '';
		if ( '' === $tok ) {
			return new WP_Error( 'fika_rtd', 'RT refused the sign-in (' . $r[0] . '): ' . ( isset( $r[1]['message'] ) ? $r[1]['message'] : 'no message' ) );
		}
		return $tok;
	}
	function fika_rtd_secret() {
		$s = (string) get_option( 'fika_rtd_secret', '' );
		if ( '' === $s ) {
			$s = wp_generate_password( 32, false, false );
			update_option( 'fika_rtd_secret', $s, false );
		}
		return $s;
	}
	function fika_rtd_find_by_tracking( $tracking ) {
		if ( '' === (string) $tracking ) {
			return null;
		}
		$o = wc_get_orders( array(
			'limit'      => 1,
			'type'       => 'shop_order',
			'status'     => array_keys( wc_get_order_statuses() ),
			'meta_query' => array( array( 'key' => '_fika_rtd_tracking', 'value' => (string) $tracking ) ),
		) );
		return $o ? $o[0] : null;
	}
	// "#1458", "1458", "FIKA-1458" -> the order with that Fika number
	function fika_rtd_find_by_number( $text ) {
		if ( ! preg_match( '/(\d{4,6})/', (string) $text, $m ) ) {
			return null;
		}
		foreach ( array( '_fika_order_number', '_fika_old_order' ) as $k ) {
			$o = wc_get_orders( array(
				'limit'      => 1,
				'type'       => 'shop_order',
				'status'     => array_keys( wc_get_order_statuses() ),
				'meta_query' => array( array( 'key' => $k, 'value' => $m[1] ) ),
			) );
			if ( $o ) {
				return $o[0];
			}
		}
		return null;
	}
	// open orders with no parcel yet (newest first); with $amount, only those whose total matches
	function fika_rtd_open_orders( $amount = null ) {
		$out = array();
		foreach ( wc_get_orders( array(
			'limit'        => 60,
			'type'         => 'shop_order',
			'status'       => array( 'wc-processing', 'wc-on-hold' ),
			'date_created' => '>' . ( time() - 45 * DAY_IN_SECONDS ),
			'orderby'      => 'date',
			'order'        => 'DESC',
		) ) as $o ) {
			if ( '' !== (string) $o->get_meta( '_fika_rtd_tracking' ) ) {
				continue;
			}
			if ( null !== $amount && abs( (float) $o->get_total() - (float) $amount ) > 0.009 ) {
				continue;
			}
			$out[] = $o;
		}
		return $out;
	}
	function fika_rtd_log( $line ) {
		$log = get_option( 'fika_rtd_log', array() );
		array_unshift( $log, array( time(), $line ) );
		update_option( 'fika_rtd_log', array_slice( $log, 0, 80 ), false );
	}
	// apply one RT update (array with tracking_id, parcel_status_slug, description, updated_at) to an order
	function fika_rtd_apply( $order, $ev ) {
		$slug  = (string) $ev['slug'];
		$when  = $ev['time'] ? $ev['time'] : time();
		$last  = (int) $order->get_meta( '_fika_rtd_time' );
		$hist  = $order->get_meta( '_fika_rtd_history' );
		$hist  = is_array( $hist ) ? $hist : array();
		foreach ( $hist as $h ) {
			if ( $h['slug'] === $slug && (int) $h['time'] === (int) $when ) {
				return 'duplicate';
			}
		}
		$hist[] = array( 'slug' => $slug, 'time' => $when, 'note' => (string) $ev['note'] );
		usort( $hist, function ( $a, $b ) { return $a['time'] - $b['time']; } );
		$order->update_meta_data( '_fika_rtd_history', array_slice( $hist, -40 ) );
		if ( '' === (string) $order->get_meta( '_fika_rtd_tracking' ) && '' !== $ev['tracking'] ) {
			$order->update_meta_data( '_fika_rtd_tracking', $ev['tracking'] );
		}
		$note = 'RT Deliveries: ' . fika_rtd_label( $slug ) . ( '' !== $ev['note'] ? ' (' . $ev['note'] . ')' : '' ) . '.';
		if ( $last && $when < $last ) {
			// an older update that arrived late: keep it in the history only
			$order->save();
			$order->add_order_note( $note . ' Older update, status left as it is.' );
			return 'older';
		}
		$order->update_meta_data( '_fika_rtd_status', $slug );
		$order->update_meta_data( '_fika_rtd_time', $when );
		$map = fika_rtd_statuses();
		// a status not in the list (e.g. "checking with merchant") counts as still in progress
		$to  = isset( $map[ $slug ] ) ? $map[ $slug ][1] : 'processing';
		$cur = $order->get_status();
		// RT decides the status, except on orders that were cancelled or refunded here
		if ( $to && $to !== $cur && in_array( $cur, array( 'pending', 'processing', 'on-hold', 'completed', 'failed', 'undelivered' ), true ) ) {
			$order->save();
			$GLOBALS['fika_rtd_changing'] = true;
			$order->update_status( $to, $note );
			$GLOBALS['fika_rtd_changing'] = false;
			return 'status:' . $to;
		}
		$order->save();
		$order->add_order_note( $note );
		return 'noted';
	}
	// read RT's webhook body into one tidy array
	function fika_rtd_event( $body ) {
		if ( isset( $body['data'] ) && is_array( $body['data'] ) && ! isset( $body['tracking_id'] ) ) {
			$body = $body['data'];
		}
		$g = function ( $k ) use ( $body ) {
			return isset( $body[ $k ] ) && is_scalar( $body[ $k ] ) ? trim( sanitize_text_field( (string) $body[ $k ] ) ) : '';
		};
		$slug = fika_rtd_slug( '' !== $g( 'parcel_status_slug' ) ? $g( 'parcel_status_slug' ) : $g( 'parcel_status' ) );
		$t = $g( 'updated_at' ) ? strtotime( $g( 'updated_at' ) ) : 0;
		return array(
			'tracking' => $g( 'tracking_id' ),
			'invoice'  => $g( 'invoice_no' ),
			'order_no' => $g( 'order_no' ),
			'cash'     => $g( 'cash_collection' ),
			'slug'     => $slug,
			'note'     => $g( 'description' ),
			'time'     => $t ? $t : time(),
		);
	}
	function fika_rtd_handle( $ev ) {
		$order = fika_rtd_find_by_tracking( $ev['tracking'] );
		if ( ! $order && '' !== $ev['invoice'] ) {
			$order = fika_rtd_find_by_number( $ev['invoice'] );
		}
		$tag = $ev['tracking'] . ' ' . fika_rtd_label( $ev['slug'] );
		if ( ! $order ) {
			$wait = get_option( 'fika_rtd_unmatched', array() );
			$key  = '' !== $ev['tracking'] ? $ev['tracking'] : md5( wp_json_encode( $ev ) );
			$prev = isset( $wait[ $key ]['events'] ) ? $wait[ $key ]['events'] : array();
			$prev[] = $ev;
			$wait[ $key ] = array( 'ev' => $ev, 'events' => array_slice( $prev, -20 ), 'got' => time() );
			update_option( 'fika_rtd_unmatched', array_slice( $wait, -100, null, true ), false );
			fika_rtd_log( $tag . ': not a website order (filed under RT parcels not from the website)' );
			return array( 'ok' => true, 'matched' => false );
		}
		$r = fika_rtd_apply( $order, $ev );
		fika_rtd_log( $tag . ': order #' . $order->get_order_number() . ' (' . $r . ')' );
		return array( 'ok' => true, 'matched' => true, 'order' => $order->get_order_number(), 'result' => $r );
	}
	// link a waiting parcel to an order and replay its updates in order
	function fika_rtd_link( $order, $tracking ) {
		$order->update_meta_data( '_fika_rtd_tracking', $tracking );
		$order->save();
		$wait = get_option( 'fika_rtd_unmatched', array() );
		if ( isset( $wait[ $tracking ] ) ) {
			$evs = $wait[ $tracking ]['events'];
			usort( $evs, function ( $a, $b ) { return $a['time'] - $b['time']; } );
			foreach ( $evs as $ev ) {
				fika_rtd_apply( wc_get_order( $order->get_id() ), $ev );
			}
			unset( $wait[ $tracking ] );
			update_option( 'fika_rtd_unmatched', $wait, false );
		}
		fika_rtd_log( $tracking . ': linked to order #' . $order->get_order_number() );
	}
}

// no order emails when the status change comes from RT (e.g. Failed -> Processing would re-send "Thanks for your order"
// and "New order"); the change is in the order note and the RT column
foreach ( array( 'new_order', 'failed_order', 'cancelled_order', 'customer_processing_order', 'customer_completed_order', 'customer_on_hold_order', 'customer_failed_order' ) as $fika_rtd_mail ) {
	add_filter( 'woocommerce_email_enabled_' . $fika_rtd_mail, function ( $on ) {
		return empty( $GLOBALS['fika_rtd_changing'] ) ? $on : false;
	}, 99 );
}

// ---------- the webhook ----------
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/rtd-webhook', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$sent = (string) $req->get_header( 'rtd_signature' );
			if ( '' === $sent ) {
				$sent = (string) $req->get_param( 'key' );
			}
			if ( '' === $sent || ! hash_equals( fika_rtd_secret(), $sent ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad signature' ), 401 );
			}
			$body = $req->get_json_params();
			if ( ! is_array( $body ) ) {
				$body = $req->get_body_params();
			}
			$list = isset( $body[0] ) && is_array( $body[0] ) ? $body : array( $body );
			$out  = array();
			foreach ( $list as $b ) {
				$ev = fika_rtd_event( is_array( $b ) ? $b : array() );
				if ( '' === $ev['tracking'] && '' === $ev['invoice'] ) {
					$out[] = array( 'ok' => false, 'error' => 'no tracking_id' );
					continue;
				}
				$out[] = fika_rtd_handle( $ev );
			}
			return 1 === count( $out ) ? $out[0] : array( 'ok' => true, 'results' => $out );
		},
	) );
} );

// ---------- WooCommerce > RT Deliveries ----------
// ---------- sending new orders to RT (switch on the settings page) ----------
add_action( 'woocommerce_order_status_processing', function ( $order_id, $order = null ) {
	if ( ! fika_rtd_send_on() ) {
		return;
	}
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
	if ( ! $order || '' !== (string) $order->get_meta( '_fika_rtd_tracking' ) || $order->get_meta( '_fika_rtd_queued' ) ) {
		return;
	}
	if ( preg_match( '/@example\.(com|org|net)$/i', (string) $order->get_billing_email() ) ) {
		return; // test orders never go to RT
	}
	$since = (int) get_option( 'fika_rtd_send_since', 0 );
	if ( ! $order->get_date_created() || $order->get_date_created()->getTimestamp() < $since ) {
		return; // orders from before the switch went on (old orders moved back to Processing) are not sent
	}
	$order->update_meta_data( '_fika_rtd_queued', time() );
	$order->save_meta_data();
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'fika_rtd_send_order', array( (int) $order_id, 1 ), 'fika' );
	} else {
		do_action( 'fika_rtd_send_order', (int) $order_id, 1 );
	}
}, 20, 2 );
add_action( 'fika_rtd_send_order', function ( $order_id, $try = 1 ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! fika_rtd_send_on() || '' !== (string) $order->get_meta( '_fika_rtd_tracking' ) ) {
		return;
	}
	if ( $order->has_status( array( 'cancelled', 'refunded', 'failed', 'trash' ) ) ) {
		return;
	}
	$res = fika_rtd_send( $order );
	if ( ! $res[0] && $try < 3 && function_exists( 'as_schedule_single_action' ) ) {
		as_schedule_single_action( time() + ( 1 === (int) $try ? 10 : 30 ) * MINUTE_IN_SECONDS, 'fika_rtd_send_order', array( (int) $order_id, (int) $try + 1 ), 'fika' );
	}
}, 10, 2 );
add_action( 'admin_post_fika_rtd_send_now', function () {
	$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_send_now_' . $id );
	$order = wc_get_order( $id );
	if ( $order ) {
		fika_rtd_send( $order );
	}
	wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders' ) );
	exit;
} );
add_action( 'admin_post_fika_rtd_send_switch', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_send_switch' );
	$on = ! empty( $_POST['on'] );
	update_option( 'fika_rtd_send', $on ? 'on' : 'off', false );
	if ( $on ) {
		update_option( 'fika_rtd_send_since', time(), false );
	}
	fika_rtd_log( 'Sending new orders to RT switched ' . ( $on ? 'ON' : 'OFF' ) );
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' ) );
	exit;
} );

add_action( 'admin_menu', function () {
	$title = 'RT Deliveries'; // parcels not from the website are reference only: no to-do badge
	add_submenu_page( 'woocommerce', 'RT Deliveries', $title, 'manage_woocommerce', 'fika-rtd', function () {
		$url   = rest_url( 'fika/v1/rtd-webhook' );
		$wait  = (array) get_option( 'fika_rtd_unmatched', array() );
		$log   = (array) get_option( 'fika_rtd_log', array() );
		$open  = fika_rtd_open_orders();
		$fmt   = function ( $t ) { return wp_date( 'j M, H:i', $t ); };
		$label = function ( $o ) {
			return '#' . $o->get_order_number() . ' · ' . trim( $o->get_shipping_first_name() . ' ' . $o->get_shipping_last_name() ) . ' · $' . number_format( (float) $o->get_total(), 2 ) . ' · ' . wp_date( 'j M', $o->get_date_created()->getTimestamp() );
		};
		?>
		<div class="wrap fika-rtd">
			<h1>RT Deliveries</h1>
			<style>
				.fika-rtd .box { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 20px; margin: 16px 0; max-width: 1000px; }
				.fika-rtd code.big { display: inline-block; padding: 6px 10px; font-size: 13px; user-select: all; word-break: break-all; }
				.fika-rtd table { border-collapse: collapse; width: 100%; }
				.fika-rtd td, .fika-rtd th { text-align: left; padding: 8px 6px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
				.fika-rtd .muted { color: #646970; }
				.fika-rtd select { max-width: 100%; }
			</style>
			<?php if ( isset( $_GET['linked'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success"><p>Parcel linked and its status applied.</p></div>
			<?php endif; ?>

			<div class="box">
				<h2>RT parcels not from the website (<?php echo (int) count( $wait ); ?>)</h2>
				<p class="muted">Parcels RT sent updates for that match no website order: ones made directly in RT's dashboard (e.g. a free replacement). Website orders are linked by themselves when they are sent to RT, so nothing here needs doing. For reference only: they change no order and no reward. If one does belong to a website order, pick it and press Link.</p>
				<?php if ( ! $wait ) : ?>
					<p>None.</p>
				<?php else : ?>
					<details><summary style="cursor:pointer;font-weight:600">Show the list</summary>
					<table>
						<tr><th>RT parcel</th><th>Latest RT status</th><th>Cash</th><th>Link to order</th></tr>
						<?php foreach ( array_reverse( $wait, true ) as $tid => $w ) :
							$ev   = $w['ev'];
							$same = '' !== $ev['cash'] ? fika_rtd_open_orders( $ev['cash'] ) : array();
							?>
							<tr>
								<td><b><?php echo esc_html( $ev['tracking'] ); ?></b><?php echo '' !== $ev['invoice'] ? '<br><span class="muted">Invoice ' . esc_html( $ev['invoice'] ) . '</span>' : ''; ?></td>
								<td><?php echo esc_html( fika_rtd_label( $ev['slug'] ) ); ?><br><span class="muted"><?php echo esc_html( $fmt( $ev['time'] ) . ( '' !== $ev['note'] ? ' · ' . $ev['note'] : '' ) ); ?></span></td>
								<td><?php echo '' !== $ev['cash'] ? '$' . esc_html( $ev['cash'] ) : '–'; ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="fika_rtd_link">
										<input type="hidden" name="tracking" value="<?php echo esc_attr( $tid ); ?>">
										<?php wp_nonce_field( 'fika_rtd_link' ); ?>
										<select name="order_id" required>
											<option value="">Choose the order…</option>
											<?php if ( $same ) : ?>
												<optgroup label="Same amount">
													<?php foreach ( $same as $o ) : ?>
														<option value="<?php echo (int) $o->get_id(); ?>"><?php echo esc_html( $label( $o ) ); ?></option>
													<?php endforeach; ?>
												</optgroup>
											<?php endif; ?>
											<optgroup label="All open orders without a parcel">
												<?php foreach ( $open as $o ) : ?>
													<option value="<?php echo (int) $o->get_id(); ?>"><?php echo esc_html( $label( $o ) ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										</select>
										<button class="button button-primary">Link</button>
										<button class="button" name="ignore" value="1" title="Remove it from this list">Remove from list</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					</details>
				<?php endif; ?>
			</div>

			<div class="box">
				<h2>Webhook for RT</h2>
				<p>Give RT these two values (in their dashboard's webhook settings, or ask their support to set them):</p>
				<p><b>Webhook URL</b><br><code class="big"><?php echo esc_html( $url ); ?></code></p>
				<p><b>Webhook secret</b><br><code class="big"><?php echo esc_html( fika_rtd_secret() ); ?></code></p>
				<p class="muted">Updates without this secret are refused. If the secret ever leaks, make a new one and give RT the new value.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Make a new secret? RT must be given the new one, or their updates will be refused.');">
					<input type="hidden" name="action" value="fika_rtd_secret">
					<?php wp_nonce_field( 'fika_rtd_secret' ); ?>
					<button class="button">Make a new secret</button>
				</form>
			</div>

			<div class="box">
				<h2>RT API login</h2>
				<?php
				$fika_key  = fika_rtd_api_key();
				$fika_mail = fika_rtd_api_email();
				$fika_pass = fika_rtd_api_password();
				$fika_note = isset( $_GET['apikey'] ) ? sanitize_key( wp_unslash( $_GET['apikey'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$fika_test = get_option( 'fika_rtd_api_test' );
				?>
				<?php if ( 'saved' === $fika_note ) : ?><p><b>Saved.</b></p><?php elseif ( 'removed' === $fika_note ) : ?><p><b>RT API login removed.</b></p><?php endif; ?>
				<p>What RT gave you for their API, and your RT dashboard login (RT's API signs in with it). Stored on this site only; the key and password are never shown in full. Leave a field empty to keep what is saved.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
					<input type="hidden" name="action" value="fika_rtd_api_key">
					<?php wp_nonce_field( 'fika_rtd_api_key' ); ?>
					<p><b>API key</b> <span class="muted"><?php echo $fika_key ? '(saved, ends with ' . esc_html( substr( $fika_key, -4 ) ) . ')' : '(none yet)'; ?></span><br>
						<input type="password" name="api_key" autocomplete="new-password" placeholder="<?php echo $fika_key ? 'Paste a new key to replace it' : 'Paste the RT API key'; ?>" style="width:100%;max-width:420px"></p>
					<p><b>RT dashboard email or phone</b> <span class="muted"><?php echo $fika_mail ? '(saved: ' . esc_html( $fika_mail ) . ')' : '(none yet)'; ?></span><br>
						<input type="text" name="api_email" autocomplete="off" placeholder="<?php echo $fika_mail ? 'Type a new one to replace it' : 'The email or phone you log in to RT with'; ?>" style="width:100%;max-width:420px"></p>
					<p><b>RT dashboard password</b> <span class="muted"><?php echo '' !== $fika_pass ? '(saved)' : '(none yet)'; ?></span><br>
						<input type="password" name="api_password" autocomplete="new-password" placeholder="<?php echo '' !== $fika_pass ? 'Type a new one to replace it' : 'Your RT dashboard password'; ?>" style="width:100%;max-width:420px"></p>
					<button class="button button-primary">Save</button>
					<?php if ( $fika_key || $fika_mail || '' !== $fika_pass ) : ?>
						<button class="button" name="remove" value="1" onclick="return confirm('Remove the saved RT API key, email and password?');">Remove all</button>
					<?php endif; ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
					<input type="hidden" name="action" value="fika_rtd_api_test">
					<?php wp_nonce_field( 'fika_rtd_api_test' ); ?>
					<button class="button" <?php disabled( ! ( $fika_key && $fika_mail && '' !== $fika_pass ) ); ?>>Test connection</button>
					<span class="muted">Signs in to RT and lists your shops (pickup points). Creates nothing in RT.</span>
				</form>
				<?php if ( is_array( $fika_test ) ) : ?>
					<p style="margin-top:10px"><b><?php echo $fika_test['ok'] ? '&#10003; Connected to RT' : '&#10007; Not connected'; ?></b> <span class="muted">(<?php echo esc_html( $fmt( $fika_test['at'] ) ); ?>)</span><br><?php echo esc_html( $fika_test['msg'] ); ?></p>
					<?php if ( ! empty( $fika_test['shops'] ) ) : ?>
						<table>
							<tr><th>Shop ID</th><th>Name</th><th>Address</th><th>Default</th><th>Status</th></tr>
							<?php foreach ( $fika_test['shops'] as $sh ) : ?>
								<tr><td><?php echo esc_html( $sh[0] ); ?></td><td><?php echo esc_html( $sh[1] ); ?></td><td class="muted"><?php echo esc_html( $sh[2] ); ?></td><td><?php echo $sh[3] ? 'Yes' : ''; ?></td><td class="muted"><?php echo esc_html( $sh[4] ); ?></td></tr>
							<?php endforeach; ?>
						</table>
					<?php endif; ?>
				<?php endif; ?>
				<h3 style="margin:16px 0 6px">Send new orders to RT</h3>
				<p><b><?php echo fika_rtd_send_on() ? '&#9679; On' : '&#9675; Off'; ?></b><?php echo fika_rtd_send_on() ? ' <span class="muted">since ' . esc_html( $fmt( (int) get_option( 'fika_rtd_send_since', 0 ) ) ) . '</span>' : ''; ?></p>
				<p class="muted">When on, each new order is sent to RT as soon as it is placed (Processing): shop 2392, Normal priority, the order number as RT's invoice number, cash on delivery = the order total. Test orders and older orders are never sent. RT cannot cancel parcels through the API: cancel them in RT's dashboard.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm(this.on.value ? 'Start sending every new order to RT?' : 'Stop sending new orders to RT?');">
					<input type="hidden" name="action" value="fika_rtd_send_switch">
					<input type="hidden" name="on" value="<?php echo fika_rtd_send_on() ? '' : '1'; ?>">
					<?php wp_nonce_field( 'fika_rtd_send_switch' ); ?>
					<button class="button <?php echo fika_rtd_send_on() ? '' : 'button-primary'; ?>"><?php echo fika_rtd_send_on() ? 'Turn off' : 'Turn on'; ?></button>
				</form>
			</div>

			<div class="box">
				<h2>What each RT status does</h2>
				<table>
					<?php foreach ( fika_rtd_statuses() as $slug => $s ) : ?>
						<tr><td><?php echo esc_html( $s[0] ); ?></td><td class="muted">Order → <b><?php echo esc_html( wc_get_order_status_name( $s[1] ) ); ?></b><?php echo 'completed' === $s[1] ? ' (counts as delivered)' : ''; ?></td></tr>
					<?php endforeach; ?>
				</table>
			</div>

			<div class="box">
				<h2>Latest updates from RT</h2>
				<?php if ( ! $log ) : ?>
					<p class="muted">None yet.</p>
				<?php else : ?>
					<table>
						<?php foreach ( array_slice( $log, 0, 40 ) as $l ) : ?>
							<tr><td class="muted" style="width:120px"><?php echo esc_html( $fmt( $l[0] ) ); ?></td><td><?php echo esc_html( $l[1] ); ?></td></tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	} );
}, 61 );

add_action( 'admin_post_fika_rtd_link', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_link' );
	$tracking = isset( $_POST['tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking'] ) ) : '';
	if ( ! empty( $_POST['ignore'] ) ) {
		$wait = get_option( 'fika_rtd_unmatched', array() );
		unset( $wait[ $tracking ] );
		update_option( 'fika_rtd_unmatched', $wait, false );
		fika_rtd_log( $tracking . ': removed from RT parcels not from the website' );
		wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' ) );
		exit;
	}
	$order = wc_get_order( isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0 );
	if ( $order && '' !== $tracking ) {
		fika_rtd_link( $order, $tracking );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd&linked=1' ) );
	exit;
} );

add_action( 'admin_post_fika_rtd_api_key', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_api_key' );
	if ( ! empty( $_POST['remove'] ) ) {
		delete_option( 'fika_rtd_api_key' );
		delete_option( 'fika_rtd_api_email' );
		delete_option( 'fika_rtd_api_password' );
		delete_option( 'fika_rtd_api_test' );
		fika_rtd_log( 'RT API login removed' );
		wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd&apikey=removed' ) );
		exit;
	}
	$key  = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
	$mail = isset( $_POST['api_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_email'] ) ) ) : '';
	$pass = isset( $_POST['api_password'] ) ? (string) wp_unslash( $_POST['api_password'] ) : ''; // phpcs:ignore -- a password: kept as typed
	$done = array();
	if ( '' !== $key ) {
		update_option( 'fika_rtd_api_key', $key, false );
		$done[] = 'API key (ends with ' . substr( $key, -4 ) . ')';
	}
	if ( '' !== $mail ) {
		update_option( 'fika_rtd_api_email', $mail, false );
		$done[] = 'email ' . $mail;
	}
	if ( '' !== $pass ) {
		update_option( 'fika_rtd_api_password', $pass, false );
		$done[] = 'password';
	}
	if ( $done ) {
		delete_option( 'fika_rtd_api_test' );
		fika_rtd_log( 'RT API login saved: ' . implode( ', ', $done ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' . ( $done ? '&apikey=saved' : '' ) ) );
	exit;
} );

// "Test connection": sign in and list the RT shops (read-only)
add_action( 'admin_post_fika_rtd_api_test', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_api_test' );
	$res = array( 'ok' => false, 'at' => time(), 'msg' => '', 'shops' => array() );
	$tok = fika_rtd_api_signin();
	if ( is_wp_error( $tok ) ) {
		$res['msg'] = $tok->get_error_message();
	} else {
		$r = fika_rtd_api_call( 'GET', '/get-shops', null, $tok );
		if ( is_wp_error( $r ) ) {
			$res['msg'] = 'Signed in, but the shop list failed: ' . $r->get_error_message();
		} elseif ( isset( $r[1]['data']['shops'] ) && is_array( $r[1]['data']['shops'] ) ) {
			$res['ok'] = true;
			foreach ( $r[1]['data']['shops'] as $sh ) {
				$res['shops'][] = array( isset( $sh['id'] ) ? $sh['id'] : '', isset( $sh['name'] ) ? $sh['name'] : '', isset( $sh['address'] ) ? $sh['address'] : '', ! empty( $sh['default_shop'] ), isset( $sh['statusName'] ) ? $sh['statusName'] : '' );
			}
			$res['msg'] = 'Signed in. ' . count( $res['shops'] ) . ' shop(s) found.';
		} else {
			$res['msg'] = 'Signed in, but RT answered the shop list with ' . $r[0] . ': ' . ( isset( $r[1]['message'] ) ? $r[1]['message'] : 'no message' );
		}
	}
	update_option( 'fika_rtd_api_test', $res, false ); // an option, not a transient: the site's cache dropped transients
	fika_rtd_log( 'RT API test: ' . $res['msg'] );
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' ) );
	exit;
} );

add_action( 'admin_post_fika_rtd_secret', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_secret' );
	update_option( 'fika_rtd_secret', wp_generate_password( 32, false, false ), false );
	fika_rtd_log( 'New webhook secret made' );
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' ) );
	exit;
} );

// ---------- the order page: RT tracking ID box + parcel history ----------
add_action( 'add_meta_boxes', function () {
	$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	add_meta_box( 'fika-rtd', 'RT Deliveries', function ( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$t    = (string) $order->get_meta( '_fika_rtd_tracking' );
		$hist = $order->get_meta( '_fika_rtd_history' );
		wp_nonce_field( 'fika_rtd_box', 'fika_rtd_nonce' );
		echo '<p><label for="fika_rtd_tracking"><b>RT tracking ID</b></label><br><input type="text" id="fika_rtd_tracking" name="fika_rtd_tracking" value="' . esc_attr( $t ) . '" style="width:100%" placeholder="Paste it from RT"></p>';
		if ( is_array( $hist ) && $hist ) {
			echo '<ul style="margin:0">';
			foreach ( array_reverse( $hist ) as $h ) {
				echo '<li><b>' . esc_html( fika_rtd_label( $h['slug'] ) ) . '</b> <span style="color:#646970">' . esc_html( wp_date( 'j M, H:i', $h['time'] ) ) . '</span>' . ( '' !== $h['note'] ? '<br><span style="color:#646970">' . esc_html( $h['note'] ) . '</span>' : '' ) . '</li>';
			}
			echo '</ul>';
		} elseif ( $t ) {
			echo '<p style="color:#646970">No update from RT yet.</p>';
		}
		$err = (string) $order->get_meta( '_fika_rtd_send_error' );
		if ( '' === $t ) {
			if ( '' !== $err ) {
				echo '<p style="color:#a11c1c"><b>Not sent to RT:</b> ' . esc_html( $err ) . '</p>';
			}
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fika_rtd_send_now&order=' . $order->get_id() ), 'fika_rtd_send_now_' . $order->get_id() ) ) . '" onclick="return confirm(\'Create this parcel in RT now?\');">Send to RT now</a></p>';
		}
	}, $screen, 'side', 'high' );
} );
add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
	if ( ! isset( $_POST['fika_rtd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fika_rtd_nonce'] ) ), 'fika_rtd_box' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	$new   = isset( $_POST['fika_rtd_tracking'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['fika_rtd_tracking'] ) ) ) : '';
	if ( ! $order || $new === (string) $order->get_meta( '_fika_rtd_tracking' ) ) {
		return;
	}
	if ( '' === $new ) {
		$order->delete_meta_data( '_fika_rtd_tracking' );
		$order->save();
		return;
	}
	// runs after WooCommerce saved the order, so the status it applies is not overwritten
	add_action( 'shutdown', function () use ( $order_id, $new ) {
		$o = wc_get_order( $order_id );
		if ( $o ) {
			fika_rtd_link( $o, $new );
		}
	} );
}, 50 );

// ---------- Orders list: "RT" column; orders searchable by tracking ID ----------
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $fika_hook ) {
	add_filter( $fika_hook, function ( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$out['fika_rtd'] = 'RT';
			}
		}
		return $out;
	}, 30 );
}
if ( ! function_exists( 'fika_rtd_column' ) ) {
	function fika_rtd_column( $order ) {
		$slug = (string) $order->get_meta( '_fika_rtd_status' );
		$t    = (string) $order->get_meta( '_fika_rtd_tracking' );
		if ( '' === $slug && '' === $t ) {
			echo '' !== (string) $order->get_meta( '_fika_rtd_send_error' ) ? '<span style="color:#a11c1c;font-weight:600" title="' . esc_attr( $order->get_meta( '_fika_rtd_send_error' ) ) . '">Not sent to RT</span>' : '<span style="color:#a7aaad">–</span>';
			return;
		}
		echo esc_html( '' !== $slug ? fika_rtd_label( $slug ) : 'Linked' );
		if ( $t ) {
			echo '<br><small style="color:#646970">' . esc_html( $t ) . '</small>';
		}
	}
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $col, $order ) {
	if ( 'fika_rtd' === $col ) {
		fika_rtd_column( $order );
	}
}, 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'fika_rtd' === $col && ( $o = wc_get_order( $post_id ) ) ) {
		fika_rtd_column( $o );
	}
}, 10, 2 );
add_filter( 'woocommerce_order_table_search_query_meta_keys', function ( $keys ) {
	$keys[] = '_fika_rtd_tracking';
	return $keys;
} );
add_filter( 'woocommerce_shop_order_search_fields', function ( $fields ) {
	$fields[] = '_fika_rtd_tracking';
	return $fields;
} );

// ---------- Orders list: the status links as big filter buttons (Processing, Failed, Completed first) ----------
foreach ( array( 'views_woocommerce_page_wc-orders', 'views_edit-shop_order' ) as $fika_hook ) {
	add_filter( $fika_hook, function ( $views ) {
		$first = array();
		foreach ( array( 'all', 'wc-processing', 'wc-failed', 'wc-completed' ) as $k ) {
			if ( isset( $views[ $k ] ) ) {
				$first[ $k ] = $views[ $k ];
				unset( $views[ $k ] );
			}
		}
		return $first + $views;
	}, 30 );
}
add_action( 'admin_head', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
		return;
	}
	?>
	<style>
		.wrap ul.subsubsub { float: none; display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 14px; font-size: 0; }
		.wrap ul.subsubsub li { font-size: 0; margin: 0; }
		.wrap ul.subsubsub li a { display: inline-block; font-size: 14px; font-weight: 600; line-height: 1; padding: 10px 14px; border-radius: 999px; border: 1px solid #dcdcde; background: #fff; color: #1d2327; text-decoration: none; }
		.wrap ul.subsubsub li a .count { color: inherit; opacity: .75; font-weight: 500; }
		.wrap ul.subsubsub li.wc-processing a { background: #e7f3e2; border-color: #c6e1c6; color: #2f5d12; }
		.wrap ul.subsubsub li.wc-failed a { background: #fbe7e7; border-color: #eba3a3; color: #761919; }
		.wrap ul.subsubsub li.wc-completed a { background: #e5edf3; border-color: #c8d7e1; color: #2e4453; }
		.wrap ul.subsubsub li a.current { outline: 3px solid #2271b1; outline-offset: 1px; }
		.wrap ul.subsubsub li a:hover { filter: brightness(.96); }
	</style>
	<?php
} );
