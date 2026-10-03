/* =========================================================================
   CELB MGMT — Admin JS
   Repeatable rows, sortable ordering, media pickers for hero + gallery.
   ========================================================================= */
(function ($) {
	'use strict';

	$(function () {
		initRepeaters();
		initHeroPickers();
		initGallery();
	});

	/* ----------------------------------------------------------------------
	   REPEATERS (Career History, Awards) + CSV import
	   ---------------------------------------------------------------------- */
	function initRepeaters() {
		$('.celb-repeater').each(function () {
			var $rep = $(this);
			var type = $rep.data('repeater'); // 'career' | 'awards'
			var $items = $rep.find('.celb-repeater-items');
			var template = $rep.find('.celb-row-template').html();
			var index = $items.children('.celb-repeater-row').length;

			// Make rows sortable.
			if ($.fn.sortable) {
				$items.sortable({ handle: '.celb-drag', items: '.celb-repeater-row', tolerance: 'pointer' });
			}

			// Append a row, optionally pre-filled with an array of CSV values.
			// Values are mapped to fields BY NAME (not raw input order), so an
			// inserted field like career "type" can't shift the columns.
			// CSV column order: career = project, role, year, [type];
			// awards = festival, title, project, year, location.
			var CSV_FIELDS = {
				career: ['project', 'role', 'year', 'type'],
				awards: ['festival', 'title', 'project', 'year', 'location']
			};
			function addRow(values) {
				var html = template.replace(/__i__/g, 'new_' + index);
				index++;
				var $row = $(html);
				if (values && values.length) {
					var fields = CSV_FIELDS[type];
					if (fields) {
						values.forEach(function (v, k) {
							var f = fields[k];
							if (!f) { return; }
							$row.find('[name$="[' + f + ']"]').first().val($.trim(v || ''));
						});
					} else {
						$row.find('input').each(function (k) {
							if (k < values.length) { $(this).val($.trim(values[k] || '')); }
						});
					}
				}
				$items.append($row);
				return $row;
			}

			$rep.on('click', '.celb-add-row', function (e) {
				e.preventDefault();
				addRow();
			});

			$rep.on('click', '.celb-remove-row', function (e) {
				e.preventDefault();
				var $rows = $items.children('.celb-repeater-row');
				if ($rows.length <= 1) {
					$(this).closest('.celb-repeater-row').find('input').val('');
				} else {
					$(this).closest('.celb-repeater-row').remove();
				}
			});

			// CSV import.
			$rep.on('change', '.celb-csv-input', function () {
				var input = this;
				var file = input.files && input.files[0];
				var $status = $rep.find('.celb-csv-status');
				if (!file) { return; }
				var reader = new FileReader();
				reader.onload = function (ev) {
					var rows = stripHeader(parseCSV(ev.target.result));
					var added = 0;
					rows.forEach(function (r) {
						if (r.join('').trim() === '') { return; }
						addRow(r);
						added++;
					});
					$status.text(added + ' row(s) imported — click Update to save.');
					input.value = ''; // allow re-importing the same file
				};
				reader.onerror = function () { $status.text('Could not read that file.'); };
				reader.readAsText(file);
			});

			// Sample CSV download.
			$rep.on('click', '.celb-csv-sample', function (e) {
				e.preventDefault();
				var csv = (type === 'awards')
					? 'Festival,Award Title,Project,Year,Location\nCairo Drama Festival,Best Actress,The Kingdom,2025,Egypt\nAnnaba Film Festival,Lifetime Achievement,,2024,Algeria'
					: 'Project,Role,Year,Type\nThe Kingdom,Laila,2025,TV Series\nDunia,Mona,2023,Movie';
				var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
				var url = URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = type + '-sample.csv';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				URL.revokeObjectURL(url);
			});
		});
	}

	// Minimal RFC-4180-ish CSV parser (handles quotes, escaped quotes, commas, newlines).
	function parseCSV(text) {
		var rows = [], row = [], field = '', i = 0, inQ = false;
		text = String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
		// Strip a UTF-8 BOM if present.
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
		return rows.filter(function (r) {
			return r.some(function (cell) { return String(cell).trim() !== ''; });
		});
	}

	// Drop the first row if it looks like a header.
	function stripHeader(rows) {
		if (!rows.length) { return rows; }
		var tokens = ['project', 'project name', 'role', 'character', 'character/role', 'character / role',
			'role name', 'year', 'festival', 'organization', 'festival / organization',
			'festival/organization', 'award', 'award title', 'title', 'location', 'type'];
		var first = rows[0].map(function (c) { return String(c).trim().toLowerCase(); });
		var isHeader = first.some(function (c) { return tokens.indexOf(c) !== -1; });
		return isHeader ? rows.slice(1) : rows;
	}

	/* ----------------------------------------------------------------------
	   HERO IMAGE PICKERS (single image each)
	   ---------------------------------------------------------------------- */
	function initHeroPickers() {
		$('.celb-hero-picker').each(function () {
			var $picker = $(this);
			var $input = $picker.find('.celb-hero-input');
			var $preview = $picker.find('.celb-hero-preview');
			var $remove = $picker.find('.celb-hero-remove');
			var frame;

			$picker.on('click', '.celb-hero-select', function (e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: (window.CELB_ADMIN && CELB_ADMIN.frameTitle) || 'Select Image',
					button: { text: (window.CELB_ADMIN && CELB_ADMIN.frameButton) || 'Use Image' },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					$input.val(att.id);
					var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
					$preview.css('background-image', 'url(' + url + ')');
					$remove.show();
				});
				frame.open();
			});

			$remove.on('click', function (e) {
				e.preventDefault();
				$input.val('');
				$preview.css('background-image', '');
				$remove.hide();
			});
		});
	}

	/* ----------------------------------------------------------------------
	   GALLERY (multiple images, sortable)
	   ---------------------------------------------------------------------- */
	function initGallery() {
		var $mgr = $('.celb-gallery-manager');
		if (!$mgr.length) { return; }

		var $input = $mgr.find('.celb-gallery-input');
		var $list = $mgr.find('.celb-gallery-list');
		var frame;

		function syncInput() {
			var ids = [];
			$list.children('.celb-gallery-item').each(function () {
				ids.push($(this).data('id'));
			});
			$input.val(ids.join(','));
		}

		if ($.fn.sortable) {
			$list.sortable({ items: '.celb-gallery-item', tolerance: 'pointer', update: syncInput });
		}

		$mgr.on('click', '.celb-gallery-add', function (e) {
			e.preventDefault();
			frame = wp.media({
				title: (window.CELB_ADMIN && CELB_ADMIN.galleryAdd) || 'Add Gallery Images',
				button: { text: (window.CELB_ADMIN && CELB_ADMIN.frameButton) || 'Use Image' },
				library: { type: 'image' },
				multiple: 'add'
			});
			frame.on('select', function () {
				var selection = frame.state().get('selection');
				selection.each(function (att) {
					var data = att.toJSON();
					if ($list.find('.celb-gallery-item[data-id="' + data.id + '"]').length) { return; }
					var thumb = (data.sizes && data.sizes.thumbnail) ? data.sizes.thumbnail.url : data.url;
					var $li = $('<li class="celb-gallery-item" data-id="' + data.id + '">' +
						'<img src="' + thumb + '" alt="" />' +
						'<button type="button" class="celb-gallery-remove dashicons dashicons-no-alt"></button>' +
						'</li>');
					$list.append($li);
				});
				syncInput();
			});
			frame.open();
		});

		$mgr.on('click', '.celb-gallery-remove', function (e) {
			e.preventDefault();
			$(this).closest('.celb-gallery-item').remove();
			syncInput();
		});
	}

	// Smart Link "Copy link" button (and any .celb-copy on edit screens).
	$(document).on('click', '.celb-copy', function (e) {
		e.preventDefault();
		var btn = this;
		var text = btn.getAttribute('data-copy');
		if (!text) { return; }
		var label = btn.textContent;
		var done = function () {
			btn.textContent = 'Copied!';
			setTimeout(function () { btn.textContent = label; }, 1500);
		};
		if (navigator.clipboard) {
			navigator.clipboard.writeText(text).then(done, done);
		} else {
			var t = document.createElement('textarea');
			t.value = text;
			document.body.appendChild(t);
			t.select();
			try { document.execCommand('copy'); } catch (err) {}
			document.body.removeChild(t);
			done();
		}
	});

	/* Shooting-day status: reveal the "→ new date / new call" fields only when
	   the day is marked Postponed. */
	jQuery(document).on('change', '.celb-day-status', function () {
		var pp = jQuery(this).closest('.celb-day-row').find('.celb-day-postpone');
		if (this.value === 'postponed') { pp.show(); } else { pp.hide(); }
	});

	/* Project/Schedule "Type = Other" → reveal the free-text specify field. */
	jQuery(document).on('change', '.celb-type-select', function () {
		var row = jQuery(this).closest('table').find('.celb-type-other');
		if (this.value === 'Other') { row.show(); } else { row.hide(); }
	});

	/* Schedule status = Postponed → reveal the "Postponed to" date/time. */
	jQuery(document).on('change', 'select[name="sched_status"]', function () {
		var row = jQuery(this).closest('table').find('.celb-sched-postpone');
		if (this.value === 'Postponed') { row.show(); } else { row.hide(); }
	});

	/* Recurring checkbox → reveal recurring details. */
	jQuery(document).on('change', '.celb-recur-toggle', function () {
		var row = jQuery(this).closest('table').find('.celb-recur-row');
		if (this.checked) { row.show(); } else { row.hide(); }
	});
})(jQuery);
