<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<h1>Informasi desa</h1>
		<p>Berita, agenda, galeri, dan dokumen publik Desa Cihawuk dalam satu halaman pengantar.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-4">
			<div class="col-md-6 col-xl-3">
				<a class="card-soft card-pad h-100 d-block text-decoration-none" href="<?= site_url('berita') ?>">
					<span class="quick-icon mb-2"><?= icon('file-text') ?></span>
					<h2 class="h5">Berita</h2>
					<p class="text-muted mb-0"><?= count($posts) ?> kabar terbaru diterbitkan.</p>
				</a>
			</div>
			<div class="col-md-6 col-xl-3">
				<a class="card-soft card-pad h-100 d-block text-decoration-none" href="<?= site_url('agenda') ?>">
					<span class="quick-icon mb-2"><?= icon('calendar') ?></span>
					<h2 class="h5">Agenda</h2>
					<p class="text-muted mb-0"><?= count($events) ?> kegiatan mendatang.</p>
				</a>
			</div>
			<div class="col-md-6 col-xl-3">
				<a class="card-soft card-pad h-100 d-block text-decoration-none" href="<?= site_url('galeri') ?>">
					<span class="quick-icon mb-2"><?= icon('image') ?></span>
					<h2 class="h5">Galeri</h2>
					<p class="text-muted mb-0"><?= count($galleries) ?> album dokumentasi.</p>
				</a>
			</div>
			<div class="col-md-6 col-xl-3">
				<a class="card-soft card-pad h-100 d-block text-decoration-none" href="<?= site_url('dokumen') ?>">
					<span class="quick-icon mb-2"><?= icon('folder') ?></span>
					<h2 class="h5">Dokumen publik</h2>
					<p class="text-muted mb-0"><?= count($documents) ?> dokumen tersedia.</p>
				</a>
			</div>
		</div>
	</div>
</section>
