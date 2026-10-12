<?php
/**
 * Fika: WooCommerce > Fika customers — one list of everyone: people with an account and people who ordered as guests.
 * Columns: name, email, phone, type (Account / Guest), signed up, orders, delivered, rewards, total spent, last order.
 * Rewards: where the customer stands in Fika Club (kg that count: delivered + on the way, the lap, the next gift),
 * codes still available to them (claimed rewards, personal codes), rewards reached and not claimed yet, codes used
 * (on an order on its way, or delivered) and the mystery taste. Guests: the same kg and codes, but no Fika Club.
 * Tabs: All / Accounts / Accounts that ordered / Accounts with no orders / Guests. Search (name, email, phone) and
 * click-to-sort columns. "Download CSV" exports what is on screen (tab + search + sort).
 * WooCommerce's own Customers page is hidden from the menu (this list replaces it; remove that one line to bring it back).
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
				'reach'    => 0,
				'used'     => array(),
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
					'reach'   => 0,
					'used'    => array(),
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
			// what counts in Fika Club (delivered + on the way), and the codes this order used
			$r['reach'] += function_exists( 'fika_swim_order_grams' ) ? fika_swim_order_grams( $o ) : 0;
			foreach ( $o->get_coupon_codes() as $c ) {
				$r['used'][] = array( 'code' => strtoupper( $c ), 'num' => $o->get_order_number(), 'done' => 'completed' === $o->get_status() );
			}
			if ( 'completed' === $o->get_status() ) {
				$r['done']++;
				$r['grams'] += function_exists( 'fika_order_grams' ) ? fika_order_grams( $o ) : 0;
			}
			unset( $r );
		}
		// staff accounts only appear if they ordered
		$rows = array_values( array_filter( $rows, function ( $r ) {
			return ! $r['staff'] || $r['orders'] > 0;
		} ) );
		fika_customer_add_rewards( $rows );
		return $rows;
	}
}

if ( ! function_exists( 'fika_customer_add_rewards' ) ) {
	// a code's short description: "100 g on us" for rewards, else "10% off" / "$5 off"
	function fika_customer_code_label( $c ) {
		$cps = function_exists( 'fika_swim_checkpoints' ) ? fika_swim_checkpoints() : array();
		if ( preg_match( '/^swim(3|6|10|15)-/i', $c->get_code(), $m ) && isset( $cps[ (int) $m[1] * 1000 ] ) ) {
			return $cps[ (int) $m[1] * 1000 ]['title'];
		}
		return 'percent' === $c->get_discount_type() ? (float) $c->get_amount() . '% off' : '$' . (float) $c->get_amount() . ' off';
	}
	// Fika Club standing and codes, for every row (one look at all coupons, no extra order queries)
	function fika_customer_add_rewards( &$rows ) {
		$open = array(); // email => [ [code, label], ... ] personal codes still usable
		$ids  = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			$c = new WC_Coupon( $id );
			$emails = $c->get_email_restrictions();
			if ( ! $emails ) {
				continue; // codes for everyone (FIKA10 ...) are not personal
			}
			$limit = (int) $c->get_usage_limit();
			$exp   = $c->get_date_expires();
			if ( ( $limit && $c->get_usage_count() >= $limit ) || ( $exp && $exp->getTimestamp() < time() ) ) {
				continue;
			}
			foreach ( $emails as $e ) {
				$open[ strtolower( trim( $e ) ) ][] = array( strtoupper( $c->get_code() ), fika_customer_code_label( $c ) );
			}
		}
		$cps  = function_exists( 'fika_swim_checkpoints' ) ? fika_swim_checkpoints() : array();
		$lapg = defined( 'FIKA_SWIM_LAP' ) ? FIKA_SWIM_LAP : 15000;
		foreach ( $rows as &$r ) {
			$used = array_map( function ( $u ) { return $u['code']; }, $r['used'] );
			$r['avail'] = array_values( array_filter( isset( $open[ strtolower( trim( $r['email'] ) ) ] ) ? $open[ strtolower( trim( $r['email'] ) ) ] : array(), function ( $a ) use ( $used ) {
				return ! in_array( $a[0], $used, true );
			} ) );
			$r['ready'] = array();
			$r['taste'] = array();
			$r['next']  = '';
			$r['lap']   = (int) floor( $r['reach'] / $lapg ) + 1;
			if ( $r['user_id'] ) {
				if ( function_exists( 'fika_swim_unclaimed' ) ) {
					foreach ( fika_swim_unclaimed( $r['user_id'], $r['reach'] ) as $u ) {
						$r['ready'][] = isset( $cps[ $u[1] ] ) ? $cps[ $u[1] ]['title'] : $u[1] / 1000 . ' kg gift';
					}
				}
				if ( function_exists( 'fika_taste_list' ) ) {
					foreach ( fika_taste_list( $r['user_id'] ) as $t ) {
						$pr = wc_get_product( (int) $t['pid'] );
						$r['taste'][] = ( $pr ? html_entity_decode( $pr->get_name() ) : 'Mystery taste' ) . ( 'used' === $t['status'] ? ' (in an order)' : ' (won, not ordered yet)' );
					}
				}
				$pos  = $r['reach'] - ( $r['lap'] - 1 ) * $lapg;
				$have = array(); // gifts of this lap already claimed (a used 100 g does not count as kg, so it can sit just below)
				foreach ( function_exists( 'fika_swim_claims' ) ? fika_swim_claims( $r['user_id'] ) : array() as $cl ) {
					$have[ (int) $cl['lap'] . ':' . (int) $cl['g'] ] = true;
				}
				foreach ( $cps as $g => $cp ) {
					if ( $g > $pos && empty( $have[ $r['lap'] . ':' . $g ] ) ) {
						$r['next'] = fika_customer_kg( $g - $pos ) . ' to ' . $cp['title'];
						break;
					}
				}
			}
		}
		unset( $r );
	}
	function fika_customer_kg( $g ) {
		return function_exists( 'fika_kg_text' ) ? fika_kg_text( $g ) : round( $g / 1000, 1 ) . ' kg';
	}
	function fika_customer_rewards_cell( $r ) {
		$h = '<div class="fk-rw"><b>' . esc_html( fika_customer_kg( $r['reach'] ) ) . '</b>';
		if ( $r['user_id'] ) {
			$h .= ' <small>' . ( $r['lap'] > 1 ? 'lap ' . (int) $r['lap'] . ' &middot; ' : '' ) . esc_html( $r['next'] ) . '</small>';
		} else {
			$h .= ' <small>guest, not in Fika Club</small>';
		}
		$chips = '';
		foreach ( $r['avail'] as $a ) {
			$chips .= '<span class="fk-c fk-c-av" title="Available: not used yet">' . esc_html( $a[0] ) . ' &middot; ' . esc_html( $a[1] ) . '</span>';
		}
		foreach ( $r['ready'] as $t ) {
			$chips .= '<span class="fk-c fk-c-ready" title="Reached, not claimed yet">' . esc_html( $t ) . ' &middot; ready to claim</span>';
		}
		foreach ( $r['used'] as $u ) {
			$chips .= '<span class="fk-c ' . ( $u['done'] ? 'fk-c-used' : 'fk-c-way' ) . '" title="' . ( $u['done'] ? 'Used on a delivered order' : 'On an order on its way' ) . '">' . esc_html( $u['code'] ) . ' &middot; ' . ( $u['done'] ? 'used' : 'on its way' ) . ' #' . esc_html( $u['num'] ) . '</span>';
		}
		foreach ( $r['taste'] as $t ) {
			$chips .= '<span class="fk-c fk-c-taste" title="Mystery taste">&#127873; ' . esc_html( $t ) . '</span>';
		}
		return $h . ( $chips ? '<div class="fk-cs">' . $chips . '</div>' : '' ) . '</div>';
	}
	function fika_customer_rewards_text( $r, $what ) {
		if ( 'avail' === $what ) {
			return implode( '; ', array_merge( array_map( function ( $a ) { return $a[0] . ' (' . $a[1] . ')'; }, $r['avail'] ), array_map( function ( $t ) { return $t . ' (ready to claim)'; }, $r['ready'] ) ) );
		}
		return implode( '; ', array_map( function ( $u ) { return $u['code'] . ' (' . ( $u['done'] ? 'used' : 'on its way' ) . ' #' . $u['num'] . ')'; }, $r['used'] ) );
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

if ( ! function_exists( 'fika_customer_view' ) ) {
	// The rows for a tab, narrowed by a search and sorted by a column
	function fika_customer_view( $tab, $search, $orderby, $order ) {
		$tabs = fika_customer_tabs();
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'all';
		$rows = array_values( array_filter( fika_customer_rows(), $tabs[ $tab ][1] ) );
		$q    = strtolower( trim( $search ) );
		if ( '' !== $q ) {
			$digits = preg_replace( '/\D/', '', $q );
			$rows   = array_values( array_filter( $rows, function ( $r ) use ( $q, $digits ) {
				if ( false !== strpos( strtolower( $r['name'] . ' ' . $r['email'] ), $q ) ) {
					return true;
				}
				return strlen( $digits ) >= 3 && false !== strpos( preg_replace( '/\D/', '', $r['phone'] ), $digits );
			} ) );
		}
		$cols = fika_customer_sort_columns();
		if ( isset( $cols[ $orderby ] ) ) {
			$f = $cols[ $orderby ];
			usort( $rows, function ( $a, $b ) use ( $f ) {
				$x = $a[ $f ];
				$y = $b[ $f ];
				return is_string( $x ) ? strcasecmp( $x, $y ) : ( $x <=> $y );
			} );
			if ( 'desc' === $order ) {
				$rows = array_reverse( $rows );
			}
		} else {
			usort( $rows, function ( $a, $b ) {
				return max( $b['joined'], $b['last'] ) <=> max( $a['joined'], $a['last'] );
			} );
		}
		return array( $tab, $rows );
	}
}

if ( ! function_exists( 'fika_customer_sort_columns' ) ) {
	// column key => row field
	function fika_customer_sort_columns() {
		return array( 'name' => 'name', 'email' => 'email', 'phone' => 'phone', 'type' => 'type', 'joined' => 'joined', 'orders' => 'orders', 'done' => 'done', 'reach' => 'reach', 'spent' => 'spent', 'last' => 'last' );
	}
}

if ( ! function_exists( 'fika_orders_admin_url' ) ) {
	function fika_orders_admin_url( $args ) {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return $hpos ? add_query_arg( $args, admin_url( 'admin.php?page=wc-orders' ) ) : add_query_arg( array_merge( array( 'post_type' => 'shop_order' ), $args ), admin_url( 'edit.php' ) );
	}
}

if ( ! function_exists( 'fika_customers_page_html' ) ) {
	function fika_customers_page_html( $tab, $search = '', $orderby = '', $order = '' ) {
		$tabs  = fika_customer_tabs();
		$all   = fika_customer_rows();
		$order = 'desc' === $order ? 'desc' : 'asc';
		list( $tab, $rows ) = fika_customer_view( $tab, $search, $orderby, $order );
		$base  = admin_url( 'admin.php?page=fika-customers' );
		$state = array_filter( array( 'tab' => $tab, 's' => $search, 'orderby' => $orderby, 'order' => $orderby ? $order : '' ) );
		// a sortable column header (WordPress list-table style, with the arrow)
		$th = function ( $key, $label, $width ) use ( $base, $state, $orderby, $order ) {
			$is   = $orderby === $key;
			$next = $is && 'asc' === $order ? 'desc' : 'asc';
			$url  = add_query_arg( array_merge( $state, array( 'orderby' => $key, 'order' => $next ) ), $base );
			$cls  = $is ? 'sorted ' . $order : 'sortable ' . ( 'asc' === $next ? 'desc' : 'asc' );
			return '<th scope="col" class="manage-column ' . esc_attr( $cls ) . '" style="width:' . esc_attr( $width ) . '"' . ( $is ? ' aria-sort="' . ( 'asc' === $order ? 'ascending' : 'descending' ) . '"' : '' ) . '><a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>';
		};
		$date = function ( $t ) {
			return $t ? esc_html( wp_date( 'j M Y', $t ) ) : '&mdash;';
		};
		ob_start();
		?>
<div class="wrap fika-customers">
	<h1 class="wp-heading-inline">Fika customers</h1>
	<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( $state, array( 'action' => 'fika_customers_csv' ) ), admin_url( 'admin-post.php' ) ), 'fika_customers_csv' ) ); ?>">Download CSV</a>
	<hr class="wp-header-end">
	<p class="description">Everyone with a Fika account, and everyone who ordered as a guest. Orders count when Processing, On hold or Completed; "Delivered" counts Completed orders only. Rewards: the kg that count in Fika Club (delivered + on the way) and the next gift; <span class="fk-c fk-c-av">green</span> codes still available, <span class="fk-c fk-c-ready">pink</span> gifts reached but not claimed yet, <span class="fk-c fk-c-way">blue</span> codes on an order on its way, <span class="fk-c fk-c-used">grey</span> codes used on a delivered order, <span class="fk-c fk-c-taste">yellow</span> the mystery taste. Guests earn no rewards; their kg and codes are shown for reference.</p>
	<ul class="subsubsub">
		<?php
		$i = 0;
		foreach ( $tabs as $k => $t ) :
			$n = count( array_filter( $all, $t[1] ) );
			?>
			<li><?php echo $i++ ? ' | ' : ''; ?><a href="<?php echo esc_url( add_query_arg( array_merge( $state, array( 'tab' => $k ) ), $base ) ); ?>"<?php echo $k === $tab ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $t[0] ); ?> <span class="count">(<?php echo (int) $n; ?>)</span></a></li>
		<?php endforeach; ?>
	</ul>
	<form method="get" class="search-form fika-search">
		<input type="hidden" name="page" value="fika-customers">
		<?php foreach ( array( 'tab', 'orderby', 'order' ) as $k ) : ?>
			<?php if ( ! empty( $state[ $k ] ) ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $state[ $k ] ); ?>"><?php endif; ?>
		<?php endforeach; ?>
		<p class="search-box">
			<label class="screen-reader-text" for="fika-customer-search">Search customers</label>
			<input type="search" id="fika-customer-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Name, email or phone">
			<input type="submit" class="button" value="Search customers">
			<?php if ( '' !== $search ) : ?><a class="button-link fika-clear" href="<?php echo esc_url( add_query_arg( array_diff_key( $state, array( 's' => 1 ) ), $base ) ); ?>">Clear</a><?php endif; ?>
		</p>
	</form>
	<?php if ( '' !== $search ) : ?>
		<p class="fika-results"><?php echo (int) count( $rows ); ?> result<?php echo 1 === count( $rows ) ? '' : 's'; ?> for &ldquo;<?php echo esc_html( $search ); ?>&rdquo;</p>
	<?php endif; ?>
	<table class="wp-list-table widefat fixed striped">
		<thead><tr>
			<?php
			echo $th( 'name', 'Name', '12%' ) . $th( 'email', 'Email', '16%' ) . $th( 'phone', 'Phone', '9%' ) . $th( 'type', 'Type', '6%' ) . $th( 'joined', 'Signed up', '7%' ) . // phpcs:ignore
				$th( 'orders', 'Orders', '5%' ) . $th( 'done', 'Delivered', '6%' ) . $th( 'reach', 'Rewards', '25%' ) . $th( 'spent', 'Spent', '6%' ) . $th( 'last', 'Last order', '8%' ); // phpcs:ignore
			?>
		</tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="10"><?php echo '' !== $search ? 'No customers match your search.' : 'Nobody here yet.'; ?></td></tr>
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
				<td><?php echo fika_customer_rewards_cell( $r ); // phpcs:ignore -- escaped inside ?></td>
				<td><?php echo wp_kses_post( wc_price( $r['spent'] ) ); ?></td>
				<td><?php echo $date( $r['last'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<style>
	.fika-customers .subsubsub { margin: 8px 0 10px; }
	.fika-customers .fika-search .search-box { margin: 4px 0 10px; display: flex; gap: 6px; align-items: center; }
	.fika-customers .fika-search input[type=search] { min-width: 240px; }
	.fika-customers .fika-results { clear: both; margin: 0 0 8px; color: #50575e; }
	.fika-customers table { clear: both; }
	.fika-customers .fika-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
	.fika-customers .fika-badge-acc { background: #dcebff; color: #004aad; }
	.fika-customers .fika-badge-guest { background: #f3f0f0; color: #50575e; }
	.fika-customers .fk-rw small { color: #646970; }
	.fika-customers .fk-cs { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
	.fika-customers .fk-c { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; line-height: 1.5; white-space: nowrap; }
	.fika-customers .fk-c-av { background: #e7f6ea; color: #1e6b35; }
	.fika-customers .fk-c-ready { background: #fde7f0; color: #a3245a; }
	.fika-customers .fk-c-way { background: #e5f0fb; color: #1d5a99; }
	.fika-customers .fk-c-used { background: #f0f0f1; color: #646970; text-decoration: line-through; text-decoration-color: rgba(100,105,112,.5); }
	.fika-customers .fk-c-taste { background: #fff6e0; color: #8a5a00; }
	</style>
</div>
		<?php
		return ob_get_clean();
	}
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Fika customers', 'Fika customers', 'manage_woocommerce', 'fika-customers', function () {
		$g = function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security
		};
		echo fika_customers_page_html( sanitize_key( $g( 'tab' ) ), $g( 's' ), sanitize_key( $g( 'orderby' ) ), sanitize_key( $g( 'order' ) ) ); // phpcs:ignore WordPress.Security
	} );
}, 60 );

// This list replaces WooCommerce > Customers, so that menu item is hidden (delete this block to bring it back)
add_action( 'admin_menu', function () {
	remove_submenu_page( 'woocommerce', 'wc-admin&path=/customers' );
}, 999 );

// "Download CSV": exactly what is on screen (tab, search, sort)
add_action( 'admin_post_fika_customers_csv', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_customers_csv' );
	$g = function ( $k ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
	};
	list( $tab, $rows ) = fika_customer_view( sanitize_key( $g( 'tab' ) ), $g( 's' ), sanitize_key( $g( 'orderby' ) ), 'desc' === $g( 'order' ) ? 'desc' : 'asc' );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=fika-customers-' . $tab . '-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // so Excel reads the accents correctly
	fputcsv( $out, array( 'Name', 'Email', 'Phone', 'Type', 'Signed up', 'Orders', 'Delivered', 'Kg delivered', 'Kg in Fika Club (delivered + on the way)', 'Next gift', 'Codes available', 'Codes used', 'Spent (USD)', 'Last order' ) );
	foreach ( $rows as $r ) {
		fputcsv( $out, array(
			$r['name'], $r['email'], $r['phone'], $r['type'],
			$r['joined'] ? wp_date( 'Y-m-d', $r['joined'] ) : '',
			$r['orders'], $r['done'], round( $r['grams'] / 1000, 1 ), round( $r['reach'] / 1000, 1 ), $r['user_id'] ? $r['next'] : 'guest, not in Fika Club',
			fika_customer_rewards_text( $r, 'avail' ), fika_customer_rewards_text( $r, 'used' ), number_format( $r['spent'], 2, '.', '' ),
			$r['last'] ? wp_date( 'Y-m-d', $r['last'] ) : '',
		) );
	}
	fclose( $out );
	exit;
} );
