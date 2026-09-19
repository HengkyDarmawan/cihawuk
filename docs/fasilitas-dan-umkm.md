# Direktori fasilitas dan UMKM

Dua direktori publik yang sama-sama menolak data karangan: fasilitas tidak dibuat dari angka
agregat, dan profil UMKM tidak terbit tanpa persetujuan pemiliknya.

## Fasilitas desa

### Model data

- `places` — lokasi publik (kantor, fasilitas, potensi) beserta koordinat opsional dan
  penanda `is_sensitive`.
- `facilities` — satu entri untuk satu objek nyata.
- `facility_services` — layanan yang disediakan fasilitas itu.

### Aturan yang ditegakkan

- **Angka agregat bukan fasilitas.** Nama yang terbaca sebagai hitungan (misalnya `4 SD`)
  ditolak dengan pesan yang mengarahkan angkanya ke Data Desa. Tidak ada importer yang
  membuat fasilitas dari angka agregat.
- Entri wajib punya alamat atau lokasi terdaftar.
- Kontak publik hanya tersimpan bila izinnya dicatat, dan berhenti tampil begitu izinnya
  dicabut.
- **Koordinat hanya masuk peta publik bila lokasinya terverifikasi DAN tidak sensitif.**
  Keduanya diuji langsung terhadap HTML publik.
- Menyimpan fasilitas atau lokasi mencabut verifikasi sebelumnya.
- Penerbitan diblokir selama masih ada penghalang: belum diverifikasi, tanpa alamat maupun
  lokasi, tanpa tahun data, tanpa sumber, atau kontak tanpa izin.
- Menarik dan mengarsipkan tidak menghapus barisnya.

### Permission

`facilities.edit` (Admin Website, Editor Konten) untuk menyusun; `facilities.publish`
(Penerbit Konten) untuk memverifikasi, menerbitkan, menarik, dan mengarsipkan.

### Keadaan sekarang

Direktori **kosong**. Dokumen sumber hanya memuat jumlah (1 TK, 4 SD, 10 sumur gali, dan
seterusnya), bukan nama, alamat, dan pengelolanya. Angka-angka itu menjadi bahan Data Desa.
Halaman `/fasilitas` menjelaskan hal ini pada keadaan kosongnya.

## Direktori UMKM

### Aturan yang ditegakkan

- Kontak usaha **tidak tersimpan** tanpa persetujuan pemilik.
- Persetujuan wajib disertai catatan yang dapat ditelusuri (kapan dan bagaimana diperoleh),
  bukan sekadar centang.
- **Mencabut persetujuan langsung menurunkan profilnya** dari direktori publik dan mencatat
  alasannya.
- Usaha nonaktif dan yang diarsipkan tidak tampil, tetapi barisnya tidak dihapus.
- Penerbitan memerlukan persetujuan pemilik dan minimal deskripsi atau daftar produk.

### Permission

`umkm.edit` (Admin Website) untuk menyusun; `umkm.publish` (Penerbit Konten) untuk
menerbitkan, menarik, mencabut persetujuan, dan mengarsipkan.

### Keadaan sekarang

Direktori **kosong**. Dokumen sumber hanya memuat kategori ekonomi agregat, bukan daftar
usaha, dan spesifikasi melarang mengambil nama, produk, harga, atau nomor kontak dari sumber
lain tanpa izin pemiliknya.

## Potensi desa

Field tambahan menurut modul-backend §12.1 sudah tersedia pada form potensi: status akses
pengunjung, pengelola atau kontak resmi, catatan keselamatan, dan lokasi terdaftar. Potensi
hanya dapat dijadikan unggulan beranda bila sudah terbit, terverifikasi, **dan** punya foto
sampul.
