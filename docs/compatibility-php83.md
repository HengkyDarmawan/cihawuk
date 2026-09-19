# Kompatibilitas PHP 8.3.33

## Versi aktual (diperiksa 16 September 2026)

| Komponen | Versi | Bukti |
|---|---|---|
| PHP CLI | 8.3.33 | `C:\xampp\php-8.3.33\php.exe -v`; `php.ini` dimuat: `C:\xampp\php-8.3.33\php.ini` |
| PHP Apache | 8.3.33 (mod_php, `LoadModule php_module` di `httpd-xampp.conf`) | `tools health` via HTTP pada tahap 1 |
| CodeIgniter | 3.1.13 (`codeigniter/framework` via Composer) | `composer show` |
| Database | MariaDB 10.4.27 | `tools health`: `10.4.27-MariaDB` |
| PHPUnit | 11.5.56 | Header output PHPUnit: `Runtime: PHP 8.3.33` |

`C:\xampp\php` (7.4.33) masih ada di mesin, tetapi tidak dipakai. Composer platform check (`"php": ">=8.3"`) mencegah
aplikasi berjalan pada PHP lama.

## Pemasangan `system/`

`system/` bukan salinan manual: `scripts/install-ci3-system.php` menyalin `vendor/codeigniter/framework/system`,
memverifikasi checksum baseline (SHA-256 dari daftar `sha256 *path` semua berkas, diurutkan):

```
5f0d5870a7993384b78704fb193517ec79e67ca44b9373cbb5f9c9c192498a88
```

lalu menerapkan `patches/ci3-3.1.13-php83.patch` dengan `git apply` (`core.autocrlf=false`). Bila checksum tidak cocok,
skrip berhenti tanpa mengubah apa pun.

```powershell
& 'C:\xampp\php-8.3.33\php.exe' scripts/install-ci3-system.php
```

## Isi patch (minimal)

| Berkas | Perubahan | Masalah PHP 8.x yang ditangani |
|---|---|---|
| `system/core/Controller.php` | `#[AllowDynamicProperties]` pada `CI_Controller` | CI3 memasang library/model sebagai properti dinamis (deprecated 8.2) |
| `system/core/Loader.php` | `#[AllowDynamicProperties]` pada `CI_Loader` | Loader menulis properti dinamis |
| `system/core/Model.php` | `#[AllowDynamicProperties]` pada `CI_Model` | Model mengakses properti controller |
| `system/core/Router.php` | Deklarasi `public $uri` | "Creation of dynamic property CI_Router::$uri" |
| `system/core/URI.php` | Deklarasi `public $config` | "Creation of dynamic property CI_URI::$config" |
| `system/database/DB_driver.php` | Deklarasi `public $failover = array()` | Properti dinamis dari konfigurasi DB |
| `system/core/Input.php` | `method()` meng-cast `REQUEST_METHOD` ke string | `strtolower(null)` deprecated (8.1) saat CLI |

Driver sesi database dan kelas lain yang dipakai (Session, Form_validation, Upload, Security, Query Builder mysqli)
tidak memunculkan deprecation pada jalur yang diuji, sehingga tidak di-patch.

## Keputusan di sisi aplikasi

- Kelas aplikasi (controller, library, model) mendeklarasikan propertinya sendiri; atribut dinamis hanya berasal
  dari core CI3.
- `MY_Controller::_remap` membatasi method yang dapat dipanggil ke method publik yang dideklarasikan controller
  konkret (menggantikan perilaku auto-routing CI3).
- Tidak ada `error_reporting` yang dilonggarkan: `public/index.php` memakai `E_ALL` di semua environment;
  `display_errors` hanya aktif di development.
- Tidak memakai library CI3 yang tidak dirawat untuk kriptografi (`Encryption` CI3 tidak dipakai untuk data aplikasi);
  enkripsi memakai `sodium_crypto_secretbox`, password memakai `password_hash(PASSWORD_ARGON2ID)`.
- Email memakai PHPMailer (bukan `CI_Email`), PDF memakai Dompdf 3, sanitasi HTML memakai HTML Purifier 4.19.
- Zona waktu PHP dan sesi DB diset UTC agar perhitungan SLA deterministik.

## Bukti uji

| Pemeriksaan | Hasil |
|---|---|
| `php public/index.php tools health` (16 Sep 2026) | `Status: SEHAT` — ekstensi, DB, zona waktu, migrasi v3, key, storage, batas upload |
| `vendor/bin/phpunit` (16 Sep 2026) | `OK (63 tests, 375 assertions)` pada PHP 8.3.33; PHPUnit dikonfigurasi `failOnDeprecation`/`failOnNotice`/`failOnWarning`, sehingga deprecation pada kode yang dijalankan di proses PHPUnit (unit/service) menggagalkan run. Untuk proses server HTTP, bukti diambil dari log (lihat baris berikut) |
| Uji HTTP (PHP built-in server 8.3.33) | 15 tes HTTP lulus: login, CSRF, laporan anonim, pelacakan, upload, header keamanan |
| Apache 8.3.33 (`cihawuk.test`) | Halaman publik, `/warga`, `/admin`, form laporan, dan pelacakan diperiksa di browser; tidak ada error PHP di `storage/logs` (threshold error). Satu-satunya entri log adalah "Mail send failed" yang sengaja dipicu tes retry SMTP |
| `C:\xampp\apache\bin\httpd.exe -t` | `Syntax OK` |
| Migration | `migrate` → `migrate 0` → `migrate` tanpa error pada DB test (setiap bootstrap PHPUnit) |

## Yang belum diuji

- MySQL 8 (hanya MariaDB 10.4.27 tersedia).
- PHP 8.4 atau lebih baru.
- Server Linux / Nginx.
