<?php defined('BASEPATH') OR exit('No direct script access allowed');
$confirmed = is_array($contact) && ! empty($contact['confirmed']);
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Kontak</li></ol></nav>
		<h1>Kontak kantor desa</h1>
		<p>Alamat, jam pelayanan, dan kanal resmi Pemerintah Desa Cihawuk.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-4">
			<div class="col-lg-5">
				<div class="card-soft card-pad mb-4">
					<h2 class="h5">Alamat</h2>
					<?php if ($village && $village->office_address): ?>
						<p class="mb-0"><?= e($village->office_address) ?></p>
					<?php else: ?>
						<p class="mb-0">Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat.<br>
						<span class="text-muted small">Alamat lengkap kantor desa sedang dikonfirmasi pengelola.</span></p>
					<?php endif; ?>
				</div>

				<div class="card-soft card-pad mb-4">
					<h2 class="h5">Jam pelayanan</h2>
					<?php if (is_array($hours) && ! empty($hours['label'])): ?>
						<p class="mb-1"><?= e($hours['label']) ?></p>
						<?php if ( ! empty($hours['is_example'])): ?><p class="small text-muted mb-0">Jadwal contoh — menunggu konfirmasi kantor desa.</p><?php endif; ?>
					<?php else: ?>
						<p class="mb-0 text-muted">Belum diumumkan.</p>
					<?php endif; ?>
				</div>

				<div class="card-soft card-pad">
					<h2 class="h5">Kanal kontak</h2>
					<?php if ($confirmed): ?>
						<ul class="list-unstyled mb-0">
							<?php if ( ! empty($contact['phone'])): ?><li><?= icon('phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a></li><?php endif; ?>
							<?php if ( ! empty($contact['email'])): ?><li><?= icon('mail') ?> <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></li><?php endif; ?>
						</ul>
					<?php else: ?>
						<p>Nomor telepon dan email resmi belum dikonfirmasi, sehingga belum ditampilkan agar warga tidak menghubungi kontak yang salah.</p>
						<p class="mb-0">Untuk keperluan layanan, gunakan kanal berikut:</p>
						<div class="d-flex flex-wrap gap-2 mt-3">
							<a class="btn btn-primary" href="<?= site_url('lapor') ?>">Buat laporan</a>
							<a class="btn btn-outline-primary" href="<?= site_url('lacak') ?>">Lacak laporan</a>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="col-lg-7">
				<div class="map-box h-100">
					<?php if ( ! empty($office)): ?>
						<div data-map data-features="<?= e(json_encode(array_map(function ($f) { return array('title' => $f->title, 'geometry' => json_decode($f->geometry_json, TRUE)); }, $office))) ?>"
							data-tile="<?= e(app_env('MAP_TILE_URL')) ?>" data-attribution="<?= e(app_env('MAP_ATTRIBUTION')) ?>" style="height:100%;min-height:420px"></div>
					<?php else: ?>
						<?php $this->load->view('partials/approx_map', array('center' => $center ?? NULL, 'min_height' => 420)); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>
