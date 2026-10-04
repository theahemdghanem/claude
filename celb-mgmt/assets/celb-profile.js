/* =========================================================================
   CELB MGMT — Celebrity profile behaviour (vanilla, no dependencies)
   - picks up the theme's page background + sticky header height
   - sticky section nav with scroll-spy
   - biography "Read more", career filter + "Show all", share, mobile bar
   Gallery lightbox, videos and birthday confetti stay in celb-frontend.js.
   ========================================================================= */
(function () {
	'use strict';

	var root = document.querySelector('[data-celb-profile]');
	if (!root) { return; }

	function px(v) { return Math.round(v) + 'px'; }

	/* ---- Theme background: the colour actually behind the profile -------- */
	function pageBackground() {
		// Text colour of the theme (or of the forced mode) for filled buttons.
		root.style.setProperty('--celb-p-ink', getComputedStyle(root).color);
		if (root.classList.contains('is-light') || root.classList.contains('is-dark')) { return; }
		var node = root.parentElement;
		while (node) {
			var c = getComputedStyle(node).backgroundColor;
			if (c && c !== 'transparent' && !/rgba\([^)]*,\s*0\)$/.test(c)) {
				root.style.setProperty('--celb-p-page-bg', c);
				return;
			}
			node = node.parentElement;
		}
		root.style.setProperty('--celb-p-page-bg', '#ffffff');
	}

	/* ---- Height of fixed / sticky things at the top (admin bar, header) -- */
	var HEADERS = '#wpadminbar, header, .site-header, #masthead, #header-outer, #header, [data-elementor-type="header"], .elementor-location-header, .elementor-sticky--active, .sticky-header, .is-sticky';
	function topOffset() {
		var max = 0;
		document.querySelectorAll(HEADERS).forEach(function (el) {
			if (root.contains(el)) { return; }
			var cs = getComputedStyle(el);
			if (cs.position !== 'fixed' && cs.position !== 'sticky') { return; }
			if (cs.display === 'none' || cs.visibility === 'hidden') { return; }
			var r = el.getBoundingClientRect();
			if (r.height > 0 && r.height < 240 && r.top <= 48 && r.bottom > max) { max = r.bottom; }
		});
		root.style.setProperty('--celb-p-top', px(Math.max(0, max)));
		return max;
	}

	pageBackground();
	topOffset();
	window.addEventListener('load', function () { pageBackground(); topOffset(); });
	window.addEventListener('resize', topOffset);

	/* ---- Toast + share ----------------------------------------------------- */
	var toastEl = root.querySelector('[data-celb-toast]');
	var toastT;
	function toast(msg) {
		if (!toastEl) { return; }
		toastEl.textContent = msg;
		toastEl.classList.add('is-on');
		clearTimeout(toastT);
		toastT = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2200);
	}
	function copy(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.cssText = 'position:fixed;opacity:0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) { /* ignore */ }
		ta.remove();
		return Promise.resolve();
	}
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-celb-share]');
		if (!b) { return; }
		var url = location.href.split('#')[0];
		var title = root.getAttribute('data-name') || document.title;
		if (navigator.share) {
			navigator.share({ title: title, url: url }).catch(function () {});
		} else {
			copy(url).then(function () { toast(root.getAttribute('data-copied') || 'Link copied'); });
		}
	});

	/* ---- Biography read-more ------------------------------------------------ */
	var bio = root.querySelector('[data-celb-clamp]');
	var more = root.querySelector('[data-celb-more]');
	if (bio && more) {
		var lh = parseFloat(getComputedStyle(bio).fontSize) || 18;
		if (bio.scrollHeight > lh * 18) {
			bio.classList.add('is-clamped');
			more.hidden = false;
			more.setAttribute('aria-expanded', 'false');
			var label = more.querySelector('span');
			var open = label.textContent;
			more.addEventListener('click', function () {
				var expanded = !bio.classList.toggle('is-clamped');
				more.setAttribute('aria-expanded', expanded ? 'true' : 'false');
				label.textContent = expanded ? (root.getAttribute('data-less') || 'Show less') : open;
				if (!expanded) { bio.closest('section').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			});
		}
	}

	/* ---- Career: filter by type + fold long lists -------------------------- */
	var career = root.querySelector('[data-celb-career]');
	if (career) {
		var rows = Array.prototype.slice.call(career.querySelectorAll('.celb-p-credit'));
		var allBtn = career.querySelector('[data-celb-all]');
		var LIMIT = 8;
		var unfolded = !allBtn;
		var filter = '';
		var apply = function () {
			var shown = 0;
			rows.forEach(function (r) {
				var match = !filter || r.getAttribute('data-type') === filter;
				r.classList.toggle('is-hidden', !match);
				var fold = match && !unfolded && !filter && shown >= LIMIT;
				r.classList.toggle('is-folded', fold);
				if (match) { shown++; }
			});
			if (allBtn) { allBtn.hidden = unfolded || !!filter || shown <= LIMIT; }
		};
		career.addEventListener('click', function (e) {
			var pill = e.target.closest('[data-celb-filter]');
			if (pill) {
				filter = pill.getAttribute('data-celb-filter');
				career.querySelectorAll('[data-celb-filter]').forEach(function (p) {
					var on = p === pill;
					p.classList.toggle('is-on', on);
					p.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				apply();
			}
			if (e.target.closest('[data-celb-all]')) {
				unfolded = true;
				apply();
			}
		});
		apply();
	}

	/* ---- Section nav: stuck state + scroll-spy + smooth jump --------------- */
	var nav = root.querySelector('[data-celb-nav]');
	var hero = root.querySelector('.celb-p-hero');
	var bar = root.querySelector('[data-celb-bar]');
	var closing = root.querySelector('.celb-p-closing');
	if (bar) { root.classList.add('has-bar'); }
	var links = nav ? Array.prototype.slice.call(nav.querySelectorAll('[data-celb-nav-link]')) : [];
	var sections = links.map(function (a) { return document.getElementById(a.getAttribute('data-celb-nav-link')); }).filter(Boolean);
	var current = '';

	function setActive(id) {
		if (id === current) { return; }
		current = id;
		links.forEach(function (a) {
			var on = a.getAttribute('data-celb-nav-link') === id;
			a.classList.toggle('is-on', on);
			if (on) {
				a.setAttribute('aria-current', 'true');
				var box = a.parentNode;
				var left = a.offsetLeft - (box.clientWidth - a.offsetWidth) / 2;
				if (box.scrollWidth > box.clientWidth) { box.scrollTo({ left: left, behavior: 'smooth' }); }
			} else {
				a.removeAttribute('aria-current');
			}
		});
	}

	var ticking = false;
	function onScroll() {
		ticking = false;
		var top = parseFloat(getComputedStyle(root).getPropertyValue('--celb-p-top')) || 0;
		var heroGone = hero ? hero.getBoundingClientRect().bottom <= top + 1 : true;
		if (nav) { nav.classList.toggle('is-stuck', heroGone); }
		if (bar) {
			var closingIn = closing ? closing.getBoundingClientRect().top < window.innerHeight - 80 : false;
			var on = heroGone && !closingIn;
			bar.classList.toggle('is-on', on);
			bar.setAttribute('aria-hidden', on ? 'false' : 'true');
			bar.querySelectorAll('a, button').forEach(function (el) { el.tabIndex = on ? 0 : -1; });
		}
		if (sections.length) {
			var line = top + (nav ? nav.offsetHeight : 0) + window.innerHeight * 0.25;
			var id = '';
			sections.forEach(function (s) { if (s.getBoundingClientRect().top <= line) { id = s.id; } });
			if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) { id = sections[sections.length - 1].id; }
			setActive(id);
		}
	}
	window.addEventListener('scroll', function () {
		if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
	}, { passive: true });
	onScroll();

	root.addEventListener('click', function (e) {
		var a = e.target.closest('a[href^="#"]');
		if (!a) { return; }
		var target = document.getElementById(a.getAttribute('href').slice(1));
		if (!target) { return; }
		e.preventDefault();
		topOffset();
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
		if (history.replaceState) { history.replaceState(null, '', '#' + target.id); }
	});
}());
