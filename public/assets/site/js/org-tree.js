/*
 * Peningkatan progresif untuk struktur organisasi.
 *
 * Markup pohon sudah lengkap dan terbaca tanpa JavaScript. Berkas ini hanya menambah
 * lipat/buka, pencarian, geser dengan mouse, dan dialog detail (isi `.org-detail` yang sudah dirender server
 * disalin ke <dialog>). Tidak ada data tambahan yang diambil dari server, dan tidak
 * ada biodata yang dimasukkan ke atribut DOM.
 */
(function () {
	'use strict';
	var chart = document.querySelector('[data-org-chart]');
	if (!chart) return;

	var toolbar = document.querySelector('[data-org-toolbar]');
	if (toolbar) toolbar.hidden = false;

	// Detail disembunyikan dari kartu; tanpa dialog (browser lama) detail tetap tampil.
	var dialog = document.getElementById('org-dialog');
	var dialogContent = dialog ? dialog.querySelector('[data-org-dialog-content]') : null;
	var opener = null;
	if (dialog && dialogContent && typeof dialog.showModal === 'function') {
		chart.classList.add('org-chart-compact');
		chart.querySelectorAll('[data-org-open]').forEach(function (button) {
			button.addEventListener('click', function () {
				var detail = button.parentNode.querySelector(':scope > .org-detail');
				if (!detail) return;
				opener = button;
				dialogContent.innerHTML = '';
				dialogContent.appendChild(detail.cloneNode(true));
				dialog.showModal();
			});
		});
		dialog.addEventListener('close', function () {
			dialogContent.innerHTML = '';
			if (opener) opener.focus();
		});
		dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
		dialog.querySelectorAll('[data-dialog-close]').forEach(function (btn) {
			btn.addEventListener('click', function () { dialog.close(); });
		});
	} else {
		chart.querySelectorAll('[data-org-open]').forEach(function (button) { button.disabled = true; });
	}

	// Tambahkan tombol lipat pada setiap node yang punya bawahan.
	chart.querySelectorAll('.org-node').forEach(function (node, index) {
		// Grid jabatan tanpa bawahan tidak punya kartu sendiri, jadi tidak diberi tombol.
		if (node.classList.contains('org-leaf-group')) return;
		var childList = node.querySelector(':scope > ul');
		if (!childList) return;
		var id = 'org-children-' + index;
		childList.id = id;
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'org-toggle';
		button.setAttribute('aria-expanded', 'true');
		button.setAttribute('aria-controls', id);
		button.setAttribute('aria-label', 'Buka atau tutup bawahan');
		button.textContent = '−';
		button.addEventListener('click', function () {
			var open = button.getAttribute('aria-expanded') === 'true';
			button.setAttribute('aria-expanded', open ? 'false' : 'true');
			button.textContent = open ? '+' : '−';
			childList.hidden = open;
		});
		var card = node.querySelector(':scope > .org-card');
		if (card) card.appendChild(button);
	});

	var setOpen = function (open) {
		chart.querySelectorAll('.org-toggle').forEach(function (button) {
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
			button.textContent = open ? '−' : '+';
			var list = document.getElementById(button.getAttribute('aria-controls'));
			if (list) list.hidden = !open;
		});
	};

	var expand = document.querySelector('[data-org-expand]');
	var collapse = document.querySelector('[data-org-collapse]');
	if (expand) expand.addEventListener('click', function () { setOpen(true); });
	if (collapse) collapse.addEventListener('click', function () { setOpen(false); });

	var search = document.querySelector('[data-org-search]');
	if (search) {
		search.addEventListener('input', function () {
			var term = search.value.trim().toLowerCase();
			chart.querySelectorAll('.org-node').forEach(function (node) {
				var card = node.querySelector(':scope > .org-card');
				if (!card) return;
				var match = term === '' || card.textContent.toLowerCase().indexOf(term) !== -1;
				card.classList.toggle('org-card-match', term !== '' && match);
				card.classList.toggle('org-card-dim', term !== '' && !match);
			});
			if (term !== '') {
				setOpen(true);
				// Bagan bisa lebih lebar dari layar: bawa kartu pertama yang cocok ke tengah.
				var first = chart.querySelector('.org-card-match');
				if (first) first.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
			}
		});
	}

	// Geser bagan dengan menahan klik mouse. Layar sentuh memakai geser bawaan browser.
	var hint = document.createElement('p');
	hint.className = 'org-drag-hint';
	hint.hidden = true;
	hint.textContent = 'Bagan lebih lebar dari layar: geser dengan menahan klik mouse, atau gulir ke samping.';
	chart.parentNode.insertBefore(hint, chart);
	var updateHint = function () {
		var wide = chart.scrollWidth > chart.clientWidth + 4;
		hint.hidden = !wide;
	};
	updateHint();
	window.addEventListener('resize', updateHint);
	// Membuka/menutup cabang mengubah lebar bagan.
	document.addEventListener('click', function () { setTimeout(updateHint, 0); });

	chart.classList.add('is-draggable');
	var drag = null;
	var suppressClick = false;
	chart.addEventListener('pointerdown', function (e) {
		if (e.pointerType !== 'mouse' || e.button !== 0) return;
		if (e.target.closest('.org-toggle, input, select, textarea, a, .org-detail')) return;
		drag = { x: e.clientX, y: e.clientY, left: chart.scrollLeft, moved: false, id: e.pointerId };
	});
	chart.addEventListener('pointermove', function (e) {
		if (!drag || e.pointerId !== drag.id) return;
		var dx = e.clientX - drag.x;
		var dy = e.clientY - drag.y;
		if (!drag.moved) {
			// Ambang 5px supaya klik biasa pada kartu tetap membuka detail.
			if (Math.abs(dx) < 5 && Math.abs(dy) < 5) return;
			drag.moved = true;
			chart.classList.add('is-dragging');
			try { chart.setPointerCapture(e.pointerId); } catch (err) { /* abaikan */ }
		}
		chart.scrollLeft = drag.left - dx;
		window.scrollBy(0, -(e.movementY || 0));
		e.preventDefault();
	});
	var endDrag = function (e) {
		if (!drag || (e && e.pointerId !== drag.id)) return;
		if (drag.moved) {
			suppressClick = true;
			setTimeout(function () { suppressClick = false; }, 0);
		}
		chart.classList.remove('is-dragging');
		drag = null;
	};
	chart.addEventListener('pointerup', endDrag);
	chart.addEventListener('pointercancel', endDrag);
	chart.addEventListener('lostpointercapture', endDrag);
	// Klik yang menutup sebuah geseran tidak boleh membuka dialog kartu.
	chart.addEventListener('click', function (e) {
		if (suppressClick) {
			e.preventDefault();
			e.stopPropagation();
			suppressClick = false;
		}
	}, true);
	chart.addEventListener('dragstart', function (e) { e.preventDefault(); });
})();
