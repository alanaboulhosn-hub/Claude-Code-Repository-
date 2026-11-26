<?php
/**
 * Fika: KPIs page (WP Admin > Fika dashboard > KPIs). Owner, 16 Nov 2026: AOV without delivery, LTV, new customers,
 * retention, with filters.
 * - Orders counted: Processing, On hold, Completed, from the first old-store order (Aug 2026) on. Left out: test
 *   orders (note "TEST ORDER" or an @example.com email), cancelled, refused, failed, refunded.
 * - A customer is one phone number (Lebanese numbers compared without 961 / leading 0), or the email when there is no
 *   phone, so guest orders and account orders of one person count together.
 * - AOV = sweets per order after discounts, without delivery. LTV = sweets bought per customer so far (and the gross
 *   profit on them at the landing cost per kg set on the Fika dashboard). New customers = first order in that month /
 *   week. Retention: customers with 2 or more orders, came back within 30 / 60 / 90 days of the first order (counting
 *   only customers whose first order is at least that old), and a month-by-month table per first-order month.
 * - Filters (apply to every figure): delivery area, account or guest, how they found us (first order's source),
 *   phone or computer, first order size, first-order months from / to. The site does not ask for age or gender, so
 *   those cannot be shown.
 * - Ads (owner, 26 Nov 2026): weekly ad spend typed in on this page (option fika_kpi_ad_spend, week => $; weeks are
 *   Monday to Sunday, Beirut time). From it: aMER = first orders' sweets (no delivery) of new customers that week /
 *   ad spend; CAC = ad spend / new customers; LTGP:CAC = lifetime gross profit per customer (sweets - landing cost of
 *   the grams, customers whose first order is 60+ days old, so the figure has had time to grow) / CAC. These three use
 *   every customer (the filters above do not apply: spend cannot be split by group).
 * - Figures are worked out from the orders and kept for 10 minutes ("Refresh" recounts). Only the ad spend table saves
 *   anything (staff only); nothing else is changed.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-kpis.php
 */

if ( ! function_exists( 'fika_kpi_rows' ) ) {
	// one row per counted order, plain data (cached)
	function fika_kpi_rows( $fresh = false ) {
		$rows = $fresh ? false : get_transient( 'fika_kpi_rows' );
		if ( is_array( $rows ) ) {
			return $rows;
		}
		$accounts = array();
		foreach ( get_users( array( 'fields' => array( 'ID', 'user_email' ) ) ) as $u ) {
			$accounts[ strtolower( $u->user_email ) ] = (int) $u->ID;
		}
		$rows = array();
		foreach ( wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order', 'status' => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ), 'orderby' => 'date', 'order' => 'ASC' ) ) as $o ) {
			$email = strtolower( (string) $o->get_billing_email() );
			if ( false !== stripos( (string) $o->get_customer_note(), 'TEST ORDER' ) || preg_match( '/@example\.com$/', $email ) ) {
				continue;
			}
			$grams = 0;
			foreach ( $o->get_items() as $it ) {
				$grams += $it->get_meta( '_fika_taste' ) ? 50 : (int) $it->get_quantity() * ( has_term( 'ready-mix', 'product_cat', $it->get_product_id() ) ? 500 : 100 );
			}
			$rows[] = array(
				'ts'    => $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0,
				'phone' => (string) $o->get_billing_phone(),
				'email' => $email,
				'uid'   => (int) $o->get_customer_id() ?: ( $accounts[ $email ] ?? 0 ),
				'sw'    => round( (float) $o->get_total() - (float) $o->get_shipping_total() - (float) $o->get_shipping_tax(), 2 ),
				'g'     => $grams,
				'area'  => (string) $o->get_shipping_state(),
				'old'   => $o->get_meta( '_fika_old_order' ) ? 1 : 0,
				'src'   => (string) ( $o->get_meta( '_wc_order_attribution_utm_source' ) ?: $o->get_meta( '_wc_order_attribution_source_type' ) ),
				'dev'   => (string) $o->get_meta( '_wc_order_attribution_device_type' ),
			);
		}
		set_transient( 'fika_kpi_rows', $rows, 10 * MINUTE_IN_SECONDS );
		return $rows;
	}

	function fika_kpi_customer_key( $r ) {
		$d = preg_replace( '/\D/', '', $r['phone'] );
		if ( 0 === strpos( $d, '961' ) ) {
			$d = substr( $d, 3 );
		}
		$d = ltrim( $d, '0' );
		return strlen( $d ) >= 7 ? 'p' . $d : 'e' . $r['email'];
	}

	function fika_kpi_source( $r ) {
		if ( $r['old'] ) {
			return 'Old shop (before 9 Oct)';
		}
		$s = strtolower( $r['src'] );
		if ( preg_match( '/^(ig|instagram)/', $s ) ) {
			return 'Instagram';
		}
		if ( preg_match( '/^(fb|facebook|m\.facebook)/', $s ) ) {
			return 'Facebook';
		}
		if ( false !== strpos( $s, 'google' ) ) {
			return 'Google';
		}
		if ( in_array( $s, array( 'fika', 'email', 'mail' ), true ) ) {
			return 'Fika emails';
		}
		if ( in_array( $s, array( '(direct)', 'typein', '' ), true ) ) {
			return 'Direct / typed in';
		}
		return 'Other';
	}

	function fika_kpi_month_add( $ym, $n ) {
		list( $y, $m ) = array_map( 'intval', explode( '-', $ym ) );
		$m += $n;
		$y += intdiv( $m - 1, 12 );
		$m  = ( $m - 1 ) % 12 + 1;
		return sprintf( '%04d-%02d', $y, $m );
	}

	// all figures for the customers that match the filters; $tz turns timestamps into Beirut dates
	function fika_kpi_compute( $rows, $f, $now, $land, $tz ) {
		$ym  = function ( $ts ) use ( $tz ) { return $tz( 'Y-m', $ts ); };
		$cus = array();
		foreach ( $rows as $r ) {
			$k = fika_kpi_customer_key( $r );
			if ( ! isset( $cus[ $k ] ) ) {
				$cus[ $k ] = array( 'orders' => array(), 'acct' => false );
			}
			$cus[ $k ]['orders'][] = $r;
			if ( $r['uid'] ) {
				$cus[ $k ]['acct'] = true;
			}
		}
		$size = function ( $sw ) { return $sw < 20 ? 'Under $20' : ( $sw < 30 ? '$20 to $29' : '$30 and more' ); };
		foreach ( $cus as $k => &$c ) {
			usort( $c['orders'], function ( $a, $b ) { return $a['ts'] <=> $b['ts']; } );
			$f0          = $c['orders'][0];
			$c['first']  = $f0['ts'];
			$c['cohort'] = $ym( $f0['ts'] );
			$c['seg']    = array(
				'Delivery area'     => 'BA' === $f0['area'] ? 'Inside Beirut' : ( $f0['area'] ? 'Outside Beirut' : 'Unknown' ),
				'Account'           => $c['acct'] ? 'Has an account' : 'Guest only',
				'Found us via'      => fika_kpi_source( $f0 ),
				'Device'            => $f0['old'] ? 'Unknown (old shop)' : ( 'Mobile' === $f0['dev'] ? 'Phone' : ( 'Desktop' === $f0['dev'] ? 'Computer' : ( $f0['dev'] ? $f0['dev'] : 'Unknown' ) ) ),
				'First order size'  => $size( $f0['sw'] ),
			);
			$c['sw'] = array_sum( array_column( $c['orders'], 'sw' ) );
			$c['g']  = array_sum( array_column( $c['orders'], 'g' ) );
		}
		unset( $c );
		// filters
		$map = array( 'area' => 'Delivery area', 'acct' => 'Account', 'src' => 'Found us via', 'dev' => 'Device', 'size' => 'First order size' );
		$cus = array_filter( $cus, function ( $c ) use ( $f, $map ) {
			foreach ( $map as $p => $dim ) {
				if ( ! empty( $f[ $p ] ) && $c['seg'][ $dim ] !== $f[ $p ] ) {
					return false;
				}
			}
			if ( ! empty( $f['from'] ) && $c['cohort'] < $f['from'] ) {
				return false;
			}
			if ( ! empty( $f['to'] ) && $c['cohort'] > $f['to'] ) {
				return false;
			}
			return true;
		} );
		$out = array( 'customers' => count( $cus ), 'orders' => 0, 'sw' => 0.0, 'g' => 0, 'repeat' => 0, 'months' => array(), 'cohorts' => array(), 'weeks' => array(), 'within' => array(), 'segs' => array(), 'counts' => array( '1' => 0, '2' => 0, '3' => 0, '4+' => 0 ), 'last30' => array( 'n' => 0, 'sw' => 0.0, 'new' => 0 ), 'prev30' => array( 'n' => 0, 'sw' => 0.0, 'new' => 0 ) );
		$cur = $ym( $now );
		foreach ( $cus as $c ) {
			$n               = count( $c['orders'] );
			$out['orders']  += $n;
			$out['sw']      += $c['sw'];
			$out['g']       += $c['g'];
			$out['repeat']  += $n > 1 ? 1 : 0;
			$out['counts'][ $n >= 4 ? '4+' : (string) $n ]++;
			// months: orders, sweets, new and returning customers
			$seen = array();
			foreach ( $c['orders'] as $i => $o ) {
				$m = $ym( $o['ts'] );
				if ( ! isset( $out['months'][ $m ] ) ) {
					$out['months'][ $m ] = array( 'orders' => 0, 'sw' => 0.0, 'new' => 0, 'ret' => 0 );
				}
				$out['months'][ $m ]['orders']++;
				$out['months'][ $m ]['sw'] += $o['sw'];
				if ( ! isset( $seen[ $m ] ) ) {
					$out['months'][ $m ][ 0 === $i ? 'new' : 'ret' ]++;
					$seen[ $m ] = 1;
				}
				foreach ( array( 'last30' => array( $now - 30 * DAY_IN_SECONDS, $now ), 'prev30' => array( $now - 60 * DAY_IN_SECONDS, $now - 30 * DAY_IN_SECONDS ) ) as $pk => $pr ) {
					if ( $o['ts'] > $pr[0] && $o['ts'] <= $pr[1] ) {
						$out[ $pk ]['n']++;
						$out[ $pk ]['sw'] += $o['sw'];
						$out[ $pk ]['new'] += 0 === $i ? 1 : 0;
					}
				}
			}
			// weeks of first orders (last 12 weeks)
			$wk = (int) floor( ( $now - $c['first'] ) / WEEK_IN_SECONDS );
			if ( $wk < 12 ) {
				$out['weeks'][ $wk ] = ( $out['weeks'][ $wk ] ?? 0 ) + 1;
			}
			// cohort table: share of the cohort that ordered in month +1, +2 ...
			$co = &$out['cohorts'][ $c['cohort'] ];
			$co = $co ?? array( 'n' => 0, 'repeat' => 0, 'sw' => 0.0, 'orders' => 0, 'm' => array() );
			$co['n']++;
			$co['repeat'] += $n > 1 ? 1 : 0;
			$co['sw']     += $c['sw'];
			$co['orders'] += $n;
			$mo            = array();
			foreach ( array_slice( $c['orders'], 1 ) as $o ) {
				$m = $ym( $o['ts'] );
				for ( $j = 0; $j <= 12; $j++ ) {
					if ( fika_kpi_month_add( $c['cohort'], $j ) === $m ) {
						$mo[ $j ] = 1;
						break;
					}
				}
			}
			foreach ( $mo as $j => $one ) {
				$co['m'][ $j ] = ( $co['m'][ $j ] ?? 0 ) + 1;
			}
			unset( $co );
			// came back within N days (only customers whose first order is at least N days old)
			foreach ( array( 30, 60, 90 ) as $d ) {
				if ( $now - $c['first'] < $d * DAY_IN_SECONDS ) {
					continue;
				}
				$out['within'][ $d ]['n'] = ( $out['within'][ $d ]['n'] ?? 0 ) + 1;
				$back                     = $n > 1 && $c['orders'][1]['ts'] - $c['first'] <= $d * DAY_IN_SECONDS;
				$out['within'][ $d ]['b'] = ( $out['within'][ $d ]['b'] ?? 0 ) + ( $back ? 1 : 0 );
			}
			// segments
			foreach ( $c['seg'] as $dim => $v ) {
				$s = &$out['segs'][ $dim ][ $v ];
				$s = $s ?? array( 'n' => 0, 'repeat' => 0, 'sw' => 0.0, 'orders' => 0, 'g' => 0, 'old60' => 0, 'back60' => 0 );
				$s['n']++;
				$s['repeat'] += $n > 1 ? 1 : 0;
				$s['sw']     += $c['sw'];
				$s['g']      += $c['g'];
				$s['orders'] += $n;
				if ( $now - $c['first'] >= 60 * DAY_IN_SECONDS ) {
					$s['old60']++;
					$s['back60'] += ( $n > 1 && $c['orders'][1]['ts'] - $c['first'] <= 60 * DAY_IN_SECONDS ) ? 1 : 0;
				}
				unset( $s );
			}
		}
		ksort( $out['months'] );
		ksort( $out['cohorts'] );
		foreach ( $out['segs'] as &$dim ) {
			uasort( $dim, function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
		}
		unset( $dim );
		$out['cur']  = $cur;
		$out['land'] = $land;
		return $out;
	}

	// what the filter menus offer (from all customers, before filtering)
	// ads: aMER, CAC and LTGP:CAC from the weekly ad spend (every customer, no filters)
	function fika_kpi_acq( $rows, $spend, $land, $now ) {
		$wk  = function ( $ts ) { return wp_date( 'o-\\WW', $ts ); };
		$cus = array();
		foreach ( $rows as $r ) {
			$cus[ fika_kpi_customer_key( $r ) ][] = $r;
		}
		$weeks = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$weeks[ $wk( $now - $i * WEEK_IN_SECONDS ) ] = array( 'spend' => 0.0, 'new' => 0, 'rev' => 0.0, 'set' => false );
		}
		foreach ( $spend as $w => $v ) {
			if ( isset( $weeks[ $w ] ) ) {
				$weeks[ $w ]['spend'] = (float) $v;
				$weeks[ $w ]['set']   = true;
			}
		}
		$gp = 0.0; $n60 = 0;
		foreach ( $cus as $orders ) {
			usort( $orders, function ( $a, $b ) { return $a['ts'] <=> $b['ts']; } );
			$f0 = $orders[0]; $w = $wk( $f0['ts'] );
			if ( isset( $weeks[ $w ] ) ) {
				$weeks[ $w ]['new']++;
				$weeks[ $w ]['rev'] += $f0['sw'];
			}
			if ( $now - $f0['ts'] >= 60 * DAY_IN_SECONDS ) {
				$n60++;
				$gp += array_sum( array_column( $orders, 'sw' ) ) - array_sum( array_column( $orders, 'g' ) ) / 1000 * $land;
			}
		}
		$sp = 0.0; $nw = 0; $rv = 0.0;
		foreach ( $weeks as $w ) {
			if ( $w['set'] && $w['spend'] > 0 ) {
				$sp += $w['spend']; $nw += $w['new']; $rv += $w['rev'];
			}
		}
		$cac  = $nw ? $sp / $nw : 0;
		$ltgp = $n60 ? $gp / $n60 : 0;
		return array( 'weeks' => $weeks, 'spend' => $sp, 'new' => $nw, 'rev' => $rv, 'amer' => $sp > 0 ? $rv / $sp : 0, 'cac' => $cac, 'ltgp' => $ltgp, 'n60' => $n60, 'ratio' => $cac > 0 ? $ltgp / $cac : 0 );
	}

	function fika_kpi_options( $rows, $tz ) {
		$o = array( 'src' => array(), 'months' => array() );
		foreach ( $rows as $r ) {
			$o['src'][ fika_kpi_source( $r ) ] = 1;
			$o['months'][ $tz( 'Y-m', $r['ts'] ) ] = 1;
		}
		ksort( $o['src'] );
		ksort( $o['months'] );
		return $o;
	}

	function fika_kpi_render( $k, $f, $opts, $base, $at, $acq = null ) {
		$pct   = function ( $a, $b ) { return $b ? round( 100 * $a / $b ) . '%' : '–'; };
		$money = function ( $x ) { return '$' . number_format( (float) $x, 2 ); };
		$mname = function ( $ym ) { $t = strtotime( $ym . '-01' ); return gmdate( 'M Y', $t ); };
		$aov   = $k['orders'] ? $k['sw'] / $k['orders'] : 0;
		$ltv   = $k['customers'] ? $k['sw'] / $k['customers'] : 0;
		$gp    = $k['customers'] ? ( $k['sw'] - $k['g'] / 1000 * $k['land'] ) / $k['customers'] : 0;
		$a30   = $k['last30']['n'] ? $k['last30']['sw'] / $k['last30']['n'] : 0;
		$a60   = $k['prev30']['n'] ? $k['prev30']['sw'] / $k['prev30']['n'] : 0;
		$delta = function ( $now, $before ) {
			if ( ! $before ) {
				return '';
			}
			$ch = round( 100 * ( $now - $before ) / $before );
			return '<span class="' . ( $ch >= 0 ? 'up' : 'down' ) . '">' . ( $ch >= 0 ? '+' : '' ) . $ch . '%</span> vs the 30 days before';
		};
		$sel = function ( $name, $values, $label ) use ( $f ) {
			$h = '<label>' . esc_html( $label ) . '<select name="' . esc_attr( $name ) . '"><option value="">All</option>';
			foreach ( $values as $v => $t ) {
				$h .= '<option value="' . esc_attr( $v ) . '"' . ( (string) ( $f[ $name ] ?? '' ) === (string) $v ? ' selected' : '' ) . '>' . esc_html( $t ) . '</option>';
			}
			return $h . '</select></label>';
		};
		$same     = function ( $a ) { return array_combine( $a, $a ); };
		$months   = array();
		foreach ( array_keys( $opts['months'] ) as $m ) {
			$months[ $m ] = $mname( $m );
		}
		$filtered = count( array_filter( $f ) ) > 0;
		ob_start();
		?>
<style>
.fk { max-width: 1240px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1b2a4a; }
.fk h1 { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 26px; }
.fk h1 small { font-size: 13px; font-weight: 400; color: #6b7894; }
.fk h2 { margin: 0 0 4px; font-size: 16px; color: #004aad; }
.fk .sub { margin: 0 0 12px; color: #6b7894; font-size: 12.5px; }
.fk form.filters { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: flex-end; padding: 14px 16px; margin: 6px 0 18px; border-radius: 14px; background: #fff; border: 1px solid #e3e9f5; }
.fk form.filters label { display: flex; flex-direction: column; gap: 4px; font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: #6b7894; }
.fk form.filters select { min-width: 150px; text-transform: none; letter-spacing: 0; }
.fk form.filters .button-primary { margin-left: 4px; }
.fk .grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 16px; }
.fk .box { grid-column: span 6; box-sizing: border-box; padding: 18px 20px; border-radius: 14px; background: #fff; border: 1px solid #e3e9f5; overflow-x: auto; }
.fk .box.w12 { grid-column: span 12; } .fk .box.w4 { grid-column: span 4; } .fk .box.w8 { grid-column: span 8; }
.fk .kpis { grid-column: span 12; display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
.fk .kpi { padding: 14px 16px; border-radius: 14px; background: #fff; border: 1px solid #e3e9f5; }
.fk .kpi span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #6b7894; }
.fk .kpi b { display: block; margin: 4px 0 2px; font-size: 24px; color: #004aad; }
.fk .kpi em { font-style: normal; font-size: 12px; color: #6b7894; }
.fk .up { color: #1e8a4c; font-weight: 600; } .fk .down { color: #b42318; font-weight: 600; }
.fk table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.fk th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: #6b7894; font-weight: 600; padding: 6px 8px; border-bottom: 1px solid #e3e9f5; white-space: nowrap; }
.fk td { padding: 7px 8px; border-bottom: 1px solid #f1f4fa; white-space: nowrap; }
.fk td.n, .fk th.n { text-align: right; font-variant-numeric: tabular-nums; }
.fk td.heat { text-align: center; font-variant-numeric: tabular-nums; border-radius: 6px; }
.fk tr.dim td { background: #f6f8fc; font-weight: 600; color: #004aad; }
.fk .bars { display: flex; align-items: flex-end; gap: 6px; height: 140px; border-bottom: 1px solid #e3e9f5; }
.fk .bars div { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; font-size: 11px; color: #1b2a4a; }
.fk .bars i { display: block; width: 100%; border-radius: 4px 4px 0 0; background: #4a8ae0; min-height: 2px; }
.fk .axis { display: flex; gap: 6px; font-size: 10.5px; color: #6b7894; margin-top: 4px; } .fk .axis span { flex: 1; text-align: center; }
.fk .muted { color: #6b7894; font-size: 12.5px; } .fk ul.muted { margin: 6px 0 0 18px; list-style: disc; }
@media (max-width: 1100px) { .fk .box, .fk .box.w4, .fk .box.w8 { grid-column: span 12; } }
</style>
<div class="wrap fk">
	<h1>KPIs <small>All orders since the first order (Aug 2026) &middot; updated <?php echo esc_html( human_time_diff( $at ) ); ?> ago &middot; <a href="<?php echo esc_url( add_query_arg( array_merge( array_filter( $f ), array( 'refresh' => 1 ) ), $base ) ); ?>">Refresh</a></small></h1>
	<form class="filters" method="get">
		<input type="hidden" name="page" value="fika-kpis">
		<?php
		echo $sel( 'area', $same( array( 'Inside Beirut', 'Outside Beirut' ) ), 'Delivery area' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $sel( 'acct', $same( array( 'Has an account', 'Guest only' ) ), 'Account' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $sel( 'src', $same( array_keys( $opts['src'] ) ), 'Found us via' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $sel( 'dev', $same( array( 'Phone', 'Computer', 'Unknown (old shop)' ) ), 'Device' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $sel( 'size', $same( array( 'Under $20', '$20 to $29', '$30 and more' ) ), 'First order size' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $sel( 'from', $months, 'First order from' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $sel( 'to', $months, 'First order to' ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<button class="button button-primary">Show</button>
		<?php if ( $filtered ) : ?><a class="button" href="<?php echo esc_url( $base ); ?>">Clear filters</a><?php endif; ?>
	</form>
	<div class="grid">
		<div class="kpis">
			<div class="kpi"><span>AOV (no delivery)</span><b><?php echo esc_html( $money( $aov ) ); ?></b><em>last 30 days <?php echo esc_html( $money( $a30 ) ); ?> <?php echo wp_kses_post( $delta( $a30, $a60 ) ); ?></em></div>
			<div class="kpi"><span>LTV so far</span><b><?php echo esc_html( $money( $ltv ) ); ?></b><em>sweets per customer &middot; <?php echo esc_html( $money( $gp ) ); ?> gross profit at <?php echo esc_html( $money( $k['land'] ) ); ?>/kg</em></div>
			<div class="kpi"><span>Orders per customer</span><b><?php echo esc_html( $k['customers'] ? number_format( $k['orders'] / $k['customers'], 2 ) : '–' ); ?></b><em><?php echo (int) $k['orders']; ?> orders &middot; <?php echo (int) $k['customers']; ?> customers</em></div>
			<div class="kpi"><span>New customers, 30 days</span><b><?php echo (int) $k['last30']['new']; ?></b><em><?php echo wp_kses_post( $delta( $k['last30']['new'], $k['prev30']['new'] ) ?: ( (int) $k['prev30']['new'] . ' the 30 days before' ) ); ?></em></div>
			<div class="kpi"><span>Repeat rate</span><b><?php echo esc_html( $pct( $k['repeat'], $k['customers'] ) ); ?></b><em><?php echo (int) $k['repeat']; ?> customers with 2+ orders</em></div>
			<div class="kpi"><span>Back within 60 days</span><b><?php echo esc_html( $pct( $k['within'][60]['b'] ?? 0, $k['within'][60]['n'] ?? 0 ) ); ?></b><em>of <?php echo (int) ( $k['within'][60]['n'] ?? 0 ); ?> customers whose first order is 60+ days old</em></div>
			<?php if ( $acq ) : ?>
			<div class="kpi"><span>aMER (ads)</span><b><?php echo esc_html( $acq['spend'] > 0 ? number_format( $acq['amer'], 1 ) : '–' ); ?></b><em><?php echo $acq['spend'] > 0 ? esc_html( $money( $acq['rev'] ) . ' first orders / ' . $money( $acq['spend'] ) . ' ad spend' ) : 'type the weekly ad spend below'; ?></em></div>
			<div class="kpi"><span>CAC</span><b><?php echo esc_html( $acq['new'] && $acq['spend'] > 0 ? $money( $acq['cac'] ) : '–' ); ?></b><em><?php echo esc_html( $acq['spend'] > 0 ? $money( $acq['spend'] ) . ' / ' . (int) $acq['new'] . ' new customers' : 'ad spend per new customer' ); ?></em></div>
			<div class="kpi"><span>LTGP : CAC</span><b><?php echo esc_html( $acq['ratio'] > 0 ? number_format( $acq['ratio'], 1 ) . ' : 1' : '–' ); ?></b><em><?php echo esc_html( $money( $acq['ltgp'] ) . ' gross profit per customer (' . (int) $acq['n60'] . ' customers, 60+ days)' ); ?></em></div>
			<?php endif; ?>
		</div>

		<?php if ( $acq ) : ?>
		<div class="box w12">
			<h2>Ads: aMER, CAC and LTGP : CAC</h2>
			<p class="sub">Type what was spent on ads each week (Monday to Sunday, all platforms), then Save. aMER = new customers' first orders (sweets, no delivery) / ad spend. CAC = ad spend / new customers. LTGP = gross profit per customer over their life so far (sweets minus the landing cost of the grams, customers whose first order is 60+ days old); LTGP : CAC above 3 : 1 is healthy. Every customer counts here: the filters above do not apply.</p>
			<form method="post">
				<?php wp_nonce_field( 'fika_kpi_spend' ); ?>
				<table><tr><th>Week (Mon to Sun)</th><th class="n">Ad spend</th><th class="n">New customers</th><th class="n">Their first orders</th><th class="n">aMER</th><th class="n">CAC</th></tr>
				<?php foreach ( array_reverse( $acq['weeks'], true ) as $w => $v ) :
					$mon = strtotime( str_replace( '-W', 'W', $w ) . '1' ); ?>
					<tr><td><?php echo esc_html( wp_date( 'j M', $mon ) . ' to ' . wp_date( 'j M', $mon + 6 * DAY_IN_SECONDS ) ); ?></td>
					<td class="n">$ <input type="number" step="0.01" min="0" name="fika_ad_spend[<?php echo esc_attr( $w ); ?>]" value="<?php echo esc_attr( $v['set'] ? $v['spend'] : '' ); ?>" style="width:90px"></td>
					<td class="n"><?php echo (int) $v['new']; ?></td><td class="n"><?php echo esc_html( $money( $v['rev'] ) ); ?></td>
					<td class="n"><?php echo esc_html( $v['spend'] > 0 ? number_format( $v['rev'] / $v['spend'], 1 ) : '–' ); ?></td>
					<td class="n"><?php echo esc_html( $v['spend'] > 0 && $v['new'] ? $money( $v['spend'] / $v['new'] ) : '–' ); ?></td></tr>
				<?php endforeach; ?>
				</table>
				<p><button class="button button-primary">Save ad spend</button> <span class="muted">Not every new customer comes from the ads (posts, friends, the bio link): aMER and CAC are blended. Gross profit leaves out packaging, delivery costs and rewards.</span></p>
			</form>
		</div>
		<?php endif; ?>

		<div class="box w8">
			<h2>Month by month</h2>
			<p class="sub">AOV without delivery. New = first order that month; returning = customers who ordered that month after a first order in an earlier month.</p>
			<table><tr><th>Month</th><th class="n">Orders</th><th class="n">Sweets</th><th class="n">AOV</th><th class="n">New</th><th class="n">Returning</th><th class="n">Returning %</th></tr>
			<?php foreach ( array_reverse( $k['months'], true ) as $m => $v ) : ?>
				<tr><td><?php echo esc_html( $mname( $m ) . ( $m === $k['cur'] ? ' (so far)' : '' ) ); ?></td><td class="n"><?php echo (int) $v['orders']; ?></td><td class="n"><?php echo esc_html( $money( $v['sw'] ) ); ?></td><td class="n"><?php echo esc_html( $money( $v['orders'] ? $v['sw'] / $v['orders'] : 0 ) ); ?></td><td class="n"><?php echo (int) $v['new']; ?></td><td class="n"><?php echo (int) $v['ret']; ?></td><td class="n"><?php echo esc_html( $pct( $v['ret'], $v['new'] + $v['ret'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</table>
		</div>
		<div class="box w4">
			<h2>New customers per week</h2>
			<p class="sub">First orders, last 12 weeks (this week on the right).</p>
			<?php $mx = max( 1, max( array_merge( array( 0 ), $k['weeks'] ) ) ); ?>
			<div class="bars"><?php for ( $w = 11; $w >= 0; $w-- ) : $v = $k['weeks'][ $w ] ?? 0; ?><div><?php echo (int) $v; ?><i style="height:<?php echo (int) round( 100 * $v / $mx ); ?>%"></i></div><?php endfor; ?></div>
			<div class="axis"><?php for ( $w = 11; $w >= 0; $w-- ) : ?><span><?php echo 0 === $w ? 'now' : '-' . (int) $w; ?></span><?php endfor; ?></div>
			<h2 style="margin-top:18px">Came back within</h2>
			<table><tr><th>Window</th><th class="n">Came back</th><th class="n">Customers counted</th></tr>
			<?php foreach ( array( 30, 60, 90 ) as $d ) : ?>
				<tr><td><?php echo (int) $d; ?> days</td><td class="n"><?php echo esc_html( $pct( $k['within'][ $d ]['b'] ?? 0, $k['within'][ $d ]['n'] ?? 0 ) ); ?></td><td class="n"><?php echo (int) ( $k['within'][ $d ]['n'] ?? 0 ); ?></td></tr>
			<?php endforeach; ?>
			</table>
			<h2 style="margin-top:18px">Orders per customer</h2>
			<table><tr><th>Orders</th><th class="n">Customers</th><th class="n">Share</th></tr>
			<?php foreach ( $k['counts'] as $c => $v ) : ?><tr><td><?php echo esc_html( $c ); ?></td><td class="n"><?php echo (int) $v; ?></td><td class="n"><?php echo esc_html( $pct( $v, $k['customers'] ) ); ?></td></tr><?php endforeach; ?>
			</table>
		</div>

		<div class="box w12">
			<h2>Retention by first-order month</h2>
			<p class="sub">Each row is the customers whose first order was in that month; the columns show the share who ordered again 1, 2, 3 ... months later. The current month is not over yet.</p>
			<?php $maxj = 0; foreach ( $k['cohorts'] as $cm => $c ) { $maxj = max( $maxj, count( array_filter( range( 1, 12 ), function ( $j ) use ( $cm, $k ) { return fika_kpi_month_add( $cm, $j ) <= $k['cur']; } ) ) ); } ?>
			<table><tr><th>First order</th><th class="n">Customers</th><th class="n">LTV so far</th><th class="n">Orders each</th><th class="n">Ordered again</th><?php for ( $j = 1; $j <= $maxj; $j++ ) : ?><th class="n">Month +<?php echo (int) $j; ?></th><?php endfor; ?></tr>
			<?php foreach ( $k['cohorts'] as $cm => $c ) : ?>
				<tr><td><?php echo esc_html( $mname( $cm ) ); ?></td><td class="n"><?php echo (int) $c['n']; ?></td><td class="n"><?php echo esc_html( $money( $c['sw'] / $c['n'] ) ); ?></td><td class="n"><?php echo esc_html( number_format( $c['orders'] / $c['n'], 2 ) ); ?></td><td class="n"><?php echo esc_html( $pct( $c['repeat'], $c['n'] ) ); ?></td>
				<?php for ( $j = 1; $j <= $maxj; $j++ ) :
					$mm = fika_kpi_month_add( $cm, $j );
					if ( $mm > $k['cur'] ) {
						echo '<td></td>';
						continue;
					}
					$v = $c['m'][ $j ] ?? 0;
					$p = $c['n'] ? $v / $c['n'] : 0;
					?>
					<td class="heat" style="background:rgba(0,74,173,<?php echo esc_attr( number_format( min( 1, $p * 4 ) * 0.55, 2, '.', '' ) ); ?>);color:<?php echo $p * 4 * 0.55 > 0.35 ? '#fff' : '#1b2a4a'; ?>"><?php echo esc_html( $pct( $v, $c['n'] ) . ( $mm === $k['cur'] ? '*' : '' ) ); ?></td>
				<?php endfor; ?></tr>
			<?php endforeach; ?>
			</table>
			<p class="muted">* month not over yet.</p>
		</div>

		<div class="box w12">
			<h2>Retention and value by customer group</h2>
			<p class="sub">Groups follow each customer's first order. "Back within 60 days" counts only customers whose first order is at least 60 days old.</p>
			<table><tr><th>Group</th><th class="n">Customers</th><th class="n">Share</th><th class="n">Repeat rate</th><th class="n">Back within 60 days</th><th class="n">LTV so far</th><th class="n">AOV (no delivery)</th><th class="n">Orders each</th></tr>
			<?php foreach ( $k['segs'] as $dim => $vals ) : ?>
				<tr class="dim"><td colspan="8"><?php echo esc_html( $dim ); ?></td></tr>
				<?php foreach ( $vals as $v => $s ) : ?>
				<tr><td><?php echo esc_html( $v ); ?></td><td class="n"><?php echo (int) $s['n']; ?></td><td class="n"><?php echo esc_html( $pct( $s['n'], $k['customers'] ) ); ?></td><td class="n"><?php echo esc_html( $pct( $s['repeat'], $s['n'] ) ); ?></td><td class="n"><?php echo esc_html( $s['old60'] ? $pct( $s['back60'], $s['old60'] ) . ' of ' . $s['old60'] : '–' ); ?></td><td class="n"><?php echo esc_html( $money( $s['sw'] / $s['n'] ) ); ?></td><td class="n"><?php echo esc_html( $money( $s['orders'] ? $s['sw'] / $s['orders'] : 0 ) ); ?></td><td class="n"><?php echo esc_html( number_format( $s['orders'] / $s['n'], 2 ) ); ?></td></tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</table>
		</div>

		<div class="box w12">
			<h2>How these are counted</h2>
			<ul class="muted">
				<li>Orders: Processing, On hold and Completed, including the old shop's orders from August. Cancelled, refused, failed and test orders are left out.</li>
				<li>A customer is one phone number (or email when there is no phone), so guest and account orders of the same person count together.</li>
				<li>AOV: sweets per order after discounts, without delivery. LTV: everything a customer has bought so far, without delivery; gross profit uses the landing cost per kg from the Fika dashboard.</li>
				<li>"Has an account": the customer has a Fika account (any of their orders, or their email).</li>
				<li>Age and gender are not shown: the shop does not ask for them. Asking for a birthday at sign-up would add age groups (and a birthday treat).</li>
			</ul>
		</div>
	</div>
</div>
		<?php
		return ob_get_clean();
	}
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'fika-dashboard', 'KPIs', 'KPIs', 'manage_woocommerce', 'fika-kpis', function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- read-only filters
		$f = array();
		foreach ( array( 'area', 'acct', 'src', 'dev', 'size', 'from', 'to' ) as $p ) {
			$f[ $p ] = isset( $_GET[ $p ] ) ? sanitize_text_field( wp_unslash( $_GET[ $p ] ) ) : '';
		}
		$rows = fika_kpi_rows( ! empty( $_GET['refresh'] ) );
		// phpcs:enable
		$at   = get_option( '_transient_timeout_fika_kpi_rows' ) ? (int) get_option( '_transient_timeout_fika_kpi_rows' ) - 10 * MINUTE_IN_SECONDS : time();
		$tz   = function ( $fmt, $ts ) { return wp_date( $fmt, $ts ); };
		$land = (float) get_option( 'fika_landing_cost', 16 );
		// the weekly ad spend table (staff only, nonce checked)
		$spend = get_option( 'fika_kpi_ad_spend', array() );
		$spend = is_array( $spend ) ? $spend : array();
		if ( isset( $_POST['fika_ad_spend'], $_POST['_wpnonce'] ) && is_array( $_POST['fika_ad_spend'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fika_kpi_spend' ) ) {
			foreach ( wp_unslash( $_POST['fika_ad_spend'] ) as $w => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( ! preg_match( '/^\d{4}-W\d{2}$/', (string) $w ) ) {
					continue;
				}
				if ( '' === trim( (string) $v ) ) {
					unset( $spend[ $w ] );
				} else {
					$spend[ $w ] = max( 0, round( (float) $v, 2 ) );
				}
			}
			update_option( 'fika_kpi_ad_spend', $spend, false );
			echo '<div class="notice notice-success"><p>Ad spend saved.</p></div>';
		}
		$k    = fika_kpi_compute( $rows, $f, time(), $land, $tz );
		$acq  = fika_kpi_acq( $rows, $spend, $land, time() );
		echo fika_kpi_render( $k, $f, fika_kpi_options( $rows, $tz ), admin_url( 'admin.php?page=fika-kpis' ), $at, $acq ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	} );
}, 20 );
