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
		var bats = [[24, 14, 0, 1], [36, 19, 5, .7], [20, 23, 9, .55], [30, 17, 13, .85]]; // below the header row
		var html = web().replace('hw-web', 'hw-web l') + web().replace('hw-web', 'hw-web r') + '<span class="hw-spider"><i></i><b></b></span>';
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

	/* boo:start - each mouse click, the pumpkin glows: a soft orange ring and a few embers drifting up */
	(function () {
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
		document.addEventListener('pointerdown', function (e) {
			if (e.pointerType !== 'mouse' || e.button !== 0) { return; }
			var b = document.createElement('span');
			b.className = 'hw-boo';
			b.setAttribute('aria-hidden', 'true');
			// the cursor's point is the stem (3, 2): the glow comes from the middle of the pumpkin
			b.style.left = (e.clientX + 17) + 'px';
			b.style.top = (e.clientY + 20) + 'px';
			var html = '';
			for (var k = 0; k < 4; k++) {
				html += '<i style="--x:' + Math.round(Math.random() * 36 - 18) + 'px;--y:' + Math.round(-26 - Math.random() * 20) + 'px;--d:' + (k * 45) + 'ms"></i>';
			}
			b.innerHTML = html;
			document.body.appendChild(b);
			setTimeout(function () { if (b.parentNode) { b.parentNode.removeChild(b); } }, 900);
		}, { passive: true });
	})();
	/* boo:end */
})();
