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

$config['preset_version'] = 8;

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

$config['roles'] = array(
	'super_admin' => array(
		'name' => 'Super Admin',
		'description' => 'Mengelola akun, role, konfigurasi, modul dan pendaftaran warga. Tidak otomatis mengakses isi laporan atau data bisnis modul lain.',
		'is_staff' => 1,
		'permissions' => array('users.manage', 'users.impersonate', 'users.create_resident', 'users.assign_roles', 'residents.verify', 'roles.manage', 'settings.manage', 'settings.feature.manage', 'settings.security.manage', 'audit.view'),
	),
	'service_admin' => array(
		'name' => 'Admin Pelayanan',
		'description' => 'Verifikasi, kategori, disposisi, review akun warga, input loket dan laporan operasional.',
		'is_staff' => 1,
		'permissions' => array('tickets.create_on_behalf', 'tickets.verify', 'tickets.assign', 'tickets.work_assigned', 'tickets.close', 'tickets.view_identity', 'tickets.export', 'residents.verify', 'users.create_resident'),
	),
	'service_coordinator' => array(
		'name' => 'Koordinator Pelayanan',
		'description' => 'Monitoring lintas petugas, eskalasi, reassignment, penutupan pengecualian dan reopen.',
		'is_staff' => 1,
		'permissions' => array('tickets.monitor_scope', 'tickets.assign', 'tickets.close', 'tickets.reopen', 'tickets.work_assigned', 'tickets.export'),
	),
	'officer' => array(
		'name' => 'Petugas',
		'description' => 'Menangani laporan yang ditugaskan dan mencatat tindak lanjut.',
		'is_staff' => 1,
		'permissions' => array('tickets.work_assigned'),
	),
	'front_desk' => array(
		'name' => 'Petugas Loket',
		'description' => 'Mencatat laporan yang disampaikan langsung di kantor desa.',
		'is_staff' => 1,
		'permissions' => array('tickets.create_on_behalf', 'tickets.work_assigned'),
	),
	'confidential_handler' => array(
		'name' => 'Penangan Laporan Rahasia',
		'description' => 'Akses khusus laporan rahasia dan identitas pelapor; dipasangkan dengan role pelayanan lain. Setiap akses diaudit.',
		'is_staff' => 1,
		'permissions' => array('tickets.handle_confidential', 'tickets.view_identity'),
	),
	'village_head' => array(
		'name' => 'Kepala Desa',
		'description' => 'Ringkasan, monitoring dan arahan pada kasus dalam lingkup kewenangan.',
		'is_staff' => 1,
		'permissions' => array('tickets.monitor_scope'),
	),
	'website_admin' => array(
		'name' => 'Admin Website',
		'description' => 'Mengelola halaman publik, section beranda, menu dan media. Penerbitan tetap memerlukan role penerbit.',
		'is_staff' => 1,
		'permissions' => array('content.edit', 'cms.page.view', 'cms.page.create', 'cms.page.edit', 'cms.page.submit_review', 'cms.menu.manage', 'cms.site.manage', 'cms.media.upload', 'facilities.edit', 'organization.edit', 'umkm.edit'),
	),
	'content_editor' => array(
		'name' => 'Editor Konten',
		'description' => 'Menyusun draft profil, berita, potensi, agenda, media dan statistik.',
		'is_staff' => 1,
		'permissions' => array('content.edit', 'cms.page.view', 'cms.page.create', 'cms.page.edit', 'cms.page.submit_review', 'cms.media.upload', 'facilities.edit'),
	),
	'content_publisher' => array(
		'name' => 'Penerbit Konten',
		'description' => 'Mereview dan menerbitkan konten, menarik publikasi, serta melakukan rollback versi publik.',
		'is_staff' => 1,
		'permissions' => array('content.edit', 'content.publish', 'statistics.review', 'cms.page.view', 'cms.page.approve', 'cms.page.publish', 'cms.page.unpublish', 'cms.page.rollback', 'cms.menu.manage', 'cms.media.approve_public', 'data.publish', 'facilities.publish', 'organization.publish', 'finance.publish', 'umkm.publish'),
	),
	'data_verifier' => array(
		'name' => 'Verifikator Data',
		'description' => 'Mengimpor dokumen sumber ke staging serta memeriksa angka, tahun, sumber dan konflik data.',
		'is_staff' => 1,
		'permissions' => array('data.import', 'data.review', 'statistics.review'),
	),
	'finance_manager' => array(
		'name' => 'Pengelola Keuangan',
		'description' => 'Mengisi anggaran, perubahan dan realisasi sebagai draft.',
		'is_staff' => 1,
		'permissions' => array('finance.manage'),
	),
	'finance_verifier' => array(
		'name' => 'Verifikator Keuangan',
		'description' => 'Memeriksa rekonsiliasi anggaran dan dokumen pendukung sebelum publikasi.',
		'is_staff' => 1,
		'permissions' => array('finance.verify'),
	),
	'asset_manager' => array(
		'name' => 'Pengurus Aset',
		'description' => 'Mengelola register, unit fisik, lokasi, label QR, mutasi dan pemeliharaan aset.',
		'is_staff' => 1,
		'permissions' => array('assets.view', 'assets.create', 'assets.edit', 'assets.print_labels', 'assets.move', 'assets.maintain', 'assets.change_status', 'assets.export', 'asset_audits.create'),
	),
	'asset_auditor' => array(
		'name' => 'Auditor Aset',
		'description' => 'Melaksanakan audit fisik, memindai QR dan mencatat temuan sesuai penugasan.',
		'is_staff' => 1,
		'permissions' => array('assets.view', 'asset_audits.perform'),
	),
	'asset_verifier' => array(
		'name' => 'Verifikator Aset',
		'description' => 'Mereview temuan audit, selisih dan usulan perubahan status aset.',
		'is_staff' => 1,
		'permissions' => array('assets.view', 'assets.view_documents', 'asset_audits.verify'),
	),
	'warehouse_officer' => array(
		'name' => 'Petugas Gudang',
		'description' => 'Penerimaan, pengeluaran, transfer dan stock opname persediaan. Penyesuaian saldo memerlukan permission terpisah.',
		'is_staff' => 1,
		'permissions' => array('warehouse.view', 'warehouse.receive', 'warehouse.issue', 'warehouse.transfer', 'warehouse.stocktake', 'warehouse.export'),
	),
	'system_auditor' => array(
		'name' => 'Auditor Sistem',
		'description' => 'Membaca log audit dan riwayat tanpa mengubah data.',
		'is_staff' => 1,
		'permissions' => array('audit.view'),
	),
	'resident' => array(
		'name' => 'Warga',
		'description' => 'Warga berakun: membuat dan mengelola laporan milik sendiri.',
		'is_staff' => 0,
		'permissions' => array(),
	),
);
