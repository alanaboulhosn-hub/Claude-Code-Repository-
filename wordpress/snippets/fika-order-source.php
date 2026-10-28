<?php
/**
 * Fika: where each order came from, in WooCommerce > Orders (owner, 28 Oct 2026: "see what converts from the ad").
 * - Reads WooCommerce's own order attribution (it records the link's utm codes on every order). Meta adds
 *   utm_medium=paid and the campaign / ad set / ad numbers to every ad click, so ad orders are told apart from the
 *   Instagram bio link (utm_medium=social, link_in_bio).
 * - No column of its own (owner: WooCommerce's Origin column already shows the source): orders from a Meta ad get a
 *   "Meta ad · campaign" tag under their Origin (which says "Ig" for the ad and the bio link alike). Campaign
 *   numbers get a name in FIKA_SRC_CAMPAIGNS (else the number is shown).
 * - Above the list: a box with the Meta ad results: orders and sales (cancelled / failed / refunded left out), today
 *   and in total, per campaign.
 * - Admin only, orders only read.
 * Installed with the Code Snippets plugin (admin only). Source: wordpress/snippets/fika-order-source.php
 */
if ( ! defined( 'FIKA_SRC_CAMPAIGNS' ) ) {
	define( 'FIKA_SRC_CAMPAIGNS', wp_json_encode( array( '120250075140790390' => 'Halloween', '120249990307440473' => 'Launch (10–14 Oct)' ) ) );
}
if ( ! function_exists( 'fika_src_of' ) ) {
	// what the order's attribution says: array( key, label )
	function fika_src_of( $order ) {
		$g      = function ( $k ) use ( $order ) { return strtolower( trim( (string) $order->get_meta( '_wc_order_attribution_' . $k ) ) ); };
		$src    = $g( 'utm_source' );
		$med    = $g( 'utm_medium' );
		$type   = $g( 'source_type' );
		$ref    = $g( 'referrer' );
		$meta   = in_array( $src, array( 'ig', 'instagram', 'fb', 'facebook', 'an', 'msg', 'meta' ), true ) || false !== strpos( $ref, 'instagram.' ) || false !== strpos( $ref, 'facebook.' );
		if ( $meta && in_array( $med, array( 'paid', 'cpc', 'ppc', 'ad', 'ads', 'paid_social' ), true ) ) {
			$names = json_decode( FIKA_SRC_CAMPAIGNS, true );
			$c     = (string) $order->get_meta( '_wc_order_attribution_utm_campaign' );
			return array( 'ad', 'Meta ad' . ( '' !== $c ? ' · ' . ( isset( $names[ $c ] ) ? $names[ $c ] : $c ) : '' ), $c );
		}
		if ( 'ig' === $src || 'instagram' === $src || false !== strpos( $ref, 'instagram.' ) ) {
			return array( 'ig', 'Instagram', '' );
		}
		if ( in_array( $src, array( 'fb', 'facebook', 'm.facebook.com', 'l.facebook.com' ), true ) || false !== strpos( $ref, 'facebook.' ) ) {
			return array( 'fb', 'Facebook', '' );
		}
		if ( false !== strpos( $src, 'google' ) ) {
			return array( 'google', 'Google', '' );
		}
		if ( 'typein' === $type || '(direct)' === $src ) {
			return array( 'direct', 'Direct', '' );
		}
		if ( '' === $type && '' === $src ) {
			return array( 'none', '', '' );
		}
		return array( 'other', $src ? ucfirst( $src ) : ucfirst( $type ), '' );
	}
	function fika_src_cell( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		list( $k, $label ) = fika_src_of( $order );
		echo '' === $label ? '<span style="color:#a7aaad">–</span>' : '<span class="fika-src fika-src-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</span>';
	}
}

// no column of its own: WooCommerce's "Origin" column already names the source; it shows "Ig" for the ad and the
// bio link alike, so orders from a Meta ad get a small tag under it (priority 20: after WooCommerce's own text)
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $col, $order ) {
	if ( 'origin' === $col && $order instanceof WC_Order ) {
		list( $k, $label ) = fika_src_of( $order );
		if ( 'ad' === $k ) {
			echo '<br><span class="fika-src fika-src-ad">' . esc_html( $label ) . '</span>';
		}
	}
}, 20, 2 );
add_action( 'manage_shop_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'origin' === $col && ( $o = wc_get_order( $post_id ) ) ) {
		list( $k, $label ) = fika_src_of( $o );
		if ( 'ad' === $k ) {
			echo '<br><span class="fika-src fika-src-ad">' . esc_html( $label ) . '</span>';
		}
	}
}, 20, 2 );

// the Meta ad results, above the orders list
add_action( 'admin_notices', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$data = get_transient( 'fika_src_ads2' );
	if ( false === $data ) {
		$ids  = wc_get_orders( array(
			'limit'      => 500,
			'return'     => 'ids',
			'status'     => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
			'meta_query' => array( array( 'key' => '_wc_order_attribution_utm_medium', 'value' => 'paid' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$today = wp_date( 'Y-m-d' );
		$data  = array( 'n' => 0, 'sum' => 0.0, 'tn' => 0, 'tsum' => 0.0, 'camp' => array() );
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( ! $o ) {
				continue;
			}
			list( $k, $label, $c ) = fika_src_of( $o );
			if ( 'ad' !== $k ) {
				continue;
			}
			$t = (float) $o->get_total();
			$data['n']++;
			$data['sum'] += $t;
			if ( $o->get_date_created() && $o->get_date_created()->date_i18n( 'Y-m-d' ) === $today ) {
				$data['tn']++;
				$data['tsum'] += $t;
			}
			$name = substr( $label, strlen( 'Meta ad · ' ) );
			if ( ! isset( $data['camp'][ $name ] ) ) {
				$data['camp'][ $name ] = array( 0, 0.0 );
			}
			$data['camp'][ $name ][0]++;
			$data['camp'][ $name ][1] += $t;
		}
		set_transient( 'fika_src_ads2', $data, 5 * MINUTE_IN_SECONDS );
	}
	$m = function ( $v ) { return wp_strip_all_tags( wc_price( $v ) ); };
	echo '<div class="fika-src-box"><b>Meta ads</b>';
	if ( ! $data['n'] ) {
		echo '<span>No orders from Meta ads yet.</span>';
	} else {
		echo '<span>Today: <b>' . (int) $data['tn'] . '</b> ' . ( 1 === $data['tn'] ? 'order' : 'orders' ) . ', <b>' . esc_html( $m( $data['tsum'] ) ) . '</b></span>';
		echo '<span>In total: <b>' . (int) $data['n'] . '</b> ' . ( 1 === $data['n'] ? 'order' : 'orders' ) . ', <b>' . esc_html( $m( $data['sum'] ) ) . '</b></span>';
		if ( count( $data['camp'] ) > 1 ) {
			foreach ( $data['camp'] as $name => $v ) {
				echo '<span>' . esc_html( $name ) . ': ' . (int) $v[0] . ', ' . esc_html( $m( $v[1] ) ) . '</span>';
			}
		}
	}
	echo '<small>Cancelled, failed and refunded orders not counted. Updates every 5 minutes.</small></div>';
} );

add_action( 'admin_footer', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
		return;
	}
	?>
	<style>
		.column-origin .fika-src { margin-top: 4px; }
		.fika-src { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 1.5; white-space: nowrap; background: #f0f0f1; color: #50575e; }
		.fika-src-ad { background: #ffe6d1; color: #a34400; border: 1px solid #ffb27a; }
		.fika-src-ig { background: #fbe3f0; color: #9b1f62; }
		.fika-src-fb { background: #e3ecfb; color: #1d4f9b; }
		.fika-src-google { background: #e6f4ea; color: #1e6b37; }
		.fika-src-box { display: flex; flex-wrap: wrap; gap: 6px 22px; align-items: baseline; margin: 14px 0 6px; padding: 12px 16px; background: #fff8f2; border: 1px solid #ffcfa6; border-left: 4px solid #ff7b1c; border-radius: 6px; font-size: 14px; }
		.fika-src-box > b { color: #a34400; }
		.fika-src-box small { flex-basis: 100%; color: #8a6d57; }
	</style>
	<?php
} );
