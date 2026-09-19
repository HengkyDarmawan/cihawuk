# Transparansi anggaran

Halaman `/transparansi/anggaran` dan section ringkasan beranda hanya membaca snapshot yang
sudah disetujui. Tabel kerja keuangan tidak pernah dibaca langsung oleh frontend.

## Model data

```text
budget_years (tahun anggaran, punya status dan penanda sementara)
  ├── budget_revisions   original | amended | realization  ← TERPISAH, tidak pernah dijumlah
  │     └── budget_lines (angka per kategori)
  ├── budget_categories  bidang → subbidang → kegiatan (maksimal 3 tingkat)
  ├── budget_projects + budget_project_progress (progres fisik)
  └── budget_documents   dokumen pendukung, wajib disamarkan sebelum publik
```

Kelompok anggaran: `income`, `expenditure`, `financing_in`, `financing_out`.

## Alur kerja

```text
draft → rekonsiliasi → verifikasi → persetujuan → snapshot publik
```

| Permission | Siapa | Boleh apa |
|---|---|---|
| `finance.manage` | Pengelola Keuangan | Mengisi tahun, revisi, kategori, angka, dokumen |
| `finance.verify` | Verifikator Keuangan | Menandai terverifikasi setelah pemeriksaan lolos |
| `finance.publish` | Penerbit Konten | Menyetujui, menerbitkan, menarik, mengunci periode |

Urutannya ditegakkan: menyetujui sebelum verifikasi ditolak 409, dan menerbitkan sebelum
persetujuan ditolak 409.

## Pemeriksaan sebelum terbit

`BudgetService::validate_year()` menolak penerbitan bila:

- anggaran murni belum diisi (realisasi tidak dapat dibandingkan tanpa dasar);
- ada revisi tanpa satu pun angka;
- jumlah komponen tidak sama dengan nilai induknya dan belum ada catatan selisih — yang
  dijumlahkan hanya baris terdalam sehingga induk tidak terhitung dua kali;
- surplus atau defisit belum tertutup pembiayaan neto (toleransi 0,005 rupiah);
- ada baris realisasi yang **melebihi anggaran tanpa penjelasan**, termasuk penerimaan
  pembiayaan yang tidak dianggarkan;
- ada dokumen bersalinan publik yang belum ditandai sudah disamarkan.

Peringatan (tidak menghalangi): tahun dokumen lebih tua dari tahun anggaran, realisasi
dibandingkan anggaran murni karena perubahan belum diisi, dan data masih ditandai sementara.

## Aturan lain

- **Mengubah satu angka pada tahun yang sudah diverifikasi mengembalikan statusnya ke
  rekonsiliasi**; verifikasi dan persetujuan lama gugur.
- Periode terkunci tidak dapat disunting langsung; kuncinya harus dibuka lebih dulu.
- Nilai negatif ditolak — arah uang ditentukan kelompok anggaran, bukan tanda minus.
  Penyesuaian memakai jalurnya sendiri.
- Menarik snapshot langsung mengosongkan halaman publik, section beranda, dan unduhan JSON.

## Halaman publik

Bertingkat sesuai modul-frontend §11: ringkasan → perbandingan murni/perubahan/realisasi →
drilldown rincian → progres fisik kegiatan → dokumen publik. Tersedia unduhan **CSV**
(ber-BOM, anti formula injection) per revisi dan **JSON** snapshot penuh.

## Keadaan sekarang

**Belum ada satu pun angka APBDes nyata.** Dokumen sumber tidak memuat rincian anggaran yang
dapat diverifikasi, dan mengarang angka anggaran dilarang spesifikasi. Halaman publik
menampilkan keadaan kosong sampai pengelola keuangan mengisinya.

Belum ada: form progres fisik di dashboard, alur unggah berkas khusus keuangan, dan ekspor
PDF ringkasan.
