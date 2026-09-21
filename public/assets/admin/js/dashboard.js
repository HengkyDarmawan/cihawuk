/*
 * Dashboard Desa Cihawuk — utilitas bersama.
 * - Token CSRF CI3 berputar setiap POST: token terbaru dari respons JSON langsung
 *   dipasang ke seluruh form; request mutation diserialkan agar tidak saling mendahului.
 * - Tidak ada pengulangan otomatis untuk aksi yang berakibat ganda.
 */
window.Chw = (function ($) {
	'use strict';

	var body = document.body;
	var tokenName = body.getAttribute('data-csrf-name');
	var tokenHash = body.getAttribute('data-csrf-hash');
	var queue = Promise.resolve();

	function setToken(name, hash) {
		if (!hash) return;
		tokenName = name || tokenName;
		tokenHash = hash;
		body.setAttribute('data-csrf-hash', hash);
		document.querySelectorAll('input[name="' + tokenName + '"]').forEach(function (input) {
			input.value = hash;
		});
	}

	function refreshToken() {
		return fetch('/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.csrf) setToken(data.csrf.name, data.csrf.hash);
				return data;
			});
	}

	/** POST ber-CSRF yang diserialkan. body: FormData atau objek. */
	function post(url, data, options) {
		options = options || {};
		var run = function () {
			var form;
			if (data instanceof FormData) {
				form = data;
			} else {
				form = new FormData();
				Object.keys(data || {}).forEach(function (k) {
					if (Array.isArray(data[k])) { data[k].forEach(function (v) { form.append(k + '[]', v); }); }
					else if (data[k] !== undefined && data[k] !== null) { form.append(k, data[k]); }
				});
			}
			form.set(tokenName, tokenHash);
			return fetch(url, {
				method: 'POST',
				body: form,
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
			}).then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (payload) {
					if (payload && payload.csrf) setToken(payload.csrf.name, payload.csrf.hash);
					if (!response.ok) {
						var err = new Error(payload.message || 'Permintaan gagal (' + response.status + ').');
						err.status = response.status;
						err.payload = payload;
						throw err;
					}
					return payload;
				});
			});
		};
		queue = queue.then(run, run);
		return queue;
	}

	function alertError(err) {
		var message = (err && err.message) || 'Terjadi kesalahan.';
		if (err && err.status === 403 && err.payload && err.payload.code === 'csrf_invalid') {
			message = err.payload.message + ' Halaman akan dimuat ulang setelah token diperbarui.';
			refreshToken();
		}
		if (window.Swal) {
			window.Swal.fire({ icon: 'error', title: 'Tidak dapat diproses', text: message, confirmButtonColor: '#174B3A' });
		} else {
			window.alert(message);
		}
	}

	/* Konfirmasi untuk aksi penting (form dengan data-confirm). */
	document.querySelectorAll('form[data-confirm]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			if (form.dataset.confirmed === '1') return;
			e.preventDefault();
			var text = form.getAttribute('data-confirm');
			var title = form.getAttribute('data-confirm-title') || 'Konfirmasi tindakan';
			var confirmText = form.getAttribute('data-confirm-ok') || 'Ya, lanjutkan';
			var proceed = function () { form.dataset.confirmed = '1'; form.submit(); };
			// Batal: kembalikan pilihan dropdown yang langsung mengirim form.
			var cancel = function () {
				form.querySelectorAll('select[data-autosubmit]').forEach(function (s) { s.value = s.getAttribute('data-original') || ''; });
			};
			if (window.Swal) {
				window.Swal.fire({
					icon: 'question', title: title, text: text, showCancelButton: true,
					confirmButtonText: confirmText, cancelButtonText: 'Batal',
					confirmButtonColor: '#174B3A', cancelButtonColor: '#6c757d', focusCancel: true
				}).then(function (result) { if (result.isConfirmed) { proceed(); } else { cancel(); } });
			} else if (window.confirm(text)) {
				proceed();
			} else {
				cancel();
			}
		});
	});

	/* Cegah pengiriman ganda pada form biasa. */
	document.querySelectorAll('form[data-once]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			if (form.dataset.submitting === '1') { e.preventDefault(); return; }
			form.dataset.submitting = '1';
			form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.classList.add('disabled'); b.setAttribute('aria-disabled', 'true'); });
			window.setTimeout(function () {
				form.dataset.submitting = '';
				form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.classList.remove('disabled'); b.removeAttribute('aria-disabled'); });
			}, 8000);
		});
	});

	/* Tampilkan/sembunyikan password. */
	document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
		var input = document.getElementById(btn.getAttribute('aria-controls'));
		if (!input) return;
		btn.addEventListener('click', function () {
			var show = input.type === 'password';
			input.type = show ? 'text' : 'password';
			btn.setAttribute('aria-pressed', show ? 'true' : 'false');
		});
	});

	/* Salin teks (kode aktivasi, nomor tiket). */
	document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var el = document.getElementById(btn.getAttribute('data-copy-target'));
			if (!el) return;
			var text = el.textContent.trim();
			var done = function () {
				var old = btn.textContent;
				btn.textContent = 'Tersalin';
				setTimeout(function () { btn.textContent = old; }, 2000);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(done, function () {});
			} else {
				var range = document.createRange();
				range.selectNodeContents(el);
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(range);
				try { document.execCommand('copy'); done(); } catch (e) {}
			}
		});
	});

	/* Dropdown yang langsung mengirim form (mis. ganti role di tabel pengguna). */
	document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
		select.addEventListener('change', function () {
			var form = select.form;
			if (!form) return;
			if (form.requestSubmit) { form.requestSubmit(); } else { form.dispatchEvent(new Event('submit', { cancelable: true })); }
		});
	});

	/* Checkbox "Pilih semua" per kelompok izin: data-check-all="<id grup>" pada wadah data-check-group. */
	document.querySelectorAll('[data-check-group]').forEach(function (group) {
		var all = group.querySelector('[data-check-all]');
		var items = group.querySelectorAll('input[type="checkbox"][data-check-item]');
		var counter = group.querySelector('[data-check-count]');
		var sync = function () {
			var checked = Array.prototype.filter.call(items, function (i) { return i.checked; }).length;
			if (all) {
				all.checked = checked === items.length && items.length > 0;
				all.indeterminate = checked > 0 && checked < items.length;
			}
			if (counter) counter.textContent = checked + '/' + items.length;
		};
		if (all) {
			all.addEventListener('change', function () {
				items.forEach(function (i) { if (!i.disabled) i.checked = all.checked; });
				sync();
			});
		}
		items.forEach(function (i) { i.addEventListener('change', sync); });
		sync();
	});

	/* Tampilkan panel aksi sesuai pilihan (radio/select dengan data-toggle-panel). */
	document.querySelectorAll('[data-toggle-panel]').forEach(function (control) {
		var apply = function () {
			var value = control.type === 'checkbox' ? (control.checked ? '1' : '0') : control.value;
			document.querySelectorAll('[data-panel-for="' + control.getAttribute('data-toggle-panel') + '"]').forEach(function (panel) {
				var match = panel.getAttribute('data-panel-value').split('|').indexOf(value) !== -1;
				panel.hidden = !match;
				panel.querySelectorAll('input, select, textarea').forEach(function (f) {
					if (f.hasAttribute('data-required-when-visible')) { f.required = match; }
					f.disabled = !match;
				});
			});
		};
		control.addEventListener('change', apply);
		apply();
	});

	/* DataTables server-side untuk daftar besar. */
	if ($ && $.fn && $.fn.DataTable) {
		$('table[data-datatable]').each(function () {
			var $table = $(this);
			var extra = $table.data('filters') || {};
			$table.DataTable({
				serverSide: true,
				processing: true,
				autoWidth: false,
				searchDelay: 400,
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[$table.data('order-col') || 0, $table.data('order-dir') || 'desc']],
				ajax: {
					url: $table.data('url'),
					type: 'GET',
					data: function (d) {
						Object.keys(extra).forEach(function (k) { d[k] = extra[k]; });
						document.querySelectorAll('[data-dt-filter]').forEach(function (input) {
							var isCheck = input.type === 'checkbox' || input.type === 'radio';
							d[input.getAttribute('data-dt-filter')] = isCheck ? (input.checked ? input.value : '') : input.value;
						});
						return d;
					},
					error: function (xhr) {
						var msg = 'Data tidak dapat dimuat.';
						try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
						alertError({ message: msg, status: xhr.status });
					}
				},
				language: {
					processing: 'Memuat…',
					lengthMenu: 'Tampilkan _MENU_ baris',
					zeroRecords: 'Tidak ada data yang cocok',
					emptyTable: 'Belum ada data',
					info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
					infoEmpty: 'Tidak ada data',
					infoFiltered: '(disaring dari _MAX_ data)',
					search: 'Cari:',
					paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
					aria: { sortAscending: ': aktifkan untuk urut naik', sortDescending: ': aktifkan untuk urut turun' }
				}
			});
		});
		document.querySelectorAll('[data-dt-filter]').forEach(function (input) {
			input.addEventListener('change', function () { $('table[data-datatable]').DataTable().ajax.reload(); });
		});
	}

	// Urutan section CMS: seret-dan-lepas sebagai pelengkap tombol panah (jalur keyboard tetap utama).
	// Hasil seret hanya mengubah nilai input urutan; penyimpanan tetap lewat submit form ber-CSRF.
	document.querySelectorAll('[data-sortable]').forEach(function (list) {
		var input = document.querySelector('[data-sortable-input]');
		var dragged = null;

		function syncOrder() {
			if (!input) { return; }
			var ids = Array.prototype.map.call(list.querySelectorAll('[data-sortable-item]'), function (item) {
				return item.getAttribute('data-sortable-item');
			});
			input.value = ids.join(',');
		}

		list.querySelectorAll('[data-sortable-item]').forEach(function (item) {
			item.setAttribute('draggable', 'true');
			item.style.cursor = 'grab';

			item.addEventListener('dragstart', function (event) {
				dragged = item;
				item.classList.add('is-dragging');
				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData('text/plain', item.getAttribute('data-sortable-item'));
				}
			});

			item.addEventListener('dragend', function () {
				item.classList.remove('is-dragging');
				dragged = null;
				syncOrder();
			});

			item.addEventListener('dragover', function (event) {
				if (!dragged || dragged === item) { return; }
				event.preventDefault();
				var box = item.getBoundingClientRect();
				var after = (event.clientY - box.top) > (box.height / 2);
				list.insertBefore(dragged, after ? item.nextSibling : item);
			});

			item.addEventListener('drop', function (event) {
				event.preventDefault();
				syncOrder();
			});
		});
	});

	if ($ && $.fn && $.fn.select2) {
		$('select[data-select2]').select2({ theme: 'bootstrap4', width: '100%', language: 'id' });
	}

	return { post: post, refreshToken: refreshToken, setToken: setToken, alertError: alertError };
})(window.jQuery);
