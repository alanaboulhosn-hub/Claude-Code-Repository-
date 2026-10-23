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
