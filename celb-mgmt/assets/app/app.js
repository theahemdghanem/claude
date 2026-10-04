/* =========================================================================
   CELB Studio — manager app (screens). Shared plumbing lives in core.js.
   ========================================================================= */
(function () {
	'use strict';

	var C = window.CelbCore;
	var CFG = C.CFG, state = C.state, view = C.view;
	var esc = C.esc, qs = C.qs, qsa = C.qsa, ic = C.ic, initials = C.initials, av = C.av, pill = C.pill;
	var MONTHS = C.MONTHS, parseDate = C.parseDate, ymd = C.ymd, fmtDate = C.fmtDate, fmtTime = C.fmtTime, ago = C.ago;
	var tone = C.tone, label = C.label, toast = C.toast, copy = C.copy, share = C.share, sheet = C.sheet, closeSheet = C.closeSheet;
	var api = C.api, topbar = C.topbar, page = C.page, loading = C.loading, fail = C.fail, empty = C.empty, card = C.card, sec = C.sec, row = C.row;
	var sw = C.sw, dl = C.dl, agenda = C.agenda, setBadges = C.setBadges, route = C.route;

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
			state.badges.inbox = d.counts.inbox;
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

	var FEED_ICON = { artreq: 'inbox', booking: 'inbox', contract: 'doc', rateonb: 'tag', pdata: 'shield', block: 'cal' };
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


	function refresh() {
		return api('home').then(function (d) {
			state.home = d;
			state.unread = d.unread;
			state.badges.inbox = d.counts.inbox;
		});
	}

	C.start({
		tabs: [
			{ key: 'home', href: '#/', icon: 'home', label: 'Home' },
			{ key: 'inbox', href: '#/inbox', icon: 'inbox', label: 'Inbox', badge: true },
			{ key: 'calendar', href: '#/calendar', icon: 'cal', label: 'Calendar' },
			{ key: 'roster', href: '#/roster', icon: 'star', label: 'Roster' },
			{ key: 'more', href: '#/more', icon: 'more', label: 'More' }
		],
		routes: [
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
			[/^\/pdata$/, 'more', S.pdata]
		],
		refresh: refresh,
		live: ['/', '/inbox', '/feed']
	});
}());
