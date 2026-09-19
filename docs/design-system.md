# Design System

Sumber token: `public/assets/site/css/site.css` (situs publik) dan `public/assets/admin/css/dashboard.css`
(dashboard, prefix `--chw-`). Arah visual: tenang, hijau pegunungan dan aksen emas, dengan fotografi sebagai pusat
perhatian setelah foto resmi tersedia.

## Warna

| Token | Nilai | Pemakaian | Kontras (WCAG) |
|---|---|---|---|
| `--color-primary` | `#174B3A` | Tombol utama, tautan, header | 9,98:1 pada putih |
| `--color-primary-dark` | `#10392D` | Hover, footer | 12,76:1 dengan teks putih |
| `--color-primary-soft` | `#E6EFEA` | Latar lembut, hover menu | — |
| `--color-accent` | `#D7AF67` | Tombol aksen (teks `--color-ink`), highlight | 7,25:1 dengan teks ink |
| `--color-accent-dark` | `#8A6420` | Focus ring, label kecil | 5,35:1 pada putih |
| `--color-ink` | `#182B24` | Teks utama | 14,07:1 pada surface |
| `--color-muted` | `#596A62` | Teks sekunder | 5,73:1 putih / 5,42:1 surface |
| `--color-surface` | `#F7F9F6` | Latar halaman | — |
| `--color-border` | `#DCE5DE` | Garis, border input | — |
| `--color-danger` | `#A3261B` | Error | 7,37:1 pada putih |
| `--color-warning-ink` | `#6B4A00` | Teks peringatan | 8,06:1 pada putih |
| `--color-success` | `#1E6B3F` | Berhasil | 6,5:1 pada putih |

Seluruh pasangan teks di atas memenuhi WCAG AA (≥4,5:1). Status tidak pernah dibedakan hanya dengan warna: badge
tiket selalu memuat ikon + teks (`ticket_status_badge()`).

## Tipografi

| Peran | Font | Ukuran |
|---|---|---|
| Teks | Manrope 400–800 (self-hosted, OFL) | 16px dasar, line-height 1.6 |
| Judul hero | Playfair Display 700 | `clamp(2.25rem, 1.1rem + 4.6vw, 4.5rem)`, maks 14ch, `text-wrap: balance` |
| Judul section | Playfair Display 700 | `clamp(1.9rem, 1.35rem + 2.2vw, 3rem)` |
| Subjudul hero | Manrope | `clamp(1.0625rem, .95rem + .5vw, 1.375rem)`, maks 46ch |
| Dashboard | Manrope | 16px; Playfair tidak dipakai |

Font dimuat dengan `font-display: swap` dan subset latin/latin-ext.

## Spasi, radius, bayangan

- Skala spasi: 4, 8, 12, 16, 24, 32, 48, 64, 96 px (`--space-1` … `--space-9`).
- Lebar konten: 1200px (`--content-max`), lebar lebar 1320px; gutter 16px di bawah 576px.
- Radius: input 12px, kartu 20px, pill 999px (tombol).
- Bayangan: `--shadow-card` (diam) dan `--shadow-lift` (hover).
- Tinggi header: 76px (`--header-h`); `scroll-padding-top` mencegah heading tertutup header saat lompat anchor.

## Breakpoint

Mengikuti Bootstrap 5: `<576`, `≥576`, `≥768`, `≥992`, `≥1200`. Diperiksa pada 360, 390, 768, 1024, 1440 px.

| Komponen | Mobile | ≥768 | ≥992 | ≥1200 |
|---|---|---|---|---|
| Akses cepat | 2 kolom, deskripsi disembunyikan | 2 kolom | 4 kolom | 4 kolom |
| Statistik | 2 kolom | 2 kolom | 4 kolom | 4 kolom |
| Potensi / berita | 1 kolom | 2 kolom | 2 kolom | 3 kolom |
| Formulir laporan | Stepper 3 langkah | Stepper | Semua langkah tampil berurutan | — |
| Navigasi | Drawer (offcanvas) | Drawer | Menu horizontal | — |

## Komponen

| Komponen | Catatan |
|---|---|
| Header | Transparan di atas hero, menjadi putih + bayangan saat digulir (`is-scrolled`); sticky di halaman lain |
| Tombol | Tinggi minimum 44px (`.btn-sm` 40px untuk aksi sekunder), pill, `translateY(-1px)` saat hover |
| Kartu | Radius 20px, border tipis, bayangan lembut |
| Form | Label selalu terlihat; penanda wajib; ringkasan error di atas form dengan tautan ke field; `aria-describedby` untuk hint/error; komponen dari `form_ui_helper.php` |
| Badge status | Ikon Feather + teks + warna (`ticket_status_badge`) |
| Placeholder media | `placeholder_media()`: gradasi hijau dengan siluet bukit SVG dan label "Foto belum tersedia" — jelas belum final, tidak ada foto stok |
| Empty state | Ikon, kalimat penjelasan, dan tautan tindakan berikutnya |
| Badge pratinjau | Tampil di situs publik bila mode pratinjau draft aktif |
| Bukti laporan | Kotak `receipt` dengan nomor + kode akses, tombol salin/cetak; gaya cetak khusus |
| Dashboard | SB Admin 2 dengan warna proyek; sidebar sesuai permission; DataTables server-side dengan kolom nomor/status tidak terpotong |

Ikon: sprite Feather (`icon('nama')`) di situs dan dashboard; Font Awesome hanya untuk bawaan SB Admin 2.

## Motion

- Reveal saat scroll (`.reveal`): opacity + translateY 18px, 0,7 detik; tanpa JS konten langsung terlihat.
- Transisi tombol/header 0,2–0,3 detik.
- `prefers-reduced-motion: reduce`: semua animasi/transisi dimatikan, reveal langsung tampil, canvas hero
  disembunyikan dan Three.js tidak dimuat.

## Scene hero Three.js

- Satu scene abstrak: kontur perbukitan (16 garis × 140 titik dalam satu `LineSegments`) dan kabut partikel
  opsional (dimatikan pada perangkat sentuh).
- Ilustrasi HTML/SVG + gradasi tampil lebih dulu; teks hero tidak bergantung pada WebGL.
- Dimuat hanya bila: tidak reduced-motion, tidak Save-Data/2G, memori perangkat ≥3 GB, bukan perangkat sentuh dengan
  <4 core, dan WebGL tersedia. Pemuatan menunggu hero mendekati viewport lalu `requestIdleCallback`.
- Pixel ratio dibatasi 1,5; animasi berhenti saat tab tersembunyi; `webglcontextlost` → fallback; semua
  geometry/material di-dispose saat unmount. Canvas `aria-hidden`.

## Aksesibilitas

- Skip link "Lewati ke konten utama" di situs dan dashboard.
- Focus ring 3px `--color-accent-dark` (varian terang di atas hero dan sidebar gelap).
- Drawer mobile memakai offcanvas Bootstrap (fokus terkunci, Escape menutup).
- Target sentuh ≥44px untuk tombol utama dan ikon header.
- Gambar bermakna wajib alt; ikon dekoratif `aria-hidden`.
- Bahasa halaman `id`; tanggal ditampilkan dalam WIB dengan nama bulan Indonesia.
