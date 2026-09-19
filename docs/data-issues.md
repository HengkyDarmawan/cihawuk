# Masalah Data Sumber

Masalah dicatat di tabel `data_issues` (seed master) dan dikelola di **Admin → Data & Statistik → Isu**. Setiap isu
memiliki keputusan sistem sementara. Status saat ini (16 September 2026): **10 isu, semuanya `open`** — belum ada
keputusan pengelola desa.

Reviewer dengan `statistics.review` dapat mengubah status menjadi `resolved` (sudah diperbaiki/ditetapkan) atau
`accepted` (diterima apa adanya dengan catatan), wajib dengan catatan penyelesaian ≥10 karakter; perubahan diaudit.

| Kode | Sumber | Tingkat | Temuan | Keputusan sistem saat ini |
|---|---|---|---|---|
| `AREA_INCONSISTENT` | S1/S2/S4 | Tinggi | Luas wilayah: identitas S1/S2 **932,35 ha**, total penggunaan lahan S1 **931,35 ha**, S4 (2021) **931,00 ha** | Disimpan sebagai observasi terpisah berstatus `conflict`; tidak ada nilai kanonis; luas tidak ditampilkan sebagai statistik publik |
| `LATITUDE_SIGN` | S1/S2 | Tinggi | Lintang tertulis `7,1999` tanpa penanda selatan; bujur `107,7050` | Nilai mentah disimpan sebagai teks; tanda tidak diubah otomatis; **tidak ada pin peta**; halaman kontak/beranda menampilkan "Peta sedang dilengkapi" |
| `PUBLIC_FACILITY_SUBTOTAL` | S1 | Sedang | Tanah kas desa dan tanah bengkok masing-masing 15,50 ha, total fasilitas umum 47,61 ha | Periksa hubungan induk-anak agar tidak terhitung dua kali; tidak diagregasi |
| `FOREIGN_VILLAGE_SOURCE` | S2 | Sedang | S2 mencantumkan "Data Desa Tribaktimulya" | Dicatat; teks asli tidak diganti diam-diam; perlu konfirmasi apakah templat dari desa lain |
| `BOUNDARY_MISMATCH` | S1 vs S4 | Sedang | Daftar desa yang berbatasan berbeda antar sumber (ejaan asli dipertahankan) | Ditampilkan per tahun di area review; polygon batas tidak digambar dari teks |
| `OFFICIALS_HISTORICAL` | S1 | Sedang | Nama Yaya Dores muncul pada data 2023 dan narasi sejak 2019 | Disimpan sebagai pejabat **draft historis**; tidak dinyatakan menjabat pada 2026 tanpa pembaruan |
| `REGULATION_LABEL` | S3 | Rendah | Narasi struktur menyebut "PP No. 84 Tahun 2015" | Tidak dijadikan rujukan hukum terverifikasi |
| `EDUCATION_TOTALS` | S1 | Sedang | Kategori pendidikan tidak saling lepas; total 10.598 melebihi jumlah penduduk | Tidak dibuat diagram lingkaran; tidak diterbitkan sampai direkonsiliasi |
| `EMPTY_AND_DASH_CELLS` | S1 (juga S2/S4) | Rendah | Banyak sel kosong, `-`, atau pilihan "Ada/Tidak" | Tidak dianggap nol atau jawaban terpilih; `-` → null dengan nilai mentah (2.154 sel `-`, 64 sel kosong pada impor) |
| `DOC_VISUAL_ASSETS` | S3 | Sedang | S3 berisi gambar, nama, struktur, dan peta | Belum diinspeksi (S3 belum diimpor); pasangan foto–nama dan batas peta tidak digunakan |

## Temuan tambahan dari pembacaan ulang sumber (18 September 2026)

Ditemukan saat membaca S3 pada sesi Tahap 1 v1.2. Belum masuk tabel `data_issues` karena modul struktur organisasi
dan media library CMS baru dibangun pada tahap berikutnya; dicatat di sini agar tidak hilang.

| Temuan | Bukti | Perlakuan |
|---|---|---|
| Foto di dalam S3 berasal dari desa lain | Jejak berkas `Photo PemdesMargamukti\Pangalengan-20121217-01506.jpg` dan `KUMPULAN PROFIL PANGALENGAN 2016\...\Haris Yusmana.JPG` | Foto S3 **tidak boleh** dipakai sebagai foto perangkat Desa Cihawuk tanpa pencocokan manual |
| Berkas foto bernama pejabat | `yaya dores.jpg`, `Agus haryadi (sekdes).png`, `Aang nujaman (kaur perencanaan).png` | Nama berkas bukan bukti jabatan/periode; perlu konfirmasi pengelola |
| Poster kegiatan ikut tersimpan | Beberapa berkas poster HIV/AIDS 2023 | Bukan materi profil desa; jangan ikut diterbitkan |
| Empat peta dusun | Gambar `Peta Dusun Cihawuk`, `Peta Dusun Ciakar`, `Peta Dusun Puncakmulya`, `PINGGIRSARI PETA` | Nama dusun ini konsisten dengan "jumlah dusun 4" pada S1, tetapi tetap perlu konfirmasi resmi sebelum dibuat sebagai `administrative_areas`; gambar peta bukan GeoJSON |
| Visi pada S3 bercampur dengan visi kecamatan | Teks "Terlaksananya Pelimpahan Kewenangan..." berdampingan dengan visi desa | Simpan terpisah; jangan menjadikan visi kecamatan sebagai visi desa |
| S1 memuat Perda batas wilayah | "Perda No : 14 Tahun 2007" pada bagian penetapan batas | Dicatat sebagai klaim sumber; belum diverifikasi terhadap dokumen asli |

## Konflik tahun

- S1/S2 (2023) dan S4 (Desember 2021) disimpan sebagai tahun berbeda. Nilai 2021 tidak pernah menggantikan 2023.
- Kolom "tahun lalu" di S2 dipetakan ke 2022 dengan label **inferensi** (`year_label`).
- Contoh selisih: penduduk 6.809 (2023) vs 6.796 (2021); KK 2.174 (2023) vs 2.011 (2021).

## Aturan penerbitan statistik

1. Reviewer memverifikasi observasi (terima/tolak/koreksi dengan catatan). Koreksi disimpan di
   `corrected_value`; nilai mentah tidak berubah.
2. Reviewer mempromosikan observasi menjadi nilai statistik (indikator + tahun + wilayah).
3. Nilai harus `verified` sebelum dapat diterbitkan; penerbitan membutuhkan `content.publish`.
4. Halaman publik **Data Desa** hanya menampilkan nilai `published` + `verified`, selalu dengan sumber dan tahun.
   Saat ini belum ada nilai terbit, sehingga halaman menampilkan keadaan kosong yang menjelaskan data sedang direview.

## Yang perlu diputuskan pengelola desa

- Luas wilayah resmi dan dokumen dasarnya.
- Koordinat kantor desa (dengan tanda lintang yang benar) dan izin penampilan peta.
- Batas wilayah resmi (sebaiknya dari peta/dokumen penetapan, bukan teks).
- Susunan pejabat yang menjabat saat ini dan masa jabatannya.
- Apakah rujukan "Tribaktimulya" di S2 kesalahan templat.
- Rekonsiliasi kategori pendidikan sebelum dipublikasikan.

## Temuan dari impor S3 (19 September 2026)

S3 dikonversi dari `.doc` menjadi `.docx` dan diimpor. Dua isu baru masuk tabel `data_issues`:

| Kode | Tingkat | Temuan | Keputusan sistem |
|---|---|---|---|
| `ORG_CHART_MISMATCH` | Tinggi | S3 memuat dua susunan perangkat yang berbeda: halaman foto (nama lalu jabatan) dan bagan "PP 84/2015". Contoh: halaman foto menempatkan Agus Haryadi sebagai Sekretaris Desa, bagan menempatkan Cahya Setiadi | Dipakai pasangan dari halaman foto, karena urutannya adalah alur teks dokumen dan dikuatkan tanda tangan sambutan, bagan BPD, serta nama berkas foto. Teks bagan tersimpan dalam kotak gambar yang berurutan menurut lapisan, bukan bacaan, sehingga tidak diturunkan secara mekanis |
| `BPD_TERM_LABEL` | Sedang | Bagan BPD menulis periode 2019-2027 dengan Ketua Eneng Santi Fatmawati, Sekretaris Budi Kusnadi, Wakil Ketua Anjar Fauji dan empat anggota | Dicatat sebagai klaim sumber. BPD belum dibuat sebagai unit organisasi terpisah karena susunannya berasal dari kotak gambar yang sama dan belum dikonfirmasi |

Isu `DOC_VISUAL_ASSETS` kini dapat ditindaklanjuti: 31 gambar S3 sudah diekstrak ke pustaka
media dengan `rights_status = 'unknown'` sehingga tetap draft. Empat di antaranya adalah peta
dusun beresolusi 3900x2542 piksel.
