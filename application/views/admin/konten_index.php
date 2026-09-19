<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Konten situs</h1>
		<p>Seluruh isi halaman publik berasal dari database. Menyusun draft memakai izin <code>content.edit</code>; menerbitkan memerlukan <code>content.publish</code>.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/konten/profil') ?>"><i class="fas fa-id-card mr-1" aria-hidden="true"></i> Profil desa</a>
</div>

<?php if ($profile && $profile->publication_status !== 'published'): ?>
<div class="alert alert-info">Profil desa masih berstatus <strong><?= e(config_label('publication_statuses', $profile->publication_status)) ?></strong>, sehingga halaman Profil publik menampilkan keadaan kosong.</div>
<?php endif; ?>

<div class="row">
	<?php foreach ($types as $type => $label): ?>
	<div class="col-md-6 col-xl-4 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-body">
				<h2 class="h6"><?= $label ?></h2>
				<p class="mb-2 text-muted small">
					<?= (int) $counts[$type]['total'] ?> item ·
					<?= (int) $counts[$type]['draft'] ?> draft ·
					<?= (int) $counts[$type]['published'] ?> terbit
				</p>
				<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/konten/'.$type) ?>">Kelola</a>
				<a class="btn btn-link btn-sm" href="<?= site_url('admin/konten/'.$type.'/buat') ?>">Buat baru</a>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>
