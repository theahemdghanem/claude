(function () {
	'use strict';
	var cfg = window.CELB_MANAGE || {};
	var app = document.getElementById('celb-manage-app');
	if (!app || !cfg.root) { return; }
	document.documentElement.classList.add('celb-manage-open');
	document.body.classList.add('celb-manage-open');

	var ICON = {
		dash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
		people: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M16 6.5a3 3 0 0 1 0 5.8M17 14c2.5.4 4 2.3 4 5"/></svg>',
		gear: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.6-2-3.4-2.4 1a7 7 0 0 0-1.7-1l-.3-2.5H9.5L9.2 6a7 7 0 0 0-1.7 1l-2.4-1-2 3.4L5 11a7 7 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 1.7 1l.3 2.5h4.9l.3-2.5a7 7 0 0 0 1.7-1l2.4 1 2-3.4L19 13a7 7 0 0 0 0-1z"/></svg>',
		inbox: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 13h4l2 3h6l2-3h4"/><path d="M5 13V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M3 13v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4"/></svg>',
		back: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>',
		chev: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>',
		doc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 2h7l5 5v15H6z"/><path d="M13 2v5h5"/><path d="M9 13h6M9 16.5h6"/></svg>'
	};

	var state = { view: 'dash', boot: null, list: [], search: '', editId: null, data: null, settings: null, wc: null, reqList: [], reqFilter: 0, reqOrder: 'DESC', reqCurrent: null, contractList: [], contractFilter: 0, contractStatus: '' };

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function api(method, path, body) {
		var opts = { method: method, headers: { 'X-WP-Nonce': cfg.nonce } };
		if (body instanceof FormData) { opts.body = body; }
		else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
		return fetch(cfg.root + path, opts).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok) { throw (j && j.message) ? j.message : 'Request failed'; }
				return j;
			});
		});
	}

	var toastEl;
	function toast(msg, err) {
		if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'cm-toast'; document.body.appendChild(toastEl); }
		toastEl.textContent = msg;
		toastEl.className = 'cm-toast show' + (err ? ' err' : '');
		clearTimeout(toast._t);
		toast._t = setTimeout(function () { toastEl.className = 'cm-toast'; }, 2600);
	}

	function copy(text) {
		if (navigator.clipboard) { navigator.clipboard.writeText(text).then(function () { toast('Link copied'); }); }
		else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(t); toast('Link copied'); }
	}

	/* ---------- Render ---------- */
	function render() {
		var isCeleb = state.boot && state.boot.user.mode === 'celebrity';
		var canAdmin = state.boot && state.boot.user.can_admin;
		var tabsHtml = isCeleb
			? tab('wc', 'Work Center', ICON.dash) + tab('contracts', 'Contracts', ICON.doc)
			: tab('dash', 'Dashboard', ICON.dash) + tab('list', 'Profiles', ICON.people) + tab('requests', 'Requests', ICON.inbox) + tab('contracts', 'Contracts', ICON.doc) + (canAdmin ? tab('settings', 'Settings', ICON.gear) : '');
		var html = '<div class="cm-top">' +
			'<div><h1>iLike Manage</h1>' + (state.boot ? '<div class="cm-user">' + esc(state.boot.user.name) + '</div>' : '') + '</div>' +
			'<a class="cm-logout" href="' + esc(cfg.logout) + '">Log out</a>' +
			'</div><div class="cm-body" id="cm-body"></div>' +
			'<div class="cm-tabs">' + tabsHtml + '</div>';
		app.innerHTML = html;
		var body = document.getElementById('cm-body');
		if (state.view === 'dash') { renderDash(body); }
		else if (state.view === 'list') { renderList(body); }
		else if (state.view === 'edit') { renderEdit(body); }
		else if (state.view === 'settings') { renderSettings(body); }
		else if (state.view === 'requests') { renderRequests(body); }
		else if (state.view === 'req_detail') { renderRequestDetail(body); }
		else if (state.view === 'contracts') { renderContracts(body); }
		else if (state.view.indexOf('wc') === 0) { renderWC(body); }

		Array.prototype.forEach.call(app.querySelectorAll('.cm-tab'), function (t) {
			t.addEventListener('click', function () { go(t.getAttribute('data-view')); });
		});
	}
	function tab(view, label, icon) {
		var active = state.view === view
			|| (view === 'list' && state.view === 'edit')
			|| (view === 'wc' && state.view.indexOf('wc') === 0)
			|| (view === 'myprofile' && state.view === 'edit')
			|| (view === 'requests' && state.view.indexOf('req') === 0);
		return '<button class="cm-tab' + (active ? ' is-active' : '') + '" data-view="' + view + '">' + icon + '<span>' + label + '</span></button>';
	}
	function go(view) {
		if (view === 'wc') { var u = state.boot.user; openWC(u.celeb, u.celeb_name); return; }
		if (view === 'myprofile') { openEdit(state.boot.user.celeb); return; }
		state.view = view; state.editId = null; state.data = null;
		render();
		if (view === 'list') { loadList(); }
		if (view === 'settings') { loadSettings(); }
		if (view === 'requests') { loadRequests(); }
		if (view === 'contracts') { loadContracts(); }
	}

	/* ---------- Dashboard ---------- */
	function renderDash(body) {
		var c = state.boot ? state.boot.counts : { total: 0, published: 0, drafts: 0, private: 0, news: 0 };
		var g = (state.boot && state.boot.glance) || { proj_total: 0, sched_total: 0, proj_month: [], sched_month: [] };
		var reqNew = (state.boot && state.boot.requests) ? state.boot.requests.new : 0;
		var html = '<div class="cm-stats">' +
			stat(c.total, 'Total profiles') +
			stat(c.published, 'Published', true) +
			stat(g.sched_total, 'Schedule') +
			stat(g.proj_total, 'Projects') +
			stat(c.private, 'Private') +
			stat(reqNew, 'New requests', true) +
			'</div>';

		html += '<div class="cm-section-title">This month — Schedule</div>';
		html += g.sched_month.length ? '<div class="wc-list">' + g.sched_month.map(function (s) {
			return '<button class="wc-row" data-go-celeb="' + s.celeb + '" data-go-name="' + esc(s.celeb_name) + '">' +
				'<span class="wc-row-date"><span class="d">' + fmtDate(s.date) + '</span>' + (s.time ? '<span class="t">' + esc(s.time) + '</span>' : '') + '</span>' +
				'<span class="wc-row-main"><span class="ti">' + esc(s.title) + '</span><span class="ty">' + esc([s.celeb_name, s.type].filter(Boolean).join(' · ')) + '</span></span>' +
				'<span class="cm-chevron">' + ICON.chev + '</span></button>';
		}).join('') + '</div>' : '<div class="wc-muted">Nothing scheduled for the rest of this month.</div>';

		html += '<div class="cm-section-title">This month — Projects</div>';
		html += g.proj_month.length ? '<div class="wc-cards">' + g.proj_month.map(function (p) {
			return '<button class="wc-card" data-go-celeb="' + p.celeb + '" data-go-name="' + esc(p.celeb_name) + '">' +
				'<div class="wc-card-top"><span class="wc-card-name">' + esc(p.name) + '</span>' + (p.status ? '<span class="cm-badge">' + esc(p.status) + '</span>' : '') + '</div>' +
				'<div class="wc-card-sub">' + esc([p.celeb_name, p.start].filter(Boolean).join(' · ')) + '</div></button>';
		}).join('') + '</div>' : '<div class="wc-muted">No projects starting this month.</div>';

		html += '<button class="cm-btn full cm-add" id="cm-new" style="margin-top:18px">+ New profile</button>';
		body.innerHTML = html;
		document.getElementById('cm-new').addEventListener('click', openNew);
		Array.prototype.forEach.call(body.querySelectorAll('[data-go-celeb]'), function (el) {
			el.addEventListener('click', function () { var id = parseInt(el.getAttribute('data-go-celeb'), 10); if (id) { openWC(id, el.getAttribute('data-go-name')); } });
		});
	}
	function stat(n, l, gold) {
		return '<div class="cm-stat' + (gold ? ' gold' : '') + '"><div class="n">' + (n || 0) + '</div><div class="l">' + l + '</div></div>';
	}

	/* ---------- List ---------- */
	function loadList() {
		api('GET', 'profiles' + (state.search ? '?search=' + encodeURIComponent(state.search) : '')).then(function (rows) {
			state.list = rows; if (state.view === 'list') { renderList(document.getElementById('cm-body')); }
		}).catch(function (e) { toast(e, true); });
	}
	function renderList(body) {
		var rows = state.list || [];
		var items = rows.map(function (p) {
			var badges = '<span class="cm-badge ' + (p.status === 'publish' ? 'pub' : 'draft') + '">' + (p.status === 'publish' ? 'Published' : 'Draft') + '</span>' +
				(p.locked ? '<span class="cm-badge private">Private</span>' : '');
			return '<button class="cm-item" data-id="' + p.id + '">' +
				'<span class="av" style="' + (p.thumb ? 'background-image:url(' + esc(p.thumb) + ')' : '') + '"></span>' +
				'<span class="meta"><span class="nm">' + esc(p.name) + '</span>' +
				(p.role ? '<span class="rl">' + esc(p.role) + '</span>' : '') +
				'<span class="cm-badges">' + badges + '</span></span>' +
				'<span class="cm-chevron">' + ICON.chev + '</span></button>';
		}).join('');
		body.innerHTML =
			'<input class="cm-search" id="cm-search" type="search" placeholder="Search profiles…" value="' + esc(state.search) + '" />' +
			'<button class="cm-btn full cm-add" id="cm-new">+ New profile</button>' +
			'<div class="cm-list">' + (items || '<div class="cm-empty">No profiles found.</div>') + '</div>';
		var s = document.getElementById('cm-search');
		s.addEventListener('input', function () { state.search = s.value; clearTimeout(loadList._t); loadList._t = setTimeout(loadList, 280); });
		document.getElementById('cm-new').addEventListener('click', openNew);
		Array.prototype.forEach.call(body.querySelectorAll('.cm-item'), function (it) {
			it.addEventListener('click', function () { openEdit(it.getAttribute('data-id')); });
		});
	}

	/* ---------- Edit / New ---------- */
	function openNew() {
		state.view = 'edit'; state.editId = 'new';
		state.data = { id: 0, name: '', role: '', nationality: '', bio: '', birthdate: '', show_year: false, locked: false, status: 'draft', photo: '', smartlink: '', socials: {} };
		render();
	}
	function openEdit(id) {
		state.view = 'edit'; state.editId = id; state.data = null; render();
		api('GET', 'profiles/' + id).then(function (d) { state.data = d; if (state.view === 'edit') { render(); } })
			.catch(function (e) { toast(e, true); });
	}

	function renderEdit(body) {
		if (!state.data) { body.innerHTML = '<div class="cm-empty">Loading…</div>'; return; }
		var d = state.data;
		var isNew = state.editId === 'new';
		var isMgr = !(state.boot && state.boot.user.mode === 'celebrity');
		var platforms = (state.boot && state.boot.platforms) || [];

		var socialFields = platforms.map(function (pl) {
			var v = (d.socials && d.socials[pl.key]) || '';
			return '<div class="cm-field"><label>' + esc(pl.label) + '</label>' +
				'<input class="cm-input cm-social" data-key="' + pl.key + '" type="url" placeholder="https://" value="' + esc(v) + '" /></div>';
		}).join('');

		body.innerHTML =
			'<div class="cm-bar"><button class="cm-back" id="cm-back">' + ICON.back + ' Back</button></div>' +
			'<div class="cm-title" style="margin-bottom:18px">' + (isNew ? 'New profile' : esc(d.name)) + '</div>' +

			(!isNew ? '<div class="cm-photo"><span class="pv" style="' + (d.photo ? 'background-image:url(' + esc(d.photo) + ')' : '') + '" id="cm-pv"></span>' +
				'<div class="pa"><input type="file" id="cm-photo-input" accept="image/*" style="display:none" />' +
				'<button class="cm-btn ghost sm" id="cm-photo-btn">Change photo</button></div></div>' : '') +

			'<div class="cm-field"><label>Name</label><input class="cm-input" id="f-name" value="' + esc(d.name) + '" /></div>' +
			'<div class="cm-row"><div class="cm-field"><label>Role</label><input class="cm-input" id="f-role" value="' + esc(d.role) + '" placeholder="Actress" /></div>' +
			'<div class="cm-field"><label>Nationality</label><input class="cm-input" id="f-nat" value="' + esc(d.nationality) + '" placeholder="Egyptian" /></div></div>' +
			'<div class="cm-field"><label>Biography</label><textarea class="cm-textarea" id="f-bio">' + esc(d.bio) + '</textarea></div>' +
			'<div class="cm-row"><div class="cm-field"><label>Birthdate</label><input class="cm-input" id="f-bday" type="date" value="' + esc(d.birthdate) + '" /></div>' +
			(isMgr ? '<div class="cm-field"><label>Status</label><select class="cm-select" id="f-status"><option value="draft"' + (d.status !== 'publish' ? ' selected' : '') + '>Draft</option><option value="publish"' + (d.status === 'publish' ? ' selected' : '') + '>Published</option></select></div>' : '') + '</div>' +
			toggle('f-year', 'Show birth year', d.show_year) +
			(isMgr ? toggle('f-locked', 'Keep profile private', d.locked) : '') +

			(!isNew && d.smartlink ? '<div class="cm-smartlink"><div class="u">' + esc(d.smartlink) + '</div><div class="acts">' +
				'<button class="cm-btn sm" id="cm-copy">Copy link</button>' +
				'<a class="cm-btn ghost sm" href="' + esc(d.smartlink) + '" target="_blank" rel="noopener">Preview</a>' +
				'</div></div>' : '') +

			(!isNew && d.rate && d.rate.has ? '<div class="cm-smartlink"><div class="u">' + esc(d.rate.url) + (d.rate.pw ? ' · 🔒 ' + esc(d.rate.pw) : '') + '</div><div class="acts">' +
				'<button class="cm-btn sm" id="cm-rate-copy">Copy link + password</button>' +
				'<a class="cm-btn ghost sm" href="' + esc(d.rate.url) + '" target="_blank" rel="noopener">Preview</a>' +
				'</div></div>' : '') +

			(!isNew && d.cal ? '<div class="cm-smartlink"><div class="u">📅 ' + esc(d.cal) + '</div><div class="acts">' +
				'<button class="cm-btn sm" id="cm-cal-copy">Copy calendar link</button>' +
				'</div></div>' : '') +

			'<div class="cm-section-title">Social accounts</div>' + socialFields +

			'<button class="cm-btn full" id="cm-save" style="margin-top:8px">' + (isNew ? 'Create profile' : 'Save changes') + '</button>' +
			(!isNew ? '<button class="cm-btn full cm-wc-btn" id="cm-wc" style="margin-top:10px">⧉ Open Work Center</button>' : '') +
			(!isNew ? '<button class="cm-btn ghost full" id="cm-view" style="margin-top:10px">View on site</button>' : '');

		document.getElementById('cm-back').addEventListener('click', function () { go('list'); });
		document.getElementById('cm-save').addEventListener('click', save);
		var copyBtn = document.getElementById('cm-copy');
		if (copyBtn) { copyBtn.addEventListener('click', function () { copy(d.smartlink); }); }
		var rateCopyBtn = document.getElementById('cm-rate-copy');
		if (rateCopyBtn) { rateCopyBtn.addEventListener('click', function () { copy(d.rate.url + (d.rate.pw ? '\nPassword: ' + d.rate.pw : '')); }); }
		var calCopyBtn = document.getElementById('cm-cal-copy');
		if (calCopyBtn) { calCopyBtn.addEventListener('click', function () { copy(d.cal); }); }
		var viewBtn = document.getElementById('cm-view');
		if (viewBtn) { viewBtn.addEventListener('click', function () { window.open(d.view_url || d.smartlink, '_blank'); }); }
		var wcBtn = document.getElementById('cm-wc');
		if (wcBtn) { wcBtn.addEventListener('click', function () { openWC(d.id, d.name); }); }
		var pbtn = document.getElementById('cm-photo-btn');
		if (pbtn) {
			var pinput = document.getElementById('cm-photo-input');
			pbtn.addEventListener('click', function () { pinput.click(); });
			pinput.addEventListener('change', function () { if (pinput.files && pinput.files[0]) { uploadPhoto(pinput.files[0]); } });
		}
	}

	function toggle(id, label, on) {
		return '<div class="cm-toggle"><span>' + label + '</span><label class="cm-switch"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + ' /><span class="sl"></span></label></div>';
	}

	function collectForm() {
		var socials = {};
		Array.prototype.forEach.call(document.querySelectorAll('.cm-social'), function (i) { socials[i.getAttribute('data-key')] = i.value; });
		var out = {
			name: val('f-name'), role: val('f-role'), nationality: val('f-nat'),
			bio: val('f-bio'), birthdate: val('f-bday'),
			show_year: checked('f-year'), socials: socials
		};
		if (document.getElementById('f-status')) { out.status = val('f-status'); }
		if (document.getElementById('f-locked')) { out.locked = checked('f-locked'); }
		return out;
	}
	function val(id) { var e = document.getElementById(id); return e ? e.value : ''; }
	function checked(id) { var e = document.getElementById(id); return e ? e.checked : false; }

	function save() {
		var btn = document.getElementById('cm-save');
		var payload = collectForm();
		if (!payload.name.trim()) { toast('Name is required', true); return; }
		btn.disabled = true; btn.textContent = 'Saving…';
		var isNew = state.editId === 'new';
		var req = isNew ? api('POST', 'profiles', payload) : api('POST', 'profiles/' + state.editId, payload);
		req.then(function (d) {
			state.data = d; state.editId = String(d.id);
			toast(isNew ? 'Profile created' : 'Saved');
			render();
		}).catch(function (e) { toast(e, true); btn.disabled = false; btn.textContent = isNew ? 'Create profile' : 'Save changes'; });
	}

	function uploadPhoto(file) {
		var fd = new FormData(); fd.append('file', file);
		toast('Uploading…');
		api('POST', 'profiles/' + state.editId + '/photo', fd).then(function (r) {
			state.data.photo = r.photo;
			var pv = document.getElementById('cm-pv'); if (pv) { pv.style.backgroundImage = 'url(' + r.photo + ')'; }
			toast('Photo updated');
		}).catch(function (e) { toast(e, true); });
	}

	/* ---------- Settings ---------- */
	function loadSettings() {
		api('GET', 'settings').then(function (s) { state.settings = s; if (state.view === 'settings') { render(); } })
			.catch(function (e) { toast(e, true); });
	}
	function renderSettings(body) {
		if (!state.settings) { body.innerHTML = '<div class="cm-empty">Loading…</div>'; return; }
		var s = state.settings;
		body.innerHTML =
			'<div class="cm-title" style="margin-bottom:18px">Settings</div>' +
			'<div class="cm-field"><label>Accent colour</label><input class="cm-input" id="s-accent" value="' + esc(s.accent || '') + '" placeholder="#999999" /></div>' +
			'<div class="cm-field"><label>Theme</label><select class="cm-select" id="s-theme"><option value="light"' + (s.theme !== 'dark' ? ' selected' : '') + '>Light</option><option value="dark"' + (s.theme === 'dark' ? ' selected' : '') + '>Dark</option></select></div>' +
			'<div class="cm-row"><div class="cm-field"><label>Grid columns</label><input class="cm-input" id="s-grid" type="number" min="2" max="4" value="' + esc(s.grid_cols || 3) + '" /></div>' +
			'<div class="cm-field"><label>Carousel items</label><input class="cm-input" id="s-cn" type="number" min="2" max="8" value="' + esc(s.carousel_items || 6) + '" /></div></div>' +
			toggle('s-va', 'Carousel “View all” button', !!s.carousel_viewall) +
			'<div class="cm-field"><label>View-all label</label><input class="cm-input" id="s-va-label" value="' + esc(s.carousel_viewall_label || '') + '" /></div>' +
			'<div class="cm-field"><label>View-all link</label><input class="cm-input" id="s-va-url" type="url" value="' + esc(s.carousel_viewall_url || '') + '" /></div>' +
			toggle('s-cta', 'Profile “Let’s Talk” button', !!s.cta_show) +
			'<div class="cm-field"><label>Let’s Talk label</label><input class="cm-input" id="s-cta-label" value="' + esc(s.cta_label || '') + '" /></div>' +
			'<div class="cm-field"><label>Let’s Talk link</label><input class="cm-input" id="s-cta-url" type="url" value="' + esc(s.cta_url || '') + '" /></div>' +
			'<button class="cm-btn full" id="s-save" style="margin-top:8px">Save settings</button>';
		document.getElementById('s-save').addEventListener('click', saveSettings);
	}
	function saveSettings() {
		var btn = document.getElementById('s-save');
		btn.disabled = true; btn.textContent = 'Saving…';
		var payload = {
			accent: val('s-accent'), theme: val('s-theme'),
			grid_cols: parseInt(val('s-grid'), 10) || 3, carousel_items: parseInt(val('s-cn'), 10) || 6,
			carousel_viewall: checked('s-va') ? 1 : 0, carousel_viewall_label: val('s-va-label'), carousel_viewall_url: val('s-va-url'),
			cta_show: checked('s-cta') ? 1 : 0, cta_label: val('s-cta-label'), cta_url: val('s-cta-url')
		};
		api('POST', 'settings', payload).then(function (s) { state.settings = s; toast('Settings saved'); render(); })
			.catch(function (e) { toast(e, true); btn.disabled = false; btn.textContent = 'Save settings'; });
	}

	/* ===================== WORK CENTER ===================== */
	var REMIND = [['No reminder', 0], ['5 minutes before', 5], ['15 minutes before', 15], ['30 minutes before', 30], ['1 hour before', 60], ['2 hours before', 120], ['1 day before', 1440]];

	function pad(n) { return n < 10 ? '0' + n : '' + n; }
	function todayStr() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function sel(id, options, current) {
		return '<select class="cm-select" id="' + id + '">' + options.map(function (o) {
			return '<option' + (String(o) === String(current) ? ' selected' : '') + '>' + esc(o) + '</option>';
		}).join('') + '</select>';
	}
	function fmtDate(d) {
		if (!d) { return ''; }
		var p = String(d).split('-'); if (p.length !== 3) { return d; }
		var mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		return parseInt(p[2], 10) + ' ' + mo[parseInt(p[1], 10) - 1] + ' ' + p[0];
	}
	function wcHeader(sub) { return '<div class="wc-head"><div class="wc-name">' + esc(state.wc.celebName) + '</div><div class="wc-sub">' + esc(sub) + '</div></div>'; }
	function wcBack(label) {
		return (state.boot && state.boot.user.mode === 'celebrity') ? '' : '<div class="cm-bar"><button class="cm-back" id="wc-back">' + ICON.back + ' ' + label + '</button></div>';
	}
	function wcFooter() {
		if (!cfg.logo) { return ''; }
		return '<a class="wc-footer" href="' + esc(cfg.site || '#') + '" target="_blank" rel="noopener"><img src="' + esc(cfg.logo) + '" alt="" /></a>';
	}
	function attRow(a) {
		var icon = a.kind === 'image' ? '🖼' : a.kind === 'video' ? '🎬' : a.kind === 'pdf' ? '📄' : '📎';
		return '<div class="wc-att"><a class="wc-att-main" href="' + esc(a.url) + '" target="_blank" rel="noopener">' +
			(a.thumb ? '<span class="th" style="background-image:url(' + esc(a.thumb) + ')"></span>' : '<span class="ic">' + icon + '</span>') +
			'<span class="nm">' + esc(a.name || a.title) + '</span></a>' +
			'<button class="wc-att-del" data-att="' + a.id + '">✕</button></div>';
	}

	function openWC(id, name) {
		state.view = 'wc';
		state.wc = { celeb: id, celebName: name, boot: null, proj: null, sched: null };
		render();
		api('GET', 'workcenter/' + id).then(function (b) { state.wc.boot = b; if (state.view === 'wc') { render(); } })
			.catch(function (e) { toast(e, true); });
	}

	function renderWC(body) {
		if (state.view === 'wc') { return renderWCDash(body); }
		if (state.view === 'wc_proj') { return renderProjEditor(body); }
		if (state.view === 'wc_sched') { return renderSchedEditor(body); }
	}

	function renderWCDash(body) {
		var b = state.wc.boot;
		if (!b) { body.innerHTML = wcBack('Profile') + wcHeader('Work Center') + '<div class="cm-empty">Loading…</div>'; bindBack(); return; }
		function projCard(p) {
			var c = p.counts || {};
			return '<button class="wc-card" data-proj="' + p.id + '">' +
				'<div class="wc-card-top"><span class="wc-card-name">' + esc(p.name) + '</span>' + (p.status ? '<span class="cm-badge">' + esc(p.status) + '</span>' : '') + '</div>' +
				'<div class="wc-card-sub">' + esc([p.type, p.company].filter(Boolean).join(' · ')) + '</div>' +
				'<div class="wc-daycounts"><span><b>' + (c.total || 0) + '</b> days</span><span><b>' + (c.completed || 0) + '</b> done</span><span><b>' + (c.upcoming || 0) + '</b> upcoming</span></div>' +
				'</button>';
		}
		function schedRow(s) {
			var off = s.type === 'Unavailable' || s.type === 'Personal';
			return '<button class="wc-row' + (off ? ' wc-row--off' : '') + '" data-sched="' + s.id + '">' +
				'<span class="wc-row-date"><span class="d">' + fmtDate(s.date) + '</span>' + (s.time ? '<span class="t">' + esc(s.time) + '</span>' : '') + '</span>' +
				'<span class="wc-row-main"><span class="ti">' + esc(s.title) + '</span><span class="ty">' + esc(s.type) + '</span></span>' +
				'<span class="cm-chevron">' + ICON.chev + '</span></button>';
		}
		function section(title, content) { return '<div class="cm-section-title">' + title + '</div>' + content; }

		var html = wcBack('Profile') + wcHeader('Work Center');
		html += '<div class="wc-quick"><button class="cm-btn" id="wc-new-proj">+ Project</button><button class="cm-btn ghost" id="wc-new-sched">+ Schedule</button></div>';
		html += section('Today', b.today_agenda.length ? '<div class="wc-list">' + b.today_agenda.map(schedRow).join('') + '</div>' : '<div class="wc-muted">Nothing scheduled today.</div>');
		html += section('Upcoming projects', b.projects_up.length ? '<div class="wc-cards">' + b.projects_up.map(projCard).join('') + '</div>' : '<div class="wc-muted">No upcoming projects.</div>');
		html += section('Upcoming schedule', b.sched_up.length ? '<div class="wc-list">' + b.sched_up.map(schedRow).join('') + '</div>' : '<div class="wc-muted">Nothing upcoming.</div>');
		html += section('Past projects', b.projects_past.length ? '<div class="wc-cards">' + b.projects_past.map(projCard).join('') + '</div>' : '<div class="wc-muted">No past projects.</div>');
		html += section('Past schedule', b.sched_past.length ? '<div class="wc-list">' + b.sched_past.map(schedRow).join('') + '</div>' : '<div class="wc-muted">No past entries.</div>');
		html += wcFooter();
		body.innerHTML = html;

		bindBack();
		document.getElementById('wc-new-proj').addEventListener('click', function () { openProject('new'); });
		document.getElementById('wc-new-sched').addEventListener('click', function () { openSched('new'); });
		Array.prototype.forEach.call(body.querySelectorAll('[data-proj]'), function (el) { el.addEventListener('click', function () { openProject(el.getAttribute('data-proj')); }); });
		Array.prototype.forEach.call(body.querySelectorAll('[data-sched]'), function (el) { el.addEventListener('click', function () { openSched(el.getAttribute('data-sched')); }); });
	}
	function bindBack() {
		var bk = document.getElementById('wc-back');
		if (bk) { bk.addEventListener('click', function () { openEdit(state.wc.celeb); }); }
	}

	/* ----- Project editor ----- */
	function openProject(id) {
		state.view = 'wc_proj';
		if (id === 'new') {
			state.wc.proj = { id: 0, celeb: state.wc.celeb, name: '', type: (state.wc.boot.project_types[0] || ''), company: '', status: 'Upcoming', start: '', end: '', notes: '', locations: [], days: [], attachments: [], updates: [] };
			render();
		} else {
			state.wc.proj = null; render();
			api('GET', 'projects/' + id).then(function (d) { state.wc.proj = d; if (state.view === 'wc_proj') { render(); } }).catch(function (e) { toast(e, true); });
		}
	}
	function syncProjTop() {
		var p = state.wc.proj; if (!p) { return; }
		p.name = val('p-name'); p.company = val('p-company'); p.type = val('p-type');
		p.status = val('p-status'); p.start = val('p-start'); p.end = val('p-end'); p.notes = val('p-notes');
	}
	function dayCounts(days) {
		var t = days.length, c = 0, u = 0, today = todayStr();
		days.forEach(function (d) { if (d.status === 'completed') { c++; } else if (d.date && d.date >= today) { u++; } });
		return { total: t, completed: c, upcoming: u };
	}
	function renderProjEditor(body) {
		var p = state.wc.proj;
		if (!p) { body.innerHTML = wcHeader('Project') + '<div class="cm-empty">Loading…</div>'; return; }
		var b = state.wc.boot, isNew = !p.id, counts = dayCounts(p.days || []);

		var locs = (p.locations || []).map(function (l, i) {
			return '<div class="wc-rep"><div class="cm-field"><label>Location label</label><input class="cm-input wc-bind" data-rep="loc" data-i="' + i + '" data-f="label" value="' + esc(l.label || '') + '" placeholder="Set / Studio" /></div>' +
				'<div class="cm-field"><label>Address (for Maps)</label><input class="cm-input wc-bind" data-rep="loc" data-i="' + i + '" data-f="address" value="' + esc(l.address || '') + '" /></div>' +
				(l.map ? '<a class="wc-loc link" href="' + esc(l.map) + '" target="_blank" rel="noopener">📍 Open in Maps</a>' : '') +
				'<button class="wc-rep-del" data-del="loc" data-i="' + i + '">Remove location</button></div>';
		}).join('');

		var days = (p.days || []).map(function (d, i) {
			return '<div class="wc-rep wc-day"><div class="cm-row"><div class="cm-field"><label>Date</label><input class="cm-input wc-bind" type="date" data-rep="day" data-i="' + i + '" data-f="date" value="' + esc(d.date || '') + '" /></div>' +
				'<div class="cm-field"><label>Call time</label><input class="cm-input wc-bind" data-rep="day" data-i="' + i + '" data-f="call" value="' + esc(d.call || '') + '" placeholder="06:30" /></div></div>' +
				'<div class="cm-field"><label>Location</label><input class="cm-input wc-bind" data-rep="day" data-i="' + i + '" data-f="location" value="' + esc(d.location || '') + '" /></div>' +
				'<div class="cm-field"><label>Notes</label><input class="cm-input wc-bind" data-rep="day" data-i="' + i + '" data-f="notes" value="' + esc(d.notes || '') + '" /></div>' +
				'<label class="wc-done"><input type="checkbox" class="wc-bind" data-rep="day" data-i="' + i + '" data-f="status"' + (d.status === 'completed' ? ' checked' : '') + ' /> <span>Completed</span></label>' +
				'<button class="wc-rep-del" data-del="day" data-i="' + i + '">Remove day</button></div>';
		}).join('');

		var atts = (p.attachments || []).map(attRow).join('');
		var updates = (p.updates || []).map(function (u) {
			return '<div class="wc-update"><div class="t">' + esc(u.text) + '</div><div class="m">' + esc(u.user || '') + ' · ' + esc((u.time || '').replace('T', ' ')) + '</div></div>';
		}).join('');

		body.innerHTML =
			'<div class="cm-bar"><button class="cm-back" id="p-back">' + ICON.back + ' Work Center</button></div>' +
			wcHeader(isNew ? 'New Project' : 'Project') +
			'<div class="cm-field"><label>Project name</label><input class="cm-input" id="p-name" value="' + esc(p.name) + '" /></div>' +
			'<div class="cm-row"><div class="cm-field"><label>Type</label>' + sel('p-type', b.project_types, p.type) + '</div>' +
			'<div class="cm-field"><label>Status</label>' + sel('p-status', b.statuses, p.status) + '</div></div>' +
			'<div class="cm-field"><label>Production company</label><input class="cm-input" id="p-company" value="' + esc(p.company) + '" /></div>' +
			'<div class="cm-row"><div class="cm-field"><label>Start date</label><input class="cm-input" type="date" id="p-start" value="' + esc(p.start) + '" /></div>' +
			'<div class="cm-field"><label>End date</label><input class="cm-input" type="date" id="p-end" value="' + esc(p.end) + '" /></div></div>' +
			'<div class="cm-field"><label>Notes</label><textarea class="cm-textarea" id="p-notes">' + esc(p.notes) + '</textarea></div>' +
			'<div class="cm-section-title">Shooting locations</div>' + locs +
			'<button class="cm-btn ghost full" id="p-add-loc">+ Add location</button>' +
			'<div class="cm-section-title">Shooting days</div>' +
			'<div class="wc-daycounts big"><span><b>' + counts.total + '</b> total</span><span><b>' + counts.completed + '</b> completed</span><span><b>' + counts.upcoming + '</b> upcoming</span></div>' +
			days +
			'<button class="cm-btn ghost full" id="p-add-day" style="margin-top:8px">+ Add shooting day</button>' +
			'<div class="cm-section-title">Attachments</div>' +
			(isNew ? '<div class="wc-muted">Save the project first to add call sheets, scripts and files.</div>'
				: (atts || '<div class="wc-muted">No attachments yet.</div>') + '<input type="file" id="p-file" style="display:none" /><button class="cm-btn ghost full" id="p-add-file" style="margin-top:8px">+ Upload file</button>') +
			(!isNew ? '<div class="cm-section-title">Update history</div><div class="cm-field"><input class="cm-input" id="p-note" placeholder="Add an update note (optional)" /></div><div class="wc-updates">' + (updates || '<div class="wc-muted">No updates logged.</div>') + '</div>' : '') +
			'<button class="cm-btn full" id="p-save" style="margin-top:14px">' + (isNew ? 'Create project' : 'Save project') + '</button>' +
			(!isNew ? '<button class="cm-btn danger full" id="p-del" style="margin-top:10px">Delete project</button>' : '') +
			wcFooter();

		document.getElementById('p-back').addEventListener('click', function () { syncProjTop(); openWC(state.wc.celeb, state.wc.celebName); });
		document.getElementById('p-add-loc').addEventListener('click', function () { syncProjTop(); p.locations = p.locations || []; p.locations.push({ label: '', address: '', map: '' }); render(); });
		document.getElementById('p-add-day').addEventListener('click', function () { syncProjTop(); p.days = p.days || []; p.days.push({ date: '', call: '', location: '', status: 'scheduled', notes: '' }); render(); });
		document.getElementById('p-save').addEventListener('click', saveProject);
		var del = document.getElementById('p-del'); if (del) { del.addEventListener('click', deleteProject); }
		var addf = document.getElementById('p-add-file');
		if (addf) { var f = document.getElementById('p-file'); addf.addEventListener('click', function () { f.click(); }); f.addEventListener('change', function () { if (f.files && f.files[0]) { uploadProjFile(f.files[0]); } }); }
		bindRepeaters();
		bindAttachDelete('projects', p.id, 'proj');
	}
	function bindRepeaters() {
		var model = state.wc.proj;
		Array.prototype.forEach.call(document.querySelectorAll('.wc-bind'), function (el) {
			var ev = el.type === 'checkbox' ? 'change' : 'input';
			el.addEventListener(ev, function () {
				var rep = el.getAttribute('data-rep'), i = parseInt(el.getAttribute('data-i'), 10), f = el.getAttribute('data-f');
				var arr = rep === 'loc' ? model.locations : model.days;
				if (!arr[i]) { return; }
				if (el.type === 'checkbox') { arr[i][f] = el.checked ? 'completed' : 'scheduled'; updateDayCounts(); }
				else { arr[i][f] = el.value; if (f === 'date') { updateDayCounts(); } }
			});
		});
		Array.prototype.forEach.call(document.querySelectorAll('[data-del]'), function (el) {
			el.addEventListener('click', function () {
				syncProjTop();
				var rep = el.getAttribute('data-del'), i = parseInt(el.getAttribute('data-i'), 10);
				var arr = rep === 'loc' ? model.locations : model.days;
				arr.splice(i, 1); render();
			});
		});
	}
	function updateDayCounts() {
		var c = dayCounts(state.wc.proj.days || []);
		var box = document.querySelector('.wc-daycounts.big');
		if (box) { box.innerHTML = '<span><b>' + c.total + '</b> total</span><span><b>' + c.completed + '</b> completed</span><span><b>' + c.upcoming + '</b> upcoming</span>'; }
	}
	function bindAttachDelete(base, id, kind) {
		Array.prototype.forEach.call(document.querySelectorAll('[data-att]'), function (el) {
			el.addEventListener('click', function () {
				api('POST', base + '/' + id + '/detach', { att_id: el.getAttribute('data-att') }).then(function (r) {
					if (kind === 'proj') { state.wc.proj.attachments = r.attachments; } else { state.wc.sched.attachments = r.attachments; }
					render();
				}).catch(function (e) { toast(e, true); });
			});
		});
	}
	function saveProject() {
		syncProjTop();
		var p = state.wc.proj;
		if (!p.name.trim()) { toast('Project name is required', true); return; }
		var btn = document.getElementById('p-save'); btn.disabled = true; btn.textContent = 'Saving…';
		var payload = { celeb: state.wc.celeb, name: p.name, type: p.type, company: p.company, status: p.status, start: p.start, end: p.end, notes: p.notes, locations: p.locations, days: p.days };
		var note = val('p-note'); if (note) { payload.update_note = note; }
		var isNew = !p.id;
		(isNew ? api('POST', 'projects', payload) : api('POST', 'projects/' + p.id, payload))
			.then(function (d) { state.wc.proj = d; toast(isNew ? 'Project created' : 'Saved'); render(); })
			.catch(function (e) { toast(e, true); btn.disabled = false; btn.textContent = isNew ? 'Create project' : 'Save project'; });
	}
	function deleteProject() {
		if (!window.confirm('Delete this project? This cannot be undone.')) { return; }
		api('DELETE', 'projects/' + state.wc.proj.id).then(function () { toast('Project deleted'); openWC(state.wc.celeb, state.wc.celebName); }).catch(function (e) { toast(e, true); });
	}
	function uploadProjFile(file) {
		var fd = new FormData(); fd.append('file', file); toast('Uploading…');
		api('POST', 'projects/' + state.wc.proj.id + '/attach', fd).then(function (r) { state.wc.proj.attachments = r.attachments; toast('File added'); render(); }).catch(function (e) { toast(e, true); });
	}

	/* ----- Schedule editor ----- */
	function openSched(id) {
		state.view = 'wc_sched';
		if (id === 'new') {
			state.wc.sched = { id: 0, celeb: state.wc.celeb, title: '', type: (state.wc.boot.sched_types[0] || 'Meeting'), date: state.wc.boot.today, time: '', duration: 60, location: { label: '', address: '', map: '' }, description: '', prep: '', reminder: 0, attachments: [], ics: '' };
			render();
		} else {
			state.wc.sched = null; render();
			api('GET', 'sched/' + id).then(function (d) { if (!d.location) { d.location = { label: '', address: '', map: '' }; } state.wc.sched = d; if (state.view === 'wc_sched') { render(); } }).catch(function (e) { toast(e, true); });
		}
	}
	function renderSchedEditor(body) {
		var s = state.wc.sched;
		if (!s) { body.innerHTML = wcHeader('Schedule') + '<div class="cm-empty">Loading…</div>'; return; }
		var b = state.wc.boot, isNew = !s.id, loc = s.location || {};
		var atts = (s.attachments || []).map(attRow).join('');
		var remOpts = REMIND.map(function (r) { return '<option value="' + r[1] + '"' + (r[1] === s.reminder ? ' selected' : '') + '>' + esc(r[0]) + '</option>'; }).join('');

		body.innerHTML =
			'<div class="cm-bar"><button class="cm-back" id="s-back">' + ICON.back + ' Work Center</button></div>' +
			wcHeader(isNew ? 'New Schedule' : 'Schedule') +
			'<div class="cm-field"><label>Title</label><input class="cm-input" id="s-title" value="' + esc(s.title) + '" /></div>' +
			'<div class="cm-field"><label>Type</label>' + sel('s-type', b.sched_types, s.type) + '</div>' +
			'<div class="cm-row"><div class="cm-field"><label>Date</label><input class="cm-input" type="date" id="s-date" value="' + esc(s.date) + '" /></div>' +
			'<div class="cm-field"><label>Time</label><input class="cm-input" type="time" id="s-time" value="' + esc(s.time) + '" /></div></div>' +
			'<div class="cm-field"><label>Duration (minutes)</label><input class="cm-input" type="number" min="0" id="s-dur" value="' + esc(s.duration || '') + '" /></div>' +
			'<div class="cm-field"><label>Location label</label><input class="cm-input" id="s-loc-label" value="' + esc(loc.label || '') + '" placeholder="Studio / Venue" /></div>' +
			'<div class="cm-field"><label>Address (for Maps)</label><input class="cm-input" id="s-loc-addr" value="' + esc(loc.address || '') + '" /></div>' +
			(loc.map ? '<a class="wc-loc link" href="' + esc(loc.map) + '" target="_blank" rel="noopener">📍 Open in Maps</a>' : '') +
			'<div class="cm-field"><label>Description</label><textarea class="cm-textarea" id="s-desc">' + esc(s.description) + '</textarea></div>' +
			'<div class="cm-field"><label>Preparation notes</label><textarea class="cm-textarea" id="s-prep">' + esc(s.prep) + '</textarea></div>' +
			'<div class="cm-field"><label>Reminder</label><select class="cm-select" id="s-rem">' + remOpts + '</select></div>' +
			'<div class="cm-section-title">Attachments</div>' +
			(isNew ? '<div class="wc-muted">Save the entry first to add files.</div>'
				: (atts || '<div class="wc-muted">No attachments yet.</div>') + '<input type="file" id="s-file" style="display:none" /><button class="cm-btn ghost full" id="s-add-file" style="margin-top:8px">+ Upload file</button>') +
			(!isNew && s.ics ? '<button class="cm-btn ghost full" id="s-cal" style="margin-top:14px">＋ Add to calendar</button>' : '') +
			'<button class="cm-btn full" id="s-save" style="margin-top:10px">' + (isNew ? 'Create entry' : 'Save entry') + '</button>' +
			(!isNew ? '<button class="cm-btn danger full" id="s-del" style="margin-top:10px">Delete entry</button>' : '') +
			wcFooter();

		document.getElementById('s-back').addEventListener('click', function () { openWC(state.wc.celeb, state.wc.celebName); });
		document.getElementById('s-save').addEventListener('click', saveSched);
		var del = document.getElementById('s-del'); if (del) { del.addEventListener('click', deleteSched); }
		var cal = document.getElementById('s-cal'); if (cal) { cal.addEventListener('click', function () { window.open(s.ics, '_blank'); }); }
		var addf = document.getElementById('s-add-file');
		if (addf) { var f = document.getElementById('s-file'); addf.addEventListener('click', function () { f.click(); }); f.addEventListener('change', function () { if (f.files && f.files[0]) { uploadSchedFile(f.files[0]); } }); }
		bindAttachDelete('sched', s.id, 'sched');
	}
	function saveSched() {
		var title = val('s-title');
		if (!title.trim()) { toast('Title is required', true); return; }
		var btn = document.getElementById('s-save'); btn.disabled = true; btn.textContent = 'Saving…';
		var payload = { celeb: state.wc.celeb, title: title, type: val('s-type'), date: val('s-date'), time: val('s-time'), duration: parseInt(val('s-dur'), 10) || 0, description: val('s-desc'), prep: val('s-prep'), reminder: parseInt(val('s-rem'), 10) || 0, location: { label: val('s-loc-label'), address: val('s-loc-addr'), map: '' } };
		var isNew = !state.wc.sched.id;
		(isNew ? api('POST', 'sched', payload) : api('POST', 'sched/' + state.wc.sched.id, payload))
			.then(function (d) { if (!d.location) { d.location = { label: '', address: '', map: '' }; } state.wc.sched = d; toast(isNew ? 'Entry created' : 'Saved'); render(); })
			.catch(function (e) { toast(e, true); btn.disabled = false; btn.textContent = isNew ? 'Create entry' : 'Save entry'; });
	}
	function deleteSched() {
		if (!window.confirm('Delete this entry?')) { return; }
		api('DELETE', 'sched/' + state.wc.sched.id).then(function () { toast('Entry deleted'); openWC(state.wc.celeb, state.wc.celebName); }).catch(function (e) { toast(e, true); });
	}
	function uploadSchedFile(file) {
		var fd = new FormData(); fd.append('file', file); toast('Uploading…');
		api('POST', 'sched/' + state.wc.sched.id + '/attach', fd).then(function (r) { state.wc.sched.attachments = r.attachments; toast('File added'); render(); }).catch(function (e) { toast(e, true); });
	}

	/* ===================== REQUESTS ===================== */
	function reqStatusLabel(s) { return s === 'in_progress' ? 'In progress' : (s === 'closed' ? 'Closed' : 'New'); }

	function loadRequests() {
		var q = 'requests?order=' + state.reqOrder + (state.reqFilter ? '&celeb=' + state.reqFilter : '');
		api('GET', q).then(function (rows) { state.reqList = rows; if (state.view === 'requests') { renderRequests(document.getElementById('cm-body')); } })
			.catch(function (e) { toast(e, true); });
	}
	function renderRequests(body) {
		var celebs = (state.boot && state.boot.celebs) || [];
		var opts = '<option value="0">All celebrities</option>' + celebs.map(function (c) { return '<option value="' + c.id + '"' + (c.id === state.reqFilter ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('');
		var rows = (state.reqList || []).map(function (r) {
			var sc = r.status === 'new' ? 'pub' : (r.status === 'closed' ? 'draft' : '');
			return '<button class="cm-item" data-req="' + r.id + '">' +
				'<span class="meta"><span class="nm">' + esc(r.name) + '</span>' +
				'<span class="rl">' + esc([r.celeb_name, r.type].filter(Boolean).join(' · ')) + '</span>' +
				'<span class="cm-badges"><span class="cm-badge ' + sc + '">' + esc(reqStatusLabel(r.status)) + '</span>' +
				(r.date ? '<span class="cm-badge">' + esc(r.date) + '</span>' : '') + '</span></span>' +
				'<span class="cm-chevron">' + ICON.chev + '</span></button>';
		}).join('');
		body.innerHTML =
			'<div class="cm-title" style="margin-bottom:14px">Requests</div>' +
			'<div class="cm-row" style="margin-bottom:14px;gap:10px"><select class="cm-select" id="rq-celeb">' + opts + '</select>' +
			'<button class="cm-btn ghost" id="rq-order" style="flex:0 0 auto">' + (state.reqOrder === 'DESC' ? 'Newest' : 'Oldest') + '</button></div>' +
			'<div class="cm-list">' + (rows || '<div class="cm-empty">No requests yet.</div>') + '</div>';
		document.getElementById('rq-celeb').addEventListener('change', function () { state.reqFilter = parseInt(this.value, 10) || 0; loadRequests(); });
		document.getElementById('rq-order').addEventListener('click', function () { state.reqOrder = (state.reqOrder === 'DESC' ? 'ASC' : 'DESC'); loadRequests(); });
		Array.prototype.forEach.call(body.querySelectorAll('[data-req]'), function (el) { el.addEventListener('click', function () { openRequest(el.getAttribute('data-req')); }); });
	}
	function openRequest(id) {
		state.view = 'req_detail'; state.reqCurrent = null; render();
		api('GET', 'requests/' + id).then(function (d) { state.reqCurrent = d; if (state.view === 'req_detail') { render(); } }).catch(function (e) { toast(e, true); });
	}
	function renderRequestDetail(body) {
		var r = state.reqCurrent;
		if (!r) { body.innerHTML = '<div class="cm-empty">Loading…</div>'; return; }
		function row(l, v) { return v ? '<div class="cm-field"><label>' + l + '</label><div class="rq-val">' + esc(v) + '</div></div>' : ''; }
		var contacts = '';
		if (r.email) { contacts += '<a class="cm-btn ghost sm" href="mailto:' + esc(r.email) + '">Email</a> '; }
		if (r.phone) {
			contacts += '<a class="cm-btn ghost sm" href="tel:' + esc(r.phone.replace(/[^0-9+]/g, '')) + '">Call</a> ' +
				'<a class="cm-btn ghost sm" href="https://wa.me/' + esc(r.phone.replace(/[^0-9]/g, '')) + '" target="_blank" rel="noopener">WhatsApp</a>';
		}
		var statusSel = '<select class="cm-select" id="rq-status"><option value="new"' + (r.status === 'new' ? ' selected' : '') + '>New</option><option value="in_progress"' + (r.status === 'in_progress' ? ' selected' : '') + '>In progress</option><option value="closed"' + (r.status === 'closed' ? ' selected' : '') + '>Closed</option></select>';
		body.innerHTML =
			'<div class="cm-bar"><button class="cm-back" id="rq-back">' + ICON.back + ' Requests</button></div>' +
			'<div class="cm-title" style="margin-bottom:4px">' + esc(r.name) + '</div>' +
			'<div class="wc-sub" style="margin-bottom:16px">' + esc([r.celeb_name, r.type].filter(Boolean).join(' · ')) + '</div>' +
			(contacts ? '<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">' + contacts + '</div>' : '') +
			row('Company', r.company) + row('Preferred date', r.date) + row('Email', r.email) + row('Phone', r.phone) +
			(r.message ? '<div class="cm-field"><label>Details</label><div class="rq-val">' + esc(r.message) + '</div></div>' : '') +
			'<div class="cm-field"><label>Status</label>' + statusSel + '</div>' +
			'<button class="cm-btn full" id="rq-save">Save status</button>';
		document.getElementById('rq-back').addEventListener('click', function () { go('requests'); });
		document.getElementById('rq-save').addEventListener('click', function () {
			var btn = this; btn.disabled = true; btn.textContent = 'Saving…';
			api('POST', 'requests/' + r.id, { status: val('rq-status') }).then(function (d) { state.reqCurrent = d; toast('Saved'); render(); })
				.catch(function (e) { toast(e, true); btn.disabled = false; btn.textContent = 'Save status'; });
		});
	}

	/* ---------- Contracts ---------- */
	function loadContracts() {
		var isMgr = state.boot && state.boot.user.mode !== 'celebrity';
		var q = 'contracts';
		if (isMgr) {
			var p = [];
			if (state.contractFilter) { p.push('celeb=' + state.contractFilter); }
			if (state.contractStatus) { p.push('status=' + state.contractStatus); }
			if (p.length) { q += '?' + p.join('&'); }
		}
		api('GET', q).then(function (rows) {
			state.contractList = rows;
			if (state.view === 'contracts') { renderContracts(document.getElementById('cm-body')); }
		}).catch(function (e) { toast(e, true); });
	}
	function renderContracts(body) {
		var isMgr = state.boot && state.boot.user.mode !== 'celebrity';
		var filters = '';
		if (isMgr) {
			var celebs = (state.boot && state.boot.celebs) || [];
			var opts = '<option value="0">All talent</option>' + celebs.map(function (c) {
				return '<option value="' + c.id + '"' + (c.id === state.contractFilter ? ' selected' : '') + '>' + esc(c.name) + '</option>';
			}).join('');
			var st = '<select class="cm-select" id="ct-status"><option value="">All statuses</option>' +
				'<option value="pending"' + (state.contractStatus === 'pending' ? ' selected' : '') + '>Pending</option>' +
				'<option value="signed"' + (state.contractStatus === 'signed' ? ' selected' : '') + '>Signed</option></select>';
			filters = '<div class="cm-row" style="margin-bottom:14px;gap:10px"><select class="cm-select" id="ct-celeb">' + opts + '</select>' + st + '</div>';
		}
		var rows = (state.contractList || []).map(function (c) {
			var signed = c.status === 'signed';
			var sc = signed ? 'pub' : 'draft';
			var primary = esc(isMgr ? (c.celeb_name || c.title) : (c.template || c.title));
			var sub = isMgr ? esc(c.template) : (signed ? 'Tap to download' : 'Tap to review & sign');
			return '<button class="cm-item" data-ct="' + c.id + '">' +
				'<span class="meta"><span class="nm">' + primary + '</span>' +
				(sub ? '<span class="rl">' + sub + '</span>' : '') +
				'<span class="cm-badges"><span class="cm-badge ' + sc + '">' + (signed ? 'Signed' : 'Pending') + '</span>' +
				(c.signed_at ? '<span class="cm-badge">' + esc(c.signed_at) + '</span>' : '') + '</span></span>' +
				'<span class="cm-chevron">' + ICON.chev + '</span></button>';
		}).join('');
		body.innerHTML =
			'<div class="cm-title" style="margin-bottom:14px">Contracts</div>' + filters +
			'<div class="cm-list">' + (rows || '<div class="cm-empty">No contracts yet.</div>') + '</div>';
		if (isMgr) {
			var cf = document.getElementById('ct-celeb');
			if (cf) { cf.addEventListener('change', function () { state.contractFilter = parseInt(this.value, 10) || 0; loadContracts(); }); }
			var cs = document.getElementById('ct-status');
			if (cs) { cs.addEventListener('change', function () { state.contractStatus = this.value; loadContracts(); }); }
		}
		Array.prototype.forEach.call(body.querySelectorAll('[data-ct]'), function (el) {
			el.addEventListener('click', function () {
				var id = parseInt(el.getAttribute('data-ct'), 10);
				var c = (state.contractList || []).filter(function (x) { return x.id === id; })[0];
				if (!c) { return; }
				if (c.status === 'signed' && c.download) { window.open(c.download, '_blank'); }
				else if (c.sign) { if (isMgr) { copy(c.sign); } else { window.open(c.sign, '_blank'); } }
				else { toast('Not available yet'); }
			});
		});
	}

	/* ---------- Boot ---------- */
	api('GET', 'bootstrap').then(function (b) {
		state.boot = b;
		if (b.user && b.user.mode === 'celebrity' && b.user.celeb) { openWC(b.user.celeb, b.user.celeb_name); }
		else { render(); loadList(); }
	})
		.catch(function (e) { app.innerHTML = '<div class="cm-empty">Could not load (' + esc(e) + '). Please refresh.</div>'; });
})();
