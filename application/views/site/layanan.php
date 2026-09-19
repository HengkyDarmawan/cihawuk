<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<h1>Layanan warga</h1>
		<p>Pengaduan, aspirasi, dan permintaan informasi ditangani melalui satu sistem tiket. Anda dapat mengirim tanpa akun, atau memakai akun warga agar seluruh laporan terkumpul di satu dashboard.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-4 mb-5">
			<div class="col-md-4">
				<div class="card-soft card-pad h-100">
					<span class="quick-icon mb-2"><?= icon('edit-3') ?></span>
					<h2 class="h5">Tanpa akun (anonim)</h2>
					<p>Tidak diminta nama, NIK, atau nomor telepon. Anda menerima nomor tiket dan kode akses rahasia untuk memantau laporan.</p>
					<a class="btn btn-accent" href="<?= site_url('lapor') ?>">Buat laporan anonim</a>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card-soft card-pad h-100">
					<span class="quick-icon mb-2"><?= icon('user-check') ?></span>
					<h2 class="h5">Dengan akun warga</h2>
					<p>Riwayat laporan, balasan petugas, dan notifikasi tersimpan di dashboard. Daftar cukup dengan nama, username, dan password.</p>
					<div class="d-flex gap-2 flex-wrap">
						<a class="btn btn-primary" href="<?= site_url('masuk') ?>">Masuk</a>
						<a class="btn btn-outline-primary" href="<?= site_url('daftar') ?>">Daftar</a>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card-soft card-pad h-100">
					<span class="quick-icon mb-2"><?= icon('home') ?></span>
					<h2 class="h5">Datang ke kantor desa</h2>
					<p>Petugas loket dapat mencatatkan laporan Anda ke sistem. Anda tetap dapat memilih tidak mencantumkan identitas.</p>
					<?php if (is_array($service_hours) && ! empty($service_hours['label'])): ?>
						<p class="small mb-1"><strong>Jam pelayanan:</strong> <?= e($service_hours['label']) ?></p>
						<?php if ( ! empty($service_hours['is_example'])): ?><p class="small text-muted mb-0">Jadwal contoh — menunggu konfirmasi kantor desa.</p><?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="row g-5">
			<div class="col-lg-7">
				<h2 class="section-title h3">Alur penanganan</h2>
				<ol class="timeline">
					<li><span class="tl-dot"><?= icon('send') ?></span><div class="tl-title">Penerimaan</div><div class="tl-body is-reporter">Laporan masuk mendapat nomor tiket, waktu penerimaan, dan bukti penerimaan.</div></li>
					<li><span class="tl-dot"><?= icon('search') ?></span><div class="tl-title">Verifikasi</div><div class="tl-body is-reporter">Petugas memeriksa kelengkapan, kategori, kewenangan, dan kemungkinan duplikasi.</div></li>
					<li><span class="tl-dot"><?= icon('user-check') ?></span><div class="tl-title">Disposisi</div><div class="tl-body is-reporter">Laporan diteruskan ke petugas atau unit yang bertanggung jawab.</div></li>
					<li><span class="tl-dot"><?= icon('tool') ?></span><div class="tl-title">Penanganan</div><div class="tl-body is-reporter">Petugas menindaklanjuti, dapat meminta kelengkapan, dan mencatat bukti.</div></li>
					<li><span class="tl-dot"><?= icon('message-circle') ?></span><div class="tl-title">Konfirmasi</div><div class="tl-body is-reporter">Hasil disampaikan kepada Anda untuk diterima atau dimintakan tindak lanjut.</div></li>
					<li><span class="tl-dot"><?= icon('check-circle') ?></span><div class="tl-title">Penutupan</div><div class="tl-body is-reporter">Laporan ditutup dengan riwayat yang tetap tersimpan dan dapat dibuka kembali bila perlu.</div></li>
				</ol>
				<p class="small text-muted">Susunan ini adalah rancangan layanan Desa Cihawuk yang dapat disesuaikan dengan SOP desa. Tidak semua laporan memerlukan persetujuan Kepala Desa.</p>

				<h2 class="section-title h3 mt-5">Status laporan</h2>
				<div class="table-wrap">
					<table class="table table-data mb-0">
						<caption class="visually-hidden">Daftar status laporan dan artinya</caption>
						<thead><tr><th scope="col">Status</th><th scope="col">Arti</th></tr></thead>
						<tbody>
						<?php foreach ($statuses as $code => $meta): ?>
							<tr><td><?= ticket_status_badge($code, 'reporter') ?></td><td><?= e($meta['staff']) ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="scroll-hint">Geser tabel ke samping untuk melihat seluruh kolom.</p>
			</div>

			<aside class="col-lg-5">
				<div class="card-soft card-pad mb-4">
					<h2 class="h5">Jenis laporan</h2>
					<?php foreach ($report_types as $code => $label): ?>
						<h3 class="h6 mt-3"><?= e($label) ?></h3>
						<ul class="small mb-0 ps-3">
						<?php foreach ($categories as $cat): if ($cat->report_type !== $code) { continue; } ?>
							<li><?= e($cat->name) ?><?php if ($cat->description): ?> — <span class="text-muted"><?= e($cat->description) ?></span><?php endif; ?></li>
						<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>
					<p class="small text-muted mt-3 mb-0">Kategori sensitif otomatis diperlakukan sebagai laporan rahasia dengan akses terbatas dan tercatat.</p>
				</div>

				<div class="card-soft card-pad mb-4">
					<h2 class="h5">Target layanan (usulan)</h2>
					<ul class="mb-2">
						<li>Verifikasi: 3 hari kerja</li>
						<li>Respons awal petugas: 5 hari kerja setelah disposisi</li>
						<li>Waktu tanggapan pelapor: 10 hari kalender</li>
					</ul>
					<p class="small text-muted mb-0">Nilai ini adalah konfigurasi awal Desa Cihawuk untuk didiskusikan, bukan tenggat hukum nasional, dan dihitung memakai kalender kerja kantor desa.</p>
				</div>

				<div class="card-soft card-pad">
					<h2 class="h5">Di luar kewenangan desa?</h2>
					<p class="mb-0">Laporan tidak dihapus. Petugas memberi petunjuk rujukan dan mencatat instansi tujuan. Bila laporan benar-benar diteruskan, buktinya dicatat pada riwayat laporan Anda. Layanan ini belum terhubung dengan SP4N-LAPOR!.</p>
				</div>
			</aside>
		</div>
	</div>
</section>
