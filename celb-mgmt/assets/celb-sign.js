/* CELB MGMT — contract signing page (standalone).
   Config is injected as window.CELB_SIGN before this script loads. */
(function () {
	'use strict';
	var CFG = window.CELB_SIGN || {};
	var doc = document.querySelector('.celb-sign-doc');
	var form = document.querySelector('.celb-sign-form');
	if (!doc || !form) { return; }

	/* ---- Live placeholder fill ---- */
	function fillToken(key, val) {
		var spans = doc.querySelectorAll('.celb-tok[data-key="' + key + '"]');
		spans.forEach(function (s) {
			s.textContent = val || s.getAttribute('data-label') || '';
			s.classList.toggle('celb-tok--filled', !!val);
		});
		if (key === 'full_name') {
			var nm = doc.querySelector('.celb-actor-name');
			if (nm) { nm.textContent = val || nm.getAttribute('data-label') || ''; }
		}
	}
	form.querySelectorAll('[data-fieldkey]').forEach(function (inp) {
		inp.addEventListener('input', function () { fillToken(inp.getAttribute('data-fieldkey'), inp.value.trim()); });
		fillToken(inp.getAttribute('data-fieldkey'), inp.value.trim());
	});

	/* ---- Signature pad ---- */
	var canvas = document.getElementById('celb-sigpad');
	var ctx = canvas ? canvas.getContext('2d') : null;
	var drawing = false, hasInk = false, last = null;

	function resizePad() {
		if (!canvas) { return; }
		var ratio = window.devicePixelRatio || 1;
		var rect = canvas.getBoundingClientRect();
		var data = hasInk ? canvas.toDataURL() : null;
		canvas.width = rect.width * ratio;
		canvas.height = rect.height * ratio;
		ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
		ctx.lineWidth = 2.2;
		ctx.lineCap = 'round';
		ctx.lineJoin = 'round';
		ctx.strokeStyle = '#111';
		if (data) {
			var img = new Image();
			img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
			img.src = data;
		}
	}
	function posFromEvent(e) {
		var rect = canvas.getBoundingClientRect();
		var p = (e.touches && e.touches[0]) ? e.touches[0] : e;
		return { x: p.clientX - rect.left, y: p.clientY - rect.top };
	}
	function start(e) { e.preventDefault(); drawing = true; last = posFromEvent(e); }
	function move(e) {
		if (!drawing) { return; }
		e.preventDefault();
		var p = posFromEvent(e);
		ctx.beginPath();
		ctx.moveTo(last.x, last.y);
		ctx.lineTo(p.x, p.y);
		ctx.stroke();
		last = p;
		hasInk = true;
	}
	function end() { drawing = false; }

	if (canvas) {
		resizePad();
		window.addEventListener('resize', resizePad);
		canvas.addEventListener('mousedown', start);
		canvas.addEventListener('mousemove', move);
		window.addEventListener('mouseup', end);
		canvas.addEventListener('touchstart', start, { passive: false });
		canvas.addEventListener('touchmove', move, { passive: false });
		canvas.addEventListener('touchend', end);
	}
	var clearBtn = document.querySelector('.celb-sig-clear');
	if (clearBtn) {
		clearBtn.addEventListener('click', function (e) {
			e.preventDefault();
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			hasInk = false;
		});
	}

	/* ---- Submit ---- */
	var submitBtn = document.querySelector('.celb-sign-submit');
	var statusEl = document.querySelector('.celb-sign-status');
	function setStatus(msg, kind) {
		if (!statusEl) { return; }
		statusEl.textContent = msg || '';
		statusEl.className = 'celb-sign-status' + (kind ? ' is-' + kind : '');
	}

	function collect() {
		var data = {}, missing = [];
		(CFG.fields || []).forEach(function (f) {
			var inp = form.querySelector('[data-fieldkey="' + f.key + '"]');
			var v = inp ? inp.value.trim() : '';
			data[f.key] = v;
			if (f.required && !v) { missing.push(f.label); }
		});
		return { data: data, missing: missing };
	}

	/* Build a clean multi-page A4 PDF: paginate the contract into page-sized
	   boxes, each with the logo top-left, and capture each page separately so
	   lines never get cut across a page boundary. */
	function buildPdf() {
		return new Promise(function (resolve, reject) {
			var PAGE_W = 794, PAGE_H = 1123, PAD = 48, LOGO_H = 32, GAP = 18; // A4 @ 96dpi
			var logo = CFG.logo || '';
			var usable = PAGE_H - (PAD * 2) - (logo ? (LOGO_H + GAP) : 0);
			var dir = doc.getAttribute('dir') || 'ltr';
			var stage = document.createElement('div');
			stage.className = 'celb-print';
			document.body.appendChild(stage);

			function newPage() {
				var pg = document.createElement('div');
				pg.className = 'celb-page';
				pg.style.width = PAGE_W + 'px';
				pg.style.height = PAGE_H + 'px';
				pg.style.padding = PAD + 'px';
				var head = logo
					? '<div class="celb-page-head" style="height:' + LOGO_H + 'px;margin-bottom:' + GAP + 'px;"><img class="celb-page-logo" crossorigin="anonymous" src="' + logo + '" style="height:' + LOGO_H + 'px;width:auto;" /></div>'
					: '';
				pg.innerHTML = head + '<div class="celb-page-body" dir="' + dir + '"></div>';
				stage.appendChild(pg);
				return pg;
			}

			var src = doc.cloneNode(true);
			var blocks = Array.prototype.slice.call(src.children);
			var pages = [];
			var page = newPage(); pages.push(page);
			var body = page.querySelector('.celb-page-body');

			blocks.forEach(function (b) {
				body.appendChild(b);
				if (body.scrollHeight > usable && body.children.length > 1) {
					body.removeChild(b);
					page = newPage(); pages.push(page);
					body = page.querySelector('.celb-page-body');
					body.appendChild(b);
				}
			});

			var jsPDF = window.jspdf.jsPDF;
			var pdf = new jsPDF('p', 'mm', 'a4');
			var i = 0;
			function renderNext() {
				if (i >= pages.length) {
					document.body.removeChild(stage);
					resolve(pdf.output('datauristring'));
					return;
				}
				window.html2canvas(pages[i], { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false, width: PAGE_W, height: PAGE_H, windowWidth: PAGE_W }).then(function (cnv) {
					var img = cnv.toDataURL('image/jpeg', 0.92);
					if (i > 0) { pdf.addPage(); }
					pdf.addImage(img, 'JPEG', 0, 0, 210, 297);
					i++;
					renderNext();
				}).catch(function (err) {
					if (stage.parentNode) { document.body.removeChild(stage); }
					reject(err);
				});
			}
			renderNext();
		});
	}

	if (submitBtn) {
		submitBtn.addEventListener('click', function (e) {
			e.preventDefault();
			var c = collect();
			if (c.missing.length) { setStatus('Please complete: ' + c.missing.join(', '), 'err'); return; }
			if (!hasInk) { setStatus('Please draw your signature in the box.', 'err'); return; }
			var consent = form.querySelector('.celb-sign-consent');
			if (consent && !consent.checked) { setStatus('Please tick the confirmation box to continue.', 'err'); return; }
			if (typeof window.jspdf === 'undefined' || typeof window.html2canvas === 'undefined') {
				setStatus('Could not load the PDF engine. Please refresh and try again.', 'err');
				return;
			}

			submitBtn.disabled = true;
			setStatus('Generating your signed contract…', 'busy');

			// Inject the drawn signature into the document.
			var sigData = canvas.toDataURL('image/png');
			var slot = doc.querySelector('.celb-actor-sig');
			if (slot) { slot.innerHTML = '<img src="' + sigData + '" alt="" />'; }

			buildPdf().then(function (pdfData) {
				return fetch(CFG.restUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						cid: CFG.cid,
						token: CFG.token,
						data: c.data,
						actor_sig: sigData,
						pdf: pdfData
					})
				});
			}).then(function (res) {
				return res.json().then(function (body) { return { ok: res.ok, body: body }; });
			}).then(function (r) {
				if (r.ok && r.body && r.body.ok) {
					document.querySelector('.celb-sign-wrap').classList.add('is-done');
					var done = document.querySelector('.celb-sign-done');
					if (done) { done.style.display = 'block'; done.scrollIntoView({ behavior: 'smooth' }); }
				} else {
					setStatus((r.body && r.body.message) ? r.body.message : 'Something went wrong. Please try again.', 'err');
					submitBtn.disabled = false;
				}
			}).catch(function () {
				setStatus('Network error while submitting. Please try again.', 'err');
				submitBtn.disabled = false;
			});
		});
	}
})();
