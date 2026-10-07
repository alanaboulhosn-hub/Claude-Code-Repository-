<?php
/**
 * Fika: "Swim to your free kilo" — the loyalty tracker for signed-in customers.
 * - A cartoon Swedish fish swims along a water lane towards the 8 kg mark. It counts:
 *     delivered kilos (Completed orders)           -> deep blue water
 *     kilos on their way (Processing / On hold)    -> light blue water
 *     what is in the bag right now (home page bag) -> candy stripes, moving live, gram by gram
 *   Orders marked "Undelivered" (a new order status), Cancelled, Failed or Refunded do not count; when an order
 *   stops counting, the fish swims back from where the customer last saw it.
 * - The free kilo itself does not count towards the next 8 kg.
 * - Every 8 kg DELIVERED earns a personal one-time code: 1 kg of sweets free (worth exactly the customer's
 *   own 1 kg, most expensive sweets first; delivery not included), locked to the customer's email. It does not
 *   combine with the 10% before-you-go offer, and that offer is not made to customers holding a free kilo.
 *   If delivered kilos later drop below the mark (an order is marked Undelivered), an unused code is withdrawn.
 * - Shown on the home page (above the shop) and the My account dashboard; never to visitors who are not signed in.
 *   At checkout a "Use my free kilo" button applies the code.
 * - Shop managers can preview on the home page: ?fika_fish=5.2 (delivered kg) &fika_pending=1 (kg on the way).
 * Needs snippet 10 (Fika accounts). Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-loyalty.php
 */

if ( ! defined( 'FIKA_SWIM_GOAL' ) ) {
	define( 'FIKA_SWIM_GOAL', 8000 );   // grams per free kilo
}
if ( ! defined( 'FIKA_FREE_KG_VALUE' ) ) {
	define( 'FIKA_FREE_KG_VALUE', 25 ); // fallback value of 1 kg; at checkout the real value of the customer's kilo is used
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
	// Grams an order adds to the swim: everything except the free kilo it may have used
	function fika_swim_order_grams( $order ) {
		$grams = fika_order_grams( $order );
		foreach ( $order->get_coupon_codes() as $code ) {
			if ( 0 === stripos( $code, 'freekg-' ) ) {
				$grams -= min( 1000, $grams );
				break;
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

if ( ! function_exists( 'fika_swim_rewards' ) ) {
	// Issue (or withdraw) free-kilo codes to match the delivered grams; returns [ [code, used], ... ]
	function fika_swim_rewards( $user_id, $delivered = null ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! class_exists( 'WC_Coupon' ) ) {
			return array();
		}
		if ( null === $delivered ) {
			$t         = fika_swim_totals( $user_id );
			$delivered = $t['delivered'];
		}
		$codes  = get_user_meta( $user_id, 'fika_rewards', true );
		$codes  = is_array( $codes ) ? array_values( $codes ) : array();
		$earned = (int) floor( $delivered / FIKA_SWIM_GOAL );
		$user   = get_userdata( $user_id );
		$used   = function ( $code ) {
			$c = new WC_Coupon( $code );
			return $c->get_id() ? $c->get_usage_count() >= 1 : true;
		};
		// an order stopped counting: withdraw the newest unused codes above what is earned now
		for ( $i = count( $codes ) - 1; $i >= 0 && count( $codes ) > $earned; $i-- ) {
			if ( ! $used( $codes[ $i ] ) ) {
				$c = new WC_Coupon( $codes[ $i ] );
				if ( $c->get_id() ) {
					$c->delete( true );
				}
				array_splice( $codes, $i, 1 );
				update_user_meta( $user_id, 'fika_rewards', $codes );
			}
		}
		while ( count( $codes ) < $earned && $user ) {
			$code = 'FREEKG-' . strtoupper( wp_generate_password( 6, false, false ) );
			if ( wc_get_coupon_id_by_code( $code ) ) {
				continue;
			}
			$c = new WC_Coupon();
			$c->set_code( $code );
			$c->set_discount_type( 'fixed_cart' );
			$c->set_amount( FIKA_FREE_KG_VALUE );
			$c->set_individual_use( true ); // not combined with the 10% before-you-go offer
			$c->set_usage_limit( 1 );
			$c->set_usage_limit_per_user( 1 );
			$c->set_email_restrictions( array( $user->user_email ) );
			$c->set_description( sprintf( 'Fika free kilo #%d for %s (user %d): 1 kg of sweets free, earned at %d kg delivered.', count( $codes ) + 1, $user->user_email, $user_id, ( count( $codes ) + 1 ) * FIKA_SWIM_GOAL / 1000 ) );
			$c->update_meta_data( '_fika_reward_user', $user_id );
			$c->save();
			$codes[] = $code;
			update_user_meta( $user_id, 'fika_rewards', $codes );
		}
		$out = array();
		foreach ( $codes as $code ) {
			$out[] = array( $code, $used( $code ) );
		}
		return $out;
	}
}

// Check for a new (or withdrawn) free kilo whenever an order changes status
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( $order && $order->get_customer_id() ) {
		fika_swim_rewards( $order->get_customer_id() );
	}
}, 30, 4 );

// ---------- The free kilo is worth exactly 1 kg of what is in the bag (most expensive sweets first) ----------
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
add_filter( 'woocommerce_coupon_get_amount', function ( $amount, $coupon ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $amount;
	}
	if ( 0 === stripos( $coupon->get_code(), 'freekg-' ) && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		return fika_free_kg_value();
	}
	return $amount;
}, 10, 2 );

// ---------- What the tracker shows for the signed-in customer ----------
if ( ! function_exists( 'fika_swim_state' ) ) {
	function fika_swim_state() {
		if ( ! is_user_logged_in() || ! function_exists( 'fika_order_grams' ) ) {
			return null;
		}
		$uid     = get_current_user_id();
		$u       = wp_get_current_user();
		$t       = fika_swim_totals( $uid );
		$preview = isset( $_GET['fika_fish'] ) && current_user_can( 'manage_woocommerce' );
		if ( $preview ) {
			$t['delivered'] = (int) round( floatval( wp_unslash( $_GET['fika_fish'] ) ) * 1000 );
			$t['placed']    = isset( $_GET['fika_pending'] ) ? (int) round( floatval( wp_unslash( $_GET['fika_pending'] ) ) * 1000 ) : 0;
		}
		$code    = '';
		$rewards = $preview ? array() : fika_swim_rewards( $uid, $t['delivered'] );
		foreach ( $rewards as $r ) {
			if ( ! $r[1] ) {
				$code = $r[0];
				break;
			}
		}
		$earned = (int) floor( $t['delivered'] / FIKA_SWIM_GOAL );
		if ( $preview && $earned > 0 ) {
			$code = 'FREEKG-PREVIEW';
		}
		$base = ( $code ? $earned - 1 : $earned ) * FIKA_SWIM_GOAL; // grams used up by earlier laps
		return array(
			'uid'       => $uid,
			'name'      => $u->first_name ? $u->first_name : $u->display_name,
			'goal'      => FIKA_SWIM_GOAL,
			'lap'       => (int) ( $base / FIKA_SWIM_GOAL ) + 1,
			'code'      => $code,
			'delivered' => $code ? FIKA_SWIM_GOAL : max( 0, $t['delivered'] - $base ),
			'placed'    => $code ? 0 : $t['placed'],
		);
	}
}

if ( ! function_exists( 'fika_swim_html' ) ) {
	function fika_swim_html( $where ) {
		$s = fika_swim_state();
		if ( ! $s ) {
			return '';
		}
		$goal_kg = FIKA_SWIM_GOAL / 1000;
		$data    = array(
			'uid'  => $s['uid'],
			'name' => $s['name'],
			'goal' => $s['goal'],
			'lap'  => $s['lap'],
			'd'    => $s['delivered'],
			'o'    => $s['placed'],
			'done' => (bool) $s['code'],
		);
		$fmt   = function ( $g ) {
			return rtrim( rtrim( number_format( $g / 1000, 1, '.', '' ), '0' ), '.' );
		};
		$total = min( FIKA_SWIM_GOAL, $s['delivered'] + $s['placed'] );
		ob_start();
		?>
<section class="fika-swim fika-swim-<?php echo esc_attr( $where ); ?><?php echo $s['code'] ? ' is-done' : ''; ?>" data-swim="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" aria-label="Your progress to a free kilo">
	<div class="fs-head">
		<div class="fs-intro">
			<p class="fs-kicker"><?php echo $s['lap'] > 1 ? 'Lap ' . (int) $s['lap'] . ' &middot; ' : ''; ?>Fika rewards</p>
			<h2 class="fs-title"><?php echo $s['code'] ? 'You made it! Your next kilo is on us' : 'Swim to your free kilo'; ?></h2>
			<p class="fs-sub"><?php
			if ( $s['code'] ) {
				echo esc_html( ( $s['name'] ? $s['name'] . ', you' : 'You' ) . ' reached ' . $goal_kg . ' kg of Fika sweets. Here is 1 kg on the house.' );
			} else {
				echo esc_html( $fmt( FIKA_SWIM_GOAL - $total ) . ' kg to go. At ' . $goal_kg . ' kg your next kilo is free.' );
			}
			?></p>
			<p class="fs-legend"></p>
		</div>
		<div class="fs-big" aria-hidden="true"><b><?php echo esc_html( $fmt( $total ) ); ?></b><span>/ <?php echo (int) $goal_kg; ?> kg</span></div>
	</div>
	<div class="fs-track" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $goal_kg; ?>" aria-valuenow="<?php echo esc_attr( $total / 1000 ); ?>">
		<div class="fs-lane">
			<div class="fs-seg fs-water"><svg class="fs-wave" viewBox="0 0 120 12" preserveAspectRatio="none" aria-hidden="true"><path d="M0 6 Q15 0 30 6 T60 6 T90 6 T120 6 V12 H0 Z"/></svg></div>
			<div class="fs-seg fs-way"></div>
			<div class="fs-seg fs-inbag"></div>
			<div class="fs-fish" aria-hidden="true"><div class="fs-turn">
				<svg viewBox="0 0 100 100"><defs><linearGradient id="fsFishG<?php echo esc_attr( $where ); ?>" x1="0" x2="1"><stop offset="0" stop-color="#ff6a3d"/><stop offset="1" stop-color="#e3241f"/></linearGradient></defs>
					<g class="fs-tail"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/></g>
					<path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="url(#fsFishG<?php echo esc_attr( $where ); ?>)" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/>
					<path d="M38 40 Q42 50 38 62 M48 38 Q52 50 48 64" fill="none" stroke="#a3160f" stroke-opacity=".35" stroke-width="1.6"/>
					<ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/>
					<circle cx="24" cy="46" r="5.2" fill="#fff"/><circle cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/>
					<path d="M10 56 Q14 59 18 57" fill="none" stroke="#a3160f" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</div></div>
		</div>
		<div class="fs-goal" aria-hidden="true">
			<svg viewBox="0 0 48 48"><path d="M10 18h28l-2.4 22H12.4L10 18z" fill="#fff" stroke="#004aad" stroke-width="2.4" stroke-linejoin="round"/><path d="M17 18v-3a7 7 0 0 1 14 0v3" fill="none" stroke="#004aad" stroke-width="2.4" stroke-linecap="round"/><text x="24" y="34" text-anchor="middle" font-family="Bebas Neue, Impact, sans-serif" font-size="11" fill="#004aad">FREE</text></svg>
			<span><?php echo (int) $goal_kg; ?> kg</span>
		</div>
	</div>
	<div class="fs-ticks" aria-hidden="true"><?php for ( $i = 0; $i < $goal_kg; $i++ ) : ?><i data-i="<?php echo (int) $i; ?>"><?php echo (int) $i; ?></i><?php endfor; ?></div>
	<?php if ( $s['code'] ) : ?>
	<div class="fs-reward">
		<div class="fs-code"><span>Your code</span><b><?php echo esc_html( $s['code'] ); ?></b></div>
		<button type="button" class="fs-copy" data-code="<?php echo esc_attr( $s['code'] ); ?>">Copy code</button>
		<p>Use it at checkout: 1 kg of the sweets in your bag is free, whichever you pick (delivery not included). One use, for your account only.</p>
	</div>
	<?php endif; ?>
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

.fika-swim .fs-track { position: relative; display: flex; align-items: center; gap: 10px; }
.fika-swim .fs-lane { position: relative; flex: 1; height: 62px; border-radius: 999px; background: #fdeaf2; overflow: visible;
  background-image: repeating-linear-gradient(90deg, transparent 0 16px, rgba(0, 74, 173, .08) 16px 26px); background-size: 100% 3px; background-repeat: no-repeat; background-position: 0 50%; }
.fika-swim .fs-seg { position: absolute; top: 0; bottom: 0; left: 0; width: 0; overflow: hidden; }
.fika-swim .fs-water { border-radius: 999px; z-index: 1; background: linear-gradient(180deg, #6fb6ff 0%, #2f86ea 55%, #1c6fd6 100%); box-shadow: inset 0 -6px 12px rgba(0, 40, 120, .18); }
.fika-swim .fs-water::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 70%, rgba(255,255,255,.35) 0 3px, transparent 4px), radial-gradient(circle at 55% 40%, rgba(255,255,255,.3) 0 2px, transparent 3px), radial-gradient(circle at 80% 75%, rgba(255,255,255,.3) 0 2.5px, transparent 3.5px); background-size: 90px 62px; animation: fsBub 4s linear infinite; }
.fika-swim .fs-wave { position: absolute; left: 0; top: -2px; width: 200%; height: 12px; fill: rgba(255, 255, 255, .35); animation: fsWave 3s linear infinite; }
.fika-swim .fs-way { z-index: 0; border-radius: 0 999px 999px 0; background: linear-gradient(180deg, #d4e9ff, #a9d3ff); }
.fika-swim .fs-inbag { z-index: 0; top: 6px; bottom: 6px; border-radius: 0 999px 999px 0; background: repeating-linear-gradient(135deg, rgba(255, 111, 165, .55) 0 8px, rgba(255, 210, 63, .55) 8px 16px); background-size: 22.6px 22.6px; animation: fsCandy 1s linear infinite; }
.fika-swim .fs-fish { position: absolute; top: 50%; left: 0; width: var(--fw); height: var(--fw); margin-top: calc(var(--fw) / -2); z-index: 2; pointer-events: none; will-change: transform; }
.fika-swim .fs-turn { width: 100%; height: 100%; transform: scaleX(-1); transition: transform .35s ease; }
.fika-swim.is-back .fs-turn { transform: scaleX(1); }
.fika-swim .fs-fish svg { width: 100%; height: 100%; filter: drop-shadow(0 3px 3px rgba(0, 40, 120, .25)); animation: fsBob 1.6s ease-in-out infinite; overflow: visible; }
.fika-swim .fs-tail { transform-origin: 64px 51px; animation: fsTail .9s ease-in-out infinite; }
.fika-swim.is-swimming .fs-tail { animation-duration: .28s; }
.fika-swim.is-swimming .fs-fish svg { animation-duration: .7s; }
.fika-swim .fs-bubble { position: absolute; z-index: 3; width: 8px; height: 8px; border-radius: 50%; border: 1.6px solid rgba(255, 255, 255, .95); background: rgba(160, 205, 255, .35); pointer-events: none; animation: fsRise 1.3s ease-out forwards; }
.fika-swim .fs-goal { flex: none; width: var(--goal); display: flex; flex-direction: column; align-items: center; font-family: 'Bebas Neue', Impact, sans-serif; font-size: 18px; color: var(--fs-blue); }
.fika-swim .fs-goal svg { width: 46px; height: 46px; transform-origin: 50% 80%; }
.fika-swim.is-there .fs-goal svg { animation: fsGoal 1.4s ease-in-out infinite; }
.fika-swim.is-done.is-swum .fs-fish svg { animation: fsJump 1.4s ease-in-out infinite; }
.fika-swim .fs-ticks { position: relative; height: 18px; margin: 6px calc(var(--goal) + 10px) 0 0; font-size: 12.5px; font-weight: 600; color: #8b97b3; }
.fika-swim .fs-ticks i { position: absolute; top: 0; left: 0; transform: translateX(-50%); font-style: normal; }
.fika-swim .fs-ticks i::before { content: ''; position: absolute; left: 50%; top: -7px; width: 2px; height: 5px; border-radius: 1px; background: #c9d4ea; }
.fika-swim .fs-reward { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; margin-top: 16px; padding: 16px 18px; border-radius: 18px; background: #fdeaf2; }
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
  .fika-swim .fs-goal svg { width: 36px; height: 36px; }
  .fika-swim .fs-goal { font-size: 15px; }
  .fika-swim .fs-ticks { font-size: 11px; }
  .fika-swim .fs-code b { font-size: 25px; }
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
		var G = s.goal || 8000, done = !!s.done;
		var lane = el.querySelector('.fs-lane'), fish = el.querySelector('.fs-fish');
		var water = el.querySelector('.fs-water'), way = el.querySelector('.fs-way'), inbag = el.querySelector('.fs-inbag');
		var big = el.querySelector('.fs-big b'), sub = el.querySelector('.fs-sub'), legend = el.querySelector('.fs-legend'), title = el.querySelector('.fs-title');
		var ticks = el.querySelectorAll('.fs-ticks i');
		var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
		var seenKey = 'fika_swim_seen_' + s.uid;
		var bagG = 0, cur = 0, target = 0, started = false, raf = 0, lastBub = 0, lastTs = 0, wasThere = false;

		// where the fish ends up: delivered + on the way + in the bag (one lap at most)
		function goalPos() { return done ? G : Math.min(G, s.d + s.o + bagG); }
		function x(g, W, fw) { return (W - fw) * Math.min(1, Math.max(0, g / G)) + fw * 0.86; }
		function paint() {
			var W = lane.clientWidth, fw = fish.offsetWidth;
			var dEnd = Math.min(cur, s.d), oEnd = Math.min(cur, s.d + s.o);
			water.style.width = (dEnd > 0 ? x(dEnd, W, fw) : 0) + 'px';
			var oStart = s.d > 0 ? x(s.d, W, fw) - 30 : 0;
			way.style.left = oStart + 'px';
			way.style.width = (oEnd > s.d ? Math.max(0, x(oEnd, W, fw) - oStart) : 0) + 'px';
			var bStart = (s.d + s.o) > 0 ? x(s.d + s.o, W, fw) - 30 : 0;
			inbag.style.left = bStart + 'px';
			inbag.style.width = (cur > s.d + s.o ? Math.max(0, x(cur, W, fw) - bStart) : 0) + 'px';
			fish.style.transform = 'translateX(' + ((W - fw) * Math.min(1, cur / G)) + 'px)';
			for (var i = 0; i < ticks.length; i++) ticks[i].style.left = x(+ticks[i].getAttribute('data-i') * 1000, W, fw) + 'px';
			if (big) big.textContent = kg(cur);
		}
		function words() {
			if (done) return;
			var tot = s.d + s.o + bagG, left = Math.max(0, G - tot), name = s.name ? ', ' + s.name : '';
			if (tot >= G) {
				title.textContent = 'Nearly there!';
				sub.innerHTML = '';
				sub.appendChild(document.createTextNode(bagG > 0 ? 'This bag takes you to ' + (G / 1000) + ' kg' + name + '! Your free kilo unlocks once it is delivered.' : 'Your free kilo unlocks once your orders on their way are delivered' + name + '.'));
			} else {
				title.textContent = 'Swim to your free kilo';
				sub.innerHTML = '<b></b>';
				sub.firstChild.textContent = kg(left) + ' kg';
				sub.appendChild(document.createTextNode(' to go' + name + '. At ' + (G / 1000) + ' kg your next kilo is free.' + (bagG > 0 ? ' Every gram in your bag moves the fish!' : '')));
			}
			var parts = [];
			if (s.d > 0) parts.push(['l-d', kg(s.d) + ' kg delivered']);
			if (s.o > 0) parts.push(['l-o', kg(s.o) + ' kg on its way']);
			if (bagG > 0) parts.push(['l-b', kg(bagG) + ' kg in your bag']);
			legend.innerHTML = '';
			parts.forEach(function (p) { var sp = document.createElement('span'); sp.className = p[0]; sp.innerHTML = '<i></i>'; sp.appendChild(document.createTextNode(p[1])); legend.appendChild(sp); });
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
		function confetti() {
			var g = el.querySelector('.fs-goal').getBoundingClientRect(), lr = el.getBoundingClientRect();
			var cols = ['#ff6fa5', '#ffd23f', '#4aa8ff', '#ff4d4d', '#7ad67a', '#c77dff', '#ff9f43'];
			for (var i = 0; i < 26; i++) {
				var c = document.createElement('i');
				c.className = 'fs-confetti';
				var a = Math.random() * Math.PI * 2, d = 40 + Math.random() * 90;
				c.style.left = (g.left - lr.left + g.width / 2) + 'px';
				c.style.top = (g.top - lr.top + g.height / 3) + 'px';
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
			el.classList.add('is-swum');
			setTimeout(function () { if (!el.classList.contains('is-swimming')) el.classList.remove('is-back'); }, 500);
			var there = cur >= G;
			if (there) { el.classList.add('is-there'); } else { el.classList.remove('is-there'); }
			if (there ? !wasThere : false) { if (!reduce) confetti(); }
			wasThere = there;
			// remember where the customer last saw the fish (without the bag), to swim from there next time
			lsSet(seenKey, JSON.stringify({ lap: s.lap, g: done ? G : Math.min(G, s.d + s.o) }));
		}
		function go() { target = goalPos(); if (!raf) raf = requestAnimationFrame(frame); }
		function onBag() {
			var g = readBag();
			if (g === bagG) return;
			bagG = g;
			words();
			if (started) go();
		}
		// start from where the fish was last time (so an order that stopped counting swims back)
		var seen = null;
		try { seen = JSON.parse(lsGet(seenKey) || 'null'); } catch (e) {}
		cur = seen ? (seen.lap === s.lap ? Math.min(G, +seen.g || 0) : 0) : 0;
		bagG = readBag();
		words();
		paint();
		this.start = function () { if (started) return; started = true; setTimeout(go, 250); };
		window.addEventListener('fikabag', function () { setTimeout(onBag, 0); });
		window.addEventListener('storage', function (e) { if (e.key === 'fika_bag_v1') onBag(); });
		setInterval(onBag, 800);
		window.addEventListener('resize', paint);
		var copy = el.querySelector('.fs-copy');
		if (copy) copy.addEventListener('click', function () {
			var code = copy.getAttribute('data-code');
			function ok() { copy.textContent = 'Copied!'; setTimeout(function () { copy.textContent = 'Copy code'; }, 1800); }
			if (navigator.clipboard ? navigator.clipboard.writeText : false) navigator.clipboard.writeText(code).then(ok, ok); else ok();
		});
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

// ---------- Home page (above the shop cards) and shop page (under the banner) ----------
add_action( 'wp_footer', function () {
	if ( ! ( is_front_page() || is_page( 'shop' ) ) || ! is_user_logged_in() ) {
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
	// home: just above the shop cards; shop page: between the banner and the filter bar
	var tpl = document.getElementById('fikaSwimTpl'), shop = document.getElementById('fsBar') || document.getElementById('shop');
	if (!tpl || !shop || document.querySelector('.fika-swim-home')) return;
	shop.parentNode.insertBefore(tpl.content.cloneNode(true), shop);
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

// ---------- Checkout: one tap to use an unused free kilo ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! is_user_logged_in() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	$s = fika_swim_state();
	if ( ! $s || ! $s['code'] || 'FREEKG-PREVIEW' === $s['code'] ) {
		return;
	}
	?>
<style>
.fika-freekg { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 18px; max-width: 1100px; margin: 0 auto 22px; padding: 16px 22px; border-radius: 20px; background: #fff; box-shadow: 0 6px 24px rgba(0, 74, 173, .08); border-left: 6px solid #2fb36b; font-family: 'Outfit', Arial, sans-serif; color: #1b2a4a; }
.fika-freekg b { display: block; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 26px; letter-spacing: .02em; color: #004aad; line-height: 1.05; }
.fika-freekg span { font-size: 15px; }
.fika-freekg button { height: 46px; padding: 0 24px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fika-freekg button:hover { background: #003a8a; }
.fika-freekg.is-on { border-left-color: #004aad; }
</style>
<script>
(function () {
	var CODE = <?php echo wp_json_encode( $s['code'] ); ?>;
	window.FIKA_FREEKG = CODE; // the before-you-go popup (snippet 9) then skips its 10% offer
	function applied() {
		try { return (wp.data.select('wc/store/cart').getCartData().coupons || []).some(function (c) { return (c.code || '').toLowerCase() === CODE.toLowerCase(); }); } catch (e) { return false; }
	}
	function mount() {
		var anchor = document.querySelector('.fika-shoptitle');
		if (!anchor || document.querySelector('.fika-freekg')) return;
		var box = document.createElement('div');
		box.className = 'fika-freekg';
		box.innerHTML = '<div><b>Your free kilo is ready</b><span>You swam to 8 kg! Take 1 kg of your sweets off this order, whichever you pick.</span></div><button type="button">Use my free kilo</button>';
		anchor.parentNode.insertBefore(box, anchor.nextSibling);
		var btn = box.querySelector('button');
		function sync() {
			if (applied()) { box.classList.add('is-on'); box.querySelector('b').textContent = 'Free kilo applied'; box.querySelector('span').textContent = '1 kg of your sweets is off this order. Enjoy, and keep swimming!'; btn.style.display = 'none'; }
		}
		btn.addEventListener('click', function () {
			var d = window.wp ? wp.data.dispatch('wc/store/cart') : null;
			if (!d || !d.applyCoupon) return;
			btn.disabled = true; btn.textContent = 'Applying…';
			d.applyCoupon(CODE).then(sync).catch(function () { btn.disabled = false; btn.textContent = 'Use my free kilo'; });
		});
		sync();
		if (window.wp ? wp.data : null) wp.data.subscribe(sync);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
	setTimeout(mount, 1500);
})();
</script>
	<?php
}, 30 );

// ---------- Header account menu: show the distance to the free kilo ----------
add_action( 'wp_footer', function () {
	if ( ! ( is_front_page() || is_page( 'shop' ) ) || ! is_user_logged_in() ) {
		return;
	}
	$s = fika_swim_state();
	if ( ! $s ) {
		return;
	}
	$left = max( 0, FIKA_SWIM_GOAL - $s['delivered'] - $s['placed'] ) / 1000;
	$text = $s['code'] ? 'Your free kilo is ready!' : ( $left > 0 ? rtrim( rtrim( number_format( $left, 1, '.', '' ), '0' ), '.' ) . ' kg to your free kilo' : 'Free kilo unlocks on delivery' );
	?>
<script>
(function () {
	var sm = document.querySelector('.fika-acct .fa-hi small');
	if (sm) sm.textContent = <?php echo wp_json_encode( $text ); ?>;
})();
</script>
	<?php
}, 40 );
