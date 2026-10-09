<?php
/**
 * Fika Club: confirm the email with a 6-digit code at checkout, then a welcome popup with the rewards to use now.
 * - Checkout, "Create a Fika Club account" ticked: a box under it sends a code to the email (POST
 *   /fika/v1/club-code). Typing it (POST /fika/v1/club-verify) creates the account already confirmed, with the
 *   password and details typed so far, joins every earlier order with that email (old-store imports included), logs
 *   the customer in and reloads the checkout with the welcome popup. "Resend code" waits 60 s; "Skip, confirm later
 *   by email" keeps today's way (account made with the order, confirmation link by email).
 * - Place order with the box ticked and neither confirmed nor skipped: the click is held and the code box is shown.
 * - Welcome popup (checkout ?fika_club=welcome, GET /fika/v1/club-welcome): the lane (delivered + on the way + this
 *   bag), "Spin now" for each mystery spin reached (fika-taste.php, POST /fika/v1/taste-spin) and "Use now" for each
 *   kilo reward reached (fika-loyalty.php, POST /fika/v1/swim-claim, then applied to the bag). A note says when a
 *   reward replaces the 10% offer (FIKA10).
 * - Codes: 6 digits, 15 minutes, 5 tries; one email per 60 s and 5 per hour per address, 15 per hour per visitor.
 *   Stored hashed (transient fika_club_<md5 email>).
 * - Switch: option fika_club_on (1 = everyone). While off, only browsers that opened the preview link (cookie
 *   fika_club_preview) see it. GET/POST /fika/v1/club-status (admins) shows the link and switches it.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-club.php
 */

if ( ! function_exists( 'fika_club_on' ) ) {
	function fika_club_preview_key() {
		return substr( wp_hash( 'fika-club-preview' ), 0, 16 );
	}
	// on for everyone, or for a browser that opened the preview link
	function fika_club_on() {
		if ( get_option( 'fika_club_on' ) ) {
			return true;
		}
		return isset( $_COOKIE['fika_club_preview'] ) ? hash_equals( fika_club_preview_key(), (string) $_COOKIE['fika_club_preview'] ) : false;
	}
	function fika_club_key( $email ) {
		return 'fika_club_' . md5( strtolower( trim( (string) $email ) ) );
	}
	function fika_club_hash( $email, $code ) {
		return hash_hmac( 'sha256', strtolower( trim( (string) $email ) ) . '|' . $code, wp_salt( 'auth' ) );
	}
	function fika_club_ip_ok() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$k  = 'fika_club_ip_' . md5( $ip );
		$n  = (int) get_transient( $k );
		if ( $n >= 15 ) {
			return false;
		}
		set_transient( $k, $n + 1, HOUR_IN_SECONDS );
		return true;
	}
	// the email with the code (WooCommerce's email look and sender)
	function fika_club_send_code( $email, $code ) {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		$mailer = WC()->mailer();
		$body   = '<p>Here is your Fika Club code:</p>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:22px auto;"><tr><td align="center" style="padding:16px 28px;border-radius:16px;background:#fdeaf2;font-family:Helvetica,Arial,sans-serif;font-size:34px;font-weight:700;letter-spacing:10px;color:#004aad;">' . esc_html( $code ) . '</td></tr></table>'
			. '<p>Type it at the checkout to join Fika Club. Your earlier orders join your account straight away, and every kilo moves your fish towards free sweets.</p>'
			. '<p>The code works for 15 minutes. Didn&rsquo;t ask for it? You can safely ignore this email.</p>';
		return $mailer->send( $email, 'Your Fika Club code: ' . $code, $mailer->wrap_message( 'Welcome to Fika Club!', $body ) );
	}
	// what the welcome popup shows for a customer
	function fika_club_state( $uid ) {
		$t      = fika_swim_totals( $uid );
		$bag    = fika_swim_cart_grams();
		$reach  = $t['delivered'] + $t['placed'] + $bag;
		$cps    = fika_swim_checkpoints();
		$tastes = function_exists( 'fika_taste_list' ) ? fika_taste_list( $uid ) : array();
		$spins  = array();
		$next   = null;
		$stops  = function_exists( 'fika_taste_stops' ) ? fika_taste_stops() : array();
		for ( $lap = 1; fika_swim_need( $lap, 0 ) <= $reach; $lap++ ) {
			foreach ( $stops as $g ) {
				$need = fika_swim_need( $lap, $g );
				if ( $need <= $reach ? ! isset( $tastes[ $lap . ':' . $g ] ) : false ) {
					$spins[] = array( 'lap' => $lap, 'g' => $g, 'need' => $need );
				}
			}
		}
		$rewards = array();
		foreach ( fika_swim_unclaimed( $uid, $reach ) as $u ) {
			$cp        = $cps[ $u[1] ];
			$rewards[] = array( 'lap' => $u[0], 'g' => $u[1], 'need' => $u[2], 'title' => $cp['title'], 'co' => $cp['co'], 'amount' => $cp['amount'] );
		}
		$open = array();
		foreach ( fika_swim_open_codes( $uid ) as $o ) {
			$open[] = array( 'code' => $o[0], 'title' => $o[1], 'co' => $o[3], 'amount' => $o[4], 'need' => $o[5] );
		}
		// the next stop on the lane: a spin or a kilo reward
		$lap  = (int) floor( $reach / FIKA_SWIM_LAP ) + 1;
		$cand = array();
		foreach ( array( $lap, $lap + 1 ) as $l ) {
			foreach ( $stops as $g ) {
				$cand[ fika_swim_need( $l, $g ) ] = 'a mystery spin';
			}
			foreach ( $cps as $g => $cp ) {
				$cand[ fika_swim_need( $l, $g ) ] = $cp['title'];
			}
		}
		ksort( $cand );
		foreach ( $cand as $need => $title ) {
			if ( $need > $reach ) {
				$next = array( 'need' => $need, 'title' => $title, 'g' => $need - ( $lap - 1 ) * FIKA_SWIM_LAP );
				break;
			}
		}
		$u = get_userdata( $uid );
		return array(
			'name'      => $u ? ( $u->first_name ? $u->first_name : '' ) : '',
			'joined'    => (int) get_user_meta( $uid, 'fika_club_joined_n', true ),
			'delivered' => (int) $t['delivered'],
			'placed'    => (int) $t['placed'],
			'bag'       => (int) $bag,
			'lap'       => $lap,
			'goal'      => FIKA_SWIM_LAP,
			'stops'     => array_merge( $stops, array_keys( $cps ) ),
			'spins'     => $spins,
			'rewards'   => $rewards,
			'open'      => $open,
			'next'      => $next,
		);
	}
}

// the preview link: /?fika-club-preview=KEY turns it on for this browser (30 days); ?fika-club-preview=off turns it off
add_action( 'init', function () {
	if ( ! isset( $_GET['fika-club-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$v = sanitize_text_field( wp_unslash( $_GET['fika-club-preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'off' === $v ) {
		setcookie( 'fika_club_preview', '', time() - 3600, '/', '', is_ssl(), true );
	} elseif ( hash_equals( fika_club_preview_key(), $v ) ) {
		setcookie( 'fika_club_preview', $v, array( 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	} else {
		return;
	}
	nocache_headers();
	wp_safe_redirect( remove_query_arg( 'fika-club-preview' ) );
	exit;
}, 1 );

// a new account confirmed with the code: no confirmation-link email, and it counts as confirmed from the start
add_action( 'woocommerce_created_customer', function ( $customer_id ) {
	if ( ! empty( $GLOBALS['fika_club_verified'] ) ) {
		$u = get_userdata( $customer_id );
		if ( $u ? strtolower( $u->user_email ) === $GLOBALS['fika_club_verified'] : false ) {
			update_user_meta( $customer_id, 'fika_email_ok', strtolower( $u->user_email ) );
		}
	}
}, 1 );

add_action( 'rest_api_init', function () {
	// send a code: { email }
	register_rest_route( 'fika/v1', '/club-code', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			if ( ! fika_club_on() ) {
				return new WP_REST_Response( array( 'error' => 'Not available.' ), 404 );
			}
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			if ( ! is_email( $email ) ) {
				return new WP_REST_Response( array( 'error' => 'Please type a valid email address first.' ), 400 );
			}
			if ( email_exists( $email ) ) {
				return array( 'exists' => true );
			}
			$k   = fika_club_key( $email );
			$rec = get_transient( $k );
			$rec = is_array( $rec ) ? $rec : array( 'sent' => array() );
			$now = time();
			$rec['sent'] = array_values( array_filter( (array) $rec['sent'], function ( $t ) use ( $now ) { return $t > $now - HOUR_IN_SECONDS; } ) );
			if ( $rec['sent'] ? end( $rec['sent'] ) > $now - 60 : false ) {
				return new WP_REST_Response( array( 'error' => 'We just sent a code. You can ask for a new one in a minute.', 'wait' => 60 - ( $now - end( $rec['sent'] ) ) ), 429 );
			}
			if ( count( $rec['sent'] ) >= 5 ? true : ! fika_club_ip_ok() ) {
				return new WP_REST_Response( array( 'error' => 'Too many codes asked for. Please try again in an hour, or tap Skip.' ), 429 );
			}
			$code          = (string) random_int( 100000, 999999 );
			$rec['hash']   = fika_club_hash( $email, $code );
			$rec['exp']    = $now + 15 * MINUTE_IN_SECONDS;
			$rec['tries']  = 0;
			$rec['sent'][] = $now;
			set_transient( $k, $rec, HOUR_IN_SECONDS );
			$ok = fika_club_send_code( $email, $code );
			return array( 'sent' => (bool) $ok );
		},
	) );

	// check the code and create the account: { email, code, password, first, last, phone, address }
	register_rest_route( 'fika/v1', '/club-verify', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			if ( ! fika_club_on() ) {
				return new WP_REST_Response( array( 'error' => 'Not available.' ), 404 );
			}
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			$code  = preg_replace( '/\D+/', '', (string) $req->get_param( 'code' ) );
			$k     = fika_club_key( $email );
			$rec   = get_transient( $k );
			if ( ! is_email( $email ) ? true : ! is_array( $rec ) || empty( $rec['hash'] ) ) {
				return new WP_REST_Response( array( 'error' => 'Please ask for a code first.' ), 400 );
			}
			if ( (int) $rec['exp'] < time() ? true : (int) $rec['tries'] >= 5 ) {
				return new WP_REST_Response( array( 'error' => 'This code has expired. Tap "Resend code" for a new one.' ), 400 );
			}
			if ( ! hash_equals( $rec['hash'], fika_club_hash( $email, $code ) ) ) {
				$rec['tries'] = (int) $rec['tries'] + 1;
				set_transient( $k, $rec, HOUR_IN_SECONDS );
				return new WP_REST_Response( array( 'error' => 5 - $rec['tries'] > 0 ? 'That code isn’t right. Check the email and try again.' : 'Too many tries. Tap "Resend code" for a new one.' ), 400 );
			}
			if ( email_exists( $email ) ) {
				delete_transient( $k );
				return array( 'exists' => true );
			}
			$pw = (string) $req->get_param( 'password' );
			if ( strlen( $pw ) < 6 ) {
				return new WP_REST_Response( array( 'error' => 'Choose a password of at least 6 characters above, then tap Confirm again.', 'password' => true ), 400 );
			}
			$first = sanitize_text_field( (string) $req->get_param( 'first' ) );
			$last  = sanitize_text_field( (string) $req->get_param( 'last' ) );
			$GLOBALS['fika_club_verified'] = strtolower( $email );
			$uid = wc_create_new_customer( $email, '', $pw, array( 'first_name' => $first, 'last_name' => $last ) );
			unset( $GLOBALS['fika_club_verified'] );
			if ( is_wp_error( $uid ) ) {
				return new WP_REST_Response( array( 'error' => wp_strip_all_tags( $uid->get_error_message() ) ), 400 );
			}
			delete_transient( $k );
			if ( $first ) {
				wp_update_user( array( 'ID' => $uid, 'display_name' => $first ) );
			}
			// what was typed at checkout so far becomes the account's details
			$addr  = (array) $req->get_param( 'address' );
			$allow = array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' );
			foreach ( array( 'billing', 'shipping' ) as $type ) {
				$a = isset( $addr[ $type ] ) ? (array) $addr[ $type ] : array();
				foreach ( $allow as $f ) {
					if ( isset( $a[ $f ] ) ? '' !== (string) $a[ $f ] : false ) {
						update_user_meta( $uid, $type . '_' . $f, sanitize_text_field( (string) $a[ $f ] ) );
					}
				}
			}
			$phone = sanitize_text_field( (string) $req->get_param( 'phone' ) );
			if ( $phone ) {
				update_user_meta( $uid, 'billing_phone', $phone );
				update_user_meta( $uid, 'shipping_phone', $phone );
			}
			update_user_meta( $uid, 'billing_email', $email );
			$n = fika_link_past_orders( $uid ); // confirms the email, joins earlier orders, updates totals and rewards
			update_user_meta( $uid, 'fika_club_joined_n', (int) $n );
			update_user_meta( $uid, 'fika_club_joined', time() );
			wc_set_customer_auth_cookie( $uid );
			return array( 'ok' => true, 'joined' => (int) $n, 'name' => $first );
		},
	) );

	// the welcome popup's numbers (signed-in customer)
	register_rest_route( 'fika/v1', '/club-welcome', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => function () {
			return fika_club_state( get_current_user_id() );
		},
	) );

	// admins: preview link and on/off
	register_rest_route( 'fika/v1', '/club-status', array(
		'methods'             => array( 'GET', 'POST' ),
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( $req ) {
			if ( 'POST' === $req->get_method() ? null !== $req->get_param( 'on' ) : false ) {
				update_option( 'fika_club_on', (int) (bool) $req->get_param( 'on' ), true );
				do_action( 'litespeed_purge_all', 'Fika Club switched' );
			}
			return array( 'on' => (bool) get_option( 'fika_club_on' ), 'preview' => add_query_arg( 'fika-club-preview', fika_club_preview_key(), wc_get_checkout_url() ) );
		},
	) );
} );

// ---------- checkout: the code box (guests) and the welcome popup (signed in) ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || ! fika_club_on() ) {
		return;
	}
	$cfg = array(
		'in'      => is_user_logged_in(),
		'welcome' => is_user_logged_in() ? isset( $_GET['fika_club'] ) : false, // phpcs:ignore WordPress.Security.NonceVerification
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'api'     => esc_url_raw( rest_url( 'fika/v1/' ) ),
		'login'   => add_query_arg( 'redirect_to', rawurlencode( wc_get_checkout_url() ), wc_get_page_permalink( 'myaccount' ) ),
		'back'    => wc_get_checkout_url(),
	);
	echo '<script>window.FIKA_CLUB = ' . wp_json_encode( $cfg ) . ';</script>';
	echo <<<'FIKA_CLUB'
<style>
.fkc-box { margin: 10px 0 16px; padding: 16px; border: 2px solid #004aad; border-radius: 18px; background: #fff; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; display: grid; gap: 10px; text-align: center; }
.fkc-box[hidden] { display: none; }
.fkc-box p { margin: 0; font-size: 14.5px; line-height: 1.45; }
.fkc-box p b { color: #004aad; word-break: break-word; }
.fkc-code { width: 100%; max-width: 280px; justify-self: center; height: 56px; padding: 0 12px; border: 2px solid #cfd6e6; border-radius: 14px; background: #fff; color: #1b2a4a; font: 600 28px/1 'Outfit', Arial, sans-serif; letter-spacing: .45em; text-align: center; font-variant-numeric: tabular-nums; box-sizing: border-box; }
.fkc-code:focus { outline: none; border-color: #004aad; box-shadow: 0 0 0 4px rgba(0, 74, 173, .15); }
.fkc-go { display: block; width: 100%; padding: 14px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 700 14px/1.2 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fkc-go:hover { background: #003a8a; }
.fkc-go[disabled] { opacity: .6; cursor: default; }
.fkc-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; font-size: 13.5px; }
.fkc-links button, .fkc-links a { padding: 4px 2px; border: 0; background: none; color: #6c7b9c; font: inherit; text-decoration: underline; cursor: pointer; }
.fkc-links button[disabled] { opacity: .55; cursor: default; text-decoration: none; }
.fkc-msg { min-height: 0; font-size: 13.5px; color: #b0234f; }
.fkc-msg:empty { display: none; }
.fkc-msg.ok { color: #1f8a4c; }
.fkc-box.is-ok { border-color: #1f8a4c; background: #e7f6ee; }
.fkc-box.is-hold { animation: fkcShake .45s; box-shadow: 0 0 0 4px rgba(226, 64, 111, .25); }
@keyframes fkcShake { 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }

.fkc-veil { position: fixed; inset: 0; z-index: 200001; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(253, 234, 242, .8);
  -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); opacity: 0; transition: opacity .25s; }
.fkc-veil.on { opacity: 1; }
.fkc-card { position: relative; width: 100%; max-width: 440px; max-height: calc(100vh - 32px); overflow: auto; background: #fff; border-radius: 26px; box-shadow: 0 24px 60px rgba(0, 74, 173, .22);
  padding: 28px 22px 20px; text-align: center; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; display: grid; gap: 12px; box-sizing: border-box; }
.fkc-card:focus { outline: none; }
.fkc-x { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: #fdeaf2; color: #004aad; font-size: 22px; line-height: 1; cursor: pointer; }
.fkc-tag { justify-self: center; padding: 4px 12px; border-radius: 999px; background: #004aad; color: #fff; font: 400 15px 'Bebas Neue', Impact, sans-serif; letter-spacing: .1em; }
.fkc-h { margin: 0; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 30px; line-height: 1.05; color: #004aad; }
.fkc-p { margin: 0; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 17px; line-height: 1.3; }
.fkc-lane { position: relative; height: 18px; border-radius: 999px; background: #eef1f8; margin: 30px 8px 22px; }
.fkc-lane .d { position: absolute; top: 0; bottom: 0; left: 0; border-radius: 999px; background: #004aad; }
.fkc-lane .b { position: absolute; top: 0; bottom: 0; border-radius: 0 999px 999px 0; background: repeating-linear-gradient(45deg, #ff6fa5 0 6px, #ff9cc2 6px 12px); }
.fkc-lane .s { position: absolute; top: 50%; width: 11px; height: 11px; margin: -5.5px 0 0 -5.5px; border-radius: 50%; background: #fff; border: 2px solid #ff6fa5; box-sizing: border-box; }
.fkc-lane .s.got { background: #ff6fa5; }
.fkc-lane .t { position: absolute; top: 100%; margin-top: 5px; transform: translateX(-50%); font-size: 11px; color: #6c7b9c; font-variant-numeric: tabular-nums; white-space: nowrap; }
.fkc-lane .f { position: absolute; top: -24px; width: 38px; height: 38px; margin-left: -19px; transition: left 1.4s cubic-bezier(.3, 1, .4, 1); }
.fkc-lane .f svg { width: 100%; height: 100%; display: block; overflow: visible; filter: drop-shadow(0 3px 3px rgba(0, 40, 120, .25)); }
.fkc-sum { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; font-size: 13.5px; }
.fkc-sum span { padding: 3px 10px; border-radius: 999px; background: #f2f4f9; }
.fkc-sum .dl { background: #e3ecfb; color: #004aad; }
.fkc-sum .bg { background: #ffe3ee; color: #a3245a; }
.fkc-rew { display: grid; grid-template-columns: 46px 1fr auto; gap: 10px; align-items: center; text-align: left; padding: 10px 12px; border-radius: 16px; background: #fdeaf2; }
.fkc-rew .ic { width: 46px; height: 46px; border-radius: 50%; background: #004aad; color: #fff; display: grid; place-items: center; font: 400 15px/1 'Bebas Neue', Impact, sans-serif; text-align: center; }
.fkc-rew .ic.sp { background: conic-gradient(#ff6fa5 0 25%, #004aad 0 50%, #ffd23f 0 75%, #36c08b 0); border: 3px solid #fff; box-shadow: 0 0 0 2px #004aad; box-sizing: border-box; }
.fkc-rew .ic.sp.go { animation: fkcSpin .9s cubic-bezier(.3, .1, .3, 1) infinite; }
@keyframes fkcSpin { to { transform: rotate(360deg); } }
.fkc-rew p { margin: 0; font-size: 13.5px; line-height: 1.35; min-width: 0; }
.fkc-rew p b { display: block; font-size: 14.5px; color: #004aad; }
.fkc-rew button { padding: 10px 14px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 700 12.5px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; cursor: pointer; }
.fkc-rew button[disabled] { background: #1f8a4c; cursor: default; }
.fkc-next { margin: 0; font-size: 13.5px; color: #6c7b9c; }
.fkc-next b { color: #1b2a4a; }
.fkc-note { margin: 0; padding: 9px 12px; border-radius: 12px; background: #fff7e0; color: #6b4a00; font-size: 12.5px; line-height: 1.4; text-align: left; }
.fkc-note:empty { display: none; }
.fkc-won { display: grid; justify-items: center; gap: 8px; }
.fkc-won img { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 5px solid #ffd23f; box-shadow: 0 8px 22px rgba(0, 74, 173, .2); }
.fkc-btn { display: block; width: 100%; padding: 15px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 700 14px/1.2 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fkc-btn.ghost { background: #fff; color: #004aad; border: 2px solid #004aad; }
.fkc-err { margin: 0; font-size: 13px; color: #b0234f; }
.fkc-err:empty { display: none; }
@media (max-width: 420px) { .fkc-card { padding: 26px 16px 16px; } .fkc-h { font-size: 26px; } .fkc-rew { grid-template-columns: 40px 1fr; } .fkc-rew .ic { width: 40px; height: 40px; } .fkc-rew button { grid-column: 1 / -1; width: 100%; padding: 12px; } }
@media (prefers-reduced-motion: reduce) { .fkc-lane .f { transition: none; } .fkc-rew .ic.sp.go { animation: none; } .fkc-box.is-hold { animation: none; } }
</style>
<script>
(function () {
  var C = window.FIKA_CLUB || {};
  // the checkout's account box says "Create a Fika Club account"
  try { wp.hooks.addFilter('i18n.gettext', 'fika/club-label', function (t, text) { return text === 'Create an account with %s' ? 'Create a Fika Club account' : t; }); } catch (e) {}
  var FISH = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/><path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="#ff5a2f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/><ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/><circle cx="24" cy="46" r="5.2" fill="#fff"/><circle cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/></svg>';
  function api(path, opt) {
    opt = opt || {};
    var h = { 'Content-Type': 'application/json' };
    if (C.in) h['X-WP-Nonce'] = C.nonce;
    return fetch(C.api + path, { method: opt.method || 'GET', credentials: 'same-origin', headers: h, body: opt.body ? JSON.stringify(opt.body) : undefined })
      .then(function (r) { return r.json().then(function (j) { j = j || {}; j._status = r.status; return j; }, function () { return { _status: r.status }; }); });
  }
  function kg(g) { var v = Math.round(g / 100) / 10; return (v % 1 ? v.toFixed(1) : v) + ' kg'; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function cart() { try { return wp.data.select('wc/store/cart').getCartData(); } catch (e) { return null; } }
  function customer() { try { return wp.data.select('wc/store/cart').getCustomerData(); } catch (e) { return {}; } }

  // ---------- guests: the code box under "Create a Fika Club account" ----------
  if (!C.in) {
    var box = null, state = { sent: false, ok: false, skip: false, email: '', cool: 0 }, timer = null;
    function tick() {
      var lab = [].slice.call(document.querySelectorAll('.wc-block-checkout__create-account label'))[0];
      return lab ? lab.querySelector('input[type=checkbox]') : null;
    }
    function emailVal() { var e = document.getElementById('email'); return e ? e.value.trim() : ''; }
    function render() {
      if (!box) return;
      var em = emailVal();
      if (state.ok) {
        box.className = 'fkc-box is-ok';
        box.innerHTML = '<p class="fkc-msg ok" style="font-size:15px"><b style="color:#145c33">Email confirmed.</b> Welcome to Fika Club! Loading your rewards…</p>';
        return;
      }
      box.className = 'fkc-box';
      if (!state.sent) {
        box.innerHTML = '<p>To join <b>Fika Club</b>, we email you a 6-digit code. Your earlier orders then join your account straight away.</p>' +
          '<button type="button" class="fkc-go" data-k="send">Email me my code</button><p class="fkc-msg"></p>' +
          '<div class="fkc-links"><button type="button" data-k="skip">Skip, confirm later by email</button></div>';
      } else {
        box.innerHTML = '<p>We’ve emailed a 6-digit code to <b>' + esc(state.email) + '</b>. Type it here to join Fika Club.</p>' +
          '<input class="fkc-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="······" aria-label="6-digit code">' +
          '<button type="button" class="fkc-go" data-k="verify">Confirm my email</button><p class="fkc-msg"></p>' +
          '<div class="fkc-links"><button type="button" data-k="send">Resend code</button><button type="button" data-k="skip">Skip, confirm later by email</button></div>' +
          '<p style="font-size:12.5px;color:#6c7b9c">Can’t see it? Check your junk folder. It can take a minute.</p>';
        var inp = box.querySelector('.fkc-code');
        inp.addEventListener('input', function () { inp.value = inp.value.replace(/\D/g, '').slice(0, 6); if (inp.value.length === 6) verify(); });
        cool();
      }
    }
    function msg(t, ok) { var m = box ? box.querySelector('.fkc-msg') : null; if (m) { m.textContent = t || ''; m.className = 'fkc-msg' + (ok ? ' ok' : ''); } }
    function cool() {
      var b = box ? box.querySelector('[data-k="send"]') : null;
      if (!b) return;
      var left = Math.max(0, Math.ceil((state.cool - Date.now()) / 1000));
      b.disabled = left > 0; b.textContent = left > 0 ? 'Resend code (' + left + ' s)' : 'Resend code';
      clearTimeout(timer); if (left > 0) timer = setTimeout(cool, 1000);
    }
    function send() {
      var em = emailVal();
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) { msg('Type your email address above first.'); var e = document.getElementById('email'); if (e) e.focus(); return; }
      var b = box.querySelector('[data-k="send"]'); if (b) { b.disabled = true; }
      api('club-code', { method: 'POST', body: { email: em } }).then(function (j) {
        if (j.exists) { box.innerHTML = '<p>This email already has a <b>Fika Club</b> account. <a href="' + esc(C.login) + '">Log in</a> to use your rewards, or untick the box to order as a guest.</p>'; return; }
        if (j.sent) { state.sent = true; state.email = em; state.cool = Date.now() + 60000; render(); var i = box.querySelector('.fkc-code'); if (i) i.focus(); msg('Code sent.', true); return; }
        if (j.wait) { state.sent = true; state.email = em; state.cool = Date.now() + j.wait * 1000; render(); }
        if (b) b.disabled = false; msg(j.error || 'We could not send the code. Please try again, or tap Skip.');
      }, function () { if (b) b.disabled = false; msg('No connection. Please try again, or tap Skip.'); });
    }
    function verify() {
      var inp = box.querySelector('.fkc-code'), code = inp ? inp.value : '';
      if (code.length !== 6) { msg('The code has 6 digits.'); return; }
      if (emailVal().toLowerCase() !== state.email.toLowerCase()) { state.sent = false; render(); msg('Your email changed. Tap the button to get a code for the new one.'); return; }
      var pw = document.querySelector('.wc-block-checkout input[type=password]');
      var cd = customer(), sa = cd.shippingAddress || {}, ba = cd.billingAddress || {};
      var ph = document.getElementById('contact-fika-phone');
      var b = box.querySelector('[data-k="verify"]'); b.disabled = true; b.textContent = 'Checking…';
      api('club-verify', { method: 'POST', body: { email: state.email, code: code, password: pw ? pw.value : '', first: sa.first_name || ba.first_name || '', last: sa.last_name || ba.last_name || '', phone: ph ? ph.value : (sa.phone || ba.phone || ''), address: { shipping: sa, billing: ba } } }).then(function (j) {
        if (j.ok) { state.ok = true; render(); setTimeout(function () { var u = new URL(C.back, location.href); u.searchParams.set('fika_club', 'welcome'); location.replace(u.toString()); }, 900); return; }
        if (j.exists) { box.innerHTML = '<p>This email already has a <b>Fika Club</b> account. <a href="' + esc(C.login) + '">Log in</a> to use your rewards.</p>'; return; }
        b.disabled = false; b.textContent = 'Confirm my email'; msg(j.error || 'Something went wrong. Please try again.');
        if (j.password) { var p = document.querySelector('.wc-block-checkout input[type=password]'); if (p) { p.scrollIntoView({ block: 'center', behavior: 'smooth' }); p.focus({ preventScroll: true }); } }
      }, function () { b.disabled = false; b.textContent = 'Confirm my email'; msg('No connection. Please try again.'); });
    }
    function sync() {
      var cb = tick(), wrap = cb ? cb.closest('.wc-block-checkout__create-account') : null;
      if (!wrap) { if (box) box.hidden = true; return; }
      if (!box) {
        box = document.createElement('div'); box.className = 'fkc-box'; box.setAttribute('aria-live', 'polite');
        box.addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (!b) return; var k = b.getAttribute('data-k'); if (k === 'send') send(); else if (k === 'verify') verify(); else if (k === 'skip') { state.skip = true; box.hidden = true; } });
        render();
      }
      // keep the box right under the account box (and the password field WooCommerce adds under it)
      var after = wrap; var pwf = wrap.parentNode.querySelector('.wc-block-components-text-input input[type=password]');
      if (pwf) after = pwf.closest('.wc-block-components-text-input') || wrap;
      if (box.previousElementSibling !== after) after.parentNode.insertBefore(box, after.nextSibling);
      box.hidden = !cb.checked || state.skip;
      if (cb.checked ? false : state.skip) state.skip = false; // unticked: next time, ask again
    }
    setInterval(sync, 600);
    document.addEventListener('change', function (e) { if (e.target ? e.target.closest('.wc-block-checkout__create-account') : false) setTimeout(sync, 30); }, true);
    // "Place order" with the box ticked: confirm (or skip) first
    document.addEventListener('click', function (e) {
      var po = e.target.closest ? e.target.closest('.wc-block-components-checkout-place-order-button') : null;
      if (!po) return;
      var cb = tick();
      if (!cb || !cb.checked || state.ok || state.skip) return;
      e.preventDefault(); e.stopImmediatePropagation();
      sync(); if (!box) return;
      box.scrollIntoView({ behavior: 'smooth', block: 'center' });
      box.classList.remove('is-hold'); void box.offsetWidth; box.classList.add('is-hold');
      msg(state.sent ? 'Type the 6-digit code from your email to join Fika Club, or tap Skip.' : 'Tap "Email me my code" to join Fika Club, or tap Skip.');
    }, true);
    return;
  }

  // ---------- signed in, just joined: the welcome popup ----------
  if (!C.welcome) return;
  try { var cu = new URL(location.href); cu.searchParams.delete('fika_club'); history.replaceState(null, '', cu.toString()); } catch (e) {}
  var veil, card, S;
  function lane(s) {
    var goal = s.goal, base = (s.lap - 1) * goal, d = Math.max(0, Math.min(goal, s.delivered + s.placed - base)), bg = Math.max(0, Math.min(goal - d, s.bag));
    var p = function (g) { return (g / goal * 100).toFixed(2) + '%'; }, h = '<div class="fkc-lane" role="img" aria-label="' + kg(d + bg) + ' of ' + kg(goal) + '">';
    if (d) h += '<span class="d" style="width:' + p(d) + '"></span>';
    if (bg) h += '<span class="b" style="left:' + p(d) + ';width:' + p(bg) + (d ? '' : ';border-radius:999px') + '"></span>';
    s.stops.forEach(function (g) { h += '<span class="s' + (d + bg >= g ? ' got' : '') + '" style="left:' + p(g) + '"></span>'; });
    [0, 3000, 6000, 10000, goal].forEach(function (g) { h += '<span class="t" style="left:' + p(g) + '">' + (g === goal ? kg(g) : g / 1000) + '</span>'; });
    h += '<span class="f" style="left:3%">' + FISH + '</span></div>';
    return { html: h, to: Math.max(3, (d + bg) / goal * 100) };
  }
  function fika10() { var c = cart(), f = c ? (c.coupons || []).filter(function (x) { return (x.code || '').toLowerCase() === 'fika10'; })[0] : null; return f ? (f.totals ? f.totals.total_discount / Math.pow(10, f.totals.currency_minor_unit || 2) : 0) || 0.01 : 0; }
  function money(v) { return '$' + (Math.round(v * 100) / 100).toFixed(2).replace(/\.00$/, ''); }
  function bagGrams() { return S ? S.bag : 0; }
  function close() { veil.classList.remove('on'); setTimeout(function () { veil.remove(); }, 260); }
  function main() {
    var s = S, l = lane(s), name = s.name ? ', ' + esc(s.name) : '';
    var h = '<button type="button" class="fkc-x" data-k="close" aria-label="Close">×</button><span class="fkc-tag">FIKA CLUB</span>' +
      '<h2 class="fkc-h" id="fkcTitle">Welcome to Fika Club' + name + '!</h2>' +
      '<p class="fkc-p">' + (s.joined ? 'We found ' + s.joined + ' earlier order' + (s.joined === 1 ? '' : 's') + ' under your email' : 'Your fish starts swimming with this order') + '</p>' + l.html +
      '<div class="fkc-sum">' + (s.delivered ? '<span class="dl"><b>' + kg(s.delivered) + '</b> delivered</span>' : '') + (s.placed ? '<span class="dl"><b>' + kg(s.placed) + '</b> on the way</span>' : '') +
      (s.bag ? '<span class="bg">+ <b>' + kg(s.bag) + '</b> this bag</span>' : '') + ((s.delivered + s.placed) && s.bag ? '<span>= <b>' + kg(s.delivered + s.placed + s.bag) + '</b></span>' : '') + '</div>';
    s.spins.forEach(function (sp, i) {
      h += '<div class="fkc-rew"><span class="ic sp"></span><p><b>Mystery spin unlocked</b>You passed ' + kg(sp.need) + '. Spin for 50 g of a surprise candy, free in this order.</p><button type="button" data-k="spin" data-i="' + i + '">Spin now</button></div>';
    });
    s.rewards.forEach(function (r, i) {
      h += '<div class="fkc-rew"><span class="ic">' + esc(r.title.replace(/ on us$/i, '').replace(/^a whole kilo$/i, '1 KG').toUpperCase()) + '</span><p><b>' + esc(r.title) + '</b>' + esc(r.co) + '.</p><button type="button" data-k="claim" data-i="' + i + '">Use now</button></div>';
    });
    s.open.forEach(function (o, i) {
      h += '<div class="fkc-rew"><span class="ic">✓</span><p><b>' + esc(o.title) + '</b>' + esc(o.co) + '.</p><button type="button" data-k="apply" data-i="' + i + '">Use now</button></div>';
    });
    var f10 = fika10(), best = 0;
    s.rewards.concat(s.open).forEach(function (r) { best = Math.max(best, +r.amount || 0); });
    if ((s.rewards.length || s.open.length) && f10) h += '<p class="fkc-note">Your 10% offer saves ' + money(f10) + ' on this bag. A reward can’t be combined with it, so using one replaces the 10%' + (best > f10 ? '.' : ': here the 10% saves more, so you may want to keep your reward for your next order.') + '</p>';
    if (s.next) h += '<p class="fkc-next">' + (s.spins.length || s.rewards.length || s.open.length ? 'Next: ' : '') + '<b>' + esc(s.next.title.charAt(0).toUpperCase() + s.next.title.slice(1)) + '</b> at ' + kg(s.next.g) + ' · ' + kg(s.next.need - (s.delivered + s.placed + s.bag)) + ' to go</p>';
    h += '<p class="fkc-err"></p><button type="button" class="fkc-btn' + (s.spins.length || s.rewards.length || s.open.length ? ' ghost' : '') + '" data-k="close">Back to my order</button>';
    card.innerHTML = h;
    var f = card.querySelector('.fkc-lane .f'); setTimeout(function () { if (f) f.style.left = l.to + '%'; }, 120);
  }
  function err(t) { var e = card.querySelector('.fkc-err'); if (e) e.textContent = t || ''; }
  function won(res) {
    card.innerHTML = '<button type="button" class="fkc-x" data-k="done" aria-label="Close">×</button><span class="fkc-tag">FIKA CLUB</span>' +
      '<h2 class="fkc-h" id="fkcTitle">' + esc(res.name) + ' it is!</h2><div class="fkc-won">' + (res.img ? '<img src="' + esc(res.img) + '" alt="' + esc(res.name) + '">' : '') + '</div>' +
      '<p class="fkc-p">50 g of ' + esc(res.name) + ', free, joins this order</p>' +
      '<button type="button" class="fkc-btn" data-k="done">Back to my order</button>';
  }
  function act(e) {
    var b = e.target.closest('[data-k]'); if (!b) return;
    var k = b.getAttribute('data-k'), i = +b.getAttribute('data-i');
    if (k === 'close') { close(); return; }
    if (k === 'done') { location.replace(C.back); return; } // reload: the free taste joins the bag
    if (k === 'spin') {
      var sp = S.spins[i], ic = b.parentNode.querySelector('.ic'); b.disabled = true; b.textContent = 'Spinning…'; if (ic) ic.classList.add('go'); err('');
      var t0 = Date.now();
      api('taste-spin', { method: 'POST', body: { lap: sp.lap, g: sp.g, filter: 'all', bag: bagGrams() } }).then(function (j) {
        setTimeout(function () { if (j.id) { won(j); return; } if (ic) ic.classList.remove('go'); b.disabled = false; b.textContent = 'Spin now'; err(j.error || 'The wheel got stuck. Please try again.'); }, Math.max(0, 1600 - (Date.now() - t0)));
      }, function () { if (ic) ic.classList.remove('go'); b.disabled = false; b.textContent = 'Spin now'; err('No connection. Please try again.'); });
      return;
    }
    if (k === 'claim' || k === 'apply') {
      b.disabled = true; b.textContent = 'Adding…'; err('');
      var apply = function (code) {
        try {
          var d = wp.data.dispatch('wc/store/cart');
          return (fika10() ? d.removeCoupon('fika10') : Promise.resolve()).then(function () { return d.applyCoupon(code); }).then(function () { b.textContent = 'Added ✓'; }, function (x) { b.disabled = false; b.textContent = 'Use now'; err((x ? x.message : '') ? String(x.message).replace(/<[^>]*>/g, '') : 'This reward could not be added. Try it in the rewards box on the checkout.'); });
        } catch (x) { b.disabled = false; b.textContent = 'Use now'; err('This reward could not be added. Try it in the rewards box on the checkout.'); }
      };
      if (k === 'apply') { apply(S.open[i].code); return; }
      var r = S.rewards[i];
      api('swim-claim', { method: 'POST', body: { lap: r.lap, g: r.g } }).then(function (j) {
        if (j.code) apply(j.code); else { b.disabled = false; b.textContent = 'Use now'; err(j.error || 'This reward could not be added.'); }
      }, function () { b.disabled = false; b.textContent = 'Use now'; err('No connection. Please try again.'); });
    }
  }
  function open() {
    veil = document.createElement('div'); veil.className = 'fkc-veil'; veil.setAttribute('role', 'dialog'); veil.setAttribute('aria-modal', 'true'); veil.setAttribute('aria-labelledby', 'fkcTitle');
    card = document.createElement('div'); card.className = 'fkc-card'; card.tabIndex = -1; veil.appendChild(card); document.body.appendChild(veil);
    veil.addEventListener('click', function (e) { if (e.target === veil) close(); else act(e); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' ? document.body.contains(veil) : false) close(); });
    main(); requestAnimationFrame(function () { veil.classList.add('on'); card.focus(); });
  }
  // wait for the checkout's bag to load, so "this bag" is right
  var tries = 0;
  (function load() {
    var c = cart();
    if ((c ? !(c.items || []).length : true) ? tries++ < 20 : false) { setTimeout(load, 300); return; }
    api('club-welcome').then(function (j) { if (j ? j.goal : false) { S = j; open(); } });
  })();
})();
</script>
FIKA_CLUB;
}, 40 );
