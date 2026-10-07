<?php
/**
 * Fika: "your bag is waiting" reminder for shoppers who leave the checkout.
 * - Saves the shopper's email and bag as soon as the email is typed on the checkout (WooCommerce sends it to the
 *   server by itself), or when a signed-in customer opens the checkout. The saved bag follows later bag changes.
 * - One hour after the last activity, if no order was placed with that email, one reminder email is sent:
 *   the bag's contents and a "Finish my order" button that refills the bag on any device and opens the checkout.
 * - At most one reminder per email address per 7 days; never after an order; the email has a "no more reminders"
 *   link.
 * - Saved bags are kept 30 days. WP Admin > WooCommerce > Checkout leavers lists them, with reminders sent and
 *   orders that followed a reminder.
 * - Sent with WooCommerce's own email layout and sender (Fika <hello@swedishfikalb.com>), from the site's mailer.
 *   Timing uses WooCommerce's scheduler (runs when the site gets visits; a server cron can make it exact).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-bag-reminder.php
 */

if ( ! defined( 'FIKA_BAG_DELAY' ) ) {
	define( 'FIKA_BAG_DELAY', HOUR_IN_SECONDS );
}

// ---------- storage: option fika_saved_bags = token => record ----------
if ( ! function_exists( 'fika_bags' ) ) {
	function fika_bags() {
		$all = get_option( 'fika_saved_bags', array() );
		return is_array( $all ) ? $all : array();
	}
	function fika_bags_save( $all ) {
		$cut = time() - 30 * DAY_IN_SECONDS;
		foreach ( $all as $t => $r ) {
			if ( (int) $r['updated'] < $cut ) {
				unset( $all[ $t ] );
			}
		}
		update_option( 'fika_saved_bags', $all, false );
	}
	function fika_bag_stopped( $email ) {
		$stop = get_option( 'fika_bag_stop', array() );
		return is_array( $stop ) ? in_array( md5( strtolower( $email ) ), $stop, true ) : false;
	}
	// the cart as a list of lines
	function fika_bag_items() {
		$items = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $items;
		}
		foreach ( WC()->cart->get_cart() as $line ) {
			$items[] = array(
				'id'  => (int) $line['product_id'],
				'var' => (int) $line['variation_id'],
				'qty' => (int) $line['quantity'],
			);
		}
		return $items;
	}
	// save (or refresh) this visitor's bag; schedules the reminder one hour from now
	function fika_bag_remember( $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) || fika_bag_stopped( $email ) || ! WC()->session ) {
			return;
		}
		$items = fika_bag_items();
		if ( ! $items ) {
			return;
		}
		$all   = fika_bags();
		$token = WC()->session->get( 'fika_bag_token' );
		if ( ! $token || ! isset( $all[ $token ] ) || 'waiting' !== $all[ $token ]['status'] ) {
			$token         = wp_generate_password( 24, false, false );
			$all[ $token ] = array( 'created' => time(), 'status' => 'waiting', 'sent' => 0 );
			WC()->session->set( 'fika_bag_token', $token );
		}
		$name                     = WC()->customer ? WC()->customer->get_shipping_first_name() : '';
		$all[ $token ]['email']   = $email;
		$all[ $token ]['name']    = $name ? $name : ( isset( $all[ $token ]['name'] ) ? $all[ $token ]['name'] : '' );
		$all[ $token ]['items']   = $items;
		$all[ $token ]['updated'] = time();
		fika_bags_save( $all );
		fika_bag_schedule( $token );
	}
	function fika_bag_schedule( $token ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		as_unschedule_all_actions( 'fika_bag_reminder', array( $token ), 'fika' );
		as_schedule_single_action( time() + FIKA_BAG_DELAY, 'fika_bag_reminder', array( $token ), 'fika' );
	}
	function fika_bag_set( $token, $fields ) {
		$all = fika_bags();
		if ( isset( $all[ $token ] ) ) {
			$all[ $token ] = array_merge( $all[ $token ], $fields );
			fika_bags_save( $all );
		}
	}
	// "300 g" for candies, "1 bag (500 g)" for Ready Mix
	function fika_bag_amount( $product_id, $qty ) {
		if ( has_term( 'ready-mix', 'product_cat', $product_id ) ) {
			return $qty . ( 1 === $qty ? ' bag' : ' bags' ) . ' (' . ( $qty * 500 ) . ' g)';
		}
		$g = $qty * 100;
		return $g >= 1000 ? rtrim( rtrim( number_format( $g / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg' : $g . ' g';
	}
	function fika_bag_link( $token, $arg = 'fika_bag' ) {
		return add_query_arg( $arg, rawurlencode( $token ), home_url( '/' ) );
	}
	// the reminder email (WooCommerce layout)
	function fika_bag_email_html( $token, $r ) {
		// built from the same parts as WooCommerce's order emails, so it has the exact look of the order confirmation
		$rows  = '';
		$total = 0;
		$grams = 0;
		foreach ( $r['items'] as $it ) {
			$p = wc_get_product( $it['var'] ? $it['var'] : $it['id'] );
			if ( ! $p || ! $p->is_purchasable() ) {
				continue;
			}
			$line   = (float) wc_get_price_to_display( $p ) * $it['qty'];
			$total += $line;
			$grams += $it['qty'] * ( has_term( 'ready-mix', 'product_cat', $it['id'] ) ? 500 : 100 );
			$thumb  = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '';
			$rows  .= '<tr class="order_item">' .
				'<td class="td font-family text-align-left email-order-item-thumbnail" width="64" style="width:64px;vertical-align:middle;padding-left:0;padding-right:12px;">' .
				( $thumb ? '<img src="' . esc_url( $thumb ) . '" width="52" height="52" alt="" style="display:block;width:52px;height:52px;border-radius:12px;">' : '' ) . '</td>' .
				'<td class="td font-family text-align-left" style="vertical-align:middle;padding-left:0;"><span class="fika-pname">' . esc_html( $p->get_name() ) . '</span><div class="email-order-item-meta">Weight: ' . esc_html( fika_bag_amount( $it['id'], $it['qty'] ) ) . '</div></td>' .
				'<td class="td font-family text-align-right" style="vertical-align:middle;white-space:nowrap;">&times;' . (int) $it['qty'] . '</td>' .
				'<td class="td font-family text-align-right" style="vertical-align:middle;white-space:nowrap;padding-right:0;">' . wc_price( $line ) . '</td></tr>';
		}
		if ( ! $rows ) {
			return '';
		}
		$weight = $grams >= 1000 ? rtrim( rtrim( number_format( $grams / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg' : $grams . ' g';
		$body   = '<div class="email-introduction"><p>' . ( $r['name'] ? 'Hi ' . esc_html( $r['name'] ) . ',' : 'Hi there,' ) . '</p>' .
			'<p>You left some Swedish sweets in your bag. We saved it for you, so you can finish your order in one click.</p></div>' .
			'<h2 class="email-order-detail-heading">Your bag<br><span>' . esc_html( $weight ) . ' of pick-and-mix</span></h2>' .
			'<table class="td font-family email-order-details" role="presentation" cellspacing="0" cellpadding="6" border="0" width="100%" style="width:100%;">' .
			'<thead><tr><th class="td text-align-left" scope="col" colspan="2" style="padding-left:0;">Product</th><th class="td text-align-right" scope="col">Quantity</th><th class="td text-align-right" scope="col" style="padding-right:0;">Price</th></tr></thead>' .
			'<tbody>' . $rows . '</tbody>' .
			'<tfoot><tr class="order-totals order-totals-total"><th class="td text-align-left" scope="row" colspan="3" style="padding-left:0;">Candies:</th><td class="td text-align-right" style="padding-right:0;">' . wc_price( $total ) . '</td></tr></tfoot></table>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr><td align="center" style="text-align:center;padding:30px 0 26px;">' .
			'<a class="fika-mail-btn" href="' . esc_url( fika_bag_link( $token ) ) . '" style="display:inline-block;padding:14px 32px;border-radius:999px;background:#004aad;color:#ffffff;text-decoration:none;font-weight:600;">Finish my order</a></td></tr></table>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:6px 0 6px;"><tr><td class="fika-mail-box">' .
			'<h2>Delivery</h2><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' .
			'<tr><td class="k">Inside Beirut:</td><td>1&ndash;2 business days</td></tr>' .
			'<tr><td class="k">Outside Beirut:</td><td>2&ndash;3 business days</td></tr>' .
			'<tr><td class="k">Payment:</td><td>Cash on delivery</td></tr>' .
			'</table></td></tr></table>' .
			'<p style="margin-top:22px !important;text-align:center;color:#6c7b9c !important;font-size:13px !important;">This is the only reminder we send for this bag. <a href="' . esc_url( fika_bag_link( $token, 'fika_bag_stop' ) ) . '" style="color:#6c7b9c !important;">No more reminders</a>.</p>';
		return WC()->mailer()->wrap_message( 'Your bag is waiting', $body );
	}
	// has this email placed an order since the time given?
	function fika_bag_ordered_since( $email, $since ) {
		$orders = wc_get_orders( array(
			'billing_email' => $email,
			'date_created'  => '>' . (int) $since,
			'status'        => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-undelivered' ),
			'limit'         => 1,
			'return'        => 'ids',
		) );
		return ! empty( $orders );
	}
}

// ---------- saving the bag ----------
// the email typed on the checkout (WooCommerce sends it with the address)
add_action( 'woocommerce_store_api_cart_update_customer_from_request', function ( $customer, $request ) {
	$b     = $request->get_param( 'billing_address' );
	$email = is_array( $b ) ? ( isset( $b['email'] ) ? $b['email'] : '' ) : '';
	if ( ! $email ) {
		$email = $customer->get_billing_email();
	}
	if ( $email ) {
		fika_bag_remember( $email );
	}
}, 20, 2 );
// signed-in customers opening the checkout: we already know the email
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}
	$u = wp_get_current_user();
	fika_bag_remember( WC()->customer && WC()->customer->get_billing_email() ? WC()->customer->get_billing_email() : $u->user_email );
}, 30 );
// the bag changed later: keep the saved copy up to date. An empty bag is kept as empty (no reminder is sent for it),
// but not cancelled: the shop's Checkout button empties the bag and refills it in one go.
add_action( 'woocommerce_cart_updated', function () {
	if ( ! WC()->session ) {
		return;
	}
	$token = WC()->session->get( 'fika_bag_token' );
	$all   = fika_bags();
	if ( ! $token || ! isset( $all[ $token ] ) || 'waiting' !== $all[ $token ]['status'] ) {
		return;
	}
	$items = fika_bag_items();
	if ( $items === $all[ $token ]['items'] ) {
		return;
	}
	fika_bag_set( $token, array( 'items' => $items, 'updated' => time() ) );
	if ( $items ) {
		fika_bag_schedule( $token );
	}
} );
// an order was placed: no reminder (or count it as won back if a reminder went out)
$fika_bag_done = function ( $order ) {
	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
	if ( ! $order ) {
		return;
	}
	$email = strtolower( $order->get_billing_email() );
	$token = WC()->session ? WC()->session->get( 'fika_bag_token' ) : '';
	$all   = fika_bags();
	foreach ( $all as $t => $r ) {
		if ( $t !== $token && strtolower( isset( $r['email'] ) ? $r['email'] : '' ) !== $email ) {
			continue;
		}
		if ( 'waiting' === $r['status'] ) {
			$all[ $t ]['status'] = 'ordered';
		} elseif ( 'reminded' === $r['status'] ) {
			$all[ $t ]['status'] = 'won-back';
			$all[ $t ]['order']  = $order->get_id();
		} else {
			continue;
		}
		$all[ $t ]['updated'] = time();
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'fika_bag_reminder', array( $t ), 'fika' );
		}
	}
	fika_bags_save( $all );
	if ( WC()->session ) {
		WC()->session->set( 'fika_bag_token', null );
	}
};
add_action( 'woocommerce_store_api_checkout_order_processed', $fika_bag_done );
add_action( 'woocommerce_checkout_order_processed', $fika_bag_done );

// ---------- sending ----------
add_action( 'fika_bag_reminder', function ( $token ) {
	$all = fika_bags();
	if ( ! isset( $all[ $token ] ) || 'waiting' !== $all[ $token ]['status'] ) {
		return;
	}
	$r = $all[ $token ];
	if ( fika_bag_stopped( $r['email'] ) ) {
		fika_bag_set( $token, array( 'status' => 'stopped' ) );
		return;
	}
	if ( fika_bag_ordered_since( $r['email'], $r['created'] ) ) {
		fika_bag_set( $token, array( 'status' => 'ordered' ) );
		return;
	}
	foreach ( $all as $t => $o ) {
		if ( $t !== $token ? ( strtolower( $o['email'] ) === strtolower( $r['email'] ) ? (int) $o['sent'] > time() - 7 * DAY_IN_SECONDS : false ) : false ) {
			fika_bag_set( $token, array( 'status' => 'skipped' ) );
			return;
		}
	}
	$html = $r['items'] ? fika_bag_email_html( $token, $r ) : '';
	if ( ! $html ) {
		fika_bag_set( $token, array( 'status' => 'emptied' ) );
		return;
	}
	$ok = WC()->mailer()->send( $r['email'], 'Your Fika bag is waiting', $html );
	fika_bag_set( $token, array( 'status' => 'reminded', 'sent' => time(), 'mail_ok' => $ok ? 1 : 0 ) );
} );

// ---------- links in the email ----------
add_action( 'template_redirect', function () {
	// "Finish my order": refill the bag and open the checkout
	if ( isset( $_GET['fika_bag'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$token = sanitize_text_field( wp_unslash( $_GET['fika_bag'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$all   = fika_bags();
		if ( isset( $all[ $token ] ) ? in_array( $all[ $token ]['status'], array( 'waiting', 'reminded' ), true ) : false ) {
			$r = $all[ $token ];
			WC()->cart->empty_cart();
			foreach ( $r['items'] as $it ) {
				WC()->cart->add_to_cart( $it['id'], $it['qty'], $it['var'] );
			}
			if ( WC()->customer ) {
				WC()->customer->set_billing_email( $r['email'] );
				WC()->customer->save();
			}
			WC()->session->set( 'fika_bag_token', $token );
			// the shop pages keep their own copy of the bag in the browser; fill it too
			$bag = array();
			foreach ( $r['items'] as $it ) {
				$bag[ ( has_term( 'ready-mix', 'product_cat', $it['id'] ) ? 'rw' : 'w' ) . $it['id'] ] = $it['qty'] * ( has_term( 'ready-mix', 'product_cat', $it['id'] ) ? 500 : 100 );
			}
			$to = wc_get_checkout_url();
			echo '<!doctype html><meta charset="utf-8"><title>Fika</title><script>try{var k="fika_bag_v1",s=JSON.parse(localStorage.getItem(k)||"null")||{bag:{},meta:{}};s.bag=' . wp_json_encode( $bag ) . ';localStorage.setItem(k,JSON.stringify(s));}catch(e){}location.replace(' . wp_json_encode( $to ) . ');</script><a href="' . esc_url( $to ) . '">Continue to checkout</a>';
			exit;
		}
		wp_safe_redirect( home_url( '/mix-your-own/' ) );
		exit;
	}
	// "No more reminders"
	if ( isset( $_GET['fika_bag_stop'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$token = sanitize_text_field( wp_unslash( $_GET['fika_bag_stop'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$all   = fika_bags();
		if ( isset( $all[ $token ] ) ) {
			$stop   = get_option( 'fika_bag_stop', array() );
			$stop   = is_array( $stop ) ? $stop : array();
			$stop[] = md5( strtolower( $all[ $token ]['email'] ) );
			update_option( 'fika_bag_stop', array_values( array_unique( $stop ) ), false );
			fika_bag_set( $token, array( 'status' => 'stopped' ) );
		}
		wp_die( '<h1 style="font-family:sans-serif;color:#004aad">Done</h1><p style="font-family:sans-serif">We won&rsquo;t send you bag reminders any more. <a href="' . esc_url( home_url( '/' ) ) . '">Back to Fika</a></p>', 'Fika', array( 'response' => 200 ) );
	}
}, 5 );

// ---------- WP Admin: listed on WooCommerce > Checkout leavers ----------
add_action( 'fika_checkout_leavers_after', function () {
	$all = fika_bags();
	uasort( $all, function ( $a, $b ) { return (int) $b['updated'] - (int) $a['updated']; } );
	$count = array( 'waiting' => 0, 'reminded' => 0, 'won-back' => 0, 'ordered' => 0 );
	foreach ( $all as $r ) {
		if ( isset( $count[ $r['status'] ] ) ) {
			$count[ $r['status'] ]++;
		}
	}
	$label = array(
		'waiting'  => 'Waiting (reminder due)',
		'reminded' => 'Reminder sent',
		'won-back' => 'Ordered after the reminder',
		'ordered'  => 'Ordered (no reminder needed)',
		'emptied'  => 'Bag emptied',
		'skipped'  => 'Skipped (reminded in the last 7 days)',
		'stopped'  => 'Asked for no reminders',
	);
	?>
	<h2>Saved bags and reminders</h2>
	<p>Shoppers who typed their email on the checkout (or opened it signed in). If they don&rsquo;t order within an hour, they get one
	&ldquo;Your bag is waiting&rdquo; email with a button that refills their bag. Kept 30 days.
	Reminders sent: <strong><?php echo (int) ( $count['reminded'] + $count['won-back'] ); ?></strong> &middot;
	orders after a reminder: <strong><?php echo (int) $count['won-back']; ?></strong>.</p>
	<table class="widefat striped" style="max-width:900px">
		<thead><tr><th>Email</th><th>Bag</th><th style="width:150px">Last activity</th><th style="width:210px">Status</th></tr></thead>
		<tbody>
		<?php if ( ! $all ) : ?>
			<tr><td colspan="4">No saved bags yet.</td></tr>
		<?php endif; ?>
		<?php foreach ( array_slice( $all, 0, 100, true ) as $r ) : ?>
			<?php
			$bits = array();
			foreach ( $r['items'] as $it ) {
				$p      = wc_get_product( $it['var'] ? $it['var'] : $it['id'] );
				$bits[] = ( $p ? $p->get_name() : '#' . $it['id'] ) . ' ' . fika_bag_amount( $it['id'], $it['qty'] );
			}
			?>
			<tr>
				<td><?php echo esc_html( $r['email'] ); ?></td>
				<td><?php echo esc_html( implode( ', ', $bits ) ); ?></td>
				<td><?php echo esc_html( wp_date( 'j M, H:i', (int) $r['updated'] ) ); ?></td>
				<td><?php echo esc_html( isset( $label[ $r['status'] ] ) ? $label[ $r['status'] ] : $r['status'] ); ?><?php echo ! empty( $r['order'] ) ? ' (#' . (int) $r['order'] . ')' : ''; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
} );

// ---------- for testing (shop managers only) ----------
add_action( 'rest_api_init', function () {
	$can = function () { return current_user_can( 'manage_woocommerce' ); };
	register_rest_route( 'fika/v1', '/saved-bags', array( 'methods' => 'GET', 'permission_callback' => $can, 'callback' => function () {
		$out = array();
		foreach ( fika_bags() as $t => $r ) {
			$r['next'] = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( 'fika_bag_reminder', array( $t ), 'fika' ) : null;
			$out[ $t ] = $r;
		}
		return $out;
	} ) );
	// send a waiting reminder now instead of in an hour; ?preview=1 returns the email instead of sending
	register_rest_route( 'fika/v1', '/saved-bags/(?P<token>[A-Za-z0-9]+)/send', array( 'methods' => 'POST', 'permission_callback' => $can, 'callback' => function ( $req ) {
		$t   = $req['token'];
		$all = fika_bags();
		if ( ! isset( $all[ $t ] ) ) {
			return new WP_Error( 'fika_bag', 'Not found', array( 'status' => 404 ) );
		}
		if ( $req->get_param( 'preview' ) ) {
			return array( 'html' => fika_bag_email_html( $t, $all[ $t ] ) );
		}
		do_action( 'fika_bag_reminder', $t );
		$all = fika_bags();
		return $all[ $t ];
	} ) );
	// remove saved bags (and "no more reminders" entries) of test addresses (@example.com / .org / .net)
	register_rest_route( 'fika/v1', '/saved-bags/test', array( 'methods' => 'DELETE', 'permission_callback' => $can, 'callback' => function () {
		$all  = fika_bags();
		$stop = get_option( 'fika_bag_stop', array() );
		$stop = is_array( $stop ) ? $stop : array();
		$n    = 0;
		foreach ( $all as $t => $r ) {
			if ( preg_match( '/@example\.(com|org|net)$/i', $r['email'] ) ) {
				$stop = array_values( array_diff( $stop, array( md5( strtolower( $r['email'] ) ) ) ) );
				unset( $all[ $t ] );
				if ( function_exists( 'as_unschedule_all_actions' ) ) {
					as_unschedule_all_actions( 'fika_bag_reminder', array( $t ), 'fika' );
				}
				$n++;
			}
		}
		update_option( 'fika_saved_bags', $all, false );
		update_option( 'fika_bag_stop', $stop, false );
		return array( 'removed' => $n );
	} ) );
} );
