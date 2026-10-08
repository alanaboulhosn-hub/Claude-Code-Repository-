<?php
/**
 * Fika: the shop dashboard (WP Admin > Fika dashboard, and a summary box on the WordPress Dashboard).
 * One page with what the owner needs, for the last 7 / 30 / 90 days or all time:
 * - Launch health: email sending, search engines, Meta pixel, win-back mode, privacy page, New order email,
 *   failed background jobs.
 * - Sales: revenue (sweets), delivery fees, orders, average order, kg sold, new / returning customers, estimated
 *   gross profit (landing cost per kg is set on the page), compared with the period before; a daily sales chart.
 * - Top sweets by kg; delivery areas (inside / outside Beirut).
 * - Leading customers (all time): orders, kg, spend, last order, rewards progress.
 * - Rewards: claimed / used per checkpoint, mystery tastes, discount codes, what they cost at landing cost.
 * - Bag recovery: saved bags, reminders sent, orders that came back.
 * - What customers tell us: why shoppers left the checkout (before-you-go answers), refused / cancelled /
 *   refunded orders, the latest order notes from customers. (WhatsApp / email messages are not on the site.)
 * Orders whose note contains "TEST ORDER" are left out. Figures are cached for 10 minutes ("Refresh" recounts).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-dashboard.php
 */

if ( ! function_exists( 'fika_dash_data' ) ) {
	function fika_dash_landing() {
		return (float) get_option( 'fika_landing_cost', 16 );
	}
	// grams in an order as packed: candies 100 g per unit, Ready Mix 500 g per bag, the free mystery taste 50 g
	function fika_dash_grams_by_product( $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			$g   = $item->get_meta( '_fika_taste' ) ? 50 : (int) $item->get_quantity() * ( has_term( 'ready-mix', 'product_cat', $pid ) ? 500 : 100 );
			$out[ $pid ] = ( $out[ $pid ] ?? 0 ) + $g;
		}
		return $out;
	}
	function fika_dash_is_test( $order ) {
		return apply_filters( 'fika_dash_hide_tests', true ) ? false !== stripos( (string) $order->get_customer_note(), 'TEST ORDER' ) : false;
	}
	function fika_dash_orders( $from, $to, $statuses ) {
		$args = array( 'limit' => -1, 'type' => 'shop_order', 'status' => $statuses, 'orderby' => 'date', 'order' => 'ASC' );
		if ( $from ) {
			$args['date_created'] = $from . '...' . $to;
		}
		return array_values( array_filter( wc_get_orders( $args ), function ( $o ) { return ! fika_dash_is_test( $o ); } ) );
	}
	// sales figures for a period (timestamps; $from 0 = all time)
	function fika_dash_period( $from, $to ) {
		$sold    = fika_dash_orders( $from, $to, array( 'wc-processing', 'wc-on-hold', 'wc-completed' ) );
		$lost    = fika_dash_orders( $from, $to, array( 'wc-undelivered', 'wc-cancelled', 'wc-refunded', 'wc-failed' ) );
		$r       = array( 'orders' => count( $sold ), 'sweets' => 0.0, 'delivery' => 0.0, 'discounts' => 0.0, 'grams' => 0, 'new' => 0, 'returning' => 0, 'inside' => 0, 'outside' => 0, 'lost' => array(), 'days' => array(), 'products' => array() );
		$seen    = array();
		foreach ( $sold as $o ) {
			$sweets          = (float) $o->get_subtotal() - (float) $o->get_discount_total();
			$r['sweets']    += $sweets;
			$r['delivery']  += (float) $o->get_shipping_total();
			$r['discounts'] += (float) $o->get_discount_total();
			foreach ( fika_dash_grams_by_product( $o ) as $pid => $g ) {
				$r['grams']              += $g;
				$r['products'][ $pid ]    = ( $r['products'][ $pid ] ?? 0 ) + $g;
			}
			$day                = wp_date( 'Y-m-d', $o->get_date_created()->getTimestamp() );
			$r['days'][ $day ]  = ( $r['days'][ $day ] ?? 0 ) + $sweets + (float) $o->get_shipping_total();
			$state = $o->get_shipping_state();
			if ( 'BA' === $state ) {
				$r['inside']++;
			} elseif ( $state ) {
				$r['outside']++;
			}
			// new or returning: did this email order before this order?
			$email = strtolower( $o->get_billing_email() );
			if ( ! isset( $seen[ $email ] ) ) {
				$before        = wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'type' => 'shop_order', 'billing_email' => $o->get_billing_email(), 'status' => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ), 'date_created' => '<' . $o->get_date_created()->getTimestamp() ) );
				$seen[ $email ] = $before ? 'returning' : 'new';
				$r[ $seen[ $email ] ]++;
			}
		}
		foreach ( $lost as $o ) {
			$r['lost'][ $o->get_status() ] = ( $r['lost'][ $o->get_status() ] ?? 0 ) + 1;
		}
		$r['lost_orders'] = array_slice( array_reverse( $lost ), 0, 10 );
		return $r;
	}
	function fika_dash_data( $days ) {
		$key  = 'fika_dash_' . $days;
		$data = get_transient( $key );
		if ( $data && empty( $_GET['refresh'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $data;
		}
		$now  = time();
		$from = $days ? $now - $days * DAY_IN_SECONDS : 0;
		$data = array( 'at' => $now, 'days' => $days, 'cur' => fika_dash_period( $from, $now ) );
		if ( $days ) {
			$data['prev'] = fika_dash_period( $from - $days * DAY_IN_SECONDS, $from );
		}
		// leading customers (all time)
		$people = array();
		foreach ( fika_dash_orders( 0, $now, array( 'wc-processing', 'wc-on-hold', 'wc-completed' ) ) as $o ) {
			$e = strtolower( $o->get_billing_email() );
			if ( ! isset( $people[ $e ] ) ) {
				$people[ $e ] = array( 'email' => $o->get_billing_email(), 'name' => trim( $o->get_shipping_first_name() . ' ' . $o->get_shipping_last_name() ), 'phone' => $o->get_billing_phone(), 'uid' => $o->get_customer_id(), 'orders' => 0, 'spend' => 0.0, 'grams' => 0, 'last' => 0, 'area' => '' );
			}
			$p                = &$people[ $e ];
			$p['orders']++;
			$p['spend']      += (float) $o->get_subtotal() - (float) $o->get_discount_total();
			$p['grams']      += array_sum( fika_dash_grams_by_product( $o ) );
			$p['last']        = max( $p['last'], $o->get_date_created()->getTimestamp() );
			$p['area']        = 'BA' === $o->get_shipping_state() ? 'Inside Beirut' : 'Outside Beirut';
			if ( $o->get_customer_id() ) {
				$p['uid'] = $o->get_customer_id();
			}
			unset( $p );
		}
		uasort( $people, function ( $a, $b ) { return $b['spend'] <=> $a['spend']; } );
		$data['people']      = array_slice( array_values( $people ), 0, 15 );
		$data['customers']   = count( $people );
		$data['repeat']      = count( array_filter( $people, function ( $p ) { return $p['orders'] > 1; } ) );
		$data['accounts']    = count( get_users( array( 'role' => 'customer', 'fields' => 'ID' ) ) );
		// rewards and mystery tastes
		$rw = array();
		foreach ( get_users( array( 'meta_key' => 'fika_swim_claims', 'fields' => 'ID' ) ) as $uid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( (array) get_user_meta( $uid, 'fika_swim_claims', true ) as $cl ) {
				if ( empty( $cl['code'] ) ) {
					continue;
				}
				$g              = (int) $cl['g'];
				$rw[ $g ]       = $rw[ $g ] ?? array( 'claimed' => 0, 'used' => 0 );
				$rw[ $g ]['claimed']++;
				$c = new WC_Coupon( $cl['code'] );
				if ( $c->get_id() && $c->get_usage_count() > 0 ) {
					$rw[ $g ]['used']++;
				}
			}
		}
		$data['rewards'] = $rw;
		$ts              = array( 'won' => 0, 'used' => 0 );
		foreach ( get_users( array( 'meta_key' => 'fika_tastes', 'fields' => 'ID' ) ) as $uid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( (array) get_user_meta( $uid, 'fika_tastes', true ) as $t ) {
				if ( isset( $t['status'] ) ) {
					$ts[ 'used' === $t['status'] ? 'used' : 'won' ]++;
				}
			}
		}
		$data['tastes'] = $ts;
		$codes          = array();
		foreach ( array( 'fika10' => 'FIKA10 (before you go, 10%)' ) as $code => $label ) {
			$c               = new WC_Coupon( $code );
			$codes[ $label ] = $c->get_id() ? $c->get_usage_count() : 0;
		}
		$wb = 0;
		foreach ( get_posts( array( 'post_type' => 'shop_coupon', 'posts_per_page' => -1, 'fields' => 'ids', 's' => 'COMEBACK-' ) ) as $cid ) {
			$c   = new WC_Coupon( $cid );
			$wb += $c->get_usage_count();
		}
		$codes['Win-back codes (COMEBACK-)'] = $wb;
		$data['codes']                       = $codes;
		// bag recovery
		$bags = array( 'waiting' => 0, 'reminded' => 0, 'won-back' => 0, 'ordered' => 0, 'stopped' => 0 );
		foreach ( (array) get_option( 'fika_saved_bags', array() ) as $b ) {
			$st          = $b['status'] ?? 'waiting';
			$bags[ $st ] = ( $bags[ $st ] ?? 0 ) + 1;
		}
		$data['bags'] = $bags;
		// what customers tell us
		$data['exit']       = (array) get_option( 'fika_exit_reasons', array() );
		$data['exit_since'] = (int) get_option( 'fika_exit_reasons_since', 0 );
		$notes = array();
		foreach ( wc_get_orders( array( 'limit' => 40, 'type' => 'shop_order', 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			$n = trim( (string) $o->get_customer_note() );
			if ( '' !== $n && ! fika_dash_is_test( $o ) ) {
				$notes[] = array( 'id' => $o->get_id(), 'date' => $o->get_date_created()->getTimestamp(), 'who' => trim( $o->get_shipping_first_name() . ' ' . $o->get_shipping_last_name() ), 'note' => $n );
			}
			if ( count( $notes ) >= 8 ) {
				break;
			}
		}
		$data['notes'] = $notes;
		set_transient( $key, $data, 10 * MINUTE_IN_SECONDS );
		return $data;
	}
	// launch health checks
	function fika_dash_health() {
		$out  = array();
		$smtp = get_option( 'wp_mail_smtp', array() );
		$ok   = isset( $smtp['mail']['mailer'] ) && 'smtp' === $smtp['mail']['mailer'] && ! empty( $smtp['smtp']['pass'] );
		$out[] = array( $ok, 'Emails sent through the hello@swedishfikalb.com mailbox', $ok ? 'Connected (SMTP).' : 'Not yet: the site sends through the web server ("via srv1317"), so emails can land in spam. WP Mail SMTP > enter the mailbox password.', admin_url( 'admin.php?page=wp-mail-smtp' ) );
		$pub   = (int) get_option( 'blog_public' );
		$out[] = array( $pub ? true : null, 'Search engines', $pub ? 'Google can list the shop.' : 'Hidden from Google (right for the test site). Untick "Discourage search engines" at launch.', admin_url( 'options-reading.php' ) );
		$m     = (array) get_option( 'fika_meta', array() );
		$mon   = ! empty( $m['on'] ) && ! empty( $m['pixel'] ) && ! empty( $m['token'] );
		$out[] = array( $mon ? true : null, 'Meta Pixel + Conversions API', $mon ? 'On' . ( ! empty( $m['test'] ) ? ', with a test event code (remove it at launch).' : '.' ) : 'Installed, waiting for the pixel ID and token (at launch).', admin_url( 'admin.php?page=fika-meta' ) );
		$wb    = (array) get_option( 'fika_winback_settings', array() );
		$mode  = $wb['mode'] ?? 'test';
		$out[] = array( null, 'Win-back emails', 'Mode: ' . ucfirst( $mode ) . ( 'live' === $mode ? ' (sending to customers).' : ' (not sent to customers).' ), admin_url( 'admin.php?page=fika-winback' ) );
		$pp    = (int) get_option( 'wp_page_for_privacy_policy' );
		$pst   = $pp ? get_post_status( $pp ) : '';
		$out[] = array( 'publish' === $pst ? true : null, 'Privacy policy', 'publish' === $pst ? 'Published.' : 'Draft (kept as a draft until you decide).', $pp ? admin_url( 'post.php?post=' . $pp . '&action=edit' ) : '' );
		$no    = (array) get_option( 'woocommerce_new_order_settings', array() );
		$non   = 'yes' === ( $no['enabled'] ?? 'yes' );
		$out[] = array( $non ? true : null, '"New order" email to the shop', $non ? 'On: you get an email for every order.' : 'Off: check new orders in WooCommerce > Orders.', admin_url( 'admin.php?page=wc-settings&tab=email&section=wc_email_new_order' ) );
		global $wpdb;
		$failed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'failed' AND hook LIKE 'fika%'" );
		$late   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'pending' AND hook LIKE 'fika%' AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 2 HOUR" );
		$out[]  = array( ! $failed && ! $late, 'Background jobs (bag reminders, win-back, Meta)', ( $failed || $late ) ? $failed . ' failed, ' . $late . ' late.' : 'Running on time.', admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=fika' ) );
		return $out;
	}
}

// ---------- the page ----------
add_action( 'admin_menu', function () {
	add_menu_page( 'Fika dashboard', 'Fika dashboard', 'manage_woocommerce', 'fika-dashboard', 'fika_dash_page', 'dashicons-chart-area', 3 );
} );
function fika_dash_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( isset( $_POST['fika_landing'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fika_dash' ) ) {
		update_option( 'fika_landing_cost', max( 0, (float) wp_unslash( $_POST['fika_landing'] ) ), false );
		foreach ( array( 7, 30, 90, 0 ) as $d ) {
			delete_transient( 'fika_dash_' . $d );
		}
	}
	$days  = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification
	$days  = in_array( $days, array( 7, 30, 90, 0 ), true ) ? $days : 30;
	$d     = fika_dash_data( $days );
	$c     = $d['cur'];
	$p     = $d['prev'] ?? null;
	$land  = fika_dash_landing();
	$money = function ( $x ) { return '$' . number_format( (float) $x, 2 ); };
	$kg    = function ( $g ) { return rtrim( rtrim( number_format( $g / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg'; };
	$delta = function ( $now, $before ) use ( $p ) {
		if ( ! $p ) {
			return '';
		}
		if ( ! $before ) {
			return $now ? '<span class="fd-up">new</span>' : '';
		}
		$ch = round( 100 * ( $now - $before ) / $before );
		return '<span class="' . ( $ch >= 0 ? 'fd-up' : 'fd-down' ) . '">' . ( $ch >= 0 ? '+' : '' ) . $ch . '%</span>';
	};
	$profit  = function ( $per ) use ( $land ) { return $per['sweets'] - $per['grams'] / 1000 * $land; };
	$aov     = $c['orders'] ? ( $c['sweets'] + $c['delivery'] ) / $c['orders'] : 0;
	$aov_p   = ( $p && $p['orders'] ) ? ( $p['sweets'] + $p['delivery'] ) / $p['orders'] : 0;
	$label   = $days ? 'last ' . $days . ' days' : 'all time';
	$url     = admin_url( 'admin.php?page=fika-dashboard' );
	$cps     = function_exists( 'fika_swim_checkpoints' ) ? fika_swim_checkpoints() : array();
	$reasons = array( 'delivery-price' => 'Delivery is too expensive', 'delivery-time' => 'Delivery takes too long', 'product-price' => 'The candies are too expensive', 'change-order' => 'I want to change my order', 'other' => 'Another reason' );
	$etotal  = 0;
	foreach ( $reasons as $k => $l ) {
		$etotal += (int) ( $d['exit'][ $k ] ?? 0 );
	}
	?>
<style>
.fd { max-width: 1240px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1b2a4a; }
.fd h1 { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 26px; }
.fd h1 small { font-size: 13px; font-weight: 400; color: #6b7894; }
.fd h2 { margin: 0 0 12px; font-size: 16px; color: #004aad; }
.fd .fd-tabs { display: flex; gap: 6px; margin: 4px 0 18px; flex-wrap: wrap; }
.fd .fd-tabs a { padding: 6px 14px; border-radius: 999px; border: 1px solid #c9d4ea; background: #fff; color: #004aad; text-decoration: none; font-weight: 600; }
.fd .fd-tabs a.on { background: #004aad; color: #fff; border-color: #004aad; }
.fd .fd-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 16px; }
.fd .fd-box { grid-column: span 6; box-sizing: border-box; padding: 18px 20px; border-radius: 14px; background: #fff; border: 1px solid #e3e9f5; }
.fd .fd-box.w12 { grid-column: span 12; } .fd .fd-box.w4 { grid-column: span 4; } .fd .fd-box.w8 { grid-column: span 8; }
.fd .fd-kpis { grid-column: span 12; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.fd .fd-kpi { padding: 14px 16px; border-radius: 14px; background: #fff; border: 1px solid #e3e9f5; }
.fd .fd-kpi span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #6b7894; }
.fd .fd-kpi b { display: block; margin: 4px 0 2px; font-size: 24px; color: #004aad; }
.fd .fd-kpi em { font-style: normal; font-size: 12px; color: #6b7894; }
.fd .fd-up { color: #1e8a4c; font-weight: 600; } .fd .fd-down { color: #b42318; font-weight: 600; }
.fd table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.fd th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: #6b7894; font-weight: 600; padding: 6px 8px; border-bottom: 1px solid #e3e9f5; }
.fd td { padding: 7px 8px; border-bottom: 1px solid #f1f4fa; vertical-align: top; }
.fd .fd-bar { height: 8px; border-radius: 4px; background: #ff6fa5; }
.fd .fd-health td:first-child { width: 26px; font-size: 16px; }
.fd .ok { color: #1e8a4c; } .fd .warn { color: #c77700; } .fd .bad { color: #b42318; }
.fd .fd-chart { display: flex; align-items: flex-end; gap: 2px; height: 150px; padding-top: 6px; border-bottom: 1px solid #e3e9f5; }
.fd .fd-chart i { flex: 1; min-width: 2px; border-radius: 3px 3px 0 0; background: #4a8ae0; position: relative; }
.fd .fd-chart i:hover { background: #004aad; }
.fd .fd-axis { display: flex; justify-content: space-between; font-size: 11px; color: #6b7894; margin-top: 4px; }
.fd .fd-muted { color: #6b7894; font-size: 12.5px; }
.fd .fd-empty { color: #6b7894; font-style: italic; }
@media (max-width: 1100px) { .fd .fd-box, .fd .fd-box.w4, .fd .fd-box.w8 { grid-column: span 12; } }
</style>
<div class="wrap fd">
	<h1>Fika dashboard <small>Figures for the <?php echo esc_html( $label ); ?> &middot; updated <?php echo esc_html( human_time_diff( $d['at'] ) ); ?> ago &middot; <a href="<?php echo esc_url( add_query_arg( array( 'days' => $days, 'refresh' => 1 ), $url ) ); ?>">Refresh</a></small></h1>
	<div class="fd-tabs">
		<?php foreach ( array( 7 => '7 days', 30 => '30 days', 90 => '90 days', 0 => 'All time' ) as $v => $t ) : ?>
		<a class="<?php echo $v === $days ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $v, $url ) ); ?>"><?php echo esc_html( $t ); ?></a>
		<?php endforeach; ?>
	</div>
	<div class="fd-grid">
		<div class="fd-kpis">
			<div class="fd-kpi"><span>Sales (sweets)</span><b><?php echo esc_html( $money( $c['sweets'] ) ); ?></b><em><?php echo wp_kses_post( $delta( $c['sweets'], $p['sweets'] ?? 0 ) ); ?> after discounts</em></div>
			<div class="fd-kpi"><span>Orders</span><b><?php echo (int) $c['orders']; ?></b><em><?php echo wp_kses_post( $delta( $c['orders'], $p['orders'] ?? 0 ) ); ?></em></div>
			<div class="fd-kpi"><span>Average order</span><b><?php echo esc_html( $money( $aov ) ); ?></b><em><?php echo wp_kses_post( $delta( $aov, $aov_p ) ); ?> with delivery</em></div>
			<div class="fd-kpi"><span>Sweets sold</span><b><?php echo esc_html( $kg( $c['grams'] ) ); ?></b><em><?php echo $c['orders'] ? esc_html( $kg( $c['grams'] / $c['orders'] ) . ' per order' ) : ''; ?></em></div>
			<div class="fd-kpi"><span>Est. gross profit</span><b><?php echo esc_html( $money( $profit( $c ) ) ); ?></b><em><?php echo wp_kses_post( $p ? $delta( $profit( $c ), $profit( $p ) ) : '' ); ?> at <?php echo esc_html( $money( $land ) ); ?>/kg landing</em></div>
			<div class="fd-kpi"><span>Delivery fees</span><b><?php echo esc_html( $money( $c['delivery'] ) ); ?></b><em>paid by customers</em></div>
			<div class="fd-kpi"><span>Customers</span><b><?php echo (int) ( $c['new'] + $c['returning'] ); ?></b><em><?php echo (int) $c['new']; ?> new &middot; <?php echo (int) $c['returning']; ?> returning</em></div>
			<div class="fd-kpi"><span>Refused / cancelled</span><b><?php echo (int) array_sum( $c['lost'] ); ?></b><em><?php echo $c['orders'] + array_sum( $c['lost'] ) ? (int) round( 100 * array_sum( $c['lost'] ) / ( $c['orders'] + array_sum( $c['lost'] ) ) ) . '% of orders' : ''; ?></em></div>
		</div>

		<div class="fd-box w8">
			<h2>Daily sales (<?php echo esc_html( $label ); ?>)</h2>
			<?php
			$span = $days ? $days : max( 30, $c['days'] ? (int) ceil( ( time() - strtotime( array_key_first( $c['days'] ) ) ) / DAY_IN_SECONDS ) + 1 : 30 );
			$span = min( 180, $span );
			$vals = array();
			for ( $i = $span - 1; $i >= 0; $i-- ) {
				$day           = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
				$vals[ $day ]  = $c['days'][ $day ] ?? 0;
			}
			$max = max( 1, max( $vals ) );
			?>
			<?php if ( array_sum( $vals ) > 0 ) : ?>
			<div class="fd-chart"><?php foreach ( $vals as $day => $v ) : ?><i style="height:<?php echo esc_attr( max( 1, round( 100 * $v / $max ) ) ); ?>%" title="<?php echo esc_attr( wp_date( 'j M', strtotime( $day ) ) . ': ' . $money( $v ) ); ?>"></i><?php endforeach; ?></div>
			<div class="fd-axis"><span><?php echo esc_html( wp_date( 'j M', strtotime( array_key_first( $vals ) ) ) ); ?></span><span>best day <?php echo esc_html( $money( $max ) ); ?></span><span><?php echo esc_html( wp_date( 'j M' ) ); ?></span></div>
			<?php else : ?><p class="fd-empty">No orders in this period yet.</p><?php endif; ?>
		</div>

		<div class="fd-box w4">
			<h2>Launch health</h2>
			<table class="fd-health"><tbody>
			<?php foreach ( fika_dash_health() as $h ) : ?>
				<tr><td class="<?php echo true === $h[0] ? 'ok' : ( false === $h[0] ? 'bad' : 'warn' ); ?>"><?php echo true === $h[0] ? '&#10004;' : ( false === $h[0] ? '&#10006;' : '&#9679;' ); ?></td>
				<td><b><?php echo esc_html( $h[1] ); ?></b><br><span class="fd-muted"><?php echo esc_html( $h[2] ); ?></span><?php if ( $h[3] ) : ?> <a href="<?php echo esc_url( $h[3] ); ?>">Open</a><?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>

		<div class="fd-box">
			<h2>Top sweets by weight (<?php echo esc_html( $label ); ?>)</h2>
			<?php
			arsort( $c['products'] );
			$top  = array_slice( $c['products'], 0, 10, true );
			$tmax = $top ? max( $top ) : 1;
			if ( $top ) :
				?>
			<table><thead><tr><th>Sweet</th><th style="width:40%"></th><th>Sold</th></tr></thead><tbody>
				<?php foreach ( $top as $pid => $g ) : $pp = wc_get_product( $pid ); ?>
				<tr><td><?php echo esc_html( $pp ? $pp->get_name() : '#' . $pid ); ?></td><td><div class="fd-bar" style="width:<?php echo esc_attr( round( 100 * $g / $tmax ) ); ?>%"></div></td><td><?php echo esc_html( $kg( $g ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php else : ?><p class="fd-empty">No sweets sold in this period yet.</p><?php endif; ?>
		</div>

		<div class="fd-box">
			<h2>Delivery areas (<?php echo esc_html( $label ); ?>)</h2>
			<?php $at = $c['inside'] + $c['outside']; ?>
			<?php if ( $at ) : ?>
			<table><tbody>
				<tr><td>Inside Beirut ($5)</td><td style="width:45%"><div class="fd-bar" style="width:<?php echo esc_attr( round( 100 * $c['inside'] / $at ) ); ?>%;background:#4a8ae0"></div></td><td><?php echo (int) $c['inside']; ?> (<?php echo (int) round( 100 * $c['inside'] / $at ); ?>%)</td></tr>
				<tr><td>Outside Beirut ($6)</td><td><div class="fd-bar" style="width:<?php echo esc_attr( round( 100 * $c['outside'] / $at ) ); ?>%;background:#ffb02e"></div></td><td><?php echo (int) $c['outside']; ?> (<?php echo (int) round( 100 * $c['outside'] / $at ); ?>%)</td></tr>
			</tbody></table>
			<?php else : ?><p class="fd-empty">No deliveries in this period yet.</p><?php endif; ?>
			<h2 style="margin-top:18px">Customers (all time)</h2>
			<p><?php echo (int) $d['customers']; ?> people ordered &middot; <?php echo (int) $d['repeat']; ?> ordered more than once (<?php echo $d['customers'] ? (int) round( 100 * $d['repeat'] / $d['customers'] ) : 0; ?>%) &middot; <?php echo (int) $d['accounts']; ?> accounts</p>
		</div>

		<div class="fd-box w12">
			<h2>Leading customers (all time, by spend)</h2>
			<?php if ( $d['people'] ) : ?>
			<table><thead><tr><th>Customer</th><th>Contact</th><th>Area</th><th>Orders</th><th>Sweets</th><th>Spent</th><th>Last order</th><th>Rewards lane</th></tr></thead><tbody>
				<?php foreach ( $d['people'] as $pr ) : ?>
				<tr><td><b><?php echo esc_html( $pr['name'] ? $pr['name'] : '—' ); ?></b><?php echo $pr['uid'] ? '' : ' <span class="fd-muted">(guest)</span>'; ?></td>
				<td><?php echo esc_html( $pr['email'] ); ?><br><span class="fd-muted"><?php echo esc_html( $pr['phone'] ); ?></span></td>
				<td><?php echo esc_html( $pr['area'] ); ?></td><td><?php echo (int) $pr['orders']; ?></td><td><?php echo esc_html( $kg( $pr['grams'] ) ); ?></td><td><?php echo esc_html( $money( $pr['spend'] ) ); ?></td>
				<td><?php echo esc_html( wp_date( 'j M Y', $pr['last'] ) ); ?><br><span class="fd-muted"><?php echo esc_html( human_time_diff( $pr['last'] ) ); ?> ago</span></td>
				<td><?php echo $pr['uid'] && function_exists( 'fika_swim_reach' ) ? esc_html( $kg( fika_swim_reach( $pr['uid'] ) ) ) . ' <span class="fd-muted">of 15 kg laps</span>' : '<span class="fd-muted">no account</span>'; ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php else : ?><p class="fd-empty">No customers yet. They will appear here with their first orders.</p><?php endif; ?>
		</div>

		<div class="fd-box">
			<h2>Rewards (all time)</h2>
			<table><thead><tr><th>Reward</th><th>Claimed</th><th>Used</th><th>Cost (landing)</th></tr></thead><tbody>
				<?php
				$rcost = 0;
				foreach ( $cps as $g => $cp ) :
					$rr     = $d['rewards'][ $g ] ?? array( 'claimed' => 0, 'used' => 0 );
					$cost   = $rr['used'] * ( $cp['rg'] / 1000 ) * $land;
					$rcost += $cost;
					?>
				<tr><td><?php echo esc_html( $cp['title'] . ' (' . $cp['kg'] . ' kg)' ); ?></td><td><?php echo (int) $rr['claimed']; ?></td><td><?php echo (int) $rr['used']; ?></td><td><?php echo esc_html( $money( $cost ) ); ?></td></tr>
				<?php endforeach; ?>
				<?php $tcost = $d['tastes']['used'] * 0.05 * $land; $rcost += $tcost; ?>
				<tr><td>Mystery tastes (50 g, 1.5 and 12.5 kg)</td><td><?php echo (int) ( $d['tastes']['won'] + $d['tastes']['used'] ); ?> spun</td><td><?php echo (int) $d['tastes']['used']; ?></td><td><?php echo esc_html( $money( $tcost ) ); ?></td></tr>
				<tr><td><b>Total</b></td><td></td><td></td><td><b><?php echo esc_html( $money( $rcost ) ); ?></b></td></tr>
			</tbody></table>
			<h2 style="margin-top:18px">Discount codes used (all time)</h2>
			<table><tbody><?php foreach ( $d['codes'] as $l => $n ) : ?><tr><td><?php echo esc_html( $l ); ?></td><td><?php echo (int) $n; ?></td></tr><?php endforeach; ?>
				<tr><td>Discounts given in the <?php echo esc_html( $label ); ?> (all codes and rewards)</td><td><?php echo esc_html( $money( $c['discounts'] ) ); ?></td></tr></tbody></table>
		</div>

		<div class="fd-box">
			<h2>Bag recovery (saved bags, last 30 days)</h2>
			<?php $b = $d['bags']; $sent = $b['reminded'] + $b['won-back']; ?>
			<table><tbody>
				<tr><td>Shoppers who left the checkout with an email typed in</td><td><?php echo (int) array_sum( $b ); ?></td></tr>
				<tr><td>Ordered by themselves (no reminder needed)</td><td><?php echo (int) $b['ordered']; ?></td></tr>
				<tr><td>"Your Fika bag is waiting" reminders sent</td><td><?php echo (int) $sent; ?></td></tr>
				<tr><td>Ordered after the reminder</td><td><b><?php echo (int) $b['won-back']; ?></b><?php echo $sent ? ' (' . (int) round( 100 * $b['won-back'] / $sent ) . '%)' : ''; ?></td></tr>
				<tr><td>Still waiting (reminder within the hour)</td><td><?php echo (int) $b['waiting']; ?></td></tr>
			</tbody></table>
			<p class="fd-muted">Details: <a href="<?php echo esc_url( admin_url( 'admin.php?page=fika-checkout-leavers' ) ); ?>">WooCommerce &gt; Checkout leavers</a></p>
		</div>

		<div class="fd-box">
			<h2>What customers tell us: why they left the checkout</h2>
			<?php if ( $etotal ) : ?>
			<table><tbody>
				<?php arsort( $d['exit'] ); foreach ( $reasons as $k => $l ) : $n = (int) ( $d['exit'][ $k ] ?? 0 ); ?>
				<tr><td><?php echo esc_html( $l ); ?></td><td style="width:40%"><div class="fd-bar" style="width:<?php echo esc_attr( round( 100 * $n / $etotal ) ); ?>%"></div></td><td><?php echo (int) $n; ?> (<?php echo (int) round( 100 * $n / $etotal ); ?>%)</td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<p class="fd-muted">10% offer: <?php echo (int) ( $d['exit']['offer-applied'] ?? 0 ); ?> used, <?php echo (int) ( $d['exit']['offer-declined'] ?? 0 ); ?> declined<?php echo $d['exit_since'] ? ' &middot; counting since ' . esc_html( wp_date( 'j M Y', $d['exit_since'] ) ) : ''; ?>.</p>
			<?php else : ?><p class="fd-empty">No answers yet. Shoppers who try to leave the checkout are asked why; their answers show here.</p><?php endif; ?>
		</div>

		<div class="fd-box">
			<h2>Problem orders (<?php echo esc_html( $label ); ?>)</h2>
			<?php if ( $c['lost_orders'] ) : ?>
			<table><thead><tr><th>Order</th><th>Status</th><th>Customer</th><th>Date</th></tr></thead><tbody>
				<?php foreach ( $c['lost_orders'] as $o ) : ?>
				<tr><td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a></td><td><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></td><td><?php echo esc_html( trim( $o->get_shipping_first_name() . ' ' . $o->get_shipping_last_name() ) ); ?></td><td><?php echo esc_html( wp_date( 'j M', $o->get_date_created()->getTimestamp() ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php else : ?><p class="fd-empty">No refused, cancelled or refunded orders in this period.</p><?php endif; ?>
			<h2 style="margin-top:18px">Latest notes from customers (at checkout)</h2>
			<?php if ( $d['notes'] ) : ?>
			<table><tbody><?php foreach ( $d['notes'] as $n ) : ?><tr><td style="width:80px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $n['id'] ) ); ?>">#<?php echo (int) $n['id']; ?></a><br><span class="fd-muted"><?php echo esc_html( wp_date( 'j M', $n['date'] ) ); ?></span></td><td><b><?php echo esc_html( $n['who'] ); ?></b><br><?php echo esc_html( wp_trim_words( $n['note'], 30 ) ); ?></td></tr><?php endforeach; ?></tbody></table>
			<?php else : ?><p class="fd-empty">No notes yet.</p><?php endif; ?>
			<p class="fd-muted">Messages on WhatsApp or by email are not on the site, so they do not show here.</p>
		</div>

		<div class="fd-box w12">
			<h2>Settings and more detail</h2>
			<form method="post" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
				<?php wp_nonce_field( 'fika_dash' ); ?>
				<label>Landing cost per kg (for the profit and reward costs): $ <input type="number" step="0.01" min="0" name="fika_landing" value="<?php echo esc_attr( $land ); ?>" style="width:90px"></label>
				<button class="button">Save</button>
			</form>
			<p class="fd-muted" style="margin-top:12px">More detail:
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>">Orders</a> &middot;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fika-customers' ) ); ?>">Fika customers</a> &middot;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ) ); ?>">WooCommerce Analytics</a> &middot;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fika-checkout-leavers' ) ); ?>">Checkout leavers</a> &middot;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fika-winback' ) ); ?>">Win-back emails</a> &middot;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fika-meta' ) ); ?>">Meta pixel</a> &middot;
				Ads and visitors: Meta Events Manager (once the pixel is on).</p>
		</div>
	</div>
</div>
	<?php
}

// ---------- a summary on the WordPress Dashboard ----------
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'fika_dash_widget', 'Fika: last 7 days', function () {
		$d = fika_dash_data( 7 );
		$c = $d['cur'];
		echo '<p style="font-size:14px;line-height:1.7">';
		echo '<b>$' . esc_html( number_format( $c['sweets'], 2 ) ) . '</b> in sweets &middot; <b>' . (int) $c['orders'] . '</b> orders &middot; <b>' . esc_html( rtrim( rtrim( number_format( $c['grams'] / 1000, 1, '.', '' ), '0' ), '.' ) ) . ' kg</b> sold<br>';
		echo (int) $c['new'] . ' new and ' . (int) $c['returning'] . ' returning customers &middot; ' . (int) array_sum( $c['lost'] ) . ' refused or cancelled';
		echo '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=fika-dashboard' ) ) . '">Open the Fika dashboard</a></p>';
	} );
} );
