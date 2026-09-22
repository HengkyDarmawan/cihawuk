/* Kalender agenda: klik kegiatan/tanggal -> popup SweetAlert2.
   Tanpa SweetAlert2 (atau tanpa JS) tautan biasa ke halaman detail tetap berjalan.
   Semua isi dari server di-escape sebelum masuk ke HTML popup. */
(function () {
	'use strict';
	if (!window.Swal) return;

	var Dialog = window.Swal.mixin({
		confirmButtonColor: '#174B3A', cancelButtonColor: '#6c757d', reverseButtons: true,
		customClass: { popup: 'chw-swal cal-swal' }, showCloseButton: true
	});

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function parse(el, attr) {
		try { return JSON.parse(el.getAttribute(attr)); } catch (e) { return null; }
	}

	// Ikon Feather dari sprite yang sama dengan halaman (diambil dari ikon yang sudah ada).
	var useEl = document.querySelector('svg.icon use');
	var sprite = useEl ? (useEl.getAttribute('href') || '').split('#')[0] : '';
	function icon(name) {
		return sprite ? '<svg class="icon" aria-hidden="true" focusable="false"><use href="' + esc(sprite) + '#' + name + '"></use></svg>' : '';
	}

	function row(name, text) {
		return text ? '<li><span class="cal-swal-ico">' + icon(name) + '</span><span>' + esc(text) + '</span></li>' : '';
	}

	function showEvent(ev, backToDay) {
		var html = '';
		if (ev.poster) html += '<img class="cal-swal-poster" src="' + esc(ev.poster) + '" alt="">';
		html += '<ul class="cal-swal-meta">' + row('clock', ev.when) + row('map-pin', ev.location) + row('users', ev.organizer) + '</ul>';
		if (ev.summary) html += '<p class="cal-swal-summary">' + esc(ev.summary) + '</p>';
		Dialog.fire({
			title: esc(ev.title),
			html: html,
			showCancelButton: true,
			confirmButtonText: 'Lihat detail',
			cancelButtonText: backToDay ? '‹ Kembali' : 'Tutup'
		}).then(function (r) {
			if (r.isConfirmed) { window.location.href = ev.url; }
			else if (backToDay && r.dismiss === window.Swal.DismissReason.cancel) { showDay(backToDay); }
		});
	}

	function showDay(day) {
		if (!day || !day.events || !day.events.length) return;
		if (day.events.length === 1) { showEvent(day.events[0]); return; }
		var html = '<ul class="cal-swal-list">' + day.events.map(function (ev, i) {
			return '<li><button type="button" class="cal-swal-item" data-i="' + i + '"><span class="cal-pill-time">' + esc(ev.time) + '</span>' +
				'<span><strong>' + esc(ev.title) + '</strong>' + (ev.location ? '<small>' + esc(ev.location) + '</small>' : '') + '</span></button></li>';
		}).join('') + '</ul>';
		Dialog.fire({
			title: esc(day.date),
			html: '<p class="cal-swal-count">' + day.events.length + ' kegiatan</p>' + html,
			showConfirmButton: false,
			didOpen: function (popup) {
				popup.querySelectorAll('.cal-swal-item').forEach(function (btn) {
					btn.addEventListener('click', function () { showEvent(day.events[+btn.getAttribute('data-i')], day); });
				});
			}
		});
	}

	document.addEventListener('click', function (e) {
		var evEl = e.target.closest('[data-event]');
		if (evEl) {
			var ev = parse(evEl, 'data-event');
			if (ev) { e.preventDefault(); showEvent(ev); }
			return;
		}
		var dayEl = e.target.closest('[data-day-events]');
		if (dayEl) {
			var day = parse(dayEl, 'data-day-events');
			if (day) { e.preventDefault(); showDay(day); }
		}
	});
})();
