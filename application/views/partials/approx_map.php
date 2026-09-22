<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Peta perkiraan lokasi desa (satu titik + lingkaran radius). Hanya dipakai selama belum
 * ada titik terverifikasi; catatannya selalu terlihat agar tidak dibaca sebagai titik resmi.
 * @var array $center village_center dari config/app.php  @var int $min_height
 */
$center = $center ?? NULL;
$tile = (string) app_env('MAP_TILE_URL');
?>
<?php if ($center && $tile): ?>
<div class="approx-map">
	<div data-map data-features="[]"
		data-center="<?= e(json_encode(array('lat' => (float) $center['lat'], 'lng' => (float) $center['lng'], 'zoom' => (int) ($center['zoom'] ?? 14), 'radius' => (int) ($center['radius_m'] ?? 1000), 'title' => 'Perkiraan lokasi Desa Cihawuk'))) ?>"
		data-tile="<?= e($tile) ?>" data-attribution="<?= e(app_env('MAP_ATTRIBUTION')) ?>"
		role="region" aria-label="Peta perkiraan lokasi Desa Cihawuk"
		style="height:100%;min-height:<?= (int) ($min_height ?? 380) ?>px"></div>
	<p class="approx-map-note"><?= icon('info') ?> <span>Titik <strong>perkiraan</strong> dari <?= e($center['source'] ?? 'dokumen profil desa') ?>, belum diverifikasi di lapangan. Lingkaran menandai area sekitar ±<?= e(number_format(($center['radius_m'] ?? 1000) / 1000, 0, ',', '.')) ?> km.</span></p>
</div>
<?php else: ?>
<div class="map-fallback">
	<div>
		<?= icon('map') ?>
		<h3 class="h5">Peta sedang dilengkapi</h3>
		<p class="mb-1">Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat.</p>
		<p class="small mb-0">Titik lokasi akan ditampilkan setelah koordinatnya dikonfirmasi.</p>
	</div>
</div>
<?php endif; ?>
