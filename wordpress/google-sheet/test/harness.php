<?php
// stand-ins for WordPress / WooCommerce, fed from a JSON file of orders (wc/v3 format, dates in Beirut time)
const DAY_IN_SECONDS = 86400;
$GLOBALS['opts'] = []; $GLOBALS['routes'] = [];
function add_action($h,$f){ if($h==='rest_api_init') $f(); }
function register_rest_route($ns,$r,$a){ $GLOBALS['routes'][$r]=$a; }
function get_option($k){ return $GLOBALS['opts'][$k] ?? false; }
function add_option($k,$v){ if(!isset($GLOBALS['opts'][$k])) $GLOBALS['opts'][$k]=$v; }
function wp_generate_password($n){ return substr(str_repeat('k',$n),0,$n); }
function current_user_can($c){ return false; }
function nocache_headers(){}
function has_term($t,$tax,$pid){ return in_array($pid,[94,95,96]); }
function get_the_title($id){ return [94=>'Sweet &amp; Sour Mix',95=>'Sour Mix',96=>'Sweet Mix',999=>'New Candy'][$id] ?? "#$id"; }
class WP_Error { function __construct(public $code,public $msg,public $data){} }
class Item { function __construct(public $li){} function get_product_id(){return $this->li['product_id'];} function get_quantity(){return $this->li['quantity'];}
  function get_meta($k){ foreach($this->li['meta_data'] as $m) if($m['key']===$k) return $m['value']; return ''; } }
class Order { function __construct(public $o){} function get_id(){return $this->o['id'];} function get_customer_note(){return $this->o['customer_note'];}
  function get_date_created(){ return new DateTime($this->o['date_created'], new DateTimeZone('Asia/Beirut')); }
  function get_items(){ $r=[]; foreach($this->o['line_items'] as $li) $r[$li['id']]=new Item($li); return $r; }
  function get_qty_refunded_for_item($id){ return $this->o['refunded'][$id] ?? 0; } }
function wc_get_orders($a){
  [$s,$e]=explode('...',$a['date_created']); $out=[];
  foreach($GLOBALS['orders'] as $o){ $t=(new DateTime($o['date_created'],new DateTimeZone('Asia/Beirut')))->getTimestamp();
    if($t>=$s && $t<=$e && in_array('wc-'.$o['status'],$a['status'])) $out[]=new Order($o); }
  return $out;
}
class Req { function __construct(public $p,public $h){} function get_param($k){return $this->p[$k]??null;} function get_header($k){return $this->h[$k]??null;} }
if (getenv('MIXES')) define('FIKA_DS_MIXES', json_decode(getenv('MIXES'), true));
$GLOBALS['orders'] = json_decode(file_get_contents($argv[1]), true);
require 'snippet.php';
$r=$GLOBALS['routes']['/daily-sales'];
if (getenv('HDR')!==false && !$r['permission_callback'](new Req([], ['x-fika-key'=>getenv('HDR')]))) { echo '{"code":"rest_forbidden"}'; exit(3); }
$key=fika_ds_key();
$checks = [
  'no key'    => $r['permission_callback'](new Req([], [])),
  'wrong key' => $r['permission_callback'](new Req([], ['x-fika-key'=>'nope'])),
  'right key' => $r['permission_callback'](new Req([], ['x-fika-key'=>$key])),
  'bad dates' => get_class($r['callback'](new Req(['from'=>'2026-10-22','to'=>'2026-10-01'],[]))),
  'too long'  => get_class($r['callback'](new Req(['from'=>'2026-01-01','to'=>'2026-10-01'],[]))),
  'key admin' => $GLOBALS['routes']['/daily-sales-key']['permission_callback'](),
];
fwrite(STDERR, json_encode($checks)."\n");
echo json_encode($r['callback'](new Req(['from'=>$argv[2],'to'=>$argv[3]],[])), JSON_UNESCAPED_UNICODE);
