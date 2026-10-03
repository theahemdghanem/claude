/* Work Center — wp-admin meta box helpers (repeaters + media uploader) */
jQuery(function ($) {

	function recount() {
		var total = 0, done = 0, up = 0;
		var today = new Date().toISOString().slice(0, 10);
		$('.celb-day-row').each(function () {
			var d = $(this).find('.celb-day-date').val();
			var s = $(this).find('.celb-day-status').is(':checked');
			total++;
			if (s) { done++; }
			else if (d && d >= today) { up++; }
		});
		$('.celb-day-counts .t').text(total);
		$('.celb-day-counts .c').text(done);
		$('.celb-day-counts .u').text(up);
	}

	$(document).on('click', '.celb-rep-add', function () {
		var target = $(this).data('target');
		var tpl = $(this).closest('.celb-rep').find('.celb-tpl-' + target).html();
		if (!tpl) { return; }
		var idx = 'n' + Date.now() + Math.floor(Math.random() * 1000);
		tpl = tpl.replace(/__i__/g, idx);
		$(this).closest('.celb-rep').find('.celb-rep-rows').append(tpl);
		recount();
	});

	$(document).on('click', '.celb-rep-del', function () {
		$(this).closest('.celb-rep-row').remove();
		recount();
	});

	$(document).on('change input', '.celb-day-date, .celb-day-status', recount);

	/* Media uploader */
	$(document).on('click', '.celb-media-add', function (e) {
		e.preventDefault();
		var box = $(this).closest('.celb-media');
		var frame = wp.media({
			title: 'Select or upload files',
			button: { text: 'Use these files' },
			multiple: true
		});
		frame.on('select', function () {
			var sel = frame.state().get('selection');
			var ids = (box.find('.celb-media-ids').val() || '').split(',').filter(Boolean);
			sel.each(function (att) {
				var a = att.toJSON();
				if (ids.indexOf(String(a.id)) === -1) {
					ids.push(String(a.id));
					var thumb = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : (a.type === 'image' ? a.url : '');
					var icon = a.type === 'image'
						? '<img src="' + thumb + '" alt="">'
						: '<span class="celb-media-ic">' + (a.type === 'video' ? '🎬' : (a.subtype === 'pdf' ? '📄' : '📎')) + '</span>';
					var name = a.filename || a.title || ('#' + a.id);
					box.find('.celb-media-list').append(
						'<span class="celb-media-item" data-id="' + a.id + '">' + icon +
						'<span class="nm">' + $('<div>').text(name).html() + '</span>' +
						'<button type="button" class="celb-media-del">✕</button></span>'
					);
				}
			});
			box.find('.celb-media-ids').val(ids.join(','));
		});
		frame.open();
	});

	$(document).on('click', '.celb-media-del', function () {
		var item = $(this).closest('.celb-media-item');
		var box = $(this).closest('.celb-media');
		var id = String(item.data('id'));
		var ids = (box.find('.celb-media-ids').val() || '').split(',').filter(Boolean).filter(function (x) { return x !== id; });
		box.find('.celb-media-ids').val(ids.join(','));
		item.remove();
	});
});
