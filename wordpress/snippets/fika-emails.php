<?php
/**
 * Fika: the look of every WooCommerce email (order received, delivered, failed, refunded, notes, account emails)
 * and of the "Your bag is waiting" reminder, matching the website: pink background, white rounded card,
 * the Fika wordmark with candy cartoons on top (an image, so it shows in every email app), small-caps serif
 * headings like the site's Fanwood Text, Outfit for text, blue pill buttons, and the wavy blue footer.
 * - One "Delivery details" block instead of the identical billing and shipping addresses (checkout only asks
 *   for the delivery address).
 * - Friendlier subjects, headings and closing lines. Anything typed in WooCommerce > Settings > Emails for an
 *   email (other than WooCommerce's own default wording) wins over these.
 * Images: media 304 (wordmark, fika-email-logo.png); footer pictures 317-321 (fika-email-foot-*.png).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-emails.php
 */

if ( ! defined( 'FIKA_MAIL_LOGO' ) ) {
	define( 'FIKA_MAIL_LOGO', '/wp-content/uploads/2026/10/fika-email-logo.png' );
	define( 'FIKA_MAIL_WAVE', '/wp-content/uploads/2026/10/fika-email-wave.png' );
}

// ---------- colours, logo and layout settings (as in WooCommerce > Settings > Emails) ----------
foreach ( array(
	'woocommerce_email_header_image'         => function () { return home_url( FIKA_MAIL_LOGO ); },
	'woocommerce_email_header_image_width'   => function () { return 300; },
	'woocommerce_email_header_alignment'     => function () { return 'center'; },
	'woocommerce_email_base_color'           => function () { return '#004aad'; },
	'woocommerce_email_background_color'     => function () { return '#fdeaf2'; },
	'woocommerce_email_body_background_color' => function () { return '#ffffff'; },
	'woocommerce_email_text_color'           => function () { return '#1b2a4a'; },
	'woocommerce_email_footer_text_color'    => function () { return '#fdeaf2'; },
) as $fika_opt => $fika_val ) {
	add_filter( 'pre_option_' . $fika_opt, $fika_val );
}

// ---------- styles (WooCommerce copies them onto each element, so they also work in Gmail) ----------
add_filter( 'woocommerce_email_styles', function ( $css ) {
	$serif = "'Fanwood Text', Georgia, 'Times New Roman', serif";
	$sans  = "'Outfit', 'Helvetica Neue', Helvetica, Arial, sans-serif";
	return $css . "
@import url('https://fonts.googleapis.com/css2?family=Fanwood+Text&family=Outfit:wght@400;500;600&display=swap');
body, #outer_wrapper, #wrapper { background-color: #fdeaf2 !important; background-image: linear-gradient(#fdeaf2, #fdeaf2) !important; }
#wrapper { padding: 28px 12px 0 !important; box-sizing: border-box !important; }
#inner_wrapper, #inner_wrapper > table, #template_header_image { background: transparent !important; background-color: transparent !important; box-shadow: none !important; border: 0 !important; }
#template_header_image { padding: 10px 24px 18px !important; text-align: center !important; }
#template_header_image img { display: inline-block !important; width: 300px !important; max-width: 80% !important; height: auto !important; }
#template_container { background-color: #ffffff !important; border: 0 !important; border-radius: 28px 28px 0 0 !important; box-shadow: none !important; overflow: hidden; }
#template_header { background-color: #ffffff !important; border-radius: 28px 28px 0 0 !important; }
#header_wrapper { padding: 38px 40px 0 !important; text-align: center !important; }
#header_wrapper h1, h1 { font-family: $serif !important; font-variant: small-caps; font-weight: 400 !important; font-size: 36px !important; line-height: 1.15 !important;
  color: #004aad !important; text-align: center !important; margin: 0 !important; letter-spacing: .01em; }
#body_content { background-color: #ffffff !important; }
#body_content_inner_cell { padding: 22px 40px 34px !important; }
#body_content_inner, #body_content_inner p, #body_content_inner td, #body_content_inner th, .td, address, .font-family { font-family: $sans !important; color: #1b2a4a !important; }
#body_content_inner { font-size: 16px !important; line-height: 1.6 !important; }
#body_content_inner p { margin: 0 0 14px !important; }
.email-introduction { padding-bottom: 0 !important; margin-bottom: 0 !important; }
.email-introduction p:last-child { margin-bottom: 0 !important; }
.email-introduction + h2, .email-introduction + .email-order-detail-heading { margin-top: 20px !important; }
h2, .email-order-detail-heading { font-family: $serif !important; font-variant: small-caps; font-weight: 400 !important; font-size: 25px !important; color: #004aad !important; margin: 26px 0 8px !important; }
h2 span, .email-order-detail-heading span { font-family: $sans !important; font-variant: normal; font-size: 13px !important; color: #6c7b9c !important; }
h3 { font-family: $sans !important; font-weight: 600 !important; font-size: 16px !important; color: #004aad !important; }
a { color: #004aad !important; }
.email-order-details .order_item td { border-bottom: 1px solid #f3dbe6 !important; padding-top: 12px !important; padding-bottom: 12px !important; }
.email-order-details th, .email-order-details td { border-color: #f3dbe6 !important; }
.email-order-item-thumbnail img { border-radius: 12px !important; background: #fdeaf2; }
.order-item-data, .order_item td { color: #004aad !important; }
.email-order-item-meta, .wc-item-meta, .wc-item-meta li, .wc-item-meta p { color: #6c7b9c !important; font-size: 13px !important; }
.order-totals td, .order-totals th { color: #1b2a4a !important; }
.order-totals-total td, .order-totals-total th { color: #004aad !important; font-size: 18px !important; }
hr, .email-separator, .email-order-details + br { border-color: #f3dbe6 !important; }
.button, a.button, .button.alt { display: inline-block !important; background: #004aad !important; color: #ffffff !important; border: 0 !important; border-radius: 999px !important;
  padding: 13px 30px !important; font-family: $sans !important; font-weight: 600 !important; font-size: 15px !important; text-decoration: none !important; letter-spacing: .02em; }
.fika-mail-box { background: #fdeaf2; border-radius: 18px; padding: 18px 22px !important; }
.fika-mail-box h2 { margin: 0 0 6px !important; font-size: 22px !important; }
.fika-mail-box p, .fika-mail-box address { margin: 0 !important; font-style: normal; line-height: 1.55 !important; color: #1b2a4a !important; }
.fika-mail-box td { padding: 3px 0 !important; vertical-align: top; font-size: 15px !important; word-break: break-word; overflow-wrap: anywhere; }
#body_content_inner .fika-mail-box td.k { width: 1%; white-space: nowrap; color: #004aad !important; font-weight: 600 !important; padding-right: 18px !important; }
.fika-mail-btn { display: inline-block; background: #004aad; color: #ffffff !important; border-radius: 999px; padding: 14px 32px; font-family: $sans; font-weight: 600; font-size: 15px; text-decoration: none !important; }
#template_footer { background: transparent !important; border: 0 !important; margin: 0 !important; }
#template_footer > tbody > tr > td, #template_footer td td { padding-top: 0 !important; }
#credit { padding: 0 !important; border: 0 !important; color: #fdeaf2 !important; }
#credit p { margin: 0 !important; }
@media screen and (max-width: 600px) {
  #wrapper { padding: 18px 8px 0 !important; }
  #template_header_image { padding: 6px 10px 14px !important; }
  #header_wrapper { padding: 30px 22px 0 !important; }
  #body_content_inner_cell { padding: 18px 20px 28px !important; }
  #header_wrapper h1, h1 { font-size: 30px !important; }
  #body_content_inner, #body_content_inner p, #body_content_inner td, #body_content_inner th { font-size: 15px !important; }
  .email-order-item-meta { font-size: 13px !important; }
  .fika-mail-box { padding: 14px 14px !important; }
  #body_content_inner .fika-mail-box td.k { padding-right: 10px !important; }
}
";
}, 20 );

// ---------- footer: the wave and the blue band, like the website ----------
// Made of pictures (media 317-321): phone apps in dark mode recolour backgrounds but never pictures, so a coloured
// band next to the wave picture came out in two different blues. The three buttons are separate linked pictures.
add_filter( 'woocommerce_email_footer_text', function () {
	$u    = function ( $f ) { return esc_url( home_url( '/wp-content/uploads/2026/10/' . $f ) ); };
	$img  = function ( $f, $w, $h, $alt ) use ( $u ) {
		return '<img src="' . $u( $f ) . '" width="' . $w . '" height="' . $h . '" alt="' . esc_attr( $alt ) . '" style="display:block;width:100%;max-width:' . $w . 'px;height:auto;border:0;outline:none;">';
	};
	$cell = function ( $href, $f, $alt ) use ( $img ) {
		return '<td width="33.33%" style="width:33.33%;padding:0;line-height:0;font-size:0;"><a href="' . esc_url( $href ) . '" style="display:block;text-decoration:none;">' . $img( $f, 200, 64, $alt ) . '</a></td>';
	};
	return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0;border-collapse:collapse;">' .
		'<tr><td bgcolor="#ffffff" style="padding:0;line-height:0;font-size:0;background:#ffffff;">' . $img( 'fika-email-foot-top.png', 600, 116, 'Fika - Swedish pick-and-mix, delivered across Lebanon' ) . '</td></tr>' .
		'<tr><td style="padding:0;line-height:0;font-size:0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;"><tr>' .
		$cell( 'https://wa.me/96179411565', 'fika-email-foot-wa.png', 'WhatsApp 79 411 565' ) .
		$cell( 'mailto:hello@swedishfikalb.com', 'fika-email-foot-mail.png', 'Email hello@swedishfikalb.com' ) .
		$cell( 'https://instagram.com/swedishfika.lb', 'fika-email-foot-ig.png', 'Instagram @swedishfika.lb' ) .
		'</tr></table></td></tr>' .
		'<tr><td style="padding:0;line-height:0;font-size:0;">' . $img( 'fika-email-foot-bottom.png', 600, 62, 'WhatsApp 79 411 565, hello@swedishfikalb.com, @swedishfika.lb. Questions about your order? Just reply to this email.' ) . '</td></tr>' .
		'</table>';
}, 20 );

// ---------- keep the light colours in phone apps that switch emails to dark mode (iPhone Mail, Outlook) ----------
add_filter( 'woocommerce_mail_content', function ( $html ) {
	$meta = '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only">' .
		'<style>:root { color-scheme: light only; supported-color-schemes: light only; }</style>';
	return false !== stripos( $html, '<head>' ) ? preg_replace( '/<head>/i', '<head>' . $meta, $html, 1 ) : $meta . $html;
}, 20 );

// ---------- one "Delivery details" block instead of billing + shipping ----------
add_action( 'woocommerce_email', function ( $mailer ) {
	remove_action( 'woocommerce_email_customer_details', array( $mailer, 'email_addresses' ), 20 );
	add_action( 'woocommerce_email_customer_details', function ( $order, $sent_to_admin = false, $plain_text = false ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$pick  = $order->get_shipping_address_1() ? 'shipping' : 'billing';
		$get   = function ( $f ) use ( $order, $pick ) { return trim( (string) $order->{'get_' . $pick . '_' . $f}() ); };
		$name  = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
		$states = WC()->countries->get_states( $get( 'country' ) );
		$area  = $get( 'state' ) ? ( isset( $states[ $get( 'state' ) ] ) ? $states[ $get( 'state' ) ] : $get( 'state' ) ) : '';
		// "Street, apartment, City, Inside / Outside Beirut" (the city is left out when it repeats the street)
		$place = array_values( array_unique( array_filter( array( $get( 'address_1' ), $get( 'address_2' ), $get( 'city' ), $area ) ) ) );
		$rows  = array(
			'Name'         => $name,
			'Location'     => implode( ', ', $place ),
			'Phone number' => $order->get_billing_phone(),
			'Email'        => $order->get_billing_email(),
		);
		$rows  = array_filter( $rows );
		if ( $plain_text ) {
			echo "\n" . esc_html( wc_strtoupper( 'Delivery details' ) ) . "\n\n";
			foreach ( $rows as $k => $v ) {
				echo esc_html( $k . ': ' . $v ) . "\n";
			}
			return;
		}
		$html = '';
		foreach ( $rows as $k => $v ) {
			$html .= '<tr><td class="k">' . esc_html( $k ) . ':</td><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:26px 0 6px;"><tr><td class="fika-mail-box">' .
			'<h2>Delivery details</h2><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $html . '</table>' .
			'</td></tr></table>';
	}, 20, 3 );
} );

// ---------- order emails: no "Pay with cash upon delivery." note; the delivery row reads "Delivery: $6.00" ----------
add_action( 'woocommerce_email_before_order_table', function () {
	global $wp_filter;
	if ( empty( $wp_filter['woocommerce_email_before_order_table'] ) ) {
		return;
	}
	foreach ( $wp_filter['woocommerce_email_before_order_table']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			if ( is_array( $cb['function'] ) ? ( is_object( $cb['function'][0] ) ? ( 'email_instructions' === $cb['function'][1] ? is_a( $cb['function'][0], 'WC_Gateway_COD' ) : false ) : false ) : false ) {
				remove_action( 'woocommerce_email_before_order_table', $cb['function'], $prio );
			}
		}
	}
}, 1 );
add_filter( 'woocommerce_get_order_item_totals', function ( $rows, $order ) {
	if ( ! doing_action( 'woocommerce_email_order_details' ) || ! isset( $rows['shipping'] ) ) {
		return $rows;
	}
	$cost                      = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
	$rows['shipping']['label'] = 'Delivery:';
	$rows['shipping']['value'] = $cost > 0 ? wc_price( $cost, array( 'currency' => $order->get_currency() ) ) : 'Free';
	$rows['shipping']['meta']  = ''; // WooCommerce's email layout prints the delivery method's name from here
	return $rows;
}, 20, 2 );

// ---------- friendlier subjects, headings and closing lines (WooCommerce settings win when filled in) ----------
if ( ! function_exists( 'fika_mail_copy' ) ) {
	function fika_mail_copy() {
		return array(
			'customer_processing_order' => array( 'subject' => 'Your Fika order #{order_number} is confirmed', 'heading' => 'Thank you for your order!', 'additional_content' => '' ),
			'customer_on_hold_order'    => array( 'subject' => 'We have your Fika order #{order_number}', 'heading' => 'Thank you for your order!', 'additional_content' => '' ),
			'customer_completed_order'  => array( 'subject' => 'Your Fika sweets have arrived', 'heading' => 'Time for fika!', 'additional_content' => 'We hope every bite is a little treat. Tell us what you loved with a review on the candy’s page, and tag us @swedishfika.lb.' ),
			'customer_failed_order'     => array( 'subject' => 'Your Fika order #{order_number} didn’t go through', 'heading' => 'Something went wrong', 'additional_content' => 'Your sweets are not lost: try again from the shop, or message us on WhatsApp 79 411 565 and we will sort it out together.' ),
			'customer_refunded_order'   => array( 'heading' => 'Your refund is on its way', 'additional_content' => 'Questions about your refund? Reply to this email or WhatsApp us on 79 411 565.' ),
			'customer_cancelled_order'  => array( 'heading' => 'Your order was cancelled', 'additional_content' => 'If this is a surprise, reply to this email or WhatsApp us on 79 411 565.' ),
			'customer_note'             => array( 'heading' => 'A note about your order', 'additional_content' => '' ),
			'customer_new_account'      => array( 'subject' => 'Welcome to Fika!', 'heading' => 'Welcome to Fika!', 'additional_content' => 'Every kilo you order now swims you closer to a free one. Follow your progress in your account.' ),
			'customer_reset_password'   => array( 'heading' => 'Reset your password', 'additional_content' => 'Didn’t ask for this? You can safely ignore this email.' ),
			'customer_verify_email'     => array( 'heading' => 'Confirm your email' ),
		);
	}
}
foreach ( fika_mail_copy() as $fika_id => $fika_parts ) {
	foreach ( $fika_parts as $fika_part => $fika_text ) {
		add_filter( 'woocommerce_email_' . $fika_part . '_' . $fika_id, function ( $value, $object = null, $email = null ) use ( $fika_part, $fika_text ) {
			if ( ! is_object( $email ) ) {
				return $value;
			}
			$saved   = isset( $email->settings[ $fika_part ] ) ? trim( (string) $email->settings[ $fika_part ] ) : '';
			$default = method_exists( $email, 'get_default_' . $fika_part ) ? trim( (string) $email->{'get_default_' . $fika_part}() ) : '';
			return ( '' === $saved || $saved === $default ) ? $email->format_string( $fika_text ) : $value;
		}, 20, 3 );
	}
}
