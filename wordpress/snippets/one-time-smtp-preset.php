<?php
// Ran once (Code Snippets, scope single-use) on 2026-10-07; kept for reference.
// One-time: preset WP Mail SMTP to send through the Hostinger mailbox (password is entered in the plugin screen)
$o = get_option( 'wp_mail_smtp', array() );
$o = is_array( $o ) ? $o : array();
$o['mail'] = array_merge( isset( $o['mail'] ) ? (array) $o['mail'] : array(), array(
	'from_email'       => 'hello@swedishfikalb.com',
	'from_name'        => 'Fika',
	'mailer'           => 'smtp',
	'return_path'      => true,
	'from_email_force' => true,
	'from_name_force'  => true,
) );
$smtp = isset( $o['smtp'] ) ? (array) $o['smtp'] : array();
$o['smtp'] = array_merge( $smtp, array(
	'host'       => 'smtp.hostinger.com',
	'port'       => 465,
	'encryption' => 'ssl',
	'autotls'    => true,
	'auth'       => true,
	'user'       => 'hello@swedishfikalb.com',
	'pass'       => isset( $smtp['pass'] ) ? $smtp['pass'] : '',
) );
update_option( 'wp_mail_smtp', $o );
