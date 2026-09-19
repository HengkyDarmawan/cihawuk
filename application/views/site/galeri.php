<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Galeri</li></ol></nav>
		<h1>Galeri desa</h1>
		<p>Dokumentasi kegiatan dan suasana desa yang sudah memperoleh izin publikasi.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-wide">
		<?php if (empty($galleries)): ?>
			<div class="empty-state">
				<?= icon('image') ?>
				<h2 class="h5">Galeri sedang disiapkan</h2>
				<p class="mb-0">Foto ditampilkan hanya bila status hak publikasinya sudah jelas. Foto stok atau gambar hasil generatif tidak dipakai sebagai dokumentasi desa.</p>
			</div>
		<?php else: ?>
			<?php foreach ($galleries as $gallery): ?>
			<section class="mb-5" aria-labelledby="g-<?= (int) $gallery->id ?>">
				<div class="section-head">
					<div>
						<h2 class="section-title h3" id="g-<?= (int) $gallery->id ?>"><?= e($gallery->title) ?></h2>
						<?php if ($gallery->description): ?><p class="section-lead"><?= e($gallery->description) ?></p><?php endif; ?>
					</div>
					<a class="link-arrow" href="<?= site_url('galeri/'.rawurlencode($gallery->slug)) ?>">Buka album <?= icon('arrow-right') ?></a>
				</div>
				<div class="gallery-grid">
					<?php foreach ($gallery->items as $media): ?>
						<a class="gallery-thumb" href="<?= site_url('galeri/'.rawurlencode($gallery->slug)) ?>">
							<?= media_img($media, 'Foto galeri', FALSE, '(min-width: 992px) 25vw, 50vw') ?>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>
