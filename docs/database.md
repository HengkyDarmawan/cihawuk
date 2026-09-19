# Database

- Server: **MariaDB 10.4.27** (XAMPP), engine InnoDB, charset `utf8mb4_unicode_ci`.
- Skema dibuat oleh enam migration berurutan di `application/migrations/` (total **83 tabel**); `down()` diuji
  (migrate 0 → migrate) pada database test di setiap run PHPUnit.
- Semua `DATETIME` disimpan dalam **UTC** (sesi DB diset `time_zone='+00:00'`); tampilan dikonversi ke WIB.
- Kolom JSON disimpan sebagai `LONGTEXT` (nama berakhiran `_json`) dan divalidasi di aplikasi, karena tipe JSON
  MariaDB 10.4 hanyalah alias `LONGTEXT` dan CHECK constraint dihindari agar portabel ke MySQL 8.
- Nilai enum (status, tipe) disimpan `VARCHAR` dengan allowlist di `application/config/app.php` dan service.
- Akun aplikasi `cihawuk_app` hanya DML; perubahan skema memakai `cihawuk_migrate` lewat `tools migrate`.
- Kata sandi, token, dan kode akses tidak pernah disimpan sebagai plaintext (lihat `docs/security.md`).

## Relasi inti

```
users ─┬─< user_roles >── roles ─< role_permissions >── permissions
       ├─< user_unit_scopes >── organizational_units
       ├── resident_profiles ── administrative_areas
       ├─< user_sessions, account_tokens, user_mfa, mfa_recovery_codes
       └─< notifications

ticket_categories ── sla_policies ── business_calendars ─< business_holidays
        │
tickets ─┬── ticket_private_contacts (terenkripsi)      ┌─ ticket_sla_instances ─< ticket_sla_pauses
         ├── ticket_access_secrets (hash HMAC)          ├─ ticket_escalations
         ├─< anonymous_access_grants                    ├─ ticket_resolution_episodes
         ├─< ticket_messages ─< ticket_attachments ── private_files
         ├─< ticket_status_history (append-only)
         ├─< ticket_assignments, ticket_references, ticket_feedback, ticket_conflicts
         └── duplicate_of_ticket_id → tickets

source_documents ─< import_batches ─< source_observations ─── statistic_values >── statistic_indicators
                  └─< data_issues                                    └── administrative_areas
posts ─< post_revisions ; slug_redirects ; media_assets ─< gallery_items >── galleries
potentials ─< potential_media ; events ; public_documents ; navigation_items ; hero_slides
village_profiles ; official_positions ─< officials ; map_features
```

## Migration 001 — akses dan operasional (23 tabel)

| Tabel | Isi | Kunci/indeks penting |
|---|---|---|
| `users` | Akun (username, email opsional, `password_hash` argon2id, `account_status`, `auth_version`, `registration_channel`, `created_by`) | UNIQUE `public_id`, `username`, `email`; idx status |
| `roles`, `permissions`, `user_roles`, `role_permissions` | RBAC; `roles.is_staff` | UNIQUE code; PK gabungan |
| `organizational_units`, `user_unit_scopes` | Unit kerja dan lingkup (`member`, `monitor`, `all`) | UNIQUE (user, unit, scope_type) |
| `administrative_areas` | Wilayah (root `DESA-CIHAWUK`; dusun belum dibuat) | UNIQUE code |
| `resident_profiles` | Profil warga, `verification_status`; NIK tidak diwajibkan | FK user (1:1) |
| `account_tokens` | Aktivasi/reset: `selector` + `token_hash` HMAC, `expires_at`, `used_at` | UNIQUE selector |
| `user_mfa`, `mfa_recovery_codes` | Secret TOTP terenkripsi; recovery code di-hash, sekali pakai | FK user |
| `ci_sessions` | Driver sesi CI3 | idx timestamp |
| `user_sessions` | Sesi login aktif (fingerprint sesi, `auth_version`, area, label perangkat, `last_seen_at`, `expires_at`, `revoked_at`) | UNIQUE session_fingerprint |
| `remember_tokens` | Disiapkan; fitur remember-me mati (`FEATURE_REMEMBER_ME=false`) | — |
| `rate_limits` | Fixed window per kunci HMAC | PK key |
| `audit_logs` | Aktor, aksi, objek, request ID, metadata ter-redaksi | idx objek, aktor, waktu |
| `app_settings` | Pengaturan (nilai JSON) | UNIQUE key |
| `job_locks` | Kunci job berkala dengan kedaluwarsa | PK name |
| `idempotency_keys` | Kunci submit (hash aktor/sesi, aksi, hash kunci), hasil referensi | UNIQUE (actor_scope_hash, action, key_hash) |
| `private_files` | Metadata berkas privat (path relatif acak, MIME terdeteksi, ukuran, SHA-256, `scan_status`) | UNIQUE storage_key |
| `notifications` | Notifikasi dalam aplikasi (`recipient_user_id`, `read_at`) | idx penerima + read |
| `notification_outbox` | Email tertunda (status, attempts, `next_attempt_at`, `dedupe_key`) | UNIQUE dedupe_key |

## Migration 002 — layanan (21 tabel)

| Tabel | Isi | Catatan |
|---|---|---|
| `business_calendars`, `business_holidays` | Jadwal mingguan WIB (JSON), libur/override hari kerja | Kalender seed bertanda `is_example=1` |
| `sla_policies` | Target hari kerja: verifikasi 3, respons pertama 5, jendela konfirmasi 10; `pause_rules_json`; `auto_close_enabled=0` | Usulan, belum SOP resmi |
| `ticket_categories` | 12 kategori, `report_type` (NULL = semua), unit default, `is_sensitive`, `location_required` | Kategori sensitif → tiket `restricted` |
| `tickets` | Lihat kolom di bawah | UNIQUE `public_code`; indeks status, pelapor, assignee, unit, kategori |
| `ticket_private_contacts` | Nama/email/telepon kontak **terenkripsi** (sodium), hanya untuk tiket non-anonim | FK tiket 1:1 |
| `ticket_access_secrets` | Hash HMAC kode akses + versi | Plaintext tidak disimpan |
| `anonymous_access_grants` | Grant pelacakan per sesi (hash session id, tiket, kedaluwarsa) | Dibersihkan job |
| `ticket_assignments` | Riwayat penugasan unit/petugas (aktif/berakhir) | — |
| `ticket_messages` | Pesan publik/internal (`visibility`), penulis user atau pelapor anonim | — |
| `ticket_status_history` | Append-only: dari/ke status, aksi, aktor, alasan, episode | Tidak pernah di-update |
| `ticket_attachments` | Relasi pesan/tiket ↔ `private_files`, visibilitas | UNIQUE private_file_id |
| `ticket_references` | Duplikat/rujukan/terkait | — |
| `ticket_feedback` | Tanggapan pelapor per episode | UNIQUE (ticket, handling_episode) |
| `ticket_resolution_episodes` | Episode penyelesaian (reopen menambah episode) | UNIQUE (ticket, episode) |
| `ticket_sla_instances`, `ticket_sla_pauses` | Snapshot kebijakan + tenggat UTC per episode; interval jeda | UNIQUE (ticket, episode) |
| `ticket_escalations` | Eskalasi SLA | UNIQUE `dedupe_key` |
| `ticket_conflicts` | Pengguna yang terlapor/berkonflik dengan tiket | UNIQUE (ticket, user) |
| `export_jobs` | Permintaan ekspor (filter JSON, status, berkas privat) | — |

Kolom `tickets` yang perlu diketahui:

| Kolom | Aturan |
|---|---|
| `public_code` | `CHW-YYYY-XXXXXXXX` acak (Crockford base32), bukan ID berurutan |
| `reporter_user_id` | NULL untuk anonim, termasuk bila pengguna login memilih tidak mengaitkan akun |
| `created_by_user_id` | Petugas loket yang mencatat; terpisah dari pelapor |
| `intake_channel` | `public_anonymous`, `resident_dashboard`, `front_desk` |
| `identity_mode` | `anonymous`, `identified`, `masked` |
| `confidentiality` | `private` (default) atau `restricted` |
| `status`, `return_status` | Lihat `docs/workflows.md`; `return_status` menyimpan tahap asal saat `needs_information` |
| `version` | Optimistic locking; setiap perubahan `version = version + 1` dengan `WHERE version = ?` |
| `current_episode` | Naik saat reopen |
| `latitude`/`longitude` | Hanya bila pelapor membagikan lokasi |

## Migration 003 — konten dan data sumber (23 tabel)

| Tabel | Isi | Catatan |
|---|---|---|
| `media_assets` | Media publik: path, MIME, dimensi, alt, `rights_status`, `publication_status` | Hanya `rights_status` yang jelas yang boleh terbit |
| `content_categories` | Kategori berita/potensi/dokumen | — |
| `source_documents` | Register S1–S4: kode, nama berkas, tahun, SHA-256, status impor | UNIQUE source_code, checksum |
| `posts`, `post_revisions`, `slug_redirects` | Berita/halaman; revisi; redirect 301 slug lama | UNIQUE (type, slug); FULLTEXT (title, excerpt) |
| `map_features` | Titik/area peta (belum ada data terverifikasi) | — |
| `potentials`, `potential_media` | Potensi desa + media | Status publikasi + verifikasi |
| `events` | Agenda (waktu UTC) | — |
| `galleries`, `gallery_items` | Galeri | — |
| `public_documents` | Dokumen publik (PDF di media publik) | — |
| `navigation_items` | Menu; URL internal atau https allowlist | — |
| `hero_slides` | Hero beranda (poster, judul) | — |
| `village_profiles` | Profil desa per versi (visi, misi, sejarah, kontak) | Seed: draft |
| `official_positions`, `officials` | Struktur jabatan dan pejabat, masa jabatan, sumber | Seed: draft historis |
| `import_batches` | Batch impor per sumber (checksum, parser, jumlah) | Idempotent |
| `source_observations` | Nilai mentah + nilai ternormalisasi + tipe + locator | UNIQUE (source, locator, field) → impor ulang tidak menggandakan |
| `data_issues` | Konflik/keanehan data sumber + keputusan | UNIQUE issue_code |
| `statistic_indicators` | Definisi indikator (kode, satuan, tipe) | 11 indikator seed |
| `statistic_values` | Nilai kanonis per indikator/tahun/wilayah, rujukan observasi | UNIQUE (indicator, year, area); publik hanya `published` + `verified` |

## Migration 004 — modul, audit, dan media (5 tabel + kolom tambahan)

| Tabel | Isi | Catatan |
|---|---|---|
| `feature_modules` | Status modul (`disabled`/`internal_only`/`public_readonly`/`active`/`maintenance`), dependency JSON, prefix route publik/admin | UNIQUE code; prefix boleh berisi beberapa nilai dipisah koma |
| `feature_module_histories` | Riwayat perubahan status: dari, ke, alasan, aktor, waktu efektif | Append-only |
| `admin_activity_events` | Peristiwa operasional untuk dashboard maintenance (severity, modul, pesan, konteks) | Terpisah dari `audit_logs` |
| `media_derivatives` | Turunan publik tiap media (`public`, `webp`, `thumb`) | UNIQUE (media, variant) dan UNIQUE storage_key |
| `media_usages` | Konten yang memakai sebuah media | UNIQUE (media, object_type, object_id, field_name) |

Kolom tambahan pada tabel lama:

| Tabel | Kolom | Alasan |
|---|---|---|
| `audit_logs` | `module_code`, `ip_hash`, `user_agent_digest` | Filter per modul; jejak jaringan disimpan sebagai HMAC, bukan IP mentah |
| `roles` | `preset_version` | Menandai versi preset permission yang sudah diterapkan seeder |
| `media_assets` | `private_file_id`, `caption`, `source_year`, `people_shown`, `verification_status`; `storage_key` menjadi nullable | Berkas asli privat, metadata media library, dan status arsip |

## Migration 006 — menu navigasi (2 tabel)

| Tabel | Isi |
|---|---|
| `cms_menus` | Satu baris per lokasi menu (`header`, `mobile`, `footer_primary`, `footer_secondary`, `quick_link`), berikut status dan waktu terbit terakhir |
| `cms_menu_items` | Item menu draft: label, jenis tautan (route/page/external), induk, urutan, aktif/tidak |

Snapshot menu dan snapshot identitas/tema situs memakai tabel yang sudah ada,
`cms_publication_snapshots`, dengan `target_type` `menu` dan `site` (`target_id = 0` untuk situs).
`down()` migration ini ikut menghapus kedua jenis snapshot itu dan tidak menyentuh snapshot halaman.

## Migration 005 — halaman CMS (9 tabel)

| Tabel | Isi | Catatan |
|---|---|---|
| `cms_pages` | Halaman publik: `page_key` stabil, status, versi draft/terbit | UNIQUE page_key dan public_id |
| `cms_page_versions` | Versi halaman: judul, slug, ringkasan, template, SEO | UNIQUE (page, version_no); versi lama tidak pernah diubah |
| `cms_sections` | Instance section pada halaman: jenis, urutan, aktif/nonaktif, jadwal tampil | UNIQUE public_id |
| `cms_section_versions` | Versi section: judul, subjudul, varian layout, `config_json` | UNIQUE (section, version_no) |
| `cms_publication_snapshots` | Snapshot immutable yang dibaca frontend | UNIQUE (target, revision_no); `superseded_at` menandai yang tidak aktif |
| `cms_redirects` | Pengalihan 301 saat slug halaman berubah | UNIQUE old_path |
| `content_review_requests` | Pengajuan review per objek dan versi | Status open/approved/changes_requested |
| `content_review_comments` | Komentar review yang dapat ditindaklanjuti | FK ke request |
| `scheduled_publications` | Publikasi terjadwal (UTC) + idempotency key | UNIQUE idempotency_key; diklaim atomik oleh job |

## Migration 007 — dataset statistik (3 tabel + 2 kolom)

`datasets`, `dataset_versions`, `dataset_series`. Kolom baru `statistic_values.dataset_version_id`
dan `statistic_values.suppressed`. Snapshot memakai `cms_publication_snapshots` dengan
`target_type = 'dataset'`. Rincian aturannya ada di [data-desa.md](data-desa.md).

## Migration 008 — profil desa (3 tabel)

`profile_blocks`, `profile_block_versions`, `leadership_terms`. Snapshot memakai
`target_type = 'profile'` dengan `target_id = 0` sehingga seluruh profil terbit sebagai satu
kesatuan. Lihat [profil-dan-organisasi.md](profil-dan-organisasi.md).

## Migration 009 — lokasi dan fasilitas (3 tabel + 4 kolom)

`places`, `facilities`, `facility_services`. Kolom baru pada `potentials`: `place_id`,
`access_status`, `manager_name`, `safety_note`.

## Migration 010 — struktur organisasi (5 tabel)

`org_periods`, `org_units`, `org_positions`, `people`, `org_assignments`. `people` sengaja
terpisah dari `users`. Nomor SK disimpan terenkripsi pada
`org_assignments.decree_number_ciphertext`. Snapshot memakai `target_type = 'organization'`
dengan `target_id` = id periode.

## Migration 011 — transparansi anggaran (7 tabel)

`budget_years`, `budget_revisions`, `budget_categories`, `budget_lines`, `budget_projects`,
`budget_project_progress`, `budget_documents`. Anggaran murni, perubahan, dan realisasi adalah
baris berbeda pada `budget_revisions` dengan kunci unik per tahun. Snapshot memakai
`target_type = 'budget'`. Lihat [keuangan.md](keuangan.md).

## Migration 012 — direktori UMKM (1 tabel)

`businesses`, dengan `owner_consent`, `consent_recorded_at`, dan `consent_note` sebagai syarat
publikasi.

## Migration 013 — aset, QR, dan audit (18 tabel)

`asset_categories`, `asset_locations`, `asset_registers`, `asset_units`, `asset_unit_media`,
`asset_documents`, `asset_qr_tokens`, `asset_label_batches`, `asset_label_batch_items`,
`asset_status_events`, `asset_movements`, `asset_loans`, `asset_maintenance`,
`asset_audit_sessions`, `asset_audit_targets`, `asset_audit_findings`, `asset_audit_addenda`,
`asset_import_rows`.

Kolom terenkripsi: `asset_units.serial_number_ciphertext`,
`asset_documents.document_number_ciphertext`, `asset_loans.borrower_name_ciphertext`.
`asset_qr_tokens` hanya menyimpan digest token, tidak pernah token aslinya.

## Migration 014 — gudang persediaan (10 tabel)

`warehouse_locations`, `inventory_items`, `inventory_unit_conversions`,
`inventory_transactions`, `inventory_transaction_lines`, `inventory_ledger`,
`inventory_requests`, `inventory_request_lines`, `inventory_stocktakes`,
`inventory_stocktake_lines`.

`inventory_ledger` append-only dan menjadi sumber saldo kanonis; tidak ada kolom saldo yang
dapat disunting. Lihat [aset-qr-dan-gudang.md](aset-qr-dan-gudang.md).

## Migration 015 — pemeriksa tautan (1 tabel)

`link_check_results` menyimpan hasil pemeriksaan terakhir; setiap kali dijalankan isinya
diganti, bukan ditumpuk.

## Ringkasan skema

Total **134 tabel** pada versi skema **15**. Seluruh migration reversibel: bootstrap
pengujian menjalankan `tools migrate 0` → `tools migrate` → `tools seed` pada database
pengujian setiap kali suite dijalankan.

## Aturan data

- Hapus data layanan tidak dilakukan lewat UI; FK layanan memakai `ON DELETE RESTRICT`.
- Riwayat status, revisi konten, observasi mentah, dan audit bersifat append-only.
- Observasi dari tahun berbeda (2021 vs 2023) tidak digabung; statistik publik harus direview dan diterbitkan.
- `-`, sel kosong, dan pilihan "Ada/Tidak" tidak dianggap nol; lihat `docs/source-inventory.md`.
- Data demo hanya dibuat oleh `tools seed_demo` (development/testing), memakai email `.test`, dan dapat dihapus
  dengan `tools purge_demo`.

## Migration 016 — jejak data demonstrasi (19 September 2026)

| Tabel | Isi |
|---|---|
| `demo_records` | `entity_type`, `entity_id`, `public_id`, `label`, `created_at`; unik per (jenis, id) |

Data demonstrasi untuk modul baru sengaja tidak memakai awalan `[Demo]` pada namanya supaya
tampil wajar saat diperagakan. Jejak inilah yang membuat `tools purge_demo` tetap dapat
menghapusnya dengan pasti, tanpa menyentuh baris yang bukan buatan seeder demo.

Skema menjadi **135 tabel, versi 16**.

Beberapa kunci asing menuntut urutan penghapusan tertentu, dan itu ditangani eksplisit di
`DemoSeeder::purge_tracked()`:

| Kunci asing | Aturan | Penanganan |
|---|---|---|
| `budget_categories.parent_id` → dirinya sendiri | RESTRICT | `parent_id` dikosongkan lebih dulu |
| `asset_units.register_id` → `asset_registers` | RESTRICT | unit dihapus sebelum register |
| `asset_movements.to_location_id` → `asset_locations` | RESTRICT | mutasi ikut terhapus bersama unit |
| `inventory_ledger.item_id` / `.location_id` | RESTRICT | transaksi dicari lewat barang demo, lalu dihapus |
| `export_jobs.requested_by` → `users` | RESTRICT | dihapus sebelum akun demo |
