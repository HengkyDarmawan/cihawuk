# Spesifikasi Frontend Berbasis Data Desa Cihawuk

Versi 1.0 — 18 September 2026  
Dokumen pendamping `prompt-master-desa-cihawuk(1).md`

## 1 Tujuan dan kedudukan dokumen

Dokumen ini menerjemahkan isi dokumen profil Desa Cihawuk menjadi kebutuhan frontend publik, komponen CMS, seed awal, visualisasi, dan aturan publikasi. Dokumen ini tidak menjadikan seluruh isi Word sebagai data publik secara otomatis.

AI wajib membedakan empat kondisi data:

1. **Siap disiapkan sebagai seed**: dapat dimasukkan ke staging atau CMS sebagai draft dengan sumber dan tahun yang jelas.
2. **Dapat dipublikasikan setelah persetujuan**: isinya relatif konsisten, tetapi pengelola tetap harus menekan aksi publish.
3. **Perlu verifikasi atau rekonsiliasi**: ada konflik, kategori tumpang tindih, label ambigu, status kedaluwarsa, atau angka yang belum wajar.
4. **Internal atau sensitif**: tidak ditampilkan kepada publik meskipun terdapat dalam dokumen sumber.

Frontend harus membaca data yang telah diterbitkan dari database. Jangan menyalin angka, nama pejabat, foto, peta, sejarah, visi misi, atau daftar fasilitas sebagai string permanen di view.

## 2 Sumber yang dipetakan

| Kode | Isi utama | Peran pada frontend |
|---|---|---|
| S1 | Potensi Desa Cihawuk Desember 2023 | Identitas wilayah, penggunaan lahan, iklim, topografi, pertanian, wisata, penduduk, pendidikan, pekerjaan, lembaga dan prasarana |
| S2 | Tingkat Perkembangan Desa Cihawuk Desember 2023 | Perkembangan penduduk dan keluarga, ekonomi, pendidikan, kesehatan, partisipasi, lembaga, pemerintahan dan APBDes |
| S3 | Kata Pengantar dan Profil Desa Cihawuk 2023 | Pengantar, sambutan, sejarah, visi misi, riwayat kepala desa, calon foto pejabat/lembaga, lambang dan peta gambar |
| S4 | Profil Desa dan Kelurahan 2021 | Arsip pembanding; tidak boleh dipresentasikan sebagai kondisi 2023 atau kondisi terkini |
| S5 | Register aset Desa Cihawuk | Sumber modul aset dan QR; bukan sumber statistik homepage sebelum audit fisik |

Nama salinan dokumen dapat memiliki akhiran `(1)` atau `(2)`. Gunakan checksum dan perbandingan isi untuk mendeteksi duplikasi.

## 3 Arsitektur informasi frontend

Gunakan struktur route berikut sebagai arah implementasi. Route dapat disesuaikan dengan router CI3 selama slug publik stabil dan redirect dari slug lama tersedia.

| Route | Tujuan |
|---|---|
| `/` | Beranda ringkas dan pintu masuk layanan |
| `/profil` | Ringkasan identitas dan pengantar desa |
| `/profil/sejarah` | Sejarah dan linimasa kepemimpinan per periode |
| `/profil/visi-misi` | Visi, misi dan status periode berlakunya |
| `/profil/geografi` | Luas, ketinggian, iklim, lahan, batas dan peta yang terverifikasi |
| `/pemerintahan` | Pengantar pemerintah desa dan lembaga |
| `/pemerintahan/struktur` | Tree dinamis pemerintah desa atau lembaga berdasarkan periode |
| `/potensi` | Daftar dan filter potensi desa |
| `/potensi/{slug}` | Detail potensi, foto, data pendukung dan lokasi terverifikasi |
| `/data-desa` | Ringkasan dataset berdasarkan tahun dan tema |
| `/data-desa/{tema}` | Demografi, ekonomi, pendidikan, kesehatan, lingkungan atau prasarana |
| `/fasilitas` | Direktori fasilitas yang telah diverifikasi |
| `/fasilitas/{slug}` | Detail fasilitas, layanan, lokasi dan kontak publik |
| `/transparansi/anggaran` | Ringkasan dan eksplorasi APBDes yang sudah disetujui |
| `/dokumen` | Dokumen publik yang sudah direview dan disamarkan |

Menu utama tetap mengikuti batas maksimal pada prompt master. `Data Desa`, `Fasilitas Desa`, dan `Transparansi Anggaran` dikelompokkan dalam satu kelompok navigasi.

## 4 Beranda

### 4.1 Hero

- Gunakan foto lanskap atau aktivitas warga Cihawuk yang autentik dan mempunyai izin publikasi.
- Dokumen S3 tidak menyediakan foto lanskap yang layak sebagai hero; jangan memakai foto pejabat, peta hasil scan, atau gambar stok seolah-olah sebagai panorama Cihawuk.
- Konten awal: “Selamat Datang di Desa Cihawuk” dan “Kecamatan Kertasari, Kabupaten Bandung”.
- CTA utama: `Jelajahi Desa` dan `Layanan Warga`.
- Mode foto, video, atau Three.js dikontrol CMS dan memiliki fallback gambar.

### 4.2 Statistik utama

Tampilkan empat kartu dari tahun yang sama dan satu dataset yang telah diterbitkan:

| Indikator | Seed draft 2023 | Unit |
|---|---:|---|
| Total penduduk | 6.809 | orang |
| Laki-laki | 3.510 | orang |
| Perempuan | 3.299 | orang |
| Kepala keluarga | 2.174 | KK |

Di bawah kartu wajib ada label `Data Profil Desa 2023`, tautan sumber atau metodologi, dan tanggal publikasi. Jangan mengganti jumlah penduduk dengan jumlah akun aplikasi.

### 4.3 Sekilas wilayah

Komponen `Sekilas Cihawuk` dapat menampilkan ketinggian 1.514 meter dpl, suhu rata-rata harian 16,30 °C, curah hujan 1.565 mm, dan enam bulan hujan setelah disetujui pengelola. Luas wilayah tidak boleh ditampilkan sebagai satu angka kanonis sebelum konflik `932,35 ha`, `931,35 ha`, dan `931,00 ha` diselesaikan.

### 4.4 Potensi unggulan

Beranda hanya menampilkan potensi yang memiliki judul, ringkasan, cover valid, status verifikasi, dan status publish. Siapkan kategori awal:

- Pertanian
- Alam dan lingkungan
- UMKM dan ekonomi lokal
- Budaya dan masyarakat
- Fasilitas desa

Komoditas dalam S1 seperti kentang, kubis, wortel, cabai, tomat, jagung, dan ubi jalar dapat masuk sebagai draft konten pertanian. Luas contoh kentang 80 ha, kubis 70 ha, wortel 70 ha, dan cabai 15 ha harus menyimpan tahun serta sumber; jangan menjumlahkannya sebagai total wilayah.

### 4.5 Data, fasilitas dan transparansi

Sediakan tiga kartu editorial yang berbeda dari akses cepat:

- `Lihat Data Desa` menuju dashboard statistik publik.
- `Temukan Fasilitas` menuju direktori fasilitas.
- `Pantau Anggaran Desa` menuju halaman transparansi APBDes.

Jika belum ada dataset atau anggaran yang diterbitkan, tampilkan keadaan kosong yang menjelaskan bahwa data sedang diverifikasi. Jangan menampilkan angka demo di production.

## 5 Halaman Profil Desa

### 5.1 Identitas

Seed draft yang dapat disiapkan:

| Field | Nilai sumber |
|---|---|
| Desa | Cihawuk |
| Kecamatan | Kertasari |
| Kabupaten | Bandung |
| Provinsi | Jawa Barat |
| Kode PUM | `320431.2006` |
| Tahun sumber utama | 2023 |

Kode PUM disimpan dan ditampilkan sebagai string. Koordinat `107,7050` dan `7,1999` disimpan sebagai nilai mentah; jangan menentukan tanda lintang, pin kantor, atau pusat wilayah tanpa verifikasi.

### 5.2 Sambutan

S3 memuat kata pengantar dan sambutan kepala desa. Simpan sebagai konten berperiode dengan nama penulis, jabatan saat dokumen dibuat, tanggal dokumen, naskah asli, versi editorial, foto terverifikasi, dan status publish. Jangan menggunakan sambutan tahun 2023 sebagai sambutan pejabat aktif 2026 tanpa konfirmasi.

### 5.3 Sejarah dan linimasa kepemimpinan

Sajikan narasi bahwa Cihawuk disebut sebagai hasil pemekaran Desa Sukapura pada 1984 menurut Profil Desa 2023. Tanggal 14 April dan dasar keputusan disimpan sebagai klaim sumber yang memerlukan verifikasi nomor dokumen.

Siapkan linimasa draft:

| Periode sumber | Nama dalam dokumen |
|---|---|
| 1984–1994 | Buloh |
| 1994–1998 | Ebi Rahmat |
| 1998–1999 | Ate Suhanda |
| 1999–2005 | U. Juhana |
| 2005–2006 | S. Tahyadi |
| 2006–2012 | Aep Saepuloh |
| 2012–2018 | Aep Saepuloh |
| 2018–2019 | Aep Saepudin S.Pd S.E |
| 2019–tahun dokumen | Yaya Dores |

Gabungkan periode orang yang sama hanya setelah pengelola memastikan tidak ada perbedaan masa jabatan. Teks “sampai sekarang” pada dokumen 2023 berarti sampai waktu dokumen dibuat, bukan otomatis sampai 2026.

### 5.4 Visi dan misi

Pisahkan visi Kecamatan Kertasari dari visi Desa Cihawuk. Teks ditampilkan sebagai HTML aksesibel, bukan poster gambar. Poster S3 hanya dapat masuk galeri arsip setelah izin dan keterangannya jelas.

Sediakan field periode berlaku, dokumen dasar, versi naskah asli, versi ejaan yang telah direview, dan status aktif. Jangan mengoreksi makna atau menambahkan program yang tidak tertulis.

### 5.5 Geografi

Halaman geografi dapat memuat:

- Identitas lokasi dan ketinggian.
- Iklim dan topografi.
- Penggunaan lahan.
- Batas wilayah per tahun sumber.
- Peta wilayah yang sudah diverifikasi.
- Catatan metodologi dan konflik data.

Komposisi lahan S1 dapat disiapkan sebagai draft: tanah kering 327,41 ha, tanah basah 0,42 ha, perkebunan 10,00 ha, fasilitas umum 47,61 ha, serta hutan 545,91 ha. Totalnya 931,35 ha dan belum boleh dianggap luas resmi sebelum rekonsiliasi.

## 6 Pemerintahan dan struktur organisasi

### 6.1 Struktur yang dipisahkan

Frontend minimal dapat memilih:

- Pemerintah Desa Cihawuk
- Badan Permusyawaratan Desa
- Lembaga desa lain yang disetujui
- Arsip struktur berdasarkan periode

Data S3 hanya menjadi draft periode 2023 atau periode yang secara eksplisit tertulis. Jangan menandai seluruh nama sebagai pejabat aktif saat ini.

### 6.2 Foto dan identitas

S3 menghasilkan kandidat visual berupa foto kepala desa, perangkat desa, BPD atau lembaga, Babinsa, Bhabinkamtibmas, lambang, serta peta. Urutan objek pada dokumen lama tidak selalu membuktikan pasangan foto dan nama. Setiap foto harus melalui pencocokan manual dengan field:

- nama orang;
- jabatan dan unit;
- periode penugasan;
- sumber berkas dan halaman;
- izin publikasi;
- hasil crop;
- alt text;
- status verifikasi.

Lambang yang terdapat di S3 adalah lambang Kabupaten Bandung dan tidak boleh otomatis disebut logo Desa Cihawuk. Gunakan hanya pada konteks dan izin yang sesuai.

### 6.3 Tampilan publik

Gunakan tree dinamis dengan foto, nama, jabatan, unit, badge Plt atau kosong, pencarian, expand/collapse, zoom, fit screen, dan pilihan periode. Sediakan tampilan daftar hierarkis sebagai fallback aksesibel dan mode cetak.

Klik node membuka drawer atau halaman ringkas berisi tupoksi publik, periode, dan bio yang sudah disetujui. Jangan menampilkan NIK, nomor telepon pribadi, alamat rumah, nomor SK privat, atau data akun login.

## 7 Potensi Desa

### 7.1 Pertanian dan perkebunan

Gunakan kartu komoditas dan detail berbasis tahun. Field minimal: nama, kategori, deskripsi, luas tanam, produksi, satuan, musim/periode, lokasi umum, foto, sumber, status verifikasi, dan catatan metodologi.

Jangan mengubah seluruh tabel S1 menjadi kartu. Tampilkan komoditas dengan data bermakna; tabel lengkap tetap tersedia pada bagian data desa jika sudah diverifikasi.

### 7.2 Alam dan wisata

S1 menyebut jenis potensi gunung sekitar 10,00 ha, goa 0,001 ha, dan air terjun 2,00 ha. Data ini belum menyebut nama tempat, akses, keamanan, pengelola, tarif, titik koordinat, status operasional, atau hak publikasi foto.

Masukkan sebagai draft `potensi`, bukan destinasi wisata aktif. Kartu publik baru boleh dibuat setelah minimal tersedia:

- nama resmi atau nama lokal yang disetujui;
- deskripsi faktual;
- foto autentik;
- lokasi atau cakupan publik yang aman;
- status akses;
- pengelola atau kontak resmi jika ada;
- catatan keselamatan;
- tanggal verifikasi.

### 7.3 UMKM dan usaha lokal

Dokumen memuat kategori ekonomi agregat, bukan direktori UMKM lengkap. Jangan membuat nama, produk, harga, nomor WhatsApp, alamat, atau jam buka. Sediakan formulir CMS untuk menambah profil usaha setelah persetujuan pemilik dan verifikasi desa.

## 8 Data Desa

### 8.1 Navigasi dataset

Halaman data memiliki filter tahun, tema, indikator dan sumber. Tema awal:

- Kependudukan
- Keluarga dan kesejahteraan agregat
- Pendidikan
- Pekerjaan dan ekonomi
- Kesehatan agregat
- Lingkungan dan penggunaan lahan
- Lembaga dan partisipasi
- Prasarana dan fasilitas
- Pemerintahan dan anggaran

Setiap dataset memiliki status `draft`, `in_review`, `published`, `superseded`, atau `archived`. Hanya `published` yang dapat dibaca publik.

### 8.2 Visualisasi yang diperbolehkan

| Data | Visual utama | Catatan |
|---|---|---|
| Laki-laki dan perempuan | Bar atau donut | Boleh menjadi komposisi jika total dan tahun konsisten |
| Usia satu tahunan | Piramida penduduk atau bar kelompok usia | Agregasikan dengan aturan yang terdokumentasi; tabel sumber tetap tersedia |
| Perubahan jumlah penduduk/KK | Line chart | Hanya jika definisi indikator antartahun sama |
| Mata pencaharian | Horizontal bar | Jangan menyimpulkan seluruh penduduk bekerja dari jumlah kategori |
| Penggunaan lahan | Bar atau donut | Hanya setelah total luas direkonsiliasi |
| Fasilitas | Kartu angka dan bar | Angka agregat tidak menggantikan direktori fasilitas |
| APBDes | Bar, progress dan tabel drilldown | Bandingkan anggaran murni, perubahan dan realisasi secara terpisah |

Semua grafik mempunyai judul, tahun, satuan, sumber, waktu publikasi, tabel angka ekuivalen, dan unduhan CSV yang sesuai izin.

### 8.3 Larangan visualisasi menyesatkan

- Total pendidikan S1 sebesar 10.598 melebihi total penduduk 6.809 karena kategorinya berpotensi tumpang tindih. Jangan membuat pie/donut pendidikan seolah-olah setiap warga hanya berada pada satu kategori.
- Data 2021 dari S4 tidak otomatis dapat dibandingkan dengan 2023 jika definisi indikator berbeda.
- Label “tahun lalu” pada S2 tidak otomatis ditetapkan sebagai 2022 tanpa pemeriksaan periode pelaporan.
- Nilai kosong dan tanda `-` adalah `null/unknown`, bukan otomatis nol.
- Data produk domestik desa, pendapatan, aset ekonomi, kesehatan, keamanan dan kriminalitas harus melalui rekonsiliasi serta pemeriksaan sensitivitas sebelum dipublikasikan.

### 8.4 Privasi statistik

Jangan membuka daftar individu. Untuk kesehatan, disabilitas, bantuan, kemiskinan, kriminalitas, keyakinan, dan kategori sensitif lain, tampilkan hanya agregat yang telah disetujui. Terapkan ambang minimum atau penggabungan kategori bila jumlah kecil dapat mengarah pada identifikasi orang atau keluarga.

## 9 Direktori Fasilitas Desa

S1 memuat data agregat fasilitas pendidikan, air bersih, sanitasi, pemerintahan, peribadatan, olahraga, kesehatan, komunikasi dan transportasi. Angka ini dapat menjadi draft statistik, tetapi belum cukup untuk membuat direktori detail.

Contoh seed agregat yang perlu verifikasi:

- Pendidikan formal: 1 TK, 4 SD/sederajat, 1 SMP/sederajat, dan 1 SMA/sederajat.
- Pendidikan keagamaan: 6 RA, 1 MTs, 1 MA, 2 pesantren, 13 DTA, dan 5 PAUD berdasarkan kolom yang diisi pada sumber.
- Air bersih: 10 sumur gali, 1 embung, dan 4 mata air.
- Sanitasi: 1 MCK umum dan 1.999 KK pemilik jamban keluarga.

Satu entitas fasilitas minimal memiliki nama, kategori, pengelola, status aktif, alamat atau wilayah, koordinat terverifikasi opsional, jam layanan, kontak publik berizin, layanan, foto, aksesibilitas, tahun data, sumber, dan tanggal verifikasi. Jika hanya tersedia angka agregat, tampilkan pada Data Desa dan jangan membuat fasilitas fiktif.

## 10 Peta dan media

### 10.1 Peta

S3 memuat satu gambar peta desa dan tiga gambar peta dusun. Perlakukan gambar tersebut sebagai arsip visual, bukan GeoJSON atau bukti batas hukum. Jangan menjiplak garisnya menjadi polygon resmi tanpa proses georeferensi, validasi, dan persetujuan.

Peta Leaflet publik hanya menggunakan:

- titik kantor yang sudah dikonfirmasi;
- titik fasilitas/potensi yang sudah diverifikasi;
- polygon GeoJSON yang sudah disetujui;
- atribusi tile provider yang benar.

Jika data belum siap, tampilkan alamat teks dan status `Peta sedang dilengkapi`.

### 10.2 Media

Semua media memiliki judul, alt text, sumber, tahun, pemilik atau lisensi, izin publikasi, orang yang tampak, kategori, status verifikasi, dan turunan ukuran responsif. Hapus metadata yang tidak perlu pada salinan publik.

Foto dokumen lama boleh dipakai sebagai arsip setelah review. Untuk beranda dan potensi, prioritaskan foto lapangan baru dengan orientasi landscape dan kualitas yang sesuai. Jangan memperbesar foto kecil sampai buram atau menggunakan foto identitas sebagai foto editorial.

## 11 Transparansi anggaran pada frontend

Halaman `/transparansi/anggaran` harus menggunakan transparansi bertingkat:

1. Ringkasan pendapatan, belanja, pembiayaan, surplus/defisit dan SILPA.
2. Perbandingan anggaran murni, anggaran perubahan, realisasi, selisih dan persentase.
3. Drilldown bidang, subbidang, kegiatan dan proyek.
4. Progres fisik, lokasi aman, target, hasil serta foto sebelum/proses/sesudah.
5. Dokumen publik yang telah direview dan disamarkan.
6. Ekspor PDF, CSV/XLSX dan JSON sesuai izin.

Infografik bukan sumber transaksi otomatis. Perbedaan judul tahun, tahun laporan, nilai realisasi lebih dari anggaran, dan label pembiayaan harus direkonsiliasi sebelum publish. Detail NIK, rekening, NPWP, tanda tangan, alamat pribadi, serta data penerima bantuan yang sensitif tidak tampil.

## 12 Model CMS yang diperlukan

Selain tabel pada prompt master, frontend memerlukan konsep konten berikut:

- `source_documents` untuk asal dokumen dan checksum.
- `datasets` dan `dataset_versions` untuk tema, tahun, metodologi dan status.
- `indicators` dan `observations` untuk nilai mentah, normalisasi, satuan dan sumber.
- `places` untuk kantor, fasilitas, potensi dan lokasi publik.
- `potentials` untuk narasi potensi, status kunjungan dan verifikasi.
- `leadership_terms` atau penugasan struktur untuk linimasa pejabat.
- `media_assets` dan `media_usages` agar satu foto dapat dipakai secara terkontrol.
- `public_documents` untuk salinan yang sudah disetujui, bukan file sumber privat.

Setiap konten publik minimal menyimpan `source_year`, `verified_at`, `verified_by`, `publication_status`, `published_at`, `revision`, dan catatan sumber. Perubahan draft tidak boleh langsung mengubah frontend publik.

## 13 Data yang tidak langsung masuk frontend publik

- Nama pengisi dokumen jika tidak relevan bagi masyarakat.
- Nomor identitas, alamat rumah, kontak pribadi, rekening, tanda tangan dan dokumen kepemilikan.
- Daftar individu terkait kesehatan, bantuan, disabilitas, pekerjaan, agama atau kondisi ekonomi.
- Nilai ekonomi yang belum direkonsiliasi atau tampak tidak wajar.
- Data keamanan/kriminalitas dengan jumlah kecil tanpa kajian privasi.
- Sel kosong, tanda minus atau pilihan `Ada/Tidak` yang belum jelas terpilih.
- Nama pejabat dan struktur lama sebagai kondisi terkini.
- Foto pejabat yang belum dipasangkan dan disetujui.
- Gambar peta sebagai polygon resmi.
- Tabel inventaris kantor pada halaman profil; data tersebut masuk modul aset internal dan QR sesuai batas publik.

## 14 Kriteria penerimaan frontend

Implementasi dianggap memenuhi modul ini jika:

1. Beranda tidak memiliki angka, acara, potensi, fasilitas atau foto rekaan.
2. Semua statistik menampilkan tahun, satuan, sumber dan tanggal publikasi.
3. Draft atau data belum terverifikasi tidak dapat diakses melalui URL publik maupun API publik.
4. Konflik luas wilayah tidak disembunyikan atau dipilih sepihak oleh kode.
5. Halaman Data Desa mempunyai tabel ekuivalen untuk setiap grafik.
6. Filter tahun tidak mencampur data 2021 dan 2023 dalam satu kartu tanpa label.
7. Tree organisasi memisahkan periode, orang, jabatan dan akun login.
8. Foto perangkat tidak dipublikasikan sebelum pasangan identitas, periode dan izin disetujui.
9. Peta scan tidak digunakan sebagai batas interaktif resmi.
10. Direktori fasilitas tidak membuat entitas fiktif dari angka agregat.
11. Data sensitif tidak muncul pada HTML, atribut DOM, JSON, sitemap, pencarian atau ekspor publik.
12. Semua halaman mempunyai empty, loading, error, not found dan archived state yang jelas.
13. Tampilan diuji pada 360, 390, 768, 1024 dan 1440 px serta zoom 200 persen.
14. Navigasi keyboard, fokus, alt text, heading, tabel, carousel dan drawer diuji.
15. Halaman tetap dapat membaca konten inti ketika JavaScript atau peta gagal.

