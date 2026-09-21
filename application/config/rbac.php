<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Preset permission dan role (sumber seed). Role teknis bukan jabatan formal desa.
|
| Daftar ini menggabungkan permission master (prompt-master §6) dengan permission granular
| modul backend CMS (modul-backend §4.2). Pemetaan kode yang setara didokumentasikan pada
| docs/roles-permissions.md; jangan membuat dua kode untuk maksud yang sama.
|
| preset_version dinaikkan bila preset role berubah. Seeder hanya MENAMBAH permission preset
| yang belum terpasang pada role sistem ketika versi naik, dan tidak pernah mencabut permission
| yang diberikan pengelola.
*/

$config['preset_version'] = 9;

$config['permissions'] = array(
	// Akun dan akses
	'users.manage' => 'Mengelola akun pengguna (status, data akun)',
	'users.impersonate' => 'Login sebagai pengguna lain untuk memeriksa tampilan dan hak aksesnya',
	'users.create_resident' => 'Mendaftarkan akun warga',
	'users.assign_roles' => 'Memberi atau mencabut role pengguna',
	'residents.verify' => 'Mereview dan mengaktifkan akun/profil warga',
	'roles.manage' => 'Mengelola role dan permission',
	'settings.manage' => 'Mengelola pengaturan aplikasi, kalender SLA, dan status job',
	'settings.feature.manage' => 'Mengubah status modul aplikasi (feature module)',
	'settings.security.manage' => 'Mengubah pengaturan keamanan aplikasi',
	'audit.view' => 'Melihat log audit',

	// Layanan warga
	'tickets.create_on_behalf' => 'Mencatat laporan dari loket kantor desa',
	'tickets.verify' => 'Memverifikasi laporan masuk',
	'tickets.assign' => 'Mendisposisikan dan memindahkan penugasan laporan',
	'tickets.work_assigned' => 'Menangani laporan yang ditugaskan',
	'tickets.monitor_scope' => 'Memantau laporan dalam lingkup unit',
	'tickets.close' => 'Menutup laporan berdasarkan kebijakan',
	'tickets.reopen' => 'Membuka kembali laporan yang selesai',
	'tickets.view_identity' => 'Melihat identitas pelapor (diaudit)',
	'tickets.handle_confidential' => 'Mengakses laporan rahasia',
	'tickets.export' => 'Mengekspor rekap laporan',

	// Konten situs
	'content.edit' => 'Menyusun dan mengubah draft konten',
	'content.publish' => 'Menerbitkan dan mengarsipkan konten',
	'cms.page.view' => 'Melihat halaman dan section CMS',
	'cms.page.create' => 'Membuat halaman CMS baru',
	'cms.page.edit' => 'Mengubah draft halaman dan section',
	'cms.page.submit_review' => 'Mengajukan halaman untuk direview',
	'cms.page.approve' => 'Menyetujui halaman yang direview',
	'cms.page.publish' => 'Menerbitkan halaman dan menjadwalkan publikasi',
	'cms.page.unpublish' => 'Menarik halaman dari publikasi',
	'cms.page.rollback' => 'Mengembalikan publikasi ke versi sebelumnya',
	'cms.menu.manage' => 'Mengelola menu dan navigasi publik',
	'cms.site.manage' => 'Mengelola identitas situs, token tema, dan cache publik',
	'cms.media.upload' => 'Mengunggah berkas ke Media Library',
	'cms.media.approve_public' => 'Menyetujui media untuk tampil publik',

	// Data desa dan statistik
	'data.import' => 'Mengimpor dokumen sumber ke staging',
	'data.review' => 'Mereview observasi, konflik, dan koreksi data sumber',
	'data.publish' => 'Menerbitkan dataset dan statistik publik',
	'statistics.review' => 'Mereview data sumber dan statistik',

	// Fasilitas dan UMKM
	'facilities.edit' => 'Mengelola direktori fasilitas desa',
	'facilities.publish' => 'Menerbitkan dan menarik entri fasilitas',

	'umkm.edit' => 'Mengelola profil UMKM desa',
	'umkm.publish' => 'Menerbitkan dan menarik profil UMKM',

	// Transparansi keuangan
	'finance.manage' => 'Mengisi data anggaran dan realisasi sebagai draft',
	'finance.verify' => 'Memeriksa rekonsiliasi dan dokumen pendukung keuangan',
	'finance.publish' => 'Menerbitkan snapshot anggaran publik',

	// Aset dan inventaris
	'assets.view' => 'Melihat register dan unit aset',
	'assets.create' => 'Menambah register atau unit aset',
	'assets.edit' => 'Mengubah data register atau unit aset',
	'assets.view_financial' => 'Melihat nilai perolehan dan biaya aset',
	'assets.view_documents' => 'Melihat dokumen kepemilikan aset',
	'assets.print_labels' => 'Menerbitkan dan mencetak label QR aset',
	'assets.move' => 'Mencatat mutasi lokasi atau penanggung jawab aset',
	'assets.maintain' => 'Mencatat pemeliharaan aset',
	'assets.change_status' => 'Mengubah status lifecycle aset',
	'assets.export' => 'Mengekspor data aset',
	'asset_audits.create' => 'Membuat sesi audit fisik aset',
	'asset_audits.perform' => 'Melaksanakan audit fisik dan mencatat temuan',
	'asset_audits.verify' => 'Memverifikasi temuan audit aset',

	// Gudang persediaan
	'warehouse.view' => 'Melihat item, saldo, dan kartu stok',
	'warehouse.receive' => 'Mencatat penerimaan barang persediaan',
	'warehouse.issue' => 'Mencatat pengeluaran barang persediaan',
	'warehouse.transfer' => 'Mencatat transfer antarlokasi gudang',
	'warehouse.adjust' => 'Mencatat penyesuaian saldo dengan alasan',
	'warehouse.stocktake' => 'Melaksanakan stock opname',
	'warehouse.export' => 'Mengekspor data persediaan',

	// Struktur organisasi
	'organization.edit' => 'Mengelola periode, unit, jabatan, orang, dan penugasan',
	'organization.publish' => 'Menerbitkan snapshot struktur organisasi',
);

/*
| Role dirampingkan menjadi 4. Super Admin tetap memegang seluruh permission lewat
| AuthorizationService (termasuk permission baru), daftar di bawah hanya untuk tampilan.
*/
$config['roles'] = array(
	'super_admin' => array(
		'name' => 'Super Admin',
		'description' => 'Akses penuh ke seluruh modul, pengelolaan akun, role, pengaturan dan login sebagai pengguna lain.',
		'is_staff' => 1,
		'permissions' => array_keys($config['permissions']),
	),
	'admin_desa' => array(
		'name' => 'Admin Desa',
		'description' => 'Mengelola seluruh pekerjaan desa: laporan warga, konten situs, data, keuangan, aset dan gudang. Tidak mengelola role dan pengaturan keamanan.',
		'is_staff' => 1,
		'permissions' => array_values(array_diff(array_keys($config['permissions']), array(
			'roles.manage', 'users.assign_roles', 'users.impersonate', 'settings.security.manage', 'settings.feature.manage',
		))),
	),
	'petugas' => array(
		'name' => 'Petugas',
		'description' => 'Pekerjaan harian: menangani laporan yang ditugaskan, mencatat laporan loket, menyusun draft konten, serta mencatat aset dan gudang.',
		'is_staff' => 1,
		'permissions' => array(
			'tickets.create_on_behalf', 'tickets.work_assigned', 'users.create_resident',
			'content.edit', 'cms.page.view', 'cms.page.create', 'cms.page.edit', 'cms.page.submit_review', 'cms.media.upload',
			'facilities.edit', 'umkm.edit',
			'assets.view', 'assets.move', 'assets.maintain', 'assets.print_labels', 'asset_audits.perform',
			'warehouse.view', 'warehouse.receive', 'warehouse.issue', 'warehouse.transfer', 'warehouse.stocktake',
		),
	),
	'resident' => array(
		'name' => 'Warga',
		'description' => 'Warga berakun: membuat dan mengelola laporan milik sendiri.',
		'is_staff' => 0,
		'permissions' => array(),
	),
);

/*
| Role lama (preset versi 8) dan role penggantinya. MasterSeeder memindahkan pemegang role
| lama ke role baru lalu menghapus role lama (hanya role sistem).
*/
$config['legacy_role_map'] = array(
	'service_admin' => 'admin_desa',
	'service_coordinator' => 'admin_desa',
	'village_head' => 'admin_desa',
	'website_admin' => 'admin_desa',
	'content_publisher' => 'admin_desa',
	'data_verifier' => 'admin_desa',
	'finance_manager' => 'admin_desa',
	'finance_verifier' => 'admin_desa',
	'asset_manager' => 'admin_desa',
	'asset_verifier' => 'admin_desa',
	'system_auditor' => 'admin_desa',
	'confidential_handler' => 'admin_desa',
	'officer' => 'petugas',
	'front_desk' => 'petugas',
	'content_editor' => 'petugas',
	'asset_auditor' => 'petugas',
	'warehouse_officer' => 'petugas',
);

/*
| Nama modul untuk mengelompokkan permission di layar akses (prefix kode => label).
*/
$config['permission_groups'] = array(
	'users' => 'Akun pengguna',
	'residents' => 'Akun pengguna',
	'roles' => 'Role dan akses',
	'settings' => 'Pengaturan',
	'audit' => 'Log audit',
	'tickets' => 'Laporan warga',
	'content' => 'Konten situs',
	'cms' => 'Konten situs',
	'data' => 'Data dan statistik',
	'statistics' => 'Data dan statistik',
	'facilities' => 'Fasilitas dan UMKM',
	'umkm' => 'Fasilitas dan UMKM',
	'finance' => 'Keuangan',
	'assets' => 'Aset',
	'asset_audits' => 'Aset',
	'warehouse' => 'Gudang persediaan',
	'organization' => 'Struktur organisasi',
);
