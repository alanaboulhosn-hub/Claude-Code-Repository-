<?php
/**
 * Fika: the pages WooCommerce and WordPress make on their own either get the Fika look or send visitors to our pages.
 * - Product pages (/product/...): Fika header, footer, fonts and bag. "Add to cart" becomes "Add to bag" (the same
 *   bag as the shop, so nothing goes into WooCommerce's cart until Checkout). The breadcrumbs, the category/tag line and
 *   "Related products" are hidden; under the details sits the shop carousel ("More sweets to mix", or the other Ready
 *   Mix bags). The pages stay (reviews, search engines and shared links keep working).
 * - Category and tag pages go to Mix your own with that filter chosen (Sweet, Sour, Gelatin-free); the Ready Mix
 *   category goes to /ready-mix/. A search (?s=...) goes to Mix your own with the search filled in.
 * - The cart page (/cart/) opens the bag on Mix your own instead (quantities are changed in the bag).
 * - A missing page shows the Fika "page not found" banner (pattern 335) with the shop under it; it still answers 404.
 * - Mix your own reads ?f= (sweet, sour, gf) and ?q= (search), and any of our pages opens the bag for ?bag=open.
 * - The WordPress emoji script (loaded from s.w.org) is not loaded on the site.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-store-pages.php
 */

// the synced patterns shared by our pages (wordpress/pages/parts/ids.json)
function fika_sp_part( $key ) {
	$ids = array( 'head' => 239, 'fish' => 240, 'mix' => 241, 'foot' => 242, 'lost' => 335 );
	return do_blocks( '<!-- wp:block {"ref":' . $ids[ $key ] . '} /-->' );
}

// ---------- no emoji script ----------
add_action( 'init', function () {
	if ( is_admin() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'embed_head', 'print_emoji_detection_script' );
} );

// ---------- category, tag, search and cart pages go to our pages ----------
add_action( 'template_redirect', function () {
	$to   = '';
	$code = 301;
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$to   = '/mix-your-own/?bag=open';
		$code = 302;
	} elseif ( is_tax( 'product_cat' ) || is_tax( 'product_tag' ) ) {
		$term = get_queried_object();
		$map  = array( 'ready-mix' => '/ready-mix/', 'sweet' => '/mix-your-own/?f=sweet', 'sour' => '/mix-your-own/?f=sour', 'gelatin-free' => '/mix-your-own/?f=gf' );
		$to   = ( $term && isset( $map[ $term->slug ] ) ) ? $map[ $term->slug ] : '/mix-your-own/';
	} elseif ( is_search() ) {
		$q    = trim( get_search_query( false ) );
		$to   = '/mix-your-own/' . ( '' !== $q ? '?q=' . rawurlencode( $q ) : '' );
		$code = 302;
	}
	if ( $to ) {
		wp_safe_redirect( home_url( $to ), $code );
		exit;
	}
}, 2 );

// ---------- product pages ----------
add_action( 'wp', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	add_filter( 'render_block', 'fika_sp_product_block', 10, 2 );
	add_filter( 'body_class', function ( $c ) {
		$c[] = 'fika-product';
		return $c;
	} );
	add_action( 'wp_body_open', function () {
		echo fika_sp_part( 'head' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo fika_sp_part( 'fish' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}, 5 );
	add_action( 'wp_footer', 'fika_sp_product_footer', 5 );
} );

function fika_sp_is_ready( $pid ) {
	return has_term( 'ready-mix', 'product_cat', $pid );
}

function fika_sp_product_block( $html, $block ) {
	switch ( $block['blockName'] ) {
		case 'woocommerce/breadcrumbs':
		case 'woocommerce/product-meta':
		case 'woocommerce/product-collection':
			return '';
		case 'woocommerce/add-to-cart-form':
			return fika_sp_buy_box();
	}
	return $html;
}

// "Add to bag": candies 100 g at a time, a Ready Mix one 500 g bag at a time (same steps and bag as the shop cards)
function fika_sp_buy_box() {
	$p = wc_get_product( get_the_ID() );
	if ( ! $p ) {
		return '';
	}
	$ready = fika_sp_is_ready( $p->get_id() );
	$shown = in_array( $p->get_catalog_visibility(), array( 'visible', 'catalog' ), true );
	if ( ! $p->is_purchasable() || ! $p->is_in_stock() || ! $shown ) {
		return '<div class="fk-buy fk-buy-off"><p class="fk-buy-unit">Not available right now.</p><a class="fk-buy-view" href="' . esc_url( home_url( $ready ? '/ready-mix/' : '/mix-your-own/' ) ) . '">See what&rsquo;s in the shop</a></div>';
	}
	$price = (float) wc_get_price_to_display( $p );
	$id    = ( $ready ? 'rw' : 'w' ) . $p->get_id();
	$step  = $ready ? 500 : 100;
	$per   = $ready ? $price / 5 : $price; // the bag keeps the price per 100 g
	$unit  = $ready ? 'Add as many bags as you like.' : 'Add as much as you like, 100 g at a time.';
	ob_start();
	?>
<div class="mx-card fk-buy" data-id="<?php echo esc_attr( $id ); ?>" data-name="<?php echo esc_attr( $p->get_name() ); ?>" data-price="<?php echo esc_attr( round( $per, 4 ) ); ?>" data-step="<?php echo (int) $step; ?>">
	<p class="fk-buy-unit"><?php echo esc_html( html_entity_decode( $unit ) ); ?></p>
	<div class="fk-buy-row">
		<button type="button" class="fk-buy-add" data-a="+">Add to bag</button>
		<div class="fk-buy-pill"><button type="button" data-a="-" aria-label="Less">&minus;</button><span class="fk-buy-g" aria-live="polite"></span><button type="button" data-a="+" aria-label="More">+</button></div>
		<a class="fk-buy-view" href="<?php echo esc_url( home_url( '/mix-your-own/?bag=open' ) ); ?>">View bag</a>
	</div>
</div>
	<?php
	return ob_get_clean();
}

function fika_sp_product_footer() {
	$pid   = get_the_ID();
	$ready = fika_sp_is_ready( $pid );
	$id    = ( $ready ? 'rw' : 'w' ) . $pid;
	?>
<script>
document.documentElement.classList.add('fika-product-page'<?php echo $ready ? ", 'fika-ready-product'" : ''; ?>);
window.fikaMxTitle = 'More sweets to mix';
window.fikaMxView = function (list) { return list.filter(function (p) { return p.id !== <?php echo wp_json_encode( $id ); ?>; }); };
window.fikaMxCount = function () { return ''; };
</script>
<style>
body.fika-product { background: #fff; }
body.fika-product .wp-site-blocks { padding-top: 0; }
body.fika-product .wp-site-blocks > .woocommerce.product { background: linear-gradient(180deg, #fdeaf2 0, #fdeaf2 340px, #fff 340px); }
body.fika-product main.wp-block-group { max-width: var(--fk-max, 1320px); margin: 0 auto !important; padding: 150px var(--fk-side, 10vw) 30px !important; box-sizing: border-box; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a; }
body.fika-product main.wp-block-group > * { max-width: none !important; }
body.fika-product main .wp-block-columns { gap: clamp(28px, 5vw, 80px); align-items: flex-start; margin: 0 !important; }
body.fika-product .woocommerce-product-gallery { margin: 0; }
body.fika-product .woocommerce-product-gallery__image img { box-sizing: border-box; max-width: 100%; border-radius: 26px; border: 1.5px solid #e2e9f6; background: #fff; box-shadow: 0 14px 40px rgba(0, 74, 173, .10); }
body.fika-product .wp-block-post-title { margin: 6px 0 10px; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: clamp(38px, 3.8vw, 68px); line-height: 1.1; color: #004aad; }
body.fika-product .wp-block-woocommerce-product-price .wc-block-components-product-price { font-family: 'Outfit', Arial, sans-serif; font-weight: 600; font-size: 26px; color: #1b2a4a; }
body.fika-product .wp-block-post-excerpt { margin: 10px 0 0; font: 17px/1.6 'Outfit', Arial, sans-serif; color: #33415c; }
body.fika-product .wp-block-post-excerpt p { margin: 0; }
body.fika-product .fk-buy { margin: 22px 0 10px; display: block; }
body.fika-product .fk-buy-unit { margin: 0 0 14px; font: 15px/1.5 'Outfit', Arial, sans-serif; color: #5a6782; }
body.fika-product .fk-buy-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; }
body.fika-product .fk-buy-add, body.fika-product .fk-buy-pill { height: 54px; border-radius: 999px; background: #004aad; color: #fff; border: 0; }
body.fika-product .fk-buy-add { padding: 0 38px; font: 22px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .05em; cursor: pointer; transition: background .15s, transform .15s; }
body.fika-product .fk-buy-add:hover { background: #003a8a; }
body.fika-product .fk-buy-add:active { transform: scale(.97); }
body.fika-product .fk-buy-pill { display: none; align-items: center; padding: 0 6px; }
body.fika-product .fk-buy-pill button { width: 42px; height: 42px; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .16); color: #fff; font: 600 22px/1 'Outfit', Arial, sans-serif; cursor: pointer; }
body.fika-product .fk-buy-pill button:hover { background: rgba(255, 255, 255, .3); }
body.fika-product .fk-buy-g { min-width: 92px; text-align: center; font: 600 18px/1 'Outfit', Arial, sans-serif; }
body.fika-product .fk-buy-view { display: none; font: 600 16px 'Outfit', Arial, sans-serif; color: #004aad; text-decoration: underline; text-underline-offset: 4px; }
body.fika-product .fk-buy.has .fk-buy-add { display: none; }
body.fika-product .fk-buy.has .fk-buy-pill { display: inline-flex; }
body.fika-product .fk-buy.has .fk-buy-view, body.fika-product .fk-buy-off .fk-buy-view { display: inline; }
/* details and reviews */
body.fika-product .wp-block-woocommerce-product-details { margin-top: 50px !important; }
body.fika-product .woocommerce-tabs ul.tabs { display: flex; gap: 8px; padding: 0 !important; margin: 0 0 20px !important; border: 0 !important; }
body.fika-product .woocommerce-tabs ul.tabs::before, body.fika-product .woocommerce-tabs ul.tabs li::before, body.fika-product .woocommerce-tabs ul.tabs li::after { display: none !important; }
body.fika-product .woocommerce-tabs ul.tabs li { margin: 0 !important; padding: 0 !important; border: 0 !important; background: none !important; }
body.fika-product .woocommerce-tabs ul.tabs li a { display: inline-flex !important; align-items: center; box-sizing: border-box; height: 42px; padding: 0 18px !important; line-height: 1 !important; border-radius: 999px; border: 2px solid #004aad; color: #004aad !important; text-decoration: none;
  font: 19px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .04em; }
body.fika-product .woocommerce-tabs ul.tabs li.active a { background: #004aad; color: #fff !important; }
body.fika-product .woocommerce-Tabs-panel { font: 16px/1.6 'Outfit', Arial, sans-serif; color: #33415c; }
body.fika-product .woocommerce-Tabs-panel h2, body.fika-product #reply-title { font: 26px/1.2 'Bebas Neue', Impact, sans-serif; letter-spacing: .03em; color: #004aad; }
body.fika-product #review_form input[type=text], body.fika-product #review_form input[type=email], body.fika-product #review_form textarea { box-sizing: border-box; width: 100%; border: 1.5px solid #c9d4ea; border-radius: 14px; padding: 12px 14px; font: 15px 'Outfit', Arial, sans-serif; }
body.fika-product #review_form .submit { border: 0; border-radius: 999px; padding: 14px 30px; background: #004aad; color: #fff; font: 20px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .05em; cursor: pointer; }
body.fika-product .comment-form-rating .stars a { color: #004aad; }
/* the shop carousel under the details */
html.fika-product-page #shop { padding-top: 30px; }
html.fika-product-page:not(.fika-ready-product) #rmPage, html.fika-ready-product #shop { display: none !important; }
html.fika-ready-product #rmGrid .mx-card[data-id="<?php echo esc_attr( $id ); ?>"] { display: none; }
@media (max-width: 781px) {
  body.fika-product .wp-site-blocks > .woocommerce.product { background: linear-gradient(180deg, #fdeaf2 0, #fdeaf2 220px, #fff 220px); }
  body.fika-product main.wp-block-group { padding: 120px 20px 20px !important; }
  body.fika-product .fk-buy-add { flex: 1; }
}
</style>
<script>
(function () {
  var box = document.querySelector('.fk-buy[data-id]');
  if (!box) return;
  var KEY = 'fika_bag_v1', id = box.getAttribute('data-id'), step = +box.getAttribute('data-step') || 100,
      price = +box.getAttribute('data-price') || 0, name = box.getAttribute('data-name');
  function read() { try { var s = JSON.parse(localStorage.getItem(KEY) || 'null'); return { bag: (s && s.bag) || {}, meta: (s && s.meta) || {} }; } catch (e) { return { bag: {}, meta: {} }; } }
  function gText(g) { return g >= 1000 ? (g / 1000) + ' kg' : g + 'g'; }
  function show() { var g = +read().bag[id] || 0; box.classList.toggle('has', g > 0); box.querySelector('.fk-buy-g').textContent = gText(g) + ' in bag'; }
  function set(g) {
    var st = read();
    if (g > 0) { st.bag[id] = g; st.meta[id] = { name: name, price: price }; } else { delete st.bag[id]; delete st.meta[id]; }
    try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {}
    try { window.dispatchEvent(new CustomEvent('fikabag', { detail: 'product' })); } catch (e) {}
    show();
  }
  box.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-a]');
    if (!b) return;
    var g = +read().bag[id] || 0, add = b.getAttribute('data-a') === '+';
    set(add ? g + step : Math.max(0, g - step));
    if (add) { try { if (typeof window.fbq === 'function') window.fbq('track', 'AddToCart', { value: price * step / 100, currency: 'USD', content_name: name }); } catch (e) {} }
  });
  box.querySelector('.fk-buy-view').addEventListener('click', function (e) {
    var cart = document.querySelector('.fika-cart');
    if (cart) { e.preventDefault(); cart.click(); }
  });
  window.addEventListener('fikabag', function (e) { if (e.detail !== 'product') show(); });
  window.addEventListener('storage', function (e) { if (e.key === KEY) show(); });
  show();
})();
</script>
	<?php
	echo fika_sp_part( 'mix' );  // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $ready ) {
		echo '<script>(function () { var h = document.querySelector("#rmPage .mx-h1"); if (h) h.textContent = "More Ready Mixes"; })();</script>';
	}
	echo fika_sp_part( 'foot' ); // phpcs:ignore WordPress.Security.EscapeOutput
}

// ---------- page not found: the Fika banner and the shop, still a 404 ----------
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	status_header( 404 );
	nocache_headers();
	add_filter( 'body_class', function ( $c ) {
		$c[] = 'fika-404';
		return $c;
	} );
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Page not found &middot; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
<style>body.fika-404 { margin: 0; background: #fff; } body.fika-404 #rmPage { display: none !important; } body.fika-404 #shop { padding-top: 40px; }</style>
</head>
<body <?php body_class(); ?>>
	<?php
	wp_body_open();
	echo '<script>window.fikaMxTitle = "Pick a few sweets instead"; window.fikaMxCount = function () { return ""; };</script>';
	echo fika_sp_part( 'head' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo fika_sp_part( 'lost' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo fika_sp_part( 'fish' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo fika_sp_part( 'mix' );  // phpcs:ignore WordPress.Security.EscapeOutput
	echo fika_sp_part( 'foot' ); // phpcs:ignore WordPress.Security.EscapeOutput
	wp_footer();
	?>
</body>
</html>
	<?php
	exit;
}, 20 );

// ---------- Mix your own: ?f= and ?q= choose the filter and search; any of our pages: ?bag=open opens the bag ----------
add_action( 'wp_footer', function () {
	?>
<script>
(function () {
  if (!window.URLSearchParams) return;
  var u = new URLSearchParams(location.search), f = u.get('f'), q = u.get('q'), open = u.get('bag') === 'open';
  if (!f && !q && !open) return;
  function go() {
    var chip = f ? document.querySelector('#fsBar .fs-chip[data-f="' + f.replace(/[^a-z]/g, '') + '"]') : null;
    if (chip) chip.click();
    var box = q ? document.getElementById('fsQ') : null;
    if (box) { box.value = q; box.dispatchEvent(new Event('input')); }
    var cart = open ? document.querySelector('.fika-cart') : null;
    if (cart) cart.click();
    ['f', 'q', 'bag'].forEach(function (k) { u.delete(k); });
    var s = u.toString();
    try { history.replaceState(null, '', location.pathname + (s ? '?' + s : '') + location.hash); } catch (e) {}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(go, 0); }); else setTimeout(go, 0);
})();
</script>
	<?php
}, 50 );
