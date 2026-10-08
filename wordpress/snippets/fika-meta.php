<?php
/**
 * Fika: Meta Pixel + Conversions API (server events), with matching event IDs so Meta counts each event once.
 * - Settings: WP Admin > WooCommerce > Meta pixel (pixel ID, Conversions API access token, optional test event code,
 *   on/off, skip signed-in shop staff). The token is kept in the database, never in this file.
 * - Events (browser pixel + the same event from the server):
 *     PageView          every shop page (not the admin, not the order-received hop)
 *                       (ID made in the browser, server copy through the same route as the other browser events,
 *                       so it works with page caching)
 *     AddToCart         + on a candy or Ready Mix card, product page "Add to bag" (value, content_ids)
 *     InitiateCheckout  the bag's Checkout button (value, number of items)
 *     Purchase          the order: sent from the server in the background when the order is placed (email, phone,
 *                       name, area hashed as Meta requires), and from the browser on the "Order confirmed" popup,
 *                       both with event ID "purchase.<order number>".
 *   Browser events go through window.fikaTrack(event, data), which adds the event ID and asks the server
 *   (POST /wp-json/fika/v1/meta) to send the same event with the visitor's IP, browser, _fbp/_fbc cookies and,
 *   for signed-in customers, their hashed email and phone.
 * - "Send a test event" on the settings screen checks the token (shows Meta's answer); with a test event code the
 *   server events appear under Events Manager > Test events only.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-meta.php
 */

if ( ! function_exists( 'fika_meta_set' ) ) {
	function fika_meta_set() {
		$s = get_option( 'fika_meta', array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array( 'pixel' => '', 'token' => '', 'test' => '', 'on' => 0, 'skip_staff' => 1, 'api' => 'v24.0' ) );
	}
	// pixel is on for this visitor
	function fika_meta_active() {
		$s = fika_meta_set();
		if ( empty( $s['on'] ) || ! preg_match( '/^\d{6,20}$/', $s['pixel'] ) ) {
			return false;
		}
		if ( ! empty( $s['skip_staff'] ) && is_user_logged_in() && current_user_can( 'edit_shop_orders' ) ) {
			return false;
		}
		return true;
	}
	function fika_meta_hash( $v ) {
		$v = strtolower( trim( (string) $v ) );
		return '' === $v ? null : hash( 'sha256', $v );
	}
	// Lebanese numbers as Meta wants them: digits with the country code (961), no leading 0
	function fika_meta_phone( $p ) {
		$d = preg_replace( '/\D+/', '', (string) $p );
		if ( '' === $d ) {
			return '';
		}
		$d = preg_replace( '/^00/', '', $d );
		if ( 0 !== strpos( $d, '961' ) ) {
			$d = '961' . ltrim( $d, '0' );
		}
		return $d;
	}
	function fika_meta_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = trim( explode( ',', wp_unslash( $_SERVER[ $k ] ) )[0] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
	// what we know about the visitor making this request (browser, cookies, signed-in customer)
	function fika_meta_request_user() {
		$u = array(
			'client_ip_address' => fika_meta_ip(),
			'client_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 400 ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		);
		foreach ( array( '_fbp' => 'fbp', '_fbc' => 'fbc' ) as $c => $k ) {
			if ( ! empty( $_COOKIE[ $c ] ) ) {
				$u[ $k ] = sanitize_text_field( wp_unslash( $_COOKIE[ $c ] ) );
			}
		}
		if ( is_user_logged_in() ) {
			$me = wp_get_current_user();
			$u['em']          = array( fika_meta_hash( $me->user_email ) );
			$u['external_id'] = array( fika_meta_hash( 'wp' . $me->ID ) );
			$ph               = fika_meta_phone( get_user_meta( $me->ID, 'billing_phone', true ) );
			if ( $ph ) {
				$u['ph'] = array( fika_meta_hash( $ph ) );
			}
			if ( $me->first_name ) {
				$u['fn'] = array( fika_meta_hash( $me->first_name ) );
			}
		}
		return array_filter( $u );
	}
	// send events to the Conversions API; $wait false = do not hold up the page
	function fika_meta_send( $events, $wait = false ) {
		$s = fika_meta_set();
		if ( empty( $s['on'] ) || '' === $s['token'] || ! preg_match( '/^\d{6,20}$/', $s['pixel'] ) ) {
			return new WP_Error( 'fika_meta_off', 'Pixel ID or access token missing, or the pixel is switched off.' );
		}
		$body = array( 'data' => array_values( $events ) );
		if ( '' !== $s['test'] ) {
			$body['test_event_code'] = $s['test'];
		}
		$url = 'https://graph.facebook.com/' . rawurlencode( $s['api'] ) . '/' . $s['pixel'] . '/events?access_token=' . rawurlencode( $s['token'] );
		return wp_remote_post( $url, array( 'timeout' => $wait ? 10 : 1, 'blocking' => (bool) $wait, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
	}
	function fika_meta_event( $name, $id, $data, $user, $url ) {
		return array_filter( array(
			'event_name'       => $name,
			'event_time'       => time(),
			'event_id'         => $id,
			'action_source'    => 'website',
			'event_source_url' => $url,
			'user_data'        => $user,
			'custom_data'      => $data ? $data : null,
		) );
	}
}

// ---------- browser pixel ----------
add_action( 'wp_head', function () {
	if ( is_admin() || ! fika_meta_active() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
		return;
	}
	$s   = fika_meta_set();
	$am  = array();
	if ( is_user_logged_in() ) {
		$me = wp_get_current_user();
		$am = array_filter( array( 'em' => strtolower( $me->user_email ), 'ph' => fika_meta_phone( get_user_meta( $me->ID, 'billing_phone', true ) ), 'external_id' => 'wp' . $me->ID ) );
	}
	?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?php echo wp_json_encode( $s['pixel'] ); ?>, <?php echo wp_json_encode( (object) $am ); ?>);
// browser event + the same event from the server (same event ID, so Meta counts it once)
window.fikaTrack = function (ev, data, id) {
  data = data || {};
  id = id || (ev.toLowerCase() + '.' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8));
  try { fbq('track', ev, data, { eventID: id }); } catch (e) {}
  if (ev === 'Purchase') return; // the server already sent it when the order was placed
  try {
    var body = JSON.stringify({ ev: ev, id: id, data: data, url: location.href });
    if (navigator.sendBeacon) navigator.sendBeacon(<?php echo wp_json_encode( rest_url( 'fika/v1/meta' ) ); ?>, new Blob([body], { type: 'text/plain' }));
  } catch (e) {}
};
// PageView: the event ID is made here, not in the page, so it stays unique when the page is served from the cache
fikaTrack('PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $s['pixel'] ); ?>&amp;ev=PageView&amp;noscript=1"></noscript>
	<?php
}, 1 );

// ---------- the server copy of browser events ----------
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/meta', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			if ( ! fika_meta_active() ) {
				return new WP_REST_Response( null, 204 );
			}
			$in = json_decode( $req->get_body(), true );
			if ( ! is_array( $in ) || empty( $in['ev'] ) || ! in_array( $in['ev'], array( 'PageView', 'AddToCart', 'InitiateCheckout', 'ViewContent' ), true ) ) {
				return new WP_REST_Response( null, 204 );
			}
			// a little limit per visitor (60 events a minute)
			$k = 'fika_meta_' . md5( fika_meta_ip() );
			$n = (int) get_transient( $k );
			if ( $n > 60 ) {
				return new WP_REST_Response( null, 204 );
			}
			set_transient( $k, $n + 1, MINUTE_IN_SECONDS );
			$d    = is_array( $in['data'] ?? null ) ? $in['data'] : array();
			$data = array_filter( array(
				'currency'     => 'USD',
				'value'        => isset( $d['value'] ) ? round( (float) $d['value'], 2 ) : null,
				'content_name' => isset( $d['content_name'] ) ? substr( sanitize_text_field( $d['content_name'] ), 0, 100 ) : null,
				'content_ids'  => isset( $d['content_ids'] ) && is_array( $d['content_ids'] ) ? array_slice( array_map( 'strval', array_map( 'absint', $d['content_ids'] ) ), 0, 50 ) : null,
				'content_type' => isset( $d['content_ids'] ) ? 'product' : null,
				'num_items'    => isset( $d['num_items'] ) ? absint( $d['num_items'] ) : null,
			), function ( $v ) { return null !== $v; } );
			$url = isset( $in['url'] ) ? esc_url_raw( $in['url'] ) : home_url( '/' );
			if ( 0 !== strpos( $url, home_url() ) ) {
				$url = home_url( '/' );
			}
			$id = substr( preg_replace( '/[^a-z0-9._-]/i', '', (string) ( $in['id'] ?? '' ) ), 0, 80 );
			fika_meta_send( array( fika_meta_event( $in['ev'], $id, $data, fika_meta_request_user(), $url ) ) );
			return new WP_REST_Response( null, 204 );
		},
	) );
} );

// ---------- Purchase from the server ----------
// When the order is placed (the shopper's own request): keep their browser details on the order, then send in the
// background so the checkout is not slowed down.
add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
	if ( ! fika_meta_active() || $order->get_meta( '_fika_meta_purchase' ) ) {
		return;
	}
	$order->update_meta_data( '_fika_meta_user', fika_meta_request_user() );
	$order->update_meta_data( '_fika_meta_url', wc_get_checkout_url() );
	$order->save();
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'fika_meta_purchase', array( $order->get_id() ), 'fika' );
	}
} );
add_action( 'fika_meta_purchase', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_fika_meta_purchase' ) ) {
		return;
	}
	$u = (array) $order->get_meta( '_fika_meta_user' );
	unset( $u['em'], $u['ph'], $u['fn'] ); // use the order's own details
	$u['em'] = array( fika_meta_hash( $order->get_billing_email() ) );
	$ph      = fika_meta_phone( $order->get_billing_phone() );
	if ( $ph ) {
		$u['ph'] = array( fika_meta_hash( $ph ) );
	}
	foreach ( array( 'fn' => $order->get_shipping_first_name(), 'ln' => $order->get_shipping_last_name(), 'ct' => preg_replace( '/\s+/', '', (string) $order->get_shipping_city() ) ) as $k => $v ) {
		if ( '' !== (string) $v ) {
			$u[ $k ] = array( fika_meta_hash( $v ) );
		}
	}
	$u['country'] = array( fika_meta_hash( 'lb' ) );
	if ( empty( $u['external_id'] ) ) {
		$u['external_id'] = array( fika_meta_hash( $order->get_customer_id() ? 'wp' . $order->get_customer_id() : $order->get_billing_email() ) );
	}
	$ids = array();
	foreach ( $order->get_items() as $it ) {
		$ids[] = array( 'id' => (string) $it->get_product_id(), 'quantity' => (int) $it->get_quantity() );
	}
	$data = array( 'currency' => $order->get_currency(), 'value' => (float) $order->get_total(), 'order_id' => (string) $order->get_order_number(),
		'contents' => $ids, 'content_ids' => wp_list_pluck( $ids, 'id' ), 'content_type' => 'product', 'num_items' => count( $ids ) );
	$r = fika_meta_send( array( fika_meta_event( 'Purchase', 'purchase.' . $order->get_id(), $data, array_filter( $u ), (string) $order->get_meta( '_fika_meta_url' ) ) ), true );
	$ok = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
	$order->update_meta_data( '_fika_meta_purchase', $ok ? time() : 0 );
	$order->add_order_note( $ok ? 'Meta: purchase sent to the Conversions API.' : 'Meta: purchase not sent (' . ( is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_body( $r ) ) . ').' );
	$order->save();
} );

// ---------- settings screen ----------
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Meta pixel', 'Meta pixel', 'manage_woocommerce', 'fika-meta', 'fika_meta_screen' );
}, 60 );
function fika_meta_screen() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$msg = '';
	if ( isset( $_POST['fika_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fika_meta_nonce'] ) ), 'fika_meta' ) ) {
		$s           = fika_meta_set();
		$s['pixel']  = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['pixel'] ?? '' ) ) );
		$tok         = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
		if ( '' !== $tok ) {
			$s['token'] = $tok;
		}
		if ( ! empty( $_POST['forget_token'] ) ) {
			$s['token'] = '';
		}
		$s['test']       = preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['test'] ?? '' ) ) );
		$s['on']         = empty( $_POST['on'] ) ? 0 : 1;
		$s['skip_staff'] = empty( $_POST['skip_staff'] ) ? 0 : 1;
		update_option( 'fika_meta', $s, false );
		$msg = 'Saved.';
		if ( ! empty( $_POST['send_test'] ) ) {
			$r   = fika_meta_send( array( fika_meta_event( 'PageView', 'test.' . time(), null, fika_meta_request_user(), home_url( '/' ) ) ), true );
			$msg = is_wp_error( $r ) ? 'Test not sent: ' . $r->get_error_message() : 'Meta answered (' . wp_remote_retrieve_response_code( $r ) . '): ' . wp_remote_retrieve_body( $r );
		}
	}
	$s = fika_meta_set();
	?>
<div class="wrap">
	<h1>Meta pixel and Conversions API</h1>
	<?php if ( $msg ) : ?><div class="notice notice-info"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
	<form method="post">
		<?php wp_nonce_field( 'fika_meta', 'fika_meta_nonce' ); ?>
		<table class="form-table">
			<tr><th>On</th><td><label><input type="checkbox" name="on" value="1" <?php checked( $s['on'] ); ?>> Send events to Meta</label></td></tr>
			<tr><th><label for="fm-pixel">Pixel ID</label></th><td><input id="fm-pixel" name="pixel" class="regular-text" value="<?php echo esc_attr( $s['pixel'] ); ?>"><p class="description">Events Manager &gt; your dataset (pixel) &gt; Settings: the number at the top.</p></td></tr>
			<tr><th><label for="fm-token">Conversions API access token</label></th><td><input id="fm-token" name="token" type="password" class="regular-text" autocomplete="off" placeholder="<?php echo $s['token'] ? 'saved (leave empty to keep)' : 'paste the token'; ?>">
				<?php if ( $s['token'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="forget_token" value="1"> forget the saved token</label><?php endif; ?>
				<p class="description">Events Manager &gt; your dataset &gt; Settings &gt; Conversions API &gt; Generate access token.</p></td></tr>
			<tr><th><label for="fm-test">Test event code</label></th><td><input id="fm-test" name="test" class="regular-text" value="<?php echo esc_attr( $s['test'] ); ?>"><p class="description">Optional (e.g. TEST12345, from Events Manager &gt; Test events). While set, server events show under Test events only. Empty it at launch.</p></td></tr>
			<tr><th>Shop staff</th><td><label><input type="checkbox" name="skip_staff" value="1" <?php checked( $s['skip_staff'] ); ?>> Do not track signed-in shop managers and admins</label></td></tr>
		</table>
		<p><button class="button button-primary">Save</button> <button class="button" name="send_test" value="1">Save and send a test event</button></p>
	</form>
</div>
	<?php
}
