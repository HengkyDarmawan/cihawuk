# Layanan Digital Desa Cihawuk

Profil desa, informasi publik, dan layanan pengaduan/aspirasi/permintaan informasi untuk Desa Cihawuk,
Kecamatan Kertasari, Kabupaten Bandung. Satu aplikasi dengan tiga area:

| Area | URL | Untuk |
|---|---|---|
| Website publik | `/` | Profil, pemerintahan, potensi, data desa, berita, agenda, galeri, dokumen, laporan anonim, pelacakan |
| Dashboard warga | `/warga` | Warga berakun: membuat, memantau, dan membalas laporan sendiri |
| Dashboard pengelola | `/admin` | Verifikasi, disposisi, tindak lanjut, CMS, data sumber, pengguna, pengaturan, audit |

Spesifikasi utama: [`prompt-master-desa-cihawuk(1).md`](prompt-master-desa-cihawuk%281%29.md) (v1.2, 18 September 2026)
beserta dua dokumen pendamping: [`modul-frontend-data-desa-cihawuk.md`](modul-frontend-data-desa-cihawuk.md) dan
[`modul-backend-cms-maintenance-desa-cihawuk.md`](modul-backend-cms-maintenance-desa-cihawuk.md).

## Stack

- PHP **8.3.33** (Apache mod_php XAMPP dan CLI) — `C:\xampp\php-8.3.33\php.exe`
- CodeIgniter **3.1.13** + patch kompatibilitas PHP 8.3 (`patches/`)
- **MariaDB 10.4.27** (bawaan XAMPP ini) via `mysqli`, InnoDB utf8mb4
- Frontend publik: Bootstrap 5.3.8, Swiper 14, Three.js 0.186 (dibundel, dimuat tertunda), Leaflet 1.9.4, Chart.js 4.5
- Dashboard: SB Admin 2 (Bootstrap 4.6.2 + jQuery 3.7.1), DataTables 3, Select2, SweetAlert2
- PHP: phpdotenv, PHPMailer, Dompdf, HTML Purifier, PHPUnit (dev)

Tidak ada server Node saat runtime. Node hanya dipakai untuk mengambil aset vendor dan membundel scene Three.js
(`scripts/assets`, hasilnya sudah ada di `public/assets`).

## Quick start (XAMPP, Windows)

```powershell
$php = 'C:\xampp\php-8.3.33\php.exe'
composer install
composer check-platform-reqs
copy .env.example .env            # isi DB_*, STORAGE_PRIVATE_PATH
& $php public/index.php tools generate_keys
& $php public/index.php tools migrate
& $php public/index.php tools seed
& $php public/index.php tools create_admin
& $php public/index.php tools health
```

Buka **http://localhost/cihawuk/** (Alias Apache ke `public/`, isi `APP_BASE_URL_ALIASES=http://localhost/cihawuk/`),
atau tambahkan `127.0.0.1 cihawuk.test` ke `C:\Windows\System32\drivers\etc\hosts` lalu buka http://cihawuk.test/. Panduan lengkap: [docs/setup-xampp.md](docs/setup-xampp.md).

Data demonstrasi (development saja): `php public/index.php tools seed_demo` — hapus dengan `tools purge_demo`.

## Pengujian

```powershell
& 'C:\xampp\php-8.3.33\php.exe' vendor/bin/phpunit
```

Memakai database terpisah `cihawuk_digital_test` (`.env.testing`). Hasil terakhir: lihat [docs/test-report.md](docs/test-report.md).

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [setup-xampp.md](docs/setup-xampp.md) | Instalasi lokal dari nol sampai laporan pertama |
| [deployment.md](docs/deployment.md) | HTTPS, konfigurasi produksi, scheduler, backup/restore, rollback |
| [architecture.md](docs/architecture.md) | Area aplikasi, service, sesi, penyimpanan berkas, outbox |
| [database.md](docs/database.md) | Skema, relasi, indeks, aturan data |
| [roles-permissions.md](docs/roles-permissions.md) | Role preset, permission, lingkup objek |
| [workflows.md](docs/workflows.md) | Status tiket, loket, pelacakan anonim, SLA, rujukan |
| [cms.md](docs/cms.md) | Registry section, versi draft, snapshot publikasi, pratinjau, penjadwalan |
| [data-desa.md](docs/data-desa.md) | Dataset berversi, verifikasi nilai, aturan visualisasi, CSV |
| [profil-dan-organisasi.md](docs/profil-dan-organisasi.md) | Blok profil berversi, linimasa, struktur organisasi dinamis |
| [fasilitas-dan-umkm.md](docs/fasilitas-dan-umkm.md) | Direktori fasilitas, lokasi publik, dan profil UMKM berizin |
| [keuangan.md](docs/keuangan.md) | APBDes: revisi terpisah, validasi, snapshot publik |
| [aset-qr-dan-gudang.md](docs/aset-qr-dan-gudang.md) | Register dan unit aset, QR publik, audit fisik, ledger gudang |
| [compatibility-php83.md](docs/compatibility-php83.md) | Versi aktual, patch CI3, bukti uji |
| [dependencies.md](docs/dependencies.md) | Daftar dependensi, versi, lisensi, cara update |
| [design-system.md](docs/design-system.md) | Warna, tipografi, komponen, motion |
| [source-inventory.md](docs/source-inventory.md) | Register dokumen S1–S4 dan status impor |
| [data-issues.md](docs/data-issues.md) | Konflik data sumber dan keputusan sistem |
| [content-needed.md](docs/content-needed.md) | Konten/aset yang perlu dilengkapi pengelola |
| [data-demo.md](docs/data-demo.md) | Data contoh untuk peragaan: mana yang nyata, mana yang karangan, cara menghapusnya |
| [security.md](docs/security.md) | Autentikasi, privasi, berkas, kunci, keterbatasan |
| [test-report.md](docs/test-report.md) | Hasil uji per ID skenario |
| [user-guide.md](docs/user-guide.md) | Panduan warga, petugas, admin, penerbit |
| [progress.md](docs/progress.md) | Status tahap implementasi |

## Status modul

Status tiap modul disimpan di database dan diatur lewat **Pengaturan → Modul aplikasi**. Modul yang belum dibangun
berstatus `disabled`, sehingga route-nya tertutup dan tidak ada klaim fitur yang belum ada:

| Aktif | Publik baca saja | Nonaktif (belum dibangun) |
|---|---|---|
| Website publik, pengaduan/aspirasi, akun warga, berita, agenda, galeri, dokumen publik, data desa, potensi | Struktur organisasi | Fasilitas, transparansi anggaran, aset & QR, gudang, UMKM, surat desa |

## Status

Layanan inti (pengaduan, akun warga, CMS dasar, data sumber) berjalan dan teruji pada lingkungan lokal. **Belum siap produksi** sampai: HTTPS dan server target
disiapkan, SMTP dikonfigurasi, konten/aset resmi diisi (lihat `docs/content-needed.md`), serta SOP layanan desa
disahkan. Fitur surat desa (`FEATURE_LETTERS`) sengaja tidak dibangun.

## Data demonstrasi

Aplikasi dapat diisi data contoh untuk diperagakan kepada pemerintah desa:

```
php public/index.php tools seed_demo     # isi data contoh
php public/index.php tools purge_demo    # hapus kembali sampai bersih
```

Setel `DEMO_MODE=true` pada `.env` supaya setiap halaman publik diberi penanda "data contoh"
dan `noindex`. **Wajib `false` dan `purge_demo` sebelum dipakai sungguhan.**

Penjelasan lengkap — mana yang nyata dari dokumen sumber dan mana yang karangan — ada di
[docs/data-demo.md](docs/data-demo.md).
