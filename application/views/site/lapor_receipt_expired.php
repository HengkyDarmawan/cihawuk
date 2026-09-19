<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-body">
	<div class="container-site" style="max-width: 680px;">
		<div class="card-soft card-pad">
			<p class="eyebrow">Bukti penerimaan</p>
			<h1 class="h3 display-serif">Bukti sudah tidak ditampilkan</h1>
			<p>Demi keamanan, kode akses hanya tampil sesaat setelah laporan dikirim dan tidak dapat ditampilkan ulang.</p>
			<ul>
				<li>Jika Anda sudah menyimpan nomor tiket dan kode akses, lanjutkan ke halaman pelacakan.</li>
				<li>Jika kode akses hilang, laporan Anda tetap diproses petugas, tetapi Anda tidak dapat membuka detailnya. Anda dapat mengirim laporan baru dan menyebutkan nomor tiket lama pada uraian.</li>
			</ul>
			<div class="d-flex flex-wrap gap-2 mt-3">
				<a class="btn btn-primary" href="<?= site_url('lacak') ?>">Lacak laporan</a>
				<a class="btn btn-outline-primary" href="<?= site_url('lapor') ?>">Buat laporan baru</a>
			</div>
		</div>
	</div>
</section>
