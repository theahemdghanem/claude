/* iLike — Rate Card interactive quotation builder */
(function () {
	'use strict';
	var root = document.getElementById('celb-rate');
	if (!root) { return; }
	var cfg = window.CELB_RATE || {};
	var pre = cfg.currencyPre || '';
	var suf = cfg.currencySuf || '';
	var usdSuf = cfg.usdSuffix || 'USD';
	var usdRate = parseFloat(cfg.usdRate) || 0;
	var usdMarkup = parseFloat(cfg.usdMarkup) || 0;
	var mode = 'base'; // 'base' (EGP) or 'usd'

	function toUsd(egp) {
		if (usdRate <= 0) { return Math.round(egp); }
		return Math.round((egp / usdRate) * (1 + usdMarkup / 100));
	}
	function fmt(n) {
		n = n || 0;
		if (mode === 'usd') {
			return toUsd(n).toLocaleString('en-US') + ' ' + usdSuf;
		}
		var s = Math.round(n).toLocaleString('en-US');
		return (pre ? pre + ' ' : '') + s + (suf ? ' ' + suf : '');
	}
	function fmtPct(v) {
		v = parseFloat(v) || 0;
		var s = (Math.round(v * 100) / 100).toString();
		return '+' + s + '%';
	}
	function servicePrice(el) {
		var base = parseFloat(el.getAttribute('data-base')) || 0;
		var addl = parseFloat(el.getAttribute('data-addl')) || 0;
		var extra = parseInt(el.getAttribute('data-extra'), 10) || 0;
		return base + Math.max(0, extra) * addl;
	}

	function each(list, fn) { Array.prototype.forEach.call(list, fn); }

	function collect() {
		var subtotal = 0;
		var lines = [];
		each(root.querySelectorAll('.rc-service'), function (el) {
			var priceEl = el.querySelector('.rc-line-price');
			if (el.classList.contains('is-active')) {
				var p = servicePrice(el);
				subtotal += p;
				if (priceEl) { priceEl.textContent = fmt(p); }
				lines.push({
					name: el.getAttribute('data-name') || '',
					incl: parseInt(el.getAttribute('data-incl'), 10) || 0,
					extra: parseInt(el.getAttribute('data-extra'), 10) || 0,
					plat: el.getAttribute('data-platlabel') || '',
					price: p
				});
			} else if (priceEl) {
				priceEl.textContent = '';
			}
		});

		var adjustments = [];
		function applyOpt(el, forceOn) {
			if (!el) { return; }
			if (!forceOn && el.type === 'checkbox' && !el.checked) { return; }
			var t = el.getAttribute('data-type');
			var v = parseFloat(el.getAttribute('data-value')) || 0;
			var amt = 0;
			if (t === 'percent') { amt = subtotal * v / 100; }
			else if (t === 'fixed') { amt = v; }
			if (amt) {
				adjustments.push({ label: el.getAttribute('data-label') || '', amt: amt });
			} else if (el.getAttribute('data-label')) {
				adjustments.push({ label: el.getAttribute('data-label'), amt: 0, note: true });
			}
		}

		applyOpt(root.querySelector('input[name="rc_usage"]:checked'), true);
		applyOpt(root.querySelector('input[name="rc_excl"]:checked'), true);
		applyOpt(root.querySelector('.rc-rush'));
		applyOpt(root.querySelector('.rc-travelfee'));

		var addTotal = 0;
		adjustments.forEach(function (a) { addTotal += a.amt; });
		return { lines: lines, adjustments: adjustments, subtotal: subtotal, total: subtotal + addTotal };
	}

	function lineLabel(l) {
		var label = l.name + (l.plat ? ' (' + l.plat + ')' : '');
		if (l.extra > 0) {
			label += (l.incl > 0) ? (' — ' + l.incl + ' + ' + l.extra + ' extra') : (' × ' + (l.extra));
		}
		return label;
	}

	function renderSummary(data) {
		var box = root.querySelector('.rc-summary-body');
		if (!box) { return; }
		if (!data.lines.length) {
			box.innerHTML = '<p class="rc-summary-empty">' + (cfg.emptyText || 'Select one or more services to build your estimate.') + '</p>';
		} else {
			var html = '<ul class="rc-summary-list">';
			data.lines.forEach(function (l) {
				html += '<li><span>' + esc(lineLabel(l)) + '</span><span>' + fmt(l.price) + '</span></li>';
			});
			data.adjustments.forEach(function (a) {
				html += '<li class="rc-summary-adj"><span>' + esc(a.label) + '</span><span>' + (a.note ? '—' : '+ ' + fmt(a.amt)) + '</span></li>';
			});
			html += '</ul>';
			box.innerHTML = html;
		}
		var tv = root.querySelector('.rc-total-value');
		if (tv) { tv.textContent = fmt(data.total); }
	}

	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	function recalc() { renderSummary(collect()); }

	/* ---- Service add / remove / extra-item stepper ---- */
	each(root.querySelectorAll('.rc-service'), function (el) {
		var addBtn = el.querySelector('.rc-add');
		var removeBtn = el.querySelector('.rc-remove');
		var num = el.querySelector('.rc-extranum');
		var plus = el.querySelector('.rc-plus');
		var minus = el.querySelector('.rc-minus');

		function sync() {
			var extra = parseInt(el.getAttribute('data-extra'), 10) || 0;
			if (num) { num.textContent = extra; }
		}
		if (addBtn) {
			addBtn.addEventListener('click', function () {
				el.classList.add('is-active');
				sync(); recalc();
			});
		}
		if (removeBtn) {
			removeBtn.addEventListener('click', function () {
				el.classList.remove('is-active');
				el.setAttribute('data-extra', 0);
				sync(); recalc();
			});
		}
		if (plus) {
			plus.addEventListener('click', function () {
				el.setAttribute('data-extra', (parseInt(el.getAttribute('data-extra'), 10) || 0) + 1);
				sync(); recalc();
			});
		}
		if (minus) {
			minus.addEventListener('click', function () {
				var extra = parseInt(el.getAttribute('data-extra'), 10) || 0;
				el.setAttribute('data-extra', Math.max(0, extra - 1));
				sync(); recalc();
			});
		}
		sync();
	});

	each(root.querySelectorAll('input[name="rc_usage"],input[name="rc_excl"],.rc-rush,.rc-travelfee'), function (el) {
		el.addEventListener('change', recalc);
	});

	/* ---- Copy summary ---- */
	function buildText(data) {
		var out = [];
		out.push((cfg.talent || 'Rate') + ' — Rate Estimate');
		out.push('');
		out.push((cfg.tServices || 'Services') + ':');
		if (data.lines.length) {
			data.lines.forEach(function (l) {
				out.push('- ' + lineLabel(l) + ' — ' + fmt(l.price));
			});
		} else {
			out.push('- (none selected)');
		}
		if (data.adjustments.length) {
			out.push('');
			out.push((cfg.tOptions || 'Options') + ':');
			data.adjustments.forEach(function (a) {
				out.push('- ' + a.label + (a.note ? '' : ' — + ' + fmt(a.amt)));
			});
		}
		out.push('');
		out.push((cfg.tTotal || 'Total Estimate') + ': ' + fmt(data.total));
		out.push('');
		out.push(cfg.copyFooter || 'This is an indicative estimate. Final terms confirmed by management.');
		return out.join('\n');
	}

	function showPopup() {
		var pop = root.querySelector('.rc-popup');
		if (pop) { pop.classList.add('is-open'); }
	}
	var closeEls = root.querySelectorAll('.rc-popup-close, .rc-popup-backdrop');
	each(closeEls, function (el) {
		el.addEventListener('click', function () {
			var pop = root.querySelector('.rc-popup');
			if (pop) { pop.classList.remove('is-open'); }
		});
	});

	// iOS/Android-safe copy. Runs synchronously inside the tap so the clipboard
	// write is allowed; always resolves so the popup shows regardless.
	function legacyCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.contentEditable = 'true';
		ta.style.position = 'fixed';
		ta.style.top = '0';
		ta.style.left = '0';
		ta.style.width = '1px';
		ta.style.height = '1px';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		var range = document.createRange();
		range.selectNodeContents(ta);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
		ta.setSelectionRange(0, text.length);
		var ok = false;
		try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
		sel.removeAllRanges();
		document.body.removeChild(ta);
		return ok;
	}
	function doCopy(text) {
		// Try the legacy path first (works synchronously in the tap on iOS),
		// then also attempt the async Clipboard API when available.
		var ok = legacyCopy(text);
		if (navigator.clipboard && navigator.clipboard.writeText) {
			try { navigator.clipboard.writeText(text).then(function () {}, function () {}); ok = true; } catch (e) {}
		}
		return ok;
	}

	var barHint = root.querySelector('.rc-bar-hint');
	var barHintText = barHint ? barHint.textContent : '';
	var copyBtn = root.querySelector('.rc-copy');
	if (copyBtn) {
		copyBtn.addEventListener('click', function () {
			var data = collect();
			if (!data.lines.length) {
				copyBtn.classList.add('rc-shake');
				setTimeout(function () { copyBtn.classList.remove('rc-shake'); }, 500);
				if (barHint) {
					barHint.textContent = cfg.emptyHint || 'Please add at least one service first.';
					barHint.classList.add('rc-bar-hint-warn');
					setTimeout(function () {
						barHint.classList.remove('rc-bar-hint-warn');
						barHint.textContent = barHintText;
					}, 2500);
				}
				return;
			}
			doCopy(buildText(data));
			showPopup();
		});
	}

	/* ---- Compact summary bar expand/collapse ---- */
	var bar = root.querySelector('.rc-bar');
	var barToggle = root.querySelector('.rc-bar-toggle');
	if (bar && barToggle) {
		barToggle.addEventListener('click', function () {
			var open = bar.classList.toggle('is-open');
			barToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ---- Reference prices + option notes (re-rendered on currency switch) ---- */
	function renderStatic() {
		each(root.querySelectorAll('.rc-service'), function (el) {
			var base = parseFloat(el.getAttribute('data-base')) || 0;
			var addl = parseFloat(el.getAttribute('data-addl')) || 0;
			var baseEl = el.querySelector('.rc-price-base');
			var addlEl = el.querySelector('.rc-price-addl');
			var capEl = el.querySelector('.rc-extra-cap');
			if (baseEl) { baseEl.textContent = fmt(base); }
			if (addlEl && addl > 0) { addlEl.textContent = (cfg.perExtra || '%s per extra item').replace('%s', fmt(addl)); }
			if (capEl && addl > 0) { capEl.textContent = (cfg.extraCap || 'Extra items · %s each').replace('%s', fmt(addl)); }
		});
		each(root.querySelectorAll('.rc-opt input[data-type], .rc-rush, .rc-travelfee'), function (input) {
			var label = input.closest ? input.closest('label') : input.parentNode;
			if (!label) { return; }
			var adj = label.querySelector('.rc-opt-adj');
			if (!adj) { return; }
			var t = input.getAttribute('data-type');
			var v = parseFloat(input.getAttribute('data-value')) || 0;
			if (t === 'percent' && v) { adj.textContent = fmtPct(v); }
			else if (t === 'fixed' && v) { adj.textContent = '+' + fmt(v); }
			else { adj.textContent = ''; }
		});
	}

	/* ---- Currency switch (EGP / USD) ---- */
	function setMode(m) {
		mode = (m === 'usd') ? 'usd' : 'base';
		each(root.querySelectorAll('.rc-cur'), function (b) {
			b.classList.toggle('is-active', b.getAttribute('data-cur') === mode);
		});
		renderStatic();
		recalc();
	}
	each(root.querySelectorAll('.rc-cur'), function (b) {
		b.addEventListener('click', function () { setMode(b.getAttribute('data-cur')); });
	});

	renderStatic();
	recalc();
})();
