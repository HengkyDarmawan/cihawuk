# Data Demonstrasi

Dokumen ini menjelaskan isian contoh yang dipakai untuk memperagakan aplikasi kepada
pemerintah desa: apa yang nyata, apa yang karangan, bagaimana menandainya, dan bagaimana
menghapusnya sebelum dipakai sungguhan.

## Ringkasan

| | |
|---|---|
| Perintah mengisi | `php public/index.php tools seed_demo` |
| Perintah menghapus | `php public/index.php tools purge_demo` |
| Saklar penanda | `DEMO_MODE=true` pada `.env` |
| Jejak penghapusan | Tabel `demo_records` (migration 016) |
| Larangan | Seeder menolak berjalan saat `APP_ENV=production` |

## Yang NYATA, bukan karangan

Isi berikut berasal dari dokumen sumber desa dan tetap ada setelah `purge_demo`, karena
dimuat oleh seeder master, bukan seeder demo.

| Isi | Sumber |
|---|---|
| Sambutan Kepala Desa (naskah penuh) | S3 |
| Visi Desa Cihawuk dan visi Kecamatan Kertasari (disimpan terpisah) | S3 |
| Tujuh butir misi (akronim C-I-H-A-W-U-K) | S3 |
| Sejarah desa dan linimasa kepemimpinan 1984 sampai sekarang | S1/S3 |
| Susunan 14 jabatan perangkat desa beserta namanya | S3 |
| Identitas desa, kode PUM, geografi, penggunaan lahan, batas wilayah | S1 |
| Statistik kependudukan 2023 | S1 |

S3 (`2.Kata Pengantar Profil Cihawuk 2023 (1).doc`) sebelumnya tidak pernah terimpor karena
proyek ini tidak memaketkan pembaca `.doc`. Berkas itu kini dikonversi sekali menjadi
`.docx` memakai Microsoft Word (`scripts/convert-doc.ps1`), lalu dibaca importer yang sudah
ada. Berkas `.doc` aslinya tidak disentuh.

### Catatan penting tentang susunan perangkat

S3 memuat **dua** susunan yang berbeda:

1. **Halaman foto perangkat** — nama diikuti jabatan, mengikuti alur teks dokumen.
2. **Bagan "PP 84/2015"** — teksnya tersimpan di dalam kotak gambar yang berurutan menurut
   lapisan, bukan menurut bacaan, sehingga pasangan nama-jabatan **tidak dapat** diturunkan
   secara mekanis.

Yang dipakai adalah susunan dari halaman foto, karena dikuatkan empat bukti bebas: tanda
tangan sambutan (Yaya Dores sebagai Kepala Desa), bagan BPD (Eneng Santi Fatmawati sebagai
Ketua BPD), serta nama berkas `Agus haryadi (sekdes).png` dan
`Aang nujaman (kaur perencanaan).png` yang tercatat di `docs/data-issues.md`.

Perbedaannya dicatat sebagai isu data `ORG_CHART_MISMATCH` berstatus terbuka. **Pengelola
desa perlu membaca gambar bagannya langsung dan menetapkan mana yang benar.**

## Yang KARANGAN dan harus diganti

Seluruh isi berikut dibuat `tools seed_demo` dan hilang saat `tools purge_demo`.

| Modul | Isi contoh | Jumlah |
|---|---|---|
| Kontak desa | Alamat, telepon, surel, jam layanan | 1 blok profil |
| Lokasi | Kantor desa dan empat dusun | 5 |
| Fasilitas | Sekolah, posyandu, puskesmas pembantu, masjid, lapangan, bak air, kantor desa | 12 |
| Potensi | Kebun teh, hortikultura, kopi, sapi perah, wisata air panas | 5 |
| UMKM | Keripik, kopi bubuk, teh rakyat, anyaman bambu, warung, bengkel, dan lainnya | 10 |
| Anggaran | APBDes 2024, 2025 dan 2026 | 3 tahun, 7 revisi |
| Aset | Register, unit fisik, token QR, mutasi, peminjaman, pemeliharaan, batch label | 17 register, 51 unit |
| Gudang | Barang, konversi satuan, transaksi terposting, opname tertutup | 25 barang, 6 transaksi |
| Konten | Berita dan agenda | 8 + 6 |
| Beranda | Foto hero dan foto kantor | 2 rujukan media |
| Akun | Akun peran `.demo` dan skenario tiket | 18 akun, 9 tiket |

**Nomor telepon, surel, nama pemilik usaha, dan angka APBDes di atas tidak nyata.**

## Foto

Foto contoh diunduh dari **Wikimedia Commons** oleh `scripts/fetch-demo-media.php`, bukan
dari hasil pencarian gambar biasa. Alasannya dua: setiap berkas Commons membawa penulis dan
lisensinya secara mesin-terbaca, dan isinya relevan dengan wilayah setempat (Pangalengan,
Rancabali dan Ciwidey berada di dataran tinggi yang sama dengan Kertasari).

- Kredit fotografer disimpan pada `media_assets.source_credit`.
- Nama lisensi dan tautan halaman berkasnya disimpan pada `media_assets.license_note`.
- Seluruhnya ditandai `is_placeholder = 1` dan captionnya menyatakan **"bukan foto Desa Cihawuk"**.
- Skrip dapat dijalankan ulang; berkas yang sudah ada dilewati, hanya yang gagal diambil lagi.

Foto yang subjeknya tidak cocok **tidak** dipakai. Pada penyusunan awal, hasil pencarian
sempat memberi lukisan untuk "kentang", rumah Eropa untuk "permukiman", dan stasiun kereta
untuk "domba"; empat berkas seperti itu dibuang, bukan dibiarkan masuk.

### Foto perangkat desa sengaja dikosongkan

Tidak ada foto yang dipasangkan ke nama pejabat. Ada dua alasan:

1. Foto di dalam S3 sebagian berasal dari desa lain — jejak berkasnya menunjuk
   Margamukti/Pangalengan (`docs/data-issues.md`).
2. Memasang potret orang asing dari bank foto pada nama pejabat sungguhan bukan sekadar soal
   hak cipta, melainkan soal privasi. Memakai avatar ilustrasi pun menuntut
   `people.photo_consent = 1`, padahal tidak ada persetujuan yang pernah dicatat.

31 gambar dari S3 tetap diimpor ke pustaka media sebagai **draft** dengan
`rights_status = 'unknown'`, sehingga tidak pernah tampil publik. Pengelola dapat
memeriksanya di **Admin → Media**, termasuk empat peta dusun beresolusi tinggi.

## Penanda mode demonstrasi

`DEMO_MODE=true` menambahkan satu baris merah di atas **setiap** halaman publik:

> **Mode demonstrasi** — sebagian angka, nama usaha, dan foto pada halaman ini adalah
> **contoh**, bukan data resmi Desa Cihawuk. Jangan dikutip sebagai rujukan.

Saklar yang sama memaksa `<meta name="robots" content="noindex, nofollow">` pada seluruh
halaman publik, sehingga angka APBDes contoh tidak mungkin terindeks mesin pencari. Bilah
serupa muncul di dashboard pengelola.

Matikan dengan satu baris di `.env`:

```
DEMO_MODE=false
```

## Bagaimana penghapusan dijamin bersih

Modul baru sengaja **tidak** memakai awalan `[Demo]` pada namanya, supaya nama fasilitas dan
UMKM tampil wajar saat diperagakan. Sebagai gantinya setiap entitas akar dicatat pada tabel
`demo_records`, dan `purge_demo` menghapus tepat baris itu beserta turunannya.

Selain menghapus baris, `purge_demo` juga **membatalkan penerbitan** yang dilakukan seeder
demo: profil desa, struktur organisasi, dan dataset kependudukan kembali berstatus draft.
Isinya tetap ada karena berasal dari dokumen sumber; yang dibatalkan hanyalah aksi
menerbitkan, yang memang dilakukan seeder demo.

Jaminan ini diuji, bukan sekadar dijanjikan. `tests/service/DemoSeedTest.php` mencatat jumlah
baris pada 23 tabel, menjalankan seeder, memastikan modul utama terisi, lalu menghapusnya dan
memastikan **setiap** tabel kembali ke angka semula. Satu tes terpisah memastikan tidak ada
jenis entitas yang tercatat tanpa aturan penghapusan.

## Sebelum dipakai sungguhan

1. `php public/index.php tools purge_demo`
2. Setel `DEMO_MODE=false` pada `.env`
3. Ganti kata sandi atau hapus seluruh akun `.demo`
4. Isi data resmi sesuai `docs/content-needed.md`
5. Tetapkan susunan perangkat yang benar (isu `ORG_CHART_MISMATCH`)

## Berkas terkait

| Berkas | Peran |
|---|---|
| `database/seeds/DemoSeeder.php` | Seluruh isian contoh dan rutin penghapusannya |
| `application/migrations/016_create_demo_records_table.php` | Jejak entitas demo |
| `scripts/fetch-demo-media.php` | Unduh foto berlisensi dari Wikimedia Commons |
| `scripts/convert-doc.ps1` | Konversi S3 `.doc` menjadi `.docx` sekali jalan |
| `reference/media/demo/manifest.csv` | Kredit dan lisensi setiap foto contoh |
| `reference/media/s3/manifest.csv` | Gambar hasil ekstraksi S3 (tetap draft) |
| `tests/service/DemoSeedTest.php` | Bukti isi dan hapus kembali bersih |
| `tests/http/DemoModeHttpTest.php` | Bukti penanda dan noindex pada halaman publik |
