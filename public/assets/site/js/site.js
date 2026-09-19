/* Desa Cihawuk — interaksi frontend publik (progressive enhancement). */
(function () {
	'use strict';

	var doc = document.documentElement;
	doc.classList.remove('no-js');
	doc.classList.add('js');

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* Header: transparan di atas hero, putih setelah melewati hero. */
	var header = document.querySelector('[data-site-header]');
	if (header) {
		var hero = document.querySelector('.hero');
		var onScroll = function () {
			var threshold = hero ? Math.max(40, hero.offsetHeight - header.offsetHeight - 40) : 8;
			header.classList.toggle('is-scrolled', window.scrollY > (header.classList.contains('is-transparent') ? threshold : 8));
		};
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
	}

	/* Elemen muncul saat masuk viewport (dilewati bila reduced-motion). */
	var reveals = document.querySelectorAll('.reveal');
	if (reveals.length) {
		if (reduceMotion || !('IntersectionObserver' in window)) {
			reveals.forEach(function (el) { el.classList.add('is-visible'); });
		} else {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-visible');
						io.unobserve(entry.target);
					}
				});
			}, { rootMargin: '0px 0px -8% 0px' });
			reveals.forEach(function (el) { io.observe(el); });
		}
	}

	/* Tampilkan/sembunyikan password. */
	document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
		var input = document.getElementById(btn.getAttribute('aria-controls'));
		if (!input) return;
		btn.addEventListener('click', function () {
			var show = input.type === 'password';
			input.type = show ? 'text' : 'password';
			btn.setAttribute('aria-pressed', show ? 'true' : 'false');
			btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
		});
	});

	/* Salin teks (kode akses, nomor tiket). */
	document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = document.getElementById(btn.getAttribute('data-copy-target'));
			if (!target) return;
			var text = target.textContent.trim();
			var done = function () {
				var label = btn.querySelector('[data-copy-label]');
				var status = document.getElementById(btn.getAttribute('data-copy-status'));
				if (label) { var old = label.textContent; label.textContent = 'Tersalin'; setTimeout(function () { label.textContent = old; }, 2000); }
				if (status) { status.textContent = 'Teks berhasil disalin ke clipboard.'; }
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(done, function () { selectText(target); });
			} else {
				selectText(target);
				try { document.execCommand('copy'); done(); } catch (e) { /* pengguna dapat menyalin manual */ }
			}
		});
	});
	function selectText(el) {
		var range = document.createRange();
		range.selectNodeContents(el);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	}

	document.querySelectorAll('[data-print]').forEach(function (btn) {
		btn.addEventListener('click', function () { window.print(); });
	});

	/* Carousel Swiper (bila tersedia). Autoplay tidak dipakai. */
	if (window.Swiper) {
		document.querySelectorAll('[data-swiper]').forEach(function (el) {
			var section = el.closest('section') || document;
			var prev = section.querySelector('[data-swiper-prev]');
			var next = section.querySelector('[data-swiper-next]');
			new window.Swiper(el, {
				slidesPerView: 'auto',
				spaceBetween: 20,
				speed: reduceMotion ? 0 : 500,
				watchOverflow: true,
				keyboard: { enabled: true, onlyInViewport: true },
				a11y: {
					enabled: true,
					prevSlideMessage: 'Slide sebelumnya',
					nextSlideMessage: 'Slide berikutnya',
					firstSlideMessage: 'Ini slide pertama',
					lastSlideMessage: 'Ini slide terakhir',
					slideLabelMessage: '{{index}} dari {{slidesLength}}'
				},
				navigation: prev && next ? { prevEl: prev, nextEl: next, disabledClass: 'is-disabled' } : undefined
			});
		});
	}

	/* Lightbox galeri memakai <dialog> (Escape dan fokus bawaan browser). */
	var dialog = document.getElementById('lightbox');
	if (dialog && typeof dialog.showModal === 'function') {
		var img = dialog.querySelector('img');
		var caption = dialog.querySelector('.lightbox-caption');
		var opener = null;
		document.querySelectorAll('[data-lightbox]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				opener = btn;
				img.src = btn.getAttribute('data-lightbox');
				img.alt = btn.getAttribute('data-alt') || '';
				caption.textContent = btn.getAttribute('data-caption') || '';
				dialog.showModal();
			});
		});
		dialog.addEventListener('close', function () { img.removeAttribute('src'); if (opener) opener.focus(); });
		dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
		dialog.querySelectorAll('[data-dialog-close]').forEach(function (btn) {
			btn.addEventListener('click', function () { dialog.close(); });
		});
	}

	/* Formulir bertahap (seluler): langkah 1–3; desktop menampilkan semuanya. */
	document.querySelectorAll('[data-stepper-form]').forEach(function (form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('.form-step'));
		var indicators = form.querySelectorAll('.stepper li');
		if (steps.length < 2) return;
		var mq = window.matchMedia('(min-width: 992px)');
		var current = 0;
		var firstError = form.querySelector('.is-invalid');
		if (firstError) {
			var stepWithError = firstError.closest('.form-step');
			current = Math.max(0, steps.indexOf(stepWithError));
		}
		var show = function (index, focus) {
			current = index;
			steps.forEach(function (s, i) { s.hidden = !mq.matches && i !== index; });
			indicators.forEach(function (li, i) {
				if (i === index) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
				li.classList.toggle('is-done', i < index);
			});
			if (focus && !mq.matches) {
				var heading = steps[index].querySelector('.form-step-title');
				if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus(); }
			}
		};
		form.querySelectorAll('[data-step-next]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var fields = steps[current].querySelectorAll('input, select, textarea');
				for (var i = 0; i < fields.length; i++) {
					if (!fields[i].checkValidity()) { fields[i].reportValidity(); return; }
				}
				show(Math.min(current + 1, steps.length - 1), true);
				if (current === steps.length - 1) fillReview(form);
			});
		});
		form.querySelectorAll('[data-step-prev]').forEach(function (btn) {
			btn.addEventListener('click', function () { show(Math.max(current - 1, 0), true); });
		});
		mq.addEventListener ? mq.addEventListener('change', function () { show(current, false); }) : mq.addListener(function () { show(current, false); });
		show(current, false);
		fillReview(form);
		form.addEventListener('input', function () { fillReview(form); });
	});

	function fillReview(form) {
		form.querySelectorAll('[data-review-for]').forEach(function (out) {
			var field = form.elements[out.getAttribute('data-review-for')];
			if (!field) return;
			var value = '';
			if (field.tagName === 'SELECT') {
				value = field.selectedIndex > 0 ? field.options[field.selectedIndex].text : '';
			} else if (field.length && field[0] && field[0].type === 'radio') {
				for (var i = 0; i < field.length; i++) { if (field[i].checked) { value = field[i].parentNode.textContent.trim(); } }
			} else if (field.type === 'file') {
				value = field.files && field.files.length ? field.files.length + ' berkas' : '';
			} else {
				value = field.value;
			}
			out.textContent = value ? value : '—';
		});
	}

	/* Cegah kirim ganda: nonaktifkan tombol setelah submit valid. */
	document.querySelectorAll('form[data-once]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			if (form.dataset.submitting === '1') { e.preventDefault(); return; }
			form.dataset.submitting = '1';
			form.querySelectorAll('button[type="submit"]').forEach(function (b) {
				b.setAttribute('aria-disabled', 'true');
				b.classList.add('disabled');
				var label = b.querySelector('[data-loading-label]');
				if (label) label.textContent = label.getAttribute('data-loading-label');
			});
		});
	});

	/* Periksa ukuran lampiran di browser sebelum dikirim (server tetap memvalidasi). */
	document.querySelectorAll('input[type="file"][data-max-files]').forEach(function (input) {
		input.addEventListener('change', function () {
			var maxFiles = parseInt(input.getAttribute('data-max-files'), 10);
			var maxBytes = parseInt(input.getAttribute('data-max-bytes'), 10);
			var maxTotal = parseInt(input.getAttribute('data-max-total'), 10);
			var msg = '';
			var total = 0;
			if (input.files.length > maxFiles) msg = 'Maksimal ' + maxFiles + ' berkas.';
			for (var i = 0; i < input.files.length && !msg; i++) {
				total += input.files[i].size;
				if (input.files[i].size > maxBytes) msg = 'Berkas "' + input.files[i].name + '" melebihi 5 MB.';
			}
			if (!msg && total > maxTotal) msg = 'Total lampiran melebihi 15 MB.';
			input.setCustomValidity(msg);
			var out = document.getElementById(input.getAttribute('aria-describedby').split(' ')[0]);
			var status = document.getElementById(input.id + '-status');
			if (status) status.textContent = msg || (input.files.length ? input.files.length + ' berkas dipilih.' : '');
			if (msg) input.reportValidity();
		});
	});

	/* Titik lokasi: geolokasi hanya atas tindakan pengguna. */
	document.querySelectorAll('[data-geolocate]').forEach(function (btn) {
		if (!('geolocation' in navigator)) { btn.hidden = true; return; }
		btn.addEventListener('click', function () {
			var status = document.getElementById(btn.getAttribute('data-status'));
			status.textContent = 'Meminta lokasi dari perangkat…';
			navigator.geolocation.getCurrentPosition(function (pos) {
				document.getElementById('latitude').value = pos.coords.latitude.toFixed(6);
				document.getElementById('longitude').value = pos.coords.longitude.toFixed(6);
				status.textContent = 'Titik lokasi ditambahkan (akurasi sekitar ' + Math.round(pos.coords.accuracy) + ' m). Anda dapat menghapusnya.';
				var clear = document.querySelector('[data-geoclear]');
				if (clear) clear.hidden = false;
			}, function () {
				status.textContent = 'Lokasi tidak dapat diambil. Anda tetap dapat menuliskan lokasi secara manual.';
			}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
		});
	});
	document.querySelectorAll('[data-geoclear]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.getElementById('latitude').value = '';
			document.getElementById('longitude').value = '';
			btn.hidden = true;
			var status = document.getElementById(btn.getAttribute('data-status'));
			if (status) status.textContent = 'Titik lokasi dihapus.';
		});
	});

	/* Tampilkan field sesuai jenis laporan. */
	document.querySelectorAll('[data-report-type-form]').forEach(function (form) {
		var rules = {};
		try { rules = JSON.parse(document.getElementById('field-rules').textContent); } catch (e) { rules = {}; }
		var categorySelect = form.querySelector('#category_id');
		var apply = function () {
			var checked = form.querySelector('input[name="report_type"]:checked');
			var type = checked ? checked.value : null;
			form.querySelectorAll('[data-field-group]').forEach(function (group) {
				var key = group.getAttribute('data-field-group');
				var rule = type && rules[type] ? rules[type][key] : 'optional';
				group.hidden = rule === 'hidden';
				group.querySelectorAll('input, textarea, select').forEach(function (f) { f.disabled = rule === 'hidden'; });
			});
			if (categorySelect) {
				Array.prototype.forEach.call(categorySelect.options, function (opt) {
					var t = opt.getAttribute('data-type');
					opt.hidden = !!(type && t && t !== type);
					opt.disabled = opt.hidden;
				});
				if (categorySelect.selectedOptions[0] && categorySelect.selectedOptions[0].disabled) categorySelect.value = '';
			}
		};
		form.addEventListener('change', function (e) { if (e.target.name === 'report_type') apply(); });
		apply();
	});
})();
