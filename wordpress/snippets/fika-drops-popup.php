<?php
/**
 * Fika: "Two new drops" popup when a visitor arrives (owner, 26 Oct 2026). NOT LIVE: preview only.
 * - Offer 1, Limited Edition Drop (sweet & sour bundle, 500 g, $14): the new BUBS Sour Strawberry Vanilla Diamond with
 *   BUBS Wild Berry Pomegranate Ovals (product 108) back for a short while, limited supply.
 * - Offer 2, Skull Mix (500 g, $14): the five BUBS skulls of the shop (products 120 Bubblegum, 124 Cola, 122 Lemon
 *   Raspberry, 118 Fruity Lemon Mix (Mini), 107 Raspberry Salty Licorice) in a skull-shaped bag.
 * - When: right on arrival (0.4 s after the page shows), once per visit (sessionStorage fika_drops_seen), on our shop
 *   pages only (home, Mix your own, Ready Mix, About), never on the checkout or My account, and not when the
 *   "order confirmed" popup is showing. Closes with x, "Keep browsing", a tap outside or Esc.
 * - Under the Halloween look (body.fika-hw) it takes the night colours.
 * - The bag illustrations are drawn here (SVG); the candies in them are the shop's cut-out photos (piece-*.webp).
 *   The Strawberry Vanilla Diamond has no photo yet: a drawn diamond stands in for it.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-drops-popup.php
 */

function fika_drops_piece( $slug ) {
	return esc_url( content_url( '/uploads/2026/10/piece-' . $slug . '.webp' ) );
}

// the new candy, drawn: a sugared diamond, strawberry pink on top, vanilla cream below
function fika_drops_diamond( $x, $y, $s, $rot ) {
	return '<g transform="translate(' . $x . ' ' . $y . ') rotate(' . $rot . ') scale(' . $s . ')">'
		. '<path d="M-22 -6 L-12 -16 L12 -16 L22 -6 L0 18 Z" fill="#fff1d6" stroke="#e9c98f" stroke-width="1.2"/>'
		. '<path d="M-22 -6 L-12 -16 L12 -16 L22 -6 Z" fill="#ff5f8a"/>'
		. '<path d="M-12 -16 L-6 -6 L6 -6 L12 -16" fill="none" stroke="#ffd0dd" stroke-width="1.2"/>'
		. '<path d="M-22 -6 L22 -6 M-6 -6 L0 18 M6 -6 L0 18" fill="none" stroke="#f0d6a8" stroke-width="1"/>'
		. '<g fill="#fff" opacity=".85"><circle cx="-14" cy="-10" r="1.1"/><circle cx="-4" cy="-12" r="1"/><circle cx="6" cy="-10" r="1.1"/><circle cx="14" cy="-9" r="1"/><circle cx="-8" cy="2" r="1"/><circle cx="4" cy="6" r="1"/></g>'
		. '</g>';
}

function fika_drops_markup() {
	$skulls = array( 'bubs-bubblegum-skull', 'bubs-cola-skull', 'bubs-lemon-raspberry-skull', 'bubs-fruity-lemon-mix-skulls', 'bubs-raspberry-salty-licorice-skulls' );
	$oval   = fika_drops_piece( 'bubs-wild-berry-pomegranate-oval' );
	ob_start();
	?>
<div class="fkd-veil" id="fkdVeil" hidden>
  <div class="fkd-card" role="dialog" aria-modal="true" aria-labelledby="fkdTitle" tabindex="-1">
    <button type="button" class="fkd-x" aria-label="Close">&times;</button>
    <p class="fkd-kicker">Just landed at Fika</p>
    <h2 class="fkd-title" id="fkdTitle">Two new drops</h2>
    <div class="fkd-offers">

      <article class="fkd-offer">
        <span class="fkd-badge">Limited edition</span>
        <div class="fkd-pic" aria-hidden="true">
          <svg viewBox="0 0 220 200" class="fkd-svg">
            <defs><clipPath id="fkdWin1"><rect x="62" y="74" width="96" height="78" rx="18"/></clipPath></defs>
            <ellipse cx="110" cy="190" rx="78" ry="7" fill="rgba(0,74,173,.10)"/>
            <path d="M48 40 L172 40 L178 186 L42 186 Z" fill="#fdeaf2" stroke="#efb8cf" stroke-width="2"/>
            <path d="M48 40 l8 -10 l8 10 l8 -10 l8 10 l8 -10 l8 10 l8 -10 l8 10 l8 -10 l8 10 l8 -10 l8 10 l8 -10 l8 10 l8 -10 l4 10 Z" fill="#f7cfe0" stroke="#efb8cf" stroke-width="2" stroke-linejoin="round"/>
            <text x="110" y="64" text-anchor="middle" class="fkd-logo">Fika</text>
            <rect x="62" y="74" width="96" height="78" rx="18" fill="#fff"/>
            <g clip-path="url(#fkdWin1)">
              <image href="<?php echo $oval; ?>" x="58" y="104" width="62" height="52" transform="rotate(-14 89 130)"/>
              <image href="<?php echo $oval; ?>" x="104" y="110" width="58" height="48" transform="rotate(18 133 134)"/>
              <?php echo fika_drops_diamond( 88, 98, 1.05, -12 ); ?>
              <?php echo fika_drops_diamond( 134, 92, .95, 16 ); ?>
              <?php echo fika_drops_diamond( 110, 136, .8, 4 ); ?>
            </g>
            <rect x="62" y="74" width="96" height="78" rx="18" fill="none" stroke="#efb8cf" stroke-width="2"/>
            <path d="M72 82 q10 -4 22 -3" stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none" opacity=".8"/>
            <text x="110" y="174" text-anchor="middle" class="fkd-bagtag">500 g</text>
          </svg>
        </div>
        <h3 class="fkd-name">Limited Edition Drop</h3>
        <p class="fkd-sub">Sweet &amp; sour bundle</p>
        <ul class="fkd-list">
          <li><b class="fkd-new">New</b> BUBS Sour Strawberry Vanilla Diamond</li>
          <li><b class="fkd-back">Back</b> BUBS Wild Berry Pomegranate Ovals, limited supply</li>
        </ul>
        <div class="fkd-buy"><span class="fkd-price">$14 <small>500 g</small></span><button type="button" class="fkd-add" data-offer="drop">Add to bag</button></div>
      </article>

      <article class="fkd-offer">
        <span class="fkd-badge">New</span>
        <div class="fkd-pic" aria-hidden="true">
          <svg viewBox="0 0 220 200" class="fkd-svg">
            <defs>
              <linearGradient id="fkdSkullG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff7fb"/><stop offset="1" stop-color="#f9d3e4"/></linearGradient>
              <clipPath id="fkdSkull"><path d="M110 12 C162 12 198 47 198 96 C198 127 184 147 168 157 L168 186 L52 186 L52 157 C36 147 22 127 22 96 C22 47 58 12 110 12 Z"/></clipPath>
              <clipPath id="fkdEyeL"><ellipse cx="80" cy="100" rx="30" ry="27"/></clipPath>
              <clipPath id="fkdEyeR"><ellipse cx="140" cy="100" rx="30" ry="27"/></clipPath>
            </defs>
            <ellipse cx="110" cy="192" rx="82" ry="6" fill="rgba(0,74,173,.10)"/>
            <path d="M110 12 C162 12 198 47 198 96 C198 127 184 147 168 157 L168 186 L52 186 L52 157 C36 147 22 127 22 96 C22 47 58 12 110 12 Z" fill="url(#fkdSkullG)"/>
            <g clip-path="url(#fkdSkull)">
              <path d="M0 0 H220 V30 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-8 8 l-8 -8 l-12 8 Z" fill="#f4bfd5"/>
            </g>
            <path d="M110 12 C162 12 198 47 198 96 C198 127 184 147 168 157 L168 186 L52 186 L52 157 C36 147 22 127 22 96 C22 47 58 12 110 12 Z" fill="none" stroke="#e7a3c1" stroke-width="2.5"/>
            <text x="110" y="64" text-anchor="middle" class="fkd-logo">Fika</text>
            <ellipse cx="80" cy="100" rx="30" ry="27" fill="#fff"/>
            <ellipse cx="140" cy="100" rx="30" ry="27" fill="#fff"/>
            <g clip-path="url(#fkdEyeL)">
              <image href="<?php echo fika_drops_piece( $skulls[0] ); ?>" x="46" y="70" width="40" height="58" transform="rotate(-12 66 99)"/>
              <image href="<?php echo fika_drops_piece( $skulls[1] ); ?>" x="74" y="76" width="42" height="54" transform="rotate(14 95 103)"/>
            </g>
            <g clip-path="url(#fkdEyeR)">
              <image href="<?php echo fika_drops_piece( $skulls[2] ); ?>" x="106" y="70" width="40" height="58" transform="rotate(10 126 99)"/>
              <image href="<?php echo fika_drops_piece( $skulls[3] ); ?>" x="134" y="76" width="44" height="52" transform="rotate(-16 156 102)"/>
            </g>
            <ellipse cx="80" cy="100" rx="30" ry="27" fill="none" stroke="#e08db2" stroke-width="3"/>
            <ellipse cx="140" cy="100" rx="30" ry="27" fill="none" stroke="#e08db2" stroke-width="3"/>
            <path d="M62 86 q8 -6 18 -5 M122 86 q8 -6 18 -5" stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none" opacity=".9"/>
            <path d="M110 132 c-3 0 -9 9 -9 13 c0 3 4 4 9 1 c5 3 9 2 9 -1 c0 -4 -6 -13 -9 -13 Z" fill="#e08db2"/>
            <rect x="66" y="160" width="88" height="22" rx="5" fill="#fff" stroke="#e7a3c1" stroke-width="2"/>
            <path d="M83.6 160 v22 M101.2 160 v22 M118.8 160 v22 M136.4 160 v22" stroke="#e7a3c1" stroke-width="2"/>
            <image href="<?php echo fika_drops_piece( $skulls[4] ); ?>" x="164" y="140" width="44" height="50" transform="rotate(18 186 165)"/>
            <image href="<?php echo fika_drops_piece( $skulls[0] ); ?>" x="14" y="148" width="30" height="43" transform="rotate(-20 29 170)"/>
          </svg>
        </div>
        <h3 class="fkd-name">Skull Mix</h3>
        <p class="fkd-sub">Five BUBS skulls, one skull bag</p>
        <ul class="fkd-list fkd-list-skulls">
          <li>Bubblegum</li><li>Cola</li><li>Lemon Raspberry</li><li>Fruity Lemon Mix</li><li>Raspberry Salty Licorice</li>
        </ul>
        <div class="fkd-buy"><span class="fkd-price">$14 <small>500 g</small></span><button type="button" class="fkd-add" data-offer="skull">Add to bag</button></div>
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
	?>
<style id="fika-drops-css">
.fkd-veil { position: fixed; inset: 0; z-index: 99990; display: flex; align-items: center; justify-content: center; padding: 16px;
  background: rgba(16, 28, 60, .45); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); opacity: 0; transition: opacity .3s ease; }
.fkd-veil[hidden] { display: none; }
.fkd-veil.on { opacity: 1; }
.fkd-card { position: relative; width: 100%; max-width: 760px; max-height: calc(100vh - 32px); overflow: auto; overscroll-behavior: contain;
  background: #fff; border-radius: 26px; box-shadow: 0 24px 60px rgba(0, 74, 173, .25); padding: 26px 26px 18px; text-align: center;
  font-family: 'Outfit', Arial, sans-serif; color: #1b2a4a; transform: translateY(14px) scale(.97); transition: transform .35s cubic-bezier(.2, 1.2, .4, 1); }
.fkd-veil.on .fkd-card { transform: none; }
.fkd-x { position: absolute; top: 12px; right: 12px; width: 38px; height: 38px; border: 0; border-radius: 50%; background: #f3f5fa; color: #4a587a; font-size: 22px; line-height: 1; cursor: pointer; }
.fkd-x:hover { background: #fdeaf2; }
.fkd-card:focus { outline: 0; }
.fkd-x:focus-visible, .fkd-add:focus-visible, .fkd-later:focus-visible { outline: 2px solid #004aad; outline-offset: 2px; }
.fkd-kicker { margin: 0; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 18px; color: #e0447f; }
.fkd-title { margin: 2px 0 18px; font-family: 'NF Le Petit Cochon', 'Bebas Neue', cursive; font-variant: small-caps; font-weight: 400; font-size: 40px; line-height: 1.05; color: #004aad; }
.fkd-offers { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; text-align: left; }
.fkd-offer { position: relative; display: flex; flex-direction: column; border: 1.5px solid #f3d3e1; border-radius: 20px; padding: 14px 16px 16px; background: #fff; }
.fkd-badge { position: absolute; top: 12px; left: 12px; z-index: 1; padding: 4px 10px; border-radius: 999px; background: #004aad; color: #fff; font: 600 12px/1.2 'Outfit', Arial, sans-serif; letter-spacing: .03em; text-transform: uppercase; }
.fkd-pic { margin: 0 -6px 6px; border-radius: 14px; background: radial-gradient(ellipse at 50% 60%, #fff 0%, #fdeaf2 75%); }
.fkd-svg { display: block; width: 100%; height: auto; max-height: 190px; }
.fkd-logo { font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-size: 22px; fill: #004aad; }
.fkd-bagtag { font: 600 12px 'Outfit', Arial, sans-serif; fill: #c2577f; letter-spacing: .04em; }
.fkd-name { margin: 4px 0 0; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 27px; letter-spacing: .02em; line-height: 1; color: #004aad; }
.fkd-sub { margin: 3px 0 8px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 17px; color: #4a5878; }
.fkd-list { margin: 0 0 12px; padding: 0; list-style: none; font-size: 14px; line-height: 1.45; color: #1b2a4a; }
.fkd-list li { margin: 0 0 4px; }
.fkd-list b { display: inline-block; margin-right: 6px; padding: 1px 7px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; vertical-align: 1px; }
.fkd-new { background: #ffe1ea; color: #c21d5a; }
.fkd-back { background: #e5efff; color: #004aad; }
.fkd-list-skulls { display: flex; flex-wrap: wrap; gap: 5px; }
.fkd-list-skulls li { margin: 0; padding: 3px 9px; border-radius: 999px; background: #fdeaf2; font-size: 13px; }
.fkd-buy { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.fkd-price { font-family: 'Bebas Neue', Impact, sans-serif; font-size: 28px; letter-spacing: .02em; line-height: 1; color: #1b2a4a; }
.fkd-price small { margin-left: 4px; font: 500 14px 'Outfit', Arial, sans-serif; letter-spacing: 0; color: #4a5878; }
.fkd-add { padding: 11px 20px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px 'Outfit', Arial, sans-serif; letter-spacing: .03em; cursor: pointer; box-shadow: 0 6px 16px rgba(0, 74, 173, .25); transition: transform .15s ease; }
.fkd-add:hover { transform: translateY(-2px); }
.fkd-later { margin: 14px auto 0; display: block; padding: 6px 12px; border: 0; background: none; color: #4a5878; font: 500 14px 'Outfit', Arial, sans-serif; text-decoration: underline; cursor: pointer; }
@media (max-width: 640px) {
  .fkd-veil { align-items: flex-end; padding: 0; }
  .fkd-card { max-width: none; max-height: 92vh; border-radius: 24px 24px 0 0; padding: 22px 16px calc(14px + env(safe-area-inset-bottom, 0px)); transform: translateY(40px); }
  .fkd-title { font-size: 34px; margin-bottom: 14px; }
  .fkd-offers { grid-template-columns: 1fr; gap: 12px; }
  .fkd-offer { display: grid; grid-template-columns: 118px 1fr; column-gap: 12px; padding: 12px; }
  .fkd-badge { top: 8px; left: 8px; font-size: 10.5px; padding: 3px 8px; }
  .fkd-pic { grid-row: 1 / span 4; margin: 0; align-self: start; }
  .fkd-svg { max-height: none; }
  .fkd-name { font-size: 24px; }
  .fkd-sub { font-size: 15px; margin-bottom: 6px; }
  .fkd-list { font-size: 13px; margin-bottom: 10px; }
  .fkd-list-skulls li { font-size: 12px; padding: 2px 8px; }
  .fkd-buy { grid-column: 1 / -1; margin-top: 4px; }
  .fkd-add { padding: 12px 22px; }
}
@media (prefers-reduced-motion: reduce) { .fkd-veil, .fkd-card { transition: none; } }
/* the Halloween look: night colours */
body.fika-hw .fkd-veil { background: rgba(5, 3, 12, .6); }
body.fika-hw .fkd-card { background: #140e22; color: #f4ecdc; box-shadow: 0 0 0 1px rgba(255, 123, 28, .35), 0 24px 60px rgba(0, 0, 0, .6); }
body.fika-hw .fkd-x { background: #2b1c47; color: #f4ecdc; }
body.fika-hw .fkd-kicker { color: #a6e35b; }
body.fika-hw .fkd-title { font-family: 'Creepster', 'NF Le Petit Cochon', cursive; font-variant: normal; letter-spacing: .03em; color: #ff7b1c; text-shadow: 0 0 22px rgba(255, 123, 28, .5); }
body.fika-hw .fkd-offer { background: #1b1230; border-color: #3a2a5c; }
body.fika-hw .fkd-badge, body.fika-hw .fkd-add { background: #ff7b1c; color: #1b0d00; box-shadow: 0 0 18px rgba(255, 123, 28, .35); }
body.fika-hw .fkd-pic { background: radial-gradient(ellipse at 50% 60%, #f4ecdc 0%, #e6dcc6 75%); }
body.fika-hw .fkd-name { color: #ff7b1c; }
body.fika-hw .fkd-sub, body.fika-hw .fkd-later, body.fika-hw .fkd-price small { color: #b9aed0; }
body.fika-hw .fkd-list, body.fika-hw .fkd-price { color: #f4ecdc; }
body.fika-hw .fkd-list-skulls li { background: #2b1c47; }
body.fika-hw .fkd-back { background: #2b1c47; color: #ffb36b; }
</style>
	<?php
	echo fika_drops_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
<script id="fika-drops-js">
(function () {
  var veil = document.getElementById('fkdVeil'); if (!veil) return;
  try { if (sessionStorage.getItem('fika_drops_seen') === '1' && !/fika_drops=preview/.test(location.search)) return; } catch (e) {}
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
