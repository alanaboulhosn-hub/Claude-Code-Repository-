<?php
/**
 * Fika: "Swim to your rewards" — the loyalty tracker for signed-in customers.
 * - A cartoon Swedish fish swims along a 15 kg water lane with four checkpoints, each a gift of free sweets:
 *     3 kg  -> 100 g on us ($2.80 off, code SWIM3-XXXXXX)
 *     6 kg  -> 200 g on us ($5.60 off, SWIM6-)
 *     10 kg -> 400 g on us ($11.20 off, SWIM10-)
 *     15 kg -> a whole kilo on us ($28 off, SWIM15-)
 *   (values at $2.80 per 100 g since 2026-10-09; codes claimed before keep their old value: $2.50 / $5 / $10 / $25)
 *   then the next 15 kg lap starts (18, 21, 25, 30 kg ...).
 * - The fish counts delivered kilos (Completed orders, deep blue water), kilos on their way (Processing / On hold,
 *   light blue) and what is in the bag right now (candy stripes, live). Rewards are available as soon as the orders
 *   pass a checkpoint: a gift reached by placed orders lights up and spins (tap to claim); a gift the bag reaches
 *   lights up too, and that reward can be used in the very order that reaches it (at checkout). The bag drawer and
 *   checkout say what this bag unlocks, or how much more to add (within 1 kg).
 * - Tapping a lit checkpoint claims the reward: a personal one-use code locked to the customer's email, kept on the
 *   account until the customer chooses to use it. At checkout every saved reward has a "Use" / "Remove" button:
 *   nothing is applied by itself. Rewards combine with each other (e.g. 200 g + the free kilo) but not with other
 *   codes (FIKA10, win-back). Free sweets do not count towards the next checkpoint. Customers holding a reward are not
 *   offered the 10% before-you-go code.
 * - The part of an order paid by a reward does not count towards the next checkpoint (e.g. $5.60 = 200 g).
 * - Orders marked "Undelivered" (a new order status), Cancelled, Failed or Refunded do not count; when an order
 *   stops counting, the fish swims back and an unused reward above the new total is withdrawn.
 * - Shown on the home page, Mix your own, Ready Mix (above the shop) and the My account dashboard; never to
 *   visitors who are not signed in.
 * - Shop managers can preview: ?fika_fish=5.2 (delivered kg) &fika_pending=1 (kg on the way).
 * Needs snippet 10 (Fika accounts). Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-loyalty.php
 */

if ( ! defined( 'FIKA_SWIM_LAP' ) ) {
	define( 'FIKA_SWIM_LAP', 15000 ); // grams per lap
}
if ( ! defined( 'FIKA_FREE_KG_VALUE' ) ) {
	define( 'FIKA_FREE_KG_VALUE', 28 ); // fallback value of 1 kg
}
if ( ! function_exists( 'fika_swim_checkpoints' ) ) {
	// the checkpoints of a lap: grams => reward
	function fika_swim_checkpoints() {
		return array(
			3000  => array( 'kg' => 3, 'prefix' => 'SWIM3', 'badge' => '100 g', 'title' => '100 g on us', 'unlock' => '100 g on us (worth $2.80)', 'text' => 'Worth $2.80 on any sweets you choose', 'co' => '$2.80 off this order', 'amount' => 2.8, 'rg' => 100 ),
			6000  => array( 'kg' => 6, 'prefix' => 'SWIM6', 'badge' => '200 g', 'title' => '200 g on us', 'unlock' => '200 g on us (worth $5.60)', 'text' => 'Worth $5.60 on any sweets you choose', 'co' => '$5.60 off this order', 'amount' => 5.6, 'rg' => 200 ),
			10000 => array( 'kg' => 10, 'prefix' => 'SWIM10', 'badge' => '400 g', 'title' => '400 g on us', 'unlock' => '400 g on us (worth $11.20)', 'text' => 'Worth $11.20 on any sweets you choose', 'co' => '$11.20 off this order', 'amount' => 11.2, 'rg' => 400 ),
			15000 => array( 'kg' => 15, 'prefix' => 'SWIM15', 'badge' => '1 kg', 'title' => 'A whole kilo on us', 'unlock' => 'a whole kilo on us (worth $28)', 'text' => 'Worth $28 on any sweets you choose', 'co' => '$28 off this order', 'amount' => 28, 'rg' => 1000 ),
		);
	}
	function fika_swim_is_code( $code ) {
		return (bool) preg_match( '/^swim(3|6|10|15)-/i', (string) $code );
	}
}

// ---------- "Undelivered" order status: set it when an order was not delivered, so it stops counting ----------
add_action( 'init', function () {
	register_post_status( 'wc-undelivered', array(
		'label'                     => 'Undelivered',
		'public'                    => false,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: number of orders */
		'label_count'               => _n_noop( 'Undelivered <span class="count">(%s)</span>', 'Undelivered <span class="count">(%s)</span>' ),
	) );
} );
add_filter( 'wc_order_statuses', function ( $statuses ) {
	$out = array();
	foreach ( $statuses as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'wc-completed' === $k ) {
			$out['wc-undelivered'] = 'Undelivered';
		}
	}
	if ( ! isset( $out['wc-undelivered'] ) ) {
		$out['wc-undelivered'] = 'Undelivered';
	}
	return $out;
} );
foreach ( array( 'bulk_actions-edit-shop_order', 'bulk_actions-woocommerce_page_wc-orders' ) as $fika_hook ) {
	add_filter( $fika_hook, function ( $actions ) {
		$actions['mark_undelivered'] = 'Change status to undelivered';
		return $actions;
	}, 20 );
}
add_action( 'admin_head', function () {
	echo '<style>.order-status.status-undelivered { background: #f8dce3; color: #8a1f3d; }</style>';
} );

if ( ! function_exists( 'fika_swim_order_grams' ) ) {
	// Grams an order adds to the swim: everything except the part paid by a reward (at the order's own price per gram)
	function fika_swim_order_grams( $order ) {
		$grams = fika_order_grams( $order );
		// the free 2nd-order mystery taste (fika-taste.php) does not count
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_fika_taste' ) ) {
				$grams -= 100 * (int) $item->get_quantity();
			}
		}
		if ( $grams <= 0 ) {
			return 0;
		}
		$paid = 0;
		foreach ( $order->get_items( 'coupon' ) as $c ) {
			if ( fika_swim_is_code( $c->get_code() ) ) {
				$paid += (float) $c->get_discount();
			}
		}
		if ( $paid > 0 ) {
			$sub = (float) $order->get_subtotal();
			if ( $sub > 0 ) {
				$grams -= (int) round( $paid / ( $sub / $grams ) );
			}
		}
		return max( 0, $grams );
	}
}

if ( ! function_exists( 'fika_swim_totals' ) ) {
	// Delivered grams (Completed) and grams on their way (Processing / On hold) for a customer
	function fika_swim_totals( $user_id ) {
		$out = array( 'delivered' => 0, 'placed' => 0 );
		if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}
		$orders = wc_get_orders( array(
			'customer_id' => (int) $user_id,
			'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
			'limit'       => -1,
			'type'        => 'shop_order',
		) );
		foreach ( $orders as $o ) {
			$g = fika_swim_order_grams( $o );
			if ( 'completed' === $o->get_status() ) {
				$out['delivered'] += $g;
			} else {
				$out['placed'] += $g;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'fika_swim_claims' ) ) {
	// Claimed rewards: user meta fika_swim_claims = [ { lap, g, code, at }, ... ]
	function fika_swim_claims( $user_id ) {
		$c = get_user_meta( (int) $user_id, 'fika_swim_claims', true );
		return is_array( $c ) ? array_values( $c ) : array();
	}
	function fika_swim_code_used( $code ) {
		$c = new WC_Coupon( $code );
		return $c->get_id() ? $c->get_usage_count() >= 1 : true;
	}
	// grams needed (in all) for a checkpoint of a lap
	function fika_swim_need( $lap, $g ) {
		return ( (int) $lap - 1 ) * FIKA_SWIM_LAP + (int) $g;
	}
	// grams that count now: delivered + on the way (orders that are refused or cancelled drop out)
	function fika_swim_reach( $user_id ) {
		$t = fika_swim_totals( $user_id );
		return $t['delivered'] + $t['placed'];
	}
	// grams in the WooCommerce cart (the bag at checkout): 100 g per candy unit, 500 g per Ready Mix bag
	function fika_swim_cart_grams() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return 0;
		}
		$g = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['fika_taste'] ) ) {
				continue; // the free mystery taste does not count
			}
			$g += ( has_term( 'ready-mix', 'product_cat', $item['product_id'] ) ? 500 : 100 ) * (int) $item['quantity'];
		}
		return $g;
	}
	// claim lookup by code
	function fika_swim_claim_of( $user_id, $code ) {
		foreach ( fika_swim_claims( $user_id ) as $cl ) {
			if ( strtolower( $cl['code'] ) === strtolower( $code ) ) {
				return $cl;
			}
		}
		return null;
	}
	// unclaimed checkpoints from the first lap up to a little past what can be reached: [ lap, g, need ], in order
	function fika_swim_unclaimed( $user_id, $upto ) {
		$have = array();
		foreach ( fika_swim_claims( $user_id ) as $cl ) {
			$have[ (int) $cl['lap'] . ':' . (int) $cl['g'] ] = 1;
		}
		$out = array();
		for ( $lap = 1; fika_swim_need( $lap, 0 ) < $upto; $lap++ ) {
			foreach ( array_keys( fika_swim_checkpoints() ) as $g ) {
				$need = fika_swim_need( $lap, $g );
				if ( $need <= $upto && empty( $have[ $lap . ':' . $g ] ) ) {
					$out[] = array( $lap, $g, $need );
				}
			}
		}
		return $out;
	}
	// an order stopped counting: withdraw unused rewards above what counts now ($reach: delivered + on the way,
	// plus the bag when the customer is at checkout)
	function fika_swim_sync( $user_id, $reach = null ) {
		if ( null === $reach ) {
			$reach = fika_swim_reach( $user_id );
		}
		$claims = fika_swim_claims( $user_id );
		$keep   = array();
		foreach ( $claims as $cl ) {
			if ( fika_swim_need( $cl['lap'], $cl['g'] ) > $reach && ! fika_swim_code_used( $cl['code'] ) ) {
				$c = new WC_Coupon( $cl['code'] );
				if ( $c->get_id() ) {
					$c->delete( true );
				}
				continue;
			}
			$keep[] = $cl;
		}
		if ( count( $keep ) !== count( $claims ) ) {
			update_user_meta( $user_id, 'fika_swim_claims', $keep );
		}
		return $keep;
	}
	// claim a reached checkpoint: creates the personal code; returns the claim or WP_Error
	function fika_swim_claim( $user_id, $lap, $g ) {
		$cps  = fika_swim_checkpoints();
		$lap  = (int) $lap;
		$g    = (int) $g;
		$user = get_userdata( $user_id );
		if ( ! $user || $lap < 1 || ! isset( $cps[ $g ] ) ) {
			return new WP_Error( 'fika_swim', 'This reward does not exist.' );
		}
		// what counts: delivered + on the way + the bag being checked out (a reward can be used in the order that reaches it)
		$reach = fika_swim_reach( $user_id ) + fika_swim_cart_grams();
		if ( fika_swim_need( $lap, $g ) > $reach ) {
			return new WP_Error( 'fika_swim', 'Add a little more to your bag to unlock this reward.' );
		}
		$claims = fika_swim_sync( $user_id, $reach );
		foreach ( $claims as $cl ) {
			if ( (int) $cl['lap'] === $lap && (int) $cl['g'] === $g ) {
				return $cl; // already claimed
			}
		}
		$cp = $cps[ $g ];
		do {
			$code = $cp['prefix'] . '-' . strtoupper( wp_generate_password( 6, false, false ) );
		} while ( wc_get_coupon_id_by_code( $code ) );
		$c = new WC_Coupon();
		$c->set_code( $code );
		$c->set_discount_type( 'fixed_cart' );
		$c->set_amount( $cp['amount'] );
		$c->set_individual_use( false ); // rewards combine with each other; other codes are kept out below
		$c->set_usage_limit( 1 );
		$c->set_usage_limit_per_user( 1 );
		$c->set_email_restrictions( array( $user->user_email ) );
		$c->set_description( sprintf( 'Fika reward for %s (user %d): %s (%s), lap %d checkpoint %d kg (%d kg ordered in all).', $user->user_email, $user_id, $cp['title'], $cp['co'], $lap, $cp['kg'], fika_swim_need( $lap, $g ) / 1000 ) );
		$c->update_meta_data( '_fika_reward_user', $user_id );
		$c->save();
		$cl       = array( 'lap' => $lap, 'g' => $g, 'code' => $code, 'at' => time() );
		$claims[] = $cl;
		update_user_meta( $user_id, 'fika_swim_claims', $claims );
		return $cl;
	}
	// claimed codes not used yet, oldest first: [ [code, title, text, checkout label, amount, grams needed], ... ]
	function fika_swim_open_codes( $user_id ) {
		$cps = fika_swim_checkpoints();
		$out = array();
		foreach ( fika_swim_claims( $user_id ) as $cl ) {
			if ( ! fika_swim_code_used( $cl['code'] ) && isset( $cps[ (int) $cl['g'] ] ) ) {
				$cp    = $cps[ (int) $cl['g'] ];
				$out[] = array( $cl['code'], $cp['title'], $cp['text'], $cp['co'], $cp['amount'], fika_swim_need( $cl['lap'], $cl['g'] ) );
			}
		}
		return $out;
	}
}

// Withdraw rewards that no longer apply whenever an order changes status
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( $order && $order->get_customer_id() ) {
		fika_swim_sync( $order->get_customer_id() );
	}
}, 30, 4 );

// Claim: POST /wp-json/fika/v1/swim-claim { lap, g } (signed-in customer, REST nonce)
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/swim-claim', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => function ( $req ) {
			$cl = fika_swim_claim( get_current_user_id(), (int) $req->get_param( 'lap' ), (int) $req->get_param( 'g' ) );
			if ( is_wp_error( $cl ) ) {
				return new WP_REST_Response( array( 'error' => $cl->get_error_message() ), 400 );
			}
			$cps = fika_swim_checkpoints();
			$cp  = $cps[ (int) $cl['g'] ];
			return array( 'code' => $cl['code'], 'title' => $cp['title'], 'text' => $cp['text'], 'co' => $cp['co'], 'amount' => $cp['amount'], 'need' => fika_swim_need( $cl['lap'], $cl['g'] ) );
		},
	) );
} );

// ---------- The value of the customer's first kilo in the bag (most expensive sweets first) ----------
if ( ! function_exists( 'fika_free_kg_value' ) ) {
	function fika_free_kg_value() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return FIKA_FREE_KG_VALUE;
		}
		$rows = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$p = $item['data'];
			if ( ! $p ) {
				continue;
			}
			$step   = has_term( 'ready-mix', 'product_cat', $item['product_id'] ) ? 500 : 100;
			$rows[] = array( 'per_g' => (float) $p->get_price() / $step, 'grams' => $step * (int) $item['quantity'] );
		}
		if ( ! $rows ) {
			return FIKA_FREE_KG_VALUE;
		}
		usort( $rows, function ( $a, $b ) {
			return $b['per_g'] <=> $a['per_g'];
		} );
		$left = 1000;
		$val  = 0;
		foreach ( $rows as $r ) {
			$g     = min( $left, $r['grams'] );
			$val  += $g * $r['per_g'];
			$left -= $g;
			if ( $left <= 0 ) {
				break;
			}
		}
		return round( $val, 2 );
	}
}
// rewards combine with each other, not with other codes (FIKA10, win-back ...)
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $valid;
	}
	$mine   = fika_swim_is_code( $coupon->get_code() );
	if ( $mine && is_user_logged_in() ) {
		$cl = fika_swim_claim_of( get_current_user_id(), $coupon->get_code() );
		if ( $cl ) {
			// the bag as it is (the free sweets included) has to reach the checkpoint
			$short = fika_swim_need( $cl['lap'], $cl['g'] ) - fika_swim_reach( get_current_user_id() ) - fika_swim_cart_grams();
			if ( $short > 0 ) {
				throw new Exception( sprintf( 'Add %s more to your bag to use this reward.', $short >= 1000 ? rtrim( rtrim( number_format( $short / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg' : $short . ' g' ) );
			}
		}
	}
	foreach ( WC()->cart->get_applied_coupons() as $other ) {
		if ( strtolower( $other ) === strtolower( $coupon->get_code() ) ) {
			continue;
		}
		if ( $mine !== fika_swim_is_code( $other ) ) {
			throw new Exception( $mine ? 'Fika Club rewards cannot be combined with other discount codes. Remove the other code to use your reward.' : 'This code cannot be combined with your Fika Club rewards. Remove the rewards to use it.' );
		}
	}
	return $valid;
}, 20, 2 );


// ---------- What the tracker shows for the signed-in customer ----------
if ( ! function_exists( 'fika_swim_state' ) ) {
	function fika_swim_state() {
		if ( ! is_user_logged_in() || ! function_exists( 'fika_order_grams' ) ) {
			return null;
		}
		$uid     = get_current_user_id();
		$u       = wp_get_current_user();
		$t       = fika_swim_totals( $uid );
		$preview = isset( $_GET['fika_fish'] ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $preview ) {
			$t['delivered'] = (int) round( floatval( wp_unslash( $_GET['fika_fish'] ) ) * 1000 ); // phpcs:ignore
			$t['placed']    = isset( $_GET['fika_pending'] ) ? (int) round( floatval( wp_unslash( $_GET['fika_pending'] ) ) * 1000 ) : 0; // phpcs:ignore
		}
		$claims  = $preview ? array() : fika_swim_sync( $uid, $t['delivered'] + $t['placed'] );
		$claimed = array();
		foreach ( $claims as $cl ) {
			$claimed[ (int) $cl['lap'] . ':' . (int) $cl['g'] ] = $cl['code'];
		}
		$cps    = fika_swim_checkpoints();
		$stops  = function_exists( 'fika_taste_stops' ) ? fika_taste_stops() : array();
		$tastes = function_exists( 'fika_taste_list' ) ? fika_taste_list( $uid ) : array();
		// the lap on show: the first one with a reached reward (or spin) still to take, else the one being swum
		$lap = (int) floor( ( $t['delivered'] + $t['placed'] ) / FIKA_SWIM_LAP ) + 1;
		for ( $l = 1; $l < $lap; $l++ ) {
			foreach ( $cps as $g => $cp ) {
				if ( ! isset( $claimed[ $l . ':' . $g ] ) ) {
					$lap = $l;
					break 2;
				}
			}
			foreach ( $stops as $g ) {
				if ( ! isset( $tastes[ $l . ':' . $g ] ) ) {
					$lap = $l;
					break 2;
				}
			}
		}
		$base = ( $lap - 1 ) * FIKA_SWIM_LAP;
		$d    = min( FIKA_SWIM_LAP, max( 0, $t['delivered'] - $base ) );
		$o    = min( FIKA_SWIM_LAP - $d, $t['placed'] + max( 0, $t['delivered'] - $base - FIKA_SWIM_LAP ) );
		$list = array();
		foreach ( $cps as $g => $cp ) {
			$code = isset( $claimed[ $lap . ':' . $g ] ) ? $claimed[ $lap . ':' . $g ] : '';
			if ( $code ) {
				$state = fika_swim_code_used( $code ) ? 'used' : 'claimed';
			} elseif ( $d + $o >= $g ) {
				$state = 'ready';
			} else {
				$state = 'locked';
			}
			$list[] = array( 'g' => $g, 'kg' => $cp['kg'], 'badge' => $cp['badge'], 'title' => $cp['title'], 'unlock' => $cp['unlock'], 'text' => $cp['text'], 'rg' => $cp['rg'], 'state' => $state, 'code' => 'claimed' === $state ? $code : '' );
		}
		$spins = array();
		foreach ( $stops as $g ) {
			$tk = isset( $tastes[ $lap . ':' . $g ] ) ? $tastes[ $lap . ':' . $g ] : null;
			$st = $tk ? ( 'used' === $tk['status'] ? 'used' : 'won' ) : ( $d + $o >= $g ? 'ready' : 'locked' );
			$pn = $tk ? wc_get_product( (int) $tk['pid'] ) : null;
			$spins[] = array( 'g' => $g, 'kg' => $g / 1000, 'state' => $st, 'name' => $pn ? html_entity_decode( $pn->get_name() ) : '' );
		}
		return array(
			'uid'     => $uid,
			'name'    => $u->first_name ? $u->first_name : $u->display_name,
			'lap'     => $lap,
			'spins'   => $spins,
			'd'       => $d,
			'o'       => $o,
			'cps'     => $list,
			'open'    => $preview ? array() : fika_swim_open_codes( $uid ),
			'preview' => $preview,
		);
	}
}

if ( ! function_exists( 'fika_swim_html' ) ) {
	function fika_swim_html( $where ) {
		$s = fika_swim_state();
		if ( ! $s ) {
			return '';
		}
		$data = array(
			'uid'   => $s['uid'],
			'name'  => $s['name'],
			'goal'  => FIKA_SWIM_LAP,
			'lap'   => $s['lap'],
			'd'     => $s['d'],
			'o'     => $s['o'],
			'cps'   => $s['cps'],
			'spins' => $s['spins'],
			'claim' => $s['preview'] ? '' : rest_url( 'fika/v1/swim-claim' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		);
		$fmt   = function ( $g ) {
			return rtrim( rtrim( number_format( $g / 1000, 1, '.', '' ), '0' ), '.' );
		};
		$total = min( FIKA_SWIM_LAP, $s['d'] + $s['o'] );
		$wid   = esc_attr( $where );
		ob_start();
		?>
<section class="fika-swim fika-swim-<?php echo $wid; ?>" data-swim="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" aria-label="Your Fika Club rewards">
	<div class="fs-head">
		<div class="fs-intro">
			<p class="fs-kicker"><?php echo $s['lap'] > 1 ? 'Lap ' . (int) $s['lap'] . ' &middot; ' : ''; ?>Fika Club</p>
			<h2 class="fs-title">Swim to your sweet rewards</h2>
			<p class="fs-sub"></p>
			<p class="fs-legend"></p>
		</div>
		<div class="fs-big" aria-hidden="true"><b><?php echo esc_html( $fmt( $total ) ); ?></b><span>/ <?php echo (int) ( FIKA_SWIM_LAP / 1000 ); ?> kg</span></div>
	</div>
	<div class="fs-track" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) ( FIKA_SWIM_LAP / 1000 ); ?>" aria-valuenow="<?php echo esc_attr( $total / 1000 ); ?>">
		<div class="fs-cps">
			<?php foreach ( $s['cps'] as $cp ) : ?>
			<button type="button" class="fs-cp is-<?php echo esc_attr( $cp['state'] ); ?>" data-g="<?php echo (int) $cp['g']; ?>" aria-label="<?php echo esc_attr( $cp['kg'] . ' kg: ' . $cp['text'] ); ?>">
				<span class="fs-gift"><svg viewBox="0 0 48 48" aria-hidden="true"><rect x="8" y="20" width="32" height="22" rx="4"/><rect x="5" y="13" width="38" height="9" rx="3"/><path class="r" d="M24 13v29"/><path class="b" d="M24 13c-3-7-12-8-12-3 0 3 6 3 12 3zM24 13c3-7 12-8 12-3 0 3-6 3-12 3z"/></svg></span>
				<span class="fs-badge"><?php echo esc_html( $cp['badge'] ); ?></span>
			</button>
			<?php endforeach; ?>
		</div>
		<div class="fs-lane">
			<div class="fs-clip">
				<div class="fs-seg fs-water"><svg class="fs-wave" viewBox="0 0 120 12" preserveAspectRatio="none" aria-hidden="true"><path d="M0 6 Q15 0 30 6 T60 6 T90 6 T120 6 V12 H0 Z"/></svg></div>
				<div class="fs-seg fs-way"></div>
				<div class="fs-seg fs-inbag"></div>
			</div>
			<?php foreach ( $s['cps'] as $cp ) : ?><i class="fs-flag" data-g="<?php echo (int) $cp['g']; ?>"></i><?php endforeach; ?>
			<i class="fs-finish" aria-hidden="true"></i>
			<?php foreach ( $s['spins'] as $sp ) : ?><button type="button" class="fs-spin is-<?php echo esc_attr( $sp['state'] ); ?>" data-g="<?php echo (int) $sp['g']; ?>" aria-label="<?php echo esc_attr( $sp['kg'] . ' kg: mystery spin, a free 50 g taste' ); ?>"><svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18" fill="#fff"/><g class="w"><path d="M20 20L20 4A16 16 0 0 1 33.9 12z" fill="#ff6fa5"/><path d="M20 20L33.9 12A16 16 0 0 1 33.9 28z" fill="#ffd23f"/><path d="M20 20L33.9 28A16 16 0 0 1 20 36z" fill="#4aa8ff"/><path d="M20 20L20 36A16 16 0 0 1 6.1 28z" fill="#7ad67a"/><path d="M20 20L6.1 28A16 16 0 0 1 6.1 12z" fill="#c77dff"/><path d="M20 20L6.1 12A16 16 0 0 1 20 4z" fill="#ff9f43"/></g><circle cx="20" cy="20" r="18" fill="none" stroke="#004aad" stroke-width="2.6"/><circle cx="20" cy="20" r="5.5" fill="#fff" stroke="#004aad" stroke-width="2"/><text x="20" y="23" text-anchor="middle" font-family="Bebas Neue, Impact, sans-serif" font-size="9" fill="#004aad">?</text></svg></button><?php endforeach; ?>
			<div class="fs-fish" aria-hidden="true"><div class="fs-turn">
				<svg viewBox="0 0 100 100"><defs><linearGradient id="fsFishG<?php echo $wid; ?>" x1="0" x2="1"><stop offset="0" stop-color="#ff6a3d"/><stop offset="1" stop-color="#e3241f"/></linearGradient></defs>
					<g class="fs-tail"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/></g>
					<path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="url(#fsFishG<?php echo $wid; ?>)" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/>
					<path d="M38 40 Q42 50 38 62 M48 38 Q52 50 48 64" fill="none" stroke="#a3160f" stroke-opacity=".35" stroke-width="1.6"/>
					<ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/>
					<circle cx="24" cy="46" r="5.2" fill="#fff"/><circle cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/>
					<path d="M10 56 Q14 59 18 57" fill="none" stroke="#a3160f" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</div></div>
		</div>
	</div>
	<div class="fs-ticks" aria-hidden="true"><?php for ( $i = 0; $i <= FIKA_SWIM_LAP / 1000; $i++ ) : ?><i data-i="<?php echo (int) $i; ?>"<?php echo in_array( $i, array_merge( array( 0 ), wp_list_pluck( fika_swim_checkpoints(), 'kg' ) ), true ) ? ' class="m"' : ''; ?>><?php echo (int) $i; ?></i><?php endfor; ?></div>
	<p class="fs-note">Every gram you pay for moves the fish. Free sweets don&rsquo;t count towards your next reward.</p>
	<div class="fs-rewards">
		<?php foreach ( $s['open'] as $r ) : ?>
		<div class="fs-reward"><div class="fs-code"><span><?php echo esc_html( $r[1] ); ?></span><b><?php echo esc_html( $r[0] ); ?></b></div><button type="button" class="fs-copy" data-code="<?php echo esc_attr( $r[0] ); ?>">Copy code</button><p><?php echo esc_html( $r[2] ); ?>. Use it whenever you like: tap &ldquo;Use&rdquo; at checkout. Rewards can be combined; delivery not included.</p></div>
		<?php endforeach; ?>
	</div>
</section>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'fika_swim_assets' ) ) {
	function fika_swim_assets() {
		?>
<style>
.fika-swim { --fs-blue: #004aad; --fw: 76px; --goal: 74px; position: relative; box-sizing: border-box; background: #fff; border-radius: 26px; padding: 26px 30px 22px; box-shadow: 0 10px 34px rgba(0, 74, 173, .10); color: #1b2a4a; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; overflow: hidden; }
.fika-swim *, .fika-swim *::before { box-sizing: border-box; }
.fika-swim-home { max-width: 1100px; margin: 34px auto 10px; width: calc(100% - 12%); }
.fika-swim-account { margin: 4px 0 22px; padding: 22px 22px 18px; box-shadow: none; background: #f4f8ff; }
.fika-swim .fs-head { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
.fika-swim .fs-kicker { margin: 0 0 2px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 17px; color: #6b7894; }
.fika-swim h2.fs-title { margin: 0 0 4px; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: clamp(28px, 3.2vw, 38px); letter-spacing: .02em; line-height: 1; color: var(--fs-blue); }
.fika-swim .fs-sub { margin: 0; font-size: 16px; line-height: 1.45; }
.fika-swim .fs-sub b { color: var(--fs-blue); }
.fika-swim .fs-legend { display: flex; flex-wrap: wrap; gap: 4px 16px; margin: 6px 0 0; font-size: 14px; color: #6b7894; }
.fika-swim .fs-legend:empty { display: none; }
.fika-swim .fs-legend span { display: inline-flex; align-items: center; gap: 6px; }
.fika-swim .fs-legend i { width: 12px; height: 12px; border-radius: 4px; }
.fika-swim .fs-legend .l-d i { background: #1c6fd6; }
.fika-swim .fs-legend .l-o i { background: #a9d3ff; }
.fika-swim .fs-legend .l-b i { background: repeating-linear-gradient(135deg, #ff6fa5 0 3px, #ffd23f 3px 6px); }
.fika-swim .fs-big { flex: none; text-align: right; font-family: 'Bebas Neue', Impact, sans-serif; color: var(--fs-blue); line-height: .9; }
.fika-swim .fs-big b { font-weight: 400; font-size: 64px; }
.fika-swim .fs-big span { font-size: 26px; margin-left: 4px; color: #6b7894; }

.fika-swim .fs-track { position: relative; }
.fika-swim .fs-lane { position: relative; height: 62px; border-radius: 999px; background: #fdeaf2; overflow: visible;
  background-image: repeating-linear-gradient(90deg, transparent 0 16px, rgba(0, 74, 173, .08) 16px 26px); background-size: 100% 3px; background-repeat: no-repeat; background-position: 0 50%; }
.fika-swim .fs-clip { position: absolute; inset: 0; border-radius: 999px; overflow: hidden; pointer-events: none; }
.fika-swim .fs-seg { position: absolute; top: 0; bottom: 0; left: 0; width: 0; overflow: hidden; }
.fika-swim .fs-water { border-radius: 999px; z-index: 1; background: linear-gradient(180deg, #6fb6ff 0%, #2f86ea 55%, #1c6fd6 100%); box-shadow: inset 0 -6px 12px rgba(0, 40, 120, .18); }
.fika-swim .fs-water::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 70%, rgba(255,255,255,.35) 0 3px, transparent 4px), radial-gradient(circle at 55% 40%, rgba(255,255,255,.3) 0 2px, transparent 3px), radial-gradient(circle at 80% 75%, rgba(255,255,255,.3) 0 2.5px, transparent 3.5px); background-size: 90px 62px; animation: fsBub 4s linear infinite; }
.fika-swim .fs-wave { position: absolute; left: 0; top: -2px; width: 200%; height: 12px; fill: rgba(255, 255, 255, .35); animation: fsWave 3s linear infinite; }
.fika-swim .fs-way { z-index: 0; border-radius: 0 999px 999px 0; background: linear-gradient(180deg, #d4e9ff, #a9d3ff); }
.fika-swim .fs-inbag { z-index: 0; border-radius: 0 999px 999px 0; background: repeating-linear-gradient(135deg, rgba(255, 111, 165, .55) 0 8px, rgba(255, 210, 63, .55) 8px 16px); background-size: 22.6px 22.6px; animation: fsCandy 1s linear infinite; }
.fika-swim .fs-fish { position: absolute; top: 50%; left: 0; width: var(--fw); height: var(--fw); margin-top: calc(var(--fw) / -2); z-index: 2; pointer-events: none; will-change: transform; }
.fika-swim .fs-turn { width: 100%; height: 100%; transform: scaleX(-1); transition: transform .35s ease; }
.fika-swim.is-back .fs-turn { transform: scaleX(1); }
.fika-swim .fs-fish svg { width: 100%; height: 100%; filter: drop-shadow(0 3px 3px rgba(0, 40, 120, .25)); animation: fsBob 1.6s ease-in-out infinite; overflow: visible; }
.fika-swim .fs-tail { transform-origin: 64px 51px; animation: fsTail .9s ease-in-out infinite; }
.fika-swim.is-swimming .fs-tail { animation-duration: .28s; }
.fika-swim.is-swimming .fs-fish svg { animation-duration: .7s; }
.fika-swim .fs-bubble { position: absolute; z-index: 3; width: 8px; height: 8px; border-radius: 50%; border: 1.6px solid rgba(255, 255, 255, .95); background: rgba(160, 205, 255, .35); pointer-events: none; animation: fsRise 1.3s ease-out forwards; }
.fika-swim .fs-ticks { position: relative; height: 18px; margin: 6px 0 0; font-size: 12.5px; font-weight: 600; color: #8b97b3; }
.fika-swim .fs-ticks i { position: absolute; top: 0; left: 0; transform: translateX(-50%); font-style: normal; }
.fika-swim .fs-ticks i::before { content: ''; position: absolute; left: 50%; top: -7px; width: 2px; height: 5px; border-radius: 1px; background: #c9d4ea; }
.fika-swim .fs-rewards:empty { display: none; }
.fika-swim .fs-reward.is-new { animation: fsPop .5s cubic-bezier(.2, 1.4, .4, 1); }
.fika-swim .fs-reward { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; margin-top: 12px; padding: 16px 18px; border-radius: 18px; background: #fdeaf2; }
.fika-swim .fs-code { display: flex; flex-direction: column; padding: 8px 18px; border: 2px dashed var(--fs-blue); border-radius: 14px; background: #fff; }
.fika-swim .fs-code span { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #6b7894; }
.fika-swim .fs-code b { font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 30px; letter-spacing: .06em; color: var(--fs-blue); line-height: 1.05; }
.fika-swim .fs-copy { height: 46px; padding: 0 24px; border: 0; border-radius: 999px; background: var(--fs-blue); color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fika-swim .fs-copy:hover { background: #003a8a; }
.fika-swim .fs-reward p { flex: 1 1 260px; margin: 0; font-size: 14.5px; line-height: 1.45; }
.fika-swim .fs-confetti { position: absolute; z-index: 3; width: 9px; height: 9px; border-radius: 3px; pointer-events: none; animation: fsConf 1.4s cubic-bezier(.2, .7, .4, 1) forwards; }
@keyframes fsBob { 0%, 100% { transform: translateY(-2px) rotate(-2deg); } 50% { transform: translateY(3px) rotate(3deg); } }
@keyframes fsTail { 0%, 100% { transform: rotate(-12deg); } 50% { transform: rotate(12deg); } }
@keyframes fsWave { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@keyframes fsBub { from { background-position: 0 0, 0 0, 0 0; } to { background-position: 0 -62px, 0 -62px, 0 -62px; } }
@keyframes fsCandy { from { background-position: 0 0; } to { background-position: 22.6px 0; } }
@keyframes fsRise { 0% { opacity: 0; transform: translate(0, 0) scale(.5); } 20% { opacity: 1; } 100% { opacity: 0; transform: translate(var(--bx, -14px), -34px) scale(1.1); } }
@keyframes fsGoal { 0%, 100% { transform: rotate(0) scale(1); } 20% { transform: rotate(-10deg) scale(1.12); } 40% { transform: rotate(8deg) scale(1.12); } 60% { transform: rotate(0) scale(1); } }
@keyframes fsJump { 0%, 60%, 100% { transform: translateY(0) rotate(0); } 25% { transform: translateY(-16px) rotate(14deg); } 45% { transform: translateY(2px) rotate(-6deg); } }
@keyframes fsConf { 0% { opacity: 1; transform: translate(0, 0) rotate(0); } 100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--r)); } }
@media (max-width: 700px) {
  .fika-swim { --fw: 58px; --goal: 52px; padding: 20px 16px 16px; border-radius: 20px; }
  .fika-swim-home { width: calc(100% - 32px); margin-top: 24px; }
  .fika-swim .fs-head { align-items: flex-start; gap: 10px; margin-bottom: 14px; }
  .fika-swim .fs-big b { font-size: 44px; }
  .fika-swim .fs-big span { font-size: 19px; }
  .fika-swim .fs-sub { font-size: 15px; }
  .fika-swim .fs-legend { font-size: 13px; gap: 2px 12px; }
  .fika-swim .fs-lane { height: 48px; }
  .fika-swim .fs-ticks { font-size: 11px; }
  .fika-swim .fs-code b { font-size: 25px; }
}
.fika-swim .fs-note { margin: 4px 0 0; font-size: 12.5px; color: #8b97b3; text-align: right; }
@media (max-width: 700px) { .fika-swim .fs-ticks i:not(.m) { color: transparent; } .fika-swim .fs-note { text-align: left; } }
/* checkpoints: a gift above the lane at each checkpoint */
.fika-swim .fs-cps { position: relative; height: 70px; }
.fika-swim .fs-cp { position: absolute; bottom: 6px; left: 0; display: flex; flex-direction: column; align-items: center; gap: 2px; width: 60px; margin-left: -30px; padding: 0; border: 0; background: none; cursor: default; font: inherit; z-index: 2; }
.fika-swim .fs-gift { display: block; width: 40px; height: 40px; perspective: 200px; }
.fika-swim .fs-gift svg { display: block; width: 100%; height: 100%; overflow: visible; transform-style: preserve-3d; }
.fika-swim .fs-gift rect { fill: #e6ebf5; stroke: #b7c3dc; stroke-width: 2.4; }
.fika-swim .fs-gift path { fill: none; stroke: #b7c3dc; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
.fika-swim .fs-badge { display: inline-block; min-width: 38px; padding: 3px 8px; border-radius: 999px; background: #e6ebf5; color: #8b97b3; font: 400 15px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .04em; text-align: center; }
.fika-swim .fs-flag { position: absolute; top: 6px; bottom: 6px; left: 0; width: 0; border-left: 2px dashed rgba(0, 74, 173, .28); z-index: 1; pointer-events: none; }
.fika-swim .fs-ticks i.m { color: #004aad; font-weight: 700; }
/* the finish line at the end of each 15 kg lap (the next lap starts again at 0 kg) */
.fika-swim .fs-finish { position: absolute; top: 7px; bottom: 7px; left: 0; z-index: 1; width: 12px; margin-left: -6px; border-radius: 3px; background: repeating-conic-gradient(#1b2a4a 0 25%, #fff 0 50%) 0 0 / 12px 12px; box-shadow: 0 0 0 2px #fff, 0 2px 6px rgba(27, 42, 74, .25); pointer-events: none; }
.fika-swim .fs-flag[data-g="15000"] { display: none; }
.fika-swim .fs-ticks i[data-i="15"]::after { content: ''; position: absolute; left: 100%; top: 3px; width: 11px; height: 8px; margin-left: 4px; border-radius: 1px; background: repeating-conic-gradient(#1b2a4a 0 25%, #fff 0 50%) 0 0 / 5.5px 4px; box-shadow: 0 0 0 1px #1b2a4a; }
.fika-swim.is-lapdone .fs-finish { animation: fsFinish .7s ease-out 2; }
@keyframes fsFinish { 50% { transform: scaleY(1.25); box-shadow: 0 0 0 3px #ffd23f, 0 0 16px rgba(255, 210, 63, .9); } }
@media (max-width: 700px) { .fika-swim .fs-finish { width: 10px; margin-left: -5px; background-size: 10px 10px; } }
/* mystery spin stops: little wheels in the lane */
.fika-swim .fs-spin { position: absolute; top: 50%; left: 0; z-index: 3; width: 32px; height: 32px; margin: -16px 0 0 -16px; padding: 0; border: 0; border-radius: 50%; background: none; cursor: default; }
.fika-swim .fs-spin svg { display: block; width: 100%; height: 100%; overflow: visible; }
.fika-swim .fs-spin.is-locked { filter: grayscale(1); opacity: .55; }
.fika-swim .fs-spin.is-ready, .fika-swim .fs-spin.is-bag, .fika-swim .fs-spin.is-won { cursor: pointer; }
.fika-swim .fs-spin.is-ready svg, .fika-swim .fs-spin.is-bag svg { filter: drop-shadow(0 0 5px rgba(255, 210, 63, 1)) drop-shadow(0 0 10px rgba(255, 111, 165, .8)); }
.fika-swim .fs-spin.is-ready .w, .fika-swim .fs-spin.is-bag .w { transform-origin: 20px 20px; animation: fsWheel 1.6s linear infinite; }
.fika-swim .fs-spin.is-ready { animation: fsTap 1.6s ease-in-out infinite; }
.fika-swim .fs-spin.is-used { opacity: .5; }
.fika-swim .fs-spin.is-won::after, .fika-swim .fs-spin.is-used::after { content: '\2713'; position: absolute; right: -4px; top: -4px; width: 15px; height: 15px; border-radius: 50%; background: #2fb36b; color: #fff; font: 700 10px/15px Arial, sans-serif; text-align: center; }
@keyframes fsWheel { to { transform: rotate(360deg); } }
@media (max-width: 700px) { .fika-swim .fs-spin { width: 26px; height: 26px; margin: -13px 0 0 -13px; } }
/* a spin waiting (skipped when it popped up): a bouncy "Tap to spin!" label above the little wheel */
.fika-swim .fs-spin.is-ready::before, .fika-swim .fs-spin.is-bag::before { content: 'Tap to spin!'; position: absolute; left: 50%; bottom: calc(100% + 9px); z-index: 4; padding: 6px 11px 5px; border-radius: 999px; background: #ff4f91; color: #fff; font: 400 16px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .05em; white-space: nowrap; box-shadow: 0 4px 12px rgba(255, 79, 145, .45); transform: translateX(-50%); animation: fsHop 1s cubic-bezier(.3, 0, .4, 1) infinite; pointer-events: auto; }
.fika-swim .fs-spin.is-ready::after, .fika-swim .fs-spin.is-bag::after { content: ''; position: absolute; left: 50%; bottom: calc(100% + 3px); z-index: 4; width: 0; height: 0; margin-left: -6px; border: 6px solid transparent; border-bottom: 0; border-top-color: #ff4f91; animation: fsHopTip 1s cubic-bezier(.3, 0, .4, 1) infinite; }
.fika-swim .fs-spin.is-ready, .fika-swim .fs-spin.is-bag { z-index: 5; }
@keyframes fsHop { 0%, 100% { transform: translate(-50%, 0); } 45% { transform: translate(-50%, -9px); } 60% { transform: translate(-50%, -7px); } }
@keyframes fsHopTip { 0%, 100% { transform: translateY(0); } 45% { transform: translateY(-9px); } 60% { transform: translateY(-7px); } }
/* phones: little room between the badges, so a short "Spin!"; the 3 kg badge sits right next to the 1.5 kg wheel,
   so that label leans left of its wheel */
@media (max-width: 700px) {
  .fika-swim .fs-spin.is-ready::before, .fika-swim .fs-spin.is-bag::before { content: 'Spin!'; font-size: 15px; padding: 5px 9px 4px; }
  .fika-swim .fs-spin[data-g="1500"].is-ready::before, .fika-swim .fs-spin[data-g="1500"].is-bag::before { left: calc(50% + 4px); animation-name: fsHopL; }
  .fika-swim .fs-spin[data-g="1500"].is-ready::after, .fika-swim .fs-spin[data-g="1500"].is-bag::after { left: calc(50% - 5px); }
}
@keyframes fsHopL { 0%, 100% { transform: translate(-100%, 0); } 45% { transform: translate(-100%, -9px); } 60% { transform: translate(-100%, -7px); } }
@media (prefers-reduced-motion: reduce) { .fika-swim .fs-spin::before, .fika-swim .fs-spin::after { animation: none !important; } }
/* on its way: unlocks once delivered */
.fika-swim .fs-cp.is-way rect { fill: #eaf4ff; stroke: #7fb7f2; }
.fika-swim .fs-cp.is-way path { stroke: #7fb7f2; }
.fika-swim .fs-cp.is-way .fs-badge { background: #d4e9ff; color: #2f6fc0; }
/* reached and delivered: lit, spinning, tap to claim */
.fika-swim .fs-cp.is-ready { cursor: pointer; }
.fika-swim .fs-cp.is-ready .fs-gift { filter: drop-shadow(0 0 6px rgba(255, 210, 63, .95)) drop-shadow(0 0 14px rgba(255, 111, 165, .7)); animation: fsGlow 1.6s ease-in-out infinite; }
.fika-swim .fs-cp.is-ready .fs-gift svg { animation: fsSpin 2.6s linear infinite; }
.fika-swim .fs-cp.is-ready rect, .fika-swim .fs-cp.is-claimed rect { fill: #ff6fa5; stroke: #004aad; }
.fika-swim .fs-cp.is-ready path, .fika-swim .fs-cp.is-claimed path { stroke: #ffd23f; stroke-width: 3; }
.fika-swim .fs-cp.is-ready .fs-badge { background: #004aad; color: #fff; animation: fsTap 1.6s ease-in-out infinite; }
.fika-swim .fs-cp.is-ready:hover .fs-gift { transform: scale(1.1); }
.fika-swim .fs-cp.is-ready:focus-visible { outline: 3px solid #ffd23f; outline-offset: 4px; border-radius: 12px; }
.fika-swim .fs-cp[aria-busy] .fs-gift svg { animation-duration: .6s; }
/* reached with what is in the bag: lit (used at checkout in this order) */
.fika-swim .fs-cp.is-bag { cursor: pointer; }
.fika-swim .fs-cp.is-bag .fs-gift { filter: drop-shadow(0 0 6px rgba(255, 210, 63, .9)); animation: fsBagBob 1.4s ease-in-out infinite; }
.fika-swim .fs-cp.is-bag rect { fill: #ffd0e2; stroke: #004aad; }
.fika-swim .fs-cp.is-bag path { stroke: #ffd23f; stroke-width: 3; }
.fika-swim .fs-cp.is-bag .fs-badge { background: repeating-linear-gradient(135deg, #ff6fa5 0 6px, #ff8ab8 6px 12px); color: #fff; }
@keyframes fsBagBob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
/* claimed (code below), used */
.fika-swim .fs-cp.is-claimed .fs-badge { background: #2fb36b; color: #fff; }
.fika-swim .fs-cp.is-used { opacity: .6; }
.fika-swim .fs-cp.is-used .fs-badge { background: #e6ebf5; color: #6b7894; text-decoration: line-through; }
@keyframes fsSpin { from { transform: rotateY(0); } to { transform: rotateY(360deg); } }
@keyframes fsGlow { 0%, 100% { filter: drop-shadow(0 0 4px rgba(255, 210, 63, .8)) drop-shadow(0 0 8px rgba(255, 111, 165, .5)); } 50% { filter: drop-shadow(0 0 9px rgba(255, 210, 63, 1)) drop-shadow(0 0 18px rgba(255, 111, 165, .85)); } }
@keyframes fsTap { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
@keyframes fsPop { from { opacity: 0; transform: translateY(8px) scale(.97); } to { opacity: 1; transform: none; } }
@media (max-width: 700px) {
  .fika-swim .fs-cps { height: 60px; }
  .fika-swim .fs-cp { width: 50px; margin-left: -25px; }
  .fika-swim .fs-gift { width: 32px; height: 32px; }
  .fika-swim .fs-badge { font-size: 13px; min-width: 32px; padding: 3px 6px; }
}
@media (prefers-reduced-motion: reduce) { .fika-swim *, .fika-swim *::after { animation: none !important; } }

</style>
<script>
(function () {
	function readBag() {
		var g = 0;
		try {
			var st = JSON.parse(localStorage.getItem('fika_bag_v1') || 'null'), bag = (st && st.bag) || {};
			Object.keys(bag).forEach(function (k) { var v = +bag[k] || 0; if (v > 0) g += v; });
		} catch (e) {}
		return g;
	}
	function kg(g) { return (Math.round(g / 100) / 10).toString(); }
	function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
	function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

	function Swim(el) {
		var s = JSON.parse(el.getAttribute('data-swim') || '{}');
		var G = s.goal || 15000, cps = s.cps || [];
		var lane = el.querySelector('.fs-lane'), fish = el.querySelector('.fs-fish');
		var water = el.querySelector('.fs-water'), way = el.querySelector('.fs-way'), inbag = el.querySelector('.fs-inbag');
		var big = el.querySelector('.fs-big b'), sub = el.querySelector('.fs-sub'), legend = el.querySelector('.fs-legend'), title = el.querySelector('.fs-title');
		var ticks = el.querySelectorAll('.fs-ticks i'), flags = el.querySelectorAll('.fs-flag'), btns = el.querySelectorAll('.fs-cp'), wheels = el.querySelectorAll('.fs-spin'), spins = s.spins || [];
		var rewards = el.querySelector('.fs-rewards'), fin = el.querySelector('.fs-finish');
		var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
		var seenKey = 'fika_swim_seen_' + s.uid, partyKey = 'fika_swim_party_' + s.uid;
		var bagG = 0, cur = 0, target = 0, started = false, raf = 0, lastBub = 0, lastTs = 0;
		// a new lap since this customer last looked: the fish first swims over the finish line of the lap before
		var lapKey = 'fika_swim_lap_' + s.uid, lapCross = false, lapNew = s.lap > 1 ? (+lsGet(lapKey) || 1) < s.lap : false;

		function cp(g) { for (var i = 0; i < cps.length; i++) if (cps[i].g === g) return cps[i]; return null; }
		function btn(g) { for (var i = 0; i < btns.length; i++) if (+btns[i].getAttribute('data-g') === g) return btns[i]; return null; }
		// where the fish ends up: delivered + on the way + in the bag (one lap at most)
		function goalPos() { return lapCross ? G : Math.min(G, s.d + s.o + bagG); }
		function x(g, W, fw) { return (W - fw) * Math.min(1, Math.max(0, g / G)) + fw * 0.86; }
		function paint() {
			var W = lane.clientWidth, fw = fish.offsetWidth;
			// crossing the finish line of the lap before: the whole lane is that lap's
			var dEnd = lapCross ? cur : Math.min(cur, s.d), oEnd = lapCross ? cur : Math.min(cur, s.d + s.o);
			water.style.width = (dEnd > 0 ? x(dEnd, W, fw) : 0) + 'px';
			var oStart = s.d > 0 ? x(s.d, W, fw) - 30 : 0;
			way.style.left = oStart + 'px';
			way.style.width = (oEnd > s.d ? Math.max(0, x(oEnd, W, fw) - oStart) : 0) + 'px';
			var bStart = (s.d + s.o) > 0 ? x(s.d + s.o, W, fw) - 30 : 0;
			inbag.style.left = bStart + 'px';
			inbag.style.width = (lapCross ? false : cur > s.d + s.o) ? Math.max(0, x(cur, W, fw) - bStart) + 'px' : '0px';
			if (fin) fin.style.left = x(G, W, fw) + 'px';
			fish.style.transform = 'translateX(' + ((W - fw) * Math.min(1, cur / G)) + 'px)';
			for (var i = 0; i < ticks.length; i++) ticks[i].style.left = x(+ticks[i].getAttribute('data-i') * 1000, W, fw) + 'px';
			for (var j = 0; j < flags.length; j++) flags[j].style.left = x(+flags[j].getAttribute('data-g'), W, fw) + 'px';
			for (var k = 0; k < btns.length; k++) btns[k].style.left = x(+btns[k].getAttribute('data-g'), W, fw) + 'px';
			for (var q = 0; q < wheels.length; q++) wheels[q].style.left = x(+wheels[q].getAttribute('data-g'), W, fw) + 'px';
			if (big) big.textContent = kg(cur);
		}
		function setState(b, c, st) {
			c.state = st;
			b.className = 'fs-cp is-' + st;
			b.setAttribute('aria-label', c.kg + ' kg: ' + c.text + (st === 'ready' ? ' (tap to claim)' : st === 'claimed' ? ' (claimed)' : st === 'used' ? ' (used)' : st === 'bag' ? ' (unlocked by this bag: use it at checkout)' : ''));
			b.title = st === 'ready' ? 'Tap to claim ' + c.title : st === 'claimed' ? c.title + ' claimed' : st === 'used' ? c.title + ' used' : st === 'bag' ? c.title + ': unlocked by this bag, use it at checkout' : c.title + ' at ' + c.kg + ' kg';
		}
		// gifts the bag reaches light up (they are used at checkout, in the order that reaches them)
		function wheelOf(g) { for (var i = 0; i < wheels.length; i++) if (+wheels[i].getAttribute('data-g') === g) return wheels[i]; return null; }
		function lights() {
			spins.forEach(function (sp) {
				if (sp.state !== 'locked' ? sp.state !== 'bag' : false) return;
				var st = s.d + s.o + bagG >= sp.g ? 'bag' : 'locked', w = wheelOf(sp.g);
				if (w ? st !== sp.state : false) { sp.state = st; w.className = 'fs-spin is-' + st; }
			});
			cps.forEach(function (c) {
				if (c.state !== 'locked' ? c.state !== 'bag' : false) return;
				var st = s.d + s.o + bagG >= c.g ? 'bag' : 'locked', b = btn(c.g);
				if (b ? st !== c.state : false) setState(b, c, st);
			});
		}
		function words() {
			lights();
			var name = s.name ? ', ' + s.name : '';
			var ready = cps.filter(function (c) { return c.state === 'ready'; });
			var lit = cps.filter(function (c) { return c.state === 'bag'; });
			var spinNow = spins.filter(function (x) { return x.state === 'ready' || x.state === 'bag'; });
			var tot = s.d + s.o + bagG, next = null;
			var ahead = cps.filter(function (c) { return c.state === 'locked' ? c.g > tot : false; }).concat(spins.filter(function (x) { return x.state === 'locked' ? x.g > tot : false; }).map(function (x) { return { g: x.g, title: 'a mystery spin' }; }));
			ahead.sort(function (a, b) { return a.g - b.g; });
			next = ahead[0] || null;
			sub.innerHTML = '';
			if (lit.length ? !ready.length : false) {
				var L = lit[lit.length - 1], lt = L.title.replace(/^A whole/, 'a whole');
				title.textContent = 'This bag unlocks ' + lt + '!';
				sub.appendChild(document.createTextNode('Go to checkout and tap \u201cUse\u201d: the sweets come off this very order' + name + '.'));
			} else if (spinNow.length ? !ready.length : false) {
				title.textContent = 'Mystery spin unlocked!';
				sub.appendChild(document.createTextNode('Tap the little wheel on the lane and spin for a free 50 g mystery taste' + name + '.'));
			} else if (ready.length) {
				title.textContent = 'You made it to ' + ready[0].kg + ' kg!';
				sub.appendChild(document.createTextNode('Tap the glowing gift to unlock ' + ready[0].unlock + name + '.'));
			} else if (lapNew) {
				title.textContent = 'Lap ' + (s.lap - 1) + ' finished!';
				sub.appendChild(document.createTextNode('You crossed the finish line' + name + '. Lap ' + s.lap + ' starts at 0 kg, with every reward and mystery spin back on the lane.'));
			} else if (next) {
				title.textContent = 'Swim to your sweet rewards';
				var b = document.createElement('b'); b.textContent = kg(next.g - tot) + ' kg'; sub.appendChild(b);
				sub.appendChild(document.createTextNode(' to go until '));
				var t = document.createElement('b'); t.textContent = next.title.replace(/^A whole/, 'a whole'); sub.appendChild(t);
				sub.appendChild(document.createTextNode((s.name ? ', ' + s.name : '') + '!' + (bagG > 0 ? ' Every gram in your bag moves the fish.' : '')));
			} else {
				title.textContent = tot >= G ? 'This bag crosses the finish line!' : 'Lap ' + s.lap + ' finished!';
				sub.appendChild(document.createTextNode('All your rewards are saved' + name + '. Lap ' + (s.lap + 1) + ' starts at 0 kg.'));
			}
			var parts = [];
			if (s.d > 0) parts.push(['l-d', kg(s.d) + ' kg delivered']);
			if (s.o > 0) parts.push(['l-o', kg(s.o) + ' kg on its way']);
			if (bagG > 0) parts.push(['l-b', kg(bagG) + ' kg in your bag']);
			legend.innerHTML = '';
			parts.forEach(function (p) { var sp = document.createElement('span'); sp.className = p[0]; sp.innerHTML = '<i></i>'; sp.appendChild(document.createTextNode(p[1])); legend.appendChild(sp); });
			autoSpin();
		}
		// a spin stop the customer has just reached opens the wheel by itself, in the middle of the page.
		// Closed without spinning: it stays on the lane (tap the little wheel) and does not pop up again this visit.
		var popped = {};
		function ssKey(g) { return 'fika_spin_pop_' + s.uid + '_' + s.lap + '_' + g; }
		function ssGet(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
		function ssSet(k) { try { sessionStorage.setItem(k, '1'); } catch (e) {} }
		function autoSpin(tries) {
			var sp = spins.filter(function (x) { return (x.state === 'ready' || x.state === 'bag') ? !(popped[x.g] || ssGet(ssKey(x.g))) : false; })[0];
			if (!sp || document.querySelector('.fk-wheel-veil')) return;
			if (!window.fikaTasteWheel) { if ((tries || 0) < 40) setTimeout(function () { autoSpin((tries || 0) + 1); }, 250); return; }
			popped[sp.g] = true;
			var w = wheelOf(sp.g);
			window.fikaTasteWheel({ lap: s.lap, g: sp.g, bag: bagG,
				done: function (j) { sp.state = 'won'; sp.name = j.name; if (w) w.className = 'fs-spin is-won'; words(); },
				closed: function (won) { if (!won) ssSet(ssKey(sp.g)); } });
		}
		function bubble(back) {
			var r = fish.getBoundingClientRect(), lr = el.getBoundingClientRect();
			var b = document.createElement('i');
			b.className = 'fs-bubble';
			b.style.left = (back ? r.left - lr.left + r.width * 0.05 : r.right - lr.left - r.width * 0.12) + 'px';
			b.style.top = (r.top - lr.top + r.height * 0.3) + 'px';
			b.style.setProperty('--bx', back ? '14px' : '-14px');
			el.appendChild(b);
			setTimeout(function () { b.remove(); }, 1400);
		}
		function confetti(target) {
			if (reduce || !target) return;
			var g = target.getBoundingClientRect(), lr = el.getBoundingClientRect();
			var cols = ['#ff6fa5', '#ffd23f', '#4aa8ff', '#ff4d4d', '#7ad67a', '#c77dff', '#ff9f43'];
			for (var i = 0; i < 26; i++) {
				var c = document.createElement('i');
				c.className = 'fs-confetti';
				var a = Math.random() * Math.PI * 2, d = 40 + Math.random() * 90;
				c.style.left = (g.left - lr.left + g.width / 2) + 'px';
				c.style.top = (g.top - lr.top + g.height / 2) + 'px';
				c.style.background = cols[i % cols.length];
				c.style.setProperty('--dx', (Math.cos(a) * d) + 'px');
				c.style.setProperty('--dy', (Math.sin(a) * d - 30) + 'px');
				c.style.setProperty('--r', (Math.random() * 540 - 270) + 'deg');
				el.appendChild(c);
				(function (n) { setTimeout(function () { n.remove(); }, 1500); })(c);
			}
		}
		// the fish swims towards the target at a gentle, distance-based speed (forwards or backwards)
		function frame(ts) {
			raf = 0;
			var dt = lastTs ? Math.min(64, ts - lastTs) : 16;
			lastTs = ts;
			var diff = target - cur;
			if (reduce || Math.abs(diff) < 4) {
				cur = target;
			} else {
				var step = Math.max(Math.abs(diff) * 0.0032 * dt, 0.35 * dt);
				cur += diff > 0 ? Math.min(step, diff) : Math.max(-step, diff);
			}
			var moving = cur !== target;
			if (moving) { el.classList.add('is-swimming'); } else { el.classList.remove('is-swimming'); }
			if (diff < 0) { el.classList.add('is-back'); } else if (diff > 0) { el.classList.remove('is-back'); }
			if (moving ? ts - lastBub > 170 : false) { lastBub = ts; bubble(diff < 0); }
			paint();
			if (moving) { raf = requestAnimationFrame(frame); return; }
			lastTs = 0;
			if (lapCross) {
				lapCross = false;
				el.classList.add('is-lapdone');
				confetti(fin);
				words();
				setTimeout(function () { cur = 0; paint(); go(); }, 1100);
				return;
			}
			el.classList.add('is-swum');
			setTimeout(function () { if (!el.classList.contains('is-swimming')) el.classList.remove('is-back'); }, 500);
			// a checkpoint just unlocked (first time this customer sees it): confetti
			var party = {}; try { party = JSON.parse(lsGet(partyKey) || '{}') || {}; } catch (e) {}
			cps.forEach(function (c) {
				var k = s.lap + ':' + c.g;
				if (c.state === 'ready' ? !party[k] : false) { party[k] = 1; confetti(btn(c.g)); }
			});
			lsSet(partyKey, JSON.stringify(party));
			// remember where the customer last saw the fish (without the bag), to swim from there next time
			lsSet(seenKey, JSON.stringify({ lap: s.lap, g: Math.min(G, s.d + s.o) }));
			lsSet(lapKey, String(s.lap));
		}
		function go() { target = goalPos(); if (!raf) raf = requestAnimationFrame(frame); }
		function onBag() {
			var g = readBag();
			if (g === bagG) return;
			bagG = g;
			words();
			if (started) go();
		}
		function addReward(r) {
			var box = document.createElement('div');
			box.className = 'fs-reward is-new';
			box.innerHTML = '<div class="fs-code"><span></span><b></b></div><button type="button" class="fs-copy">Copy code</button><p></p>';
			box.querySelector('span').textContent = r.title;
			box.querySelector('b').textContent = r.code;
			box.querySelector('.fs-copy').setAttribute('data-code', r.code);
			box.querySelector('p').textContent = r.text + '. Use it whenever you like: tap “Use” at checkout. Rewards can be combined; delivery not included.';
			rewards.insertBefore(box, rewards.firstChild);
		}
		// tap a lit checkpoint: claim the reward
		Array.prototype.forEach.call(btns, function (b) {
			b.addEventListener('click', function () {
				var c = cp(+b.getAttribute('data-g'));
				if (c ? c.state === 'bag' : false) { title.textContent = c.title + ' is waiting at checkout'; sub.textContent = 'Place this bag and tap “Use” at checkout: it comes off this very order.'; return; }
				if (!c || c.state !== 'ready' || b.getAttribute('aria-busy')) return;
				if (!s.claim) { addReward({ code: 'PREVIEW', title: c.title, text: c.text }); setState(b, c, 'claimed'); words(); return; }
				b.setAttribute('aria-busy', 'true');
				fetch(s.claim, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': s.nonce }, body: JSON.stringify({ lap: s.lap, g: c.g }) })
					.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (res) {
						b.removeAttribute('aria-busy');
						if (!res.ok || !res.j.code) { alert((res.j && res.j.error) || 'Sorry, something went wrong. Please try again.'); return; }
						confetti(b);
						setState(b, c, 'claimed');
						addReward(res.j);
						try { window.dispatchEvent(new CustomEvent('fikareward', { detail: { lap: s.lap, g: c.g, code: res.j.code, title: res.j.title, text: res.j.text } })); } catch (e) {}
						words();
						var sm = document.querySelector('.fika-acct .fa-hi small');
						if (sm ? !cps.some(function (x) { return x.state === 'ready'; }) : false) sm.textContent = 'Your reward is saved for checkout';
					})
					.catch(function () { b.removeAttribute('aria-busy'); alert('Sorry, something went wrong. Please try again.'); });
			});
		});
		Array.prototype.forEach.call(wheels, function (w) {
			w.addEventListener('click', function () {
				var g = +w.getAttribute('data-g'), sp = spins.filter(function (x) { return x.g === g; })[0];
				if (!sp) return;
				if (sp.state === 'won' || sp.state === 'used') { title.textContent = 'Your mystery taste: ' + sp.name; sub.textContent = sp.state === 'won' ? '50 g, free: it joins your order at checkout.' : 'Already in one of your orders. Enjoy!'; return; }
				if (sp.state === 'locked') { title.textContent = 'Mystery spin at ' + sp.kg + ' kg'; sub.textContent = 'Reach ' + sp.kg + ' kg and spin the candy wheel for a free 50 g taste.'; return; }
				if (!window.fikaTasteWheel) return;
				window.fikaTasteWheel({ lap: s.lap, g: g, bag: bagG, done: function (j) { sp.state = 'won'; sp.name = j.name; w.className = 'fs-spin is-won'; words(); } });
			});
		});
		el.addEventListener('click', function (e) {
			var copy = e.target.closest ? e.target.closest('.fs-copy') : null;
			if (!copy) return;
			var code = copy.getAttribute('data-code');
			function ok() { copy.textContent = 'Copied!'; setTimeout(function () { copy.textContent = 'Copy code'; }, 1800); }
			if (navigator.clipboard ? navigator.clipboard.writeText : false) navigator.clipboard.writeText(code).then(ok, ok); else ok();
		});
		// start from where the fish was last time (so an order that stopped counting swims back)
		var seen = null;
		try { seen = JSON.parse(lsGet(seenKey) || 'null'); } catch (e) {}
		cur = seen ? (seen.lap === s.lap ? Math.min(G, +seen.g || 0) : 0) : 0;
		if (lapNew) { lapCross = true; cur = seen ? (seen.lap === s.lap - 1 ? Math.min(G, +seen.g || 0) : 0) : 0; if (reduce) { lapCross = false; cur = 0; } }
		cps.forEach(function (c) { var b = btn(c.g); if (b) setState(b, c, c.state); });
		bagG = readBag();
		words();
		paint();
		this.start = function () { if (started) return; started = true; setTimeout(go, 250); };
		window.addEventListener('fikabag', function () { setTimeout(onBag, 0); });
		window.addEventListener('storage', function (e) { if (e.key === 'fika_bag_v1') onBag(); });
		setInterval(onBag, 800);
		window.addEventListener('resize', paint);
	}
	function init() {
		document.querySelectorAll('.fika-swim').forEach(function (el) {
			if (el.fikaSwim) return;
			var sw = new Swim(el);
			el.fikaSwim = sw;
			if (!window.IntersectionObserver) { sw.start(); return; }
			var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { io.disconnect(); sw.start(); } }, { threshold: 0.45 });
			io.observe(el);
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
	window.fikaSwimInit = init;
})();
</script>
		<?php
	}
}

// ---------- Home page (above the shop cards), Mix your own and Ready Mix (under the banner) ----------
add_action( 'wp_footer', function () {
	if ( ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) || ! is_user_logged_in() ) {
		return;
	}
	$html = fika_swim_html( 'home' );
	if ( ! $html ) {
		return;
	}
	echo '<template id="fikaSwimTpl">' . $html . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
	?>
<script>
(function () {
	// home: just above the shop cards; Mix your own: between the banner and the filter bar; Ready Mix: under the banner
	var tpl = document.getElementById('fikaSwimTpl'), shop = document.getElementById('fsBar') || document.getElementById('shop');
	if (!tpl || !shop || document.querySelector('.fika-swim-home')) return;
	shop.parentNode.insertBefore(tpl.content.cloneNode(true), shop);
})();
</script>
	<?php
	// the bag drawer: what this bag unlocks, or how much more to add (live, gram by gram)
	$uid   = get_current_user_id();
	$reach = fika_swim_reach( $uid );
	$cps   = fika_swim_checkpoints();
	$next  = array();
	foreach ( fika_swim_unclaimed( $uid, $reach + FIKA_SWIM_LAP ) as $u ) {
		$next[] = array( $u[2], $cps[ $u[1] ]['title'], $cps[ $u[1] ]['rg'] );
	}
	// mystery spin stops (fika-taste.php) not spun yet
	if ( function_exists( 'fika_taste_stops' ) ) {
		$tl = fika_taste_list( $uid );
		for ( $lap = 1; ( $lap - 1 ) * FIKA_SWIM_LAP < $reach + FIKA_SWIM_LAP; $lap++ ) {
			foreach ( fika_taste_stops() as $g ) {
				$need = ( $lap - 1 ) * FIKA_SWIM_LAP + $g;
				if ( $need <= $reach + FIKA_SWIM_LAP && empty( $tl[ $lap . ':' . $g ] ) ) {
					$next[] = array( $need, 'a mystery spin (a free 50 g taste)', 0 );
				}
			}
		}
		usort( $next, function ( $a, $b ) { return $a[0] - $b[0]; } );
	}
	$saved = count( fika_swim_open_codes( $uid ) );
	?>
<style>.mx-df .fk-rw-note { margin: 0 0 10px; padding: 9px 12px; border-radius: 12px; background: #fff6d6; color: #1b2a4a; font: 500 13.5px/1.35 'Outfit', 'Open Sans', Arial, sans-serif; }
.mx-df .fk-rw-note:empty { display: none; }
.mx-df .fk-rw-note b { color: #004aad; }
.mx-df .fk-rw-note.is-on { background: #e3f6ea; }</style>
<script>
(function () {
	var REACH = <?php echo (int) $reach; ?>, NEXT = <?php echo wp_json_encode( $next ); ?>, SAVED = <?php echo (int) $saved; ?>;
	function bagG() { var g = 0; try { var st = JSON.parse(localStorage.getItem('fika_bag_v1') || 'null'), bag = (st && st.bag) || {}; Object.keys(bag).forEach(function (k) { g += +bag[k] || 0; }); } catch (e) {} return g; }
	function amt(g) { return g >= 1000 ? (Math.round(g / 100) / 10) + ' kg' : g + ' g'; }
	function low(t) { return t.replace(/^A whole/, 'a whole'); }
	var note = null;
	function show() {
		var go = document.getElementById('mxGo');
		if (!go) return;
		if (!note) { note = document.createElement('p'); note.className = 'fk-rw-note'; go.parentNode.insertBefore(note, go); }
		// the next reward: reaching its checkpoint with this bag unlocks it for this order
		// rewards already reached wait on the tracker; this bag is about the next checkpoint ahead
		var g = bagG(), r = REACH + g, nx = NEXT.filter(function (n) { return n[0] > REACH; })[0], toCp = nx ? nx[0] - r : 0;
		var waiting = SAVED + NEXT.filter(function (n) { return n[0] <= REACH; }).length;
		var cpKg = nx ? ((nx[0] - 1) % <?php echo (int) FIKA_SWIM_LAP; ?> + 1) / 1000 : 0;
		note.innerHTML = ''; note.classList.toggle('is-on', nx ? (g > 0 ? toCp <= 0 : false) : false);
		function add(t, bold) { var x = bold ? document.createElement('b') : document.createTextNode(t); if (bold) x.textContent = t; note.appendChild(x); }
		if (nx ? (g > 0 ? (toCp <= 0 ? nx[2] === 0 : false) : false) : false) {
			add('This bag reaches ' + cpKg + ' kg: '); add('a mystery spin', true); add(' is waiting on your Fika Club lane. Spin it and 50 g of a surprise candy join this order, free.');
		} else if (nx ? (g > 0 ? toCp <= 0 : false) : false) {
			add('This bag reaches ' + cpKg + ' kg and unlocks '); add(low(nx[1]), true); add('! Tap “Use” at checkout and they come off this order.');
		} else if (nx ? (g > 0 ? toCp <= 1000 : false) : false) {
			add('Add '); add(amt(toCp) + ' more', true); add(' to reach ' + cpKg + ' kg and get '); add(low(nx[1]), true); add(' in this order.');
		} else if (waiting) {
			add(waiting === 1 ? 'You have a Fika Club reward waiting. Use it at checkout whenever you like.' : 'You have ' + waiting + ' Fika Club rewards waiting. Choose which to use at checkout.');
		}
	}
	show();
	window.addEventListener('fikabag', function () { setTimeout(show, 0); });
	window.addEventListener('storage', function (e) { if (e.key === 'fika_bag_v1') show(); });
})();
</script>
	<?php
	fika_swim_assets();
}, 25 );

// ---------- My account dashboard: the same tracker above the totals ----------
// (the style and script go in the footer: the account page content is run through texturize, which breaks scripts)
add_action( 'woocommerce_account_dashboard', function () {
	$html = fika_swim_html( 'account' );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		$GLOBALS['fika_swim_assets_needed'] = true;
	}
}, 4 );
add_action( 'wp_footer', function () {
	if ( ! empty( $GLOBALS['fika_swim_assets_needed'] ) ) {
		fika_swim_assets();
	}
}, 25 );

// ---------- My account > Rewards: ready to use and already used ----------
// Under the fish lane: every reward the customer can use now (saved codes, gifts reached but not unlocked yet,
// mystery tastes on their way) and the ones already used, with the order they went on.
if ( ! function_exists( 'fika_rewards_lists' ) ) {
	function fika_rewards_lists( $uid ) {
		$cps    = fika_swim_checkpoints();
		$ready  = array();
		$used   = array();
		$orders = wc_get_orders( array( 'customer_id' => $uid, 'limit' => -1, 'type' => 'shop_order', 'status' => array_keys( wc_get_order_statuses() ) ) );
		$by     = array();
		foreach ( $orders as $o ) {
			foreach ( $o->get_coupon_codes() as $code ) {
				$by[ strtolower( $code ) ] = $o;
			}
		}
		$claims = fika_swim_claims( $uid );
		$have   = array();
		foreach ( $claims as $cl ) {
			$cp = isset( $cps[ (int) $cl['g'] ] ) ? $cps[ (int) $cl['g'] ] : null;
			if ( ! $cp ) {
				continue;
			}
			$have[ (int) $cl['lap'] . ':' . (int) $cl['g'] ] = 1;
			$o = isset( $by[ strtolower( $cl['code'] ) ] ) ? $by[ strtolower( $cl['code'] ) ] : null;
			if ( fika_swim_code_used( $cl['code'] ) ) {
				$used[] = array( 'title' => $cp['title'], 'meta' => 'Lap ' . (int) $cl['lap'] . ', ' . $cp['kg'] . ' kg', 'how' => $o ? 'Used on order #' . $o->get_order_number() . ', ' . wc_format_datetime( $o->get_date_created(), 'j M Y' ) : 'Used', 'at' => $o ? $o->get_date_created()->getTimestamp() : (int) $cl['at'] );
			} else {
				$ready[] = array( 'type' => 'code', 'k' => (int) $cl['lap'] . ':' . (int) $cl['g'], 'title' => $cp['title'], 'meta' => $cp['text'], 'code' => strtoupper( $cl['code'] ), 'how' => 'Comes off your next order at checkout. It never runs out.' );
			}
		}
		// gifts reached but not unlocked yet (tap the glowing gift on the lane)
		$reach = fika_swim_reach( $uid );
		for ( $lap = 1; ( $lap - 1 ) * FIKA_SWIM_LAP < $reach; $lap++ ) {
			foreach ( $cps as $g => $cp ) {
				if ( fika_swim_need( $lap, $g ) <= $reach && empty( $have[ $lap . ':' . $g ] ) ) {
					$ready[] = array( 'type' => 'unlock', 'k' => $lap . ':' . $g, 'lap' => $lap, 'g' => $g, 'title' => $cp['title'], 'meta' => 'Reached at ' . $cp['kg'] . ' kg (lap ' . $lap . ')', 'code' => '', 'how' => 'Unlock it to get your code.' );
				}
			}
		}
		// mystery spins reached but not spun yet
		if ( function_exists( 'fika_taste_stops' ) ) {
			$tl = function_exists( 'fika_taste_list' ) ? fika_taste_list( $uid ) : array();
			for ( $lap = 1; ( $lap - 1 ) * FIKA_SWIM_LAP < $reach; $lap++ ) {
				foreach ( fika_taste_stops() as $g ) {
					if ( ( $lap - 1 ) * FIKA_SWIM_LAP + $g <= $reach && empty( $tl[ $lap . ':' . $g ] ) ) {
						$ready[] = array( 'type' => 'spin', 'k' => 's' . $lap . ':' . $g, 'lap' => $lap, 'g' => $g, 'title' => 'Mystery spin', 'meta' => 'Reached at ' . ( $g / 1000 ) . ' kg (lap ' . $lap . ')', 'code' => '', 'how' => 'Spin the candy wheel for a free 50 g taste.' );
					}
				}
			}
		}
		// mystery tastes
		if ( function_exists( 'fika_taste_list' ) ) {
			foreach ( fika_taste_list( $uid ) as $k => $t ) {
				$p    = wc_get_product( (int) $t['pid'] );
				$name = $p ? html_entity_decode( $p->get_name() ) : 'a sweet';
				list( $lap, $g ) = array_map( 'intval', explode( ':', $k ) );
				if ( 'used' === $t['status'] ) {
					$o      = ! empty( $t['order'] ) ? wc_get_order( (int) $t['order'] ) : null;
					$used[] = array( 'title' => 'Mystery taste: ' . $name, 'meta' => 'Lap ' . $lap . ', ' . ( $g / 1000 ) . ' kg spin, 50 g free', 'how' => $o ? 'In order #' . $o->get_order_number() . ', ' . wc_format_datetime( $o->get_date_created(), 'j M Y' ) : 'In one of your orders', 'at' => $o ? $o->get_date_created()->getTimestamp() : (int) ( $t['at'] ?? 0 ) );
				} else {
					$ready[] = array( 'type' => 'taste', 'k' => 't' . $k, 'title' => 'Mystery taste: ' . $name, 'meta' => '50 g, free', 'code' => '', 'how' => 'Joins your next order by itself at checkout.' );
				}
			}
		}
		usort( $used, function ( $a, $b ) { return $b['at'] - $a['at']; } );
		return array( $ready, $used );
	}
}
add_action( 'woocommerce_account_dashboard', function () {
	$uid = get_current_user_id();
	if ( ! $uid || ! function_exists( 'fika_swim_claims' ) ) {
		return;
	}
	list( $ready ) = fika_rewards_lists( $uid );
	$btn = array( 'code' => 'Use', 'unlock' => 'Unlock', 'spin' => 'Spin', 'taste' => 'Use' );
	echo '<section class="fika-rw-lists" aria-label="Your rewards"><div class="fr-col"><h3>Ready to use</h3><ul class="fr-ready">';
	foreach ( $ready as $r ) {
		printf(
			'<li class="fr-item is-%1$s" data-k="%2$s" data-type="%1$s" data-lap="%3$d" data-g="%4$d" data-code="%5$s"><div class="fr-t"><b>%6$s</b><span>%7$s</span>%8$s<p class="fr-how">%9$s</p></div><button type="button" class="fr-go">%10$s</button></li>',
			esc_attr( $r['type'] ),
			esc_attr( $r['k'] ),
			isset( $r['lap'] ) ? (int) $r['lap'] : 0,
			isset( $r['g'] ) ? (int) $r['g'] : 0,
			esc_attr( $r['code'] ),
			esc_html( $r['title'] ),
			esc_html( $r['meta'] ),
			$r['code'] ? '<code>' . esc_html( $r['code'] ) . '</code>' : '',
			esc_html( $r['how'] ),
			esc_html( $btn[ $r['type'] ] )
		);
	}
	echo '</ul><p class="fr-none"' . ( $ready ? ' hidden' : '' ) . '>Nothing waiting yet. Swim to 1.5 kg for your first mystery spin, and 3 kg for 100 g on us.</p></div></section>';
	$GLOBALS['fika_rw_lists'] = array( 'claim' => rest_url( 'fika/v1/swim-claim' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'bag' => home_url( '/mix-your-own/?bag=open' ) );
}, 6 );
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['fika_rw_lists'] ) ) {
		return;
	}
	$cfg = $GLOBALS['fika_rw_lists'];
	?>
<style>
.fika-swim-account .fs-rewards { display: none; }
.fika-rw-lists { margin: 18px 0 22px; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; }
.fika-rw-lists .fr-col { padding: 22px 24px; border-radius: 22px; background: #fff; box-shadow: 0 10px 34px rgba(0, 74, 173, .08); }
.fika-rw-lists h3 { margin: 0 0 14px; font: 400 28px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .02em; color: #004aad; }
.fika-rw-lists ul { margin: 0; padding: 0; list-style: none; }
.fika-rw-lists .fr-item { display: flex; align-items: center; gap: 16px; padding: 14px 16px; margin: 0 0 10px; border-radius: 16px; background: #fdeaf2; }
.fika-rw-lists .fr-item.is-unlock, .fika-rw-lists .fr-item.is-spin { background: linear-gradient(120deg, #fff6d6, #ffe3ef); }
.fika-rw-lists .fr-t { flex: 1; min-width: 0; }
.fika-rw-lists .fr-t b { display: block; font-weight: 600; font-size: 16px; color: #004aad; }
.fika-rw-lists .fr-t span { display: block; font-size: 13px; color: #6b7894; }
.fika-rw-lists code { display: inline-block; margin: 6px 0 0; padding: 4px 9px; border-radius: 8px; background: #fff; border: 1px dashed #004aad; font: 600 13px/1 ui-monospace, Menlo, Consolas, monospace; color: #004aad; letter-spacing: .03em; }
.fika-rw-lists .fr-how { margin: 6px 0 0 !important; font-size: 13.5px; }
.fika-rw-lists .fr-go { flex: none; min-width: 104px; height: 42px; padding: 0 22px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fika-rw-lists .fr-go:hover { background: #003a8a; }
.fika-rw-lists .fr-go[disabled] { background: #c9d4ea; cursor: default; }
.fika-rw-lists .is-spin .fr-go, .fika-rw-lists .is-unlock .fr-go { background: #ff4f91; }
.fika-rw-lists .fr-none { margin: 0 !important; font-size: 14px; color: #6b7894; }
@media (max-width: 560px) { .fika-rw-lists .fr-col { padding: 16px; } .fika-rw-lists .fr-item { flex-wrap: wrap; } .fika-rw-lists .fr-go { width: 100%; } }
</style>
<script>
(function () {
  var C = <?php echo wp_json_encode( $cfg ); ?>;
  var list = document.querySelector('.fika-rw-lists .fr-ready'); if (!list) return;
  function none() { var n = list.parentNode.querySelector('.fr-none'); if (n) n.hidden = list.children.length > 0; }
  function codeRow(r) {
    var li = document.createElement('li'); li.className = 'fr-item is-code'; li.setAttribute('data-type', 'code'); li.setAttribute('data-k', r.lap + ':' + r.g); li.setAttribute('data-code', String(r.code || '').toUpperCase());
    li.innerHTML = '<div class="fr-t"><b></b><span></span><code></code><p class="fr-how">Comes off your next order at checkout. It never runs out.</p></div><button type="button" class="fr-go">Use</button>';
    li.querySelector('b').textContent = r.title || ''; li.querySelector('span').textContent = r.text || ''; li.querySelector('code').textContent = String(r.code || '').toUpperCase();
    return li;
  }
  // a gift unlocked on the lane: its row gets the code
  window.addEventListener('fikareward', function (e) {
    var r = e.detail || {}, old = list.querySelector('[data-k="' + r.lap + ':' + r.g + '"]');
    var li = codeRow(r); if (old) list.replaceChild(li, old); else list.insertBefore(li, list.firstChild); none();
  });
  list.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.fr-go') : null; if (!b || b.disabled) return;
    var li = b.closest('.fr-item'), type = li.getAttribute('data-type');
    if (type === 'code') {
      // the code is remembered: the checkout's rewards box puts it on the order
      try { sessionStorage.setItem('fika_use_code', li.getAttribute('data-code')); } catch (x) {}
      location.href = C.bag; return;
    }
    if (type === 'taste') { location.href = C.bag; return; }
    if (type === 'spin') {
      if (!window.fikaTasteWheel) return;
      window.fikaTasteWheel({ lap: +li.getAttribute('data-lap'), g: +li.getAttribute('data-g'), bag: 0, closed: function (won) { if (won) location.reload(); } });
      return;
    }
    if (type === 'unlock') {
      b.disabled = true; b.textContent = '…';
      fetch(C.claim, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify({ lap: +li.getAttribute('data-lap'), g: +li.getAttribute('data-g') }) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.j.code) { b.disabled = false; b.textContent = 'Unlock'; alert((res.j && res.j.error) || 'Sorry, something went wrong. Please try again.'); return; }
          // the gift on the lane stops glowing on the next visit; reload so the lane shows it claimed now
          list.replaceChild(codeRow({ lap: li.getAttribute('data-lap'), g: li.getAttribute('data-g'), code: res.j.code, title: res.j.title, text: res.j.text }), li);
          setTimeout(function () { location.reload(); }, 900);
        })
        .catch(function () { b.disabled = false; b.textContent = 'Unlock'; alert('Sorry, something went wrong. Please try again.'); });
    }
  });
})();
</script>
	<?php
}, 26 );

// ---------- Checkout: saved rewards and rewards this order unlocks; the customer picks any mix (or none) ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! is_user_logged_in() || is_wc_endpoint_url( 'order-received' ) || ! function_exists( 'fika_order_grams' ) ) {
		return;
	}
	$uid   = get_current_user_id();
	$reach = fika_swim_reach( $uid );
	$open  = fika_swim_open_codes( $uid );
	$cps   = fika_swim_checkpoints();
	$uncl  = array();
	foreach ( fika_swim_unclaimed( $uid, $reach + 2 * FIKA_SWIM_LAP ) as $u ) {
		$cp     = $cps[ $u[1] ];
		$uncl[] = array( $u[0], $u[1], $u[2], $cp['title'], $cp['co'], $cp['rg'] );
	}
	$ready = array_map( 'intval', get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'ready-mix' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	?>
<style>
.fika-freekg { max-width: 1100px; margin: 0 auto 22px; padding: 16px 22px; border-radius: 20px; background: #fff; box-shadow: 0 6px 24px rgba(0, 74, 173, .08); border-left: 6px solid #ffd23f; font-family: 'Outfit', Arial, sans-serif; color: #1b2a4a; box-sizing: border-box; transition: border-color .3s; }
.fika-freekg[hidden] { display: none; }
.fika-freekg.is-on { border-left-color: #2fb36b; }
.fika-freekg > b { display: block; margin-bottom: 2px; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 26px; letter-spacing: .02em; color: #004aad; line-height: 1.05; }
.fika-freekg .fr-msg { margin: 0 0 8px; font-size: 14.5px; color: #33415c; }
.fika-freekg .fr-msg:empty { display: none; }
.fika-freekg .fr-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 18px; padding: 9px 0; border-top: 1px solid #eef2fa; }
.fika-freekg .fr-row span { font-size: 15px; }
.fika-freekg .fr-row span strong { color: #004aad; }
.fika-freekg .fr-row .fr-new { display: inline-block; margin-right: 6px; padding: 2px 8px; border-radius: 999px; background: #ff6fa5; color: #fff; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; vertical-align: 1px; }
.fika-freekg .fr-row small { display: block; font-size: 12.5px; color: #6b7894; }
.fika-freekg .fr-row small:empty { display: none; }
.fika-freekg button.fr-use { min-width: 112px; height: 40px; padding: 0 20px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fika-freekg button.fr-use:hover { background: #003a8a; }
.fika-freekg button.fr-use[disabled] { background: #c9d4ea; cursor: default; }
.fika-freekg .fr-row.is-on button.fr-use { background: #fff; color: #004aad; box-shadow: inset 0 0 0 2px #004aad; }
.fika-freekg .fr-row.is-on strong::after { content: ' \2713'; color: #2fb36b; }
.fika-freekg .fr-nudge { margin: 8px 0 0; padding: 10px 14px; border-radius: 14px; background: #fff6d6; font-size: 14.5px; }
.fika-freekg .fr-nudge:empty { display: none; }
.fika-freekg .fr-nudge b { color: #004aad; }
.fika-freekg .fr-nudge a { margin-left: 6px; color: #004aad; font-weight: 600; }
.fika-freekg .fr-err { margin: 6px 0 0; font-size: 13.5px; color: #b42318; }
.fika-freekg .fr-err:empty { display: none; }
</style>
<script>
(function () {
	// CODES: saved [code, title, text, checkout label, amount, grams needed]; UNCL: not claimed yet [lap, g, grams needed, title, checkout label, grams it pays for]
	var CODES = <?php echo wp_json_encode( $open ); ?>, UNCL = <?php echo wp_json_encode( $uncl ); ?>, REACH = <?php echo (int) $reach; ?>;
	var READY = <?php echo wp_json_encode( $ready ); ?>, CLAIM = <?php echo wp_json_encode( rest_url( 'fika/v1/swim-claim' ) ); ?>, NONCE = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
	var BAG = <?php echo wp_json_encode( home_url( '/mix-your-own/?bag=open' ) ); ?>;
	if (CODES.length) window.FIKA_FREEKG = CODES[0][0]; // the before-you-go popup (snippet 9) then skips its 10% offer
	function cart() { try { return wp.data.select('wc/store/cart').getCartData() || {}; } catch (e) { return {}; } }
	function onCart() { return (cart().coupons || []).map(function (c) { return (c.code || '').toLowerCase(); }); }
	function grams() { var g = 0; (cart().items || []).forEach(function (it) { g += (it.quantity || 0) * (READY.indexOf(it.id) !== -1 ? 500 : 100); }); return g; }
	function amt(g) { return g >= 1000 ? (Math.round(g / 100) / 10) + ' kg' : g + ' g'; }
	function low(t) { return t.replace(/^A whole/, 'a whole'); }
	function d() { return window.wp ? wp.data.dispatch('wc/store/cart') : null; }
	var busy = false, box = null, list = null, err = null, nudge = null, lastKey = '';
	// "Use" tapped on My account > Rewards: once the bag is here, tap "Use" on that code (same checks as a tap)
	function autoUse() {
		var want = null; try { want = sessionStorage.getItem('fika_use_code'); } catch (e) {}
		if (!want || !box || busy || !(cart().items || []).length) return;
		var rows = box.querySelectorAll('.fr-row'), hit = null;
		for (var i = 0; i < rows.length; i++) if ((rows[i].getAttribute('data-code') || '').toLowerCase() === want.toLowerCase()) hit = rows[i];
		try { sessionStorage.removeItem('fika_use_code'); } catch (e) {}
		if (!hit) return;
		var b = hit.querySelector('.fr-use');
		if (b ? !b.disabled : false) { if (onCart().indexOf(want.toLowerCase()) === -1) b.click(); }
	}
	function mount() {
		var anchor = document.querySelector('.fika-shoptitle');
		if (!anchor || box) return;
		box = document.createElement('div');
		box.className = 'fika-freekg'; box.hidden = true;
		box.innerHTML = '<b></b><p class="fr-msg"></p><div class="fr-list"></div><p class="fr-nudge"></p><p class="fr-err"></p>';
		list = box.querySelector('.fr-list'); nudge = box.querySelector('.fr-nudge'); err = box.querySelector('.fr-err');
		anchor.parentNode.insertBefore(box, anchor.nextSibling);
		box.addEventListener('click', onClick);
		render();
		if (window.wp ? wp.data : null) wp.data.subscribe(function () { if (!busy) { render(); autoUse(); } });
	}
	function row(o) {
		var r = document.createElement('div');
		r.className = 'fr-row' + (o.on ? ' is-on' : '');
		if (o.code) r.setAttribute('data-code', o.code);
		if (o.lap) { r.setAttribute('data-lap', o.lap); r.setAttribute('data-g', o.g); }
		r.innerHTML = '<span>' + (o.fresh ? '<i class="fr-new">New</i>' : '') + '<strong></strong> &middot; <em style="font-style:normal"></em><small></small></span><button type="button" class="fr-use"></button>';
		r.querySelector('strong').textContent = o.title;
		r.querySelector('em').textContent = o.co;
		r.querySelector('small').textContent = o.note || '';
		var b = r.querySelector('.fr-use'); b.textContent = o.on ? 'Remove' : 'Use'; b.disabled = !!o.off;
		return r;
	}
	function sub() { var t = cart().totals || {}, m = t.currency_minor_unit == null ? 2 : t.currency_minor_unit; return (parseInt(t.total_items || '0', 10) || 0) / Math.pow(10, m); }
	// the bag as it is (free sweets included) counts towards a checkpoint
	function paid() { return grams(); }
	function worth(code) { return /^swim15-/i.test(code) ? 28 : /^swim10-/i.test(code) ? 11.2 : /^swim6-/i.test(code) ? 5.6 : 2.8; }
	function render() {
		if (!box) return;
		var on = onCart(), g = grams(), reach = REACH + g, rows = [], n = 0;
		var onSwim = on.filter(function (c) { return /^swim(3|6|10|15)-/.test(c); });
		function withMe(code) { return onSwim.indexOf(code.toLowerCase()) !== -1 ? onSwim : onSwim.concat([code.toLowerCase()]); }
		CODES.forEach(function (c) {
			var used = on.indexOf(c[0].toLowerCase()) !== -1, short = c[5] - REACH - paid();
			if (used) n++;
			// a reward worth more than the bag: say so (the rest would be lost)
			var note = short > 0 ? 'Add ' + amt(short) + ' more to use this one' : (!used ? (worth(c[0]) > sub() + 0.001 ? 'Worth $' + worth(c[0]).toFixed(2).replace(/\.00$/, '') + ': best on a bag of ' + amt(Math.round(worth(c[0]) / 0.28) * 10) + ' or more' : '') : '');
			rows.push({ code: c[0], title: c[1], co: c[3], on: used, off: !used ? short > 0 : false, note: note, fresh: c[5] > REACH });
		});
		// rewards not claimed yet: shown (and usable) once this bag reaches them
		var open = UNCL.filter(function (u) { return u[2] <= reach; }), ahead = UNCL.filter(function (u) { return u[2] > reach; })[0];
		open.forEach(function (u) {
			rows.push({ lap: u[0], g: u[1], title: u[3], co: u[4], fresh: u[2] > REACH, note: u[2] > REACH ? 'Unlocked by this order' : 'Ready to use' });
		});
		var aheadCp = ahead ? ahead[2] - reach : 0;
		var key = JSON.stringify([rows, aheadCp]);
		if (key === lastKey) return;
		lastKey = key;
		list.innerHTML = '';
		rows.forEach(function (o) { list.appendChild(row(o)); });
		nudge.innerHTML = '';
		if (ahead ? aheadCp <= 1000 : false) {
			nudge.appendChild(document.createTextNode('Add '));
			var x = document.createElement('b'); x.textContent = amt(aheadCp) + ' more'; nudge.appendChild(x);
			nudge.appendChild(document.createTextNode(' to reach ' + (ahead[1] / 1000) + ' kg and get '));
			var y = document.createElement('b'); y.textContent = low(ahead[3]); nudge.appendChild(y);
			nudge.appendChild(document.createTextNode(' in this order.'));
			var a = document.createElement('a'); a.href = BAG; a.textContent = 'Back to your bag →'; nudge.appendChild(a);
		}
		box.hidden = !rows.length ? !nudge.textContent : false;
		if (rows.length) window.FIKA_FREEKG = window.FIKA_FREEKG || 'reward'; // no 10% before-you-go offer next to a reward
		box.classList.toggle('is-on', n > 0);
		box.querySelector('b').textContent = n ? (n === 1 ? '1 reward on this order' : n + ' rewards on this order') : (rows.length ? (rows.length === 1 ? 'You have a sweet reward' : 'You have ' + rows.length + ' sweet rewards') : 'So close to a sweet reward');
		box.querySelector('.fr-msg').textContent = rows.length ? 'Use ' + (rows.length > 1 ? 'any of them' : 'it') + ' on this order, or keep ' + (rows.length > 1 ? 'them' : 'it') + ' for later.' + (rows.length > 1 ? ' Rewards can be combined.' : '') + ' Delivery not included.' : '';
	}
	function onClick(e) {
		var b = e.target.closest ? e.target.closest('.fr-use') : null, x = d();
		if (!b || b.disabled || busy || !x || !x.applyCoupon) return;
		var r = b.closest('.fr-row'), code = r.getAttribute('data-code');
		busy = true; err.textContent = ''; b.textContent = 'One moment…';
		var job;
		if (code) {
			job = onCart().indexOf(code.toLowerCase()) !== -1 ? x.removeCoupon(code) : x.applyCoupon(code);
		} else {
			// not claimed yet: claim it now (the server checks this bag reaches it), then put it on the order
			var lap = +r.getAttribute('data-lap'), g = +r.getAttribute('data-g');
			job = fetch(CLAIM, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE }, body: JSON.stringify({ lap: lap, g: g }) })
				.then(function (res) { return res.json().then(function (j) { if (!res.ok || !j.code) throw new Error(j.error || 'Sorry, that did not work.'); return j; }); })
				.then(function (j) {
					UNCL = UNCL.filter(function (u) { return !(u[0] === lap ? u[1] === g : false); });
					CODES.push([j.code, j.title, j.text, j.co, j.amount, j.need]);
					window.FIKA_FREEKG = CODES[0][0];
					return x.applyCoupon(j.code);
				});
		}
		job.catch(function (ex) { err.textContent = (ex && ex.message ? ex.message : 'Sorry, that did not work. Please try again.').replace(/<[^>]+>/g, ''); })
			.then(function () { busy = false; lastKey = ''; render(); });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
	setTimeout(mount, 1500);
})();
</script>
	<?php
}, 30 );

// ---------- Header account menu: show the distance to the next reward ----------
add_action( 'wp_footer', function () {
	if ( ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) || ! is_user_logged_in() ) {
		return;
	}
	$s = fika_swim_state();
	if ( ! $s ) {
		return;
	}
	$text = '';
	foreach ( $s['cps'] as $cp ) {
		if ( 'ready' === $cp['state'] ) {
			$text = 'A sweet reward is waiting!';
			break;
		}
	}
	if ( ! $text ) {
		foreach ( $s['cps'] as $cp ) {
			if ( $cp['g'] > $s['d'] + $s['o'] ) {
				$text = rtrim( rtrim( number_format( ( $cp['g'] - $s['d'] - $s['o'] ) / 1000, 1, '.', '' ), '0' ), '.' ) . ' kg to ' . lcfirst( $cp['title'] );
				break;
			}
		}
	}
	if ( ! $text ) {
		$text = 'Rewards unlock on delivery';
	}
	?>
<script>
(function () {
	var sm = document.querySelector('.fika-acct .fa-hi small');
	if (sm) sm.textContent = <?php echo wp_json_encode( $text ); ?>;
})();
</script>
	<?php
}, 40 );
