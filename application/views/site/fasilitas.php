<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Direktori fasilitas. Hanya entri terbit yang sampai ke sini; koordinat yang lolos
 * ke peta sudah disaring service (bukan sensitif dan lokasinya terverifikasi).
 */
$points = array();
foreach ($facilities as $facility)
{
	if ($facility['latitude'] !== NULL && $facility['longitude'] !== NULL)
	{
		$points[] = array(
			'title' => $facility['name'],
			'geometry' => array('type' => 'Point', 'coordinates' => array($facility['longitude'], $facility['latitude'])),
		);
	}
}
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Fasilitas Desa</li></ol></nav>
		<h1>Fasilitas desa</h1>
		<p>Fasilitas yang identitasnya sudah diperiksa pengelola desa. Angka agregat seperti jumlah sekolah ada di <a href="<?= site_url('data-desa') ?>">Data Desa</a>.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ($total === 0): ?>
			<div class="empty-state">
				<?= icon('map-pin') ?>
				<h2 class="h5">Direktori fasilitas sedang disusun</h2>
				<p class="mb-0">Dokumen sumber baru memuat jumlah fasilitas, belum nama, alamat, dan pengelolanya. Entri dibuat setelah pengelola desa memverifikasi kondisi di lapangan, bukan dibuat dari angka agregat.</p>
			</div>
		<?php else: ?>
			<form class="filter-bar mb-4" method="get" action="<?= site_url('fasilitas') ?>">
				<div>
					<label class="form-label" for="kategori">Kategori</label>
					<select class="form-select" id="kategori" name="kategori">
						<option value="">Semua kategori</option>
						<?php foreach ($categories_present as $code => $label): ?>
							<option value="<?= e($code) ?>" <?= $filters['kategori'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label class="form-label" for="q">Cari nama</label>
					<input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="60">
				</div>
				<div><button class="btn btn-primary" type="submit">Tampilkan</button></div>
			</form>

			<?php if ($points): ?>
			<div class="map-box mb-4">
				<div data-map data-features="<?= e(json_encode($points)) ?>"
					data-tile="<?= e(app_env('MAP_TILE_URL')) ?>" data-attribution="<?= e(app_env('MAP_ATTRIBUTION')) ?>"
					style="height:360px"></div>
			</div>
			<p class="small text-muted">Peta hanya memuat titik yang koordinatnya sudah diverifikasi dan tidak ditandai sensitif.</p>
			<?php endif; ?>

			<?php if (empty($facilities)): ?>
				<div class="empty-state"><?= icon('search') ?><h2 class="h5">Tidak ada fasilitas yang cocok</h2><p class="mb-0">Ubah kategori atau kata kuncinya.</p></div>
			<?php endif; ?>

			<div class="row g-4">
				<?php foreach ($facilities as $facility): ?>
				<div class="col-md-6 col-lg-4">
					<article class="card-soft card-pad h-100">
						<p class="eyebrow"><?= e($facility['category_label']) ?></p>
						<h2 class="h5"><a href="<?= site_url('fasilitas/'.rawurlencode($facility['slug'])) ?>"><?= e($facility['name']) ?></a></h2>
						<?php if ($facility['description']): ?><p class="mb-2"><?= e(str_limit_id($facility['description'], 120)) ?></p><?php endif; ?>
						<?php if ($facility['address']): ?><p class="small mb-1"><?= icon('map-pin') ?> <?= e($facility['address']) ?></p><?php endif; ?>
						<?php if ($facility['manager_name']): ?><p class="small mb-1">Pengelola: <?= e($facility['manager_name']) ?></p><?php endif; ?>
						<p class="small text-muted mb-0">
							Data <?= $facility['source_year'] ? (int) $facility['source_year'] : 'tanpa tahun' ?>
							<?php if ($facility['verified_at']): ?>&middot; diverifikasi <?= e(format_wib($facility['verified_at'], 'date')) ?><?php endif; ?>
						</p>
					</article>
				</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
