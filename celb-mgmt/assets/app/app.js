/* =========================================================================
   CELB Studio — app
   A small hash-routed single page app on top of /wp-json/celb-app/v1.
   ========================================================================= */
(function () {
	'use strict';

	var CFG = window.CELB_APP || {};
	var root = document.getElementById('app');
	var state = { unread: 0, inbox: 0, home: null };
	var view = { act: {}, change: {} };

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
	var TABS = [
		{ key: 'home', href: '#/', icon: 'home', label: 'Home' },
		{ key: 'inbox', href: '#/inbox', icon: 'inbox', label: 'Inbox' },
		{ key: 'calendar', href: '#/calendar', icon: 'cal', label: 'Calendar' },
		{ key: 'roster', href: '#/roster', icon: 'star', label: 'Roster' },
		{ key: 'more', href: '#/more', icon: 'more', label: 'More' }
	];

	function shell() {
		root.innerHTML = '<div class="shell">' +
			'<header class="topbar"></header>' +
			'<main class="page-wrap"></main>' +
			'<nav class="tabs" aria-label="Sections">' +
				(CFG.logo ? '<a class="brand" href="#/"><img src="' + esc(CFG.logo) + '" alt="' + esc(CFG.name) + '"></a>' : '') +
				TABS.map(function (t) {
					return '<a class="tab" data-tab="' + t.key + '" href="' + t.href + '">' + ic(t.icon) + '<span>' + t.label + '</span>' + (t.key === 'inbox' ? '<i class="badge" hidden></i>' : '') + '</a>';
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
		var b = qs('.tab[data-tab="inbox"] .badge');
		if (b) {
			b.hidden = !state.inbox;
			b.textContent = state.inbox > 99 ? '99+' : state.inbox;
		}
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

	/* ---------------------------------------------------------------------
	 * Screens
	 * ------------------------------------------------------------------ */
	var S = {};

	S.home = function () {
		topbar(CFG.name, { bell: true, right: '<a class="iconbtn" href="#/settings" aria-label="Settings">' + ic('gear') + '</a>' });
		loading();
		return api('home').then(function (d) {
			state.home = d;
			state.unread = d.unread;
			state.inbox = d.counts.inbox;
			setBadges();
			var c = d.counts;
			var h = new Date().getHours();
			var greet = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
			var tiles = [
				{ href: '#/inbox', icon: 'inbox', n: c.inbox, l: 'New in inbox', dark: true, flag: c.inbox > 0 },
				{ href: '#/calendar', icon: 'cal', n: c.today, l: c.week + ' this week' },
				{ href: '#/projects', icon: 'film', n: c.projects, l: 'Active projects' },
				{ href: '#/contracts', icon: 'doc', n: c.contracts, l: 'Contracts awaiting signature' },
				{ href: '#/roster', icon: 'star', n: c.roster, l: 'Talent on the roster' },
				{ href: '#/news', icon: 'news', n: c.news, l: c.drafts ? c.drafts + ' drafts' : 'Published stories' },
				{ href: '#/rates', icon: 'tag', n: c.rates, l: 'Live rate cards' }
			];
			if (CFG.user.admin) {
				tiles.push({ href: '#/onboarding', icon: 'link', n: c.onboard, l: 'Rate submissions to review', flag: c.onboard > 0 });
				tiles.push({ href: '#/pdata', icon: 'shield', n: c.pdata, l: 'Personal data files' });
			}
			if (tiles.length % 2) { tiles.pop(); }
			var feed = d.feed.length ? '<section class="card">' + d.feed.map(feedRow).join('') + '</section>' : card(empty('bell', 'New requests and signed contracts will show up here.'));
			page(
				'<div class="hello"><p>' + esc(fmtDate(d.today, true)) + '</p><h2>' + esc(greet) + ', ' + esc(CFG.user.first) + '</h2></div>' +
				'<div class="tiles">' + tiles.map(function (t) {
					return '<a class="tile' + (t.dark ? ' dark' : '') + '" href="' + t.href + '"><span class="ic">' + ic(t.icon) + '</span>' + (t.flag ? '<i class="flag"></i>' : '') + '<b>' + esc(t.n) + '</b><span>' + esc(t.l) + '</span></a>';
				}).join('') + '</div>' +
				sec('Coming up', '<a href="#/calendar">Calendar</a>') +
				agenda(d.agenda, d.today, { emptyText: 'Nothing on the calendar this week.' }) +
				sec('Activity', '<a href="#/feed">See all</a>') + feed
			);
		});
	};

	var FEED_ICON = { artreq: 'inbox', booking: 'inbox', contract: 'doc', rateonb: 'tag', pdata: 'shield' };
	function feedRow(e) {
		var href = '#' + (String(e.url || '').split('#')[1] || '/');
		return row({ href: href, unread: e.unread, lead: '<span class="menu"><span class="ic">' + ic(FEED_ICON[e.event] || 'bell') + '</span></span>', title: e.title, sub: esc(e.body), end: esc(ago(e.t)) });
	}

	S.feed = function () {
		topbar('Activity', { back: '#/', right: '<button class="iconbtn" data-act="read" aria-label="Mark all read">' + ic('check') + '</button>' });
		loading();
		view.act.read = function () {
			api('feed/read', {}).then(function () {
				state.unread = 0;
				setBadges();
				qsa('.row.unread').forEach(function (r) { r.classList.remove('unread'); });
				toast('All caught up');
			});
		};
		return api('feed').then(function (list) {
			page(list.length ? '<section class="card">' + list.map(feedRow).join('') + '</section>' : card(empty('bell', 'No activity yet.')));
			if (list.some(function (e) { return e.unread; })) {
				setTimeout(function () { api('feed/read', {}).then(function () { state.unread = 0; setBadges(); }); }, 1500);
			}
		});
	};

	/* Inbox ------------------------------------------------------------ */
	var inboxFilter = { kind: '', status: 'open', q: '' };
	S.inbox = function () {
		topbar('Inbox', { bell: true, sub: 'Artist & booking requests' });
		loading();
		return api('inbox').then(function (list) {
			function draw() {
				var f = inboxFilter, q = f.q.toLowerCase();
				var items = list.filter(function (i) {
					if (f.kind && i.kind !== f.kind) { return false; }
					if (f.status === 'open' && (i.status === 'closed' || i.status === 'booked')) { return false; }
					if (f.status === 'unread' && !i.unread) { return false; }
					if (q && (i.name + ' ' + i.company + ' ' + i.email + ' ' + i.excerpt + ' ' + i.artists.map(function (a) { return a.name; }).join(' ')).toLowerCase().indexOf(q) === -1) { return false; }
					return true;
				});
				var count = function (k) { return list.filter(function (i) { return !k || i.kind === k; }).length; };
				var chips = [['', 'All', count('')], ['artreq', 'Artist requests', count('artreq')], ['booking', 'Bookings', count('booking')]].map(function (c) {
					return '<button class="chip' + (f.kind === c[0] ? ' on' : '') + '" data-act="kind" data-v="' + c[0] + '">' + esc(c[1]) + ' <b>' + c[2] + '</b></button>';
				}).join('');
				var st = [['open', 'Open'], ['unread', 'Unread'], ['all', 'Everything']].map(function (c) {
					return '<button class="' + (f.status === c[0] ? 'on' : '') + '" data-act="status" data-v="' + c[0] + '">' + c[1] + '</button>';
				}).join('');
				var body = items.length ? '<section class="card list">' + items.map(function (i) {
					var who = i.all ? 'All artists' : i.artists.map(function (a) { return a.name; }).join(', ');
					return row({
						href: '#/inbox/' + i.kind + '/' + i.id,
						unread: i.unread,
						lead: av('', i.name, i.kind === 'booking' ? 'sq' : ''),
						title: i.name || '(no name)',
						sub: esc([i.company, who].filter(Boolean).join(' · ')),
						extra: i.excerpt,
						end: esc(ago(i.time)) + pill(label(i.status), tone(i.status))
					});
				}).join('') + '</section>' : card(empty('inbox', 'No requests match.'));
				page('<div class="search">' + ic('search') + '<input type="search" placeholder="Search name, brand, artist…" value="' + esc(f.q) + '" data-input="q"></div>' +
					'<div class="chips">' + chips + '</div><div class="seg" style="margin:-2px 0 14px">' + st + '</div>' + body);
			}
			view.act.kind = function (el) { inboxFilter.kind = el.getAttribute('data-v'); draw(); };
			view.act.status = function (el) { inboxFilter.status = el.getAttribute('data-v'); draw(); };
			view.change.q = function (el) {
				inboxFilter.q = el.value;
				clearTimeout(view.qt);
				view.qt = setTimeout(function () {
					var pos = el.selectionStart;
					draw();
					var n = qs('[data-input="q"]');
					n.focus();
					try { n.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
				}, 180);
			};
			draw();
		});
	};

	S.request = function (kind, id) {
		topbar(kind === 'artreq' ? 'Artist request' : 'Booking request', { back: '#/inbox' });
		loading();
		var path = 'inbox/' + kind + '/' + id;
		function draw(r) {
			var acts = [];
			if (r.email) { acts.push('<a class="btn primary" href="mailto:' + esc(r.email) + '">' + ic('mail') + 'Email</a>'); }
			if (r.wa) { acts.push('<a class="btn wa" href="https://wa.me/' + esc(r.wa) + '" target="_blank" rel="noopener">' + ic('wa') + 'WhatsApp</a>'); }
			if (r.phone) { acts.push('<a class="btn" href="tel:' + esc(r.phone.replace(/[^0-9+]/g, '')) + '">' + ic('phone') + 'Call</a>'); }
			var statuses = Object.keys(r.statuses).map(function (k) {
				return '<button class="' + (r.status === k ? 'on' : '') + '" data-act="setst" data-v="' + esc(k) + '">' + esc(r.statuses[k]) + '</button>';
			}).join('');
			var artists = r.all ? '<div class="card-b">' + pill('All artists', 'slate') + '</div>' : r.artists.map(function (a) {
				return row({ href: '#/roster/' + a.id, lead: av(a.photo, a.name), title: a.name });
			}).join('');
			var log = (r.log || []).map(function (e) {
				var what = { created: 'Request received', seen: 'Opened', status: 'Status changed', note: 'Note', reply: 'Replied', email: 'Emailed' }[e.type] || label(e.type);
				return '<li><b>' + esc(what) + '</b>' + (e.who ? ' · ' + esc(e.who) : '') + '<time>' + esc(ago(e.t)) + ' ago</time>' +
					(e.text ? (e.type === 'status' ? '<div>' + esc(r.statuses[e.text] || label(e.text)) + '</div>' : e.type === 'note' ? '<div class="note">' + esc(e.text) + '</div>' : '<div>' + esc(e.text) + '</div>') : '') + '</li>';
			}).join('');
			page(
				'<div class="hero">' + av('', r.name, 'lg' + (kind === 'booking' ? ' sq' : '')) + '<div><h2>' + esc(r.name || '(no name)') + '</h2><p>' + esc([r.company, ago(r.time) + ' ago'].filter(Boolean).join(' · ')) + '</p></div></div>' +
				(acts.length ? '<div class="actions">' + acts.join('') + '</div>' : '') +
				sec('Status') + '<div class="seg">' + statuses + '</div>' +
				sec('Message') + card('<div class="card-b prose">' + esc(r.message || '—') + '</div>') +
				sec(kind === 'artreq' ? 'Artists' : 'Talent') + '<section class="card list">' + (artists || '<div class="empty">—</div>') + '</section>' +
				sec('Contact') + card('<div class="card-b">' + dl([
					['Name', esc(r.name)],
					['Company', esc(r.company)],
					['Email', r.email ? '<a href="mailto:' + esc(r.email) + '">' + esc(r.email) + '</a>' : ''],
					['Phone', r.phone ? '<a href="tel:' + esc(r.phone) + '">' + esc(r.phone) + '</a>' : ''],
					['Type', esc(r.type || '')],
					['Date', esc(r.date ? fmtDate(r.date, true) : '')],
					['Received', esc(new Date(r.time * 1000).toLocaleString())]
				]) + '</div>') +
				(kind === 'artreq' ?
					sec('Activity') + card('<div class="card-b"><div class="field" style="margin:0"><textarea class="input" placeholder="Add an internal note…" data-input="note"></textarea></div>' +
						'<div class="actions" style="margin:10px 0 ' + (log ? '18px' : '0') + '"><button class="btn sm primary" data-act="note">' + ic('plus') + 'Add note</button><button class="btn sm" data-act="unread">Mark unread</button></div>' +
						(log ? '<ul class="log">' + log + '</ul>' : '') + '</div>') : '') +
				'<div class="actions" style="margin-top:18px"><a class="btn block" href="' + esc(r.admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Open in admin</a></div>'
			);
		}
		view.act.setst = function (el) {
			var v = el.getAttribute('data-v');
			qsa('[data-act="setst"]').forEach(function (b) { b.classList.toggle('on', b === el); });
			api(path, { status: v }).then(function (r) { draw(r); toast('Status: ' + r.statuses[r.status]); }).catch(function (e) { toast(e.message); });
		};
		view.act.note = function () {
			var t = qs('[data-input="note"]').value.trim();
			if (!t) { return; }
			api(path, { note: t }).then(function (r) { draw(r); toast('Note added'); }).catch(function (e) { toast(e.message); });
		};
		view.act.unread = function () {
			api(path, { unread: true }).then(function () { toast('Marked unread'); location.hash = '#/inbox'; });
		};
		return api(path).then(draw);
	};

	/* Calendar --------------------------------------------------------- */
	S.calendar = function () {
		topbar('Calendar', { bell: true, sub: 'Schedule & shooting days' });
		loading();
		var range = state.calRange || 'upcoming';
		function load() {
			var t = new Date(), from, to;
			if (range === 'past') {
				from = new Date(t.getFullYear(), t.getMonth(), t.getDate() - 60);
				to = new Date(t.getFullYear(), t.getMonth(), t.getDate() - 1);
			} else {
				from = new Date(t.getFullYear(), t.getMonth(), t.getDate());
				to = new Date(t.getFullYear(), t.getMonth(), t.getDate() + 90);
			}
			return api('calendar?from=' + ymd(from) + '&to=' + ymd(to)).then(function (d) {
				var items = range === 'past' ? d.items.slice().reverse() : d.items;
				var chips = [['upcoming', 'Next 90 days'], ['past', 'Past 60 days']].map(function (c) {
					return '<button class="chip' + (range === c[0] ? ' on' : '') + '" data-act="range" data-v="' + c[0] + '">' + c[1] + '</button>';
				}).join('') + '<a class="chip" href="' + esc(CFG.admin + 'post-new.php?post_type=celb_sched') + '" target="_blank" rel="noopener">' + ic('plus') + 'New entry</a>';
				page('<div class="chips">' + chips + '</div>' + agenda(items, d.today, { emptyText: range === 'past' ? 'Nothing in the last 60 days.' : 'Nothing scheduled in the next 90 days.' }));
			});
		}
		view.act.range = function (el) { range = state.calRange = el.getAttribute('data-v'); loading(); load().catch(fail); };
		return load();
	};

	S.sched = function (id) {
		topbar('Schedule', { back: '#/calendar' });
		loading();
		var path = 'sched/' + id;
		function draw(d) {
			var st = d.status || 'Upcoming';
			var loc = d.location || {};
			var statuses = d.statuses.map(function (s) {
				return '<button class="' + (s === st ? 'on' : '') + '" data-act="setst" data-v="' + esc(s) + '">' + esc(s) + '</button>';
			}).join('');
			var atts = (d.attachments || []).map(function (a) {
				return row({ href: a.url, attrs: ' target="_blank" rel="noopener"', lead: a.thumb ? '<span class="av sq" style="background-image:url(\'' + esc(a.thumb) + '\')"></span>' : '<span class="menu"><span class="ic">' + ic('doc') + '</span></span>', title: a.name, sub: esc(a.kind) });
			}).join('');
			page(
				'<div class="hero">' + (d.celeb ? av(d.celeb.photo, d.celeb.name, 'lg') : '') + '<div><h2>' + esc(d.title) + '</h2><p>' + esc([d.type, d.celeb ? d.celeb.name : ''].filter(Boolean).join(' · ')) + '</p></div></div>' +
				'<div class="actions">' +
					(loc.map ? '<a class="btn primary" href="' + esc(loc.map) + '" target="_blank" rel="noopener">' + ic('pin') + 'Directions</a>' : '') +
					(d.ics ? '<a class="btn" href="' + esc(d.ics) + '">' + ic('cal') + 'Add to calendar</a>' : '') +
				'</div>' +
				card('<div class="card-b">' + dl([
					['Date', esc(fmtDate(d.date, true))],
					['Time', esc(fmtTime(d.time))],
					['Duration', d.duration ? esc(d.duration + ' min') : ''],
					['Where', esc([loc.label, loc.address].filter(Boolean).join(' — '))],
					['Reminder', d.reminder ? esc(d.reminder + ' min before') : '']
				]) + '</div>') +
				sec('Status') + '<div class="seg">' + statuses + '</div>' +
				'<div class="pp"' + (st === 'Postponed' ? '' : ' hidden') + '><div class="field"><label>New date</label><input class="input" type="date" data-k="new_date" value="' + esc(d.new_date) + '"></div>' +
				'<div class="field"><label>New time</label><input class="input" type="time" data-k="new_time" value="' + esc(d.new_time) + '"></div>' +
				'<div class="actions" style="margin-top:10px"><button class="btn sm primary" data-act="savepp">Save new date</button></div></div>' +
				(d.description ? sec('Details') + card('<div class="card-b prose">' + esc(d.description) + '</div>') : '') +
				(d.prep ? sec('Preparation') + card('<div class="card-b prose">' + esc(d.prep) + '</div>') : '') +
				(atts ? sec('Attachments') + '<section class="card list">' + atts + '</section>' : '') +
				'<div class="actions" style="margin-top:18px"><a class="btn block" href="' + esc(d.admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Edit in admin</a></div>'
			);
		}
		function save(data, msg) {
			return api(path, data).then(function (d) { draw(d); toast(msg); }).catch(function (e) { toast(e.message); });
		}
		view.act.setst = function (el) {
			var v = el.getAttribute('data-v');
			if (v === 'Postponed') {
				qsa('[data-act="setst"]').forEach(function (b) { b.classList.toggle('on', b === el); });
				qs('.pp').hidden = false;
				return;
			}
			save({ status: v }, 'Status: ' + v + ' — talent notified');
		};
		view.act.savepp = function () {
			save({ status: 'Postponed', new_date: qs('[data-k="new_date"]').value, new_time: qs('[data-k="new_time"]').value }, 'Postponed — talent notified');
		};
		return api(path).then(draw);
	};

	/* Projects --------------------------------------------------------- */
	S.projects = function () {
		topbar('Projects', { back: '#/more' });
		loading();
		return api('projects').then(function (list) {
			var f = state.projF || 'active';
			function draw() {
				var items = list.filter(function (p) {
					return f === 'all' || (f === 'active' ? ['Ongoing', 'Upcoming'].indexOf(p.status) !== -1 : ['Ongoing', 'Upcoming'].indexOf(p.status) === -1);
				});
				page('<div class="chips">' + [['active', 'Active'], ['done', 'Finished'], ['all', 'All']].map(function (c) {
					return '<button class="chip' + (f === c[0] ? ' on' : '') + '" data-act="pf" data-v="' + c[0] + '">' + c[1] + '</button>';
				}).join('') + '</div>' +
				(items.length ? '<section class="card list">' + items.map(function (p) {
					var pct = p.total ? Math.round(p.done / p.total * 100) : 0;
					return row({
						href: '#/project/' + p.id,
						lead: p.celeb ? av(p.celeb.photo, p.celeb.name) : av('', p.name, 'sq'),
						title: p.name,
						sub: esc([p.celeb ? p.celeb.name : '', p.company, p.type].filter(Boolean).join(' · ')) + (p.total ? '<div class="meter' + (pct === 100 ? ' full' : '') + '" style="margin-top:7px"><i style="width:' + pct + '%"></i></div>' : ''),
						end: pill(p.status || '—', tone(p.status)) + (p.next ? '<span>Next ' + esc(fmtDate(p.next)) + '</span>' : (p.total ? '<span>' + p.done + '/' + p.total + ' days</span>' : ''))
					});
				}).join('') + '</section>' : card(empty('film', 'No projects here.'))));
			}
			view.act.pf = function (el) { f = state.projF = el.getAttribute('data-v'); draw(); };
			draw();
		});
	};

	S.project = function (id) {
		topbar('Project', { back: '#/projects' });
		loading();
		return api('projects/' + id).then(function (d) {
			var b = d.brief;
			var today = ymd(new Date());
			var days = (d.days || []).map(function (x) {
				var st = x.status || 'scheduled';
				var date = st === 'postponed' && x.new_date ? x.new_date : x.date;
				return '<div class="ev k-day s-' + esc(st) + '" style="cursor:default"><div class="ev-time">' + esc(date ? parseDate(date).getDate() : '—') + '<small>' + esc(date ? MONTHS[parseDate(date).getMonth()] : '') + '</small></div>' +
					'<div class="ev-main"><div class="ev-t">' + esc(x.label || x.title || fmtDate(date, true)) + '</div><div class="ev-s">' + esc([fmtTime(st === 'postponed' && x.new_time ? x.new_time : x.time), x.location].filter(Boolean).join(' · ')) + ' ' + pill(label(st), tone(st)) + (date === today ? ' ' + pill('Today', 'ink') : '') + '</div></div></div>';
			}).join('');
			var locs = (d.locations || []).map(function (l) {
				var map = l.map || (l.address ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(l.address) : '');
				return row({ href: map || null, attrs: map ? ' target="_blank" rel="noopener"' : '', lead: '<span class="menu"><span class="ic">' + ic('pin') + '</span></span>', title: l.label || l.address || 'Location', sub: esc(l.label ? l.address : '') });
			}).join('');
			var ups = (d.updates || []).map(function (u) {
				return '<li><b>' + esc(u.user || '') + '</b><time>' + esc(u.time) + '</time><div>' + esc(u.text) + '</div></li>';
			}).join('');
			var pct = b.total ? Math.round(b.done / b.total * 100) : 0;
			page(
				'<div class="hero">' + (b.celeb ? av(b.celeb.photo, b.celeb.name, 'lg') : av('', b.name, 'lg sq')) + '<div><h2>' + esc(b.name) + '</h2><p>' + esc([b.celeb ? b.celeb.name : '', b.company, b.type].filter(Boolean).join(' · ')) + '</p></div></div>' +
				card('<div class="card-b">' + dl([
					['Status', pill(b.status || '—', tone(b.status))],
					['Dates', esc([fmtDate(b.start), fmtDate(b.end)].filter(Boolean).join(' → '))],
					['Progress', b.total ? esc(b.done + ' of ' + b.total + ' days done') + '<div class="meter' + (pct === 100 ? ' full' : '') + '" style="margin-top:6px"><i style="width:' + pct + '%"></i></div>' : '']
				]) + '</div>') +
				(days ? sec('Shooting days') + '<section class="card">' + days + '</section>' : '') +
				(locs ? sec('Locations') + '<section class="card list">' + locs + '</section>' : '') +
				(d.notes ? sec('Notes') + card('<div class="card-b prose">' + esc(d.notes) + '</div>') : '') +
				(ups ? sec('Updates') + card('<div class="card-b"><ul class="log">' + ups + '</ul></div>') : '') +
				'<div class="actions" style="margin-top:18px"><a class="btn block" href="' + esc(b.admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Edit in admin</a></div>'
			);
		});
	};

	/* Roster ----------------------------------------------------------- */
	S.roster = function () {
		topbar('Roster', { bell: true });
		loading();
		return api('roster').then(function (list) {
			var f = state.rosterF || { q: '', cat: '' };
			var cats = [];
			list.forEach(function (t) { t.cats.forEach(function (c) { if (cats.indexOf(c) === -1) { cats.push(c); } }); });
			function draw(keepFocus) {
				var q = f.q.toLowerCase();
				var items = list.filter(function (t) {
					return (!f.cat || (f.cat === '_draft' ? t.status !== 'publish' : t.cats.indexOf(f.cat) !== -1)) && (!q || (t.name + ' ' + t.role + ' ' + t.nat).toLowerCase().indexOf(q) !== -1);
				});
				var chips = [['', 'All ' + list.length]].concat(cats.map(function (c) { return [c, c]; }));
				if (list.some(function (t) { return t.status !== 'publish'; })) { chips.push(['_draft', 'Drafts']); }
				var html = '<div class="search">' + ic('search') + '<input type="search" placeholder="Search talent" value="' + esc(f.q) + '" data-input="q"></div>' +
					(chips.length > 1 ? '<div class="chips">' + chips.map(function (c) {
						return '<button class="chip' + (f.cat === c[0] ? ' on' : '') + '" data-act="cat" data-v="' + esc(c[0]) + '">' + esc(c[1]) + '</button>';
					}).join('') + '</div>' : '') +
					(items.length ? '<div class="grid">' + items.map(function (t) {
						var tags = [];
						if (t.lead) { tags.push(pill('Lead', '')); }
						if (t.status !== 'publish') { tags.push(pill(label(t.status), '')); }
						if (t.locked) { tags.push(pill('Private', '')); }
						return '<a class="tcard" href="#/roster/' + t.id + '"' + (t.photo ? ' style="background-image:url(\'' + esc(t.photo) + '\')"' : '') + '>' +
							(t.photo ? '' : '<span class="mono">' + esc(initials(t.name)) + '</span>') +
							(tags.length ? '<span class="tags">' + tags.join('') + '</span>' : '') +
							'<span class="meta"><b>' + esc(t.name) + '</b><span>' + esc(t.role || t.cats.join(', ') || '—') + '</span></span>' +
							'<span class="score"><i style="width:' + (+t.score || 0) + '%"></i></span></a>';
					}).join('') + '</div>' : card(empty('star', 'No talent found.')));
				page(html);
				if (keepFocus) {
					var n = qs('[data-input="q"]');
					n.focus();
					n.setSelectionRange(n.value.length, n.value.length);
				}
			}
			view.act.cat = function (el) { f.cat = el.getAttribute('data-v'); state.rosterF = f; draw(); };
			view.change.q = function (el) {
				f.q = el.value;
				state.rosterF = f;
				clearTimeout(view.qt);
				view.qt = setTimeout(function () { draw(true); }, 180);
			};
			draw();
		});
	};

	S.talent = function (id) {
		topbar('', { back: '#/roster' });
		loading();
		var path = 'roster/' + id;
		function draw(t) {
			topbar(t.name, { back: '#/roster' });
			var score = +t.score || 0;
			var links = [];
			if (t.smartlink) { links.push(['Smart link', t.smartlink]); }
			if (t.profile) { links.push(['Profile page', t.profile]); }
			if (t.calendar) { links.push(['Calendar feed', t.calendar]); }
			var linkRows = links.map(function (l) {
				return row({ lead: '<span class="menu"><span class="ic">' + ic('link') + '</span></span>', title: l[0], sub: esc(l[1].replace(/^\w+:\/\//, '')),
					end: '<span class="actions" style="margin:0;flex-wrap:nowrap"><button class="btn sm" data-act="copy" data-v="' + esc(l[1]) + '">' + ic('copy') + '</button>' +
						(l[1].indexOf('webcal') === 0 ? '' : '<button class="btn sm" data-act="share" data-v="' + esc(l[1]) + '" data-t="' + esc(t.name) + '">' + ic('share') + '</button>') + '</span>' });
			}).join('');
			var rate = t.rate ? row({
				href: '#/rates',
				lead: '<span class="menu"><span class="ic">' + ic('tag') + '</span></span>',
				title: t.rate.title,
				sub: esc(t.rate.services + ' services' + (t.rate.haspw ? ' · password' : '')),
				end: pill(t.rate.live ? 'Live' : 'Off', t.rate.live ? 'green' : 'slate')
			}) : '<div class="empty" style="padding:22px">No rate card yet.</div>';
			var contracts = (t.contracts || []).map(contractRow).join('');
			page(
				'<div class="cover"' + (t.photo ? ' style="background-image:url(\'' + esc(t.photo) + '\')"' : '') + '><div class="in"><h2>' + esc(t.name) + '</h2><p>' + esc([t.role, t.nat].filter(Boolean).join(' · ')) + '</p></div></div>' +
				'<div class="actions">' +
					(t.smartlink ? '<button class="btn primary" data-act="share" data-v="' + esc(t.smartlink) + '" data-t="' + esc(t.name) + '">' + ic('share') + 'Share smart link</button>' : pill(label(t.status), tone(t.status))) +
					'<a class="btn" href="' + esc(t.admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Edit</a>' +
				'</div>' +
				card('<div class="card-b"><div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px"><b>Profile strength</b><b>' + score + '%</b></div>' +
					'<div class="meter' + (score >= 100 ? ' full' : '') + '"><i style="width:' + score + '%"></i></div>' +
					(t.missing.length ? '<div class="row-s" style="white-space:normal;margin-top:10px">Missing: ' + esc(t.missing.join(', ')) + '</div>' : '') + '</div>') +
				card('<div class="card-b" style="padding-top:4px;padding-bottom:4px">' +
					sw('lead', 'Lead talent', 'Featured first across the site', t.lead) +
					sw('locked', 'Private profile', 'Hide from the public roster', t.locked) + '</div>') +
				(linkRows ? sec('Links') + '<section class="card list">' + linkRows + '</section>' : '') +
				sec('Rate card') + '<section class="card list">' + rate + '</section>' +
				sec('Coming up', '<a href="' + esc(CFG.admin + 'post-new.php?post_type=celb_sched') + '" target="_blank" rel="noopener">Add</a>') + agenda(t.agenda, ymd(new Date()), { emptyText: 'Nothing in the next 90 days.' }) +
				(contracts ? sec('Contracts') + '<section class="card list">' + contracts + '</section>' : '')
			);
		}
		function toggle(key) {
			return function (el) {
				var data = {};
				data[key] = el.checked;
				api(path, data).then(function () { toast('Saved'); }).catch(function (e) { el.checked = !el.checked; toast(e.message); });
			};
		}
		view.change.lead = toggle('lead');
		view.change.locked = toggle('locked');
		return api(path).then(draw);
	};

	/* More ------------------------------------------------------------- */
	S.more = function () {
		topbar('More', { bell: true });
		var c = (state.home && state.home.counts) || {};
		var items = [
			['#/projects', 'film', 'Projects', 'Shoots and shooting days', c.projects],
			['#/news', 'news', 'Newsroom', 'Publish and unpublish stories', c.drafts ? c.drafts + ' drafts' : ''],
			['#/contracts', 'doc', 'Contracts', 'Signing links and signed copies', c.contracts ? c.contracts + ' pending' : ''],
			['#/rates', 'tag', 'Rate cards', 'Switch cards on and off, share links', c.rates ? c.rates + ' live' : '']
		];
		if (CFG.user.admin) {
			items.push(['#/onboarding', 'link', 'Rate onboarding', 'Links and submitted rates', c.onboard ? c.onboard + ' new' : '']);
			items.push(['#/pdata', 'shield', 'Personal data', 'Talent personal data submissions', '']);
		}
		items.push(['#/feed', 'bell', 'Activity', 'Everything that happened lately', '']);
		items.push(['#/settings', 'gear', 'App settings', 'Notifications and install', '']);
		var menu = function (list) {
			return '<section class="card list menu">' + list.map(function (m) {
				return row({ href: m[0], attrs: m[5] ? ' target="_blank" rel="noopener"' : '', lead: '<span class="ic">' + ic(m[1]) + '</span>', title: m[2], sub: esc(m[3]), end: m[4] ? '<span>' + esc(m[4]) + '</span>' : null });
			}).join('') + '</section>';
		};
		page(
			'<div class="hero">' + av(CFG.user.avatar, CFG.user.name, 'lg') + '<div><h2>' + esc(CFG.user.name) + '</h2><p>' + esc(CFG.site) + '</p></div></div>' +
			menu(items) +
			sec('Account') +
			menu([[CFG.admin, 'ext', 'Open wp-admin', 'The full dashboard', '', true], [CFG.logout, 'out', 'Log out', '', '']]) +
			'<p class="row-s" style="text-align:center;margin-top:18px">' + esc(CFG.name) + ' · v' + esc(CFG.version) + '</p>'
		);
		return Promise.resolve();
	};

	/* Newsroom --------------------------------------------------------- */
	S.news = function () {
		topbar('Newsroom', { back: '#/more', right: '<a class="iconbtn" href="' + esc(CFG.admin + 'post-new.php?post_type=celeb_news') + '" target="_blank" rel="noopener" aria-label="New story">' + ic('plus') + '</a>' });
		loading();
		var f = state.newsF || '';
		function draw(list) {
			var items = list.filter(function (n) { return !f || (f === 'draft' ? n.status !== 'publish' : n.status === 'publish'); });
			page('<div class="chips">' + [['', 'All'], ['publish', 'Published'], ['draft', 'Drafts']].map(function (c) {
				return '<button class="chip' + (f === c[0] ? ' on' : '') + '" data-act="nf" data-v="' + c[0] + '">' + c[1] + '</button>';
			}).join('') + '</div>' +
			(items.length ? '<section class="card list">' + items.map(function (n) {
				return row({
					attrs: ' data-act="story" data-id="' + n.id + '"',
					lead: '<span class="av sq"' + (n.thumb ? ' style="background-image:url(\'' + esc(n.thumb) + '\')"' : '') + '>' + (n.thumb ? '' : ic('news')) + '</span>',
					title: n.title || '(untitled)',
					sub: esc([fmtDate(n.date), n.celeb ? n.celeb.name : '', n.loc].filter(Boolean).join(' · ')),
					end: pill(label(n.status), tone(n.status)) + (n.ar ? '<span>EN · AR</span>' : '<span>EN</span>')
				});
			}).join('') + '</section>' : card(empty('news', 'No stories here.'))));
			view.act.story = function (el) {
				var n = list.filter(function (x) { return String(x.id) === el.getAttribute('data-id'); })[0];
				var pub = n.status === 'publish';
				sheet(n.title || '(untitled)',
					'<div class="actions" style="display:grid">' +
						'<button class="btn block primary" data-s="' + (pub ? 'draft' : 'publish') + '">' + ic(pub ? 'back' : 'send') + (pub ? 'Unpublish (move to drafts)' : 'Publish now') + '</button>' +
						(pub ? '<button class="btn block" data-share="' + esc(n.url) + '">' + ic('share') + 'Share link</button>' : '') +
						(n.url ? '<a class="btn block" href="' + esc(n.url) + '" target="_blank" rel="noopener">' + ic('ext') + (pub ? 'View story' : 'Preview') + '</a>' : '') +
						'<a class="btn block" href="' + esc(n.admin) + '" target="_blank" rel="noopener">' + ic('news') + 'Edit in admin</a>' +
					'</div>', function (sh) {
						sh.addEventListener('click', function (e) {
							var b = e.target.closest('[data-s]');
							var s = e.target.closest('[data-share]');
							if (s) { share(s.getAttribute('data-share'), n.title); }
							if (!b) { return; }
							b.disabled = true;
							api('news/' + n.id, { status: b.getAttribute('data-s') }).then(function (l) {
								closeSheet();
								toast(b.getAttribute('data-s') === 'publish' ? 'Published' : 'Moved to drafts');
								draw(l);
							}).catch(function (err) { b.disabled = false; toast(err.message); });
						});
					});
			};
		}
		return api('news').then(function (list) {
			view.act.nf = function (el) { f = state.newsF = el.getAttribute('data-v'); draw(list); };
			draw(list);
		});
	};

	/* Contracts -------------------------------------------------------- */
	function contractRow(c) {
		return row({
			attrs: ' data-act="contract" data-id="' + c.id + '"',
			lead: av(c.photo || '', c.celeb_name || c.title),
			title: c.celeb_name || c.title,
			sub: esc([c.template || c.title, c.fee].filter(Boolean).join(' · ')),
			end: pill(label(c.status), tone(c.status)) + (c.signed_at ? '<span>' + esc(fmtDate(c.signed_at)) + '</span>' : '')
		});
	}
	function contractSheet(c) {
		sheet(c.celeb_name || c.title,
			card('<div class="card-b">' + dl([['Contract', esc(c.title)], ['Template', esc(c.template)], ['Fee', esc(c.fee || '')], ['Status', pill(label(c.status), tone(c.status))], ['Signed', esc(c.signed_at ? fmtDate(c.signed_at) : '')]]) + '</div>') +
			'<div class="actions" style="display:grid;margin-top:14px">' +
				(c.sign ? '<button class="btn block primary" data-c="' + esc(c.sign) + '">' + ic('copy') + 'Copy signing link</button><button class="btn block" data-sh="' + esc(c.sign) + '">' + ic('share') + 'Send signing link</button>' : '') +
				(c.download ? '<a class="btn block' + (c.sign ? '' : ' primary') + '" href="' + esc(c.download) + '" target="_blank" rel="noopener">' + ic('down') + 'Download PDF</a>' : '') +
				(c.admin ? '<a class="btn block" href="' + esc(c.admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Edit in admin</a>' : '') +
			'</div>', function (sh) {
				sh.addEventListener('click', function (e) {
					var a = e.target.closest('[data-c]'), b = e.target.closest('[data-sh]');
					if (a) { copy(a.getAttribute('data-c')); }
					if (b) { share(b.getAttribute('data-sh'), c.title); }
				});
			});
	}
	S.contracts = function () {
		topbar('Contracts', { back: '#/more', right: '<a class="iconbtn" href="' + esc(CFG.admin + 'post-new.php?post_type=celb_contract') + '" target="_blank" rel="noopener" aria-label="New contract">' + ic('plus') + '</a>' });
		loading();
		return api('contracts').then(function (list) {
			var f = state.conF || 'pending';
			function draw() {
				var items = list.filter(function (c) { return f === 'all' || (f === 'signed' ? c.status === 'signed' : c.status !== 'signed'); });
				page('<div class="chips">' + [['pending', 'Awaiting signature'], ['signed', 'Signed'], ['all', 'All']].map(function (c) {
					return '<button class="chip' + (f === c[0] ? ' on' : '') + '" data-act="cf" data-v="' + c[0] + '">' + c[1] + '</button>';
				}).join('') + '</div>' + (items.length ? '<section class="card list">' + items.map(contractRow).join('') + '</section>' : card(empty('doc', 'No contracts here.'))));
			}
			view.act.cf = function (el) { f = state.conF = el.getAttribute('data-v'); draw(); };
			view.act.contract = function (el) {
				contractSheet(list.filter(function (c) { return String(c.id) === el.getAttribute('data-id'); })[0]);
			};
			draw();
		});
	};

	/* Rate cards ------------------------------------------------------- */
	S.rates = function () {
		topbar('Rate cards', { back: '#/more' });
		loading();
		return api('rates').then(function (list) {
			var cards = list.filter(function (r) { return !r.template; });
			var tpls = list.filter(function (r) { return r.template; });
			var rateRow = function (r) {
				return '<div class="card-b" style="border-top:1px solid var(--line)">' +
					'<div style="display:flex;gap:12px;align-items:center">' + (r.celeb ? av(r.celeb.photo, r.celeb.name) : '<span class="menu"><span class="ic">' + ic('tag') + '</span></span>') +
					'<div class="row-main"><div class="row-t"><span>' + esc(r.title) + '</span></div><div class="row-s">' + esc([r.celeb ? r.celeb.name : '', r.services + ' services', r.haspw ? 'password' : ''].filter(Boolean).join(' · ')) + '</div></div>' +
					(r.template ? pill('Template', 'slate') : pill(r.live ? 'Live' : 'Off', r.live ? 'green' : 'slate')) + '</div>' +
					(r.template ? '' : '<div style="margin-top:6px">' + sw('en-' + r.id, 'Card enabled', r.enabled && !r.live ? 'Enabled, but not live (check talent / password)' : '', r.enabled, ' data-id="' + r.id + '"') + '</div>') +
					'<div class="actions" style="margin:' + (r.template ? '12px' : '6px') + ' 0 0">' +
						(r.url ? '<button class="btn sm" data-act="copy" data-v="' + esc(r.url) + '">' + ic('copy') + 'Copy link</button><button class="btn sm" data-act="share" data-v="' + esc(r.url) + '" data-t="' + esc(r.title) + '">' + ic('share') + 'Share</button>' : '') +
						'<a class="btn sm" href="' + esc(r.admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Edit</a>' +
					'</div></div>';
			};
			page((cards.length ? card(cards.map(rateRow).join('')) : card(empty('tag', 'No rate cards yet.'))) +
				(tpls.length ? sec('Templates') + card(tpls.map(rateRow).join('')) : ''));
			qsa('.card .card-b:first-child').forEach(function (n) { n.style.borderTop = '0'; });
			cards.forEach(function (r) {
				view.change['en-' + r.id] = function (el) {
					api('rates/' + r.id, { enabled: el.checked }).then(function (n) {
						toast(n.live ? 'Card is live' : (n.enabled ? 'Enabled' : 'Card switched off'));
					}).catch(function (e) { el.checked = !el.checked; toast(e.message); });
				};
			});
		});
	};

	/* Rate onboarding -------------------------------------------------- */
	S.onboarding = function () {
		topbar('Rate onboarding', { back: '#/more' });
		loading();
		function draw(d) {
			var links = d.links.map(function (l) {
				return row({
					lead: '<span class="menu"><span class="ic">' + ic('link') + '</span></span>',
					title: l.celeb || l.src || l.token,
					sub: esc([l.celeb ? l.src : '', l.created ? ago(l.created) + ' ago' : ''].filter(Boolean).join(' · ')),
					end: '<span class="actions" style="margin:0;flex-wrap:nowrap"><button class="btn sm" data-act="copy" data-v="' + esc(l.url) + '">' + ic('copy') + '</button><button class="btn sm" data-act="share" data-v="' + esc(l.url) + '">' + ic('share') + '</button><button class="btn sm danger" data-act="revoke" data-v="' + esc(l.token) + '" aria-label="Revoke">' + ic('x') + '</button></span>'
				});
			}).join('');
			var subs = d.subs.map(function (s) {
				var rows = s.rows.slice(0, 8).map(function (r) {
					return '<div class="pricerow"><span>' + esc(r.name) + (r.section ? ' <small style="color:var(--ink-3)">· ' + esc(r.section) + '</small>' : '') + '</span><b>' + esc(Number(r.base).toLocaleString()) + ' ' + esc(s.currency) + (r.addl ? ' <small>+' + esc(Number(r.addl).toLocaleString()) + '</small>' : '') + '</b></div>';
				}).join('');
				return '<section class="card"><div class="card-h"><h4>' + esc(s.name) + '</h4>' + (s.imported ? pill('Imported', 'green') : pill('New', 'blue')) + '</div>' +
					'<div class="card-b"><div class="row-s" style="margin:-6px 0 10px">' + esc([s.src, fmtDate(s.date)].filter(Boolean).join(' · ')) + '</div>' + (rows || '<div class="row-s">No prices.</div>') +
					(s.rows.length > 8 ? '<div class="row-s" style="margin-top:6px">+' + (s.rows.length - 8) + ' more</div>' : '') +
					'<div class="actions" style="margin:12px 0 0">' + (s.imported ? '<a class="btn sm" href="' + esc(s.imported_admin) + '" target="_blank" rel="noopener">' + ic('ext') + 'Open rate card</a>' : '<button class="btn sm primary" data-act="import" data-v="' + s.id + '">' + ic('down') + 'Import as new card</button>') + '</div></div></section>';
			}).join('');
			page(
				'<button class="btn block primary" data-act="newlink">' + ic('plus') + 'New onboarding link</button>' +
				sec('Active links') + (links ? '<section class="card list">' + links + '</section>' : card(empty('link', 'No links yet. Create one and send it to talent.'))) +
				sec('Submissions') + (subs || card(empty('tag', 'Nothing submitted yet.')))
			);
			view.act.newlink = function () {
				sheet('New onboarding link',
					'<div class="field"><label>Start from</label><select class="input" data-k="src">' + d.sources.map(function (s) { return '<option value="' + s.id + '">' + esc((s.template ? 'Template — ' : '') + s.label) + '</option>'; }).join('') + '</select></div>' +
					'<div class="field"><label>Talent (optional)</label><select class="input" data-k="celeb"><option value="0">—</option>' + d.talent.map(function (t) { return '<option value="' + t.id + '">' + esc(t.name) + '</option>'; }).join('') + '</select></div>' +
					'<div class="field"><label>Custom link name (optional)</label><input class="input" data-k="slug" placeholder="e.g. summer-2026"></div>' +
					'<div class="actions" style="margin-top:16px"><button class="btn block primary" data-go>' + ic('link') + 'Create link</button></div>', function (sh) {
						qs('[data-go]', sh).addEventListener('click', function (e) {
							var b = e.currentTarget;
							b.disabled = true;
							api('onboarding/link', { src: qs('[data-k="src"]', sh).value, celeb: qs('[data-k="celeb"]', sh).value, slug: qs('[data-k="slug"]', sh).value }).then(function (nd) {
								closeSheet();
								draw(nd);
								if (nd.links[0]) { copy(nd.links[0].url); }
							}).catch(function (err) { b.disabled = false; toast(err.message); });
						});
					});
			};
			view.act.revoke = function (el) {
				if (!window.confirm('Revoke this link? It will stop working.')) { return; }
				api('onboarding/revoke', { token: el.getAttribute('data-v') }).then(function (nd) { draw(nd); toast('Link revoked'); }).catch(function (e) { toast(e.message); });
			};
			view.act['import'] = function (el) {
				el.disabled = true;
				api('onboarding/' + el.getAttribute('data-v') + '/import', { target: 'new' }).then(function (nd) { draw(nd); toast('Imported as a new rate card (off)'); }).catch(function (e) { el.disabled = false; toast(e.message); });
			};
		}
		return api('onboarding').then(draw);
	};

	/* Personal data ---------------------------------------------------- */
	S.pdata = function () {
		topbar('Personal data', { back: '#/more' });
		loading();
		return api('pdata').then(function (list) {
			var q = '';
			function val(r) {
				var v = esc(r.value);
				if (r.type === 'email' || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(r.value)) { return '<a href="mailto:' + v + '">' + v + '</a>'; }
				if (r.type === 'tel' || r.type === 'phone' || /^\+?[\d\s()-]{7,}$/.test(r.value)) { return '<a href="tel:' + esc(r.value.replace(/[^\d+]/g, '')) + '">' + v + '</a>'; }
				return v;
			}
			function draw(keep) {
				var items = list.filter(function (p) { return !q || JSON.stringify(p).toLowerCase().indexOf(q) !== -1; });
				page('<div class="note" style="margin-bottom:12px">' + ic('shield') + ' Sensitive information — only share it with people who need it.</div>' +
					'<div class="search">' + ic('search') + '<input type="search" placeholder="Search names, numbers…" value="' + esc(q) + '" data-input="q"></div>' +
					(items.length ? items.map(function (p) {
						return '<section class="card"><div class="card-h" style="padding-bottom:12px"><div style="display:flex;gap:12px;align-items:center">' + av('', p.name) + '<div><h4>' + esc(p.name) + '</h4><div class="row-s">' + esc(p.date) + '</div></div></div></div>' +
							p.sections.map(function (s) {
								return '<div class="pdsec' + (s.emergency ? ' em' : '') + '"><h5>' + esc(s.title) + '</h5>' + dl(s.rows.map(function (r) { return [r.label, val(r)]; })) + '</div>';
							}).join('') + '</section>';
					}).join('') : card(empty('shield', list.length ? 'No matches.' : 'No submissions yet.'))));
				if (keep) {
					var n = qs('[data-input="q"]');
					n.focus();
					n.setSelectionRange(n.value.length, n.value.length);
				}
			}
			view.change.q = function (el) {
				q = el.value.toLowerCase();
				clearTimeout(view.qt);
				view.qt = setTimeout(function () { draw(true); }, 200);
			};
			draw();
		});
	};

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

	S.settings = function () {
		topbar('App settings', { back: '#/more' });
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
					'<p class="row-s" style="white-space:normal;margin:8px 4px 0">These apply to every device you turn notifications on for. Events also show up in Activity.</p>' +
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
	};

	/* ---------------------------------------------------------------------
	 * Router
	 * ------------------------------------------------------------------ */
	var ROUTES = [
		[/^\/?$/, 'home', S.home],
		[/^\/feed$/, 'home', S.feed],
		[/^\/inbox$/, 'inbox', S.inbox],
		[/^\/inbox\/(artreq|booking)\/(\d+)$/, 'inbox', S.request],
		[/^\/calendar$/, 'calendar', S.calendar],
		[/^\/sched\/(\d+)$/, 'calendar', S.sched],
		[/^\/projects$/, 'more', S.projects],
		[/^\/project\/(\d+)$/, 'more', S.project],
		[/^\/roster$/, 'roster', S.roster],
		[/^\/roster\/(\d+)$/, 'roster', S.talent],
		[/^\/more$/, 'more', S.more],
		[/^\/news$/, 'more', S.news],
		[/^\/contracts$/, 'more', S.contracts],
		[/^\/rates$/, 'more', S.rates],
		[/^\/onboarding$/, 'more', S.onboarding],
		[/^\/pdata$/, 'more', S.pdata],
		[/^\/settings$/, 'more', S.settings]
	];

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
		view = { act: {}, change: {} };
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
		api('home').then(function (d) {
			state.home = d;
			state.unread = d.unread;
			state.inbox = d.counts.inbox;
			setBadges();
		}).catch(function () {});
	}

	/* ---------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */
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
			var path = (location.hash || '#/').replace(/^#/, '');
			if (path === '/' || path === '' || path === '/inbox' || path === '/feed') { route(); }
		}
	});
}());
