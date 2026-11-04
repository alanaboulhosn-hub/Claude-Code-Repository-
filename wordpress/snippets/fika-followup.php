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
 * - The email: "Name, how were your sweets?", a personal note around the Swedish saying "Delad glädje är dubbel
 *   glädje" (shared joy is double joy), three answers to tap (Loved them /
 *   Pretty good / Not quite), what they picked (photos and weights), a thank-you, a P.S. to pick the next mix,
 *   and the unsubscribe link. Same look as the order and win-back emails (fika-emails.php, fika-winback.php).
 * - The answer, by email only (owner, 4 Nov 2026: nothing on the website): each of the three buttons opens a
 *   ready-made reply to hello@swedishfikalb.com in the customer's own mail app ("My Fika order #1234: Loved them"), with
 *   a few short questions to fill in (1-5 stars for the sweets, delivery, packing and value for money, their favourite,
 *   would they order again, anything else). Replying to the email works too. Answers arrive in the hello@ inbox.
 * - Tracking (fika-email-tracking.php, kind "fu"): clicks and orders show in WooCommerce > Email results.
 * - WP Admin > WooCommerce > Delivery follow-up: on / test / off, test addresses, days, a preview, the log.
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
	// the answers come back by email only (owner, 4 Nov 2026: "nothing shall pop up on our webpage"): each button opens
	// a ready-made reply to hello@ in the customer's own mail app, their answer and a few short questions filled in
	function fika_fu_mailto( $order, $r ) {
		$a    = fika_fu_answers();
		$lines = array( $a[ $r ][0] . ' ' . $a[ $r ][1], '', 'A few quick stars, 1 to 5 (if you like):' );
		foreach ( fika_fu_aspects() as $x ) {
			$lines[] = $x[0] . ( $x[1] ? ' (' . $x[1] . ')' : '' ) . ': ';
		}
		$lines = array_merge( $lines, array( '', 'My favourite this time: ', 'Would I order from Fika again (yes / maybe / no): ', '', 'Anything else: ', '', '' ) );
		$subj  = 'My Fika order #' . $order->get_order_number() . ': ' . $a[ $r ][1];
		return 'mailto:hello@swedishfikalb.com?subject=' . rawurlencode( $subj ) . '&body=' . rawurlencode( implode( "\n", $lines ) );
	}
	function fika_fu_answers() {
		return array( 3 => array( '😍', 'Loved them' ), 2 => array( '🙂', 'Pretty good' ), 1 => array( '😕', 'Not quite' ) );
	}
	// the quick questions in the ready-made reply (owner, 4 Nov 2026: "a more detailed feedback segment"): 1-5 stars each
	function fika_fu_aspects() {
		return array(
			'taste'    => array( 'The sweets', 'taste and freshness' ),
			'delivery' => array( 'Delivery', 'time and the rider' ),
			'packing'  => array( 'Packing', 'arrived in good shape' ),
			'value'    => array( 'Value for money', '' ),
		);
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
	function fika_fu_email( $order ) {
		$f     = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$serif = 'font-family:\'Fanwood Text\',Georgia,\'Times New Roman\',serif;font-variant:small-caps;font-weight:400;';
		$email = strtolower( (string) $order->get_billing_email() );
		$name  = function_exists( 'fika_wb_first_name' ) ? fika_wb_first_name( $order->get_billing_first_name() ) : $order->get_billing_first_name();
		$strip = home_url( '/wp-content/uploads/2026/11/fika-email-owe-kilo.png' ); // ten cartoon candies, see-through
		// the three answers, as big tappable pills (a table, so every inbox shows them side by side)
		$cells = '';
		foreach ( fika_fu_answers() as $r => $a ) {
			// esc_url() would drop mailto: line breaks; the address is ours and the rest is encoded
			$href   = esc_attr( fika_fu_mailto( $order, $r ) );
			$cells .= '<td align="center" width="33%" valign="top" style="padding:0 4px;text-align:center;vertical-align:top;">' .
				'<a href="' . $href . '" style="' . $f . 'display:block;height:84px;padding:14px 4px 0;border-radius:20px;background:#fdeaf2;border:2px solid #f3b7cf;color:#004aad;text-decoration:none;">' .
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
			'<div style="' . $f . 'text-align:center;color:#6c7b9c;font-size:13px;margin:8px 0 0;">Tap one and a short reply opens in your email, with a few quick questions. Fill in what you like and press send, or simply reply to this email. We read every answer.</div>' .
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
			list( $subject, $html ) = fika_fu_email( $last[0] );
			$msg = WC()->mailer()->send( sanitize_email( $in['preview_to'] ), '[Preview] ' . $subject, $html ) ? 'preview' : 'preview-failed';
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-followup&msg=' . $msg ) );
	exit;
} );
if ( ! function_exists( 'fika_fu_admin' ) ) {
	function fika_fu_admin() {
		$set = fika_fu_settings();
		$log = array_slice( (array) get_option( 'fika_fu_log', array() ), 0, 40 );
		$sent = count( array_filter( (array) get_option( 'fika_fu_log', array() ), function ( $l ) { return 0 === strpos( $l['what'], 'Sent' ); } ) );
		$msg  = sanitize_key( $_GET['msg'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
<div class="wrap">
	<h1>Delivery follow-up</h1>
	<p style="max-width:760px"><?php echo (int) $set['days']; ?> days after an order is marked <strong>Completed</strong> (delivered), the customer gets a warm &ldquo;How were your sweets?&rdquo; email with three answers to tap (😍 Loved them, 🙂 Pretty good, 😕 Not quite). Each opens a ready-made reply to hello@ in the customer&rsquo;s own email, with a few short questions (stars for the sweets, delivery, packing and value for money, their favourite, whether they would order again, anything else). Nothing appears on the website. One email per order, at most one per customer in 14 days, only for orders completed in the last <?php echo (int) ( $set['days'] + $set['window'] ); ?> days. Sent in small batches, 10:00&ndash;20:00 Beirut time.</p>
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
	<h2>Answers</h2>
	<p style="max-width:760px">Answers come back as emails to <strong>hello@swedishfikalb.com</strong>, subject &ldquo;My Fika order #&hellip;: Loved them / Pretty good / Not quite&rdquo;, with the customer&rsquo;s stars, favourite and words. Nothing appears on the website. Sent so far: <strong><?php echo (int) $sent; ?></strong>.</p>
	<h2>Recent activity</h2>
	<table class="widefat striped" style="max-width:900px"><tbody>
		<?php if ( ! $log ) : ?><tr><td>Nothing sent yet.</td></tr><?php endif; ?>
		<?php foreach ( $log as $l ) : ?><tr><td style="width:140px"><?php echo esc_html( wp_date( 'j M, H:i', $l['t'] ) ); ?></td><td><?php echo esc_html( $l['email'] ); ?></td><td><?php echo esc_html( $l['what'] ); ?></td></tr><?php endforeach; ?>
	</tbody></table>
</div>
		<?php
	}
}
