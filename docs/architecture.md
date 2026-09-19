# Arsitektur

## Lapisan

```
public/index.php ──► bootstrap/app.php (Composer autoload, .env, zona waktu UTC)
                 └─► CodeIgniter 3 (system/, dengan patch PHP 8.3)
                       ├─ application/config/routes.php  (route eksplisit; URI lain → 404)
                       ├─ core/MY_Controller             (DB UTC, header keamanan, _remap + exception → HTTP)
                       │    ├─ Public_Controller          (situs publik; sesi hanya bila dibutuhkan)
                       │    ├─ Resident_Controller        (/warga: login + role warga)
                       │    ├─ Admin_Controller           (/admin: login + role staf, MFA opsional wajib)
                       │    └─ Cli_Controller             (tools: menolak HTTP)
                       ├─ controllers → validasi request, panggil service, pilih respons
                       ├─ libraries (service)             → aturan bisnis, transaksi, izin per objek
                       ├─ models                          → query (Query Builder / binding)
                       └─ views                           → tanpa query DB; escape sesuai konteks
```

- Semua method controller dijalankan lewat `MY_Controller::_remap`: hanya method publik yang dideklarasikan
  controller konkret yang bisa dipanggil; `AccessDeniedException`→403, `DomainRuleException`→status yang dibawa
  (422/404/409/429…), `VersionConflictException`→409, `DbWriteException`→500 dengan pesan aman.
- Route terakhir `(.+) → errors/not_found` mematikan auto-routing CI3 sehingga method yang tidak didaftarkan tidak
  dapat dipanggil via URL.

## Service utama (`application/libraries`)

| Service | Tanggung jawab |
|---|---|
| `AuthService` | Login, MFA TOTP, sesi (`user_sessions` + `auth_version`), daftar/aktivasi, reset/pemulihan, reautentikasi |
| `AuthorizationService` | Permission per pengguna, kemampuan per tiket, filter lingkup daftar (SQL), syarat penanggung jawab |
| `TicketWorkflowService` | Satu-satunya jalur perubahan status (allowlist aksi), transaksi + versi, riwayat, SLA, notifikasi |
| `TicketAccessService` | Nomor tiket acak, kode akses (hash HMAC), grant sesi pelacakan, receipt sekali tampil |
| `SlaService` | Kalender kerja WIB, tenggat UTC, snapshot kebijakan, jeda, indikator terlambat |
| `UploadService` | Validasi lampiran (allowlist, finfo, signature, re-encode gambar), penyimpanan privat, unduhan, media publik |
| `NotificationService` | Notifikasi dalam aplikasi + outbox email (dedupe, retry, klaim atomik) |
| `ContentService` | Skema CMS, sanitasi HTML Purifier, slug unik + 301, revisi, publikasi, pemakaian media |
| `SourceImportService` | Parser DOCX, normalisasi, observasi mentah, review, promosi ke statistik |
| `ExportService` | Ekspor CSV/PDF tertunda, cek izin ulang, anti formula injection |
| `JobRunner` | Outbox, eskalasi SLA, pengingat tanggapan, ekspor, pembersihan — dengan `job_locks` |
| `FeatureModuleService` | Status modul aplikasi, validasi dependency, histori perubahan, guard route |
| `CmsService` | Registry section, versi halaman/section, validasi konfigurasi, pengambilan data render |
| `CmsPublicationService` | Review, snapshot publikasi, penjadwalan, penarikan, rollback, token pratinjau |
| `CmsMenuService` | Item menu, validasi kedalaman/URL/cycle, snapshot dan rollback menu |
| `SiteSettingsService` | Draft dan snapshot identitas situs + token tema, validasi warna/kontras/font |
| `PublicCache` | Cache halaman/menu/identitas dengan invalidasi terarah |
| `RateLimiter`, `Idempotency`, `Crypto`, `Totp`, `Clock`, `Settings`, `AuditService`, `Mailer` | Infrastruktur |

## Service modul lanjutan

| Service | Tanggung jawab |
|---|---|
| `DatasetService` | Dataset berversi, verifikasi nilai, pemeriksaan sebelum terbit, snapshot, rollback, pembacaan publik |
| `ProfileService` | Blok profil terstruktur berversi, linimasa kepemimpinan, snapshot profil |
| `FacilityService` | Lokasi publik, direktori fasilitas, layanan, verifikasi, publikasi |
| `BusinessService` | Direktori UMKM beserta pencatatan dan pencabutan persetujuan pemilik |
| `OrganizationService` | Periode, unit, jabatan, orang, penugasan, snapshot struktur |
| `BudgetService` | Tahun anggaran, revisi murni/perubahan/realisasi, validasi, snapshot |
| `AssetService` | Register, unit fisik, status ber-histori, mutasi, peminjaman, pemeliharaan, token QR, batch label |
| `AssetAuditService` | Sesi audit dengan target beku, temuan, verifikasi, koreksi, penutupan, addendum |
| `AssetImportService` | Impor register aset ke staging, idempotent lewat checksum |
| `WarehouseService` | Barang, konversi satuan, transaksi, ledger append-only, opname |
| `LinkCheckService` | Pemeriksa tautan internal rusak pada menu, section, dan dokumen |

Seluruh service mengikuti pola yang sama seperti CMS: draft disimpan berversi, penerbitan
menghasilkan snapshot pada `cms_publication_snapshots`, dan frontend hanya membaca snapshot.
`target_type` yang dipakai: `page`, `menu`, `site`, `dataset`, `profile`, `organization`,
`budget`.

## Status modul (feature module)

Setiap modul aplikasi memiliki satu status di tabel `feature_modules`: `disabled`, `internal_only`,
`public_readonly`, `active`, atau `maintenance`. `MY_Controller::_remap` mencocokkan URI dengan prefix route modul
sebelum method dijalankan:

| Status | Area publik | Dashboard pengelola |
|---|---|---|
| `disabled` | 404 | 404 |
| `internal_only` | 404 | tersedia |
| `public_readonly` | tersedia (aksi tulis publik ditolak) | tersedia |
| `active` | tersedia | tersedia |
| `maintenance` | 503 halaman pemeliharaan | tersedia |

Perubahan status memerlukan permission `settings.feature.manage` dan alasan minimal 10 karakter, lalu dicatat di
`feature_module_histories`, `audit_logs`, dan `admin_activity_events`. Dependency divalidasi dua arah: modul tidak
dapat diaktifkan bila dependensinya mati, dan tidak dapat dimatikan bila masih ada modul aktif yang bergantung
padanya. Variabel `.env` (`FEATURE_ASSET_MANAGEMENT`, `FEATURE_PUBLIC_ASSET_QR`, `FEATURE_WAREHOUSE`,
`FEATURE_ORGANIZATION_TREE`, `FEATURE_LETTERS`) hanya berfungsi sebagai pemutus darurat: nilai `false` memaksa
nonaktif, nilai `true` tidak memaksa aktif. Menonaktifkan modul tidak menghapus data, berkas, histori, atau audit.

## Navigasi, identitas, dan cache publik

`Public_Controller` membaca tiga hal dari snapshot pada setiap permintaan halaman publik: susunan menu
(`CmsMenuService::published_menu()`), identitas serta token tema (`SiteSettingsService::published()`), dan
susunan halaman (`CmsPublicationService::published_layout()`). Bila menu belum pernah diterbitkan, navigasi lama
pada `navigation_items` dipakai sebagai cadangan sehingga situs tetap dapat dijelajahi.

Halaman CMS selain beranda dilayani `Halaman::show()` lewat dua route wildcard yang didaftarkan **setelah**
seluruh route literal, jadi route sistem selalu menang. Controller memastikan halamannya benar-benar terbit;
jika tidak, `cms_redirects` diperiksa untuk 301, lalu 404.

`PublicCache` menyimpan hasil pembacaan snapshot per grup (`page`, `menu`, `site`, `listing`) dan membuang hanya
entri yang terpengaruh saat publikasi. Menyimpan draft tidak menyentuh cache.

## Halaman publik berbasis snapshot

Beranda dan halaman CMS lain dirender dari `cms_publication_snapshots` yang aktif, bukan dari tabel draft.
`Home` memanggil `CmsPublicationService::published_layout('home')`, lalu tiap section dirender oleh partial
`application/views/site/sections/{jenis}.php` dengan data bisnis yang diambil saat render
(`CmsService::section_data()`). Draft hanya terlihat lewat pratinjau bertanda tangan atau mode pratinjau editor.
Rinciannya ada di `docs/cms.md`.

## Sesi dan autentikasi

- Driver sesi CI3 **database** (`ci_sessions`). Cookie `chw_session` HttpOnly, SameSite=Lax, Secure di produksi.
- Setelah login, sesi menyimpan `uid`, `auth_version`, area, id baris `user_sessions`, dan token acak (hash di DB).
  Setiap request memeriksa status akun, `auth_version`, pencabutan sesi, idle timeout, dan batas absolut:
  pengelola 30 menit/8 jam, warga 60 menit/12 jam (konfigurasi `.env`).
- Perubahan password/role/status menaikkan `auth_version` sehingga sesi lain berakhir.
- Session ID diregenerasi saat login, MFA, logout, dan pergantian password.
- Grant pelacakan anonim adalah data sesi terpisah (`anon_grant`) dan tidak menjadikan pengunjung pengguna berakun.

## CSRF dan AJAX

- CSRF CI3 aktif untuk semua POST dengan regenerasi token per request.
- `MY_Security`: request AJAX yang gagal CSRF menerima JSON 403 (`code=csrf_invalid`) beserta token baru;
  kelebihan `post_max_size` dilaporkan sebagai 413.
- `dashboard.js` menserialkan POST AJAX dan memperbarui token di semua form. `GET /csrf-token` (same-origin,
  no-store) untuk pemulihan. DataTables memakai GET read-only.

## Penyimpanan berkas

| Jenis | Lokasi | Akses |
|---|---|---|
| Lampiran laporan, ekspor, dokumen sumber | `STORAGE_PRIVATE_PATH` (di luar `public/`), nama acak tanpa ekstensi | Hanya via controller `Berkas`/`Ekspor` setelah cek hak objek induk |
| Berkas asli media | `STORAGE_PRIVATE_PATH/media/YYYY/MM/<acak>` (`private_files.purpose = media_original`) | Tidak memiliki URL publik |
| Derivative media publik | `public/media/YYYY/MM/<acak>[-web|-thumb].<ext>` | Statis; `.htaccess` menolak skrip/HTML/SVG |
| Dokumen sumber pengembangan | `reference/documents/` | Tidak dilayani web, diabaikan git |

Unggahan media menghasilkan satu berkas asli privat dan tiga derivative publik (`public`, `webp` 1600 px,
`thumb` 480 px) yang dibuat ulang dengan GD sehingga metadata EXIF hilang. Tabel `media_derivatives` mencatat tiap
turunan dan `media_usages` mencatat konten yang memakainya (`ContentService::sync_media_usages()`). Mengarsipkan
media membuang derivative publik dan mempertahankan berkas asli beserta histori.

## Outbox dan job

Notifikasi dan pesan email ditulis dalam transaksi yang sama dengan perubahan tiket. Email dikirim oleh job
(`tools run_jobs`) dengan klaim baris atomik, dedupe key unik, retry eksponensial (maks 5), dan status
`not_configured` bila SMTP belum aktif. Kegagalan email tidak memengaruhi status tiket.

## Frontend

- Situs publik: Bootstrap 5 + `public/assets/site/css/site.css` (design token), `site.js` (header, drawer,
  stepper form, carousel, lightbox, geolokasi atas tindakan pengguna).
- Scene Three.js dibundel (`scripts/assets/src/hero-scene.js` → `public/assets/site/js/hero-scene.bundle.js`) dan
  dimuat oleh `hero-loader.js` hanya bila perangkat mampu dan pengguna tidak meminta reduced-motion/Save-Data.
- Dashboard: SB Admin 2 (Bootstrap 4 + jQuery) + `dashboard.css`/`dashboard.js`. Kedua bundle tidak pernah dimuat
  pada halaman yang sama.
- CSP: `script-src 'self'` (tanpa skrip inline; data dikirim lewat `<script type="application/json">`).

## Tambahan 19 September 2026

| Berkas | Perubahan |
|---|---|
| `application/libraries/UploadService.php` | `store_media_file($path, $nama, $meta, $user_id)` untuk impor media dari disk. Badan `store_media_asset()` dipindahkan ke `ingest_media_inner()`; keduanya memakai pemeriksaan yang sama persis dan hanya berbeda pada cara memindahkan berkas (`move_uploaded_file` vs `copy`). `ingest_media()` membungkus keduanya dalam transaksi dan membuang berkas yang terlanjur ditulis bila gagal |
| `application/libraries/PublicCache.php` | `flush()` mengosongkan seluruh grup sekaligus; dipakai saat banyak entitas dihapus serentak |
| `application/controllers/Tools.php` | Perintah `import_media <folder>`, membaca `reference/media/<folder>/manifest.csv`, idempotent lewat checksum berkas asli |
| `application/config/app.php` | `features['demo_mode']` dari `DEMO_MODE` |
| `application/core/Public_Controller.php` | Meneruskan `demo_mode` ke layout |
| `database/seeds/DemoSeeder.php` | Bertambah dari akun dan tiket menjadi seluruh modul, dengan jejak `demo_records` dan rutin penghapusan per modul |

Catatan rancangan: seluruh data contoh dibuat **melalui service**, bukan `INSERT` langsung.
Karena itu riwayat, versi, audit log, dan snapshot ikut terbentuk, dan aturan validasi
benar-benar terbukti dilewati dengan sah. APBDes contoh, misalnya, tetap harus menutup
identitas `(pendapatan - belanja) + (pembiayaan masuk - pembiayaan keluar) = 0`; angka daunnya
ditulis tetap dan pembiayaan keluarnya dihitung dari situ.
