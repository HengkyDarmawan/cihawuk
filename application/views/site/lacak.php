<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<h1>Lacak laporan</h1>
		<p>Masukkan nomor tiket dan kode akses rahasia yang Anda terima saat mengirim laporan.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-4">
			<div class="col-lg-7">
				<div class="card-soft card-pad">
					<?php if ( ! empty($track_error)): ?>
						<div class="alert alert-danger d-flex gap-2" role="alert"><?= icon('alert-circle') ?><div><?= e($track_error) ?></div></div>
					<?php endif; ?>
					<form method="post" action="<?= site_url('lacak') ?>" novalidate data-once>
						<?= csrf_field() ?>
						<?= ui_input(array('name' => 'public_code', 'label' => 'Nomor tiket', 'required' => TRUE, 'maxlength' => 20,
							'placeholder' => 'CHW-2026-XXXXXXXX', 'raw_attrs' => 'autocapitalize="characters" spellcheck="false"')) ?>
						<?= ui_input(array('name' => 'access_code', 'label' => 'Kode akses', 'required' => TRUE, 'maxlength' => 60, 'value' => '',
							'autocomplete' => 'off', 'help' => 'Huruf besar/kecil dan tanda hubung boleh berbeda.', 'raw_attrs' => 'autocapitalize="characters" spellcheck="false"')) ?>
						<button class="btn btn-primary btn-lg w-100" type="submit"><?= icon('search') ?> <span data-loading-label="Memeriksa…">Buka laporan</span></button>
					</form>
				</div>
			</div>
			<aside class="col-lg-5">
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('info') ?> Tentang pelacakan</h2>
					<ul class="small mb-0 ps-3">
						<li>Nomor tiket saja tidak cukup — kode akses adalah kunci rahasianya.</li>
						<li>Percobaan yang gagal dibatasi untuk mencegah tebakan otomatis.</li>
						<li>Sesi pelacakan berlaku 30 menit dan hanya untuk satu laporan.</li>
					</ul>
				</div>
				<div class="card-soft card-pad">
					<h2 class="h6"><?= icon('user-check') ?> Punya akun warga?</h2>
					<p class="small mb-2">Laporan yang dibuat dari dashboard warga dapat dipantau tanpa kode akses.</p>
					<a class="btn btn-outline-primary btn-sm" href="<?= site_url('masuk') ?>">Masuk ke dashboard</a>
				</div>
			</aside>
		</div>
	</div>
</section>
