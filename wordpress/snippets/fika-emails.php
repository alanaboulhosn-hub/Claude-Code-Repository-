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
 * Images: media 304 (wordmark, fika-email-logo.png) and 305 (wave, fika-email-wave.png).
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
body, #outer_wrapper, #wrapper { background-color: #fdeaf2 !important; }
#wrapper { padding: 28px 12px 0 !important; }
#inner_wrapper, #inner_wrapper > table, #template_header_image { background: transparent !important; background-color: transparent !important; box-shadow: none !important; border: 0 !important; }
#template_header_image { padding: 10px 24px 18px !important; text-align: center !important; }
#template_header_image img { display: inline-block !important; width: 300px !important; max-width: 80% !important; height: auto !important; }
#template_container { background-color: #ffffff !important; border: 0 !important; border-radius: 28px !important; box-shadow: 0 10px 30px rgba(0, 74, 173, .10) !important; overflow: hidden; }
#template_header { background-color: #ffffff !important; border-radius: 28px 28px 0 0 !important; }
#header_wrapper { padding: 38px 40px 0 !important; text-align: center !important; }
#header_wrapper h1, h1 { font-family: $serif !important; font-variant: small-caps; font-weight: 400 !important; font-size: 36px !important; line-height: 1.15 !important;
  color: #004aad !important; text-align: center !important; margin: 0 !important; letter-spacing: .01em; }
#body_content { background-color: #ffffff !important; }
#body_content_inner_cell { padding: 22px 40px 34px !important; }
#body_content_inner, #body_content_inner p, #body_content_inner td, #body_content_inner th, .td, address, .font-family { font-family: $sans !important; color: #1b2a4a !important; }
#body_content_inner { font-size: 16px !important; line-height: 1.6 !important; }
#body_content_inner p { margin: 0 0 14px !important; }
.email-introduction p:first-child { font-family: $serif !important; font-variant: small-caps; font-size: 21px !important; color: #004aad !important; }
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
.fika-mail-btn { display: inline-block; background: #004aad; color: #ffffff !important; border-radius: 999px; padding: 14px 32px; font-family: $sans; font-weight: 600; font-size: 15px; text-decoration: none !important; }
#template_footer { background: transparent !important; border: 0 !important; }
#credit { padding: 0 !important; border: 0 !important; color: #fdeaf2 !important; }
#credit p { margin: 0 !important; }
@media screen and (max-width: 600px) {
  #header_wrapper { padding: 30px 22px 0 !important; }
  #body_content_inner_cell { padding: 18px 22px 28px !important; }
  #header_wrapper h1, h1 { font-size: 30px !important; }
}
";
}, 20 );

// ---------- footer: the wave and the blue band, like the website ----------
add_filter( 'woocommerce_email_footer_text', function () {
	$wave  = esc_url( home_url( FIKA_MAIL_WAVE ) );
	$serif = "'Fanwood Text',Georgia,'Times New Roman',serif";
	$sans  = "'Outfit','Helvetica Neue',Helvetica,Arial,sans-serif";
	$link  = 'color:#fdeaf2 !important;text-decoration:none;';
	return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:26px;"><tr><td style="padding:0;line-height:0;font-size:0;">' .
		'<img src="' . $wave . '" width="600" height="28" alt="" style="display:block;width:100%;height:28px;border:0;"></td></tr>' .
		'<tr><td bgcolor="#004aad" style="background:#004aad;color:#fdeaf2;text-align:center;font-family:' . $serif . ';font-variant:small-caps;font-size:16px;line-height:1.7;padding:16px 24px 30px;">' .
		'<span style="font-size:24px;color:#ffffff;">Fika</span><br>Swedish pick-and-mix, delivered across Lebanon<br>' .
		'<a href="https://wa.me/96179411565" style="' . $link . '">WhatsApp 79 411 565</a> &nbsp;&middot;&nbsp; <a href="mailto:hello@swedishfikalb.com" style="' . $link . '">hello@swedishfikalb.com</a> &nbsp;&middot;&nbsp; <a href="https://instagram.com/swedishfika.lb" style="' . $link . '">@swedishfika.lb</a><br>' .
		'<span style="font-family:' . $sans . ';font-variant:normal;font-size:12px;color:#c9d4ea;">Questions about your order? Just reply to this email.</span></td></tr></table>';
}, 20 );

// ---------- one "Delivery details" block instead of billing + shipping ----------
add_action( 'woocommerce_email', function ( $mailer ) {
	remove_action( 'woocommerce_email_customer_details', array( $mailer, 'email_addresses' ), 20 );
	add_action( 'woocommerce_email_customer_details', function ( $order, $sent_to_admin = false, $plain_text = false ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$addr  = $order->get_formatted_shipping_address();
		$addr  = $addr ? $addr : $order->get_formatted_billing_address();
		$phone = $order->get_billing_phone();
		$mail  = $order->get_billing_email();
		if ( $plain_text ) {
			echo "\n" . esc_html( wc_strtoupper( 'Delivery details' ) ) . "\n\n" . esc_html( wp_strip_all_tags( str_replace( '<br/>', "\n", $addr ) ) ) . "\n" . esc_html( $phone ) . "\n" . esc_html( $mail ) . "\n";
			return;
		}
		echo '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:26px 0 6px;"><tr><td class="fika-mail-box">' .
			'<h2>Delivery details</h2><address>' . wp_kses_post( $addr ) . '</address>' .
			( $phone ? '<p>' . esc_html( $phone ) . '</p>' : '' ) . ( $mail ? '<p>' . esc_html( $mail ) . '</p>' : '' ) .
			'</td></tr></table>';
	}, 20, 3 );
} );

// ---------- friendlier subjects, headings and closing lines (WooCommerce settings win when filled in) ----------
if ( ! function_exists( 'fika_mail_copy' ) ) {
	function fika_mail_copy() {
		$delivery = 'We are packing your sweets with care. Delivery inside Beirut takes 1–2 business days, outside Beirut 2–3 (no deliveries on Sundays), and you pay in cash when they arrive.';
		return array(
			'customer_processing_order' => array( 'subject' => 'Your Fika order #{order_number} is confirmed', 'heading' => 'Thank you for your order!', 'additional_content' => $delivery ),
			'customer_on_hold_order'    => array( 'subject' => 'We have your Fika order #{order_number}', 'heading' => 'Thank you for your order!', 'additional_content' => $delivery ),
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
