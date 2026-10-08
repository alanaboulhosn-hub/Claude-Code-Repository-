<?php
/**
 * Fika: mystery tastes — two spin stops on the rewards lane (1.5 kg and 12.5 kg of every 15 kg lap).
 * - When the customer's orders (placed or delivered), or the bag they are filling, pass a stop, the little wheel on
 *   the lane lights up and spins, and the candy wheel opens by itself in the middle of the page (once per visit if
 *   closed without spinning; the little wheel on the lane opens it again). The candy wheel: every candy in the shop (in stock, shown in the
 *   shop, not Ready Mix) with its photo, narrowed first if they like: All sweets, Gelatin-free, Gluten-free, Vegan
 *   (a filter shows once products carry the tag gelatin-free, gluten-free or vegan). They tap the candy button; the
 *   server picks the candy (so the result cannot be chosen) and the wheel lands on it.
 * - The win (user meta fika_tastes: lap, stop, candy, won / used) goes into the order that reaches the stop, or the
 *   next one: a free line "Mystery taste: 50 g, free" of that candy (price 0, quantity locked), added once the
 *   bag at checkout reaches the stop and has something else in it. Removing it at checkout keeps it for a later
 *   order. Placing the order uses it; the order line says "Mystery taste: 50 g, free" for packing.
 * - The free 50 g does not count towards the rewards track. Cost: 50 g at landing cost (about $0.80) per stop.
 * - A card above the tracker (home, Mix your own, Ready Mix, My account) and a line in the bag drawer say which
 *   taste is waiting. Needs snippet 11 (Fika rewards).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-taste.php
 */

if ( ! function_exists( 'fika_taste_list' ) ) {
	// the spin stops of a lap (grams into the lap)
	function fika_taste_stops() {
		return array( 1500, 12500 );
	}
	// [ 'lap:g' => { lap, g, pid, status (won / used), at, order } ]
	function fika_taste_list( $uid ) {
		$t = get_user_meta( (int) $uid, 'fika_tastes', true );
		return is_array( $t ) ? $t : array();
	}
	function fika_taste_need( $key ) {
		list( $lap, $g ) = array_map( 'intval', explode( ':', $key ) );
		return ( $lap - 1 ) * ( defined( 'FIKA_SWIM_LAP' ) ? FIKA_SWIM_LAP : 15000 ) + $g;
	}
	// the candies on the wheel, with the filters they belong to
	function fika_taste_candies() {
		$out = array();
		$ids = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'stock_status' => 'instock', 'visibility' => 'catalog', 'orderby' => 'menu_order', 'order' => 'ASC', 'return' => 'ids' ) );
		foreach ( $ids as $id ) {
			if ( has_term( 'ready-mix', 'product_cat', $id ) ) {
				continue;
			}
			$p = wc_get_product( $id );
			if ( ! $p || ! $p->is_purchasable() ) {
				continue;
			}
			$tags  = wp_get_post_terms( $id, 'product_tag', array( 'fields' => 'slugs' ) );
			$img   = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
			$out[] = array(
				'id'   => $id,
				'name' => html_entity_decode( $p->get_name() ),
				'img'  => $img ? $img : '',
				'f'    => array_values( array_intersect( array( 'gelatin-free', 'gluten-free', 'vegan' ), is_array( $tags ) ? $tags : array() ) ),
			);
		}
		return $out;
	}
	function fika_taste_is_item( $cart_item ) {
		return ! empty( $cart_item['fika_taste'] );
	}
	// put won tastes in the cart once the bag reaches their stop (and has something else in it); take them out if not
	function fika_taste_ensure() {
		static $busy = false;
		if ( $busy || ! is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'fika_swim_reach' ) ) {
			return;
		}
		$uid  = get_current_user_id();
		$won  = array_filter( fika_taste_list( $uid ), function ( $t ) { return 'won' === $t['status']; } );
		$have = array();
		$other = false;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( fika_taste_is_item( $item ) ) {
				$have[ $item['fika_taste'] ] = $key;
			} else {
				$other = true;
			}
		}
		if ( ! $won && ! $have ) {
			return;
		}
		$reach = fika_swim_reach( $uid ) + fika_swim_cart_grams();
		$later = WC()->session ? (array) WC()->session->get( 'fika_taste_later' ) : array();
		$busy  = true;
		$GLOBALS['fika_taste_auto'] = true; // our own removals are not "keep it for later"
		foreach ( $have as $k => $cart_key ) {
			if ( ! $other || empty( $won[ $k ] ) || fika_taste_need( $k ) > $reach ) {
				WC()->cart->remove_cart_item( $cart_key );
			}
		}
		$GLOBALS['fika_taste_auto'] = false;
		if ( $other ) {
			foreach ( $won as $k => $t ) {
				if ( isset( $have[ $k ] ) || ! empty( $later[ $k ] ) || fika_taste_need( $k ) > $reach ) {
					continue;
				}
				$p = wc_get_product( (int) $t['pid'] );
				if ( $p && $p->is_purchasable() && $p->is_in_stock() ) {
					WC()->cart->add_to_cart( (int) $t['pid'], 1, 0, array(), array( 'fika_taste' => $k ) );
				}
			}
		}
		$busy = false;
	}
}

// ---------- the taste in the cart: free, 50 g, one per order ----------
add_action( 'woocommerce_add_to_cart', function ( $key, $pid, $qty, $vid, $var, $data ) {
	if ( empty( $data['fika_taste'] ) ) {
		fika_taste_ensure();
	}
}, 20, 6 );
add_action( 'woocommerce_cart_loaded_from_session', 'fika_taste_ensure', 99 );
// the bag changed at checkout: the taste follows (in only while the bag reaches its stop)
add_action( 'woocommerce_after_cart_item_quantity_update', function () {
	fika_taste_ensure();
}, 20 );
add_action( 'woocommerce_cart_item_removed', function ( $key, $cart ) {
	$removed = isset( $cart->removed_cart_contents[ $key ] ) ? $cart->removed_cart_contents[ $key ] : array();
	if ( fika_taste_is_item( $removed ) ) {
		// taken out by the customer (not by us): keep it for a later order
		if ( WC()->session && empty( $GLOBALS['fika_taste_auto'] ) ) {
			$later = (array) WC()->session->get( 'fika_taste_later' );
			$later[ $removed['fika_taste'] ] = 1;
			WC()->session->set( 'fika_taste_later', $later );
		}
		return;
	}
	fika_taste_ensure();
}, 20, 2 );
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	foreach ( $cart->get_cart() as $key => $item ) {
		if ( fika_taste_is_item( $item ) ) {
			$item['data']->set_price( 0 );
			if ( 1 !== (int) $item['quantity'] ) {
				$cart->cart_contents[ $key ]['quantity'] = 1;
			}
		}
	}
}, 99 );
add_filter( 'woocommerce_store_api_product_quantity_editable', function ( $editable, $product, $cart_item ) {
	return ( is_array( $cart_item ) && fika_taste_is_item( $cart_item ) ) ? false : $editable;
}, 10, 3 );
add_filter( 'woocommerce_get_item_data', function ( $data, $cart_item ) {
	if ( ! fika_taste_is_item( $cart_item ) ) {
		return $data;
	}
	$data   = array_values( array_filter( $data, function ( $d ) { return ! in_array( $d['key'], array( 'Weight', 'Amount' ), true ); } ) );
	$data[] = array( 'key' => 'Mystery taste', 'value' => '50 g, free' );
	return $data;
}, 20, 2 );
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
	if ( fika_taste_is_item( $values ) ) {
		$item->delete_meta_data( 'Weight' );
		$item->add_meta_data( 'Mystery taste', '50 g, free', true );
		$item->add_meta_data( '_fika_taste', $values['fika_taste'], true );
	}
}, 20, 3 );
// the order is placed: its tastes are used
add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
	$uid = $order->get_customer_id();
	if ( ! $uid ) {
		return;
	}
	$list = fika_taste_list( $uid );
	$hit  = false;
	foreach ( $order->get_items() as $item ) {
		$k = (string) $item->get_meta( '_fika_taste' );
		if ( $k && isset( $list[ $k ] ) ) {
			$list[ $k ]['status'] = 'used';
			$list[ $k ]['order']  = $order->get_id();
			$hit                  = true;
		}
	}
	if ( $hit ) {
		update_user_meta( $uid, 'fika_tastes', $list );
	}
	if ( WC()->session ) {
		WC()->session->set( 'fika_taste_later', null );
	}
} );
// an order with a taste was refused / cancelled / refunded: the customer never got it, so the same candy is
// saved again as their bonus (no new spin); it rejoins the next order that reaches the stop
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order || ! $order->get_customer_id() || ! in_array( $to, array( 'cancelled', 'failed', 'refunded', 'undelivered' ), true ) ) {
		return;
	}
	$uid  = $order->get_customer_id();
	$list = fika_taste_list( $uid );
	$hit  = false;
	foreach ( $list as $k => $t ) {
		if ( 'used' === $t['status'] && (int) ( $t['order'] ?? 0 ) === (int) $order_id ) {
			$list[ $k ]['status'] = 'won';
			unset( $list[ $k ]['order'] );
			$hit = true;
		}
	}
	if ( $hit ) {
		update_user_meta( $uid, 'fika_tastes', $list );
		$order->add_order_note( 'Fika: the mystery taste in this order is saved again for the customer (no new spin).' );
	}
}, 20, 4 );

// an account is deleted: remove its spin locks too
add_action( 'delete_user', function ( $uid ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'fika_spin_' . (int) $uid . '_' ) . '%' ) );
} );

// (the free 50 g does not count towards the rewards track: see fika_swim_order_grams in fika-loyalty.php)

// ---------- spin: POST /wp-json/fika/v1/taste-spin { lap, g, filter, bag } ----------
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/taste-spin', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => function ( $req ) {
			$uid = get_current_user_id();
			$lap = (int) $req->get_param( 'lap' );
			$g   = (int) $req->get_param( 'g' );
			$key = $lap . ':' . $g;
			if ( $lap < 1 || ! in_array( $g, fika_taste_stops(), true ) || ! function_exists( 'fika_swim_reach' ) ) {
				return new WP_REST_Response( array( 'error' => 'This spin does not exist.' ), 400 );
			}
			$list = fika_taste_list( $uid );
			if ( isset( $list[ $key ] ) ) {
				return new WP_REST_Response( array( 'error' => 'You already spun this wheel.' ), 400 );
			}
			// one spin per account, stop and lap, even with two taps at the same moment: add_option only succeeds once
			$lock = 'fika_spin_' . $uid . '_' . $lap . '_' . $g;
			if ( ! add_option( $lock, time(), '', 'no' ) ) {
				return new WP_REST_Response( array( 'error' => 'You already spun this wheel.' ), 400 );
			}
			// the stop has to be reached by the orders, plus what is in the bag (the taste only joins an order that reaches it)
			$bag = min( 20000, max( 0, (int) $req->get_param( 'bag' ) ) );
			if ( fika_taste_need( $key ) > fika_swim_reach( $uid ) + max( $bag, fika_swim_cart_grams() ) ) {
				delete_option( $lock );
				return new WP_REST_Response( array( 'error' => 'Fill your bag up to this stop to spin.' ), 400 );
			}
			$f     = sanitize_key( (string) $req->get_param( 'filter' ) );
			$cands = array_values( array_filter( fika_taste_candies(), function ( $c ) use ( $f ) { return 'all' === $f || '' === $f || in_array( $f, $c['f'], true ); } ) );
			if ( ! $cands ) {
				delete_option( $lock );
				return new WP_REST_Response( array( 'error' => 'No sweets in this filter right now.' ), 400 );
			}
			$win          = $cands[ wp_rand( 0, count( $cands ) - 1 ) ];
			$list[ $key ] = array( 'lap' => $lap, 'g' => $g, 'pid' => $win['id'], 'filter' => $f ? $f : 'all', 'status' => 'won', 'at' => time() );
			update_user_meta( $uid, 'fika_tastes', $list );
			return array( 'id' => $win['id'], 'name' => $win['name'], 'img' => $win['img'] );
		},
	) );
} );

// ---------- the card above the rewards tracker, the wheel, and the bag drawer line ----------
if ( ! function_exists( 'fika_taste_assets' ) ) {
	function fika_taste_assets( $where ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return;
		}
		$waiting = array();
		foreach ( fika_taste_list( $uid ) as $k => $t ) {
			$p = 'won' === $t['status'] ? wc_get_product( (int) $t['pid'] ) : null;
			if ( $p ) {
				$waiting[] = array( 'key' => $k, 'name' => html_entity_decode( $p->get_name() ), 'img' => $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '' );
			}
		}
		$cand = fika_taste_candies();
		$have = array();
		foreach ( $cand as $c ) {
			foreach ( $c['f'] as $f ) {
				$have[ $f ] = 1;
			}
		}
		$filters = array( array( 'all', 'All sweets' ) );
		foreach ( array( 'gelatin-free' => 'Gelatin-free', 'gluten-free' => 'Gluten-free', 'vegan' => 'Vegan' ) as $slug => $label ) {
			if ( ! empty( $have[ $slug ] ) ) {
				$filters[] = array( $slug, $label );
			}
		}
		$data = array(
			'where'   => $where,
			'waiting' => $waiting,
			'cand'    => $cand,
			'filters' => $filters,
			'spin'    => rest_url( 'fika/v1/taste-spin' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'bag'     => home_url( '/mix-your-own/?bag=open' ),
		);
		?>
<style>
.fk-taste { --tb: #004aad; position: relative; box-sizing: border-box; display: flex; align-items: center; gap: 18px; padding: 18px 24px; border-radius: 22px; background: linear-gradient(120deg, #fff6d6, #ffe3ef); color: #1b2a4a; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; overflow: hidden; }
.fk-taste-home { max-width: 1100px; width: calc(100% - 12%); margin: 34px auto -14px; }
.fk-taste-account { margin: 0 0 16px; }
.fk-taste .ft-ico { flex: none; width: 58px; height: 58px; }
.fk-taste .ft-ico svg { width: 100%; height: 100%; animation: ftWob 2.4s ease-in-out infinite; }
.fk-taste .ft-txt { flex: 1; min-width: 0; }
.fk-taste .ft-txt b { display: block; font: 400 26px/1.05 'Bebas Neue', Impact, sans-serif; letter-spacing: .02em; color: var(--tb); }
.fk-taste .ft-txt span { font-size: 15px; line-height: 1.4; }
.fk-taste .ft-go { flex: none; height: 46px; padding: 0 24px; border: 0; border-radius: 999px; background: var(--tb); color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fk-taste .ft-go:hover { background: #003a8a; }
.fk-taste .ft-won { flex: none; width: 58px; height: 58px; border-radius: 16px; background: #fff center / cover no-repeat; box-shadow: 0 4px 14px rgba(0, 74, 173, .15); }
@keyframes ftWob { 0%, 100% { transform: rotate(-6deg); } 50% { transform: rotate(6deg) scale(1.05); } }
/* the wheel */
.fk-wheel-veil { position: fixed; inset: 0; z-index: 100001; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(27, 42, 74, .45); -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px); }
.fk-wheel-card { position: relative; box-sizing: border-box; width: 100%; max-width: 440px; max-height: calc(100vh - 32px); overflow: auto; padding: 26px 24px 22px; border-radius: 26px; background: #fff; text-align: center; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; box-shadow: 0 20px 60px rgba(0, 0, 0, .25); }
.fk-wheel-card h2 { margin: 0 0 4px; font: 400 32px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .02em; color: #004aad; }
.fk-wheel-card p { margin: 0 0 12px; font-size: 15px; }
.fk-wheel-x { position: absolute; top: 10px; right: 12px; width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f1f4fa; color: #1b2a4a; font-size: 22px; line-height: 1; cursor: pointer; }
.fk-wheel-chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin: 0 0 14px; }
.fk-wheel-chips button { height: 34px; padding: 0 14px; border-radius: 999px; border: 2px solid #004aad; background: #fff; color: #004aad; font: 400 17px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .04em; cursor: pointer; }
.fk-wheel-chips button.on { background: #004aad; color: #fff; }
.fk-wheel-chips button:disabled { opacity: .5; cursor: default; }
.fk-wheel-box { position: relative; width: min(320px, 78vw); height: min(320px, 78vw); margin: 0 auto; }
.fk-wheel-box svg.w { width: 100%; height: 100%; display: block; filter: drop-shadow(0 8px 18px rgba(0, 74, 173, .18)); }
.fk-wheel-box .rot { transform-origin: 160px 160px; transform-box: view-box; }
.fk-wheel-box .ptr { position: absolute; top: -6px; left: 50%; width: 34px; height: 40px; margin-left: -17px; z-index: 2; filter: drop-shadow(0 2px 3px rgba(0, 0, 0, .25)); }
.fk-wheel-spin { position: absolute; top: 50%; left: 50%; width: 92px; height: 92px; margin: -46px 0 0 -46px; z-index: 2; border: 4px solid #fff; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #ff9cc4, #ff4f91); color: #fff; font: 400 24px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .05em; cursor: pointer; box-shadow: 0 6px 18px rgba(255, 79, 145, .45); transition: transform .15s; }
.fk-wheel-spin:hover { transform: scale(1.05); }
.fk-wheel-spin:disabled { cursor: default; transform: none; }
.fk-wheel-spin svg { display: block; width: 34px; height: 34px; margin: 0 auto 2px; }
.fk-wheel-now { min-height: 24px; margin: 12px 0 0; font-weight: 600; font-size: 16px; color: #004aad; }
.fk-wheel-res { display: none; margin-top: 10px; }
.fk-wheel-res img { display: block; width: 120px; height: 120px; margin: 0 auto 10px; border-radius: 20px; object-fit: cover; box-shadow: 0 6px 18px rgba(0, 74, 173, .15); }
.fk-wheel-res .ft-go { display: inline-block; height: 46px; padding: 0 26px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px/46px 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; text-decoration: none; cursor: pointer; }
.fk-wheel-card.is-done .fk-wheel-res { display: block; animation: ftPop .5s cubic-bezier(.2, 1.4, .4, 1); }
.fk-wheel-card.is-done .fk-wheel-box, .fk-wheel-card.is-done .fk-wheel-chips, .fk-wheel-card.is-done .fk-wheel-now, .fk-wheel-card.is-done .ft-intro { display: none; }
.fk-wheel-conf { position: absolute; z-index: 3; width: 9px; height: 9px; border-radius: 3px; pointer-events: none; animation: ftConf 1.4s cubic-bezier(.2, .7, .4, 1) forwards; }
@keyframes ftPop { from { opacity: 0; transform: scale(.9); } to { opacity: 1; transform: none; } }
@keyframes ftConf { 0% { opacity: 1; transform: translate(0, 0) rotate(0); } 100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--r)); } }
.mx-df .fk-taste-note { margin: 0 0 10px; padding: 9px 12px; border-radius: 12px; background: #ffe3ef; color: #1b2a4a; font: 500 13.5px/1.35 'Outfit', 'Open Sans', Arial, sans-serif; }
.mx-df .fk-taste-note b { color: #004aad; }
@media (max-width: 700px) {
  .fk-taste { flex-wrap: wrap; gap: 12px; padding: 16px; }
  .fk-taste-home { width: calc(100% - 32px); margin-top: 24px; }
  .fk-taste .ft-ico, .fk-taste .ft-won { width: 46px; height: 46px; }
  .fk-taste .ft-txt b { font-size: 22px; }
  .fk-taste .ft-go { width: 100%; }
}
@media (prefers-reduced-motion: reduce) { .fk-taste *, .fk-wheel-card * { animation: none !important; } }
</style>
<script>
(function () {
	var D = <?php echo wp_json_encode( $data ); ?>;
	var GIFT = '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#fff"/><path d="M20 26c0-7 9-11 12-3 3-8 12-4 12 3" fill="none" stroke="#ff4f91" stroke-width="4" stroke-linecap="round"/><rect x="14" y="26" width="36" height="24" rx="5" fill="#ff6fa5" stroke="#004aad" stroke-width="3"/><path d="M32 26v24M14 36h36" stroke="#ffd23f" stroke-width="4"/><text x="32" y="47" text-anchor="middle" font-family="Bebas Neue, Impact, sans-serif" font-size="11" fill="#004aad">?</text></svg>';
	function esc(s) { var d = document.createElement('span'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
	// a card for each taste waiting for its order
	function cards() {
		return D.waiting.map(function (w) {
			var c = document.createElement('section');
			c.className = 'fk-taste fk-taste-' + D.where;
			c.innerHTML = '<div class="ft-won"></div><div class="ft-txt"><b></b><span>50 g, free. It joins the order that reaches its stop on the lane (or your next one) by itself at checkout.</span></div>';
			c.querySelector('b').textContent = 'Your mystery taste: ' + w.name;
			if (w.img) c.querySelector('.ft-won').style.backgroundImage = 'url("' + w.img.replace(/"/g, '') + '")';
			return c;
		});
	}
	function mount() {
		if (document.querySelector('.fk-taste')) return;
		var spot = D.where === 'account' ? (document.querySelector('.fika-swim-account') || document.querySelector('.woocommerce-MyAccount-content > *')) : (document.querySelector('.fika-swim-home') || document.getElementById('fsBar') || document.getElementById('shop'));
		if (spot) cards().forEach(function (c) { spot.parentNode.insertBefore(c, spot); });
		// the bag drawer: the taste is coming
		var go = document.getElementById('mxGo');
		if (go ? D.waiting.length > 0 : false) {
			var n = document.createElement('p'); n.className = 'fk-taste-note';
			n.innerHTML = 'Your mystery taste <b></b> (50 g, free) joins your order at checkout.';
			n.querySelector('b').textContent = D.waiting.map(function (w) { return w.name; }).join(' + ');
			go.parentNode.insertBefore(n, go);
		}
	}
	// ---- the wheel ----
	// opened from a lit spin stop on the lane, or by itself when the customer reaches the stop:
	// o = { lap, g, bag (grams in the bag), done(result), closed(won) }
	function wheel(o) {
		o = o || {};
		var COLS = ['#ff6fa5', '#ffd23f', '#4aa8ff', '#7ad67a', '#c77dff', '#ff9f43'];
		var filter = 'all', list = [], spinning = false, angle = 0;
		var veil = document.createElement('div');
		veil.className = 'fk-wheel-veil';
		veil.setAttribute('role', 'dialog'); veil.setAttribute('aria-modal', 'true'); veil.setAttribute('aria-label', 'Spin for your mystery taste');
		veil.innerHTML = '<div class="fk-wheel-card"><button class="fk-wheel-x" type="button" aria-label="Close">×</button>' +
			'<div class="ft-intro"><h2>Spin for your mystery taste</h2><p>Pick the kind of sweets, then tap the candy.</p></div>' +
			'<div class="fk-wheel-chips"></div>' +
			'<div class="fk-wheel-box"><svg class="ptr" viewBox="0 0 34 40" aria-hidden="true"><path d="M17 38 L3 6 Q17 -2 31 6 Z" fill="#004aad" stroke="#fff" stroke-width="3" stroke-linejoin="round"/></svg>' +
			'<svg class="w" viewBox="0 0 320 320" aria-hidden="true"><g class="rot"></g></svg>' +
			'<button class="fk-wheel-spin" type="button" aria-label="Spin"><svg viewBox="0 0 40 40" aria-hidden="true"><path d="M6 20l-5-6v12zM34 20l5-6v12z" fill="#fff"/><ellipse cx="20" cy="20" rx="14" ry="10" fill="#fff"/><path d="M12 15l5 10M19 13l5 14M26 14l3 8" stroke="#ff4f91" stroke-width="2.4" stroke-linecap="round"/></svg>SPIN</button></div>' +
			'<p class="fk-wheel-now" aria-live="polite"></p>' +
			'<div class="fk-wheel-res"><img alt=""><h2></h2><p>50 g of it is yours, free. It joins your order by itself at checkout.</p><button type="button" class="ft-go">Back to my sweets</button></div></div>';
		document.body.appendChild(veil);
		var cardEl = veil.querySelector('.fk-wheel-card'), rot = veil.querySelector('.rot'), chips = veil.querySelector('.fk-wheel-chips'), now = veil.querySelector('.fk-wheel-now'), spin = veil.querySelector('.fk-wheel-spin');
		function close() { if (spinning) return; veil.remove(); if (o.closed) o.closed(cardEl.classList.contains('is-done')); }
		veil.querySelector('.fk-wheel-x').addEventListener('click', close);
		veil.querySelector('.fk-wheel-res .ft-go').addEventListener('click', close);
		veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
		D.filters.forEach(function (f) {
			var b = document.createElement('button'); b.type = 'button'; b.textContent = f[1]; b.setAttribute('data-f', f[0]);
			if (f[0] === filter) b.className = 'on';
			chips.appendChild(b);
		});
		chips.addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b || spinning) return;
			filter = b.getAttribute('data-f');
			Array.prototype.forEach.call(chips.children, function (x) { x.classList.toggle('on', x === b); });
			draw();
		});
		// one slice per candy, with its photo
		function draw() {
			list = D.cand.filter(function (c) { return filter === 'all' || c.f.indexOf(filter) !== -1; });
			var n = list.length, R = 150, html = '<circle cx="160" cy="160" r="156" fill="#004aad"/>';
			list.forEach(function (c, i) {
				var a0 = (i / n) * 2 * Math.PI - Math.PI / 2, a1 = ((i + 1) / n) * 2 * Math.PI - Math.PI / 2;
				var x0 = 160 + R * Math.cos(a0), y0 = 160 + R * Math.sin(a0), x1 = 160 + R * Math.cos(a1), y1 = 160 + R * Math.sin(a1);
				html += '<path d="M160 160 L' + x0.toFixed(2) + ' ' + y0.toFixed(2) + ' A' + R + ' ' + R + ' 0 ' + (n === 1 ? 1 : 0) + ' 1 ' + x1.toFixed(2) + ' ' + y1.toFixed(2) + ' Z" fill="' + COLS[i % COLS.length] + '" stroke="#fff" stroke-width="1.5"/>';
				var am = (a0 + a1) / 2, r2 = 118, sz = Math.max(14, Math.min(34, 2 * Math.PI * r2 / n - 6));
				var cx = 160 + r2 * Math.cos(am), cy = 160 + r2 * Math.sin(am);
				if (c.img) html += '<clipPath id="ftc' + i + '"><circle cx="' + cx.toFixed(1) + '" cy="' + cy.toFixed(1) + '" r="' + (sz / 2).toFixed(1) + '"/></clipPath><circle cx="' + cx.toFixed(1) + '" cy="' + cy.toFixed(1) + '" r="' + (sz / 2 + 1.5).toFixed(1) + '" fill="#fff"/><image href="' + esc(c.img) + '" x="' + (cx - sz / 2).toFixed(1) + '" y="' + (cy - sz / 2).toFixed(1) + '" width="' + sz.toFixed(1) + '" height="' + sz.toFixed(1) + '" clip-path="url(#ftc' + i + ')" preserveAspectRatio="xMidYMid slice"/>';
			});
			rot.innerHTML = html;
			now.textContent = n + (n === 1 ? ' sweet' : ' sweets') + ' on the wheel';
		}
		// the candy under the pointer (for the live name while spinning)
		function under(deg) { var n = list.length; if (!n) return null; var a = ((360 - (deg % 360)) % 360 + 360) % 360; return list[Math.floor(a / (360 / n)) % n]; }
		function live() {
			if (!spinning) return;
			var m = getComputedStyle(rot).transform, deg = angle;
			if (m && m !== 'none') { var v = m.split('(')[1].split(')')[0].split(','); deg = Math.atan2(+v[1], +v[0]) * 180 / Math.PI; }
			var c = under(deg); if (c) now.textContent = c.name;
			requestAnimationFrame(live);
		}
		function confetti() {
			var cols = ['#ff6fa5', '#ffd23f', '#4aa8ff', '#ff4d4d', '#7ad67a', '#c77dff'];
			for (var i = 0; i < 30; i++) {
				var c = document.createElement('i'); c.className = 'fk-wheel-conf';
				var a = Math.random() * Math.PI * 2, d = 60 + Math.random() * 120;
				c.style.left = '50%'; c.style.top = '30%'; c.style.background = cols[i % cols.length];
				c.style.setProperty('--dx', (Math.cos(a) * d) + 'px'); c.style.setProperty('--dy', (Math.sin(a) * d - 40) + 'px'); c.style.setProperty('--r', (Math.random() * 540 - 270) + 'deg');
				cardEl.appendChild(c); (function (x) { setTimeout(function () { x.remove(); }, 1500); })(c);
			}
		}
		spin.addEventListener('click', function () {
			if (spinning || !list.length) return;
			spinning = true; spin.disabled = true;
			Array.prototype.forEach.call(chips.children, function (x) { x.disabled = true; });
			// a slow wind-up while the server picks the candy
			rot.style.transition = 'transform 1.2s cubic-bezier(.5, 0, 1, .6)'; angle += 540; rot.style.transform = 'rotate(' + angle + 'deg)'; live();
			fetch(D.spin, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce }, body: JSON.stringify({ filter: filter, lap: o.lap, g: o.g, bag: o.bag || 0 }) })
				.then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.id) throw new Error(j.error || 'Sorry, the wheel got stuck. Please try again.'); return j; }); })
				.then(function (j) {
					var i = -1; list.forEach(function (c, k) { if (c.id === j.id) i = k; });
					var n = list.length, slice = 360 / n, target = i >= 0 ? (360 - (i + 0.5) * slice + (Math.random() - 0.5) * slice * 0.6) : 0;
					var base = Math.ceil(angle / 360) * 360 + 360 * 5;
					angle = base + target;
					rot.style.transition = 'transform 4.6s cubic-bezier(.12, .7, .12, 1)'; rot.style.transform = 'rotate(' + angle + 'deg)';
					setTimeout(function () {
						spinning = false; now.textContent = j.name;
						var res = veil.querySelector('.fk-wheel-res');
						res.querySelector('h2').textContent = j.name + '!';
						if (j.img) res.querySelector('img').src = j.img; else res.querySelector('img').remove();
						setTimeout(function () { cardEl.classList.add('is-done'); confetti(); if (o.done) o.done(j); }, 700);
					}, 4700);
				})
				.catch(function (ex) { spinning = false; spin.disabled = false; Array.prototype.forEach.call(chips.children, function (x) { x.disabled = false; }); now.textContent = ex.message; });
		});
		draw();
	}
	window.fikaTasteWheel = wheel;
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(mount, 0); }); else setTimeout(mount, 0);
})();
</script>
		<?php
	}
}
add_action( 'wp_footer', function () {
	if ( ! is_user_logged_in() || ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404() ) ) {
		return;
	}
	fika_taste_assets( 'home' );
}, 26 );
add_action( 'woocommerce_account_dashboard', function () {
	$GLOBALS['fika_taste_account'] = true;
}, 3 );
add_action( 'wp_footer', function () {
	if ( ! empty( $GLOBALS['fika_taste_account'] ) ) {
		fika_taste_assets( 'account' );
	}
}, 26 );
