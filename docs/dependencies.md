# Dependensi

Semua dependensi diambil dari registry resmi (Packagist, npm) dan dilayani dari server sendiri — **tanpa CDN**.
Versi di bawah adalah versi yang terpasang dan diuji pada 16 September 2026.

## PHP (Composer)

| Paket | Versi | Lisensi | Fungsi |
|---|---|---|---|
| `codeigniter/framework` | 3.1.13 | MIT | Framework; `system/` disalin + patch (`docs/compatibility-php83.md`) |
| `vlucas/phpdotenv` | 5.7.0 | BSD-3-Clause | Memuat `.env` / `.env.testing` |
| `phpmailer/phpmailer` | 6.12.0 | LGPL-2.1-only | Pengiriman email outbox via SMTP |
| `dompdf/dompdf` | 3.1.6 | LGPL-2.1 | Ekspor ringkasan PDF (remote fetch dimatikan) |
| `ezyang/htmlpurifier` | 4.19.0 | LGPL-2.1-or-later | Sanitasi HTML konten CMS (allowlist) |
| `phpunit/phpunit` (dev) | 11.5.56 | BSD-3-Clause | Pengujian unit/service/HTTP |

Dependensi turunan (produksi): `dompdf/php-font-lib` 1.0.2, `dompdf/php-svg-lib` 1.0.2, `masterminds/html5` 2.11.0,
`sabberworm/php-css-parser` 9.4.0, `thecodingmachine/safe` 3.4.0, `graham-campbell/result-type` 1.2.0,
`phpoption/phpoption` 1.10.0 (Apache-2.0), `symfony/polyfill-ctype` 1.37.0, `symfony/polyfill-mbstring` 1.38.2,
`symfony/polyfill-php80` 1.37.0 (MIT).

Ekstensi PHP wajib (dicek `composer check-platform-reqs`): dom, fileinfo, gd, mbstring, mysqli, openssl, sodium, zip;
intl dicek oleh `tools health`.

TOTP diimplementasikan sendiri (`application/libraries/Totp.php`, RFC 6238, diuji dengan test vector resmi) sehingga
tidak ada paket TOTP tambahan.

### Update PHP

```powershell
composer outdated --direct
composer update <paket> --with-dependencies
composer check-platform-reqs
& 'C:\xampp\php-8.3.33\php.exe' vendor/bin/phpunit
```

Untuk CodeIgniter: jangan menyalin `system/` manual. Bila versi CI3 berubah, perbarui checksum baseline dan patch di
`scripts/install-ci3-system.php` / `patches/`, lalu jalankan skrip dan seluruh tes.

## Frontend (npm, disalin ke `public/assets/vendor`)

Sumber: `scripts/assets/package.json` (versi dipin persis). `copy-vendor-assets.mjs` menyalin hanya berkas yang
dipakai + lisensinya dan menulis `public/assets/vendor/manifest.json` berisi versi, lisensi, dan SHA-256 tiap berkas.

| Paket | Versi | Lisensi | Dipakai di | Fungsi |
|---|---|---|---|---|
| bootstrap | 5.3.8 | MIT | Situs publik | Grid, komponen dasar |
| bootstrap (alias `bootstrap4`) | 4.6.2 | MIT | Dashboard | Basis SB Admin 2 |
| startbootstrap-sb-admin-2 | 4.1.4 | MIT | Dashboard | Layout sidebar/topbar (halaman demo tidak disalin) |
| jquery | 3.7.1 | MIT | Dashboard | Dibutuhkan SB Admin 2, DataTables, Select2 |
| jquery.easing | 1.4.1 | BSD-3-Clause | Dashboard | Dependensi SB Admin 2 |
| @fortawesome/fontawesome-free | 5.15.4 | CC-BY-4.0 / OFL-1.1 / MIT | Dashboard | Ikon SB Admin 2 |
| datatables.net + datatables.net-bs4 | 3.0.4 | MIT | Dashboard | Tabel server-side |
| select2 | 4.1.0 | MIT | Dashboard | Pilihan dengan pencarian (warga, petugas) |
| @ttskch/select2-bootstrap4-theme | 1.5.2 | MIT | Dashboard | Tema Select2 |
| sweetalert2 | 11.26.25 | MIT | Dashboard | Dialog konfirmasi aksi |
| chart.js | 4.5.1 | MIT | Situs + dashboard | Grafik statistik/tren |
| swiper | 14.2.0 | MIT | Situs publik | Carousel potensi/galeri |
| leaflet | 1.9.4 | BSD-2-Clause | Situs publik | Peta (diinisialisasi hanya bila ada titik terverifikasi; saat ini fallback teks) |
| feather-icons | 4.29.2 | MIT | Situs + dashboard | Sprite SVG ikon |
| @fontsource/manrope | 5.3.0 | OFL-1.1 | Situs + dashboard | Font teks |
| @fontsource/playfair-display | 5.3.0 | OFL-1.1 | Situs publik | Font judul |
| three | 0.186.0 | MIT | Situs publik | Scene hero, **dibundel** (lihat bawah) |
| esbuild (dev) | 0.25.10 | MIT | Build | Bundel scene Three.js |

### Three.js

`scripts/assets/src/hero-scene.js` di-bundle menjadi `public/assets/site/js/hero-scene.bundle.js` (ESM, minified,
tree-shaken, komentar lisensi di akhir berkas; ±527 KB, ±136 KB gzip). Bundel hanya dimuat oleh
`hero-loader.js` melalui dynamic import saat hero mendekati viewport dan perangkat memenuhi syarat.

### Update frontend

```powershell
cd scripts\assets
npm install          # Node hanya untuk build, tidak dibutuhkan saat runtime
npm run build        # copy + build:three
```

Setelah update: periksa `manifest.json` (versi/lisensi/hash), jalankan pemeriksaan visual beranda, dashboard,
DataTables, dan formulir; pastikan tidak ada CDN yang ditambahkan (CSP `script-src 'self'` akan memblokirnya).

## Lisensi

Berkas lisensi setiap paket frontend ikut disalin ke folder vendornya. Paket PHP menyimpan lisensinya di `vendor/`.
Proyek ini tidak mengubah kode library pihak ketiga kecuali patch CI3 yang terdokumentasi. Paket LGPL (PHPMailer,
Dompdf, HTML Purifier) dipakai sebagai library tanpa modifikasi.
