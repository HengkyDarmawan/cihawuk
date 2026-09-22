<?php defined('BASEPATH') OR exit('No direct script access allowed');
$cfg = $s['config'];
$media = $d['media'] ?? NULL;
$image_left = ($s['layout'] ?? 'text_image.image_left') === 'text_image.image_left';
$links = $cfg['cta'] ?? array();
// Tanpa foto CMS: pakai foto kawasan Kec. Kertasari (bukan Desa Cihawuk) lengkap dengan kreditnya.
$regional = $d['regional'] ?? array();
$landscape = ( ! $media && ! empty($regional['photos'])) ? ($regional['photos'][1] ?? $regional['photos'][0]) : NULL;
?>
<section class="section" aria-labelledby="profil-title">
	<div class="container-wide profile-split<?= $image_left ? '' : ' profile-split-reverse' ?>">
		<div class="profile-figure reveal">
			<?php if ($landscape): ?>
			<figure class="mb-0">
				<div class="media-frame"><img src="<?= e(asset_url($landscape['file'])) ?>" alt="<?= e($landscape['alt']) ?>" width="<?= (int) $landscape['width'] ?>" height="<?= (int) $landscape['height'] ?>" loading="lazy" decoding="async" sizes="(min-width: 992px) 50vw, 100vw"></div>
				<figcaption class="landscape-credit"><?= icon('camera') ?> <?= e($regional['area'] ?? 'Kawasan Kec. Kertasari') ?> · <?= e($landscape['place']) ?>. Foto: <a href="<?= e($landscape['source']) ?>" target="_blank" rel="noopener"><?= e($landscape['author']) ?></a>, <?= e($landscape['license']) ?></figcaption>
			</figure>
			<?php else: ?>
			<div class="media-frame"><?= media_img($media, 'Foto lanskap desa belum tersedia', FALSE, '(min-width: 992px) 50vw, 100vw') ?></div>
			<?php endif; ?>
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
