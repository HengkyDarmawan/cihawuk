# Data Desa dan statistik publik

Dokumen ini menjelaskan bagaimana angka sampai ke halaman `/data-desa` dan kartu statistik
beranda, beserta aturan yang ditegakkan server. Ditulis mengikuti kode yang benar-benar ada.

## Hierarki data

```text
source_documents        dokumen sumber (S1–S4), punya checksum
  └── import_batches    satu kali impor, idempotent lewat checksum
        └── source_observations   nilai MENTAH + nilai ternormalisasi, tidak pernah ditimpa
              └── statistic_values     nilai kanonis per indikator per tahun
                    └── dataset_versions   rangkaian nilai yang siap diterbitkan
                          └── cms_publication_snapshots (target_type = 'dataset')
```

Observasi mentah tidak pernah diubah. Koreksi dicatat sebagai nilai terpisah, dan dataset
hanya merangkai nilai yang sudah **diverifikasi**.

## Registry

`application/config/datasets.php` memuat daftar tertutup:

- **9 tema**: population, family_welfare, education, employment, health, environment,
  institution, infrastructure, government.
- **4 jenis grafik**: bar, bar horizontal, donut, kartu angka. Donut hanya untuk komposisi.
- **Tema sensitif**: health dan family_welfare, dengan ambang data kecil **10**.
- **Total komposisi**: `population_sex` harus berjumlah sama dengan `population_total`.

## Pemisahan izin

| Permission | Siapa | Boleh apa |
|---|---|---|
| `data.import` | Verifikator Data | Mengimpor dokumen sumber ke staging |
| `data.review` | Verifikator Data | Menyusun dataset, menambah seri, memverifikasi nilai |
| `data.publish` | Penerbit Konten | Menerbitkan, menarik, rollback, mengarsipkan dataset |

Pemegang `data.review` **tidak** dapat menerbitkan (403), dan pemegang `data.publish`
**tidak** dapat mengubah tanda verifikasi nilai (403). Keduanya diuji lewat HTTP.

## Pemeriksaan sebelum terbit

`DatasetService::validate_version()` menolak penerbitan bila:

- dataset belum punya seri indikator;
- metodologi kurang dari 20 karakter, atau sumber belum dicantumkan;
- ada nilai yang belum diverifikasi;
- ada nilai kosong — *kosong berarti tidak diketahui, bukan nol*;
- tema sensitif memuat angka di bawah ambang 10 tanpa ditandai `suppressed`;
- donut dipakai untuk seri yang bukan komposisi sah, atau komposisinya tidak lengkap;
- jumlah komponen tidak sama dengan indikator totalnya (toleransi 0,001);
- satuan antar anggota satu komposisi berbeda;
- `sensitivity = restricted`.

Hasil pemeriksaan disimpan pada `dataset_versions.validation_status` dan
`validation_report`, dan mengubah apa pun mengembalikan statusnya menjadi `pending`.

## Halaman publik

`/data-desa` menampilkan satu kartu per dataset terbit. Setiap dataset memuat:

- grafik `<canvas>` **dan** tabel angka yang setara (aturan wajib modul-frontend §8.2);
- satuan, tahun, cakupan, sumber, metodologi, catatan kualitas, dan waktu terbit;
- unduhan CSV ber-BOM UTF-8 dengan penetral formula injection.

Filter: tahun, tema, indikator, sumber, dan pencarian bebas. `/data-desa/{tema}` adalah
alamat tetap per tema; tema di luar registry menghasilkan 404.

Nilai yang disamarkan ditampilkan sebagai `<10` beserta keterangannya, bukan dihilangkan
diam-diam. Nilai yang tidak diketahui ditulis "tidak diketahui", bukan 0.

## Kartu statistik beranda

Section `statistics` memilih **satu dataset terbit** dan maksimal empat seri darinya.
Tahun, satuan, sumber, dan tanggal terbit dibaca dari snapshot dataset sehingga kartu tidak
pernah menyimpang dari halaman Data Desa. Menerbitkan atau menarik dataset membuang cache
halaman yang memasangnya secara terarah.

## Batasan yang diketahui

- Perbandingan antartahun dalam satu grafik belum ada; satu versi dataset mewakili satu periode.
- Baru tema `population` yang punya dataset seed (status draft).
- S3 masih `.doc` dan belum dapat diekstrak otomatis (LibreOffice tidak terpasang).
