<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2">
			<li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li>
			<li class="breadcrumb-item"><a href="<?= site_url('potensi') ?>">Potensi</a></li>
			<li class="breadcrumb-item active" aria-current="page"><?= e($potential->category_name) ?></li>
		</ol></nav>
		<h1><?= e($potential->title) ?></h1>
		<p><?= e($potential->summary) ?></p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?= preview_badge($potential->publication_status) ?>
		<div class="row g-5">
			<div class="col-lg-8">
				<div class="media-frame mb-4"><?= media_img($potential->cover, 'Foto potensi belum tersedia', TRUE, '(min-width: 992px) 66vw, 100vw') ?></div>
				<div class="prose"><?= $potential->body_html ?></div>

				<?php if ( ! empty($potential->gallery)): ?>
				<h2 class="h4 mt-5">Galeri</h2>
				<div class="gallery-grid">
					<?php foreach ($potential->gallery as $media): ?>
						<button class="gallery-thumb" type="button" data-lightbox="<?= e(media_url($media)) ?>" data-alt="<?= e($media->alt_text) ?>" data-caption="<?= e($media->caption ?? '') ?>">
							<?= media_img($media, 'Foto', FALSE, '(min-width: 992px) 25vw, 50vw') ?>
						</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>

			<aside class="col-lg-4">
				<div class="card-soft card-pad mb-4">
					<h2 class="h6">Informasi</h2>
					<dl class="fact-list mb-0">
						<div><dt>Kategori</dt><dd><?= e($potential->category_name) ?></dd></div>
						<?php if ($potential->source_year): ?><div><dt>Tahun sumber</dt><dd><?= (int) $potential->source_year ?></dd></div><?php endif; ?>
						<div><dt>Status data</dt><dd><?= e(config_label('verification_statuses', $potential->verification_status)) ?></dd></div>
					</dl>
					<?php if ($potential->source_title): ?>
						<p class="small text-muted mt-3 mb-0">Sumber: <?= e($potential->source_title) ?></p>
					<?php endif; ?>
				</div>

				<?php if ($potential->contact_permission && $potential->public_contact): ?>
				<div class="card-soft card-pad mb-4">
					<h2 class="h6">Kontak</h2>
					<p class="mb-0"><?= e($potential->public_contact) ?></p>
				</div>
				<?php endif; ?>

				<?php if ($potential->map_feature): ?>
				<div class="card-soft card-pad">
					<h2 class="h6">Lokasi</h2>
					<div class="map-box" style="min-height: 240px;">
						<div data-map data-features="<?= e(json_encode(array(array('title' => $potential->map_feature->title, 'geometry' => json_decode($potential->map_feature->geometry_json, TRUE))))) ?>"
							data-tile="<?= e(app_env('MAP_TILE_URL')) ?>" data-attribution="<?= e(app_env('MAP_ATTRIBUTION')) ?>" style="height:240px"></div>
					</div>
				</div>
				<?php endif; ?>
			</aside>
		</div>

		<?php if (count($related) > 1): ?>
		<section class="mt-5" aria-labelledby="terkait">
			<h2 class="section-title h3" id="terkait">Potensi lain di kategori ini</h2>
			<div class="potential-grid">
				<?php foreach ($related as $item): if ($item->id === $potential->id) { continue; } ?>
				<a class="photo-card" href="<?= site_url('potensi/'.rawurlencode($item->slug)) ?>">
					<?= media_img($item->cover, 'Foto potensi belum tersedia', FALSE, '(min-width: 1200px) 33vw, 100vw') ?>
					<span class="photo-card-body"><span class="photo-card-cat"><?= e($item->category_name) ?></span><h3><?= e($item->title) ?></h3></span>
				</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>
	</div>
</section>

<dialog class="lightbox-dialog" id="lightbox" aria-label="Pratinjau foto">
	<button class="btn btn-light-glass lightbox-close" type="button" data-dialog-close>Tutup</button>
	<img alt="">
	<p class="lightbox-caption"></p>
</dialog>
