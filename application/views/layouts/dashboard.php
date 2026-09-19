<?php defined('BASEPATH') OR exit('No direct script access allowed');
$flash = isset($this->session) ? $this->session->flashdata('flash') : NULL;
$is_admin = ($area ?? 'warga') === 'admin';
$perms = $permissions ?? array();
$can = function ($p) use ($perms) { return in_array($p, $perms, TRUE); };
$can_any = function (array $ps) use ($perms) { return count(array_intersect($ps, $perms)) > 0; };
$extra_css = isset($extra_css) ? (array) $extra_css : array();
$extra_js = isset($extra_js) ? (array) $extra_js : array();
$nav_active = $nav_active ?? '';
$title = ($page_title ?? 'Dashboard').' — '.($is_admin ? 'Pengelola' : 'Warga').' Desa Cihawuk';

if ($is_admin)
{
	// Menu hanya tampil bila pengguna punya permission DAN modulnya tersedia.
	// Menyembunyikan menu bukan kontrol akses: setiap route tetap diperiksa di server.
	$module_on = function ($code) {
		$CI =& get_instance();
		$CI->load->library('FeatureModuleService', NULL, 'modules');
		return $CI->modules->backend_available($code);
	};

	$menu = array();
	$menu[] = array('heading' => NULL, 'items' => array(
		array('key' => 'dashboard', 'url' => 'admin', 'icon' => 'fa-gauge-high fa-tachometer-alt', 'label' => 'Ringkasan', 'show' => TRUE),
	));
	$menu[] = array('heading' => 'Layanan Warga', 'items' => array(
		array('key' => 'laporan', 'url' => 'admin/laporan', 'icon' => 'fa-inbox', 'label' => 'Pengaduan dan Aspirasi', 'show' => $module_on('complaints') && $can_any(array('tickets.verify', 'tickets.monitor_scope', 'tickets.work_assigned'))),
		array('key' => 'laporan-buat', 'url' => 'admin/laporan/buat', 'icon' => 'fa-pen-to-square fa-edit', 'label' => 'Input dari Loket', 'show' => $module_on('complaints') && $can('tickets.create_on_behalf')),
		array('key' => 'ekspor', 'url' => 'admin/ekspor', 'icon' => 'fa-file-export', 'label' => 'Laporan dan Ekspor', 'show' => $can('tickets.export')),
	));
	$menu[] = array('heading' => 'Website dan CMS', 'items' => array(
		array('key' => 'cms-beranda', 'url' => 'admin/cms/beranda', 'icon' => 'fa-layer-group fa-object-group', 'label' => 'Pengaturan Beranda', 'show' => $can('cms.page.view')),
		array('key' => 'cms-halaman', 'url' => 'admin/cms/halaman', 'icon' => 'fa-file-lines fa-file-alt', 'label' => 'Halaman Publik', 'show' => $can('cms.page.view')),
		array('key' => 'cms-menu', 'url' => 'admin/cms/menu', 'icon' => 'fa-bars', 'label' => 'Menu dan Navigasi', 'show' => $can('cms.menu.manage')),
		array('key' => 'cms-situs', 'url' => 'admin/cms/situs', 'icon' => 'fa-palette', 'label' => 'Identitas dan Tema', 'show' => $can('cms.site.manage')),
		array('key' => 'profil', 'url' => 'admin/profil', 'icon' => 'fa-address-card', 'label' => 'Profil Desa', 'show' => $can_any(array('content.edit', 'content.publish'))),
		array('key' => 'konten', 'url' => 'admin/konten', 'icon' => 'fa-newspaper', 'label' => 'Konten Situs', 'show' => $can_any(array('content.edit', 'cms.page.view'))),
		array('key' => 'media', 'url' => 'admin/media', 'icon' => 'fa-images', 'label' => 'Media Library', 'show' => $can_any(array('content.edit', 'cms.media.upload'))),
	));
	$menu[] = array('heading' => 'Data Desa', 'items' => array(
		array('key' => 'dataset', 'url' => 'admin/dataset', 'icon' => 'fa-chart-column fa-chart-bar', 'label' => 'Dataset Publik', 'show' => $module_on('village_data') && $can_any(array('data.review', 'data.publish'))),
		array('key' => 'statistik', 'url' => 'admin/statistik', 'icon' => 'fa-chart-bar', 'label' => 'Nilai dan Dokumen Sumber', 'show' => $module_on('village_data') && $can_any(array('content.edit', 'statistics.review', 'data.review'))),
	));
	$menu[] = array('heading' => 'Potensi dan Fasilitas', 'items' => array(
		array('key' => 'umkm', 'url' => 'admin/umkm', 'icon' => 'fa-store', 'label' => 'Direktori UMKM', 'show' => $module_on('umkm_directory') && $can_any(array('umkm.edit', 'umkm.publish'))),
		array('key' => 'fasilitas', 'url' => 'admin/fasilitas', 'icon' => 'fa-location-dot fa-map-marker-alt', 'label' => 'Direktori Fasilitas', 'show' => $module_on('facilities') && $can_any(array('facilities.edit', 'facilities.publish'))),
	));
	$menu[] = array('heading' => 'Pemerintahan', 'items' => array(
		array('key' => 'struktur', 'url' => 'admin/struktur', 'icon' => 'fa-sitemap', 'label' => 'Struktur Organisasi', 'show' => $module_on('organization') && $can('organization.edit')),
	));
	$menu[] = array('heading' => 'Transparansi Keuangan', 'items' => array(
		array('key' => 'keuangan', 'url' => 'admin/keuangan', 'icon' => 'fa-coins', 'label' => 'Anggaran dan Realisasi', 'show' => $module_on('budget_transparency') && $can_any(array('finance.manage', 'finance.verify', 'finance.publish'))),
	));
	$menu[] = array('heading' => 'Aset dan Persediaan', 'items' => array(
		array('key' => 'aset', 'url' => 'admin/aset', 'icon' => 'fa-boxes-stacked fa-box', 'label' => 'Aset dan QR', 'show' => $module_on('assets') && $can('assets.view')),
		array('key' => 'audit-aset', 'url' => 'admin/audit-aset', 'icon' => 'fa-clipboard-check', 'label' => 'Audit Aset', 'show' => $module_on('assets') && $can_any(array('asset_audits.create', 'asset_audits.perform', 'asset_audits.verify'))),
		array('key' => 'gudang', 'url' => 'admin/gudang', 'icon' => 'fa-warehouse', 'label' => 'Gudang Persediaan', 'show' => $module_on('warehouse') && $can('warehouse.view')),
	));
	$menu[] = array('heading' => 'Pengaturan', 'items' => array(
		array('key' => 'pengguna', 'url' => 'admin/pengguna', 'icon' => 'fa-users', 'label' => 'Pengguna', 'show' => $can_any(array('users.manage', 'users.create_resident', 'residents.verify', 'users.assign_roles'))),
		array('key' => 'pengaturan', 'url' => 'admin/pengaturan', 'icon' => 'fa-sliders-h', 'label' => 'Pengaturan Aplikasi', 'show' => $can('settings.manage')),
		array('key' => 'modul', 'url' => 'admin/pengaturan/modul', 'icon' => 'fa-toggle-on', 'label' => 'Modul dan Feature Toggle', 'show' => $can('settings.feature.manage')),
		array('key' => 'audit', 'url' => 'admin/audit', 'icon' => 'fa-clipboard-list', 'label' => 'Log Audit', 'show' => $can('audit.view')),
	));
}
else
{
	$menu = array(
		array('heading' => NULL, 'items' => array(
			array('key' => 'dashboard', 'url' => 'warga', 'icon' => 'fa-home', 'label' => 'Beranda', 'show' => TRUE),
			array('key' => 'laporan-buat', 'url' => 'warga/laporan/buat', 'icon' => 'fa-edit', 'label' => 'Buat Laporan', 'show' => TRUE),
			array('key' => 'laporan', 'url' => 'warga/laporan', 'icon' => 'fa-inbox', 'label' => 'Laporan Saya', 'show' => TRUE),
			array('key' => 'notifikasi', 'url' => 'warga/notifikasi', 'icon' => 'fa-bell', 'label' => 'Notifikasi', 'show' => TRUE),
		)),
		array('heading' => 'Akun', 'items' => array(
			array('key' => 'profil', 'url' => 'warga/profil', 'icon' => 'fa-user', 'label' => 'Profil', 'show' => TRUE),
			array('key' => 'akun', 'url' => 'warga/akun', 'icon' => 'fa-shield-alt', 'label' => 'Keamanan Akun', 'show' => TRUE),
		)),
	);
}
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e($title) ?></title>
	<link rel="stylesheet" href="<?= asset_url('vendor/fontawesome/css/all.min.css') ?>">
	<link rel="stylesheet" href="<?= asset_url('vendor/sb-admin-2/css/sb-admin-2.min.css') ?>">
	<?php foreach ($extra_css as $css): ?><link rel="stylesheet" href="<?= asset_url($css) ?>"><?php endforeach; ?>
	<link rel="stylesheet" href="<?= asset_url('admin/css/dashboard.css') ?>">
	<link rel="icon" href="<?= base_url('assets/site/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body id="page-top" class="area-<?= $is_admin ? 'admin' : 'warga' ?>" data-csrf-name="<?= e($this->security->get_csrf_token_name()) ?>" data-csrf-hash="<?= e($this->security->get_csrf_hash()) ?>">
	<a class="skip-link" href="#konten">Lewati ke konten utama</a>
	<?php if ( ! empty($this->config->item('features', 'app')['demo_mode'])): ?>
	<div class="demo-bar" role="status">
		<strong>Mode demonstrasi aktif.</strong> Sebagian isi basis data ini adalah contoh. Hapus dengan <code>tools purge_demo</code> dan setel <code>DEMO_MODE=false</code> sebelum dipakai sungguhan.
	</div>
	<?php endif; ?>
	<div id="wrapper">
		<nav class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar" aria-label="Menu <?= $is_admin ? 'pengelola' : 'warga' ?>">
			<a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url($is_admin ? 'admin' : 'warga') ?>">
				<div class="sidebar-brand-icon" aria-hidden="true">
					<svg viewBox="0 0 40 40" width="34" height="34" focusable="false"><rect width="40" height="40" rx="10" fill="#D7AF67" opacity=".18"/><path d="M6 29 15 17l6 7 4-5 9 10H6Z" fill="#D7AF67"/><circle cx="28" cy="12" r="3.2" fill="#fff"/></svg>
				</div>
				<div class="sidebar-brand-text mx-2 text-left">Cihawuk<small class="d-block"><?= $is_admin ? 'Pengelola' : 'Dashboard Warga' ?></small></div>
			</a>
			<?php foreach ($menu as $group):
				$visible = array_filter($group['items'], function ($i) { return $i['show']; });
				if (empty($visible)) { continue; } ?>
				<hr class="sidebar-divider my-1">
				<?php if ($group['heading']): ?><div class="sidebar-heading"><?= e($group['heading']) ?></div><?php endif; ?>
				<?php foreach ($visible as $item): $active = ($nav_active === $item['key']); ?>
				<div class="nav-item<?= $active ? ' active' : '' ?>">
					<a class="nav-link" href="<?= site_url($item['url']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
						<i class="fas fa-fw <?= e($item['icon']) ?>" aria-hidden="true"></i>
						<span><?= e($item['label']) ?></span>
					</a>
				</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<hr class="sidebar-divider d-none d-md-block">
			<div class="nav-item">
				<a class="nav-link" href="<?= site_url('/') ?>"><i class="fas fa-fw fa-globe" aria-hidden="true"></i><span>Lihat situs publik</span></a>
			</div>
			<div class="text-center d-none d-md-inline">
				<button class="rounded-circle border-0" id="sidebarToggle" type="button" aria-label="Ciutkan menu samping"></button>
			</div>
		</nav>

		<div id="content-wrapper" class="d-flex flex-column">
			<div id="content">
				<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm" aria-label="Bilah atas">
					<button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3" type="button" aria-label="Buka/tutup menu samping">
						<i class="fa fa-bars" aria-hidden="true"></i>
					</button>
					<?php if ($is_admin && $can_any(array('tickets.verify', 'tickets.monitor_scope', 'tickets.work_assigned'))): ?>
					<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search" method="get" action="<?= site_url('admin/laporan') ?>" role="search">
						<div class="input-group">
							<label class="sr-only" for="topbar-search">Cari nomor tiket atau judul laporan dalam lingkup akses Anda</label>
							<input type="search" id="topbar-search" name="q" class="form-control bg-light border-0 small" placeholder="Cari nomor tiket / judul…" maxlength="100" value="<?= e((string) $this->input->get('q')) ?>">
							<div class="input-group-append">
								<button class="btn btn-primary" type="submit" aria-label="Cari"><i class="fas fa-search fa-sm" aria-hidden="true"></i></button>
							</div>
						</div>
					</form>
					<?php endif; ?>
					<ul class="navbar-nav ml-auto">
						<li class="nav-item mx-1">
							<a class="nav-link" href="<?= site_url($is_admin ? 'admin/notifikasi' : 'warga/notifikasi') ?>" aria-label="Notifikasi<?= ! empty($unread_notifications) ? ', '.(int) $unread_notifications.' belum dibaca' : '' ?>">
								<i class="fas fa-bell fa-fw" aria-hidden="true"></i>
								<?php if ( ! empty($unread_notifications)): ?><span class="badge badge-danger badge-counter" aria-hidden="true"><?= (int) $unread_notifications > 9 ? '9+' : (int) $unread_notifications ?></span><?php endif; ?>
							</a>
						</li>
						<div class="topbar-divider d-none d-sm-block"></div>
						<li class="nav-item dropdown no-arrow">
							<a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<span class="mr-2 d-none d-lg-inline text-gray-700 small font-weight-bold"><?= e($user->display_name) ?></span>
								<span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user->display_name, 0, 1))) ?></span>
								<span class="sr-only">Menu akun</span>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="userDropdown">
								<span class="dropdown-item-text small text-muted">Masuk sebagai <strong><?= e($user->username) ?></strong></span>
								<div class="dropdown-divider"></div>
								<a class="dropdown-item" href="<?= site_url($is_admin ? 'admin/akun' : 'warga/akun') ?>"><i class="fas fa-shield-alt fa-sm fa-fw mr-2 text-gray-500" aria-hidden="true"></i> Keamanan akun</a>
								<?php if ( ! $is_admin): ?>
								<a class="dropdown-item" href="<?= site_url('warga/profil') ?>"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-500" aria-hidden="true"></i> Profil</a>
								<?php endif; ?>
								<div class="dropdown-divider"></div>
								<form method="post" action="<?= site_url('keluar') ?>" class="px-3 py-1">
									<?= csrf_field() ?>
									<button class="btn btn-outline-danger btn-sm btn-block" type="submit"><i class="fas fa-sign-out-alt fa-sm fa-fw mr-1" aria-hidden="true"></i> Keluar</button>
								</form>
							</div>
						</li>
					</ul>
				</nav>

				<main class="container-fluid" id="konten" tabindex="-1">
					<?php if ( ! empty($flash) && is_array($flash)): ?>
					<div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
						<?= e($flash['message']) ?>
						<button type="button" class="close" data-dismiss="alert" aria-label="Tutup pesan"><span aria-hidden="true">&times;</span></button>
					</div>
					<?php endif; ?>
					<?= $content ?>
				</main>
			</div>

			<footer class="sticky-footer bg-white">
				<div class="container my-auto">
					<div class="copyright text-center my-auto small">
						<span>Layanan Digital Desa Cihawuk · Sesi <?= $is_admin ? 'pengelola' : 'warga' ?> berakhir otomatis bila tidak aktif.</span>
					</div>
				</div>
			</footer>
		</div>
	</div>

	<script src="<?= asset_url('vendor/jquery/jquery.min.js') ?>"></script>
	<script src="<?= asset_url('vendor/bootstrap4/js/bootstrap.bundle.min.js') ?>"></script>
	<script src="<?= asset_url('vendor/jquery-easing/jquery.easing.min.js') ?>"></script>
	<script src="<?= asset_url('vendor/sb-admin-2/js/sb-admin-2.min.js') ?>"></script>
	<?php foreach ($extra_js as $js): ?><script src="<?= asset_url($js) ?>"></script><?php endforeach; ?>
	<script src="<?= asset_url('admin/js/dashboard.js') ?>"></script>
</body>
</html>
