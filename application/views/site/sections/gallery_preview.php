<?php defined('BASEPATH') OR exit('No direct script access allowed');
$gallery = $d['gallery'] ?? NULL;
$items = $d['items'] ?? array();
?>
<section class="section" aria-labelledby="galeri-title">
	<div class="container-wide">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?= e($s['subtitle'] ?: 'Galeri') ?></p>
				<h2 class="section-title" id="galeri-title"><?= e($s['title'] ?: ($gallery ? $gallery->title : 'Dokumentasi kegiatan')) ?></h2>
			</div>
			<?php if ($gallery): ?><a class="link-arrow" href="<?= site_url('galeri/'.rawurlencode($gallery->slug)) ?>">Buka album <?= icon('arrow-right') ?></a><?php endif; ?>
		</div>
		<?php if ($items): ?>
		<div class="gallery-grid">
			<?php foreach ($items as $media): ?>
				<div class="media-frame"><?= media_img($media, 'Foto galeri', FALSE, '(min-width: 992px) 25vw, 50vw') ?></div>
			<?php endforeach; ?>
		</div>
		<?php else: ?>
		<div class="empty-state">
			<?= icon('image') ?>
			<h3>Belum ada foto terbit</h3>
			<p class="mb-0">Foto kegiatan akan tampil setelah hak publikasinya jelas.</p>
		</div>
		<?php endif; ?>
	</div>
</section>
