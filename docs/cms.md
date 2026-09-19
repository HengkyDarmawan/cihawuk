# CMS Halaman dan Beranda

Halaman publik disusun dari **section** yang sudah disediakan pengembang. Pengelola mengatur isi, urutan, dan
publikasinya dari dashboard tanpa menyentuh kode, tetapi tidak dapat menulis HTML, CSS, JavaScript, atau memilih
komponen di luar daftar yang tersedia.

Sumber: `application/config/cms_sections.php` (registry), `application/libraries/CmsService.php` (penyusunan dan
validasi), `application/libraries/CmsPublicationService.php` (review, publikasi, penjadwalan, rollback).

## Prinsip

1. **Frontend hanya membaca snapshot yang diterbitkan.** Mengubah draft tidak pernah mengubah halaman publik.
2. **Data bisnis dirujuk, bukan disalin.** Snapshot menyimpan jenis section, urutan, layout, dan konfigurasi
   (mis. `indicators: [population_total]`), sedangkan angka statistik, berita, dan potensi diambil saat render
   dari tabel aslinya dengan query scope publik.
3. **Arsip, bukan hapus.** Section diarsipkan; snapshot lama disimpan dan hanya ditandai `superseded_at`.
4. **Pemisahan tugas.** Menyusun, menyetujui, dan menerbitkan adalah permission terpisah.

## Status halaman

```
draft → in_review → changes_requested → draft
                 ↘ approved → scheduled → published
published → unpublished → published
published / unpublished → archived
```

| Aksi | Permission | Catatan |
|---|---|---|
| Menyusun draft dan section | `cms.page.edit` | Setiap simpan membuat versi baru; versi lama tetap ada |
| Menambah halaman/section | `cms.page.create` | Jenis section berasal dari registry |
| Mengajukan review | `cms.page.submit_review` | Membuat `content_review_requests` |
| Menyetujui / minta perbaikan | `cms.page.approve` | Penolakan wajib komentar ≥10 karakter |
| Menerbitkan dan menjadwalkan | `cms.page.publish` | Menghasilkan snapshot revisi baru |
| Menarik publikasi | `cms.page.unpublish` | Alasan ≥10 karakter; snapshot ditandai tidak aktif |
| Rollback | `cms.page.rollback` | Menerbitkan ulang isi snapshot lama sebagai revisi baru |

## Registry section

15 jenis tersedia: `hero`, `quick_links`, `profile_summary`, `statistics`, `text_image`, `featured_potentials`,
`featured_facilities`, `featured_news`, `upcoming_agenda`, `gallery_preview`, `verified_map`, `budget_summary`,
`public_documents`, `service_cta`, `custom_notice`.

Setiap jenis mendeklarasikan: label, deskripsi, apakah hanya boleh satu per halaman (`singleton`), varian layout
yang diizinkan, modul yang harus hidup, dan daftar field beserta tipenya (`text`, `textarea`, `select`, `number`,
`media`, `links`, `ids`, `indicators`).

Aturan yang ditegakkan server saat menyimpan:

| Jenis | Aturan |
|---|---|
| hero | Maksimal dua tombol. Mode `video` wajib punya foto poster **dan** media video. Mode `three` memakai ilustrasi bawaan (gradasi + SVG) yang selalu tampil lebih dulu, sehingga foto fallback opsional |
| quick_links | Maksimal delapan tautan; hanya path internal atau URL `http`/`https`; `javascript:` dan `data:` ditolak |
| statistics | Maksimal empat indikator, semuanya harus punya nilai **terbit dan terverifikasi** pada tahun yang dipilih |
| featured_potentials | Pemilihan manual hanya menerima potensi berstatus terbit + terverifikasi |
| gallery_preview | Hanya album yang sudah terbit |
| Semua | Kunci konfigurasi yang tidak dikenal registry dibuang; media harus sudah berstatus terbit |

Section yang modulnya sedang mati tidak dapat dibuat, tidak muncul di daftar pilihan, dan **tidak ikut** saat
halaman diterbitkan — meskipun sudah ada sebagai draft.

## Snapshot publikasi

Tabel `cms_publication_snapshots` menyimpan satu baris per publikasi: `revision_no`, `snapshot_json`, alasan,
penerbit, waktu, dan `rolled_back_from` bila revisi tersebut hasil rollback. Snapshot aktif adalah baris dengan
`superseded_at IS NULL` pada halaman berstatus `published`.

`CmsPublicationService::published_layout($page_key)` dipakai frontend; `draft_layout($page)` dipakai pratinjau.
Keduanya menghasilkan bentuk yang sama sehingga template publik yang dipakai identik.

## Pratinjau

`POST /admin/cms/halaman/{public_id}/pratinjau` menghasilkan token bertanda tangan (HMAC) yang terikat pada
halaman, pengguna, dan waktu kedaluwarsa (15 menit). Halaman `GET /admin/pratinjau/{token}` memerlukan login dan
permission `cms.page.view`, memakai `Cache-Control: no-store` serta `X-Robots-Tag: noindex, nofollow`. Token milik
pengguna lain, token kedaluwarsa, dan tanda tangan yang diubah menghasilkan 410.

## Penjadwalan

Penjadwalan menyimpan waktu WIB yang dikonversi ke UTC pada `scheduled_publications` beserta idempotency key
(halaman + versi + waktu). Job `scheduled_publications` pada `tools run_jobs` mengklaim baris secara atomik
(`status = pending → running`), menerbitkan, lalu menandainya `done`. Job yang dijalankan bersamaan tidak
menerbitkan dua kali, dan kegagalan job menandai baris `failed` tanpa mengubah status halaman menjadi terbit.

## Beranda hasil seed

Seed master membuat halaman `home` (halaman sistem) berisi sembilan section: hero, akses cepat, profil singkat,
statistik, potensi unggulan, berita terbaru, agenda mendatang, peta terverifikasi, dan ajakan layanan — lalu
menerbitkannya sebagai revisi 1. Susunan itu sama dengan beranda sebelum CMS ada, sehingga tampilan publik tidak
berubah, tetapi kini dapat diatur pengelola.

Section statistik pada seed menunjuk tahun 2023 dengan empat indikator penduduk. Karena nilainya masih draft,
section menampilkan keadaan kosong "Statistik sedang direview" sampai pengelola menerbitkan nilainya.

## Halaman publik CMS

Halaman selain beranda dilayani pada slug versi yang diterbitkan: `/{slug}`, atau `/{slug-induk}/{slug-anak}`
bila halaman punya induk yang juga terbit. Beranda tetap hanya dilayani pada `/`.

| Keadaan halaman | Yang dilihat pengunjung |
|---|---|
| Draft, menunggu review, disetujui | 404 |
| Terbit | Halaman lengkap (judul, ringkasan, section) |
| Ditarik atau diarsipkan | 404 |
| Slug diganti setelah pernah terbit | 301 dari alamat lama ke alamat baru (`cms_redirects`) |
| `search_indexable` dimatikan | Halaman tetap dapat dibuka, tetapi `noindex` dan tidak masuk sitemap/pencarian |

Slug yang dipakai route sistem ditolak. Daftarnya **diturunkan otomatis** dari `config/routes.php`
(`CmsService::reserved_slugs()`), jadi route baru langsung ikut terlindungi tanpa mengubah daftar manual.

## Menu dan navigasi

Lima lokasi menu: `header`, `mobile`, `footer_primary`, `footer_secondary`, `quick_link`. Item dapat menunjuk
path aplikasi, halaman CMS, atau URL luar.

Aturan yang ditegakkan server saat menyimpan item:

- kedalaman maksimal dua tingkat, dan item yang sudah punya submenu tidak dapat dijadikan submenu;
- item tidak boleh menjadi induk dirinya sendiri;
- path internal harus valid dan bukan `/admin`, `/warga`, `/tools`, `/berkas`, atau `/csrf-token`;
- URL luar hanya `http`/`https`; `javascript:` dan `data:` ditolak;
- halaman CMS yang belum terbit tidak dapat dipasang di menu publik;
- maksimal 40 item per lokasi.

Menu punya draft sendiri dan baru berlaku setelah diterbitkan sebagai snapshot
(`cms_publication_snapshots.target_type = 'menu'`). Item nonaktif dan item yang menunjuk halaman yang sudah
ditarik dilewati saat publikasi, tetapi tetap tersimpan sebagai draft. Rollback menu bekerja seperti halaman:
membuat revisi baru dari snapshot lama.

Menyusun menu memerlukan `cms.menu.manage`; menerbitkan memerlukan `cms.page.publish`.

## Identitas situs dan tema

Satu draft berisi identitas (nama situs, wilayah, alamat, kontak, jam layanan, teks footer, kontak privasi,
logo/favicon/social image, tautan media sosial) dan token tema (empat warna, font isi/judul, preset sudut,
mode hero, preferensi reduced motion, gambar placeholder). Draft diterbitkan sebagai snapshot
`target_type = 'site'`, jadi situs publik tidak berubah sebelum penerbitan.

Validasi server:

- warna wajib heksadesimal enam digit;
- warna utama dan utama gelap harus kontras ≥ 4,5:1 terhadap teks putih; warna latar harus kontras ≥ 4,5:1
  terhadap teks isi; warna aksen minimal 3:1 — pengelola tidak dapat membuat situs tidak terbaca;
- font hanya dari yang sudah terpasang (Manrope, Playfair Display, font sistem);
- preset sudut dan mode hero dari daftar tertutup;
- tautan media sosial hanya `http`/`https`; email dan nomor telepon diperiksa formatnya;
- media harus sudah berstatus terbit.

Token tema dirender sebagai custom property pada `<head>`, disaring sekali lagi di view sehingga nilainya tidak
dapat keluar dari blok `<style>`. CSS bebas tetap hanya lewat source code.

Menyunting memerlukan `cms.site.manage`; menerbitkan memerlukan `cms.page.publish`.

## Cache publik

`PublicCache` menyimpan susunan snapshot per halaman, menu, dan identitas di `storage/cache/public/{grup}/`.
Invalidasinya terarah:

| Peristiwa | Yang dibuang |
|---|---|
| Halaman diterbitkan/ditarik/rollback | Cache halaman itu + grup `listing` (sitemap, pencarian) |
| Menu diterbitkan/rollback | Seluruh cache menu |
| Identitas/tema diterbitkan/rollback | Cache identitas + cache halaman (identitas ikut dirender di setiap halaman) |
| Menyimpan draft | Tidak ada — cache tidak disentuh sama sekali |

Cache dimatikan pada environment testing (`FEATURE_PUBLIC_CACHE=false` di `.env.testing`) supaya tes membaca
keadaan database terbaru; mekanisme invalidasinya tetap diuji tersendiri. Waktu invalidasi terakhir dicatat di
`storage/cache/public/last-invalidation.json`.

## Yang belum ada

- Menu belum punya alur review (`in_review`/`approved`) seperti halaman: penyusun menyimpan draft, penerbit
  langsung menerbitkan.
- Identitas dan tema juga belum melewati alur review; pemisahannya ada pada permission (menyusun vs menerbitkan).
- Halaman CMS belum bisa dijadwalkan lewat menu, dan menu belum punya penjadwalan sendiri.
- Belum ada pemeriksa tautan rusak (broken link checker) untuk item menu yang menunjuk path yang sudah hilang.
- Menu lokasi `mobile`, `footer_secondary`, dan `quick_link` sudah tersedia di dashboard, tetapi situs publik
  baru memakai `header` dan `footer_primary`; lokasi lain menunggu tahap tampilan berikutnya.
- Belum ada penguncian antar-editor bila dua orang menyunting item/section yang sama bersamaan.
