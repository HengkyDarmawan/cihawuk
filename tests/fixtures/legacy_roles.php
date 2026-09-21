<?php
/**
 * Preset role granular versi 8 (sebelum role dirampingkan menjadi 4).
 *
 * Hanya dipakai test: CiTestCase::make_user() membuat role ini sebagai role non-sistem
 * bila diminta, supaya test aturan permission tetap dapat memakai kombinasi izin yang sempit.
 */

$config = array();
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

return $config['roles'];
