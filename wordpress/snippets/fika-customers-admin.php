<?php
/**
 * Fika: WooCommerce > Fika customers — one list of everyone: people with an account and people who ordered as guests.
 * Columns: name, email, phone, type (Account / Guest), signed up, orders, delivered, kg delivered, total spent, last order.
 * Tabs: All / Accounts / Accounts that ordered / Accounts with no orders / Guests. "Download CSV" exports the current tab.
 * Orders counted: Processing, On hold and Completed ("delivered" = Completed). Shop managers only.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-customers-admin.php
 */

if ( ! function_exists( 'fika_customer_rows' ) ) {
	function fika_customer_rows() {
		$rows = array();
		// everyone with an account (customers; plus staff only if they ordered)
		foreach ( get_users( array( 'orderby' => 'registered', 'order' => 'DESC', 'number' => -1 ) ) as $u ) {
			$rows[ 'u' . $u->ID ] = array(
				'key'      => 'u' . $u->ID,
				'user_id'  => $u->ID,
				'type'     => 'Account',
				'staff'    => user_can( $u, 'edit_posts' ),
				'name'     => trim( $u->first_name . ' ' . $u->last_name ) ? trim( $u->first_name . ' ' . $u->last_name ) : $u->display_name,
				'email'    => $u->user_email,
				'phone'    => (string) get_user_meta( $u->ID, 'billing_phone', true ),
				'joined'   => strtotime( $u->user_registered . ' UTC' ),
				'orders'   => 0,
				'done'     => 0,
				'grams'    => 0,
				'spent'    => 0.0,
				'last'     => 0,
			);
		}
		$orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( array(
			'status' => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ),
			'limit'  => -1,
			'type'   => 'shop_order',
		) ) : array();
		foreach ( $orders as $o ) {
			$cid = (int) $o->get_customer_id();
			$key = $cid ? 'u' . $cid : 'g' . strtolower( trim( $o->get_billing_email() ) );
			if ( ! isset( $rows[ $key ] ) ) {
				if ( $cid ) {
					continue; // order of a deleted account
				}
				$rows[ $key ] = array(
					'key'     => $key,
					'user_id' => 0,
					'type'    => 'Guest',
					'staff'   => false,
					'name'    => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
					'email'   => $o->get_billing_email(),
					'phone'   => $o->get_billing_phone(),
					'joined'  => 0,
					'orders'  => 0,
					'done'    => 0,
					'grams'   => 0,
					'spent'   => 0.0,
					'last'    => 0,
				);
			}
			$r = &$rows[ $key ];
			$r['orders']++;
			$r['spent'] += (float) $o->get_total() - (float) $o->get_total_refunded();
			$t = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0;
			if ( $t > $r['last'] ) {
				$r['last'] = $t;
				if ( ! $r['phone'] ) {
					$r['phone'] = $o->get_billing_phone();
				}
				if ( ! $r['name'] ) {
					$r['name'] = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
				}
			}
			if ( 'completed' === $o->get_status() ) {
				$r['done']++;
				$r['grams'] += function_exists( 'fika_order_grams' ) ? fika_order_grams( $o ) : 0;
			}
			unset( $r );
		}
		// staff accounts only appear if they ordered
		return array_values( array_filter( $rows, function ( $r ) {
			return ! $r['staff'] || $r['orders'] > 0;
		} ) );
	}
}

if ( ! function_exists( 'fika_customer_tabs' ) ) {
	function fika_customer_tabs() {
		return array(
			'all'      => array( 'All', function ( $r ) { return true; } ),
			'accounts' => array( 'Accounts', function ( $r ) { return 'Account' === $r['type']; } ),
			'ordered'  => array( 'Accounts that ordered', function ( $r ) { return 'Account' === $r['type'] && $r['orders'] > 0; } ),
			'noorders' => array( 'Accounts with no orders yet', function ( $r ) { return 'Account' === $r['type'] && 0 === $r['orders']; } ),
			'guests'   => array( 'Guests (ordered without an account)', function ( $r ) { return 'Guest' === $r['type']; } ),
		);
	}
}

if ( ! function_exists( 'fika_orders_admin_url' ) ) {
	function fika_orders_admin_url( $args ) {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return $hpos ? add_query_arg( $args, admin_url( 'admin.php?page=wc-orders' ) ) : add_query_arg( array_merge( array( 'post_type' => 'shop_order' ), $args ), admin_url( 'edit.php' ) );
	}
}

if ( ! function_exists( 'fika_customers_page_html' ) ) {
	function fika_customers_page_html( $tab ) {
		$tabs = fika_customer_tabs();
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'all';
		$all  = fika_customer_rows();
		$rows = array_values( array_filter( $all, $tabs[ $tab ][1] ) );
		usort( $rows, function ( $a, $b ) {
			return max( $b['joined'], $b['last'] ) <=> max( $a['joined'], $a['last'] );
		} );
		$base = admin_url( 'admin.php?page=fika-customers' );
		$date = function ( $t ) {
			return $t ? esc_html( wp_date( 'j M Y', $t ) ) : '&mdash;';
		};
		ob_start();
		?>
<div class="wrap fika-customers">
	<h1 class="wp-heading-inline">Fika customers</h1>
	<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'fika_customers_csv', 'tab' => $tab ), admin_url( 'admin-post.php' ) ), 'fika_customers_csv' ) ); ?>">Download CSV</a>
	<hr class="wp-header-end">
	<p class="description">Everyone with a Fika account, and everyone who ordered as a guest. Orders count when Processing, On hold or Completed; "Delivered" and "Kg delivered" count Completed orders only.</p>
	<ul class="subsubsub">
		<?php
		$i = 0;
		foreach ( $tabs as $k => $t ) :
			$n = count( array_filter( $all, $t[1] ) );
			?>
			<li><?php echo $i++ ? ' | ' : ''; ?><a href="<?php echo esc_url( add_query_arg( 'tab', $k, $base ) ); ?>"<?php echo $k === $tab ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $t[0] ); ?> <span class="count">(<?php echo (int) $n; ?>)</span></a></li>
		<?php endforeach; ?>
	</ul>
	<table class="wp-list-table widefat fixed striped">
		<thead><tr>
			<th style="width:16%">Name</th><th style="width:20%">Email</th><th style="width:11%">Phone</th><th style="width:8%">Type</th>
			<th style="width:9%">Signed up</th><th style="width:6%">Orders</th><th style="width:7%">Delivered</th><th style="width:7%">Kg delivered</th><th style="width:7%">Spent</th><th style="width:9%">Last order</th>
		</tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="10">Nobody here yet.</td></tr>
		<?php endif; ?>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><strong><?php if ( $r['user_id'] ) : ?><a href="<?php echo esc_url( get_edit_user_link( $r['user_id'] ) ); ?>"><?php echo esc_html( $r['name'] ? $r['name'] : '(no name)' ); ?></a><?php else : ?><?php echo esc_html( $r['name'] ? $r['name'] : '(no name)' ); ?><?php endif; ?></strong></td>
				<td><a href="mailto:<?php echo esc_attr( $r['email'] ); ?>"><?php echo esc_html( $r['email'] ); ?></a></td>
				<td><?php echo $r['phone'] ? esc_html( $r['phone'] ) : '&mdash;'; ?></td>
				<td><span class="fika-badge fika-badge-<?php echo 'Account' === $r['type'] ? 'acc' : 'guest'; ?>"><?php echo esc_html( $r['type'] ); ?></span></td>
				<td><?php echo $date( $r['joined'] ); ?></td>
				<td><?php if ( $r['orders'] ) : ?><a href="<?php echo esc_url( fika_orders_admin_url( $r['user_id'] ? array( '_customer_user' => $r['user_id'] ) : array( 's' => $r['email'] ) ) ); ?>"><?php echo (int) $r['orders']; ?></a><?php else : ?>0<?php endif; ?></td>
				<td><?php echo (int) $r['done']; ?></td>
				<td><?php echo esc_html( function_exists( 'fika_kg_text' ) ? fika_kg_text( $r['grams'] ) : ( $r['grams'] / 1000 ) . ' kg' ); ?></td>
				<td><?php echo wp_kses_post( wc_price( $r['spent'] ) ); ?></td>
				<td><?php echo $date( $r['last'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<style>
	.fika-customers .subsubsub { margin-bottom: 10px; }
	.fika-customers table { clear: both; }
	.fika-customers .fika-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
	.fika-customers .fika-badge-acc { background: #dcebff; color: #004aad; }
	.fika-customers .fika-badge-guest { background: #f3f0f0; color: #50575e; }
	</style>
</div>
		<?php
		return ob_get_clean();
	}
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Fika customers', 'Fika customers', 'manage_woocommerce', 'fika-customers', function () {
		echo fika_customers_page_html( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'all' ); // phpcs:ignore WordPress.Security
	} );
}, 60 );

// "Download CSV" for the tab being viewed
add_action( 'admin_post_fika_customers_csv', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_customers_csv' );
	$tabs = fika_customer_tabs();
	$tab  = isset( $_GET['tab'], $tabs[ sanitize_key( wp_unslash( $_GET['tab'] ) ) ] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'all';
	$rows = array_values( array_filter( fika_customer_rows(), $tabs[ $tab ][1] ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=fika-customers-' . $tab . '-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // so Excel reads the accents correctly
	fputcsv( $out, array( 'Name', 'Email', 'Phone', 'Type', 'Signed up', 'Orders', 'Delivered', 'Kg delivered', 'Spent (USD)', 'Last order' ) );
	foreach ( $rows as $r ) {
		fputcsv( $out, array(
			$r['name'], $r['email'], $r['phone'], $r['type'],
			$r['joined'] ? wp_date( 'Y-m-d', $r['joined'] ) : '',
			$r['orders'], $r['done'], round( $r['grams'] / 1000, 1 ), number_format( $r['spent'], 2, '.', '' ),
			$r['last'] ? wp_date( 'Y-m-d', $r['last'] ) : '',
		) );
	}
	fclose( $out );
	exit;
} );
