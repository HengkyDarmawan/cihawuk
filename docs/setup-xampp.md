# Setup XAMPP (Windows)

Panduan dari folder kosong sampai login dan laporan pertama. Diuji pada mesin pengembangan 15–16 September 2026.

## 1. Kenali instalasi PHP yang benar

XAMPP di mesin ini memiliki **dua** folder PHP:

| Folder | Versi | Dipakai oleh |
|---|---|---|
| `C:\xampp\php-8.3.33` | 8.3.33 | Apache (`httpd-xampp.conf`: `LoadModule php_module`, `PHPIniDir`) dan `php` di PATH |
| `C:\xampp\php` | 7.4.33 | Sisa instalasi lama — **jangan dipakai** |

Selalu gunakan path penuh:

```powershell
$php = 'C:\xampp\php-8.3.33\php.exe'
& $php -v          # PHP 8.3.33
& $php -m          # cek ekstensi
composer --version # harus melaporkan "PHP version 8.3.33"
```

## 2. Ekstensi dan batas upload

Di `C:\xampp\php-8.3.33\php.ini` (buat backup dulu) pastikan:

```ini
extension=mysqli
extension=mbstring
extension=fileinfo
extension=openssl
extension=gd
extension=intl
extension=zip
extension=sodium        ; dipakai untuk enkripsi data privat & MFA
upload_max_filesize = 5M
post_max_size = 20M
```

Pada mesin ini `extension=sodium` semula ter-comment dan batas upload 2M/8M; sudah diubah (backup:
`php.ini.bak-cihawuk-20260915`). Restart Apache setelah mengubah php.ini.

## 3. Database

Server XAMPP ini adalah **MariaDB 10.4.27**. Buat database dan dua akun terpisah (jalankan sebagai root, sekali):

```sql
CREATE DATABASE cihawuk_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cihawuk_digital_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- akun aplikasi: DML saja
CREATE USER 'cihawuk_app'@'localhost' IDENTIFIED BY '<password-acak>';
CREATE USER 'cihawuk_app'@'127.0.0.1' IDENTIFIED BY '<password-acak>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES ON cihawuk_digital.* TO 'cihawuk_app'@'localhost', 'cihawuk_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES ON cihawuk_digital_test.* TO 'cihawuk_app'@'localhost', 'cihawuk_app'@'127.0.0.1';
-- akun migration: DDL, hanya untuk CLI
CREATE USER 'cihawuk_migrate'@'localhost' IDENTIFIED BY '<password-acak-lain>';
CREATE USER 'cihawuk_migrate'@'127.0.0.1' IDENTIFIED BY '<password-acak-lain>';
GRANT ALL PRIVILEGES ON cihawuk_digital.* TO 'cihawuk_migrate'@'localhost', 'cihawuk_migrate'@'127.0.0.1';
GRANT ALL PRIVILEGES ON cihawuk_digital_test.* TO 'cihawuk_migrate'@'localhost', 'cihawuk_migrate'@'127.0.0.1';
```

> Catatan mesin ini: tabel sistem Aria MariaDB sempat rusak (sudah ada sebelum proyek; kemudian log Aria juga rusak
> setelah proses mysqld dihentikan paksa). Diperbaiki pada 16 September 2026: backup folder `data\mysql`,
> `aria_chk -r`, log Aria lama dipindahkan (bukan dihapus), lalu `REPAIR TABLE` untuk tabel sistem. Semua
> `CHECK TABLE` kini OK. Selalu hentikan MariaDB lewat XAMPP Control Panel atau `mysqladmin shutdown`, bukan menutup
> jendela konsol.

## 4. Dependensi dan environment

```powershell
cd C:\xampp\htdocs\cihawuk
composer install
composer check-platform-reqs
copy .env.example .env
```

Isi `.env`: `DB_PASSWORD`, `DB_MIGRATE_PASSWORD`, `STORAGE_PRIVATE_PATH=C:/xampp/htdocs/cihawuk/storage/private`,
`APP_BASE_URL=http://cihawuk.test/`. Untuk pengujian, salin menjadi `.env.testing` dengan `APP_ENV=testing`,
`DB_DATABASE=cihawuk_digital_test`, dan `STORAGE_PRIVATE_PATH` ke folder terpisah (mis. `storage/testing/private`).

```powershell
& $php public/index.php tools generate_keys                  # .env
$env:APP_ENV='testing'; & $php public/index.php tools generate_keys; Remove-Item Env:APP_ENV   # .env.testing
```

Generator hanya mengisi key yang kosong dan tidak mencetak nilainya. Simpan salinan `.env` terpisah dengan akses terbatas —
tanpa `APP_ENCRYPTION_KEY` yang sama, kontak pelapor terenkripsi tidak dapat dibaca.

## 5. Virtual host Apache

Ditambahkan ke `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (backup: `httpd-vhosts.conf.bak-cihawuk-20260915`):

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"
    # http://localhost/cihawuk/ hanya memetakan folder public/
    Alias "/cihawuk" "C:/xampp/htdocs/cihawuk/public"
</VirtualHost>

<Directory "C:/xampp/htdocs/cihawuk">
    Options -Indexes
    AllowOverride None
    Require all denied
</Directory>

<Directory "C:/xampp/htdocs/cihawuk/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<VirtualHost *:80>
    ServerName cihawuk.test
    DocumentRoot "C:/xampp/htdocs/cihawuk/public"
    ErrorLog "logs/cihawuk.test-error.log"
    CustomLog "logs/cihawuk.test-access.log" common
</VirtualHost>
```

VirtualHost `localhost` pertama menjaga proyek lain di `htdocs` tetap berjalan seperti semula.

Aplikasi dapat dibuka di dua alamat:

- **http://localhost/cihawuk/** — langsung bisa dipakai tanpa mengubah hosts. `Alias` hanya membuka folder
  `public/`, sehingga `/cihawuk/.env`, `/cihawuk/application/...`, dan `/cihawuk/storage/...` tetap 403/404.
  Tambahkan alamat ini di `.env`: `APP_BASE_URL_ALIASES=http://localhost/cihawuk/` (daftar alamat yang diizinkan;
  tautan dan aset mengikuti alamat yang sedang dibuka, Host lain diabaikan).
- **http://cihawuk.test/** — butuh entri hosts di bawah. Tautan email/CLI memakai `APP_BASE_URL`.

`public/.htaccess` tidak memakai `RewriteBase` tetap, sehingga sama-sama berfungsi di root maupun di `/cihawuk`.

Backup vhosts sebelum Alias: `httpd-vhosts.conf.bak-cihawuk-20260916-alias`.

**Langkah manual yang harus dilakukan pemilik mesin** (butuh hak administrator): tambahkan baris berikut ke
`C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1   cihawuk.test
```

Periksa konfigurasi lalu restart Apache dari XAMPP Control Panel:

```powershell
C:\xampp\apache\bin\httpd.exe -t      # Syntax OK
```

> Apache di mesin ini berjalan sebagai proses konsol (bukan service) dan membutuhkan jendela konsol sendiri. Menutup
> jendela tersebut menghentikan Apache. Cara paling aman: tombol Stop/Start di XAMPP Control Panel.

## 6. Skema, data master, dan admin

```powershell
& $php public/index.php tools migrate        # memakai akun DB_MIGRATE_*
& $php public/index.php tools seed           # idempotent; tidak menimpa data yang sudah diedit
& $php public/index.php tools create_admin   # interaktif; password tidak lewat argumen
& $php public/index.php tools import_sources # impor S1/S2/S4 dari reference/documents (opsional)
& $php public/index.php tools health
```

`create_admin` hanya berjalan bila belum ada Super Admin aktif. Tidak ada password admin bawaan.

Letakkan dokumen sumber di `reference/documents/` (folder ini tidak dilayani web dan diabaikan git).

## 7. Job berkala (Windows Task Scheduler)

Job: kirim outbox email, eskalasi SLA, pengingat tanggapan, ekspor, pembersihan. Aman dijalankan bersamaan (memakai `job_locks`).

```powershell
schtasks /Create /TN "Cihawuk run_jobs" /SC MINUTE /MO 5 /RL LIMITED `
  /TR "\"C:\xampp\php-8.3.33\php.exe\" \"C:\xampp\htdocs\cihawuk\public\index.php\" tools run_jobs"
```

Saat pengembangan, jalankan manual: `& $php public/index.php tools run_jobs`.

## 8. Smoke test

| Periksa | Cara | Hasil yang diharapkan |
|---|---|---|
| Kesehatan | `tools health` | `Status: SEHAT` |
| Beranda | http://cihawuk.test/ | 200, hero tampil |
| Berkas terlindungi | http://cihawuk.test/.env | 403 |
| Laporan anonim | `/lapor` → kirim | Halaman bukti dengan nomor tiket + kode akses |
| Pelacakan | `/lacak` | Detail laporan tampil dengan kode yang benar |
| Login admin | `/masuk` | Dashboard `/admin` |
| Input loket | `/admin/laporan/buat` (role Petugas Loket/Admin Pelayanan) | Tiket kanal loket tersimpan |
| Unggah privat | Kirim laporan dengan foto | Berkas ada di `storage/private/YYYY/MM/`, tidak di `public/` |
| Tes otomatis | `vendor/bin/phpunit` | Semua lulus |

## 9. Server pratinjau tanpa Apache (opsional)

```powershell
$env:APP_BASE_URL='http://127.0.0.1:8765/'
& $php -S 127.0.0.1:8765 -t public scripts/dev-router.php
```

Hanya untuk pengembangan/pengujian; bukan konfigurasi produksi.
