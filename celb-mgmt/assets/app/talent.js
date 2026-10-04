/* =========================================================================
   CELB Talent — the talent's own app (screens). Shared plumbing: core.js.
   ========================================================================= */
(function () {
	'use strict';

	var C = window.CelbCore;
	var CFG = C.CFG, state = C.state, view = C.view;
	var esc = C.esc, qs = C.qs, ic = C.ic, av = C.av, pill = C.pill;
	var MONTHS = C.MONTHS, parseDate = C.parseDate, ymd = C.ymd, fmtDate = C.fmtDate, fmtTime = C.fmtTime;
	var tone = C.tone, label = C.label, toast = C.toast, copy = C.copy, share = C.share, sheet = C.sheet, closeSheet = C.closeSheet;
	var api = C.api, topbar = C.topbar, page = C.page, loading = C.loading, empty = C.empty, card = C.card, sec = C.sec, row = C.row;
	var dl = C.dl, agenda = C.agenda, setBadges = C.setBadges, route = C.route;

	var TYPES = ['Unavailable', 'Personal', 'Program', 'Podcast', 'Interview', 'Filming', 'TV Appearance', 'Meeting', 'Photoshoot', 'Event', 'Brand Campaign', 'Other'];
	var S = {};

	function blockBtn(cls) {
		return '<button class="btn' + (cls ? ' ' + cls : '') + '" data-act="block">' + ic('plus') + 'Block time</button>';
	}

	/* Add / edit an entry the talent owns. */
	function blockSheet(d, done) {
		d = d || {};
		var today = ymd(new Date());
		var types = TYPES.map(function (t) { return '<option' + ((d.type || 'Unavailable') === t ? ' selected' : '') + '>' + esc(t) + '</option>'; }).join('');
		var loc = d.location || {};
		sheet(d.id ? 'Edit entry' : 'Block time',
			'<p class="row-s" style="white-space:normal;margin:-6px 0 6px">The agency sees this on your calendar so they don’t book you then.</p>' +
			'<div class="field"><label>Type</label><select class="input" data-k="type">' + types + '</select></div>' +
			'<div class="field"><label>Title (optional)</label><input class="input" data-k="title" value="' + esc(d.title || '') + '" placeholder="e.g. Travelling"></div>' +
			'<div class="field"><label>Date</label><input class="input" type="date" data-k="date" value="' + esc(d.date || today) + '"></div>' +
			'<div class="field"><label>Start time (leave empty for all day)</label><input class="input" type="time" data-k="time" value="' + esc(d.time || '') + '"></div>' +
			'<div class="field"><label>Hours</label><input class="input" type="number" min="0" step="0.5" data-k="hours" value="' + esc(d.duration ? d.duration / 60 : '') + '" placeholder="e.g. 3"></div>' +
			'<div class="field"><label>Place (optional)</label><input class="input" data-k="place" value="' + esc(loc.label || '') + '"></div>' +
			'<div class="field"><label>Note (optional)</label><textarea class="input" data-k="note">' + esc(d.description || '') + '</textarea></div>' +
			'<div class="actions" style="margin-top:16px"><button class="btn block primary" data-go>' + ic('check') + (d.id ? 'Save' : 'Add to my calendar') + '</button></div>',
			function (sh) {
				qs('[data-go]', sh).addEventListener('click', function (e) {
					var b = e.currentTarget, data = {};
					['type', 'title', 'date', 'time', 'hours', 'place', 'note'].forEach(function (k) { data[k] = qs('[data-k="' + k + '"]', sh).value; });
					if (!data.date) { toast('Pick a date'); return; }
					b.disabled = true;
					api(d.id ? 'sched/' + d.id : 'sched', data).then(function (r) {
						closeSheet();
						toast(d.id ? 'Saved' : 'Added to your calendar');
						if (done) { done(r); }
					}).catch(function (err) { b.disabled = false; toast(err.message); });
				});
			});
	}

	/* Home ------------------------------------------------------------- */
	S.home = function () {
		topbar(CFG.name, { right: '<a class="iconbtn" href="#/settings" aria-label="Settings">' + ic('gear') + '</a>' });
		loading();
		view.act.block = function () { blockSheet(null, function () { route(); }); };
		return api('home').then(function (d) {
			state.home = d;
			state.badges.contracts = d.counts.sign;
			setBadges();
			var c = d.counts, me = d.me || {};
			var h = new Date().getHours();
			var greet = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
			var tiles = [
				{ href: '#/calendar', icon: 'cal', n: c.today, l: 'Today', dark: true },
				{ href: '#/calendar', icon: 'clock', n: c.week, l: 'In the next 7 days' },
				{ href: '#/contracts', icon: 'doc', n: c.sign, l: 'Contracts to sign', flag: c.sign > 0 },
				{ href: '#/projects', icon: 'film', n: c.projects, l: 'Active projects' }
			];
			var sign = d.to_sign.length ? sec('Waiting for your signature') + '<section class="card list">' + d.to_sign.map(function (k) {
				return row({ lead: '<span class="menu"><span class="ic">' + ic('doc') + '</span></span>', title: k.template || k.title, sub: esc(k.fee || k.title),
					end: '<a class="btn sm primary" href="' + esc(k.sign) + '" target="_blank" rel="noopener">Review & sign</a>' });
			}).join('') + '</section>' : '';
			page(
				'<div class="hero" style="margin-bottom:18px">' + av(me.photo, me.name, 'lg') + '<div><p style="margin:0">' + esc(fmtDate(d.today, true)) + '</p><h2>' + esc(greet) + ', ' + esc(CFG.user.first) + '</h2></div></div>' +
				'<div class="tiles">' + tiles.map(function (t) {
					return '<a class="tile' + (t.dark ? ' dark' : '') + '" href="' + t.href + '"><span class="ic">' + ic(t.icon) + '</span>' + (t.flag ? '<i class="flag"></i>' : '') + '<b>' + esc(t.n) + '</b><span>' + esc(t.l) + '</span></a>';
				}).join('') + '</div>' +
				sign +
				sec('Coming up', '<a href="#/calendar">Calendar</a>') +
				agenda(d.agenda, d.today, { emptyText: 'Nothing booked in the next 30 days.' }) +
				'<div class="actions" style="margin-top:14px">' + blockBtn('block') + '</div>'
			);
		});
	};

	/* Calendar --------------------------------------------------------- */
	S.calendar = function () {
		topbar('My calendar', { right: '<button class="iconbtn" data-act="block" aria-label="Block time">' + ic('plus') + '</button>' });
		loading();
		var range = state.calRange || 'upcoming';
		function load() {
			var t = new Date(), from, to;
			if (range === 'past') {
				from = new Date(t.getFullYear(), t.getMonth(), t.getDate() - 90);
				to = new Date(t.getFullYear(), t.getMonth(), t.getDate() - 1);
			} else {
				from = new Date(t.getFullYear(), t.getMonth(), t.getDate());
				to = new Date(t.getFullYear(), t.getMonth(), t.getDate() + 120);
			}
			return api('calendar?from=' + ymd(from) + '&to=' + ymd(to)).then(function (d) {
				var items = range === 'past' ? d.items.slice().reverse() : d.items;
				page('<div class="chips">' + [['upcoming', 'Upcoming'], ['past', 'Past']].map(function (c) {
					return '<button class="chip' + (range === c[0] ? ' on' : '') + '" data-act="range" data-v="' + c[0] + '">' + c[1] + '</button>';
				}).join('') + '</div>' +
				agenda(items, d.today, { emptyText: range === 'past' ? 'Nothing in the last 90 days.' : 'Nothing booked yet.' }) +
				(range === 'past' ? '' : '<div class="actions" style="margin-top:14px">' + blockBtn('block') + '</div>'));
			});
		}
		view.act.range = function (el) { range = state.calRange = el.getAttribute('data-v'); loading(); load().catch(C.fail); };
		view.act.block = function () { blockSheet(null, function () { load(); }); };
		return load();
	};

	S.sched = function (id) {
		topbar('Booking', { back: '#/calendar' });
		loading();
		function draw(d) {
			var st = d.status || 'Upcoming';
			var loc = d.location || {};
			var atts = (d.attachments || []).map(function (a) {
				return row({ href: a.url, attrs: ' target="_blank" rel="noopener"', lead: a.thumb ? '<span class="av sq" style="background-image:url(\'' + esc(a.thumb) + '\')"></span>' : '<span class="menu"><span class="ic">' + ic('doc') + '</span></span>', title: a.name, sub: esc(a.kind) });
			}).join('');
			page(
				'<div class="hero"><span class="menu"><span class="ic" style="width:56px;height:56px;border-radius:16px">' + ic(d.mine ? 'clock' : 'cal') + '</span></span><div><h2>' + esc(d.title) + '</h2><p>' + esc(d.type) + ' · ' + pill(st, tone(st)) + '</p></div></div>' +
				(st === 'Postponed' && d.new_date ? '<div class="note warn" style="margin-bottom:12px">Moved to ' + esc(fmtDate(d.new_date, true)) + (d.new_time ? ' at ' + esc(fmtTime(d.new_time)) : '') + '</div>' : '') +
				(st === 'Canceled' ? '<div class="note warn" style="margin-bottom:12px">This booking was cancelled.</div>' : '') +
				'<div class="actions">' +
					(loc.map ? '<a class="btn primary" href="' + esc(loc.map) + '" target="_blank" rel="noopener">' + ic('pin') + 'Directions</a>' : '') +
					(d.ics ? '<a class="btn" href="' + esc(d.ics) + '">' + ic('cal') + 'Add to my phone calendar</a>' : '') +
				'</div>' +
				card('<div class="card-b">' + dl([
					['Date', esc(fmtDate(d.date, true))],
					['Time', esc(fmtTime(d.time) || 'All day')],
					['Duration', d.duration ? esc((d.duration / 60) + ' h') : ''],
					['Where', esc([loc.label, loc.address].filter(Boolean).join(' — '))]
				]) + '</div>') +
				(d.description ? sec(d.mine ? 'Note' : 'Details') + card('<div class="card-b prose">' + esc(d.description) + '</div>') : '') +
				(d.prep ? sec('How to prepare') + card('<div class="card-b prose">' + esc(d.prep) + '</div>') : '') +
				(atts ? sec('Attachments') + '<section class="card list">' + atts + '</section>' : '') +
				(d.mine ? '<div class="actions" style="margin-top:18px"><button class="btn primary" data-act="edit">' + ic('gear') + 'Edit</button><button class="btn danger" data-act="del">' + ic('x') + 'Remove</button></div>' :
					'<p class="row-s" style="white-space:normal;margin-top:16px">Booked by the agency. Contact your manager to change it.</p>')
			);
			view.act.edit = function () { blockSheet(d, draw); };
			view.act.del = function () {
				if (!window.confirm('Remove this from your calendar?')) { return; }
				api('sched/' + id, { 'delete': true }).then(function () { toast('Removed'); location.hash = '#/calendar'; }).catch(function (e) { toast(e.message); });
			};
		}
		return api('sched/' + id).then(draw);
	};

	/* Projects --------------------------------------------------------- */
	S.projects = function () {
		topbar('My projects');
		loading();
		return api('projects').then(function (list) {
			page(list.length ? '<section class="card list">' + list.map(function (p) {
				var pct = p.total ? Math.round(p.done / p.total * 100) : 0;
				return row({
					href: '#/project/' + p.id,
					lead: av('', p.name, 'sq'),
					title: p.name,
					sub: esc([p.company, p.type].filter(Boolean).join(' · ')) + (p.total ? '<div class="meter' + (pct === 100 ? ' full' : '') + '" style="margin-top:7px"><i style="width:' + pct + '%"></i></div>' : ''),
					end: pill(p.status || '—', tone(p.status)) + (p.next ? '<span>Next ' + esc(fmtDate(p.next)) + '</span>' : '')
				});
			}).join('') + '</section>' : card(empty('film', 'No projects yet.')));
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
				var dt = parseDate(date);
				return '<div class="ev k-day s-' + esc(st) + '" style="cursor:default"><div class="ev-time">' + esc(dt ? dt.getDate() : '—') + '<small>' + esc(dt ? MONTHS[dt.getMonth()] : '') + '</small></div>' +
					'<div class="ev-main"><div class="ev-t">' + esc(x.label || x.title || fmtDate(date, true)) + '</div><div class="ev-s">' + esc([fmtTime(st === 'postponed' && x.new_time ? x.new_time : x.time), x.location].filter(Boolean).join(' · ')) + ' ' + pill(label(st), tone(st)) + (date === today ? ' ' + pill('Today', 'ink') : '') + '</div></div></div>';
			}).join('');
			var locs = (d.locations || []).map(function (l) {
				var map = l.map || (l.address ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(l.address) : '');
				return row({ href: map || null, attrs: map ? ' target="_blank" rel="noopener"' : '', lead: '<span class="menu"><span class="ic">' + ic('pin') + '</span></span>', title: l.label || l.address || 'Location', sub: esc(l.label ? l.address : '') });
			}).join('');
			var atts = (d.attachments || []).map(function (a) {
				return row({ href: a.url, attrs: ' target="_blank" rel="noopener"', lead: '<span class="menu"><span class="ic">' + ic('doc') + '</span></span>', title: a.name, sub: esc(a.kind) });
			}).join('');
			var ups = (d.updates || []).map(function (u) {
				return '<li><b>' + esc(u.user || '') + '</b><time>' + esc(u.time) + '</time><div>' + esc(u.text) + '</div></li>';
			}).join('');
			var pct = b.total ? Math.round(b.done / b.total * 100) : 0;
			page(
				'<div class="hero">' + av('', b.name, 'lg sq') + '<div><h2>' + esc(b.name) + '</h2><p>' + esc([b.company, b.type].filter(Boolean).join(' · ')) + '</p></div></div>' +
				card('<div class="card-b">' + dl([
					['Status', pill(b.status || '—', tone(b.status))],
					['Dates', esc([fmtDate(b.start), fmtDate(b.end)].filter(Boolean).join(' → '))],
					['Progress', b.total ? esc(b.done + ' of ' + b.total + ' days done') + '<div class="meter' + (pct === 100 ? ' full' : '') + '" style="margin-top:6px"><i style="width:' + pct + '%"></i></div>' : '']
				]) + '</div>') +
				(days ? sec('Shooting days') + '<section class="card">' + days + '</section>' : '') +
				(locs ? sec('Locations') + '<section class="card list">' + locs + '</section>' : '') +
				(d.notes ? sec('Notes') + card('<div class="card-b prose">' + esc(d.notes) + '</div>') : '') +
				(atts ? sec('Files') + '<section class="card list">' + atts + '</section>' : '') +
				(ups ? sec('Updates') + card('<div class="card-b"><ul class="log">' + ups + '</ul></div>') : '')
			);
		});
	};

	/* Contracts -------------------------------------------------------- */
	S.contracts = function () {
		topbar('My contracts');
		loading();
		return api('contracts').then(function (list) {
			state.badges.contracts = list.filter(function (c) { return c.status !== 'signed' && c.sign; }).length;
			setBadges();
			page(list.length ? '<section class="card list">' + list.map(function (c) {
				var signed = c.status === 'signed';
				return row({
					lead: '<span class="menu"><span class="ic">' + ic('doc') + '</span></span>',
					title: c.template || c.title,
					sub: esc([c.fee, signed && c.signed_at ? 'Signed ' + fmtDate(c.signed_at) : ''].filter(Boolean).join(' · ') || label(c.status)),
					end: signed ? (c.download ? '<a class="btn sm" href="' + esc(c.download) + '" target="_blank" rel="noopener">' + ic('down') + 'PDF</a>' : pill('Signed', 'green')) :
						(c.sign ? '<a class="btn sm primary" href="' + esc(c.sign) + '" target="_blank" rel="noopener">Review & sign</a>' : pill(label(c.status), tone(c.status)))
				});
			}).join('') + '</section>' : card(empty('doc', 'No contracts yet.')));
		});
	};

	/* Me --------------------------------------------------------------- */
	S.me = function () {
		topbar('Me');
		loading();
		return api('profile').then(function (t) {
			var links = [];
			if (t.smartlink) { links.push(['link', 'My smart link', t.smartlink, true]); }
			if (t.profile) { links.push(['user', 'My profile page', t.profile, true]); }
			if (t.rate) { links.push(['tag', 'My rate card', t.rate.url, true]); }
			var linkRows = links.map(function (l) {
				return row({ lead: '<span class="menu"><span class="ic">' + ic(l[0]) + '</span></span>', title: l[1], sub: esc(l[2].replace(/^\w+:\/\//, '')),
					end: '<span class="actions" style="margin:0;flex-wrap:nowrap"><button class="btn sm" data-act="copy" data-v="' + esc(l[2]) + '" aria-label="Copy">' + ic('copy') + '</button><button class="btn sm" data-act="share" data-v="' + esc(l[2]) + '" data-t="' + esc(t.name) + '" aria-label="Share">' + ic('share') + '</button></span>' });
			}).join('');
			var socials = (t.socials || []).map(function (s) {
				return row({ href: s.url, attrs: ' target="_blank" rel="noopener"', lead: '<span class="menu"><span class="ic">' + ic('ext') + '</span></span>', title: s.label, sub: esc(s.url.replace(/^https?:\/\/(www\.)?/, '')) });
			}).join('');
			page(
				'<div class="cover"' + (t.photo ? ' style="background-image:url(\'' + esc(t.photo) + '\')"' : '') + '><div class="in"><h2>' + esc(t.name) + '</h2><p>' + esc([t.role, t.nat].filter(Boolean).join(' · ')) + '</p></div></div>' +
				(t.smartlink ? '<div class="actions"><button class="btn primary" data-act="share" data-v="' + esc(t.smartlink) + '" data-t="' + esc(t.name) + '">' + ic('share') + 'Share my smart link</button></div>' : '<div class="note" style="margin-bottom:12px">Your public profile isn’t live yet — your links appear here once the agency publishes it.</div>') +
				(linkRows ? sec('My links') + '<section class="card list">' + linkRows + '</section>' : '') +
				(t.calendar ? sec('Calendar') + card('<div class="card-b"><div class="prose" style="font-size:14px">Subscribe once and every booking shows up in your phone’s calendar automatically.</div><div class="actions" style="margin:12px 0 0"><a class="btn sm primary" href="' + esc(t.calendar) + '">' + ic('cal') + 'Subscribe in Calendar</a><button class="btn sm" data-act="copy" data-v="' + esc(t.calendar) + '">' + ic('copy') + 'Copy link</button></div></div>') : '') +
				(socials ? sec('Social') + '<section class="card list">' + socials + '</section>' : '') +
				sec('App') +
				'<section class="card list menu">' +
					row({ href: '#/settings', lead: '<span class="ic">' + ic('bell') + '</span>', title: 'Notifications & install', sub: esc('Push alerts for bookings and contracts') }) +
					row({ href: CFG.logout, lead: '<span class="ic">' + ic('out') + '</span>', title: 'Log out' }) +
				'</section>' +
				'<p class="row-s" style="text-align:center;margin-top:18px">' + esc(CFG.name) + ' · v' + esc(CFG.version) + '</p>'
			);
		});
	};

	function refresh() {
		return api('home').then(function (d) {
			state.home = d;
			state.badges.contracts = d.counts.sign;
		});
	}

	C.start({
		tabs: [
			{ key: 'home', href: '#/', icon: 'home', label: 'Home' },
			{ key: 'calendar', href: '#/calendar', icon: 'cal', label: 'Calendar' },
			{ key: 'projects', href: '#/projects', icon: 'film', label: 'Projects' },
			{ key: 'contracts', href: '#/contracts', icon: 'doc', label: 'Contracts', badge: true },
			{ key: 'me', href: '#/me', icon: 'user', label: 'Me' }
		],
		routes: [
			[/^\/?$/, 'home', S.home],
			[/^\/calendar$/, 'calendar', S.calendar],
			[/^\/sched\/(\d+)$/, 'calendar', S.sched],
			[/^\/projects$/, 'projects', S.projects],
			[/^\/project\/(\d+)$/, 'projects', S.project],
			[/^\/contracts$/, 'contracts', S.contracts],
			[/^\/me$/, 'me', S.me]
		],
		settingsTab: 'me',
		settingsBack: '#/me',
		refresh: refresh,
		live: ['/', '/calendar', '/contracts']
	});
}());
