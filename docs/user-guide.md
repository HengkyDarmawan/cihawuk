# Panduan Pengguna

Panduan singkat untuk warga, pelapor tanpa akun, petugas, admin, dan pengelola konten. Nama menu di bawah sama
dengan yang tampil di aplikasi.

---

## 1. Melapor tanpa akun (anonim)

1. Buka situs desa, pilih **Buat Laporan** (atau buka `/lapor`).
2. **Langkah 1** — pilih jenis: *Pengaduan*, *Aspirasi*, atau *Permintaan Informasi*, lalu kategori.
3. **Langkah 2** — tulis judul dan uraian. Lokasi boleh diketik; tombol **Gunakan lokasi perangkat** hanya dipakai
   bila Anda mau membagikan titik lokasi.
4. **Langkah 3** — lampirkan foto/PDF bila perlu (maksimal 3 berkas, masing-masing 5 MB), centang pernyataan, lalu
   kirim.
5. Halaman bukti menampilkan **Nomor Laporan** dan **Kode Akses**.
   - Salin atau cetak keduanya **sekarang**. Kode akses hanya ditampilkan sekali dan tidak dapat dikirim ulang.
   - Jangan membagikan kode akses; siapa pun yang memegangnya dapat membaca laporan Anda.

Anda tidak diminta nama, NIK, atau nomor telepon. Petugas juga tidak dapat mengetahui identitas Anda dari sistem.

### Memantau laporan anonim

1. Buka **Lacak Laporan** (`/lacak`), isi nomor laporan dan kode akses.
2. Anda dapat melihat status, membaca tanggapan, membalas, melengkapi informasi bila diminta, menerima hasil atau
   meminta tindak lanjut, dan menarik laporan (sebelum ditangani).
3. Tekan **Keluar** bila memakai perangkat bersama. Akses otomatis berakhir setelah 30 menit tidak aktif.
4. Bila sudah punya akun warga dan sedang masuk, Anda dapat menekan **Kaitkan ke akun** (setelah mencentang
   persetujuan) agar laporan muncul di dashboard.

Terlalu sering salah memasukkan kode akan menunda percobaan sementara.

---

## 2. Warga berakun

### Mendaftar dan masuk
1. **Daftar** (`/daftar`): isi nama, username, email atau nomor HP, dan password minimal 12 karakter
   (gunakan kalimat yang mudah Anda ingat).
2. Akun berstatus *menunggu aktivasi*. Aktivasi dilakukan lewat tautan email (bila email desa sudah aktif) atau oleh
   petugas di kantor desa, yang memberi kode aktivasi untuk Anda masukkan di **Aktivasi**.
3. **Masuk** (`/masuk`) dengan username atau email.
4. Lupa password: **Lupa password**. Bila email belum aktif, datang ke kantor desa untuk pemulihan.

Warga juga dapat didaftarkan oleh petugas di kantor desa; Anda tetap membuat password sendiri saat aktivasi.

### Dashboard warga (`/warga`)
- **Beranda** — ringkasan laporan dan hal yang menunggu tanggapan Anda.
- **Buat Laporan** — sama seperti formulir anonim, tetapi tercatat di akun Anda. Centang
  *Sembunyikan identitas saya dari petugas biasa* bila perlu (ini bukan anonim penuh; gunakan formulir publik bila
  tidak ingin laporan dikaitkan dengan akun).
- **Laporan Saya** — daftar dan detail laporan, balas, lengkapi informasi, tanggapi hasil, tarik laporan.
- **Notifikasi** — pemberitahuan perubahan status.
- **Profil** dan **Akun** — ubah data diri, password, lihat dan keluarkan sesi di perangkat lain, aktifkan MFA
  (kode dari aplikasi autentikator).

### Arti status
| Status | Artinya |
|---|---|
| Terkirim | Laporan diterima, menunggu diperiksa |
| Sedang diverifikasi | Petugas memeriksa laporan |
| Perlu dilengkapi | Petugas meminta informasi tambahan — balas secepatnya |
| Diteruskan ke petugas | Laporan diserahkan ke petugas penanggung jawab |
| Sedang ditangani | Petugas sedang menindaklanjuti |
| Menunggu tanggapan Anda | Petugas melaporkan hasil — pilih *Saya menerima hasil ini* atau *Saya meminta tindak lanjut* |
| Selesai | Penanganan selesai |
| Tidak dapat diproses | Ditolak dengan alasan yang dijelaskan |
| Dirujuk ke layanan lain | Bukan kewenangan desa; ada petunjuk tujuan |
| Ditarik pelapor | Anda menarik laporan |

---

## 3. Petugas loket

Menu **Input Loket** (`/admin/laporan/buat`):

1. Pilih salah satu:
   - **Pelapor memiliki akun warga** — cari akunnya (hasil pencarian disamarkan).
   - **Tanpa akun, bersedia memberi identitas** — isi nama/telepon/email; disimpan terenkripsi untuk tindak lanjut.
   - **Pelapor anonim** — setelah disimpan, halaman detail menampilkan *Bukti untuk pelapor (tampil sekali)* berisi
     nomor laporan dan kode akses; serahkan langsung kepada warga.
2. Isi jenis, kategori, judul, uraian sesuai penyampaian warga, lampiran bila ada. Centang
   *Tandai sebagai laporan rahasia* bila perlu, lalu simpan.

Nama Anda tercatat sebagai pencatat, terpisah dari pelapor. Jangan menyalin kode akses ke catatan lain.

Pendaftaran akun warga di loket: **Pengguna → Daftarkan warga** (bila punya izin). Berikan kode aktivasi yang tampil sekali
langsung kepada warga.

---

## 4. Petugas penanganan dan admin pelayanan

### Daftar laporan (**Laporan**)
- Tabel dapat dicari, diurutkan, dan difilter (status, jenis, kategori, kanal, tanggal, prioritas internal,
  *Terlambat*, *Tugas saya*).
- Anda hanya melihat laporan yang menjadi hak Anda (antrian verifikasi, tugas Anda, atau unit yang Anda pantau).
- Tanda **Terlambat** berarti target layanan terlewati.

### Detail laporan
Tombol yang tampil menyesuaikan status dan izin Anda:

| Tombol | Kapan dipakai |
|---|---|
| Mulai verifikasi | Laporan baru masuk |
| Minta kelengkapan → Kirim permintaan | Informasi kurang; tulis pertanyaan yang jelas |
| Disposisi ke petugas / Pindahkan penugasan → Simpan disposisi | Pilih unit dan petugas; pemindahan wajib alasan |
| Tolak laporan | Pilih alasan dan tulis penjelasan untuk pelapor; duplikat wajib nomor laporan induk |
| Simpan rujukan | Bukan kewenangan desa; tulis tujuan dan penjelasan; penerusan nyata wajib bukti |
| Terima penugasan & mulai tangani | Anda mulai menangani |
| Tindak lanjut / catatan → Simpan catatan | Catatan publik (terlihat pelapor) atau internal (hanya petugas), bisa dengan lampiran |
| Sampaikan hasil penanganan → Kirim hasil | Penanganan selesai; pelapor diminta menanggapi |
| Tutup sesuai kebijakan → Tutup laporan | Pelapor tidak menanggapi sampai batas waktu; tulis dasar kebijakannya |
| Lanjutkan penanganan | Hasil belum tuntas |
| Buka kembali | Laporan selesai perlu ditangani lagi; wajib alasan |
| Buka identitas | Hanya bila perlu; wajib alasan; tercatat audit |
| Catat konflik | Petugas terkait/terlapor tidak boleh menangani laporan ini |

Jika muncul pesan **"Data telah diubah oleh pengguna lain"**, muat ulang halaman lalu ulangi tindakan.

Laporan kategori sensitif (perilaku aparat, perlindungan) hanya terlihat oleh penangan laporan rahasia.

### Ekspor Rekap
**Ekspor Rekap** → pilih filter dan format (CSV/PDF) → tunggu notifikasi → unduh. Berkas hanya berisi laporan dalam
lingkup Anda, tanpa identitas pelapor, dan kedaluwarsa otomatis.

---

## 5. Super Admin

- **Pengguna** — daftarkan/cari akun, aktifkan warga, tangguhkan/tutup akun (dengan alasan), beri/cabut role,
  terbitkan pemulihan manual, reset MFA. Aksi sensitif meminta password Anda lagi.
- Super Admin **tidak** otomatis membaca isi laporan; beri diri Anda role pelayanan bila memang bertugas.
- Super Admin aktif terakhir tidak dapat dinonaktifkan.
- **Pengaturan** — umum & situs (kontak, jam layanan), kalender kerja & hari libur, kebijakan target layanan,
  kategori layanan, informasi sistem (status job, outbox email).
- **Log Audit** — riwayat tindakan penting beserta pelaku dan waktu.
- Sangat disarankan semua akun pengelola mengaktifkan MFA di **Akun**.

---

## 6. Editor dan penerbit konten

### Mengatur beranda (**Pengaturan Beranda**)
Beranda tersusun dari section. Perubahan tersimpan sebagai draft dan **belum tampil publik** sampai diterbitkan.

1. Buka **Website dan CMS → Pengaturan Beranda**. Daftar section tampil beserta status aktif/nonaktif dan urutannya.
2. **Ubah** membuka form isi section. Isian mengikuti jenis section (judul, tautan, jumlah kartu, tahun data);
   tidak ada kolom HTML bebas.
3. Urutan diubah dengan tombol ↑ ↓, seret-dan-lepas, atau menyunting daftar ID, lalu tekan **Simpan urutan**.
4. **Nonaktifkan** menyembunyikan section dari susunan berikutnya; **Arsipkan** mengeluarkannya dari draft tanpa
   menghapus riwayat versinya.
5. **Pratinjau draft** membuka tampilan seperti aslinya lewat tautan berumur pendek yang hanya berlaku untuk Anda.
6. Bila Anda penyusun: tekan **Ajukan untuk review**. Bila Anda penerbit: **Setujui** lalu **Terbitkan sekarang**,
   atau **Jadwalkan** pada tanggal dan jam WIB tertentu.
7. **Tarik dari publik** menghentikan tampilan tanpa menghapus riwayat; **Kembalikan** pada daftar revisi
   menerbitkan ulang susunan lama.

Section yang modulnya belum aktif (mis. transparansi anggaran) tidak dapat ditambahkan dan tidak ikut diterbitkan.
Section statistik hanya menerima indikator yang nilainya sudah terbit dan terverifikasi pada tahun yang dipilih.

### Membuat halaman publik baru (**Halaman Publik**)
1. Buka **Website dan CMS → Halaman Publik**, isi **Kunci halaman** (mis. `info_layanan`; tidak pernah berubah)
   dan **Judul halaman**, lalu **Buat draft**. Slug dibuat dari judul bila dikosongkan.
2. Pada halaman itu, lengkapi ringkasan, SEO, dan centang **Boleh diindeks mesin pencari** bila halaman ingin
   muncul di sitemap dan pencarian.
3. **Tambah section**, isi, lalu **Aktifkan**. Section nonaktif tidak ikut terbit.
4. Ajukan review, lalu penerbit menyetujui dan menerbitkan. Selama belum terbit, alamatnya 404 bagi pengunjung.
5. Setelah terbit, halaman dapat dibuka di `/{slug}`. Mengganti slug membuat alamat lama otomatis dialihkan 301.
6. **Tarik dari publik** mengembalikan alamat itu ke 404 tanpa menghapus riwayat.

### Mengatur menu (**Menu dan Navigasi**)
1. Pilih lokasi menu (header, seluler, footer, akses cepat), lalu tambah item dengan label dan jenis tautan:
   path aplikasi (mis. `/profil`), halaman CMS yang sudah terbit, atau URL luar `http`/`https`.
2. Submenu dibuat dengan memilih **Induk**. Menu maksimal dua tingkat.
3. Urutkan dengan ↑ ↓, nonaktifkan item yang belum siap, lalu **Terbitkan menu**. Sebelum diterbitkan, situs
   publik masih memakai susunan lama.
4. **Kembalikan** pada riwayat revisi menerbitkan ulang susunan menu sebelumnya.

Yang ditolak server: tautan ke `/admin`, `/warga`, `/berkas`, alamat `javascript:` atau `data:`, halaman CMS
yang belum terbit, dan tingkat ketiga.

### Mengubah identitas dan tema (**Identitas dan Tema**)
1. Isi nama situs, wilayah, alamat kantor, kontak, jam layanan, teks footer, media sosial, serta logo/favicon.
2. Pada bagian Tema, ubah empat warna (format `#RRGGBB`), font, preset sudut, dan mode hero. Warna yang kontrasnya
   di bawah batas keterbacaan akan ditolak beserta alasannya.
3. **Simpan draft** tidak mengubah situs. Penerbit menekan **Terbitkan sekarang** agar berlaku publik;
   **Kembalikan** pada riwayat mengembalikan revisi sebelumnya berikut draftnya.

Catatan: jangan memakai lambang Kabupaten Bandung sebagai logo desa — kolom logo sengaja dibiarkan kosong sampai
lambang resmi desa tersedia.

### Menyusun konten (**Konten Situs**)
- Jenis: profil desa, berita & pengumuman, potensi, agenda, galeri, dokumen publik, pejabat, menu, hero.
- Simpan sebagai **Draft**, lalu **Kirim untuk review**. Penerbit menekan **Terbitkan** atau mengarsipkan.
- Mengubah slug otomatis membuat pengalihan dari alamat lama.
- Setiap simpan membuat revisi.
- Tombol **Aktifkan pratinjau draft** di Ringkasan menampilkan draft di situs publik hanya untuk Anda (ada label
  pratinjau). Matikan setelah selesai.

### Media
- Unggah JPG/PNG/WebP/PDF di **Media**; isi teks alternatif, nama fotografer/sumber, dan status hak.
- Media dengan status hak "belum jelas" tidak tampil di situs publik.
- Media yang masih dipakai konten tidak dapat dihapus.

### Data & Statistik
- **Sumber** — daftar dokumen S1–S4 dan observasi hasil impor. Reviewer dapat menerima, menolak, mengoreksi
  (nilai mentah tetap disimpan), atau mempromosikan observasi menjadi nilai statistik.
- **Isu** — konflik data sumber; tutup dengan catatan penyelesaian.
- Nilai statistik harus **terverifikasi** sebelum dapat diterbitkan oleh penerbit. Situs selalu menampilkan sumber
  dan tahun data.

---

## 7. Bantuan

- Tidak bisa masuk atau lupa kode akses laporan: datang ke kantor desa (kode akses anonim tidak dapat dipulihkan —
  buat laporan baru bila perlu).
- Halaman **Bantuan akun** (`/bantuan-akun`) menjelaskan langkah pemulihan.
- Kebijakan privasi: **Privasi** di footer situs.

---

## Modul baru: langkah praktis bagi pengelola

Seluruh modul di bawah memakai pola yang sama: **susun draft → verifikasi → terbitkan**.
Menyimpan draft tidak pernah mengubah halaman publik.

### Data Desa (Verifikator Data lalu Penerbit Konten)

1. Buka **Data Desa > Dataset Publik**, pilih dataset atau buat baru.
2. Tambahkan indikator, lalu tekan **Verifikasi** pada setiap nilai yang sudah dicocokkan
   dengan dokumen sumber.
3. Tekan **Periksa**. Bila ada yang merah, perbaiki lebih dulu; daftarnya menjelaskan apa
   yang kurang.
4. Penerbit Konten menekan **Terbitkan**. Halaman `/data-desa` langsung terisi.
5. Untuk memasangnya di beranda: **Pengaturan Beranda > Statistik**, pilih dataset dan
   maksimal empat indikator, simpan, lalu terbitkan beranda.

### Profil desa (Editor lalu Penerbit)

1. **Profil Desa** menampilkan tujuh blok. Isi blok yang datanya sudah pasti.
2. Tekan **Ajukan** pada blok yang siap, lalu Penerbit menekan **Verifikasi**.
3. Isi linimasa kepemimpinan. Centang "sampai sekarang menurut sumber" hanya bila dokumen
   memang menulis begitu — jangan mengisi tahun selesai karangan.
4. Tekan **Terbitkan profil**. Empat halaman profil publik terisi sekaligus.

### Struktur organisasi (Admin Website lalu Penerbit)

1. **Struktur Organisasi > Periode**: buat periode, lalu isi unit dan jabatan.
2. Tambahkan orang pada daftar **Orang**. Akun login tetap terpisah dari data orang.
3. Buat penugasan. Jabatan yang belum ada orangnya diisi jenis **Kosong**, bukan dibiarkan.
4. Penerbit menekan **Terbitkan** dan memilih apakah periode itu menjadi tampilan bawaan.

### Fasilitas dan UMKM

- Fasilitas hanya untuk objek nyata. Angka seperti "4 SD" ditolak sistem; angka itu tempatnya
  di Data Desa.
- Lokasi yang berisiko dicentang **sensitif** supaya koordinatnya tidak masuk peta publik.
- UMKM wajib mencatat persetujuan pemilik beserta kapan dan bagaimana diperolehnya. Bila
  pemilik mencabutnya, tekan **Cabut persetujuan** — profilnya langsung turun dari situs.

### Transparansi anggaran

1. **Anggaran dan Realisasi**: buat tahun anggaran, lalu kategori (bidang → subbidang →
   kegiatan), lalu revisi (murni, perubahan, realisasi) dan angkanya.
2. Tekan **Mulai rekonsiliasi**, lalu Verifikator Keuangan menekan **Verifikasi**. Kalau ada
   selisih komponen atau realisasi melebihi anggaran, isi catatan selisihnya lebih dulu.
3. Penerbit menekan **Setujui** lalu **Terbitkan**.
4. Mengubah satu angka setelah verifikasi mengembalikan statusnya ke rekonsiliasi — itu
   disengaja.

### Aset dan QR

1. **Aset dan QR**: buat kategori dan lokasi, lalu register aset.
2. Buat unit fisik satu per satu, atau pakai **usulan pemecahan unit** dengan menuliskan
   alasannya.
3. Ubah status unit menjadi aktif beserta kondisinya; setiap perubahan minta alasan dan
   tersimpan sebagai histori.
4. Tekan **Terbitkan token baru**. Tautan QR tampil **sekali**; salin sekarang karena tidak
   dapat ditampilkan lagi. Bila label hilang, tekan **Cabut token** lalu terbitkan yang baru.
5. **Audit Aset**: buat sesi, tentukan scope, terbitkan (daftar target dibekukan), catat
   temuan, verifikasi, lalu tekan **Koreksi master** bila temuannya diterima.

### Gudang persediaan

1. **Gudang Persediaan**: buat lokasi dan barang beserta satuan dasarnya.
2. Tambahkan konversi satuan bila perlu, misalnya 1 rim = 500 lembar.
3. Buat transaksi (penerimaan, pengeluaran, transfer, retur, penyesuaian), tambahkan
   barisnya, lalu **Posting**. Setelah diposting transaksi tidak dapat diubah.
4. **Stock opname**: buka opname, isi hasil hitung, lalu tutup. Selisihnya dihitung sistem
   dan diposting sebagai penyesuaian.

### Pemeliharaan

**Pengaturan > Pemeliharaan** memperlihatkan antrean gagal, ukuran penyimpanan, modul yang
tidak aktif, pekerjaan yang menunggu verifikasi, unit aset tanpa QR, stok di bawah minimum,
dan tautan internal yang rusak. Jalankan `php public/index.php tools run_jobs` secara berkala
agar isinya mutakhir.

