<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Registry menu dashboard pengelola.
 *
 * Setiap item terikat ke route yang sudah diperiksa izinnya di server, jadi daftar ini
 * tetap di kode. Label, grup, urutan, status aktif, dan visibilitas per role dapat diubah
 * dari Pengguna & Akses › Pengaturan lanjutan › Menu dashboard (tabel `admin_menu_overrides` dan `role_menu_hidden`).
 *
 * Kolom item:
 * - key: sama dengan `nav_active` controller (dipakai untuk penanda aktif dan override)
 * - module: kode feature module yang harus tersedia (opsional)
 * - any / all: izin yang disyaratkan; kosong berarti selalu tampil untuk staf
 */
$config['admin_menu'] = array(
	array('heading' => NULL, 'items' => array(
		array('key' => 'dashboard', 'url' => 'admin', 'icon' => 'fa-gauge-high fa-tachometer-alt', 'label' => 'Ringkasan'),
	)),
	array('heading' => 'Layanan Warga', 'items' => array(
		array('key' => 'laporan', 'url' => 'admin/laporan', 'icon' => 'fa-inbox', 'label' => 'Pengaduan dan Aspirasi', 'module' => 'complaints', 'any' => array('tickets.verify', 'tickets.monitor_scope', 'tickets.work_assigned')),
		array('key' => 'laporan-buat', 'url' => 'admin/laporan/buat', 'icon' => 'fa-pen-to-square fa-edit', 'label' => 'Input dari Loket', 'module' => 'complaints', 'all' => array('tickets.create_on_behalf')),
		array('key' => 'ekspor', 'url' => 'admin/ekspor', 'icon' => 'fa-file-export', 'label' => 'Laporan dan Ekspor', 'all' => array('tickets.export')),
	)),
	array('heading' => 'Website dan CMS', 'items' => array(
		array('key' => 'cms-beranda', 'url' => 'admin/cms/beranda', 'icon' => 'fa-layer-group fa-object-group', 'label' => 'Pengaturan Beranda', 'all' => array('cms.page.view')),
		array('key' => 'cms-halaman', 'url' => 'admin/cms/halaman', 'icon' => 'fa-file-lines fa-file-alt', 'label' => 'Halaman Publik', 'all' => array('cms.page.view')),
		array('key' => 'cms-menu', 'url' => 'admin/cms/menu', 'icon' => 'fa-bars', 'label' => 'Menu dan Navigasi', 'all' => array('cms.menu.manage')),
		array('key' => 'cms-situs', 'url' => 'admin/cms/situs', 'icon' => 'fa-palette', 'label' => 'Identitas dan Tema', 'all' => array('cms.site.manage')),
		array('key' => 'profil', 'url' => 'admin/profil', 'icon' => 'fa-address-card', 'label' => 'Profil Desa', 'any' => array('content.edit', 'content.publish')),
		array('key' => 'konten', 'url' => 'admin/konten', 'icon' => 'fa-newspaper', 'label' => 'Konten Situs', 'any' => array('content.edit', 'cms.page.view')),
		array('key' => 'media', 'url' => 'admin/media', 'icon' => 'fa-images', 'label' => 'Media Library', 'any' => array('content.edit', 'cms.media.upload')),
	)),
	array('heading' => 'Data Desa', 'items' => array(
		array('key' => 'dataset', 'url' => 'admin/dataset', 'icon' => 'fa-chart-column fa-chart-bar', 'label' => 'Dataset Publik', 'module' => 'village_data', 'any' => array('data.review', 'data.publish')),
		array('key' => 'statistik', 'url' => 'admin/statistik', 'icon' => 'fa-chart-bar', 'label' => 'Nilai dan Dokumen Sumber', 'module' => 'village_data', 'any' => array('content.edit', 'statistics.review', 'data.review')),
	)),
	array('heading' => 'Potensi dan Fasilitas', 'items' => array(
		array('key' => 'umkm', 'url' => 'admin/umkm', 'icon' => 'fa-store', 'label' => 'Direktori UMKM', 'module' => 'umkm_directory', 'any' => array('umkm.edit', 'umkm.publish')),
		array('key' => 'fasilitas', 'url' => 'admin/fasilitas', 'icon' => 'fa-location-dot fa-map-marker-alt', 'label' => 'Direktori Fasilitas', 'module' => 'facilities', 'any' => array('facilities.edit', 'facilities.publish')),
	)),
	array('heading' => 'Pemerintahan', 'items' => array(
		array('key' => 'struktur', 'url' => 'admin/struktur', 'icon' => 'fa-sitemap', 'label' => 'Struktur Organisasi', 'module' => 'organization', 'any' => array('organization.edit', 'organization.publish')),
	)),
	array('heading' => 'Transparansi Keuangan', 'items' => array(
		array('key' => 'keuangan', 'url' => 'admin/keuangan', 'icon' => 'fa-coins', 'label' => 'Anggaran dan Realisasi', 'module' => 'budget_transparency', 'any' => array('finance.manage', 'finance.verify', 'finance.publish')),
	)),
	array('heading' => 'Aset dan Persediaan', 'items' => array(
		array('key' => 'aset', 'url' => 'admin/aset', 'icon' => 'fa-boxes-stacked fa-box', 'label' => 'Aset dan QR', 'module' => 'assets', 'all' => array('assets.view')),
		array('key' => 'audit-aset', 'url' => 'admin/audit-aset', 'icon' => 'fa-clipboard-check', 'label' => 'Audit Aset', 'module' => 'assets', 'any' => array('asset_audits.create', 'asset_audits.perform', 'asset_audits.verify')),
		array('key' => 'gudang', 'url' => 'admin/gudang', 'icon' => 'fa-warehouse', 'label' => 'Gudang Persediaan', 'module' => 'warehouse', 'all' => array('warehouse.view')),
	)),
	array('heading' => 'Pengaturan', 'items' => array(
		array('key' => 'pengguna', 'url' => 'admin/pengguna', 'icon' => 'fa-users', 'label' => 'Pengguna & Akses', 'any' => array('users.manage', 'users.create_resident', 'residents.verify', 'users.assign_roles', 'roles.manage')),
		array('key' => 'pengaturan', 'url' => 'admin/pengaturan', 'icon' => 'fa-sliders-h', 'label' => 'Pengaturan Aplikasi', 'all' => array('settings.manage')),
		array('key' => 'modul', 'url' => 'admin/pengaturan/modul', 'icon' => 'fa-toggle-on', 'label' => 'Modul dan Feature Toggle', 'all' => array('settings.feature.manage')),
		array('key' => 'audit', 'url' => 'admin/audit', 'icon' => 'fa-clipboard-list', 'label' => 'Log Audit', 'all' => array('audit.view')),
	)),
);

/**
 * Item yang tidak boleh disembunyikan atau dimatikan dari dashboard, supaya pengelola
 * RBAC tidak mengunci dirinya sendiri keluar dari halaman pengaturannya.
 */
$config['admin_menu_locked'] = array('dashboard', 'pengguna');
