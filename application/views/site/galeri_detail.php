<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2">
			<li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li>
			<li class="breadcrumb-item"><a href="<?= site_url('galeri') ?>">Galeri</a></li>
		</ol></nav>
		<h1><?= e($gallery->title) ?></h1>
		<?php if ($gallery->description): ?><p><?= e($gallery->description) ?></p><?php endif; ?>
	</div>
</section>

<section class="page-body">
	<div class="container-wide">
		<?php if (empty($gallery->items)): ?>
			<div class="empty-state"><?= icon('image') ?><h2 class="h5">Album ini belum memiliki foto terbit</h2></div>
		<?php else: ?>
		<div class="gallery-grid">
			<?php foreach ($gallery->items as $media): ?>
			<button class="gallery-thumb" type="button" data-lightbox="<?= e(media_url($media)) ?>" data-alt="<?= e($media->alt_text) ?>" data-caption="<?= e($media->caption ?? '') ?>">
				<?= media_img($media, 'Foto galeri', FALSE, '(min-width: 992px) 25vw, 50vw') ?>
			</button>
			<?php endforeach; ?>
		</div>
		<p class="small text-muted mt-3">Tekan foto untuk memperbesar. Tekan Escape untuk menutup.</p>
		<?php endif; ?>
	</div>
</section>

<dialog class="lightbox-dialog" id="lightbox" aria-label="Pratinjau foto">
	<button class="btn btn-light-glass lightbox-close" type="button" data-dialog-close>Tutup</button>
	<img alt="">
	<p class="lightbox-caption"></p>
</dialog>
