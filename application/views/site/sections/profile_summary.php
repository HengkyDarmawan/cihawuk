<?php defined('BASEPATH') OR exit('No direct script access allowed');
$cfg = $s['config'];
$media = $d['media'] ?? NULL;
$image_left = ($s['layout'] ?? 'text_image.image_left') === 'text_image.image_left';
$links = $cfg['cta'] ?? array();
?>
<section class="section" aria-labelledby="profil-title">
	<div class="container-wide profile-split<?= $image_left ? '' : ' profile-split-reverse' ?>">
		<div class="profile-figure reveal">
			<div class="media-frame"><?= media_img($media, 'Foto lanskap desa belum tersedia', FALSE, '(min-width: 992px) 50vw, 100vw') ?></div>
			<?php if ( ! empty($elevation)): ?>
			<div class="profile-badge">
				<strong>±<?= format_number_id($elevation->numeric_value) ?> mdpl</strong>
				<span class="text-muted small">Ketinggian menurut data <?= (int) $elevation->source_year ?></span>
			</div>
			<?php endif; ?>
		</div>
		<div class="reveal">
			<p class="eyebrow"><?= e($s['subtitle'] ?: 'Profil desa') ?></p>
			<h2 class="section-title" id="profil-title"><?= e($s['title'] ?: 'Desa di dataran tinggi Kertasari') ?></h2>
			<?php if ( ! empty($cfg['body'])): ?>
				<p class="section-lead"><?= e($cfg['body']) ?></p>
			<?php elseif ( ! empty($village)): ?>
				<?= preview_badge($village->publication_status) ?>
				<p class="section-lead"><?= e($village->summary) ?></p>
			<?php else: ?>
				<p class="section-lead">Profil desa sedang disiapkan pengelola.</p>
			<?php endif; ?>
			<?php if ( ! empty($village)): ?>
			<dl class="fact-list">
				<div><dt>Kecamatan</dt><dd><?= e($village->district) ?></dd></div>
				<div><dt>Kabupaten</dt><dd><?= e($village->regency) ?></dd></div>
				<div><dt>Provinsi</dt><dd><?= e($village->province) ?></dd></div>
			</dl>
			<?php endif; ?>
			<?php if ($links): ?>
			<div class="d-flex flex-wrap gap-3 mt-3">
				<?php foreach ($links as $i => $link): ?>
					<?php if ($i === 0): ?>
						<a class="btn btn-primary" href="<?= nav_href($link['url']) ?>"><?= e($link['label']) ?></a>
					<?php else: ?>
						<a class="link-arrow align-self-center" href="<?= nav_href($link['url']) ?>"><?= e($link['label']) ?> <?= icon('arrow-right') ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
