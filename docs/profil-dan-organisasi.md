# Profil desa dan struktur organisasi

Dua modul yang sama-sama memakai pola "draft berversi → verifikasi → snapshot publikasi".

## Profil desa

### Blok terstruktur

`application/config/profile_blocks.php` memuat **7 blok tertutup**: identitas, sambutan,
sejarah, visi, misi, geografi, kontak. Setiap blok punya daftar field sendiri dengan tipe
`text`, `textarea`, `number`, `decimal`, `code`, `string_list`, atau `pair_list`.

Pengelola **tidak dapat menulis HTML**. Field di luar registry dibuang server tanpa
pemberitahuan, sehingga form yang dimodifikasi tidak dapat menyelundupkan data.

Setiap blok menyimpan periode berlaku, dokumen sumber, catatan sumber, status verifikasi,
dan versi. Menyimpan blok membuat versi baru **dan mencabut verifikasi sebelumnya**, karena
yang diverifikasi adalah isi yang lama.

### Aturan yang ditegakkan

- **Konflik luas wilayah tidak dapat disembunyikan.** Bila jumlah komposisi penggunaan lahan
  berbeda dari observasi `area_total_ha` mana pun, catatan konflik menjadi wajib. Halaman
  geografi menampilkan jumlah komposisinya sendiri beserta catatan itu, dan tidak pernah
  menyebut satu angka sebagai luas resmi.
- Visi kecamatan yang disalin sama persis dengan visi desa ditolak.
- Sambutan wajib punya tahun periode sebelum terbit, supaya sambutan lama tidak tampil
  seolah sambutan pejabat saat ini.
- Kode PUM disimpan dan ditampilkan sebagai string.
- Koordinat mentah disimpan apa adanya; tanda lintang tidak ditentukan otomatis.

### Linimasa kepemimpinan

`leadership_terms` menyimpan tahun mulai, tahun selesai **nullable**, dan `ongoing_claim`.
Tanda itu berarti dokumen sumber menulis "sampai sekarang" — yaitu sampai dokumen dibuat,
bukan sampai hari ini. Periode bertanda itu tidak boleh punya tahun selesai, dan halaman
publik menulis rentangnya sebagai "2019–tahun dokumen".

Nama yang muncul pada lebih dari satu periode menghasilkan **peringatan**, bukan penggabungan
otomatis. Halaman publik tetap menampilkan keduanya terpisah.

### Publikasi

Satu snapshot (`cms_publication_snapshots`, `target_type = 'profile'`, `target_id = 0`)
memuat seluruh blok terbit beserta linimasanya, sehingga halaman publik tidak pernah
setengah lama setengah baru. Menarik profil mengosongkan halaman publik tetapi menyimpan
riwayat snapshotnya. Rollback menerbitkan ulang isi snapshot lama sebagai revisi baru.

Halaman publik: `/profil`, `/profil/sejarah`, `/profil/visi-misi`, `/profil/geografi`.

## Struktur organisasi

### Model data

```text
org_periods (periode)
  ├── org_units (unit atau lembaga, boleh bertingkat)
  ├── org_positions (jabatan, punya atasan dan level)
  └── org_assignments (penugasan: orang -> jabatan)
people (orang)  — TERPISAH dari `users`
```

`people` sengaja bukan `users`: menutup akun login tidak menghapus profil pejabat, dan
sebaliknya.

### Aturan yang ditegakkan

- Self-parent, cycle, atasan lintas periode, dan kedalaman lebih dari 5 tingkat ditolak.
  Penelusuran cycle menyusuri seluruh rantai induk, bukan satu tingkat.
- Satu jabatan hanya boleh punya **satu penugasan aktif**; penugasan lama harus diakhiri
  lebih dulu, dan mengakhiri tidak menghapus barisnya.
- Penugasan `vacant` tidak boleh punya orang; `definitive` dan `acting` wajib punya orang.
- Menonaktifkan jabatan yang masih punya bawahan aktif ditolak.
- **Foto tanpa izin tidak tersimpan**, dan snapshot hanya memuat foto yang izinnya tercatat.
- **Nomor SK tidak pernah publik**: disimpan terenkripsi dan tidak ikut ke snapshot.
- Menyalin struktur antar periode menyalin unit dan jabatan saja; penugasan tidak ikut karena
  masa jabatannya sudah berakhir.
- Hanya satu periode yang menjadi tampilan publik bawaan.

### Tampilan publik

`/pemerintahan/struktur` merender pohon sebagai **daftar hierarkis semantik** (`<ul>`/`<li>`)
yang lengkap di HTML. `public/assets/site/js/org-tree.js` hanya menambahkan lipat/buka dan
pencarian di atasnya. Halaman tetap utuh tanpa JavaScript, dapat dicetak, dan terbaca
pembaca layar.

Spesifikasi menyebut d3-org-chart. Pustaka itu tidak dipaketkan di `public/assets/vendor/`
dan CSP situs melarang skrip dari CDN, jadi lapisan visualnya belum memakai d3. Data dan
markup dasarnya sudah siap bila nanti pustaka itu dipaketkan.

## Batasan yang diketahui

- Blok sambutan, visi, misi, dan kontak belum berisi: naskahnya tidak ada pada dokumen sumber.
- Foto pejabat belum dipasangkan; S3 memuat foto desa lain sehingga pemasangan harus manual.
- Belum ada penjadwalan publikasi dan alur review terpisah untuk profil maupun struktur.
- Drag-and-drop, zoom, dan fit-screen pada tree belum ada.
