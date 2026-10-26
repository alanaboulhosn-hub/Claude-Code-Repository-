<?php
/**
 * Fika: "Two new drops" Halloween popup when a visitor arrives (owner, 26 Oct 2026). Installed, hidden (FIKA_DROPS_LIVE false).
 * - Offer 1, Limited Edition Drop (sweet & sour, 500 g, $14): the new BUBS Sour Strawberry Vanilla Diamond with
 *   BUBS Wild Berry Pomegranate Ovals (product 108) back for a short while, limited supply.
 * - Offer 2, Skull Mix (500 g, $14): the five BUBS skulls of the shop (products 120 Bubblegum, 124 Cola, 122 Lemon
 *   Raspberry, 118 Fruity Lemon Mix (Mini), 107 Raspberry Salty Licorice).
 * - Halloween: it drops with the Halloween look and shows only while the look is on (fika_hw_on(), snippet 184, which
 *   also clears the page cache when switched), once FIKA_DROPS_LIVE is true. Night card, Creepster title, the shop's
 *   arched Halloween photo frames. Before that only the preview link shows it: the Halloween look's private link
 *   (?fika_preview=<key>, snippet 184), or ?fika_drops=preview for a signed-in shop manager.
 * - When: right on arrival (0.4 s after the page shows), once per visit (sessionStorage fika_drops_seen), on our shop
 *   pages only (home, Mix your own, Ready Mix, About), never on the checkout or My account, and not right after an
 *   order (the "Order confirmed" popup). Closes with x, "Keep browsing", a tap outside or Esc.
 * - The pictures (media 1195 fika-drop-skull-mix.webp, 1196 fika-drop-limited-edition.webp) are made from the
 *   shop's own photos: the Fika bag from the Sweet Mix photo, recoloured, with a sticker, and the real candies cut
 *   out of the product photos coming out of it (wordpress/images/drops/compose.py). The Strawberry Vanilla Diamond
 *   has no photo yet: it is the real Tutti Frutti Diamond, recoloured strawberry and vanilla.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-drops-popup.php
 */

if ( ! defined( 'FIKA_DROPS_LIVE' ) ) {
	// false: customers never see it, also with the Halloween look on (the two bags are not products yet, so "Add to
	// bag" cannot work); only the preview link shows it. true: it drops with the Halloween look.
	define( 'FIKA_DROPS_LIVE', false );
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
          <img src="<?php echo fika_drops_img( 'fika-drop-limited-edition.webp' ); ?>" width="720" height="745" alt="A purple Fika bag with a Limited Edition sticker, Strawberry Vanilla Diamonds and Pomegranate Ovals spilling out" decoding="async">
          <span class="fkd-badge">Limited edition</span>
        </div>
        <h3 class="fkd-name">Limited Edition Drop</h3>
        <p class="fkd-sub">Sweet &amp; sour, 500 g</p>
        <p class="fkd-what"><b class="fkd-new">New</b> BUBS Sour Strawberry Vanilla Diamond, with <b class="fkd-back">Back</b> BUBS Wild Berry Pomegranate Ovals for a short while. Limited supply.</p>
        <div class="fkd-buy"><span class="fkd-price">$14</span><button type="button" class="fkd-add" data-offer="drop">Add to bag</button></div>
      </article>

      <article class="fkd-offer">
        <div class="fkd-pic">
          <img src="<?php echo fika_drops_img( 'fika-drop-skull-mix.webp' ); ?>" width="720" height="745" alt="A black Fika bag with a Skull Mix sticker, the five BUBS skulls pouring out" decoding="async">
          <span class="fkd-badge fkd-badge-new">New</span>
        </div>
        <h3 class="fkd-name">Skull Mix</h3>
        <p class="fkd-sub">Five BUBS skulls, 500 g</p>
        <p class="fkd-what">Bubblegum, Cola, Lemon Raspberry, Fruity Lemon Mix and Raspberry Salty Licorice: every BUBS skull in one bag.</p>
        <div class="fkd-buy"><span class="fkd-price">$14</span><button type="button" class="fkd-add" data-offer="skull">Add to bag</button></div>
      </article>

    </div>
    <button type="button" class="fkd-later">Keep browsing</button>
  </div>
</div>
	<?php
	return ob_get_clean();
}

add_action( 'wp_footer', function () {
	if ( is_admin() || ! ( is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us' ) ) ) ) {
		return;
	}
	// the private Halloween preview link (snippet 184, ?fika_preview=<key>), or a shop manager with ?fika_drops=preview
	$preview = ( function_exists( 'fika_hw_preview' ) && fika_hw_preview() )
		|| ( isset( $_GET['fika_drops'] ) && 'preview' === $_GET['fika_drops'] && current_user_can( 'manage_woocommerce' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $preview && ! ( FIKA_DROPS_LIVE && function_exists( 'fika_hw_on' ) && fika_hw_on() ) ) {
		return; // it drops with the Halloween look, once FIKA_DROPS_LIVE is true
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
  [].forEach.call(veil.querySelectorAll('.fkd-add'), function (b) {
    b.addEventListener('click', function () {
      // going live: adds the offer's 500 g bag (a Ready Mix product) to the Fika bag; here it only says so
      b.textContent = 'Added ✓'; setTimeout(close, 700);
    });
  });
  function go() { setTimeout(open, 400); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
</script>
	<?php
}, 60 );
