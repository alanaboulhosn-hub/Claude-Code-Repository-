<?php
/**
 * Fika: "Swim to your free kilo" — the loyalty tracker for signed-in customers.
 * - A cartoon Swedish fish swims along a water lane towards the 8 kg mark, as far as the customer's
 *   delivered kilos (Completed orders, from snippet 10's fika_customer_totals()). Orders still on their way
 *   show as a dotted stretch ahead of the fish.
 * - Every 8 kg delivered earns a personal one-time code worth 1 kg of sweets ($25 off the candies, not
 *   delivery), locked to the customer's email. The code is created automatically when an order completes.
 * - Shown on the home page (above the shop) and on the My account dashboard; never to visitors who are not
 *   signed in. At checkout a "Use my free kilo" button applies the code.
 * - Shop managers can preview any state on the home page with ?fika_fish=5.2 (kg) or ?fika_fish=8.
 * Needs snippet 10 (Fika accounts). Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-loyalty.php
 */

if ( ! defined( 'FIKA_SWIM_GOAL' ) ) {
	define( 'FIKA_SWIM_GOAL', 8000 );   // grams delivered per free kilo
}
if ( ! defined( 'FIKA_FREE_KG_VALUE' ) ) {
	define( 'FIKA_FREE_KG_VALUE', 25 ); // 1 kg = 10 x 100 g at $2.50 (or 2 Ready Mix bags at $12.50)
}

if ( ! function_exists( 'fika_swim_rewards' ) ) {
	// Issue any free-kilo codes the customer has earned; returns [ [code, used], ... ]
	function fika_swim_rewards( $user_id, $grams = null ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! class_exists( 'WC_Coupon' ) || ! function_exists( 'fika_customer_totals' ) ) {
			return array();
		}
		if ( null === $grams ) {
			$t     = fika_customer_totals( $user_id );
			$grams = (int) $t['grams'];
		}
		$codes  = get_user_meta( $user_id, 'fika_rewards', true );
		$codes  = is_array( $codes ) ? $codes : array();
		$earned = (int) floor( $grams / FIKA_SWIM_GOAL );
		$user   = get_userdata( $user_id );
		while ( count( $codes ) < $earned && $user ) {
			$code = 'FREEKG-' . strtoupper( wp_generate_password( 6, false, false ) );
			if ( wc_get_coupon_id_by_code( $code ) ) {
				continue;
			}
			$c = new WC_Coupon();
			$c->set_code( $code );
			$c->set_discount_type( 'fixed_cart' );
			$c->set_amount( FIKA_FREE_KG_VALUE );
			$c->set_individual_use( true );
			$c->set_usage_limit( 1 );
			$c->set_usage_limit_per_user( 1 );
			$c->set_email_restrictions( array( $user->user_email ) );
			$c->set_description( sprintf( 'Fika free kilo #%d for %s (user %d), earned at %s kg delivered.', count( $codes ) + 1, $user->user_email, $user_id, number_format( ( count( $codes ) + 1 ) * FIKA_SWIM_GOAL / 1000, 0 ) ) );
			$c->update_meta_data( '_fika_reward_user', $user_id );
			$c->save();
			$codes[] = $code;
			update_user_meta( $user_id, 'fika_rewards', $codes );
		}
		$out = array();
		foreach ( $codes as $code ) {
			$c     = new WC_Coupon( $code );
			$out[] = array( $code, $c->get_id() ? ( $c->get_usage_count() >= 1 ) : true );
		}
		return $out;
	}
}

// Check for a new free kilo whenever an order changes status (after snippet 10 refreshed the totals)
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( $order && $order->get_customer_id() ) {
		fika_swim_rewards( $order->get_customer_id() );
	}
}, 30, 4 );

if ( ! function_exists( 'fika_swim_state' ) ) {
	function fika_swim_state() {
		$state = array( 'grams' => 0, 'pending' => 0, 'code' => '', 'lap' => 1, 'name' => '' );
		if ( ! is_user_logged_in() || ! function_exists( 'fika_customer_totals' ) ) {
			return null;
		}
		$uid           = get_current_user_id();
		$u             = wp_get_current_user();
		$state['name'] = $u->first_name ? $u->first_name : $u->display_name;
		$t             = fika_customer_totals( $uid );
		$grams         = (int) $t['grams'];
		$rewards       = fika_swim_rewards( $uid, $grams );
		foreach ( $rewards as $r ) {
			if ( ! $r[1] ) {
				$state['code'] = $r[0];
				break;
			}
		}
		// orders on their way (count once delivered)
		$pending = 0;
		foreach ( wc_get_orders( array( 'customer_id' => $uid, 'status' => array( 'wc-processing', 'wc-on-hold' ), 'limit' => 20, 'type' => 'shop_order' ) ) as $o ) {
			$pending += fika_order_grams( $o );
		}
		$preview = isset( $_GET['fika_fish'] ) && current_user_can( 'manage_woocommerce' );
		if ( $preview ) {
			$grams   = (int) round( floatval( wp_unslash( $_GET['fika_fish'] ) ) * 1000 );
			$pending = isset( $_GET['fika_pending'] ) ? (int) round( floatval( wp_unslash( $_GET['fika_pending'] ) ) * 1000 ) : 0;
			$state['code'] = $grams >= FIKA_SWIM_GOAL ? 'FREEKG-PREVIEW' : '';
		}
		$used           = $preview ? 0 : count( $rewards ) - ( $state['code'] ? 1 : 0 );
		$state['lap']   = $used + 1;
		// progress in the current lap; an unused code keeps the fish at the finish
		$lap_grams        = $state['code'] ? FIKA_SWIM_GOAL : max( 0, $grams - $used * FIKA_SWIM_GOAL );
		$state['grams']   = min( FIKA_SWIM_GOAL, $lap_grams );
		$state['total']   = $grams;
		$state['pending']     = $state['code'] ? 0 : min( FIKA_SWIM_GOAL - $state['grams'], $pending ); // the stripe stops at the goal
		$state['pending_all'] = $state['code'] ? 0 : $pending;
		return $state;
	}
}

if ( ! function_exists( 'fika_swim_html' ) ) {
	function fika_swim_html( $where ) {
		$s = fika_swim_state();
		if ( ! $s ) {
			return '';
		}
		$goal_kg = FIKA_SWIM_GOAL / 1000;
		$kg      = $s['grams'] / 1000;
		$pct     = round( 100 * $s['grams'] / FIKA_SWIM_GOAL, 2 );
		$ppct    = round( 100 * $s['pending'] / FIKA_SWIM_GOAL, 2 );
		$left    = max( 0, FIKA_SWIM_GOAL - $s['grams'] ) / 1000;
		$fmt     = function ( $v ) {
			return rtrim( rtrim( number_format( $v, 1, '.', '' ), '0' ), '.' );
		};
		ob_start();
		?>
<section class="fika-swim fika-swim-<?php echo esc_attr( $where ); ?><?php echo $s['code'] ? ' is-done' : ''; ?>" data-pct="<?php echo esc_attr( $pct ); ?>" aria-label="Your progress to a free kilo">
	<div class="fs-head">
		<div class="fs-intro">
			<p class="fs-kicker"><?php echo $s['lap'] > 1 ? 'Lap ' . (int) $s['lap'] . ' &middot; ' : ''; ?>Fika rewards</p>
			<h2><?php echo $s['code'] ? 'You made it! Your next kilo is on us' : 'Swim to your free kilo'; ?></h2>
			<p class="fs-sub">
				<?php if ( $s['code'] ) : ?>
					<?php echo esc_html( $s['name'] ? $s['name'] . ', you' : 'You' ); ?> reached <?php echo (int) $goal_kg; ?> kg of Fika sweets. Here is 1 kg on the house.
				<?php elseif ( $s['grams'] <= 0 ) : ?>
					Every <?php echo (int) $goal_kg; ?> kg delivered earns you a free kilo. Your first order starts the swim<?php echo $s['name'] ? ', ' . esc_html( $s['name'] ) : ''; ?>!
				<?php else : ?>
					<b><?php echo esc_html( $fmt( $left ) ); ?> kg</b> to go<?php echo $s['name'] ? ', ' . esc_html( $s['name'] ) : ''; ?>. At <?php echo (int) $goal_kg; ?> kg your next kilo is free.
				<?php endif; ?>
				<?php if ( $s['pending'] > 0 ) : ?>
					<span class="fs-pendnote">+<?php echo esc_html( $fmt( $s['pending_all'] / 1000 ) ); ?> kg on its way, counted once delivered.</span>
				<?php endif; ?>
			</p>
		</div>
		<div class="fs-big" aria-hidden="true"><b data-kg="<?php echo esc_attr( $kg ); ?>"><?php echo esc_html( $fmt( $kg ) ); ?></b><span>/ <?php echo (int) $goal_kg; ?> kg</span></div>
	</div>
	<div class="fs-track" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $goal_kg; ?>" aria-valuenow="<?php echo esc_attr( $kg ); ?>" aria-valuetext="<?php echo esc_attr( $fmt( $kg ) . ' of ' . $goal_kg . ' kg' ); ?>">
		<div class="fs-lane">
			<div class="fs-pend" style="--pp: <?php echo esc_attr( $ppct ); ?>"></div>
			<div class="fs-water">
				<svg class="fs-wave" viewBox="0 0 120 12" preserveAspectRatio="none" aria-hidden="true"><path d="M0 6 Q15 0 30 6 T60 6 T90 6 T120 6 V12 H0 Z"/></svg>
			</div>
			<div class="fs-fish" aria-hidden="true">
				<svg viewBox="0 0 100 100"><defs><linearGradient id="fsFishG" x1="0" x2="1"><stop offset="0" stop-color="#ff6a3d"/><stop offset="1" stop-color="#e3241f"/></linearGradient></defs>
					<g class="fs-tail"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/></g>
					<path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="url(#fsFishG)" stroke="#a3160f" stroke-width="2.6" stroke-linejoin="round"/>
					<path d="M38 40 Q42 50 38 62 M48 38 Q52 50 48 64" fill="none" stroke="#a3160f" stroke-opacity=".35" stroke-width="1.6"/>
					<ellipse cx="34" cy="36" rx="9" ry="4" transform="rotate(-30 34 36)" fill="#fff" opacity=".55"/>
					<circle cx="24" cy="46" r="5.2" fill="#fff"/><circle class="fs-pupil" cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/>
					<path d="M10 56 Q14 59 18 57" fill="none" stroke="#a3160f" stroke-width="2" stroke-linecap="round"/>
				</svg>
			</div>
		</div>
		<div class="fs-goal" aria-hidden="true">
			<svg viewBox="0 0 48 48"><path d="M10 18h28l-2.4 22H12.4L10 18z" fill="#fff" stroke="#004aad" stroke-width="2.4" stroke-linejoin="round"/><path d="M17 18v-3a7 7 0 0 1 14 0v3" fill="none" stroke="#004aad" stroke-width="2.4" stroke-linecap="round"/><text x="24" y="34" text-anchor="middle" font-family="Bebas Neue, Impact, sans-serif" font-size="11" fill="#004aad">FREE</text></svg>
			<span><?php echo (int) $goal_kg; ?> kg</span>
		</div>
	</div>
	<div class="fs-ticks" aria-hidden="true"><?php for ( $i = 0; $i < $goal_kg; $i++ ) : ?><i style="--i: <?php echo (int) $i; ?>"><?php echo (int) $i; ?></i><?php endfor; ?></div>
	<?php if ( $s['code'] ) : ?>
	<div class="fs-reward">
		<div class="fs-code"><span>Your code</span><b><?php echo esc_html( $s['code'] ); ?></b></div>
		<button type="button" class="fs-copy" data-code="<?php echo esc_attr( $s['code'] ); ?>">Copy code</button>
		<p>Use it at checkout for 1 kg of sweets free (up to $<?php echo (int) FIKA_FREE_KG_VALUE; ?> off your candies; delivery not included). One use, for your account only.</p>
	</div>
	<?php endif; ?>
</section>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'fika_swim_assets' ) ) {
	function fika_swim_assets() {
		?>
<style>
.fika-swim { --fs-blue: #004aad; --fw: 76px; --goal: 74px; position: relative; box-sizing: border-box; background: #fff; border-radius: 26px; padding: 26px 30px 22px; box-shadow: 0 10px 34px rgba(0, 74, 173, .10); color: #1b2a4a; font-family: 'Outfit', 'Open Sans', Arial, sans-serif; overflow: hidden; }
.fika-swim *, .fika-swim *::before { box-sizing: border-box; }
.fika-swim-home { max-width: 1100px; margin: 34px auto 10px; width: calc(100% - 12%); }
.fika-swim-account { margin: 4px 0 22px; padding: 22px 22px 18px; box-shadow: none; background: #f4f8ff; }
.fika-swim .fs-head { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
.fika-swim .fs-kicker { margin: 0 0 2px; font-family: 'Fanwood Text', Georgia, serif; font-variant: small-caps; font-size: 17px; color: #6b7894; }
.fika-swim h2 { margin: 0 0 4px; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: clamp(28px, 3.2vw, 38px); letter-spacing: .02em; line-height: 1; color: var(--fs-blue); }
.fika-swim .fs-sub { margin: 0; font-size: 16px; line-height: 1.45; }
.fika-swim .fs-sub b { color: var(--fs-blue); }
.fika-swim .fs-pendnote { display: block; margin-top: 2px; font-size: 14px; color: #6b7894; }
.fika-swim .fs-big { flex: none; text-align: right; font-family: 'Bebas Neue', Impact, sans-serif; color: var(--fs-blue); line-height: .9; }
.fika-swim .fs-big b { font-weight: 400; font-size: 64px; }
.fika-swim .fs-big span { font-size: 26px; margin-left: 4px; color: #6b7894; }

/* the lane: water fills up to the fish; the fish's nose sits on the delivered kilos */
.fika-swim .fs-track { position: relative; display: flex; align-items: center; gap: 10px; }
.fika-swim .fs-lane { --p: 0; position: relative; flex: 1; height: 62px; border-radius: 999px; background: #fdeaf2;
  background-image: repeating-linear-gradient(90deg, transparent 0 16px, rgba(0, 74, 173, .08) 16px 26px); background-size: 100% 3px; background-repeat: no-repeat; background-position: 0 50%; }
.fika-swim .fs-water { position: absolute; left: 0; top: 0; bottom: 0; width: calc((100% - var(--fw)) * var(--p) / 100 + var(--fw) * .86); min-width: 0; border-radius: 999px;
  background: linear-gradient(180deg, #6fb6ff 0%, #2f86ea 55%, #1c6fd6 100%); overflow: hidden; box-shadow: inset 0 -6px 12px rgba(0, 40, 120, .18); }
.fika-swim .fs-water::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 70%, rgba(255,255,255,.35) 0 3px, transparent 4px), radial-gradient(circle at 55% 40%, rgba(255,255,255,.3) 0 2px, transparent 3px), radial-gradient(circle at 80% 75%, rgba(255,255,255,.3) 0 2.5px, transparent 3.5px); background-size: 90px 62px; animation: fsBub 4s linear infinite; }
.fika-swim .fs-wave { position: absolute; left: 0; top: -2px; width: 200%; height: 12px; fill: rgba(255, 255, 255, .35); animation: fsWave 3s linear infinite; }
.fika-swim .fs-pend { position: absolute; top: 7px; bottom: 7px; left: calc((100% - var(--fw)) * var(--p) / 100 + var(--fw) * .86); width: calc((100% - var(--fw)) * var(--pp) / 100);
  border-radius: 0 999px 999px 0; background: repeating-linear-gradient(135deg, rgba(47, 134, 234, .28) 0 7px, rgba(47, 134, 234, .1) 7px 14px); opacity: 0; transition: opacity .6s ease; }
.fika-swim.is-swum .fs-pend { opacity: 1; }
.fika-swim .fs-fish { position: absolute; top: 50%; left: calc((100% - var(--fw)) * var(--p) / 100); width: var(--fw); height: var(--fw); margin-top: calc(var(--fw) / -2); z-index: 2; pointer-events: none; }
.fika-swim .fs-fish svg { width: 100%; height: 100%; transform: scaleX(-1); filter: drop-shadow(0 3px 3px rgba(0, 40, 120, .25)); animation: fsBob 1.6s ease-in-out infinite; overflow: visible; }
.fika-swim .fs-tail { transform-origin: 64px 51px; animation: fsTail .9s ease-in-out infinite; }
.fika-swim.is-swimming .fs-tail { animation-duration: .28s; }
.fika-swim.is-swimming .fs-fish svg { animation-duration: .7s; }
.fika-swim .fs-bubble { position: absolute; z-index: 1; width: 8px; height: 8px; border-radius: 50%; border: 1.6px solid rgba(255, 255, 255, .9); background: rgba(255, 255, 255, .25); pointer-events: none; animation: fsRise 1.3s ease-out forwards; }
.fika-swim .fs-goal { flex: none; width: var(--goal); display: flex; flex-direction: column; align-items: center; gap: 0; font-family: 'Bebas Neue', Impact, sans-serif; font-size: 18px; color: var(--fs-blue); }
.fika-swim .fs-goal svg { width: 46px; height: 46px; transform-origin: 50% 80%; }
.fika-swim.is-done.is-swum .fs-goal svg { animation: fsGoal 1.4s ease-in-out infinite; }
.fika-swim.is-done.is-swum .fs-fish svg { animation: fsJump 1.4s ease-in-out infinite; }
.fika-swim .fs-ticks { position: relative; height: 18px; margin: 6px calc(var(--goal) + 10px) 0 0; font-size: 12.5px; font-weight: 600; color: #8b97b3; }
.fika-swim .fs-ticks i { position: absolute; top: 0; left: calc((100% - var(--fw)) * var(--i) / 8 + var(--fw) * .86); transform: translateX(-50%); font-style: normal; }
.fika-swim .fs-ticks i::before { content: ''; position: absolute; left: 50%; top: -7px; width: 2px; height: 5px; border-radius: 1px; background: #c9d4ea; }
.fika-swim .fs-reward { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 18px; margin-top: 16px; padding: 16px 18px; border-radius: 18px; background: #fdeaf2; }
.fika-swim .fs-code { display: flex; flex-direction: column; padding: 8px 18px; border: 2px dashed var(--fs-blue); border-radius: 14px; background: #fff; }
.fika-swim .fs-code span { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #6b7894; }
.fika-swim .fs-code b { font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 30px; letter-spacing: .06em; color: var(--fs-blue); line-height: 1.05; }
.fika-swim .fs-copy { height: 46px; padding: 0 24px; border: 0; border-radius: 999px; background: var(--fs-blue); color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fika-swim .fs-copy:hover { background: #003a8a; }
.fika-swim .fs-reward p { flex: 1 1 260px; margin: 0; font-size: 14.5px; line-height: 1.45; }
.fika-swim .fs-confetti { position: absolute; z-index: 3; width: 9px; height: 9px; border-radius: 3px; pointer-events: none; animation: fsConf 1.4s cubic-bezier(.2, .7, .4, 1) forwards; }
@keyframes fsBob { 0%, 100% { transform: scaleX(-1) translateY(-2px) rotate(-2deg); } 50% { transform: scaleX(-1) translateY(3px) rotate(3deg); } }
@keyframes fsTail { 0%, 100% { transform: rotate(-12deg); } 50% { transform: rotate(12deg); } }
@keyframes fsWave { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@keyframes fsBub { from { background-position: 0 0, 0 0, 0 0; } to { background-position: 0 -62px, 0 -62px, 0 -62px; } }
@keyframes fsRise { 0% { opacity: 0; transform: translate(0, 0) scale(.5); } 20% { opacity: 1; } 100% { opacity: 0; transform: translate(-14px, -34px) scale(1.1); } }
@keyframes fsGoal { 0%, 100% { transform: rotate(0) scale(1); } 20% { transform: rotate(-10deg) scale(1.12); } 40% { transform: rotate(8deg) scale(1.12); } 60% { transform: rotate(0) scale(1); } }
@keyframes fsJump { 0%, 60%, 100% { transform: scaleX(-1) translateY(0) rotate(0); } 25% { transform: scaleX(-1) translateY(-16px) rotate(-14deg); } 45% { transform: scaleX(-1) translateY(2px) rotate(6deg); } }
@keyframes fsConf { 0% { opacity: 1; transform: translate(0, 0) rotate(0); } 100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--r)); } }
@media (max-width: 700px) {
  .fika-swim { --fw: 58px; --goal: 52px; padding: 20px 16px 16px; border-radius: 20px; }
  .fika-swim-home { width: calc(100% - 32px); margin-top: 24px; }
  .fika-swim .fs-head { align-items: flex-start; gap: 10px; margin-bottom: 14px; }
  .fika-swim .fs-big b { font-size: 44px; }
  .fika-swim .fs-big span { font-size: 19px; }
  .fika-swim .fs-sub { font-size: 15px; }
  .fika-swim .fs-lane { height: 48px; }
  .fika-swim .fs-goal svg { width: 36px; height: 36px; }
  .fika-swim .fs-goal { font-size: 15px; }
  .fika-swim .fs-ticks { font-size: 11px; }
  .fika-swim .fs-code b { font-size: 25px; }
}
@media (prefers-reduced-motion: reduce) { .fika-swim *, .fika-swim *::after { animation: none !important; } }
</style>
<script>
(function () {
	function run(el) {
		if (el.getAttribute('data-ran')) return;
		el.setAttribute('data-ran', '1');
		var lane = el.querySelector('.fs-lane'), fish = el.querySelector('.fs-fish'), big = el.querySelector('.fs-big b');
		var target = parseFloat(el.getAttribute('data-pct')) || 0;
		var kg = parseFloat(big ? big.getAttribute('data-kg') : 0) || 0;
		var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
		var dur = reduce ? 0 : 900 + 1700 * Math.min(1, target / 100), t0 = null, lastBub = 0;
		function fmt(v) { return (Math.round(v * 10) / 10).toString(); }
		function ease(x) { return 1 - Math.pow(1 - x, 3); }
		function bubble() {
			var r = fish.getBoundingClientRect(), lr = el.getBoundingClientRect();
			var b = document.createElement('i');
			b.className = 'fs-bubble';
			b.style.left = (r.right - lr.left - r.width * 0.12) + 'px';
			b.style.top = (r.top - lr.top + r.height * 0.32) + 'px';
			el.appendChild(b);
			setTimeout(function () { b.remove(); }, 1400);
		}
		function confetti() {
			var g = el.querySelector('.fs-goal').getBoundingClientRect(), lr = el.getBoundingClientRect();
			var cols = ['#ff6fa5', '#ffd23f', '#4aa8ff', '#ff4d4d', '#7ad67a', '#c77dff', '#ff9f43'];
			for (var i = 0; i < 26; i++) {
				var c = document.createElement('i');
				c.className = 'fs-confetti';
				var a = Math.random() * Math.PI * 2, d = 40 + Math.random() * 90;
				c.style.left = (g.left - lr.left + g.width / 2) + 'px';
				c.style.top = (g.top - lr.top + g.height / 3) + 'px';
				c.style.background = cols[i % cols.length];
				c.style.setProperty('--dx', (Math.cos(a) * d) + 'px');
				c.style.setProperty('--dy', (Math.sin(a) * d - 30) + 'px');
				c.style.setProperty('--r', (Math.random() * 540 - 270) + 'deg');
				el.appendChild(c);
				(function (n) { setTimeout(function () { n.remove(); }, 1500); })(c);
			}
		}
		function frame(ts) {
			if (t0 === null) t0 = ts;
			var x = dur ? Math.min(1, (ts - t0) / dur) : 1, e = ease(x);
			lane.style.setProperty('--p', (target * e).toFixed(2));
			if (big) big.textContent = fmt(kg * e);
			if (!reduce && target > 0 && ts - lastBub > 160 && x < 1) { lastBub = ts; bubble(); }
			if (x < 1) { requestAnimationFrame(frame); return; }
			el.classList.remove('is-swimming');
			el.classList.add('is-swum');
			if (el.classList.contains('is-done') && !reduce) confetti();
		}
		el.classList.add('is-swimming');
		setTimeout(function () { requestAnimationFrame(frame); }, 250);
	}
	function init() {
		var els = document.querySelectorAll('.fika-swim');
		els.forEach(function (el) {
			var copy = el.querySelector('.fs-copy');
			if (copy) copy.addEventListener('click', function () {
				var code = copy.getAttribute('data-code');
				function ok() { copy.textContent = 'Copied!'; setTimeout(function () { copy.textContent = 'Copy code'; }, 1800); }
				if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(code).then(ok, ok); else ok();
			});
			if (!window.IntersectionObserver) { run(el); return; }
			var io = new IntersectionObserver(function (en) {
				if (en[0].isIntersecting) { io.disconnect(); run(el); }
			}, { threshold: 0.45 });
			io.observe(el);
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
		<?php
	}
}

// ---------- Home page: the tracker sits just above the shop ----------
add_action( 'wp_footer', function () {
	if ( ! is_front_page() || ! is_user_logged_in() ) {
		return;
	}
	$html = fika_swim_html( 'home' );
	if ( ! $html ) {
		return;
	}
	echo '<template id="fikaSwimTpl">' . $html . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
	?>
<script>
(function () {
	var tpl = document.getElementById('fikaSwimTpl'), shop = document.getElementById('shop');
	if (!tpl || !shop || document.querySelector('.fika-swim-home')) return;
	shop.parentNode.insertBefore(tpl.content.cloneNode(true), shop);
})();
</script>
	<?php
	fika_swim_assets();
}, 25 );

// ---------- My account dashboard: the same tracker above the totals ----------
// (the style and script go in the footer: the account page content is run through texturize, which breaks scripts)
add_action( 'woocommerce_account_dashboard', function () {
	$html = fika_swim_html( 'account' );
	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		$GLOBALS['fika_swim_assets_needed'] = true;
	}
}, 4 );
add_action( 'wp_footer', function () {
	if ( ! empty( $GLOBALS['fika_swim_assets_needed'] ) ) {
		fika_swim_assets();
	}
}, 25 );

// ---------- Checkout: one tap to use an unused free kilo ----------
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! is_user_logged_in() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	$s = fika_swim_state();
	if ( ! $s || ! $s['code'] || 'FREEKG-PREVIEW' === $s['code'] ) {
		return;
	}
	?>
<style>
.fika-freekg { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 18px; max-width: 1100px; margin: 0 auto 22px; padding: 16px 22px; border-radius: 20px; background: #fff; box-shadow: 0 6px 24px rgba(0, 74, 173, .08); border-left: 6px solid #2fb36b; font-family: 'Outfit', Arial, sans-serif; color: #1b2a4a; }
.fika-freekg b { display: block; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 26px; letter-spacing: .02em; color: #004aad; line-height: 1.05; }
.fika-freekg span { font-size: 15px; }
.fika-freekg button { height: 46px; padding: 0 24px; border: 0; border-radius: 999px; background: #004aad; color: #fff; font: 600 14px/1 'Outfit', Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
.fika-freekg button:hover { background: #003a8a; }
.fika-freekg.is-on { border-left-color: #004aad; }
</style>
<script>
(function () {
	var CODE = <?php echo wp_json_encode( $s['code'] ); ?>;
	function applied() {
		try { return (wp.data.select('wc/store/cart').getCartData().coupons || []).some(function (c) { return (c.code || '').toLowerCase() === CODE.toLowerCase(); }); } catch (e) { return false; }
	}
	function mount() {
		var anchor = document.querySelector('.fika-shoptitle');
		if (!anchor || document.querySelector('.fika-freekg')) return;
		var box = document.createElement('div');
		box.className = 'fika-freekg';
		box.innerHTML = '<div><b>Your free kilo is ready</b><span>You swam to 8 kg! Take 1 kg of sweets off this order (up to $<?php echo (int) FIKA_FREE_KG_VALUE; ?>).</span></div><button type="button">Use my free kilo</button>';
		anchor.parentNode.insertBefore(box, anchor.nextSibling);
		var btn = box.querySelector('button');
		function sync() {
			if (applied()) { box.classList.add('is-on'); box.querySelector('b').textContent = 'Free kilo applied'; box.querySelector('span').textContent = '1 kg of sweets is off this order. Enjoy, and keep swimming!'; btn.style.display = 'none'; }
		}
		btn.addEventListener('click', function () {
			var d = window.wp ? wp.data.dispatch('wc/store/cart') : null;
			if (!d || !d.applyCoupon) return;
			btn.disabled = true; btn.textContent = 'Applying…';
			d.applyCoupon(CODE).then(sync).catch(function () { btn.disabled = false; btn.textContent = 'Use my free kilo'; });
		});
		sync();
		if (window.wp ? wp.data : null) wp.data.subscribe(sync);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
	setTimeout(mount, 1500);
})();
</script>
	<?php
}, 30 );

// ---------- Header account menu: show the distance to the free kilo ----------
add_action( 'wp_footer', function () {
	if ( ! is_front_page() || ! is_user_logged_in() ) {
		return;
	}
	$s = fika_swim_state();
	if ( ! $s ) {
		return;
	}
	$left = max( 0, FIKA_SWIM_GOAL - $s['grams'] ) / 1000;
	$text = $s['code'] ? 'Your free kilo is ready!' : rtrim( rtrim( number_format( $left, 1, '.', '' ), '0' ), '.' ) . ' kg to your free kilo';
	?>
<script>
(function () {
	var sm = document.querySelector('.fika-acct .fa-hi small');
	if (sm) sm.textContent = <?php echo wp_json_encode( $text ); ?>;
})();
</script>
	<?php
}, 40 );
