# Aset, QR, audit fisik, dan gudang persediaan

Dua domain yang sengaja dipisah: **aset tetap** dicatat sebagai register dan unit fisik,
**barang persediaan** dicatat sebagai saldo yang bertambah dan berkurang. Saldo aset tidak
pernah dikurangi seperti stok.

## Aset tetap

### Dua tingkat data

- `asset_registers` — identitas administrasi dan keuangan: kategori, kode lama, nama, uraian,
  volume sumber **apa adanya**, tahun perolehan, nilai, asal-usul, status kepemilikan.
- `asset_units` — barang fisik yang benar-benar diperiksa atau ditempeli QR: asset tag unik,
  nomor seri (terenkripsi), merek, tipe, lokasi, penanggung jawab, lifecycle, kondisi.

Satu register dapat mewakili nol, satu, atau banyak unit. **Pemecahan unit adalah usulan yang
wajib beralasan minimal 10 karakter**; volume seperti `2 Unit` atau `I Set` tidak pernah
dipecah otomatis.

### Status dan histori

Lifecycle (`draft`, `active`, `in_maintenance`, `inactive`, `transferred`, `disposed`,
`lost`) terpisah dari kondisi (`not_assessed`, `good`, `minor_damage`, `major_damage`).

Setiap perubahan menulis `asset_status_events` **dan** memperbarui master dalam satu
transaksi, dengan alasan wajib. Menonaktifkan tidak menghapus unit maupun historinya.

### QR publik

- Token acak 128 bit; yang disimpan hanya **digest SHA-256**. Token asli dikembalikan sekali
  saat diterbitkan dan tidak masuk basis data maupun audit log.
- Menerbitkan token baru otomatis mencabut yang lama.
- `/aset/q/{token}` menjawab "barang ini apa" dengan tampilan mobile-first.
- **Tidak pernah tampil**: harga dan nilai buku, nomor dokumen, sertifikat, nomor seri,
  lokasi bertanda sensitif, nama penanggung jawab, catatan audit internal, biaya perawatan.
- Setiap keadaan punya halamannya sendiri: aktif, pemeliharaan, tidak aktif, dipindahtangankan,
  dihapuskan, hilang, token dicabut, QR tidak dikenal. Tidak ada 500 atau halaman kosong.
- Unit yang masih `draft` tidak diakui publik meski tokennya sah.
- Halaman `noindex`, di luar sitemap, dan dibatasi rate limit `asset_qr_ip` (120 per 15 menit)
  yang tetap longgar untuk audit massal petugas.
- Halaman menyatakan bahwa QR adalah identitas inventaris, **bukan** bukti kepemilikan,
  sertifikat hukum, atau tanda tangan elektronik.

### Mutasi, peminjaman, pemeliharaan

- Mengajukan mutasi **tidak** mengubah lokasi; lokasi berubah setelah serah terima diterima.
- Nama peminjam disimpan terenkripsi; satu unit tidak boleh punya dua peminjaman aktif.
- **Menutup pemeliharaan tidak otomatis membuat kondisi menjadi baik**; kondisi sesudahnya
  dinilai dan dicatat terpisah lewat perubahan status.

### Audit fisik

```text
draft → terbit (target DIBEKUKAN) → temuan → verifikasi → koreksi master → tutup → addendum
```

- Menerbitkan sesi membekukan `asset_audit_targets` beserta snapshot kondisi saat itu.
  Perubahan master sesudahnya tidak mengubah snapshot.
- Temuan **tidak menimpa master**. Perbedaan ditampilkan terpisah; verifikasi dan koreksi
  adalah dua langkah berbeda, dan koreksi meninggalkan histori sendiri.
- Temuan `not_found` yang dikoreksi menjadikan unit berstatus `lost`.
- Sesi tidak dapat ditutup selama masih ada temuan yang belum diverifikasi.
- Sesudah ditutup, satu-satunya jalan koreksi adalah **addendum** yang teraudit.
- Badge "Data terverifikasi" pada halaman QR hanya menyala setelah ada temuan terverifikasi.

### Impor register

`tools import_assets <berkas.csv> [kode_sumber]` membaca berkas dari `reference/imports/`.

- `raw_json` disimpan apa adanya; usulan register disimpan terpisah.
- Baris bermasalah **ditandai**, bukan dibuang: nama kosong → `failed`, kode ganda atau tahun
  tidak wajar → `conflict`, harga kosong dan keberadaan kosong → catatan review.
- Volume yang bukan angka murni diberi catatan bahwa pemecahan unit harus diputuskan manual.
- Idempotent lewat checksum berkas + nomor baris: mengulang impor melewati seluruh barisnya.
- Baris `failed` tidak dapat dikomit sebelum diperbaiki; komit ulang tidak membuat register kedua.

**Berkas S5 asli belum tersedia**, dan berformat `.xlsx` yang harus diekspor ke CSV lebih dulu
karena proyek ini tidak memaketkan pembaca xlsx. Importer diuji dengan
`tests/fixtures/aset-contoh.csv`.

### Batasan yang diketahui

- **Label QR belum menghasilkan PDF bergambar QR.** Batch label, histori cetak, jumlah salinan,
  offset lembar, dan alasan cetak ulang sudah ada, tetapi tidak ada pustaka pembuat gambar QR
  yang dipaketkan dan CSP melarang mengambilnya dari CDN.
- Form unggah dokumen kepemilikan belum dibuat (tabelnya ada dan sudah dikecualikan dari publik).
- Halaman QR untuk petugas login belum dibuat.

## Gudang persediaan

### Saldo

`inventory_ledger` bersifat **append-only** dan menjadi sumber saldo kanonis. Tidak ada kolom
saldo yang dapat disunting. Saldo selalu dijumlahkan dari `quantity_delta`.

### Aturan yang ditegakkan

- **Stok negatif ditolak secara transaksional.** Posting mengunci baris barang
  (`SELECT ... FOR UPDATE`) sebelum membaca saldo, sehingga dua pengeluaran atas stok terakhir
  tidak dapat sama-sama berhasil — yang kedua ditolak 409 dan saldo tetap konsisten.
- Konversi satuan memakai **numerator dan denominator bilangan bulat positif**. Konversi yang
  menghasilkan jumlah pecahan ditolak, dan satuan tanpa konversi terdaftar juga ditolak.
- Transaksi yang sudah diposting tidak dapat diubah; koreksi memakai penyesuaian baru.
- Penyesuaian wajib disertai alasan.
- Barang bertanda batch wajib mengisi nomor batch.
- Pemohon tidak dapat menyetujui permintaannya sendiri.

### Stock opname

Membuka opname membekukan saldo harapan per barang pada lokasi itu. Petugas mengisi hasil
hitung, dan **selisih dihitung server**, bukan diketik. Menutup opname membuat transaksi
penyesuaian dari selisih itu lalu mempostingnya; opname tidak dapat ditutup selama masih ada
barang yang belum dihitung.

### Batasan yang diketahui

- Belum ada QR untuk item atau bin gudang (opsional menurut spesifikasi).
- Laporan barang tidak bergerak dan rekap penerimaan/pengeluaran per periode belum dibuat;
  yang sudah ada adalah kartu stok, saldo per lokasi, dan daftar di bawah minimum.
- Halaman publik tidak pernah menampilkan saldo gudang.
