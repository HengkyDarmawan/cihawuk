# Role dan Permission

Sumber: `application/config/rbac.php` (di-seed ke tabel `roles`, `permissions`, `role_permissions`). Daftar ini
menggabungkan permission master (prompt-master §6) dengan permission granular modul backend CMS (§4.2):
**62 permission dan 19 role** per 19 September 2026. Role adalah preset teknis, **bukan** jabatan formal desa.

Sebagian permission baru menyiapkan modul yang belum dibangun (aset, gudang, struktur organisasi, keuangan, CMS
bersection). Permission tersebut sudah dapat diberikan, tetapi modulnya masih berstatus `disabled` sehingga
route-nya tetap tertutup — lihat `docs/architecture.md` bagian feature module.

## Versi preset

`rbac.php` memiliki `preset_version` (saat ini `3`) dan tabel `roles` memiliki kolom `preset_version`.
Saat versi naik, seeder **menambahkan** permission preset yang belum terpasang pada role sistem lalu menyimpan versi
barunya. Seeder tidak pernah mencabut permission; permission yang ditambahkan pengelola tetap ada. Konsekuensinya:
bila pengelola mencabut permission preset dan versi preset kemudian naik, permission itu dapat terpasang kembali —
periksa daftar role setelah pembaruan aplikasi.

## Pemetaan kode yang setara

Kedua dokumen spesifikasi kadang menyebut maksud yang sama dengan nama berbeda. Kode yang dipakai aplikasi:

| Disebut pada spesifikasi | Kode yang dipakai |
|---|---|
| `organization.manage` (modul backend) | `organization.edit` |
| `asset.manage` (modul backend) | `assets.view`, `assets.create`, `assets.edit`, `assets.change_status` |
| `asset.audit` (modul backend) | `asset_audits.create`, `asset_audits.perform`, `asset_audits.verify` |
| `warehouse.transact` (modul backend) | `warehouse.receive`, `warehouse.issue`, `warehouse.transfer` |
| `cms.media.upload` | dipakai bersama `content.edit` yang sudah ada |

`assets.view_financial` dan `warehouse.adjust` sengaja **tidak** diberikan ke role preset mana pun: keduanya harus
diberikan per orang dengan alasan, sesuai pemisahan tugas pada master §6.

Seorang pengguna dapat memiliki beberapa role; permission efektif adalah gabungannya. Pemberian role dilakukan
Super Admin di **Admin → Pengguna**; aksi pemberian/pencabutan role, pemulihan akun manual, dan reset MFA
memerlukan reautentikasi.

## Permission

| Kode | Arti |
|---|---|
| `users.manage` | Mengelola akun pengguna (status, data akun) |
| `users.create_resident` | Mendaftarkan akun warga |
| `users.assign_roles` | Memberi/mencabut role |
| `residents.verify` | Mereview dan mengaktifkan akun/profil warga |
| `roles.manage` | Mengelola role dan permission |
| `settings.manage` | Pengaturan aplikasi, kalender SLA, status job |
| `tickets.create_on_behalf` | Mencatat laporan dari loket |
| `tickets.verify` | Memverifikasi laporan masuk |
| `tickets.assign` | Disposisi dan pemindahan penugasan |
| `tickets.work_assigned` | Menangani laporan yang ditugaskan kepadanya |
| `tickets.monitor_scope` | Memantau laporan dalam lingkup unit |
| `tickets.close` | Menutup laporan berdasarkan kebijakan |
| `tickets.reopen` | Membuka kembali laporan selesai |
| `tickets.view_identity` | Melihat identitas/kontak pelapor (diaudit) |
| `tickets.handle_confidential` | Mengakses laporan rahasia |
| `tickets.export` | Mengekspor rekap laporan |
| `content.edit` | Menyusun draft konten |
| `content.publish` | Menerbitkan/mengarsipkan konten |
| `statistics.review` | Mereview data sumber dan statistik |
| `audit.view` | Melihat log audit |
| `settings.feature.manage` | Mengubah status modul aplikasi (feature module) |
| `settings.security.manage` | Mengubah pengaturan keamanan aplikasi |
| `cms.page.view/create/edit/submit_review` | Menyusun halaman dan section CMS |
| `cms.page.approve/publish/unpublish/rollback` | Menyetujui, menerbitkan, menarik, dan mengembalikan versi publik |
| `cms.menu.manage` | Mengelola menu dan navigasi publik |
| `cms.site.manage` | Mengelola identitas situs, token tema, dan cache publik |
| `cms.media.upload` / `cms.media.approve_public` | Mengunggah media / menyetujui media tampil publik |
| `data.import` / `data.review` / `data.publish` | Impor staging, review observasi, penerbitan dataset |
| `finance.manage` / `finance.verify` / `finance.publish` | Draft anggaran, verifikasi rekonsiliasi, penerbitan snapshot |
| `assets.view/create/edit/change_status/move/maintain/export` | Pengelolaan register dan unit aset |
| `assets.view_financial` / `assets.view_documents` / `assets.print_labels` | Nilai aset, dokumen kepemilikan, label QR |
| `asset_audits.create/perform/verify` | Pembuatan, pelaksanaan, dan verifikasi audit fisik |
| `warehouse.view/receive/issue/transfer/adjust/stocktake/export` | Transaksi dan pelaporan persediaan |
| `organization.edit` / `organization.publish` | Pengelolaan dan penerbitan struktur organisasi |

## Role preset

| Role | Permission |
|---|---|
| Super Admin (`super_admin`) | users.manage, users.create_resident, users.assign_roles, residents.verify, roles.manage, settings.manage, settings.feature.manage, settings.security.manage, audit.view — **tidak** otomatis membaca laporan |
| Admin Pelayanan (`service_admin`) | tickets.create_on_behalf, tickets.verify, tickets.assign, tickets.work_assigned, tickets.close, tickets.view_identity, tickets.export, residents.verify, users.create_resident |
| Koordinator Pelayanan (`service_coordinator`) | tickets.monitor_scope, tickets.assign, tickets.close, tickets.reopen, tickets.work_assigned, tickets.export |
| Petugas (`officer`) | tickets.work_assigned |
| Petugas Loket (`front_desk`) | tickets.create_on_behalf, tickets.work_assigned |
| Penangan Laporan Rahasia (`confidential_handler`) | tickets.handle_confidential, tickets.view_identity — dipasangkan dengan role pelayanan |
| Kepala Desa (`village_head`) | tickets.monitor_scope |
| Editor Konten (`content_editor`) | content.edit, cms.page.view/create/edit/submit_review, cms.media.upload |
| Penerbit Konten (`content_publisher`) | content.edit, content.publish, statistics.review, cms.page.view/approve/publish/unpublish/rollback, cms.menu.manage, cms.media.approve_public, data.publish |
| Warga (`resident`) | — (akses hanya ke data miliknya di `/warga`) |
| Admin Website (`website_admin`) | content.edit, cms.page.view/create/edit/submit_review, cms.menu.manage, cms.site.manage, cms.media.upload |
| Verifikator Data (`data_verifier`) | data.import, data.review, statistics.review |
| Pengelola Keuangan (`finance_manager`) | finance.manage |
| Verifikator Keuangan (`finance_verifier`) | finance.verify |
| Pengurus Aset (`asset_manager`) | assets.view/create/edit/print_labels/move/maintain/change_status/export, asset_audits.create |
| Auditor Aset (`asset_auditor`) | assets.view, asset_audits.perform |
| Verifikator Aset (`asset_verifier`) | assets.view, assets.view_documents, asset_audits.verify |
| Petugas Gudang (`warehouse_officer`) | warehouse.view/receive/issue/transfer/stocktake/export (tanpa `warehouse.adjust`) |
| Auditor Sistem (`system_auditor`) | audit.view |

Editor konten memegang `cms.page.*` tingkat penyusunan saja; penerbitan tetap terpisah pada role penerbit.

Role staf (`is_staff=1`) masuk ke `/admin`; akun dengan hanya role `resident` masuk ke `/warga`. Menu sidebar
dashboard ditampilkan berdasarkan permission, tetapi **setiap endpoint memeriksa ulang di server**
(`Admin_Controller::require_permission`, `AuthorizationService`).

## Permission modul lanjutan (preset v7)

| Permission | Dipegang preset | Keterangan |
|---|---|---|
| `data.import`, `data.review` | Verifikator Data | Impor staging dan verifikasi nilai statistik |
| `data.publish` | Penerbit Konten | Menerbitkan dan menarik dataset |
| `facilities.edit` | Admin Website, Editor Konten | Menyusun lokasi dan fasilitas |
| `facilities.publish` | Penerbit Konten | Memverifikasi, menerbitkan, mengarsipkan fasilitas |
| `umkm.edit` | Admin Website | Menyusun profil UMKM |
| `umkm.publish` | Penerbit Konten | Menerbitkan, menarik, mencabut persetujuan UMKM |
| `organization.edit` | Admin Website | Periode, unit, jabatan, orang, penugasan |
| `organization.publish` | Penerbit Konten | Menerbitkan snapshot struktur organisasi |
| `finance.manage` | Pengelola Keuangan | Mengisi angka anggaran |
| `finance.verify` | Verifikator Keuangan | Menandai terverifikasi |
| `finance.publish` | Penerbit Konten | Menyetujui, menerbitkan, mengunci periode |
| `assets.*` | Pengurus Aset, Verifikator Aset | Register, unit, status, mutasi, label |
| `assets.view_financial` | Verifikator Aset | Nilai perolehan dan biaya; tidak dipegang Pengurus Aset |
| `asset_audits.*` | Pengurus, Auditor, Verifikator Aset | Sesi, pelaksanaan, verifikasi audit |
| `warehouse.*` | Petugas Gudang | Item, penerimaan, pengeluaran, transfer, penyesuaian, opname |

Pola yang berlaku di seluruh modul: **menyusun** dan **menerbitkan** selalu dipegang role
berbeda. Yang diuji lewat HTTP untuk setiap modul adalah arah "penyusun tidak dapat
menerbitkan"; peran penerbit pada preset ini memang juga memegang izin menyunting konten.

Permission `finance.publish`, `organization.publish`, `facilities.publish`, `umkm.publish`,
dan `data.publish` sengaja dikumpulkan pada satu role penerbit supaya keputusan publikasi
punya satu pintu. Desa dapat memindahkannya ke role lain lewat pengelolaan role.

## Lingkup objek tiket

`AuthorizationService::ticket_abilities()` menentukan hak per tiket; `apply_ticket_scope()` menerapkan aturan yang
sama sebagai klausa SQL untuk daftar, badge, statistik dashboard, dan ekspor.

| Kondisi | Hasil |
|---|---|
| Pengguna tercatat di `ticket_conflicts` untuk tiket itu | Tidak ada akses sama sekali, tidak bisa ditugaskan |
| Pengguna adalah pelapor tiket | Tidak dapat menangani tiketnya di `/admin` |
| Tiket `restricted` tanpa `tickets.handle_confidential` | Tidak terlihat |
| `tickets.verify` | Melihat semua tiket (selain batasan di atas) — antrian verifikasi |
| `tickets.monitor_scope` | Tiket yang `assigned_unit_id` termasuk lingkup unit (`user_unit_scopes`: member/monitor/all) |
| `tickets.work_assigned` | Hanya tiket dengan `assigned_user_id` = dirinya |
| `tickets.assign` | Hanya bila juga dapat verifikasi atau memantau tiket tersebut |
| `tickets.close` / `tickets.reopen` / `tickets.view_identity` | Hanya pada tiket yang dapat dilihat |

Syarat penanggung jawab (`can_be_assignee`): akun aktif, punya `tickets.work_assigned`, punya
`tickets.handle_confidential` bila tiket rahasia, bukan pelapor, dan tidak berkonflik.

## Identitas dan data rahasia

- Kontak pelapor disimpan terenkripsi dan tidak ditampilkan secara default. Pengguna dengan
  `tickets.view_identity` menekan **Tampilkan identitas** dan wajib mengisi alasan (minimal 10 karakter); setiap
  pembukaan dicatat `audit_logs` (`ticket.identity_revealed`). Identitas mode `masked` juga membutuhkan
  `tickets.handle_confidential`. Pembukaan identitas belum meminta reautentikasi (lihat keterbatasan di
  `docs/security.md`).
- Kategori sensitif (Perilaku aparat, Perlindungan) otomatis `restricted`.
- Tiket anonim tidak memiliki identitas yang dapat dibuka.
- Petugas yang dilaporkan dapat ditandai konflik oleh pengelola (**Tandai konflik**) sehingga kehilangan akses.

## Ekspor

- Hanya `tickets.export`. Filter ekspor selalu dipersempit dengan `apply_ticket_scope` milik peminta.
- Permission diperiksa saat permintaan, saat job berjalan, dan saat unduh; bila dicabut, job gagal dan unduhan
  ditolak.
- Ekspor memakai kolom daftar (nomor, judul, kategori, status, unit, tanggal) dan tidak memuat kontak pelapor.

## Area lain

| Area | Syarat |
|---|---|
| Admin → Pengguna | users.manage / users.create_resident / residents.verify (aksi dicek per tombol); assign role butuh users.assign_roles |
| Super Admin terakhir | Tidak dapat dinonaktifkan atau dicabut role-nya |
| Konten, media, galeri | content.edit (draft) — terbit/arsip content.publish |
| Menu dan Navigasi | Susun item: cms.menu.manage; terbitkan/rollback menu: cms.page.publish / cms.page.rollback |
| Identitas dan Tema | Susun draft: cms.site.manage; terbitkan/rollback: cms.page.publish / cms.page.rollback |
| Pengaturan Beranda & Halaman Publik | Lihat: cms.page.view; susun: cms.page.edit/create; ajukan: cms.page.submit_review; setujui: cms.page.approve; terbitkan/jadwalkan: cms.page.publish; tarik: cms.page.unpublish; rollback: cms.page.rollback |
| Data & Statistik | Lihat: statistics.review atau content.edit; impor, review observasi, isu, simpan nilai: statistics.review; terbitkan nilai: content.publish |
| Pengaturan, kalender, status job | settings.manage |
| Pengaturan → Modul aplikasi | settings.feature.manage (terpisah dari settings.manage) |
| Audit | audit.view |
| Pratinjau draft di situs publik | Staf dengan content.edit, diaktifkan dari dashboard; halaman menampilkan badge "Pratinjau" dan no-store |
