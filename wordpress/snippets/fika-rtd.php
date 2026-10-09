<?php
/**
 * Fika: RT Deliveries parcel tracking (WooCommerce > RT Deliveries).
 * Parcels are still created by hand in the RT dashboard. RT sends every status change to our webhook
 * (POST /wp-json/fika/v1/rtd-webhook, header RTD-Signature = the secret shown on the settings page), and the order
 * follows it:
 * - Delivered                                  -> order Completed (counts as delivered on Fika customers + rewards)
 * - Return_to_warehouse / Return_assign_to_merchant -> order Undelivered
 * - anything else (picked up, out for delivery, attempt failed, ...) -> order unchanged; the RT status is shown
 * Each update adds a private order note. No customer emails are sent by this snippet.
 * A parcel is linked to its order by (1) its tracking ID saved on the order (box on the order page), or (2) the Fika
 * order number typed in RT's "Invoice no" field. Parcels that match nothing wait under "Parcels to link" on the
 * settings page, with suggested orders of the same amount; one click links them and applies their status.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-rtd.php
 */

if ( ! function_exists( 'fika_rtd_statuses' ) ) {
	// RT status slug => array( label, order status to set or '' )
	function fika_rtd_statuses() {
		return array(
			'Pending'                   => array( 'Parcel created', '' ),
			'Pickup_Assign'             => array( 'Pickup assigned', '' ),
			'Pickup_Failed'             => array( 'Pickup failed', '' ),
			'Collected_By_Driver'       => array( 'Picked up by driver', '' ),
			'Collected_By_Pickup'       => array( 'Picked up', '' ),
			'Received_Warehouse'        => array( 'At RT warehouse', '' ),
			'Transfer_to_hub'           => array( 'On the way to hub', '' ),
			'Received_by_hub'           => array( 'At RT hub', '' ),
			'Delivery_Man_Assign'       => array( 'Out for delivery', '' ),
			'Delivery_Re_Schedule'      => array( 'Delivery rescheduled', '' ),
			'Delivered_failed'          => array( 'Delivery attempt failed', '' ),
			'Delivered'                 => array( 'Delivered', 'completed' ),
			'Return_to_warehouse'       => array( 'Returning to RT', 'undelivered' ),
			'Return_assign_to_merchant' => array( 'Returned to us', 'undelivered' ),
		);
	}
	function fika_rtd_label( $slug ) {
		$s = fika_rtd_statuses();
		return isset( $s[ $slug ] ) ? $s[ $slug ][0] : str_replace( '_', ' ', (string) $slug );
	}
	function fika_rtd_secret() {
		$s = (string) get_option( 'fika_rtd_secret', '' );
		if ( '' === $s ) {
			$s = wp_generate_password( 32, false, false );
			update_option( 'fika_rtd_secret', $s, false );
		}
		return $s;
	}
	function fika_rtd_find_by_tracking( $tracking ) {
		if ( '' === (string) $tracking ) {
			return null;
		}
		$o = wc_get_orders( array(
			'limit'      => 1,
			'type'       => 'shop_order',
			'status'     => array_keys( wc_get_order_statuses() ),
			'meta_query' => array( array( 'key' => '_fika_rtd_tracking', 'value' => (string) $tracking ) ),
		) );
		return $o ? $o[0] : null;
	}
	// "#1458", "1458", "FIKA-1458" -> the order with that Fika number
	function fika_rtd_find_by_number( $text ) {
		if ( ! preg_match( '/(\d{4,6})/', (string) $text, $m ) ) {
			return null;
		}
		foreach ( array( '_fika_order_number', '_fika_old_order' ) as $k ) {
			$o = wc_get_orders( array(
				'limit'      => 1,
				'type'       => 'shop_order',
				'status'     => array_keys( wc_get_order_statuses() ),
				'meta_query' => array( array( 'key' => $k, 'value' => $m[1] ) ),
			) );
			if ( $o ) {
				return $o[0];
			}
		}
		return null;
	}
	// open orders with no parcel yet (newest first); with $amount, only those whose total matches
	function fika_rtd_open_orders( $amount = null ) {
		$out = array();
		foreach ( wc_get_orders( array(
			'limit'        => 60,
			'type'         => 'shop_order',
			'status'       => array( 'wc-processing', 'wc-on-hold' ),
			'date_created' => '>' . ( time() - 45 * DAY_IN_SECONDS ),
			'orderby'      => 'date',
			'order'        => 'DESC',
		) ) as $o ) {
			if ( '' !== (string) $o->get_meta( '_fika_rtd_tracking' ) ) {
				continue;
			}
			if ( null !== $amount && abs( (float) $o->get_total() - (float) $amount ) > 0.009 ) {
				continue;
			}
			$out[] = $o;
		}
		return $out;
	}
	function fika_rtd_log( $line ) {
		$log = get_option( 'fika_rtd_log', array() );
		array_unshift( $log, array( time(), $line ) );
		update_option( 'fika_rtd_log', array_slice( $log, 0, 80 ), false );
	}
	// apply one RT update (array with tracking_id, parcel_status_slug, description, updated_at) to an order
	function fika_rtd_apply( $order, $ev ) {
		$slug  = (string) $ev['slug'];
		$when  = $ev['time'] ? $ev['time'] : time();
		$last  = (int) $order->get_meta( '_fika_rtd_time' );
		$hist  = $order->get_meta( '_fika_rtd_history' );
		$hist  = is_array( $hist ) ? $hist : array();
		foreach ( $hist as $h ) {
			if ( $h['slug'] === $slug && (int) $h['time'] === (int) $when ) {
				return 'duplicate';
			}
		}
		$hist[] = array( 'slug' => $slug, 'time' => $when, 'note' => (string) $ev['note'] );
		usort( $hist, function ( $a, $b ) { return $a['time'] - $b['time']; } );
		$order->update_meta_data( '_fika_rtd_history', array_slice( $hist, -40 ) );
		if ( '' === (string) $order->get_meta( '_fika_rtd_tracking' ) && '' !== $ev['tracking'] ) {
			$order->update_meta_data( '_fika_rtd_tracking', $ev['tracking'] );
		}
		$note = 'RT Deliveries: ' . fika_rtd_label( $slug ) . ( '' !== $ev['note'] ? ' (' . $ev['note'] . ')' : '' ) . '.';
		if ( $last && $when < $last ) {
			// an older update that arrived late: keep it in the history only
			$order->save();
			$order->add_order_note( $note . ' Older update, status left as it is.' );
			return 'older';
		}
		$order->update_meta_data( '_fika_rtd_status', $slug );
		$order->update_meta_data( '_fika_rtd_time', $when );
		$map = fika_rtd_statuses();
		$to  = isset( $map[ $slug ] ) ? $map[ $slug ][1] : '';
		$cur = $order->get_status();
		$ok  = array(
			'completed'   => array( 'pending', 'processing', 'on-hold', 'undelivered' ),
			'undelivered' => array( 'pending', 'processing', 'on-hold' ),
		);
		if ( $to && $to !== $cur && in_array( $cur, $ok[ $to ], true ) ) {
			$order->save();
			$order->update_status( $to, $note );
			return 'status:' . $to;
		}
		$order->save();
		$order->add_order_note( $note );
		return 'noted';
	}
	// read RT's webhook body into one tidy array
	function fika_rtd_event( $body ) {
		if ( isset( $body['data'] ) && is_array( $body['data'] ) && ! isset( $body['tracking_id'] ) ) {
			$body = $body['data'];
		}
		$g = function ( $k ) use ( $body ) {
			return isset( $body[ $k ] ) && is_scalar( $body[ $k ] ) ? trim( sanitize_text_field( (string) $body[ $k ] ) ) : '';
		};
		$slug = $g( 'parcel_status_slug' );
		if ( '' === $slug ) {
			$slug = str_replace( ' ', '_', $g( 'parcel_status' ) );
		}
		$t = $g( 'updated_at' ) ? strtotime( $g( 'updated_at' ) ) : 0;
		return array(
			'tracking' => $g( 'tracking_id' ),
			'invoice'  => $g( 'invoice_no' ),
			'order_no' => $g( 'order_no' ),
			'cash'     => $g( 'cash_collection' ),
			'slug'     => $slug,
			'note'     => $g( 'description' ),
			'time'     => $t ? $t : time(),
		);
	}
	function fika_rtd_handle( $ev ) {
		$order = fika_rtd_find_by_tracking( $ev['tracking'] );
		if ( ! $order && '' !== $ev['invoice'] ) {
			$order = fika_rtd_find_by_number( $ev['invoice'] );
		}
		$tag = $ev['tracking'] . ' ' . fika_rtd_label( $ev['slug'] );
		if ( ! $order ) {
			$wait = get_option( 'fika_rtd_unmatched', array() );
			$key  = '' !== $ev['tracking'] ? $ev['tracking'] : md5( wp_json_encode( $ev ) );
			$prev = isset( $wait[ $key ]['events'] ) ? $wait[ $key ]['events'] : array();
			$prev[] = $ev;
			$wait[ $key ] = array( 'ev' => $ev, 'events' => array_slice( $prev, -20 ), 'got' => time() );
			update_option( 'fika_rtd_unmatched', array_slice( $wait, -100, null, true ), false );
			fika_rtd_log( $tag . ': no order linked yet (waiting under Parcels to link)' );
			return array( 'ok' => true, 'matched' => false );
		}
		$r = fika_rtd_apply( $order, $ev );
		fika_rtd_log( $tag . ': order #' . $order->get_order_number() . ' (' . $r . ')' );
		return array( 'ok' => true, 'matched' => true, 'order' => $order->get_order_number(), 'result' => $r );
	}
	// link a waiting parcel to an order and replay its updates in order
	function fika_rtd_link( $order, $tracking ) {
		$order->update_meta_data( '_fika_rtd_tracking', $tracking );
		$order->save();
		$wait = get_option( 'fika_rtd_unmatched', array() );
		if ( isset( $wait[ $tracking ] ) ) {
			$evs = $wait[ $tracking ]['events'];
			usort( $evs, function ( $a, $b ) { return $a['time'] - $b['time']; } );
			foreach ( $evs as $ev ) {
				fika_rtd_apply( wc_get_order( $order->get_id() ), $ev );
			}
			unset( $wait[ $tracking ] );
			update_option( 'fika_rtd_unmatched', $wait, false );
		}
		fika_rtd_log( $tracking . ': linked to order #' . $order->get_order_number() );
	}
}

// ---------- the webhook ----------
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/rtd-webhook', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$sent = (string) $req->get_header( 'rtd_signature' );
			if ( '' === $sent ) {
				$sent = (string) $req->get_param( 'key' );
			}
			if ( '' === $sent || ! hash_equals( fika_rtd_secret(), $sent ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad signature' ), 401 );
			}
			$body = $req->get_json_params();
			if ( ! is_array( $body ) ) {
				$body = $req->get_body_params();
			}
			$list = isset( $body[0] ) && is_array( $body[0] ) ? $body : array( $body );
			$out  = array();
			foreach ( $list as $b ) {
				$ev = fika_rtd_event( is_array( $b ) ? $b : array() );
				if ( '' === $ev['tracking'] && '' === $ev['invoice'] ) {
					$out[] = array( 'ok' => false, 'error' => 'no tracking_id' );
					continue;
				}
				$out[] = fika_rtd_handle( $ev );
			}
			return 1 === count( $out ) ? $out[0] : array( 'ok' => true, 'results' => $out );
		},
	) );
} );

// ---------- WooCommerce > RT Deliveries ----------
add_action( 'admin_menu', function () {
	$n     = count( (array) get_option( 'fika_rtd_unmatched', array() ) );
	$title = 'RT Deliveries' . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' );
	add_submenu_page( 'woocommerce', 'RT Deliveries', $title, 'manage_woocommerce', 'fika-rtd', function () {
		$url   = rest_url( 'fika/v1/rtd-webhook' );
		$wait  = (array) get_option( 'fika_rtd_unmatched', array() );
		$log   = (array) get_option( 'fika_rtd_log', array() );
		$open  = fika_rtd_open_orders();
		$fmt   = function ( $t ) { return wp_date( 'j M, H:i', $t ); };
		$label = function ( $o ) {
			return '#' . $o->get_order_number() . ' · ' . trim( $o->get_shipping_first_name() . ' ' . $o->get_shipping_last_name() ) . ' · $' . number_format( (float) $o->get_total(), 2 ) . ' · ' . wp_date( 'j M', $o->get_date_created()->getTimestamp() );
		};
		?>
		<div class="wrap fika-rtd">
			<h1>RT Deliveries</h1>
			<style>
				.fika-rtd .box { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 20px; margin: 16px 0; max-width: 1000px; }
				.fika-rtd code.big { display: inline-block; padding: 6px 10px; font-size: 13px; user-select: all; word-break: break-all; }
				.fika-rtd table { border-collapse: collapse; width: 100%; }
				.fika-rtd td, .fika-rtd th { text-align: left; padding: 8px 6px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
				.fika-rtd .muted { color: #646970; }
				.fika-rtd select { max-width: 100%; }
			</style>
			<?php if ( isset( $_GET['linked'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success"><p>Parcel linked and its status applied.</p></div>
			<?php endif; ?>

			<div class="box">
				<h2>Parcels to link (<?php echo (int) count( $wait ); ?>)</h2>
				<p class="muted">RT sent updates for these parcels but no order has their tracking ID or order number yet. Pick the order and press Link. Next time, type the Fika order number (e.g. 1458) in RT's <b>Invoice no</b> field, and the parcel links by itself.</p>
				<?php if ( ! $wait ) : ?>
					<p>Nothing waiting.</p>
				<?php else : ?>
					<table>
						<tr><th>RT parcel</th><th>Latest RT status</th><th>Cash</th><th>Link to order</th></tr>
						<?php foreach ( array_reverse( $wait, true ) as $tid => $w ) :
							$ev   = $w['ev'];
							$same = '' !== $ev['cash'] ? fika_rtd_open_orders( $ev['cash'] ) : array();
							?>
							<tr>
								<td><b><?php echo esc_html( $ev['tracking'] ); ?></b><?php echo '' !== $ev['invoice'] ? '<br><span class="muted">Invoice ' . esc_html( $ev['invoice'] ) . '</span>' : ''; ?></td>
								<td><?php echo esc_html( fika_rtd_label( $ev['slug'] ) ); ?><br><span class="muted"><?php echo esc_html( $fmt( $ev['time'] ) . ( '' !== $ev['note'] ? ' · ' . $ev['note'] : '' ) ); ?></span></td>
								<td><?php echo '' !== $ev['cash'] ? '$' . esc_html( $ev['cash'] ) : '–'; ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="fika_rtd_link">
										<input type="hidden" name="tracking" value="<?php echo esc_attr( $tid ); ?>">
										<?php wp_nonce_field( 'fika_rtd_link' ); ?>
										<select name="order_id" required>
											<option value="">Choose the order…</option>
											<?php if ( $same ) : ?>
												<optgroup label="Same amount">
													<?php foreach ( $same as $o ) : ?>
														<option value="<?php echo (int) $o->get_id(); ?>"><?php echo esc_html( $label( $o ) ); ?></option>
													<?php endforeach; ?>
												</optgroup>
											<?php endif; ?>
											<optgroup label="All open orders without a parcel">
												<?php foreach ( $open as $o ) : ?>
													<option value="<?php echo (int) $o->get_id(); ?>"><?php echo esc_html( $label( $o ) ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										</select>
										<button class="button button-primary">Link</button>
										<button class="button" name="ignore" value="1" title="Not a website order (e.g. a WhatsApp order)">Not a website order</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>

			<div class="box">
				<h2>Webhook for RT</h2>
				<p>Give RT these two values (in their dashboard's webhook settings, or ask their support to set them):</p>
				<p><b>Webhook URL</b><br><code class="big"><?php echo esc_html( $url ); ?></code></p>
				<p><b>Webhook secret</b><br><code class="big"><?php echo esc_html( fika_rtd_secret() ); ?></code></p>
				<p class="muted">Updates without this secret are refused. If the secret ever leaks, make a new one and give RT the new value.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Make a new secret? RT must be given the new one, or their updates will be refused.');">
					<input type="hidden" name="action" value="fika_rtd_secret">
					<?php wp_nonce_field( 'fika_rtd_secret' ); ?>
					<button class="button">Make a new secret</button>
				</form>
			</div>

			<div class="box">
				<h2>What each RT status does</h2>
				<table>
					<?php foreach ( fika_rtd_statuses() as $slug => $s ) : ?>
						<tr><td><?php echo esc_html( $s[0] ); ?></td><td class="muted"><?php echo 'completed' === $s[1] ? 'Order → <b>Completed</b> (counts as delivered)' : ( 'undelivered' === $s[1] ? 'Order → <b>Undelivered</b>' : 'Shown on the order, status unchanged' ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			</div>

			<div class="box">
				<h2>Latest updates from RT</h2>
				<?php if ( ! $log ) : ?>
					<p class="muted">None yet.</p>
				<?php else : ?>
					<table>
						<?php foreach ( array_slice( $log, 0, 40 ) as $l ) : ?>
							<tr><td class="muted" style="width:120px"><?php echo esc_html( $fmt( $l[0] ) ); ?></td><td><?php echo esc_html( $l[1] ); ?></td></tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	} );
}, 61 );

add_action( 'admin_post_fika_rtd_link', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_link' );
	$tracking = isset( $_POST['tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking'] ) ) : '';
	if ( ! empty( $_POST['ignore'] ) ) {
		$wait = get_option( 'fika_rtd_unmatched', array() );
		unset( $wait[ $tracking ] );
		update_option( 'fika_rtd_unmatched', $wait, false );
		fika_rtd_log( $tracking . ': marked as not a website order' );
		wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' ) );
		exit;
	}
	$order = wc_get_order( isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0 );
	if ( $order && '' !== $tracking ) {
		fika_rtd_link( $order, $tracking );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd&linked=1' ) );
	exit;
} );

add_action( 'admin_post_fika_rtd_secret', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_rtd_secret' );
	update_option( 'fika_rtd_secret', wp_generate_password( 32, false, false ), false );
	fika_rtd_log( 'New webhook secret made' );
	wp_safe_redirect( admin_url( 'admin.php?page=fika-rtd' ) );
	exit;
} );

// ---------- the order page: RT tracking ID box + parcel history ----------
add_action( 'add_meta_boxes', function () {
	$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	add_meta_box( 'fika-rtd', 'RT Deliveries', function ( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$t    = (string) $order->get_meta( '_fika_rtd_tracking' );
		$hist = $order->get_meta( '_fika_rtd_history' );
		wp_nonce_field( 'fika_rtd_box', 'fika_rtd_nonce' );
		echo '<p><label for="fika_rtd_tracking"><b>RT tracking ID</b></label><br><input type="text" id="fika_rtd_tracking" name="fika_rtd_tracking" value="' . esc_attr( $t ) . '" style="width:100%" placeholder="Paste it from RT"></p>';
		if ( is_array( $hist ) && $hist ) {
			echo '<ul style="margin:0">';
			foreach ( array_reverse( $hist ) as $h ) {
				echo '<li><b>' . esc_html( fika_rtd_label( $h['slug'] ) ) . '</b> <span style="color:#646970">' . esc_html( wp_date( 'j M, H:i', $h['time'] ) ) . '</span>' . ( '' !== $h['note'] ? '<br><span style="color:#646970">' . esc_html( $h['note'] ) . '</span>' : '' ) . '</li>';
			}
			echo '</ul>';
		} elseif ( $t ) {
			echo '<p style="color:#646970">No update from RT yet.</p>';
		}
	}, $screen, 'side', 'high' );
} );
add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
	if ( ! isset( $_POST['fika_rtd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fika_rtd_nonce'] ) ), 'fika_rtd_box' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	$new   = isset( $_POST['fika_rtd_tracking'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['fika_rtd_tracking'] ) ) ) : '';
	if ( ! $order || $new === (string) $order->get_meta( '_fika_rtd_tracking' ) ) {
		return;
	}
	if ( '' === $new ) {
		$order->delete_meta_data( '_fika_rtd_tracking' );
		$order->save();
		return;
	}
	// runs after WooCommerce saved the order, so the status it applies is not overwritten
	add_action( 'shutdown', function () use ( $order_id, $new ) {
		$o = wc_get_order( $order_id );
		if ( $o ) {
			fika_rtd_link( $o, $new );
		}
	} );
}, 50 );

// ---------- Orders list: "RT" column; orders searchable by tracking ID ----------
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $fika_hook ) {
	add_filter( $fika_hook, function ( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$out['fika_rtd'] = 'RT';
			}
		}
		return $out;
	}, 30 );
}
if ( ! function_exists( 'fika_rtd_column' ) ) {
	function fika_rtd_column( $order ) {
		$slug = (string) $order->get_meta( '_fika_rtd_status' );
		$t    = (string) $order->get_meta( '_fika_rtd_tracking' );
		if ( '' === $slug && '' === $t ) {
			echo '<span style="color:#a7aaad">–</span>';
			return;
		}
		echo esc_html( '' !== $slug ? fika_rtd_label( $slug ) : 'Linked' );
		if ( $t ) {
			echo '<br><small style="color:#646970">' . esc_html( $t ) . '</small>';
		}
	}
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $col, $order ) {
	if ( 'fika_rtd' === $col ) {
		fika_rtd_column( $order );
	}
}, 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'fika_rtd' === $col && ( $o = wc_get_order( $post_id ) ) ) {
		fika_rtd_column( $o );
	}
}, 10, 2 );
add_filter( 'woocommerce_order_table_search_query_meta_keys', function ( $keys ) {
	$keys[] = '_fika_rtd_tracking';
	return $keys;
} );
add_filter( 'woocommerce_shop_order_search_fields', function ( $fields ) {
	$fields[] = '_fika_rtd_tracking';
	return $fields;
} );
