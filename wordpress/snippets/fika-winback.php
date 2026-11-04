<?php
/**
 * Fika: win-back emails for customers who have not ordered in a while.
 * - Email 1, 30 days after a customer's last order (or after they created an account, if they never ordered):
 *   "It's been a while": what's new (products added in the last 45 days, or the favourites), a news box written
 *   in WP Admin, and a "Pick your mix" button.
 * - Email 2, 14 days after email 1 if they still have not ordered (owner, 3-4 Nov 2026): "Fika owes you $5" in store
 *   credit, with loss aversion: "Name, don't lose your $5 ... before it runs out, or it is gone". A personal code, $5 off
 *   any order (no minimum; setting owe_min can add one), one use, only for their email, valid 2 days until 11:59 pm
 *   Beirut on the last day ("Only 2 days left · ends Friday"). Members and non-members get their own version.
 *   Top to bottom, each part with its own button:
 *     1. the voucher picture, "Name, don't lose your $5", the deadline: "Claim my $5 store credit" (members) /
 *        "Join for free & claim my $5" (non-members);
 *     2. their Fika Club progress, always (0 kg too): the lane with their fish and the next reward (fika_wb_claim):
 *        members "Move my fish forward" (0 kg: "Start my lane"); non-members "Unlock my free mystery taste" /
 *        "Unlock my free sweets" (reward reached), "Save my 0.9 kg & claim $5", 0 kg "Start my lane & claim my $5";
 *     3. the limited edition Fan Favourites Mix, while it is on: "Get it for $9" / "Join for free & get it for $9";
 *     4. "From our inbox", real customer messages (animated): "Get obsessed too" / "Join & get obsessed too".
 *   Where the links go: non-members, every button and picture to the sign-up page (My account, the "Join Fika Club"
 *   form) with their email filled in and a "We owe you $5" box; signing up there with that email needs no confirmation
 *   (the email link is the proof): they are logged in, their earlier orders join, and Mix your own opens with the bag
 *   and "Welcome to Fika Club!". Members: Mix your own; the Fan Favourites picture and button open Ready Mix with one
 *   bag of it already in the bag (?fika_add). Pictures: wordpress/email-assets/make-email-owe.js (+ make-email-inbox).
 * - The $5 (email-2 codes are marked _fika_owe): the email's links remember the code (cookie fika_coupon), and a
 *   logged-in customer's code is also found by their email (fika_wb_owed), on any phone or computer. It is only for
 *   Fika Club members: it goes on by itself when a logged-in customer has sweets in the bag, and is forgotten once an
 *   order uses it or it has expired (fika_wb_sync_code). In the bag: "✓ Your $5 store credit is taken off your total",
 *   a green "Fika store credit −$5.00" line and the Total less $5 (never below $0; WooCommerce never takes off more
 *   than the sweets cost, nor the delivery fee). At checkout the discount reads "Fika store credit". Logged out:
 *   "Create your free account to claim it, or log in".
 * - Tracking (fika-email-tracking.php, when installed): each send gets an id, its links get UTM tags, the email 2 code
 *   carries the id, so clicks and orders show per email in WooCommerce > Email results.
 * - Ordering again starts the cycle over. Every email has an unsubscribe link, and unsubscribed addresses never get
 *   these emails again. Shop staff accounts and test addresses (@example.com/.org/.net) are left out.
 * - Sends in small batches (10 emails, a few seconds apart, one batch an hour, 10:00-20:00 Beirut, at most 50 in any
 *   24 hours, within the hello@ mailbox's limit of about 100), so a big list goes out over a few days.
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
			'news_title'  => 'Fredagsmys is here',
			'news_text'   => 'Make Friday night the sweetest night of the week: let everyone pick a few favourites, pour them into one big bowl and gather the family around it.',
			'owe'         => 5,  // email 2: dollars of store credit
			'owe_min'     => 0, // ... store credit on any order (owner, 4 Nov 2026: no minimum; was $15). Above 0: a minimum of sweets
			'valid_days'  => 2, // owner, 3 Nov 2026: 2 days, for urgency (was 14)
			'first_days'  => 30,
			'second_days' => 14,
			'email2'      => 0, // 1 = email 2 (the code) is sent too; 0 = only email 1 goes out
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
	// the steps of the email being sent (who, then each step with the time). A trace that never reached "done" (a
	// send that froze) is kept in fika_winback_trace_stuck (the last 5), so the last step shows where it froze
	function fika_wb_trace( $email, $step, $new = false ) {
		$t = get_option( 'fika_winback_trace', array() );
		$t = is_array( $t ) ? $t : array();
		if ( $new ) {
			// (the closing QUIT can come after "done", so look for "done" anywhere in the steps)
			$done = ! empty( array_filter( empty( $t['steps'] ) ? array() : $t['steps'], function ( $x ) { return 0 === strpos( (string) $x[1], 'done:' ); } ) );
			if ( ! empty( $t['steps'] ) && ! $done ) {
				$stuck = get_option( 'fika_winback_trace_stuck', array() );
				$stuck = array_slice( array_merge( array( $t ), is_array( $stuck ) ? $stuck : array() ), 0, 5 );
				update_option( 'fika_winback_trace_stuck', $stuck, false );
			}
			$t = array( 'who' => substr( md5( strtolower( $email ) ), 0, 6 ), 'steps' => array() );
		}
		$t['steps'][] = array( round( microtime( true ), 2 ), $step );
		update_option( 'fika_winback_trace', $t, false );
	}
	function fika_wb_stopped( $email ) {
		$stop = get_option( 'fika_marketing_stop', array() );
		return is_array( $stop ) ? in_array( md5( strtolower( $email ) ), $stop, true ) : false;
	}
	// the name to greet: the first word only, with a capital letter ("galia" -> "Galia", "SARA" -> "Sara",
	// "Henriette rawady" -> "Henriette", "jean-paul" -> "Jean-Paul"); '' if nothing usable is left
	function fika_wb_first_name( $name ) {
		$name = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $name ) ) );
		if ( '' === $name ) {
			return '';
		}
		$first = explode( ' ', $name )[0];
		if ( ! preg_match( '/\p{L}/u', $first ) ) {
			return '';
		}
		return mb_convert_case( mb_strtolower( $first, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
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
				'name' => fika_wb_first_name( $o->get_billing_first_name() ? $o->get_billing_first_name() : $o->get_shipping_first_name() ),
				'last' => $o->get_date_created()->getTimestamp(),
			);
		}
		foreach ( get_users( array( 'role__in' => array( 'customer', 'subscriber' ), 'fields' => array( 'ID', 'user_email', 'user_registered' ) ) ) as $u ) {
			$e = strtolower( $u->user_email );
			if ( $skip( $e ) || user_can( $u->ID, 'manage_woocommerce' ) ) {
				continue;
			}
			if ( ! isset( $people[ $e ] ) ) {
				$people[ $e ] = array( 'name' => fika_wb_first_name( get_user_meta( $u->ID, 'first_name', true ) ), 'last' => 0, 'joined' => strtotime( $u->user_registered . ' UTC' ) );
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
				$is_new = 'picked';
				return array_slice( $list, 0, 4 );
			}
		}
		$args   = array( 'status' => 'publish', 'visibility' => 'catalog', 'stock_status' => 'instock', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' );
		$list   = wc_get_products( array_merge( $args, array( 'date_created' => '>' . ( time() - 45 * DAY_IN_SECONDS ) ) ) );
		$is_new = $list ? 'new' : 'fav';
		if ( ! $list ) {
			$list = wc_get_products( array_merge( $args, array( 'featured' => true, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) );
		}
		return $list;
	}
	// a product photo for email: a JPG copy (Outlook on Windows shows no WebP) with off-white turned pure white, so
	// every photo sits on the same white; saved once under uploads/fika-email-img/; the original if it cannot be made
	function fika_wb_photo( $att_id ) {
		$url = wp_get_attachment_image_url( $att_id, 'woocommerce_thumbnail' );
		if ( ! $url || ! function_exists( 'imagecreatefromstring' ) ) {
			return $url;
		}
		$up  = wp_upload_dir();
		$fn  = 'p' . (int) $att_id . '-v1.jpg';
		$dir = trailingslashit( $up['basedir'] ) . 'fika-email-img';
		if ( file_exists( $dir . '/' . $fn ) ) {
			return trailingslashit( $up['baseurl'] ) . 'fika-email-img/' . $fn;
		}
		$path = str_replace( trailingslashit( $up['baseurl'] ), trailingslashit( $up['basedir'] ), $url );
		$data = file_exists( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore
		$src  = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore
		if ( ! $src || ! wp_mkdir_p( $dir ) ) {
			return $url;
		}
		$w  = imagesx( $src );
		$h  = imagesy( $src );
		$im = imagecreatetruecolor( $w, $h );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 255, 255, 255 ) );
		imagecopy( $im, $src, 0, 0, 0, 0, $w, $h );
		$white = imagecolorallocate( $im, 255, 255, 255 );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$c = imagecolorat( $im, $x, $y );
				if ( ( $c >> 16 & 255 ) >= 246 && ( $c >> 8 & 255 ) >= 246 && ( $c & 255 ) >= 246 ) {
					imagesetpixel( $im, $x, $y, $white );
				}
			}
		}
		$ok = imagejpeg( $im, $dir . '/' . $fn, 86 );
		imagedestroy( $im );
		imagedestroy( $src );
		return $ok ? trailingslashit( $up['baseurl'] ) . 'fika-email-img/' . $fn : $url;
	}
	function fika_wb_grid( $list ) {
		$cells = array();
		foreach ( $list as $p ) {
			$img     = $p->get_image_id() ? fika_wb_photo( $p->get_image_id() ) : '';
			$bag     = has_term( 'ready-mix', 'product_cat', $p->get_id() );
			$price   = wc_price( wc_get_price_to_display( $p ) ) . ( $bag ? ' per 500 g bag' : ' per 100 g' );
			$name    = trim( preg_replace( array( '/^BUBS\s+/i', '/\s*\(mini\)\s*$/i' ), '', $p->get_name() ) ); // "BUBS Forest Berry Ovals (Mini)" -> "Forest Berry Ovals"
			$link    = esc_url( home_url( $bag ? '/ready-mix/' : '/mix-your-own/' ) );
			$cells[] = '<td width="50%" valign="top" style="width:50%;padding:8px;text-align:center;">' .
				( $img ? '<a href="' . $link . '" style="text-decoration:none;"><img src="' . esc_url( $img ) . '" width="200" alt="' . esc_attr( $p->get_name() ) . '" style="display:block;width:100%;max-width:200px;height:auto;margin:0 auto 8px;border-radius:16px;"></a>' : '' ) .
				'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="52" valign="top" style="height:52px;padding:0;text-align:center;">' .
				'<div><a href="' . $link . '" class="fika-pname" style="text-decoration:none;">' . esc_html( $name ) . '</a></div></td></tr>' .
				'<tr><td style="padding:2px 0 0;text-align:center;"><div style="color:#6c7b9c;font-size:13px;">' . wp_strip_all_tags( $price ) . '</div></td></tr></table></td>';
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
		$size = array( 'we-miss-you' => array( 548, 87 ), 'fresh-from-sweden' => array( 839, 108 ), 'made-for-sharing' => array( 763, 98 ), 'your-fika-club' => array( 650, 103 ), 'join-the-fika-club' => array( 776, 108 ),
			// media 1146-1150 (make-email-box-headings.js); fredagsmys-is-here added 2026-10-23
			'picked-for-you' => array( 604, 105 ), 'waiting-for-you' => array( 675, 105 ), 'lordagsmys-is-here' => array( 817, 105 ), 'fredagsmys-is-here' => array( 817, 104 ), 'family-time' => array( 477, 105 ), 'real-quality' => array( 543, 105 ), 'the-warmth-of-sharing' => array( 976, 105 ) );
		$w    = (int) round( $size[ $key ][0] * $h / $size[ $key ][1] );
		return '<img src="' . esc_url( home_url( '/wp-content/uploads/2026/10/fika-email-h-' . $key . '.png' ) ) . '" width="' . $w . '" height="' . (int) $h . '" alt="' . esc_attr( $alt ) . '" class="fika-t" style="display:block;width:' . $w . 'px;max-width:100%;height:auto;margin:0 auto;border:0;">';
	}
	// a box title: the cute-font picture when there is one for these words, else the email's own heading style
	function fika_wb_box_title( $text ) {
		$key = sanitize_title( remove_accents( $text ) );
		if ( in_array( $key, array( 'lordagsmys-is-here', 'fredagsmys-is-here', 'family-time', 'real-quality', 'the-warmth-of-sharing' ), true ) ) {
			return '<p style="margin:0 0 8px;">' . str_replace( 'margin:0 auto;', 'margin:0;', fika_wb_head( $key, $text, 24 ) ) . '</p>';
		}
		return '<h2>' . esc_html( $text ) . '</h2>';
	}
	// email 1, part 2: what fika means (family time, quality, the warmth of sharing)
	// labelled divider: the dotted lines are drawn inside the side cells, centred on the label (owner, 3 Nov 2026: they sat
	// on the top edge, above the text)
	function fika_wb_divider( $id, $label = '' ) {
		if ( '' === $label ) {
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:30px 0 6px;" id="' . esc_attr( $id ) . '"><tr><td style="border-top:2px dotted #f3b7cf;font-size:0;line-height:0;">&nbsp;</td></tr></table>';
		}
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:30px 0 6px;" id="' . esc_attr( $id ) . '"><tr>' .
			'<td style="width:38%;padding:0;vertical-align:middle;"><div style="border-top:2px dotted #f3b7cf;height:0;font-size:0;line-height:0;">&nbsp;</div></td><td align="center" style="font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#d35d93;padding:0 10px;white-space:nowrap;vertical-align:middle;line-height:18px;"><span style="color:#d35d93;">' . esc_html( $label ) . '</span></td><td style="width:38%;padding:0;vertical-align:middle;"><div style="border-top:2px dotted #f3b7cf;height:0;font-size:0;line-height:0;">&nbsp;</div></td></tr></table>';
	}
	function fika_wb_part2() {
		$f    = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$vals = array(
			array( 'Family time', 'Friday evening, one big bowl, everyone picking their favourites. In Sweden it is called fredagsmys, and it is our favourite tradition.', '#fdeaf2' ),
			array( 'Real quality', 'Every sweet is hand-selected and imported from Sweden, with clean ingredients and gelatin-free options clearly marked.', '#eaf2ff' ),
			array( 'The warmth of sharing', 'Pick-and-mix is made to be passed around: a bag for the car, a bowl for the table, a little extra for a friend.', '#fff4d9' ),
		);
		$out = fika_wb_divider( 's2', 'What fika means' ) .
			'<img src="' . esc_url( home_url( '/wp-content/uploads/2026/10/fika-email-friends.jpg' ) ) . '" width="520" alt="Three cartoon sweets holding hands" style="display:block;width:100%;max-width:520px;height:auto;margin:6px auto 4px;border-radius:18px;">' .
			'<p style="margin:22px 0 8px;text-align:center;">' . fika_wb_head( 'made-for-sharing', 'Made for sharing' ) . '</p>' .
			'<p style="' . $f . 'color:#1b2a4a;font-size:16px;line-height:1.6;margin:0 0 14px;text-align:center;">Fika is the Swedish habit of pausing to share something sweet with the people you love. That is why we started Fika in Lebanon.</p>';
		foreach ( $vals as $v ) {
			$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:10px 0;"><tr><td class="fika-mail-box" style="background:' . $v[2] . ';">' .
				fika_wb_box_title( $v[0] ) .
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
	// lane up to the fish, and the fish (media 1143-1145, fika-email-lane4-*, drawn from the real lane by
	// wordpress/email-assets/make-email-lane.js at 3x). Saved once per step under uploads/fika-lane/, so email apps
	// fetch a plain file. False if it cannot be made (no GD, missing pictures): the email then shows a plain bar.
	function fika_wb_lane_url( $g ) {
		$g   = max( 0, min( 15000, (int) round( $g / 100 ) * 100 ) );
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'fika-lane';
		$fn  = 'lane-v4-' . $g . '.png';
		$url = trailingslashit( $up['baseurl'] ) . 'fika-lane/' . $fn;
		if ( file_exists( $dir . '/' . $fn ) ) {
			return $url;
		}
		$src = trailingslashit( $up['basedir'] ) . '2026/10/fika-email-lane4-';
		if ( ! function_exists( 'imagecreatefrompng' ) || ! file_exists( $src . 'empty.png' ) || ! file_exists( $src . 'full.png' ) || ! file_exists( $src . 'fish.png' ) || ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$im   = imagecreatefrompng( $src . 'empty.png' );
		$full = imagecreatefrompng( $src . 'full.png' );
		$fish = imagecreatefrompng( $src . 'fish.png' );
		if ( ! $im || ! $full || ! $fish ) {
			return false;
		}
		// the 900 x 396 pictures (the website's lane drawn 300 px wide, at 3x): 0 kg tick at x 173.6, 15 kg at 809.6,
		// water band y 172-336 (centre 252), gifts above it (y 0-172), mystery wheels on it
		$at = function ( $grams ) {
			return (int) round( 173.6 + $grams / 15000 * 636 );
		};
		$x = $at( $g );
		if ( $g > 0 ) {
			imagecopy( $im, $full, 0, 172, 0, 172, $x, 164 ); // the water up to the fish
		}
		foreach ( array( 3000, 6000, 10000, 15000 ) as $cp ) { // gifts reached light up
			if ( $g >= $cp ) {
				imagecopy( $im, $full, $at( $cp ) - 64, 0, $at( $cp ) - 64, 0, min( 128, imagesx( $full ) - $at( $cp ) + 64 ), 172 );
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
	// a reward's name in the cute font, as a picture (media 1137-1141, made by
	// wordpress/email-assets/make-email-rewards.js at 2x, 111 px tall); the words stay as alt text
	function fika_wb_reward_name( $name ) {
		$w = array( 'Mystery taste' => 614, '100 g on us' => 520, '200 g on us' => 526, '400 g on us' => 534, 'A whole kilogram on us' => 1006 );
		if ( ! isset( $w[ $name ] ) ) {
			return esc_html( $name );
		}
		$key = sanitize_title( $name );
		// one size for all, small enough that the longest ("A whole kilogram on us") fits a phone's column
		$px  = (int) round( $w[ $name ] * 17 / 111 );
		return '<img src="' . esc_url( home_url( '/wp-content/uploads/2026/10/fika-email-r-' . $key . '.png' ) ) . '" width="' . $px . '" height="17" alt="' . esc_attr( $name ) . '" class="fika-t" style="display:block;width:' . $px . 'px;max-width:100%;height:auto;border:0;margin:0 0 3px;">';
	}
	// email 1, part 3: the Fika Club, with this customer's place on the 15 kg lane
	function fika_wb_part3( $email, $name, $divider = true ) {
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
			array( 1500, 'Mystery taste', 'A free 50 g surprise' ),
			array( 3000, '100 g on us', 'worth $2.80' ),
			array( 6000, '200 g on us', 'worth $5.60' ),
			array( 10000, '400 g on us', 'worth $11.20' ),
			array( 12500, 'Mystery taste', 'A free 50 g surprise' ),
			array( 15000, 'A whole kilogram on us', 'worth $28' ),
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
		$bar  = $lane ? '<a href="' . esc_url( $member ? home_url( '/my-account/' ) : home_url( '/my-account/#register' ) ) . '" style="display:block;text-decoration:none;"><img src="' . esc_url( $lane ) . '" width="400" alt="Your fish at ' . esc_attr( $kg( $g ) ) . ' on the 15 kg lane" class="fika-t" style="display:block;width:100%;max-width:400px;height:auto;margin:4px auto 0;border:0;"></a>' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:separate;border-radius:999px;background:#ffffff;border:2px solid #004aad;"><tr>' .
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
			// without an account a reward reached cannot be claimed yet: it unlocks once they join with this email
			$st      = $done ? ( $member ? '<span style="color:#1f7a4d;font-weight:600;">Reached &#10003;</span>' : '<span style="color:#d35d93;font-weight:600;white-space:normal;">Unlocks when<br>you join</span>' ) : ( $is_next ? '<span style="color:#004aad;font-weight:600;">Next</span>' : '<span style="color:#8a96b3;">' . $togo( $r[0] - $g ) . ' to go</span>' );
			$rows   .= '<tr><td style="' . $f . 'padding:9px 0;border-bottom:1px solid #f3d3e1;width:58px;color:#004aad;font-weight:600;font-size:15px;white-space:nowrap;">' . $kg( $r[0] ) . '</td>' .
				'<td style="' . $f . 'padding:9px 8px;border-bottom:1px solid #f3d3e1;color:#1b2a4a;font-size:15px;' . ( $is_next ? 'font-weight:600;' : '' ) . '">' . fika_wb_reward_name( $r[1] ) . '<span style="display:block;color:#6c7b9c;font-size:13px;font-weight:400;">' . esc_html( $r[2] ) . '</span></td>' .
				'<td align="right" style="' . $f . 'padding:9px 0;border-bottom:1px solid #f3d3e1;font-size:13.5px;white-space:nowrap;">' . $st . '</td></tr>';
		}
		$title = $member ? 'Your Fika Club' : 'Join the Fika Club';
		$intro = $member ? 'Every kilogram you order moves your Swedish fish closer to free sweets. Here is where you are' . ( $name ? ', ' . $name : '' ) . '.' : 'Every kilogram you order earns free sweets, and the orders you have already placed count too.';
		$small = 'margin:12px 0 0;' . $f . 'color:#1b2a4a;font-size:14px;line-height:1.5;';
		return ( $divider ? fika_wb_divider( 's3' ) : '' ) .
			'<p style="margin:16px 0 8px;text-align:center;">' . fika_wb_head( $member ? 'your-fika-club' : 'join-the-fika-club', $title ) . '</p>' .
			'<p style="' . $f . 'color:#1b2a4a;font-size:16px;line-height:1.6;margin:0 0 14px;text-align:center;">' . esc_html( $intro ) . '</p>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr><td class="fika-mail-box">' .
			'<p style="' . $f . 'padding-bottom:10px;color:#1b2a4a;font-size:14px;">' . ( $member ? 'You have' : 'You have already' ) . ' ordered' . ( $lap > 1 ? ' (lap ' . $lap . ')' : '' ) . '<br><span style="' . $h . 'font-variant:normal;font-size:40px;line-height:1.1;">' . $kg( $g ) . '</span> <span style="color:#6c7b9c;">of the 15 kg lane</span></p>' .
			$bar . '<p style="' . $f . 'color:#1b2a4a;font-size:15px;line-height:1.5;padding-top:12px;">' . $line . '</p></td></tr></table>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:14px 0 0;">' . $rows . '</table>' .
			( $member && $gift ? '<p style="' . $small . '">Rewards you have reached wait on your account until you use them. Tap them under My account.</p>' : '' ) .
			( ! $member && $g > 0 ? '<p style="' . $small . '">Sign up with this email address and your ' . $kg( $total ) . ' count straight away.</p>' : '' ) .
			fika_wb_button( $member ? home_url( '/my-account/' ) : home_url( '/my-account/#register' ), $member ? 'See my rewards' : 'Join the Fika Club' );
	}
	// rewards a member has and has not used yet: mystery tastes won (a free 50 g line at checkout), reward codes
	// claimed, and gifts their delivered kilos reached but not claimed yet: [ [what, how to use, product], ... ]
	function fika_wb_waiting_list( $email ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || user_can( $user, 'manage_woocommerce' ) ) {
			return array();
		}
		$out    = array();
		$tastes = get_user_meta( $user->ID, 'fika_tastes', true );
		$have   = fika_wb_delivered( $email, $user );
		foreach ( is_array( $tastes ) ? $tastes : array() as $k => $t ) {
			$pr = ! empty( $t['pid'] ) && 'won' === ( isset( $t['status'] ) ? $t['status'] : '' ) ? wc_get_product( (int) $t['pid'] ) : null;
			if ( $pr ) {
				// the free 50 g joins the bag once delivered kilos + the bag reach the wheel's stop (fika-taste.php)
				$need  = function_exists( 'fika_taste_need' ) ? (int) fika_taste_need( $k ) : 0;
				$short = $need > $have ? (int) ( ceil( ( $need - $have ) / 100 ) * 100 ) : 0;
				$how   = $short ? 'Add at least ' . ( $short >= 1000 ? rtrim( rtrim( number_format( $short / 1000, 1 ), '0' ), '.' ) . ' kg' : $short . ' g' ) . ' to your bag and your free 50 g is added at checkout.' : 'Fill your bag and your free 50 g is added at checkout.';
				$out[] = array( 'a free 50 g of ' . trim( preg_replace( array( '/^BUBS\s+/i', '/\s*\(mini\)\s*$/i' ), '', $pr->get_name() ) ), $how, $pr );
			}
		}
		if ( function_exists( 'fika_swim_open_codes' ) ) {
			foreach ( fika_swim_open_codes( $user->ID ) as $c ) {
				$out[] = array( str_replace( 'kilo ', 'kilogram ', lcfirst( $c[1] ) ) . ' (' . $c[3] . ')', 'It is saved on your account: tap Use at checkout.', null );
			}
		}
		if ( function_exists( 'fika_swim_unclaimed' ) && function_exists( 'fika_swim_checkpoints' ) ) {
			$cps = fika_swim_checkpoints();
			foreach ( fika_swim_unclaimed( $user->ID, fika_wb_delivered( $email, $user ) ) as $u ) {
				$out[] = array( str_replace( 'kilo ', 'kilogram ', lcfirst( $cps[ $u[1] ]['title'] ) ) . ' (worth $' . rtrim( rtrim( number_format( $cps[ $u[1] ]['amount'], 2 ), '0' ), '.' ) . ')', 'Tap the gift on your rewards lane to claim it, then use it whenever you like.', null );
			}
		}
		return $out;
	}
	// the "Waiting for you" box at the top of email 1 (only for members with something unused)
	function fika_wb_waiting( $email, $name ) {
		$list = fika_wb_waiting_list( $email );
		if ( ! $list ) {
			return '';
		}
		$first = $list[0];
		$img   = $first[2] && $first[2]->get_image_id() ? fika_wb_photo( $first[2]->get_image_id() ) : '';
		$items = '';
		foreach ( $list as $w ) {
			$items .= '<p style="margin:0 0 6px;"><b>' . str_replace( ' g ', '&nbsp;g ', esc_html( ucfirst( $w[0] ) ) ) . '</b><br><span style="color:#6c7b9c;font-size:13.5px;">' . str_replace( ' g ', '&nbsp;g ', esc_html( $w[1] ) ) . '</span></p>';
		}
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:18px 0 22px;"><tr><td class="fika-mail-box" style="background:#fff4d9;border:2px dashed #f2b84b;">' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' .
			( $img ? '<td width="96" valign="top" style="width:96px;padding:0;"><img src="' . esc_url( $img ) . '" width="96" alt="' . esc_attr( $first[2]->get_name() ) . '" style="display:block;width:96px;height:auto;border-radius:14px;border:0;"></td><td width="14" style="width:14px;padding:0;font-size:0;line-height:0;">&nbsp;</td>' : '' ) .
			'<td valign="top" style="padding:0;">' .
			'<p style="margin:0 0 8px;">' . str_replace( 'margin:0 auto;', 'margin:0;', fika_wb_head( 'waiting-for-you', 'Waiting for you', 24 ) ) . '</p>' .
			'<p style="margin:0 0 8px;">' . esc_html( ( $name ? $name . ', you' : 'You' ) . ' still have ' . ( 1 === count( $list ) ? 'a reward' : count( $list ) . ' rewards' ) . ' on your Fika account:' ) . '</p>' .
			$items . '</td></tr></table>' .
			fika_wb_button( home_url( '/mix-your-own/' ), 'Use it now' ) . '</td></tr></table>';
	}
	// the inbox preview text for email 1: where the customer's fish is
	function fika_wb_preheader( $email ) {
		$user   = get_user_by( 'email', $email );
		$member = $user && ! user_can( $user, 'manage_woocommerce' );
		$g      = fika_wb_delivered( $email, $member ? $user : null ) % 15000;
		$left   = 0;
		$next   = '';
		foreach ( array( 1500 => 'a free mystery taste', 3000 => '100 g of free sweets', 6000 => '200 g of free sweets', 10000 => '400 g of free sweets', 12500 => 'a free mystery taste', 15000 => 'a whole free kilogram' ) as $at => $what ) {
			if ( $at > $g ) {
				$left = $at - $g;
				$next = $what;
				break;
			}
		}
		$togo = $left < 1000 ? ( (int) round( $left / 50 ) * 50 ) . ' g' : rtrim( rtrim( number_format( $left / 1000, 1 ), '0' ), '.' ) . ' kg';
		$wait = $member ? fika_wb_waiting_list( $email ) : array();
		if ( $wait ) {
			return ucfirst( $wait[0][0] ) . ' is waiting for you on your Fika account 🍬';
		}
		if ( $g > 0 && $member ) {
			return 'Your fish is only ' . $togo . ' away from ' . $next . ' 🍬';
		}
		if ( $g > 0 ) {
			return 'You already have ' . rtrim( rtrim( number_format( $g / 1000, 1 ), '0' ), '.' ) . ' kg towards free sweets 🍬';
		}
		return 'New sweets on the candy wall, and free sweets to swim for 🍬';
	}
	// email 1: it's been a while
	// $as: show another customer's Fika Club progress (admin previews); the email itself is for $email
	function fika_wb_email1( $email, $p, $as = '' ) {
		$who  = $as ? $as : $email;
		$set  = fika_wb_settings();
		$kind = 'fav';
		$list = fika_wb_products( $kind );
		$hi   = $p['name'] ? 'Hi ' . esc_html( $p['name'] ) . ',' : 'Hi there,';
		$lead = $p['last']
			? 'It&rsquo;s been a while since your last fika! Here&rsquo;s how close your Swedish fish is to free sweets.'
			: 'You joined Fika a while ago but haven&rsquo;t picked your first mix yet. Here&rsquo;s how our free sweets work, and what&rsquo;s on our candy wall right now.';
		$body = '<div class="email-introduction"><p>' . $hi . '</p><p>' . $lead . '</p></div>' .
			fika_wb_waiting( $who, $p['name'] ) .
			fika_wb_part3( $who, $p['name'], false ) .
			( $list ? fika_wb_divider( 's1' ) : '' ) .
			( $list ? ( 'fav' !== $kind
				? '<p style="margin:16px 0 4px;text-align:center;">' . ( 'new' === $kind ? fika_wb_head( 'fresh-from-sweden', 'Fresh from Sweden', 38 ) : fika_wb_head( 'picked-for-you', 'Picked for you', 38 ) ) . '</p><p style="text-align:center;margin:0 0 6px;"><span style="color:#6c7b9c;font-size:13.5px;">' . ( 'new' === $kind ? ( 4 === count( $list ) ? 'Four' : 'A few' ) . ' new friends on the candy wall' : 'A few favourites from our candy wall' ) . '</span></p>'
				: '<h2 class="email-order-detail-heading">Fan favourites<br><span>The sweets everyone keeps coming back for</span></h2>' ) .
				fika_wb_grid( $list ) : '' ) .
			( trim( $set['news_title'] . $set['news_text'] ) ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:22px 0 4px;"><tr><td class="fika-mail-box">' .
				( $set['news_title'] ? fika_wb_box_title( $set['news_title'] ) : '' ) . '<p>' . nl2br( esc_html( $set['news_text'] ) ) . '</p></td></tr></table>' : '' ) .
			fika_wb_button( home_url( '/mix-your-own/' ), 'Pick your mix' ) .
			fika_wb_part2() .
			fika_wb_small( $email );
		$html = str_replace( '>We miss you!</h1>', '>' . fika_wb_head( 'we-miss-you', 'We miss you!', 46 ) . '</h1>', WC()->mailer()->wrap_message( 'We miss you!', $body ) );
		// the preview text, hidden at the very top (padded so the inbox does not run on into the email's first words)
		$pre  = '<div class="fika-pre" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' . esc_html( fika_wb_preheader( $who ) ) . str_repeat( '&#847;&zwnj;&nbsp;', 40 ) . '</div>';
		$html = preg_replace( '/(<body\b[^>]*>)/i', '$1' . $pre, $html, 1 );
		return array( 'It’s been a while' . ( $p['name'] ? ', ' . $p['name'] : '' ) . ( 'new' === $kind ? '! Here’s what’s new at Fika' : '! A few sweets picked for you' ), $html );
	}
	// email 2, under the code (owner, 3 Nov 2026): the kilograms this customer already has with Fika and the reward
	// they unlock, with "Create my account" (no account yet: earlier orders count once they join with this email) or
	// "See my rewards" (members). Nothing when they have no delivered order.
	function fika_wb_claim( $email, $name, $btn = null ) {
		$f      = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$h      = 'display:block;font-variant:small-caps;font-family:\'Fanwood Text\',Georgia,\'Times New Roman\',serif;font-weight:400;color:#004aad;';
		$user   = get_user_by( 'email', $email );
		$member = $user && ! user_can( $user, 'manage_woocommerce' );
		$total  = fika_wb_delivered( $email, $member ? $user : null );
		// nothing delivered yet: the box still shows (owner, 4 Nov 2026: their progress is always the 2nd part), with
		// the fish at the start of the lane and the first reward to aim for
		$zero   = $total <= 0;
		$g    = $total % 15000;
		$kg   = function ( $x ) {
			$x = round( $x / 1000, 1 );
			return ( fmod( $x, 1 ) ? number_format( $x, 1 ) : number_format( $x, 0 ) ) . ' kg';
		};
		$togo = function ( $x ) use ( $kg ) {
			return $x < 1000 ? ( (int) round( $x / 50 ) * 50 ) . ' g' : $kg( $x );
		};
		$ladder = array( 1500 => array( 'a free mystery taste', 'a free 50 g surprise' ), 3000 => array( '100 g of free sweets', 'worth $2.80' ), 6000 => array( '200 g of free sweets', 'worth $5.60' ), 10000 => array( '400 g of free sweets', 'worth $11.20' ), 12500 => array( 'a free mystery taste', 'a free 50 g surprise' ), 15000 => array( 'a whole free kilogram', 'worth $28' ) );
		$got  = null;
		$next = null;
		foreach ( $ladder as $at => $r ) {
			if ( $at <= $g ) {
				$got = $r;
			} elseif ( ! $next ) {
				$next = array( $at, $r );
			}
		}
		$lane = fika_wb_lane_url( $g );
		$link = $btn ? $btn[0] : ( $member ? home_url( '/my-account/' ) : home_url( '/my-account/#register' ) ); // the picture goes where its button goes
		$pic  = $lane ? '<a href="' . esc_url( $link ) . '" style="display:block;text-decoration:none;"><img src="' . esc_url( $lane ) . '" width="400" alt="Your fish at ' . esc_attr( $kg( $g ) ) . ' on the 15 kg lane" class="fika-t" style="display:block;width:100%;max-width:400px;height:auto;margin:4px auto 0;border:0;"></a>' : '';
		if ( $zero ) {
			$text = 'Every kilogram you order moves your fish along the lane to free sweets: <b>' . $togo( $next[0] ) . ' to go</b> until ' . $next[1][0] . ' (' . $next[1][1] . ').' . ( $member ? '' : ' Your Fika Club account keeps count.' );
		} elseif ( $member ) {
			$text = $next ? '<b>' . $togo( $next[0] - $g ) . ' to go</b> until ' . $next[1][0] . ' (' . $next[1][1] . '). Rewards you have reached wait on your account.' : 'Your next order starts a new 15 kg lap.';
		} elseif ( $got ) {
			$text = 'That is already <b>' . $got[0] . '</b> (' . $got[1] . '). It is yours as soon as you create your Fika Club account with this email: your earlier orders count straight away.';
		} else {
			$text = 'Create your Fika Club account with this email and your earlier orders count straight away: only <b>' . $togo( $next[0] - $g ) . ' to go</b> until ' . $next[1][0] . ' (' . $next[1][1] . ').';
		}
		return fika_wb_divider( 'c2', $member || $zero ? 'Your Fika Club' : 'Already earned' ) .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:14px 0 0;"><tr><td class="fika-mail-box">' .
			'<p style="' . $f . 'padding-bottom:10px;color:#1b2a4a;font-size:14px;">' . ( $name ? esc_html( $name ) . ( $zero ? ', your lane starts at' : ', you already have' ) : ( $zero ? 'Your lane starts at' : 'You already have' ) ) . '<br><span style="' . $h . 'font-variant:normal;font-size:40px;line-height:1.1;">' . $kg( $g ) . '</span> <span style="color:#6c7b9c;">with Fika</span></p>' .
			$pic . '<p style="' . $f . 'color:#1b2a4a;font-size:15px;line-height:1.5;padding-top:12px;">' . $text . '</p></td></tr></table>' .
			fika_wb_button( $link, $btn ? fika_wb_claim_label( $member, $zero, $g, $got ) : ( $member ? 'See my rewards' : 'Create my account' ) );
	}
	// the progress box's button (owner, 4 Nov 2026: each part of email 2 has its own words, after what it shows)
	function fika_wb_claim_label( $member, $zero, $g, $got ) {
		$owe = fika_wb_money( fika_wb_owe()[0] );
		if ( $member ) {
			return $zero ? 'Start my lane' : 'Move my fish forward';
		}
		if ( $zero ) {
			return 'Start my lane & claim my ' . $owe;
		}
		if ( $got ) {
			return 'a free mystery taste' === $got[0] ? 'Unlock my free mystery taste' : 'Unlock my free sweets';
		}
		$x = round( $g / 1000, 1 );
		return 'Save my ' . ( fmod( $x, 1 ) ? number_format( $x, 1 ) : number_format( $x, 0 ) ) . ' kg & claim ' . $owe;
	}
	// email 2's offer: [ dollars we owe, minimum order of sweets ]
	function fika_wb_owe() {
		$set = fika_wb_settings();
		return array( (float) $set['owe'], (float) $set['owe_min'] );
	}
	function fika_wb_money( $x ) {
		return '$' . rtrim( rtrim( number_format( (float) $x, 2, '.', '' ), '0' ), '.' );
	}
	// email 2: the limited edition bag, while it is on (snippet 193, FIKA_DROPS_MODE 'limited') and in stock
	// $btn: [ link, words ] for a button under the bag (owner, 4 Nov 2026: the claim button there too)
	function fika_wb_ff_box( $link, $btn = null ) {
		if ( ! defined( 'FIKA_DROPS_MODE' ) || 'limited' !== FIKA_DROPS_MODE ) {
			return '';
		}
		$p = wc_get_product( FIKA_DROPS_DROP_ID );
		if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() || 'publish' !== $p->get_status() ) {
			return '';
		}
		$f   = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$img = $p->get_image_id() ? fika_wb_photo( $p->get_image_id() ) : '';
		return fika_wb_divider( 'ff', 'Our pick for you' ) .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:14px 0 0;"><tr><td align="center" style="text-align:center;padding:0;">' .
			( $img ? '<a href="' . esc_url( $link ) . '" style="display:block;"><img src="' . esc_url( $img ) . '" width="230" alt="' . esc_attr( $p->get_name() ) . '" style="display:block;width:230px;max-width:100%;height:auto;margin:0 auto;border:0;border-radius:18px;"></a>' : '' ) .
			'<div style="' . $f . 'display:inline-block;margin-top:10px;padding:3px 10px;border-radius:999px;background:#004aad;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Limited edition</div>' .
			'<div style="' . $f . 'margin:8px 0 4px;color:#004aad;font-size:20px;font-weight:600;line-height:1.25;">' . esc_html( $p->get_name() ) . '</div>' .
			'<div style="' . $f . 'color:#1b2a4a;font-size:15px;line-height:1.5;">Our top-selling Sweet &amp; Sour Mix with the new BUBS Strawberry Vanilla Diamond and the Pomegranate Ovals back.</div>' .
			'<div style="' . $f . 'margin-top:6px;color:#6c7b9c;font-size:13.5px;">' . wp_strip_all_tags( wc_price( wc_get_price_to_display( $p ) ) ) . ' per 500 g bag, while it lasts</div>' .
			'</td></tr></table>' .
			( $btn ? fika_wb_button( $btn[0], str_replace( '{price}', fika_wb_money( max( 0, (float) wc_get_price_to_display( $p ) - fika_wb_owe()[0] ) ), $btn[1] ) ) : '' );
	}
	// email 2: a real message a customer sent after a delivery (fika-checkout-texts.php), as the checkout's Fika inbox
	// played as a short animation (a GIF: typing, the messages popping in, the pink heart), made by
	// wordpress/email-assets/make-email-inbox.js + .py; it starts on the finished chat for apps that show one frame
	function fika_wb_quote() {
		if ( ! function_exists( 'fika_ct_messages' ) ) {
			return '';
		}
		$all = fika_ct_messages();
		if ( empty( $all['sleep'] ) ) {
			return '';
		}
		$f   = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$alt = 'A customer wrote: ' . implode( ' / ', wp_list_pluck( $all['sleep'], 0 ) );
		$n   = function_exists( 'fika_ct_delivered' ) ? (int) fika_ct_delivered() : 0;
		return fika_wb_divider( 'q', 'From our inbox' ) .
			'<img src="' . esc_url( home_url( '/wp-content/uploads/2026/11/fika-email-owe-inbox.gif' ) ) . '" width="440" alt="' . esc_attr( $alt ) . '" class="fika-t" style="display:block;width:100%;max-width:440px;height:auto;margin:14px auto 0;border:0;border-radius:18px;">' .
			'<div style="' . $f . 'margin:10px 0 0;text-align:center;color:#6c7b9c;font-size:13.5px;line-height:1.5;">Sent to us after their deliveries' . ( $n > 0 ? '. ' . number_format( $n ) . ' orders delivered since August.' : '.' ) . '</div>'; // owner, 3 Nov: the GIF shows several customers now
	}
	// has this email a Fika Club account (not shop staff)?
	function fika_wb_is_member( $email ) {
		$u = get_user_by( 'email', $email );
		return $u && ! user_can( $u, 'manage_woocommerce' );
	}
	// email 2: "We owe you $5" in store credit (members claim it, everyone else joins Fika Club to claim it)
	function fika_wb_email2( $email, $p, $code, $expires ) {
		list( $amount, $min ) = fika_wb_owe();
		$f     = 'font-family:Outfit,\'Helvetica Neue\',Helvetica,Arial,sans-serif;';
		$serif = 'font-family:\'Fanwood Text\',Georgia,\'Times New Roman\',serif;font-variant:small-caps;font-weight:400;';
		$name  = $p['name'];
		$owe   = fika_wb_money( $amount );
		$until = wp_date( 'l j F', $expires );
		// the deadline pill: "Only 2 days left", counted from today to the last day (Beirut dates)
		$days  = max( 0, (int) round( ( strtotime( wp_date( 'Y-m-d', $expires ) ) - strtotime( wp_date( 'Y-m-d' ) ) ) / DAY_IN_SECONDS ) );
		$left  = $days > 1 ? 'Only ' . $days . ' days left' : ( 1 === $days ? 'Only 1 day left' : 'Last day' );
		$link  = add_query_arg( 'fika_coupon', rawurlencode( $code ), home_url( '/mix-your-own/' ) );
		// members go straight to the shop; everyone else to the sign-up page (owner, 3 Nov 2026), the code remembered on
		// the way (the ?fika_coupon handler keeps the #register part)
		$member = fika_wb_is_member( $email );
		$claim  = $member ? 'Claim my ' . $owe . ' store credit' : 'Join for free & claim my ' . $owe;
		$go     = $member ? $link : add_query_arg( 'fika_coupon', rawurlencode( $code ), wc_get_page_permalink( 'myaccount' ) ) . '#register';
		$ffgo   = $member && defined( 'FIKA_DROPS_DROP_ID' ) ? add_query_arg( array( 'fika_coupon' => rawurlencode( $code ), 'fika_add' => FIKA_DROPS_DROP_ID, 'bag' => 'open' ), home_url( '/ready-mix/' ) ) : $go;
		$body  =
			// the personal line right under the IOU (the voucher picture is the heading)
			'<div style="' . $serif . 'text-align:center;color:#004aad;font-size:27px;line-height:1.15;margin:2px 0 10px;">' . ( $name ? esc_html( $name ) . ', don&rsquo;t lose your ' : 'Don&rsquo;t lose your ' ) . $owe . '</div>' .
			'<p style="text-align:center;">' . ( $member
				// loss aversion (owner, 4 Nov 2026): the $5 is already theirs, and it runs out
				? 'It is already in your Fika Club account as store credit. Use it on anything you like before it runs out, or it is gone.'
				: 'It is already yours. Join Fika Club to keep it before it runs out, or it is gone. Joining is free and takes a minute, and your earlier orders join too.' ) . '</p>' .
			'<p style="text-align:center;margin:0 !important;"><span style="' . $f . 'display:inline-block;padding:7px 16px;border-radius:999px;background:#fff4d9;border:1px solid #f2b84b;color:#8a5a00;font-size:14px;font-weight:600;">' . esc_html( $left ) . ' · ends ' . esc_html( wp_date( 'l', $expires ) ) . '</span></p>' .
			fika_wb_button( $go, $claim ) .
			// each part's button says what that part shows; members go to the shop, everyone else to the sign-up page
			fika_wb_claim( $email, $name, array( $go ) ) .
			// Fan Favourites (picture and button): members to Ready Mix with the bag already in their bag (owner, 4 Nov
			// 2026), everyone else to the sign-up page
			fika_wb_ff_box( $ffgo, array( $ffgo, $member ? 'Get it for {price}' : 'Join for free & get it for {price}' ) ) .
			fika_wb_quote() .
			fika_wb_button( $go, $member ? 'Get obsessed too' : 'Join & get obsessed too' ) . // echoes "Im obsessed with them" in the inbox
			fika_wb_small( $email );
		$html = WC()->mailer()->wrap_message( 'We owe you ' . $owe, $body );
		// the IOU voucher is the heading
		$html = str_replace( '>We owe you ' . $owe . '</h1>', '><img src="' . esc_url( home_url( '/wp-content/uploads/2026/11/fika-email-owe-voucher2.png' ) ) . '" width="480" alt="Fika owes you ' . esc_attr( $owe ) . ' in store credit" class="fika-t" style="display:block;width:100%;max-width:480px;height:auto;margin:0 auto;border:0;"></h1>', $html );
		$pre  = '<div class="fika-pre" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' . esc_html( ( $member ? 'Your ' . $owe . ' store credit runs out on ' . $until . '. Don\'t lose it.' : 'Your ' . $owe . ' runs out on ' . $until . '. Join Fika Club for free to keep it.' ) ) . str_repeat( '&#847;&zwnj;&nbsp;', 40 ) . '</div>';
		$html = preg_replace( '/(<body\b[^>]*>)/i', '$1' . $pre, $html, 1 );
		return array( ( $name ? $name . ', we owe you ' : 'We owe you ' ) . $owe . ' 🍬', $html );
	}
	function fika_wb_coupon( $email, $note = 'Win-back email 2', $send = '' ) {
		$set                  = fika_wb_settings();
		list( $amount, $min ) = fika_wb_owe();
		$code                 = 'IOU' . (int) $amount . '-' . strtoupper( wp_generate_password( 6, false, false ) );
		// the code lasts until 11:59 pm (Beirut) on the last day, so "ends Thursday 5 November" in the email holds all day
		$expires              = ( new DateTime( '@' . ( time() + (int) $set['valid_days'] * DAY_IN_SECONDS ) ) )->setTimezone( wp_timezone() )->setTime( 23, 59, 59 )->getTimestamp();
		$c                    = new WC_Coupon();
		$c->set_code( $code );
		$c->set_discount_type( 'fixed_cart' );
		$c->set_amount( $amount );
		// the minimum is ours, not WooCommerce's minimum spend: the code waits until the bag reaches it
		// (fika_wb_sync_code) instead of failing with an error and being lost
		$c->update_meta_data( '_fika_owe', 1 ); // an email-2 code: store credit, needs a Fika Club account
		$c->update_meta_data( '_fika_owe_min', $min );
		$c->set_individual_use( true );
		$c->set_usage_limit( 1 );
		$c->set_usage_limit_per_user( 1 );
		$c->set_email_restrictions( array( $email ) );
		$c->set_date_expires( $expires );
		$c->set_description( $note . ' for ' . $email );
		// the email's $5 also follows the account: once logged in (any phone or computer) it is found by email
		$owed                         = (array) get_option( 'fika_wb_owed', array() );
		$owed[ strtolower( $email ) ] = $code;
		update_option( 'fika_wb_owed', $owed, false );
		if ( $send ) {
			$c->update_meta_data( '_fika_et_send', $send ); // an order with this code counts for this email (tracking)
		}
		$c->save();
		return array( $code, $expires );
	}
	function fika_wb_send( $email, $p, $which ) {
		// tracking (fika-email-tracking.php): this send's id, put on the code and on the email's links
		$sid = function_exists( 'fika_et_new_send' ) ? fika_et_new_send( '2' === $which ? 'wb2' : 'wb1', $email ) : '';
		if ( '2' === $which ) {
			list( $code, $expires ) = fika_wb_coupon( $email, 'Win-back email 2', $sid );
			list( $subject, $html ) = fika_wb_email2( $email, $p, $code, $expires );
		} else {
			list( $subject, $html ) = fika_wb_email1( $email, $p );
		}
		if ( $sid ) {
			$html = fika_et_tag( $html, $sid );
		}
		// a mail server that stops answering fails this email after 30 seconds instead of freezing the whole run:
		// Timeout covers connecting and each read, Timelimit the wait for an answer (PHPMailer's default is 300 s,
		// which is why a held email used to freeze the run until the PHP time limit ended it)
		fika_wb_trace( $email, 'start', true );
		$short = function ( $m ) {
			$m->Timeout = 30;
			$m->getSMTPInstance()->Timelimit = 30;
			// each step of the conversation with the mail server goes into the trace (not the email's own lines)
			$m->SMTPDebug   = 3;
			$m->Debugoutput = function ( $line ) {
				$line = trim( preg_replace( '/\s+/', ' ', (string) $line ) );
				if ( preg_match( '/^(SERVER -> CLIENT|Connection:|SMTP|CLIENT -> SERVER: (EHLO|STARTTLS|AUTH|MAIL|RCPT|DATA|QUIT|\.$))/', $line ) ) {
					fika_wb_trace( '', preg_replace( '/^(CLIENT -> SERVER: AUTH \S+).*/', '$1 ...', substr( $line, 0, 160 ) ) );
				}
			};
		};
		add_action( 'phpmailer_init', $short, 999 );
		$ok = WC()->mailer()->send( $email, $subject, $html );
		remove_action( 'phpmailer_init', $short, 999 );
		fika_wb_trace( '', $ok ? 'done: sent' : 'done: mail error' );
		return $ok ? ( isset( $code ) ? $code : true ) : false;
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
	// a code remembered from an email link (cookie fika_coupon, 14 days), else the email-2 code owed to the logged-in
	// customer's email (fika_wb_owed), so the $5 is there after signing up or logging in on another phone
	function fika_wb_pending_code() {
		if ( isset( $_COOKIE['fika_coupon'] ) && '' !== $_COOKIE['fika_coupon'] ) {
			return wc_format_coupon_code( wp_unslash( $_COOKIE['fika_coupon'] ) );
		}
		if ( is_user_logged_in() ) {
			$owed = (array) get_option( 'fika_wb_owed', array() );
			$k    = strtolower( wp_get_current_user()->user_email );
			return isset( $owed[ $k ] ) ? wc_format_coupon_code( $owed[ $k ] ) : '';
		}
		return '';
	}
	function fika_wb_forget_code() {
		$code = fika_wb_pending_code();
		wc_setcookie( 'fika_coupon', '', time() - HOUR_IN_SECONDS );
		unset( $_COOKIE['fika_coupon'] );
		$owed = (array) get_option( 'fika_wb_owed', array() );
		$left = array_filter( $owed, function ( $c ) use ( $code ) {
			return wc_format_coupon_code( $c ) !== $code;
		} );
		if ( count( $left ) !== count( $owed ) ) {
			update_option( 'fika_wb_owed', $left, false );
		}
	}
	// an email-2 code ("we owe you"): marked _fika_owe (codes made before 4 Nov only have a minimum)
	function fika_wb_is_owe( $c ) {
		return $c instanceof WC_Coupon && ( $c->get_meta( '_fika_owe' ) || (float) $c->get_meta( '_fika_owe_min' ) > 0 );
	}
	// an email-2 code that can still be used: [ coupon, minimum ] or null
	function fika_wb_owe_code( $code ) {
		$c = $code ? new WC_Coupon( $code ) : null;
		if ( ! $c || ! $c->get_id() || ! fika_wb_is_owe( $c ) ) {
			return null;
		}
		$exp = $c->get_date_expires();
		if ( ( $exp && $exp->getTimestamp() < time() ) || ( $c->get_usage_limit() && $c->get_usage_count() >= $c->get_usage_limit() ) ) {
			return null;
		}
		return array( $c, (float) $c->get_meta( '_fika_owe_min' ) );
	}
	// what the sweets in the cart cost (the code's minimum is in dollars: $2.80 per 100 g)
	function fika_wb_cart_value( $cart = null ) {
		$cart = $cart ? $cart : ( function_exists( 'WC' ) ? WC()->cart : null );
		$v    = 0.0;
		foreach ( $cart ? $cart->get_cart() : array() as $it ) {
			$v += (float) $it['data']->get_price() * (int) $it['quantity'];
		}
		return $v;
	}
	// email 2's $5 is for Fika Club members (owner, 3 Nov 2026: "to claim their store credit they need an account"):
	// signed in (joining at checkout with the emailed code signs them in). The code's email check stays WooCommerce's.
	function fika_wb_member_ok( $c ) {
		return ! fika_wb_is_owe( $c ) || is_user_logged_in();
	}
	// the remembered code goes on once the bag reaches the code's minimum (email 2: $15), comes off again
	// (quietly) if the bag drops below it, and stays remembered until an order uses it or it cannot be used any more
	function fika_wb_sync_code() {
		$code = fika_wb_pending_code();
		if ( ! $code || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$c   = new WC_Coupon( $code );
		$exp = $c->get_id() ? $c->get_date_expires() : null;
		if ( ! $c->get_id() || ( $exp && $exp->getTimestamp() < time() ) || ( $c->get_usage_limit() && $c->get_usage_count() >= $c->get_usage_limit() ) ) {
			fika_wb_forget_code();
			return;
		}
		$on = WC()->cart->has_discount( $code );
		$ok = ! WC()->cart->is_empty() && fika_wb_cart_value() >= (float) $c->get_meta( '_fika_owe_min' ) && fika_wb_member_ok( $c );
		if ( $ok && ! $on ) {
			fika_wb_apply_code( $code );
		} elseif ( ! $ok && $on ) {
			WC()->cart->remove_coupon( $code );
		}
	}
	// the daily run
	function fika_wb_run( $limit = 10 ) {
		$set = fika_wb_settings();
		if ( 'off' === $set['mode'] ) {
			return 0;
		}
		// one run at a time (two runs at once could both send to the same person); a lock left by a run that was cut
		// off is taken over after 15 minutes
		$lock = (int) get_option( 'fika_winback_lock', 0 );
		if ( $lock && time() - $lock < 15 * MINUTE_IN_SECONDS ) {
			return 0;
		}
		delete_option( 'fika_winback_lock' );
		if ( ! add_option( 'fika_winback_lock', time(), '', false ) ) {
			return 0;
		}
		$test = array_filter( array_map( 'strtolower', array_map( 'trim', preg_split( '/[\s,;]+/', (string) $set['test_emails'] ) ) ) );
		$st   = fika_wb_state();
		$sent = 0;
		foreach ( fika_wb_audience() as $email => $p ) {
			if ( $limit <= $sent ) {
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
			if ( ! $due || ( '2' === $due && empty( $set['email2'] ) ) ) {
				continue;
			}
			// never twice, even at the cost of none: the email is marked as sent (and saved) BEFORE it goes out, so a run
			// cut off part-way (a stuck mail server, the PHP time limit) or a send that fails is never sent again
			$st[ $email ]               = isset( $st[ $email ] ) ? $st[ $email ] : array( 'base' => (int) $p['last'] );
			$st[ $email ][ 's' . $due ] = time();
			update_option( 'fika_winback_state', $st, false );
			if ( $sent ) {
				sleep( 4 ); // a short gap between emails, so the mail server is not flooded
			}
			$ok = fika_wb_send( $email, $p, $due );
			if ( ! $ok ) {
				fika_wb_log( $email, 'Email ' . $due . ' not sent (mail error), not retried' );
				break; // the mail server is struggling: stop this batch, the next one tries the next people
			}
			if ( is_string( $ok ) ) {
				$st[ $email ]['coupon'] = $ok;
				update_option( 'fika_winback_state', $st, false );
			}
			fika_wb_log( $email, '1' === $due ? 'Email 1 sent (it’s been a while)' : 'Email 2 sent ($' . (int) fika_wb_owe()[0] . ' store credit, code ' . ( is_string( $ok ) ? $ok : '' ) . ')' );
			$sent++;
		}
		update_option( 'fika_winback_state', $st, false );
		delete_option( 'fika_winback_lock' );
		return $sent;
	}
}

// ---------- sending: small batches, one an hour ----------
if ( ! function_exists( 'fika_wb_sent_24h' ) ) {
	// win-back emails sent in the last 24 hours, from the log. The hello@ mailbox may send about 100 emails in any
	// 24 hours (Hostinger's limit, order emails included), so win-back keeps to 50 and leaves the rest for orders
	function fika_wb_sent_24h() {
		$n = 0;
		foreach ( (array) get_option( 'fika_winback_log', array() ) as $l ) {
			if ( (int) $l['t'] < time() - DAY_IN_SECONDS ) {
				break;
			}
			$n += preg_match( '/^Email \d (sent|not sent)/', $l['what'] ) ? 1 : 0;
		}
		return $n;
	}
	// when the next batch may go: in an hour, kept between 10:00 and 20:00 Beirut
	function fika_wb_next_slot() {
		$next = new DateTime( '@' . ( time() + HOUR_IN_SECONDS ) );
		$next->setTimezone( new DateTimeZone( 'Asia/Beirut' ) );
		$h = (int) $next->format( 'G' );
		if ( $h >= 20 ) {
			$next->modify( 'tomorrow 10:00' );
		} elseif ( $h < 10 ) {
			$next->modify( 'today 10:00' );
		}
		return max( time() + 10 * MINUTE_IN_SECONDS, $next->getTimestamp() );
	}
	// a batch is waiting to go (only pending ones count: the batch that is running right now is "in progress")
	function fika_wb_batch_pending() {
		return function_exists( 'as_get_scheduled_actions' ) && as_get_scheduled_actions( array( 'hook' => 'fika_winback_batch', 'group' => 'fika', 'status' => 'pending', 'per_page' => 1 ), 'ids' );
	}
}
add_action( 'fika_winback_batch', function () {
	$set = fika_wb_settings();
	if ( 'off' === $set['mode'] ) {
		return;
	}
	// sends only from WordPress's own cron (wp-cron.php). WooCommerce's scheduler sometimes runs a job through an
	// "async request" instead, and the web server ends that request part-way (every frozen batch, 17-21 Oct, had
	// started that way; every batch run from cron finished). Such a run just plans the batch again in a minute
	if ( ! wp_doing_cron() ) {
		if ( function_exists( 'as_schedule_single_action' ) && ! fika_wb_batch_pending() ) {
			as_schedule_single_action( time() + MINUTE_IN_SECONDS, 'fika_winback_batch', array(), 'fika' );
		}
		return;
	}
	$h    = (int) wp_date( 'G', null, new DateTimeZone( 'Asia/Beirut' ) );
	$left = 50 - fika_wb_sent_24h();
	$open = $h >= 10 && $h < 20 && $left > 0;
	// the next batch is planned BEFORE this one sends, so a run cut off part-way (a mail server that stops
	// answering, the PHP time limit) does not stop the chain
	if ( function_exists( 'as_schedule_single_action' ) && ! fika_wb_batch_pending() ) {
		as_schedule_single_action( fika_wb_next_slot(), 'fika_winback_batch', array(), 'fika' );
	}
	$sent = $open ? fika_wb_run( min( 10, $left ) ) : 0;
	// a batch that could send but found nobody due ends the chain until the next daily start
	if ( $open && ! $sent && function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'fika_winback_batch', array(), 'fika' );
	}
} );
// once a day: start the batches (those then keep going an hour apart while people are due)
add_action( 'fika_winback_daily', function () {
	if ( ! fika_wb_batch_pending() ) {
		do_action( 'fika_winback_batch' );
	}
} );
// a one-off "go live" at a set time (scheduled with Action Scheduler as fika_winback_golive): switches the mode to
// Live and starts the batches
add_action( 'fika_winback_golive', function () {
	$set         = fika_wb_settings();
	$set['mode'] = 'live';
	update_option( 'fika_winback_settings', $set, false );
	fika_wb_log( '-', 'Switched to Live (scheduled)' );
	do_action( 'fika_winback_batch' );
} );
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
			// remembered for 14 days; it goes on as soon as the bag is big enough (fika_wb_sync_code)
			wc_setcookie( 'fika_coupon', $code, time() + 14 * DAY_IN_SECONDS );
			$_COOKIE['fika_coupon'] = $code;
			fika_wb_sync_code();
		}
		wp_safe_redirect( remove_query_arg( 'fika_coupon' ) );
		exit;
	}
	// Unsubscribe
	if ( isset( $_GET['fika_unsub'], $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$email = strtolower( sanitize_email( wp_unslash( $_GET['fika_unsub'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok    = $email ? hash_equals( substr( wp_hash( 'fika-wb|' . $email ), 0, 20 ), (string) wp_unslash( $_GET['k'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification
		// opening the link only asks; the button (a POST) unsubscribes, so mail scanners that open every link
		// (Outlook Safe Links and the like) cannot unsubscribe anyone
		if ( $ok && 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			wp_die( '<h1 style="font-family:sans-serif;color:#004aad">Unsubscribe from Fika emails?</h1><p style="font-family:sans-serif">You will stop getting our &ldquo;it&rsquo;s been a while&rdquo; emails at ' . esc_html( $email ) . '. Order emails still arrive as usual.</p>' .
				'<form method="post" action="' . esc_url( add_query_arg( array( 'fika_unsub' => rawurlencode( $email ), 'k' => (string) wp_unslash( $_GET['k'] ) ), home_url( '/' ) ) ) . '"><button type="submit" style="font:600 15px sans-serif;padding:12px 26px;border:0;border-radius:999px;background:#004aad;color:#fff;cursor:pointer">Unsubscribe me</button></form>' . // phpcs:ignore
				'<p style="font-family:sans-serif"><a href="' . esc_url( home_url( '/' ) ) . '">No, take me back to Fika</a></p>', 'Fika', array( 'response' => 200 ) );
		}
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
// the remembered code follows the bag: on when it is big enough, off (quietly) when it is not (the shop's Checkout
// button empties the cart and fills it again from the bag, and every page load re-checks)
add_action( 'woocommerce_add_to_cart', 'fika_wb_sync_code', 30 );
add_action( 'woocommerce_cart_item_removed', 'fika_wb_sync_code', 30 );
add_action( 'woocommerce_after_cart_item_quantity_update', 'fika_wb_sync_code', 30 );
add_action( 'woocommerce_cart_loaded_from_session', 'fika_wb_sync_code', 30 );
// a code with our minimum: no "coupon applied" notice (it goes on by itself), and refused below the minimum with a
// clear message (only seen if someone types it in with a smaller bag)
add_filter( 'woocommerce_coupon_message', function ( $msg, $msg_code, $coupon ) {
	return function_exists( 'fika_wb_is_owe' ) && fika_wb_is_owe( $coupon ) ? '' : $msg;
}, 10, 3 );
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon, $discounts ) {
	$min = $coupon instanceof WC_Coupon ? (float) $coupon->get_meta( '_fika_owe_min' ) : 0;
	$obj = is_object( $discounts ) && method_exists( $discounts, 'get_object' ) ? $discounts->get_object() : null;
	if ( $valid && fika_wb_is_owe( $coupon ) && $obj instanceof WC_Cart && ! is_user_logged_in() ) {
		throw new Exception( 'Your ' . fika_wb_money( $coupon->get_amount() ) . ' is waiting in your Fika Club account: log in, or create your free account, and it comes off by itself.' );
	}
	if ( $valid && $min > 0 && $obj instanceof WC_Cart && fika_wb_cart_value( $obj ) < $min ) {
		throw new Exception( 'Your ' . fika_wb_money( $coupon->get_amount() ) . ' comes off on ' . fika_wb_money( $min ) . ' of sweets or more: fill your bag and it is added by itself.' );
	}
	return $valid;
}, 10, 3 );
// an order that used the remembered code: forget it
foreach ( array( 'woocommerce_store_api_checkout_order_processed', 'woocommerce_checkout_order_processed' ) as $fika_wb_hook ) {
	add_action( $fika_wb_hook, function ( $order ) {
		$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
		$code  = fika_wb_pending_code();
		if ( $code && $order instanceof WC_Order && in_array( $code, array_map( 'wc_format_coupon_code', $order->get_coupon_codes() ), true ) ) {
			fika_wb_forget_code();
		}
	}, 30 );
}

// the waiting $5 (email 2) shown to the customer: in the bag, above the totals ("add 300 g more", a progress bar, or
// "comes off at checkout"), and at the checkout while the bag is still under the minimum (with a link back to the bag)
add_action( 'wp_footer', function () {
	if ( is_admin() || ! function_exists( 'WC' ) ) {
		return;
	}
	$owe = fika_wb_owe_code( fika_wb_pending_code() );
	if ( ! $owe || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
		return;
	}
	list( $c, $min ) = $owe;
	$d = array(
		'amt'      => fika_wb_money( $c->get_amount() ),
		'credit'   => (float) $c->get_amount(),
		'min'      => (int) round( $min / 0.028 ),
		'checkout' => function_exists( 'is_checkout' ) && is_checkout(),
		'member'   => is_user_logged_in(),
		'join'     => wc_get_page_permalink( 'myaccount' ) . '#register',
		'welcome'  => isset( $_GET['fika_owe'] ) && 'welcome' === $_GET['fika_owe'], // phpcs:ignore WordPress.Security.NonceVerification
	);
	?>
<style id="fika-owe-css">
.fk-owe { margin: 0 0 12px; padding: 10px 12px 11px; border-radius: 14px; background: #fdeaf2; border: 1.5px dashed #004aad; font-family: 'Outfit', Arial, sans-serif; color: #004aad; font-size: 14px; line-height: 1.35; text-align: left; }
.fk-owe b { font-weight: 700; }
.fk-owe .bar { height: 7px; margin-top: 8px; border-radius: 999px; background: #fff; overflow: hidden; }
.fk-owe .bar i { display: block; height: 100%; border-radius: 999px; background: #004aad; transition: width .35s ease; }
.fk-owe.done { border-style: solid; background: #eaf7ef; border-color: #1f8a4c; color: #1f6b3d; }
.fk-owe a { color: #004aad; font-weight: 600; text-decoration: underline; }
.fk-owe-co { max-width: 680px; margin: 0 0 18px; }
#fkOweRow { color: #1f8a4c; font-weight: 600; }
</style>
<script id="fika-owe-js">
(function () {
  var D = <?php echo wp_json_encode( $d ); ?>;
  function grams() { var g = 0; try { var b = (JSON.parse(localStorage.getItem('fika_bag_v1') || '{}').bag) || {}; Object.keys(b).forEach(function (k) { g += +b[k] || 0; }); } catch (e) {} return g; }
  function more(g) { return D.min - g >= 1000 ? (Math.round((D.min - g) / 100) / 10) + ' kg' : (Math.ceil((D.min - g) / 100) * 100) + ' g'; }
  function txt(g) {
    if (!D.member) return 'We owe you <b>' + D.amt + '</b>: it is waiting for you in Fika Club. <a href="' + D.join + '">Create your free account</a> to claim it (your bag stays saved), or <a href="/my-account/#login">log in</a>.';
    var hi = D.welcome ? '<b>Welcome to Fika Club!</b> ' : '';
    if (!g) return hi + 'We owe you <b>' + D.amt + '</b>: add any sweets and it comes off at checkout.';
    return hi + (g >= D.min ? '✓ Your ' + D.amt + ' store credit is taken off your total.' : 'We owe you <b>' + D.amt + '</b>: add <b>' + more(g) + '</b> more and it comes off at checkout.');
  }
  // the bag's own totals: a "Fika store credit -$5.00" line above Total, and the Total less the credit (never below
  // $0: WooCommerce takes off at most what the sweets cost), redone whenever the bag redraws its totals
  function money(x) { return '$' + x.toFixed(2); }
  function credit(done) {
    var sub = document.getElementById('mxSub'), tot = document.getElementById('mxTot'), row = document.getElementById('fkOweRow');
    if (!sub || !tot) return;
    var s = parseFloat((sub.textContent || '').replace(/[^0-9.]/g, '')) || 0;
    if (!done || !s) { if (row) { row.remove(); tot.textContent = money(s); } return; }
    var off = Math.min(D.credit, s);
    if (!row) { row = document.createElement('div'); row.id = 'fkOweRow'; row.className = 'mx-sum'; tot.parentNode.parentNode.insertBefore(row, tot.parentNode); }
    var t = '<span>Fika store credit</span><span>&minus;' + money(off) + '</span>';
    if (row.innerHTML !== t) row.innerHTML = t;
    if (tot.textContent !== money(s - off)) tot.textContent = money(s - off);
  }
  function draw() {
    var g = grams(), done = g > 0 && g >= D.min && D.member;
    credit(done);
    // in the bag, above the totals
    var df = document.querySelector('#mxDrawer .mx-df');
    if (df) {
      var el = document.getElementById('fkOwe');
      if (!el) { el = document.createElement('div'); el.id = 'fkOwe'; el.className = 'fk-owe'; df.insertBefore(el, df.firstChild); }
      el.className = 'fk-owe' + (done ? ' done' : '');
      el.innerHTML = '<div>' + txt(g) + '</div>' + (D.member && g < D.min ? '<div class="bar"><i style="width:' + Math.min(100, Math.round(g / D.min * 100)) + '%"></i></div>' : '');
    }
    // at the checkout, while the bag is under the minimum
    if (D.checkout) {
      var co = document.getElementById('fkOweCo'), main = document.querySelector('.wc-block-checkout__main, .wp-block-woocommerce-checkout-fields-block');
      if (done || (!g && D.member)) { if (co) co.remove(); return; }
      if (main) {
        if (!co) { co = document.createElement('div'); co.id = 'fkOweCo'; co.className = 'fk-owe fk-owe-co'; main.insertBefore(co, main.firstChild); }
        co.innerHTML = '<div>' + txt(g) + (D.member && g < D.min ? ' <a href="/mix-your-own/?bag=open">Add sweets</a>' : '') + '</div>' + (D.member && g < D.min ? '<div class="bar"><i style="width:' + Math.min(100, Math.round(g / D.min * 100)) + '%"></i></div>' : '');
      }
    }
  }
  window.addEventListener('fikabag', function () { setTimeout(draw, 0); });
  // at the checkout, the code's line reads "Fika store credit", like the bag (not the code "iou5-…")
  try {
    if (D.checkout && window.wc && wc.blocksCheckout && wc.blocksCheckout.registerCheckoutFilters) {
      wc.blocksCheckout.registerCheckoutFilters('fika-owe', { coupons: function (cs) {
        return (cs || []).map(function (c) { return /^iou\d+-/i.test(c.code || '') ? Object.assign({}, c, { label: 'Fika store credit' }) : c; });
      } });
    }
  } catch (e) {}
  // the bag rewrites its subtotal on every change (also without a fikabag event): follow it
  (function watch(n) {
    var sub = document.getElementById('mxSub');
    if (!window.MutationObserver) return;
    if (!sub) { if (n < 20) setTimeout(function () { watch(n + 1); }, 500); return; }
    new MutationObserver(function () { draw(); }).observe(sub, { childList: true, characterData: true, subtree: true });
  })(0);
  window.addEventListener('storage', function (e) { if (e.key === 'fika_bag_v1') draw(); });
  var tries = 0, t = setInterval(function () { draw(); if (++tries > 20) clearInterval(t); }, 500);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', draw); else draw();
})();
</script>
	<?php
}, 60 );

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
	$set['owe']         = max( 1, min( 50, absint( isset( $in['owe'] ) ? $in['owe'] : 5 ) ) );
	$set['owe_min']     = max( 0, absint( isset( $in['owe_min'] ) ? $in['owe_min'] : 0 ) );
	$set['valid_days']  = max( 1, absint( isset( $in['valid_days'] ) ? $in['valid_days'] : 2 ) );
	$set['first_days']  = max( 1, absint( isset( $in['first_days'] ) ? $in['first_days'] : 30 ) );
	$set['second_days'] = max( 1, absint( isset( $in['second_days'] ) ? $in['second_days'] : 14 ) );
	$set['email2']      = empty( $in['email2'] ) ? 0 : 1;
	$set['products']    = implode( ',', array_slice( array_filter( array_map( 'absint', isset( $in['products'] ) ? (array) $in['products'] : array() ) ), 0, 4 ) );
	update_option( 'fika_winback_settings', $set, false );
	$msg = 'saved';
	if ( ! empty( $in['preview'] ) ) {
		$to = sanitize_email( isset( $in['preview_to'] ) ? $in['preview_to'] : '' );
		if ( is_email( $to ) ) {
			// the preview greets: the name typed in, else the recipient's account or latest order, else a name taken
			// from the address (alan.aboulhosn@... -> Alan), else "Sara"
			$name = sanitize_text_field( isset( $in['preview_name'] ) ? $in['preview_name'] : '' );
			// "Show it as this customer": their name and Fika Club progress, sent only to the preview address
			$as   = strtolower( sanitize_email( isset( $in['preview_as'] ) ? $in['preview_as'] : '' ) );
			$aud  = $as ? fika_wb_audience() : array();
			$as   = isset( $aud[ $as ] ) ? $as : '';
			if ( $as && ! $name ) {
				$name = $aud[ $as ]['name'];
			}
			$pu   = $name || $as ? false : get_user_by( 'email', $to );
			if ( $pu ) {
				$name = get_user_meta( $pu->ID, 'first_name', true );
			}
			if ( ! $name ) {
				$po   = wc_get_orders( array( 'billing_email' => $to, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
				$name = $po ? $po[0]->get_billing_first_name() : '';
			}
			if ( ! $name && ! $as && preg_match( '/^([a-z]{2,})/i', $to, $mm ) ) {
				$name = ucfirst( strtolower( $mm[1] ) );
			}
			$name = fika_wb_first_name( $name );
			$p    = array( 'name' => $name ? $name : 'Sara', 'last' => time() - 40 * DAY_IN_SECONDS, 'since' => time() - 40 * DAY_IN_SECONDS );
			if ( '2' === $in['preview'] ) {
				list( $code, $exp )     = fika_wb_coupon( $to, 'Win-back preview' );
				list( $subject, $html ) = fika_wb_email2( $to, $p, $code, $exp );
			} else {
				list( $subject, $html ) = fika_wb_email1( $to, $p, $as );
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
	<p style="max-width:760px">Customers who haven&rsquo;t ordered for <?php echo (int) $set['first_days']; ?> days get <strong>email 1</strong> (&ldquo;It&rsquo;s been a while&rdquo;: what&rsquo;s new, your news, &ldquo;Pick your mix&rdquo;).
	<?php echo (int) $set['second_days']; ?> days later, if they still haven&rsquo;t ordered, <strong>email 2</strong>: &ldquo;Fika owes you <?php echo esc_html( fika_wb_money( $set['owe'] ) ); ?>&rdquo; in store credit (&ldquo;don&rsquo;t lose your <?php echo esc_html( fika_wb_money( $set['owe'] ) ); ?>&rdquo;), on <?php echo $set['owe_min'] > 0 ? 'an order of ' . esc_html( fika_wb_money( $set['owe_min'] ) ) . ' or more' : 'any order'; ?>, for <?php echo (int) $set['valid_days']; ?> days (until 11:59 pm on the last day).
	The <?php echo esc_html( fika_wb_money( $set['owe'] ) ); ?> is for Fika Club members: members&rsquo; buttons open the shop with the <?php echo esc_html( fika_wb_money( $set['owe'] ) ); ?> already taken off in the bag; everyone else&rsquo;s open the sign-up page (no confirmation email needed when they come from the email), then the shop.
	Email 2 also shows their Fika Club progress and the limited edition bag. Ordering again starts over.
	Sent in small batches, 10:00&ndash;20:00 Beirut time, at most 50 a day. Orders came back after these emails: <strong><?php echo (int) get_option( 'fika_winback_won', 0 ); ?></strong>.</p>
	<?php if ( 'saved' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
	<?php if ( 'preview' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Saved, and the preview is on its way.</p></div><?php endif; ?>
	<?php if ( 'preview-failed' === $msg ) : ?><div class="notice notice-error is-dismissible"><p>The preview could not be sent.</p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="fika_wb_save"><?php wp_nonce_field( 'fika_wb_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Sending</th><td>
				<label><input type="radio" name="mode" value="test" <?php checked( 'test', $set['mode'] ); ?>> <strong>Test</strong>: only to the addresses below</label><br>
				<label><input type="radio" name="mode" value="live" <?php checked( 'live', $set['mode'] ); ?>> <strong>Live</strong>: to every customer who is due</label><br>
				<label><input type="radio" name="mode" value="off" <?php checked( 'off', $set['mode'] ); ?>> Off</label>
				<?php $golive = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( 'fika_winback_golive', array(), 'fika' ) : false; ?>
				<?php if ( $golive ) : ?><p class="description" style="color:#004aad"><strong>Goes Live by itself on <?php echo esc_html( wp_date( 'j F, H:i', $golive, new DateTimeZone( 'Asia/Beirut' ) ) ); ?> Beirut time</strong>, then sends to everyone due in small batches (10 an hour, 10:00-20:00, at most 50 in 24 hours).</p><?php endif; ?></td></tr>
			<tr><th>Email 2</th><td><label><input type="checkbox" name="email2" value="1" <?php checked( ! empty( $set['email2'] ) ); ?>> Send email 2 (&ldquo;Fika owes you <?php echo esc_html( fika_wb_money( $set['owe'] ) ); ?>&rdquo; store credit) as well</label><p class="description">Off: only email 1 goes out, also in Live mode. Customers who got email 1 receive email 2 once this is on and <?php echo (int) $set['second_days']; ?> days have passed. Each email 2 creates its own personal code (one use, only for that customer&rsquo;s email).</p></td></tr>
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
			<tr><th>Email 2: store credit</th><td>$ <input type="number" name="owe" min="1" max="50" value="<?php echo (int) $set['owe']; ?>" style="width:70px"> off an order of at least $ <input type="number" name="owe_min" min="0" value="<?php echo (int) $set['owe_min']; ?>" style="width:70px"> of sweets (0 = any order; $15 = about 550 g; 1 kg = $28), valid <input type="number" name="valid_days" min="1" value="<?php echo (int) $set['valid_days']; ?>" style="width:70px"> days</td></tr>
			<tr><th>Timing</th><td>Email 1 after <input type="number" name="first_days" min="1" value="<?php echo (int) $set['first_days']; ?>" style="width:70px"> days without an order, email 2 <input type="number" name="second_days" min="1" value="<?php echo (int) $set['second_days']; ?>" style="width:70px"> days after email 1</td></tr>
			<tr><th>Send a preview</th><td><input type="email" name="preview_to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"> <input type="text" name="preview_name" placeholder="Name to greet (optional)"><br>
				<input type="email" name="preview_as" class="regular-text" placeholder="Show it as this customer (their email, optional)" style="margin-top:6px">
				<button class="button" name="preview" value="1">Email 1</button> <button class="button" name="preview" value="2">Email 2</button>
				<p class="description">A preview of email 2 creates a real <?php echo esc_html( fika_wb_money( $set['owe'] ) ); ?> code for that address (it shows the member or non-member version, as that address is). &ldquo;Show it as this customer&rdquo; uses that customer&rsquo;s name and Fika Club progress; the preview still goes only to the address above.</p></td></tr>
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
			list( $subject, $html ) = fika_wb_email2( 'sara@example.com', $p, 'IOU5-PREVIEW', time() + 2 * DAY_IN_SECONDS );
		} else {
			list( $subject, $html ) = fika_wb_email1( 'sara@example.com', $p );
		}
		return array( 'subject' => $subject, 'html' => apply_filters( 'woocommerce_mail_content', $e->style_inline( $html ) ) );
	} ) );
} );

// email 2, people without an account: the button opens the sign-up page (owner, 3 Nov 2026). There: a banner about
// the $5 and their email already filled in (the code only works for the email we wrote to). Signing up sends the
// usual "confirm your email" link; tapping it logs them in and opens Mix your own with the bag and the $5 waiting
// (instead of the home page). Logging in with an existing account goes there too.
add_action( 'wp_footer', function () {
	if ( is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}
	$owe = fika_wb_owe_code( fika_wb_pending_code() );
	if ( ! $owe ) {
		return;
	}
	list( $c ) = $owe;
	$emails    = $c->get_email_restrictions();
	$amt       = fika_wb_money( $c->get_amount() );
	$exp       = $c->get_date_expires();
	$until     = $exp ? wp_date( 'l', $exp->getTimestamp() ) : '';
	$sent      = isset( $_GET['fika-check'] ) || isset( $_POST['register'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$msg       = $sent
		? '<b>Last step for your ' . $amt . ':</b> tap the link we just emailed you. It logs you in, and your ' . $amt . ' is waiting in your bag.'
		: '<b>We owe you ' . $amt . '.</b> Create your free Fika Club account below and it is yours' . ( $until ? ', until ' . $until . ' 11:59 pm' : '' ) . '. Your earlier orders join too. Already have one? Log in above.';
	?>
<style id="fika-owe-join-css">
.fk-owe-join { margin: 0 0 18px; padding: 12px 14px; border-radius: 14px; background: #fdeaf2; border: 1.5px dashed #004aad; font-family: 'Outfit', Arial, sans-serif; color: #004aad; font-size: 15px; line-height: 1.4; }
.fk-owe-join b { font-weight: 700; }
</style>
<script id="fika-owe-join-js">
(function () {
  var msg = <?php echo wp_json_encode( $msg ); ?>, email = <?php echo wp_json_encode( 1 === count( $emails ) ? $emails[0] : '' ); ?>;
  function go() {
    // at the top of the sign-up form (the #register link scrolls to it; on phones it sits below the log-in form)
    var form = document.querySelector('form.woocommerce-form-register, form.register');
    if (!form) return;
    if (!document.getElementById('fkOweJoin')) {
      var d = document.createElement('div'); d.id = 'fkOweJoin'; d.className = 'fk-owe-join'; d.innerHTML = msg;
      form.parentNode.insertBefore(d, form);
    }
    var r = document.getElementById('reg_email');
    if (r && !r.value && email) r.value = email;
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
</script>
	<?php
}, 61 );
// after the "confirm your email" link (fika-accounts.php sends it to the home page with a welcome) and after logging
// in: to Mix your own with the bag open, when an email-2 $5 is waiting
add_filter( 'wp_redirect', function ( $to ) {
	if ( false === strpos( (string) $to, 'fika-welcome=' ) || ! is_user_logged_in() || ! function_exists( 'fika_wb_owe_code' ) ) {
		return $to;
	}
	return fika_wb_owe_code( fika_wb_pending_code() ) ? add_query_arg( array( 'bag' => 'open', 'fika_owe' => 'welcome' ), home_url( '/mix-your-own/' ) ) : $to;
} );
add_filter( 'woocommerce_login_redirect', function ( $to, $user ) {
	if ( ! $user instanceof WP_User || user_can( $user, 'edit_posts' ) || ! function_exists( 'fika_wb_owe_code' ) ) {
		return $to;
	}
	$owed = (array) get_option( 'fika_wb_owed', array() );
	$k    = strtolower( $user->user_email );
	$code = isset( $_COOKIE['fika_coupon'] ) && '' !== $_COOKIE['fika_coupon'] ? wc_format_coupon_code( wp_unslash( $_COOKIE['fika_coupon'] ) ) : ( isset( $owed[ $k ] ) ? $owed[ $k ] : '' );
	return fika_wb_owe_code( $code ) ? add_query_arg( 'bag', 'open', home_url( '/mix-your-own/' ) ) : $to;
}, 30, 2 );
// email 2: signing up from the email's button, with the email we wrote to, needs no "confirm your email" step: the
// link came to that inbox, which is the proof (owner, 4 Nov 2026). The email counts as confirmed (earlier orders
// join, as with the confirmation link), the customer is logged in and goes straight to Mix your own with the bag and
// the $5. Anyone else signing up (no email-2 link, or another email) still gets the confirmation email.
add_action( 'woocommerce_created_customer', function ( $uid ) {
	if ( ! isset( $_POST['register'] ) || ! function_exists( 'fika_link_past_orders' ) || empty( $_COOKIE['fika_coupon'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$owe = fika_wb_owe_code( wc_format_coupon_code( wp_unslash( $_COOKIE['fika_coupon'] ) ) );
	$u   = get_userdata( $uid );
	if ( ! $owe || ! $u || ! in_array( strtolower( $u->user_email ), array_map( 'strtolower', $owe[0]->get_email_restrictions() ), true ) ) {
		return;
	}
	fika_link_past_orders( $uid ); // marks the email confirmed, joins earlier orders, updates totals and rewards
	update_user_meta( $uid, 'fika_joined_from', 'winback-2' );
	$GLOBALS['fika_wb_signed_up'] = (int) $uid;
}, 25 ); // after the name and phone (20), before the confirmation email (30), which then is not sent
add_filter( 'woocommerce_registration_auth_new_customer', function ( $auth, $uid ) {
	return ( ! empty( $GLOBALS['fika_wb_signed_up'] ) && (int) $GLOBALS['fika_wb_signed_up'] === (int) $uid ) ? true : $auth;
}, 20, 2 );
add_filter( 'woocommerce_add_success', function ( $msg ) {
	// no "Almost there! Check your email" for them
	return ( ! empty( $GLOBALS['fika_wb_signed_up'] ) && preg_match( '/Almost there|account was created/i', wp_strip_all_tags( (string) $msg ) ) ) ? '' : $msg;
}, 20 );
add_filter( 'woocommerce_registration_redirect', function ( $to ) {
	return empty( $GLOBALS['fika_wb_signed_up'] ) ? $to : add_query_arg( array( 'bag' => 'open', 'fika_owe' => 'welcome' ), home_url( '/mix-your-own/' ) );
}, 99 );

// email 2, members' Fan Favourites link (/ready-mix/?fika_add=<id>&bag=open): one 500 g bag of it goes into the Fika bag
// (localStorage fika_bag_v1, id rw<product>, as the Ready Mix "Add to bag" does), unless it is in there already, and the
// bag opens (?bag=open, fika-store-pages.php). Only the limited edition bag, while it can be bought.
add_action( 'wp_footer', function () {
	if ( is_admin() || ! isset( $_GET['fika_add'] ) || ! defined( 'FIKA_DROPS_DROP_ID' ) || (int) $_GET['fika_add'] !== (int) FIKA_DROPS_DROP_ID ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$p = wc_get_product( FIKA_DROPS_DROP_ID );
	if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() || 'publish' !== $p->get_status() ) {
		return;
	}
	$d = array( 'id' => 'rw' . $p->get_id(), 'name' => $p->get_name(), 'per' => (float) wc_get_price_to_display( $p ) / 5 );
	?>
<script id="fika-owe-add">
(function () {
  var D = <?php echo wp_json_encode( $d ); ?>, st = null;
  try { st = JSON.parse(localStorage.getItem('fika_bag_v1') || 'null'); } catch (e) {}
  st = st || {}; st.bag = st.bag || {}; st.meta = st.meta || {};
  if (!st.bag[D.id]) {
    st.bag[D.id] = 500; st.meta[D.id] = { name: D.name, price: D.per };
    try { localStorage.setItem('fika_bag_v1', JSON.stringify(st)); } catch (e) {}
    try { window.dispatchEvent(new CustomEvent('fikabag', { detail: 'owe' })); } catch (e) {}
  }
  try { var u = new URL(location.href); u.searchParams.delete('fika_add'); history.replaceState(null, '', u.toString()); } catch (e) {}
})();
</script>
	<?php
}, 5 );
