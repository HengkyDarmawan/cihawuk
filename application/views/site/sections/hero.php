<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** @var array $s section (type, title, subtitle, layout, config) @var array $d data */
$cfg = $s['config'];
$mode = $cfg['mode'] ?? 'image';
$fallback = $d['fallback'] ?? NULL;
$video = $d['video'] ?? NULL;
$use_three = ($mode === 'three') && ! empty($three_enabled) && ! $video;
$cta = $cfg['cta'] ?? array();
$editorial = ($s['layout'] ?? '') === 'hero.editorial';
// Kolom kanan: foto pilihan CMS bila ada; selain itu foto kawasan Kec. Kertasari (berlisensi,
// dengan kredit). Foto kawasan tidak pernah diberi label "Desa Cihawuk".
$side = $d['side'] ?? NULL;
$regional = $d['regional'] ?? array();
$region_photos = array_slice($regional['photos'] ?? array(), 0, 3);
$region_area = $regional['area'] ?? 'Kawasan Kec. Kertasari';
$region_img = function (array $p, $eager, $sizes) {
	return '<img src="'.e(asset_url($p['file'])).'" alt="'.e($p['alt']).'" width="'.(int) $p['width'].'" height="'.(int) $p['height'].'"'
		.($eager ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"').' sizes="'.e($sizes).'">';
};
?>
<section class="hero<?= $editorial ? ' hero-editorial' : '' ?>" aria-labelledby="hero-title">
	<div class="hero-media" aria-hidden="<?= $fallback ? 'false' : 'true' ?>">
		<?php if ($fallback): ?>
			<?= media_img($fallback, '', TRUE) ?>
		<?php else: ?>
			<svg class="hero-static-art" viewBox="0 0 1440 560" preserveAspectRatio="none" aria-hidden="true" focusable="false">
				<defs>
					<linearGradient id="hillA" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2E6B55" stop-opacity=".85"/><stop offset="1" stop-color="#0B2A20"/></linearGradient>
					<linearGradient id="hillB" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1D5543"/><stop offset="1" stop-color="#081E17"/></linearGradient>
				</defs>
				<path d="M0 250 C160 170 300 190 430 140 S700 60 860 120 S1150 210 1290 150 1440 120 1440 120 V560 H0Z" fill="url(#hillA)" opacity=".55"/>
				<path d="M0 330 C200 260 360 300 520 250 S820 190 980 250 S1260 330 1440 270 V560 H0Z" fill="url(#hillB)" opacity=".8"/>
				<g fill="none" stroke="#D7AF67" stroke-opacity=".28" stroke-width="1">
					<path d="M0 360 C220 300 380 340 560 300 S860 250 1040 300 S1300 360 1440 320"/>
					<path d="M0 400 C220 345 390 380 570 345 S870 300 1050 345 S1300 400 1440 365"/>
					<path d="M0 440 C230 390 400 420 580 390 S880 350 1060 390 S1310 440 1440 410"/>
				</g>
				<path d="M0 430 C240 380 420 420 640 380 S1000 350 1180 400 1440 430 1440 430 V560 H0Z" fill="#071A14" opacity=".92"/>
			</svg>
		<?php endif; ?>
	</div>
	<?php if ($use_three): ?>
	<div class="hero-canvas-wrap" data-hero-scene data-src="<?= asset_url('site/js/hero-scene.bundle.js') ?>" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="hero-overlay" aria-hidden="true"></div>

	<div class="container-wide hero-content<?= ($side || $region_photos) ? ' has-visual' : '' ?>">
		<div class="hero-text">
			<p class="hero-kicker"><?= icon('map-pin') ?> Jawa Barat · Dataran tinggi Kertasari</p>
			<h1 class="hero-title" id="hero-title"><?= e($s['title'] ?: 'Selamat Datang di Desa Cihawuk') ?></h1>
			<?php if ($s['subtitle']): ?><p class="hero-subtitle"><?= e($s['subtitle']) ?></p><?php endif; ?>
			<?php if ($cta): ?>
			<div class="hero-actions">
				<?php foreach ($cta as $i => $link): ?>
					<a class="btn btn-lg <?= $i === 0 ? 'btn-accent' : 'btn-light-glass' ?>" href="<?= nav_href($link['url']) ?>"><?= e($link['label']) ?><?= $i === 0 ? ' '.icon('arrow-right') : '' ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<?php if ($side): ?>
		<figure class="hero-visual hero-visual-single">
			<div class="hero-photo hero-photo-main"><?= media_img($side, '', TRUE, '(min-width: 992px) 45vw, 100vw') ?></div>
			<?php if ($side->caption OR $side->source_credit): ?>
			<figcaption class="hero-photo-caption">
				<?php if ($side->caption): ?><span class="hero-photo-place"><?= icon('camera') ?> <?= e($side->caption) ?></span><?php endif; ?>
				<?php if ($side->source_credit): ?><span class="hero-photo-credit">Foto: <?= e($side->source_credit) ?></span><?php endif; ?>
			</figcaption>
			<?php endif; ?>
		</figure>
		<?php elseif ($region_photos): $main = $region_photos[0]; ?>
		<figure class="hero-visual">
			<div class="hero-photo hero-photo-main"><?= $region_img($main, TRUE, '(min-width: 992px) 42vw, 100vw') ?></div>
			<?php foreach (array_slice($region_photos, 1, 2) as $k => $photo): ?>
				<div class="hero-photo hero-photo-small hero-photo-small-<?= $k + 1 ?>"><?= $region_img($photo, FALSE, '220px') ?></div>
			<?php endforeach; ?>
			<figcaption class="hero-photo-caption">
				<span class="hero-photo-place"><?= icon('camera') ?> <?= e($region_area) ?> · <?= e($main['place']) ?></span>
				<span class="hero-photo-credit">Foto: <a href="<?= e($main['source']) ?>" target="_blank" rel="noopener"><?= e($main['author']) ?></a>, <?= e($main['license']) ?> via Wikimedia Commons</span>
			</figcaption>
		</figure>
		<?php endif; ?>
	</div>
	<?php if ($fallback && $fallback->source_credit): ?>
		<p class="hero-credit">Foto: <?= e($fallback->source_credit) ?></p>
	<?php endif; ?>
</section>
