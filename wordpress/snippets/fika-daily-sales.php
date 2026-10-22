<?php
/**
 * Fika: kilograms of each candy ordered per day, for the FIKA Google Sheet (Daily Sales tab, Inventory "Sold since
 * count"). The sheet's script (wordpress/google-sheet/fika-daily-sales.gs) asks for it every night.
 * - GET /wp-json/fika/v1/daily-sales?from=YYYY-MM-DD&to=YYYY-MM-DD with the header X-Fika-Key. Days are Beirut days.
 *   Answer: { rows: [ { date, candy, kg, orders } ] }, candy under its name on the sheet's Inventory tab. Only totals:
 *   no customer names, phones or emails.
 * - Counted as the shop dashboard does: orders processing, on hold or completed (not failed, cancelled, refunded or
 *   pending), "TEST ORDER" notes left out; 100 g per candy unit, the free mystery taste 50 g, refunded units taken off.
 * - Ready Mix bags (500 g) are split by FIKA_DS_MIXES once their contents are known; until then they come as their own
 *   line, "Ready Mix: <name>", which no Inventory row matches.
 * - The key: made on first use (option fika_ds_key). WP admins read it at GET /wp-json/fika/v1/daily-sales-key.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-daily-sales.php
 */

if ( ! function_exists( 'fika_ds_rows' ) ) {
	// shop product id => candy name on the sheet's Inventory tab (column A, exactly)
	function fika_ds_names() {
		return array(
			93  => 'AROMA GELEHALLON BIB',                     // Raspberry Bites
			124 => 'COOL COLA SKALLE 3,0KG',                   // BUBS Cola Skull
			119 => 'TUTTI FRUTTI DIAMOND 2,6KG',               // BUBS Tutti Frutti Diamond
			110 => 'BANANA TOFFEE OVALS 2,8 KG',               // BUBS Banana Toffee Ovals
			122 => 'LEMON RASPBERRY SKALLE 3,2KG',             // BUBS Lemon Raspberry Skull
			108 => 'WILD BERRY POMEGRANATE OVALS 2,8 KG',      // BUBS Wild Berry Pomegranate Oval
			123 => 'FRUITY PEAR OVALS MEDIUM 2,6KG',           // BUBS Fruity Pear Ovals
			91  => 'Mormors Löständer 3x1KG',                  // Loose Teeth
			109 => 'PERSIKOR KRT*AA',                          // Peaches
			101 => 'SOCKRADE ÄPPLEN LÖSVIKT 3 KG BIB Lösvikt', // Sugared Apples
			103 => 'Tutti Frutti Rings Lösvikt',               // Tutti Frutti Rings
			102 => 'Tutti Frutti Passion Lösvikt',             // Tutti Frutti Passion
			112 => 'Tutti Frutti Sour Lösvikt',                // Tutti Frutti Sour
			113 => 'SURA MELONNAPPAR 3KG',                     // Sour Watermelon Pacifier
			90  => 'Fizzy Cola Bottles 3,25 kg',               // Fizzy Cola
			99  => 'SUGAR STRAWBERRYS 3 KG LÖSVIKT',           // Sugared Strawberries
			115 => 'Syrlig Pastellfisk 3,2 kg',                // Sour Swedish Fish
			100 => 'Pastellfisk 3 kg BiB lösvikt',             // Swedish Fish
			106 => 'SKOGSBÄR',                                 // Forest Berries
			116 => 'SURA KÖRSBÄR 3kg',                         // Sour Cherries
			105 => 'RABARBERBITAR STORA (TIVOLIBITAR)',        // Rhubarb Bites
			104 => 'RED AMMO - 3 kg',                          // Red Ammo
			125 => 'FIZZYPOP BLUE 6 KG LÖSVIKT',               // Fizzy Blue
			114 => 'Sour Pineapple 3 kg lösvikt',              // Sour Pineapple
			111 => 'MINIDYNAMIT SUR JORDGUBB',                 // Sour Strawberries
			92  => 'Bubbliz Original',                         // Fizzy Pop
			118 => 'Fruit Lemon Mix skalle 190g',              // BUBS Fruity Lemon Mix Skulls (Mini)
			121 => 'Forest Berries Ovals 175g',                // BUBS Forest Berry Ovals (Mini)
			107 => 'Raspberry Salty Licorice Skalle 155g',     // BUBS Raspberry Salty Licorice Skulls
			120 => 'Bubblegum Skalle 2.6kg',                   // BUBS Bubblegum Skull
			117 => 'Tutti Frutti Syrlig Melon',                // Tutti Frutti Sour Melon
		);
	}
	// Ready Mix product id => [ candy product id => grams in one 500 g bag ]; empty until the owner sends the contents
	if ( ! defined( 'FIKA_DS_MIXES' ) ) {
		define( 'FIKA_DS_MIXES', array() );
	}
	function fika_ds_key() {
		$k = get_option( 'fika_ds_key' );
		if ( ! $k ) {
			$k = wp_generate_password( 40, false );
			add_option( 'fika_ds_key', $k, '', false );
			$k = get_option( 'fika_ds_key' );
		}
		return $k;
	}
	// rows for the Beirut days $from to $to (Y-m-d)
	function fika_ds_rows( $from, $to ) {
		$tz    = new DateTimeZone( 'Asia/Beirut' );
		$start = new DateTime( $from . ' 00:00:00', $tz );
		$end   = new DateTime( $to . ' 23:59:59', $tz );
		$names = fika_ds_names();
		$mixes = FIKA_DS_MIXES;
		$orders = wc_get_orders( array(
			'limit'        => -1,
			'type'         => 'shop_order',
			'status'       => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ),
			'date_created' => $start->getTimestamp() . '...' . $end->getTimestamp(),
		) );
		$kg = array(); // day => candy => [ grams, order ids ]
		foreach ( $orders as $o ) {
			if ( false !== stripos( (string) $o->get_customer_note(), 'TEST ORDER' ) ) {
				continue;
			}
			$day = $o->get_date_created()->setTimezone( $tz )->format( 'Y-m-d' );
			foreach ( $o->get_items() as $item_id => $item ) {
				$pid = $item->get_product_id();
				$qty = (int) $item->get_quantity() + (int) $o->get_qty_refunded_for_item( $item_id );
				if ( $qty <= 0 ) {
					continue;
				}
				$parts = array();
				if ( $item->get_meta( '_fika_taste' ) ) {
					$parts[ $pid ] = 50;
				} elseif ( has_term( 'ready-mix', 'product_cat', $pid ) ) {
					if ( ! empty( $mixes[ $pid ] ) ) {
						foreach ( $mixes[ $pid ] as $cid => $g ) {
							$parts[ $cid ] = $qty * $g;
						}
					} else {
						$parts[ 'mix' . $pid ] = $qty * 500;
					}
				} else {
					$parts[ $pid ] = $qty * 100;
				}
				foreach ( $parts as $cid => $g ) {
					if ( isset( $names[ $cid ] ) ) {
						$candy = $names[ $cid ];
					} elseif ( 0 === strpos( (string) $cid, 'mix' ) ) {
						$candy = 'Ready Mix: ' . html_entity_decode( get_the_title( (int) substr( $cid, 3 ) ) );
					} else {
						$candy = html_entity_decode( get_the_title( (int) $cid ) ) . ' (not on the sheet)';
					}
					$kg[ $day ][ $candy ]['g']                    = ( $kg[ $day ][ $candy ]['g'] ?? 0 ) + $g;
					$kg[ $day ][ $candy ]['o'][ $o->get_id() ] = true;
				}
			}
		}
		ksort( $kg );
		$rows = array();
		foreach ( $kg as $day => $candies ) {
			ksort( $candies );
			foreach ( $candies as $candy => $v ) {
				$rows[] = array( 'date' => $day, 'candy' => $candy, 'kg' => round( $v['g'] / 1000, 3 ), 'orders' => count( $v['o'] ) );
			}
		}
		return $rows;
	}
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/daily-sales', array(
		'methods'             => 'GET',
		'permission_callback' => function ( $req ) {
			$k = (string) $req->get_header( 'x-fika-key' );
			return '' !== $k && hash_equals( fika_ds_key(), $k );
		},
		'callback'            => function ( $req ) {
			$from = (string) $req->get_param( 'from' );
			$to   = (string) $req->get_param( 'to' );
			$ok   = '/^\d{4}-\d{2}-\d{2}$/';
			if ( ! preg_match( $ok, $from ) || ! preg_match( $ok, $to ) || $from > $to || ( strtotime( $to ) - strtotime( $from ) ) > 120 * DAY_IN_SECONDS ) {
				return new WP_Error( 'fika_ds_dates', 'from and to: YYYY-MM-DD, at most 120 days apart', array( 'status' => 400 ) );
			}
			nocache_headers();
			return array( 'from' => $from, 'to' => $to, 'rows' => fika_ds_rows( $from, $to ) );
		},
	) );
	register_rest_route( 'fika/v1', '/daily-sales-key', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () { return array( 'key' => fika_ds_key() ); },
	) );
} );
