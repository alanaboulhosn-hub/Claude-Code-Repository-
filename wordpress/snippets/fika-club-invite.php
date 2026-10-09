<?php
/**
 * Fika: Fika Club invite popup when a guest lands on the site.
 * - Who: visitors who are not signed in, on a browser that has never been signed in to an account. Any page view while
 *   signed in (log in, sign up, joining Fika Club at checkout) marks the browser for good (localStorage fika_member),
 *   so the popup never shows there again, also after signing out. "Already a member? Log in" marks it too and opens
 *   the log-in form.
 * - When: on the 1st visit, then every 3rd visit (1, 4, 7, ...), counted per browser (localStorage fika_visits; a new
 *   visit starts after 30 minutes away), about a second after the visitor first adds something to the bag during that
 *   visit (so it never covers the shop before they have looked). Our shop pages only (home, Mix your own, Ready Mix,
 *   About), never the checkout or My account; it waits while the bag drawer or another popup is open.
 * - Counts per day (shown, join, login, close): GET /wp-json/fika/v1/invite-stats (shop managers).
 * - Switched off on 2026-10-09 by the owner (too many Fika Club asks in one visit): FIKA_INVITE_LIVE false. Set it to
 *   true to switch it back on. Preview any time (also when signed in): add ?fika_invite=preview to a page;
 *   ?fika_invite=test runs the real rules (visit count, member mark) before it is live.
 * - When it opens, the small sign-up cloud (fika-signup-nudge.php) closes and stays away for the rest of the visit.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-club-invite.php
 */

if ( ! defined( 'FIKA_INVITE_LIVE' ) ) {
	define( 'FIKA_INVITE_LIVE', false );
}

// daily counts: POST { e: shown | join | login | close } from the popup; GET for shop managers
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/invite-stats', array(
		array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( $req ) {
			$e = sanitize_key( (string) $req->get_param( 'e' ) );
			if ( in_array( $e, array( 'shown', 'join', 'login', 'close' ), true ) ) {
				$st  = get_option( 'fika_invite_stats', array() );
				$st  = is_array( $st ) ? $st : array();
				$day = wp_date( 'Y-m-d' );
				$st[ $day ][ $e ] = ( isset( $st[ $day ][ $e ] ) ? (int) $st[ $day ][ $e ] : 0 ) + 1;
				krsort( $st );
				update_option( 'fika_invite_stats', array_slice( $st, 0, 60, true ), false );
			}
			return new WP_REST_Response( null, 204 );
		} ),
		array( 'methods' => 'GET', 'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); }, 'callback' => function () { return get_option( 'fika_invite_stats', array() ); } ),
	) );
} );

add_action( 'wp_footer', function () {
	$ours = is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404();
	if ( ! $ours ) {
		return;
	}
	$mode    = isset( $_GET['fika_invite'] ) ? sanitize_key( wp_unslash( $_GET['fika_invite'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$preview = 'preview' === $mode; // always shows
	$test    = 'test' === $mode;    // the real rules (visits, member mark) before going live
	if ( is_user_logged_in() && ! $preview ) {
		// this browser has an account: never invite it again
		echo "<script>try { localStorage.setItem('fika_member', '1'); } catch (e) {}</script>\n";
		return;
	}
	if ( ! FIKA_INVITE_LIVE && ! $preview && ! $test ) {
		return;
	}
	$acct = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	$cfg  = array(
		'preview' => $preview,
		'join'    => $acct . '#register',
		'login'   => $acct . '#login',
		'stat'    => ( $preview || $test ) ? '' : rest_url( 'fika/v1/invite-stats' ),
	);
	echo '<script>window.FIKA_INVITE = ' . wp_json_encode( $cfg ) . ';</script>';
	echo <<<'FIKA_INVITE'
<style>
.fki-veil { position: fixed; inset: 0; z-index: 200000; display: flex; align-items: center; justify-content: center; padding: 16px;
  background: rgba(27, 42, 74, .35); opacity: 0; pointer-events: none; transition: opacity .25s; }
.fki-veil.on { opacity: 1; pointer-events: auto; }
.fki-card { position: relative; width: 100%; max-width: 380px; background: #fff; border-radius: 22px; box-shadow: 0 20px 50px rgba(0, 74, 173, .2);
  padding: 30px 26px 18px; text-align: center; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a;
  transform: translateY(12px); transition: transform .3s ease; }
.fki-card, .fki-card * { box-sizing: border-box; }
.fki-veil.on .fki-card { transform: none; }
.fki-card:focus { outline: none; }
.fki-x { position: absolute; top: 10px; right: 10px; width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f3f5fa; color: #4a587a; font-size: 20px; line-height: 1; cursor: pointer; }
.fki-x:hover { background: #e6eaf3; }
.fki-toons { display: flex; justify-content: center; align-items: flex-end; gap: 8px; height: 52px; margin: 0 0 8px; }
.fki-toons i { display: block; width: 34px; height: 34px; animation: fkiBob 1.6s ease-in-out infinite alternate; }
.fki-toons i:nth-child(2) { width: 52px; height: 52px; animation-name: fkiSwim; animation-duration: .9s; }
.fki-toons i:nth-child(3) { animation-delay: .5s; }
.fki-toons svg { width: 100%; height: 100%; display: block; overflow: visible; filter: drop-shadow(0 2px 2px rgba(80, 20, 50, .18)); }
@keyframes fkiBob { from { transform: translateY(0) rotate(-8deg); } to { transform: translateY(-5px) rotate(8deg); } }
@keyframes fkiSwim { from { transform: rotate(-5deg) translateX(-2px); } to { transform: rotate(5deg) translateX(2px); } }
@media (prefers-reduced-motion: reduce) { .fki-toons i { animation: none; } }
.fki-h { font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 32px; line-height: 1.1; color: #004aad; margin: 0 0 8px; }
.fki-p { font-size: 16px; line-height: 1.5; color: #4a587a; margin: 0 0 20px; }
.fki-go { display: block; width: 100%; padding: 14px 18px; border-radius: 999px; background: #004aad; color: #fff; text-decoration: none;
  font: 700 15px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .03em; text-transform: uppercase; }
.fki-go:hover, .fki-go:focus-visible { background: #003a8a; color: #fff; outline: none; }
.fki-member { display: inline-block; margin-top: 10px; padding: 6px; color: #6c7b9c; font: 500 14.5px 'Outfit', Arial, sans-serif; text-decoration: none; }
.fki-member u { color: #004aad; font-weight: 600; }
@media (max-width: 420px) { .fki-card { padding: 28px 20px 16px; } .fki-h { font-size: 28px; } }
</style>
<script>
(function () {
  var C = window.FIKA_INVITE || {};
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function ss(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }
  if (!C.preview) {
    if (ls('fika_member')) return;
    // count visits: a new visit after 30 minutes away; the invite is due on visits 1, 4, 7, ...
    var now = Date.now(), v = null;
    try { v = JSON.parse(ls('fika_visits') || 'null'); } catch (e) {}
    v = v && typeof v.n === 'number' ? v : { n: 0, t: 0 };
    var fresh = now - v.t > 30 * 60 * 1000;
    if (fresh) { v.n += 1; ss('fika_invite_due', (v.n - 1) % 3 === 0 ? '1' : '0'); }
    v.t = now; ls('fika_visits', JSON.stringify(v));
    if (ss('fika_invite_due') !== '1') return;
  }
  function stat(e) { if (!C.stat) return; try { navigator.sendBeacon(C.stat, new Blob([JSON.stringify({ e: e })], { type: 'application/json' })); } catch (x) {} }

  var FISH = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/><path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="#ff5a2f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/><ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/><circle cx="24" cy="46" r="5.2" fill="#fff"/><circle cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/></svg>';
  function toon(s) { return window.FIKA_CARTOON ? window.FIKA_CARTOON(s) : ''; }
  var veil, card;

  function build() {
    veil = document.createElement('div'); veil.className = 'fki-veil';
    veil.innerHTML = '<div class="fki-card" role="dialog" aria-modal="true" aria-labelledby="fkiH" tabindex="-1">' +
      '<button type="button" class="fki-x" aria-label="Close">×</button>' +
      '<div class="fki-toons" aria-hidden="true"><i>' + toon('bubs-bubblegum-skull') + '</i><i>' + FISH + '</i><i>' + toon('sour-strawberries') + '</i></div>' +
      '<h2 class="fki-h" id="fkiH">Join the Fika Club</h2>' +
      '<p class="fki-p">Amazing deals and free sweets as you order. Joining is free, the sweets are sweeter!</p>' +
      '<a class="fki-go" href="' + C.join + '">Join the Fika Club</a>' +
      '<a class="fki-member" href="' + C.login + '">Already a member? <u>Log in</u></a>' +
      '</div>';
    document.body.appendChild(veil);
    card = veil.querySelector('.fki-card');
    veil.addEventListener('click', function (e) { if (e.target === veil || e.target.closest('.fki-x')) { stat('close'); close(); } });
    veil.querySelector('.fki-member').addEventListener('click', function () { ls('fika_member', '1'); stat('login'); });
    veil.querySelector('.fki-go').addEventListener('click', function () { stat('join'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && veil) { stat('close'); close(); } });
  }
  // wait while the bag drawer or another popup is open (up to a minute), then show
  function open(tries) {
    tries = tries || 0;
    if (document.querySelector('.fkoc-veil, .fkx-veil.on, .fkc-veil, .fki-veil, .mx-drawer.on, .mx-drawer.open, body.mx-open')) { if (tries < 60) setTimeout(function () { open(tries + 1); }, 1000); return; }
    // the sign-up cloud (fika-signup-nudge.php) steps aside and stays away for the rest of this visit
    ss('fika_invite_shown', '1');
    var cl = document.querySelector('.fk-cloud'); if (cl) { cl.classList.remove('on'); setTimeout(function () { cl.remove(); }, 400); }
    var ac = document.querySelector('.fika-acct.fa-wave'); if (ac) ac.classList.remove('fa-wave');
    build(); ss('fika_invite_due', '0'); stat('shown');
    requestAnimationFrame(function () { veil.classList.add('on'); card.focus({ preventScroll: true }); });
  }
  function close() {
    veil.classList.remove('on'); var v = veil; veil = null; setTimeout(function () { v.remove(); }, 320);
  }
  if (C.preview) { if (document.readyState === 'complete') setTimeout(open, 1200); else window.addEventListener('load', function () { setTimeout(open, 1200); }); return; }
  // the first time something goes into the bag on this visit (after the candy has flown into the bag icon)
  function grams() { try { var b = JSON.parse(ls('fika_bag_v1') || '{}').bag || {}; return Object.keys(b).reduce(function (a, k) { return a + (+b[k] || 0); }, 0); } catch (e) { return 0; } }
  var start = grams(), fired = false;
  function onBag() {
    if (fired) return;
    var g = grams();
    if (g > start) { fired = true; setTimeout(open, 1100); } else start = Math.min(start, g);
  }
  window.addEventListener('fikabag', function () { setTimeout(onBag, 0); });
  setInterval(onBag, 800);
})();
</script>
FIKA_INVITE;
}, 30 );
