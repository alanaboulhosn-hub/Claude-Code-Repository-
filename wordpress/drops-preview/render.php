<?php
// renders the drops popup's footer output, with stand-ins for WordPress (test browser only)
$H = [];
function add_action($h, $f, $p = 10) { global $H; $H[$h][] = $f; }
function is_admin() { return false; } function is_front_page() { return true; } function is_page($x = null) { return false; }
function content_url($p = '') { return 'https://swedishfikalb.com/wp-content' . $p; }
function esc_url($u) { return $u; }
require $argv[1];
foreach ($H['wp_footer'] as $f) $f();
