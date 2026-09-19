<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Potensi</li></ol></nav>
		<h1>Potensi desa</h1>
		<p>Pertanian, alam, UMKM, budaya, dan fasilitas desa yang datanya sudah diverifikasi pengelola.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-wide">
		<nav class="filter-chips" aria-label="Filter kategori potensi">
			<a class="chip" href="<?= site_url('potensi') ?>" <?= $active_category === NULL ? 'aria-current="true"' : '' ?>>Semua</a>
			<?php foreach ($categories as $cat): ?>
				<a class="chip" href="<?= site_url('potensi?kategori='.rawurlencode($cat->slug)) ?>" <?= $active_category === $cat->slug ? 'aria-current="true"' : '' ?>><?= e($cat->name) ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if (empty($potentials)): ?>
			<div class="empty-state">
				<?= icon('compass') ?>
				<h2 class="h5">Belum ada potensi yang diterbitkan pada kategori ini</h2>
				<p class="mb-0">Kategori tersedia bukan berarti sudah ada objek yang terverifikasi. Data, foto, dan izin publikasi diperiksa dulu sebelum tampil.</p>
			</div>
		<?php else: ?>
			<div class="potential-grid">
				<?php foreach ($potentials as $p): ?>
				<a class="photo-card" href="<?= site_url('potensi/'.rawurlencode($p->slug)) ?>">
					<?= media_img($p->cover, 'Foto potensi belum tersedia', FALSE, '(min-width: 1200px) 33vw, (min-width: 768px) 46vw, 100vw') ?>
					<?= preview_badge($p->publication_status) ?>
					<span class="photo-card-body">
						<span class="photo-card-cat"><?= e($p->category_name) ?></span>
						<h2 class="h4"><?= e($p->title) ?></h2>
						<p><?= e($p->summary) ?></p>
					</span>
				</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
