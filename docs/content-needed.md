# Konten yang Perlu Dilengkapi Pengelola

Aplikasi berjalan lengkap dengan draft dan fallback yang jelas, tetapi konten berikut **harus diberikan atau
dikonfirmasi pemerintah desa** sebelum situs dianggap resmi. Tidak ada data di bawah yang dikarang oleh sistem.

Tempat mengisi: **Admin → Konten** (profil, pejabat, potensi, berita, agenda, galeri, dokumen, hero, menu),
**Admin → Media** (foto/berkas), **Admin → Pengaturan** (kontak, jam layanan, kalender, SLA).

## Prioritas tinggi (sebelum go-live)

| Item | Kondisi sekarang | Yang dibutuhkan | Catatan |
|---|---|---|---|
| Logo / lambang desa | Tidak ada; header memakai ikon perbukitan generik (SVG) + nama desa | Berkas PNG/WebP resolusi tinggi + izin pemakaian | `village_profiles.logo_media_id` |
| Alamat kantor desa | Kosong | Alamat lengkap | Tampil di kontak & footer |
| Telepon, email, WhatsApp publik | Kosong (`site.contact.confirmed = false`) | Nomor/alamat resmi yang boleh dipublikasikan | Jangan memakai nomor pribadi tanpa izin |
| Jam layanan | Contoh "Senin–Jumat 08.00–16.00 WIB" bertanda contoh | Jam layanan resmi | Juga menentukan kalender SLA |
| Kalender kerja & hari libur | Kalender contoh tanpa daftar libur | Hari kerja, jam, libur nasional/daerah, cuti bersama | **Pengaturan → Kalender** |
| Pejabat yang menjabat | Kepala Desa: Yaya Dores (draft, belum diverifikasi, sumber 2023) | Nama, jabatan, masa jabatan, foto berizin untuk susunan saat ini | Issue `OFFICIALS_HISTORICAL` |
| Struktur organisasi | Hanya jabatan Kepala Desa dan Sekretaris Desa (draft) | Bagan struktur resmi (kasi/kaur/kadus) | Unit kerja seed hanya contoh |
| Visi & misi resmi | Ringkasan editorial (draft) | Teks resmi dari S3 atau dokumen RPJMDes | S3 belum diimpor |
| Sejarah desa | Ringkasan draft; nomor keputusan pemekaran 1984 belum terverifikasi | Teks yang disetujui | — |
| SOP layanan pengaduan | Alur & target SLA adalah **usulan** (3/5/10 hari kerja) | Persetujuan atau revisi SOP, target, dan pejabat penanggung jawab | `docs/workflows.md` |
| Kontak privasi | "Pengelola Layanan Digital Desa Cihawuk" (belum dikonfirmasi) | Nama/jabatan dan kanal pengelola data pribadi | Halaman Privasi |
| Akun pengelola nyata | Hanya akun demo (dev) | Daftar petugas + role + unit | Dibuat Super Admin; wajib MFA |

## Media

| Item | Kondisi | Yang dibutuhkan |
|---|---|---|
| Foto hero beranda | Tidak ada; hero memakai gradasi + scene Three.js abstrak | Foto lanskap desa (≥2400px lebar) dengan hak pakai jelas |
| Foto potensi, berita, galeri | Placeholder "Foto belum tersedia" | Foto asli + nama fotografer + status hak (`rights_status`) |
| Foto kantor / profil | Tidak ada | Foto kantor desa |
| Video hero (opsional) | Tidak ada | Video pendek tanpa audio + poster |
| Aset visual di S3 | Belum diinspeksi | Pemeriksaan pasangan foto–nama sebelum dipakai (`DOC_VISUAL_ASSETS`) |

Media dengan status hak `unknown` otomatis tetap draft (tidak tampil publik). Isi teks alternatif untuk setiap foto bermakna.

## Data wilayah dan peta

| Item | Kondisi | Yang dibutuhkan |
|---|---|---|
| Koordinat kantor desa | Tidak dipasang (`map.default_center = null`); "Peta sedang dilengkapi" | Koordinat terverifikasi dengan tanda lintang benar (`LATITUDE_SIGN`) |
| Batas wilayah | Tidak digambar | Peta/polygon resmi (`BOUNDARY_MISMATCH`) |
| Nama dusun / RW / RT | Tidak dibuat (hanya root `DESA-CIHAWUK`) | Daftar wilayah administratif resmi |
| Luas wilayah | Konflik 932,35 / 931,35 / 931,00 ha | Nilai resmi + dasar (`AREA_INCONSISTENT`) |
| Penyedia tile peta | `.env.example` memakai tile OpenStreetMap; peta belum tampil karena belum ada titik terverifikasi | Pastikan penggunaan tile sesuai kebijakan penyedia (OSM tidak untuk trafik besar) atau ganti `MAP_TILE_URL`/`MAP_ATTRIBUTION` |

## Konten publik

| Item | Kondisi |
|---|---|
| Profil desa | Draft (belum terbit) → halaman Profil menampilkan "Profil desa sedang disiapkan" |
| Potensi | 1 draft ("Hortikultura dataran tinggi") belum diverifikasi |
| Statistik Data Desa | 16 nilai draft/pending; perlu review & penerbitan (`docs/data-issues.md`) |
| Berita, agenda, galeri, dokumen publik | Belum ada konten resmi. (Database pengembangan memuat satu berita uji QA — hapus/arsipkan sebelum go-live) |
| Tautan resmi (kabupaten, kecamatan, LAPOR!) | Belum diisi; menu hanya memuat tautan internal |
| Dokumen publik (APBDes, perdes, dll.) | Belum ada; unggah PDF resmi di **Admin → Konten → Dokumen** |

## Berkas sumber yang perlu diserahkan

| Berkas | Kondisi | Dibutuhkan untuk |
|---|---|---|
| `ALL ASET FIX 2020-2026 CIHAWUK(AutoRecovered).xlsx` (S5) | **Belum ada** di `reference/documents/` | Impor staging modul aset, pemecahan unit, dan label QR |
| S3 versi `.docx` | Hanya tersedia `.doc` biner | Impor otomatis pengantar, visi/misi, sejarah |
| Foto perangkat desa yang sudah dipasangkan | Foto di S3 sebagian berasal dari desa lain | Halaman pemerintahan dan struktur organisasi |
| Daftar dusun resmi | S3 memuat peta bernama Cihawuk, Ciakar, Puncakmulya, Pinggirsari; belum dikonfirmasi | Wilayah administratif dan statistik per dusun |

## Konfigurasi teknis yang terkait konten

- SMTP untuk email notifikasi (`.env` `MAIL_*`).
- Domain resmi dan HTTPS (`docs/deployment.md`).
- Kategori laporan dan unit penanggung jawab disesuaikan dengan struktur desa (seed: 12 kategori, 5 unit contoh).

---

## Tambahan setelah tahap 4–12 (19 September 2026)

Modul-modul berikut sudah dibangun penuh tetapi **sengaja kosong** karena datanya tidak ada
pada dokumen sumber dan mengarangnya dilarang spesifikasi. Yang dibutuhkan dari pengelola:

| Modul | Yang dibutuhkan | Tanpa itu |
|---|---|---|
| Aset dan QR | Berkas **S5** `ALL ASET FIX 2020-2026 CIHAWUK.xlsx`, **diekspor ke CSV** lalu ditaruh di `reference/imports/` | Register aset kosong; importer sudah siap dan diuji |
| Transparansi anggaran | Angka APBDes per tahun: murni, perubahan, realisasi, beserta dokumen yang sudah disamarkan | `/transparansi/anggaran` menampilkan keadaan kosong |
| Direktori fasilitas | Nama, alamat, pengelola, jam layanan, dan tahun data setiap fasilitas nyata | `/fasilitas` kosong; angka agregat tetap di Data Desa |
| Direktori UMKM | Persetujuan tertulis pemilik usaha beserta catatan kapan dan bagaimana diperoleh | `/umkm` kosong |
| Profil desa | Naskah sambutan, visi, misi, dan kontak resmi | Tiga halaman profil terisi, `/profil/visi-misi` masih kosong |
| Struktur organisasi | Nama pejabat selain Kepala Desa dan Sekretaris Desa, beserta foto yang sudah dipasangkan dan berizin | Tree terbit dengan jabatan bertanda kosong |
| Dataset statistik | Verifikasi nilai per indikator untuk tema selain kependudukan | Hanya dataset Kependudukan 2023 yang siap diterbitkan |
| Surat desa | Jenis surat, syarat, format nomor, pejabat penandatangan, dan SOP | Modul sengaja `disabled`, belum dibangun |

Dua keterbatasan teknis yang memerlukan keputusan, bukan data:

- **Gambar QR pada label** memerlukan satu dependensi baru (pustaka pembuat QR). Saat ini
  batch label menyimpan daftar unit, jumlah salinan, offset, dan histori cetak, tetapi belum
  menghasilkan PDF bergambar.
- **Pembaca .xlsx** memerlukan dependensi baru bila impor S5 ingin langsung dari berkas asli
  tanpa ekspor manual ke CSV.


---

## Pembaruan 19 September 2026 — S3 akhirnya terbaca

Berkas S3 (`2.Kata Pengantar Profil Cihawuk 2023 (1).doc`) dikonversi menjadi `.docx` dan
diimpor. Isinya ternyata memuat beberapa hal yang selama ini tercatat di dokumen ini sebagai
"harus diberikan pengelola". Butir-butir berikut **gugur** dari daftar kebutuhan:

| Item | Keadaan sekarang |
|---|---|
| Sambutan Kepala Desa | **Ada.** Naskah penuh dari S3, ditandatangani Yaya Dores, Januari 2024 |
| Visi dan misi resmi | **Ada.** Visi desa dan tujuh butir misi; visi Kecamatan Kertasari disimpan terpisah |
| Sejarah desa | **Ada.** Narasi pemekaran 1984 dan linimasa sembilan kepemimpinan |
| Struktur organisasi | **Ada.** 14 jabatan beserta namanya, bukan lagi hanya Kepala Desa dan Sekretaris Desa |
| Media dari S3 | **Ada.** 31 gambar diekstrak ke pustaka media sebagai draft, termasuk empat peta dusun |

### Yang justru menjadi kebutuhan baru

| Item | Yang dibutuhkan |
|---|---|
| **Susunan perangkat yang benar** | S3 memuat dua susunan yang saling bertentangan. Lihat isu `ORG_CHART_MISMATCH`. Pengelola perlu membaca gambar bagan pada dokumen dan menetapkan mana yang berlaku |
| Nama yang hanya ada di bagan | Imam Sihabudin, Egi Nugraha, Ajid Achmadi, Ujang Juhana belum jelas jabatannya |
| Susunan BPD | Bagan BPD S3 menyebut periode 2019-2027 dengan tujuh nama; belum dikonfirmasi (isu `BPD_TERM_LABEL`) |
| Pemeriksaan 31 gambar S3 | Sebagian foto berasal dari desa lain; perlu dipilah sebelum ada yang diterbitkan |
| Foto perangkat berizin | Belum ada satu pun foto yang dipasangkan ke nama, dan `people.photo_consent` masih 0 untuk semuanya |

### Masih dibutuhkan seperti semula

Angka APBDes, entri fasilitas nyata, profil UMKM beserta persetujuan pemiliknya, register
aset (berkas S5), kontak resmi, logo desa, koordinat kantor, daftar dusun resmi, dan SOP
surat desa. Seluruhnya kini **terisi data contoh** supaya aplikasinya dapat diperagakan;
lihat `docs/data-demo.md` untuk cara membedakan dan menghapusnya.
