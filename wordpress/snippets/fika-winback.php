<?php
/**
 * Fika: win-back emails for customers who have not ordered in a while.
 * - Email 1, 30 days after a customer's last order (or after they created an account, if they never ordered):
 *   "It's been a while": what's new (products added in the last 45 days, or the favourites), a news box written
 *   in WP Admin, and a "Pick your mix" button.
 * - Email 2, 14 days later if they still have not ordered: a personal discount code (default 10% off the candies,
 *   one use, only for their email, valid 14 days). Its button opens the shop and the code applies at checkout.
 * - Ordering again starts the cycle over. Every email has an unsubscribe link, and unsubscribed addresses never get
 *   these emails again. Shop staff accounts and test addresses (@example.com/.org/.net) are left out.
 * - Runs once a day (WooCommerce's scheduler, about 10:00 Beirut time), at most 50 emails per run.
 * - Mode: "Test" sends only to the addresses listed in WP Admin; "Live" sends to everyone due; "Off" sends nothing.
 * - WP Admin > WooCommerce > Win-back emails: settings, previews, who is due, what was sent, who came back.
 * Sent with WooCommerce's email layout (the same template as the order emails).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-winback.php
 */

if ( ! function_exists( 'fika_wb_settings' ) ) {
	function fika_wb_settings() {
		$s = get_option( 'fika_winback_settings', array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array(
			'mode'        => 'test',
			'test_emails' => 'alan.aboulhosn@gmail.com',
			'news_title'  => 'Lördagsmys is here',
			'news_text'   => 'Make Saturday the sweetest day of the week: let everyone pick a few favourites, pour them into one big bowl and gather the family around it.',
			'discount'    => 10,
			'valid_days'  => 14,
			'first_days'  => 30,
			'second_days' => 14,
			'products'    => '118,121,120,107', // email 1 candy grid: product IDs, in order; empty = automatic
		) );
	}
	function fika_wb_state() {
		$s = get_option( 'fika_winback_state', array() );
		return is_array( $s ) ? $s : array();
	}
	function fika_wb_log( $email, $what ) {
		$log = get_option( 'fika_winback_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 't' => time(), 'email' => $email, 'what' => $what ) );
		update_option( 'fika_winback_log', array_slice( $log, 0, 200 ), false );
	}
	function fika_wb_stopped( $email ) {
		$stop = get_option( 'fika_marketing_stop', array() );
		return is_array( $stop ) ? in_array( md5( strtolower( $email ) ), $stop, true ) : false;
	}
	function fika_wb_unsub_link( $email ) {
		$email = strtolower( $email );
		return add_query_arg( array( 'fika_unsub' => rawurlencode( $email ), 'k' => substr( wp_hash( 'fika-wb|' . $email ), 0, 20 ) ), home_url( '/' ) );
	}
	// everyone who could get these emails: email => name, last order time (0 = never), start of the quiet period
	function fika_wb_audience() {
		$people = array();
		$skip   = function ( $email ) {
			return ! is_email( $email ) || preg_match( '/@example\.(com|org|net)$/i', $email );
		};
		$orders = wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order', 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-undelivered' ), 'orderby' => 'date', 'order' => 'ASC' ) );
		foreach ( $orders as $o ) {
			$e = strtolower( $o->get_billing_email() );
			if ( $skip( $e ) || ! $o->get_date_created() ) {
				continue;
			}
			$people[ $e ] = array(
				// the person who ordered (billing), not a gift recipient (shipping)
				'name' => $o->get_billing_first_name() ? $o->get_billing_first_name() : $o->get_shipping_first_name(),
				'last' => $o->get_date_created()->getTimestamp(),
			);
		}
		foreach ( get_users( array( 'role__in' => array( 'customer', 'subscriber' ), 'fields' => array( 'ID', 'user_email', 'user_registered' ) ) ) as $u ) {
			$e = strtolower( $u->user_email );
			if ( $skip( $e ) || user_can( $u->ID, 'manage_woocommerce' ) ) {
				continue;
			}
			if ( ! isset( $people[ $e ] ) ) {
				$people[ $e ] = array( 'name' => get_user_meta( $u->ID, 'first_name', true ), 'last' => 0, 'joined' => strtotime( $u->user_registered . ' UTC' ) );
			}
		}
		foreach ( $people as $e => $p ) {
			// staff who also placed orders are left out
			$u = get_user_by( 'email', $e );
			if ( $u ? user_can( $u, 'manage_woocommerce' ) : false ) {
				unset( $people[ $e ] );
				continue;
			}
			$people[ $e ]['since'] = $p['last'] ? $p['last'] : ( isset( $p['joined'] ) ? $p['joined'] : time() );
		}
		return $people;
	}
	// what each person is due today: '1', '2' or ''
	function fika_wb_due( $email, $p, $st, $set ) {
		$s = isset( $st[ $email ] ) ? $st[ $email ] : array();
		if ( ! empty( $s ) ? (int) $s['base'] !== (int) $p['last'] : false ) {
			$s = array(); // ordered since: a new cycle
		}
		if ( empty( $s['s1'] ) ) {
			return ( time() - $p['since'] >= (int) $set['first_days'] * DAY_IN_SECONDS ) ? '1' : '';
		}
		if ( empty( $s['s2'] ) ) {
			return ( time() - (int) $s['s1'] >= (int) $set['second_days'] * DAY_IN_SECONDS ) ? '2' : '';
		}
		return '';
	}
	// candies to show: added in the last 45 days, else the favourites
	function fika_wb_products( &$is_new ) {
		$set  = fika_wb_settings();
		$pick = array_filter( array_map( 'absint', explode( ',', (string) $set['products'] ) ) );
		if ( $pick ) {
			$list = array();
			foreach ( $pick as $pid ) {
				$pr = wc_get_product( $pid );
				if ( $pr && 'publish' === $pr->get_status() && $pr->is_in_stock() ) {
					$list[] = $pr;
				}
			}
			if ( $list ) {
				$is_new = true;
				return array_slice( $list, 0, 4 );
			}
		}
		$args   = array( 'status' => 'publish', 'visibility' => 'catalog', 'stock_status' => 'instock', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' );
		$list   = wc_get_products( array_merge( $args, array( 'date_created' => '>' . ( time() - 45 * DAY_IN_SECONDS ) ) ) );
		$is_new = (bool) $list;
		if ( ! $list ) {
			$list = wc_get_products( array_merge( $args, array( 'featured' => true, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) );
		}
		return $list;
	}
	function fika_wb_grid( $list ) {
		$cells = array();
		foreach ( $list as $p ) {
			$img     = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
			$bag     = has_term( 'ready-mix', 'product_cat', $p->get_id() );
			$price   = wc_price( wc_get_price_to_display( $p ) ) . ( $bag ? ' per 500 g bag' : ' per 100 g' );
			$cells[] = '<td width="50%" valign="top" style="width:50%;padding:8px;text-align:center;">' .
				'<a href="' . esc_url( home_url( $bag ? '/ready-mix/' : '/mix-your-own/' ) ) . '" style="text-decoration:none;">' .
				( $img ? '<img src="' . esc_url( $img ) . '" width="200" alt="" style="display:block;width:100%;max-width:200px;height:auto;margin:0 auto 8px;border-radius:16px;">' : '' ) .
				'<span class="fika-pname" style="display:block;">' . esc_html( $p->get_name() ) . '</span></a>' .
				'<span style="display:block;color:#6c7b9c;font-size:13px;">' . wp_strip_all_tags( $price ) . '</span></td>';
		}
		$rows = '';
		foreach ( array_chunk( $cells, 2 ) as $pair ) {
			$rows .= '<tr>' . implode( '', $pair ) . ( 1 === count( $pair ) ? '<td width="50%"></td>' : '' ) . '</tr>';
		}
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:6px 0 4px;">' . $rows . '</table>';
	}
	function fika_wb_button( $href, $text ) {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr><td align="center" style="text-align:center;padding:26px 0 22px;">' .
			'<a class="fika-mail-btn" href="' . esc_url( $href ) . '" style="display:inline-block;padding:14px 32px;border-radius:999px;background:#004aad;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html( $text ) . '</a></td></tr></table>';
	}
	function fika_wb_small( $email ) {
		return '<p style="margin-top:20px !important;text-align:center;color:#6c7b9c !important;font-size:12.5px !important;">You get this email because you shop with Fika. ' .
			'<a href="' . esc_url( fika_wb_unsub_link( $email ) ) . '" style="color:#6c7b9c !important;">Unsubscribe</a>.</p>';
	}
	// headings drawn in the website's cute font (NF Le Petit Cochon), as pictures so every inbox shows them;
	// made by wordpress/email-assets/make-email-headings.js at 2x: [file, width, height] at 1x
	function fika_wb_head( $key, $alt, $h = 36 ) {
		$size = array( 'we-miss-you' => array( 548, 87 ), 'fresh-from-sweden' => array( 839, 108 ), 'made-for-sharing' => array( 763, 98 ), 'your-fika-club' => array( 650, 103 ), 'join-the-fika-club' => array( 776, 108 ) );
		$w    = (int) round( $size[ $key ][0] * $h / $size[ $key ][1] );
		return '<img src="' . esc_url( home_url( '/wp-content/uploads/2026/10/fika-email-h-' . $key . '.png' ) ) . '" width="' . $w . '" height="' . (int) $h . '" alt="' . esc_attr( $alt ) . '" style="display:block;width:' . $w . 'px;max-width:100%;height:auto;margin:0 auto;border:0;">';
	}
	// email 1, part 2: what fika means (family time, quality, the warmth of sharing)
	function fika_wb_divider( $id, $label ) {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:30px 0 6px;" id="' . esc_attr( $id ) . '"><tr>' .
			'<td style="border-top:2px dotted #f3b7cf;width:38%;"></td><td align="center" style="font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#d35d93;padding:0 10px;white-space:nowrap;"><span style="color:#d35d93;">' . esc_html( $label ) . '</span></td><td style="border-top:2px dotted #f3b7cf;width:38%;"></td></tr></table>';
	}
	function fika_wb_part2() {
		$f    = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$vals = array(
			array( 'Family time', 'Saturday evening, one big bowl, everyone picking their favourites. In Sweden it is called lördagsmys, and it is our favourite tradition.', '#fdeaf2' ),
			array( 'Real quality', 'Every sweet is hand-selected and imported from Sweden, with clean ingredients and gelatin-free options clearly marked.', '#eaf2ff' ),
			array( 'The warmth of sharing', 'Pick-and-mix is made to be passed around: a bag for the car, a bowl for the table, a little extra for a friend.', '#fff4d9' ),
		);
		$out = fika_wb_divider( 's2', 'What fika means' ) .
			'<img src="' . esc_url( home_url( '/wp-content/uploads/2026/10/fika-email-friends.jpg' ) ) . '" width="520" alt="Three cartoon sweets holding hands" style="display:block;width:100%;max-width:520px;height:auto;margin:6px auto 4px;border-radius:18px;">' .
			'<p style="margin:22px 0 8px;text-align:center;">' . fika_wb_head( 'made-for-sharing', 'Made for sharing' ) . '</p>' .
			'<p style="' . $f . 'color:#1b2a4a;font-size:16px;line-height:1.6;margin:0 0 14px;text-align:center;">Fika is the Swedish habit of pausing to share something sweet with the people you love. That is why we started Fika in Lebanon.</p>';
		foreach ( $vals as $v ) {
			$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:10px 0;"><tr><td class="fika-mail-box" style="background:' . $v[2] . ';">' .
				'<h2>' . esc_html( $v[0] ) . '</h2>' .
				'<p style="' . $f . 'color:#1b2a4a;font-size:15px;line-height:1.55;margin:0;">' . esc_html( $v[1] ) . '</p></td></tr></table>';
		}
		return $out . '<img src="' . esc_url( home_url( '/wp-content/uploads/2026/10/fika-email-bowl.jpg' ) ) . '" width="520" alt="A crystal bowl full of Swedish pick-and-mix" style="display:block;width:100%;max-width:520px;height:auto;margin:14px auto 0;border-radius:18px;">';
	}
	// delivered grams for an email: the account's orders, or (no account) the orders placed with that email
	function fika_wb_delivered( $email, $user ) {
		if ( $user && function_exists( 'fika_swim_totals' ) ) {
			$t = fika_swim_totals( $user->ID );
			return (int) $t['delivered'];
		}
		$g = 0;
		if ( function_exists( 'fika_swim_order_grams' ) ) {
			foreach ( wc_get_orders( array( 'billing_email' => $email, 'status' => 'wc-completed', 'limit' => -1, 'type' => 'shop_order' ) ) as $o ) {
				$g += fika_swim_order_grams( $o );
			}
		}
		return $g;
	}
	// the website's fish lane as a picture for this many grams (0-15000, in 100 g steps): the empty lane, the full
	// lane up to the fish, and the fish (media 1130-1132, drawn from the real lane by
	// wordpress/email-assets/make-email-lane.js at 3x). Saved once per step under uploads/fika-lane/, so email apps
	// fetch a plain file. False if it cannot be made (no GD, missing pictures): the email then shows a plain bar.
	function fika_wb_lane_url( $g ) {
		$g   = max( 0, min( 15000, (int) round( $g / 100 ) * 100 ) );
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'fika-lane';
		$fn  = 'lane-v2-' . $g . '.png';
		$url = trailingslashit( $up['baseurl'] ) . 'fika-lane/' . $fn;
		if ( file_exists( $dir . '/' . $fn ) ) {
			return $url;
		}
		$src = trailingslashit( $up['basedir'] ) . '2026/10/fika-email-lane-';
		if ( ! function_exists( 'imagecreatefrompng' ) || ! file_exists( $src . 'empty.png' ) || ! file_exists( $src . 'full.png' ) || ! file_exists( $src . 'fish.png' ) || ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$im   = imagecreatefrompng( $src . 'empty.png' );
		$full = imagecreatefrompng( $src . 'full.png' );
		$fish = imagecreatefrompng( $src . 'fish.png' );
		if ( ! $im || ! $full || ! $fish ) {
			return false;
		}
		// the 1110 x 396 pictures (the website's phone lane at 3x): 0 kg tick at x 179.6, 15 kg at 1055.6, water
		// band y 172-336 (centre 252), gifts above it (y 0-172), mystery wheels on it
		$at = function ( $grams ) {
			return (int) round( 179.6 + $grams / 15000 * 876 );
		};
		$x = $at( $g );
		if ( $g > 0 ) {
			imagecopy( $im, $full, 0, 172, 0, 172, $x, 164 ); // the water up to the fish
		}
		foreach ( array( 3000, 6000, 10000, 15000 ) as $cp ) { // gifts reached light up
			if ( $g >= $cp ) {
				imagecopy( $im, $full, $at( $cp ) - 64, 0, $at( $cp ) - 64, 0, 128, 172 );
			}
		}
		foreach ( array( 1500, 12500 ) as $sp ) { // mystery wheels the water has fully passed light up
			if ( $x >= $at( $sp ) + 66 ) {
				imagecopy( $im, $full, $at( $sp ) - 52, 190, $at( $sp ) - 52, 190, 118, 122 );
			}
		}
		imagealphablending( $im, true );
		imagecopy( $im, $fish, $x - 125, 252 - (int) ( imagesy( $fish ) / 2 ), 0, 0, imagesx( $fish ), imagesy( $fish ) );
		$ok = imagepng( $im, $dir . '/' . $fn, 9 );
		imagedestroy( $im );
		imagedestroy( $full );
		imagedestroy( $fish );
		return $ok ? $url : false;
	}
	// email 1, part 3: the Fika Club, with this customer's place on the 15 kg lane
	function fika_wb_part3( $email, $name ) {
		$f      = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$h      = 'display:block;font-variant:small-caps;font-family:\'Fanwood Text\',Georgia,\'Times New Roman\',serif;font-weight:400;color:#004aad;';
		$user   = get_user_by( 'email', $email );
		$member = $user && ! user_can( $user, 'manage_woocommerce' );
		$total  = fika_wb_delivered( $email, $member ? $user : null );
		$lap    = (int) floor( $total / 15000 ) + 1;
		$g      = $total - ( $lap - 1 ) * 15000; // grams in the current lap
		$kg     = function ( $x ) {
			$x = round( $x / 1000, 1 );
			return ( fmod( $x, 1 ) ? number_format( $x, 1 ) : number_format( $x, 0 ) ) . ' kg';
		};
		$togo   = function ( $x ) use ( $kg ) {
			return $x < 1000 ? ( (int) round( $x / 50 ) * 50 ) . ' g' : $kg( $x );
		};
		$ladder = array(
			array( 1500, 'Mystery taste', '50 g of a surprise sweet, free' ),
			array( 3000, '100 g on us', 'worth $2.80' ),
			array( 6000, '200 g on us', 'worth $5.60' ),
			array( 10000, '400 g on us', 'worth $11.20' ),
			array( 12500, 'Mystery taste', '50 g of a surprise sweet, free' ),
			array( 15000, 'A whole kilo on us', 'worth $28' ),
		);
		$next = null;
		$gift = false;
		foreach ( $ladder as $r ) {
			if ( $r[0] > $g && ! $next ) {
				$next = $r;
			}
			if ( $r[0] <= $g && 'Mystery taste' !== $r[1] ) {
				$gift = true;
			}
		}
		$pct  = max( 0, min( 100, (int) round( $g / 150 ) ) );
		$lane = fika_wb_lane_url( $g );
		// a link, so Gmail shows no download button over it; opens the rewards (or sign-up) page
		$bar  = $lane ? '<a href="' . esc_url( $member ? home_url( '/my-account/' ) : home_url( '/my-account/?action=register' ) ) . '" style="display:block;text-decoration:none;"><img src="' . esc_url( $lane ) . '" width="480" alt="Your fish at ' . esc_attr( $kg( $g ) ) . ' on the 15 kg lane" style="display:block;width:100%;max-width:480px;height:auto;margin:4px auto 0;border:0;"></a>' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:separate;border-radius:999px;background:#ffffff;border:2px solid #004aad;"><tr>' .
			( $pct > 0 ? '<td width="' . $pct . '%" style="width:' . $pct . '%;height:14px;background:#1c6fd6;border-radius:999px;font-size:0;line-height:0;">&nbsp;</td>' : '' ) .
			( $pct < 100 ? '<td style="height:14px;font-size:0;line-height:0;">&nbsp;</td>' : '' ) . '</tr></table>';
		$left = $next ? $next[0] - $g : 0;
		$bags = (int) ceil( $left / 500 );
		$line = $next
			? '<b>' . $togo( $left ) . ' to go</b> until ' . lcfirst( $next[1] ) . ' (' . $next[2] . ')' . ( $left >= 500 ? ', about ' . $bags . ' Ready Mix bag' . ( $bags > 1 ? 's' : '' ) : ', one small scoop away' ) . '.'
			: '<b>You finished the whole lane!</b> A new 15 kg lap starts with your next order.';
		$rows = '';
		foreach ( $ladder as $r ) {
			$done    = $r[0] <= $g;
			$is_next = $next && $r[0] === $next[0];
			$st      = $done ? '<span style="color:#1f7a4d;font-weight:600;">Reached &#10003;</span>' : ( $is_next ? '<span style="color:#004aad;font-weight:600;">Next</span>' : '<span style="color:#8a96b3;">' . $togo( $r[0] - $g ) . ' to go</span>' );
			$rows   .= '<tr><td style="' . $f . 'padding:9px 0;border-bottom:1px solid #f3d3e1;width:58px;color:#004aad;font-weight:600;font-size:15px;white-space:nowrap;">' . $kg( $r[0] ) . '</td>' .
				'<td style="' . $f . 'padding:9px 8px;border-bottom:1px solid #f3d3e1;color:#1b2a4a;font-size:15px;' . ( $is_next ? 'font-weight:600;' : '' ) . '">' . esc_html( $r[1] ) . '<span style="display:block;color:#6c7b9c;font-size:13px;font-weight:400;">' . esc_html( $r[2] ) . '</span></td>' .
				'<td align="right" style="' . $f . 'padding:9px 0;border-bottom:1px solid #f3d3e1;font-size:13.5px;white-space:nowrap;">' . $st . '</td></tr>';
		}
		$title = $member ? 'Your Fika Club' : 'Join the Fika Club';
		$intro = $member ? 'Every kilo you order moves your Swedish fish closer to free sweets. Here is where you are' . ( $name ? ', ' . $name : '' ) . '.' : 'Every kilo you order earns free sweets, and the orders you have already placed count too.';
		$small = 'margin:12px 0 0;' . $f . 'color:#1b2a4a;font-size:14px;line-height:1.5;';
		return fika_wb_divider( 's3', 'Your rewards' ) .
			'<p style="margin:16px 0 8px;text-align:center;">' . fika_wb_head( $member ? 'your-fika-club' : 'join-the-fika-club', $title ) . '</p>' .
			'<p style="' . $f . 'color:#1b2a4a;font-size:16px;line-height:1.6;margin:0 0 14px;text-align:center;">' . esc_html( $intro ) . '</p>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr><td class="fika-mail-box">' .
			'<p style="' . $f . 'padding-bottom:10px;color:#1b2a4a;font-size:14px;">' . ( $member ? 'You have' : 'You have already' ) . ' ordered' . ( $lap > 1 ? ' (lap ' . $lap . ')' : '' ) . '<br><span style="' . $h . 'font-variant:normal;font-size:40px;line-height:1.1;">' . $kg( $g ) . '</span> <span style="color:#6c7b9c;">of the 15 kg lane</span></p>' .
			$bar . '<p style="' . $f . 'color:#1b2a4a;font-size:15px;line-height:1.5;padding-top:12px;">' . $line . '</p></td></tr></table>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:14px 0 0;">' . $rows . '</table>' .
			'<p style="' . $f . 'color:#6c7b9c;font-size:13px;line-height:1.5;margin:10px 0 0;">Delivered orders count towards the lane. Free sweets don&rsquo;t count, and after 15 kg a new lap starts.</p>' .
			( $member && $gift ? '<p style="' . $small . '">Rewards you have reached wait on your account until you use them. Tap them under My account.</p>' : '' ) .
			( ! $member && $g > 0 ? '<p style="' . $small . '">Sign up with this email address and your ' . $kg( $total ) . ' count straight away.</p>' : '' ) .
			fika_wb_button( $member ? home_url( '/my-account/' ) : home_url( '/my-account/?action=register' ), $member ? 'See my rewards' : 'Join the Fika Club' );
	}
	// email 1: it's been a while
	function fika_wb_email1( $email, $p ) {
		$set  = fika_wb_settings();
		$new  = false;
		$list = fika_wb_products( $new );
		$hi   = $p['name'] ? 'Hi ' . esc_html( $p['name'] ) . ',' : 'Hi there,';
		$lead = $p['last']
			? 'It&rsquo;s been a while since your last fika! While you were away, a few new sweets found their way onto our candy wall. Let us introduce them.'
			: 'You joined Fika a while ago but haven&rsquo;t picked your first mix yet. Here is what&rsquo;s on our candy wall right now.';
		$body = '<div class="email-introduction"><p>' . $hi . '</p><p>' . $lead . '</p></div>' .
			( $list ? ( $new
				? '<p style="margin:22px 0 4px;text-align:center;">' . fika_wb_head( 'fresh-from-sweden', 'Fresh from Sweden', 38 ) . '</p><p style="text-align:center;margin:0 0 6px;"><span style="color:#6c7b9c;font-size:13.5px;">' . ( 4 === count( $list ) ? 'Four' : 'A few' ) . ' new friends on the candy wall</span></p>'
				: '<h2 class="email-order-detail-heading">Fan favourites<br><span>The sweets everyone keeps coming back for</span></h2>' ) .
				fika_wb_grid( $list ) .
				( $new ? '<p style="margin:10px 0 0;text-align:center;">Mix them with your old favourites. Every scoop is 100 g, so a little of everything is easy.</p>' : '' ) : '' ) .
			( trim( $set['news_title'] . $set['news_text'] ) ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:22px 0 4px;"><tr><td class="fika-mail-box">' .
				( $set['news_title'] ? '<h2>' . esc_html( $set['news_title'] ) . '</h2>' : '' ) . '<p>' . nl2br( esc_html( $set['news_text'] ) ) . '</p></td></tr></table>' : '' ) .
			fika_wb_button( home_url( '/mix-your-own/' ), 'Pick your mix' ) .
			fika_wb_part2() .
			fika_wb_part3( $email, $p['name'] ) .
			fika_wb_small( $email );
		$html = str_replace( '>We miss you!</h1>', '>' . fika_wb_head( 'we-miss-you', 'We miss you!', 46 ) . '</h1>', WC()->mailer()->wrap_message( 'We miss you!', $body ) );
		return array( 'It’s been a while' . ( $p['name'] ? ', ' . $p['name'] : '' ) . '! Here’s what’s new at Fika', $html );
	}
	// email 2: a personal discount code
	function fika_wb_email2( $email, $p, $code, $expires ) {
		$set  = fika_wb_settings();
		$pct  = (int) $set['discount'];
		$hi   = $p['name'] ? 'Hi ' . esc_html( $p['name'] ) . ',' : 'Hi there,';
		$body = '<div class="email-introduction"><p>' . $hi . '</p><p>We would love to see you back, so here is a little treat for your next bag.</p></div>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:22px 0 4px;"><tr><td class="fika-mail-box" style="text-align:center;">' .
			'<h2 style="text-align:center;">' . $pct . '% off your candies</h2>' .
			'<p style="text-align:center;">Your personal code</p>' .
			'<p style="text-align:center;margin:10px 0 !important;"><span style="display:inline-block;padding:10px 22px;border:2px dashed #004aad;border-radius:14px;background:#ffffff;color:#004aad;font-size:22px;font-weight:700;letter-spacing:.08em;">' . esc_html( $code ) . '</span></p>' .
			'<p style="text-align:center;color:#6c7b9c !important;font-size:13.5px !important;">Valid until ' . esc_html( wp_date( 'j F Y', $expires ) ) . ', once, for ' . esc_html( $email ) . '.</p>' .
			'</td></tr></table>' .
			fika_wb_button( add_query_arg( 'fika_coupon', rawurlencode( $code ), home_url( '/mix-your-own/' ) ), 'Use my ' . $pct . '%' ) .
			'<p style="text-align:center;">Fill your bag and the code is added at checkout. You can also type it in yourself.</p>' .
			fika_wb_small( $email );
		return array( 'A little treat to welcome you back: ' . $pct . '% off', WC()->mailer()->wrap_message( 'Here’s ' . $pct . '% off your next bag', $body ) );
	}
	function fika_wb_coupon( $email, $note = 'Win-back email 2' ) {
		$set     = fika_wb_settings();
		$code    = 'COMEBACK-' . strtoupper( wp_generate_password( 6, false, false ) );
		$expires = time() + (int) $set['valid_days'] * DAY_IN_SECONDS;
		$c       = new WC_Coupon();
		$c->set_code( $code );
		$c->set_discount_type( 'percent' );
		$c->set_amount( (int) $set['discount'] );
		$c->set_individual_use( true );
		$c->set_usage_limit( 1 );
		$c->set_usage_limit_per_user( 1 );
		$c->set_email_restrictions( array( $email ) );
		$c->set_date_expires( $expires );
		$c->set_description( $note . ' for ' . $email );
		$c->save();
		return array( $code, $expires );
	}
	function fika_wb_send( $email, $p, $which ) {
		if ( '2' === $which ) {
			list( $code, $expires ) = fika_wb_coupon( $email );
			list( $subject, $html ) = fika_wb_email2( $email, $p, $code, $expires );
		} else {
			list( $subject, $html ) = fika_wb_email1( $email, $p );
		}
		return WC()->mailer()->send( $email, $subject, $html ) ? ( isset( $code ) ? $code : true ) : false;
	}
	// a personal code (tied to one email): fill in that email for the shopper, then add the code
	function fika_wb_apply_code( $code ) {
		$c = new WC_Coupon( $code );
		if ( ! $c->get_id() ) {
			return false;
		}
		$emails = $c->get_email_restrictions();
		if ( 1 === count( $emails ) && WC()->customer && ! WC()->customer->get_billing_email() ) {
			WC()->customer->set_billing_email( $emails[0] );
			WC()->customer->save();
		}
		return WC()->cart->apply_coupon( $code );
	}
	// the daily run
	function fika_wb_run() {
		$set = fika_wb_settings();
		if ( 'off' === $set['mode'] ) {
			return 0;
		}
		$test = array_filter( array_map( 'strtolower', array_map( 'trim', preg_split( '/[\s,;]+/', (string) $set['test_emails'] ) ) ) );
		$st   = fika_wb_state();
		$sent = 0;
		foreach ( fika_wb_audience() as $email => $p ) {
			if ( 50 <= $sent ) {
				break;
			}
			if ( 'test' === $set['mode'] ? ! in_array( $email, $test, true ) : false ) {
				continue;
			}
			if ( fika_wb_stopped( $email ) ) {
				continue;
			}
			if ( isset( $st[ $email ] ) ? (int) $st[ $email ]['base'] !== (int) $p['last'] : false ) {
				unset( $st[ $email ] );
			}
			$due = fika_wb_due( $email, $p, $st, $set );
			if ( ! $due ) {
				continue;
			}
			$ok = fika_wb_send( $email, $p, $due );
			if ( ! $ok ) {
				continue;
			}
			$st[ $email ]               = isset( $st[ $email ] ) ? $st[ $email ] : array( 'base' => (int) $p['last'] );
			$st[ $email ][ 's' . $due ] = time();
			if ( is_string( $ok ) ) {
				$st[ $email ]['coupon'] = $ok;
			}
			fika_wb_log( $email, '1' === $due ? 'Email 1 sent (it’s been a while)' : 'Email 2 sent (code ' . ( is_string( $ok ) ? $ok : '' ) . ')' );
			$sent++;
		}
		update_option( 'fika_winback_state', $st, false );
		return $sent;
	}
}

// ---------- once a day ----------
add_action( 'fika_winback_daily', 'fika_wb_run' );
add_action( 'init', function () {
	if ( ! function_exists( 'as_next_scheduled_action' ) || as_next_scheduled_action( 'fika_winback_daily', array(), 'fika' ) ) {
		return;
	}
	$first = strtotime( 'tomorrow 07:00 UTC' ); // about 10:00 in Beirut
	as_schedule_recurring_action( $first, DAY_IN_SECONDS, 'fika_winback_daily', array(), 'fika' );
}, 20 );

// ---------- a customer who comes back: counted, and the cycle starts over on the next run ----------
$fika_wb_order = function ( $order ) {
	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
	if ( ! $order ) {
		return;
	}
	$e  = strtolower( $order->get_billing_email() );
	$st = fika_wb_state();
	if ( ! empty( $st[ $e ]['s1'] ) ) {
		fika_wb_log( $e, 'Ordered again (#' . $order->get_order_number() . ')' . ( empty( $st[ $e ]['s2'] ) ? ' after email 1' : ' after email 2' ) );
		$won = (int) get_option( 'fika_winback_won', 0 );
		update_option( 'fika_winback_won', $won + 1, false );
		unset( $st[ $e ] );
		update_option( 'fika_winback_state', $st, false );
	}
};
add_action( 'woocommerce_store_api_checkout_order_processed', $fika_wb_order );
add_action( 'woocommerce_checkout_order_processed', $fika_wb_order );

// ---------- links in the emails ----------
add_action( 'template_redirect', function () {
	// "Use my 10%": remember the code in a cookie (a first visit has no WooCommerce session yet), add it once the bag
	// reaches the cart
	if ( isset( $_GET['fika_coupon'] ) && function_exists( 'WC' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$code = wc_format_coupon_code( wp_unslash( $_GET['fika_coupon'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $code ) {
			if ( WC()->cart ? ! WC()->cart->is_empty() : false ) {
				fika_wb_apply_code( $code );
			} else {
				wc_setcookie( 'fika_coupon', $code, time() + 14 * DAY_IN_SECONDS );
			}
		}
		wp_safe_redirect( remove_query_arg( 'fika_coupon' ) );
		exit;
	}
	// Unsubscribe
	if ( isset( $_GET['fika_unsub'], $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$email = strtolower( sanitize_email( wp_unslash( $_GET['fika_unsub'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok    = $email ? hash_equals( substr( wp_hash( 'fika-wb|' . $email ), 0, 20 ), (string) wp_unslash( $_GET['k'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $ok ) {
			$stop   = get_option( 'fika_marketing_stop', array() );
			$stop   = is_array( $stop ) ? $stop : array();
			$stop[] = md5( $email );
			update_option( 'fika_marketing_stop', array_values( array_unique( $stop ) ), false );
			fika_wb_log( $email, 'Unsubscribed' );
		}
		wp_die( '<h1 style="font-family:sans-serif;color:#004aad">' . ( $ok ? 'You are unsubscribed' : 'Link not recognised' ) . '</h1><p style="font-family:sans-serif">' .
			( $ok ? 'We won&rsquo;t send you these emails any more. Order emails still arrive as usual.' : 'Please use the link from the email.' ) .
			' <a href="' . esc_url( home_url( '/' ) ) . '">Back to Fika</a></p>', 'Fika', array( 'response' => 200 ) );
	}
}, 6 );
// the remembered code goes on as soon as there is something in the cart (the shop's Checkout button fills it)
add_action( 'woocommerce_add_to_cart', function () {
	if ( ! WC()->cart || empty( $_COOKIE['fika_coupon'] ) ) {
		return;
	}
	$code = wc_format_coupon_code( wp_unslash( $_COOKIE['fika_coupon'] ) );
	wc_setcookie( 'fika_coupon', '', time() - HOUR_IN_SECONDS );
	unset( $_COOKIE['fika_coupon'] );
	if ( $code ? ! WC()->cart->has_discount( $code ) : false ) {
		fika_wb_apply_code( $code );
	}
}, 30 );

// ---------- WP Admin > WooCommerce > Win-back emails ----------
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Win-back emails', 'Win-back emails', 'manage_woocommerce', 'fika-winback', 'fika_wb_admin' );
}, 61 );
add_action( 'admin_post_fika_wb_save', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_wb_save' );
	$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	$set = fika_wb_settings();
	$set['mode']        = in_array( isset( $in['mode'] ) ? $in['mode'] : '', array( 'off', 'test', 'live' ), true ) ? $in['mode'] : 'test';
	$set['test_emails'] = sanitize_textarea_field( isset( $in['test_emails'] ) ? $in['test_emails'] : '' );
	$set['news_title']  = sanitize_text_field( isset( $in['news_title'] ) ? $in['news_title'] : '' );
	$set['news_text']   = sanitize_textarea_field( isset( $in['news_text'] ) ? $in['news_text'] : '' );
	$set['discount']    = max( 1, min( 100, absint( isset( $in['discount'] ) ? $in['discount'] : 10 ) ) );
	$set['valid_days']  = max( 1, absint( isset( $in['valid_days'] ) ? $in['valid_days'] : 14 ) );
	$set['first_days']  = max( 1, absint( isset( $in['first_days'] ) ? $in['first_days'] : 30 ) );
	$set['second_days'] = max( 1, absint( isset( $in['second_days'] ) ? $in['second_days'] : 14 ) );
	$set['products']    = implode( ',', array_slice( array_filter( array_map( 'absint', isset( $in['products'] ) ? (array) $in['products'] : array() ) ), 0, 4 ) );
	update_option( 'fika_winback_settings', $set, false );
	$msg = 'saved';
	if ( ! empty( $in['preview'] ) ) {
		$to = sanitize_email( isset( $in['preview_to'] ) ? $in['preview_to'] : '' );
		if ( is_email( $to ) ) {
			// the preview greets: the name typed in, else the recipient's account or latest order, else a name taken
			// from the address (alan.aboulhosn@... -> Alan), else "Sara"
			$name = sanitize_text_field( isset( $in['preview_name'] ) ? $in['preview_name'] : '' );
			$pu   = $name ? false : get_user_by( 'email', $to );
			if ( $pu ) {
				$name = get_user_meta( $pu->ID, 'first_name', true );
			}
			if ( ! $name ) {
				$po   = wc_get_orders( array( 'billing_email' => $to, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
				$name = $po ? $po[0]->get_billing_first_name() : '';
			}
			if ( ! $name && preg_match( '/^([a-z]{2,})/i', $to, $mm ) ) {
				$name = ucfirst( strtolower( $mm[1] ) );
			}
			$p = array( 'name' => $name ? $name : 'Sara', 'last' => time() - 40 * DAY_IN_SECONDS, 'since' => time() - 40 * DAY_IN_SECONDS );
			if ( '2' === $in['preview'] ) {
				list( $code, $exp )     = fika_wb_coupon( $to, 'Win-back preview' );
				list( $subject, $html ) = fika_wb_email2( $to, $p, $code, $exp );
			} else {
				list( $subject, $html ) = fika_wb_email1( $to, $p );
			}
			$msg = WC()->mailer()->send( $to, '[Preview] ' . $subject, $html ) ? 'preview' : 'preview-failed';
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=fika-winback&msg=' . $msg ) );
	exit;
} );
if ( ! function_exists( 'fika_wb_admin' ) ) {
	function fika_wb_admin() {
		$set   = fika_wb_settings();
		$st    = fika_wb_state();
		$aud   = fika_wb_audience();
		$due1  = 0;
		$due2  = 0;
		$rows  = '';
		foreach ( $aud as $e => $p ) {
			if ( fika_wb_stopped( $e ) ) {
				continue;
			}
			$d = fika_wb_due( $e, $p, $st, $set );
			$due1 += '1' === $d ? 1 : 0;
			$due2 += '2' === $d ? 1 : 0;
			$s     = isset( $st[ $e ] ) ? $st[ $e ] : array();
			$next  = empty( $s['s1'] ) ? $p['since'] + $set['first_days'] * DAY_IN_SECONDS : ( empty( $s['s2'] ) ? $s['s1'] + $set['second_days'] * DAY_IN_SECONDS : 0 );
			$rows .= '<tr><td>' . esc_html( $e ) . '</td><td>' . ( $p['last'] ? esc_html( wp_date( 'j M Y', $p['last'] ) ) : 'Never (account since ' . esc_html( wp_date( 'j M Y', $p['since'] ) ) . ')' ) . '</td><td>' .
				( empty( $s['s1'] ) ? 'Email 1' : ( empty( $s['s2'] ) ? 'Email 2' : 'Both sent' ) ) . ( $next ? ' &middot; ' . esc_html( wp_date( 'j M Y', $next ) ) : '' ) . ( $d ? ' <strong>(due now)</strong>' : '' ) . '</td></tr>';
		}
		$log = get_option( 'fika_winback_log', array() );
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
<div class="wrap">
	<h1>Win-back emails</h1>
	<p style="max-width:760px">Customers who haven&rsquo;t ordered for <?php echo (int) $set['first_days']; ?> days get <strong>email 1</strong> (what&rsquo;s new, your news, &ldquo;Pick your mix&rdquo;).
	<?php echo (int) $set['second_days']; ?> days later, if they still haven&rsquo;t ordered, <strong>email 2</strong> brings a personal <?php echo (int) $set['discount']; ?>% code, valid <?php echo (int) $set['valid_days']; ?> days.
	Ordering again starts over. Sent once a day, around 10:00. Orders came back after these emails: <strong><?php echo (int) get_option( 'fika_winback_won', 0 ); ?></strong>.</p>
	<?php if ( 'saved' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
	<?php if ( 'preview' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved, and the preview is on its way.</p></div><?php endif; ?>
	<?php if ( 'preview-failed' === $msg ) : ?><div class="notice notice-error is-dismissible"><p>The preview could not be sent.</p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="fika_wb_save"><?php wp_nonce_field( 'fika_wb_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Sending</th><td>
				<label><input type="radio" name="mode" value="test" <?php checked( 'test', $set['mode'] ); ?>> <strong>Test</strong>: only to the addresses below</label><br>
				<label><input type="radio" name="mode" value="live" <?php checked( 'live', $set['mode'] ); ?>> <strong>Live</strong>: to every customer who is due</label><br>
				<label><input type="radio" name="mode" value="off" <?php checked( 'off', $set['mode'] ); ?>> Off</label></td></tr>
			<tr><th>Test addresses</th><td><textarea name="test_emails" rows="2" class="large-text"><?php echo esc_textarea( $set['test_emails'] ); ?></textarea><p class="description">Used in Test mode. One or more, separated by commas.</p></td></tr>
			<tr><th>News (email 1)</th><td><input type="text" name="news_title" class="regular-text" value="<?php echo esc_attr( $set['news_title'] ); ?>" placeholder="Title"><br><br>
				<textarea name="news_text" rows="3" class="large-text" placeholder="A few words of news"><?php echo esc_textarea( $set['news_text'] ); ?></textarea><p class="description">Shown in a pink box. Leave both empty to leave it out. &ldquo;New drops&rdquo; fill in by themselves: candies added in the last 45 days, otherwise your favourites.</p></td></tr>
			<tr><th>Candies (email 1)</th><td><?php
				$chosen = array_filter( array_map( 'absint', explode( ',', (string) $set['products'] ) ) );
				$all    = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
				for ( $i = 0; $i < 4; $i++ ) :
					?><select name="products[]" style="margin:0 6px 6px 0;max-width:260px"><option value="">(automatic)</option><?php foreach ( $all as $pr ) : ?><option value="<?php echo (int) $pr->get_id(); ?>"<?php selected( isset( $chosen[ $i ] ) ? $chosen[ $i ] : 0, $pr->get_id() ); ?>><?php echo esc_html( $pr->get_name() ); ?></option><?php endforeach; ?></select><?php
				endfor; ?>
				<p class="description">The four candies in the &ldquo;New drops&rdquo; grid. All left on (automatic): candies added in the last 45 days, otherwise your favourites. Sold-out candies are skipped.</p></td></tr>
			<tr><th>Discount (email 2)</th><td><input type="number" name="discount" min="1" max="100" value="<?php echo (int) $set['discount']; ?>" style="width:80px"> % off the candies, valid <input type="number" name="valid_days" min="1" value="<?php echo (int) $set['valid_days']; ?>" style="width:70px"> days</td></tr>
			<tr><th>Timing</th><td>Email 1 after <input type="number" name="first_days" min="1" value="<?php echo (int) $set['first_days']; ?>" style="width:70px"> days without an order, email 2 <input type="number" name="second_days" min="1" value="<?php echo (int) $set['second_days']; ?>" style="width:70px"> days after email 1</td></tr>
			<tr><th>Send a preview</th><td><input type="email" name="preview_to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"> <input type="text" name="preview_name" placeholder="Name to greet (optional)">
				<button class="button" name="preview" value="1">Email 1</button> <button class="button" name="preview" value="2">Email 2</button>
				<p class="description">A preview of email 2 creates a real code for that address.</p></td></tr>
		</table>
		<?php submit_button( 'Save' ); ?>
	</form>
	<h2>Customers (<?php echo count( $aud ); ?>) &middot; due now: email 1 <?php echo (int) $due1; ?>, email 2 <?php echo (int) $due2; ?></h2>
	<table class="widefat striped" style="max-width:900px"><thead><tr><th>Email</th><th>Last order</th><th>Next email</th></tr></thead><tbody><?php echo $rows ? $rows : '<tr><td colspan="3">No customers yet.</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
	<h2>Recent activity</h2>
	<table class="widefat striped" style="max-width:900px"><thead><tr><th style="width:160px">When</th><th>Email</th><th>What</th></tr></thead><tbody>
		<?php if ( ! $log ) : ?><tr><td colspan="3">Nothing sent yet.</td></tr><?php endif; ?>
		<?php foreach ( array_slice( (array) $log, 0, 50 ) as $l ) : ?>
		<tr><td><?php echo esc_html( wp_date( 'j M Y, H:i', $l['t'] ) ); ?></td><td><?php echo esc_html( $l['email'] ); ?></td><td><?php echo esc_html( $l['what'] ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
</div>
		<?php
	}
}

// ---------- shop managers: preview without sending ----------
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/winback-preview', array( 'methods' => 'GET', 'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); }, 'callback' => function ( $req ) {
		$p = array( 'name' => 'Sara', 'last' => $req['never'] ? 0 : time() - 40 * DAY_IN_SECONDS, 'since' => time() - 40 * DAY_IN_SECONDS );
		$e = new WC_Email();
		if ( '2' === $req['n'] ) {
			list( $subject, $html ) = fika_wb_email2( 'sara@example.com', $p, 'COMEBACK-PREVIEW', time() + 14 * DAY_IN_SECONDS );
		} else {
			list( $subject, $html ) = fika_wb_email1( 'sara@example.com', $p );
		}
		return array( 'subject' => $subject, 'html' => apply_filters( 'woocommerce_mail_content', $e->style_inline( $html ) ) );
	} ) );
} );
