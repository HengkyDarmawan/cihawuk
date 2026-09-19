<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Modul fasilitas belum dibangun; section ini hanya menampilkan keadaan kosong yang jujur. */
?>
<section class="section" aria-labelledby="fasilitas-title">
	<div class="container-wide">
		<p class="eyebrow"><?= e($s['subtitle'] ?: 'Fasilitas') ?></p>
		<h2 class="section-title" id="fasilitas-title"><?= e($s['title'] ?: 'Fasilitas desa') ?></h2>
		<div class="empty-state">
			<?= icon('map-pin') ?>
			<h3>Direktori fasilitas sedang disiapkan</h3>
			<p class="mb-0">Data fasilitas ditampilkan setelah nama, lokasi, dan verifikasinya lengkap.</p>
		</div>
	</div>
</section>
