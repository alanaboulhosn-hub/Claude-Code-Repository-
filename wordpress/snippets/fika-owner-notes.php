<?php
/**
 * Fika: the owner's note on each order: a personal message to that customer and the question that goes with it
 * (answered on Instagram). Shown in the order preview on the Orders list (the eye icon), under the products, where
 * it can be edited and saved; the preview also has arrows to the previous and next order of the list.
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
}

// the note travels with the order preview's data
add_filter( 'woocommerce_admin_order_preview_get_order_details', function ( $data, $order ) {
	$n                 = fika_owner_note_get( $order );
	$data['fika_note'] = array(
		'id'       => $order->get_id(),
		'name'     => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
		'message'  => $n['message'] ?? '',
		'question' => $n['question'] ?? '',
		'meta'     => $n ? 'Written by ' . ( $n['by'] ?? '' ) . ', ' . wp_date( 'j M Y, H:i', (int) ( $n['at'] ?? 0 ) ) : '',
	);
	return $data;
}, 10, 2 );

// ... and is shown under the products in the preview (an underscore template: {{ }} escapes)
add_action( 'woocommerce_admin_order_preview_end', function () {
	?>
	<# if ( data.fika_note ) { #>
	<div class="fon" data-order="{{ data.fika_note.id }}">
		<div class="fon-title">Owner's note</div>
		<div class="fon-card">
			<span class="fon-lbl">Message to {{ data.fika_note.name }}</span>
			<textarea class="fon-m" rows="3" placeholder="No note yet">{{ data.fika_note.message }}</textarea>
		</div>
		<div class="fon-card q">
			<span class="fon-lbl">Question for Instagram</span>
			<textarea class="fon-q" rows="2" placeholder="No question yet">{{ data.fika_note.question }}</textarea>
		</div>
		<div class="fon-foot"><span class="fon-meta">{{ data.fika_note.meta }}</span><button type="button" class="button fon-save">Save note</button></div>
	</div>
	<# } #>
	<?php
} );

// styles, the save button and the previous / next arrows, on the Orders list only
add_action( 'admin_footer', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	?>
	<style>
	.wc-order-preview .fon { margin: 18px 1.5em 6px; display: grid; gap: 12px; }
	.wc-order-preview .fon-title { font-size: 14px; font-weight: 600; color: #1d2327; }
	.wc-order-preview .fon-card { border: 1px solid #e6c9d6; background: #fff7fa; border-radius: 8px; padding: 12px 14px; }
	.wc-order-preview .fon-card.q { border-color: #c9d6ee; background: #f5f8fe; }
	.wc-order-preview .fon-lbl { display: block; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #8a5a72; margin-bottom: 6px; }
	.wc-order-preview .fon-card.q .fon-lbl { color: #3d5a99; }
	.wc-order-preview .fon textarea { width: 100%; border: 0; background: transparent; padding: 0; font-size: 14px; line-height: 1.55; color: #1d2433; resize: vertical; box-shadow: none; min-height: 0; }
	.wc-order-preview .fon textarea:focus { outline: 1px dashed #c9a0b5; outline-offset: 4px; box-shadow: none; }
	.wc-order-preview .fon-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
	.wc-order-preview .fon-meta { font-size: 12px; color: #7a8193; }
	.fon-nav { position: absolute; top: 0; right: 54px; height: 100%; display: flex; align-items: center; gap: 4px; }
	.fon-nav button { font-size: 20px; line-height: 1; color: #1d2327; border: 1px solid #c3c4c7; background: #fff; border-radius: 4px; width: 32px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
	.fon-nav button:hover:not([disabled]) { border-color: #2271b1; color: #2271b1; }
	.fon-nav button[disabled] { opacity: .35; cursor: default; }
	</style>
	<script>
	jQuery( function ( $ ) {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
		var api = <?php echo wp_json_encode( esc_url_raw( rest_url( 'fika/v1/owner-note/' ) ) ); ?>;
		var current = 0;
		function buttons() { return $( 'a.order-preview' ).filter( function () { return $( this ).closest( 'tr' ).is( ':visible' ); } ); }
		function indexOf( id ) { var i = -1; buttons().each( function ( k ) { if ( String( $( this ).data( 'orderId' ) ) === String( id ) ) { i = k; } } ); return i; }
		// which order is open: caught before WooCommerce handles the click, and read again from the preview itself
		document.addEventListener( 'click', function ( e ) { var a = e.target.closest ? e.target.closest( 'a.order-preview' ) : null; if ( a ) { current = a.getAttribute( 'data-order-id' ); } }, true );
		function go( step ) {
			var list = buttons(), i = indexOf( current ) + step;
			if ( i < 0 ) { return; }
			if ( i >= list.length ) { return; }
			var next = list.eq( i );
			$( '.wc-backbone-modal .modal-close' ).first().trigger( 'click' );
			setTimeout( function () { next.trigger( 'click' ); }, 60 );
		}
		$( document.body ).on( 'wc_backbone_modal_loaded', function ( e, target ) {
			if ( 'wc-modal-view-order' !== target ) { return; }
			current = $( '.wc-order-preview .fon' ).last().data( 'order' ) || current;
			var head = $( '.wc-backbone-modal-header' ).last(), i = indexOf( current ), n = buttons().length;
			head.css( 'position', 'relative' ).find( '.fon-nav' ).remove();
			var nav = $( '<div class="fon-nav"><button type="button" class="fon-prev" title="Previous order (left arrow key)">&#8249;</button><button type="button" class="fon-next" title="Next order (right arrow key)">&#8250;</button></div>' );
			nav.find( '.fon-prev' ).prop( 'disabled', i <= 0 ).on( 'click', function () { go( -1 ); } );
			nav.find( '.fon-next' ).prop( 'disabled', i < 0 || i >= n - 1 ).on( 'click', function () { go( 1 ); } );
			head.append( nav );
		} );
		$( document ).on( 'keydown', function ( e ) {
			if ( ! $( '.wc-backbone-modal .wc-order-preview' ).length ) { return; }
			if ( $( e.target ).is( 'textarea, input' ) ) { return; }
			if ( 37 === e.which ) { go( -1 ); }
			if ( 39 === e.which ) { go( 1 ); }
		} );
		$( document.body ).on( 'click', '.wc-order-preview .fon-save', function () {
			var box = $( this ).closest( '.fon' ), btn = $( this );
			btn.prop( 'disabled', true ).text( 'Saving...' );
			$.ajax( { url: api + box.data( 'order' ), method: 'POST', contentType: 'application/json', headers: { 'X-WP-Nonce': nonce },
				data: JSON.stringify( { message: box.find( '.fon-m' ).val(), question: box.find( '.fon-q' ).val() } ) } )
				.done( function () { btn.text( 'Saved' ); box.find( '.fon-meta' ).text( 'Saved just now' ); } )
				.fail( function ( x ) { btn.text( 'Not saved' ); alert( ( x.responseJSON && x.responseJSON.message ) || 'The note could not be saved.' ); } )
				.always( function () { setTimeout( function () { btn.prop( 'disabled', false ).text( 'Save note' ); }, 1500 ); } );
		} );
	} );
	</script>
	<?php
} );

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
				$by = (string) $req->get_param( 'by' );
				if ( '' === $by ) {
					$u  = wp_get_current_user();
					$by = $u->display_name ?: 'the shop';
				}
				return fika_owner_note_set( $order, $m, $q, $by );
			},
		),
	) );
} );
