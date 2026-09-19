<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-body">
	<div class="container-site" style="max-width: 760px;">
		<div class="alert alert-success d-flex gap-2" role="status">
			<?= icon('check-circle') ?>
			<div><strong>Laporan Anda diterima.</strong> Simpan bukti di bawah ini sebelum menutup halaman.</div>
		</div>

		<div class="receipt">
			<p class="eyebrow mb-1">Bukti penerimaan</p>
			<h1 class="h3 display-serif mb-1">Laporan <?= e($report_type_label) ?></h1>
			<p class="text-muted">Diterima <?= format_wib($receipt['submitted_at']) ?></p>

			<div class="mb-3">
				<p class="code-label" id="label-ticket">Nomor tiket</p>
				<p class="code-display" id="ticket-code" aria-labelledby="label-ticket"><?= e($receipt['code']) ?></p>
				<button class="btn btn-outline-primary btn-sm mt-2" type="button" data-copy-target="ticket-code" data-copy-status="copy-status"><?= icon('copy') ?> <span data-copy-label>Salin nomor</span></button>
			</div>

			<div class="mb-2">
				<p class="code-label" id="label-access">Kode akses rahasia (ditampilkan sekali)</p>
				<p class="code-display" id="access-code" aria-labelledby="label-access"><?= e($formatted_code) ?></p>
				<button class="btn btn-primary btn-sm mt-2" type="button" data-copy-target="access-code" data-copy-status="copy-status"><?= icon('copy') ?> <span data-copy-label>Salin kode</span></button>
				<button class="btn btn-outline-primary btn-sm mt-2" type="button" data-print><?= icon('printer') ?> Cetak bukti</button>
				<p class="form-text" id="copy-status" role="status"></p>
			</div>

			<div class="privacy-panel mt-3">
				<h2>Cara menggunakan</h2>
				<ul>
					<li>Buka halaman <a href="<?= site_url('lacak') ?>">Lacak Laporan</a>, masukkan nomor tiket dan kode akses.</li>
					<li>Kode akses bersifat rahasia — jangan dibagikan. Petugas desa tidak akan menanyakan kode ini.</li>
					<li>Bila kode hilang, kami tidak dapat memulihkannya. Anda perlu mengirim laporan baru.</li>
					<li>Huruf besar/kecil dan tanda hubung boleh berbeda; sistem menormalkannya saat pengecekan.</li>
				</ul>
			</div>
		</div>

		<div class="d-flex flex-wrap gap-2 mt-4 no-print">
			<a class="btn btn-primary" href="<?= site_url('lacak') ?>">Lacak laporan sekarang</a>
			<form method="post" action="<?= site_url('lapor/berhasil/selesai') ?>">
				<?= csrf_field() ?>
				<button class="btn btn-outline-primary" type="submit">Saya sudah menyimpan kode</button>
			</form>
		</div>
		<p class="small text-muted mt-3 no-print">Halaman ini berhenti menampilkan kode setelah beberapa menit atau saat Anda menekan tombol di atas.</p>
	</div>
</section>
