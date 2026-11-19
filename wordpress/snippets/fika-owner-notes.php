<?php
/**
 * Fika: the owner's note on each order: a personal message to that customer and the question that goes with it
 * (answered on Instagram). Shown on the order page in the admin, under the order, and editable there.
 * Written by the owner's-notes agent (wordpress/tools/notes) through POST /wp-json/fika/v1/owner-note/{order id}
 * (shop staff only). Stored in the order meta _fika_owner_note: { message, question, by, at }.
 * Never shown or emailed to the customer. Saving a note changes nothing else on the order.
 * Installed with the Code Snippets plugin (scope: everywhere). Source: wordpress/snippets/fika-owner-notes.php
 */

if ( ! function_exists( 'fika_owner_note_get' ) ) {
	function fika_owner_note_get( $order ) {
		$n = $order ? $order->get_meta( '_fika_owner_note' ) : '';
		if ( is_string( $n ) && '' !== $n ) {
			$n = json_decode( $n, true );
		}
		return is_array( $n ) ? $n : array();
	}

	function fika_owner_note_set( $order, $message, $question, $by ) {
		$order->update_meta_data( '_fika_owner_note', array(
			'message'  => mb_substr( sanitize_textarea_field( $message ), 0, 800 ),
			'question' => mb_substr( sanitize_textarea_field( $question ), 0, 400 ),
			'by'       => sanitize_text_field( $by ),
			'at'       => time(),
		) );
		$order->save_meta_data();   // only the note: no order save, no status change, no email
		return fika_owner_note_get( $order );
	}

	function fika_owner_note_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$n    = fika_owner_note_get( $order );
		$name = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
		wp_nonce_field( 'fika_owner_note', 'fika_owner_note_nonce' );
		?>
		<style>
		#fika-owner-note .fon { display: grid; gap: 14px; padding: 6px 2px 4px; }
		#fika-owner-note .fon-card { border: 1px solid #e6c9d6; background: #fff7fa; border-radius: 10px; padding: 14px 16px; }
		#fika-owner-note .fon-card.q { border-color: #c9d6ee; background: #f5f8fe; }
		#fika-owner-note .fon-lbl { display: block; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #8a5a72; margin-bottom: 6px; }
		#fika-owner-note .q .fon-lbl { color: #3d5a99; }
		#fika-owner-note textarea { width: 100%; border: 0; background: transparent; padding: 0; font-size: 15px; line-height: 1.55; color: #1d2433; resize: vertical; box-shadow: none; }
		#fika-owner-note textarea:focus { outline: 1px dashed #c9a0b5; outline-offset: 4px; }
		#fika-owner-note .fon-meta { font-size: 12px; color: #7a8193; }
		#fika-owner-note .fon-empty { color: #7a8193; font-style: italic; }
		</style>
		<div class="fon">
			<?php if ( ! $n ) : ?>
				<p class="fon-empty">No note yet for <?php echo esc_html( $name ); ?>. The notes agent writes one for every new order; you can also write it here.</p>
			<?php endif; ?>
			<div class="fon-card">
				<span class="fon-lbl">Message to <?php echo esc_html( $name ); ?></span>
				<textarea name="fika_owner_note_message" rows="3"><?php echo esc_textarea( $n['message'] ?? '' ); ?></textarea>
			</div>
			<div class="fon-card q">
				<span class="fon-lbl">Question for Instagram</span>
				<textarea name="fika_owner_note_question" rows="2"><?php echo esc_textarea( $n['question'] ?? '' ); ?></textarea>
			</div>
			<?php if ( $n ) : ?>
				<div class="fon-meta">Written by <?php echo esc_html( $n['by'] ?? '' ); ?>, <?php echo esc_html( wp_date( 'j M Y, H:i', (int) ( $n['at'] ?? 0 ) ) ); ?>. Edit the text and press Update to change it.</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

// the box, under the order's items, on the order page (both order storage screens)
add_action( 'add_meta_boxes', function () {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	foreach ( array_unique( $screens ) as $screen ) {
		add_meta_box( 'fika-owner-note', "Owner's note", 'fika_owner_note_box', $screen, 'normal', 'default' );
	}
} );

// right under the order items, even when an admin's saved page layout predates the box
foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $fika_screen ) {
	add_filter( 'get_user_option_meta-box-order_' . $fika_screen, function ( $order ) {
		if ( ! is_array( $order ) || empty( $order['normal'] ) ) {
			return $order;
		}
		$ids = array_values( array_diff( explode( ',', $order['normal'] ), array( 'fika-owner-note' ) ) );
		$at  = array_search( 'woocommerce-order-items', $ids, true );
		array_splice( $ids, false === $at ? count( $ids ) : $at + 1, 0, 'fika-owner-note' );
		foreach ( array( 'side', 'advanced' ) as $ctx ) {
			if ( ! empty( $order[ $ctx ] ) ) {
				$order[ $ctx ] = implode( ',', array_diff( explode( ',', $order[ $ctx ] ), array( 'fika-owner-note' ) ) );
			}
		}
		$order['normal'] = implode( ',', $ids );
		return $order;
	} );
}

// edits made in the box are kept when the order is updated
add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
	if ( ! isset( $_POST['fika_owner_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fika_owner_note_nonce'] ) ), 'fika_owner_note' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$m   = isset( $_POST['fika_owner_note_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fika_owner_note_message'] ) ) : '';
	$q   = isset( $_POST['fika_owner_note_question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fika_owner_note_question'] ) ) : '';
	$old = fika_owner_note_get( $order );
	if ( trim( $m ) === trim( $old['message'] ?? '' ) && trim( $q ) === trim( $old['question'] ?? '' ) ) {
		return;
	}
	$user = wp_get_current_user();
	fika_owner_note_set( $order, $m, $q, $user->display_name ?: 'the shop' );
}, 60 );

// the agent's door: read or write one order's note (shop staff only)
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/owner-note/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
			'callback'            => function ( $req ) {
				$order = wc_get_order( (int) $req['id'] );
				return $order ? fika_owner_note_get( $order ) : new WP_Error( 'fika_no_order', 'No such order', array( 'status' => 404 ) );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
			'callback'            => function ( $req ) {
				$order = wc_get_order( (int) $req['id'] );
				if ( ! $order ) {
					return new WP_Error( 'fika_no_order', 'No such order', array( 'status' => 404 ) );
				}
				$m = (string) $req->get_param( 'message' );
				$q = (string) $req->get_param( 'question' );
				if ( '' === trim( $m ) || '' === trim( $q ) ) {
					return new WP_Error( 'fika_note_empty', 'Message and question are both needed', array( 'status' => 400 ) );
				}
				return fika_owner_note_set( $order, $m, $q, (string) ( $req->get_param( 'by' ) ?: 'the notes agent' ) );
			},
		),
	) );
} );
