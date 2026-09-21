<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Tab navigasi halaman Role, Izin, dan Menu. */
$tabs = array('role' => array('admin/rbac', 'Role'), 'izin' => array('admin/rbac/izin', 'Izin'), 'menu' => array('admin/rbac/menu', 'Menu dashboard'));
?>
<ul class="nav nav-tabs mb-4">
	<?php foreach ($tabs as $key => $t): ?>
	<li class="nav-item">
		<a class="nav-link<?= $tab === $key ? ' active' : '' ?>" href="<?= site_url($t[0]) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($t[1]) ?></a>
	</li>
	<?php endforeach; ?>
</ul>
<p class="small text-muted">Setiap perubahan meminta konfirmasi ulang password dan tercatat di log audit. Izin diperiksa ulang pada setiap permintaan, jadi perubahan langsung berlaku.</p>
