<?php
/**
 * Fika: the delivery follow-up email (owner, 4 Nov 2026: "a very warm follow-up 5 days after the order is marked
 * completed, asking them how the candies were").
 * - Who: an order marked Completed (delivered: the RT status update sets it, fika-rtd.php) gets one email, 5 days after
 *   it was completed (setting), and only while the completion is at most 8 days old: nobody is asked weeks later.
 *   One email per order, at most one per customer in 14 days, never to unsubscribed addresses (the win-back
 *   unsubscribe list, fika_marketing_stop), test addresses (@example.com/.org/.net) or shop staff.
 * - Never twice: the order is marked (_fika_fu) and saved BEFORE the email goes out; a failed send is not retried.
 * - Sent in small batches: at most 5 an hour, 10:00-20:00 Beirut, at most 20 in 24 hours, and never when the
 *   win-back emails and these together reached 70 in 24 hours (the hello@ mailbox sends about 100 a day, order
 *   emails included).
 * - The email: "How were your sweets?", a warm note with the day it arrived, three answers to tap (Loved them /
 *   Pretty good / Not quite), what they picked (photos and weights), a thank-you, a P.S. to pick the next mix,
 *   and the unsubscribe link. Same look as the order and win-back emails (fika-emails.php, fika-winback.php).
 * - The answer: the tap opens the home page with a Fika card: the answer chosen, then a few quick questions, all optional
 *   (4 Nov 2026): 1-5 stars for the sweets, delivery, packing and value for money, their favourite candy from that
 *   order, "Would you order from Fika again?" (yes / maybe / probably not) and a box for a few words ("Send"). Opening the link saves nothing (mail scanners open links); sending does. The answer goes on the order
 *   (meta _fika_fb, an order note) and on WooCommerce > Delivery follow-up. The thank-you card fits the answer:
 *   loved -> Instagram and the next mix, good -> the next mix, not quite -> WhatsApp, to make it right.
 * - Tracking (fika-email-tracking.php, kind "fu"): clicks and orders show in WooCommerce > Email results.
 * - WP Admin > WooCommerce > Delivery follow-up: on / test / off, test addresses, days, a preview, the answers, the log.
 * Needs the win-back snippet (its buttons, photos and unsubscribe link). Installed with the Code Snippets plugin.
 * Source: wordpress/snippets/fika-followup.php
 */

if ( ! function_exists( 'fika_fu_settings' ) ) {
	function fika_fu_settings() {
		$s = get_option( 'fika_fu_settings', array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array(
			'mode'        => 'off', // off | test (only the test addresses) | live
			'test_emails' => 'alan.aboulhosn@gmail.com',
			'days'        => 5,     // days after the order was completed
			'window'      => 3,     // ... and not later than this many days after that
		) );
	}
	function fika_fu_log( $email, $what ) {
		$log = get_option( 'fika_fu_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 't' => time(), 'email' => $email, 'what' => $what ) );
		update_option( 'fika_fu_log', array_slice( $log, 0, 400 ), false );
	}
	function fika_fu_sent_24h() {
		$n = 0;
		foreach ( (array) get_option( 'fika_fu_log', array() ) as $l ) {
			if ( (int) $l['t'] < time() - DAY_IN_SECONDS ) {
				break;
			}
			$n += preg_match( '/^(Sent|Not sent)/', $l['what'] ) ? 1 : 0;
		}
		return $n;
	}
	// the signed link for an order's answer: /?fika_fb=<order id>.<signature>&r=3|2|1
	function fika_fu_sig( $order_id ) {
		return substr( wp_hash( 'fika-fb|' . (int) $order_id ), 0, 16 );
	}
	// $preview: an admin preview's links (the card works, nothing is saved on the order)
	function fika_fu_link( $order_id, $r, $preview = false ) {
		return add_query_arg( array_merge( array( 'fika_fb' => (int) $order_id . '.' . fika_fu_sig( $order_id ), 'r' => (int) $r ), $preview ? array( 'fika_fb_test' => 1 ) : array() ), home_url( '/' ) );
	}
	function fika_fu_answers() {
		return array( 3 => array( '😍', 'Loved them' ), 2 => array( '🙂', 'Pretty good' ), 1 => array( '😕', 'Not quite' ) );
	}
	// the quick questions on the answer card (owner, 4 Nov 2026: "a more detailed feedback segment"): 1-5 stars each
	function fika_fu_aspects() {
		return array(
			'taste'    => array( 'The sweets', 'taste and freshness' ),
			'delivery' => array( 'Delivery', 'time and the rider' ),
			'packing'  => array( 'Packing', 'arrived in good shape' ),
			'value'    => array( 'Value for money', '' ),
		);
	}
	function fika_fu_again() {
		return array( 'yes' => 'Yes, for sure', 'maybe' => 'Maybe', 'no' => 'Probably not' );
	}
	// the candies of the order, to pick a favourite from (names as on the order, the mystery taste left out)
	function fika_fu_fav_options( $order ) {
		$out = array();
		foreach ( $order->get_items() as $it ) {
			if ( '' !== (string) $it->get_meta( 'Mystery taste' ) ) {
				continue;
			}
			$n = html_entity_decode( $it->get_name() );
			$out[ $n ] = $n;
		}
		return array_values( $out );
	}
	// the order behind a signed answer link, or null
	function fika_fu_order_from( $raw ) {
		list( $id, $sig ) = array_pad( explode( '.', (string) $raw, 2 ), 2, '' );
		$id = absint( $id );
		if ( ! $id || ! hash_equals( fika_fu_sig( $id ), (string) $sig ) ) {
			return null;
		}
		$o = wc_get_order( $id );
		return $o instanceof WC_Order ? $o : null;
	}
	function fika_fu_skip( $email ) {
		if ( ! is_email( $email ) || preg_match( '/@example\.(com|org|net)$/i', $email ) ) {
			return true;
		}
		if ( function_exists( 'fika_wb_stopped' ) && fika_wb_stopped( $email ) ) {
			return true;
		}
		$u = get_user_by( 'email', $email );
		return $u ? user_can( $u, 'manage_woocommerce' ) : false;
	}
	// orders due now: completed between (days + window) and days ago, not emailed yet
	function fika_fu_due() {
		$set  = fika_fu_settings();
		$from = time() - ( (int) $set['days'] + (int) $set['window'] ) * DAY_IN_SECONDS;
		$to   = time() - (int) $set['days'] * DAY_IN_SECONDS;
		$list = wc_get_orders( array( 'limit' => 200, 'type' => 'shop_order', 'status' => array( 'wc-completed' ), 'date_completed' => $from . '...' . $to, 'orderby' => 'date', 'order' => 'ASC' ) );
		$last = (array) get_option( 'fika_fu_last', array() );
		$out  = array();
		foreach ( $list as $o ) {
			$e = strtolower( (string) $o->get_billing_email() );
			if ( $o->get_meta( '_fika_fu' ) || fika_fu_skip( $e ) || isset( $out[ $e ] ) ) {
				continue;
			}
			if ( isset( $last[ md5( $e ) ] ) && time() - (int) $last[ md5( $e ) ] < 14 * DAY_IN_SECONDS ) {
				continue; // one follow-up per customer in 14 days
			}
			$out[ $e ] = $o;
		}
		return array_values( $out );
	}
	// what they picked: [ photo, name, weight label ] for each line (the free mystery taste included)
	function fika_fu_items( $order ) {
		$rows = array();
		foreach ( $order->get_items() as $it ) {
			$p     = $it->get_product();
			$label = (string) $it->get_meta( 'Weight' );
			$label = '' !== $label ? $label : (string) $it->get_meta( 'Amount' );
			$label = '' !== $label ? $label : ( '' !== (string) $it->get_meta( 'Mystery taste' ) ? 'Mystery taste, 50 g' : '' );
			$img   = $p && $p->get_image_id() && function_exists( 'fika_wb_photo' ) ? fika_wb_photo( $p->get_image_id() ) : '';
			$rows[] = array( $img, html_entity_decode( $it->get_name() ), $label );
		}
		return $rows;
	}
	// the email: [ subject, html ]
	function fika_fu_email( $order, $preview = false ) {
		$f     = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$serif = 'font-family:\'Fanwood Text\',Georgia,\'Times New Roman\',serif;font-variant:small-caps;font-weight:400;';
		$email = strtolower( (string) $order->get_billing_email() );
		$name  = function_exists( 'fika_wb_first_name' ) ? fika_wb_first_name( $order->get_billing_first_name() ) : $order->get_billing_first_name();
		$strip = home_url( '/wp-content/uploads/2026/11/fika-email-owe-kilo.png' ); // ten cartoon candies, see-through
		// the three answers, as big tappable pills (a table, so every inbox shows them side by side)
		$cells = '';
		foreach ( fika_fu_answers() as $r => $a ) {
			$cells .= '<td align="center" width="33%" valign="top" style="padding:0 4px;text-align:center;vertical-align:top;">' .
				'<a href="' . esc_url( fika_fu_link( $order->get_id(), $r, $preview ) ) . '" style="' . $f . 'display:block;height:84px;padding:14px 4px 0;border-radius:20px;background:#fdeaf2;border:2px solid #f3b7cf;color:#004aad;text-decoration:none;">' .
				'<span style="display:block;font-size:34px;line-height:1.1;">' . $a[0] . '</span>' .
				'<span style="display:block;margin-top:6px;font-size:13.5px;font-weight:600;line-height:1.25;color:#004aad;white-space:nowrap;">' . esc_html( $a[1] ) . '</span></a></td>';
		}
		$items = '';
		foreach ( array_slice( fika_fu_items( $order ), 0, 8 ) as $row ) {
			$items .= '<tr>' .
				'<td width="52" style="padding:7px 12px 7px 0;width:52px;vertical-align:middle;">' . ( $row[0] ? '<img src="' . esc_url( $row[0] ) . '" width="44" height="44" alt="" style="display:block;width:44px;height:44px;border-radius:12px;object-fit:cover;border:0;">' : '' ) . '</td>' .
				'<td style="' . $f . 'padding:7px 0;vertical-align:middle;color:#004aad;font-size:15px;font-weight:600;line-height:1.3;">' . esc_html( $row[1] ) . '</td>' .
				'<td align="right" style="' . $f . 'padding:7px 0 7px 10px;vertical-align:middle;color:#6c7b9c;font-size:13.5px;white-space:nowrap;">' . esc_html( $row[2] ) . '</td></tr>';
		}
		$div = function ( $id, $label ) {
			return function_exists( 'fika_wb_divider' ) ? fika_wb_divider( $id, $label ) : '';
		};
		$body =
			'<img src="' . esc_url( $strip ) . '" width="480" alt="" style="display:block;width:100%;max-width:480px;height:auto;margin:0 auto 14px;border:0;">' .
			// the opening: to them, personally, around a Swedish saying about sharing (owner, 4 Nov 2026)
			'<p style="text-align:center;">' . ( $name ? 'Hi ' . esc_html( $name ) . ',' : 'Hi,' ) . '</p>' .
			'<p style="text-align:center;">we hope your sweets brought a little extra joy to your week. Maybe they waited in a bowl on the table, maybe they were gone before the bag was even unpacked, or maybe you shared them with the people you love most.</p>' .
			'<div style="margin:18px 0 18px;padding:18px 16px 16px;border-radius:20px;background:#fdeaf2;text-align:center;">' .
				'<div style="' . $f . 'color:#d35d93;font-size:12px;letter-spacing:.14em;text-transform:uppercase;">In Sweden we say</div>' .
				'<div style="font-family:\'Fanwood Text\',Georgia,\'Times New Roman\',serif;font-style:italic;color:#004aad;font-size:24px;line-height:1.3;margin:8px 0 6px;">&ldquo;Delad glädje är dubbel glädje.&rdquo;</div>' .
				'<div style="' . $serif . 'color:#1b2a4a;font-size:17px;">Shared joy is double joy.</div>' .
			'</div>' .
			'<p style="text-align:center;">That is what fika is about: slowing down, and sharing something sweet with the people around you. We hope your bag did just that.</p>' .
			'<p style="text-align:center;">Every bag is weighed and packed by hand, so it would mean a lot to us to hear how yours was.</p>' .
			$div( 'fb', 'Tap one' ) .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:14px 0 4px;"><tr>' . $cells . '</tr></table>' .
			'<div style="' . $f . 'text-align:center;color:#6c7b9c;font-size:13px;margin:8px 0 0;">One tap, then a few quick questions (about 30 seconds). We read every answer.</div>' .
			( $items ? $div( 'items', 'What you picked' ) . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:12px 0 0;">' . $items . '</table>' : '' ) .
			$div( 'thanks', '' ) .
			'<div style="' . $f . 'text-align:center;color:#1b2a4a;font-size:15.5px;line-height:1.6;margin:6px 0 0;">Thank you for choosing Fika. It truly means the world to us.</div>' .
			'<div style="' . $serif . 'text-align:center;color:#004aad;font-size:21px;line-height:1.3;margin:10px 0 0;">With love,<br>the Fika family</div>' .
			'<div style="' . $f . 'text-align:center;color:#1b2a4a;font-size:14.5px;line-height:1.55;margin:22px 0 0;">P.S. Running low already? <a href="' . esc_url( home_url( '/mix-your-own/' ) ) . '" style="color:#004aad;font-weight:600;">Pick your next mix</a></div>' .
			( function_exists( 'fika_wb_small' ) ? fika_wb_small( $email ) : '' );
		$html = WC()->mailer()->wrap_message( $name ? $name . ', how were your sweets?' : 'How were your sweets?', $body );
		$pre  = '<div class="fika-pre" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' . esc_html( 'Delad glädje är dubbel glädje: shared joy is double joy. We would love to hear how your sweets were.' ) . str_repeat( '&#847;&zwnj;&nbsp;', 40 ) . '</div>';
		$html = preg_replace( '/(<body[^>]*>)/i', '$1' . $pre, $html, 1 );
		return array( ( $name ? $name . ', how' : 'How' ) . ' were your sweets? 🍬', $html );
	}
	// send one: marked first (never twice), then sent
	function fika_fu_send( $order ) {
		$email = strtolower( (string) $order->get_billing_email() );
		$order->update_meta_data( '_fika_fu', time() );
		$order->save();
		$last              = (array) get_option( 'fika_fu_last', array() );
		$last[ md5( $email ) ] = time();
		update_option( 'fika_fu_last', $last, false );
		list( $subject, $html ) = fika_fu_email( $order );
		$sid = function_exists( 'fika_et_new_send' ) ? fika_et_new_send( 'fu', $email ) : '';
		if ( $sid ) {
			$html = fika_et_tag( $html, $sid );
			$order->update_meta_data( '_fika_fu_send', $sid );
			$order->save();
		}
		$ok = WC()->mailer()->send( $email, $subject, $html );
		fika_fu_log( $email, ( $ok ? 'Sent' : 'Not sent (mail error), not retried' ) . ' (order #' . $order->get_order_number() . ')' );
		return $ok;
	}
	function fika_fu_run( $limit = 5 ) {
		$set = fika_fu_settings();
		if ( 'off' === $set['mode'] ) {
			return 0;
		}
		$lock = (int) get_option( 'fika_fu_lock', 0 );
		if ( $lock && time() - $lock < 15 * MINUTE_IN_SECONDS ) {
			return 0;
		}
		delete_option( 'fika_fu_lock' );
		if ( ! add_option( 'fika_fu_lock', time(), '', false ) ) {
			return 0;
		}
		$test = array_filter( array_map( 'strtolower', array_map( 'trim', preg_split( '/[\s,;]+/', (string) $set['test_emails'] ) ) ) );
		$room = min( $limit, 20 - fika_fu_sent_24h(), 70 - fika_fu_sent_24h() - ( function_exists( 'fika_wb_sent_24h' ) ? fika_wb_sent_24h() : 0 ) );
		$sent = 0;
		foreach ( fika_fu_due() as $o ) {
			if ( $sent >= $room ) {
				break;
			}
			if ( 'test' === $set['mode'] && ! in_array( strtolower( (string) $o->get_billing_email() ), $test, true ) ) {
				continue;
			}
			if ( $sent ) {
				sleep( 4 );
			}
			if ( ! fika_fu_send( $o ) ) {
				break; // the mail server is struggling: the next batch tries again with the next people
			}
			$sent++;
		}
		delete_option( 'fika_fu_lock' );
		return $sent;
	}
}

// ---------- sending: once an hour, 10:00-20:00 Beirut, from WordPress's own cron only ----------
add_action( 'init', function () {
	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		return;
	}
	$on = 'off' !== fika_fu_settings()['mode'];
	if ( $on && ! as_has_scheduled_action( 'fika_fu_tick', array(), 'fika' ) ) {
		as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 'fika_fu_tick', array(), 'fika' );
	} elseif ( ! $on && as_has_scheduled_action( 'fika_fu_tick', array(), 'fika' ) ) {
		as_unschedule_all_actions( 'fika_fu_tick', array(), 'fika' );
	}
} );
add_action( 'fika_fu_tick', function () {
	// an "async request" run can be cut off part-way (see fika-winback.php); the next hour's run picks it up
	if ( ! wp_doing_cron() ) {
		return;
	}
	$h = (int) wp_date( 'G', null, new DateTimeZone( 'Asia/Beirut' ) );
	if ( $h >= 10 && $h < 20 ) {
		fika_fu_run( 5 );
	}
} );

// ---------- the answer: the card on the home page, and saving it ----------
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['fika_fb'] ) || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$o = fika_fu_order_from( sanitize_text_field( wp_unslash( $_GET['fika_fb'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$r = isset( $_POST['fika_r'] ) ? absint( $_POST['fika_r'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$a = fika_fu_answers();
	if ( ! $o || ! isset( $a[ $r ] ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	if ( ! empty( $_GET['fika_fb_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		// an admin preview: the thank-you card, nothing saved on the (real) order behind the preview
		wp_safe_redirect( add_query_arg( array( 'fika_fb_done' => $r, 'n' => rawurlencode( 'Preview' ) ), home_url( '/' ) ) );
		exit;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$text  = isset( $_POST['fika_text'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['fika_text'] ) ), 0, 1000 ) : '';
	$stars = array();
	foreach ( fika_fu_aspects() as $k => $x ) {
		$v = isset( $_POST['fika_s'][ $k ] ) ? absint( $_POST['fika_s'][ $k ] ) : 0;
		if ( $v >= 1 && $v <= 5 ) {
			$stars[ $k ] = $v;
		}
	}
	$fav   = isset( $_POST['fika_fav'] ) ? sanitize_text_field( wp_unslash( $_POST['fika_fav'] ) ) : '';
	$fav   = in_array( $fav, fika_fu_fav_options( $o ), true ) ? $fav : '';
	$again = isset( $_POST['fika_again'] ) ? sanitize_key( $_POST['fika_again'] ) : '';
	$again = isset( fika_fu_again()[ $again ] ) ? $again : '';
	// phpcs:enable
	$prev = $o->get_meta( '_fika_fb' );
	$o->update_meta_data( '_fika_fb', array( 'r' => $r, 's' => $stars, 'fav' => $fav, 'again' => $again, 'text' => $text, 't' => time() ) );
	$bits = array();
	foreach ( $stars as $k => $v ) {
		$bits[] = fika_fu_aspects()[ $k ][0] . ' ' . $v . '/5';
	}
	$o->add_order_note( 'Customer answer (delivery follow-up): ' . $a[ $r ][0] . ' ' . $a[ $r ][1] . ( $bits ? '. ' . implode( ', ', $bits ) : '' ) . ( '' !== $fav ? '. Favourite: ' . $fav : '' ) . ( '' !== $again ? '. Order again: ' . fika_fu_again()[ $again ] : '' ) . ( '' !== $text ? '. "' . $text . '"' : '' ) . ( $prev ? ' (changed their answer)' : '' ) );
	$o->save();
	$all = (array) get_option( 'fika_fu_feedback', array() );
	$all = array_values( array_filter( $all, function ( $x ) use ( $o ) { return (int) $x['order'] !== (int) $o->get_id(); } ) );
	array_unshift( $all, array( 'order' => $o->get_id(), 'num' => $o->get_order_number(), 'name' => $o->get_billing_first_name(), 'r' => $r, 's' => $stars, 'fav' => $fav, 'again' => $again, 'text' => $text, 't' => time() ) );
	update_option( 'fika_fu_feedback', array_slice( $all, 0, 500 ), false );
	$name = function_exists( 'fika_wb_first_name' ) ? fika_wb_first_name( $o->get_billing_first_name() ) : '';
	wp_safe_redirect( add_query_arg( array( 'fika_fb_done' => $r, 'n' => rawurlencode( $name ) ), home_url( '/' ) ) );
	exit;
}, 5 );
add_action( 'wp_footer', function () {
	// phpcs:disable WordPress.Security.NonceVerification
	$ask  = isset( $_GET['fika_fb'] ) ? fika_fu_order_from( sanitize_text_field( wp_unslash( $_GET['fika_fb'] ) ) ) : null;
	$done = isset( $_GET['fika_fb_done'] ) ? absint( $_GET['fika_fb_done'] ) : 0;
	if ( ! $ask && ! $done ) {
		return;
	}
	$a    = fika_fu_answers();
	$r    = isset( $_GET['r'] ) && isset( $a[ absint( $_GET['r'] ) ] ) ? absint( $_GET['r'] ) : 3;
	$name = $ask ? ( function_exists( 'fika_wb_first_name' ) ? fika_wb_first_name( $ask->get_billing_first_name() ) : '' ) : sanitize_text_field( wp_unslash( $_GET['n'] ?? '' ) );
	// phpcs:enable
	if ( $ask ) {
		$opts = '';
		foreach ( $a as $k => $x ) {
			$opts .= '<label class="fkf-opt"><input type="radio" name="fika_r" value="' . (int) $k . '"' . checked( $r, $k, false ) . '><span><i>' . $x[0] . '</i>' . esc_html( $x[1] ) . '</span></label>';
		}
		// a few quick questions, all optional but the first (owner, 4 Nov 2026)
		$rows = '';
		foreach ( fika_fu_aspects() as $k => $x ) {
			$st = '';
			for ( $i = 1; $i <= 5; $i++ ) {
				$st .= '<label class="fkf-star"><input type="radio" name="fika_s[' . esc_attr( $k ) . ']" value="' . $i . '" aria-label="' . esc_attr( $x[0] . ': ' . $i . ' of 5' ) . '"><span>★</span></label>';
			}
			$rows .= '<div class="fkf-row"><div class="fkf-lab">' . esc_html( $x[0] ) . ( $x[1] ? '<small>' . esc_html( $x[1] ) . '</small>' : '' ) . '</div><div class="fkf-stars" data-k="' . esc_attr( $k ) . '">' . $st . '</div></div>';
		}
		$favs = '';
		foreach ( fika_fu_fav_options( $ask ) as $n ) {
			$favs .= '<label class="fkf-chip"><input type="radio" name="fika_fav" value="' . esc_attr( $n ) . '"><span>' . esc_html( $n ) . '</span></label>';
		}
		$again = '';
		foreach ( fika_fu_again() as $k => $x ) {
			$again .= '<label class="fkf-chip"><input type="radio" name="fika_again" value="' . esc_attr( $k ) . '"><span>' . esc_html( $x ) . '</span></label>';
		}
		$card = '<form class="fkf-card" method="post" action="' . esc_url( add_query_arg( null, null ) ) . '">' .
			'<button class="fkf-x" type="button" aria-label="Close">×</button>' .
			'<h2 class="fkf-h">' . ( $name ? esc_html( $name ) . ', how were your sweets?' : 'How were your sweets?' ) . '</h2>' .
			'<div class="fkf-opts">' . $opts . '</div>' .
			'<div class="fkf-sec">A few quick stars <em>(optional)</em></div>' . $rows .
			( $favs ? '<div class="fkf-sec">Your favourite this time</div><div class="fkf-chips">' . $favs . '</div>' : '' ) .
			'<div class="fkf-sec">Would you order from Fika again?</div><div class="fkf-chips">' . $again . '</div>' .
			'<div class="fkf-sec">Anything else?</div>' .
			'<textarea name="fika_text" rows="3" maxlength="1000" placeholder="What you loved, what we can do better, a candy you miss…"></textarea>' .
			'<button class="fkf-go" type="submit">Send</button>' .
			'<p class="fkf-small">' . ( ! empty( $_GET['fika_fb_test'] ) ? 'Preview: nothing you send here is saved.' : 'About 30 seconds. Your answers go straight to the Fika team.' ) . '</p></form>'; // phpcs:ignore WordPress.Security.NonceVerification
	} else {
		$wa   = 'https://wa.me/96179411565';
		$who  = $name ? ', ' . esc_html( $name ) : '';
		$msgs = array(
			3 => array( 'That makes our day' . $who . '! 💗', 'Thank you for telling us. Share your bag on Instagram and tag <b>@swedishfika.lb</b>: we love seeing where our sweets end up.', '<a class="fkf-go" href="' . esc_url( home_url( '/mix-your-own/' ) ) . '">Pick my next mix</a>' ),
			2 => array( 'Thank you' . $who . '!', 'We are glad you enjoyed them, and we will keep making every bag better.', '<a class="fkf-go" href="' . esc_url( home_url( '/mix-your-own/' ) ) . '">Pick my next mix</a>' ),
			1 => array( 'We are so sorry' . $who, 'Thank you for telling us, it really helps. If something went wrong with your order, message us on WhatsApp and we will make it right.', '<a class="fkf-go" href="' . esc_url( $wa ) . '">WhatsApp us · 79 411 565</a>' ),
		);
		$m    = isset( $msgs[ $done ] ) ? $msgs[ $done ] : $msgs[2];
		$card = '<div class="fkf-card"><button class="fkf-x" type="button" aria-label="Close">×</button><h2 class="fkf-h">' . $m[0] . '</h2><p class="fkf-p">' . $m[1] . '</p>' . $m[2] . '</div>';
	}
	?>
<style id="fika-fb-css">
/* the answer card is the only popup while it shows (not the Fan Favourites or Fika Club ones) */
.fkd-veil, .fki-veil { display: none !important; }
.fkf-veil { position: fixed; inset: 0; z-index: 200001; display: flex; align-items: center; justify-content: center; padding: 18px; background: rgba(27, 42, 74, .35); }
.fkf-card { position: relative; width: 100%; max-width: 440px; max-height: calc(100vh - 36px); overflow: auto; background: #fff; border-radius: 26px; box-shadow: 0 20px 50px rgba(0, 74, 173, .22); padding: 34px 26px 24px; text-align: center; font-family: 'Outfit', Arial, sans-serif; color: #1b2a4a; }
.fkf-card, .fkf-card * { box-sizing: border-box; }
.fkf-x { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: #fdeaf2; color: #004aad; font-size: 22px; line-height: 1; cursor: pointer; }
.fkf-h { font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 30px; line-height: 1.1; color: #004aad; margin: 4px 30px 16px; }
.fkf-p { font-family: 'Fanwood Text', Georgia, serif; font-size: 18px; line-height: 1.45; margin: 0 0 20px; }
.fkf-p b { font-family: 'Outfit', Arial, sans-serif; font-size: 16px; color: #004aad; }
.fkf-opts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 0 0 14px; }
.fkf-opt input { position: absolute; opacity: 0; pointer-events: none; }
.fkf-opt span { display: block; padding: 12px 4px 10px; border: 2px solid #f3b7cf; border-radius: 18px; background: #fdeaf2; color: #004aad; font-size: 13.5px; font-weight: 600; line-height: 1.25; cursor: pointer; transition: transform .15s, background .15s; }
.fkf-opt span i { display: block; font-style: normal; font-size: 32px; line-height: 1.15; margin-bottom: 4px; }
.fkf-opt input:checked + span { background: #004aad; border-color: #004aad; color: #fff; transform: translateY(-2px); }
.fkf-opt input:focus-visible + span { outline: 3px solid #f5b3cf; }
.fkf-sec { margin: 18px 0 8px; color: #d35d93; font-size: 12px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; text-align: left; }
.fkf-sec em { font-style: normal; color: #6c7b9c; letter-spacing: .04em; text-transform: none; font-weight: 400; }
.fkf-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 7px 0; border-bottom: 1px solid #f3dbe6; text-align: left; }
.fkf-lab { font-size: 15px; font-weight: 600; color: #004aad; line-height: 1.2; }
.fkf-lab small { display: block; font-size: 12.5px; font-weight: 400; color: #6c7b9c; }
.fkf-stars { display: flex; flex-shrink: 0; }
.fkf-star input, .fkf-chip input { position: absolute; opacity: 0; pointer-events: none; }
.fkf-star span { display: block; padding: 0 2px; font-size: 27px; line-height: 1; color: #e3d5dc; cursor: pointer; transition: transform .12s, color .12s; }
.fkf-star.on span { color: #f2b84b; }
.fkf-star input:focus-visible + span { outline: 2px solid #f5b3cf; border-radius: 6px; }
.fkf-chips { display: flex; flex-wrap: wrap; gap: 7px; text-align: left; }
.fkf-chip span { display: inline-block; padding: 7px 13px; border: 1.5px solid #c9d5ea; border-radius: 999px; background: #fff; color: #004aad; font-size: 14px; font-weight: 600; cursor: pointer; }
.fkf-chip input:checked + span { background: #004aad; border-color: #004aad; color: #fff; }
.fkf-chip input:focus-visible + span { outline: 2px solid #f5b3cf; }
.fkf-card textarea { width: 100%; min-height: 84px; padding: 12px 14px; border: 1.5px solid #c9d5ea; border-radius: 16px; font: 15px/1.4 'Outfit', Arial, sans-serif; color: #1b2a4a; resize: vertical; margin: 0 0 14px; }
.fkf-go { display: block; width: 100%; padding: 15px 18px; border: 0; border-radius: 999px; background: #004aad; color: #fff !important; font: 700 16px 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; text-decoration: none; cursor: pointer; }
.fkf-small { margin: 12px 0 0; color: #6c7b9c; font-size: 13px; }
@media (max-width: 480px) { .fkf-card { padding: 30px 18px 20px; } .fkf-h { font-size: 27px; } }
</style>
<div class="fkf-veil" id="fkfVeil" role="dialog" aria-modal="true"><?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
<script>
(function () {
  var v = document.getElementById('fkfVeil');
  if (!v) return;
  function close() { v.remove(); try { var u = new URL(location.href); ['fika_fb', 'r', 'fika_fb_done', 'n', 'fika_fb_test'].forEach(function (k) { u.searchParams.delete(k); }); history.replaceState(null, '', u.toString()); } catch (e) {} }
  v.addEventListener('click', function (e) { if (e.target === v || (e.target.closest && e.target.closest('.fkf-x'))) close(); });
  // the stars fill up to the one tapped
  v.addEventListener('change', function (e) {
    var row = e.target.closest ? e.target.closest('.fkf-stars') : null;
    if (!row) return;
    var n = +e.target.value;
    [].forEach.call(row.querySelectorAll('.fkf-star'), function (l, i) { l.classList.toggle('on', i < n); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
	<?php
}, 70 );

// ---------- WP Admin > WooCommerce > Delivery follow-up ----------
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Delivery follow-up', 'Delivery follow-up', 'manage_woocommerce', 'fika-followup', 'fika_fu_admin' );
}, 62 );
add_action( 'admin_post_fika_fu_save', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_fu_save' );
	$in  = wp_unslash( $_POST );
	$set = fika_fu_settings();
	$set['mode']        = in_array( $in['mode'] ?? '', array( 'off', 'test', 'live' ), true ) ? $in['mode'] : 'off';
	$set['test_emails'] = sanitize_textarea_field( $in['test_emails'] ?? '' );
	$set['days']        = max( 1, absint( $in['days'] ?? 5 ) );
	$set['window']      = max( 1, absint( $in['window'] ?? 3 ) );
	update_option( 'fika_fu_settings', $set, false );
	$msg = 'saved';
	if ( ! empty( $in['preview'] ) && is_email( $in['preview_to'] ?? '' ) ) {
		$last = wc_get_orders( array( 'limit' => 1, 'status' => array( 'wc-completed' ), 'orderby' => 'date', 'order' => 'DESC' ) );
		if ( $last ) {
			list( $subject, $html ) = fika_fu_email( $last[0], true );
			$msg = WC()->mailer()->send( sanitize_email( $in['preview_to'] ), '[Preview] ' . $subject, $html ) ? 'preview' : 'preview-failed';
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-followup&msg=' . $msg ) );
	exit;
} );
if ( ! function_exists( 'fika_fu_admin' ) ) {
	function fika_fu_admin() {
		$set = fika_fu_settings();
		$fb  = (array) get_option( 'fika_fu_feedback', array() );
		$log = array_slice( (array) get_option( 'fika_fu_log', array() ), 0, 40 );
		$a   = fika_fu_answers();
		$cnt = array( 3 => 0, 2 => 0, 1 => 0 );
		$sum = array(); $num = array(); $ag = array(); $favs = array();
		foreach ( $fb as $x ) {
			$cnt[ (int) $x['r'] ] = ( $cnt[ (int) $x['r'] ] ?? 0 ) + 1;
			foreach ( (array) ( $x['s'] ?? array() ) as $k => $v ) {
				$sum[ $k ] = ( $sum[ $k ] ?? 0 ) + (int) $v;
				$num[ $k ] = ( $num[ $k ] ?? 0 ) + 1;
			}
			if ( ! empty( $x['again'] ) ) {
				$ag[ $x['again'] ] = ( $ag[ $x['again'] ] ?? 0 ) + 1;
			}
			if ( ! empty( $x['fav'] ) ) {
				$favs[ $x['fav'] ] = ( $favs[ $x['fav'] ] ?? 0 ) + 1;
			}
		}
		arsort( $favs );
		$asp = fika_fu_aspects();
		$agn = fika_fu_again();
		$sent = count( array_filter( (array) get_option( 'fika_fu_log', array() ), function ( $l ) { return 0 === strpos( $l['what'], 'Sent' ); } ) );
		$msg  = sanitize_key( $_GET['msg'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
<div class="wrap">
	<h1>Delivery follow-up</h1>
	<p style="max-width:760px"><?php echo (int) $set['days']; ?> days after an order is marked <strong>Completed</strong> (delivered), the customer gets a warm &ldquo;How were your sweets?&rdquo; email with three answers to tap (😍 Loved them, 🙂 Pretty good, 😕 Not quite). The tap opens a short card on the site: 1&ndash;5 stars for the sweets, delivery, packing and value for money, their favourite from the order, whether they would order again, and a box for a few words (all optional but the first). One email per order, at most one per customer in 14 days, only for orders completed in the last <?php echo (int) ( $set['days'] + $set['window'] ); ?> days. Sent in small batches, 10:00&ndash;20:00 Beirut time.</p>
	<?php if ( 'saved' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
	<?php if ( 'preview' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved, and the preview is on its way (it uses the latest completed order).</p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="fika_fu_save"><?php wp_nonce_field( 'fika_fu_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Sending</th><td>
				<label><input type="radio" name="mode" value="live" <?php checked( 'live', $set['mode'] ); ?>> <strong>On</strong>: to every customer whose order is due</label><br>
				<label><input type="radio" name="mode" value="test" <?php checked( 'test', $set['mode'] ); ?>> <strong>Test</strong>: only to the addresses below</label><br>
				<label><input type="radio" name="mode" value="off" <?php checked( 'off', $set['mode'] ); ?>> Off</label></td></tr>
			<tr><th>Test addresses</th><td><textarea name="test_emails" rows="2" class="large-text"><?php echo esc_textarea( $set['test_emails'] ); ?></textarea></td></tr>
			<tr><th>Timing</th><td><input type="number" name="days" min="1" value="<?php echo (int) $set['days']; ?>" style="width:70px"> days after the order is completed, and not more than <input type="number" name="window" min="1" value="<?php echo (int) $set['window']; ?>" style="width:70px"> days after that</td></tr>
			<tr><th>Send a preview</th><td><input type="email" name="preview_to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"> <button class="button" name="preview" value="1">Send a preview</button></td></tr>
		</table>
		<?php submit_button( 'Save' ); ?>
	</form>
	<h2>Answers (<?php echo count( $fb ); ?>) &middot; sent: <?php echo (int) $sent; ?></h2>
	<p><?php foreach ( $a as $k => $x ) { echo esc_html( $x[0] . ' ' . $x[1] . ': ' . (int) $cnt[ $k ] ) . ' &nbsp; '; } ?></p>
	<table class="widefat" style="max-width:900px;margin:0 0 14px"><tbody>
		<tr><th style="width:220px">Average stars</th><td><?php foreach ( $asp as $k => $x ) { echo '<b>' . esc_html( $x[0] ) . '</b> ' . ( ! empty( $num[ $k ] ) ? esc_html( number_format( $sum[ $k ] / $num[ $k ], 1 ) ) . ' ★ (' . (int) $num[ $k ] . ')' : '–' ) . ' &nbsp; &nbsp; '; } ?></td></tr>
		<tr><th>Would order again</th><td><?php foreach ( $agn as $k => $x ) { echo esc_html( $x . ': ' . (int) ( $ag[ $k ] ?? 0 ) ) . ' &nbsp; &nbsp; '; } ?></td></tr>
		<tr><th>Favourites</th><td><?php echo $favs ? esc_html( implode( ', ', array_map( function ( $n, $c ) { return $n . ' (' . $c . ')'; }, array_keys( array_slice( $favs, 0, 8, true ) ), array_slice( $favs, 0, 8, true ) ) ) ) : '–'; ?></td></tr>
	</tbody></table>
	<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:110px">When</th><th style="width:120px">Order</th><th style="width:130px">Answer</th><th style="width:230px">Stars</th><th style="width:140px">Favourite</th><th style="width:100px">Again?</th><th>Their words</th></tr></thead><tbody>
		<?php if ( ! $fb ) : ?><tr><td colspan="7">No answers yet.</td></tr><?php endif; ?>
		<?php foreach ( array_slice( $fb, 0, 100 ) as $x ) : ?>
		<tr><td><?php echo esc_html( wp_date( 'j M, H:i', $x['t'] ) ); ?></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $x['order'] ) ); ?>">#<?php echo esc_html( $x['num'] ); ?></a> <?php echo esc_html( $x['name'] ); ?></td><td><?php echo esc_html( $a[ (int) $x['r'] ][0] . ' ' . $a[ (int) $x['r'] ][1] ); ?></td>
			<td><?php echo esc_html( implode( ' · ', array_map( function ( $k, $v ) use ( $asp ) { return ( $asp[ $k ][0] ?? $k ) . ' ' . $v; }, array_keys( (array) ( $x['s'] ?? array() ) ), (array) ( $x['s'] ?? array() ) ) ) ); ?></td>
			<td><?php echo esc_html( $x['fav'] ?? '' ); ?></td><td><?php echo esc_html( isset( $x['again'] ) && isset( $agn[ $x['again'] ] ) ? $agn[ $x['again'] ] : '' ); ?></td><td><?php echo esc_html( $x['text'] ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
	<h2>Recent activity</h2>
	<table class="widefat striped" style="max-width:900px"><tbody>
		<?php if ( ! $log ) : ?><tr><td>Nothing sent yet.</td></tr><?php endif; ?>
		<?php foreach ( $log as $l ) : ?><tr><td style="width:140px"><?php echo esc_html( wp_date( 'j M, H:i', $l['t'] ) ); ?></td><td><?php echo esc_html( $l['email'] ); ?></td><td><?php echo esc_html( $l['what'] ); ?></td></tr><?php endforeach; ?>
	</tbody></table>
</div>
		<?php
	}
}
