<?php
/**
 * Fika Club rewards in the bag (signed-in customers): the mystery taste and the checkpoint rewards show in
 * "Your bag", and a popup tells the customer when a checkpoint is reached.
 * - Mystery taste (fika-taste.php): a won taste shows as a line in the bag ("Mystery taste: <candy>, 50 g, free")
 *   while the orders + this bag reach its spin stop (1.5 kg / 12.5 kg of a lap) and the bag has sweets in it; below
 *   the stop the line goes. At checkout fika-taste.php adds / removes the free line by the same rule.
 * - Checkpoint rewards (fika-loyalty.php: 3, 6, 10, 15 kg of each lap): when the orders + this bag reach one the
 *   customer has not been told about yet, a popup says so, with "Use now" and "Use later" (once per reward).
 *   "Use now" puts the reward in the bag ("100 g on us  -$2.80", total lowered) for as long as the orders + bag still
 *   reach the checkpoint; below it the reward leaves the bag (it comes back if the bag goes back up). "Use later" keeps
 *   it for later: the bag shows it with a "Use now" button as a reminder.
 * - Checkout: a reward chosen with "Use now" is applied by itself (fika-loyalty.php's checkout box: claimed there if
 *   needed, only while the order reaches it). Applied rewards show greyed out with "Unapply"; unapplying takes it out
 *   of the bag too. Delivery is never discounted.
 * - The choice is kept in this browser (localStorage fika_rw_use_<user>, fika_rw_seen_<user>). Data for the bag:
 *   inline on the page, refreshed from GET /wp-json/fika/v1/bag-rewards when the bag opens.
 * Needs snippets 11 (Fika rewards) and 46 (mystery taste). Installed with the Code Snippets plugin.
 * Source: wordpress/snippets/fika-bag-rewards.php
 */

if ( ! function_exists( 'fika_brw_data' ) ) {
	// what this customer can have in the bag: rewards (claimed and not used, or not claimed yet) and won tastes
	function fika_brw_data( $uid ) {
		$uid = (int) $uid;
		if ( ! $uid || ! function_exists( 'fika_swim_reach' ) ) {
			return null;
		}
		$reach  = fika_swim_reach( $uid );
		$cps    = fika_swim_checkpoints();
		// read only: never withdraw rewards here (one claimed at checkout for the bag is not in an order yet)
		$claims = fika_swim_claims( $uid );
		$rw     = array();
		foreach ( $claims as $cl ) {
			$g = (int) $cl['g'];
			if ( ! isset( $cps[ $g ] ) || fika_swim_code_used( $cl['code'] ) ) {
				continue;
			}
			$c      = new WC_Coupon( $cl['code'] );
			$need   = fika_swim_need( $cl['lap'], $g );
			$rw[]   = array( 'key' => (int) $cl['lap'] . ':' . $g, 'code' => $cl['code'], 'need' => $need, 'kg' => $need / 1000, 'title' => $cps[ $g ]['title'], 'co' => $cps[ $g ]['co'], 'amount' => $c->get_id() ? (float) $c->get_amount() : (float) $cps[ $g ]['amount'] );
		}
		foreach ( fika_swim_unclaimed( $uid, $reach + 2 * FIKA_SWIM_LAP ) as $u ) {
			$cp   = $cps[ $u[1] ];
			$rw[] = array( 'key' => (int) $u[0] . ':' . (int) $u[1], 'code' => '', 'need' => (int) $u[2], 'kg' => $u[2] / 1000, 'title' => $cp['title'], 'co' => $cp['co'], 'amount' => (float) $cp['amount'] );
		}
		usort( $rw, function ( $a, $b ) { return $a['need'] - $b['need']; } );
		$tastes = array();
		if ( function_exists( 'fika_taste_list' ) ) {
			foreach ( fika_taste_list( $uid ) as $k => $t ) {
				$p = 'won' === $t['status'] ? wc_get_product( (int) $t['pid'] ) : null;
				if ( $p ) {
					$tastes[] = array( 'key' => $k, 'need' => fika_taste_need( $k ), 'name' => html_entity_decode( $p->get_name() ), 'img' => $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '' );
				}
			}
		}
		return array( 'uid' => $uid, 'reach' => $reach, 'rewards' => $rw, 'tastes' => $tastes );
	}
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/bag-rewards', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => function () {
			nocache_headers();
			return fika_brw_data( get_current_user_id() );
		},
	) );
} );

// ---------- shop pages: the bag lines and the checkpoint popup ----------
add_action( 'wp_footer', function () {
	$ours = is_front_page() || is_page( array( 'mix-your-own', 'ready-mix', 'about-us' ) ) || ( function_exists( 'is_product' ) && is_product() ) || is_404();
	if ( ! $ours || ! is_user_logged_in() ) {
		return;
	}
	$data = fika_brw_data( get_current_user_id() );
	if ( ! $data ) {
		return;
	}
	$cfg = array( 'data' => $data, 'api' => rest_url( 'fika/v1/bag-rewards' ), 'nonce' => wp_create_nonce( 'wp_rest' ) );
	echo '<script>window.FIKA_BRW = ' . wp_json_encode( $cfg ) . ';</script>';
	echo <<<'FIKA_BRW'
<style>
/* the taste line says it is in the bag now, so the older "joins your order at checkout" note is not needed */
.mx-df .fk-taste-note { display: none !important; }
/* the rewards note under the bag ("this bag unlocks ... tap Use at checkout") is replaced by the reward lines */
.mx-df .fk-rw-note.is-on { display: none !important; }
.mx-line.fk-brw { color: #1b2a4a; background: #fff8fb; margin: 0 -12px; padding-left: 12px; padding-right: 12px; }
.mx-line.fk-brw .fk-brw-ic { flex: none; width: 38px; height: 38px; border-radius: 10px; background: #ffe3ef center / cover no-repeat; display: flex; align-items: center; justify-content: center; font-size: 19px; }
.mx-line.fk-brw > div:nth-child(2) { flex: 1; min-width: 0; color: #004aad; }
.mx-line.fk-brw small { display: block; color: #6b7894; font-size: 12.5px; }
.mx-line.fk-brw .mx-lp { font-weight: 700; color: #2fb36b; }
.mx-line.fk-brw.is-later .mx-lp { color: #004aad; }
.fk-brw-use { flex: none; height: 32px; padding: 0 14px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 12.5px/1 'Outfit', Arial, sans-serif; letter-spacing: .03em; text-transform: uppercase; cursor: pointer; }
.fk-brw-use:hover { background: #003a8a; }
.fk-brw-off { flex: none; border: 0; background: none; color: #a2aecb; font-size: 20px; cursor: pointer; }
.fk-brw-off:hover { color: #d13b73; }
.mx-sum.fk-brw-sum { color: #2fb36b; font-weight: 600; }
.fk-rwp-veil { position: fixed; inset: 0; z-index: 200001; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(27, 42, 74, .35); opacity: 0; transition: opacity .25s; }
.fk-rwp-veil.on { opacity: 1; }
.fk-rwp { position: relative; box-sizing: border-box; width: 100%; max-width: 380px; padding: 28px 24px 18px; border-radius: 22px; background: #fff; text-align: center; color: #1b2a4a; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; box-shadow: 0 20px 50px rgba(0, 74, 173, .22); transform: translateY(12px) scale(.97); transition: transform .35s cubic-bezier(.3, 1.4, .5, 1); }
.fk-rwp-veil.on .fk-rwp { transform: none; }
.fk-rwp * { box-sizing: border-box; }
.fk-rwp .fk-rwp-x { position: absolute; top: 10px; right: 10px; width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f3f5fa; color: #4a587a; font-size: 20px; line-height: 1; cursor: pointer; }
.fk-rwp .fk-rwp-gift { font-size: 46px; line-height: 1; margin: 0 0 6px; animation: fkRwpPop .6s cubic-bezier(.3, 1.6, .5, 1); }
@keyframes fkRwpPop { from { transform: scale(.4) rotate(-12deg); } to { transform: none; } }
.fk-rwp h2 { margin: 0 0 6px; font: 400 30px/1.05 'NF Le Petit Cochon', cursive; font-variant: small-caps; color: #004aad; }
.fk-rwp .fk-rwp-what { margin: 0 0 4px; font: 400 26px/1.1 'Bebas Neue', Impact, sans-serif; letter-spacing: .02em; color: #1b2a4a; }
.fk-rwp p { margin: 0 0 18px; font-size: 15px; line-height: 1.45; color: #4a587a; }
.fk-rwp .fk-rwp-now { display: block; width: 100%; padding: 14px 18px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 700 15px/1.2 'Outfit', Arial, sans-serif; letter-spacing: .03em; text-transform: uppercase; cursor: pointer; }
.fk-rwp .fk-rwp-now:hover { background: #003a8a; }
.fk-rwp .fk-rwp-later { display: inline-block; margin-top: 10px; padding: 6px 10px; border: 0; background: none; color: #004aad; font: 600 14.5px 'Outfit', Arial, sans-serif; text-decoration: underline; cursor: pointer; }
.fk-rwp small { display: block; margin-top: 6px; color: #8a94a8; font-size: 12.5px; }
</style>
<script>
(function () {
  var C = window.FIKA_BRW || {}, D = C.data || { rewards: [], tastes: [], reach: 0, uid: 0 };
  var UK = 'fika_rw_use_' + D.uid, SK = 'fika_rw_seen_' + D.uid;
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function arr(k) { try { var a = JSON.parse(ls(k) || '[]'); return Array.isArray(a) ? a : []; } catch (e) { return []; } }
  function setArr(k, a) { ls(k, JSON.stringify(a.filter(function (x, i) { return a.indexOf(x) === i; }))); }
  function bagG() { var g = 0; try { var b = JSON.parse(ls('fika_bag_v1') || '{}').bag || {}; Object.keys(b).forEach(function (k) { g += +b[k] || 0; }); } catch (e) {} return g; }
  function money(n) { return '$' + (Math.round(n * 100) / 100).toFixed(2); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function reach() { return D.reach + bagG(); }
  function using() { return arr(UK); }

  // ---- the bag drawer ----
  var busy = false, obs = null, lastHtml = '', lastNodes = [];
  function drawLines() {
    var items = document.getElementById('mxItems'), df = document.querySelector('.mx-df');
    if (!items || !df || busy) return;
    busy = true;
    if (obs) obs.disconnect();
    [].forEach.call(document.querySelectorAll('.fk-brw, .fk-brw-sum'), function (n) { n.remove(); });
    var g = bagG(), r = reach(), use = using(), html = '', disc = 0;
    if (g > 0) {
      D.tastes.forEach(function (t) {
        if (t.need > r) return;
        html += '<div class="mx-line fk-brw is-taste"><span class="fk-brw-ic"' + (t.img ? ' style="background-image:url(&quot;' + esc(t.img) + '&quot;)"' : '') + '>' + (t.img ? '' : '🎁') + '</span>' +
          '<div>Mystery taste: ' + esc(t.name) + '<small>50 g, from your spin. On us!</small></div><div class="mx-lp">Free</div></div>';
      });
      D.rewards.forEach(function (w) {
        if (w.need > r) return;
        var on = use.indexOf(w.key) !== -1;
        if (on) disc += w.amount;
        html += '<div class="mx-line fk-brw ' + (on ? 'is-on' : 'is-later') + '" data-rw="' + esc(w.key) + '"><span class="fk-brw-ic">🎁</span>' +
          '<div>' + esc(w.title) + '<small>' + (on ? 'Fika Club reward, ' + esc(w.co) : 'Fika Club reward waiting for you') + '</small></div>' +
          (on ? '<div class="mx-lp">&minus;' + money(w.amount) + '</div><button class="fk-brw-off" type="button" aria-label="Take this reward out of the bag">&times;</button>'
              : '<button class="fk-brw-use" type="button">Use now</button>') + '</div>';
      });
    }
    if (html) {
      // the same lines as last time: put the same elements back (no new photo load), else build them
      if (html !== lastHtml) { var tmp = document.createElement('div'); tmp.innerHTML = html; lastNodes = [].slice.call(tmp.children); lastHtml = html; }
      // at the top of the bag, so they are seen without scrolling
      var first = items.firstChild;
      lastNodes.forEach(function (n) { items.insertBefore(n, first); });
    }
    var subEl = document.getElementById('mxSub'), totEl = document.getElementById('mxTot');
    if (disc > 0 && subEl && totEl) {
      var sub = parseFloat((subEl.textContent || '').replace(/[^0-9.]/g, '')) || 0, d = Math.min(disc, sub);
      var row = document.createElement('div'); row.className = 'mx-sum fk-brw-sum';
      row.innerHTML = '<span>Fika Club rewards</span><span>&minus;' + money(d) + '</span>';
      var tot = df.querySelector('.mx-sum.tot'); if (tot) df.insertBefore(row, tot);
      totEl.textContent = money(sub - d);
    } else if (subEl && totEl) {
      totEl.textContent = subEl.textContent; // no reward on: the total is the bag again
    }
    busy = false;
    watch();
  }
  function watch() {
    var items = document.getElementById('mxItems');
    if (!items || !window.MutationObserver) return;
    // the bag list was just redrawn by the shop: put our lines back in the same moment, before the browser paints
    // (a tick later the list showed for an instant without them and jumped: the flicker)
    if (!obs) obs = new MutationObserver(function () { if (!busy) drawLines(); });
    obs.observe(items, { childList: true });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.fk-brw-use, .fk-brw-off') : null;
    if (!b) return;
    var key = b.closest('.fk-brw').getAttribute('data-rw'), use = using();
    if (b.classList.contains('fk-brw-use')) use.push(key); else use = use.filter(function (k) { return k !== key; });
    setArr(UK, use); drawLines();
  });
  // fresh data each time the bag opens (a taste won a moment ago, a reward claimed on the tracker)
  function refresh() {
    if (!C.api || !window.fetch) return;
    fetch(C.api, { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) { if (j && j.rewards) { D = j; drawLines(); check(); } }).catch(function () {});
  }
  var drawer = document.getElementById('mxDrawer');
  if (drawer && window.MutationObserver) new MutationObserver(function () { if (drawer.classList.contains('on')) refresh(); }).observe(drawer, { attributes: true, attributeFilter: ['class'] });
  window.addEventListener('fikabag', function () { setTimeout(function () { drawLines(); check(); }, 0); });

  // ---- the checkpoint popup ----
  var open = false;
  function check() {
    if (open) return;
    if (document.querySelector('.fk-wheel-veil, .fkoc-veil, .fkc-veil, .fki-veil, .fkx-veil.on')) return;
    var seen = arr(SK), use = using(), r = reach();
    var w = D.rewards.filter(function (x) { return x.need <= r && seen.indexOf(x.key) === -1 && use.indexOf(x.key) === -1; })[0];
    if (w) pop(w);
  }
  function pop(w) {
    open = true;
    var veil = document.createElement('div'); veil.className = 'fk-rwp-veil';
    veil.innerHTML = '<div class="fk-rwp" role="dialog" aria-modal="true" aria-labelledby="fkRwpH" tabindex="-1">' +
      '<button type="button" class="fk-rwp-x" aria-label="Close">&times;</button><div class="fk-rwp-gift" aria-hidden="true">🎁</div>' +
      '<h2 id="fkRwpH">You reached ' + esc(String(w.kg).replace('.', '.')) + ' kg!</h2>' +
      '<div class="fk-rwp-what">' + esc(w.title) + '</div>' +
      '<p>Your Fika Club reward is ready: ' + esc(w.co) + '. Use it on this bag, or keep it for later.</p>' +
      '<button type="button" class="fk-rwp-now">Use now</button><button type="button" class="fk-rwp-later">Use later</button>' +
      '<small>It stays in your account until you use it. Delivery not included.</small></div>';
    document.body.appendChild(veil);
    var seen = arr(SK); seen.push(w.key); setArr(SK, seen);
    function close() { veil.classList.remove('on'); setTimeout(function () { veil.remove(); open = false; check(); }, 260); }
    veil.addEventListener('click', function (e) {
      if (e.target.closest('.fk-rwp-now')) { var u = using(); u.push(w.key); setArr(UK, u); drawLines(); close(); return; }
      if (e.target === veil || e.target.closest('.fk-rwp-later, .fk-rwp-x')) close();
    });
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { document.removeEventListener('keydown', esc); close(); } });
    requestAnimationFrame(function () { veil.classList.add('on'); veil.querySelector('.fk-rwp').focus({ preventScroll: true }); });
  }

  function start() { drawLines(); setTimeout(check, 1500); setInterval(function () { check(); }, 1500); }
  if (document.readyState === 'complete') start(); else window.addEventListener('load', start);
})();
</script>
FIKA_BRW;
}, 40 );

// ---------- checkout: rewards chosen with "Use now" are applied by themselves; unapplying takes them out ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! is_user_logged_in() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	$data = fika_brw_data( get_current_user_id() );
	if ( ! $data ) {
		return;
	}
	$map = array();
	foreach ( $data['rewards'] as $w ) {
		if ( $w['code'] ) {
			$map[ strtolower( $w['code'] ) ] = $w['key'];
		}
	}
	echo '<script>window.FIKA_BRW_CO = ' . wp_json_encode( array( 'uid' => $data['uid'], 'map' => $map, 'api' => rest_url( 'fika/v1/bag-rewards' ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) ) . ';</script>';
	echo <<<'FIKA_BRW_CO'
<style>
/* applied rewards: greyed out, with "Unapply" */
.fika-freekg .fr-row.is-on { margin: 0 -10px; padding: 9px 10px; border-radius: 12px; background: #f1f3f7; }
.fika-freekg .fr-row.is-on span, .fika-freekg .fr-row.is-on span strong { color: #8a94a8; }
.fika-freekg .fr-row.is-on strong::after { content: ' \2713  Applied'; color: #8a94a8; font-weight: 600; }
.fika-freekg .fr-row.is-on button.fr-use { background: #fff; color: #4a587a; box-shadow: inset 0 0 0 2px #c9d1e0; }
</style>
<script>
(function () {
  var C = window.FIKA_BRW_CO || {}, UK = 'fika_rw_use_' + C.uid, auto = false;
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function arr() { try { var a = JSON.parse(ls(UK) || '[]'); return Array.isArray(a) ? a : []; } catch (e) { return []; } }
  // a reward claimed here a moment ago has a new code: learn which reward it is
  var asked = 0;
  function learn() {
    if (Date.now() - asked < 3000 || !window.fetch) return; asked = Date.now();
    fetch(C.api, { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } }).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) { (j && j.rewards ? j.rewards : []).forEach(function (w) { if (w.code) C.map[w.code.toLowerCase()] = w.key; }); }).catch(function () {});
  }
  // which reward a checkout row is: by its code, by lap + checkpoint, or (a code claimed here a moment ago, not
  // learned yet) by the checkpoint in the code (SWIM3- = 3 kg) and the customer's chosen rewards
  function keyOf(row) {
    var c = (row.getAttribute('data-code') || '').toLowerCase();
    if (!c) return row.getAttribute('data-lap') ? row.getAttribute('data-lap') + ':' + row.getAttribute('data-g') : '';
    if (C.map[c]) return C.map[c];
    var m = c.match(/^swim(3|6|10|15)-/), g = m ? (+m[1]) * 1000 : 0;
    var hit = arr().filter(function (k) { return +k.split(':')[1] === g; })[0];
    return hit || '';
  }
  // the customer's own taps: "Use" adds the reward to their choice, "Unapply" takes it out (also out of the bag)
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.fika-freekg .fr-use') : null;
    if (!b || auto) return;
    var row = b.closest('.fr-row'), k = keyOf(row), use = arr();
    if (!k) return;
    if (row.classList.contains('is-on')) use = use.filter(function (x) { return x !== k; }); else if (use.indexOf(k) === -1) use.push(k);
    ls(UK, JSON.stringify(use));
  }, true);
  // a reward chosen in the bag: apply it once this order reaches it (one at a time)
  function tick() {
    var use = arr(); if (!use.length) return;
    var rows = document.querySelectorAll('.fika-freekg .fr-row');
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i], b = row.querySelector('.fr-use'), k = keyOf(row);
      if (!k || use.indexOf(k) === -1 || row.classList.contains('is-on') || !b || b.disabled || /moment/i.test(b.textContent)) continue;
      auto = true; b.click(); auto = false;
      return;
    }
  }
  // the button reads "Unapply" on applied rewards
  function unknown() { [].forEach.call(document.querySelectorAll('.fika-freekg .fr-row[data-code]'), function (r) { if (!C.map[(r.getAttribute('data-code') || '').toLowerCase()]) learn(); }); }
  function label() { [].forEach.call(document.querySelectorAll('.fika-freekg .fr-row.is-on .fr-use'), function (b) { if (b.textContent === 'Remove') b.textContent = 'Unapply'; }); }
  setInterval(function () { unknown(); tick(); label(); }, 700);
})();
</script>
FIKA_BRW_CO;
}, 40 );
