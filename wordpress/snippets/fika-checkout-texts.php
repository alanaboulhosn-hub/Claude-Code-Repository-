<?php
/**
 * Fika: "Fika inbox" at checkout. Real messages customers sent the shop after their delivery (WhatsApp, October 2026,
 * screenshots from the owner, copied word for word with their own spelling and emoji), played one after another like
 * a chat: "a customer is typing...", the message, then the pink heart the shop reacted with.
 * - Where it sits: on a computer (two-column checkout, .is-large) at the top of the right-hand column, staying in view
 *   (sticky) while the customer fills in the form, the order summary scrolling by under it (under the summary it would only
 *   show in the last third of the form); on a phone just under the Place order button. One
 *   fixed height (the tallest message), messages at the bottom like a chat, so nothing on the page moves.
 * - Phones also get a slim message that slides up from the bottom now and then (short messages only, about 4.5 s every
 *   18 s): never while a field is being typed in, never while the card or the Place order button is on screen, and
 *   not again this visit once closed.
 * - The message that fits the bag comes first: BUBS Wild Berry Pomegranate (product 108) in the bag -> the Wild Berry
 *   message; sweet and sour candies (or the Sweet & Sour Mix) -> "loved both the sweet and the sour".
 * - The Wild Berry message is left out while BUBS Wild Berry Pomegranate is out of stock, and comes back with it.
 * - "N orders delivered since August": Completed orders, counted live (cached 6 hours).
 * - Plays by itself from the moment it is on screen: the next customer's messages start 1 second after the heart has
 *   popped in. Tap or the arrow skips ahead; keyboard focus on it pauses it. With reduced motion: no typing or movement,
 *   a new message every 4 seconds.
 * Installed with the Code Snippets plugin. Source: wordpress/snippets/fika-checkout-texts.php
 */

if ( ! function_exists( 'fika_ct_messages' ) ) {
	// each group: one customer's messages in a row [ text, time ], as they sent them
	function fika_ct_messages() {
		return array(
			'sleep'    => array( array( 'Im obsessed with them', '17:05' ), array( 'I went to sleep yesterday thinking of them im not joking', '17:06' ), array( 'Its so ridiculous but i cant', '17:06' ) ),
			// one long message: its two paragraphs as two bubbles, without the closing "Thank you so much! 🥰"
			'wild'     => array( array( 'The candies are amazing! 😍 I’ve been waiting so long to try them, and I’m so happy I finally found them in Lebanon. I loved them all, but the Bub Wild Berry... OMG, I’m officially addicted! 😂❤️', '19:26' ), array( 'My diet is officially ruined now 😂 And I honestly didn’t expect the serving to be this big the quantity is amazing!', '19:26' ) ),
			'ten'      => array( array( 'sooooo sooo goood i love them', '20:04' ), array( '10/10', '20:04' ) ),
			'perfect'  => array( array( "Helloo💕\nEverything was perfect from the moment i placed my order til i received it the service was great! As for the candies what can i say 😍 they taste amazing i loved both the sweet and the sour😍 thank you so much 🥰", '19:05' ) ),
			'again'    => array( array( 'hiii they were really goodd definitely will be ordering again', '19:12' ) ),
			'obsessed' => array( array( 'sooooooooo good', '17:54' ), array( 'obsessed', '17:54' ) ),
			'loved'    => array( array( 'Hii 💗 I absolutely loved them! The candies were delicious and everything went perfectly from ordering to delivery 🥰🍬 Thank you so much!', '18:13' ) ),
			'yummy'    => array( array( 'Hii yess they were soo yummy and the whole process was very smooth thank youu', '18:14' ) ),
			'delivery' => array( array( 'hello everything was perfect, the delivery time and the experience was great thank you ❤️', '18:10' ) ),
			'service'  => array( array( "The candy is sooo good thank you so much for checking and the service was great as well\nGoodluck 💗", '18:44' ) ),
			'wanting'  => array( array( "hii\nits been a while that ive been wanting to try them\nfav candiess so far soo good and evrthg was smooth", '18:10' ) ),
			'themm'    => array( array( 'I loved themm', '18:26' ), array( 'Theyre so good', '18:27' ) ),
			'uu'       => array( array( 'Hii it was so good🤍 thank uu', '18:58' ) ),
			'much'     => array( array( 'Love them so much😍 thank you', '18:01' ) ),
			'great'    => array( array( 'Hi yes they were great', '18:06' ) ),
		);
	}
	// orders delivered so far (Completed), for the line under the messages
	function fika_ct_delivered() {
		$n = get_transient( 'fika_ct_delivered' );
		if ( false === $n ) {
			$n = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'completed' ) : 0;
			set_transient( 'fika_ct_delivered', $n, 6 * HOUR_IN_SECONDS );
		}
		return (int) $n;
	}
	// the groups in the order they play, the one that fits this bag first
	function fika_ct_groups() {
		$all   = fika_ct_messages();
		$first = array();
		$sweet = false;
		$sour  = false;
		$wild  = false;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				$pid = (int) $item['product_id'];
				if ( 108 === $pid ) {
					$wild = true;
				}
				if ( 94 === $pid || has_term( 'sour', 'product_cat', $pid ) ) {
					$sour = true;
				}
				if ( 94 === $pid || has_term( 'sweet', 'product_cat', $pid ) ) {
					$sweet = true;
				}
			}
		}
		$tags = array();
		if ( $wild ) {
			$first[]        = 'wild';
			$tags['wild']   = 'About a sweet in your bag: BUBS Wild Berry';
		}
		if ( $sweet && $sour ) {
			$first[]          = 'perfect';
			$tags['perfect']  = 'Sweet and sour, like your bag';
		}
		// the Wild Berry message only while that sweet can be ordered
		$wb = function_exists( 'wc_get_product' ) ? wc_get_product( 108 ) : null;
		if ( ! $wb || ! $wb->is_in_stock() || 'publish' !== $wb->get_status() ) {
			unset( $all['wild'] );
			$first = array_diff( $first, array( 'wild' ) );
		}
		$order = array_unique( array_merge( $first, array_keys( $all ) ) );
		$out   = array();
		foreach ( $order as $k ) {
			$out[] = array( 'm' => $all[ $k ], 'tag' => isset( $tags[ $k ] ) ? $tags[ $k ] : '' );
		}
		return $out;
	}
}

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	$data = array( 'groups' => fika_ct_groups(), 'delivered' => fika_ct_delivered() );
	?>
<style id="fika-texts-css">
.fika-texts { margin: 18px 0 4px; border-radius: 18px; overflow: hidden; border: 1px solid #f3d3e1; background: #fdeaf2; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
.fika-texts .ft-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border-bottom: 1px solid #f3d3e1; }
.fika-texts .ft-av { flex: none; width: 36px; height: 36px; border-radius: 50%; background: #fdeaf2; display: grid; place-items: center; }
.fika-texts .ft-av svg { width: 26px; height: 26px; transform: scaleX(-1); }
.fika-texts .ft-who { flex: 1; min-width: 0; line-height: 1.25; }
.fika-texts .ft-who b { display: block; font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400; font-size: 19px; letter-spacing: .03em; color: #004aad; }
.fika-texts .ft-who span { display: block; font-size: 12.5px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fika-texts .ft-who span.is-typing { color: #1f9d55; }
.fika-texts .ft-n { font-size: 12px; color: #8a96b3; font-variant-numeric: tabular-nums; }
.fika-texts .ft-next { flex: none; width: 32px; height: 32px; border-radius: 50%; border: 1px solid #f3d3e1; background: #fff; color: #004aad; font: 600 18px/1 Arial, sans-serif; cursor: pointer; display: grid; place-items: center; padding: 0; }
.fika-texts .ft-next:hover { background: #fdeaf2; }
.fika-texts .ft-next:focus-visible, .fika-texts .ft-body:focus-visible { outline: 2px solid #004aad; outline-offset: 2px; }
.fika-texts .ft-body { position: relative; height: 150px; overflow: hidden; cursor: pointer; }
.fika-texts .ft-thread { position: absolute; left: 0; right: 0; bottom: 0; padding: 12px 12px 14px; display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.fika-texts .ft-measure { visibility: hidden; bottom: auto; top: 0; pointer-events: none; }
.fika-texts .ft-tag { align-self: center; margin-bottom: 4px; padding: 3px 10px; border-radius: 999px; background: #fff4d9; color: #8a5a00; font-size: 12px; font-weight: 600; }
.fika-texts .ft-b { position: relative; max-width: 88%; padding: 6px 9px 7px; border-radius: 10px; background: #fff; color: #111b21; font-size: 14.5px; line-height: 1.38; white-space: pre-line; overflow-wrap: anywhere; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); }
.fika-texts .ft-b.first { border-top-left-radius: 0; }
.fika-texts .ft-b.first::before { content: ''; position: absolute; left: -7px; top: 0; border-top: 9px solid #fff; border-left: 8px solid transparent; }
.fika-texts .ft-b time { float: right; margin: 6px 0 -4px 10px; font-size: 11px; color: #667781; }
.fika-texts .ft-heart { margin: -2px 0 0 8px; padding: 1px 6px; border-radius: 999px; background: #fff; box-shadow: 0 1px 2px rgba(11, 20, 26, .15); font-size: 13px; line-height: 20px; }
.fika-texts .ft-dots { padding: 11px 12px; border-radius: 10px; border-top-left-radius: 0; background: #fff; box-shadow: 0 1px .5px rgba(11, 20, 26, .13); display: flex; gap: 4px; }
.fika-texts .ft-dots i { width: 7px; height: 7px; border-radius: 50%; background: #9aa6b3; animation: ftDot 1.2s infinite ease-in-out; }
.fika-texts .ft-dots i:nth-child(2) { animation-delay: .15s; } .fika-texts .ft-dots i:nth-child(3) { animation-delay: .3s; }
@keyframes ftDot { 0%, 60%, 100% { transform: translateY(0); opacity: .45; } 30% { transform: translateY(-4px); opacity: 1; } }
.fika-texts .ft-in { animation: ftIn .32s cubic-bezier(.2, 1.3, .4, 1) both; }
@keyframes ftIn { from { opacity: 0; transform: translateY(8px) scale(.96); } to { opacity: 1; transform: none; } }
.fika-texts .ft-out { transition: opacity .25s ease; opacity: 0; }
.fika-texts .ft-foot { padding: 8px 12px 9px; background: #fff; border-top: 1px solid #f3d3e1; font-size: 12.5px; color: #4a5878; display: flex; gap: 6px; align-items: center; }
.fika-texts .ft-foot b { color: #004aad; }
.fika-texts.ft-side { position: sticky; top: 24px; z-index: 5; margin: 0 0 18px; box-shadow: 0 8px 24px rgba(0, 74, 173, .10); }
.wc-block-components-sidebar-layout.is-large { align-items: stretch; }
.wc-block-components-sidebar-layout.is-large > .wc-block-checkout__sidebar { align-self: stretch; }
/* phones: the slim message from the bottom */
.fika-toast { position: fixed; left: max(12px, env(safe-area-inset-left, 0px)); right: max(12px, env(safe-area-inset-right, 0px)); bottom: calc(12px + env(safe-area-inset-bottom, 0px)); z-index: 9990;
  display: flex; align-items: center; gap: 10px; padding: 9px 10px 9px 9px; border-radius: 16px; background: #fff; border: 1px solid #f3d3e1; box-shadow: 0 8px 28px rgba(0, 74, 173, .18);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; transform: translateY(140%); opacity: 0; transition: transform .35s cubic-bezier(.2, 1.2, .4, 1), opacity .25s ease; pointer-events: none; }
.fika-toast.on { transform: none; opacity: 1; pointer-events: auto; }
.fika-toast .ft-av { flex: none; width: 34px; height: 34px; border-radius: 50%; background: #fdeaf2; display: grid; place-items: center; }
.fika-toast .ft-av svg { width: 24px; height: 24px; transform: scaleX(-1); }
.fika-toast .ft-tx { flex: 1; min-width: 0; line-height: 1.3; }
.fika-toast .ft-tx b { display: block; font-size: 12px; font-weight: 600; color: #004aad; }
.fika-toast .ft-tx b span { font-weight: 400; color: #667781; }
.fika-toast .ft-tx b span.is-typing { color: #1f9d55; }
.fika-toast .ft-tx p { margin: 2px 0 0; font-size: 14px; color: #111b21; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.fika-toast .ft-x { flex: none; width: 28px; height: 28px; border: 0; border-radius: 50%; background: #f4f6f9; color: #667781; font: 400 18px/1 Arial, sans-serif; cursor: pointer; padding: 0; }
@media (prefers-reduced-motion: reduce) { .fika-texts .ft-in { animation: none; } .fika-texts .ft-dots i { animation: none; } .fika-toast { transition: none; } }
</style>
<script id="fika-texts-js">
(function () {
	var D = <?php echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
	if (!D.groups || !D.groups.length) { return; }
	var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
	var FISH = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 38 L90 22 C85 40 85 62 90 79 L62 64 Z" fill="#e3241f" stroke="#a3160f" stroke-width="3" stroke-linejoin="round"/><path d="M8 52 C16 32 44 25 66 38 C70 46 70 58 66 64 C44 78 16 72 8 52 Z" fill="#ff6a3d" stroke="#a3160f" stroke-width="3" stroke-linejoin="round"/><circle cx="24" cy="46" r="5.2" fill="#fff"/><circle cx="22.5" cy="46.5" r="2.8" fill="#1b2a4a"/></svg>';
	var SUB = 'Real texts from customers';
	var box, body, thread, sub, count, cur = -1, timer = null, step = 0, hold = false, seen = false;

	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = text; } return e; }
	function later(fn, ms) { clearTimeout(timer); timer = setTimeout(fn, ms); }
	// the card keeps one height, the tallest message's, so nothing on the page moves when messages change
	function bubbleEl(m, first) { var b = el('div', 'ft-b' + (first ? ' first' : ''), m[0]); b.appendChild(el('time', '', m[1])); return b; }
	function measure() {
		if (!body || !body.isConnected) { return; }
		var probe = el('div', 'ft-thread ft-measure'), max = 0;
		body.appendChild(probe);
		D.groups.forEach(function (g) {
			probe.innerHTML = '';
			if (g.tag) { probe.appendChild(el('span', 'ft-tag', g.tag)); }
			g.m.forEach(function (m, k) { probe.appendChild(bubbleEl(m, k === 0)); });
			probe.appendChild(el('span', 'ft-heart', '\ud83d\udc97'));
			max = Math.max(max, probe.offsetHeight);
		});
		probe.remove();
		if (max > 0) { body.style.height = max + 'px'; }
	}
	function fit() {}

	function build() {
		box = el('section', 'fika-texts');
		box.setAttribute('aria-label', 'Messages from Fika customers');
		var head = el('div', 'ft-head');
		var av = el('span', 'ft-av'); av.innerHTML = FISH; head.appendChild(av);
		var who = el('div', 'ft-who'); who.appendChild(el('b', '', 'Fika inbox')); sub = el('span', '', SUB); who.appendChild(sub); head.appendChild(who);
		count = el('span', 'ft-n'); head.appendChild(count);
		var nx = el('button', 'ft-next', '›'); nx.type = 'button'; nx.setAttribute('aria-label', 'Next message'); head.appendChild(nx);
		box.appendChild(head);
		body = el('div', 'ft-body'); body.tabIndex = 0; body.setAttribute('aria-label', 'Customer message. Tap for the next one.');
		thread = el('div', 'ft-thread'); thread.setAttribute('aria-live', 'off'); body.appendChild(thread); box.appendChild(body);
		if (D.delivered > 0) {
			var foot = el('div', 'ft-foot');
			foot.appendChild(el('span', '', '✓'));
			var t = el('span'); var b = el('b', '', D.delivered.toLocaleString('en-US') + ' orders'); t.appendChild(b); t.appendChild(document.createTextNode(' delivered since August')); foot.appendChild(t);
			box.appendChild(foot);
		}
		nx.addEventListener('click', function () { next(true); });
		body.addEventListener('click', function () { next(true); });
		body.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); next(true); } });
		box.addEventListener('focusin', function (e) { try { hold = e.target.matches(':focus-visible'); } catch (x) { hold = false; } });
		box.addEventListener('focusout', function () { hold = false; });
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting && !seen) { seen = true; next(); } }); }, { threshold: .4 }).observe(box);
		} else { seen = true; next(); }
	}

	// one customer's messages: typing, then each bubble, then the heart
	function show(g, manual) {
		step++;
		var my = step;
		count.textContent = (cur + 1) + '/' + D.groups.length;
		thread.innerHTML = '';
		if (g.tag) { thread.appendChild(el('span', 'ft-tag ft-in', g.tag)); }
		var k = 0;
		function bubble() {
			if (my !== step) { return; }
			var dots = thread.querySelector('.ft-dots'); if (dots) { dots.remove(); }
			var m = g.m[k];
			var b = bubbleEl(m, k === 0); b.classList.add('ft-in');
			thread.appendChild(b);
			k++;
			fit();
			if (k < g.m.length) { setTimeout(bubble, reduce ? 0 : 650); return; }
			setTimeout(function () {
				if (my !== step) { return; }
				thread.appendChild(el('span', 'ft-heart ft-in', '💗'));
				sub.textContent = SUB; sub.className = '';
				fit();
				// the next customer 1 second after the heart has popped in (its animation takes .32 s)
				later(next, reduce ? 4000 : 1320);
			}, reduce ? 0 : 420);
		}
		if (reduce || manual) { sub.textContent = SUB; sub.className = ''; bubble(); return; }
		sub.textContent = 'a customer is typing…'; sub.className = 'is-typing';
		var d = el('div', 'ft-dots ft-in'); d.innerHTML = '<i></i><i></i><i></i>'; thread.appendChild(d); fit();
		setTimeout(bubble, 1100);
	}
	function next(manual) {
		clearTimeout(timer); // a tap on the arrow replaces the switch that was due
		if (!manual && (hold || document.hidden)) { later(next, 1500); return; }
		cur = (cur + 1) % D.groups.length;
		count.textContent = (cur + 1) + '/' + D.groups.length; // straight away, so a tap during the fade counts from what is shown
		if (thread.children.length && !reduce) {
			thread.classList.add('ft-out');
			var my = ++step;
			setTimeout(function () { if (my !== step) { return; } thread.classList.remove('ft-out'); show(D.groups[cur], manual); }, 260);
		} else { show(D.groups[cur], manual); }
	}

	// just under the Place order button (the checkout draws itself after the page loads, and redraws)
	// computer (two columns): the right-hand column, staying in view; phone: just under the Place order button
	function place() {
		var row = document.querySelector('.wc-block-checkout__actions_row');
		if (!row) { return; }
		if (!box) { build(); }
		var lay = document.querySelector('.wc-block-components-sidebar-layout');
		var side = lay ? lay.querySelector('.wc-block-checkout__sidebar') : null;
		if (side ? lay.classList.contains('is-large') : false) {
			if (box.parentNode !== side || box.previousElementSibling) { side.insertBefore(box, side.firstChild); box.classList.add('ft-side'); measure(); }
		} else if (box.previousElementSibling !== row) {
			row.parentNode.insertBefore(box, row.nextSibling); box.classList.remove('ft-side'); measure();
		}
		toastSetup();
	}

	// phones: a short message slides up from the bottom now and then, while the customer goes through the form
	var toast = null, tIdx = -1, tShort = [], tTimer = null, inView = { card: false, button: false };
	function closed() { try { return sessionStorage.getItem('fikaToastOff') === '1'; } catch (e) { return false; } }
	function typing() { var a = document.activeElement; return a ? /^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName) : false; }
	function narrow() { return !box.classList.contains('ft-side'); }
	function hideToast() { if (toast) { toast.classList.remove('on'); } }
	function toastSetup() {
		if (toast || closed()) { return; }
		tShort = D.groups.filter(function (g) { return g.m.reduce(function (n, x) { return n + x[0].length; }, 0) <= 90; });
		if (!tShort.length) { return; }
		toast = el('div', 'fika-toast'); toast.setAttribute('role', 'status'); toast.setAttribute('aria-live', 'off');
		var av = el('span', 'ft-av'); av.innerHTML = FISH; toast.appendChild(av);
		var tx = el('div', 'ft-tx'); var hb = el('b', '', 'Fika inbox '); var hs = el('span'); hb.appendChild(hs); tx.appendChild(hb); var tp = el('p'); tx.appendChild(tp); toast.appendChild(tx);
		var x = el('button', 'ft-x', '\u00d7'); x.type = 'button'; x.setAttribute('aria-label', 'Close'); toast.appendChild(x);
		x.addEventListener('click', function () { hideToast(); clearTimeout(tTimer); try { sessionStorage.setItem('fikaToastOff', '1'); } catch (e) {} });
		document.body.appendChild(toast);
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (en) { en.forEach(function (e) { if (e.target === box) { inView.card = e.isIntersecting; } else { inView.button = e.isIntersecting; } }); if (inView.card || inView.button) { hideToast(); } });
			io.observe(box); var r = document.querySelector('.wc-block-checkout__actions_row'); if (r) { io.observe(r); }
		}
		document.addEventListener('focusin', function () { if (typing()) { hideToast(); } });
		function round() {
			clearTimeout(tTimer);
			if (closed()) { return; }
			if (!narrow() || typing() || inView.card || inView.button || document.hidden) { tTimer = setTimeout(round, 4000); return; }
			tIdx = (tIdx + 1) % tShort.length;
			var g = tShort[tIdx];
			hs.textContent = '\u00b7 a customer is typing\u2026'; hs.className = 'is-typing'; tp.textContent = '\u2026';
			toast.classList.add('on');
			tTimer = setTimeout(function () {
				hs.textContent = '\u00b7 ' + g.m[0][1]; hs.className = '';
				tp.textContent = g.m.map(function (m) { return m[0]; }).join(' \u00b7 ') + ' \ud83d\udc97';
				tTimer = setTimeout(function () { hideToast(); tTimer = setTimeout(round, 18000); }, 4500);
			}, reduce ? 0 : 1100);
		}
		tTimer = setTimeout(round, 8000);
	}
	var rz = null;
	window.addEventListener('resize', function () { clearTimeout(rz); rz = setTimeout(measure, 200); });
	if (document.fonts && document.fonts.ready) { document.fonts.ready.then(measure); }
	var t = null;
	new MutationObserver(function () { clearTimeout(t); t = setTimeout(place, 150); }).observe(document.body, { childList: true, subtree: true });
	place();
})();
</script>
	<?php
}, 56 );
