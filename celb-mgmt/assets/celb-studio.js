/* =========================================================================
   CELB MGMT — Studio admin
   Tabs, media pickers, galleries, repeaters (+CSV), live profile snapshot,
   newsroom language switch, roster view toggle and the settings app.
   Field names are untouched: every control is a plain form input that the
   existing save handlers read.
   ========================================================================= */
(function ($) {
	'use strict';

	var CFG = window.CELB_STUDIO || { screen: '', i18n: {} };
	var T = CFG.i18n || {};

	function t(key, fallback) { return T[key] || fallback || key; }
	function store(kind) {
		try { return window[kind]; } catch (e) { return null; }
	}
	function sget(kind, k) { var s = store(kind); try { return s ? s.getItem(k) : null; } catch (e) { return null; } }
	function sset(kind, k, v) { var s = store(kind); try { if (s) { s.setItem(k, v); } } catch (e) {} }

	/* ---------------------------------------------------------------------
	   Copy to clipboard
	   --------------------------------------------------------------------- */
	$(document).on('click', '[data-cs-copy]', function (e) {
		e.preventDefault();
		var btn = this, text = btn.getAttribute('data-cs-copy');
		if (!text) { return; }
		var done = function () {
			btn.classList.add('is-copied');
			var lbl = btn.querySelector('span');
			var old = lbl ? lbl.textContent : null;
			if (lbl && !btn.classList.contains('cs-linkchip') && !btn.classList.contains('cs-code-tag')) { lbl.textContent = t('copied', 'Copied'); }
			setTimeout(function () { btn.classList.remove('is-copied'); if (lbl && old !== null) { lbl.textContent = old; } }, 1400);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { legacyCopy(text); done(); });
		} else { legacyCopy(text); done(); }
	});
	function legacyCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
		document.body.appendChild(ta); ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		document.body.removeChild(ta);
	}

	/* ---------------------------------------------------------------------
	   Workspace tabs (editor screens)
	   --------------------------------------------------------------------- */
	function initTabs($app) {
		var key = 'cs-tab-' + $app.data('cs-app') + '-' + ($app.data('post') || 0);
		var $tabs = $app.find('[data-cs-tab]');
		var $panels = $app.find('[data-cs-panel]');

		function show(name, focus) {
			if (!$panels.filter('[data-cs-panel="' + name + '"]').length) { return; }
			$tabs.each(function () {
				var on = this.getAttribute('data-cs-tab') === name;
				this.classList.toggle('is-active', on);
				this.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			$panels.each(function () { this.classList.toggle('is-active', this.getAttribute('data-cs-panel') === name); });
			sset('sessionStorage', key, name);
			// TinyMCE and canvases measure themselves on resize.
			setTimeout(function () { $(window).trigger('resize'); }, 30);
			if (focus) {
				var bar = $app.find('.cs-tabs-bar')[0];
				if (bar && bar.getBoundingClientRect().top < 0) { bar.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			}
		}
		$tabs.on('click', function () { show(this.getAttribute('data-cs-tab'), true); });
		$(document).on('click', '[data-cs-goto]', function () {
			show(this.getAttribute('data-cs-goto'), true);
			var lang = this.getAttribute('data-cs-goto-lang');
			if (lang) { $('[data-cs-lang="' + lang + '"]').trigger('click'); }
		});

		// Keyboard: arrow keys move between tabs.
		$app.find('.cs-tabs').on('keydown', function (e) {
			if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
			var list = $tabs.toArray(), i = list.indexOf(document.activeElement);
			if (i < 0) { return; }
			var n = list[(i + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length];
			n.focus(); n.click(); e.preventDefault();
		});

		// Initial tab: #hash (tab name or an element inside a panel) → saved → default.
		var initial = null, h = (location.hash || '').replace('#', '');
		if (h) {
			if ($panels.filter('[data-cs-panel="' + h + '"]').length) { initial = h; }
			else {
				var el = document.getElementById(h);
				var p = el ? $(el).closest('[data-cs-panel]') : null;
				if (p && p.length) { initial = p.attr('data-cs-panel'); }
			}
		}
		if (!initial) { initial = sget('sessionStorage', key); }
		if (initial) { show(initial, false); }
	}

	/* Save proxy → the core Publish box buttons. */
	function initSave($app) {
		var $btn = $app.find('[data-cs-save]');
		var $pub = $('#publish'), $draft = $('#save-post');
		function label() {
			var useDraft = $draft.length && $draft.is(':visible');
			var txt = useDraft ? $draft.val() : $pub.val();
			if (txt) { $btn.find('span').text(txt); }
		}
		label();
		$btn.on('click', function () {
			if ($draft.length && $draft.is(':visible')) { $draft.trigger('click'); }
			else if ($pub.length) { $pub.trigger('click'); }
		});
		// Cmd/Ctrl + S saves.
		$(document).on('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); $btn.trigger('click'); }
		});
	}

	function setCount(panel, n) {
		var $c = $('[data-cs-count="' + panel + '"]');
		$c.text(n);
		$c.prop('hidden', !n);
	}
	function panelOf(el) { return $(el).closest('[data-cs-panel]').attr('data-cs-panel'); }

	/* ---------------------------------------------------------------------
	   Single-image pickers
	   --------------------------------------------------------------------- */
	function mediaReady() {
		if (typeof wp === 'undefined' || !wp.media) { window.alert(t('mediaLoading', 'The media library is still loading.')); return false; }
		return true;
	}
	function setMedia($m, id, url) {
		var store = $m.attr('data-store') || 'id';
		var $input = $m.find('.cs-media-input').first();
		$input.val(store === 'url' ? (url || '') : (id || '')).trigger('change');
		$m.find('.cs-media-img').first().css('background-image', url ? 'url("' + url + '")' : '');
		$m.toggleClass('has-image', !!url);
		$m.find('[data-cs-media-clear]').prop('hidden', !url);
		$m.find('.cs-media-bar [data-cs-media-pick]').text(url ? t('replace', 'Replace') : t('choose', 'Choose'));
		$m.trigger('cs:media', [id, url]);
	}
	$(document).on('click', '[data-cs-media-pick]', function (e) {
		e.preventDefault();
		if (!mediaReady()) { return; }
		var $m = $(this).closest('[data-cs-media]');
		var frame = $m.data('csFrame');
		if (!frame) {
			frame = wp.media({ title: t('pick', 'Select image'), button: { text: t('use', 'Use image') }, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				var sz = a.sizes || {};
				var preview = (sz.medium_large || sz.large || sz.medium || {}).url || a.url;
				setMedia($m, a.id, ($m.attr('data-store') === 'url') ? a.url : preview);
			});
			$m.data('csFrame', frame);
		}
		frame.open();
	});
	$(document).on('click', '[data-cs-media-clear]', function (e) {
		e.preventDefault();
		setMedia($(this).closest('[data-cs-media]'), '', '');
	});
	// URL-stored pickers: typing a URL updates the preview.
	$(document).on('change input', '[data-cs-media][data-store="url"] .cs-media-input', function (e) {
		if (e.isTrigger) { return; }
		var $m = $(this).closest('[data-cs-media]'), v = $.trim(this.value);
		$m.find('.cs-media-img').css('background-image', v ? 'url("' + v.replace(/"/g, '') + '")' : '');
		$m.toggleClass('has-image', !!v);
		$m.find('[data-cs-media-clear]').prop('hidden', !v);
	});

	/* ---------------------------------------------------------------------
	   Galleries
	   --------------------------------------------------------------------- */
	function initGallery(root) {
		var $g = $(root), $list = $g.find('.cs-gallery-list'), $input = $g.find('.cs-gallery-input');
		function sync() {
			var ids = $list.children('.cs-gallery-item').map(function () { return $(this).attr('data-id'); }).get();
			$input.val(ids.join(',')).trigger('change');
			$g.toggleClass('is-empty', !ids.length);
			var p = panelOf($g);
			if (p) { setCount(p, ids.length); }
		}
		if ($.fn.sortable) { $list.sortable({ items: '.cs-gallery-item', tolerance: 'pointer', placeholder: 'cs-gallery-ph', update: sync }); }
		$g.on('click', '[data-cs-gallery-add]', function (e) {
			e.preventDefault();
			if (!mediaReady()) { return; }
			var frame = wp.media({ title: t('addImages', 'Add images'), button: { text: t('use', 'Use image') }, library: { type: 'image' }, multiple: 'add' });
			frame.on('select', function () {
				frame.state().get('selection').each(function (att) {
					var a = att.toJSON();
					if ($list.find('[data-id="' + a.id + '"]').length) { return; }
					var sz = a.sizes || {}, th = (sz.medium || sz.thumbnail || {}).url || a.url;
					var $li = $('<li class="cs-gallery-item" />').attr('data-id', a.id);
					$li.append($('<img alt="" />').attr('src', th));
					$li.append('<button type="button" class="cs-gallery-remove" data-cs-gallery-remove aria-label="Remove"><svg class="cs-ic" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>');
					$list.append($li);
				});
				sync();
			});
			frame.open();
		});
		$g.on('click', '[data-cs-gallery-remove]', function (e) {
			e.preventDefault();
			$(this).closest('.cs-gallery-item').remove();
			sync();
		});
		$g.toggleClass('is-empty', !$list.children().length);
	}

	/* ---------------------------------------------------------------------
	   Repeaters (+ CSV import / sample, sort by year)
	   --------------------------------------------------------------------- */
	var CSV_FIELDS = {
		career: ['project', 'role', 'year', 'type'],
		awards: ['festival', 'title', 'project', 'year', 'location']
	};
	var CSV_SAMPLE = {
		career: 'Project,Role,Year,Type\nThe Kingdom,Laila,2025,TV Series\nDunia,Mona,2023,Movie',
		awards: 'Festival,Award Title,Project,Year,Location\nCairo Drama Festival,Best Actress,The Kingdom,2025,Egypt\nAnnaba Film Festival,Lifetime Achievement,,2024,Algeria'
	};
	var seq = 0;

	function initRepeater(root) {
		var $r = $(root), type = $r.attr('data-cs-repeater');
		var $items = $r.children('.cs-rep-items');
		var tpl = ($r.children('template.cs-rep-tpl')[0] || {}).innerHTML || '';

		function refresh() {
			var n = $items.children('.cs-row').length;
			$r.find('[data-cs-rep-count]').first().text(n);
			$r.children('.cs-rep-empty').prop('hidden', n > 0);
			$r.toggleClass('is-empty', !n);
			var p = panelOf($r);
			if (p && $r.closest('.cs-app').length) { setCount(p, n); }
			$(document).trigger('cs:changed');
		}
		function addRow(values, prepend) {
			var html = tpl.replace(/__i__/g, 'n' + Date.now().toString(36) + (seq++));
			var $row = $($.parseHTML($.trim(html)));
			if (values && values.length && CSV_FIELDS[type]) {
				values.forEach(function (v, k) {
					var f = CSV_FIELDS[type][k];
					if (f) { $row.find('[name$="[' + f + ']"]').first().val($.trim(v || '')); }
				});
			}
			if (prepend) { $items.prepend($row); } else { $items.append($row); }
			refresh();
			return $row;
		}

		if ($.fn.sortable) {
			$items.sortable({ handle: '.cs-row-handle', items: '> .cs-row', tolerance: 'pointer', axis: 'y', placeholder: 'cs-row-ph', forcePlaceholderSize: true });
		}
		$r.on('click', '[data-cs-rep-add]', function (e) {
			e.preventDefault();
			var $row = addRow();
			$row.find('input:not([type=hidden]),select').first().trigger('focus');
		});
		$r.on('click', '[data-cs-rep-remove]', function (e) {
			e.preventDefault();
			var $row = $(this).closest('.cs-row');
			$row.addClass('is-leaving');
			setTimeout(function () { $row.remove(); refresh(); }, 160);
		});
		$r.on('change', '[data-cs-soon]', function () { $(this).closest('.cs-row').toggleClass('is-soon', this.checked); });

		$r.on('click', '[data-cs-rep-sort]', function (e) {
			e.preventDefault();
			var rows = $items.children('.cs-row').get();
			rows.sort(function (a, b) {
				var sa = $(a).find('[data-cs-soon]').is(':checked') ? 1 : 0;
				var sb = $(b).find('[data-cs-soon]').is(':checked') ? 1 : 0;
				if (sa !== sb) { return sb - sa; }
				var ya = parseInt($(a).find('[data-cs-year]').val(), 10) || 0;
				var yb = parseInt($(b).find('[data-cs-year]').val(), 10) || 0;
				return yb - ya;
			});
			$items.append(rows);
			$(document).trigger('cs:changed');
		});

		$r.on('click', '[data-cs-csv-sample]', function (e) {
			e.preventDefault();
			var blob = new Blob([CSV_SAMPLE[type] || ''], { type: 'text/csv;charset=utf-8;' });
			var url = URL.createObjectURL(blob), a = document.createElement('a');
			a.href = url; a.download = type + '-sample.csv';
			document.body.appendChild(a); a.click(); document.body.removeChild(a);
			setTimeout(function () { URL.revokeObjectURL(url); }, 500);
		});
		$r.on('change', '[data-cs-csv]', function () {
			var input = this, file = input.files && input.files[0];
			var $status = $r.find('[data-cs-rep-status]').first();
			if (!file) { return; }
			var reader = new FileReader();
			reader.onload = function (ev) {
				var added = 0;
				stripHeader(parseCSV(ev.target.result)).forEach(function (row) {
					if (row.join('').trim() === '') { return; }
					addRow(row); added++;
				});
				$status.text(t('imported', '%d row(s) imported').replace('%d', added)).addClass('is-on');
				setTimeout(function () { $status.removeClass('is-on'); }, 5000);
				input.value = '';
			};
			reader.onerror = function () { $status.text(t('readError', 'Could not read that file.')).addClass('is-on'); };
			reader.readAsText(file);
		});
		refresh();
	}

	function parseCSV(text) {
		var rows = [], row = [], field = '', i = 0, inQ = false;
		text = String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
		if (text.charCodeAt(0) === 0xFEFF) { text = text.slice(1); }
		while (i < text.length) {
			var c = text[i];
			if (inQ) {
				if (c === '"') {
					if (text[i + 1] === '"') { field += '"'; i += 2; continue; }
					inQ = false; i++; continue;
				}
				field += c; i++; continue;
			}
			if (c === '"') { inQ = true; i++; continue; }
			if (c === ',') { row.push(field); field = ''; i++; continue; }
			if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; i++; continue; }
			field += c; i++;
		}
		if (field !== '' || row.length) { row.push(field); rows.push(row); }
		return rows.filter(function (r) { return r.some(function (x) { return String(x).trim() !== ''; }); });
	}
	function stripHeader(rows) {
		if (!rows.length) { return rows; }
		var tokens = ['project', 'project name', 'role', 'character', 'character/role', 'character / role', 'role name', 'year',
			'festival', 'organization', 'organisation', 'festival / organization', 'festival/organization', 'award', 'award title',
			'title', 'location', 'type'];
		var first = rows[0].map(function (c) { return String(c).trim().toLowerCase(); });
		return first.some(function (c) { return tokens.indexOf(c) !== -1; }) ? rows.slice(1) : rows;
	}

	/* Platform badge for video / link URLs. */
	function platformOf(url) {
		var u = String(url || '').toLowerCase();
		if (!u) { return 'Link'; }
		if (/youtube\.com|youtu\.be/.test(u)) { return 'YouTube'; }
		if (/vimeo\.com/.test(u)) { return 'Vimeo'; }
		if (/tiktok\.com/.test(u)) { return 'TikTok'; }
		if (/instagram\.com/.test(u)) { return 'Instagram'; }
		if (/facebook\.com|fb\.watch/.test(u)) { return 'Facebook'; }
		if (/twitter\.com|x\.com/.test(u)) { return 'X'; }
		return 'Link';
	}
	$(document).on('input change', '[data-cs-video-url]', function () {
		var p = platformOf(this.value);
		$(this).siblings('[data-cs-platform]').text(p).attr('data-p', p.toLowerCase());
	});

	/* ---------------------------------------------------------------------
	   Celebrity editor: social, live snapshot, profile strength
	   --------------------------------------------------------------------- */
	/* Text of a classic editor. TinyMCE reports nothing until it has finished
	   loading, so fall back to the underlying textarea (which WordPress fills
	   with the saved content) whenever the editor is not ready or empty. */
	function editorText(id) {
		var ed = window.tinymce && window.tinymce.get(id);
		var html = '';
		if (ed && ed.initialized && !ed.isHidden()) {
			html = ed.getContent();
			if (!ed._csBound) { ed._csBound = true; ed.on('change keyup input SetContent', function () { $(document).trigger('cs:changed'); }); }
		}
		if (!$.trim($('<div>').html(html).text())) { html = $('#' + id).val() || ''; }
		return $('<div>').html(html).text().trim();
	}
	/* Re-check once editors have loaded (they can finish before or after us). */
	function recheckLater(fn) {
		$(window).on('load', fn);
		setTimeout(fn, 800);
		setTimeout(fn, 2500);
		if (window.tinymce && window.tinymce.on) { window.tinymce.on('AddEditor', function (e) { e.editor.on('init', fn); }); }
	}
	function mark(key, done) {
		$('[data-cs-check="' + key + '"]').toggleClass('is-done', !!done);
	}
	function score() {
		var $items = $('[data-cs-check]');
		if (!$items.length) { return; }
		var done = $items.filter('.is-done').length;
		var pct = Math.round(100 * done / $items.length);
		$('[data-cs-score-num]').text(pct + '%');
		$('[data-cs-score-bar]').css('width', pct + '%');
		$('[data-cs-score]').attr('data-tone', pct >= 100 ? 'full' : (pct >= 70 ? 'good' : 'low'));
	}
	function hasRows(type) {
		return $('[data-cs-repeater="' + type + '"] > .cs-rep-items > .cs-row').filter(function () {
			return $(this).find('input[type=text],input[type=number],input[type=url]').filter(function () { return $.trim(this.value) !== ''; }).length > 0;
		}).length > 0;
	}

	function celebrityChecks() {
		var thumb = parseInt($('#_thumbnail_id').val(), 10) > 0;
		mark('photo', !!$('input[name="celb_profile"]').val() || thumb);
		mark('role', $.trim($('#celb_role').val()) !== '');
		mark('nationality', $.trim($('#celb_nationality').val()) !== '');
		mark('bio', editorText('celb_bio_editor') !== '');
		mark('hero_d', !!$('input[name="celb_hero_desktop"]').val());
		mark('hero_m', !!$('input[name="celb_hero_mobile"]').val());
		mark('social', $('[data-cs-social]').filter(function () { return $.trim(this.value) !== ''; }).length > 0);
		mark('career', hasRows('career'));
		mark('gallery', !!$('input[name="celb_gallery"]').val());
		score();
	}
	function newsChecks() {
		var thumb = parseInt($('#_thumbnail_id').val(), 10) > 0;
		mark('headline', $.trim($('#title').val()) !== '');
		mark('body', editorText('content') !== '');
		mark('celeb', parseInt($('#news_celebrity').val(), 10) > 0);
		mark('hero', !!$('input[name="news_header"]').val() || thumb);
		var ar = $.trim($('input[name="news_title_ar"]').val()) !== '';
		mark('arabic', ar);
		$('[data-cs-ar-dot]').toggleClass('is-on', ar || editorText('news_body_ar') !== '');
		score();
	}

	function initCelebrity($app) {
		// Social
		$app.on('input change', '[data-cs-social]', function () {
			$(this).closest('.cs-social-item').toggleClass('is-filled', $.trim(this.value) !== '');
			setCount('social', $app.find('[data-cs-social]').filter(function () { return $.trim(this.value) !== ''; }).length);
		});

		// Snapshot: name, subtitle, badges, photo
		var $name = $('[data-cs-snap-name]'), $sub = $('[data-cs-snap-sub]');
		$('#title').on('input', function () { $name.text(this.value || '—'); });
		$app.on('input', '[data-cs-live="role"],[data-cs-live="nationality"]', function () {
			$sub.text([$('#celb_role').val(), $('#celb_nationality').val()].filter(function (v) { return $.trim(v); }).join(' / '));
		});
		$app.on('change', '[data-cs-live="lead"],[data-cs-live="locked"]', function () {
			$('[data-cs-badge="' + this.getAttribute('data-cs-live') + '"]').prop('hidden', !this.checked);
		});
		$app.on('cs:media', '[data-cs-media]', function (e, id, url) {
			if ($(this).find('input[name="celb_profile"]').length) {
				$('[data-cs-snap-photo]').css('background-image', url ? 'url("' + url + '")' : '').toggleClass('has-photo', !!url);
			}
		});
		$('[data-cs-snap-photo]').toggleClass('has-photo', !!$('[data-cs-snap-photo]').attr('style'));

		var run = function () { celebrityChecks(); };
		$(document).on('input change cs:changed', run);
		$(document).on('tinymce-editor-init', function (e, ed) { if (ed && ed.id === 'celb_bio_editor') { ed.on('change keyup input', run); run(); } });
		run();
		recheckLater(run);
	}

	/* ---------------------------------------------------------------------
	   Newsroom editor: language switch, celebrity picker
	   --------------------------------------------------------------------- */
	function initNews($app) {
		var key = 'cs-lang-' + ($app.data('post') || 0);
		function lang(l) {
			$app.find('[data-cs-lang]').each(function () { this.classList.toggle('is-active', this.getAttribute('data-cs-lang') === l); });
			$app.find('[data-cs-lang-pane]').each(function () { this.classList.toggle('is-active', this.getAttribute('data-cs-lang-pane') === l); });
			document.body.classList.toggle('cs-lang-ar', l === 'ar');
			sset('sessionStorage', key, l);
			setTimeout(function () { $(window).trigger('resize'); }, 30);
		}
		$app.on('click', '[data-cs-lang]', function () { lang(this.getAttribute('data-cs-lang')); });
		if (sget('sessionStorage', key) === 'ar') { lang('ar'); }

		$('[data-cs-who-select]').on('change', function () {
			var photo = $(this).find('option:selected').attr('data-photo') || '';
			$('[data-cs-who-photo]').css('background-image', photo ? 'url("' + photo + '")' : '').toggleClass('has-photo', !!photo);
		}).trigger('change');

		var run = function () { newsChecks(); };
		$(document).on('input change cs:changed', run);
		$(document).on('tinymce-editor-init', function (e, ed) { if (ed && (ed.id === 'content' || ed.id === 'news_body_ar')) { ed.on('change keyup input', run); run(); } });
		run();
		recheckLater(run);
	}

	/* ---------------------------------------------------------------------
	   Roster list
	   --------------------------------------------------------------------- */
	function initList() {
		var root = document.documentElement;
		var $table = $('.wp-list-table').first();
		var $cards = buildCards($table);

		function view(v) {
			root.classList.toggle('cs-roster-grid', v === 'grid');
			$('[data-cs-view]').each(function () { this.classList.toggle('is-active', this.getAttribute('data-cs-view') === v); });
			sset('localStorage', 'celbRosterView', v);
			// Keep card ticks in step with the table (bulk actions read the table).
			if ($cards) {
				$cards.find('[data-cs-pick]').each(function () {
					var orig = document.getElementById(this.getAttribute('data-cs-pick'));
					this.checked = !!(orig && orig.checked);
					$(this).closest('.cs-tcard').toggleClass('is-picked', this.checked);
				});
			}
		}
		$(document).on('click', '[data-cs-view]', function () { view(this.getAttribute('data-cs-view')); });
		view(root.classList.contains('cs-roster-grid') ? 'grid' : 'list');

		moveSubs();

		// Card ticks drive the real checkboxes; Quick Edit opens in the table.
		$(document).on('change', '[data-cs-pick]', function () {
			var orig = document.getElementById(this.getAttribute('data-cs-pick'));
			if (orig) { orig.checked = this.checked; }
			$(this).closest('.cs-tcard').toggleClass('is-picked', this.checked);
		});
		$(document).on('click', '[data-cs-quickedit]', function (e) {
			e.preventDefault();
			var row = this.getAttribute('data-cs-quickedit');
			view('list');
			var btn = document.querySelector('#' + row + ' .editinline');
			if (btn) { btn.click(); }
		});
	}

	/* Secondary line (role / nationality, or brand / email) under the row title. */
	function moveSubs() {
		$('#the-list tr').each(function () {
			var $sub = $(this).find('[data-cs-sub]');
			var $strong = $(this).find('td.column-title strong').first();
			if ($sub.length && $strong.length) { $sub.insertAfter($strong); }
		});
	}

	/* Artist request view: replying flips the status to Replied; notes save via Update. */
	function initRequest() {
		var $pub = $('#publish');
		$(document).on('click', '[data-cs-ar-submit]', function (e) {
			e.preventDefault();
			if ($pub.length) { $pub.trigger('click'); }
		});
		$(document).on('click', '[data-cs-ar-replied]', function () {
			var $cur = $('input[name="ar_status"]:checked');
			if (!$cur.length || $cur.val() === 'new' || $cur.val() === 'progress') {
				$('input[name="ar_status"][value="replied"]').prop('checked', true).trigger('change');
				$('[data-cs-ar-hint]').prop('hidden', false);
			}
		});
		$(document).on('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); $pub.trigger('click'); }
		});
	}

	/* Cards are built only from our own columns, so columns other plugins add
	   (SEO scores, view counters…) never end up inside a card. */
	function buildCards($table) {
		if (!$table.length || !$('#the-list').length) { return null; }
		var $wrap = $('<div class="cs-tcards" />');
		$('#the-list > tr').each(function () {
			var $tr = $(this), id = this.id;
			if (!id || $tr.hasClass('no-items')) { return; }
			var $link = $tr.find('a.row-title').first();
			var $card = $('<article class="cs-tcard" />').attr('data-row', id);

			var $media = $('<a class="cs-tcard-media" />').attr('href', $link.attr('href') || '#');
			var $thumb = $tr.find('.cs-thumb').first();
			var bg = $thumb.css('background-image');
			if (bg && bg !== 'none') { $media.css('background-image', bg).addClass('has-photo'); }
			else { $media.append($('<span class="cs-tcard-mono" />').text($.trim($thumb.text()))); }
			$card.append($media);

			var $cb = $tr.find('th.check-column input[type=checkbox]').first();
			if ($cb.length) {
				if (!$cb.attr('id')) { $cb.attr('id', 'cs-cb-' + id); }
				$card.append($('<label class="cs-tcard-pick" />').append(
					$('<input type="checkbox" />').attr('data-cs-pick', $cb.attr('id')).attr('aria-label', $.trim($link.text()))
				));
			}

			var $body = $('<div class="cs-tcard-body" />');
			$body.append($('<h3 class="cs-tcard-name" />').append($('<a />').attr('href', $link.attr('href') || '#').text($.trim($link.text()))));
			$body.append($('<p class="cs-tcard-sub" />').text($.trim($tr.find('[data-cs-sub]').first().text())));
			$body.append($tr.find('td.column-celb_flags .cs-badges').first().clone());
			$body.append($tr.find('td.column-celb_cats .cs-cats').first().clone());
			$body.append($tr.find('td.column-celb_strength .cs-strength').first().clone());
			$card.append($body);

			var $foot = $('<div class="cs-tcard-foot" />');
			$foot.append($tr.find('td.column-celb_link .cs-linkchip').first().clone());
			var $acts = $('<div class="cs-tcard-actions" />');
			$tr.find('.row-actions > span').each(function () {
				var $a = $(this).children('a, button').first();
				if (!$a.length) { return; }
				if ($a.hasClass('editinline')) {
					$acts.append($('<button type="button" />').text($.trim($a.text())).attr('data-cs-quickedit', id));
				} else {
					var $c = $a.clone().removeAttr('aria-label');
					if (this.className.indexOf('trash') !== -1 || this.className.indexOf('delete') !== -1) { $c.addClass('is-danger'); }
					$acts.append($c);
				}
			});
			$foot.append($acts);
			$card.append($foot);
			$wrap.append($card);
		});
		// Directly under the top toolbar, whatever other plugins add to the page.
		var $top = $('.tablenav.top').first();
		if ($top.length) { $wrap.insertAfter($top); } else { $wrap.insertBefore($table); }
		return $wrap;
	}

	/* ---------------------------------------------------------------------
	   Settings app
	   --------------------------------------------------------------------- */
	function initSettings() {
		var $form = $('[data-cs-settings]');
		if (!$form.length) { return; }
		var $nav = $form.find('[data-cs-nav]'), $sections = $form.find('[data-cs-section]');
		var KEY = 'cs-settings-section';

		function show(name) {
			if (!$sections.filter('[data-cs-section="' + name + '"]').length) { name = $sections.first().attr('data-cs-section'); }
			$nav.each(function () { this.classList.toggle('is-active', this.getAttribute('data-cs-nav') === name); });
			$sections.each(function () { this.classList.toggle('is-active', this.getAttribute('data-cs-section') === name); });
			sset('sessionStorage', KEY, name);
			if (history.replaceState) { history.replaceState(null, '', '#' + name); }
		}
		$nav.on('click', function () {
			$search.val(''); filter('');
			show(this.getAttribute('data-cs-nav'));
			var top = $form.find('.cs-sections')[0];
			if (top && top.getBoundingClientRect().top < 0) { window.scrollTo({ top: window.scrollY + top.getBoundingClientRect().top - 90, behavior: 'smooth' }); }
		});
		var h = (location.hash || '').replace('#', '');
		show(h && $sections.filter('[data-cs-section="' + h + '"]').length ? h : (sget('sessionStorage', KEY) || 'brand'));

		// Search: filters the nav and shows every matching section at once.
		var $search = $form.find('[data-cs-search]');
		function filter(q) {
			q = $.trim(q).toLowerCase();
			$form.toggleClass('is-searching', !!q);
			if (!q) {
				$nav.show(); $form.find('.cs-snav-group').show();
				$sections.removeClass('is-match');
				$form.find('[data-cs-search-empty]').prop('hidden', true);
				return;
			}
			var any = false;
			$sections.each(function () {
				var key = this.getAttribute('data-cs-section');
				var $n = $nav.filter('[data-cs-nav="' + key + '"]');
				var hay = ($n.attr('data-keywords') || '') + ' ' + $(this).text().toLowerCase();
				var hit = hay.indexOf(q) !== -1;
				this.classList.toggle('is-match', hit);
				$n.toggle(hit);
				any = any || hit;
			});
			$form.find('.cs-snav-group').each(function () { $(this).toggle($(this).find('[data-cs-nav]:visible').length > 0); });
			$form.find('[data-cs-search-empty]').prop('hidden', any);
		}
		$search.on('input', function () { filter(this.value); });
		$search.on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });

		// Colour pickers.
		if ($.fn.wpColorPicker) {
			$form.find('.cs-color').wpColorPicker({ change: function () { setTimeout(dirty, 0); }, clear: function () { setTimeout(dirty, 0); } });
		}

		// Dependent fields dim when their switch is off.
		function deps() {
			$form.find('[data-cs-depends]').each(function () {
				var cb = $form.find('input[type=checkbox][name="' + this.getAttribute('data-cs-depends') + '"]')[0];
				this.classList.toggle('is-off', cb ? !cb.checked : false);
			});
		}
		$form.on('change', 'input[type=checkbox]', deps);
		deps();

		// Password generator.
		$form.on('click', '[data-cs-pw-gen]', function (e) {
			e.preventDefault();
			var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789', out = '';
			var arr = (window.crypto && crypto.getRandomValues) ? crypto.getRandomValues(new Uint32Array(14)) : null;
			for (var i = 0; i < 14; i++) { out += chars[(arr ? arr[i] : Math.floor(Math.random() * 1e9)) % chars.length]; }
			$(this).siblings('[data-cs-pw]').val(out).trigger('change');
		});

		// Unsaved-changes indicator + leave warning.
		var initial = $form.serialize(), submitting = false;
		var $dirty = $form.find('[data-cs-dirty]');
		function dirty() {
			var d = $form.serialize() !== initial;
			$form.toggleClass('is-dirty', d);
			$dirty.text(d ? t('unsaved', 'Unsaved changes') : ($dirty.data('saved') ? t('allSaved', 'All changes saved') : ''));
		}
		$dirty.data('saved', $.trim($dirty.text()) !== '');
		$form.on('input change', dirty);
		$(document).on('cs:changed', dirty);
		$form.on('submit', function () { submitting = true; });
		$(window).on('beforeunload', function () {
			if (!submitting && $form.serialize() !== initial) { return t('confirmLeave', 'You have unsaved changes.'); }
		});
		$(document).on('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); submitting = true; $form.trigger('submit'); }
		});

		var $toast = $('[data-cs-toast]');
		if ($toast.hasClass('is-on')) { setTimeout(function () { $toast.removeClass('is-on'); }, 3200); }
		if (/settings-updated=true/.test(location.search) && history.replaceState) {
			history.replaceState(null, '', location.pathname + location.search.replace(/&?settings-updated=true/, '') + location.hash);
		}
	}

	/* ---------------------------------------------------------------------
	   Operations screens (schedule, contracts, templates, personal data)
	   --------------------------------------------------------------------- */

	/* Small reveal helpers used by several editors. */
	function initReveals() {
		// "Other" type → show the specify field.
		$(document).on('change', '[data-cs-other]', function () {
			var key = this.getAttribute('data-cs-other');
			$('[data-cs-other-for="' + key + '"]').closest('.cs-field').toggleClass('is-hidden', this.value !== 'Other');
		});
		// Switch → reveal its block.
		$(document).on('change', '[data-cs-toggle]', function () {
			$('[data-cs-toggled="' + this.getAttribute('data-cs-toggle') + '"]').toggleClass('is-on', this.checked);
		});
		// Schedule status → postponed date.
		$(document).on('change', '[data-cs-status]', function () {
			$('[data-cs-postponed]').toggleClass('is-on', this.value === 'Postponed' && this.checked);
		});
		// Talent picker → photo.
		$(document).on('change', '[data-cs-celeb-select]', function () {
			var photo = $(this).find('option:selected').attr('data-photo') || '';
			$(this).closest('.cs-who').find('[data-cs-who-photo]').css('background-image', photo ? 'url("' + photo + '")' : '').toggleClass('has-photo', !!photo);
			if ($('[data-cs-doc-title]').length && this.name === 'contract_celeb') {
				var name = $.trim($(this).find('option:selected').text());
				$('[data-cs-doc-title]').text(this.value !== '0' ? 'Contract — ' + name : 'New contract');
			}
		});
	}

	/* Cmd/Ctrl+S on editors that have no Studio save bar. */
	function initSaveShortcut() {
		if ($('[data-cs-save]').length || !$('#publish').length || CFG.screen === 'request') { return; }
		$(document).on('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); $('#publish').trigger('click'); }
		});
	}

	/* Contract template: label → key, live placeholder chips, click to insert. */
	function slugKey(v) {
		return String(v || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
	}
	function insertToken(tok) {
		var ed = window.tinymce && window.tinymce.get('content');
		if (ed && !ed.isHidden()) { ed.focus(); ed.execCommand('mceInsertContent', false, tok); return; }
		var ta = document.getElementById('content');
		if (!ta) { return; }
		var a = ta.selectionStart || 0, b = ta.selectionEnd || 0;
		ta.value = ta.value.slice(0, a) + tok + ta.value.slice(b);
		ta.focus(); ta.selectionStart = ta.selectionEnd = a + tok.length;
	}
	function initTemplate() {
		function refreshTokens() {
			var $box = $('[data-cs-field-tokens]');
			if (!$box.length) { return; }
			$box.empty();
			$('[data-cs-cf-key]').each(function () {
				var k = $.trim(this.value);
				if (k) { $box.append($('<button type="button" class="cs-token cs-token--btn" />').attr('data-cs-insert', '{{' + k + '}}').text('{{' + k + '}}')); }
			});
		}
		function sync($row) {
			var k = $row.find('[data-cs-cf-key]').val();
			$row.find('.cs-cf-key .cs-token').attr('data-cs-insert', '{{' + k + '}}').text('{{' + (k || 'key') + '}}');
			refreshTokens();
		}
		$(document).on('input', '[data-cs-cf-label]', function () {
			var $row = $(this).closest('.cs-row'), $k = $row.find('[data-cs-cf-key]');
			if (!$k.attr('data-touched')) { $k.val(slugKey(this.value)); sync($row); }
		});
		$(document).on('input', '[data-cs-cf-key]', function () {
			this.setAttribute('data-touched', '1');
			var pos = this.selectionStart, v = slugKey(this.value);
			if (v !== this.value) { this.value = v; try { this.setSelectionRange(pos, pos); } catch (e) {} }
			sync($(this).closest('.cs-row'));
		});
		$(document).on('cs:changed', refreshTokens);
		$(document).on('mousedown', '[data-cs-insert]', function (e) { e.preventDefault(); }); // keep the editor caret
		$(document).on('click', '[data-cs-insert]', function (e) {
			e.preventDefault();
			insertToken(this.getAttribute('data-cs-insert'));
			var b = this; b.classList.add('is-copied'); setTimeout(function () { b.classList.remove('is-copied'); }, 700);
		});
	}

	/* Personal data form builder. */
	function initBuilder() {
		var $wrap = $('[data-cs-pdb-sections]');
		if (!$wrap.length) { return; }
		var seq = Date.now();
		var secTpl = ($('#celb-pdb-sec-tpl')[0] || {}).innerHTML || '';
		var qTpl = ($('#celb-pdb-q-tpl')[0] || {}).innerHTML || '';
		function sortables() {
			if (!$.fn.sortable) { return; }
			$wrap.sortable({ handle: '.cs-pdb-drag-sec', items: '> .cs-pdb-sec', placeholder: 'cs-row-ph', forcePlaceholderSize: true, tolerance: 'pointer' });
			$wrap.find('.cs-pdb-qs').each(function () {
				if ($(this).data('uiSortable')) { return; }
				$(this).sortable({ handle: '.cs-pdb-drag-q', items: '> .cs-row', placeholder: 'cs-row-ph', forcePlaceholderSize: true, tolerance: 'pointer', axis: 'y' });
			});
		}
		function count($sec) { $sec.find('[data-cs-pdb-count]').text($sec.find('.cs-pdb-qs > .cs-row').length); }
		$(document).on('click', '[data-cs-pdb-addsec]', function () {
			var $s = $($.parseHTML($.trim(secTpl.replace(/__S__/g, 's' + (++seq)))));
			$wrap.append($s); sortables(); $s.find('.cs-pdb-sec-title').trigger('focus');
		});
		$(document).on('click', '[data-cs-pdb-addq]', function () {
			var $sec = $(this).closest('.cs-pdb-sec');
			var $q = $($.parseHTML($.trim(qTpl.replace(/__S__/g, $sec.attr('data-sid')).replace(/__Q__/g, 'q' + (++seq)))));
			$sec.find('.cs-pdb-qs').first().append($q); count($sec); sortables();
			$q.find('input[type=text]').first().trigger('focus');
		});
		$(document).on('click', '[data-cs-pdb-delq]', function () {
			var $sec = $(this).closest('.cs-pdb-sec');
			$(this).closest('.cs-row').remove(); count($sec);
		});
		$(document).on('click', '[data-cs-pdb-delsec]', function () {
			if (window.confirm('Remove this section and its questions?')) { $(this).closest('.cs-pdb-sec').remove(); }
		});
		$(document).on('change', '[data-cs-pdb-type]', function () {
			$(this).closest('.cs-row').toggleClass('has-opts', this.value === 'select');
		});
		sortables();
		var initial = $('[data-cs-pdb]').serialize(), submitting = false;
		$('[data-cs-pdb]').on('submit', function () { submitting = true; });
		$(window).on('beforeunload', function () { if (!submitting && $('[data-cs-pdb]').serialize() !== initial) { return 'unsaved'; } });
	}

	/* Personal data submissions: instant search. */
	function initSubmissions() {
		$(document).on('input', '[data-cs-pd-search]', function () {
			var q = $.trim(this.value).toLowerCase(), any = false;
			$('[data-cs-pd-card]').each(function () {
				var hit = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
				$(this).toggle(hit); any = any || hit;
			});
			$('[data-cs-pd-empty]').prop('hidden', any);
		});
	}

	/* Toasts fade out on their own. */
	function initToasts() {
		$('[data-cs-toast].is-on').each(function () { var t = this; setTimeout(function () { t.classList.remove('is-on'); }, 3600); });
	}

	/* ---------------------------------------------------------------------
	   Boot
	   --------------------------------------------------------------------- */
	$(function () {
		$('[data-cs-gallery]').each(function () { initGallery(this); });
		$('[data-cs-repeater]').each(function () { initRepeater(this); });

		var $app = $('[data-cs-app]').first();
		if ($app.length) {
			initTabs($app);
			initSave($app);
			if ($app.data('cs-app') === 'celebrity') { initCelebrity($app); }
			if ($app.data('cs-app') === 'news') { initNews($app); }
			$('body').addClass('cs-ready');
		}
		if (CFG.screen === 'list') { initList(); }
		if (document.body.classList.contains('cs-screen-list') && CFG.screen !== 'list') { moveSubs(); }
		initReveals();
		initSaveShortcut();
		initToasts();
		if (CFG.screen === 'template') { initTemplate(); }
		if (CFG.screen === 'pdbuilder') { initBuilder(); }
		if (CFG.screen === 'pdsubs') { initSubmissions(); }
		if (CFG.screen === 'request') { initRequest(); }
		if (CFG.screen === 'settings') { initSettings(); }
	});
})(jQuery);
