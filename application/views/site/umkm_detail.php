<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Detail satu UMKM; seluruh datanya sudah disetujui pemiliknya. */
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item"><a href="<?= site_url('umkm') ?>">UMKM Desa</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($business['name']) ?></li></ol></nav>
		<p class="eyebrow"><?= e($business['category_label']) ?></p>
		<h1><?= e($business['name']) ?></h1>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-5">
			<div class="col-lg-7">
				<?php if ($photo): ?><figure class="mb-4"><?= media_img($photo, 'Foto usaha belum tersedia', TRUE) ?></figure><?php endif; ?>
				<div class="prose">
					<?php if ($business['description']): ?><p><?= e($business['description']) ?></p><?php endif; ?>
					<?php if ($business['products']): ?><h2>Produk</h2><p><?= e($business['products']) ?></p><?php endif; ?>
				</div>
			</div>
			<aside class="col-lg-5">
				<div class="card-soft card-pad">
					<h2 class="h6">Informasi usaha</h2>
					<table class="table table-sm mb-0">
						<caption class="visually-hidden">Informasi usaha</caption>
						<tbody>
							<?php if ($business['owner_name']): ?><tr><th scope="row">Pemilik</th><td><?= e($business['owner_name']) ?></td></tr><?php endif; ?>
							<?php if ($business['public_location']): ?><tr><th scope="row">Lokasi</th><td><?= e($business['public_location']) ?></td></tr><?php endif; ?>
							<?php if ($business['opening_hours']): ?><tr><th scope="row">Jam buka</th><td><?= e($business['opening_hours']) ?></td></tr><?php endif; ?>
							<?php if ($business['public_contact']): ?><tr><th scope="row">Kontak</th><td><?= e($business['public_contact']) ?></td></tr><?php endif; ?>
						</tbody>
					</table>
					<p class="small text-muted mt-3 mb-0">Data ini dimuat atas persetujuan pemilik usaha dan dapat dicabut kapan saja.</p>
				</div>
			</aside>
		</div>
	</div>
</section>
