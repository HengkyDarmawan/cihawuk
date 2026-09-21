/* Aset & QR: gambar QR (SVG), unduh PNG, salin tautan, cetak, pilih unit, dan tabel lokal. */
(function () {
	'use strict';

	function makeQr(text) {
		if (typeof window.qrcode !== 'function') { return null; }
		var qr = window.qrcode(0, 'M');
		qr.addData(text, 'Byte');
		qr.make();
		return qr;
	}

	/* <div data-qr="URL"> menjadi SVG yang tajam di layar maupun cetakan. */
	document.querySelectorAll('[data-qr]').forEach(function (el) {
		var qr = makeQr(el.getAttribute('data-qr'));
		if (!qr) { return; }
		el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
	});

	/* Modal QR dari daftar inventaris: tombol [data-qr-show] mengisi #qr-modal. */
	var $ = window.jQuery;
	document.querySelectorAll('[data-qr-show]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var box = document.getElementById('qr-modal-box');
			var qr = makeQr(btn.getAttribute('data-qr-show'));
			if (!box || !qr) { return; }
			box.setAttribute('data-qr', btn.getAttribute('data-qr-show'));
			box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
			var code = btn.getAttribute('data-qr-code') || '';
			var set = function (id, text) { var el = document.getElementById(id); if (el) { el.textContent = text; } };
			set('qr-modal-code', code);
			set('qr-modal-name', btn.getAttribute('data-qr-name') || '');
			var unit = document.getElementById('qr-modal-unit');
			if (unit) { unit.value = btn.getAttribute('data-qr-unit') || ''; }
			var detail = document.getElementById('qr-modal-detail');
			if (detail) { detail.href = btn.getAttribute('data-qr-detail') || '#'; }
			document.querySelectorAll('#qr-modal [data-qr-download]').forEach(function (d) { d.setAttribute('data-qr-download', code); });
			if ($ && $.fn && $.fn.modal) { $('#qr-modal').modal('show'); }
		});
	});

	/* Unduh PNG: QR + kode aset di bawahnya, siap ditempel ke dokumen atau dicetak sendiri. */
	document.querySelectorAll('[data-qr-download]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var box = document.querySelector(btn.getAttribute('data-qr-source') || '[data-qr]');
			var qr = box ? makeQr(box.getAttribute('data-qr')) : null;
			if (!qr) { return; }
			var caption = btn.getAttribute('data-qr-download') || '';
			var count = qr.getModuleCount();
			var cell = 10, margin = 4 * cell, size = count * cell + margin * 2, footer = caption ? 56 : 0;
			var canvas = document.createElement('canvas');
			canvas.width = size;
			canvas.height = size + footer;
			var ctx = canvas.getContext('2d');
			ctx.fillStyle = '#ffffff';
			ctx.fillRect(0, 0, canvas.width, canvas.height);
			ctx.fillStyle = '#000000';
			for (var r = 0; r < count; r++) {
				for (var c = 0; c < count; c++) {
					if (qr.isDark(r, c)) { ctx.fillRect(margin + c * cell, margin + r * cell, cell, cell); }
				}
			}
			if (caption) {
				ctx.font = 'bold 28px Consolas, monospace';
				ctx.textAlign = 'center';
				ctx.fillText(caption, size / 2, size + 30, size - 20);
			}
			var link = document.createElement('a');
			link.href = canvas.toDataURL('image/png');
			link.download = 'qr-' + caption.replace(/[^A-Za-z0-9_.-]+/g, '-') + '.png';
			document.body.appendChild(link);
			link.click();
			link.remove();
		});
	});

	/* Salin isi input (mis. tautan QR). */
	document.querySelectorAll('[data-copy-value]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = document.querySelector(btn.getAttribute('data-copy-value'));
			if (!input) { return; }
			var done = function () {
				var old = btn.innerHTML;
				btn.textContent = 'Tersalin';
				window.setTimeout(function () { btn.innerHTML = old; }, 1500);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(input.value).then(done);
			} else {
				input.select();
				document.execCommand('copy');
				done();
			}
		});
	});

	/* Tombol cetak pada lembar label. */
	document.querySelectorAll('[data-print]').forEach(function (btn) {
		btn.addEventListener('click', function () { window.print(); });
	});

	/* Pilih unit untuk "Cetak QR terpilih". */
	var checkAll = document.querySelector('[data-check-all]');
	var selectedBtn = document.querySelector('[data-label-selected]');
	function syncSelected() {
		if (!selectedBtn) { return; }
		var n = document.querySelectorAll('[data-check-item]:checked').length;
		selectedBtn.disabled = n === 0;
		selectedBtn.lastChild.textContent = n ? ' Cetak QR terpilih (' + n + ')' : ' Cetak QR terpilih';
	}
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			document.querySelectorAll('[data-check-item]').forEach(function (cb) { cb.checked = checkAll.checked; });
			syncSelected();
		});
	}
	document.addEventListener('change', function (e) {
		if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-check-item')) { syncSelected(); }
	});

	/* Tabel kecil: cari dan urut di sisi browser (tanpa server-side). */
	var $ = window.jQuery;
	if ($ && $.fn && $.fn.DataTable) {
		$('table[data-local-table]').each(function () {
			var $table = $(this);
			var noSort = [];
			$table.find('thead th').each(function (i) { if (this.getAttribute('data-orderable') === 'false') { noSort.push(i); } });
			$table.DataTable({
				autoWidth: false,
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[parseInt($table.data('order-col') || 0, 10), $table.data('order-dir') || 'asc']],
				columnDefs: noSort.length ? [{ orderable: false, targets: noSort }] : [],
				language: {
					lengthMenu: 'Tampilkan _MENU_ baris',
					zeroRecords: 'Tidak ada data yang cocok',
					emptyTable: 'Belum ada data',
					info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
					infoEmpty: 'Tidak ada data',
					infoFiltered: '(disaring dari _MAX_ data)',
					search: 'Cari:',
					paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' }
				}
			});
		});
	}
})();
