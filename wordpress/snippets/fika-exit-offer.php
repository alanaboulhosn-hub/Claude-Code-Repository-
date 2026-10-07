<?php
/**
 * Fika: "before you go" popup on the checkout.
 * Shows when a shopper is about to leave the checkout: clicking "Return to store" or the Fika logo,
 * moving the mouse up out of the page (desktop exit intent), or pressing Back (phones).
 * Step 1 asks why they are leaving. "I want to change my order" sends them back to the store.
 * Any other reason leads to a one-time offer: 10% off the candies with coupon FIKA10
 * (WooCommerce coupon: percent, individual use, usage limit 1 per customer, checked by email).
 * The offer is shown once per browser; reasons are counted in the option "fika_exit_reasons".
 * WP Admin > WooCommerce > Checkout leavers shows the counts and can reset them.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-exit-offer.php
 */

// Count the reasons people give (no personal data): POST /wp-json/fika/v1/exit-reason { reason }
add_action( 'rest_api_init', function () {
	register_rest_route( 'fika/v1', '/exit-reason', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array( 'reason' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( $request ) {
			$allowed = array( 'delivery-price', 'delivery-time', 'product-price', 'change-order', 'other', 'offer-applied', 'offer-declined' );
			$reason  = sanitize_key( $request->get_param( 'reason' ) );
			if ( ! in_array( $reason, $allowed, true ) ) {
				return new WP_Error( 'fika_reason', 'Unknown reason', array( 'status' => 400 ) );
			}
			$counts            = get_option( 'fika_exit_reasons', array() );
			$counts[ $reason ] = isset( $counts[ $reason ] ) ? (int) $counts[ $reason ] + 1 : 1;
			update_option( 'fika_exit_reasons', $counts, false );
			return array( 'ok' => true );
		},
	) );
	// Read the counts (shop managers only): GET /wp-json/fika/v1/exit-reasons
	register_rest_route( 'fika/v1', '/exit-reasons', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function () { return get_option( 'fika_exit_reasons', array() ); },
	) );
	// Reset the counts (shop managers only): DELETE /wp-json/fika/v1/exit-reasons
	register_rest_route( 'fika/v1', '/exit-reasons', array(
		'methods'             => 'DELETE',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function () { fika_exit_reset(); return array( 'reset' => true ); },
	) );
} );

if ( ! function_exists( 'fika_exit_reset' ) ) {
	function fika_exit_reset() {
		delete_option( 'fika_exit_reasons' );
		update_option( 'fika_exit_reasons_since', time(), false );
	}
}

// ---------- WP Admin > WooCommerce > Checkout leavers ----------
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Checkout leavers', 'Checkout leavers', 'manage_woocommerce', 'fika-checkout-leavers', 'fika_exit_admin_page' );
}, 60 );
add_action( 'admin_post_fika_exit_reset', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'fika_exit_reset' );
	fika_exit_reset();
	wp_safe_redirect( admin_url( 'admin.php?page=fika-checkout-leavers&reset=1' ) );
	exit;
} );
if ( ! function_exists( 'fika_exit_admin_page' ) ) {
	function fika_exit_admin_page() {
		$c       = get_option( 'fika_exit_reasons', array() );
		$n       = function ( $k ) use ( $c ) { return isset( $c[ $k ] ) ? (int) $c[ $k ] : 0; };
		$reasons = array(
			'delivery-price' => 'Delivery is too expensive',
			'delivery-time'  => 'Delivery takes too long',
			'product-price'  => 'The candies are too expensive',
			'change-order'   => 'I want to change my order',
			'other'          => 'Another reason',
		);
		$total   = 0;
		foreach ( $reasons as $k => $label ) {
			$total += $n( $k );
		}
		$applied  = $n( 'offer-applied' );
		$declined = $n( 'offer-declined' );
		$since    = (int) get_option( 'fika_exit_reasons_since', 0 );
		$pct      = function ( $x, $of ) { return $of ? round( 100 * $x / $of ) . '%' : '–'; };
		?>
<div class="wrap">
	<h1>Checkout leavers</h1>
	<p>What shoppers answered in the &ldquo;Leaving already?&rdquo; popup on the checkout, and what they did with the one-time 10% offer (FIKA10).
	Anonymous counts only. <?php echo $since ? 'Counting since ' . esc_html( wp_date( 'j F Y, H:i', $since ) ) . '.' : ''; ?></p>
		<?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
	<div class="notice notice-success is-dismissible"><p>The counts were reset to zero.</p></div>
		<?php endif; ?>
	<h2>Why they wanted to leave</h2>
	<table class="widefat striped" style="max-width:640px">
		<thead><tr><th>Reason</th><th style="width:90px">Shoppers</th><th style="width:90px">Share</th></tr></thead>
		<tbody>
		<?php foreach ( $reasons as $k => $label ) : ?>
			<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo (int) $n( $k ); ?></td><td><?php echo esc_html( $pct( $n( $k ), $total ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot><tr><th>Total</th><th><?php echo (int) $total; ?></th><th></th></tr></tfoot>
	</table>
	<h2>The 10% offer</h2>
	<table class="widefat striped" style="max-width:640px">
		<thead><tr><th>Answer</th><th style="width:90px">Shoppers</th><th style="width:90px">Share</th></tr></thead>
		<tbody>
			<tr><td>Applied the 10% and stayed</td><td><?php echo (int) $applied; ?></td><td><?php echo esc_html( $pct( $applied, $applied + $declined ) ); ?></td></tr>
			<tr><td>Said &ldquo;No thanks, I&rsquo;ll leave&rdquo;</td><td><?php echo (int) $declined; ?></td><td><?php echo esc_html( $pct( $declined, $applied + $declined ) ); ?></td></tr>
		</tbody>
	</table>
	<p class="description" style="max-width:640px">&ldquo;I want to change my order&rdquo; goes back to the shop without an offer. Shoppers who already used the offer,
	have another discount code, or hold a free kilo are not offered the 10%. Closing the popup without answering is not counted.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px"
		onsubmit="return confirm('Reset all counts to zero? This cannot be undone.');">
		<input type="hidden" name="action" value="fika_exit_reset">
		<?php wp_nonce_field( 'fika_exit_reset' ); ?>
		<button type="submit" class="button">Reset counts</button>
	</form>
</div>
		<?php
	}
}

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	echo <<<'FIKA_EXIT'
<style>
.fkx-veil { position: fixed; inset: 0; z-index: 200000; display: flex; align-items: center; justify-content: center; padding: 18px;
  background: rgba(253, 234, 242, .74); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
  opacity: 0; pointer-events: none; transition: opacity .25s; }
.fkx-veil.on { opacity: 1; pointer-events: auto; }
.fkx-card { position: relative; width: 100%; max-width: 440px; max-height: calc(100vh - 36px); overflow: auto; background: #fff; border-radius: 26px;
  box-shadow: 0 24px 60px rgba(0, 74, 173, .22); padding: 34px 28px 24px; text-align: center; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; color: #1b2a4a;
  transform: translateY(18px) scale(.96); transition: transform .35s cubic-bezier(.3, 1.4, .5, 1); }
.fkx-veil.on .fkx-card { transform: none; }
.fkx-card:focus { outline: none; }
.fkx-x { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: #fdeaf2; color: #004aad; font-size: 22px; line-height: 1; cursor: pointer; }
.fkx-x:hover { background: #fbd6e6; }
.fkx-toons { display: flex; justify-content: center; gap: 10px; height: 54px; margin: -6px 0 8px; }
.fkx-toons i { width: 50px; height: 50px; display: block; animation: fkxBob 1.6s ease-in-out infinite alternate; }
.fkx-toons i:nth-child(2) { animation-delay: .25s; width: 56px; height: 56px; margin-top: -6px; }
.fkx-toons i:nth-child(3) { animation-delay: .5s; }
.fkx-toons svg { width: 100%; height: 100%; display: block; overflow: visible; filter: drop-shadow(0 3px 3px rgba(80, 20, 50, .22)); }
@keyframes fkxBob { from { transform: translateY(0) rotate(-6deg); } to { transform: translateY(-6px) rotate(6deg); } }
.fkx-h { font-family: 'NF Le Petit Cochon', cursive; font-variant: small-caps; font-weight: 400; font-size: 34px; line-height: 1.1; color: #004aad; margin: 0 0 8px; }
.fkx-p { font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 18px; line-height: 1.4; margin: 0 0 18px; }
.fkx-opts { display: grid; gap: 10px; margin: 0 0 14px; }
.fkx-opt { display: block; width: 100%; padding: 14px 18px; border: 2px solid #004aad; border-radius: 999px; background: #fff; color: #004aad;
  font: 600 16px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; cursor: pointer; transition: background .15s, color .15s, transform .15s; }
.fkx-opt:hover, .fkx-opt:focus-visible { background: #004aad; color: #fff; transform: translateY(-1px); outline: none; }
.fkx-go { display: block; width: 100%; padding: 16px 18px; border: 0; border-radius: 999px; background: #004aad; color: #fff;
  font: 700 16px/1.2 'Outfit', 'Open Sans', Arial, sans-serif; letter-spacing: .03em; text-transform: uppercase; cursor: pointer; }
.fkx-go:hover { background: #003a8a; }
.fkx-go[disabled] { opacity: .6; cursor: default; }
.fkx-link { display: inline-block; margin-top: 12px; padding: 8px; border: 0; background: none; color: #6c7b9c; font: 500 15px 'Outfit', Arial, sans-serif; text-decoration: underline; cursor: pointer; }
.fkx-badge { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; width: 150px; height: 150px; margin: 4px auto 16px;
  border-radius: 50%; background: radial-gradient(circle at 35% 30%, #2f6fd0, #004aad 70%); color: #fff; box-shadow: 0 10px 30px rgba(0, 74, 173, .35);
  animation: fkxPop .6s cubic-bezier(.3, 1.6, .5, 1); }
.fkx-badge b { font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 64px; line-height: .9; }
.fkx-badge span { font-family: 'Bebas Neue', Impact, sans-serif; font-size: 24px; letter-spacing: .08em; }
@keyframes fkxPop { 0% { transform: scale(.3) rotate(-25deg); } 100% { transform: none; } }
.fkx-code { font: 600 14px 'Outfit', Arial, sans-serif; color: #6c7b9c; margin: -6px 0 16px; }
.fkx-code b { color: #004aad; letter-spacing: .08em; }
.fkx-msg { font: 600 15px 'Outfit', Arial, sans-serif; color: #c0335f; margin: 10px 0 0; min-height: 1em; }
.fkx-ok { font: 700 17px 'Outfit', Arial, sans-serif; color: #1f8a4c; margin: 4px 0 0; }
.fkx-confetti { position: absolute; left: 50%; top: 40%; width: 0; height: 0; pointer-events: none; }
.fkx-confetti i { position: absolute; left: -15px; top: -15px; width: 30px; height: 30px; opacity: 0; animation: fkxBurst 1.2s cubic-bezier(.2, .7, .3, 1) forwards; }
.fkx-confetti svg { width: 100%; height: 100%; display: block; overflow: visible; }
@keyframes fkxBurst { 0% { opacity: 0; transform: translate(0, 0) scale(.3); } 15% { opacity: 1; } 100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--r)) scale(1); } }
@media (max-width: 480px) { .fkx-card { padding: 30px 20px 20px; border-radius: 22px; } .fkx-h { font-size: 30px; } .fkx-badge { width: 128px; height: 128px; } .fkx-badge b { font-size: 54px; } }
@media (prefers-reduced-motion: reduce) { .fkx-toons i, .fkx-badge, .fkx-confetti i { animation: none !important; } .fkx-confetti { display: none; } }
</style>
<script>
(function () {
  if (window.__fikaExit) return;
  window.__fikaExit = true;

  var COUPON = 'FIKA10';
  var OFFER_KEY = 'fika_exit_offer_v1';      // localStorage: 'applied' | 'declined' once the offer has been shown
  var SEEN_KEY = 'fika_exit_seen_v1';        // sessionStorage: popup already shown this visit
  var veil = null, card = null, dest = null, busy = false;

  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function ss(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }
  function toon(slug) { return window.FIKA_CARTOON ? window.FIKA_CARTOON(slug) : ''; }
  function hasCoupon() {
    try { var c = wp.data.select('wc/store/cart').getCartData(); return (c.coupons || []).some(function (x) { return (x.code || '').toLowerCase() === COUPON.toLowerCase(); }); } catch (e) { return false; }
  }
  function cartEmpty() {
    try { return !(wp.data.select('wc/store/cart').getCartData().items || []).length; } catch (e) { return false; }
  }
  // No 10% offer for customers holding a free kilo (snippet 11): the codes don't combine, and $25 off beats 10%
  function freeKilo() {
    if (window.FIKA_FREEKG) return true;
    try { return (wp.data.select('wc/store/cart').getCartData().coupons || []).some(function (x) { return (x.code || '').toLowerCase().indexOf('freekg-') === 0; }); } catch (e) { return false; }
  }
  function offerAvailable() { return !ls(OFFER_KEY) && !hasCoupon() && !freeKilo(); }
  function track(reason) {
    try {
      fetch('/wp-json/fika/v1/exit-reason', { method: 'POST', keepalive: true, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason: reason }) });
    } catch (e) {}
  }

  function build() {
    veil = document.createElement('div');
    veil.className = 'fkx-veil';
    veil.setAttribute('role', 'dialog');
    veil.setAttribute('aria-modal', 'true');
    veil.setAttribute('aria-labelledby', 'fkxTitle');
    card = document.createElement('div');
    card.className = 'fkx-card';
    card.tabIndex = -1;
    veil.appendChild(card);
    document.body.appendChild(veil);
    veil.addEventListener('click', function (e) { if (e.target === veil) stay(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if (veil.classList.contains('on')) stay(); } });
  }
  function closeBtn() { return '<button class="fkx-x" type="button" data-fkx="stay" aria-label="Close">×</button>'; }
  function toons(a, b, c) { return '<div class="fkx-toons" aria-hidden="true"><i>' + toon(a) + '</i><i>' + toon(b) + '</i><i>' + toon(c) + '</i></div>'; }

  function stepReasons() {
    card.innerHTML = closeBtn() + toons('bubs-bubblegum-skull', 'swedish-fish', 'sugared-strawberries') +
      '<h2 class="fkx-h" id="fkxTitle">Leaving already?</h2>' +
      '<p class="fkx-p">Your sweets are waiting. Mind telling us why you are going?</p>' +
      '<div class="fkx-opts">' +
        '<button class="fkx-opt" type="button" data-fkx="reason" data-r="delivery-price">Delivery is too expensive</button>' +
        '<button class="fkx-opt" type="button" data-fkx="reason" data-r="delivery-time">Delivery takes too long</button>' +
        '<button class="fkx-opt" type="button" data-fkx="reason" data-r="product-price">The candies are too expensive</button>' +
        '<button class="fkx-opt" type="button" data-fkx="reason" data-r="change-order">I want to change my order</button>' +
        '<button class="fkx-opt" type="button" data-fkx="reason" data-r="other">Another reason</button>' +
      '</div>' +
      '<button class="fkx-link" type="button" data-fkx="stay">Keep checking out</button>';
  }
  var LINES = {
    'delivery-price': 'We can’t change the delivery fee, but we can make your candies 10% cheaper.',
    'delivery-time': 'Fresh Swedish sweets are worth the wait — and now they are 10% cheaper.',
    'product-price': 'Let’s make it sweeter: 10% off every candy in your bag.',
    'other': 'Before you go, here is a little something: 10% off your candies.'
  };
  function stepOffer(reason) {
    card.innerHTML = closeBtn() +
      '<h2 class="fkx-h" id="fkxTitle">Wait! A treat for you</h2>' +
      '<p class="fkx-p">' + (LINES[reason] || LINES.other) + '</p>' +
      '<div class="fkx-badge" aria-hidden="true"><b>10%</b><span>OFF</span></div>' +
      '<p class="fkx-code">One time only · code <b>' + COUPON + '</b></p>' +
      '<button class="fkx-go" type="button" data-fkx="apply">Apply 10% and finish my order</button>' +
      '<p class="fkx-msg" aria-live="polite"></p>' +
      '<button class="fkx-link" type="button" data-fkx="leave">No thanks, I’ll leave</button>';
    burst();
  }
  function stepThanks() {
    card.innerHTML = closeBtn() + toons('peaches', 'sugared-apples', 'tutti-frutti-rings') +
      '<h2 class="fkx-h" id="fkxTitle">Thanks for telling us</h2>' +
      '<p class="fkx-p">Your bag is saved, so you can pick up where you left off any time.</p>' +
      '<button class="fkx-go" type="button" data-fkx="stay">Keep checking out</button>' +
      '<button class="fkx-link" type="button" data-fkx="leave">Leave anyway</button>';
  }
  function burst() {
    if (!window.FIKA_CARTOON) return;
    var slugs = (window.FIKA_CARTOON_SLUGS || []).slice().sort(function () { return Math.random() - 0.5; }).slice(0, 12);
    var c = document.createElement('div');
    c.className = 'fkx-confetti';
    slugs.forEach(function (sl, i) {
      var a = (i / slugs.length) * Math.PI * 2, d = 120 + Math.random() * 70;
      var el = document.createElement('i');
      el.style.cssText = '--dx:' + Math.round(Math.cos(a) * d) + 'px;--dy:' + Math.round(Math.sin(a) * d) + 'px;--r:' + Math.round(Math.random() * 540 - 270) + 'deg;animation-delay:' + (i * 0.02) + 's';
      el.innerHTML = toon(sl);
      c.appendChild(el);
    });
    card.appendChild(c);
    setTimeout(function () { if (c.parentNode) c.parentNode.removeChild(c); }, 1500);
  }

  function open(target) {
    if (busy) return false;
    if (cartEmpty()) return false;
    if (ss(SEEN_KEY)) return false;
    ss(SEEN_KEY, '1');
    dest = target;
    if (!veil) build();
    stepReasons();
    veil.classList.add('on');
    setTimeout(function () { card.focus(); }, 60);
    return true;
  }
  function hide() { if (veil) veil.classList.remove('on'); }
  function stay() { hide(); }
  function leave() {
    hide();
    if (dest === '__back__') { busy = true; history.back(); return; }
    if (dest) { busy = true; location.href = dest; }
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-fkx]') : null;
    if (!b) return;
    var act = b.getAttribute('data-fkx');
    if (act === 'stay') { stay(); return; }
    if (act === 'leave') { if (card.querySelector('.fkx-badge')) { ls(OFFER_KEY, 'declined'); track('offer-declined'); } leave(); return; }
    if (act === 'reason') {
      var r = b.getAttribute('data-r');
      track(r);
      if (r === 'change-order') { hide(); busy = true; location.href = '/mix-your-own/'; return; }
      if (offerAvailable()) stepOffer(r); else stepThanks();
      return;
    }
    if (act === 'apply') {
      var msg = card.querySelector('.fkx-msg');
      b.disabled = true; b.textContent = 'Applying…';
      var done = function () {
        ls(OFFER_KEY, 'applied'); track('offer-applied');
        card.querySelector('.fkx-code').innerHTML = '<span class="fkx-ok">10% off applied to your candies ✓</span>';
        b.textContent = 'Finish my order';
        b.disabled = false; b.setAttribute('data-fkx', 'stay');
        burst();
        setTimeout(hide, 1800);
      };
      var fail = function (err) {
        b.disabled = false; b.textContent = 'Apply 10% and finish my order';
        msg.textContent = (err && err.message) ? String(err.message).replace(/<[^>]*>/g, '') : 'Sorry, we could not apply the discount.';
      };
      try {
        var d = wp.data.dispatch('wc/store/cart');
        if (d && d.applyCoupon) { d.applyCoupon(COUPON).then(done).catch(fail); return; }
      } catch (e2) {}
      fail();
    }
  });

  // Leaving through the page: "Return to store" and the Fika logo
  document.addEventListener('click', function (e) {
    if (busy) return;
    var a = e.target.closest ? e.target.closest('.fika-back, .fika-shopbar a') : null;
    if (!a) return;
    if (open(a.getAttribute('href') || '/')) e.preventDefault();
  }, true);

  // Desktop exit intent: the mouse leaves through the top of the window (towards the tabs / address bar)
  var armedAt = Date.now() + 2500;
  document.addEventListener('mouseout', function (e) {
    if (e.relatedTarget) return;
    if (e.clientY > 12) return;
    if (Date.now() < armedAt) return;
    open(null);
  });

  // Phones: the Back button
  try {
    if (!history.state || !history.state.fikaExit) history.pushState({ fikaExit: 1 }, '', location.href);
    window.addEventListener('popstate', function () {
      if (busy) return;
      if (!open('__back__')) { busy = true; history.back(); }
    });
  } catch (e) {}
})();
</script>
FIKA_EXIT;
}, 100 );
