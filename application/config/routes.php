<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Route eksplisit. Route literal didaftarkan sebelum wildcard. Route terakhir
| menangkap semua URI lain sehingga auto-routing CI3 (controller/method) tidak
| dapat dipakai untuk memanggil method yang tidak didaftarkan.
| Kontrol akses tetap diperiksa di controller/service.
*/

$route['default_controller'] = 'home';
$route['404_override'] = 'errors/not_found';
$route['translate_uri_dashes'] = FALSE;

// CLI (Tools menolak HTTP)
$route['tools'] = 'tools/index';
$route['tools/(:any)'] = 'tools/$1';
$route['tools/(:any)/(:any)'] = 'tools/$1/$2';
$route['tools/(:any)/(:any)/(:any)'] = 'tools/$1/$2/$3';

// ---------------------------------------------------------------- Publik
$route['profil'] = 'profil/index';
$route['profil/sejarah'] = 'profil/sejarah';
$route['profil/visi-misi'] = 'profil/visi_misi';
$route['profil/geografi'] = 'profil/geografi';
$route['pemerintahan'] = 'pemerintahan/index';
$route['pemerintahan/struktur'] = 'pemerintahan/struktur';
$route['data-desa'] = 'data_desa/index';
$route['data-desa/(:any)/unduh.csv']['GET'] = 'data_desa/unduh/$1';
// Alamat tetap per tema; didaftarkan setelah rute unduhan supaya tidak menelannya.
$route['data-desa/(:any)']['GET'] = 'data_desa/tema/$1';
$route['transparansi/anggaran'] = 'transparansi/anggaran';
$route['transparansi/anggaran/(:num)/unduh.json']['GET'] = 'transparansi/anggaran_json/$1';
$route['transparansi/anggaran/(:num)/(:any)/unduh.csv']['GET'] = 'transparansi/anggaran_csv/$1/$2';
$route['fasilitas'] = 'fasilitas/index';
$route['fasilitas/(:any)'] = 'fasilitas/detail/$1';
$route['aset/q/(:any)']['GET'] = 'aset/q/$1';
$route['umkm'] = 'umkm/index';
$route['umkm/(:any)'] = 'umkm/detail/$1';
$route['potensi'] = 'potensi/index';
$route['potensi/(:any)'] = 'potensi/detail/$1';
$route['berita'] = 'berita/index';
$route['berita/kategori/(:any)'] = 'berita/kategori/$1';
$route['berita/(:any)'] = 'berita/detail/$1';
$route['pengumuman/(:any)'] = 'berita/detail/$1/announcement';
$route['informasi'] = 'informasi/index';
$route['agenda'] = 'agenda/index';
$route['agenda/(:any)'] = 'agenda/detail/$1';
$route['galeri'] = 'galeri/index';
$route['galeri/(:any)'] = 'galeri/detail/$1';
$route['dokumen'] = 'dokumen/index';
$route['dokumen/(:num)/unduh'] = 'dokumen/unduh/$1';
$route['kontak'] = 'kontak/index';
$route['cari'] = 'cari/index';
$route['privasi'] = 'halaman/privasi';
$route['ketentuan'] = 'halaman/ketentuan';
$route['sitemap.xml'] = 'sitemap/index';
$route['media/(:any)'] = 'media/show/$1';

// Layanan warga & laporan anonim
$route['layanan'] = 'layanan/index';
$route['lapor']['GET'] = 'lapor/index';
$route['lapor']['POST'] = 'lapor/submit';
$route['lapor/berhasil']['GET'] = 'lapor/receipt';
$route['lapor/berhasil/selesai']['POST'] = 'lapor/receipt_done';
$route['lacak']['GET'] = 'lacak/index';
$route['lacak']['POST'] = 'lacak/verify';
$route['lacak/detail']['GET'] = 'lacak/detail';
$route['lacak/balas']['POST'] = 'lacak/reply';
$route['lacak/konfirmasi']['POST'] = 'lacak/confirm';
$route['lacak/tarik']['POST'] = 'lacak/withdraw';
$route['lacak/keluar']['POST'] = 'lacak/leave';
$route['lacak/klaim']['POST'] = 'lacak/claim';
$route['csrf-token']['GET'] = 'csrf/token';

// Autentikasi
$route['masuk']['GET'] = 'auth/login';
$route['masuk']['POST'] = 'auth/login_submit';
$route['mfa']['GET'] = 'auth/mfa';
$route['mfa']['POST'] = 'auth/mfa_submit';
$route['daftar']['GET'] = 'auth/register';
$route['daftar']['POST'] = 'auth/register_submit';
$route['daftar/berhasil']['GET'] = 'auth/register_done';
$route['aktivasi']['GET'] = 'auth/activate';
$route['aktivasi']['POST'] = 'auth/activate_submit';
$route['lupa-password']['GET'] = 'auth/forgot';
$route['lupa-password']['POST'] = 'auth/forgot_submit';
$route['reset-password']['GET'] = 'auth/reset';
$route['reset-password']['POST'] = 'auth/reset_submit';
$route['keluar']['POST'] = 'auth/logout';
$route['akun/kembali']['POST'] = 'auth/stop_impersonation';
$route['bantuan-akun'] = 'auth/help';

// Berkas privat
$route['berkas/privat/(:num)']['GET'] = 'berkas/privat/$1';

// ---------------------------------------------------------------- Warga
$route['warga'] = 'warga/dashboard/index';
$route['warga/laporan']['GET'] = 'warga/laporan/index';
$route['warga/laporan']['POST'] = 'warga/laporan/store';
$route['warga/laporan/buat']['GET'] = 'warga/laporan/create';
$route['warga/laporan/(:any)/balas']['POST'] = 'warga/laporan/reply/$1';
$route['warga/laporan/(:any)/konfirmasi']['POST'] = 'warga/laporan/confirm/$1';
$route['warga/laporan/(:any)/tarik']['POST'] = 'warga/laporan/withdraw/$1';
$route['warga/laporan/(:any)']['GET'] = 'warga/laporan/show/$1';
$route['warga/profil']['GET'] = 'warga/profil/index';
$route['warga/profil']['POST'] = 'warga/profil/update';
$route['warga/notifikasi']['GET'] = 'warga/notifikasi/index';
$route['warga/notifikasi/baca']['POST'] = 'warga/notifikasi/read';
$route['warga/akun']['GET'] = 'warga/akun/index';
$route['warga/akun/(:any)']['POST'] = 'warga/akun/$1';

// ---------------------------------------------------------------- Admin
$route['admin'] = 'admin/dashboard/index';
$route['admin/pratinjau']['POST'] = 'admin/dashboard/preview';
$route['admin/laporan']['GET'] = 'admin/laporan/index';
$route['admin/laporan']['POST'] = 'admin/laporan/store';
$route['admin/laporan/data']['GET'] = 'admin/laporan/data';
$route['admin/laporan/buat']['GET'] = 'admin/laporan/create';
$route['admin/laporan/cari-warga']['GET'] = 'admin/laporan/search_residents';
$route['admin/laporan/(:any)/verifikasi']['POST'] = 'admin/laporan/verify/$1';
$route['admin/laporan/(:any)/disposisi']['POST'] = 'admin/laporan/assign/$1';
$route['admin/laporan/(:any)/tindak-lanjut']['POST'] = 'admin/laporan/follow_up/$1';
$route['admin/laporan/(:any)/status']['POST'] = 'admin/laporan/transition/$1';
$route['admin/laporan/(:any)/identitas']['POST'] = 'admin/laporan/reveal_identity/$1';
$route['admin/laporan/(:any)/konflik']['POST'] = 'admin/laporan/conflict/$1';
$route['admin/laporan/(:any)/prioritas']['POST'] = 'admin/laporan/priority/$1';
$route['admin/laporan/(:any)']['GET'] = 'admin/laporan/show/$1';
$route['admin/ekspor']['GET'] = 'admin/ekspor/index';
$route['admin/ekspor']['POST'] = 'admin/ekspor/request_export';
$route['admin/ekspor/(:any)/unduh']['GET'] = 'admin/ekspor/download/$1';
$route['admin/notifikasi']['GET'] = 'admin/notifikasi/index';
$route['admin/notifikasi/baca']['POST'] = 'admin/notifikasi/read';
$route['admin/akun']['GET'] = 'admin/akun/index';
$route['admin/akun/(:any)']['POST'] = 'admin/akun/$1';
$route['admin/audit']['GET'] = 'admin/audit/index';

// Pengguna
$route['admin/pengguna']['GET'] = 'admin/pengguna/index';
$route['admin/pengguna/buat']['GET'] = 'admin/pengguna/create';
$route['admin/pengguna/buat']['POST'] = 'admin/pengguna/store';
$route['admin/pengguna/(:any)/(:any)']['POST'] = 'admin/pengguna/action/$1/$2';
$route['admin/pengguna/(:any)']['GET'] = 'admin/pengguna/show/$1';

// Role, izin, dan menu dashboard (roles.manage).
$route['admin/rbac']['GET'] = 'admin/rbac/index';
$route['admin/rbac/role/baru']['GET'] = 'admin/rbac/role_create';
$route['admin/rbac/role/baru']['POST'] = 'admin/rbac/role_store';
$route['admin/rbac/role/(:any)/hapus']['POST'] = 'admin/rbac/role_delete/$1';
$route['admin/rbac/role/(:any)']['GET'] = 'admin/rbac/role_edit/$1';
$route['admin/rbac/role/(:any)']['POST'] = 'admin/rbac/role_update/$1';
$route['admin/rbac/izin']['GET'] = 'admin/rbac/permissions';
$route['admin/rbac/izin']['POST'] = 'admin/rbac/permission_store';
$route['admin/rbac/izin/(:any)/hapus']['POST'] = 'admin/rbac/permission_delete/$1';
$route['admin/rbac/izin/(:any)']['POST'] = 'admin/rbac/permission_update/$1';
$route['admin/rbac/menu']['GET'] = 'admin/rbac/menu';
$route['admin/rbac/menu']['POST'] = 'admin/rbac/menu_save';

// Pengaturan beranda dan halaman publik (section registry, versi, snapshot)
$route['admin/cms/beranda']['GET'] = 'admin/cms/home';
$route['admin/cms/halaman']['GET'] = 'admin/cms/pages';
$route['admin/cms/halaman/buat']['POST'] = 'admin/cms/create_page';
$route['admin/cms/section/tambah']['POST'] = 'admin/cms/add_section';
$route['admin/cms/section/urutkan']['POST'] = 'admin/cms/reorder';
$route['admin/cms/section/(:any)/simpan']['POST'] = 'admin/cms/save_section/$1';
$route['admin/cms/section/(:any)/status']['POST'] = 'admin/cms/toggle_section/$1';
$route['admin/cms/section/(:any)/pindah']['POST'] = 'admin/cms/move_section/$1';
$route['admin/cms/section/(:any)/arsip']['POST'] = 'admin/cms/archive_section/$1';
$route['admin/cms/section/(:any)']['GET'] = 'admin/cms/section/$1';
$route['admin/cms/halaman/(:any)/simpan']['POST'] = 'admin/cms/save_page/$1';
$route['admin/cms/halaman/(:any)/pratinjau']['POST'] = 'admin/cms/preview_link/$1';
$route['admin/cms/halaman/(:any)/alur/(:any)']['POST'] = 'admin/cms/workflow/$1/$2';
$route['admin/cms/halaman/(:any)']['GET'] = 'admin/cms/page/$1';
$route['admin/pratinjau/(:any)']['GET'] = 'pratinjau/show/$1';

// Menu publik dan identitas/tema situs
$route['admin/cms/menu']['GET'] = 'admin/cms/menus';
$route['admin/cms/menu/(:any)']['GET'] = 'admin/cms/menu/$1';
$route['admin/cms/menu/(:any)/item']['POST'] = 'admin/cms/save_menu_item/$1';
$route['admin/cms/menu/(:any)/urutkan']['POST'] = 'admin/cms/reorder_menu/$1';
$route['admin/cms/menu/(:any)/terbitkan']['POST'] = 'admin/cms/publish_menu/$1';
$route['admin/cms/menu/(:any)/rollback']['POST'] = 'admin/cms/rollback_menu/$1';
$route['admin/cms/menu-item/(:any)/(:any)']['POST'] = 'admin/cms/menu_item_action/$1/$2';
$route['admin/cms/situs']['GET'] = 'admin/cms/site';
$route['admin/cms/situs/simpan']['POST'] = 'admin/cms/save_site';
$route['admin/cms/situs/terbitkan']['POST'] = 'admin/cms/publish_site';
$route['admin/cms/situs/rollback']['POST'] = 'admin/cms/rollback_site';

// Konten (CMS)
$route['admin/konten']['GET'] = 'admin/konten/index';
$route['admin/konten/profil']['GET'] = 'admin/konten/profile';
$route['admin/konten/profil']['POST'] = 'admin/konten/profile_save';
$route['admin/konten/(:any)']['GET'] = 'admin/konten/listing/$1';
$route['admin/konten/(:any)/buat']['GET'] = 'admin/konten/edit/$1';
$route['admin/konten/(:any)/simpan']['POST'] = 'admin/konten/save/$1';
$route['admin/konten/(:any)/(:num)']['GET'] = 'admin/konten/edit/$1/$2';
$route['admin/konten/(:any)/(:num)/status']['POST'] = 'admin/konten/status/$1/$2';

// Media
$route['admin/media']['GET'] = 'admin/media/index';
$route['admin/media']['POST'] = 'admin/media/upload';
$route['admin/media/(:num)']['POST'] = 'admin/media/update/$1';
$route['admin/media/(:num)/hapus']['POST'] = 'admin/media/delete/$1';
$route['admin/media/(:num)/pratinjau']['GET'] = 'admin/media/preview/$1';

// Dataset statistik (Tahap 4)

// Gudang barang persediaan.
$route['admin/gudang']['GET'] = 'admin/gudang/index';
$route['admin/gudang/barang']['POST'] = 'admin/gudang/save_item';
$route['admin/gudang/lokasi']['POST'] = 'admin/gudang/save_location';
$route['admin/gudang/transaksi']['POST'] = 'admin/gudang/create_transaction';
$route['admin/gudang/opname']['POST'] = 'admin/gudang/open_stocktake';
$route['admin/gudang/barang/(:any)']['GET'] = 'admin/gudang/item/$1';
$route['admin/gudang/barang/(:any)/konversi']['POST'] = 'admin/gudang/save_conversion/$1';
$route['admin/gudang/transaksi/(:any)']['GET'] = 'admin/gudang/transaction/$1';
$route['admin/gudang/transaksi/(:any)/baris']['POST'] = 'admin/gudang/add_line/$1';
$route['admin/gudang/transaksi/(:any)/posting']['POST'] = 'admin/gudang/post_transaction/$1';
$route['admin/gudang/opname/(:any)']['GET'] = 'admin/gudang/stocktake/$1';
$route['admin/gudang/opname/(:any)/hitung']['POST'] = 'admin/gudang/count_line/$1';
$route['admin/gudang/opname/(:any)/tutup']['POST'] = 'admin/gudang/close_stocktake/$1';

// Aset, unit fisik, QR, dan audit fisik.
$route['admin/aset']['GET'] = 'admin/aset/index';
$route['admin/aset/register']['POST'] = 'admin/aset/create_register';
$route['admin/aset/kategori']['POST'] = 'admin/aset/save_category';
$route['admin/aset/lokasi']['POST'] = 'admin/aset/save_location';
$route['admin/aset/label']['POST'] = 'admin/aset/create_labels';
$route['admin/aset/unit/(:any)']['GET'] = 'admin/aset/unit/$1';
$route['admin/aset/unit/(:any)/status']['POST'] = 'admin/aset/change_status/$1';
$route['admin/aset/unit/(:any)/qr/(:any)']['POST'] = 'admin/aset/qr/$1/$2';
$route['admin/aset/unit/(:any)/mutasi']['POST'] = 'admin/aset/request_movement/$1';
$route['admin/aset/unit/(:any)/mutasi/(:any)/terima']['POST'] = 'admin/aset/accept_movement/$1/$2';
$route['admin/aset/unit/(:any)/pinjam']['POST'] = 'admin/aset/checkout/$1';
$route['admin/aset/unit/(:any)/pinjam/(:any)/kembali']['POST'] = 'admin/aset/return_loan/$1/$2';
$route['admin/aset/unit/(:any)/pemeliharaan']['POST'] = 'admin/aset/create_maintenance/$1';
$route['admin/aset/unit/(:any)/pemeliharaan/(:any)/selesai']['POST'] = 'admin/aset/complete_maintenance/$1/$2';
$route['admin/aset/(:any)']['GET'] = 'admin/aset/register/$1';
$route['admin/aset/(:any)/simpan']['POST'] = 'admin/aset/save_register/$1';
$route['admin/aset/(:any)/verifikasi']['POST'] = 'admin/aset/verify_register/$1';
$route['admin/aset/(:any)/unit']['POST'] = 'admin/aset/create_unit/$1';
$route['admin/aset/(:any)/pecah-unit']['POST'] = 'admin/aset/propose_units/$1';

// Audit fisik aset.
$route['admin/audit-aset']['GET'] = 'admin/audit_aset/index';
$route['admin/audit-aset/buat']['POST'] = 'admin/audit_aset/create';
$route['admin/audit-aset/(:any)']['GET'] = 'admin/audit_aset/show/$1';
$route['admin/audit-aset/(:any)/terbitkan']['POST'] = 'admin/audit_aset/publish/$1';
$route['admin/audit-aset/(:any)/temuan']['POST'] = 'admin/audit_aset/record/$1';
$route['admin/audit-aset/(:any)/temuan/(:any)/(:any)']['POST'] = 'admin/audit_aset/finding_action/$1/$2/$3';
$route['admin/audit-aset/(:any)/tutup']['POST'] = 'admin/audit_aset/close/$1';
$route['admin/audit-aset/(:any)/addendum']['POST'] = 'admin/audit_aset/addendum/$1';

// Direktori UMKM.
$route['admin/umkm']['GET'] = 'admin/umkm/index';
$route['admin/umkm/buat']['POST'] = 'admin/umkm/create';
$route['admin/umkm/(:any)/simpan']['POST'] = 'admin/umkm/save/$1';
$route['admin/umkm/(:any)/alur/(:any)']['POST'] = 'admin/umkm/workflow/$1/$2';
$route['admin/umkm/(:any)']['GET'] = 'admin/umkm/show/$1';

// Transparansi anggaran.
$route['admin/keuangan']['GET'] = 'admin/keuangan/index';
$route['admin/keuangan/tahun']['POST'] = 'admin/keuangan/create_year';
$route['admin/keuangan/(:any)']['GET'] = 'admin/keuangan/show/$1';
$route['admin/keuangan/(:any)/revisi']['POST'] = 'admin/keuangan/save_revision/$1';
$route['admin/keuangan/(:any)/kategori']['POST'] = 'admin/keuangan/save_category/$1';
$route['admin/keuangan/(:any)/angka']['POST'] = 'admin/keuangan/save_line/$1';
$route['admin/keuangan/(:any)/dokumen']['POST'] = 'admin/keuangan/save_document/$1';
$route['admin/keuangan/(:any)/alur/(:any)']['POST'] = 'admin/keuangan/workflow/$1/$2';

// Struktur organisasi dinamis.
$route['admin/struktur']['GET'] = 'admin/struktur/index';
$route['admin/struktur/periode/simpan']['POST'] = 'admin/struktur/save_period';
$route['admin/struktur/orang/simpan']['POST'] = 'admin/struktur/save_person';
$route['admin/struktur/periode/(:any)']['GET'] = 'admin/struktur/period/$1';
$route['admin/struktur/periode/(:any)/salin']['POST'] = 'admin/struktur/clone_structure/$1';
$route['admin/struktur/periode/(:any)/unit']['POST'] = 'admin/struktur/save_unit/$1';
$route['admin/struktur/periode/(:any)/jabatan']['POST'] = 'admin/struktur/save_position/$1';
$route['admin/struktur/periode/(:any)/jabatan-nonaktif']['POST'] = 'admin/struktur/deactivate_position/$1';
$route['admin/struktur/periode/(:any)/penugasan']['POST'] = 'admin/struktur/save_assignment/$1';
$route['admin/struktur/periode/(:any)/penugasan-akhiri']['POST'] = 'admin/struktur/end_assignment/$1';
$route['admin/struktur/periode/(:any)/alur/(:any)']['POST'] = 'admin/struktur/workflow/$1/$2';

// Direktori fasilitas dan lokasi publik.
$route['admin/fasilitas']['GET'] = 'admin/fasilitas/index';
$route['admin/fasilitas/buat']['POST'] = 'admin/fasilitas/create';
$route['admin/fasilitas/lokasi/simpan']['POST'] = 'admin/fasilitas/save_place';
$route['admin/fasilitas/lokasi/(:any)/verifikasi']['POST'] = 'admin/fasilitas/verify_place/$1';
$route['admin/fasilitas/(:any)/simpan']['POST'] = 'admin/fasilitas/save/$1';
$route['admin/fasilitas/(:any)/layanan/(:any)']['POST'] = 'admin/fasilitas/service/$1/$2';
$route['admin/fasilitas/(:any)/alur/(:any)']['POST'] = 'admin/fasilitas/workflow/$1/$2';
$route['admin/fasilitas/(:any)']['GET'] = 'admin/fasilitas/show/$1';

// Profil desa: blok terstruktur berversi dan linimasa kepemimpinan.
$route['admin/profil']['GET'] = 'admin/profil/index';
$route['admin/profil/blok/(:any)']['GET'] = 'admin/profil/block/$1';
$route['admin/profil/blok/(:any)/simpan']['POST'] = 'admin/profil/save_block/$1';
$route['admin/profil/blok/(:any)/ajukan']['POST'] = 'admin/profil/submit_block/$1';
$route['admin/profil/blok/(:any)/verifikasi']['POST'] = 'admin/profil/verify_block/$1';
$route['admin/profil/blok/(:any)/arsipkan']['POST'] = 'admin/profil/archive_block/$1';
$route['admin/profil/periode/simpan']['POST'] = 'admin/profil/save_term';
$route['admin/profil/periode/(:any)/(:any)']['POST'] = 'admin/profil/term_action/$1/$2';
$route['admin/profil/alur/(:any)']['POST'] = 'admin/profil/workflow/$1';

$route['admin/dataset']['GET'] = 'admin/dataset/index';
$route['admin/dataset/buat']['POST'] = 'admin/dataset/create';
$route['admin/dataset/(:any)/simpan']['POST'] = 'admin/dataset/save/$1';
$route['admin/dataset/(:any)/seri/(:any)']['POST'] = 'admin/dataset/series/$1/$2';
$route['admin/dataset/(:any)/verifikasi']['POST'] = 'admin/dataset/verify/$1';
$route['admin/dataset/(:any)/periksa']['POST'] = 'admin/dataset/validate/$1';
$route['admin/dataset/(:any)/alur/(:any)']['POST'] = 'admin/dataset/workflow/$1/$2';
$route['admin/dataset/(:any)']['GET'] = 'admin/dataset/show/$1';

// Statistik & data sumber
$route['admin/statistik']['GET'] = 'admin/statistik/index';
$route['admin/statistik/sumber']['GET'] = 'admin/statistik/sources';
$route['admin/statistik/sumber/(:num)']['GET'] = 'admin/statistik/source/$1';
$route['admin/statistik/impor']['POST'] = 'admin/statistik/import';
$route['admin/statistik/observasi/(:num)']['POST'] = 'admin/statistik/review_observation/$1';
$route['admin/statistik/isu']['GET'] = 'admin/statistik/issues';
$route['admin/statistik/isu/(:num)']['POST'] = 'admin/statistik/resolve_issue/$1';
$route['admin/statistik/nilai']['POST'] = 'admin/statistik/save_value';
$route['admin/statistik/nilai/(:num)/status']['POST'] = 'admin/statistik/value_status/$1';

// Pengaturan
$route['admin/pengaturan']['GET'] = 'admin/pengaturan/index';
$route['admin/pengaturan/(:any)']['GET'] = 'admin/pengaturan/section/$1';
$route['admin/pengaturan/(:any)']['POST'] = 'admin/pengaturan/save/$1';

// ---------------------------------------------------------------- Halaman CMS
// Didaftarkan setelah seluruh route literal: satu atau dua segmen slug halaman publik.
// Controller tetap memeriksa halaman itu benar-benar terbit, jika tidak maka 404.
$route['(:any)']['GET'] = 'halaman/show/$1';
$route['(:any)/(:any)']['GET'] = 'halaman/show/$1/$2';

// Semua URI lain: 404 (menonaktifkan auto-routing).
$route['(.+)'] = 'errors/not_found';
