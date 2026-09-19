# Keamanan dan Privasi

Dokumen ini menjelaskan kontrol yang benar-benar diimplementasikan, cara merawat kunci, dan keterbatasan yang
masih ada. Uji terkait: `docs/test-report.md`.

## Autentikasi

| Kontrol | Implementasi |
|---|---|
| Hash password | `password_hash(PASSWORD_ARGON2ID)`, rehash otomatis saat login bila parameter berubah |
| Kebijakan password | 12–256 karakter; menolak daftar password umum, karakter berulang, dan password yang memuat username |
| Login | Username atau email; pesan gagal generik; waktu respons disamakan dengan hash dummy untuk akun tidak dikenal |
| Pembatasan login | Per identifier (5/15 menit, blok 15 menit) dan per IP (30/15 menit) — dua akun di satu IP tetap bisa masuk; blok berakhir otomatis |
| Status akun | `pending_activation` tidak dapat masuk dashboard; `suspended`/`closed` ditolak dan sesinya berakhir |
| Sesi | Driver database; cookie `chw_session` HttpOnly, SameSite=Lax, Secure bila `COOKIE_SECURE=true`; ID diregenerasi saat login/MFA/logout/ganti password |
| Batas sesi | Pengelola idle 30 menit / maksimum 8 jam; warga idle 60 menit / maksimum 12 jam |
| Pencabutan | `user_sessions` + `auth_version`: logout semua perangkat, cabut sesi tertentu, ganti password/role/status mengakhiri sesi lain |
| Logout | Hanya POST dengan CSRF |
| Reautentikasi | Password ulang (berlaku 10 menit) untuk ganti role, pemulihan manual, reset MFA pengguna lain, serta aksi keamanan akun sendiri |
| MFA | TOTP RFC 6238 (±1 langkah, anti-replay), secret terenkripsi, 10 recovery code sekali pakai (di-hash). Status pre-MFA tidak memberi akses dashboard. Pembatasan 5 percobaan/5 menit |
| Wajib MFA | `FEATURE_MFA_ENFORCE_PRIVILEGED=true` memaksa role berhak tinggi mengaktifkan MFA sebelum memakai `/admin` (default `false` agar setup awal bisa berjalan) |
| Remember me | Tidak diaktifkan (`FEATURE_REMEMBER_ME=false`) |
| Super Admin terakhir | Tidak dapat dinonaktifkan atau dicabut role-nya |
| Admin awal | `tools create_admin` interaktif di CLI; password tidak lewat argumen; hanya bila belum ada Super Admin aktif |

## Token aktivasi dan reset

- Format `selector.validator`; hanya selector dan HMAC-SHA256 validator (kunci `APP_TOKEN_PEPPER`) yang disimpan.
- Masa berlaku: reset 30 menit, aktivasi email 3 hari, aktivasi dari loket 7 hari, pemulihan manual 24 jam.
- Sekali pakai; token lama untuk tujuan yang sama dibatalkan saat token baru dibuat; reset mengakhiri semua sesi.
- "Lupa password" selalu menampilkan pesan yang sama, terdaftar atau tidak.
- Tanpa SMTP, aktivasi/pemulihan dilakukan manual oleh pengelola: kode ditampilkan sekali kepada petugas untuk
  diserahkan langsung ke warga, dan tindakan dicatat audit dengan alasan.
- Token, kode akses, dan password tidak ditulis ke log atau audit (audit memfilter kunci seperti
  `pass|token|secret|access_code|otp|cookie|session|nik|recovery` serta isi pesan).

## Laporan anonim dan kode akses

- Nomor tiket acak (`CHW-YYYY-` + 8 karakter Crockford base32), bukan ID berurutan.
- Kode akses 16 byte acak (ditampilkan dalam grup), disimpan hanya sebagai HMAC; verifikasi waktu-konstan.
- Kode akses hanya ditampilkan sekali pada halaman bukti (tanpa cache, maks. 15 menit di sesi) dan tidak pernah ada
  di URL, query string, log, email, atau analytics. Pelacakan memakai POST.
- Pembatasan: 10 percobaan/15 menit per IP dan 5 per nomor tiket (blok 30 menit), pesan gagal generik.
- Grant pelacakan terikat satu tiket dan satu sesi (fingerprint), berlaku 30 menit, dapat dicabut dengan **Keluar**.
- Tiket anonim tidak menyimpan `reporter_user_id`, nama, atau kontak, termasuk bila pengirim sedang login dan memilih
  tidak mengaitkan akun. IP pengirim tidak disimpan pada tiket (hanya kunci HMAC di tabel pembatas yang kedaluwarsa).

## Otorisasi

- Deny-by-default: route eksplisit, `_remap` hanya memanggil method yang dideklarasikan, permission dicek per method.
- Status modul diperiksa sebelum method dijalankan: URL modul nonaktif menghasilkan 404 walaupun diketahui, modul
  `internal_only` tertutup dari publik, dan `maintenance` menghasilkan 503. Perubahan status memerlukan
  `settings.feature.manage` + alasan dan tercatat pada histori, audit, serta peristiwa operasional.
- Akses objek tiket dicek di server untuk detail, daftar, badge, statistik, lampiran, dan ekspor
  (`docs/roles-permissions.md`). Di `/warga`, tiket milik orang lain menghasilkan 404 (keberadaan tidak dibocorkan);
  di `/admin`, tiket di luar lingkup menghasilkan 403; lampiran tanpa hak menghasilkan 403.
- Field allowlist: form warga tidak dapat mengubah status, pemilik, role, atau unit.
- Tiket rahasia, konflik kepentingan, dan pembukaan identitas diaudit.

## CSRF, header, dan input

- CSRF CI3 untuk semua POST (token berputar); AJAX menerima token baru saat gagal.
- Header: `Content-Security-Policy` (`default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'`;
  `style-src` masih mengizinkan `'unsafe-inline'` untuk atribut style library), `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` (geolokasi hanya self), `X-Request-Id`.
- Halaman auth, `/warga`, `/admin`, `/lapor`, `/lacak`: `Cache-Control: no-store` + `noindex`.
- Output di-escape sesuai konteks (`e()`, `e_url()`, JSON di `<script type="application/json">`).
- HTML CMS disanitasi HTML Purifier (allowlist tag/atribut, tautan http/https/mailto, `rel="noopener"`).
- Query memakai Query Builder/binding; sort dan filter DataTables memakai allowlist kolom.
- `base_url` hanya `APP_BASE_URL` atau alamat di allowlist `APP_BASE_URL_ALIASES`; Host header lain diabaikan.
- Redirect internal divalidasi (`app_safe_redirect_path`) untuk mencegah open redirect.
- Pencarian publik dibatasi 60 permintaan/5 menit per IP.

## Berkas

| Kontrol | Implementasi |
|---|---|
| Jenis | Allowlist JPG, PNG, WebP, PDF; SVG/HTML/skrip ditolak; ekstensi ganda mencurigakan ditolak |
| Validasi isi | `finfo` harus cocok dengan ekstensi + pemeriksaan signature byte |
| Gambar | Decode dan encode ulang dengan GD (metadata EXIF/GPS dibuang), batas piksel 40 MP dan sisi 10.000 px |
| PDF | Disimpan apa adanya, diunduh sebagai attachment |
| Ukuran | 5 MB per berkas, maks. 3 berkas / 15 MB per laporan; `post_max_size` dilaporkan 413 |
| Penyimpanan | Di luar `public/` (`STORAGE_PRIVATE_PATH`), nama acak tanpa ekstensi, izin 0640 |
| Unduhan | Controller `Berkas` memeriksa hak atas tiket induk dan visibilitas lampiran; `Content-Disposition` + `nosniff` |
| Media: berkas asli | Disimpan privat (`private_files.purpose = media_original`), tanpa URL publik |
| Media: derivative publik | Nama acak di `public/media`, dibuat ulang dengan GD (metadata EXIF hilang); `.htaccess` menolak eksekusi skrip; media hak `unknown` tetap draft dan teks alternatif wajib sebelum terbit |
| Pemindaian virus | **Tidak ada** — `scan_status = not_scanned` (lihat keterbatasan) |

## Data pribadi

- Kontak pelapor (nama/email/telepon) dienkripsi `sodium_crypto_secretbox` (kunci `APP_ENCRYPTION_KEY`).
- NIK tidak diminta di mana pun.
- Lokasi GPS hanya dikirim bila pelapor menekan tombol "Gunakan lokasi perangkat".
- Pencarian warga di loket menampilkan data termasking.
- Ekspor tidak memuat kontak; berkas ekspor privat dan kedaluwarsa otomatis.
- Halaman Privasi dan Ketentuan tersedia; kontak pengelola data perlu dikonfirmasi (`docs/content-needed.md`).

## Kunci dan rotasi

| Variabel | Fungsi |
|---|---|
| `APP_ENCRYPTION_KEY` | Enkripsi kontak pelapor dan data privat |
| `APP_MFA_KEY` | Enkripsi secret TOTP |
| `APP_TOKEN_PEPPER` | HMAC token akun dan kode akses tiket |
| `APP_RATE_LIMIT_KEY` | HMAC kunci pembatas (IP/identifier tidak disimpan mentah) |
| `APP_CI_ENCRYPTION_KEY` | `encryption_key` CI3 (tidak dipakai untuk data aplikasi) |

- Dibuat dengan `tools generate_keys` (hanya mengisi yang kosong, nilai tidak dicetak). `tools health` memeriksa
  keberadaan dan panjangnya.
- Ciphertext diberi prefiks versi (`v1:`). Rotasi kunci enkripsi: pindahkan kunci lama ke
  `APP_ENCRYPTION_KEY_V1` (atau `APP_MFA_KEY_V1`), isi kunci baru, naikkan `APP_ENCRYPTION_KEY_VERSION` ke 2. Data
  lama tetap terbaca; data baru memakai kunci baru. **Belum ada perintah re-enkripsi massal**, jadi kunci lama
  harus disimpan selama data lama masih ada.
- Mengganti `APP_TOKEN_PEPPER` membatalkan semua token akun dan **semua kode akses tiket anonim** — hanya lakukan
  bila pepper bocor, dan siapkan komunikasi ke pelapor.
- Kehilangan `.env` berarti kontak terenkripsi dan MFA tidak dapat dipulihkan; backup `.env` terpisah dengan akses
  terbatas.

## Operasional

- Akun database aplikasi hanya DML; migration memakai akun terpisah dari CLI.
- `Tools` hanya berjalan di CLI; request HTTP ke `/tools/*` mendapat 404.
- Direktori `application/`, `system/`, `vendor/`, `storage/`, `reference/`, `.env` tidak berada di document root
  dan ditolak web server (OPS-01).
- Job berkala memakai `job_locks`; email outbox memakai dedupe key sehingga dua scheduler tidak mengirim ganda.
- Audit log mencatat login, perubahan akun/role, akses identitas, perubahan status tiket, ekspor, publikasi konten,
  review data, perubahan pengaturan, dan perubahan status modul, dengan request ID.
- Setiap entri audit menyimpan kode modul, **HMAC IP** (kunci `APP_RATE_LIMIT_KEY`, bukan alamat mentah), dan
  ringkasan user agent tanpa angka versi. Halaman audit dapat difilter per aksi, entitas, modul, dan request ID.

## Keterbatasan yang tersisa

- Tidak ada pemindaian antivirus lampiran (`not_scanned`); petugas diminta tidak membuka PDF dari sumber tidak
  dikenal di luar penampil yang aman. Integrasi ClamAV disarankan sebelum produksi.
- Pembukaan identitas pelapor mewajibkan alasan dan diaudit, tetapi belum meminta reautentikasi.
- CSP `style-src` masih `'unsafe-inline'`.
- Pembatas laju berbasis IP dapat terpengaruh NAT operator seluler; batas dibuat longgar dan berakhir otomatis.
  Di balik reverse proxy, isi `APP_TRUSTED_PROXIES` agar IP klien terbaca benar.
- MFA untuk pengelola belum dipaksa secara default; aktifkan `FEATURE_MFA_ENFORCE_PRIVILEGED` setelah onboarding.
- Belum ada re-enkripsi massal untuk rotasi kunci.
- HTTPS, HSTS, dan `COOKIE_SECURE` bergantung konfigurasi server produksi (belum ada server produksi).
- Email belum diuji dengan SMTP nyata (hanya mode capture pada tes).
- Belum ada uji penetrasi pihak ketiga.

---

## Field sensitif pada modul tahap 4–12

Aturan berikut ditegakkan service dan diuji langsung terhadap keluaran publik, bukan hanya
lewat pemanggilan service.

| Data | Disimpan sebagai | Tidak pernah keluar ke |
|---|---|---|
| Nomor seri unit aset | `asset_units.serial_number_ciphertext` (terenkripsi) | Halaman QR publik, HTML mana pun |
| Nomor dokumen kepemilikan aset | `asset_documents.document_number_ciphertext` | Halaman publik |
| Nama peminjam aset | `asset_loans.borrower_name_ciphertext` | Halaman publik |
| Nomor SK penugasan | `org_assignments.decree_number_ciphertext` | Snapshot dan halaman struktur |
| Token QR aset | Hanya digest SHA-256 pada `asset_qr_tokens.token_digest` | Basis data, audit log, dan log mana pun |
| Nilai perolehan aset | `asset_registers.acquisition_value` | Halaman QR publik; dashboard hanya untuk `assets.view_financial` |
| Koordinat lokasi sensitif | `places.is_sensitive`, `asset_locations.is_sensitive` | Peta publik, atribut DOM, dan JSON publik |
| Kontak fasilitas dan UMKM | Kolom biasa, tetapi dibatasi penanda izin | Halaman publik bila izinnya tidak tercatat atau dicabut |
| Foto orang pada struktur | `people.photo_consent` | Snapshot bila izinnya belum dicatat |
| Nilai statistik di bawah ambang | `statistic_values.suppressed` | Angka aslinya; halaman menulis `<10` secara terbuka |

Halaman QR aset selalu memakai header `X-Robots-Tag: noindex, nofollow`, meta robots
`noindex`, tidak masuk sitemap maupun pencarian, dan dibatasi rate limit `asset_qr_ip`.

Unit aset yang masih `draft` tidak diakui publik meski tokennya sah, sehingga barang yang
belum dipastikan keberadaannya tidak pernah "dikonfirmasi" oleh situs desa.

