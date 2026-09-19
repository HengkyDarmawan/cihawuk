<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Direktori UMKM. Hanya usaha berizin pemilik yang sampai ke sini. */
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">UMKM Desa</li></ol></nav>
		<h1>UMKM desa</h1>
		<p>Profil usaha warga yang pemiliknya menyetujui pemuatan datanya di situs desa.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ($total === 0): ?>
			<div class="empty-state">
				<?= icon('shopping-bag') ?>
				<h2 class="h5">Direktori UMKM sedang disusun</h2>
				<p class="mb-0">Dokumen sumber hanya memuat kategori ekonomi secara agregat, bukan daftar usaha. Profil usaha dimuat setelah pemiliknya menyetujui, bukan disalin dari sumber lain.</p>
			</div>
		<?php else: ?>
			<?php if (count($categories_present) > 1): ?>
			<form class="filter-bar mb-4" method="get" action="<?= site_url('umkm') ?>">
				<div>
					<label class="form-label" for="kategori">Kategori</label>
					<select class="form-select" id="kategori" name="kategori">
						<option value="">Semua kategori</option>
						<?php foreach ($categories_present as $code => $label): ?>
							<option value="<?= e($code) ?>" <?= $filters['kategori'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div><button class="btn btn-primary" type="submit">Tampilkan</button></div>
			</form>
			<?php endif; ?>

			<div class="row g-4">
				<?php foreach ($businesses as $business): ?>
				<div class="col-md-6 col-lg-4">
					<article class="card-soft card-pad h-100">
						<p class="eyebrow"><?= e($business['category_label']) ?></p>
						<h2 class="h5"><a href="<?= site_url('umkm/'.rawurlencode($business['slug'])) ?>"><?= e($business['name']) ?></a></h2>
						<?php if ($business['products']): ?><p class="small mb-1">Produk: <?= e(str_limit_id($business['products'], 90)) ?></p><?php endif; ?>
						<?php if ($business['public_location']): ?><p class="small mb-0"><?= icon('map-pin') ?> <?= e($business['public_location']) ?></p><?php endif; ?>
					</article>
				</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
