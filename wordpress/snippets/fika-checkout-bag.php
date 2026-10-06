<?php
/**
 * Fika: animated "your bag" illustration on the checkout page.
 * A pink Fika paper bag with a clear window; inside, mini versions of each product photo,
 * as many pieces per candy as its share of the grams ordered (300 g = 3x the pieces of 100 g).
 * Ready-Mix bags are shown as candies from their category (Sweet / Sour / both).
 * Reads the live cart from the WooCommerce Blocks data store, so it updates with the cart.
 * Candies are cartoon illustrations (wordpress/snippets/fika-cartoons.js, embedded below); products without
 * a cartoon fall back to their single-piece cut-out, then to the product photo.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-checkout-bag.php
 */

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	// Single-piece cut-outs: product meta "fika_piece" holds the image URL (falls back to the product photo)
	$pieces = array();
	$ids    = get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'fika_piece' ) );
	foreach ( $ids as $id ) {
		$url = get_post_meta( $id, 'fika_piece', true );
		if ( $url ) {
			$pieces[ $id ] = esc_url_raw( $url );
		}
	}
	echo '<script>window.FIKA_PIECES = ' . wp_json_encode( (object) $pieces ) . ';</script>';
	echo <<<'FIKA_BAG'
<style>
.fika-bagviz { --fika-blue: #004aad; --fika-pink: #fdeaf2; padding: 4px 4px 20px; margin: 0 0 18px; border-bottom: 1px solid #f0dbe5; }
.fika-bagviz .fbv-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin: 0 0 6px; }
.fika-bagviz .fbv-title { font-family: 'Bebas Neue', Impact, sans-serif; font-size: 26px; letter-spacing: .03em; color: var(--fika-blue); margin: 0; font-weight: 400; }
.fika-bagviz .fbv-kg { font-family: 'Outfit', Arial, sans-serif; font-weight: 600; color: var(--fika-blue); font-size: 16px; }
.fika-bagviz .fbv-stage { position: relative; width: 100%; max-width: 300px; margin: 0 auto; aspect-ratio: 300 / 360; }
.fika-bagviz .fbv-stage.fbv-wiggle { animation: fbvWiggle .7s ease-in-out; transform-origin: 50% 95%; }
@keyframes fbvWiggle { 0%,100% { transform: rotate(0); } 25% { transform: rotate(-1.6deg) scaleY(.985); } 60% { transform: rotate(1.2deg); } }
.fika-bagviz svg.fbv-bag { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.fika-bagviz .fbv-win {
  position: absolute; left: 20.5%; top: 45%; width: 59%; height: 44%;
  border-radius: 26px; overflow: hidden; isolation: isolate;
  background: radial-gradient(120% 90% at 50% 0%, #fffafc 0%, #fff1f7 70%, #fde6f0 100%);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, .95), 0 0 0 5px #e7a8c3, inset 0 8px 16px rgba(120, 30, 70, .10);
}
.fika-bagviz .fbv-candy { position: absolute; left: 0; top: 0; mix-blend-mode: multiply; transition: transform .6s cubic-bezier(.3, 1.3, .5, 1), opacity .3s; }
.fika-bagviz .fbv-candy.is-cartoon { mix-blend-mode: normal; filter: drop-shadow(0 1.5px 1.2px rgba(80, 20, 50, .25)); }
.fika-bagviz .fbv-candy.is-cartoon svg { display: block; width: 100%; height: 100%; overflow: visible; }
.fika-bagviz .fbv-candy.is-piece { mix-blend-mode: normal; filter: drop-shadow(0 1.5px 1.5px rgba(80, 20, 50, .22)); }
.fika-bagviz .fbv-candy.is-piece img { transform: none; }
.fika-bagviz .fbv-candy img { display: block; width: 100%; height: 100%; object-fit: contain; transform: scale(1.35); pointer-events: none; user-select: none; }
.fika-bagviz .fbv-glass { position: absolute; inset: 0; z-index: 5; pointer-events: none; border-radius: 26px;
  background: linear-gradient(118deg, rgba(255,255,255,0) 0 22%, rgba(255,255,255,.55) 26%, rgba(255,255,255,0) 33%, rgba(255,255,255,0) 58%, rgba(255,255,255,.32) 61%, rgba(255,255,255,0) 66%); }
.fika-bagviz .fbv-glint { position: absolute; inset: -20% -60%; z-index: 6; pointer-events: none;
  background: linear-gradient(110deg, rgba(255,255,255,0) 42%, rgba(255,255,255,.7) 50%, rgba(255,255,255,0) 58%);
  transform: translateX(-70%); animation: fbvGlint 5.5s ease-in-out 1.2s infinite; }
@keyframes fbvGlint { 0%, 70% { transform: translateX(-70%); } 100% { transform: translateX(70%); } }
.fika-bagviz .fbv-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 10px;
  font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; color: #b0708d; font-size: 17px; }
.fika-bagviz .fbv-note { font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 16px; line-height: 1.4; text-align: center; color: #1b2a4a; margin: 10px 0 0; }
@media (prefers-reduced-motion: reduce) { .fika-bagviz .fbv-glint { animation: none; display: none; } .fika-bagviz .fbv-candy { transition: none; } }
@media (max-width: 700px) { .fika-bagviz .fbv-stage { max-width: 240px; } }
</style>
<script>
/* FIKA cartoon candies: one small SVG illustration per product (keyed by product slug).
   Used by the checkout bag (wordpress/snippets/fika-checkout-bag.php).
   window.FIKA_CARTOON(slug) returns an SVG string (viewBox 0 0 100 100) or '' when there is none. */
(function () {
  var uid = 0;
  function id(p) { uid += 1; return 'fk' + p + uid; }

  // sugar crystals: little white specks spread over the shape (clipped to it)
  function sugar(clipId, n, seed) {
    var s = seed || 7, out = '';
    function r() { s = (s * 9301 + 49297) % 233280; return s / 233280; }
    for (var i = 0; i < n; i++) {
      var x = 8 + r() * 84, y = 8 + r() * 84, rr = 0.7 + r() * 1.1;
      out += '<circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="' + rr.toFixed(2) + '" fill="#fff" opacity="' + (0.55 + r() * 0.4).toFixed(2) + '"/>';
    }
    return '<g clip-path="url(#' + clipId + ')">' + out + '</g>';
  }
  function shine(x, y, rx, ry, rot, op) {
    return '<ellipse cx="' + x + '" cy="' + y + '" rx="' + rx + '" ry="' + ry + '" transform="rotate(' + (rot || -30) + ' ' + x + ' ' + y + ')" fill="#fff" opacity="' + (op || 0.55) + '"/>';
  }
  function svg(inner, defs) {
    return '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">' + (defs ? '<defs>' + defs + '</defs>' : '') + inner + '</svg>';
  }
  // a shape filled with two colours split by a line (angle in degrees, 0 = vertical split)
  function split(path, c1, c2, stroke, angle, at, sug, glossy, extra) {
    var cl = id('c'), g = id('g');
    var a = angle || 0, p = at === undefined ? 50 : at;
    var defs = '<clipPath id="' + cl + '"><path d="' + path + '"/></clipPath>' +
      '<linearGradient id="' + g + '" gradientTransform="rotate(' + a + ' .5 .5)">' +
      '<stop offset="' + (p - 4) + '%" stop-color="' + c1 + '"/><stop offset="' + (p + 4) + '%" stop-color="' + c2 + '"/></linearGradient>';
    var body = '<path d="' + path + '" fill="url(#' + g + ')"/>' +
      '<g clip-path="url(#' + cl + ')"><ellipse cx="50" cy="96" rx="60" ry="22" fill="#000" opacity=".10"/></g>' +
      (extra || '') +
      (sug ? sugar(cl, sug, path.length) : '') +
      (glossy ? shine(34, 30, 12, 6, -35, 0.6) : shine(34, 30, 9, 4, -35, 0.35)) +
      '<path d="' + path + '" fill="none" stroke="' + stroke + '" stroke-width="2.6" stroke-linejoin="round"/>';
    return svg(body, defs);
  }

  var SKULL = 'M50 12 C72 12 86 27 86 46 C86 58 80 66 73 70 L73 79 C73 85 69 88 63 88 L61 88 L61 82 L55 82 L55 88 L45 88 L45 82 L39 82 L39 88 L37 88 C31 88 27 85 27 79 L27 70 C20 66 14 58 14 46 C14 27 28 12 50 12 Z';
  function skull(cL, cR, stroke, sug, glossy) {
    var eyes = '<ellipse cx="37" cy="48" rx="8" ry="9.5" fill="#3b1a2a" opacity=".38"/><ellipse cx="63" cy="48" rx="8" ry="9.5" fill="#3b1a2a" opacity=".38"/>' +
      '<path d="M50 58 L46 66 L54 66 Z" fill="#3b1a2a" opacity=".3"/>';
    return split(SKULL, cL, cR, stroke, 0, 50, sug, glossy, eyes);
  }

  var OVAL = 'M50 20 C76 20 92 33 92 50 C92 67 76 80 50 80 C24 80 8 67 8 50 C8 33 24 20 50 20 Z';
  function bubsOval(c1, c2, stroke, angle) {
    var txt = '<text x="50" y="58" text-anchor="middle" transform="rotate(-12 50 50)" font-family="Arial Black, Arial, sans-serif" font-weight="900" font-size="22" letter-spacing="-1" fill="#fff" fill-opacity=".38" stroke="' + stroke + '" stroke-opacity=".45" stroke-width="1">BUBS</text>';
    return split(OVAL, c1, c2, stroke, angle === undefined ? 70 : angle, 50, 0, false, txt);
  }

  var DIAMOND = 'M50 9 Q56 9 62 17 L86 45 Q90 50 86 55 L62 83 Q56 91 50 91 Q44 91 38 83 L14 55 Q10 50 14 45 L38 17 Q44 9 50 9 Z';
  var BOTTLE = 'M44 7 L56 7 L56 17 Q56 24 61 29 Q69 37 69 49 L69 82 Q69 93 58 93 L42 93 Q31 93 31 82 L31 49 Q31 37 39 29 Q44 24 44 17 Z';
  function bottle(top, bottom, stroke, sug) {
    return split(BOTTLE, top, bottom, stroke, 90, 46, sug, false, '<rect x="42" y="7" width="16" height="5" rx="2" fill="#fff" opacity=".25"/>');
  }
  var FISH = 'M8 52 C16 32 44 25 64 37 L88 24 C84 40 84 62 88 77 L64 65 C44 78 16 72 8 52 Z';
  function fish(c1, c2, stroke, sug) {
    var deco = '<circle cx="25" cy="47" r="3" fill="#2b1020" opacity=".55"/>' +
      '<path d="M38 40 Q42 50 38 62 M48 38 Q52 50 48 64 M58 40 Q61 50 58 62" fill="none" stroke="' + stroke + '" stroke-opacity=".35" stroke-width="1.6"/>';
    return split(FISH, c1, c2, stroke, 0, 55, sug, !sug, deco);
  }
  // a stick lying on its side, seen from one end (filling shows in the round end)
  function tube(body, end, fill, stroke, sug, deco) {
    var cl = id('c');
    var path = 'M18 36 L74 22 Q88 19 91 32 Q94 45 80 49 L24 63 Q11 66 8 53 Q5 40 18 36 Z';
    var defs = '<clipPath id="' + cl + '"><path d="' + path + '"/></clipPath>';
    var inner = '<path d="' + path + '" fill="' + body + '"/>' +
      '<g clip-path="url(#' + cl + ')"><path d="M8 56 L92 36 L92 60 L8 80 Z" fill="#000" opacity=".12"/>' + (deco || '') + '</g>' +
      '<ellipse cx="17" cy="50" rx="9" ry="13.5" transform="rotate(-14 17 50)" fill="' + end + '" stroke="' + stroke + '" stroke-width="2.2"/>' +
      fill +
      (sug ? sugar(cl, sug, 11) : '') + shine(52, 31, 22, 3.4, -14, 0.5) +
      '<path d="' + path + '" fill="none" stroke="' + stroke + '" stroke-width="2.6" stroke-linejoin="round"/>';
    return svg(inner, defs);
  }
  function ring(c1, c2, stroke, sug) {
    var path = 'M50 12 A38 38 0 1 1 49.9 12 Z M50 36 A14 14 0 1 0 50.1 36 Z';
    var cl = id('c'), g = id('g');
    var defs = '<clipPath id="' + cl + '"><path d="' + path + '" clip-rule="evenodd"/></clipPath>' +
      '<linearGradient id="' + g + '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/></linearGradient>';
    return svg('<path d="' + path + '" fill="url(#' + g + ')" fill-rule="evenodd"/>' + sugar(cl, sug || 0, 23) + shine(30, 28, 10, 4.5, -40, 0.5) +
      '<path d="' + path + '" fill="none" fill-rule="evenodd" stroke="' + stroke + '" stroke-width="2.6"/>', defs);
  }
  function berry(c, dark, stroke, sug, glossy) {
    var dots = [[50, 28], [36, 34], [64, 34], [28, 48], [44, 46], [58, 46], [72, 48], [34, 62], [50, 60], [66, 62], [42, 75], [58, 75], [50, 86]];
    var cl = id('c'), shape = '';
    var clip = dots.map(function (d) { return '<circle cx="' + d[0] + '" cy="' + d[1] + '" r="12"/>'; }).join('');
    dots.forEach(function (d) {
      shape += '<circle cx="' + d[0] + '" cy="' + d[1] + '" r="12" fill="' + c + '" stroke="' + stroke + '" stroke-width="2"/>' +
        '<circle cx="' + (d[0] + 3) + '" cy="' + (d[1] + 4) + '" r="6" fill="' + dark + '" opacity=".35"/>' +
        '<circle cx="' + (d[0] - 4) + '" cy="' + (d[1] - 4) + '" r="' + (glossy ? 3.4 : 2.4) + '" fill="#fff" opacity="' + (glossy ? 0.75 : 0.45) + '"/>';
    });
    return svg(shape + (sug ? sugar(cl, sug, 31) : ''), '<clipPath id="' + cl + '">' + clip + '</clipPath>');
  }
  function round(path, c1, c2, stroke, sug, extra, angle) {
    return split(path, c1, c2, stroke, angle === undefined ? 120 : angle, 55, sug, false, extra);
  }

  var CIRCLE = 'M50 12 C73 12 90 29 90 52 C90 74 73 90 50 90 C27 90 10 74 10 52 C10 29 27 12 50 12 Z';
  var PEACH = 'M50 20 C56 12 72 10 82 22 C92 34 90 62 74 80 C64 90 36 90 26 80 C10 62 8 34 18 22 C28 10 44 12 50 20 Z';
  var LEMON = 'M8 50 C10 44 16 41 22 38 C32 25 68 25 78 38 C84 41 90 44 92 50 C90 56 84 59 78 62 C68 75 32 75 22 62 C16 59 10 56 8 50 Z';
  var STRAWB = 'M50 88 C30 80 12 58 14 38 C16 22 30 16 50 24 C70 16 84 22 86 38 C88 58 70 80 50 88 Z';
  var CRESCENT = 'M10 74 C4 46 24 16 56 12 C78 10 94 24 92 42 C82 44 70 48 60 56 C46 68 30 76 10 74 Z';
  var SLICE = 'M10 64 A40 40 0 0 1 90 64 Q90 70 84 70 L16 70 Q10 70 10 64 Z';
  var CHERRIES = 'M30 90 C14 90 6 76 10 62 C14 48 30 44 40 50 C42 42 48 36 56 36 C70 36 76 46 74 56 C86 52 96 62 92 76 C88 90 74 94 62 88 C56 94 40 96 30 90 Z';
  var PACIFIER_SHIELD = 'M14 46 C14 32 30 26 50 30 C70 26 86 32 86 46 C86 60 72 66 50 62 C28 66 14 60 14 46 Z';
  var GUM = 'M8 40 C20 22 80 22 92 40 C94 46 90 52 84 52 C72 40 28 40 16 52 C10 52 6 46 8 40 Z';
  var CAPSULE = 'M50 92 C34 92 24 80 24 64 L24 46 C24 34 36 26 50 26 C64 26 76 34 76 46 L76 64 C76 80 66 92 50 92 Z';

  var C = {
    'bubs-bubblegum-skull': function () { return skull('#f6a5c6', '#8fd3f0', '#c76a96', 70); },
    'bubs-cola-skull': function () { return skull('#f5ead6', '#9a5a2c', '#6b3a1b', 60); },
    'bubs-lemon-raspberry-skull': function () { return skull('#e5384f', '#ffd84a', '#a8243a', 70); },
    'bubs-fruity-lemon-mix-skulls': function () { return skull('#ffcf4d', '#ff8a4c', '#c45a2a', 70); },
    'bubs-raspberry-salty-licorice-skulls': function () { return skull('#e3243b', '#2a2326', '#141012', 0, true); },
    'bubs-forest-berry-ovals': function () { return bubsOval('#f6a5c6', '#6fd0ee', '#5b8fb0'); },
    'bubs-fruity-pear-ovals': function () { return bubsOval('#d9eed3', '#bfe0bc', '#86ad86', 0); },
    'bubs-banana-toffee-ovals': function () { return bubsOval('#e8e86a', '#c7b48e', '#8a7a52'); },
    'bubs-wild-berry-pomegranate-oval': function () { return bubsOval('#f0c1d8', '#d99cc2', '#a8678f', 0); },
    'bubs-tutti-frutti-diamond': function () {
      return split(DIAMOND, '#a7c08a', '#e8e65a', '#7b8f4e', 90, 52, 60, false,
        '<path d="M50 14 L50 86 M22 50 L78 50" stroke="#6f8a45" stroke-opacity=".35" stroke-width="1.6"/>');
    },
    'fizzy-cola': function () { return bottle('#f3dfb8', '#9b5a2e', '#6a3a1c', 70); },
    'fizzy-pop': function () { return bottle('#f7a8c4', '#8ccdf2', '#b07090', 70); },
    'fizzy-blue': function () { return bottle('#8fe0ff', '#24a7e0', '#1777a6', 80); },
    'swedish-fish': function () { return fish('#ff6a3d', '#e3241f', '#a3160f', 0); },
    'sour-swedish-fish': function () { return fish('#ffd36b', '#ff8a3b', '#c9622a', 70); },
    'sour-strawberries': function () {
      return tube('#ef5a4d', '#ffe9e6', '<ellipse cx="17" cy="50" rx="4.5" ry="7" transform="rotate(-14 17 50)" fill="#fff"/>', '#b8322b', 80);
    },
    'red-ammo': function () {
      return tube('#d81f2a', '#e83a43', '<g transform="rotate(-14 17 50)"><circle cx="17" cy="50" r="2" fill="#fff"/>' +
        '<ellipse cx="17" cy="44" rx="2" ry="3.4" fill="#fff"/><ellipse cx="17" cy="56" rx="2" ry="3.4" fill="#fff"/>' +
        '<ellipse cx="12.5" cy="50" rx="2.2" ry="2.4" fill="#fff"/><ellipse cx="21.5" cy="50" rx="2.2" ry="2.4" fill="#fff"/></g>', '#8f0f18', 0,
        '<path d="M30 33 L84 20 M34 48 L88 35" stroke="#ff7b82" stroke-width="3" opacity=".6"/>');
    },
    'rhubarb-bites': function () {
      return tube('#8e1b2a', '#b5263a', '<ellipse cx="17" cy="50" rx="5" ry="7.5" transform="rotate(-14 17 50)" fill="#f7d34a"/>', '#4e0b16', 0);
    },
    'tutti-frutti-rings': function () { return ring('#ffd27a', '#ff8fa8', '#d2667f', 90); },
    'raspberry-bites': function () { return berry('#f2384a', '#a5141f', '#a5141f', 60); },
    'forest-berries': function () { return berry('#4a2347', '#1c0a1a', '#1c0a1a', 0, true); },
    'peaches': function () {
      return round(PEACH, '#ffd36b', '#ff6b5c', '#cf4d3e', 80, '<path d="M50 22 Q46 50 50 86" stroke="#cf4d3e" stroke-opacity=".4" stroke-width="2" fill="none"/>', 135);
    },
    'sugared-apples': function () {
      return round(CIRCLE, '#c9ec6a', '#93cf3a', '#5f9a22', 80, '<path d="M50 16 Q58 4 70 8 Q64 20 50 18 Z" fill="#6cae2c" stroke="#4d8a1f" stroke-width="1.6"/>', 160);
    },
    'tutti-frutti-sour': function () { return round(LEMON, '#fff27a', '#ffc928', '#c99212', 70, '', 160); },
    'sugared-strawberries': function () {
      return round(STRAWB, '#f4f7d8', '#ff7f9c', '#cc4f6c', 90, '<path d="M30 24 Q50 34 70 24" stroke="#9fc46a" stroke-width="5" stroke-linecap="round" fill="none" opacity=".8"/>', 180);
    },
    'sour-pineapple': function () { return round(CRESCENT, '#fff26e', '#a9cf45', '#9a9420', 85, '<path d="M30 60 Q50 34 80 28" stroke="#d9c63a" stroke-width="2" fill="none" opacity=".6"/>', 60); },
    'tutti-frutti-sour-melon': function () {
      return round(SLICE, '#fff1e6', '#ff8a3d', '#d5652a', 70, '<path d="M16 64 L84 64" stroke="#ff8a3d" stroke-width="7" stroke-linecap="round" opacity=".85"/>', 90);
    },
    'sour-cherries': function () {
      return round(CHERRIES, '#ffb0c2', '#f0566e', '#c03552', 80, '<path d="M44 46 Q52 22 66 14" stroke="#7aa63a" stroke-width="4" stroke-linecap="round" fill="none"/>', 150);
    },
    'sour-watermelon-pacifier': function () {
      var cl = id('c');
      return svg('<circle cx="50" cy="26" r="15" fill="none" stroke="#9bd36a" stroke-width="8"/><circle cx="50" cy="26" r="15" fill="none" stroke="#5f9a35" stroke-width="1.6" opacity=".6"/>' +
        '<path d="' + PACIFIER_SHIELD + '" fill="#ff8fa8"/>' +
        '<path d="M40 60 C40 76 44 90 50 90 C56 90 60 76 60 60 Z" fill="#ffb3c4" stroke="#cf5c79" stroke-width="2.4"/>' +
        sugar(cl, 70, 13) + shine(32, 40, 9, 3.5, -12, 0.5) +
        '<path d="' + PACIFIER_SHIELD + '" fill="none" stroke="#cf5c79" stroke-width="2.6"/>',
        '<clipPath id="' + cl + '"><path d="' + PACIFIER_SHIELD + '"/><circle cx="50" cy="26" r="19"/><path d="M40 60 C40 76 44 90 50 90 C56 90 60 76 60 60 Z"/></clipPath>');
    },
    'loose-teeth': function () {
      var teeth = '';
      [[24, 44], [35, 40], [46, 38], [57, 38], [68, 40], [79, 44]].forEach(function (t) {
        teeth += '<rect x="' + (t[0] - 4.5) + '" y="' + t[1] + '" width="9" height="13" rx="3.5" fill="#fffaf2" stroke="#d9c9b6" stroke-width="1.4"/>';
      });
      return svg('<path d="M8 40 C20 22 80 22 92 40 C94 46 90 52 84 52 C72 40 28 40 16 52 C10 52 6 46 8 40 Z" fill="#ff7aa6" stroke="#c94a7a" stroke-width="2.6" stroke-linejoin="round"/>' +
        teeth + '<path d="M8 66 C20 84 80 84 92 66 C94 60 90 54 84 54 C72 66 28 66 16 54 C10 54 6 60 8 66 Z" fill="#ff7aa6" stroke="#c94a7a" stroke-width="2.6" stroke-linejoin="round"/>' +
        shine(30, 31, 10, 3, -10, 0.55));
    },
    'tutti-frutti-passion': function () {
      var star = '<path d="M50 30 L54 38 L63 37 L57 44 L61 52 L52 49 L45 55 L46 46 L38 42 L47 39 Z" fill="#ff9a1f" opacity=".85"/>';
      return split(CAPSULE, '#ffe14a', '#ff7a2a', '#d0571c', 90, 50, 0, true, star);
    }
  };

  window.FIKA_CARTOON = function (slug) { var f = C[slug]; return f ? f() : ''; };
  window.FIKA_CARTOON_SLUGS = Object.keys(C);
})();
</script>
<script>
(function () {
  if (window.__fikaBagViz) return;
  window.__fikaBagViz = true;

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var products = null;           // id -> { cats: [slugs], img: url, name }
  var lastSig = '';
  var root = null, stage = null, win = null, kgEl = null, emptyEl = null;
  var pieces = {};               // key -> { el, x, y, r, s }

  var BAG_SVG =
    '<svg class="fbv-bag" viewBox="0 0 300 360" aria-hidden="true">' +
      '<defs>' +
        '<linearGradient id="fbvPaper" x1="0" x2="1" y1="0" y2="0">' +
          '<stop offset="0" stop-color="#f3c3d8"/><stop offset=".12" stop-color="#f9dbe8"/>' +
          '<stop offset=".5" stop-color="#fbe5ef"/><stop offset=".88" stop-color="#f9dbe8"/><stop offset="1" stop-color="#efb8cf"/>' +
        '</linearGradient>' +
        '<linearGradient id="fbvFold" x1="0" x2="0" y1="0" y2="1">' +
          '<stop offset="0" stop-color="#f6cfe0"/><stop offset="1" stop-color="#eab0c9"/>' +
        '</linearGradient>' +
      '</defs>' +
      '<ellipse cx="150" cy="350" rx="118" ry="9" fill="rgba(0,74,173,.10)"/>' +
      '<path d="M44 74 L256 74 L266 334 Q267 346 254 346 L46 346 Q33 346 34 334 Z" fill="url(#fbvPaper)" stroke="#e6a9c3" stroke-width="1.5"/>' +
      '<path d="M62 78 L58 342" stroke="#eab3cb" stroke-width="2" opacity=".7"/>' +
      '<path d="M238 78 L242 342" stroke="#eab3cb" stroke-width="2" opacity=".7"/>' +
      '<path d="M40 42 L260 42 L256 80 L44 80 Z" fill="url(#fbvFold)" stroke="#e1a0bc" stroke-width="1.5"/>' +
      '<path d="M40 42 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 l11 -9 l11 9 Z" fill="#f4c6da" stroke="#e1a0bc" stroke-width="1.2" stroke-linejoin="round"/>' +
      '<path d="M44 80 L256 80" stroke="#d98fb0" stroke-width="2" opacity=".55"/>' +
      '<text x="150" y="140" text-anchor="middle" transform="rotate(-5 150 128)" font-family="NF Le Petit Cochon, cursive" font-size="56" fill="#004aad" style="font-variant: small-caps">Fika</text>' +
    '</svg>';

  function rng(seed) {
    var a = seed >>> 0;
    return function () {
      a = (a + 0x6D2B79F5) >>> 0;
      var t = a;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  function hash(str) { var h = 2166136261; for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }

  function build() {
    root = document.createElement('div');
    root.className = 'fika-bagviz';
    root.innerHTML =
      '<div class="fbv-head"><h3 class="fbv-title">Your Fika bag</h3><span class="fbv-kg"></span></div>' +
      '<div class="fbv-stage">' + BAG_SVG +
        '<div class="fbv-win"><div class="fbv-glass"></div><div class="fbv-glint"></div><div class="fbv-empty">Your bag is empty</div></div>' +
      '</div>' +
      '<p class="fbv-note">A peek inside: every candy is shown in proportion to the grams you picked.</p>';
    stage = root.querySelector('.fbv-stage');
    win = root.querySelector('.fbv-win');
    kgEl = root.querySelector('.fbv-kg');
    emptyEl = root.querySelector('.fbv-empty');
  }

  // Keep the illustration at the top of the checkout's right-hand column
  function mount() {
    var side = document.querySelector('.wc-block-checkout__sidebar') ||
               document.querySelector('.wp-block-woocommerce-checkout-totals-block');
    if (!side) return false;
    if (!root) build();
    if (root.parentNode !== side) side.insertBefore(root, side.firstChild);
    return true;
  }

  function loadProducts() {
    if (products) return Promise.resolve(products);
    return fetch('/wp-json/wc/store/v1/products?per_page=100', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (list) {
        products = {};
        (list || []).forEach(function (p) {
          var im = p.images && p.images[0];
          products[p.id] = {
            name: p.name,
            slug: p.slug,
            cats: (p.categories || []).map(function (c) { return c.slug; }),
            img: im ? (im.thumbnail || im.src) : '',
            piece: (window.FIKA_PIECES || {})[p.id] || '',
            cartoon: window.FIKA_CARTOON ? window.FIKA_CARTOON(p.slug) : ''
          };
        });
        return products;
      })
      .catch(function () { products = {}; return products; });
  }

  function cartItems() {
    try {
      var d = window.wp && wp.data && wp.data.select('wc/store/cart');
      if (d && d.getCartData) return (d.getCartData().items || []);
    } catch (e) {}
    return null;
  }

  // Turn the cart into [{ key, img, grams }] candy entries (Ready-Mix -> its candies)
  function entries(items) {
    var out = [];
    items.forEach(function (it) {
      var p = products[it.id] || { cats: [], img: '' };
      var img = p.img || (it.images && it.images[0] ? (it.images[0].thumbnail || it.images[0].src) : '');
      if (p.cats.indexOf('ready-mix') !== -1) {
        var nm = (it.name || '').toLowerCase();
        var want = [];
        if (nm.indexOf('sweet') !== -1) want.push('sweet');
        if (nm.indexOf('sour') !== -1) want.push('sour');
        if (!want.length) want = ['sweet', 'sour'];
        var pool = Object.keys(products).filter(function (id) {
          var c = products[id].cats;
          return (products[id].cartoon || products[id].img) ? want.some(function (w) { return c.indexOf(w) !== -1; }) : false;
        });
        var rand = rng(hash('mix' + it.id));
        pool.sort(function () { return rand() - 0.5; });
        var pick = pool.slice(0, 5);
        var grams = it.quantity * 500;
        if (!pick.length) { out.push({ key: 'm' + it.id, img: img, grams: grams }); return; }
        pick.forEach(function (id) { var q = products[id]; out.push({ key: 'm' + it.id + '-' + id, img: q.piece || q.img, piece: !!q.piece, cartoon: q.cartoon, grams: grams / pick.length }); });
      } else {
        out.push({ key: 'c' + it.id, img: p.piece || img, piece: !!p.piece, cartoon: p.cartoon || '', grams: it.quantity * 100 });
      }
    });
    return out;
  }

  function render(items) {
    if (!mount()) return;
    var W = Math.floor(win.clientWidth), H = Math.floor(win.clientHeight);
    if (!W || !H) return;
    var list = entries(items);
    var total = 0;
    list.forEach(function (e) { total += e.grams; });
    var shown = 0;
    items.forEach(function (it) { var p = products[it.id] || { cats: [] }; shown += it.quantity * (p.cats.indexOf('ready-mix') !== -1 ? 500 : 100); });
    kgEl.textContent = shown ? (shown >= 1000 ? (Math.round(shown / 100) / 10) + ' kg' : shown + ' g') : '';
    emptyEl.style.display = total ? 'none' : 'flex';

    // How many pieces: up to 4 per 100 g, never more than ~46 in the window, same ratio for every candy
    var MAXP = 46, perHundred = Math.max(1, Math.min(4, Math.floor(MAXP * 100 / Math.max(total, 1))));
    var unit = 100 / perHundred;
    if (total / unit > MAXP) unit = total / MAXP;
    var want = [];
    list.forEach(function (e) {
      var n = Math.max(1, Math.round(e.grams / unit));
      for (var k = 0; k < n; k++) want.push({ key: e.key + '#' + k, img: e.img, piece: e.piece, cartoon: e.cartoon });
    });
    var N = want.length;

    // Fill more of the window as the bag gets heavier
    var fill = Math.min(0.92, 0.44 + total / 2400);
    var rand = rng(hash(want.map(function (w) { return w.key; }).join('|')));
    want.sort(function () { return rand() - 0.5; });

    // Settle the pieces like a jar: staggered rows from the bottom, each piece overlapping its neighbours
    var s = 96, dx, cols, rows, dy;
    for (; s > 22; s -= 2) {
      dx = s * 0.8;
      cols = Math.max(2, Math.floor((W - s * 0.25) / dx));
      rows = Math.ceil(N / cols);
      dy = s * 0.56;
      if (s + (rows - 1) * dy <= fill * H) break;
    }
    var used = cols * dx + s * 0.32, left0 = (W - used) / 2;
    var placed = [], idx = 0;
    for (var r = 0; idx < N; r++) {
      var inRow = Math.min(cols, N - idx);
      var startC = Math.floor((cols - inRow) / 2);
      var off = (r % 2) ? dx / 2 : 0;
      for (var c = 0; c < inRow; c++, idx++) {
        var w0 = want[idx];
        var x = left0 + (startC + c) * dx + off - (s - dx) / 2 + (rand() - 0.5) * dx * 0.36;
        x = Math.max(-s * 0.12, Math.min(W - s * 0.88, x));
        var bottom = r * dy + (rand() - 0.5) * dy * 0.4 + (r ? 0 : 2);
        bottom = Math.max(0, bottom);
        placed.push({ key: w0.key, img: w0.img, piece: w0.piece, cartoon: w0.cartoon, x: Math.round(x), y: Math.round(H - bottom - s - 2),
                      r: Math.round(rand() * 80 - 40), bottom: bottom });
      }
    }
    placed.sort(function (a, b) { return a.bottom - b.bottom; });

    var keep = {}, fresh = 0;
    placed.forEach(function (p, i) {
      keep[p.key] = true;
      var old = pieces[p.key];
      var tr = 'translate(' + p.x + 'px,' + p.y + 'px) rotate(' + p.r + 'deg)';
      if (old) {
        old.el.style.width = old.el.style.height = s + 'px';
        old.el.style.transform = tr;
        old.el.style.zIndex = String(i + 1);
        return;
      }
      var el = document.createElement('div');
      el.className = 'fbv-candy' + (p.cartoon ? ' is-cartoon' : (p.piece ? ' is-piece' : ''));
      el.style.width = el.style.height = s + 'px';
      el.style.zIndex = String(i + 1);
      if (p.cartoon) {
        el.innerHTML = p.cartoon;
      } else {
        var im = document.createElement('img');
        im.alt = ''; im.decoding = 'async'; im.src = p.img;
        el.appendChild(im);
      }
      el.style.transform = tr;
      win.insertBefore(el, win.querySelector('.fbv-glass'));
      pieces[p.key] = { el: el };
      if (!reduce) {
        var from = 'translate(' + p.x + 'px,' + (-s - 30) + 'px) rotate(' + (p.r - 60) + 'deg)';
        var up = 'translate(' + p.x + 'px,' + (p.y - 7) + 'px) rotate(' + (p.r + 4) + 'deg)';
        el.animate([
          { transform: from, offset: 0, easing: 'cubic-bezier(.5,0,.9,.5)' },
          { transform: tr, offset: 0.72 },
          { transform: up, offset: 0.86 },
          { transform: tr, offset: 1 }
        ], { duration: 720 + Math.round(rand() * 160), delay: fresh * 55, fill: 'backwards' });
      }
      fresh++;
    });
    Object.keys(pieces).forEach(function (k) {
      if (keep[k]) return;
      var el = pieces[k].el;
      el.style.opacity = '0';
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 320);
      delete pieces[k];
    });
    if (fresh) {
      if (!reduce) setTimeout(function () {
        stage.classList.remove('fbv-wiggle'); void stage.offsetWidth; stage.classList.add('fbv-wiggle');
      }, Math.min(2600, fresh * 55 + 650));
    }
  }

  function update() {
    var items = cartItems();
    if (items === null) return;
    var sig = items.map(function (i) { return i.id + 'x' + i.quantity; }).join(',');
    if (!mount()) return;
    if (sig === lastSig) return;
    lastSig = sig;
    loadProducts().then(function () { render(items); });
  }

  // Fallback when the blocks data store is not available
  function fetchCart() {
    return fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function start() {
    var n = 0;
    var timer = setInterval(function () {
      n++;
      if (mount()) {
        if (cartItems() === null) {
          fetchCart().then(function (c) { loadProducts().then(function () { render(c.items || []); }); });
        } else {
          update();
          if (window.wp && wp.data && wp.data.subscribe) wp.data.subscribe(update);
        }
        clearInterval(timer);
      } else if (n > 60) clearInterval(timer);
    }, 250);
    // React may re-render the sidebar: put the illustration back if it disappears
    new MutationObserver(function () { if (root) { if (!document.body.contains(root)) mount(); } }).observe(document.body, { childList: true, subtree: true });
    // Re-lay the pile (without dropping it again) when the window size really changes
    var lastW = 0, rt = 0;
    window.addEventListener('resize', function () {
      clearTimeout(rt);
      rt = setTimeout(function () {
        if (!win) return;
        if (Math.abs(win.clientWidth - lastW) < 2) return;
        lastW = win.clientWidth; lastSig = ''; update();
      }, 250);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
</script>
FIKA_BAG;
}, 99 );
