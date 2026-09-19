<?php defined('BASEPATH') OR exit('No direct script access allowed');
$items = $d['items'] ?? array();
$categories = $d['categories'] ?? array();
$carousel = ($s['layout'] ?? 'cards.grid_3') === 'cards.carousel';
$links = $s['config']['cta'] ?? array();
?>
<section class="section" aria-labelledby="potensi-title">
	<div class="container-wide">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?= e($s['subtitle'] ?: 'Jelajah potensi') ?></p>
				<h2 class="section-title" id="potensi-title"><?= e($s['title'] ?: 'Kekayaan alam dan karya warga') ?></h2>
			</div>
			<?php if ($carousel && count($items) > 1): ?>
			<div class="carousel-controls">
				<button class="carousel-btn" type="button" data-swiper-prev aria-label="Potensi sebelumnya"><?= icon('arrow-left') ?></button>
				<button class="carousel-btn" type="button" data-swiper-next aria-label="Potensi berikutnya"><?= icon('arrow-right') ?></button>
			</div>
			<?php elseif ($links): ?>
				<a class="link-arrow" href="<?= nav_href($links[0]['url']) ?>"><?= e($links[0]['label']) ?> <?= icon('arrow-right') ?></a>
			<?php endif; ?>
		</div>
		<?php if ($categories): ?>
		<nav class="filter-chips" aria-label="Kategori potensi">
			<a class="chip" href="<?= site_url('potensi') ?>">Semua</a>
			<?php foreach ($categories as $cat): ?>
				<a class="chip" href="<?= site_url('potensi?kategori='.rawurlencode($cat->slug)) ?>"><?= e($cat->name) ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
		<?php if ($items && $carousel): ?>
		<div class="swiper swiper-potensi" data-swiper>
			<div class="swiper-wrapper">
				<?php foreach ($items as $p): ?>
				<div class="swiper-slide">
					<a class="photo-card" href="<?= site_url('potensi/'.rawurlencode($p->slug)) ?>">
						<?= media_img($p->cover, 'Foto potensi belum tersedia', FALSE, '(min-width: 1200px) 33vw, (min-width: 768px) 46vw, 82vw') ?>
						<?= preview_badge($p->publication_status) ?>
						<span class="photo-card-body">
							<span class="photo-card-cat"><?= e($p->category_name) ?></span>
							<h3><?= e($p->title) ?></h3>
							<p><?= e($p->summary) ?></p>
						</span>
					</a>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php elseif ($items): ?>
		<div class="potential-grid">
			<?php foreach ($items as $p): ?>
			<a class="photo-card" href="<?= site_url('potensi/'.rawurlencode($p->slug)) ?>">
				<?= media_img($p->cover, 'Foto potensi belum tersedia', FALSE, '(min-width: 1200px) 33vw, (min-width: 768px) 46vw, 92vw') ?>
				<?= preview_badge($p->publication_status) ?>
				<span class="photo-card-body">
					<span class="photo-card-cat"><?= e($p->category_name) ?></span>
					<h3><?= e($p->title) ?></h3>
					<p><?= e($p->summary) ?></p>
				</span>
			</a>
			<?php endforeach; ?>
		</div>
		<?php else: ?>
		<div class="empty-state">
			<?= icon('compass') ?>
			<h3>Potensi desa sedang didokumentasikan</h3>
			<p class="mb-0">Informasi potensi akan ditampilkan setelah data, foto, dan izin publikasinya diverifikasi.</p>
		</div>
		<?php endif; ?>
	</div>
</section>
