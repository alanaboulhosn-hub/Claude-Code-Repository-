<?php
/**
 * Fika: shared cartoon candies + home page bag tweaks.
 * - Prints the cartoon candy library (wordpress/snippets/fika-cartoons.js) on the home page and checkout,
 *   as window.FIKA_CARTOON(slug).
 * - Home page: the header bag icon is the only bag (floating Bag button hidden); it shows an item count badge,
 *   shows the bag's weight in kg, grows gently with the grams, shoots out cartoon candies on hover, and
 *   catches cartoon candies that fly in from a product photo when + is pressed;
 * - Footer social icons animate on hover: Instagram flashes, WhatsApp becomes a flapping bird,
 *   Email opens and folds into a paper plane;
 *   the bag drawer is moved to the top level of the page so it sits flush with the top of the screen,
 *   and the grey overlay beside it is invisible (clicking beside the drawer still closes it).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-home-cartoons.php
 */

add_action( 'wp_footer', function () {
	$home     = is_front_page();
	$checkout = function_exists( 'is_checkout' ) ? is_checkout() : false;
	if ( ! $home && ! $checkout ) {
		return;
	}
	echo "<script>\n";
	echo <<<'FIKA_LIB'
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

FIKA_LIB;
	echo "</script>\n";
	if ( ! $home ) {
		return;
	}
	echo <<<'FIKA_HOME'
<style>
/* Bag drawer: flush with the top of the screen, no grey veil */
.mx-veil, .mx-veil.on { background: transparent !important; }
.mx-drawer { top: 0 !important; height: 100vh; height: 100dvh; z-index: 100005 !important; }
.mx-veil { z-index: 100004 !important; }
.admin-bar .mx-drawer { top: 32px !important; height: calc(100vh - 32px); }
@media (max-width: 782px) { .admin-bar .mx-drawer { top: 46px !important; height: calc(100vh - 46px); } }

/* One bag only: the floating "Bag 0 kg" button is gone; the header bag icon opens the bag */
#mxFab, .mx-fab { display: none !important; }

/* Header bag icon grows gently with the order and shows how many items are inside */
.fika-cart svg { scale: var(--fk-scale, 1); transition: scale .45s cubic-bezier(.3, 1.6, .5, 1); }
.fika-cart .fk-count {
  position: absolute; top: calc(-6px - (var(--fk-scale, 1) - 1) * 18px); right: calc(-24px - (var(--fk-scale, 1) - 1) * 14px); z-index: 6; height: 21px; padding: 0 7px;
  border-radius: 11px; background: #004aad; color: #fff; border: 2px solid #fdeaf2; white-space: nowrap;
  font: 700 11.5px/17px 'Outfit', 'Open Sans', Arial, sans-serif; text-align: center; box-sizing: border-box;
  transform: scale(0); transition: transform .3s cubic-bezier(.3, 1.6, .5, 1), top .45s, right .45s; pointer-events: none;
}
.fika-cart .fk-count.on { transform: scale(1); }
@media (max-width: 700px) {
  /* keep the kg badge on screen: sit it over the top-left of the bag instead of off its right edge */
  .fika-cart .fk-count { right: auto; left: calc(-30px - (var(--fk-scale, 1) - 1) * 12px); top: calc(-8px - (var(--fk-scale, 1) - 1) * 14px); height: 19px; padding: 0 6px; font-size: 10.5px; line-height: 15px; }
}
.fika-cart .fk-count.pop { animation: fkPop .45s cubic-bezier(.3, 1.6, .5, 1); }
@keyframes fkPop { 0% { transform: scale(1); } 40% { transform: scale(1.45); } 100% { transform: scale(1); } }

/* Footer social icons: Instagram flashes, WhatsApp turns into a flapping bird, Email folds into a paper plane */
.fika-foot-social a { position: relative; overflow: visible !important; }
.fika-foot-social a svg g { transform-box: view-box; }
.fika-foot-social a .fk-alt { opacity: 0; }
/* Instagram: camera flash */
.fika-foot-social a.fk-ig::after { content: ''; position: absolute; inset: -10px; border-radius: 50%; pointer-events: none;
  background: radial-gradient(circle, #fff 0 28%, rgba(255,255,255,.75) 40%, rgba(255,255,255,0) 70%); opacity: 0; transform: scale(.3); }
.fika-foot-social a.fk-ig:hover::after { animation: fkIgBurst .9s ease-out; }
.fika-foot-social a.fk-ig:hover { animation: fkIgBg .9s ease-out; }
.fika-foot-social a.fk-ig .fk-lens { transform-origin: 12px 12px; }
.fika-foot-social a.fk-ig .fk-spark { transform-origin: 17.5px 6.5px; opacity: 0; }
.fika-foot-social a.fk-ig:hover .fk-lens { animation: fkIgLens .9s ease-out; }
.fika-foot-social a.fk-ig:hover .fk-spark { animation: fkIgSpark .9s ease-out; }
@keyframes fkIgBurst { 0% { opacity: 0; transform: scale(.3); } 12% { opacity: 1; transform: scale(1.15); } 32% { opacity: 0; transform: scale(1.5); }
  48% { opacity: .85; transform: scale(1.05); } 75%, 100% { opacity: 0; transform: scale(1.6); } }
@keyframes fkIgBg { 0%, 100% { background: #fdeaf2; } 12%, 48% { background: #fff; } }
@keyframes fkIgLens { 0%, 100% { transform: scale(1); } 12% { transform: scale(.7); } 30% { transform: scale(1.12); } 48% { transform: scale(.8); } 70% { transform: scale(1); } }
@keyframes fkIgSpark { 0% { opacity: 0; transform: scale(.2) rotate(0); } 12% { opacity: 1; transform: scale(1.6) rotate(45deg); } 35% { opacity: 0; transform: scale(.6) rotate(90deg); }
  48% { opacity: 1; transform: scale(1.3) rotate(135deg); } 80%, 100% { opacity: 0; transform: scale(.4) rotate(180deg); } }
/* WhatsApp: bubble turns into a bird that flaps */
.fika-foot-social a.fk-wa .fk-base, .fika-foot-social a.fk-wa .fk-alt { transform-origin: 12px 12px; transition: opacity .25s, transform .35s cubic-bezier(.3, 1.5, .5, 1); }
.fika-foot-social a.fk-wa:hover .fk-base { opacity: 0; transform: scale(.2) rotate(-40deg); }
.fika-foot-social a.fk-wa:hover .fk-alt { opacity: 1; transform: scale(1); transition-delay: .1s; }
.fika-foot-social a.fk-wa .fk-alt { transform: scale(.2) rotate(30deg); }
.fika-foot-social a.fk-wa .fk-bird { transform-origin: 12px 12px; }
.fika-foot-social a.fk-wa .fk-wing { transform-origin: 11.5px 12.2px; }
.fika-foot-social a.fk-wa .fk-wing2 { transform-origin: 11px 12px; }
.fika-foot-social a.fk-wa:hover .fk-bird { animation: fkBob .5s ease-in-out .3s infinite alternate; }
.fika-foot-social a.fk-wa:hover .fk-wing { animation: fkFlap .26s ease-in-out .3s infinite alternate; }
.fika-foot-social a.fk-wa:hover .fk-wing2 { animation: fkFlap2 .26s ease-in-out .3s infinite alternate; }
@keyframes fkBob { from { transform: translate(-.6px, .9px); } to { transform: translate(.6px, -1.4px); } }
@keyframes fkFlap { from { transform: rotate(-12deg) scaleY(1); } to { transform: rotate(18deg) scaleY(-.75); } }
@keyframes fkFlap2 { from { transform: rotate(-20deg) scaleY(.9); } to { transform: rotate(10deg) scaleY(-.6); } }
/* Email: flap opens, envelope folds into a paper plane that glides off */
.fika-foot-social a.fk-ml .fk-flap { transform-origin: 12px 5.5px; }
.fika-foot-social a.fk-ml .fk-env { transform-origin: 12px 12px; }
.fika-foot-social a.fk-ml .fk-alt { transform-origin: 12px 12px; transform: scale(.3) rotate(-30deg); }
.fika-foot-social a.fk-ml .fk-trail { stroke-dasharray: 2 2.2; opacity: 0; }
.fika-foot-social a.fk-ml:hover .fk-flap { animation: fkFlapOpen .3s ease-out forwards; }
.fika-foot-social a.fk-ml:hover .fk-env { animation: fkEnvFold .3s ease-in .3s forwards; }
.fika-foot-social a.fk-ml:hover .fk-alt { animation: fkPlaneIn .35s cubic-bezier(.3, 1.6, .5, 1) .5s forwards, fkGlide 1.4s ease-in-out .9s infinite; }
.fika-foot-social a.fk-ml:hover .fk-trail { animation: fkTrail .9s linear .85s infinite; }
@keyframes fkFlapOpen { to { transform: scaleY(-1); } }
@keyframes fkEnvFold { to { transform: scale(.25, .1) rotate(-25deg); opacity: 0; } }
@keyframes fkPlaneIn { to { opacity: 1; transform: scale(1) rotate(0); } }
@keyframes fkGlide { 0%, 100% { opacity: 1; transform: translate(0, 0) rotate(0); } 50% { opacity: 1; transform: translate(2.2px, -2.2px) rotate(-6deg); } }
@keyframes fkTrail { 0% { opacity: .9; stroke-dashoffset: 0; } 100% { opacity: .9; stroke-dashoffset: -8.4; } }
@media (prefers-reduced-motion: reduce) {
  .fika-foot-social a *, .fika-foot-social a::after { animation: none !important; transition: none !important; }
}

/* Candy flying from a product photo into the bag */
.fk-fly { position: fixed; left: 0; top: 0; width: 42px; height: 42px; z-index: 100003; pointer-events: none; will-change: transform, opacity; }
.fk-fly svg { display: block; width: 100%; height: 100%; overflow: visible; filter: drop-shadow(0 3px 3px rgba(80, 20, 50, .3)); }
.fika-cart.fk-catch svg { animation: fkCatch .42s cubic-bezier(.3, 1.6, .5, 1); }
@keyframes fkCatch { 0% { transform: translateY(0) rotate(0); } 35% { transform: translateY(3px) rotate(-8deg) scale(1.08, .9); } 70% { transform: translateY(-2px) rotate(5deg); } 100% { transform: none; } }

/* Header bag icon: cartoon candies fly out on hover */
.fika-candies i.fk-toon { width: 28px !important; height: 28px !important; left: -14px !important; top: -14px !important;
  background: none !important; border-radius: 0 !important; }
.fika-candies i.fk-toon::before, .fika-candies i.fk-toon::after { display: none !important; }
.fika-candies i.fk-toon svg { display: block; width: 100%; height: 100%; overflow: visible;
  filter: drop-shadow(0 1px 1px rgba(80, 20, 50, .25)); }
</style>
<script>
(function () {
  // Header bag icon: item count badge + size that grows (with diminishing steps) with the grams in the bag
  var lastCount = -1, lastGrams = -1;
  function readBag() {
    try { var st = JSON.parse(localStorage.getItem('fika_bag_v1') || 'null'); return (st && st.bag) || {}; } catch (e) { return {}; }
  }
  function bagBadge() {
    var bag = readBag(), count = 0, grams = 0;
    Object.keys(bag).forEach(function (k) { var g = +bag[k] || 0; if (g > 0) { count++; grams += g; } });
    document.querySelectorAll('.fika-cart').forEach(function (cart) {
      var b = cart.querySelector('.fk-count');
      if (!b) { b = document.createElement('span'); b.className = 'fk-count'; b.setAttribute('aria-hidden', 'true'); cart.appendChild(b); }
      b.textContent = (Math.round(grams / 100) / 10).toFixed(1) + ' kg';
      b.classList.toggle('on', grams > 0);
      if (lastCount >= 0 && (count > lastCount || grams > lastGrams)) { b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop'); }
      var scale = 1 + 0.45 * (1 - Math.exp(-grams / 1500));
      cart.style.setProperty('--fk-scale', scale.toFixed(3));
      var kg = grams >= 1000 ? (Math.round(grams / 100) / 10) + ' kg' : grams + ' g';
      cart.setAttribute('aria-label', count ? 'Bag, ' + count + (count === 1 ? ' item, ' : ' items, ') + kg : 'Bag, empty');
    });
    lastCount = count; lastGrams = grams;
  }
  window.addEventListener('fikabag', function () { setTimeout(bagBadge, 0); });
  window.addEventListener('storage', function (e) { if (e.key === 'fika_bag_v1') bagBadge(); });

  // Pressing + on a candy sends cartoon versions of it flying from its photo into the header bag
  var flyReady = false, prodMap = null;
  function products() {
    if (prodMap) return Promise.resolve(prodMap);
    return fetch('/wp-json/wc/store/v1/products?per_page=100', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (list) {
        prodMap = {};
        (list || []).forEach(function (p) { prodMap[p.id] = { slug: p.slug, cats: (p.categories || []).map(function (c) { return c.slug; }), name: p.name }; });
        return prodMap;
      }).catch(function () { prodMap = {}; return prodMap; });
  }
  function slugsFor(cardId) {
    var m = /^(r?)w(\d+)$/.exec(cardId || '');
    if (!m || !prodMap) return [];
    var p = prodMap[m[2]];
    if (!p) return [];
    if (p.cats.indexOf('ready-mix') === -1) return [p.slug, p.slug, p.slug];
    var nm = (p.name || '').toLowerCase(), want = [];
    if (nm.indexOf('sweet') !== -1) want.push('sweet');
    if (nm.indexOf('sour') !== -1) want.push('sour');
    if (!want.length) want = ['sweet', 'sour'];
    var pool = Object.keys(prodMap).filter(function (id) {
      var q = prodMap[id];
      return window.FIKA_CARTOON(q.slug) ? want.some(function (w) { return q.cats.indexOf(w) !== -1; }) : false;
    }).map(function (id) { return prodMap[id].slug; });
    pool.sort(function () { return Math.random() - 0.5; });
    return pool.slice(0, 4);
  }
  function flyOne(slug, from, to, delay, last) {
    var svgStr = window.FIKA_CARTOON(slug);
    if (!svgStr) return;
    var el = document.createElement('div');
    el.className = 'fk-fly';
    el.innerHTML = svgStr;
    document.body.appendChild(el);
    var sx = from.x + (Math.random() - 0.5) * from.w * 0.4, sy = from.y + (Math.random() - 0.5) * from.h * 0.3;
    var ex = to.x, ey = to.y;
    var cx = (sx + ex) / 2 + (Math.random() - 0.5) * 80, cy = Math.min(sy, ey) - 110 - Math.random() * 60;
    var spin = (Math.random() > 0.5 ? 1 : -1) * (300 + Math.random() * 240);
    var frames = [], N = 14;
    for (var i = 0; i <= N; i++) {
      var t = i / N, u = 1 - t;
      var x = u * u * sx + 2 * u * t * cx + t * t * ex - 21;
      var y = u * u * sy + 2 * u * t * cy + t * t * ey - 21;
      var sc = t < 0.15 ? 0.6 + t / 0.15 * 0.6 : 1.2 - (t - 0.15) / 0.85 * 0.85;
      frames.push({ transform: 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px) rotate(' + (spin * t).toFixed(0) + 'deg) scale(' + sc.toFixed(2) + ')',
                    opacity: t > 0.92 ? (1 - t) / 0.08 : 1 });
    }
    var a = el.animate(frames, { duration: 780 + Math.random() * 160, delay: delay, easing: 'cubic-bezier(.45,.05,.55,.95)', fill: 'both' });
    a.onfinish = function () {
      if (el.parentNode) el.parentNode.removeChild(el);
      if (last) {
        document.querySelectorAll('.fika-cart').forEach(function (c) { c.classList.remove('fk-catch'); void c.offsetWidth; c.classList.add('fk-catch'); });
        var b = document.querySelector('.fika-cart .fk-count');
        if (b) { b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop'); }
      }
    };
  }
  function flyInit() {
    if (flyReady) return;
    flyReady = true;
    if (window.matchMedia) { if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; }
    products();
    // capture phase: measure the card before the grid re-renders after the click
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('.mx-card button[data-a="+"]') : null;
      if (!btn) return;
      if (!window.FIKA_CARTOON) return;
      var card = btn.closest('.mx-card');
      var img = card.querySelector('.mx-img') || card;
      var r = img.getBoundingClientRect();
      var cart = document.querySelector('.fika-cart svg') || document.querySelector('.fika-cart');
      if (!cart) return;
      var c = cart.getBoundingClientRect();
      var from = { x: r.left + r.width / 2, y: r.top + r.height / 2, w: r.width, h: r.height };
      var to = { x: c.left + c.width / 2, y: c.top + c.height / 2 };
      var id = card.getAttribute('data-id');
      products().then(function () {
        var list = slugsFor(id);
        list.forEach(function (slug, i) { flyOne(slug, from, to, i * 110, i === list.length - 1); });
      });
    }, true);
  }

  // Footer social icons: richer SVGs for the hover animations (Instagram flash, WhatsApp bird, Email plane)
  var ICONS = {
    Instagram: ['fk-ig',
      '<g class="fk-base"><rect x="3" y="3" width="18" height="18" rx="5"/><g class="fk-lens"><circle cx="12" cy="12" r="4"/></g>' +
      '<circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>' +
      '<g class="fk-spark"><path d="M17.5 3.8v1.4M17.5 7.8v1.4M14.8 6.5h1.4M18.8 6.5h1.4" stroke-width="1.4"/></g></g>'],
    WhatsApp: ['fk-wa',
      '<g class="fk-base"><path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.4z"/>' +
      '<path d="M9 8.6c0 3.3 3 6.4 6.4 6.4l1.2-1.6-2-1-1 .8a5 5 0 0 1-2.8-2.8l.8-1-1-2z" fill="currentColor" stroke="none"/></g>' +
      '<g class="fk-alt"><g class="fk-bird">' +
        '<g class="fk-wing2"><path d="M11 12 C10 8.2 11.6 6 14 5.6 C13.8 8.6 12.8 10.8 11 12 Z" fill="currentColor" stroke="none" opacity=".45"/></g>' +
        '<path d="M6.8 13.2 L2.8 11 L3.8 15.3 Z" fill="currentColor" stroke="none"/>' +
        '<ellipse cx="11.6" cy="13.4" rx="5.6" ry="4" fill="currentColor" stroke="none"/>' +
        '<circle cx="16.6" cy="10.8" r="2.9" fill="currentColor" stroke="none"/>' +
        '<path d="M19.3 10.4 L22.2 11.3 L19.3 12.1 Z" fill="#f5a623" stroke="none"/>' +
        '<circle cx="17.4" cy="10.2" r=".65" fill="#fdeaf2" stroke="none"/>' +
        '<g class="fk-wing"><path d="M11.5 12.2 C9.2 7.4 11.8 4.6 15.2 4.8 C14.8 8.4 13.6 10.9 11.5 12.2 Z" fill="currentColor" stroke="#fdeaf2" stroke-width=".9"/></g>' +
      '</g></g>'],
    Email: ['fk-ml',
      '<g class="fk-base"><g class="fk-env"><rect x="3" y="5.5" width="18" height="13.5" rx="2"/>' +
        '<g class="fk-flap"><path d="M3.6 6.6 L12 13 L20.4 6.6" /></g></g></g>' +
      '<g class="fk-alt"><path class="fk-trail" d="M1.5 20.5 Q5 18.5 8.5 15.5" stroke-width="1.4"/>' +
        '<path d="M2.6 11.6 L21.4 3.4 L15.2 20.4 L11.2 13.2 Z" fill="#fff" stroke-linejoin="round"/>' +
        '<path d="M11.2 13.2 L21.4 3.4 M11.2 13.2 L11.6 17.6 L13.6 15.8" stroke-linejoin="round"/></g>']
  };
  function footerIcons() {
    document.querySelectorAll('.fika-foot-social a').forEach(function (a) {
      var spec = ICONS[a.getAttribute('aria-label')];
      if (!spec) return;
      if (a.classList.contains(spec[0])) return;
      var svg = a.querySelector('svg');
      if (!svg) return;
      a.classList.add(spec[0]);
      svg.innerHTML = spec[1];
    });
  }

  function init() {
    // Move the bag drawer, its veil and the floating Bag button to the top level of the page:
    // inside the page content they are offset by the layout's transforms.
    ['mxVeil', 'mxDrawer', 'mxFab'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    bagBadge();
    flyInit();
    footerIcons();

    if (!window.FIKA_CARTOON) return;
    var slugs = (window.FIKA_CARTOON_SLUGS || []).slice();
    if (!slugs.length) return;
    function fill() {
      var pool = slugs.slice();
      pool.sort(function () { return Math.random() - 0.5; });
      document.querySelectorAll('.fika-candies i').forEach(function (i, n) {
        i.classList.add('fk-toon');
        i.innerHTML = window.FIKA_CARTOON(pool[n % pool.length]);
      });
    }
    fill();
    // a fresh handful of candies every time the bag is hovered
    document.querySelectorAll('.fika-cart').forEach(function (c) { c.addEventListener('mouseenter', fill); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  window.addEventListener('load', init);
})();
</script>
FIKA_HOME;
}, 5 );
