/* Pencarian akun warga pada form input loket (hasil dibatasi dan tersamar). */
(function () {
	'use strict';
	var input = document.getElementById('resident-search');
	var results = document.getElementById('resident-results');
	var hidden = document.getElementById('reporter_user_id');
	if (!input || !results || !hidden) return;

	var timer = null;
	var render = function (items) {
		if (!items.length) {
			results.innerHTML = '<p class="small text-muted mb-0">Tidak ada akun yang cocok.</p>';
			return;
		}
		var list = document.createElement('div');
		list.className = 'list-group';
		items.forEach(function (item) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'list-group-item list-group-item-action py-2';
			btn.innerHTML = '<strong></strong> <span class="small text-muted"></span>';
			btn.querySelector('strong').textContent = item.label;
			btn.querySelector('span').textContent = item.contact;
			btn.addEventListener('click', function () {
				hidden.value = item.id;
				results.innerHTML = '';
				var chosen = document.createElement('p');
				chosen.className = 'small mb-0';
				chosen.textContent = 'Pelapor dipilih: ' + item.label;
				results.appendChild(chosen);
				input.value = item.label;
			});
			list.appendChild(btn);
		});
		results.innerHTML = '';
		results.appendChild(list);
	};

	input.addEventListener('input', function () {
		hidden.value = '';
		var q = input.value.trim();
		window.clearTimeout(timer);
		if (q.length < 3) {
			results.innerHTML = '';
			return;
		}
		timer = window.setTimeout(function () {
			results.innerHTML = '<p class="small text-muted mb-0">Mencari…</p>';
			fetch('/admin/laporan/cari-warga?q=' + encodeURIComponent(q), {
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			})
				.then(function (r) { return r.json(); })
				.then(function (payload) { render(payload.data || []); })
				.catch(function () { results.innerHTML = '<p class="small text-danger mb-0">Pencarian gagal. Coba lagi.</p>'; });
		}, 350);
	});
})();
