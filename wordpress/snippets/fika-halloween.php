<?php
/**
 * Fika: Halloween week look. Colours and decorations only: night colours, pumpkin orange, skulls, bats, cobwebs and
 * a spider over the normal site. Every page, button and checkout step works exactly as before.
 * - Installed switched off. One click on "Halloween look" in the WP admin top bar switches it on (option fika_hw_on),
 *   another click switches it off again. It also goes off by itself at FIKA_HW_END (1 November 2026, 00:00 Beirut).
 *   Deactivating the snippet removes it completely; the normal site underneath is never changed.
 * - GET /wp-json/fika/v1/halloween (shop admins): whether it is on, the end date and the version installed.
 * - Every fish (rewards lane, sign-up card, Fika Club card and pop-up) is drawn as a spider, and the fish cursor is a
 *   jack-o'-lantern (wordpress/cursor/halloween), lit and tilted over links and buttons.
 * - The page cache (LiteSpeed) is cleared at each click and when it ends, so no visitor gets a stale look.
 * - Built from wordpress/snippets/fika-halloween.css and fika-halloween.js by build-halloween.py (edit those, rebuild).
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-halloween.php
 */

if ( ! defined( 'FIKA_HW_END' ) ) {
	define( 'FIKA_HW_END', '2026-11-01 00:00' ); // Beirut time
	define( 'FIKA_HW_VERSION', '8f2d090a14' );
}

if ( ! function_exists( 'fika_hw_on' ) ) {
	function fika_hw_end_ts() {
		return ( new DateTime( FIKA_HW_END, new DateTimeZone( 'Asia/Beirut' ) ) )->getTimestamp();
	}
	function fika_hw_on() {
		return time() < fika_hw_end_ts() && ! is_admin() && get_option( 'fika_hw_on' );
	}
}

// the switch in the admin top bar (shop admins only, until the end date)
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'manage_options' ) || time() >= fika_hw_end_ts() ) {
		return;
	}
	$off = ! get_option( 'fika_hw_on' );
	$bar->add_node( array(
		'id'    => 'fika-hw',
		'title' => $off ? '&#127875; Halloween look: off (click to switch on)' : '&#127875; Halloween look: on (click to switch off)',
		'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=fika_hw_toggle' ), 'fika_hw_toggle' ),
	) );
}, 100 );
add_action( 'admin_post_fika_hw_toggle', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'fika_hw_toggle' );
	$on = get_option( 'fika_hw_on' ) ? 0 : 1;
	update_option( 'fika_hw_on', $on, false );
	if ( $on ) {
		update_option( 'fika_hw_live', FIKA_HW_VERSION, false );
	}
	do_action( 'litespeed_purge_all' );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
	exit;
} );

// while it is on: clear the page cache if the look itself was updated, and once more when it ends
add_action( 'init', function () {
	if ( time() >= fika_hw_end_ts() || ! get_option( 'fika_hw_on' ) ) {
		return;
	}
	if ( get_option( 'fika_hw_live' ) !== FIKA_HW_VERSION ) {
		update_option( 'fika_hw_live', FIKA_HW_VERSION, false );
		do_action( 'litespeed_purge_all' );
	}
	if ( ! wp_next_scheduled( 'fika_hw_ended' ) ) {
		wp_schedule_single_event( fika_hw_end_ts() + 60, 'fika_hw_ended' );
	}
} );
add_action( 'fika_hw_ended', function () {
	do_action( 'litespeed_purge_all' );
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/halloween', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () {
			return array( 'on' => (bool) get_option( 'fika_hw_on' ), 'ends' => FIKA_HW_END . ' Beirut', 'version' => FIKA_HW_VERSION, 'ended' => time() >= fika_hw_end_ts() );
		},
	) );
} );

add_filter( 'body_class', function ( $classes ) {
	if ( fika_hw_on() ) {
		$classes[] = 'fika-hw';
	}
	return $classes;
} );

add_action( 'wp_head', function () {
	if ( ! fika_hw_on() ) {
		return;
	}
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Creepster&display=swap">' . "\n";
	echo "<style id=\"fika-hw\">\n" . <<<'FIKA_HW_CSS'
/* Fika Halloween week: colours and decorations only, layered over the normal site (body.fika-hw). */
body.fika-hw {
  --hw-night: #140e22;
  --hw-deep: #1c1430;
  --hw-plum: #2b1c47;
  --hw-line: rgba(244, 236, 220, .14);
  --hw-orange: #ff7b1c;
  --hw-orange-d: #e8640c;
  --hw-bone: #f4ecdc;
  --hw-muted: #c3b6dc;
  --hw-slime: #a6e35b;
  --hw-skull: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill-rule='evenodd' d='M12 1.6c-5.4 0-9.1 3.8-9.1 8.7 0 2.8 1.2 4.8 3.1 6V19c0 .7.5 1.2 1.2 1.2H9v-2.1h1.6v2.1h2.8v-2.1H15v2.1h1.8c.7 0 1.2-.5 1.2-1.2v-2.7c1.9-1.2 3.1-3.2 3.1-6 0-4.9-3.7-8.7-9.1-8.7zM8.4 8.6a2.3 2.3 0 1 0 0 4.6 2.3 2.3 0 0 0 0-4.6zm7.2 0a2.3 2.3 0 1 0 0 4.6 2.3 2.3 0 0 0 0-4.6zM12 13.4l-1.3 2.4h2.6z'/%3E%3C/svg%3E");
  --hw-bat: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 32'%3E%3Cpath d='M32 9c-1.2 0-2 .9-2.4 2.1L28.4 9l-.9 3.3C24 7.6 16.6 4.3 8 5.6c3.3 1.7 5 4.4 4.9 7.4C9.6 11.6 5 11.9 0 14.9c5.6.1 10.4 2.6 12.6 6.6 1.5-2.4 4.3-3.4 6.6-2.3.4-2.4 2.9-3.7 5.2-2.7 1.9.9 3.7 3.6 7.6 9.5 3.9-5.9 5.7-8.6 7.6-9.5 2.3-1 4.8.3 5.2 2.7 2.3-1.1 5.1-.1 6.6 2.3 2.2-4 7-6.5 12.6-6.6-5-3-9.6-3.3-12.9-1.9-.1-3 1.6-5.7 4.9-7.4-8.6-1.3-16 2-19.5 6.7L35.6 9l-1.2 2.1C34 9.9 33.2 9 32 9z'/%3E%3C/svg%3E");
  background: var(--hw-night) !important;
  color: var(--hw-bone);
}
html:has(body.fika-hw) { background: var(--hw-night); }

/* the shop's own colour variables: blue becomes pumpkin orange, pink becomes night plum */
body.fika-hw .fika-hero, body.fika-hw .fika-header, body.fika-hw .fika-sec, body.fika-hw .mx-page,
body.fika-hw .mx-fab, body.fika-hw .mx-drawer, body.fika-hw .mx-veil, body.fika-hw .fika-banner, body.fika-hw .fika-bagviz {
  --fika-blue: var(--hw-orange);
  --fika-pink: var(--hw-plum);
}

/* ---------- banner and header ---------- */
body.fika-hw .fika-banner { background: #0b0714 !important; color: var(--hw-orange) !important; border-bottom: 1px solid rgba(255, 123, 28, .35); }
body.fika-hw .fika-banner a { color: var(--hw-orange) !important; }
body.fika-hw .hw-sk { display: inline-block; width: 1.05em; height: 1.05em; margin: 0 .55em; vertical-align: -.17em; background: var(--hw-bone);
  -webkit-mask: var(--hw-skull) center / contain no-repeat; mask: var(--hw-skull) center / contain no-repeat; }
body.fika-hw .fika-header.fika-scrolled, body.fika-hw .fika-header:not(.fika-top) { background: rgba(20, 14, 34, .9) !important; box-shadow: 0 4px 22px rgba(0, 0, 0, .5) !important; }
body.fika-hw .fika-logo { color: var(--hw-orange) !important; text-shadow: 0 0 18px rgba(255, 123, 28, .55), 0 2px 0 #6b2a00; }
body.fika-hw .fika-nav a, body.fika-hw .fika-nav .fika-menu-btn, body.fika-hw .fika-cart { color: var(--hw-bone) !important; }
body.fika-hw .fika-nav a:hover, body.fika-hw .fika-nav a.on { border-bottom-color: var(--hw-orange) !important; color: var(--hw-orange) !important; }
@media (max-width: 760px) {
  body.fika-hw .fika-nav .fika-nav-links { background: var(--hw-deep) !important; border: 1px solid var(--hw-line); }
  body.fika-hw .fika-nav .fika-nav-links a:hover, body.fika-hw .fika-nav .fika-nav-links a.on { background: var(--hw-plum) !important; }
}
@media (min-width: 761px) { body.fika-hw .fika-nav .fika-nav-links { background: none !important; } }

/* ---------- heroes: the same photos, at night ---------- */
body.fika-hw .fika-hero .fika-title, body.fika-hw .fika-hero .fsh-title {
  color: var(--hw-orange) !important; text-shadow: 0 0 26px rgba(255, 123, 28, .55), 0 3px 0 #5c2400 !important;
}
body.fika-hw .fika-hero .fika-text, body.fika-hw .fika-hero .fsh-text { color: var(--hw-bone) !important; text-shadow: 0 2px 10px rgba(0, 0, 0, .8) !important; }
body.fika-hw .fika-btn { background: var(--hw-orange) !important; color: #1b0d00 !important; box-shadow: 0 0 22px rgba(255, 123, 28, .45); }
body.fika-hw .fika-btn:hover { background: var(--hw-orange-d) !important; }
/* the googly eyes glow a little */
body.fika-hw .fika-eye { box-shadow: 0 0 10px rgba(166, 227, 91, .75), 0 0 0 1px rgba(0, 0, 0, .5) !important; }

/* the bag weight next to the bag icon */
body.fika-hw .fika-cart .fk-count { background: var(--hw-orange) !important; color: #1b0d00 !important; border-color: #1b0d00 !important; }

/* ---------- sections ---------- */
body.fika-hw .fika-sec, body.fika-hw .fika-sec.fika-whitebg, body.fika-hw .mx-page {
  background-color: var(--hw-night) !important;
  background-image: none !important;
}
body.fika-hw .fika-sec.fika-pinkbg { background-color: var(--hw-deep) !important; }
body.fika-hw .fika-h2, body.fika-hw .mx-h1, body.fika-hw .fika-title {
  font-family: 'Creepster', 'NF Le Petit Cochon', cursive !important; font-variant: normal !important; letter-spacing: .03em;
  color: var(--hw-orange) !important; text-shadow: 0 0 18px rgba(255, 123, 28, .35);
}
body.fika-hw .fika-hero .fika-title { font-family: 'NF Le Petit Cochon', cursive !important; font-variant: small-caps !important; letter-spacing: 0; }
body.fika-hw .fika-h2::before, body.fika-hw .mx-h1::before {
  content: ''; display: inline-block; width: .8em; height: .8em; margin-right: .3em; vertical-align: -.05em; background: var(--hw-bone);
  -webkit-mask: var(--hw-skull) center / contain no-repeat; mask: var(--hw-skull) center / contain no-repeat;
}
body.fika-hw .fika-sec, body.fika-hw .fika-sec p, body.fika-hw .fika-text { color: var(--hw-bone); }
body.fika-hw .mx-count, body.fika-hw .mx-empty { color: var(--hw-muted) !important; }

/* ---------- candy cards: little tombstones ---------- */
body.fika-hw .mx-img {
  background: #f1e7d2 !important; border: 2px solid #5a4a72 !important;
  border-radius: 48% 48% 10px 10px / 26% 26% 10px 10px !important;
  box-shadow: inset 0 -10px 18px rgba(60, 40, 90, .18), 0 8px 22px rgba(0, 0, 0, .45);
}
body.fika-hw .mx-img img { mix-blend-mode: multiply; }
body.fika-hw .mx-card:hover .mx-img { border-color: var(--hw-orange) !important; box-shadow: 0 0 0 1px var(--hw-orange), 0 0 26px rgba(255, 123, 28, .4) !important; }
body.fika-hw .mx-card.has .mx-img { border-color: var(--hw-slime) !important; }
body.fika-hw .mx-name, body.fika-hw .mx-price { color: var(--hw-bone) !important; }
body.fika-hw .mx-link { color: var(--hw-orange) !important; text-decoration-color: var(--hw-orange) !important; }
body.fika-hw .mx-price { color: var(--hw-orange) !important; }
body.fika-hw .mx-badge { background: #0b0714 !important; color: var(--hw-slime) !important; left: 50% !important; top: 10px !important; transform: translateX(-50%); border-radius: 999px; white-space: nowrap; padding: 4px 12px !important; line-height: 18px !important; box-shadow: 0 2px 8px rgba(0, 0, 0, .35); }
body.fika-hw .mx-add { background: var(--hw-orange) !important; color: #1b0d00 !important; box-shadow: 0 0 16px rgba(255, 123, 28, .35) !important; }
body.fika-hw .mx-add:hover { background: var(--hw-orange-d) !important; }
body.fika-hw .mx-pill { background: var(--hw-plum) !important; }
body.fika-hw .mx-pill button { background: var(--hw-orange) !important; color: #1b0d00 !important; }
body.fika-hw .mx-pill span { color: var(--hw-bone) !important; }
body.fika-hw .mx-prog { background: var(--hw-plum) !important; }
body.fika-hw .mx-nav { background: var(--hw-plum) !important; color: var(--hw-bone) !important; }
body.fika-hw .mx-nav:hover { background: var(--hw-orange) !important; color: #1b0d00 !important; }
body.fika-hw .mx-loading .mx-card .mx-img { background: linear-gradient(100deg, #2b1c47 30%, #3a2760 50%, #2b1c47 70%) !important; background-size: 200% 100% !important; }

/* home: the hand-drawn "All pick & mix" link */
body.fika-hw #shop .fk-allpm { color: var(--hw-slime) !important; }
body.fika-hw #shop .fk-allpm b { color: var(--hw-orange) !important; }
body.fika-hw #shop .fk-allpm .sq { stroke: rgba(166, 227, 91, .6) !important; }
body.fika-hw #shop .fk-allpm:hover { color: var(--hw-orange) !important; }

/* filters and search on Mix your own */
body.fika-hw .fs-bar { background: var(--hw-night) !important; border-color: var(--hw-line) !important; }
body.fika-hw .fs-chip { background: transparent !important; color: var(--hw-bone) !important; border-color: var(--hw-orange) !important; }
body.fika-hw .fs-chip.on { background: var(--hw-orange) !important; color: #1b0d00 !important; }
body.fika-hw .fs-search { background: transparent !important; }
body.fika-hw .fs-search input { background: var(--hw-deep) !important; color: var(--hw-bone) !important; border-color: var(--hw-line) !important; }

/* ---------- the bag (drawer) and its button ---------- */
body.fika-hw .mx-fab { background: var(--hw-orange) !important; color: #1b0d00 !important; box-shadow: 0 0 24px rgba(255, 123, 28, .5) !important; }
body.fika-hw .mx-fab b { background: #1b0d00 !important; color: var(--hw-orange) !important; }
body.fika-hw .mx-drawer { background: var(--hw-deep) !important; color: var(--hw-bone); }
body.fika-hw .mx-dh { background: var(--hw-plum) !important; }
body.fika-hw .mx-dh h3, body.fika-hw .mx-sug h4 { color: var(--hw-orange) !important; }
body.fika-hw .mx-x, body.fika-hw .mx-sug-nav button { background: var(--hw-night) !important; color: var(--hw-bone) !important; border-color: var(--hw-line) !important; }
body.fika-hw .mx-line, body.fika-hw .mx-df, body.fika-hw .mx-none { color: var(--hw-bone) !important; border-color: var(--hw-line) !important; }
body.fika-hw .mx-line small, body.fika-hw .mx-note, body.fika-hw .mx-clear { color: var(--hw-muted) !important; }
body.fika-hw .mx-qb, body.fika-hw .mx-go { background: var(--hw-orange) !important; color: #1b0d00 !important; }
body.fika-hw .mx-sc { background: var(--hw-plum) !important; border-color: var(--hw-line) !important; color: var(--hw-bone) !important; }
body.fika-hw .mx-sc b { color: var(--hw-bone) !important; }
body.fika-hw .mx-sc small { color: var(--hw-orange) !important; }

/* ---------- home: reviews, cards, FAQ ---------- */
body.fika-hw .fika-card { background: var(--hw-deep) !important; box-shadow: 0 6px 24px rgba(0, 0, 0, .4) !important; border: 1px solid var(--hw-line); }
body.fika-hw .fika-card h3, body.fika-hw .fika-card .fika-note { color: var(--hw-orange) !important; }
body.fika-hw .fika-icon { background: var(--hw-plum) !important; color: var(--hw-orange) !important; }
body.fika-hw .fika-tile { background: var(--hw-orange) !important; color: #1b0d00 !important; }
body.fika-hw .fika-tile.fika-light { background: var(--hw-plum) !important; color: var(--hw-bone) !important; border-color: var(--hw-orange) !important; }
body.fika-hw .fika-faq details { background: var(--hw-deep) !important; border: 1px solid var(--hw-line); }
body.fika-hw .fika-faq summary { color: var(--hw-bone) !important; }
body.fika-hw .fika-faq summary::after { color: var(--hw-orange) !important; }
body.fika-hw .fika-faq .fika-answer, body.fika-hw .fika-faq .fika-answer p { color: var(--hw-muted) !important; }
body.fika-hw .fika-faq .fika-answer a { color: var(--hw-orange) !important; }
body.fika-hw .fk-rev, body.fika-hw .fk-vr { color: var(--hw-bone); }
body.fika-hw .fk-vr-sub { color: var(--hw-muted) !important; }
body.fika-hw .fk-vr-arrow { background: var(--hw-plum) !important; color: var(--hw-bone) !important; }

/* ---------- footer ---------- */
body.fika-hw .fika-footer.fika-sec { background: var(--hw-night) !important; }
body.fika-hw .fika-footer .fika-foot-body { background: #0b0714 !important; }
body.fika-hw .fika-footer svg path[fill="#004aad"], body.fika-hw .fika-footer svg [fill="#004aad"] { fill: #0b0714 !important; }
body.fika-hw .fika-footer .fika-foot-logo { color: var(--hw-orange) !important; text-shadow: 0 0 18px rgba(255, 123, 28, .5); }
body.fika-hw .fika-footer .fika-foot-tag, body.fika-hw .fika-footer li a { color: var(--hw-bone) !important; }
body.fika-hw .fika-footer h4 { color: var(--hw-orange) !important; }
body.fika-hw .fika-footer .fika-foot-social a { background: var(--hw-plum) !important; color: var(--hw-orange) !important; }
body.fika-hw .hw-skulls { display: flex; justify-content: center; gap: 18px; padding: 26px 0 6px; opacity: .9; }
body.fika-hw .hw-skulls i { width: 22px; height: 22px; background: var(--hw-orange);
  -webkit-mask: var(--hw-skull) center / contain no-repeat; mask: var(--hw-skull) center / contain no-repeat; }
body.fika-hw .hw-skulls i:nth-child(even) { background: var(--hw-bone); transform: translateY(4px) rotate(-8deg); }

/* ---------- decorations: bats, webs, a spider ---------- */
.hw-fx { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 1;
  /* the hero photo at night: a moon glow top right, darker towards the words */
  background:
    radial-gradient(ellipse 38% 46% at 84% 22%, rgba(255, 190, 90, .26), transparent 70%),
    linear-gradient(180deg, rgba(20, 14, 34, .7) 0%, rgba(43, 28, 71, .5) 45%, rgba(20, 14, 34, .9) 100%); }
.hw-bat { position: absolute; left: -110px; width: 84px; height: 42px; background: #0a0612; filter: drop-shadow(0 0 5px rgba(255, 140, 50, .85));
  -webkit-mask: var(--hw-bat) center / contain no-repeat; mask: var(--hw-bat) center / contain no-repeat;
  animation: hwFly var(--t, 16s) linear var(--d, 0s) infinite; }
.hw-bat::after { content: ''; }
@keyframes hwFly {
  0% { transform: translate(0, 0) scale(var(--s, 1)) scaleY(1); }
  10% { transform: translate(12vw, -18px) scale(var(--s, 1)) scaleY(.55); }
  20% { transform: translate(24vw, 10px) scale(var(--s, 1)) scaleY(1); }
  30% { transform: translate(36vw, -14px) scale(var(--s, 1)) scaleY(.55); }
  40% { transform: translate(48vw, 6px) scale(var(--s, 1)) scaleY(1); }
  50% { transform: translate(60vw, -20px) scale(var(--s, 1)) scaleY(.55); }
  60% { transform: translate(72vw, 4px) scale(var(--s, 1)) scaleY(1); }
  70% { transform: translate(84vw, -10px) scale(var(--s, 1)) scaleY(.55); }
  80%, 100% { transform: translate(calc(100vw + 160px), 0) scale(var(--s, 1)) scaleY(1); }
}
.hw-web { position: absolute; top: 0; width: min(30vw, 260px); height: min(30vw, 260px); opacity: .5; }
.hw-web.l { left: 0; }
.hw-web.r { right: 0; transform: scaleX(-1); }
.hw-spider { position: absolute; top: 0; right: 31%; width: 2px; height: 120px; background: linear-gradient(rgba(244, 236, 220, .6), rgba(244, 236, 220, .25));
  transform-origin: top; animation: hwDangle 5.5s ease-in-out infinite; }
.hw-spider i { position: absolute; bottom: -14px; left: -10px; width: 22px; height: 18px; border-radius: 50%; background: #07040d;
  box-shadow: 0 0 0 1px rgba(244, 236, 220, .25); }
.hw-spider i::before, .hw-spider i::after { content: ''; position: absolute; top: 5px; width: 34px; height: 8px; left: -6px;
  border: 2px solid #07040d; border-bottom: 0; border-radius: 50% 50% 0 0; }
.hw-spider i::after { top: 9px; }
@keyframes hwDangle { 0%, 100% { height: 110px; transform: rotate(2deg); } 50% { height: 170px; transform: rotate(-2deg); } }
@media (max-width: 700px) { .hw-spider { right: 22%; } .hw-bat { width: 52px; height: 26px; } }
@media (prefers-reduced-motion: reduce) { .hw-bat, .hw-spider { animation: none; } .hw-bat { display: none; } }

/* ---------- About page ---------- */
body.fika-hw .fa-lede { color: var(--hw-orange) !important; }
body.fika-hw .fa-say { background: var(--hw-plum) !important; color: var(--hw-bone) !important; border-color: var(--hw-orange) !important; }
body.fika-hw .fa-step { color: var(--hw-bone); }

/* ---------- cart, checkout, My account: a night page, the forms stay light so they read easily ---------- */
body.fika-hw.woocommerce-page, body.fika-hw.woocommerce-account {
  --fika-blue: #5b2fa3;
  --fika-pink: #f1e8fb;
  background: var(--hw-night) !important;
  background-image: radial-gradient(ellipse 70% 35% at 50% 0%, rgba(124, 77, 255, .16), transparent 70%) !important;
}
body.fika-hw .fika-shopbar .fs-logo { color: var(--hw-orange) !important; text-shadow: 0 0 18px rgba(255, 123, 28, .5); }
body.fika-hw .fika-shopbar nav a { color: var(--hw-bone) !important; }
body.fika-hw .fika-shopbar nav a:hover { border-bottom-color: var(--hw-orange) !important; }
body.fika-hw .fika-shoptitle h1 { font-family: 'Creepster', 'NF Le Petit Cochon', cursive !important; font-variant: normal !important; letter-spacing: .03em;
  color: var(--hw-orange) !important; text-shadow: 0 0 18px rgba(255, 123, 28, .35); }
body.fika-hw .fika-shoptitle h1::before { content: ''; display: inline-block; width: .8em; height: .8em; margin-right: .3em; vertical-align: -.05em; background: var(--hw-bone);
  -webkit-mask: var(--hw-skull) center / contain no-repeat; mask: var(--hw-skull) center / contain no-repeat; }
body.fika-hw .fika-shoptitle, body.fika-hw .fika-shoptitle p { color: var(--hw-bone) !important; }
body.fika-hw .fika-shoptitle a, body.fika-hw .fika-shoptitle .fika-back { color: var(--hw-orange) !important; }
html body.fika-hw .wc-block-components-checkout-place-order-button.wc-block-components-button, html body.fika-hw .wc-block-cart__submit-button.wc-block-components-button,
html body.fika-hw .wc-block-components-checkout-place-order-button, html body.fika-hw .wc-block-cart__submit-button,
html body.fika-hw.woocommerce-account .woocommerce .button, html body.fika-hw.woocommerce-account .woocommerce button.button {
  background: var(--hw-orange) !important; color: #1b0d00 !important; box-shadow: 0 0 22px rgba(255, 123, 28, .4) !important; }
html body.fika-hw .wc-block-components-checkout-place-order-button.wc-block-components-button:hover, html body.fika-hw .wc-block-cart__submit-button.wc-block-components-button:hover,
html body.fika-hw.woocommerce-account .woocommerce .button:hover { background: var(--hw-orange-d) !important; }
html body.fika-hw .wc-block-components-checkout-place-order-button *, html body.fika-hw .wc-block-cart__submit-button * { color: #1b0d00 !important; }
body.fika-hw .fika-shophelp { color: var(--hw-muted) !important; }
body.fika-hw .fika-shophelp a { color: var(--hw-orange) !important; }

/* ---------- the rewards spider (was the fish) ---------- */
.hw-spd { overflow: visible; }
.hw-spd .hw-leg-a, .hw-spd .hw-leg-b { transform-box: view-box; transform-origin: 50% 58%; animation: hwLegs .5s ease-in-out infinite alternate; }
.hw-spd .hw-leg-b { animation-delay: -.25s; }
@keyframes hwLegs { from { rotate: -5deg; } to { rotate: 5deg; } }
@media (prefers-reduced-motion: reduce) { .hw-spd .hw-leg-a, .hw-spd .hw-leg-b { animation: none; } }

/* ---------- the jack-o'-lantern cursor (instead of the fish), lit and tilted over links and buttons ---------- */
body.fika-hw, body.fika-hw *, html.fika-biting body.fika-hw, html.fika-biting body.fika-hw * {
  cursor: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAK8UlEQVR42t2Yf4xc1XXHP+e++96b2Z3ZXa8X/8DGPzB2iWPHwQWEQ0lqF2hBISTFCRCqNoiSyE3pjzRNqEiUVhBoqyZKm6olFY2UNg2kEahtnKaGlJ+BONCEOmAaA4Ym2F577bXnx86PN+/ee/rHzNpr76xR1KhIvdLVmzdv5r7v/Z5zvvecI6o6EkL4tjFmPpADL3ifP9XKs+/g2FUulydUNRIRzxsxqtV981V18rUjL+pzr3xHvXZ0enjvD6nqtQCqGr8R+KyqDwCiJnz8r69lsDDE0jNW6qZ1V5j3bt62ALjPOWdE5F5VjUUk/z9FqKrSyTtfUVW9/9G73dt/c0iv/P2z9O0fLus9X7/Dd4n0ququeyOYFIBms3lWkiYveJ8P3PK5K2Xf4ZdkIC1zpHaQGy77iP76Oz+hIQRjjF4vYu/rx6SqSm89048HQEUk/KQAjaragYGB14KGP07igtn27j9y3ns6rs1oeQH/8NBn5Z7td4gxJoQg96q660QkV9VEVe30FBEVkSAirs/0IhJU1cz8T29Tp2dwxs5j591zNrKrn/jBdnfn333IxjYltglH6xMnM6l6vVh736y16kfXU0jXYAcGT3rSrh4i1J6TwWX7+7hYBAQR0TlNrKqmt8O1PvhvRSZa/MSu7f7Ov/9Q1AekeCCCe4HOjHXOA9afhowG8G1gHKjQaf0HmdshQ0NHpl2kH0iZuRMR8aq61rn8362NF80CWTvEr/zSx/SmLbcIrgL1SRgow/DZEDqw+xvw/D97Du1WWlWILJQX4ZdtMtHqSw2DwzB/KRQWTL/2EHn+eeL4T0TETRPVF2APpO398ATIH2wPn/7SzSaxCXGUUHFtrimM6TU/+i+Py8gDTNoFDI8OmOWDkwYNiC1AZHGZI5tq4totMFYbLRcO1YzOX72OxVfcJPGWD0cQQfCPYaLrRWT8VJDSxyesiLhMs3WRNw9EkT3nid07uOu+35bYdYgkYtJ3uLM+xepGxoG2Z/mSAvOGEjApiIAGWvWMrO0AQYyAKiKC947x8SqRh6UXXKDpr/2ts0vXx8G7XSaybwPavYhX+klCj8E4lfT5SPiGglyyaK2/pR3ohEBNhG3tjDWNNgdajjefO49588sgMaiH4GhUWmTNDoIiBAgeNKDBY4xh5fL5mOEh9u96RrLP/kKcv7IzN5HdQHB/02PPnI5BA2iWZeemsX02dNrJ4dsvofTKs7JjbCE1lOuaDfZUc1atGqFUTlAXEAFEusw18y5rcx4OEFvh1YkOo0nG0JkrKH7scReNLLY4d6XE8TenY6KfqIqIaBqZj2KitPH1u3x46VmpleexuTHFNa0mP256ysMppeGTweWZI2udHlz3BRACLByKOJKlhImXaf/jRwVQjLl9mqRZJu6FutdabQwbbw2VA9p46K8iUxrAeEdbhCZCKw8sWTSAOsXrCVrajbyfUQDw2mXu+H1QBgoRMZ66GSZ8/2tR/uLjijE/S167qCd70akMRgDeyqXAUOPRLwatHhNsCihWhHbmKA7GxGmMqGKLAkboZA7vfJfNPmMkFeIIfOgClW7cMFKKqTUdooHsW38RACUqXDtNdj8TExVLlwCaPX2/SmIheLRnmkbmmT+SYAaFZlA+/1iDzz3ZwGognCKzQaGUCF/d4/jDpzKqGYwWhdSeYLGYRriOI7eD+D2PG9rHBJNsnib6VIDTSel5TB0UN75HJElBw3HnDgqdyPLlnU2u+tIxbn2ozr6Kx/gwy7yqEAlUMuUz3+twzb+0+PTODj+u6fHnNhKiCDIfQeOwhOcfAlip+384JiJqTvE/VX3EAot17zNoqyUYezzcQwgEI9z2cJOb76+wvxZYUIqICKjXudwPKzBWEOq5ctfTGR/4tzbjDSWOupGQWEOWe4xAe+93FSh1ivOW9NVBXkuHgOHO/h+iAZl2KhHIPaSx4TNXDXPb5hI+KPVMEVX0dCmTQMvDVEd5/7kx91xeYGlJ6PjuurE15C4gkRU/8bICJMWh+QB2tkiVFMAfG+8yotOCKYTgwRjmFQ2fuKzEllUJv7O9TqWtzKksAvWOMloQ7rg45epzInyAtusCRyGOhFYngLGE6niP9oL2Bzg9XGdODVMPvq28bVXCgx+YxzOvtmh0ZoM0Aq1cuXiJZeuamDeNGY619PizvrsJrgfwpEs/u5hZDu97QRJ6a+WNwEjBcOnKhKOVDGNklqlbOVy02BAUDjcUa05EeDeJnDPBmgOgTAkgZmhB17zSvRSskoihkitR0vN8ulNyYSgVZA4RDD2FLtjZz8VAZUqJIoGQY0pn9JLctukP0GdNoBEvWjVCT0xjA3uOKeMNJXOeg3E2Q5AF5xztKT9nFM+VKbsA5dSw2Co2smhwasZWdFcJWW0WwJ7UtFV1Ilp1/hJJrGrwEkXQdPC7T7quCXdWTjq2kG4Q9auUZI7P1sCxtvKn7yiwdKEisSEEpbhiowBZc/LQgZNkppd/Rb3b3Yydgz1jeTCuTdMLmxYJt26MurtOhMEZs5wIpQQGYxiYMQdjKCW92fuuGMNQAplXbnhTzE3rLEebnmKskJbUrr8cYN/AWbsOMkeJiG83nwIhecsvErKcKIo42obrV0dcdpbhWHYyG9W2UsuUPJwIAO0FVr3TPUnyMO2xXWssKxtuuyhhsuGJrCUJLcyyjYF5K5TgvivyPq+q9lSAASDPph4E8sEtH4wkSZXQ9a9OgFs3WhYPCJ0AzVyZypQb3lrg7ncPkVpo+675gkK1o3z8woRbL0wQ6d7T871PbUpYOGg4XO1QHozBdUh+/oPdPeStrx0Xk1MSwaCqpjiycC/BP2KXbSC98D0hTNUxUUzmYOkg/MHGiKkczlsc85XrRrjriiGuWF/kC+8skxplKu+CuP3ilG0bYrZtSHjgXUWuOtsy0VRuXh9z+QrLkYYn88KwNODsTSE9/32GEPaRlnf0ymHfL6PuVnd5azO28LA/8iN35JMXWm3VkDhFgycW+OarHbZuGqVQtvhm6Dp+UXjy+Qa/saPJJzelbP0Zy2Szy1opBmOER19zrJtvGB0w/PdERmphrGxIP/Kwi1deYMnbt0hS/Mvp2kjm6Nd0QXp3Pyb65fZ/bs8rf/aemDhG4hRRT+g4KkFYs2YUo4oGRQWMKPvHW8xLIPMnToxpYR6MIYhhoh6oVRssH8pJbvyiSy++0QaXf9/Y+KJeVqUionMBnDb9KMF9D2OXtZ95IK/efWMcWjWkWMZYS7WRE2LD2cvLxIkBD6qKBE+tkhFy303/jQEMIiAiTB5rUJ9ssmzFCMX3/7lLNv2qxbsqkT1fRF6eWXrKabpex7sNBP8gJlri9r/gal/+PbLdjxhtZ1gD1QbUgUVnFuXMUSNqi2C75WfWzOk024R2E/E5rQw9eBSNyykrNl8dild/SszitREhHMaY94rIY6c2S+V1WnPT3YYVwedfMFF8OQBHX4GXduIrh4hKQ7BkA/VmRnnvPzlefBAq+8FlYCKCHcCNrUVX/JxoNBjJ6HLSt74DCgt7mpb/K1H8WyKyt18n93UPp5l0a95+FybeijFvBgZmrCHASqDbO+wchOYkGAtDS4DS9HITwFGgHlz+rEG+KnH88EwyTp86nAbkzGr/lOOsC7BaXUUx2UJcWDcTUa8KP0Cz/nR1767HR95ySeVElnmirzhX7/AnON6Pt8r43zbUe6Ci3qb963ZYfwov4zTd1Zm5Q5hx7v//GP8Dg9vTIhHaRdkAAAAASUVORK5CYII=) 3 2, auto !important;
  cursor: image-set(url(data:image/png;base64,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) 1x, url(data:image/png;base64,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) 2x) 3 2, auto !important;
}
body.fika-hw a, body.fika-hw a *, body.fika-hw button, body.fika-hw button *, body.fika-hw summary, body.fika-hw summary *,
body.fika-hw label, body.fika-hw select, body.fika-hw [role="button"], body.fika-hw [role="button"] *, body.fika-hw input[type="submit"],
body.fika-hw input[type="button"], body.fika-hw input[type="checkbox"], body.fika-hw input[type="radio"], body.fika-hw [onclick] {
  cursor: url(data:image/png;base64,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) 3 2, pointer !important;
  cursor: image-set(url(data:image/png;base64,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) 1x, url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFgAAABYCAYAAABxlTA0AAAa5ElEQVR42u2cebAlV33fP79zuu/29jeb1tE+2qYEEhKLsAAFMBIKSAhTiHKKmKXsAFFMgokDxAqyCVWOY2ywKwYbMGCMjY0jIBYOyKxGLDJIIhJoRhqNpNGMRvNm3rzt7n3O+eWPc/q+O29G0kh6I1Kp96vq6Tv39e3u8+3f+f7W06KqAqwHLgJeEgiXqw/PRqTKQHTwSTBqjFFgFrhBRD4KoKoZ4EVk+eBVkHR/AhgAEXGrcE6bzuU5xiLpgp8A3ny0P/LBY40t//s14H0i8qPy5p/ujSdQzZFASH87HTgB2ATkQAE8AswAW4EXAKclxXFJGe4HbgO+LyL707nKa4RjBXCW9j9JatqfmduTL7YPmhA8nV6LVmeRbr9Nq7tEq7vE5Rddw6apkwHUB6/W2F8EXqqqfwR8QERmn+qNl8AmUP0QCFuA5xHciwn+eaBbMFl22AlcEchy8wSXOaCq/wB8TERuXS2leCINfjZwO8Bd9/9Afuvj/4pWb4ncVui7HqoKKD54NkyewHUvvZ6rf+GtZDbDB++NGCsiALuA/yIin3qytDE8SFUdAV4BXEXwz0fD2djcrvhJADSUqh53kh6MlmM7hOOCF4wdPs8XgfeKyD2JNsJqU1wJcA24L4RwojFGf/rAP5sbPvFGekWbWt7Aq0eiRtJ3PVqdJc479RLefNV7uGjLiyNteOesHWjVPwLXi8i2x9OQpJ3p1OJUdQx4G/BriQYOYSYWH1UW9xqaM4LrCTaD0fXo6PH4/Q/Qv/dWXXp4h2SdA9SrlmxkEp08CTn5QrLTn4eZOiGCHXxAMVgrwALwLhH5RJpBrCbIUg5eVW8CrnHeFZnN8u277uR9f/YG2t0lapUGPkR8RAQjlnZ3CQVedvHr+NdXvJuNUydBCOpVg7XWphv/IPCHItJPGqLDgK4A+wrgw4kOSg0NHNhhuOuLwn1fFw7cB90FcH0QRYPS6wuF1tH2It12l3t3g1ewBsYbsGkSRhqWMHocdsuLqP7Cm8jOf3l6ZM6xrBQfBd5RavtqgSwlXwJnA/8AnOJc4bMst9t33cF//vgv0+osUquM4MMyJkYMCiy151g3vonrXvrrXH3Zm7Emw3nnM5uVU/EO4D0i8tUjaPBpwMXAy4BfTdPYYaxhdqfh238Ad38R2gfBViCvgbFgDEXX02n2Cc4jeDAZiAEU55RW13Fgoc9i2zMxknHKBkvu2jgy8nP/BfWrb8Se/lwIXkE8xmSE8CWMeX0ymroaIJcUYUQkqOoZwC3AaSXIO/bczXs/dh2L7YOM1MZxvjjkBNZkFK5Hqxtp4+2v+QDnnXpxMoLO22Vj9DngQ9G6h5fgwwuC6nkmy2vx4AKxeaTUH3wCbrkRWrNQnwCTJ7aN4+00e/TaBYKkEcihrqSAEUEE2l3PrpkOS23PmSePMTWWUyzNIXmd6qtuoHblu9N88QXG5hC+BObadNKnzcmy0sio6qkJ5DNLXt35yM94/yd/hd3772e8MYUYQwh+aECRNlrdRSp5jWsueyvXvfTfMVIbIwQfADHGymN5fQmdzDXnkJvfg73zL6A6HrV2aNYgQmcpgWvk8Y1m+scawRjYd7DHA4+22byxzgkbRymKAprz5Je8jsabP4nURsH7Amtz4E9E5O2qmj1dv1uOZMlV9ZQE8lk+eGeNzRZbc/zlLX/Azd/7NH3XY7Q+QVCfPIxEG8YSgmepPc8px53NG694N5df+JroQS3Thg+tee3f/yPjHrxd/PxesUWLpVBj+9f/nnNHH2TTGZvRvkOGtBIRuq0+nVYfI4f85XEHVgJdyYXFluNnDy5xwvoamzc16KuFxf1kWy5j5B03IWPrhjSZXxGRTz9dF04ey11S1ZMSyOf44AtrbAbI9l138MmbP8iPtn+LWqVONa8fws0lbXT7bfquywvOv4I3vfI/cfoJ5xP2P8TiLf+D4rYv4Pc/QHBKbmGuBQ/th3POr3Pi5vWYodmhaYYUPUdzoYuIoHq4tq4ciMjKcUGeCa2O56cPLLJ5U53j19UoyKC5H3v6Cxl9581IbTT6pMa2gWcBDySjHFYF4BUgHw/8L+A5K7SQr972V3z2q7/PngMPPAZtGIwIS+056o1pXn3+Fbzyh19m9JEH6YxUIa9hjaHVc+zY2+ZZ569j/cYa9NyK4BxCUBYPdtGgIBwCsD7GYEqAZej/JcgLzYJ7Hmpy7imjTIxkeMnRpf3kz76akX97EwTvieP8qohc8XS0WB7H8S8NXwP4DeA/AiPe+yAiGGPMQnOWv7zlD7n5+5+heDza8I6lXpPNtsLreo7ndNqIKgpse7jJ6adNsPnUMUI3REcgaZyWvNvs0WlG3i2/1ycYkCRQhwEuTWE1F/Ye6LJ7f4dnnTGBtYKaHF3YT+01v03t1b9VejMZcK2I3PRU+VieILoy5dRQ1XOA/wpcOxRYWEC27bqdT978QX68/VvUKiNU89oK2hAyY1gCJl3B7x2YZdzCAzMdpGK58FkbCEUYaJkOAex9YHGuRwi6DOwQyMP+w0owY9x9KNgiy5y87aElRIRzNo9SeEXEoN0mo7/xj2RbLvMpOvwp8Oyn6lE8btyeNFjS09smIq8FXgPck6I2cd75czZfxH972xd49xv+iKmx9cw3DyAahk6ukT408MtLTdZroFkEFjuOM08bH8z5oGkLig+KV+h0PH0XcMpgK5Kj6ob2h3zWwzcfls+vgPPKaSeMsNAqOLDQJzPJcBpD53O/DkXPpmBnK3CNiGgK/VcP4DKiSWGsSRr9ReAS4P1AK7OZDcGjKFc87w388b//Gr90+dtxlTptiRewQFuEC3o9XtTp0MkMsws9JidrjI1X8UXUzqDgVZeB9Eq76/DIAESX/LoYXghe0lZ+HjrOAcVjAO085JnhxPV1ds10CKqIBqQ2ht91B72v//Ewzb9zKLpcXYCHtTlptBWRlojcmKKwm4yxaFD13jE5uo63X/M73BhqnNdaomktPREqqlzXbCICLigLbc+JxzcgxLv2pdYGcCGC0SsCfacDAIMIagxqDViBTGJMbE38LKA2/j0Yczjgmh6Oxmv2nbJxuooq7JvrkVlBvUPqo/S+/hHC0oEyGr1UVZ+Vxm+OCcBDQPsVtHEtcKMxRkTVK7B084c47d47uKHd5x1zc1RC4GXtNuf2+3StodP1GCtMjlfxPqAa6cAFKBK4DqHnAl5ASzAzg+QGkxtMxWArFlsxabPY8RqSGQgBQjyvGlkGW2RZk5M2iwjHTdfYO9vFBY2+d1YjzO6i/52Pl4GQAX7pqWCWPSXXI5K9U9VcRArgZACT5ar9Dr1v/hlFvYKo8vJ2m/P7fSqqtI0hE1hsO0ZHK+S5pdf3SYPjwMNQaOeCRlCtQQwYI4gRRCR6GyKDSFmLQPXizch4HTezhJ9t4Q628PMdQrdAfaIhI1gjAwvZd8q6iQp7ZzvMLvTZOFXFeY9UG/S//xkqL7vemOoIhPBaVX1/mac+pgCXyXERKVKK8VXpjm3vzq9Q7NmGGZ2A4FkwhqkQImBArtDqeTZN11Gi5g62ElwREEFFsLnFZhFYYyWCnAAe+LpGCCi21aWyZQNhuo4ihMLjFrq4g23cgSZ+tkVY7OLbfTR5E6qQ5cL0eJV9cz02TFbjl5UG4dHtuLu+aioXXwvoFuAMEbl32LtadYo4wm+fA2wghABI94dfOMT3s0OGSZJW9r0y0shwPnKu13RMitIEECuYTMirhqxiqVQNecWSVwx51cR9buN3uSGvZdilNpnzZN6TeUdmoDLdoHbmBhrPP52RK7diphuEoITEzx4oPEyNV2j3PM2Ox5hl56+4/X8CuJSof8GTxe3pAFzieHE8kw2hNU//vu8h1WrKfh3ubBdeCUClmlF4BkYoiKDOI+M1zMYxjChZxQ5Azcp9bsiyuNlMyDLBWiGrGKTTx/YL8oolywy5FTINWOewGihu20nYu4DUsuhjJ7egCEq1aqnmlrmlPsaABo9UR3Dbv4lfeLQMKV98zLyIx5HzBiWxB+8kHNwNWZXDEgZDAIuJoLigcZAiaOGRqRHkhWdhT5kmM5CVgCZQrZXBZszy3hjBZgbjPDS72NxgRaNzYaEykhO27UF3zZKNVhArYCMFheRVKDA5VuHgUoEvdSOroIv78A/dHnHS8IJUOPDPBMDlbZSlHSl23Ym6AMYcloyJeAvOK0rk2AG4fQcnTiGXnklWy8jGq9h6TmaFLC+BZABm+VlkaDMmRoLz7eXjRMnqGWHHDOH+GfLRCjaLD0OsASMEhCDRLx5t5PSKQLcf0qwT8B6/41aTrPuZiYf1aN21pwzwEMlPl1+5R7atzH0fCnLyFjCCGoma03dw1ibMJafGwXuHHavG7RBQlw3ckSN8jQmn+Q6ECJCt5viHDuK2PUJWr0QqSdRirMQ42iSPRZVKxSAiNDsu5T0CZBn+odtLHs6A5z8Z7MxT9SDSvgpMDFR6dldK1ujh2pssdiBysPeK+oBecDJywcmYoDF2MIKtZNiJGgYwxmAMCdgnyLAbQ2j20HYfqee4vQsUd+/GVCzGEuklabDJTKQKEZT4sI0R6jXLYtuV40Rsjs7vgaJXjvLyZ5KDbdripVvzkR6OwL9acooIwSveGPSS06L29t0gIDNpb6caEVjzhDmpodEI2i0I3YJwsEX/jl2IsdHFk+jOldxd8neZDSpnWLVi6fb98hBsTlicwS/OmESMFwxVYo45wIMEVyj6ot0lBvnGIxxU3rQaITznVDh9I+IDxsrAKBkjMVE0XkdyC/okb8cI7sGD9G5/OCYdjCBEajGG6EuX/nRJOQIq8fBa1dLtB3xIU04s9Fpoc1aSWp+kquOJh+VYA+wg9OOJVNFwWClBhzVYFWMEH5Tijl3ID+9HDiwhKFLNMLmNWhsUaVSQejWO+kngK9bg9y2inT5ky7Np2SBGTS4jQ2ykFkQICpXMEFRxPiX3RVDfR7sLpc+5DjjuaNK9TytUHkRy3i9gHifNNIRwUDDWoEHpL3ap42HfPGaqgTluDNk4hkzWIbMx5zBRwy12EJs9ptt3ZOKSx8zKl1GgsQYxAQlK6DsoPKGWkWUG1ZjOrGQpXAwBip6kUpIFNgD3HjOAh56eYsxMjDNylbweb4bD62G6bIcwRui6wFQtRwVMr4/snkVm5pGxGrJuFE6YwE6P4B6ee2rEdYQCnUh6yoVH+5FCzVgNc0Kd/KQpZK5FcfsuxAjea3T7yjuPBYSQbM7EMc9FJHoJwA7gFRijMjLJgCYew1UzIuRZzKgNKhDWIJVk1dv92A/x6DxiLZLZJ6e9MjRzNQKq3g8024xWyEfrmLE62ViDUK+ilQzJLb0v/2TgrfiBwQDEIJX68FVqx5QiVsg9A8THN8Ww6DEUqfyumhlaHRdxMysONoLYCKrpFU88hJWA+mVAJbPISAU7UcdOjcBEHeo5Pot5EO0roe8hKG7XQdwj8+QVu7KXAbE5VEePutS2WgCXkNxVQmOP3xIN7xF8Vh2ii0bNsr/jKFygkjqsVg4qAmSwmcGlet3RAVrFTtSw0yOYiTpmpAqpMTN4JTiP9hzBK+olpkyqBrfzQIyCsui5DC4TPFQamLENw3fYfhwdWjWAw6D3zBf7sfkGu/GMmGXVw7WsLBeqKrWKxTcL2h1HrZ4tV5CPZK9yg3Nhue5+JEAn69FvLgGtJNc8pKx6EWfLYEu3qArYGJy4XbPY3A5adWNGTcA7ZOIEZGzjsNe1cMw1uIzHRWRJvfsxcEW2+YJg6nXLikYUWbHPjFAxwtx8j3XramjQWH1IbqUiqfqrZAKFD4SgSG6R0Wqa8g3MZB3TOAKgfTf0ZJOrlR5uLHxqBDooVDP8/fvRpS6mlhOK+PByWwLcx05vRqqNqDwhOIx59JnQ4GVDZ+x3gCuyE89Vu/EM3N5tSKWBaFguqQ9psQBj9YyD812cG0uaEwFQr4hPGpsZGKmSrxtFx+rYowT08LaedG4d2D1CiMEFPlDsmEn9ddBP3kOWsm04hznloqERmxngGQO4vMB3U8nIZKddTLHrbqRqkkfBgNPKLaCM1jNmZ7sszHVZN1UlBCE0csxoBZ2ow0QdGatBvUKW2fTDowR0OWcCukwH8edKCHGvmcXPLBH2LcbKCdDve3JryK2Jv7eW7JyXDLtoO0WkneKAYw5wycM/wbv92GxD9bmv1e53PjXw04ThrmsQFwgdT5ZbGpkw74XpE6fxk3XMdJ0wUoVKbLtCFVGF4ugAXen7SmYJPhBciPmPwGALiX+LnQeg8Egtxwi0e55abrDWUPS72PWnk51x6XDE//0VxZpjB/AQDy+q97cDr6icc1mwG061YWEvZJUIUomNV/LJBtVTppHpEbZsGsOM1SiqGdaAlxBppB8QozGvIUPFzaOdVhKTPsUj88j0CIzUCHlGcHH1QEgUpK0e/qFo3KwqokK749g4WYkRUbdFftkrkfoYeBfznHFV1VHRw2r5wYmHzXeAV9jGhFYvuZbW338oJmx8Sv2lxJUKjF68Gdk0TjHXIWgg9B0i4K0gNtbjUMGgyWzLoa6cPA5fBUVzS+++GXrb96HVHK1X0MkGTI+hEw1CNUdqFdzde2Cxg6nmWFF6hadwgYmRnOAdUhulctlbSXbGAA8D33syTSirUTIqL/R3KZ609Ze8Bak3IIFbummSGcJci7lP/4D+3Y9g6ll0uyRWFgZNJ2k6hzSdNcT1GCE1nUQPgENcr6CgXgnW4PYv0XtgllDN8S7QP9Ci89O9NL95L0tfuZvOt+6l/9NHcPfuw0h8kLmNXZfV3NBoVPHNeSoXXYs9aSsEXzrif5P4NzvaPrWnDfBQt8u9hLgULD/pPF97ztWEdhNMtkwRqthKhgnK4pf/D707d5ONVgeJIK/gfSzfOK+xZpcMks8M4eFZwkOzBIFghRCi++ZV0z4m8jt378X1Pa4fKPoBF8CLiSWqZo9ixwzdb9yDziySJeOGKgfme2ycqiIaC57VK3+zzNEZoEdcKPOkWqhWQ4NjgCuiGPPn5QQevfp9SLUeI6EhD8KgWBuNSOtr99D+px3Yep76fpc7fJwH58C5gDMWd/8B3LZHcfftw9++Czfbwtss9qS5uPDFW0t35yzdRxZwKhQ9T9H3uJ7H9zxahJgyrmRk1RwrggGqmTC32AdVNkyPUMwfpH7Nb2NPPC/2ChtjgM+KyI7UOnbUAMtqoDuUeJ5Iabz1gC5+5p2m9ZUPY8YnwbtBg5/qcgNf0e5T2XoCIy87N/qqhY/5WsCIYqoZZucMZucMUrUx3ehTI8v6cexZGzGNSvQUuo7WN7bhO0X0dZP3oH55ipS8bllubzMCd92/wMnHj7MxW0AufB2jb/s8IXg1YhRj2sQuy108yW73VdHgxEdWROaBj5Xu7uhr30923Olorx0zUkPumgGsKnmjQv/uvSx98U6k8JhqFrlUNSZl7nmU4md7KRSKXqDoefo+anl4+CDNr2+nec8+nFhadz1C/2AnanPPE4oQtdZFMjfooMaVKvdUMuH+PS1GRutssAtw1uWMvOXPU+rXuKS914vIQ2mmPqkOS1klihjW4ilge/B+nbGW3h1fkbnf+5fIyDgEf0hz9aAfWISiU2DWjzJy1VYqG0YJQXF37Ubu3YfUMpZXbMnyZyOoC/SWevixOn6xAy7EHMNQM7AkrS011wjkRsgzYefeLkvNLls3BqoXvYrGW/8CaUwML4b5qIi87Zh0uD8FkMu1HdcDHylXUi797Q00//Z3MFNT4IrHBNn3HEU1p3blVti/yPhD+ynKutxwgCErcukiFM1+bCR0iWdT1qlctm8SsFYEay3OBR7cvUBvyXP+2SOMXfkuKq++ATFWQ/DexBL9F0TkdU9nHfNqA1yOR4Fbgefjncdmdv4j19H57uePCLIOgaw+0OwHHpzpcPLmcU7bPEbfhRh5rXCEdeiTGEGD4rqOoudQF6NAkxYlRuNqEe/IfYsDc545X+PcX7yaxlW/idl8YUjeQRkb/Cnw9jKCe6oLElcV4ARyuXhmK3AbIVRAjXon8x95Pd0ffimB7NCyIKPLuZuQ6mbtnmfnvg6TUzXOPnOCSsXSLwJBl2FdGTGbVDEBRV3AFx71MasjYjHdeczoOuyWy8nPeD7VC64IbDwrDCk5KZHzPhH55GosDl91gFdQxVuAjxNCgZFcXcHCR99E5zufw4yPgRg0uXHDOeGQkvYuKA/OdOgrbD5xlOM2NKhUTGxN1bhABtVDmKNc9GKMxMImAkUH6TWR0y7FvObDyPFby76G4VcbPAD8NfBhEdmXfHtdtaW0xwDkLK3t+O/Auwi+QEyOCM2bPkDz724EAtIYT93o4dACqSZ7JsLBZsG++T5Yw/S6BhM1T60C4xMjsZsp5SyWM3YBfIG4buyx2HAWcumvwXPfjGQVj3dgM0vwOzH2s4nObhWR1rCCrIqHdQwBHry9RFX/BPg3hOBALcZK/2ffYunz76W//fsxkV4dAZNShKqDtXaKYG3U2sXFJgf3F/SnN2FHJnn2qY6qWyAU3Zh1ExCxSFZBxjbB8Vth6zXKeVcFUxvTpLHlmD+f3K/9wzOPVX4pxzED+Agg/z7wH6JFSyspg6f97U/R+cafUjx4O1o4xAI2A5PKN8ETnIcA+brjGXnRG6lf9S5CbRLpHISFPTC/G+m342hqE8r4cYHpU9U0pmQFDQB8C/hdEfnf6R7zZNxW/W0nxxzgIZAlGb5fJb50oxa88yam/wyq9LffSu+uW3A7/xk/twftLIG1SGMSe9wW8rMv09rFV4ds6ngNYETVaJzGg6KJGXo71UC8a2KzuxMN/I2I3FYaY1bpnRA/V4CPYPguBH4XeHkCQLGZH0pXSHAO+m3FWDW1EV1h5R9fvGsCd2PNrWD+CfixiOw+0qx6Jsb9jAG80nio6uuB64EXHlVO1BWzxpg7Mfa7wLdTZffMFDkCLAK7U0lnzxFmUcmv4Zkc8zMK8JGmpqo+D7gSuJT4rrPJEIKYCOBOVL+L1e9CdqeIzD4JWrJDQULg5yTy87rwkSx2WgtcttC00hq8lQ/HDNXHzBGKGj9XQP+fAXgF0HKk6bsC0HCsDdL/lwA/RkaOpxuirsmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMmarMnPTf4vQsao0eUeCxQAAAAASUVORK5CYII=) 2x) 3 2, pointer !important;
}
body.fika-hw input[type="text"], body.fika-hw input[type="email"], body.fika-hw input[type="search"], body.fika-hw input[type="tel"],
body.fika-hw input[type="number"], body.fika-hw input[type="password"], body.fika-hw input:not([type]), body.fika-hw textarea,
body.fika-hw [contenteditable="true"] { cursor: text !important; }
/* the fish's bite on click is left out while the pumpkin is the cursor */
body.fika-hw .fika-bite { display: none !important; }

/* ---------- "Can't decide?" / "Rather pick your own?" box: black and pumpkin orange ---------- */
body.fika-hw .fs-cross { background: #0b0714 !important; border: 2px solid var(--hw-orange) !important; box-shadow: 0 0 26px rgba(255, 123, 28, .25) !important; }
body.fika-hw .fs-cross .t { color: var(--hw-orange) !important; text-shadow: 0 0 14px rgba(255, 123, 28, .35); }
body.fika-hw .fs-cross .s { color: var(--hw-bone) !important; }
body.fika-hw .fs-cross a.go { background: var(--hw-orange) !important; color: #0b0714 !important; border-color: var(--hw-orange) !important; box-shadow: 0 0 18px rgba(255, 123, 28, .45); }
body.fika-hw .fs-cross a.go:hover { background: var(--hw-orange-d) !important; }

/* ---------- About: How it works, the buttons ---------- */
body.fika-hw .fika-about .fa-step, body.fika-hw .fa-step { background: var(--hw-deep) !important; border: 1px solid var(--hw-line); box-shadow: 0 8px 26px rgba(0, 0, 0, .4) !important; }
body.fika-hw .fika-about .fa-step::before { background: var(--hw-orange) !important; color: #0b0714 !important; }
body.fika-hw .fika-about .fa-step h3, body.fika-hw .fa-step h3 { color: var(--hw-orange) !important; }
body.fika-hw .fika-about .fa-step p, body.fika-hw .fa-step p { color: var(--hw-bone) !important; }
body.fika-hw .fika-about .fa-step a, body.fika-hw .fa-step a { color: var(--hw-orange) !important; }
body.fika-hw .fa-mys-go a, body.fika-hw .fa-btns a.solid { background: var(--hw-orange) !important; color: #0b0714 !important; border-color: var(--hw-orange) !important; }
body.fika-hw .fa-mys-go a:hover, body.fika-hw .fa-btns a.solid:hover { background: var(--hw-orange-d) !important; }
body.fika-hw .fa-btns a.line { background: transparent !important; color: var(--hw-orange) !important; border-color: var(--hw-orange) !important; }
body.fika-hw .fa-btns a.line:hover { background: var(--hw-plum) !important; }

/* ---------- footer: the copyright line ---------- */
body.fika-hw .fika-footer .fika-foot-bottom, body.fika-hw .fika-footer .fika-foot-bottom * { color: var(--hw-muted) !important; border-color: var(--hw-line) !important; }

/* ---------- phones: lighter decorations so the menu and the bag icon stay clear ---------- */
@media (max-width: 760px) {
  .hw-web { display: none; } /* under the fixed header on a small screen they read as patches */
  .hw-spider { right: auto; left: 58%; height: 70px; animation-name: hwDanglePhone; }
  .hw-spider i { width: 16px; height: 13px; left: -7px; bottom: -11px; }
  .hw-spider i::before, .hw-spider i::after { width: 26px; height: 6px; left: -5px; top: 4px; }
  .hw-spider i::after { top: 7px; }
  .hw-bat:nth-of-type(n+3) { display: none; }
  .hw-bat { width: 44px; height: 22px; }
}
@keyframes hwDanglePhone { 0%, 100% { height: 150px; transform: rotate(2deg); } 50% { height: 200px; transform: rotate(-2deg); } }

/* ---------- the Fika inbox card at checkout (fika-checkout-texts.php): the checkout's Halloween purple ---------- */
body.fika-hw .fika-texts .ft-who b, body.fika-hw .fika-texts .ft-foot b, body.fika-hw .fika-texts .ft-next { color: #5b2fa3 !important; }
FIKA_HW_CSS
	. "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 99 );

add_action( 'wp_footer', function () {
	if ( ! fika_hw_on() ) {
		return;
	}
	echo "<script id=\"fika-hw-js\">\n" . <<<'FIKA_HW_JS'
/* Fika Halloween week: decorations only (bats, cobwebs, a spider, skulls). Needs body.fika-hw. */
(function () {
	var b = document.body;
	if (!b || !b.classList.contains('fika-hw')) { return; }

	// a cobweb drawn from the top corner
	function web() {
		var s = '<svg class="hw-web" viewBox="0 0 200 200" aria-hidden="true"><g fill="none" stroke="#f4ecdc" stroke-width="1.1">';
		var i, r, a, pts;
		for (i = 0; i <= 6; i++) {
			a = i * Math.PI / 12;
			s += '<line x1="0" y1="0" x2="' + (210 * Math.cos(a)).toFixed(1) + '" y2="' + (210 * Math.sin(a)).toFixed(1) + '"/>';
		}
		for (r = 30; r <= 190; r += 32) {
			pts = '';
			for (i = 0; i <= 6; i++) {
				a = i * Math.PI / 12;
				var x = (r * Math.cos(a)).toFixed(1), y = (r * Math.sin(a)).toFixed(1);
				if (i === 0) { pts += 'M' + x + ' ' + y; } else {
					var m = (i - .5) * Math.PI / 12, rr = r * .86;
					pts += ' Q' + (rr * Math.cos(m)).toFixed(1) + ' ' + (rr * Math.sin(m)).toFixed(1) + ' ' + x + ' ' + y;
				}
			}
			s += '<path d="' + pts + '"/>';
		}
		return s + '</g></svg>';
	}

	// bats, webs and a spider in each hero (under the words, over the photo)
	var heroes = document.querySelectorAll('.fika-hero');
	for (var h = 0; h < heroes.length; h++) {
		if (heroes[h].querySelector('.hw-fx')) { continue; }
		var fx = document.createElement('div');
		fx.className = 'hw-fx';
		fx.setAttribute('aria-hidden', 'true');
		var bats = [[18, 14, 0, 1], [30, 19, 5, .7], [12, 23, 9, .55], [24, 17, 13, .85]];
		var html = web().replace('hw-web', 'hw-web l') + web().replace('hw-web', 'hw-web r') + '<span class="hw-spider"><i></i></span>';
		for (var k = 0; k < bats.length; k++) {
			html += '<i class="hw-bat" style="top:' + bats[k][0] + '%;--t:' + bats[k][1] + 's;--d:-' + bats[k][2] + 's;--s:' + bats[k][3] + '"></i>';
		}
		fx.innerHTML = html;
		// under the googly eyes, so they still shine
		var eyes = heroes[h].querySelector('.fika-eyes');
		if (eyes) { eyes.parentNode.insertBefore(fx, eyes); } else { heroes[h].appendChild(fx); }
	}

	// the banner's dots become little skulls
	function skulls() {
		var bn = document.querySelector('.fika-banner');
		if (!bn || bn.getAttribute('data-hw')) { return; }
		var walk = document.createTreeWalker(bn, NodeFilter.SHOW_TEXT, null), n, list = [];
		while ((n = walk.nextNode())) { if (n.nodeValue.indexOf('·') !== -1) { list.push(n); } }
		list.forEach(function (t) {
			var parts = t.nodeValue.split('·'), f = document.createDocumentFragment();
			parts.forEach(function (p, i) {
				if (i) { var s = document.createElement('i'); s.className = 'hw-sk'; s.setAttribute('aria-hidden', 'true'); f.appendChild(s); }
				if (i) { p = p.replace(/^\s+/, ''); }
				if (i < parts.length - 1) { p = p.replace(/\s+$/, ''); }
				f.appendChild(document.createTextNode(p));
			});
			t.parentNode.replaceChild(f, t);
		});
		bn.setAttribute('data-hw', '1');
	}
	skulls();
	setTimeout(skulls, 1500); // the banner may be filled in after load

	// every fish (rewards lane, sign-up card, Fika Club card and pop-up) becomes a little spider, also fish added later
	var LEGS_L = 'M40 48Q22 36 12 44M38 56Q18 52 8 60M38 64Q18 70 10 80M42 72Q28 84 22 94';
	var LEGS_R = 'M60 48Q78 36 88 44M62 56Q82 52 92 60M62 64Q82 70 90 80M58 72Q72 84 78 94';
	var SPIDER = '<svg viewBox="0 0 100 100" class="hw-spd" aria-hidden="true"><g transform="rotate(-90 50 54)">' +
		'<g fill="none" stroke="#1a1026" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">' +
		'<path class="hw-leg-a" d="' + LEGS_L + '"/><path class="hw-leg-b" d="' + LEGS_R + '"/></g>' +
		'<ellipse cx="50" cy="62" rx="17" ry="19" fill="#2a1840" stroke="#0d0716" stroke-width="3"/>' +
		'<path d="M44 56h12l-6 6 6 6H44l6-6z" fill="#ff7b1c"/>' +
		'<circle cx="50" cy="38" r="12" fill="#2a1840" stroke="#0d0716" stroke-width="3"/>' +
		'<circle cx="45" cy="36" r="4.4" fill="#fff"/><circle cx="55" cy="36" r="4.4" fill="#fff"/>' +
		'<circle cx="45" cy="34.6" r="2.1" fill="#1b2a4a"/><circle cx="55" cy="34.6" r="2.1" fill="#1b2a4a"/></g></svg>';
	function spiders() {
		var svgs = document.querySelectorAll('svg:not(.hw-spd)');
		for (var i = 0; i < svgs.length; i++) {
			if (svgs[i].innerHTML.indexOf('M62 38 L90 22') === -1) { continue; } // the fish's tail
			var t = document.createElement('div');
			t.innerHTML = SPIDER;
			svgs[i].parentNode.replaceChild(t.firstChild, svgs[i]);
		}
	}
	spiders();
	var st = null;

	// the Ready Mix photos on plain white (media 1178-1180), like the pick-and-mix photos
	var MIX = { 'Sweet_Sour-Mix': 'fika-hw-sweet-sour-mix', 'Sweet-Mix': 'fika-hw-sweet-mix', 'Sour-Mix': 'fika-hw-sour-mix' };
	function mixes() {
		var imgs = document.querySelectorAll('img:not([data-hw])');
		for (var i = 0; i < imgs.length; i++) {
			var src = imgs[i].getAttribute('src') || '';
			for (var k in MIX) {
				if (src.indexOf('/' + k + '.') !== -1 || src.indexOf('/' + k + '-') !== -1) {
					imgs[i].setAttribute('data-hw', '1');
					imgs[i].removeAttribute('srcset');
					imgs[i].src = src.replace(/\/wp-content\/uploads\/.*$/, '/wp-content/uploads/2026/10/' + MIX[k] + '.jpg');
					break;
				}
			}
		}
	}
	mixes();
	new MutationObserver(function () { clearTimeout(st); st = setTimeout(function () { spiders(); mixes(); }, 120); }).observe(document.body, { childList: true, subtree: true });

	// a row of skulls in the footer
	var fb = document.querySelector('.fika-footer .fika-foot-body');
	if (fb && !fb.querySelector('.hw-skulls')) {
		var row = document.createElement('div');
		row.className = 'hw-skulls';
		row.setAttribute('aria-hidden', 'true');
		row.innerHTML = new Array(8).join('<i></i>');
		fb.insertBefore(row, fb.firstChild);
	}
})();
FIKA_HW_JS
	. "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 99 );
