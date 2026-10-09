<?php
/**
 * Fika: Fika Club invite popup when a guest lands on the site.
 * - Who: visitors who are not signed in, on a browser that has never been signed in to an account. Any page view while
 *   signed in (log in, sign up, joining Fika Club at checkout) marks the browser for good (localStorage fika_member),
 *   so the popup never shows there again, also after signing out. "Already a member? Log in" marks it too and opens
 *   the log-in form.
 * - When: the 1st visit, then every 3rd visit (1, 4, 7, ...), counted per browser (localStorage fika_visits). A new
 *   visit starts after 30 minutes away. Shown on the first page of the visit (home, Mix your own, Ready Mix, About),
 *   never on the checkout or My account, and not when another popup opens the page (order confirmed, Fika Club welcome,
 *   a bag link).
 * - Not live until FIKA_INVITE_LIVE is true. Preview any time (also when signed in): add ?fika_invite=preview to a page;
 *   ?fika_invite=test runs the real rules (visit count, member mark) before it is live.
 * - On the visit it shows, the small sign-up cloud (fika-signup-nudge.php) stays away.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-club-invite.php
 */

if ( ! defined( 'FIKA_INVITE_LIVE' ) ) {
	define( 'FIKA_INVITE_LIVE', false );
}

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
	);
	echo '<script>window.FIKA_INVITE = ' . wp_json_encode( $cfg ) . ';</script>';
	echo <<<'FIKA_INVITE'
<style>
.fki-veil { position: fixed; inset: 0; z-index: 200000; display: flex; align-items: center; justify-content: center; padding: 16px;
  background: rgba(253, 234, 242, .74); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); opacity: 0; pointer-events: none; transition: opacity .3s; }
.fki-veil.on { opacity: 1; pointer-events: auto; }
.fki-card { position: relative; width: 100%; max-width: 460px; max-height: calc(100vh - 32px); overflow: auto; background: #fff; border-radius: 28px;
  box-shadow: 0 26px 64px rgba(0, 74, 173, .24); padding: 30px 26px 20px; text-align: center; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a;
  transform: translateY(22px) scale(.94); transition: transform .5s cubic-bezier(.3, 1.5, .5, 1); }
.fki-veil.on .fki-card { transform: none; }
.fki-card:focus { outline: none; }
.fki-card, .fki-card * { box-sizing: border-box; }
.fki-x { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: #fdeaf2; color: #004aad; font-size: 22px; line-height: 1; cursor: pointer; z-index: 2; }
.fki-x:hover { background: #fbd6e6; }
.fki-toons { position: absolute; inset: 0; pointer-events: none; overflow: hidden; border-radius: 28px; }
.fki-toons i { position: absolute; width: 46px; height: 46px; opacity: .95; animation: fkiBob 1.8s ease-in-out infinite alternate; }
.fki-toons svg { width: 100%; height: 100%; display: block; overflow: visible; filter: drop-shadow(0 3px 3px rgba(80, 20, 50, .2)); }
.fki-toons i:nth-child(1) { left: 14px; top: 16px; transform: rotate(-12deg); }
.fki-toons i:nth-child(2) { display: none; }
.fki-toons i:nth-child(3) { right: 62px; top: 22px; width: 38px; height: 38px; animation-delay: .8s; }
@keyframes fkiBob { from { translate: 0 0; rotate: -8deg; } to { translate: 0 -7px; rotate: 8deg; } }
.fki-kick { position: relative; display: inline-block; margin: 6px 0 10px; padding: 6px 14px; border-radius: 999px; background: #004aad; color: #fff;
  font: 400 17px/1 'Bebas Neue', Impact, sans-serif; letter-spacing: .1em; }
.fki-h { position: relative; font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 36px; line-height: 1.05; color: #004aad; margin: 0 0 8px; }
.fki-p { position: relative; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 18px; line-height: 1.35; margin: 0 auto 14px; max-width: 360px; }
/* the swim lane: the fish swims past the four checkpoints to the free kilo */
.fki-lane { position: relative; height: 74px; margin: 6px 4px 6px; border-radius: 40px; background: linear-gradient(180deg, #e7f1ff, #cfe2ff); overflow: hidden; }
.fki-lane::before { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: repeating-linear-gradient(90deg, #8fb5ea 0 10px, transparent 10px 18px); }
.fki-wave { position: absolute; left: -40px; right: -40px; bottom: -10px; height: 26px; background: radial-gradient(circle at 20px -6px, transparent 16px, #b9d4fb 17px) repeat-x; background-size: 40px 26px; animation: fkiWave 3s linear infinite; opacity: .8; }
@keyframes fkiWave { to { transform: translateX(40px); } }
.fki-stop { position: absolute; top: 50%; width: 30px; height: 30px; margin: -15px 0 0 -15px; border-radius: 50%; background: #fff; border: 3px solid #004aad;
  display: flex; align-items: center; justify-content: center; font: 400 13px/1 'Bebas Neue', Impact, sans-serif; color: #004aad; transition: background .3s, color .3s, transform .3s; z-index: 1; }
.fki-stop.hit { background: #ff5a2f; border-color: #a3160f; color: #fff; transform: scale(1.18); }
.fki-stop.big { width: 40px; height: 40px; margin: -20px 0 0 -20px; font-size: 15px; }
.fki-fish { position: absolute; top: 50%; left: 2%; width: 56px; height: 56px; margin-top: -28px; z-index: 2; animation: fkiSwim 6s cubic-bezier(.45, .05, .55, .95) infinite; }
.fki-fish svg { width: 100%; height: 100%; display: block; transform: scaleX(-1); animation: fkiWiggle .5s ease-in-out infinite alternate; }
@keyframes fkiSwim { 0% { left: -2%; opacity: 1; } 80% { left: 84%; opacity: 1; } 100% { left: 84%; opacity: 0; } }
@keyframes fkiWiggle { from { rotate: -6deg; } to { rotate: 6deg; } }
.fki-rw { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 10px 0 14px; }
.fki-rw div { padding: 10px 4px 9px; border-radius: 16px; background: #fdeaf2; }
.fki-rw b { display: block; font: 400 24px/1 'Bebas Neue', Impact, sans-serif; color: #004aad; }
.fki-rw span { display: block; margin-top: 4px; font-size: 12.5px; line-height: 1.2; font-weight: 600; color: #c43b6c; }
.fki-rw div:last-child { background: #004aad; }
.fki-rw div:last-child b, .fki-rw div:last-child span { color: #fff; }
.fki-more { margin: 0 0 16px; font-size: 14.5px; line-height: 1.45; color: #4a587a; }
.fki-more strong { color: #1b2a4a; }
.fki-go { display: block; width: 100%; padding: 16px 18px; border: 0; border-radius: 999px; background: #004aad; color: #fff; text-decoration: none;
  font: 700 16px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .03em; text-transform: uppercase; box-shadow: 0 8px 20px rgba(0, 74, 173, .28); transition: transform .15s, background .15s; }
.fki-go:hover, .fki-go:focus-visible { background: #003a8a; transform: translateY(-1px); color: #fff; outline: none; }
.fki-member { display: inline-block; margin-top: 12px; padding: 6px; color: #6c7b9c; font: 500 15px 'Outfit', Arial, sans-serif; text-decoration: none; }
.fki-member u { color: #004aad; font-weight: 600; }
@media (max-width: 420px) {
  .fki-card { padding: 26px 18px 16px; border-radius: 24px; }
  .fki-h { font-size: 30px; } .fki-p { font-size: 16.5px; }
  .fki-rw { gap: 6px; } .fki-rw b { font-size: 21px; } .fki-rw span { font-size: 11.5px; }
  .fki-toons i { width: 38px; height: 38px; }
}
@media (prefers-reduced-motion: reduce) { .fki-fish, .fki-fish svg, .fki-wave, .fki-toons i { animation: none; } .fki-fish { left: 40%; } }
</style>
<script>
(function () {
  var C = window.FIKA_INVITE || {};
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function ss(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }
  var q = location.search;
  if (!C.preview) {
    if (ls('fika_member')) return;
    // count visits: a new visit after 30 minutes away; invite on visits 1, 4, 7, ...
    var now = Date.now(), v = null;
    try { v = JSON.parse(ls('fika_visits') || 'null'); } catch (e) {}
    v = v && typeof v.n === 'number' ? v : { n: 0, t: 0 };
    var fresh = now - v.t > 30 * 60 * 1000;
    if (fresh) v.n += 1;
    v.t = now; ls('fika_visits', JSON.stringify(v));
    if (!fresh || (v.n - 1) % 3 !== 0) return;
    // another popup opens this page (the visit still counts)
    if (/[?&](fika_order|fika_club|fika_bag|bag=open)/.test(q)) return;
  }
  ss('fika_invite_shown', '1'); // the sign-up cloud stays away this visit

  var FISH = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/><path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="#ff5a2f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/><ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/><circle cx="24" cy="46" r="5.2" fill="#fff"/><circle cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/></svg>';
  var STOPS = [['3', 22], ['6', 43], ['10', 64], ['15', 88]];
  function toon(s) { return window.FIKA_CARTOON ? window.FIKA_CARTOON(s) : ''; }
  var veil, card, timers = [];

  function build() {
    veil = document.createElement('div'); veil.className = 'fki-veil';
    var toons = ['bubs-bubblegum-skull', 'peaches', 'sour-strawberries'].map(function (s) { return '<i>' + toon(s) + '</i>'; }).join('');
    var lane = '<div class="fki-lane" aria-hidden="true"><div class="fki-wave"></div>' +
      STOPS.map(function (s, i) { return '<span class="fki-stop' + (i === 3 ? ' big' : '') + '" style="left:' + s[1] + '%">' + s[0] + '</span>'; }).join('') +
      '<span class="fki-fish">' + FISH + '</span></div>';
    veil.innerHTML = '<div class="fki-card" role="dialog" aria-modal="true" aria-labelledby="fkiH" tabindex="-1">' +
      '<div class="fki-toons" aria-hidden="true">' + toons + '</div>' +
      '<button type="button" class="fki-x" aria-label="Close">×</button>' +
      '<span class="fki-kick">Fika Club · free to join</span>' +
      '<h2 class="fki-h" id="fkiH">Swim your way to free sweets!</h2>' +
      '<p class="fki-p">Every gram you order moves your fish along. Reach a stop and the sweets are on us!</p>' +
      lane +
      '<div class="fki-rw">' +
        '<div><b>3 kg</b><span>100 g free</span></div>' +
        '<div><b>6 kg</b><span>200 g free</span></div>' +
        '<div><b>10 kg</b><span>400 g free</span></div>' +
        '<div><b>15 kg</b><span>A whole kilo!</span></div>' +
      '</div>' +
      '<p class="fki-more">Plus <strong>mystery-taste spins</strong> along the way, and if you have ordered from us before, <strong>your past orders already count</strong>.</p>' +
      '<a class="fki-go" href="' + C.join + '">Join Fika Club, it’s free</a>' +
      '<a class="fki-member" href="' + C.login + '">Already a member? <u>Log in</u></a>' +
      '</div>';
    document.body.appendChild(veil);
    card = veil.querySelector('.fki-card');
    veil.addEventListener('click', function (e) { if (e.target === veil || e.target.closest('.fki-x')) close(); });
    veil.querySelector('.fki-member').addEventListener('click', function () { ls('fika_member', '1'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && veil) close(); });
    // the stops light up as the fish passes them
    var stops = veil.querySelectorAll('.fki-stop');
    function lap() {
      stops.forEach(function (s) { s.classList.remove('hit'); });
      STOPS.forEach(function (s, i) { timers.push(setTimeout(function () { stops[i].classList.add('hit'); }, 4800 * (s[1] / 88) * 0.98)); });
    }
    lap(); timers.push(setInterval(lap, 6000));
  }
  function open() {
    if (document.querySelector('.fkoc-veil, .fkx-veil.on, .fkc-veil, .mx-drawer.open, body.mx-open')) return;
    build();
    requestAnimationFrame(function () { veil.classList.add('on'); card.focus({ preventScroll: true }); });
  }
  function close() {
    timers.forEach(function (t) { clearTimeout(t); clearInterval(t); });
    veil.classList.remove('on'); var v = veil; veil = null; setTimeout(function () { v.remove(); }, 320);
  }
  if (document.readyState === 'complete') setTimeout(open, 1200); else window.addEventListener('load', function () { setTimeout(open, 1200); });
})();
</script>
FIKA_INVITE;
}, 30 );
