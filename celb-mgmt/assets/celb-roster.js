/* =========================================================================
   CELB MGMT — Talent roster grid + carousel behaviour (vanilla JS)
   - theme colour pickup (filled buttons use the theme's own text colour)
   - grid: category filter, search, empty state, reveal on scroll
   - carousel: arrows, progress, drag to scroll, gentle autoplay
   ========================================================================= */
(function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* Text colour + the first opaque background behind an element. */
	function themeColours(root) {
		var ink = getComputedStyle(root).color;
		root.style.setProperty('--celb-r-ink', ink);
		var node = root.parentElement;
		while (node) {
			var m = /rgba?\(([^)]+)\)/.exec(getComputedStyle(node).backgroundColor || '');
			if (m) {
				var p = m[1].split(',').map(parseFloat);
				if (p.length < 4 || p[3] >= 0.95) {
					root.style.setProperty('--celb-r-page-bg', 'rgb(' + p.slice(0, 3).join(',') + ')');
					return;
				}
			}
			node = node.parentElement;
		}
		var t = /rgba?\(([^)]+)\)/.exec(ink);
		var c = t ? t[1].split(',').map(parseFloat) : [0, 0, 0];
		var lum = (0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]) / 255;
		root.style.setProperty('--celb-r-page-bg', lum > 0.5 ? '#0b0b0c' : '#ffffff');
	}

	/* Fade cards in as they scroll into view (staggered per row). */
	function reveal(root, cards) {
		if (reduce || !('IntersectionObserver' in window)) { return; }
		root.classList.add('celb-r-anim');
		var io = new IntersectionObserver(function (entries) {
			var n = 0;
			entries.forEach(function (e) {
				if (!e.isIntersecting) { return; }
				e.target.style.setProperty('--d', Math.min(n++, 6) * 70 + 'ms');
				e.target.classList.add('is-in');
				io.unobserve(e.target);
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
		cards.forEach(function (c) { io.observe(c); });
		// Safety net: never leave cards hidden.
		setTimeout(function () { cards.forEach(function (c) { c.classList.add('is-in'); }); }, 4000);
	}

	/* ------------------------------------------------------------- Grid */
	function initGrid(root) {
		themeColours(root);
		var cards = Array.prototype.slice.call(root.querySelectorAll('.celb-tc'));
		var pills = Array.prototype.slice.call(root.querySelectorAll('.celb-tg-pills button'));
		var input = root.querySelector('[data-search]');
		var empty = root.querySelector('.celb-tg-empty');
		var live = root.querySelector('.celb-tg-live');
		var filter = 'all';
		var q = '';

		function norm(s) {
			return (s || '').toLowerCase().normalize ? (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '') : (s || '').toLowerCase();
		}
		function apply() {
			var shown = 0;
			cards.forEach(function (c) {
				var cats = (c.getAttribute('data-cat') || '').split(' ');
				var ok = (filter === 'all' || cats.indexOf(filter) !== -1) && (!q || (c.getAttribute('data-search') || '').indexOf(q) !== -1);
				c.classList.toggle('is-hidden', !ok);
				if (ok) { shown++; c.classList.add('is-in'); }
			});
			if (empty) { empty.hidden = shown > 0; }
			if (live) { live.textContent = shown + ' talent'; }
		}
		pills.forEach(function (p) {
			p.addEventListener('click', function () {
				filter = p.getAttribute('data-filter');
				pills.forEach(function (x) {
					var on = x === p;
					x.classList.toggle('is-on', on);
					x.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				apply();
			});
		});
		if (input) {
			var t;
			input.addEventListener('input', function () {
				clearTimeout(t);
				t = setTimeout(function () { q = norm(input.value.trim()); apply(); }, 120);
			});
		}
		var reset = root.querySelector('[data-reset]');
		if (reset) {
			reset.addEventListener('click', function () {
				q = '';
				if (input) { input.value = ''; }
				if (pills[0]) { pills[0].click(); } else { apply(); }
			});
		}
		reveal(root, cards);
	}

	/* --------------------------------------------------------- Carousel */
	function initRail(root) {
		themeColours(root);
		var track = root.querySelector('.celb-rail-track');
		if (!track) { return; }
		var prev = root.querySelector('.celb-rail-btn[data-dir="-1"]');
		var next = root.querySelector('.celb-rail-btn[data-dir="1"]');
		var bar = root.querySelector('.celb-rail-progress');

		function max() { return track.scrollWidth - track.clientWidth; }
		function step() {
			var cell = track.querySelector('.celb-rail-cell');
			if (!cell) { return track.clientWidth; }
			var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
			var w = cell.getBoundingClientRect().width + gap;
			return Math.max(w, Math.floor(track.clientWidth / w) * w);
		}
		function update() {
			var m = max();
			root.classList.toggle('is-static', m <= 2);
			if (prev) { prev.disabled = track.scrollLeft <= 2; }
			if (next) { next.disabled = track.scrollLeft >= m - 2; }
			if (bar && m > 0) {
				var visible = track.clientWidth / track.scrollWidth;
				var x = (track.scrollLeft / m) * (1 - visible);
				bar.style.setProperty('--rail-w', (visible * 100).toFixed(2) + '%');
				bar.style.setProperty('--rail-x', (x / visible * 100).toFixed(2) + '%');
			}
		}
		function go(dir) {
			if (dir > 0 && track.scrollLeft >= max() - 2) {
				track.scrollTo({ left: 0, behavior: reduce ? 'auto' : 'smooth' });
			} else {
				track.scrollBy({ left: dir * step(), behavior: reduce ? 'auto' : 'smooth' });
			}
		}
		[prev, next].forEach(function (b) {
			if (b) { b.addEventListener('click', function () { go(+b.getAttribute('data-dir')); pause(8000); }); }
		});
		track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
		window.addEventListener('resize', update);
		track.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); go(1); pause(8000); }
			if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); pause(8000); }
		});

		// Drag to scroll with a mouse (touch already scrolls natively).
		var drag = null;
		track.addEventListener('pointerdown', function (e) {
			if (e.pointerType !== 'mouse' || e.button !== 0) { return; }
			drag = { x: e.clientX, left: track.scrollLeft, moved: false };
		});
		window.addEventListener('pointermove', function (e) {
			if (!drag) { return; }
			var dx = e.clientX - drag.x;
			if (!drag.moved && Math.abs(dx) > 5) {
				drag.moved = true;
				track.classList.add('is-dragging');
				pause(8000);
			}
			if (drag.moved) { track.scrollLeft = drag.left - dx; }
		});
		window.addEventListener('pointerup', function () {
			if (!drag) { return; }
			var moved = drag.moved;
			drag = null;
			if (moved) {
				track.classList.remove('is-dragging');
				// Snap to the nearest card after a drag.
				var cell = track.querySelector('.celb-rail-cell');
				var s = cell ? cell.getBoundingClientRect().width + (parseFloat(getComputedStyle(track).columnGap) || 0) : step();
				track.scrollTo({ left: Math.round(track.scrollLeft / s) * s, behavior: 'smooth' });
			}
		});
		track.addEventListener('dragstart', function (e) { e.preventDefault(); });

		// Gentle autoplay: only when it overflows, never with reduced motion,
		// paused on hover / focus / touch / hidden tab.
		var timer = null;
		var holdUntil = 0;
		var hover = false;
		function pause(ms) { holdUntil = Date.now() + (ms || 0); }
		function tick() {
			if (hover || document.hidden || Date.now() < holdUntil || max() <= 2) { return; }
			var r = root.getBoundingClientRect();
			if (r.bottom < 0 || r.top > window.innerHeight) { return; }
			go(1);
		}
		if (!reduce && root.getAttribute('data-autoplay') !== '0') {
			timer = setInterval(tick, 5000);
			root.addEventListener('mouseenter', function () { hover = true; });
			root.addEventListener('mouseleave', function () { hover = false; });
			root.addEventListener('focusin', function () { hover = true; });
			root.addEventListener('focusout', function () { hover = false; });
			track.addEventListener('touchstart', function () { pause(10000); }, { passive: true });
		}

		update();
		window.addEventListener('load', update);
		reveal(root, Array.prototype.slice.call(root.querySelectorAll('.celb-tc')));
	}

	function boot() {
		document.querySelectorAll('[data-celb-tg]').forEach(initGrid);
		document.querySelectorAll('[data-celb-rail]').forEach(initRail);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
