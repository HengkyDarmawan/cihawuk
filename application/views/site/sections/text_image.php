<?php defined('BASEPATH') OR exit('No direct script access allowed');
$cfg = $s['config'];
$media = $d['media'] ?? NULL;
$image_left = ($s['layout'] ?? 'text_image.image_left') === 'text_image.image_left';
$links = $cfg['cta'] ?? array();
?>
<section class="section" aria-label="<?= e($s['title'] ?: 'Informasi') ?>">
	<?php // Tanpa gambar, blok memakai satu kolom agar tidak menyisakan bingkai kosong. ?>
	<div class="<?= $media ? 'container-wide profile-split'.($image_left ? '' : ' profile-split-reverse') : 'container-site' ?>">
		<?php if ($media): ?>
		<div class="profile-figure reveal">
			<div class="media-frame"><?= media_img($media, 'Gambar belum tersedia', FALSE, '(min-width: 992px) 50vw, 100vw') ?></div>
		</div>
		<?php endif; ?>
		<div class="reveal">
			<?php if ($s['subtitle']): ?><p class="eyebrow"><?= e($s['subtitle']) ?></p><?php endif; ?>
			<?php if ($s['title']): ?><h2 class="section-title"><?= e($s['title']) ?></h2><?php endif; ?>
			<p class="section-lead"><?= nl2br(e($cfg['body'] ?? '')) ?></p>
			<?php if ($links): ?>
				<a class="btn btn-primary mt-3" href="<?= nav_href($links[0]['url']) ?>"><?= e($links[0]['label']) ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
