<?php
/**
 * Fika: a small golden envelope next to the customer's name in WooCommerce > Orders when the customer wrote a note
 * with the order (owner, 27 Oct 2026), so it shows before the order is opened. Hover it to read the note.
 * - The envelope is printed in its own narrow column right after the Order column, then moved next to the name by a
 *   few lines of script (WooCommerce's Order column itself cannot take extra HTML). Without script it simply stays
 *   in that narrow column.
 * - Admin screen only: nothing changes for customers, orders are only read.
 * Installed with the Code Snippets plugin (admin only). Source: wordpress/snippets/fika-order-note-icon.php
 */
if ( ! function_exists( 'fika_note_icon' ) ) {
	function fika_note_icon( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$note = trim( (string) $order->get_customer_note() );
		if ( '' === $note ) {
			return;
		}
		$tip = 'Customer note: ' . ( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $note, 0, 300, '…' ) : substr( $note, 0, 300 ) );
		echo '<span class="fika-note-env" title="' . esc_attr( $tip ) . '" aria-label="' . esc_attr( $tip ) . '">'
			. '<svg width="18" height="13" viewBox="0 0 18 13" aria-hidden="true"><defs><linearGradient id="fkng" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0" stop-color="#f7d774"/><stop offset="1" stop-color="#d4a017"/></linearGradient></defs>'
			. '<rect x=".75" y=".75" width="16.5" height="11.5" rx="1.8" fill="url(#fkng)" stroke="#9c7300" stroke-width="1.1"/>'
			. '<path d="M1.4 1.6 9 7.4l7.6-5.8" fill="none" stroke="#9c7300" stroke-width="1.2" stroke-linejoin="round"/></svg></span>';
	}
}

// the narrow column right after Order (its header stays empty)
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $fika_hook ) {
	add_filter( $fika_hook, function ( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_number' === $k ) {
				$out['fika_note'] = '<span class="screen-reader-text">Customer note</span>';
			}
		}
		if ( ! isset( $out['fika_note'] ) ) {
			$out['fika_note'] = '<span class="screen-reader-text">Customer note</span>';
		}
		return $out;
	}, 50 );
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $col, $order ) {
	if ( 'fika_note' === $col ) {
		fika_note_icon( $order );
	}
}, 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'fika_note' === $col ) {
		fika_note_icon( wc_get_order( $post_id ) );
	}
}, 10, 2 );

// move each envelope next to the name, then hide the emptied column
add_action( 'admin_footer', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
		return;
	}
	?>
	<style>
		.column-fika_note { width: 26px; }
		.fika-note-env { display: inline-block; margin-left: 6px; vertical-align: -2px; cursor: help; line-height: 0; }
		.fika-note-env svg { display: block; filter: drop-shadow(0 1px 1px rgba(120, 85, 0, .35)); }
		.fika-note-moved .column-fika_note { display: none; }
	</style>
	<script>
	(function () {
		var t = document.querySelector('.wp-list-table');
		if (!t) { return; }
		var envs = t.querySelectorAll('td.column-fika_note .fika-note-env');
		for (var i = 0; i < envs.length; i++) {
			var row = envs[i].closest('tr'), name = row && row.querySelector('.column-order_number a.order-view');
			if (name) { name.parentNode.insertBefore(envs[i], name.nextSibling); }
		}
		if (!t.querySelector('td.column-fika_note .fika-note-env')) { t.classList.add('fika-note-moved'); }
	})();
	</script>
	<?php
} );
