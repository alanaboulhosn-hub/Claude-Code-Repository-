<?php
/**
 * Fika: win-back emails for customers who have not ordered in a while.
 * - Email 1, 30 days after a customer's last order (or after they created an account, if they never ordered):
 *   "It's been a while": what's new (products added in the last 45 days, or the favourites), a news box written
 *   in WP Admin, and a "Pick your mix" button.
 * - Email 2, 14 days later if they still have not ordered: a personal discount code (default 10% off the candies,
 *   one use, only for their email, valid 14 days). Its button opens the shop and the code applies at checkout.
 * - Ordering again starts the cycle over. Every email has an unsubscribe link, and unsubscribed addresses never get
 *   these emails again. Shop staff accounts and test addresses (@example.com/.org/.net) are left out.
 * - Runs once a day (WooCommerce's scheduler, about 10:00 Beirut time), at most 50 emails per run.
 * - Mode: "Test" sends only to the addresses listed in WP Admin; "Live" sends to everyone due; "Off" sends nothing.
 * - WP Admin > WooCommerce > Win-back emails: settings, previews, who is due, what was sent, who came back.
 * Sent with WooCommerce's email layout (the same template as the order emails).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-winback.php
 */

if ( ! function_exists( 'fika_wb_settings' ) ) {
	function fika_wb_settings() {
		$s = get_option( 'fika_winback_settings', array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array(
			'mode'        => 'test',
			'test_emails' => 'alan.aboulhosn@gmail.com',
			'news_title'  => 'Lördagsmys is here',
			'news_text'   => 'Make Saturday the sweetest day of the week: let everyone pick a few favourites, pour them into one big bowl and gather the family around it.',
			'discount'    => 10,
			'valid_days'  => 14,
			'first_days'  => 30,
			'second_days' => 14,
		) );
	}
	function fika_wb_state() {
		$s = get_option( 'fika_winback_state', array() );
		return is_array( $s ) ? $s : array();
	}
	function fika_wb_log( $email, $what ) {
		$log = get_option( 'fika_winback_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 't' => time(), 'email' => $email, 'what' => $what ) );
		update_option( 'fika_winback_log', array_slice( $log, 0, 200 ), false );
	}
	function fika_wb_stopped( $email ) {
		$stop = get_option( 'fika_marketing_stop', array() );
		return is_array( $stop ) ? in_array( md5( strtolower( $email ) ), $stop, true ) : false;
	}
	function fika_wb_unsub_link( $email ) {
		$email = strtolower( $email );
		return add_query_arg( array( 'fika_unsub' => rawurlencode( $email ), 'k' => substr( wp_hash( 'fika-wb|' . $email ), 0, 20 ) ), home_url( '/' ) );
	}
	// everyone who could get these emails: email => name, last order time (0 = never), start of the quiet period
	function fika_wb_audience() {
		$people = array();
		$skip   = function ( $email ) {
			return ! is_email( $email ) || preg_match( '/@example\.(com|org|net)$/i', $email );
		};
		$orders = wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order', 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-undelivered' ), 'orderby' => 'date', 'order' => 'ASC' ) );
		foreach ( $orders as $o ) {
			$e = strtolower( $o->get_billing_email() );
			if ( $skip( $e ) || ! $o->get_date_created() ) {
				continue;
			}
			$people[ $e ] = array(
				'name' => $o->get_shipping_first_name() ? $o->get_shipping_first_name() : $o->get_billing_first_name(),
				'last' => $o->get_date_created()->getTimestamp(),
			);
		}
		foreach ( get_users( array( 'role__in' => array( 'customer', 'subscriber' ), 'fields' => array( 'ID', 'user_email', 'user_registered' ) ) ) as $u ) {
			$e = strtolower( $u->user_email );
			if ( $skip( $e ) || user_can( $u->ID, 'manage_woocommerce' ) ) {
				continue;
			}
			if ( ! isset( $people[ $e ] ) ) {
				$people[ $e ] = array( 'name' => get_user_meta( $u->ID, 'first_name', true ), 'last' => 0, 'joined' => strtotime( $u->user_registered . ' UTC' ) );
			}
		}
		foreach ( $people as $e => $p ) {
			// staff who also placed orders are left out
			$u = get_user_by( 'email', $e );
			if ( $u ? user_can( $u, 'manage_woocommerce' ) : false ) {
				unset( $people[ $e ] );
				continue;
			}
			$people[ $e ]['since'] = $p['last'] ? $p['last'] : ( isset( $p['joined'] ) ? $p['joined'] : time() );
		}
		return $people;
	}
	// what each person is due today: '1', '2' or ''
	function fika_wb_due( $email, $p, $st, $set ) {
		$s = isset( $st[ $email ] ) ? $st[ $email ] : array();
		if ( ! empty( $s ) ? (int) $s['base'] !== (int) $p['last'] : false ) {
			$s = array(); // ordered since: a new cycle
		}
		if ( empty( $s['s1'] ) ) {
			return ( time() - $p['since'] >= (int) $set['first_days'] * DAY_IN_SECONDS ) ? '1' : '';
		}
		if ( empty( $s['s2'] ) ) {
			return ( time() - (int) $s['s1'] >= (int) $set['second_days'] * DAY_IN_SECONDS ) ? '2' : '';
		}
		return '';
	}
	// candies to show: added in the last 45 days, else the favourites
	function fika_wb_products( &$is_new ) {
		$args   = array( 'status' => 'publish', 'visibility' => 'catalog', 'stock_status' => 'instock', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' );
		$list   = wc_get_products( array_merge( $args, array( 'date_created' => '>' . ( time() - 45 * DAY_IN_SECONDS ) ) ) );
		$is_new = (bool) $list;
		if ( ! $list ) {
			$list = wc_get_products( array_merge( $args, array( 'featured' => true, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) );
		}
		return $list;
	}
	function fika_wb_grid( $list ) {
		$cells = array();
		foreach ( $list as $p ) {
			$img     = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
			$bag     = has_term( 'ready-mix', 'product_cat', $p->get_id() );
			$price   = wc_price( wc_get_price_to_display( $p ) ) . ( $bag ? ' per 500 g bag' : ' per 100 g' );
			$cells[] = '<td width="50%" valign="top" style="width:50%;padding:8px;text-align:center;">' .
				'<a href="' . esc_url( $p->get_permalink() ) . '" style="text-decoration:none;">' .
				( $img ? '<img src="' . esc_url( $img ) . '" width="200" alt="" style="display:block;width:100%;max-width:200px;height:auto;margin:0 auto 8px;border-radius:16px;">' : '' ) .
				'<span class="fika-pname" style="display:block;">' . esc_html( $p->get_name() ) . '</span></a>' .
				'<span style="display:block;color:#6c7b9c;font-size:13px;">' . wp_strip_all_tags( $price ) . '</span></td>';
		}
		$rows = '';
		foreach ( array_chunk( $cells, 2 ) as $pair ) {
			$rows .= '<tr>' . implode( '', $pair ) . ( 1 === count( $pair ) ? '<td width="50%"></td>' : '' ) . '</tr>';
		}
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:6px 0 4px;">' . $rows . '</table>';
	}
	function fika_wb_button( $href, $text ) {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr><td align="center" style="text-align:center;padding:26px 0 22px;">' .
			'<a class="fika-mail-btn" href="' . esc_url( $href ) . '" style="display:inline-block;padding:14px 32px;border-radius:999px;background:#004aad;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html( $text ) . '</a></td></tr></table>';
	}
	function fika_wb_small( $email ) {
		return '<p style="margin-top:20px !important;text-align:center;color:#6c7b9c !important;font-size:12.5px !important;">You get this email because you shop with Fika. ' .
			'<a href="' . esc_url( fika_wb_unsub_link( $email ) ) . '" style="color:#6c7b9c !important;">Unsubscribe</a>.</p>';
	}
	// email 1: it's been a while
	function fika_wb_email1( $email, $p ) {
		$set  = fika_wb_settings();
		$new  = false;
		$list = fika_wb_products( $new );
		$hi   = $p['name'] ? 'Hi ' . esc_html( $p['name'] ) . ',' : 'Hi there,';
		$lead = $p['last']
			? 'It&rsquo;s been a while since your last fika, so here is what&rsquo;s new on our candy wall.'
			: 'You joined Fika a while ago but haven&rsquo;t picked your first mix yet. Here is what&rsquo;s on our candy wall right now.';
		$body = '<div class="email-introduction"><p>' . $hi . '</p><p>' . $lead . '</p></div>' .
			( $list ? '<h2 class="email-order-detail-heading">' . ( $new ? 'New drops' : 'Fan favourites' ) . '<br><span>' . ( $new ? 'Just landed from Sweden' : 'The sweets everyone keeps coming back for' ) . '</span></h2>' . fika_wb_grid( $list ) : '' ) .
			( trim( $set['news_title'] . $set['news_text'] ) ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:22px 0 4px;"><tr><td class="fika-mail-box">' .
				( $set['news_title'] ? '<h2>' . esc_html( $set['news_title'] ) . '</h2>' : '' ) . '<p>' . nl2br( esc_html( $set['news_text'] ) ) . '</p></td></tr></table>' : '' ) .
			fika_wb_button( home_url( '/mix-your-own/' ), 'Pick your mix' ) .
			fika_wb_small( $email );
		return array( 'It’s been a while' . ( $p['name'] ? ', ' . $p['name'] : '' ) . '! Here’s what’s new at Fika', WC()->mailer()->wrap_message( 'We miss you!', $body ) );
	}
	// email 2: a personal discount code
	function fika_wb_email2( $email, $p, $code, $expires ) {
		$set  = fika_wb_settings();
		$pct  = (int) $set['discount'];
		$hi   = $p['name'] ? 'Hi ' . esc_html( $p['name'] ) . ',' : 'Hi there,';
		$body = '<div class="email-introduction"><p>' . $hi . '</p><p>We would love to see you back, so here is a little treat for your next bag.</p></div>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:22px 0 4px;"><tr><td class="fika-mail-box" style="text-align:center;">' .
			'<h2 style="text-align:center;">' . $pct . '% off your candies</h2>' .
			'<p style="text-align:center;">Your personal code</p>' .
			'<p style="text-align:center;margin:10px 0 !important;"><span style="display:inline-block;padding:10px 22px;border:2px dashed #004aad;border-radius:14px;background:#ffffff;color:#004aad;font-size:22px;font-weight:700;letter-spacing:.08em;">' . esc_html( $code ) . '</span></p>' .
			'<p style="text-align:center;color:#6c7b9c !important;font-size:13.5px !important;">Valid until ' . esc_html( wp_date( 'j F Y', $expires ) ) . ', once, for ' . esc_html( $email ) . '.</p>' .
			'</td></tr></table>' .
			fika_wb_button( add_query_arg( 'fika_coupon', rawurlencode( $code ), home_url( '/mix-your-own/' ) ), 'Use my ' . $pct . '%' ) .
			'<p style="text-align:center;">Fill your bag and the code is added at checkout. You can also type it in yourself.</p>' .
			fika_wb_small( $email );
		return array( 'A little treat to welcome you back: ' . $pct . '% off', WC()->mailer()->wrap_message( 'Here’s ' . $pct . '% off your next bag', $body ) );
	}
	function fika_wb_coupon( $email, $note = 'Win-back email 2' ) {
		$set     = fika_wb_settings();
		$code    = 'COMEBACK-' . strtoupper( wp_generate_password( 6, false, false ) );
		$expires = time() + (int) $set['valid_days'] * DAY_IN_SECONDS;
		$c       = new WC_Coupon();
		$c->set_code( $code );
		$c->set_discount_type( 'percent' );
		$c->set_amount( (int) $set['discount'] );
		$c->set_individual_use( true );
		$c->set_usage_limit( 1 );
		$c->set_usage_limit_per_user( 1 );
		$c->set_email_restrictions( array( $email ) );
		$c->set_date_expires( $expires );
		$c->set_description( $note . ' for ' . $email );
		$c->save();
		return array( $code, $expires );
	}
	function fika_wb_send( $email, $p, $which ) {
		if ( '2' === $which ) {
			list( $code, $expires ) = fika_wb_coupon( $email );
			list( $subject, $html ) = fika_wb_email2( $email, $p, $code, $expires );
		} else {
			list( $subject, $html ) = fika_wb_email1( $email, $p );
		}
		return WC()->mailer()->send( $email, $subject, $html ) ? ( isset( $code ) ? $code : true ) : false;
	}
	// a personal code (tied to one email): fill in that email for the shopper, then add the code
	function fika_wb_apply_code( $code ) {
		$c = new WC_Coupon( $code );
		if ( ! $c->get_id() ) {
			return false;
		}
		$emails = $c->get_email_restrictions();
		if ( 1 === count( $emails ) && WC()->customer && ! WC()->customer->get_billing_email() ) {
			WC()->customer->set_billing_email( $emails[0] );
			WC()->customer->save();
		}
		return WC()->cart->apply_coupon( $code );
	}
	// the daily run
	function fika_wb_run() {
		$set = fika_wb_settings();
		if ( 'off' === $set['mode'] ) {
			return 0;
		}
		$test = array_filter( array_map( 'strtolower', array_map( 'trim', preg_split( '/[\s,;]+/', (string) $set['test_emails'] ) ) ) );
		$st   = fika_wb_state();
		$sent = 0;
		foreach ( fika_wb_audience() as $email => $p ) {
			if ( 50 <= $sent ) {
				break;
			}
			if ( 'test' === $set['mode'] ? ! in_array( $email, $test, true ) : false ) {
				continue;
			}
			if ( fika_wb_stopped( $email ) ) {
				continue;
			}
			if ( isset( $st[ $email ] ) ? (int) $st[ $email ]['base'] !== (int) $p['last'] : false ) {
				unset( $st[ $email ] );
			}
			$due = fika_wb_due( $email, $p, $st, $set );
			if ( ! $due ) {
				continue;
			}
			$ok = fika_wb_send( $email, $p, $due );
			if ( ! $ok ) {
				continue;
			}
			$st[ $email ]               = isset( $st[ $email ] ) ? $st[ $email ] : array( 'base' => (int) $p['last'] );
			$st[ $email ][ 's' . $due ] = time();
			if ( is_string( $ok ) ) {
				$st[ $email ]['coupon'] = $ok;
			}
			fika_wb_log( $email, '1' === $due ? 'Email 1 sent (it’s been a while)' : 'Email 2 sent (code ' . ( is_string( $ok ) ? $ok : '' ) . ')' );
			$sent++;
		}
		update_option( 'fika_winback_state', $st, false );
		return $sent;
	}
}

// ---------- once a day ----------
add_action( 'fika_winback_daily', 'fika_wb_run' );
add_action( 'init', function () {
	if ( ! function_exists( 'as_next_scheduled_action' ) || as_next_scheduled_action( 'fika_winback_daily', array(), 'fika' ) ) {
		return;
	}
	$first = strtotime( 'tomorrow 07:00 UTC' ); // about 10:00 in Beirut
	as_schedule_recurring_action( $first, DAY_IN_SECONDS, 'fika_winback_daily', array(), 'fika' );
}, 20 );

// ---------- a customer who comes back: counted, and the cycle starts over on the next run ----------
$fika_wb_order = function ( $order ) {
	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
	if ( ! $order ) {
		return;
	}
	$e  = strtolower( $order->get_billing_email() );
	$st = fika_wb_state();
	if ( ! empty( $st[ $e ]['s1'] ) ) {
		fika_wb_log( $e, 'Ordered again (#' . $order->get_order_number() . ')' . ( empty( $st[ $e ]['s2'] ) ? ' after email 1' : ' after email 2' ) );
		$won = (int) get_option( 'fika_winback_won', 0 );
		update_option( 'fika_winback_won', $won + 1, false );
		unset( $st[ $e ] );
		update_option( 'fika_winback_state', $st, false );
	}
};
add_action( 'woocommerce_store_api_checkout_order_processed', $fika_wb_order );
add_action( 'woocommerce_checkout_order_processed', $fika_wb_order );

// ---------- links in the emails ----------
add_action( 'template_redirect', function () {
	// "Use my 10%": remember the code in a cookie (a first visit has no WooCommerce session yet), add it once the bag
	// reaches the cart
	if ( isset( $_GET['fika_coupon'] ) && function_exists( 'WC' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$code = wc_format_coupon_code( wp_unslash( $_GET['fika_coupon'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $code ) {
			if ( WC()->cart ? ! WC()->cart->is_empty() : false ) {
				fika_wb_apply_code( $code );
			} else {
				wc_setcookie( 'fika_coupon', $code, time() + 14 * DAY_IN_SECONDS );
			}
		}
		wp_safe_redirect( remove_query_arg( 'fika_coupon' ) );
		exit;
	}
	// Unsubscribe
	if ( isset( $_GET['fika_unsub'], $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$email = strtolower( sanitize_email( wp_unslash( $_GET['fika_unsub'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok    = $email ? hash_equals( substr( wp_hash( 'fika-wb|' . $email ), 0, 20 ), (string) wp_unslash( $_GET['k'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $ok ) {
			$stop   = get_option( 'fika_marketing_stop', array() );
			$stop   = is_array( $stop ) ? $stop : array();
			$stop[] = md5( $email );
			update_option( 'fika_marketing_stop', array_values( array_unique( $stop ) ), false );
			fika_wb_log( $email, 'Unsubscribed' );
		}
		wp_die( '<h1 style="font-family:sans-serif;color:#004aad">' . ( $ok ? 'You are unsubscribed' : 'Link not recognised' ) . '</h1><p style="font-family:sans-serif">' .
			( $ok ? 'We won&rsquo;t send you these emails any more. Order emails still arrive as usual.' : 'Please use the link from the email.' ) .
			' <a href="' . esc_url( home_url( '/' ) ) . '">Back to Fika</a></p>', 'Fika', array( 'response' => 200 ) );
	}
}, 6 );
// the remembered code goes on as soon as there is something in the cart (the shop's Checkout button fills it)
add_action( 'woocommerce_add_to_cart', function () {
	if ( ! WC()->cart || empty( $_COOKIE['fika_coupon'] ) ) {
		return;
	}
	$code = wc_format_coupon_code( wp_unslash( $_COOKIE['fika_coupon'] ) );
	wc_setcookie( 'fika_coupon', '', time() - HOUR_IN_SECONDS );
	unset( $_COOKIE['fika_coupon'] );
	if ( $code ? ! WC()->cart->has_discount( $code ) : false ) {
		fika_wb_apply_code( $code );
	}
}, 30 );

// ---------- WP Admin > WooCommerce > Win-back emails ----------
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Win-back emails', 'Win-back emails', 'manage_woocommerce', 'fika-winback', 'fika_wb_admin' );
}, 61 );
add_action( 'admin_post_fika_wb_save', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_wb_save' );
	$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	$set = fika_wb_settings();
	$set['mode']        = in_array( isset( $in['mode'] ) ? $in['mode'] : '', array( 'off', 'test', 'live' ), true ) ? $in['mode'] : 'test';
	$set['test_emails'] = sanitize_textarea_field( isset( $in['test_emails'] ) ? $in['test_emails'] : '' );
	$set['news_title']  = sanitize_text_field( isset( $in['news_title'] ) ? $in['news_title'] : '' );
	$set['news_text']   = sanitize_textarea_field( isset( $in['news_text'] ) ? $in['news_text'] : '' );
	$set['discount']    = max( 1, min( 100, absint( isset( $in['discount'] ) ? $in['discount'] : 10 ) ) );
	$set['valid_days']  = max( 1, absint( isset( $in['valid_days'] ) ? $in['valid_days'] : 14 ) );
	$set['first_days']  = max( 1, absint( isset( $in['first_days'] ) ? $in['first_days'] : 30 ) );
	$set['second_days'] = max( 1, absint( isset( $in['second_days'] ) ? $in['second_days'] : 14 ) );
	update_option( 'fika_winback_settings', $set, false );
	$msg = 'saved';
	if ( ! empty( $in['preview'] ) ) {
		$to = sanitize_email( isset( $in['preview_to'] ) ? $in['preview_to'] : '' );
		if ( is_email( $to ) ) {
			$p = array( 'name' => 'Sara', 'last' => time() - 40 * DAY_IN_SECONDS, 'since' => time() - 40 * DAY_IN_SECONDS );
			if ( '2' === $in['preview'] ) {
				list( $code, $exp )     = fika_wb_coupon( $to, 'Win-back preview' );
				list( $subject, $html ) = fika_wb_email2( $to, $p, $code, $exp );
			} else {
				list( $subject, $html ) = fika_wb_email1( $to, $p );
			}
			$msg = WC()->mailer()->send( $to, '[Preview] ' . $subject, $html ) ? 'preview' : 'preview-failed';
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-winback&msg=' . $msg ) );
	exit;
} );
if ( ! function_exists( 'fika_wb_admin' ) ) {
	function fika_wb_admin() {
		$set   = fika_wb_settings();
		$st    = fika_wb_state();
		$aud   = fika_wb_audience();
		$due1  = 0;
		$due2  = 0;
		$rows  = '';
		foreach ( $aud as $e => $p ) {
			if ( fika_wb_stopped( $e ) ) {
				continue;
			}
			$d = fika_wb_due( $e, $p, $st, $set );
			$due1 += '1' === $d ? 1 : 0;
			$due2 += '2' === $d ? 1 : 0;
			$s     = isset( $st[ $e ] ) ? $st[ $e ] : array();
			$next  = empty( $s['s1'] ) ? $p['since'] + $set['first_days'] * DAY_IN_SECONDS : ( empty( $s['s2'] ) ? $s['s1'] + $set['second_days'] * DAY_IN_SECONDS : 0 );
			$rows .= '<tr><td>' . esc_html( $e ) . '</td><td>' . ( $p['last'] ? esc_html( wp_date( 'j M Y', $p['last'] ) ) : 'Never (account since ' . esc_html( wp_date( 'j M Y', $p['since'] ) ) . ')' ) . '</td><td>' .
				( empty( $s['s1'] ) ? 'Email 1' : ( empty( $s['s2'] ) ? 'Email 2' : 'Both sent' ) ) . ( $next ? ' &middot; ' . esc_html( wp_date( 'j M Y', $next ) ) : '' ) . ( $d ? ' <strong>(due now)</strong>' : '' ) . '</td></tr>';
		}
		$log = get_option( 'fika_winback_log', array() );
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
<div class="wrap">
	<h1>Win-back emails</h1>
	<p style="max-width:760px">Customers who haven&rsquo;t ordered for <?php echo (int) $set['first_days']; ?> days get <strong>email 1</strong> (what&rsquo;s new, your news, &ldquo;Pick your mix&rdquo;).
	<?php echo (int) $set['second_days']; ?> days later, if they still haven&rsquo;t ordered, <strong>email 2</strong> brings a personal <?php echo (int) $set['discount']; ?>% code, valid <?php echo (int) $set['valid_days']; ?> days.
	Ordering again starts over. Sent once a day, around 10:00. Orders came back after these emails: <strong><?php echo (int) get_option( 'fika_winback_won', 0 ); ?></strong>.</p>
	<?php if ( 'saved' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
	<?php if ( 'preview' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved, and the preview is on its way.</p></div><?php endif; ?>
	<?php if ( 'preview-failed' === $msg ) : ?><div class="notice notice-error is-dismissible"><p>The preview could not be sent.</p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="fika_wb_save"><?php wp_nonce_field( 'fika_wb_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Sending</th><td>
				<label><input type="radio" name="mode" value="test" <?php checked( 'test', $set['mode'] ); ?>> <strong>Test</strong>: only to the addresses below</label><br>
				<label><input type="radio" name="mode" value="live" <?php checked( 'live', $set['mode'] ); ?>> <strong>Live</strong>: to every customer who is due</label><br>
				<label><input type="radio" name="mode" value="off" <?php checked( 'off', $set['mode'] ); ?>> Off</label></td></tr>
			<tr><th>Test addresses</th><td><textarea name="test_emails" rows="2" class="large-text"><?php echo esc_textarea( $set['test_emails'] ); ?></textarea><p class="description">Used in Test mode. One or more, separated by commas.</p></td></tr>
			<tr><th>News (email 1)</th><td><input type="text" name="news_title" class="regular-text" value="<?php echo esc_attr( $set['news_title'] ); ?>" placeholder="Title"><br><br>
				<textarea name="news_text" rows="3" class="large-text" placeholder="A few words of news"><?php echo esc_textarea( $set['news_text'] ); ?></textarea><p class="description">Shown in a pink box. Leave both empty to leave it out. &ldquo;New drops&rdquo; fill in by themselves: candies added in the last 45 days, otherwise your favourites.</p></td></tr>
			<tr><th>Discount (email 2)</th><td><input type="number" name="discount" min="1" max="100" value="<?php echo (int) $set['discount']; ?>" style="width:80px"> % off the candies, valid <input type="number" name="valid_days" min="1" value="<?php echo (int) $set['valid_days']; ?>" style="width:70px"> days</td></tr>
			<tr><th>Timing</th><td>Email 1 after <input type="number" name="first_days" min="1" value="<?php echo (int) $set['first_days']; ?>" style="width:70px"> days without an order, email 2 <input type="number" name="second_days" min="1" value="<?php echo (int) $set['second_days']; ?>" style="width:70px"> days after email 1</td></tr>
			<tr><th>Send a preview</th><td><input type="email" name="preview_to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
				<button class="button" name="preview" value="1">Email 1</button> <button class="button" name="preview" value="2">Email 2</button>
				<p class="description">A preview of email 2 creates a real code for that address.</p></td></tr>
		</table>
		<?php submit_button( 'Save' ); ?>
	</form>
	<h2>Customers (<?php echo count( $aud ); ?>) &middot; due now: email 1 <?php echo (int) $due1; ?>, email 2 <?php echo (int) $due2; ?></h2>
	<table class="widefat striped" style="max-width:900px"><thead><tr><th>Email</th><th>Last order</th><th>Next email</th></tr></thead><tbody><?php echo $rows ? $rows : '<tr><td colspan="3">No customers yet.</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
	<h2>Recent activity</h2>
	<table class="widefat striped" style="max-width:900px"><thead><tr><th style="width:160px">When</th><th>Email</th><th>What</th></tr></thead><tbody>
		<?php if ( ! $log ) : ?><tr><td colspan="3">Nothing sent yet.</td></tr><?php endif; ?>
		<?php foreach ( array_slice( (array) $log, 0, 50 ) as $l ) : ?>
		<tr><td><?php echo esc_html( wp_date( 'j M Y, H:i', $l['t'] ) ); ?></td><td><?php echo esc_html( $l['email'] ); ?></td><td><?php echo esc_html( $l['what'] ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
</div>
		<?php
	}
}

// ---------- shop managers: preview without sending ----------
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/winback-preview', array( 'methods' => 'GET', 'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); }, 'callback' => function ( $req ) {
		$p = array( 'name' => 'Sara', 'last' => $req['never'] ? 0 : time() - 40 * DAY_IN_SECONDS, 'since' => time() - 40 * DAY_IN_SECONDS );
		$e = new WC_Email();
		if ( '2' === $req['n'] ) {
			list( $subject, $html ) = fika_wb_email2( 'sara@example.com', $p, 'COMEBACK-PREVIEW', time() + 14 * DAY_IN_SECONDS );
		} else {
			list( $subject, $html ) = fika_wb_email1( 'sara@example.com', $p );
		}
		return array( 'subject' => $subject, 'html' => apply_filters( 'woocommerce_mail_content', $e->style_inline( $html ) ) );
	} ) );
} );
