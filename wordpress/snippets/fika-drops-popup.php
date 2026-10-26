<?php
/**
 * Fika: "Two new drops" Halloween popup when a visitor arrives (owner, 26 Oct 2026). Switches on and off with the Halloween look.
 * - Offer 1, Limited Edition Drop (sweet & sour, 500 g, $14): the new BUBS Sour Strawberry Vanilla Diamond with
 *   BUBS Wild Berry Pomegranate Ovals (product 108) back for a short while, limited supply.
 * - Offer 2, Skull Mix (500 g, $14): the five BUBS skulls of the shop (products 120 Bubblegum, 124 Cola, 122 Lemon
 *   Raspberry, 118 Fruity Lemon Mix (Mini), 107 Raspberry Salty Licorice).
 * - Halloween: it drops with the Halloween look and shows only while the look is on (fika_hw_on(), snippet 184, which
 *   also clears the page cache when switched). The two bags (1200, 1201) are in the Ready Mix tab only while the look
 *   is on (catalog visibility set on the first request after the switch moves). Night card, Creepster title, the
 *   shop's arched Halloween photo frames. While it is off the preview link still shows it all: ?fika_preview=<key>
 *   (snippet 184), or ?fika_drops=preview for a signed-in shop manager.
 * - When: right on arrival (0.4 s after the page shows), once per visit (sessionStorage fika_drops_seen), on our shop
 *   pages only (home, Mix your own, Ready Mix, About), never on the checkout or My account, and not right after an
 *   order (the "Order confirmed" popup). Closes with x, "Keep browsing", a tap outside or Esc.
 * - The two bags are Ready Mix products, 1200 Limited Edition Drop (the classic Sweet & Sour Mix with the limited
 *   candies) and 1201 Skull Mix, $14 for 500 g, published but hidden from the shop until they go live; "Add to bag"
 *   puts the 500 g bag in the Fika bag like the Ready Mix tab. In the preview the tab lists them first.
 * - Pictures from the shop's own photos (wordpress/images/drops): popup media 1195 (Skull Mix) and 1199 (Limited
 *   Edition: the real Sweet & Sour Mix photo, recoloured bag, limited candies mixed in); product photos 1204, 1205 (pure white table, like the other Ready Mix photos).
 *   The Strawberry Vanilla Diamond has no photo yet: it is the real Tutti Frutti Diamond, recoloured.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-drops-popup.php
 */

if ( ! defined( 'FIKA_DROPS_LIVE' ) ) {
	// true: it drops with the Halloween look (the admin-bar switch, snippet 184). false: only the preview link shows it.
	define( 'FIKA_DROPS_LIVE', true );
}

if ( ! defined( 'FIKA_DROPS_DROP_ID' ) ) {
	define( 'FIKA_DROPS_DROP_ID', 1200 );   // Limited Edition Drop, Ready Mix, $14, 500 g
	define( 'FIKA_DROPS_SKULL_ID', 1201 );  // Skull Mix, Ready Mix, $14, 500 g
}

function fika_drops_img( $name ) {
	return esc_url( content_url( '/uploads/2026/10/' . $name ) );
}

function fika_drops_markup() {
	ob_start();
	?>
<div class="fkd-veil" id="fkdVeil" hidden>
  <div class="fkd-card" role="dialog" aria-modal="true" aria-labelledby="fkdTitle" tabindex="-1">
    <svg class="fkd-web" viewBox="0 0 200 200" aria-hidden="true"><g fill="none" stroke="#f4ecdc" stroke-width="1.1"><path d="M0 0 L200 30 M0 0 L170 110 M0 0 L110 170 M0 0 L30 200"/><path d="M40 6 Q36 20 28 34 Q18 38 6 40"/><path d="M80 12 Q70 40 56 66 Q36 76 12 80"/><path d="M122 18 Q106 60 84 100 Q56 114 18 122"/><path d="M164 24 Q142 82 112 134 Q76 150 24 164"/></g></svg>
    <i class="fkd-bat b1" aria-hidden="true"></i><i class="fkd-bat b2" aria-hidden="true"></i>
    <button type="button" class="fkd-x" aria-label="Close">&times;</button>
    <p class="fkd-kicker">Halloween drop</p>
    <h2 class="fkd-title" id="fkdTitle">Two new drops</h2>
    <div class="fkd-offers">

      <article class="fkd-offer">
        <div class="fkd-pic">
          <img src="<?php echo fika_drops_img( 'fika-drop-limited-edition-mix.webp' ); ?>" width="720" height="745" alt="A purple Fika bag with a Limited Edition sticker, the Sweet &amp; Sour Mix spilling out with Strawberry Vanilla Diamonds and Pomegranate Ovals in it" decoding="async">
          <span class="fkd-badge">Limited edition</span>
        </div>
        <h3 class="fkd-name">Limited Edition Drop</h3>
        <p class="fkd-sub">Sweet &amp; sour mix, 500 g</p>
        <p class="fkd-what">Our classic Sweet &amp; Sour Mix with this season&rsquo;s limited candies in the bag: the <b class="fkd-new">New</b> BUBS Sour Strawberry Vanilla Diamond and <b class="fkd-back">Back</b> BUBS Wild Berry Pomegranate Ovals.</p>
        <div class="fkd-buy"><span class="fkd-price">$14</span><button type="button" class="fkd-add" data-id="<?php echo (int) FIKA_DROPS_DROP_ID; ?>" data-name="Limited Edition Drop">Add to bag</button></div>
      </article>

      <article class="fkd-offer">
        <div class="fkd-pic">
          <img src="<?php echo fika_drops_img( 'fika-drop-skull-mix.webp' ); ?>" width="720" height="745" alt="A black Fika bag with a Skull Mix sticker, the five BUBS skulls pouring out" decoding="async">
          <span class="fkd-badge fkd-badge-new">Halloween offer</span>
        </div>
        <h3 class="fkd-name">Skull Mix</h3>
        <p class="fkd-sub">Five BUBS skulls, 500 g</p>
        <p class="fkd-what">Bubblegum, Cola, Lemon Raspberry, Fruity Lemon Mix and Raspberry Salty Licorice: every BUBS skull in one bag.</p>
        <div class="fkd-buy"><span class="fkd-price">$14</span><button type="button" class="fkd-add" data-id="<?php echo (int) FIKA_DROPS_SKULL_ID; ?>" data-name="Skull Mix">Add to bag</button></div>
      </article>

    </div>
    <button type="button" class="fkd-later">Keep browsing</button>
  </div>
</div>
	<?php
	return ob_get_clean();
}

// the two bags follow the Halloween switch: in the Ready Mix tab while the look is on, hidden again when it is
// switched off or ends (1 Nov). Checked on each request, changed only when the switch moved.
add_action( 'init', function () {
	if ( ! FIKA_DROPS_LIVE || ! function_exists( 'fika_hw_end_ts' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$want = ( get_option( 'fika_hw_on' ) && time() < fika_hw_end_ts() ) ? 'visible' : 'hidden';
	if ( get_option( 'fika_drops_visibility' ) === $want ) {
		return;
	}
	foreach ( array( FIKA_DROPS_DROP_ID, FIKA_DROPS_SKULL_ID ) as $id ) {
		$p = wc_get_product( $id );
		if ( $p && $p->get_catalog_visibility() !== $want ) {
			$p->set_catalog_visibility( $want );
			$p->save();
		}
	}
	update_option( 'fika_drops_visibility', $want, false );
	if ( function_exists( 'fika_pi_clear' ) ) {
		fika_pi_clear(); // the in-page product list and the page cache
	} else {
		do_action( 'litespeed_purge_all' );
	}
}, 20 );

function fika_drops_show() {
	if ( is_admin() || ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us' ) ) ) ) {
		return false;
	}
	// the private Halloween preview link (snippet 184, ?fika_preview=<key>), or a shop manager with ?fika_drops=preview
	$preview = ( function_exists( 'fika_hw_preview' ) && fika_hw_preview() )
		|| ( isset( $_GET['fika_drops'] ) && 'preview' === $_GET['fika_drops'] && current_user_can( 'manage_woocommerce' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return $preview || ( FIKA_DROPS_LIVE && function_exists( 'fika_hw_on' ) && fika_hw_on() );
}

// the Ready Mix tab: while the two bags are still hidden from the shop, the preview lists them first, as they will be
add_action( 'wp_head', function () {
	if ( ! fika_drops_show() || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$extra = array();
	foreach ( array( FIKA_DROPS_DROP_ID, FIKA_DROPS_SKULL_ID ) as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || 'publish' !== $p->get_status() || 'hidden' !== $p->get_catalog_visibility() ) {
			continue; // visible products are in the list already
		}
		$res = rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/products/' . $id ) );
		if ( ! $res->is_error() && 200 === $res->get_status() ) {
			$extra[] = rest_get_server()->response_to_data( $res, false );
		}
	}
	if ( $extra ) {
		echo '<script>window.fikaProductsData=' . wp_json_encode( $extra, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '.concat(window.fikaProductsData||[]);</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 2 );

add_action( 'wp_footer', function () {
	if ( ! fika_drops_show() ) {
		return;
	}
	?>
<style id="fika-drops-css">
.fkd-veil { position: fixed; inset: 0; z-index: 99990; display: flex; align-items: center; justify-content: center; padding: 16px;
  background: rgba(5, 3, 12, .66); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); opacity: 0; transition: opacity .3s ease; }
.fkd-veil[hidden] { display: none; }
.fkd-veil.on { opacity: 1; }
.fkd-card { position: relative; width: 100%; max-width: 800px; max-height: calc(100vh - 32px); overflow: auto; overscroll-behavior: contain; box-sizing: border-box;
  background: radial-gradient(ellipse 70% 42% at 50% -8%, rgba(255, 123, 28, .20), transparent 72%), #120c1f;
  border: 1px solid rgba(255, 123, 28, .35); border-radius: 26px; box-shadow: 0 0 0 6px rgba(43, 28, 71, .35), 0 26px 70px rgba(0, 0, 0, .65);
  padding: 24px 26px 16px; text-align: center; font-family: 'Outfit', Arial, sans-serif; color: #f4ecdc;
  transform: translateY(14px) scale(.97); transition: transform .35s cubic-bezier(.2, 1.2, .4, 1); }
.fkd-veil.on .fkd-card { transform: none; }
.fkd-card:focus { outline: 0; }
.fkd-web { position: absolute; top: 0; left: 0; width: 150px; height: 150px; opacity: .28; pointer-events: none; }
.fkd-bat { position: absolute; width: 38px; height: 19px; background: #07040d; pointer-events: none; filter: drop-shadow(0 0 4px rgba(255, 140, 50, .8));
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 32'%3E%3Cpath d='M32 9c-1.2 0-2 .9-2.4 2.1L28.4 9l-.9 3.3C24 7.6 16.6 4.3 8 5.6c3.3 1.7 5 4.4 4.9 7.4C9.6 11.6 5 11.9 0 14.9c5.6.1 10.4 2.6 12.6 6.6 1.5-2.4 4.3-3.4 6.6-2.3.4-2.4 2.9-3.7 5.2-2.7 1.9.9 3.7 3.6 7.6 9.5 3.9-5.9 5.7-8.6 7.6-9.5 2.3-1 4.8.3 5.2 2.7 2.3-1.1 5.1-.1 6.6 2.3 2.2-4 7-6.5 12.6-6.6-5-3-9.6-3.3-12.9-1.9-.1-3 1.6-5.7 4.9-7.4-8.6-1.3-16 2-19.5 6.7L35.6 9l-1.2 2.1C34 9.9 33.2 9 32 9z'/%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 32'%3E%3Cpath d='M32 9c-1.2 0-2 .9-2.4 2.1L28.4 9l-.9 3.3C24 7.6 16.6 4.3 8 5.6c3.3 1.7 5 4.4 4.9 7.4C9.6 11.6 5 11.9 0 14.9c5.6.1 10.4 2.6 12.6 6.6 1.5-2.4 4.3-3.4 6.6-2.3.4-2.4 2.9-3.7 5.2-2.7 1.9.9 3.7 3.6 7.6 9.5 3.9-5.9 5.7-8.6 7.6-9.5 2.3-1 4.8.3 5.2 2.7 2.3-1.1 5.1-.1 6.6 2.3 2.2-4 7-6.5 12.6-6.6-5-3-9.6-3.3-12.9-1.9-.1-3 1.6-5.7 4.9-7.4-8.6-1.3-16 2-19.5 6.7L35.6 9l-1.2 2.1C34 9.9 33.2 9 32 9z'/%3E%3C/svg%3E") center / contain no-repeat;
  animation: fkdFloat 3.4s ease-in-out infinite; }
.fkd-bat.b1 { top: 26px; left: 19%; }
.fkd-bat.b2 { top: 58px; right: 17%; width: 28px; height: 14px; animation-delay: -1.6s; }
@keyframes fkdFloat { 0%, 100% { transform: translateY(0) scaleY(1); } 25% { transform: translateY(-5px) scaleY(.6); } 50% { transform: translateY(-2px) scaleY(1); } 75% { transform: translateY(-6px) scaleY(.6); } }
.fkd-x { position: absolute; top: 12px; right: 12px; z-index: 2; width: 38px; height: 38px; border: 0; border-radius: 50%; background: #2b1c47; color: #f4ecdc; font-size: 22px; line-height: 1; cursor: pointer; }
.fkd-x:hover { background: #ff7b1c; color: #1b0d00; }
.fkd-x:focus-visible, .fkd-add:focus-visible, .fkd-later:focus-visible { outline: 2px solid #ff7b1c; outline-offset: 2px; }
.fkd-kicker { position: relative; margin: 0; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 19px; letter-spacing: .04em; color: #a6e35b; }
.fkd-title { position: relative; margin: 0 0 18px; font-family: 'Creepster', 'NF Le Petit Cochon', cursive; font-weight: 400; font-size: 46px; line-height: 1.05; letter-spacing: .04em;
  color: #ff7b1c; text-shadow: 0 0 24px rgba(255, 123, 28, .55), 0 3px 0 #5c2400; }
.fkd-offers { position: relative; display: grid; grid-template-columns: 1fr 1fr; gap: 18px; text-align: left; }
.fkd-offer { display: flex; flex-direction: column; min-width: 0; padding: 12px 12px 14px; border-radius: 20px; background: #1b1230; border: 1px solid #3a2a5c; }
.fkd-pic { position: relative; margin: 0 0 10px; aspect-ratio: 720 / 745; overflow: hidden; background: #efe6d2;
  border: 2px solid #3a2a5c; border-radius: 50% 50% 14px 14px / 30% 30% 14px 14px; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .25); }
.fkd-pic img { display: block; width: 100%; height: 100%; object-fit: cover; }
.fkd-badge { position: absolute; left: 50%; bottom: 10px; transform: translateX(-50%); white-space: nowrap; padding: 5px 12px; border-radius: 999px;
  background: #ff7b1c; color: #1b0d00; font: 700 12px/1.2 'Outfit', Arial, sans-serif; letter-spacing: .06em; text-transform: uppercase; box-shadow: 0 0 16px rgba(255, 123, 28, .55); }
.fkd-badge-new { background: #a6e35b; box-shadow: 0 0 16px rgba(166, 227, 91, .5); }
.fkd-name { margin: 0; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 28px; letter-spacing: .02em; line-height: 1; color: #ff7b1c; }
.fkd-sub { margin: 3px 0 7px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 17px; color: #b9aed0; }
.fkd-what { margin: 0 0 12px; font-size: 14px; line-height: 1.45; color: #f4ecdc; }
.fkd-what b { display: inline-block; margin-right: 3px; padding: 0 6px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; line-height: 18px; vertical-align: 1px; }
.fkd-new { background: #a6e35b; color: #102000; }
.fkd-back { background: #2b1c47; color: #ffb36b; }
.fkd-buy { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.fkd-price { font-family: 'Bebas Neue', Impact, sans-serif; font-size: 32px; line-height: 1; color: #f4ecdc; }
.fkd-add { padding: 11px 20px; border: 0; border-radius: 999px; background: #ff7b1c; color: #1b0d00; font: 700 14px 'Outfit', Arial, sans-serif; letter-spacing: .03em; cursor: pointer;
  box-shadow: 0 0 20px rgba(255, 123, 28, .45); transition: transform .15s ease, background .15s ease; }
.fkd-add:hover { transform: translateY(-2px); background: #e8640c; }
.fkd-later { position: relative; margin: 12px auto 0; display: block; padding: 6px 12px; border: 0; background: none; color: #b9aed0; font: 500 14px 'Outfit', Arial, sans-serif; text-decoration: underline; cursor: pointer; }
/* laptops with short screens: shorter photos, so the whole popup fits without scrolling */
@media (min-width: 641px) and (max-height: 860px) {
  .fkd-card { padding-top: 18px; }
  .fkd-title { font-size: 40px; margin-bottom: 12px; }
  .fkd-pic { aspect-ratio: auto; height: max(200px, min(360px, calc(100vh - 440px))); }
  .fkd-pic img { object-position: 50% 52%; }
}
@media (max-width: 640px) {
  .fkd-veil { align-items: flex-end; padding: 0; }
  .fkd-bat.b1 { top: 12px; left: 16%; }
  .fkd-bat.b2 { top: 16px; right: 24%; }
  .fkd-card { max-height: 94vh; border-radius: 24px 24px 0 0; padding: 18px 12px calc(10px + env(safe-area-inset-bottom, 0px)); transform: translateY(40px); }
  .fkd-web { width: 110px; height: 110px; }
  .fkd-kicker { font-size: 16px; }
  .fkd-title { font-size: 34px; margin-bottom: 12px; }
  .fkd-offers { gap: 10px; }
  .fkd-offer { padding: 7px 7px 10px; border-radius: 16px; }
  .fkd-pic { margin-bottom: 7px; border-radius: 50% 50% 10px 10px / 28% 28% 10px 10px; }
  .fkd-badge { bottom: 6px; padding: 3px 8px; font-size: 10px; }
  .fkd-name { font-size: 21px; }
  .fkd-sub { font-size: 14px; margin: 2px 0 5px; }
  .fkd-what { font-size: 12px; line-height: 1.4; margin-bottom: 9px; }
  .fkd-what b { font-size: 9.5px; padding: 0 4px; line-height: 15px; }
  .fkd-buy { flex-direction: column; align-items: stretch; gap: 7px; }
  .fkd-price { font-size: 26px; }
  .fkd-add { padding: 11px 10px; font-size: 14px; }
}
@media (prefers-reduced-motion: reduce) { .fkd-veil, .fkd-card { transition: none; } .fkd-bat { animation: none; } }
/* the Ready Mix tab: the two Halloween bags carry their label like in the popup */
.mx-card[data-id="rw<?php echo (int) FIKA_DROPS_DROP_ID; ?>"] .mx-img::after, .mx-card[data-id="rw<?php echo (int) FIKA_DROPS_SKULL_ID; ?>"] .mx-img::after {
  content: 'Limited edition'; position: absolute; left: 50%; bottom: 8px; transform: translateX(-50%); z-index: 2; white-space: nowrap; padding: 4px 10px; border-radius: 999px;
  background: #ff7b1c; color: #1b0d00; font: 700 11px/1.2 'Outfit', Arial, sans-serif; letter-spacing: .06em; text-transform: uppercase; box-shadow: 0 0 14px rgba(255, 123, 28, .5); pointer-events: none; }
.mx-card[data-id="rw<?php echo (int) FIKA_DROPS_SKULL_ID; ?>"] .mx-img::after { content: 'Halloween offer'; background: #a6e35b; color: #102000; box-shadow: 0 0 14px rgba(166, 227, 91, .45); }
</style>
	<?php
	echo fika_drops_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
<script id="fika-drops-js">
(function () {
  var veil = document.getElementById('fkdVeil'); if (!veil) return;
  var preview = /fika_drops=preview/.test(location.search);
  try { if (sessionStorage.getItem('fika_drops_seen') === '1' && !preview) return; } catch (e) {}
  var last = null, afterOrder = /[?&]fika_order=/.test(location.search); // just ordered: the "Order confirmed" popup's turn
  function open() {
    if (afterOrder || document.querySelector('.fkoc-veil')) return;
    last = document.activeElement;
    veil.hidden = false; requestAnimationFrame(function () { veil.classList.add('on'); });
    document.documentElement.style.overflow = 'hidden';
    try { sessionStorage.setItem('fika_drops_seen', '1'); } catch (e) {}
    veil.querySelector('.fkd-card').focus({ preventScroll: true }); // the dialog itself, so no ring shows on the x
  }
  function close() {
    veil.classList.remove('on'); document.documentElement.style.overflow = '';
    setTimeout(function () { veil.hidden = true; if (last && last.focus) last.focus({ preventScroll: true }); }, 300);
  }
  veil.addEventListener('click', function (e) { if (e.target === veil || e.target.closest('.fkd-x, .fkd-later')) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !veil.hidden) close(); });
  // Add to bag: the same 500 g bag the Ready Mix tab puts in the Fika bag (localStorage fika_bag_v1, id rw<product>)
  var later = veil.querySelector('.fkd-later');
  [].forEach.call(veil.querySelectorAll('.fkd-add'), function (b) {
    b.addEventListener('click', function () {
      var pid = b.getAttribute('data-id'), name = b.getAttribute('data-name'), id = 'rw' + pid, st = null;
      try { st = JSON.parse(localStorage.getItem('fika_bag_v1') || 'null'); } catch (e) {}
      st = st || {}; st.bag = st.bag || {}; st.meta = st.meta || {};
      st.bag[id] = (st.bag[id] || 0) + 500; st.meta[id] = { name: name, price: 14 / 5 };
      try { localStorage.setItem('fika_bag_v1', JSON.stringify(st)); } catch (e) {}
      try { window.dispatchEvent(new CustomEvent('fikabag', { detail: 'drops' })); } catch (e) {}
      try { var ev = { value: 14, currency: 'USD', content_name: name, content_ids: [String(pid)] }; if (typeof window.fikaTrack === 'function') window.fikaTrack('AddToCart', ev); else if (typeof window.fbq === 'function') window.fbq('track', 'AddToCart', ev); } catch (e) {}
      var n = Math.round(st.bag[id] / 500);
      b.textContent = n > 1 ? n + ' in your bag ✓' : 'In your bag ✓';
      later.textContent = 'See my bag'; later.setAttribute('data-bag', '1');
    });
  });
  // after adding: "See my bag" closes the popup and opens the bag (the bag icon in the header)
  later.addEventListener('click', function () {
    if (!later.getAttribute('data-bag')) return;
    setTimeout(function () { var c = document.querySelector('.fika-cart'); if (c) c.click(); }, 320);
  });
  function go() { setTimeout(open, 400); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
</script>
	<?php
}, 60 );
