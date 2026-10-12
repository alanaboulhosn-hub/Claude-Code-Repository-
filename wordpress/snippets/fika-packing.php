<?php
/**
 * Fika: "Packaging" column in WooCommerce > Orders, for the owner only: has this bag been packed?
 * - Each order shows Unfulfilled or Fulfilled; one click on it switches it (no page reload, the order does not open).
 * - Only a private note to self (order meta _fika_packed = yes / no). It changes nothing else: not the order status,
 *   no emails, no order note, not the rewards, not RT.
 * - Orders never marked: Fulfilled once Completed (a delivered bag was packed), otherwise Unfulfilled.
 * Saved with POST /wp-json/fika/v1/packed { id, packed } (shop managers only).
 * "Delivery" column: On time or Late, from the moment the order was marked Fulfilled (Beirut time, saved as
 * _fika_packed_at) to the moment it turned Completed (delivered):
 * - Inside Beirut: packed before 5 pm -> due by the end of the next day; at 5 pm or later -> by the end of the day after.
 * - Outside Beirut: one more day in both cases.
 * - Only delivery days count: Sundays are skipped (RT delivers Monday to Saturday).
 * Not completed yet: "On time, due ..." until the end of the due day, then "Late". Orders packed before this column
 * existed have no packing time (shown as a dash), and so do failed, cancelled, refunded and undelivered orders.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-packing.php
 */

if ( ! function_exists( 'fika_packed' ) ) {
	function fika_packed( $order ) {
		$v = (string) $order->get_meta( '_fika_packed' );
		if ( '' === $v ) {
			return $order->has_status( 'completed' );
		}
		return 'yes' === $v;
	}
	// end of the due day (timestamp) for an order packed at $at
	function fika_delivery_due( $order, $at ) {
		$area = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
		$p    = ( new DateTime( '@' . (int) $at ) )->setTimezone( wp_timezone() );
		$days = ( (int) $p->format( 'G' ) < 17 ? 1 : 2 ) + ( 'BA' === $area ? 0 : 1 );
		$p->setTime( 23, 59, 59 );
		// delivery days only: Sundays are skipped (RT delivers Monday to Saturday)
		while ( $days > 0 ) {
			$p->modify( '+1 day' );
			if ( '7' !== $p->format( 'N' ) ) {
				$days--;
			}
		}
		return $p->getTimestamp();
	}
	function fika_delivery_cell( $order ) {
		$at = (int) $order->get_meta( '_fika_packed_at' );
		if ( ! $at || ! fika_packed( $order ) || $order->has_status( array( 'failed', 'cancelled', 'refunded', 'undelivered' ) ) ) {
			$why = ! fika_packed( $order ) ? 'Not packed yet' : ( ! $at ? 'Packed before packing times were saved' : 'Not delivered (order ' . wc_get_order_status_name( $order->get_status() ) . ')' );
			return '<span class="fika-dl fika-dl-none" title="' . esc_attr( $why ) . '">&ndash;</span>';
		}
		$due  = fika_delivery_due( $order, $at );
		$area = 'BA' === ( $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state() ) ? 'Inside Beirut' : 'Outside Beirut';
		$tip  = 'Packed ' . wp_date( 'D j M, H:i', $at ) . ' (' . $area . '), due by end of ' . wp_date( 'D j M', $due );
		if ( $order->has_status( 'completed' ) ) {
			$done = $order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : time();
			$late = $done > $due;
			return '<span class="fika-dl ' . ( $late ? 'fika-dl-late' : 'fika-dl-ok' ) . '" title="' . esc_attr( $tip . ', delivered ' . wp_date( 'D j M, H:i', $done ) ) . '">' . ( $late ? 'Late' : '&#10003; On time' ) . '</span><br><small>delivered ' . esc_html( wp_date( 'D j M', $done ) ) . '</small>';
		}
		if ( time() > $due ) {
			return '<span class="fika-dl fika-dl-late" title="' . esc_attr( $tip ) . '">Late</span><br><small>was due ' . esc_html( wp_date( 'D j M', $due ) ) . '</small>';
		}
		return '<span class="fika-dl fika-dl-due" title="' . esc_attr( $tip ) . '">On time</span><br><small>due ' . esc_html( wp_date( 'D j M', $due ) ) . '</small>';
	}
	function fika_packed_cell( $order ) {
		$on = fika_packed( $order );
		printf(
			'<button type="button" class="fika-pack no-link%s" data-id="%d" aria-pressed="%s" title="Click to switch">%s</button>',
			$on ? ' is-on' : '',
			(int) $order->get_id(),
			$on ? 'true' : 'false',
			$on ? '&#10003; Fulfilled' : 'Unfulfilled'
		);
	}
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/packed', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'edit_shop_orders' ); },
		'callback'            => function ( $req ) {
			$o = wc_get_order( (int) $req->get_param( 'id' ) );
			if ( ! $o ) {
				return new WP_REST_Response( array( 'error' => 'No such order.' ), 404 );
			}
			$on = rest_sanitize_boolean( $req->get_param( 'packed' ) );
			$o->update_meta_data( '_fika_packed', $on ? 'yes' : 'no' );
			if ( $on && ! (int) $o->get_meta( '_fika_packed_at' ) ) {
				$o->update_meta_data( '_fika_packed_at', time() ); // when it was packed: starts the delivery clock
			} elseif ( ! $on ) {
				$o->delete_meta_data( '_fika_packed_at' );
			}
			$o->save_meta_data(); // only this value: no status change, no emails, no order hooks
			return array( 'id' => $o->get_id(), 'packed' => $on, 'delivery' => fika_delivery_cell( $o ) );
		},
	) );
} );

// ---------- Orders list: the column, after Status (and RT) ----------
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $fika_hook ) {
	add_filter( $fika_hook, function ( $cols ) {
		$out   = array();
		$after = isset( $cols['fika_rtd'] ) ? 'fika_rtd' : 'order_status';
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( $after === $k ) {
				$out['fika_packed']   = 'Packaging';
				$out['fika_delivery'] = 'Delivery';
			}
		}
		if ( ! isset( $out['fika_packed'] ) ) {
			$out['fika_packed']   = 'Packaging';
			$out['fika_delivery'] = 'Delivery';
		}
		return $out;
	}, 40 );
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $col, $order ) {
	if ( 'fika_packed' === $col && $order instanceof WC_Order ) {
		fika_packed_cell( $order );
	}
	if ( 'fika_delivery' === $col && $order instanceof WC_Order ) {
		echo fika_delivery_cell( $order ); // phpcs:ignore -- escaped inside
	}
}, 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'fika_packed' === $col && ( $o = wc_get_order( $post_id ) ) ) {
		fika_packed_cell( $o );
	}
	if ( 'fika_delivery' === $col && ( $o = wc_get_order( $post_id ) ) ) {
		echo fika_delivery_cell( $o ); // phpcs:ignore -- escaped inside
	}
}, 10, 2 );

add_action( 'admin_footer', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
		return;
	}
	$cfg = array( 'api' => rest_url( 'fika/v1/packed' ), 'nonce' => wp_create_nonce( 'wp_rest' ) );
	?>
	<style>
		.column-fika_packed { width: 120px; }
		.fika-pack { display: inline-block; padding: 5px 11px; border: 1px solid #f0c36d; border-radius: 999px; background: #fff6e0; color: #8a5a00; font-size: 12.5px; font-weight: 600; line-height: 1.3; cursor: pointer; white-space: nowrap; }
		.fika-pack.is-on { border-color: #b8e0c2; background: #e7f6ea; color: #1e6b35; }
		.fika-pack:hover { filter: brightness(.96); }
		.fika-pack[aria-busy="true"] { opacity: .55; cursor: progress; }
		.column-fika_delivery { width: 110px; }
		.fika-dl { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; line-height: 1.3; white-space: nowrap; }
		.fika-dl-ok { background: #e7f6ea; color: #1e6b35; }
		.fika-dl-due { background: #e5f0fb; color: #1d5a99; }
		.fika-dl-late { background: #fbe7e7; color: #a11c1c; }
		.fika-dl-none { padding: 0; color: #a7aaad; font-weight: 400; }
		td.column-fika_delivery small { color: #646970; }
	</style>
	<script>
	(function () {
		var C = <?php echo wp_json_encode( $cfg ); ?>;
		document.addEventListener('click', function (e) {
			var b = e.target.closest ? e.target.closest('.fika-pack') : null;
			if (!b) return;
			e.preventDefault(); e.stopPropagation(); // the row stays put (a click on a row opens the order)
			if (b.getAttribute('aria-busy') === 'true') return;
			var on = !b.classList.contains('is-on');
			b.setAttribute('aria-busy', 'true');
			fetch(C.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify({ id: +b.getAttribute('data-id'), packed: on }) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					b.removeAttribute('aria-busy');
					if (!res.ok) { alert((res.j && (res.j.error || res.j.message)) || 'Could not save. Please try again.'); return; }
					b.classList.toggle('is-on', res.j.packed);
					b.setAttribute('aria-pressed', res.j.packed ? 'true' : 'false');
					b.innerHTML = res.j.packed ? '&#10003; Fulfilled' : 'Unfulfilled';
					var tr = b.closest('tr'), dl = tr ? tr.querySelector('td.column-fika_delivery') : null;
					if (dl && res.j.delivery) dl.innerHTML = res.j.delivery; // the delivery clock starts (or stops) at once
				})
				.catch(function () { b.removeAttribute('aria-busy'); alert('Could not save. Please try again.'); });
		}, true);
	})();
	</script>
	<?php
} );
