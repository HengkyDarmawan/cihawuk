/*
 * Peningkatan progresif untuk struktur organisasi.
 *
 * Markup pohon sudah lengkap dan terbaca tanpa JavaScript. Berkas ini hanya menambah
 * lipat/buka dan pencarian. Tidak ada data tambahan yang diambil dari server, dan tidak
 * ada biodata yang dimasukkan ke atribut DOM.
 */
(function () {
	'use strict';
	var chart = document.querySelector('[data-org-chart]');
	if (!chart) return;

	var toolbar = document.querySelector('[data-org-toolbar]');
	if (toolbar) toolbar.hidden = false;

	// Tambahkan tombol lipat pada setiap node yang punya bawahan.
	chart.querySelectorAll('.org-node').forEach(function (node, index) {
		var childList = node.querySelector(':scope > ul');
		if (!childList) return;
		var id = 'org-children-' + index;
		childList.id = id;
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'org-toggle';
		button.setAttribute('aria-expanded', 'true');
		button.setAttribute('aria-controls', id);
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
			if (term !== '') setOpen(true);
		});
	}
})();
