/* =========================================================================
   CELB apps — shared core
   Helpers, JSON API, shell, router, push + settings. Used by the Studio app
   (managers) and the Talent app; each defines its own screens and tabs.
   ========================================================================= */
(function () {
	'use strict';

	var CFG = window.CELB_APP || {};
	var root = document.getElementById('app');
	var state = { unread: 0, badges: {}, home: null };
	var view = { act: {}, change: {} };
	var TABS = [];
	var ROUTES = [];
	var OPTS = {};
	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function qs(sel, el) { return (el || document).querySelector(sel); }
	function qsa(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }

	var ICONS = {
		home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
		inbox: '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v6H3v-6z"/>',
		cal: '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		star: '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
		more: '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
		bell: '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
		back: '<path d="M15 5l-7 7 7 7"/>',
		chev: '<path d="m9 5 7 7-7 7"/>',
		search: '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/>',
		mail: '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
		phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		wa: '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c.3 2.3 2.2 4.2 4.5 4.5l1-1.2 1.8.8-.4 1.6c-3.4.3-7.2-3.5-6.9-6.9L10.6 8l.8 1.8z"/>',
		news: '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M7.5 9h9M7.5 12.5h9M7.5 16h5"/>',
		doc: '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 15.5h5"/>',
		tag: '<path d="M3.5 12.5V4.5h8l9 9-8 8z"/><circle cx="8" cy="9" r="1.4"/>',
		folder: '<path d="M3.5 6.5a2 2 0 0 1 2-2H10l2 2.5h6.5a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',
		link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11.5 6.8"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		shield: '<path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6z"/>',
		gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.6 14H3.5a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9.4a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9.4a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V7.5A1.5 1.5 0 0 1 5.5 6H10"/>',
		out: '<path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
		copy: '<rect x="8.5" y="8.5" width="12" height="12" rx="2"/><path d="M15.5 8.5V5a1.5 1.5 0 0 0-1.5-1.5H5A1.5 1.5 0 0 0 3.5 5v9A1.5 1.5 0 0 0 5 15.5h3.5"/>',
		share: '<path d="M12 3v12M7.5 7.5 12 3l4.5 4.5"/><path d="M5 12v7a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-7"/>',
		pin: '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
		down: '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 20h14"/>',
		check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		film: '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M8 4.5v15M16 4.5v15M3.5 9.5H8M16 9.5h4.5M3.5 14.5H8M16 14.5h4.5"/>',
		clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		refresh: '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v4.5h-4.5"/>',
		x: '<path d="M6 6l12 12M18 6 6 18"/>',
		send: '<path d="M21 3 10 14"/><path d="M21 3 14.5 21 10 14 3 9.5z"/>'
	};
	function ic(n) { return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[n] || '') + '</svg>'; }

	function initials(n) {
		var p = String(n || '').trim().split(/\s+/);
		return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase() || '·';
	}
	function av(photo, name, cls) {
		var st = photo ? ' style="background-image:url(\'' + esc(photo) + '\')"' : '';
		return '<span class="av' + (cls ? ' ' + cls : '') + '"' + st + '>' + (photo ? '' : esc(initials(name))) + '</span>';
	}
	function pill(text, tone) { return '<span class="pill' + (tone ? ' t-' + tone : '') + '">' + esc(text) + '</span>'; }

	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	function parseDate(s) {
		var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
		return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
	}
	function ymd(d) {
		return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
	}
	function fmtDate(s, withDay) {
		var d = parseDate(s);
		if (!d) { return s || ''; }
		return (withDay ? DAYS[d.getDay()] + ', ' : '') + d.getDate() + ' ' + MONTHS[d.getMonth()] + (d.getFullYear() !== new Date().getFullYear() ? ' ' + d.getFullYear() : '');
	}
	function fmtTime(t) {
		var m = /^(\d{1,2}):(\d{2})/.exec(t || '');
		if (!m) { return ''; }
		var h = +m[1];
		return ((h % 12) || 12) + ':' + m[2] + (h < 12 ? ' am' : ' pm');
	}
	function ago(ts) {
		var s = Math.max(1, Math.floor(Date.now() / 1000 - ts));
		if (s < 60) { return 'now'; }
		if (s < 3600) { return Math.floor(s / 60) + 'm'; }
		if (s < 86400) { return Math.floor(s / 3600) + 'h'; }
		if (s < 604800) { return Math.floor(s / 86400) + 'd'; }
		var d = new Date(ts * 1000);
		return d.getDate() + ' ' + MONTHS[d.getMonth()];
	}
	function dayLabel(s, today) {
		if (s === today) { return 'Today'; }
		var t = parseDate(today), d = parseDate(s);
		if (t && d) {
			var diff = Math.round((d - t) / 86400000);
			if (diff === 1) { return 'Tomorrow'; }
			if (diff === -1) { return 'Yesterday'; }
		}
		return fmtDate(s, true);
	}

	var TONES = {
		'new': 'blue', progress: 'amber', in_progress: 'amber', replied: 'purple', booked: 'green', closed: 'slate',
		upcoming: 'blue', ongoing: 'amber', completed: 'green', postponed: 'purple', canceled: 'red', cancelled: 'red', scheduled: 'blue',
		signed: 'green', pending: 'amber', draft: 'slate', publish: 'green', future: 'purple', on: 'green', off: 'slate'
	};
	function tone(s) { return TONES[String(s || '').toLowerCase().replace(/\s+/g, '_')] || ''; }
	function label(s) {
		var map = { 'new': 'New', progress: 'In progress', in_progress: 'In progress', replied: 'Replied', booked: 'Booked', closed: 'Closed', publish: 'Published', draft: 'Draft', future: 'Scheduled', pending: 'Pending', signed: 'Signed' };
		return map[s] || (s ? String(s).charAt(0).toUpperCase() + String(s).slice(1) : '');
	}

	function toast(msg) {
		var t = qs('.toast');
		if (!t) {
			t = document.createElement('div');
			t.className = 'toast';
			document.body.appendChild(t);
		}
		t.textContent = msg;
		t.classList.add('on');
		clearTimeout(toast.t);
		toast.t = setTimeout(function () { t.classList.remove('on'); }, 2200);
	}

	function copy(text) {
		var done = function () { toast('Copied'); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { legacyCopy(text); done(); });
		} else {
			legacyCopy(text);
			done();
		}
	}
	function legacyCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) { /* ignore */ }
		ta.remove();
	}
	function share(url, title) {
		if (navigator.share) {
			navigator.share({ title: title || CFG.name, url: url }).catch(function () {});
		} else {
			copy(url);
		}
	}

	function sheet(title, html, bind) {
		closeSheet();
		var bg = document.createElement('div');
		bg.className = 'sheet-bg';
		var sh = document.createElement('div');
		sh.className = 'sheet';
		sh.setAttribute('role', 'dialog');
		sh.innerHTML = '<h3>' + esc(title) + '</h3>' + html;
		bg.addEventListener('click', closeSheet);
		document.body.appendChild(bg);
		document.body.appendChild(sh);
		if (bind) { bind(sh); }
		return sh;
	}
	function closeSheet() {
		qsa('.sheet, .sheet-bg').forEach(function (n) { n.remove(); });
	}

	/* ---------------------------------------------------------------------
	 * API
	 * ------------------------------------------------------------------ */
	function refreshNonce() {
		var body = new FormData();
		body.append('action', 'celb_app_nonce');
		return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (j && j.success && j.data && j.data.nonce) {
					CFG.nonce = j.data.nonce;
					return true;
				}
				throw new Error('auth');
			});
	}
	function api(path, data, retried) {
		var opts = { method: data ? 'POST' : 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce } };
		if (data) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(data);
		}
		var url = CFG.rest + path.replace(/^\//, '');
		return fetch(url, opts).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (r.ok) { return j; }
				if (!retried && (r.status === 403 || r.status === 401) && j && j.code === 'rest_cookie_invalid_nonce') {
					return refreshNonce().then(function () { return api(path, data, true); });
				}
				if (r.status === 401 || (j && j.code === 'rest_not_logged_in')) {
					location.href = CFG.app;
				}
				var err = new Error((j && j.message) || ('Request failed (' + r.status + ')'));
				err.status = r.status;
				throw err;
			});
		});
	}


	/* ---------------------------------------------------------------------
	 * Shell
	 * ------------------------------------------------------------------ */
	function shell() {
		root.innerHTML = '<div class="shell">' +
			'<header class="topbar"></header>' +
			'<main class="page-wrap"></main>' +
			'<nav class="tabs" aria-label="Sections">' +
				(CFG.logo ? '<a class="brand" href="#/"><img src="' + esc(CFG.logo) + '" alt="' + esc(CFG.name) + '"></a>' : '') +
				TABS.map(function (t) {
					return '<a class="tab" data-tab="' + t.key + '" href="' + t.href + '">' + ic(t.icon) + '<span>' + t.label + '</span>' + (t.badge ? '<i class="badge" hidden></i>' : '') + '</a>';
				}).join('') +
			'</nav></div>';
		window.addEventListener('scroll', function () {
			var tb = qs('.topbar');
			if (tb) { tb.classList.toggle('is-scrolled', window.scrollY > 4); }
		}, { passive: true });

		root.addEventListener('click', function (e) {
			var el = e.target.closest('[data-act]');
			if (!el || !root.contains(el)) { return; }
			var fn = view.act[el.getAttribute('data-act')] || GLOBAL_ACTS[el.getAttribute('data-act')];
			if (fn) {
				e.preventDefault();
				fn(el, e);
			}
		});
		root.addEventListener('change', function (e) {
			var el = e.target.closest('[data-change]');
			if (el && view.change[el.getAttribute('data-change')]) {
				view.change[el.getAttribute('data-change')](el, e);
			}
		});
		root.addEventListener('input', function (e) {
			var el = e.target.closest('[data-input]');
			if (el && view.change[el.getAttribute('data-input')]) {
				view.change[el.getAttribute('data-input')](el, e);
			}
		});
	}

	var GLOBAL_ACTS = {
		back: function () {
			if (history.length > 1 && state.navigated) {
				history.back();
			} else {
				location.hash = qs('[data-act="back"]').getAttribute('data-to') || '#/';
			}
		},
		copy: function (el) { copy(el.getAttribute('data-v')); },
		share: function (el) { share(el.getAttribute('data-v'), el.getAttribute('data-t')); },
		go: function (el) { location.hash = el.getAttribute('data-to'); },
		open: function (el) { window.open(el.getAttribute('data-v'), '_blank', 'noopener'); }
	};

	function setBadges() {
		qsa('.tab .badge').forEach(function (b) {
			var n = state.badges[b.parentNode.getAttribute('data-tab')] || 0;
			b.hidden = !n;
			b.textContent = n > 99 ? '99+' : n;
		});
		var d = qs('.topbar .bell .dot');
		if (d) {
			d.hidden = !state.unread;
			d.textContent = state.unread > 9 ? '9+' : state.unread;
		}
	}

	function topbar(title, opts) {
		opts = opts || {};
		var left = opts.back ? '<button class="iconbtn plain" data-act="back" data-to="' + esc(opts.back) + '" aria-label="Back">' + ic('back') + '</button>' : '';
		var right = opts.right || '';
		if (opts.bell) {
			right += '<a class="iconbtn bell" href="#/feed" aria-label="Activity">' + ic('bell') + '<span class="dot" hidden></span></a>';
		}
		qs('.topbar').innerHTML = left + '<h1>' + esc(title) + (opts.sub ? '<span class="sub">' + esc(opts.sub) + '</span>' : '') + '</h1>' + right;
		document.title = title + ' · ' + CFG.name;
		setBadges();
	}

	function page(html) {
		var w = qs('.page-wrap');
		w.innerHTML = '<div class="page">' + html + '</div>';
		return w.firstChild;
	}
	function loading() { page('<div class="spin"></div>'); }
	function fail(err, retry) {
		if (!navigator.onLine) { err = new Error('You’re offline. Reconnect and try again.'); }
		page('<div class="empty">' + ic('refresh') + '<p>' + esc(err && err.message ? err.message : 'Something went wrong.') + '</p><button class="btn" data-act="retry">Try again</button></div>');
		view.act.retry = retry || function () { route(); };
	}
	function empty(icon, text) { return '<div class="empty">' + ic(icon) + '<div>' + esc(text) + '</div></div>'; }

	function card(inner, head) {
		return '<section class="card">' + (head ? '<div class="card-h"><h4>' + esc(head) + '</h4></div>' : '') + inner + '</section>';
	}
	function sec(title, link) {
		return '<div class="sec"><h3>' + esc(title) + '</h3>' + (link || '') + '</div>';
	}
	function row(o) {
		var tag = o.href ? 'a href="' + esc(o.href) + '"' : 'div';
		return '<' + tag + ' class="row' + (o.unread ? ' unread' : '') + '"' + (o.attrs || '') + '>' +
			(o.lead || '') +
			'<div class="row-main"><div class="row-t"><span>' + esc(o.title) + '</span>' + (o.badge || '') + '</div>' +
			(o.sub ? '<div class="row-s">' + o.sub + '</div>' : '') +
			(o.extra ? '<div class="row-x">' + esc(o.extra) + '</div>' : '') + '</div>' +
			(o.end != null ? '<div class="row-end">' + o.end + '</div>' : (o.href ? '<span class="chev">' + ic('chev') + '</span>' : '')) +
			'</' + (o.href ? 'a' : 'div') + '>';
	}
	function sw(name, title, sub, on, extra) {
		return '<label class="switch"><span><b>' + esc(title) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span>' +
			'<input type="checkbox" data-change="' + esc(name) + '"' + (on ? ' checked' : '') + (extra || '') + '><span class="track"></span></label>';
	}
	function dl(pairs) {
		var out = pairs.filter(function (p) { return p[1]; }).map(function (p) {
			return '<div><dt>' + esc(p[0]) + '</dt><dd>' + p[1] + '</dd></div>';
		}).join('');
		return out ? '<dl class="dl">' + out + '</dl>' : '';
	}
	function stack(people) {
		return '<span class="stack">' + people.slice(0, 4).map(function (p) { return av(p.photo, p.name); }).join('') + '</span>';
	}

	/* Agenda list grouped by day. */
	function agenda(items, today, opts) {
		opts = opts || {};
		if (!items.length) { return opts.empty === false ? '' : card(empty('cal', opts.emptyText || 'Nothing scheduled.')); }
		var groups = [], cur = null;
		items.forEach(function (it) {
			if (!cur || cur.date !== it.date) {
				cur = { date: it.date, items: [] };
				groups.push(cur);
			}
			cur.items.push(it);
		});
		return groups.map(function (g) {
			return '<div class="day' + (g.date === today ? ' today' : '') + '">' + esc(dayLabel(g.date, today)) + (g.date === today || dayLabel(g.date, today).length < 10 ? '<span class="n">' + esc(fmtDate(g.date)) + '</span>' : '') + '</div>' +
				'<section class="card">' + g.items.map(evRow).join('') + '</section>';
		}).join('');
	}
	function evRow(it) {
		var st = String(it.status || '').toLowerCase();
		var href = it.kind === 'sched' ? '#/sched/' + it.id : '#/project/' + it.id;
		var t = fmtTime(it.time);
		return '<a class="ev k-' + it.kind + ' s-' + esc(st) + '" href="' + href + '" style="text-decoration:none;color:inherit">' +
			'<div class="ev-time">' + (t ? esc(t.replace(/ (am|pm)$/, '')) + '<small>' + esc(t.slice(-2)) + '</small>' : '<small>All day</small>') + '</div>' +
			'<div class="ev-main"><div class="ev-t">' + esc(it.title) + '</div>' +
			'<div class="ev-s">' + (it.celeb ? esc(it.celeb.name) + ' · ' : '') + esc(it.type || '') +
			(it.where ? ' · ' + esc(it.where) : '') + (st && st !== 'upcoming' && st !== 'scheduled' ? ' ' + pill(it.status, tone(st)) : '') + '</div></div>' +
			(it.celeb ? av(it.celeb.photo, it.celeb.name) : '') + '</a>';
	}


	/* Settings + push -------------------------------------------------- */
	function b64ToU8(s) {
		var pad = '='.repeat((4 - s.length % 4) % 4);
		var raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
		var out = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
		return out;
	}
	function swReg() {
		if (!('serviceWorker' in navigator)) { return Promise.reject(new Error('This browser cannot run the app offline or receive notifications.')); }
		return navigator.serviceWorker.register(CFG.sw, { scope: CFG.app }).then(function () { return navigator.serviceWorker.ready; });
	}
	function pushSupport() {
		return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
	}
	function isIOS() { return /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); }
	function isStandalone() { return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true; }

	function currentSub() {
		if (!pushSupport()) { return Promise.resolve(null); }
		return swReg().then(function (reg) { return reg.pushManager.getSubscription(); });
	}
	function subscribe() {
		return api('push/key', {}).then(function (k) {
			if (!k.supported || !k.key) { throw new Error('This server cannot send push notifications.'); }
			return Notification.requestPermission().then(function (perm) {
				if (perm !== 'granted') { throw new Error('Notifications are blocked for this site. Allow them in your browser settings.'); }
				return swReg();
			}).then(function (reg) {
				return reg.pushManager.getSubscription().then(function (old) {
					return old ? old.unsubscribe() : true;
				}).then(function () {
					return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToU8(k.key) });
				});
			}).then(function (sub) {
				return api('push/subscribe', { subscription: sub.toJSON() });
			});
		});
	}
	function unsubscribe() {
		return currentSub().then(function (sub) {
			if (!sub) { return null; }
			var ep = sub.endpoint;
			return sub.unsubscribe().then(function () { return api('push/unsubscribe', { endpoint: ep }); });
		});
	}

	function settingsScreen() {
		topbar('App settings', { back: OPTS.settingsBack || '#/more' });
		loading();
		return Promise.all([api('prefs'), currentSub().catch(function () { return null; })]).then(function (res) {
			var p = res[0], sub = res[1];
			var on = !!sub;
			var supported = pushSupport() && CFG.push;
			var why = '';
			if (!CFG.push) { why = 'The server is missing the OpenSSL features needed to send notifications.'; }
			else if (!pushSupport()) { why = isIOS() && !isStandalone() ? 'On iPhone and iPad, add this app to your Home Screen first (Share → Add to Home Screen), then open it from there.' : 'This browser does not support push notifications.'; }
			else if (Notification.permission === 'denied') { why = 'Notifications are blocked for this site — allow them in your browser settings.'; }
			var statusCheck = on ? api('push/status', { endpoint: sub.endpoint }) : Promise.resolve({ subscribed: false });
			statusCheck.then(function (st) {
				on = on && st.subscribed;
				page(
					sec('Notifications') +
					card('<div class="card-b" style="padding-top:4px;padding-bottom:4px">' +
						sw('push', 'Push notifications on this device', on ? 'On · ' + p.devices + ' device' + (p.devices === 1 ? '' : 's') + ' in total' : 'Get alerts for new requests and submissions', on, supported && Notification.permission !== 'denied' ? '' : ' disabled') +
					'</div>') +
					(why ? '<div class="note warn" style="margin-top:10px">' + esc(why) + '</div>' : '') +
					(on ? '<div class="actions" style="margin-top:10px"><button class="btn sm" data-act="test">' + ic('send') + 'Send a test notification</button></div>' : '') +
					sec('Notify me about') +
					card('<div class="card-b" style="padding-top:4px;padding-bottom:4px">' + Object.keys(p.events).map(function (k) {
						return sw('pref', p.events[k], '', p.prefs[k], ' data-k="' + esc(k) + '"');
					}).join('') + '</div>') +
					'<p class="row-s" style="white-space:normal;margin:8px 4px 0">These apply to every device you turn notifications on for.' + (CFG.app_id === 'talent' ? '' : ' Events also show up in Activity.') + '</p>' +
					sec('Install') +
					card('<div class="card-b">' + (isStandalone() ? '<div class="row-t"><span>' + ic('check') + ' Installed — you are using the app.</span></div>' :
						(isIOS() ? '<div class="prose" style="font-size:14px">In Safari, tap <b>Share</b> then <b>Add to Home Screen</b>. Notifications on iPhone need the installed app.</div>' :
						'<div class="prose" style="font-size:14px">Install it from your browser menu (<b>Install app</b> / <b>Add to Home screen</b>) to open it like a native app.</div>' + (state.installEvt ? '<div class="actions" style="margin:12px 0 0"><button class="btn primary" data-act="install">' + ic('down') + 'Install now</button></div>' : ''))) + '</div>') +
					sec('About') +
					card('<div class="card-b">' + dl([['App', esc(CFG.name + ' v' + CFG.version)], ['Site', esc(CFG.site)], ['Address', '<a href="' + esc(CFG.app) + '">' + esc(CFG.app.replace(/^https?:\/\//, '')) + '</a>']]) +
						'<div class="actions" style="margin:12px 0 0"><button class="btn sm" data-act="copy" data-v="' + esc(CFG.app) + '">' + ic('copy') + 'Copy app address</button></div></div>')
				);
			});
			view.change.push = function (el) {
				el.disabled = true;
				(el.checked ? subscribe() : unsubscribe()).then(function () {
					toast(el.checked ? 'Notifications on' : 'Notifications off');
					route();
				}).catch(function (e) {
					el.checked = !el.checked;
					el.disabled = false;
					toast(e.message);
				});
			};
			view.change.pref = function () {
				var prefs = {};
				qsa('[data-change="pref"]').forEach(function (n) { prefs[n.getAttribute('data-k')] = n.checked; });
				api('prefs', { prefs: prefs }).then(function () { toast('Saved'); }).catch(function (e) { toast(e.message); });
			};
			view.act.test = function (el) {
				el.disabled = true;
				currentSub().then(function (s) {
					return api('push/test', { endpoint: s ? s.endpoint : '' });
				}).then(function () { toast('Test sent'); }).catch(function (e) { toast(e.message); }).then(function () { el.disabled = false; });
			};
			view.act.install = function () {
				if (!state.installEvt) { return; }
				state.installEvt.prompt();
				state.installEvt.userChoice.then(function () { state.installEvt = null; route(); });
			};
		});
	}

	/* ---------------------------------------------------------------------
	 * Router
	 * ------------------------------------------------------------------ */
	function route() {
		closeSheet();
		var path = (location.hash || '#/').replace(/^#/, '').replace(/\?.*$/, '') || '/';
		var hit = null, m = null;
		for (var i = 0; i < ROUTES.length; i++) {
			m = ROUTES[i][0].exec(path);
			if (m) { hit = ROUTES[i]; break; }
		}
		if (!hit) {
			location.replace('#/');
			return;
		}
		view.act = {};
		view.change = {};
		qsa('.tab').forEach(function (t) { t.classList.toggle('on', t.getAttribute('data-tab') === hit[1]); });
		window.scrollTo(0, 0);
		var token = route.n = (route.n || 0) + 1;
		var p;
		try {
			p = hit[2].apply(null, m.slice(1));
		} catch (e) {
			p = Promise.reject(e);
		}
		Promise.resolve(p).catch(function (e) {
			if (token === route.n) { fail(e); }
		});
	}

	function refreshCounts() {
		if (OPTS.refresh) {
			Promise.resolve(OPTS.refresh()).then(setBadges, function () {});
		}
	}

	/* ---------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */
	function start(opts) {
		OPTS = opts || {};
		TABS = OPTS.tabs || [];
		ROUTES = (OPTS.routes || []).concat([[/^\/settings$/, OPTS.settingsTab || 'more', settingsScreen]]);
		shell();
		window.addEventListener('hashchange', function () { state.navigated = true; route(); });
		route();
		if (location.hash.replace(/^#\/?/, '')) { refreshCounts(); }

		if ('serviceWorker' in navigator) {
			swReg().catch(function () {});
			navigator.serviceWorker.addEventListener('message', function (e) {
				if (e.data && e.data.type === 'navigate' && e.data.url) {
					var h = String(e.data.url).split('#')[1];
					if (h) {
						location.hash = h;
					}
					refreshCounts();
				}
			});
		}
		window.addEventListener('online', function () { if (qs('[data-act="retry"]')) { route(); } });
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			state.installEvt = e;
		});
		var hiddenAt = 0;
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				hiddenAt = Date.now();
			} else if (hiddenAt && Date.now() - hiddenAt > 60000) {
				refreshCounts();
				var path = (location.hash || '#/').replace(/^#/, '') || '/';
				if ((OPTS.live || ['/']).indexOf(path) !== -1) { route(); }
			}
		});
	}

	window.CelbCore = {
		CFG: CFG, state: state, view: view,
		esc: esc, qs: qs, qsa: qsa, ic: ic, icons: ICONS, initials: initials, av: av, pill: pill,
		MONTHS: MONTHS, DAYS: DAYS, parseDate: parseDate, ymd: ymd, fmtDate: fmtDate, fmtTime: fmtTime, ago: ago, dayLabel: dayLabel,
		tone: tone, label: label, toast: toast, copy: copy, share: share, sheet: sheet, closeSheet: closeSheet,
		api: api, topbar: topbar, page: page, loading: loading, fail: fail, empty: empty, card: card, sec: sec, row: row,
		sw: sw, dl: dl, stack: stack, agenda: agenda, evRow: evRow, setBadges: setBadges,
		route: route, refreshCounts: refreshCounts, start: start
	};
}());
