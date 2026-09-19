# Inventaris Dokumen Sumber

Empat lampiran pengguna adalah sumber fakta historis Desa Cihawuk. Berkas asli disimpan di `reference/documents/`
(tidak dilayani web, diabaikan git) dan dicatat di tabel `source_documents` dengan checksum SHA-256.
Kondisi di bawah adalah hasil `tools import_sources` pada database pengembangan, 16 September 2026 pukul 10.42 WIB.

## Register

| Kode | Berkas di mesin ini | Judul | Tahun | Peran | Status impor |
|---|---|---|---|---|---|
| S1 | `3 Potensi Desa Cihawuk 2023.docx` | Potensi Desa Cihawuk 2023 | 2023 | Identitas, wilayah, SDA, penduduk, komoditas, kelembagaan, sarana | **Terimpor** (batch 1) |
| S2 | `4 Perkembangan Desa Cihawuk 2023.docx` | Perkembangan Desa Cihawuk 2023 | 2023 | Perkembangan penduduk, keluarga, ekonomi, pendidikan, layanan | **Terimpor** (batch 2) |
| S3 | `2.Kata Pengantar Profil Cihawuk 2023 (1).doc` | Kata Pengantar Profil Cihawuk 2023 | 2023 | Pengantar, visi misi, sejarah, struktur, aset visual | **Belum diimpor** (`needs_conversion`) |
| S4 | `Profil Desa dan Kelurahan.docx` | Profil Desa dan Kelurahan (Desember 2021) | 2021 | Arsip pembanding; tidak digabung dengan statistik 2023 | **Terimpor** (batch 3; uji impor ulang batch 4) |

Nama berkas sedikit berbeda dari register spesifikasi (tanpa akhiran `(1)`); pencocokan memakai pola nama berkas.
Checksum lengkap tersimpan di `source_documents.checksum` dan ditampilkan di **Admin → Data & Statistik → Sumber**.

## Hasil impor

| Sumber | Tabel DOCX | Observasi | Integer | Desimal | Kode | Teks | Kosong / `-` (null) |
|---|---|---|---|---|---|---|---|
| S1 | 79 | 3.364 | 790 | 201 | 1 | 573 | 1.799 (1.754 `-`, 45 kosong) |
| S2 | 53 | 1.637 | 583 | 22 | 1 | 616 | 415 (400 `-`, 15 kosong) |
| S4 | 50 | 649 | 284 | 122 | 0 | 239 | 4 (kosong) |
| **Total** | 182 | **5.650** | | | | | |

Angka di atas hanya observasi hasil batch impor. Database juga memuat 26 observasi kurasi dari seed master, sehingga
total baris `source_observations` = 5.676. Nilai null tetap menyimpan nilai mentahnya (`-` atau kosong).

Semua observasi berstatus `pending` (menunggu review), kecuali 5 observasi kurasi seed (luas wilayah dan lintang) yang ditandai `conflict`.
Tidak ada statistik yang terbit otomatis.

### Bagian S1 yang terimpor

Identitas (tabel 1–3), batas wilayah, penetapan batas, luas wilayah menurut penggunaan, iklim, tanah, topografi,
kepemilikan lahan, tanaman pangan, buah-buahan, tanaman obat, perkebunan, hasil hutan, kondisi dan dampak hutan,
ternak (populasi, produksi, pakan, usaha, lahan), perikanan, bahan galian, sumber daya air, air bersih, sungai, rawa,
danau/situ, air panas, kualitas udara, kebisingan, ruang publik, potensi wisata, penduduk (jumlah, usia, pendidikan,
mata pencaharian, agama, kewarganegaraan, etnis, disabilitas, tenaga kerja, angkatan kerja), lembaga pemerintahan,
kemasyarakatan, ekonomi, pendidikan, adat, keamanan, partisipasi politik, prasarana transportasi, komunikasi, air
bersih, sanitasi, irigasi, prasarana pemerintahan/BPD/dusun/lembaga, peribadatan, olahraga, kesehatan, pendidikan,
energi, hiburan/wisata, dan kebersihan.

### S2 dan S4

Seluruh tabel terbaca (S2 dimulai dengan identitas: kode PUM, luas, koordinat, ketinggian; S4 dimulai dengan batas
wilayah dan luas tanah). **Keterbatasan:** kedua dokumen tidak memakai gaya paragraf "Heading", sehingga locator
observasi hanya berbentuk `Tabel N › Baris M › Kolom K` tanpa nama bagian. Reviewer perlu membuka dokumen asli
untuk konteks bagian. Perbaikan yang mungkin: mendeteksi paragraf tebal/kapital sebelum tabel sebagai judul.

## Cara kerja parser

- `SourceImportService::extract_blocks()` membaca `word/document.xml` dengan `ZipArchive` + DOM (tanpa eksekusi
  makro), menghasilkan blok heading/paragraf/tabel.
- Sel gabungan (`gridSpan`, `vMerge`) ditandai; sel lanjutan merge tidak dibuat observasi ganda.
- Baris dengan satu sel teks di awal tabel dipakai sebagai konteks (judul tabel).
- Setiap sel nilai menjadi satu observasi: `field_label` (konteks › label baris), `source_locator`, `raw_value`,
  `normalized_value`, `value_type`.
- Normalisasi (`normalize()`):
  - `6.809` → integer 6809 (titik ribuan),
  - `932,35` → desimal 932.35 (koma desimal),
  - `320431.2006` (kode PUM) → tetap string bertipe `code`,
  - `-`, `–`, `—`, `n/a`, kosong → null (nilai mentah disimpan),
  - teks pilihan seperti `Ada/Tidak` → tipe `text` tanpa nilai ternormalisasi (tidak dianggap jawaban terpilih); teks lain → `text`.
- Impor per sumber berjalan dalam satu transaksi. Kunci unik `(source_id, source_locator, field_key)` membuat impor
  ulang tidak menggandakan observasi; nilai berbeda pada impor ulang dicatat sebagai `data_issues`, bukan menimpa.
- Bukti impor ulang (16 Sep 2026): `tools import_sources S4` kedua kali → `batch #4 — 649 observasi (0 baru,
  0 konflik, 4 kosong)`; jumlah `source_observations` tetap 5.676 dan `data_issues` tetap 10.
- Diuji: `SourceNormalizationTest` (format angka, kode, null, dan ekstraksi DOCX asli bila berkas tersedia).

## S5 (register aset) — berkas belum tersedia

Spesifikasi v1.2 mendaftarkan sumber kelima `ALL ASET FIX 2020-2026 CIHAWUK(AutoRecovered).xlsx` sebagai dasar modul
aset. Per 18 September 2026 berkas tersebut **tidak ada** di `reference/documents/` (folder hanya berisi S1–S4) dan
belum terdaftar di tabel `source_documents`. Angka pada spesifikasi (113 register, total ≥ Rp5.681.856.430) berasal
dari pembacaan pengguna, bukan dari berkas yang pernah dibaca aplikasi ini. Impor staging aset baru dapat dimulai
setelah berkas disediakan.

## S3 (.doc) — belum diimpor

S3 adalah format Word 97–2003 biner. LibreOffice tidak terpasang di mesin ini sehingga konversi otomatis ke DOCX
tidak dapat dilakukan, dan parser biner tidak dibuat. Isi yang diharapkan (pengantar, visi misi, sejarah, struktur,
foto) belum ada di aplikasi; profil desa hanya berisi draft dengan penanda "perlu dilengkapi".

Langkah untuk mengimpor:

1. Buka S3 di Microsoft Word/LibreOffice, **Simpan sebagai** `.docx` dengan nama berawalan
   `2.Kata Pengantar Profil Cihawuk 2023`.
2. Letakkan di `reference/documents/`, lalu `php public/index.php tools import_sources S3`.
3. Teks naratif (visi, misi, sejarah) tetap perlu disalin manual ke CMS profil setelah diperiksa; foto/nama/peta di
   dalam dokumen harus diinspeksi visual (issue `DOC_VISUAL_ASSETS`).

Pembacaan teks S3 pada 18 September 2026 (ekstraksi hanya-baca, tanpa impor) memastikan dokumen memuat kata
pengantar, sambutan kepala desa, visi/misi, linimasa sembilan periode kepemimpinan 1984–2019, empat gambar peta
dusun, serta sejumlah foto — termasuk foto dari desa lain. Rinciannya ada di `docs/data-issues.md`.

## Data yang di-seed sebagai draft

Seed master (`database/seeds/MasterSeeder.php`) membuat 11 indikator dan 16 nilai statistik berstatus
`pending`/`draft` dari subset fakta spesifikasi §4.2, masing-masing tertaut ke observasi sumbernya:

| Indikator | 2023 (S1/S2) | 2022 (S2 "tahun lalu") | 2021 (S4) |
|---|---|---|---|
| Penduduk laki-laki | 3.510 | 3.454 | 3.508 |
| Penduduk perempuan | 3.299 | 3.263 | 3.288 |
| Total penduduk | 6.809 | — | 6.796 |
| Kepala keluarga | 2.174 | — | 2.011 |
| Jumlah dusun | 4 | — | — |
| Ketinggian (mdpl) | 1.514 | — | — |
| Luas kentang / kubis / wortel / cabai (ha) | 80 / 70 / 70 / 15 | — | — |

Pemetaan "tahun lalu" di S2 ke 2022 adalah inferensi dan diberi label. Luas wilayah dan koordinat **tidak** dijadikan
statistik karena konflik (lihat `docs/data-issues.md`). Nilai baru tampil publik hanya setelah reviewer
(`statistics.review`) memverifikasi dan menerbitkannya; halaman Data Desa selalu menyebut sumber dan tahun.
