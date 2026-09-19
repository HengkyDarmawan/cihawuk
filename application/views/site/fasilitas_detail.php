<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Detail fasilitas terbit. Kontak hanya tampil bila izinnya tercatat. */
$hours = (array) $facility['service_hours'];
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item"><a href="<?= site_url('fasilitas') ?>">Fasilitas Desa</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($facility['name']) ?></li></ol></nav>
		<p class="eyebrow"><?= e($facility['category_label']) ?></p>
		<h1><?= e($facility['name']) ?></h1>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-5">
			<div class="col-lg-7">
				<?php if ($photo): ?>
					<figure class="mb-4"><?= media_img($photo, 'Foto fasilitas belum tersedia', TRUE) ?></figure>
				<?php endif; ?>
				<div class="prose">
					<?php if ($facility['description']): ?><p><?= e($facility['description']) ?></p><?php endif; ?>

					<?php if ($facility['services']): ?>
						<h2>Layanan</h2>
						<ul>
						<?php foreach ($facility['services'] as $service): ?>
							<li><strong><?= e($service['label']) ?></strong><?= $service['description'] ? ' — '.e($service['description']) : '' ?></li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ($facility['accessibility']): ?>
						<h2>Aksesibilitas</h2>
						<p><?= e($facility['accessibility']) ?></p>
					<?php endif; ?>
				</div>
			</div>

			<aside class="col-lg-5">
				<div class="card-soft card-pad mb-4">
					<h2 class="h6">Informasi</h2>
					<table class="table table-sm mb-0">
						<caption class="visually-hidden">Informasi fasilitas</caption>
						<tbody>
							<?php if ($facility['address']): ?><tr><th scope="row">Alamat</th><td><?= e($facility['address']) ?></td></tr><?php endif; ?>
							<?php if ($facility['area_note']): ?><tr><th scope="row">Wilayah</th><td><?= e($facility['area_note']) ?></td></tr><?php endif; ?>
							<?php if ($facility['manager_name']): ?><tr><th scope="row">Pengelola</th><td><?= e($facility['manager_name']) ?></td></tr><?php endif; ?>
							<?php if ($facility['public_contact']): ?><tr><th scope="row">Kontak publik</th><td><?= e($facility['public_contact']) ?></td></tr><?php endif; ?>
							<tr><th scope="row">Tahun data</th><td><?= $facility['source_year'] ? (int) $facility['source_year'] : 'belum dicantumkan' ?></td></tr>
							<?php if ($facility['source_note']): ?><tr><th scope="row">Sumber</th><td><?= e($facility['source_note']) ?></td></tr><?php endif; ?>
							<?php if ($facility['verified_at']): ?><tr><th scope="row">Diverifikasi</th><td><?= e(format_wib($facility['verified_at'], 'date')) ?></td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php if ($hours): ?>
				<div class="card-soft card-pad mb-4">
					<h2 class="h6">Jam layanan</h2>
					<table class="table table-sm mb-0">
						<caption class="visually-hidden">Jam layanan fasilitas</caption>
						<tbody>
						<?php foreach ($hours as $row): ?>
							<tr><th scope="row"><?= e($row['label']) ?></th><td><?= e($row['value']) ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<div class="card-soft card-pad">
					<h2 class="h6">Lokasi</h2>
					<?php if ($facility['latitude'] !== NULL): ?>
						<div class="map-box" style="min-height:240px">
							<div data-map data-features="<?= e(json_encode(array(array('title' => $facility['name'],
								'geometry' => array('type' => 'Point', 'coordinates' => array($facility['longitude'], $facility['latitude'])))))) ?>"
								data-tile="<?= e(app_env('MAP_TILE_URL')) ?>" data-attribution="<?= e(app_env('MAP_ATTRIBUTION')) ?>" style="height:240px"></div>
						</div>
					<?php else: ?>
						<p class="mb-0 small text-muted">Peta sedang dilengkapi. Titik koordinat ditampilkan setelah diverifikasi dan dinyatakan aman untuk publik.</p>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</div>
</section>
