<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Tautan pengaturan akses lanjutan. Pekerjaan sehari-hari cukup dari Pengguna & Akses. */
$tabs = array(
	'role' => array('admin/rbac?semua=1', 'Semua role'),
	'baru' => array('admin/rbac/role/baru', 'Tambah role'),
	'izin' => array('admin/rbac/izin', 'Daftar izin'),
	'menu' => array('admin/rbac/menu', 'Menu dashboard'),
);
?>
<div class="card border-0 bg-light mb-4 mt-4">
	<div class="card-body py-2 d-flex flex-wrap align-items-center" style="gap:.5rem">
		<span class="small font-weight-bold text-muted mr-2"><i class="fas fa-sliders-h fa-sm mr-1" aria-hidden="true"></i> Pengaturan lanjutan:</span>
		<a class="btn btn-sm <?= $tab === 'pengguna' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url('admin/pengguna') ?>">Pengguna &amp; Akses</a>
		<?php foreach ($tabs as $key => $t): ?>
			<a class="btn btn-sm <?= $tab === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url($t[0]) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($t[1]) ?></a>
		<?php endforeach; ?>
	</div>
</div>
