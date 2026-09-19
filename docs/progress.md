# Progres Implementasi

Catatan ini ditulis per tahap berdasarkan pekerjaan dan perintah yang benar-benar dijalankan.

Spesifikasi aktif sejak 18 September 2026: `prompt-master-desa-cihawuk(1).md` (v1.2) beserta dua dokumen pendamping
`modul-frontend-data-desa-cihawuk.md` dan `modul-backend-cms-maintenance-desa-cihawuk.md`. Bagian "Riwayat" di
bawah dikerjakan terhadap spesifikasi versi sebelumnya dan tetap berlaku sebagai kondisi kode aktual.

Angka pada setiap bagian (jumlah permission, jumlah tes) adalah **catatan saat tahap itu
dikerjakan**, bukan keadaan terkini. Keadaan terkini selalu ada pada bagian tahap terakhir.

---

# Bagian A — Tahap 1 terhadap spesifikasi v1.2 (18 September 2026)

Cakupan sesi ini: inspeksi environment, CI3 pada PHP 8.3.33, koneksi database, konfigurasi environment, migration
dan seeder, autentikasi, role dan permission, audit log, penyimpanan file privat/publik, serta layout dasar
SB Admin 2. Frontend lengkap, aset/QR, gudang, struktur organisasi, data desa, dan transparansi keuangan **tidak**
dikerjakan pada tahap ini.

## A.1 Checklist pekerjaan

Status: ☐ belum · ☑ selesai · ◐ sebagian (alasan ditulis pada baris bukti).

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | `docs/progress.md` berisi checklist sebelum implementasi | ☑ | Dokumen ini, ditulis sebelum perubahan kode pertama |
| 2 | Inspeksi environment: PHP CLI/Apache, ekstensi, DB, Composer | ☑ | A.2 di bawah |
| 3 | CI3 pada PHP 8.3.33 + patch kompatibilitas masih terpasang | ☑ | `composer check-platform-reqs` success; `tools health` SEHAT |
| 4 | Koneksi MariaDB XAMPP dan zona waktu UTC | ☑ | `tools health`: `10.4.27-MariaDB / cihawuk_digital`, zona waktu `+00:00` |
| 5 | Konfigurasi environment (`.env`, pemutus darurat modul) | ☑ | `.env.example` + `.env`: `FEATURE_ASSET_MANAGEMENT`, `FEATURE_PUBLIC_ASSET_QR`, `FEATURE_WAREHOUSE`, `FEATURE_ORGANIZATION_TREE` |
| 6 | Migration 004: modul, media, audit | ☑ | `tools migrate` naik ke v4, `tools migrate 3`, lalu `tools migrate` kembali ke v4 |
| 7 | Seeder idempotent untuk permission, role, dan modul | ☑ | `tools seed` dua kali: 61 permission, 19 role, 17 modul; run kedua 0 baris baru |
| 8 | Autentikasi (login, aktivasi, reset, MFA, sesi) tetap lulus | ☑ | 63 tes lama tetap lulus pada run penuh |
| 9 | Role dan permission gabungan master 6 + modul backend 4.2 | ☑ | `application/config/rbac.php`; preset v2 tersinkron ke role lama |
| 10 | Audit log: kolom modul/IP/user agent, `admin_activity_events`, filter UI | ☑ | Migration 004 + `AuditService`; `admin/audit` punya filter modul dan request ID |
| 11 | Feature module service (state, dependency, histori, guard route) | ☑ | `FeatureModuleServiceTest` (5 tes) + `MediaHttpTest::test_disabled_module_blocks_public_and_admin_routes` |
| 12 | Penyimpanan file privat dan publik (original privat, derivative publik) | ☑ | `MediaPipelineTest`, `MediaHttpTest`; bukti manual pada A.3 |
| 13 | Layout dasar SB Admin 2 sesuai kerangka sidebar modul backend 3 | ☑ | Sidebar per permission + status modul; diperiksa sebagai Super Admin dan Editor |
| 14 | Pengujian dijalankan dan hasil nyata dicatat | ☑ | `OK (73 tests, 448 assertions)`; `docs/test-report.md` |
| 15 | Dokumentasi diperbarui mengikuti kode aktual | ☑ | `docs/` (lihat A.4) |

## A.2 Hasil inspeksi environment

Dijalankan 18 September 2026 pada mesin pengembangan:

| Item | Hasil nyata |
|---|---|
| PHP CLI | 8.3.33 (`C:\xampp\php-8.3.33\php.exe`, ZTS VC++ 2019 x64) |
| PHP Apache | 8.3.33 (`Apache/2.4.54 (Win64) OpenSSL/1.1.1p PHP/8.3.33`) |
| Ekstensi | mysqli, mbstring, fileinfo, openssl, gd, intl, zip, sodium, dom, curl **ada**; `exif` tidak ada (tidak dipakai karena metadata dibuang dengan decode ulang) |
| GD | WebP: ya, AVIF: ya sehingga derivative WebP dapat dibuat tanpa dependensi tambahan |
| Database | MariaDB 10.4.27, database `cihawuk_digital`, zona waktu sesi DB `+00:00` |
| Composer | 2.9.5 memakai PHP 8.3.33; `composer check-platform-reqs` seluruhnya `success` |
| Health | `tools health` CLI: `Status: SEHAT`; `tools/health` via HTTP: 404 (CLI-only, sesuai aturan) |
| Akses web | `http://localhost/cihawuk/` 200; `/.env`, `/application/...`, `/storage/...` tetap 403/404 |

## A.3 Perubahan yang benar-benar dibuat

**Migration 004** (`application/migrations/004_create_module_and_media_tables.php`), reversibel:
`feature_modules`, `feature_module_histories`, `admin_activity_events`, `media_derivatives`, `media_usages`;
kolom baru `audit_logs.module_code/ip_hash/user_agent_digest`, `roles.preset_version`, dan
`media_assets.private_file_id/caption/source_year/people_shown/verification_status`.

**RBAC**: 20 menjadi **61 permission** dan 10 menjadi **19 role**. Permission baru mencakup `assets.*`,
`asset_audits.*`, `warehouse.*`, `organization.*` (master 6) serta `cms.page.*`, `cms.menu.manage`, `cms.media.*`,
`data.*`, `finance.*`, `settings.feature.manage`, `settings.security.manage` (modul backend 4.2). Seeder memakai
`roles.preset_version`: preset baru ditambahkan sekali saat versi naik, dan seeder tidak pernah mencabut permission
yang diberikan pengelola.

**Feature module** (`application/libraries/FeatureModuleService.php`): lima state, validasi dependency dua arah,
alasan wajib minimal 10 karakter, histori + audit + peristiwa operasional. Guard di `MY_Controller::_remap`
memeriksa URL langsung: modul nonaktif menghasilkan 404, `internal_only` 404 di area publik, `maintenance` 503.
`.env` dapat memaksa modul nonaktif, tetapi tidak dapat memaksa modul menjadi aktif. Halaman
**Pengaturan > Modul aplikasi** menampilkan status, dependency, form ubah status, dan riwayat perubahan.

Status awal modul: `public_website`, `complaints`, `citizen_accounts`, `news`, `agenda`, `gallery`,
`public_documents`, `village_data`, `village_potentials` = **active**; `organization` = **public_readonly**
(halaman publik sudah ada, pengelolaan tree belum dibangun); `facilities`, `budget_transparency`, `assets`,
`public_asset_qr`, `warehouse`, `umkm_directory`, `village_letters` = **disabled** karena belum dibangun.

**Media**: `UploadService::store_media_asset()` menyimpan berkas asli ke `storage/private` (`private_files`,
purpose `media_original`) dan membuat tiga derivative publik (`public`, `webp` 1600 px, `thumb` 480 px). Teks
alternatif wajib sebelum media dapat terbit. Mengarsipkan media membuang salinan publik dan mempertahankan berkas
asli. `ContentService::sync_media_usages()` mengisi `media_usages` dari rujukan yang benar-benar ada.

Bukti manual (18 September 2026, lewat dashboard di `http://localhost/cihawuk/`):
`media/2026/09/c8a6....png` dan `-thumb.webp` merespons 200; berkas asli
`storage/private/media/2026/09/dd6e...` merespons 404 dari web tetapi ada di disk. Unggah tanpa teks alternatif
ditolak dan tidak menyisakan baris `media_assets` maupun `private_files`.

**Audit**: setiap entri menyimpan kode modul, HMAC IP (bukan IP mentah), dan ringkasan user agent tanpa angka versi.
Halaman audit memiliki filter aksi/entitas/modul/request ID serta menampilkan peristiwa operasional terakhir.

**Dashboard**: sidebar mengikuti kerangka modul backend 3 (Layanan Warga, Website dan CMS, Data Desa, Pemerintahan,
Transparansi Keuangan, Aset dan Persediaan, Pengaturan). Kelompok tanpa permission atau dengan modul nonaktif tidak
dirender sama sekali.

## A.4 Pengujian

`C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit` menghasilkan **OK (73 tests, 448 assertions)** pada PHP 8.3.33
(sebelumnya 63 tes). Tes baru: `FeatureModuleServiceTest` (5), `MediaPipelineTest` (2), `MediaHttpTest` (3).
Rincian per ID ada di `docs/test-report.md`.

## A.5 Catatan dan langkah berikutnya

- **S5 (`ALL ASET FIX 2020-2026 CIHAWUK.xlsx`) tidak ada** di `reference/documents/`. Tahap aset belum dapat
  memulai impor staging sampai berkas disediakan pengguna.
- S3 memuat foto dari desa lain (`Photo PemdesMargamukti\Pangalengan-...`, `PROFIL DESA PULOSARI 2016\...`) dan
  poster kegiatan; pasangan foto-nama wajib diperiksa manual sebelum dipakai. Dicatat pada `docs/data-issues.md`.
- Modul yang masih `disabled` menunggu tahap berikutnya sesuai 29: struktur organisasi, aset/QR, gudang, fasilitas,
  transparansi anggaran, dan UMKM.
- Belum dikerjakan pada tahap ini dan memang di luar cakupan sesi: CMS berbasis section/snapshot, preview token,
  penjadwalan publikasi, rollback, serta cache invalidation terarah.

---

# Bagian A.6 — Tahap 2: CMS halaman dan beranda (19 September 2026)

Cakupan yang dipilih pengguna: halaman publik disusun dari registry section, punya draft berversi, review,
pratinjau, snapshot publikasi, penjadwalan, dan rollback.

## A.6.1 Checklist pekerjaan

| # | Pekerjaan | Status |
|---|---|---|
| 1 | Migration 005: 9 tabel CMS (halaman, versi, section, versi section, snapshot, redirect, review, komentar, jadwal) | Selesai |
| 2 | Registry tertutup 15 jenis section + varian layout + skema field (`application/config/cms_sections.php`) | Selesai |
| 3 | `CmsService`: CRUD halaman/section, validasi per tipe, urutan transaksional, data render | Selesai |
| 4 | `CmsPublicationService`: review, publish + snapshot, jadwal, unpublish, arsip, rollback, token pratinjau | Selesai |
| 5 | Beranda publik dirender dari snapshot (15 partial `site/sections/*`) | Selesai |
| 6 | Seed halaman `home` + 9 section + snapshot revisi 1 (idempotent) | Selesai |
| 7 | Dashboard: Pengaturan Beranda, Halaman Publik, panel alur kerja, drag-and-drop urutan | Selesai |
| 8 | Route CMS + `admin/pratinjau/{token}` + menu sidebar | Selesai |
| 9 | Job `scheduled_publications` pada `tools run_jobs` | Selesai |
| 10 | Tes service + HTTP (13 tes baru) | Selesai |
| 11 | Dokumentasi (`docs/cms.md` baru + architecture, database, roles, user-guide, test-report) | Selesai |

## A.6.2 Perubahan yang benar-benar dibuat

**Basis data.** `005_create_cms_page_tables.php` menambah `cms_pages`, `cms_page_versions`, `cms_sections`,
`cms_section_versions`, `cms_publication_snapshots`, `cms_redirects`, `content_review_requests`,
`content_review_comments`, `scheduled_publications` (total 81 tabel).

**Aturan yang ditegakkan server.** Pengelola tidak dapat menulis HTML/JS/CSS; hanya jenis section dan field pada
registry yang diterima, kunci asing dibuang, tautan `javascript:`/`data:` ditolak, statistik hanya menerima
indikator yang nilainya terbit + terverifikasi pada tahun terpilih, potensi hanya yang terbit + terverifikasi,
hero mode video wajib punya poster, dan section milik modul nonaktif tidak dapat dibuat (409) serta tidak ikut
snapshot.

**Publikasi.** Setiap simpan membuat versi baru; publikasi menghasilkan baris `cms_publication_snapshots` dengan
`revision_no` naik dan snapshot lama ditandai `superseded_at`. Rollback menerbitkan ulang isi snapshot lama
sebagai revisi baru (`rolled_back_from`), tidak menimpa riwayat. Penjadwalan memakai idempotency key dan klaim
baris atomik sehingga job ganda tidak menerbitkan dua kali.

**Pratinjau.** Token HMAC berumur 15 menit terikat halaman + pengguna; halaman pratinjau perlu login dan
`cms.page.view`, memakai `no-store` dan `X-Robots-Tag: noindex`. Token pengguna lain/rusak/kedaluwarsa
menghasilkan 410.

**Beranda.** Susunan hasil seed identik dengan beranda sebelum refactor (hero, akses cepat, profil singkat,
statistik, potensi, berita, agenda, peta, ajakan layanan), sehingga tampilan publik tidak berubah tetapi kini
dapat diatur dari dashboard.

## A.6.3 Perintah yang dijalankan

```
C:\xampp\php-8.3.33\php.exe index.php tools migrate          # -> versi 5
C:\xampp\php-8.3.33\php.exe index.php tools migrate 4        # -> versi 4 (reversibel)
C:\xampp\php-8.3.33\php.exe index.php tools migrate          # -> versi 5
C:\xampp\php-8.3.33\php.exe index.php tools seed             # dua kali; kedua kali 0 baris CMS baru
C:\xampp\php-8.3.33\php.exe index.php tools run_jobs scheduled_publications   # dua kali; tidak terbit ganda
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit
```

## A.6.4 Pengujian

`vendor/bin/phpunit` menghasilkan **OK (86 tests, 524 assertions)** (sebelumnya 73). Tes baru:
`CmsPublicationTest` (8) dan `CmsHttpTest` (5). ID CMS-02…CMS-11 dan MIG-02 ada di `docs/test-report.md`.

Verifikasi manual yang dijalankan: beranda publik 200 dengan 9 section; menonaktifkan section sebagai editor
tidak mengubah halaman publik sampai penerbit menekan terbitkan; editor ditolak 403 saat mencoba menerbitkan;
penerbit ditolak 403 saat mencoba menyunting draft; rollback mengembalikan susunan lama; tanpa scroll horizontal
pada 390 px dan 1440 px; `/cihawuk/.env`, `/cihawuk/application/...`, `/cihawuk/storage/...` tetap 403/404.

## A.6.5 Yang belum ada

- Halaman CMS selain beranda belum punya route publik sendiri; membuat halaman baru menyiapkan datanya, tetapi
  belum menghasilkan URL yang dapat dibuka pengunjung.
- Sitemap dan pencarian belum memuat halaman CMS.
- Editor menu/navigasi dan tema/token warna dari dashboard belum dibuat.
- Penguncian antar-editor (dua orang menyunting section yang sama bersamaan) belum ada.
- Modul yang masih `disabled` tetap sama seperti Tahap 1: struktur organisasi, aset/QR, gudang, fasilitas,
  transparansi anggaran, UMKM.

---

# Bagian A.7 — Tahap 3: halaman publik, menu, identitas dan tema (19 September 2026)

Cakupan yang dipilih pengguna: melanjutkan urutan modul-backend tahap 5 — route publik untuk halaman CMS selain
beranda, editor menu/navigasi, identitas situs, token tema, serta invalidasi cache terarah dengan sitemap dan
pencarian yang ikut memuat halaman CMS.

**Di luar cakupan tahap ini:** profil/sejarah/pemerintahan dinamis, dataset dan statistik publik, potensi,
fasilitas, peta, transparansi anggaran, aset/QR, dan gudang. Modulnya tetap `disabled`.

## A.7.1 Checklist pekerjaan

| # | Pekerjaan | Status |
|---|---|---|
| 1 | Migration 006: tabel `cms_menus` dan `cms_menu_items` | Selesai |
| 2 | Permission `cms.site.manage` (preset v3, aditif) untuk identitas, tema, dan cache publik | Selesai |
| 3 | `PublicCache`: invalidasi terarah per halaman/menu/situs, bukan flush semua | Selesai |
| 4 | `CmsMenuService`: item menu, validasi kedalaman/URL/cycle, snapshot publikasi menu | Selesai |
| 5 | `SiteSettingsService`: draft + snapshot identitas situs dan token tema, validasi warna/kontras/font | Selesai |
| 6 | Slug terlarang diturunkan dari daftar route | Selesai |
| 7 | Route dan controller publik halaman CMS + redirect 301 dari slug lama | Selesai |
| 8 | Template publik `site/page.php` memakai section yang sama dengan beranda | Selesai |
| 9 | Dashboard: pengelola Menu dan pengelola Identitas & Tema beserta alur terbit/rollback | Selesai |
| 10 | Header dan footer publik membaca snapshot menu; layout menyuntik token tema dan identitas | Selesai |
| 11 | Sitemap dan pencarian memuat halaman CMS terbit yang `search_indexable` | Selesai |
| 12 | Seed menu, identitas, dan tema (idempotent) | Selesai |
| 13 | Tes service + HTTP (16 tes baru) | Selesai |
| 14 | Dokumentasi | Selesai |

## A.7.2 Perubahan yang benar-benar dibuat

**Basis data.** Migration 006 menambah `cms_menus` dan `cms_menu_items` (total 83 tabel). Snapshot menu dan
snapshot identitas/tema memakai `cms_publication_snapshots` yang sudah ada dengan `target_type` `menu` dan `site`.

**Halaman publik.** Halaman CMS terbit kini punya alamat nyata (`/{slug}` atau `/{induk}/{anak}`) lewat dua route
wildcard yang didaftarkan setelah seluruh route literal. Halaman draft/ditarik/arsip tetap 404, slug lama yang
pernah terbit dialihkan 301, dan halaman `noindex` tidak masuk sitemap maupun pencarian. Daftar slug terlarang
diturunkan otomatis dari `routes.php` sehingga route baru langsung terlindungi.

**Menu.** Lima lokasi menu dengan draft dan snapshot sendiri. Server menolak kedalaman ketiga, cycle induk,
`javascript:`/`data:`, path dashboard/berkas privat, dan halaman CMS yang belum terbit. Item nonaktif serta item
yang menunjuk halaman yang sudah ditarik dilewati saat publikasi tetapi tetap tersimpan sebagai draft.

**Identitas dan tema.** Satu draft berisi identitas situs dan token tema, diterbitkan sebagai snapshot. Warna
wajib heksadesimal dan lolos kontras minimum (4,5:1 terhadap teks), font hanya dari yang terpasang, preset sudut
dan mode hero dari daftar tertutup. Token dirender sebagai custom property dan disaring ulang di view sehingga
tidak dapat keluar dari blok `<style>`.

**Cache.** `PublicCache` menggantikan penghapusan seluruh direktori cache. Publikasi halaman hanya membuang cache
halaman itu beserta daftar turunan; publikasi menu hanya membuang cache menu; menyimpan draft tidak menyentuh
cache sama sekali.

**Perbaikan yang ditemukan saat verifikasi manual:** slug kosong pada form tidak lagi menghasilkan slug acak
(diambil dari judul), halaman non-beranda kini punya tombol aktif/nonaktif, urut, dan arsip section, daftar item
menu ditampilkan bertingkat, tautan kembali pada form section mengikuti halamannya, field wajib pada registry
tidak lagi berlabel "(opsional)", dan blok teks-gambar tanpa gambar tidak lagi menyisakan bingkai kosong.

## A.7.3 Perintah yang dijalankan

```
C:\xampp\php-8.3.33\php.exe public/index.php tools migrate          # -> versi 6
C:\xampp\php-8.3.33\php.exe public/index.php tools migrate 5        # -> versi 5 (reversibel)
C:\xampp\php-8.3.33\php.exe public/index.php tools migrate          # -> versi 6
C:\xampp\php-8.3.33\php.exe public/index.php tools seed             # dua kali; kedua kali 0 baris baru
C:\xampp\php-8.3.33\php.exe public/index.php tools seed_demo        # menambah akun webadmin.demo
C:\xampp\php-8.3.33\php.exe public/index.php tools run_jobs
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit
```

## A.7.4 Pengujian

`vendor/bin/phpunit` menghasilkan **OK (102 tests, 626 assertions)** (sebelumnya 86). Tes baru:
`CmsNavigationTest` (10) dan `CmsNavigationHttpTest` (6). ID NAV-01…NAV-05, MENU-01…MENU-04, SITE-01…SITE-03,
CACHE-01, dan MIG-03 ada di `docs/test-report.md`.

Verifikasi manual di `localhost/cihawuk`: membuat halaman "Informasi Layanan Desa" lewat dashboard sebagai
Admin Website, mengajukan review, lalu menyetujui dan menerbitkannya sebagai Penerbit Konten — halaman langsung
tampil di `/cihawuk/informasi-layanan`, masuk sitemap dan hasil pencarian, dan sebelum diterbitkan alamatnya 404.
Penerbit Konten ditolak 403 saat membuka Identitas dan Tema. Tidak ada scroll horizontal pada 375 px. Beranda dan
menu tetap utuh setelah siklus migrate turun-naik. `/cihawuk/.env` 403; `application/`, `storage/`, `database/`
404.

## A.7.5 Yang belum ada

- Menu, identitas, dan tema belum punya alur review (`in_review`/`approved`) seperti halaman; pemisahannya baru
  pada permission menyusun vs menerbitkan.
- Belum ada penjadwalan publikasi untuk menu dan identitas.
- Belum ada pemeriksa tautan rusak untuk item menu yang menunjuk path yang sudah hilang.
- Lokasi menu `mobile`, `footer_secondary`, dan `quick_link` sudah dapat dikelola, tetapi situs publik baru
  memakai `header` dan `footer_primary`.
- Modul yang masih `disabled` tetap sama: struktur organisasi, aset/QR, gudang, fasilitas, transparansi anggaran,
  UMKM.

---

# Bagian A.8 — Tahap 4: Data Desa dan statistik publik (19 September 2026)

Cakupan yang dipilih pengguna: dataset berversi, impor dokumen sumber ke staging, review konflik, verifikasi
nilai per tahun, dan halaman Data Desa publik dengan grafik — termasuk menghidupkan section statistik beranda.

**Di luar cakupan tahap ini:** profil/sejarah/pemerintahan dinamis, potensi dan fasilitas, peta, transparansi
anggaran, aset/QR, dan gudang. Modulnya tetap seperti sekarang.

## A.8.1 Checklist pekerjaan

Status ditulis setelah perintahnya benar-benar dijalankan, bukan saat kodenya ditulis.

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 007: `datasets`, `dataset_versions`, `dataset_series`, kolom `dataset_version_id` dan `suppressed` pada `statistic_values` | Selesai | `tools migrate 6` lalu `tools migrate` kembali ke versi 7; `tools health` SEHAT |
| 2 | Registry tema dataset (9 tema) dan aturan visualisasi yang diizinkan | Selesai | `application/config/datasets.php` |
| 3 | `DatasetService`: versi, metadata, seri indikator | Selesai | `DatasetService.php`; `DatasetTest` (10 tes) |
| 4 | Validasi sebelum terbit: satuan, tahun, total vs komponen, pie/donut, ambang data kecil | Selesai | `validate_version()`; DATA-02…DATA-06 |
| 5 | Verifikasi nilai (`data.review`) terpisah dari penerbitan (`data.publish`) | Selesai | `DatasetHttpTest::test_review_and_publish_permissions_are_separate`: verifikator 403 saat terbit, penerbit 403 saat verifikasi |
| 6 | Snapshot publikasi dataset + tarik + rollback | Selesai | `DatasetTest::test_unpublish_and_rollback_keep_history` |
| 7 | Impor staging: idempotensi, checksum, deteksi duplikasi | Selesai | `DatasetTest::test_import_staging_is_idempotent` kini benar-benar mengimpor S1 dua kali (sebelumnya selalu di-skip) |
| 8 | Dashboard: daftar dataset, form metadata, seri, panel validasi, alur terbit | Selesai | `admin/Dataset.php` + `dataset_index.php`/`dataset_form.php`; dijalankan lewat HTTP pada verifikasi manual |
| 9 | Halaman Data Desa publik: filter tahun/tema/indikator/sumber, grafik + tabel setara + sumber + waktu terbit | Selesai | Filter indikator dan route `/data-desa/{tema}` ditambahkan tahap ini |
| 10 | Unduh CSV per dataset dengan pengaman formula injection | Selesai | `DatasetHttpTest::test_csv_download_is_utf8_bom_and_formula_safe` |
| 11 | Section statistik beranda memakai dataset terbit | Selesai | Registry section diganti ke `dataset_slug` + `series`; `CmsService::section_data()` membaca snapshot dataset |
| 12 | Seed dataset (idempotent, status draft) | Selesai | `tools seed` dua kali: run pertama 1 dataset + 1 versi + 4 seri, run kedua 0 baris baru |
| 13 | Tes service + HTTP | Selesai | `DatasetHttpTest` (7 tes) baru; seluruh suite `OK (119 tests, 713 assertions)` |
| 14 | Dokumentasi | Selesai | `docs/data-desa.md` dan pembaruan dokumen lain |

## A.8.2 Perbaikan yang ditemukan saat menjalankan

Kode Tahap 4 sudah ada di disk dari sesi sebelumnya tetapi **belum pernah dijalankan**.
Menjalankan `phpunit` untuk pertama kalinya menemukan tiga kegagalan nyata:

- `build_snapshot()` selalu mengecor nilai ke `float`, sehingga jumlah penduduk tampil
  sebagai `6809.0` pada kartu, tabel, dan CSV. Sekarang dicor mengikuti
  `statistic_indicators.value_type`, dan `value_type` ikut masuk snapshot.
- `DatasetTest` bocor antar-tes: tes yang mengosongkan `numeric_value` tidak
  mengembalikannya, sehingga dua tes sesudahnya gagal terbit. `clean()` kini memulihkan
  angka dari `source_observations.normalized_value`.
- Tes idempotensi impor selalu `markTestSkipped` karena mencari `import_status = 'imported'`
  yang tidak pernah ada di DB pengujian. Sekarang tes mengimpor dokumen `.docx` pertama yang
  berkasnya tersedia dua kali dan membandingkan jumlah observasinya.

Tiga hal lain ditemukan saat menyambungkan beranda ke dataset:

- `published_datasets()` belum menyaring `sensitivity = 'restricted'`. Dataset yang
  sensitivitasnya dinaikkan setelah terbit tetap terbaca publik. Sudah disaring.
- Publikasi dataset tidak membuang cache halaman yang memasangnya, sehingga beranda bisa
  menampilkan angka basi sampai cache kedaluwarsa. `DatasetService::invalidate_pages_using()`
  kini membuang cache halaman yang snapshotnya menyebut slug dataset itu — halaman lain
  tidak disentuh.
- Modul `village_data` hanya menjaga prefix `admin/statistik`, sehingga `/admin/dataset`
  tetap terbuka saat modul dinonaktifkan. Prefix diperbaiki menjadi
  `admin/statistik,admin/dataset`, dan seeder kini menyinkronkan ulang prefix route serta
  dependency modul untuk baris yang sudah ada (`state` tidak pernah ditimpa).

## A.8.3 Perintah yang dijalankan

```
C:\xampp\php-8.3.33\php.exe public/index.php tools migrate 6      # -> versi 6
C:\xampp\php-8.3.33\php.exe public/index.php tools migrate        # -> versi 7
C:\xampp\php-8.3.33\php.exe public/index.php tools health         # Status: SEHAT
C:\xampp\php-8.3.33\php.exe public/index.php tools seed           # dua kali; run kedua 0 baris baru
C:\xampp\php-8.3.33\php.exe public/index.php tools seed_demo      # menambah akun verifikator.demo
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit                    # OK (119 tests, 713 assertions)
```

Bootstrap pengujian menjalankan `tools migrate 0` → `tools migrate` → `tools seed` pada DB
pengujian setiap kali suite dijalankan, sehingga reversibilitas seluruh migration dari nol
ikut terbukti pada setiap run.

## A.8.4 Verifikasi manual (19 September 2026, `http://localhost/cihawuk/`)

Dijalankan sebagai akun demo lewat HTTP, bukan lewat service:

- `verifikator.demo` (`data_verifier`) memverifikasi empat nilai 2023 dan menjalankan
  **Periksa** → lolos; mencoba **Terbitkan** → **403**.
- `penerbit.demo` (`content_publisher`) menerbitkan dataset → 303, `/data-desa` menampilkan
  grafik `<canvas>` beserta tabel angka setara, metodologi, sumber, dan tombol unduh.
- `/data-desa/population` → 200; `/data-desa/tema-ngawur` → **404**.
- CSV diawali BOM UTF-8 dan berisi `6809` (bukan `6809.0`).
- Beranda tetap menampilkan keadaan kosong sampai `penerbit.demo` menerbitkan ulang beranda;
  sesudah itu kartu berisi 6.809 dan 3.510 dengan label tahun dan sumber S1.

## A.8.5 Yang belum ada

- Perbandingan antartahun pada satu grafik belum ada; satu versi dataset mewakili satu periode.
- Tema dataset selain `population` belum punya dataset seed; datanya masih berupa observasi
  staging yang perlu diverifikasi pengelola.
- Halaman Data Desa belum punya alamat per dataset (`/data-desa/{tema}` sudah ada, detail per
  dataset masih berupa anchor di halaman daftar).

---

# Bagian A.9 — Tahap 5: Profil desa dinamis (19 September 2026)

Cakupan: profil desa berhenti menjadi satu baris berisi HTML dan menjadi blok terstruktur
berversi dengan periode, sumber, verifikasi, dan snapshot publikasi.

## A.9.1 Checklist

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 008: `profile_blocks`, `profile_block_versions`, `leadership_terms` | Selesai | `tools migrate 7` lalu `tools migrate` kembali ke versi 8 |
| 2 | Registry tertutup 7 blok + tipe field (`application/config/profile_blocks.php`) | Selesai | identity, greeting, history, vision, mission, geography, contact |
| 3 | `ProfileService`: versi, validasi, verifikasi, snapshot, rollback | Selesai | `ProfileTest` (10 tes) |
| 4 | Halaman publik `/profil`, `/profil/sejarah`, `/profil/visi-misi`, `/profil/geografi` | Selesai | `ProfileHttpTest` (5 tes) |
| 5 | Dashboard: daftar blok, form per registry, linimasa, panel pemeriksaan, alur terbit | Selesai | `admin/Profil.php` + 2 view; dijalankan lewat HTTP |
| 6 | Seed draft dari S1/S3 (idempotent) | Selesai | 7 blok, 3 berisi, 9 periode kepemimpinan; run kedua 0 baris baru |
| 7 | Tes + dokumentasi | Selesai | Suite `OK (134 tests, 781 assertions)` |

## A.9.2 Aturan yang ditegakkan server

- Pengelola tidak menulis HTML. Hanya field pada registry yang diterima; kunci asing dibuang
  (`ProfileTest::test_unknown_fields_are_dropped`).
- **Konflik luas wilayah tidak dapat disembunyikan.** Bila jumlah komposisi penggunaan lahan
  berbeda dari observasi `area_total_ha` mana pun, catatan konflik menjadi wajib. Halaman
  geografi menampilkan jumlah komposisinya sendiri dan catatan konflik, tanpa pernah
  menyebut satu angka sebagai luas resmi.
- **"Sampai sekarang" bukan jabatan aktif.** `ongoing_claim` menandai klaim dokumen dan tidak
  boleh dipasangkan dengan tahun selesai. Halaman sejarah menulis rentangnya sebagai
  "2019–tahun dokumen", bukan tahun berjalan.
- Nama yang muncul pada lebih dari satu periode menghasilkan peringatan, **tidak** digabung
  otomatis. Dua periode Aep Saepuloh tetap terpisah pada halaman publik.
- Visi kecamatan yang disalin sama persis dengan visi desa ditolak.
- Menyimpan blok membuat versi baru dan **mencabut verifikasi sebelumnya**, karena isinya
  berubah. Blok tanpa isi tidak dapat diajukan maupun diverifikasi (409).
- Sambutan wajib punya tahun periode sebelum terbit, supaya sambutan 2023 tidak tampil
  seolah sambutan pejabat 2026.

## A.9.3 Verifikasi manual (`http://localhost/cihawuk/`)

Sebagai `penerbit.demo`: mengajukan dan memverifikasi 3 blok berisi (identitas, sejarah,
geografi) → 303; 4 blok kosong (sambutan, visi, misi, kontak) ditolak **409** karena belum ada
isinya; 9 periode kepemimpinan diverifikasi; profil diterbitkan. Sesudahnya `/profil`
menampilkan kode PUM `320431.2006`, `/profil/sejarah` menampilkan Yaya Dores dengan catatan
klaim sumber, `/profil/geografi` menampilkan 932,35 ha beserta peringatan konflik luas, dan
`/profil/visi-misi` tetap menyatakan belum diterbitkan karena naskahnya memang tidak ada pada
dokumen sumber.

## A.9.4 Yang belum ada

- Blok sambutan, visi, misi, dan kontak belum punya isi: naskahnya tidak tersedia pada dokumen
  sumber dan tidak boleh dikarang. Pengelola mengisinya lewat dashboard.
- Foto pejabat belum dipasangkan ke periode kepemimpinan; S3 memuat foto desa lain sehingga
  pemasangan harus manual (lihat `docs/data-issues.md`).
- Belum ada penjadwalan publikasi profil dan belum ada alur review terpisah
  (`in_review` → `approved`) seperti halaman CMS.

---

# Bagian A.10 — Tahap 6: Potensi, fasilitas, lokasi dan peta (19 September 2026)

Cakupan: direktori fasilitas yang benar-benar punya identitas, tabel lokasi publik terpisah,
dan pelengkapan field potensi menurut modul-backend 12.1.

## A.10.1 Checklist

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 009: `places`, `facilities`, `facility_services` + 4 kolom baru `potentials` | Selesai | `tools migrate 8` lalu `tools migrate` kembali ke versi 9 |
| 2 | Permission `facilities.edit` dan `facilities.publish` (preset v4, aditif) | Selesai | `tools seed`: 2 permission baru, 0 role ditimpa |
| 3 | `FacilityService`: lokasi, fasilitas, layanan, verifikasi, terbit, tarik, arsip | Selesai | `FacilityTest` (11 tes) |
| 4 | Halaman publik `/fasilitas` dan `/fasilitas/{slug}` dengan peta Leaflet | Selesai | `FacilityHttpTest` (6 tes) |
| 5 | Dashboard: daftar fasilitas, form, layanan, lokasi, alur terbit | Selesai | `admin/Fasilitas.php` + 2 view |
| 6 | Field potensi 12.1 (status akses, pengelola, keselamatan, lokasi) | Selesai | `ContentService` registry potensi + `config/app.php` |
| 7 | Modul `facilities` diaktifkan | Selesai | Lewat Pengaturan > Modul aplikasi sebagai `admin.demo`; state `disabled` → `active` |
| 8 | Tes + dokumentasi | Selesai | Suite `OK (151 tests, 841 assertions)` |

## A.10.2 Aturan yang ditegakkan server

- **Angka agregat bukan fasilitas.** Nama yang terbaca sebagai hitungan (`4 SD`) ditolak
  dengan pesan yang mengarahkan angkanya ke Data Desa. Tidak ada importer yang membuat
  fasilitas dari angka agregat, dan direktori sengaja dibiarkan kosong sampai pengelola
  memasukkan objek nyata.
- Entri wajib punya alamat atau lokasi terdaftar; tanpa keduanya ditolak.
- Kontak publik hanya tersimpan bila izinnya dicatat, dan berhenti tampil begitu izinnya
  dicabut.
- **Koordinat tidak mudah bocor.** Titik hanya masuk peta publik bila lokasinya terverifikasi
  DAN tidak ditandai sensitif. Keduanya diuji lewat HTML publik, bukan hanya lewat service.
- Menyimpan fasilitas atau lokasi mencabut verifikasi sebelumnya, karena isinya berubah.
- Penerbitan diblokir selama masih ada penghalang: belum diverifikasi, tanpa alamat/lokasi,
  tanpa tahun data, tanpa sumber, atau kontak tanpa izin.
- Menarik dan mengarsipkan tidak menghapus barisnya.
- Potensi hanya dapat dijadikan unggulan beranda bila sudah terbit, terverifikasi, **dan**
  punya foto sampul.

## A.10.3 Yang belum ada

- Belum ada satu pun fasilitas dan lokasi nyata: dokumen sumber hanya memuat jumlah
  (1 TK, 4 SD, 10 sumur gali, dan seterusnya), bukan nama, alamat, dan pengelolanya.
  Angka-angka itu tetap menjadi bahan Data Desa.
- Peta publik baru memakai titik; polygon batas wilayah belum ada karena peta pada dokumen
  sumber adalah gambar hasil pindai, bukan GeoJSON.
- Field potensi baru sudah tersedia di form, tetapi isinya masih kosong sampai pengelola
  memverifikasi nama tempat, akses, dan catatan keselamatan.

---

# Bagian A.11 — Tahap 7: Struktur organisasi dinamis (19 September 2026)

Cakupan: periode, unit, jabatan, orang, dan penugasan sebagai entitas terpisah, dengan
snapshot publikasi per periode dan tree publik yang tetap terbaca tanpa JavaScript.

## A.11.1 Checklist

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 010: `org_periods`, `org_units`, `org_positions`, `people`, `org_assignments` | Selesai | `tools migrate 9` lalu `tools migrate` kembali ke versi 10 |
| 2 | `OrganizationService`: validasi struktur, penugasan, snapshot, salin periode | Selesai | `OrganizationTest` (11 tes) |
| 3 | Permission `organization.edit` (Admin Website) dan `organization.publish` (Penerbit Konten), preset v5 | Selesai | Sebelumnya kedua permission ada tetapi tidak dipegang role mana pun |
| 4 | Dashboard: periode, unit, jabatan, orang, penugasan, pemeriksaan, alur terbit | Selesai | `admin/Struktur.php` + 2 view |
| 5 | Halaman publik `/pemerintahan/struktur` dengan pemilih periode | Selesai | `OrganizationHttpTest` (5 tes) |
| 6 | Seed periode dokumen 2023 (idempotent, draft) | Selesai | 1 periode, 1 unit, 2 jabatan, 1 orang, 1 penugasan |
| 7 | Modul `organization` dari `public_readonly` menjadi `active` | Selesai | Lewat Pengaturan > Modul aplikasi |
| 8 | Tes + dokumentasi | Selesai | Suite `OK (167 tests, 882 assertions)` |

## A.11.2 Aturan yang ditegakkan server

- Self-parent, cycle induk-atasan, atasan lintas periode, dan kedalaman lebih dari 5 tingkat
  ditolak. Penelusuran cycle memakai rantai induk, bukan sekadar membandingkan satu tingkat.
- Satu jabatan hanya boleh punya **satu penugasan aktif**. Penugasan lama harus diakhiri
  lebih dulu, dan mengakhiri tidak menghapus barisnya.
- Penugasan bertipe `vacant` tidak boleh punya orang; `definitive`/`acting` wajib punya orang.
- Menonaktifkan jabatan yang masih punya bawahan aktif ditolak. Jabatan nonaktif tidak ikut
  snapshot, tetapi barisnya tetap ada.
- **Foto tanpa izin tidak tersimpan.** Menyimpan orang dengan foto tetapi tanpa catatan izin
  publikasi ditolak, dan snapshot hanya memuat foto yang izinnya tercatat.
- **Nomor SK tidak pernah publik.** Disimpan terenkripsi pada `org_assignments` dan tidak ikut
  ke snapshot; diuji dengan mencari string SK pada HTML publik.
- Menyalin struktur antar periode menyalin unit dan jabatan saja. Penugasan sengaja tidak
  ikut karena masa jabatannya sudah berakhir.
- Hanya satu periode yang menjadi tampilan publik bawaan; menerbitkan periode baru mencabut
  status bawaan periode sebelumnya.
- `people` terpisah dari `users`: menutup akun tidak menghapus profil pejabat.

## A.11.3 Catatan implementasi visualisasi

Spesifikasi menyebut d3-org-chart. Pustaka itu **tidak** ada di `public/assets/vendor/` dan
CSP situs melarang skrip dari CDN, jadi tree dirender sebagai daftar hierarkis semantik
(`<ul>`/`<li>`) yang lengkap di HTML. `public/assets/site/js/org-tree.js` hanya menambahkan
lipat/buka dan pencarian di atas markup itu. Hasilnya: halaman tetap utuh tanpa JavaScript,
dapat dicetak, dan terbaca pembaca layar — persis fallback yang diwajibkan spesifikasi.
Bila nanti d3-org-chart dipaketkan, ia dapat menggantikan lapisan visualnya tanpa mengubah
data maupun markup dasar.

## A.11.4 Yang belum ada

- Drag-and-drop penyusunan node belum ada; urutan diatur lewat field urutan.
- Zoom dan fit-screen belum ada (pohon memakai scroll horizontal biasa).
- Halaman `/pemerintahan` lama masih membaca tabel `officials` hasil tahap sebelumnya;
  penggabungannya ke struktur dinamis belum dikerjakan.
- Seed hanya memuat Kepala Desa dan Sekretaris Desa karena hanya itu yang tertulis jelas
  pada dokumen sumber. Foto pejabat belum dipasangkan sama sekali.

---

# Bagian A.12 — Tahap 8: Transparansi anggaran (19 September 2026)

Cakupan: APBDes sebagai tahun anggaran dengan revisi murni/perubahan/realisasi terpisah,
alur draft → rekonsiliasi → verifikasi → persetujuan → snapshot publik, dan halaman publik
bertingkat beserta unduhan CSV/JSON.

## A.12.1 Checklist

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 011: 7 tabel anggaran | Selesai | `tools migrate 10` lalu `tools migrate` kembali ke versi 11 |
| 2 | `BudgetService`: revisi, kategori bertingkat, angka, validasi, snapshot | Selesai | `BudgetTest` (12 tes) |
| 3 | Alur kerja dengan pemisahan izin `finance.manage` / `finance.verify` / `finance.publish` | Selesai | `BudgetHttpTest::test_permissions_are_separate_across_the_workflow` |
| 4 | Halaman publik `/transparansi/anggaran` bertingkat + unduh CSV dan JSON | Selesai | `BudgetHttpTest` (4 tes) |
| 5 | Section ringkasan anggaran beranda membaca snapshot | Selesai | `CmsService::section_data('budget_summary')` + view baru |
| 6 | Dashboard: tahun, revisi, kategori, angka, dokumen, panel pemeriksaan | Selesai | `admin/Keuangan.php` + 2 view |
| 7 | Permission `finance.publish` dipegang Penerbit Konten (preset v6) | Selesai | Sebelumnya tidak dipegang role mana pun |
| 8 | Modul `budget_transparency` diaktifkan | Selesai | Lewat Pengaturan > Modul aplikasi |
| 9 | Tes + dokumentasi | Selesai | Suite `OK (183 tests, 932 assertions)` |

## A.12.2 Aturan yang ditegakkan server

- **Murni, perubahan, dan realisasi tidak pernah tercampur.** Ketiganya adalah revisi
  terpisah dengan kunci unik per tahun; snapshot menyimpannya sebagai tiga blok berbeda.
- Jumlah komponen yang tidak sama dengan nilai induknya menghalangi penerbitan sampai ada
  catatan selisih. Yang dijumlahkan hanya baris terdalam, sehingga induk tidak dihitung dua kali.
- **Realisasi melebihi anggaran wajib punya penjelasan.** Aturan ini berlaku untuk seluruh
  kelompok, termasuk penerimaan pembiayaan yang tidak dianggarkan.
- Surplus/defisit harus tertutup pembiayaan neto (toleransi 0,005 rupiah), kalau tidak
  penerbitan ditolak.
- Tahun dokumen yang lebih tua dari tahun anggaran memunculkan peringatan agar tahun judul,
  tahun anggaran, dan tahun dokumen tidak tertukar.
- Dokumen yang punya salinan publik tetapi belum ditandai disamarkan menghalangi penerbitan,
  dan hanya dokumen yang sudah disamarkan yang masuk snapshot.
- Mengubah satu angka pada tahun yang sudah diverifikasi **mengembalikan statusnya ke
  rekonsiliasi**; persetujuan dan verifikasi lama gugur.
- Periode terkunci tidak dapat disunting langsung; kuncinya harus dibuka lebih dulu.
- Urutan alur ditegakkan: menyetujui sebelum verifikasi ditolak 409, menerbitkan sebelum
  persetujuan ditolak 409.
- Nilai negatif ditolak; arah uang ditentukan kelompok anggaran, bukan tanda minus.
- Halaman publik dan section beranda hanya membaca snapshot; menarik snapshot langsung
  mengosongkan keduanya dan membuat unduhan JSON 404.

## A.12.3 Yang belum ada

- **Belum ada satu pun angka APBDes nyata.** Dokumen sumber tidak memuat rincian anggaran
  yang dapat diverifikasi, dan mengarang angka anggaran dilarang spesifikasi. Halaman publik
  menampilkan keadaan kosong sampai pengelola keuangan mengisinya.
- Progres fisik kegiatan sudah punya tabel dan tampil di halaman publik, tetapi form
  pengisiannya di dashboard belum dibuat.
- Unggahan dokumen anggaran masih dicatat lewat id dokumen publik yang sudah ada; alur
  unggah berkas khusus keuangan belum dibuat.
- Ekspor PDF ringkasan anggaran belum ada (CSV dan JSON sudah).
- Akun demo baru: `keuangan.demo` dan `auditkeuangan.demo`.

---

# Bagian A.13 — Tahap 9 dan 10: UMKM, aset, QR, dan audit fisik (19 September 2026)

## A.13.1 Tahap 9 — Direktori UMKM (migration 012)

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 012: tabel `businesses` | Selesai | `tools migrate 11` lalu `tools migrate` kembali ke versi 12 |
| 2 | Permission `umkm.edit` dan `umkm.publish` (preset v7) | Selesai | `tools seed`: 2 permission baru |
| 3 | `BusinessService` + dashboard + halaman publik `/umkm` | Selesai | `BusinessTest` (7 tes), `BusinessHttpTest` (3 tes) |
| 4 | Modul `umkm_directory` diaktifkan | Selesai | Lewat Pengaturan > Modul aplikasi |

Aturan yang ditegakkan: kontak usaha tidak tersimpan tanpa persetujuan pemilik; persetujuan
wajib disertai catatan yang dapat ditelusuri; mencabut persetujuan langsung menurunkan
profilnya dari direktori publik; usaha nonaktif dan arsip tidak tampil, tetapi barisnya tidak
dihapus. **Tidak ada satu pun profil UMKM yang diseed** — dokumen sumber hanya memuat kategori
ekonomi agregat, bukan daftar usaha.

## A.13.2 Tahap 10 — Aset, unit fisik, QR, audit (migration 013)

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 013: 18 tabel aset (19.6) | Selesai | `tools migrate 12` lalu `tools migrate` kembali ke versi 13 |
| 2 | `AssetService`: register, unit, status, mutasi, peminjaman, pemeliharaan, QR, label | Selesai | `AssetTest` (11 tes) |
| 3 | `AssetAuditService`: sesi, target beku, temuan, verifikasi, koreksi, penutupan, addendum | Selesai | `AssetAuditTest` (6 tes) |
| 4 | `AssetImportService` + `tools import_assets` | Selesai | `AssetImportTest` (6 tes); CLI dijalankan dua kali, run kedua 5 baris dilewati |
| 5 | Halaman QR publik `/aset/q/{token}` | Selesai | `AssetHttpTest` (5 tes) |
| 6 | Dashboard aset dan audit aset | Selesai | `admin/Aset.php`, `admin/Audit_aset.php` + 5 view |
| 7 | Modul `assets` dan `public_asset_qr` diaktifkan | Selesai | Lewat Pengaturan > Modul aplikasi |
| 8 | Tes + dokumentasi | Selesai | Suite `OK (221 tests, 1068 assertions)` |

### Aturan yang ditegakkan server

- **Dua tingkat data dipertahankan.** Register menyimpan identitas administrasi; unit fisik
  dibuat terpisah. Volume sumber seperti `2 Unit` atau `I Set` disimpan apa adanya dan tidak
  pernah dipecah otomatis menjadi unit.
- Pemecahan unit adalah usulan yang **wajib beralasan minimal 10 karakter**.
- Setiap perubahan status menulis `asset_status_events` dan memperbarui master dalam satu
  transaksi; alasan wajib. Menonaktifkan tidak menghapus unit maupun historinya.
- **Token QR hanya ada sekali.** Yang disimpan hanya digest SHA-256; token asli tidak masuk
  basis data maupun audit log (diuji dengan mencari token di `audit_logs`). Menerbitkan token
  baru otomatis mencabut yang lama.
- **Halaman QR publik tidak membocorkan apa pun yang sensitif**: nomor seri (terenkripsi),
  nilai perolehan, nama penanggung jawab, dan lokasi bertanda sensitif tidak pernah keluar.
  Diuji langsung terhadap HTML publik, bukan hanya lewat service.
- Setiap keadaan punya halamannya sendiri: aktif, pemeliharaan, tidak aktif, dipindahtangankan,
  dihapuskan, hilang, token dicabut, dan QR tidak dikenal. Tidak ada 500 maupun halaman kosong.
- Unit yang masih `draft` tidak diakui publik meski tokennya sah.
- Halaman QR `noindex`, di luar sitemap, dan dibatasi rate limit `asset_qr_ip` yang tetap
  longgar untuk audit massal petugas.
- Mengajukan mutasi tidak mengubah lokasi; lokasi berubah setelah serah terima diterima.
- Nama peminjam disimpan terenkripsi; satu unit tidak boleh punya dua peminjaman aktif.
- **Menutup pemeliharaan tidak otomatis membuat kondisi menjadi baik**; kondisi dinilai
  terpisah lewat perubahan status.
- Audit: menerbitkan sesi **membekukan** snapshot target; perubahan master sesudahnya tidak
  mengubah snapshot. Temuan tidak menimpa master — verifikasi dan koreksi adalah dua langkah
  terpisah, dan koreksi meninggalkan histori. Sesi tidak dapat ditutup selama ada temuan
  menggantung, dan sesudah ditutup hanya menerima addendum.
- Impor staging menyimpan `raw_json` apa adanya, menandai baris bermasalah alih-alih
  membuangnya, dan idempotent lewat checksum berkas + nomor baris.

### Verifikasi manual (`http://localhost/cihawuk/`)

Sebagai `aset.demo`: membuat register, membuat unit, mengaktifkannya dengan alasan, lalu
menerbitkan token QR — token tampil sekali di dashboard. Memindai `/aset/q/{token}` memberi
200 dengan nama barang, asset tag, dan pemilik "Pemerintah Desa Cihawuk", header
`X-Robots-Tag: noindex, nofollow`, serta kalimat bahwa QR bukan bukti kepemilikan. Token acak
menghasilkan halaman "QR tidak dikenali", bukan galat.

### Yang belum ada

- **Berkas S5 tetap tidak tersedia**, jadi tidak ada satu pun register aset nyata. Importer
  diuji memakai fixture `tests/fixtures/aset-contoh.csv`.
- Importer membaca **CSV**; berkas `.xlsx` harus diekspor ke CSV lebih dulu karena proyek ini
  tidak memaketkan pembaca xlsx.
- **Label QR belum menghasilkan PDF bergambar QR.** Batch label, histori cetak, dan alasan
  cetak ulang sudah ada, tetapi tidak ada pustaka pembuat gambar QR yang dipaketkan, dan CSP
  situs melarang mengambilnya dari CDN. Menambahkannya memerlukan satu dependensi baru.
- Dokumen kepemilikan aset (`asset_documents`) sudah punya tabel dan sudah dikecualikan dari
  halaman publik, tetapi form unggahnya belum dibuat.
- Halaman QR untuk petugas login (aksi sesuai permission setelah scan) belum dibuat.

---

# Bagian A.14 — Tahap 11 dan 12: gudang, pemeliharaan, dan QA akhir (19 September 2026)

## A.14.1 Tahap 11 — Gudang persediaan (migration 014)

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 014: 10 tabel gudang | Selesai | `tools migrate 13` lalu `tools migrate` kembali ke versi 14 |
| 2 | `WarehouseService`: item, konversi, transaksi, ledger, permintaan, opname | Selesai | `WarehouseTest` (10 tes) |
| 3 | Dashboard gudang: barang, kartu stok, transaksi, opname | Selesai | `admin/Gudang.php` + 4 view |
| 4 | Modul `warehouse` diaktifkan | Selesai | Lewat Pengaturan > Modul aplikasi |

Aturan yang ditegakkan: saldo selalu dijumlahkan dari `inventory_ledger` yang append-only;
posting mengunci baris barang sehingga **dua pengeluaran atas stok terakhir tidak dapat
sama-sama berhasil** (yang kedua ditolak 409); konversi satuan memakai numerator dan
denominator bilangan bulat dan hasil pecahan ditolak; transaksi yang sudah diposting tidak
dapat diubah; penyesuaian wajib beralasan; pemohon tidak dapat menyetujui permintaannya
sendiri; selisih opname dihitung server, dan opname tidak dapat ditutup selama masih ada
barang yang belum dihitung.

## A.14.2 Tahap 12 — Pemeliharaan dan pemeriksa tautan (migration 015)

| # | Pekerjaan | Status | Bukti |
|---|---|---|---|
| 1 | Migration 015: `link_check_results` | Selesai | `tools migrate 14` lalu `tools migrate` kembali ke versi 15 |
| 2 | `LinkCheckService` + job `tools run_jobs link_check` | Selesai | `LinkCheckTest` (4 tes); run nyata: 35 tautan, 0 rusak |
| 3 | Halaman Pengaturan > Pemeliharaan | Selesai | Antrean, cache, penyimpanan, modul nonaktif, pekerjaan menunggu, aset tanpa QR, stok minimum, tautan rusak |

Pemeriksa hanya menelusuri tautan **internal**; tautan `http(s)://` dicatat tetapi tidak
pernah dipanggil dari server. Satu temuan nyata muncul saat menulis pemeriksanya: route
penangkap `(.+)` dan dua wildcard halaman CMS membuat setiap path terlihat sehat, sehingga
pola yang diawali wildcard sekarang dikecualikan dan halaman CMS diperiksa terpisah lewat
`page_by_path()`.

## A.14.3 QA akhir

```
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit   → OK (235 tests, 1102 assertions)
tools migrate 14 → tools migrate                  → versi 15, tanpa kehilangan data
tools seed (dua kali)                             → run kedua 0 baris baru
tools health                                      → Status: SEHAT
tools run_jobs                                    → 7 pekerjaan berjalan
```

Skema akhir: **134 tabel**, versi **15**. Bootstrap pengujian menjalankan
`tools migrate 0` → `tools migrate` → `tools seed` setiap kali suite dijalankan, sehingga
reversibilitas seluruh migration dari nol terbukti pada setiap run.

23 halaman publik diperiksa dan seluruhnya 200. `/.env` 403; `application/`, `storage/`,
`database/`, `reference/`, `composer.json` 404; seluruh `admin/...` mengarahkan ke login.

Rincian per berkas uji ada pada `docs/test-report.md`, termasuk daftar hal yang **tidak**
diuji pada sesi ini (pengujian visual, responsif, pembaca layar, MySQL 8, Linux).

## A.14.4 Status modul setelah seluruh tahap

| Modul | State | Catatan |
|---|---|---|
| public_website, complaints, citizen_accounts, news, agenda, gallery, public_documents, village_data, village_potentials | active | Sejak tahap sebelumnya |
| facilities | active | Tahap 6; direktori sengaja masih kosong |
| organization | active | Tahap 7; sebelumnya `public_readonly` |
| budget_transparency | active | Tahap 8; belum ada angka APBDes nyata |
| umkm_directory | active | Tahap 9; sengaja kosong sampai ada persetujuan pemilik |
| assets, public_asset_qr | active | Tahap 10; register kosong sampai S5 tersedia |
| warehouse | active | Tahap 11 |
| village_letters | **disabled** | Sengaja tidak dibangun; lihat A.14.5 |

## A.14.5 Yang sengaja tidak dikerjakan dan alasannya

- **Surat desa (`village_letters`)** tidak dibangun. Master prompt §28 menetapkan
  `FEATURE_LETTERS=false` dan melarang mengarang jenis surat, syarat, format nomor, biaya,
  dan pejabat penandatangan. Semua itu keputusan SOP desa. Modulnya tetap `disabled`.
- **Impor S5 aset** belum dapat dijalankan: berkasnya tidak ada di `reference/documents/`,
  dan formatnya `.xlsx` sedangkan proyek ini tidak memaketkan pembaca xlsx. Importer sudah
  ada, diuji dengan fixture CSV, dan menunggu berkas dari pengelola.
- **Label QR bergambar** belum dibuat karena tidak ada pustaka pembuat gambar QR yang
  dipaketkan dan CSP melarang CDN. Batch, histori cetak, dan alasan cetak ulang sudah ada.
- **d3-org-chart** tidak dipakai karena tidak dipaketkan; tree organisasi dirender sebagai
  daftar hierarkis semantik yang justru memenuhi syarat fallback aksesibel.
- **Angka APBDes, entri fasilitas, profil UMKM, dan register aset** sengaja kosong: dokumen
  sumber tidak memuatnya, dan mengarangnya dilarang spesifikasi.

---

# Bagian B - Riwayat implementasi (spesifikasi versi sebelumnya)

## Tahap 1 — Environment, CI3, dependensi, kompatibilitas PHP 8.3.33 (15 September 2026)

**Status: selesai.**

Perubahan penting:
- `C:\xampp\php-8.3.33\php.ini` (backup `php.ini.bak-cihawuk-20260915`): `extension=sodium` diaktifkan, `upload_max_filesize=5M`, `post_max_size=20M`.
- Database `cihawuk_digital` dan `cihawuk_digital_test` (utf8mb4_unicode_ci). User DB `cihawuk_app` (DML saja) dan `cihawuk_migrate` (DDL, khusus `tools migrate`).
- Virtual host `cihawuk.test` → `C:/xampp/htdocs/cihawuk/public` di `httpd-vhosts.conf` (backup `httpd-vhosts.conf.bak-cihawuk-20260915`), plus VirtualHost `localhost` default agar proyek lain tetap jalan dan `<Location /cihawuk>` ditolak.
- Composer: CodeIgniter 3.1.13, phpdotenv 5.7.0, PHPMailer 6.12.0, Dompdf 3.1.6, HTML Purifier 4.19.0, PHPUnit 11.5.56 (dev). `composer check-platform-reqs` lulus.
- `system/` disalin dari paket Composer dan diberi patch minimal (`patches/ci3-3.1.13-php83.patch`), dapat dipasang ulang dengan `php scripts/install-ci3-system.php`.
- Front controller `public/index.php` + `bootstrap/app.php` (loader `.env`, zona waktu PHP UTC).
- 3 migration (67 tabel) — lulus `migrate` → `migrate 0` → `migrate` pada DB testing.

Perintah yang dijalankan (ringkas):
```
C:\xampp\php-8.3.33\php.exe -m
composer require codeigniter/framework:3.1.13 vlucas/phpdotenv:^5.6 phpmailer/phpmailer:^6.9 dompdf/dompdf:^3.0 ezyang/htmlpurifier:^4.17
composer require --dev phpunit/phpunit:^11.5
composer check-platform-reqs
php public/index.php tools generate_keys
php public/index.php tools migrate
php public/index.php tools health      → Status: SEHAT
C:\xampp\apache\bin\httpd.exe -t        → Syntax OK
```

Temuan yang perlu diketahui pengelola:
- Server database adalah **MariaDB 10.4.27**, bukan MySQL. Aplikasi hanya diuji pada MariaDB ini.
- `FLUSH PRIVILEGES` gagal karena tabel sistem Aria `mysql.columns_priv` dan `mysql.proxies_priv` rusak (error 176, sudah ada sebelum proyek ini). User baru tetap berfungsi. *(Diperbarui tahap 8: tabel sistem kemudian diperbaiki — lihat "Catatan operasional mesin" di bawah.)*
- LibreOffice tidak terpasang, sehingga S3 (`.doc`) belum dapat diekstrak otomatis.
- `php` di PATH sekarang mengarah ke 8.3.33; `C:\xampp\php` (7.4.33) tetap ada. Gunakan path penuh `C:\xampp\php-8.3.33\php.exe` di dokumentasi.

## Tahap 2 — Migration master, auth, RBAC (15 September 2026)

**Status: selesai.**

- 20 permission dan 10 role preset (`application/config/rbac.php`), seed idempotent (`database/seeds/MasterSeeder.php`).
- `AuthService`: daftar mandiri, aktivasi token/manual, login username/email, logout POST, logout semua perangkat,
  daftar & cabut sesi, reset (30 menit, hash HMAC, sekali pakai), pemulihan manual, MFA TOTP + 10 recovery code,
  rehash argon2id, limiter per identifier/IP, `auth_version`, reautentikasi, proteksi Super Admin terakhir.
- `tools create_admin` interaktif (password lewat prompt tersembunyi PowerShell).
- Bukti: `AccountLifecycleTest`, `AuthHttpTest`, `TotpTest`.

## Tahap 3 — Vertical slice anonim, pelacakan, loket (15 September 2026)

**Status: selesai.**

- `/lapor` 3 langkah (honeypot, limiter, idempotensi), bukti sekali tampil, `/lacak` dengan grant sesi: balas,
  konfirmasi, tarik, keluar, kaitkan ke akun.
- `UploadService` (allowlist, finfo + signature, re-encode GD, storage privat) dan controller `Berkas`.
- `/admin/laporan/buat` (loket: akun warga, identitas terenkripsi, anonim dengan kode akses).
- Bukti: `TicketHttpTest` (anonim, brute force, kirim ganda, upload berbahaya),
  `TicketWorkflowTest::test_front_desk_records_creator_separately`.

## Tahap 4 — Dashboard warga, workflow, SLA (15 September 2026)

**Status: selesai.**

- `TicketWorkflowService`: allowlist 17 aksi, row lock + versi (409), riwayat append-only, `return_status`,
  reassignment, duplikat, rujukan, reopen per episode, konflik kepentingan.
- `/warga` (beranda, laporan, profil, notifikasi, akun) dan `/admin/laporan` (DataTables server-side, detail, aksi).
- `SlaService`: kalender WIB → UTC, snapshot, jeda, status terlambat.
- Bukti: `TicketWorkflowTest`, `SlaServiceTest`, `AccessControlTest`.

## Tahap 5 — CMS, data sumber, konten (15–16 September 2026)

**Status: selesai, dengan keterbatasan S3.**

- `ContentService` (8 jenis konten + profil, HTML Purifier, slug + 301, revisi, draft → review → terbit → arsip),
  media publik dengan status hak, pratinjau draft khusus editor.
- `SourceImportService` + `tools import_sources`: S1 3.364, S2 1.637, S4 649 observasi. S3 `.doc` →
  `needs_conversion` (LibreOffice tidak tersedia).
- 10 data issue, 11 indikator, 16 nilai statistik draft; profil, pejabat historis, potensi, dan hero sebagai draft.
- Bukti: `SourceNormalizationTest`, `JobsAndExportTest::test_content_publication_workflow`, impor ulang S4 tanpa
  duplikasi.

## Tahap 6 — Frontend publik dan dashboard (16 September 2026)

**Status: selesai.**

- Semua halaman publik §12 dengan empty state; design token; header transparan → putih; Swiper; peta fallback.
- Scene Three.js dibundel esbuild (±136 KB gzip), lazy + fallback (reduced motion, Save-Data, perangkat lemah,
  tanpa WebGL, context lost).
- SB Admin 2 dikustomisasi (warna/font, sidebar per permission).
- QA visual: lihat `docs/test-report.md` (UI-01/02/03). Perbaikan selama QA: label "(wajib)" di BS4, `.form-select`
  di BS4, filter checkbox DataTables, lebar kolom tabel admin, ukuran `legend`, tombol kembali yang diblokir CSP.

## Tahap 7 — Notifikasi, ekspor, operasi (16 September 2026)

**Status: selesai.**

- Notifikasi in-app + outbox (dedupe, klaim atomik, retry backoff, `not_configured`); `tools run_jobs` dengan
  `job_locks` (outbox, eskalasi SLA, pengingat tanggapan, ekspor, pembersihan).
- Ekspor CSV (anti formula) dan PDF (Dompdf, remote off); izin dicek saat diminta, dijalankan, dan diunduh.
- `tools seed_demo` / `tools purge_demo` (12 akun `.demo`, 10 skenario tiket).
- `scripts/backup.ps1` dan `scripts/restore-check.ps1`; OPS-02 lulus (`HASIL: PULIH`). OPS-01 via Apache lulus.

## Tahap 8 — QA akhir dan dokumentasi (16 September 2026)

**Status: selesai.**

- 17 dokumen §30 ditulis berdasarkan kode aktual (README dan 16 berkas di `docs/`).
- Tes tambahan: `JobsAndExportTest::test_smtp_failure_retries_with_backoff_then_fails`.
- Hasil akhir: `vendor/bin/phpunit` → **OK (63 tests, 375 assertions)** pada PHP 8.3.33; `tools health` → SEHAT.

Perintah yang dijalankan (ringkas):
```
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit
C:\xampp\php-8.3.33\php.exe public/index.php tools health
C:\xampp\php-8.3.33\php.exe public/index.php tools import_sources S4    (uji impor ulang)
powershell -File scripts\backup.ps1 -Destination <folder scratch>
powershell -File scripts\restore-check.ps1 -BackupDir <folder> -TargetDatabase cihawuk_restore_check
```

### Catatan operasional mesin (16 September 2026)

- MariaDB beberapa kali berhenti karena jendela konsolnya tertutup, lalu gagal start ("Could not open mysql.plugin",
  log Aria rusak). Tindakan: folder `C:\xampp\mysql\data\mysql` di-backup ke direktori scratch sesi
  (`mysql-system-backup-20260916`), `aria_chk -r` dijalankan pada tabel sistem, log Aria lama **dipindahkan** (tidak
  dihapus) ke `mysql-system-backup-20260916\aria-logs-moved`, lalu `REPAIR TABLE` untuk tabel sistem (termasuk
  `columns_priv` dan `proxies_priv` yang rusak sejak awal). Semua `CHECK TABLE` sistem kini OK dan tidak ada tabel
  yang di-drop. Folder backup berada di direktori temp sesi — salin ke lokasi permanen bila ingin disimpan.
- Jalankan/hentikan Apache dan MariaDB lewat XAMPP Control Panel, bukan dengan menutup jendela konsol.

### Keterbatasan dan langkah lanjutan

- Belum siap produksi: HTTPS/server target, SMTP nyata, konten & aset resmi (`docs/content-needed.md`),
  pengesahan SOP/SLA, akun pengelola nyata + MFA.
- S3 perlu dikonversi ke `.docx` lalu diimpor; locator S2/S4 belum memuat nama bagian.
- Belum ada antivirus lampiran, re-enkripsi massal untuk rotasi kunci, atau reautentikasi saat membuka identitas.
- Belum diuji: MySQL 8, Linux/Nginx, zoom 200% asli, reduced motion teremulasi, jaringan lambat/LCP, pembaca layar,
  dua tab AJAX (lihat `docs/test-report.md`).
- Database pengembangan berisi data demo (hapus dengan `tools purge_demo`) dan satu berita uji QA.
- Entri hosts `127.0.0.1 cihawuk.test` harus ditambahkan pemilik mesin (butuh hak administrator).
- Belum ada commit git; commit dilakukan bila diminta.

---

# Bagian A.15 — Data demonstrasi dan impor S3 (19 September 2026)

**Status: selesai.**

Permintaan: isi modul yang masih kosong dengan data contoh supaya aplikasinya dapat
diperagakan kepada pemerintah desa.

## A.15.1 Temuan yang mengubah arah pekerjaan

Folder `data/` yang ditunjuk pengguna ternyata **identik byte-per-byte** dengan
`reference/documents/` (SHA-256 keempat berkas sama). Tidak ada sumber baru.

Namun S3 (`2.Kata Pengantar Profil Cihawuk 2023 (1).doc`, 30 MB, OLE2) memang belum pernah
terimpor, semata karena proyek ini tidak memaketkan pembaca `.doc`. Microsoft Word terpasang
di mesin ini, sehingga berkas dikonversi sekali jalan menjadi `.docx` dan dibaca importer
yang sudah ada. Isinya justru memuat hal-hal yang selama ini ditandai "menunggu pengelola":
sambutan, visi, misi, sejarah, susunan perangkat, dan 31 gambar.

Akibatnya sebagian besar isi Profil **bukan data contoh, melainkan data desa sebenarnya.**

## A.15.2 Yang dikerjakan

| Bagian | Hasil |
|---|---|
| Konversi S3 | `scripts/convert-doc.ps1` (otomasi Word COM, berkas asli tidak disentuh) |
| Register sumber | Regex S3 menerima `.docx` dan mengutamakannya; checksum menunjuk berkas yang benar-benar dibaca |
| Profil | Blok `greeting`, `vision`, `mission` diisi naskah asli S3; visi kecamatan disimpan terpisah |
| Organisasi | 14 jabatan dan namanya, menggantikan dua jabatan tanpa nama |
| Isu data | `ORG_CHART_MISMATCH` dan `BPD_TERM_LABEL` dicatat, tidak diputus sepihak |
| Impor media | `UploadService::store_media_file()` + `tools import_media`; ingest dibuat transaksional |
| Mode demo | `DEMO_MODE` + bilah penanda + `noindex` pada halaman publik dan dashboard |
| Foto | 24 foto berlisensi dari Wikimedia Commons, 31 gambar S3 sebagai draft |
| Data contoh | 12 fasilitas, 5 potensi, 10 UMKM, 3 tahun APBDes, 17 register aset, 51 unit, 25 barang gudang, 8 berita, 6 agenda |
| Jejak hapus | Migration 016 `demo_records` + rutin purge per modul |

## A.15.3 Cacat yang ditemukan dan diperbaiki

- `UploadService::store_media_asset()` hanya menerima `$_FILES`; tidak ada jalur impor dari
  disk sama sekali, sehingga `public/media/` masih kosong sejak awal proyek.
- Ingest media tidak transaksional: percobaan gagal meninggalkan 31 baris `private_files`
  tanpa `media_assets`. Kini dibungkus transaksi dengan pembersihan berkas.
- `media_assets.uploaded_by` dicor `(int) NULL` menjadi 0, melanggar kunci asing pada impor CLI.
- `purge_demo` tidak dapat menghapus akun yang pernah menjalankan ekspor
  (`export_jobs.requested_by` RESTRICT).
- `MasterSeeder::profile_blocks()` melewati blok yang sudah ada, sehingga blok yang terlanjur
  dibuat kosong tidak pernah terisi. Kini blok tanpa versi diberi versi pertamanya.

## A.15.4 Hasil pengujian

```
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit   → OK (245 tests, 1193 assertions)
tools seed (dua kali)                             → run kedua 0 baris baru
tools seed_demo lalu purge_demo                   → seluruh tabel kembali ke jumlah semula
tools health                                      → Status: SEHAT
```

Skema **versi 16**. Bootstrap pengujian tetap menjalankan `tools migrate 0` → `migrate` →
`seed` setiap run, jadi migration 016 terbukti reversibel dari nol.

20 halaman publik diperiksa dan seluruhnya 200. Halaman QR aset diuji dengan token sah
(200, tanpa nomor seri maupun nilai perolehan) dan token tak dikenal (200, bukan 500).
Penelusuran kebocoran pada 11 halaman publik dan sitemap tidak menemukan nomor seri, kode
register, nilai perolehan, maupun kata sandi demo.

## A.15.5 Yang tetap tidak dikerjakan

- **Surat desa (`village_letters`)** tetap `disabled`. Yang hilang bukan datanya melainkan
  seluruh modulnya, dan master prompt §28 melarang mengarang jenis surat, syarat, format
  nomor, biaya, dan pejabat penandatangan.
- **Foto perangkat desa** tidak dipasangkan ke nama. Foto S3 sebagian berasal dari desa lain,
  dan memasang potret bank foto pada nama pejabat sungguhan adalah soal privasi, bukan hanya
  hak cipta. Avatar ilustrasi pun menuntut `photo_consent = 1` yang tidak pernah dicatat.
- **Impor S5 aset** tetap menunggu berkas dari pengelola; register aset kini terisi contoh.
- **Label QR bergambar** dan **d3-org-chart** tetap seperti A.14.5.
