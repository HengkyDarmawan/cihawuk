# Deployment

Aplikasi belum dideploy ke server produksi. Dokumen ini adalah prosedur yang harus diikuti; jalankan terlebih
dahulu di staging.

## Prasyarat server

- PHP 8.3.x (uji patch keamanan PHP di staging sebelum mengganti runtime produksi) dengan ekstensi:
  mysqli, mbstring, fileinfo, openssl, gd, intl, zip, sodium, dom.
- MySQL 8 atau MariaDB 10.4+ (aplikasi hanya diuji pada MariaDB 10.4.27 — uji ulang bila memakai MySQL).
- Apache 2.4 dengan `mod_rewrite` dan `mod_headers` (atau Nginx dengan aturan setara).
- HTTPS dengan sertifikat valid.
- Composer 2 (hanya saat deploy).

## Langkah

1. Salin kode (tanpa `vendor/`, `.env`, `storage/*`, `reference/documents/*`) ke direktori di luar webroot, mis.
   `/srv/cihawuk`.
2. `composer install --no-dev --optimize-autoloader` lalu `composer check-platform-reqs`.
3. DocumentRoot web server **harus** `/srv/cihawuk/public`. `application/`, `system/`, `vendor/`, `storage/`,
   `.env`, `reference/` tidak boleh dapat diakses (uji dengan request langsung — lihat OPS-01).
4. Buat `.env` produksi dari `.env.example`:
   - `APP_ENV=production`, `APP_BASE_URL=https://<domain>/`, `APP_DISPLAY_ERRORS=false`
   - `COOKIE_SECURE=true`
   - `APP_TRUSTED_PROXIES=<ip proxy>` hanya bila ada reverse proxy
   - kredensial DB aplikasi (DML) dan migration (DDL) terpisah
   - `STORAGE_PRIVATE_PATH=/srv/cihawuk-data/private` (di luar webroot, izin 750, pemilik user web server)
   - SMTP: `MAIL_ENABLED=true`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`
   - `FEATURE_MFA_ENFORCE_PRIVILEGED=true` setelah semua akun pengelola mengaktifkan MFA
5. `php public/index.php tools generate_keys` — **backup `.env` segera** ke penyimpanan terpisah.
6. `php public/index.php tools migrate`, `tools seed`, `tools create_admin`, `tools health`.
7. Izin berkas: `storage/logs`, `storage/cache`, `storage/tmp`, `public/media` dapat ditulis web server; kode
   aplikasi read-only. `public/media/.htaccess` mematikan eksekusi skrip (Nginx: tambahkan aturan setara).
8. PHP produksi: `display_errors=Off`, `log_errors=On`, `upload_max_filesize=5M`, `post_max_size=20M`,
   `session.use_strict_mode=1`, `expose_php=Off`.
9. Scheduler (cron Linux):

   ```cron
   */5 * * * * www-data /usr/bin/php /srv/cihawuk/public/index.php tools run_jobs >> /srv/cihawuk/storage/logs/jobs.log 2>&1
   ```

10. Monitoring: periksa `storage/logs/`, jumlah outbox `failed`, dan status job pada **Pengaturan → Informasi sistem**.

## Backup dan pemulihan

- Windows/XAMPP: `scripts/backup.ps1 -Destination <folder>` membuat dump DB (`--single-transaction`), salinan
  `storage/private`, `public/media`, `.env`, dan `manifest.txt` (jumlah berkas + SHA-256 dump).
- Linux: padanan dengan `mysqldump --single-transaction`, `rsync` folder privat/media, dan salinan `.env`.
- Simpan backup `.env` (berisi key enkripsi) terpisah dari backup data, dengan akses terbatas.
- **Uji pemulihan** secara berkala: `scripts/restore-check.ps1 -BackupDir <folder> -TargetDatabase <db_baru>`
  memverifikasi checksum, memulihkan ke database baru, membandingkan jumlah baris tabel inti, dan memastikan setiap
  `private_files` ada di backup. Lalu jalankan smoke test aplikasi terhadap target tersebut. Backup yang belum
  pernah diuji pulih belum dianggap terbukti.

Hasil uji pemulihan terakhir: lihat `docs/test-report.md` (OPS-02).

## Rollback

1. Aktifkan halaman pemeliharaan (mis. arahkan vhost ke halaman statis).
2. Kembalikan kode ke rilis sebelumnya.
3. Bila rilis baru mengubah skema: `php public/index.php tools migrate <versi_sebelumnya>` **hanya** bila migration
   `down()` tidak menghapus data penting; bila ragu, pulihkan database dari backup yang diambil tepat sebelum deploy.
4. Jalankan `tools health` dan smoke test, lalu buka kembali akses.

## Checklist sebelum go-live

- [ ] HTTPS aktif, `COOKIE_SECURE=true`
- [ ] Request langsung ke `/.env`, `/application/`, `/vendor/`, `/storage/` ditolak
- [ ] SMTP terkirim ke alamat uji; outbox tidak `failed`
- [ ] Semua akun pengelola memakai MFA
- [ ] Backup terjadwal dan satu kali uji pemulihan berhasil
- [ ] Konten resmi, logo, kontak, koordinat, dan pejabat terkini diisi (`docs/content-needed.md`)
- [ ] Kalender kerja dan hari libur resmi diisi; tanda "contoh" dihapus
- [ ] SOP layanan dan target waktu disetujui pemerintah desa
- [ ] Data demo tidak ada di produksi (`tools purge_demo` hanya untuk dev/testing)

## Sebelum go-live: hapus data demonstrasi

Basis data pengembangan berisi data contoh agar aplikasinya dapat diperagakan. Wajib
dibersihkan sebelum dipakai sungguhan:

```
php public/index.php tools purge_demo
```

Lalu pada `.env`:

```
DEMO_MODE=false
```

Periksa juga:

- Tidak ada akun berakhiran `.demo` yang tersisa (`SELECT username FROM users WHERE username LIKE '%.demo'`).
- Tabel `demo_records` kosong.
- Halaman publik kembali menampilkan keadaan kosong pada modul yang memang belum berisi data resmi.

Rinciannya di `docs/data-demo.md`.
