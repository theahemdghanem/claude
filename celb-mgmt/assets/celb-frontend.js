/* =========================================================================
   CELB MGMT — Frontend JS (vanilla, no dependencies)
   - Carousel navigation + auto-advance
   - Gallery lightbox with zoom, pan, keyboard navigation
   ========================================================================= */
(function () {
	'use strict';

	function safe( fn ) { try { fn(); } catch ( e ) { if ( window.console && console.error ) { console.error( 'celb init', e ); } } }

	function boot() {
		safe( initLangSwitch );   // guarded: binds once even if called again
		safe( fixArticleWidth );
		safe( fixHeroGap );
		safe( initBirthday );
		safe( initCollapse );
		safe( initCarousels );
		safe( initLightbox );
		safe( initNewsroom );
		safe( initNewsroomFilter );
		safe( initCareerFilter );
		safe( initPortalForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	window.addEventListener('load', function () { safe( fixArticleWidth ); });

	var celbRT;
	window.addEventListener('resize', function () {
		window.clearTimeout( celbRT );
		celbRT = window.setTimeout( function () { safe( fixArticleWidth ); }, 150 );
	});
	window.addEventListener('orientationchange', function () {
		window.setTimeout( function () { safe( fixArticleWidth ); }, 250 );
	});

	/* ----------------------------------------------------------------------
	   ARTICLE FULL-BLEED — force the single-article to true viewport width at
	   runtime. CSS breakout alone can be constrained by an unknown theme
	   wrapper (max-width / overflow / float); this widens every ancestor up to
	   <body> and then pins the article flush to the viewport's left edge, so it
	   is full width on any theme.
	   ---------------------------------------------------------------------- */
	function fixArticleWidth() {
		var art = document.querySelector( '.celb-article' );
		if ( ! art ) { return; }
		var node = art.parentElement;
		while ( node && node !== document.body ) {
			var cs = window.getComputedStyle( node );
			if ( cs.position !== 'fixed' && cs.position !== 'sticky' ) {
				node.style.maxWidth = 'none';
				node.style.width = '100%';
				node.style.paddingLeft = '0';
				node.style.paddingRight = '0';
				if ( cs.cssFloat && cs.cssFloat !== 'none' ) { node.style.cssFloat = 'none'; }
			}
			node = node.parentElement;
		}
		var vw = document.documentElement.clientWidth || window.innerWidth;
		art.style.width = vw + 'px';
		art.style.maxWidth = vw + 'px';
		art.style.marginRight = '0';
		var curML = parseFloat( window.getComputedStyle( art ).marginLeft ) || 0;
		var left = art.getBoundingClientRect().left;
		art.style.marginLeft = ( curML - left ) + 'px';
	}

	/* ----------------------------------------------------------------------
	   CAREER — filter roles by project type (desktop pills).
	   ---------------------------------------------------------------------- */
	function initCareerFilter() {
		document.querySelectorAll('.celb-career-filter').forEach(function (bar) {
			var scope = bar.closest('.celb-collapse-inner') || bar.parentNode;
			var rows = scope.querySelectorAll('.celb-table-row');
			bar.addEventListener('click', function (e) {
				var pill = e.target.closest('.celb-filter-pill');
				if (!pill) { return; }
				var val = pill.getAttribute('data-filter');
				bar.querySelectorAll('.celb-filter-pill').forEach(function (p) {
					var on = p === pill;
					p.classList.toggle('is-active', on);
					p.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				rows.forEach(function (r) {
					var show = !val || r.getAttribute('data-type') === val;
					r.classList.toggle('celb-row-hidden', !show);
				});
			});
		});
	}

	/* ----------------------------------------------------------------------
	   GLOBAL NEWSROOM — filter the list by artist via the dropdown.
	   ---------------------------------------------------------------------- */
	function initNewsroomFilter() {
		var grid = document.querySelector('.celb-news-grid');
		if (!grid) { return; }
		var sel = document.getElementById('celb-news-filter');
		var empty = document.querySelector('.celb-news-empty');
		var cards = Array.prototype.slice.call(grid.querySelectorAll('.celb-news-card'));
		if (!cards.length) { return; }

		var pager = document.querySelector('.celb-news-pager');
		if (!pager) {
			pager = document.createElement('nav');
			pager.className = 'celb-news-pager';
			pager.setAttribute('aria-label', 'Newsroom pages');
			grid.parentNode.insertBefore(pager, grid.nextSibling);
		}

		var state = { filter: '', page: 1 };

		/* 12 per page on desktop (>=768px), 10 on mobile. */
		function pageSize() { return ( ( document.documentElement.clientWidth || window.innerWidth ) >= 768 ) ? 12 : 10; }

		function filtered() {
			return cards.filter(function (c) {
				return !state.filter || c.getAttribute('data-celeb') === state.filter;
			});
		}

		function render() {
			var list = filtered();
			var ps = pageSize();
			var pages = Math.max(1, Math.ceil(list.length / ps));
			if (state.page > pages) { state.page = pages; }
			if (state.page < 1) { state.page = 1; }
			var start = (state.page - 1) * ps, end = start + ps;

			cards.forEach(function (c) { c.style.display = 'none'; });
			list.forEach(function (c, i) { c.style.display = (i >= start && i < end) ? '' : 'none'; });

			if (empty) { empty.hidden = list.length > 0; }
			renderPager(pages);
		}

		function go(p) {
			state.page = p;
			render();
			var top = grid.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0) - 40;
			try { window.scrollTo({ top: top, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, top); }
		}

		function renderPager(pages) {
			pager.innerHTML = '';
			if (pages <= 1) { pager.hidden = true; return; }
			pager.hidden = false;

			function btn(label, page, opts) {
				opts = opts || {};
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'celb-news-page' + (opts.active ? ' is-active' : '') + (opts.nav ? ' celb-news-page--nav' : '');
				b.textContent = label;
				if (opts.disabled) { b.disabled = true; }
				else { b.addEventListener('click', function () { go(page); }); }
				pager.appendChild(b);
			}
			function ellipsis() {
				var s = document.createElement('span');
				s.className = 'celb-news-ellipsis';
				s.textContent = '\u2026';
				pager.appendChild(s);
			}

			btn('\u2039', state.page - 1, { nav: true, disabled: state.page <= 1 });

			var cur = state.page, win = [ 1 ];
			for (var i = cur - 1; i <= cur + 1; i++) { if (i > 1 && i < pages) { win.push(i); } }
			win.push(pages);
			win = win.filter(function (v, idx, a) { return a.indexOf(v) === idx; }).sort(function (a, b) { return a - b; });

			var prev = 0;
			win.forEach(function (p) {
				if (prev && p - prev > 1) { ellipsis(); }
				btn(String(p), p, { active: p === cur });
				prev = p;
			});

			btn('\u203a', state.page + 1, { nav: true, disabled: state.page >= pages });
		}

		if (sel) {
			sel.addEventListener('change', function () {
				state.filter = sel.value;
				state.page = 1;
				render();
			});
		}

		var rt;
		window.addEventListener('resize', function () {
			window.clearTimeout(rt);
			rt = window.setTimeout(render, 150);
		});

		render();
	}

	/* ----------------------------------------------------------------------
	   ARTICLE LANGUAGE SWITCH (EN / AR) — runs everywhere (popup + article).
	   ---------------------------------------------------------------------- */
	var celbLangBound = false;
	function initLangSwitch() {
		if ( celbLangBound ) { return; }
		celbLangBound = true;
		document.addEventListener('click', function (e) {
			var btn = e.target && e.target.closest ? e.target.closest('.celb-news-lang-btn') : null;
			if (!btn) { return; }
			var scope = btn.closest('.celb-news-popup') || btn.closest('.celb-article');
			if (!scope) { return; }
			var lang = btn.getAttribute('data-lang');
			scope.querySelectorAll('.celb-news-lang-btn').forEach(function (b) {
				b.classList.toggle('is-active', b === btn);
			});
			scope.querySelectorAll('[data-lang-en]').forEach(function (el) { el.hidden = (lang !== 'en'); });
			scope.querySelectorAll('[data-lang-ar]').forEach(function (el) { el.hidden = (lang !== 'ar'); });
			scope.classList.toggle('celb-lang-ar', lang === 'ar');
		});
	}

	/* ----------------------------------------------------------------------
	   NEWSROOM popups (open/close, scroll-lock). Gallery uses the shared
	   lightbox above; close defers to the lightbox when it is open.
	   ---------------------------------------------------------------------- */
	function initNewsroom() {
		var items = document.querySelectorAll('.celb-news-item');
		if (!items.length) { return; }

		function closePopup(p) {
			p.classList.remove('is-open');
			p.setAttribute('aria-hidden', 'true');
			if (!document.querySelector('.celb-news-popup.is-open')) { document.body.style.overflow = ''; }
		}
		function openPopup(p) {
			document.querySelectorAll('.celb-news-popup.is-open').forEach(function (o) {
				o.classList.remove('is-open');
				o.setAttribute('aria-hidden', 'true');
			});
			p.classList.add('is-open');
			p.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
			var modal = p.querySelector('.celb-news-modal');
			if (modal) { modal.scrollTop = 0; }
		}

		items.forEach(function (it) {
			it.addEventListener('click', function () {
				var p = document.getElementById(it.getAttribute('data-news-target'));
				if (p) { openPopup(p); }
			});
		});

		document.querySelectorAll('[data-news-close]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var p = btn.closest('.celb-news-popup');
				if (p) { closePopup(p); }
			});
		});

		// Esc: close the popup, but let the lightbox take Esc first if it's open.
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') { return; }
			if (document.querySelector('.celb-lightbox.is-open')) { return; }
			var p = document.querySelector('.celb-news-popup.is-open');
			if (p) { closePopup(p); }
		}, true);
	}

	/* ----------------------------------------------------------------------
	   SUBMISSION PORTAL — add/remove repeater rows (career, awards)
	   ---------------------------------------------------------------------- */
	function initPortalForm() {
		var form = document.querySelector('.celb-portal-form');
		if (!form) { return; }
		form.querySelectorAll('.celb-portal-repeater').forEach(function (rep) {
			var items = rep.querySelector('.celb-portal-items');
			var tplEl = rep.querySelector('.celb-portal-row-tpl');
			var addBtn = rep.querySelector('.celb-portal-add');
			if (!items || !tplEl || !addBtn) { return; }
			var tpl = tplEl.innerHTML;
			var idx = items.querySelectorAll('.celb-portal-row').length;

			addBtn.addEventListener('click', function () {
				var html = tpl.replace(/__i__/g, 'new_' + idx);
				idx++;
				var wrap = document.createElement('div');
				wrap.innerHTML = html.trim();
				if (wrap.firstElementChild) { items.appendChild(wrap.firstElementChild); }
			});

			items.addEventListener('click', function (e) {
				var t = e.target.closest('.celb-portal-remove');
				if (!t) { return; }
				var rows = items.querySelectorAll('.celb-portal-row');
				if (rows.length <= 1) {
					t.closest('.celb-portal-row').querySelectorAll('input').forEach(function (i) {
						if (i.type === 'checkbox') { i.checked = false; } else { i.value = ''; }
					});
				} else {
					t.closest('.celb-portal-row').remove();
				}
			});
		});
	}

	/* ----------------------------------------------------------------------
	   COLLAPSIBLE SECTIONS (Career History, Awards)
	   Collapsed by default (aria-expanded="false" in markup); a click toggles.
	   ---------------------------------------------------------------------- */
	function initCollapse() {
		var toggles = document.querySelectorAll('.celb-collapsible .celb-toggle');
		toggles.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var open = btn.getAttribute('aria-expanded') === 'true';
				btn.setAttribute('aria-expanded', open ? 'false' : 'true');
			});
		});
	}

	/* ----------------------------------------------------------------------
	   BIRTHDAY: confetti when the profile is opened on the celebrity's birthday
	   (the "Happy Birthday" greeting itself is revealed via CSS data attribute).
	   ---------------------------------------------------------------------- */
	function initBirthday() {
		var born = document.querySelector('.celb-born[data-celb-birthday="1"]');
		if (!born) { return; }
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
		celbConfetti(6000);
	}

	function celbConfetti(duration) {
		var canvas = document.createElement('canvas');
		canvas.setAttribute('aria-hidden', 'true');
		canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:100000;';
		document.body.appendChild(canvas);
		var ctx = canvas.getContext('2d');

		function size() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
		size();
		window.addEventListener('resize', size);

		var colors = ['#cccccc', '#ffffff', '#bbbbbb', '#aaaaaa', '#888888'];
		try {
			var scope = document.querySelector('.celb-scope');
			var g = scope ? getComputedStyle(scope).getPropertyValue('--celb-gold').trim() : '';
			if (g) { colors[0] = g; }
		} catch (e) {}

		var parts = [];
		var N = Math.min(160, Math.round(canvas.width / 9));
		for (var i = 0; i < N; i++) {
			parts.push({
				x: Math.random() * canvas.width,
				y: Math.random() * -canvas.height,
				w: 6 + Math.random() * 6,
				h: 8 + Math.random() * 8,
				c: colors[(Math.random() * colors.length) | 0],
				vy: 2 + Math.random() * 3,
				vx: -1 + Math.random() * 2,
				rot: Math.random() * Math.PI,
				vr: -0.12 + Math.random() * 0.24
			});
		}

		var start = performance.now();
		function frame(t) {
			var elapsed = t - start;
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			var fade = elapsed > duration - 1200 ? Math.max(0, (duration - elapsed) / 1200) : 1;
			for (var i = 0; i < parts.length; i++) {
				var p = parts[i];
				p.x += p.vx; p.y += p.vy; p.rot += p.vr;
				if (p.y > canvas.height + 20) { p.y = -20; p.x = Math.random() * canvas.width; }
				ctx.save();
				ctx.globalAlpha = fade;
				ctx.translate(p.x, p.y);
				ctx.rotate(p.rot);
				ctx.fillStyle = p.c;
				ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
				ctx.restore();
			}
			if (elapsed < duration) {
				requestAnimationFrame(frame);
			} else {
				window.removeEventListener('resize', size);
				canvas.remove();
			}
		}
		requestAnimationFrame(frame);
	}

	/* ----------------------------------------------------------------------
	   HERO TOP-GAP FIX
	   Some themes wrap content in a container with top padding/margin, which
	   leaves a white strip above the full-bleed hero. We collapse that top
	   spacing on the static wrappers between the profile and <body>.
	   Controlled by the "Pull hero flush" setting (CELB_FRONT.pullHero).
	   ---------------------------------------------------------------------- */
	function fixHeroGap() {
		if (window.CELB_FRONT && Number(CELB_FRONT.pullHero) === 0) { return; }
		var single = document.querySelector('.celb-single');
		if (!single) { return; }
		var node = single.parentElement;
		while (node && node !== document.body) {
			var pos = window.getComputedStyle(node).position;
			// Don't disturb sticky/fixed wrappers (e.g. a header container).
			if (pos !== 'fixed' && pos !== 'sticky') {
				node.style.paddingTop = '0px';
				node.style.marginTop = '0px';
			}
			node = node.parentElement;
		}
	}

	/* ----------------------------------------------------------------------
	   CAROUSEL
	   ---------------------------------------------------------------------- */
	function initCarousels() {
		var carousels = document.querySelectorAll('[data-celb-carousel]');
		carousels.forEach(function (root) {
			var track = root.querySelector('.celb-carousel-track');
			var prev = root.querySelector('.celb-prev');
			var next = root.querySelector('.celb-next');
			if (!track) { return; }

			var cells = track.querySelectorAll('.celb-carousel-cell');

			// On desktop with 6 or fewer cells there is nothing to scroll.
			function scrollAmount() {
				var cell = track.querySelector('.celb-carousel-cell');
				if (!cell) { return track.clientWidth; }
				var style = window.getComputedStyle(track);
				var gap = parseFloat(style.columnGap || style.gap || '0') || 0;
				// Advance by ~3 cells per click for a smooth glide.
				return (cell.getBoundingClientRect().width + gap) * 3;
			}

			function maxScroll() {
				return track.scrollWidth - track.clientWidth;
			}

			function updateNav() {
				var hidden = maxScroll() <= 2 || window.innerWidth <= 782;
				if (prev) { prev.style.display = hidden ? 'none' : ''; }
				if (next) { next.style.display = hidden ? 'none' : ''; }
			}

			if (prev) {
				prev.addEventListener('click', function () {
					track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
				});
			}
			if (next) {
				next.addEventListener('click', function () {
					track.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
				});
			}

			// Gentle auto-advance only when content overflows (desktop).
			var timer = null;
			function startAuto() {
				stopAuto();
				if (window.innerWidth <= 782 || maxScroll() <= 2) { return; }
				timer = window.setInterval(function () {
					if (Math.ceil(track.scrollLeft) >= maxScroll() - 2) {
						track.scrollTo({ left: 0, behavior: 'smooth' });
					} else {
						track.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
					}
				}, 5000);
			}
			function stopAuto() {
				if (timer) { window.clearInterval(timer); timer = null; }
			}

			root.addEventListener('mouseenter', stopAuto);
			root.addEventListener('mouseleave', startAuto);
			root.addEventListener('touchstart', stopAuto, { passive: true });

			window.addEventListener('resize', function () {
				updateNav();
				startAuto();
			});

			updateNav();
			startAuto();
		});
	}

	/* ----------------------------------------------------------------------
	   LIGHTBOX
	   ---------------------------------------------------------------------- */
	function initLightbox() {
		var lb = document.querySelector('[data-celb-lightbox]');
		if (!lb) { return; }

		var img = lb.querySelector('.celb-lb-img');
		var btnClose = lb.querySelector('.celb-lb-close');
		var btnPrev = lb.querySelector('.celb-lb-prev');
		var btnNext = lb.querySelector('.celb-lb-next');

		var thumbs = [];
		var current = 0;
		var zoomed = false;
		var pan = { active: false, startX: 0, startY: 0, x: 0, y: 0 };

		function resetZoom() {
			zoomed = false;
			pan.x = 0; pan.y = 0;
			img.classList.remove('is-zoomed');
			img.style.transform = 'scale(1)';
		}

		function show(index) {
			if (!thumbs.length) { return; }
			current = (index + thumbs.length) % thumbs.length;
			var src = thumbs[current].getAttribute('data-full');
			resetZoom();
			img.src = src;
		}

		function open(set, index) {
			thumbs = set;
			show(index);
			lb.classList.add('is-open');
			lb.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
		}

		function close() {
			lb.classList.remove('is-open');
			lb.setAttribute('aria-hidden', 'true');
			// Keep scroll locked if a news popup is still open behind the lightbox.
			document.body.style.overflow = document.querySelector('.celb-news-popup.is-open') ? 'hidden' : '';
			resetZoom();
		}

		// Delegated: any gallery thumb (profile or newsroom) opens the lightbox,
		// scoped to the gallery it belongs to.
		document.addEventListener('click', function (e) {
			var t = e.target.closest('.celb-gallery-thumb, .celb-news-gthumb');
			if (!t) { return; }
			var container = t.closest('.celb-gallery-grid, .celb-news-gallery');
			if (!container) { return; }
			var set = Array.prototype.slice.call(container.querySelectorAll('.celb-gallery-thumb, .celb-news-gthumb'));
			open(set, set.indexOf(t));
		});

		if (btnClose) { btnClose.addEventListener('click', close); }
		if (btnPrev) { btnPrev.addEventListener('click', function () { show(current - 1); }); }
		if (btnNext) { btnNext.addEventListener('click', function () { show(current + 1); }); }

		// Click backdrop (not the image) to close.
		lb.addEventListener('click', function (e) {
			if (e.target === lb || e.target.classList.contains('celb-lb-stage')) {
				close();
			}
		});

		// Toggle zoom on image click.
		img.addEventListener('click', function (e) {
			e.stopPropagation();
			if (zoomed) {
				resetZoom();
			} else {
				zoomed = true;
				img.classList.add('is-zoomed');
				img.style.transform = 'scale(2)';
			}
		});

		// Drag to pan while zoomed.
		img.addEventListener('mousedown', function (e) {
			if (!zoomed) { return; }
			e.preventDefault();
			pan.active = true;
			pan.startX = e.clientX - pan.x;
			pan.startY = e.clientY - pan.y;
			img.style.cursor = 'grabbing';
		});
		window.addEventListener('mousemove', function (e) {
			if (!pan.active) { return; }
			pan.x = e.clientX - pan.startX;
			pan.y = e.clientY - pan.startY;
			img.style.transform = 'scale(2) translate(' + (pan.x / 2) + 'px,' + (pan.y / 2) + 'px)';
		});
		window.addEventListener('mouseup', function () {
			if (pan.active) { pan.active = false; img.style.cursor = 'grab'; }
		});

		// Keyboard navigation.
		document.addEventListener('keydown', function (e) {
			if (!lb.classList.contains('is-open')) { return; }
			if (e.key === 'Escape') { close(); }
			else if (e.key === 'ArrowLeft') { show(current - 1); }
			else if (e.key === 'ArrowRight') { show(current + 1); }
		});

		// Basic swipe navigation on touch devices.
		var touchX = null;
		lb.addEventListener('touchstart', function (e) {
			touchX = e.changedTouches[0].clientX;
		}, { passive: true });
		lb.addEventListener('touchend', function (e) {
			if (touchX === null || zoomed) { return; }
			var dx = e.changedTouches[0].clientX - touchX;
			if (Math.abs(dx) > 50) { show(dx < 0 ? current + 1 : current - 1); }
			touchX = null;
		}, { passive: true });
	}
})();
