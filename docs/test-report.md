# Laporan Pengujian

## Ringkasan terakhir

| Run | Tanggal | Hasil |
|---|---|---|
| Tahap 3 spesifikasi v1.2 (halaman publik, menu, identitas, tema) | 19 September 2026 | `OK (102 tests, 626 assertions)` |
| Tahap 2 spesifikasi v1.2 (CMS halaman & beranda) | 19 September 2026 | `OK (86 tests, 524 assertions)` |
| Tahap 1 spesifikasi v1.2 | 18 September 2026 | `OK (73 tests, 448 assertions)` |
| Baseline sebelumnya | 16 September 2026 | `OK (63 tests, 375 assertions)` |

Bagian **Tahap 1 v1.2** di bawah mencatat tes yang ditambahkan pada sesi 18 September 2026. Tabel ID §27.1
selanjutnya adalah hasil baseline 16 September 2026 yang tetap berlaku (seluruh tesnya ikut dijalankan ulang dan
lulus pada run 18 September).

## Tahap 3 v1.2 — halaman publik, menu, identitas dan tema (19 September 2026)

| ID | Skenario | Status | Bukti |
|---|---|---|---|
| NAV-01 | Halaman CMS 404 selama belum terbit, dapat dibuka setelah terbit, 404 lagi setelah ditarik | Pass | `CmsNavigationHttpTest::test_cms_page_is_404_until_published_then_reachable`; manual: `/cihawuk/informasi-layanan` 404 → 200 |
| NAV-02 | Path publik hanya milik halaman terbit; beranda tetap di `/` | Pass | `CmsNavigationTest::test_published_page_has_public_path_and_draft_does_not` |
| NAV-03 | Ganti slug meninggalkan redirect 301 dari alamat lama | Pass | `CmsNavigationTest::test_slug_change_keeps_old_path_as_redirect`; `CmsNavigationHttpTest::test_old_slug_redirects_to_new_address` |
| NAV-04 | Slug yang dipakai route sistem ditolak (daftar diturunkan dari `routes.php`) | Pass | `CmsNavigationTest::test_reserved_slug_from_route_is_rejected` |
| NAV-05 | Sitemap dan pencarian memuat halaman terbit, dan berhenti memuatnya saat `search_indexable` dimatikan | Pass | `CmsNavigationHttpTest::test_published_page_appears_in_sitemap_and_search`; manual: `sitemap.xml` dan `/cari` memuat halaman uji |
| MENU-01 | Aturan item menu: maksimal dua tingkat, tanpa `javascript:`/`data:`, tanpa path dashboard/berkas privat | Pass | `CmsNavigationTest::test_menu_item_rules_are_enforced` (5 kasus ditolak 422) |
| MENU-02 | Halaman draft tidak dapat dipasang di menu publik; setelah terbit boleh | Pass | `CmsNavigationTest::test_draft_page_cannot_be_used_as_menu_item` |
| MENU-03 | Draft menu tidak tampil sampai diterbitkan; rollback mengembalikan susunan lama | Pass | `CmsNavigationTest::test_menu_publish_snapshot_and_rollback`; `CmsNavigationHttpTest::test_menu_changes_are_invisible_until_published` |
| MENU-04 | Item nonaktif dan item ke halaman yang sudah ditarik dilewati saat publikasi | Pass | `CmsNavigationTest::test_disabled_item_and_unpublished_page_are_skipped_on_publish` |
| SITE-01 | Validasi identitas/tema: hex, kontras minimum, font terpasang, preset radius, email, tautan sosial | Pass | `CmsNavigationTest::test_site_identity_and_theme_validation` (8 kasus ditolak 422) |
| SITE-02 | Draft identitas/tema tidak tampil sampai diterbitkan; rollback mengembalikan revisi lama | Pass | `CmsNavigationTest::test_site_draft_is_not_public_until_published`; `CmsNavigationHttpTest::test_site_identity_and_theme_apply_after_publication` (token tema muncul sebagai CSS valid, bukan entitas HTML) |
| SITE-03 | Pemisahan izin: editor konten tidak dapat membuka menu/identitas; admin website tidak dapat menerbitkan | Pass | `CmsNavigationHttpTest::test_editor_cannot_publish_menu_or_site_settings` (403 pada tiga titik) |
| CACHE-01 | Invalidasi cache terarah, bukan flush semua | Pass | `CmsNavigationTest::test_public_cache_invalidation_is_targeted` |
| MIG-03 | Migration 006 reversibel dan seed menu/identitas idempotent | Pass (manual) | `tools migrate` → v6, `tools migrate 5`, `tools migrate` → v6; `tools seed` kedua kali 0 baris baru |

Verifikasi manual tambahan: membuat halaman lewat dashboard sebagai Admin Website, mengajukan review,
lalu menyetujui dan menerbitkannya sebagai Penerbit Konten; halaman langsung tampil di `/cihawuk/informasi-layanan`
tanpa membersihkan cache secara manual. Tidak ada scroll horizontal pada 375 px untuk halaman publik baru,
pengelola menu, dan pengelola identitas.

Belum diuji pada tahap ini: alur review untuk menu dan identitas (memang belum ada), pemeriksa tautan rusak,
serta perilaku cache di bawah beban bersamaan.

## Tahap 2 v1.2 — CMS halaman dan beranda (19 September 2026)

| ID | Skenario | Status | Bukti |
|---|---|---|---|
| CMS-02 | Draft tidak pernah tampil publik | Pass | `CmsPublicationTest::test_draft_is_not_public_until_published`; `CmsHttpTest::test_editor_edits_draft_without_changing_public_page` (section dinonaktifkan, beranda publik tetap utuh) |
| CMS-03 | Publikasi menghasilkan snapshot + audit | Pass | `test_draft_is_not_public_until_published` (revisi 1, `cms.published` pada audit); manual: revisi baru tampil di riwayat dashboard |
| CMS-04 | Editor tidak dapat menerbitkan; penerbit tidak dapat mengubah draft | Pass | `CmsHttpTest::test_editor_edits_draft_without_changing_public_page` (403), `test_publisher_publishes_and_cannot_edit_draft` (403 saat penerbit mengubah section) |
| CMS-05 | Unpublish menghilangkan dari publik, rollback membuat revisi baru | Pass | `CmsPublicationTest::test_unpublish_and_rollback_keep_history` (3 revisi tersimpan); `CmsHttpTest::test_publisher_publishes_and_cannot_edit_draft` |
| CMS-06 | Publikasi terjadwal berjalan tepat sekali | Pass | `CmsPublicationTest::test_scheduled_publication_runs_once` (job kedua 0 terbit); manual `tools run_jobs scheduled_publications` dua kali |
| CMS-07 | Section nonaktif dan section modul mati tidak ikut terbit | Pass | `CmsPublicationTest::test_disabled_section_and_disabled_module_are_not_published`; `CmsHttpTest::test_section_type_from_disabled_module_is_rejected` (409) |
| CMS-08 | Reorder konsisten dan transaksional | Pass | `CmsPublicationTest::test_section_reorder_is_transactional_and_validated` (urutan tidak lengkap ditolak 422, urutan lama tetap) |
| CMS-09 | Validasi registry menolak isi berbahaya/belum terbit | Pass | `test_registry_rules_reject_unsafe_or_unpublished_references` (`javascript:`, indikator belum terbit, hero video tanpa poster, singleton kedua) |
| CMS-10 | Pratinjau perlu login, token terikat pengguna dan kedaluwarsa | Pass | `CmsPublicationTest::test_preview_token_is_bound_to_user_and_expires`; `CmsHttpTest::test_preview_requires_login_and_valid_token` (anonim → login, token rusak → 410, header no-store + noindex) |
| CMS-11 | Beranda dirender dari snapshot | Pass | `CmsHttpTest::test_homepage_is_rendered_from_published_snapshot`; manual: 9 section tampil, tanpa scroll horizontal pada 390 dan 1440 px |
| MIG-02 | Migration 005 reversibel dan seed idempotent | Pass (manual) | `tools migrate` → v5, `tools migrate 4`, `tools migrate` → v5; `tools seed` kedua kali 0 baris baru |

Belum diuji pada tahap ini: halaman CMS selain beranda belum punya route publik, sitemap/pencarian belum memuat
halaman CMS, dan perilaku dua editor menyunting section yang sama bersamaan (optimistic locking antar editor)
belum dibuat.

## Tahap 1 v1.2 — modul, audit, dan media (18 September 2026)

| ID | Skenario | Status | Bukti |
|---|---|---|---|
| MOD-01 | Status awal modul jujur terhadap kondisi kode | Pass | `FeatureModuleServiceTest::test_seed_states_are_honest_about_unbuilt_modules`: aset, QR publik, gudang, keuangan, fasilitas, surat = `disabled`; modul tak dikenal ditolak |
| MOD-02 | Ubah status wajib beralasan, tercatat histori dan audit | Pass | `test_state_change_requires_reason_and_records_history` (alasan <10 karakter → 422; histori, `audit_logs.module_code`, `admin_activity_events` terisi) |
| MOD-03 | Dependency modul divalidasi dua arah | Pass | `test_dependency_is_enforced_both_directions` (QR sebelum aset → 409; mematikan aset saat QR aktif → 409) |
| MOD-04 | Perilaku publik/backend per status | Pass | `test_public_and_backend_availability_per_state` (`internal_only`, `public_readonly`, `maintenance`) |
| MOD-05 | Pencocokan route memakai prefix per segmen | Pass | `test_route_matching_uses_longest_prefix` (`laporanku` dan `masuk` tidak cocok) |
| MOD-06 | URL langsung modul nonaktif tertutup | Pass | `MediaHttpTest::test_disabled_module_blocks_public_and_admin_routes` (`/berita` dan `/admin/konten/berita` → 404). Manual: `maintenance` → 503, kembali `active` → 200 |
| MEDIA-01 | Berkas asli privat, derivative publik | Pass | `MediaHttpTest::test_upload_stores_private_original_and_public_derivatives` (3 derivative; original 403/404 dari web). Manual di dashboard: derivative 200, original 404 |
| MEDIA-02 | Teks alternatif wajib sebelum terbit | Pass | `MediaHttpTest::test_alt_text_is_required_before_media_can_be_published` (tidak ada baris `media_assets` maupun `private_files`) |
| MEDIA-03 | Pemakaian media tercatat dan tersinkron | Pass | `MediaPipelineTest::test_usage_is_recorded_from_real_references` (rujukan hilang → baris pemakaian ikut hilang) |
| MEDIA-04 | Arsip membuang salinan publik, menyimpan asli | Pass | `MediaPipelineTest::test_archive_removes_public_copy_but_keeps_private_original` |
| RBAC-05 | Preset permission baru sampai ke role lama tanpa menimpa | Pass (manual) | `tools seed` dua kali: `content_editor` menerima `cms.page.*` + `cms.media.upload`, `roles.preset_version` = 2, run kedua 0 baris baru |
| MIG-01 | Migration 004 reversibel | Pass (manual) | `tools migrate` → v4, `tools migrate 3`, `tools migrate` → v4 tanpa error |

Belum diuji pada tahap ini: pipeline derivative untuk PDF berukuran besar, rotasi kunci HMAC audit, dan perilaku
modul `public_readonly` terhadap endpoint tulis publik (belum ada modul publik yang menerima tulisan selain
laporan, yang statusnya `active`).

## Lingkungan

| Item | Nilai |
|---|---|
| Tanggal | 16 September 2026 |
| OS | Windows 10 Pro 10.0.19045 |
| PHP | 8.3.33 (CLI `C:\xampp\php-8.3.33\php.exe`; Apache mod_php 8.3.33) |
| Database | MariaDB 10.4.27 (XAMPP) — DB uji terpisah `cihawuk_digital_test` |
| PHPUnit | 11.5.56, `failOnWarning`/`failOnNotice`/`failOnDeprecation` aktif |
| HTTP test server | PHP built-in server 8.3.33, port 8766, `APP_ENV=testing` |
| Browser QA | Panel browser Claude (Chromium), server pratinjau port 8765 dan Apache `cihawuk.test` |

Perintah:

```powershell
& 'C:\xampp\php-8.3.33\php.exe' vendor/bin/phpunit
```

Bootstrap tes memaksa `APP_ENV=testing` (`.env.testing`) dan berhenti bila nama database tidak mengandung `_test`; skema direset
(`migrate 0` → `migrate` → `seed`) di awal setiap run. Database pengguna tidak disentuh.

## Hasil otomatis

**`OK (63 tests, 375 assertions)`** — durasi ±1 menit (16 September 2026, setelah perbaikan QA terakhir).

| Suite | Tes | Isi |
|---|---|---|
| unit | 14 | TOTP (vektor RFC 6238, replay), enkripsi/tamper, normalisasi kode, open redirect, URL aman, format tanggal/angka, kebijakan password, CSV formula injection, sanitasi HTML, normalisasi angka Indonesia, ekstraksi DOCX asli |
| service | 34 | SLA, workflow, kontrol akses, siklus akun, job/outbox/ekspor/CMS |
| http | 15 | Login/daftar/logout, CSRF, brute force, pencabutan sesi, MFA, pendaftaran oleh staf, laporan anonim, pelacakan, kirim ganda, rate limit, upload berbahaya, isolasi warga, lingkup petugas, XSS/SQLi, header keamanan |

Keluaran lengkap (`--testdox`) disimpan saat run; ringkasan per ID ada di tabel berikut.

## Hasil per ID (§27.1)

Status: **Pass** = dijalankan dan lulus; **Sebagian** = sebagian skenario dijalankan, sisanya dijelaskan;
**Not run** = belum dijalankan beserta alasannya. Tidak ada yang berstatus Fail pada run terakhir.

| ID | Status | Bukti |
|---|---|---|
| AUTH-01 | Pass | `AuthHttpTest::test_register_pending_then_active_login_logout` (pending ditolak masuk, aktivasi manual, login → `/warga`, logout POST, GET `/keluar` 404); `AccountLifecycleTest::test_self_registration_pending_then_manual_activation`, `test_email_activation_when_smtp_enabled`, `test_registration_validation_and_uniqueness` |
| AUTH-02 | Pass | `AuthHttpTest::test_staff_registers_resident_without_role_escalation` (akun pending, hanya role `resident`, kode aktivasi tampil sekali); `AccountLifecycleTest::test_staff_created_resident_activates_with_own_password` |
| AUTH-03 | Pass | `AccountLifecycleTest::test_reset_token_expiry_single_use_and_session_revocation`, `test_tokens_never_logged_in_plaintext` (token tidak ada di DB, audit, maupun `storage/logs`) |
| AUTH-04 | Pass | `AuthHttpTest::test_register_pending_then_active_login_logout` (cookie sesi berganti saat login), `test_revoked_and_suspended_sessions_end` (sesi dicabut dan akun ditangguhkan kehilangan akses) |
| AUTH-05 | Pass | `AuthHttpTest::test_bruteforce_is_throttled_without_blocking_other_accounts` (akun diserang 429, akun lain di IP sama tetap masuk); `AccessControlTest::test_rate_limiter_blocks_and_expires` (blok berakhir) |
| AUTH-06 | Pass | `AuthHttpTest::test_mfa_pre_auth_cannot_access_dashboard`; `AccountLifecycleTest::test_mfa_setup_and_recovery_codes_single_use`; `TotpTest` |
| RBAC-01 | Pass | `TicketHttpTest::test_resident_isolation_and_field_allowlist` (tiket warga B → 404 untuk A, tidak muncul di daftar A, lampiran 403) |
| RBAC-02 | Pass | `TicketHttpTest::test_staff_scope_and_editor_denial` (petugas tak ditugaskan 403, DataTables `recordsTotal=0`); `AccessControlTest::test_officer_only_sees_assigned_tickets` |
| RBAC-03 | Pass | `TicketHttpTest::test_staff_scope_and_editor_denial` (editor 403 pada `admin/laporan`, `admin/laporan/data`, `admin/pengguna`, `admin/ekspor`, dan detail tiket); `AccessControlTest::test_editor_and_super_admin_have_no_ticket_access` |
| RBAC-04 | Pass | `TicketHttpTest::test_resident_isolation_and_field_allowlist` (POST `status`, `assigned_user_id`, `reporter_user_id` diabaikan) |
| ANON-01 | Pass | `TicketHttpTest::test_anonymous_submit_track_and_reply`; `TicketWorkflowTest::test_anonymous_submission_stores_no_identity` |
| ANON-02 | Pass | `TicketHttpTest::test_anonymous_submit_track_and_reply` (kode salah 401 pesan generik), `test_tracking_bruteforce_is_limited` (429); `AccessControlTest::test_access_code_verification` |
| ANON-03 | Sebagian | Penyimpanan tanpa relasi akun diuji di service (`test_anonymous_submission_stores_no_identity`: `reporter_user_id` dan `created_by_user_id` NULL). Jalur HTTP "pengguna login memilih tidak mengaitkan akun" belum memiliki tes otomatis khusus dan belum diperiksa manual pada sesi QA ini |
| ANON-04 | Pass | `test_anonymous_submit_track_and_reply`: redirect ke `/lapor/berhasil` tanpa kode di URL, `no-store`, kode tidak ada di berkas log maupun audit; hash di DB (`test_anonymous_submission_stores_no_identity`) |
| ANON-05 | Pass | `test_anonymous_submit_track_and_reply` (tanpa grant → redirect; balasan dengan grant tersimpan; balasan palsu dari sesi lain tidak tersimpan) |
| FLOW-01 | Pass | `TicketWorkflowTest::test_full_happy_path_records_history`, `test_status_cannot_be_skipped` |
| FLOW-02 | Pass | `TicketWorkflowTest::test_needs_information_returns_to_original_stage` |
| FLOW-03 | Pass | `TicketWorkflowTest::test_reassignment_duplicate_and_referral`; reopen di `SlaServiceTest::test_ticket_sla_snapshot_pause_and_reopen` |
| FLOW-04 | Pass | `TicketWorkflowTest::test_version_conflict_is_detected` (versi lama → `VersionConflictException` → HTTP 409) |
| FLOW-05 | Pass | `TicketWorkflowTest::test_front_desk_records_creator_separately` (loket anonim dan berakun) |
| FLOW-06 | Pass | `AccessControlTest::test_restricted_ticket_requires_confidential_permission`, `test_conflict_of_interest_blocks_access_and_assignment`; `TicketWorkflowTest::test_sensitive_category_is_restricted_and_withdraw_rules` |
| DATA-01 | Pass | `SourceNormalizationTest::test_indonesian_number_formats`, `test_codes_and_missing_values`, `test_real_docx_extraction_when_available` (S1 asli: `6.809` → 6809) |
| DATA-02 | Pass (manual + seed) | Observasi 2021/2023 tersimpan dengan tahun berbeda; 16 nilai statistik tetap `draft`; luas konflik berstatus `conflict` dan tidak dipromosikan (lihat `docs/source-inventory.md`). Penerbitan nilai `unverified` ditolak 409 (kode `Statistik::value_status`). Belum ada tes otomatis khusus |
| DATA-03 | Pass | Merged cell: `test_real_docx_extraction_when_available` (locator unik). Impor ulang: `tools import_sources S4` kedua kali → 0 observasi baru, total tetap 5.676 |
| CMS-01 | Pass | `JobsAndExportTest::test_content_publication_workflow` (draft tidak publik, terbit tampil, slug 301, arsip hilang). Manual: berita uji diterbitkan dan tampil di `/berita`, `<script>` dibuang |
| FILE-01 | Pass | `TicketHttpTest::test_malicious_uploads_are_rejected` (PHP sebagai JPG, SVG, `foto.php.png`, ZIP sebagai PDF, >3 berkas → 422; tidak ada tiket/berkas tersimpan; tidak ada `.php` di `public/media`). Traversal nama berkas dinetralkan (`sanitize_name`, nama simpan acak) |
| FILE-02 | Pass | `test_anonymous_submit_track_and_reply` dan `test_resident_isolation_and_field_allowlist` (unduhan tanpa hak 403); OPS-01 (storage tidak dapat diakses langsung) |
| SEC-01 | Pass | `TicketHttpTest::test_xss_is_escaped_and_datatables_sort_is_allowlisted` (laporan, detail admin, JSON, pencarian); `CryptoAndHelpersTest::test_html_sanitizer_strips_active_content` |
| SEC-02 | Pass | `AuthHttpTest::test_csrf_is_required_for_mutations` (login, laporan, AJAX → 403 + token baru). Endpoint status/arsip admin memakai filter CSRF global yang sama, tetapi tidak diuji satu per satu |
| SEC-03 | Sebagian | Respons JSON `csrf_invalid` + token baru dan `GET /csrf-token` teruji otomatis. Aksi verifikasi lewat UI dashboard berhasil. Skenario dua tab, AJAX bersamaan, dan token berputar di browser **belum diuji** |
| SEC-04 | Pass | `test_xss_is_escaped_and_datatables_sort_is_allowlisted` (sort kolom injeksi diabaikan, keyword injeksi dicari sebagai teks) |
| SLA-01 | Pass | `SlaServiceTest` (akhir pekan, setelah jam tutup, libur + hari kerja pengganti, snapshot, pause, reopen episode baru) dengan waktu tetap |
| JOB-01 | Pass | `JobsAndExportTest::test_outbox_not_configured_and_dedupe`, `test_smtp_failure_retries_with_backoff_then_fails` (SMTP ditolak → retry 1 menit → gagal setelah 5 percobaan), `test_ticket_survives_mail_failure_and_job_lock`, `test_sla_escalation_is_deduplicated` |
| NET-01 | Pass | `TicketHttpTest::test_double_submit_creates_single_ticket` (kunci idempotensi + isi sama → tetap satu tiket; kunci sama dengan isi berbeda → 409). Skenario koneksi putus setelah commit dicakup oleh mekanisme yang sama (kirim ulang dengan kunci sama), bukan disimulasikan pada level jaringan; `AccessControlTest::test_idempotent_submission` |
| EXPORT-01 | Pass | `CryptoAndHelpersTest::test_csv_formula_injection_is_neutralized`; `JobsAndExportTest::test_export_denied_when_permission_revoked_before_run` (job ditolak dan unduhan ditolak setelah role dicabut) |
| UI-01 | Sebagian | Tanpa scroll horizontal: 360 px (semua halaman publik utama), 390 px (beranda, formulir, bukti, dashboard warga), 640 px (setara 1280 px pada zoom 200%: beranda, `/lapor` — stepper dan tombol kirim tersedia, header 76 px tidak menutup form), 768 px (beranda, lapor, data desa, potensi, lacak), 1024 px (beranda, admin daftar/detail), 1440 px (beranda, lapor, berita). Zoom browser 200% sesungguhnya tidak dapat diemulasikan di panel; halaman admin belum diperiksa di 360/768 px |
| UI-02 | Sebagian | WebGL gagal: `getContext` dipaksa null → loader menyetel `fallback`, judul dan tombol hero tetap tampil. Keyboard: skip link menjadi elemen fokus pertama dan menuju `#konten`, tidak ada `tabindex` positif; tampilan fokus tidak dapat dilihat karena panel browser tidak memiliki fokus jendela. Reduced motion: aturan CSS dan pemeriksaan di loader ditinjau di kode, tidak diemulasikan |
| UI-03 | Sebagian | Peta: tanpa titik terverifikasi selalu tampil kartu "Peta sedang dilengkapi"; hero tanpa WebGL memakai ilustrasi statis. Simulasi jaringan lambat dan pengukuran LCP **belum dijalankan** |
| OPS-01 | Pass | Apache `cihawuk.test`: `/.env`, `/.git`, `/media/.htaccess` → 403; `/application`, `/system`, `/vendor`, `/storage`, `/reference`, `/tools/health` → 404; PHP di `/media` tidak dieksekusi (403); `localhost/cihawuk/*` → 403. Otomatis: `test_security_headers_and_blocked_paths` |
| OPS-02 | Pass | `scripts/backup.ps1` → `scripts/restore-check.ps1` ke database baru `cihawuk_restore_check`: checksum cocok, jumlah baris tabel inti sama, berkas privat ada → `HASIL: PULIH`. Database uji pemulihan dihapus setelahnya |
| PHP-01 | Pass | `tools health` CLI dan HTTP `SEHAT`; seluruh suite tanpa deprecation/warning; tidak ada error PHP di `storage/logs` setelah QA via Apache (hanya entri "Mail send failed" yang sengaja dipicu tes retry SMTP) |

## Pemeriksaan visual (§27.2)

Diperiksa di panel browser: beranda (hero, akses cepat, statistik kosong berlabel, potensi, berita, peta fallback),
potensi, data desa, formulir laporan (stepper mobile, error summary), halaman bukti, pelacakan, dashboard warga,
daftar dan detail laporan pengelola (DataTables, aksi verifikasi). Temuan yang sudah diperbaiki selama QA:

- Label "(wajib)" tampil di dashboard BS4 → diberi `sr-only`; `.form-select` diberi gaya BS4.
- Filter checkbox DataTables selalu terkirim `1` → dikirim sesuai status centang.
- Kolom tabel admin terpotong → aturan lebar/`overflow-wrap`, `autoWidth: false`.
- `legend` form publik terlalu besar → disamakan dengan label.
- Tombol "Kembali" memakai `javascript:` diblokir CSP → diganti handler `data-dialog-close`.

## Belum diuji

- MySQL 8 (hanya MariaDB tersedia), server Linux/Nginx, HTTPS.
- Pengiriman SMTP nyata (hanya mode capture dan koneksi ditolak).
- Pengukuran performa (LCP/CLS), jaringan lambat, perangkat fisik.
- Pembaca layar (NVDA/TalkBack).
- Impor S3 (`.doc`).
- Uji penetrasi pihak ketiga.

---

## Hasil akhir sesi 19 September 2026 (tahap 4–12)

```
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit
OK (235 tests, 1102 assertions)
```

Bootstrap pengujian menjalankan `tools migrate 0` → `tools migrate` → `tools seed` pada
`cihawuk_digital_test` setiap kali suite dijalankan, sehingga **reversibilitas seluruh 15
migration dari nol ikut terbukti pada setiap run**.

### Berkas uji dan jumlah tesnya

| Berkas | Tes | Fokus |
|---|---:|---|
| `service/DatasetTest.php` | 10 | Dataset berversi, verifikasi, ambang data kecil, rollback, impor idempotent |
| `http/DatasetHttpTest.php` | 7 | Draft tidak publik, grafik + tabel setara, CSV aman, pemisahan izin, modul nonaktif |
| `service/ProfileTest.php` | 10 | Blok berversi, konflik luas wajib dijelaskan, klaim "sampai sekarang", rollback |
| `http/ProfileHttpTest.php` | 5 | Halaman profil kosong sampai terbit, nomor SK dan foto tanpa izin tidak bocor |
| `service/FacilityTest.php` | 11 | Angka agregat ditolak, koordinat sensitif, izin kontak, penerbitan |
| `http/FacilityHttpTest.php` | 6 | Direktori publik, koordinat tidak bocor ke HTML, pemisahan izin |
| `service/OrganizationTest.php` | 11 | Cycle, kedalaman, satu penugasan aktif, foto berizin, snapshot |
| `http/OrganizationHttpTest.php` | 5 | Tree terbaca tanpa JavaScript, nomor SK tidak bocor |
| `service/BudgetTest.php` | 12 | Revisi terpisah, total komponen, realisasi melebihi anggaran, kunci periode |
| `http/BudgetHttpTest.php` | 4 | Halaman bertingkat, CSV dan JSON aman, pemisahan izin alur |
| `service/BusinessTest.php` | 7 | Persetujuan pemilik, pencabutan, arsip |
| `http/BusinessHttpTest.php` | 3 | Direktori publik dan pemisahan izin |
| `service/AssetTest.php` | 11 | Dua tingkat data, pemecahan unit beralasan, QR sekali pakai, field sensitif |
| `service/AssetAuditTest.php` | 6 | Target beku, temuan tidak menimpa master, penutupan, addendum |
| `service/AssetImportTest.php` | 6 | Nilai mentah utuh, baris bermasalah ditandai, impor ulang idempotent |
| `http/AssetHttpTest.php` | 5 | Halaman QR semua keadaan, noindex, di luar sitemap, izin dashboard |
| `service/WarehouseTest.php` | 10 | Saldo dari ledger, stok negatif, dua pengeluaran stok terakhir, konversi bulat, opname |
| `service/LinkCheckTest.php` | 4 | Route sehat, tautan rusak terdeteksi, tautan luar tidak dipanggil |

Berkas uji tahap sebelumnya tetap berjalan tanpa perubahan perilaku, kecuali tiga
penyesuaian yang dicatat pada `docs/progress.md`: kontrak section statistik berubah ke
dataset, daftar modul "belum dibangun" menyusut karena modulnya sudah dibangun, dan setiap
berkas uji HTTP kini memuat `require_once __DIR__.'/HttpTestCase.php';` sendiri.

### Perintah operasional yang dijalankan

```
tools migrate 14 -> 15   (turun-naik pada DB pengembangan, tanpa kehilangan data)
tools seed               (dua kali; run kedua 0 baris baru)
tools seed_demo          (menambah verifikator.demo, keuangan.demo, auditkeuangan.demo, aset.demo, gudang.demo)
tools health             (Status: SEHAT)
tools import_assets aset-contoh.csv   (dua kali; run kedua 5 baris dilewati)
tools run_jobs           (7 pekerjaan, termasuk link_check: 35 tautan, 0 rusak)
```

### Pemeriksaan HTTP manual

23 halaman publik diperiksa dan seluruhnya 200: beranda, profil dan tiga subhalamannya,
pemerintahan dan strukturnya, data desa dan tema, fasilitas, UMKM, transparansi anggaran,
potensi, berita, agenda, galeri, dokumen, kontak, layanan, lapor, lacak, cari, sitemap.

Jalur yang harus tertutup tetap tertutup: `/.env` 403; `/application/...`, `/storage/...`,
`/database/...`, `/reference/...`, `/composer.json` 404; `/admin/...` mengarahkan ke login.

### Yang TIDAK diuji pada sesi ini

- Pengujian visual dan responsif (360/390/768/1024/1440 px, zoom 200%) tidak dijalankan ulang
  karena sesi ini tidak punya peramban. Markupnya mengikuti pola halaman yang sudah lulus QA
  visual pada tahap 6 lama, dan setiap tabel baru diberi `<caption>` serta setiap form diberi
  label, tetapi itu belum menggantikan pemeriksaan mata.
- Pembaca layar, navigasi keyboard menyeluruh, dan pengujian jaringan lambat belum dijalankan.
- MySQL 8 dan Linux/Nginx belum diuji; seluruh hasil di atas dari MariaDB 10.4.27 di Windows.


---

## Pembaruan 19 September 2026 — data demonstrasi dan impor S3

```
C:\xampp\php-8.3.33\php.exe vendor/bin/phpunit
OK (245 tests, 1193 assertions)   Waktu 02:50
```

Naik dari 235 tes / 1102 assertion. Tiga berkas baru:

| Berkas | Tes | Yang dibuktikan |
|---|---|---|
| `tests/service/MediaImportTest.php` | 5 | Berkas dari disk masuk dengan 3 derivative dan berkas asli tetap utuh; `rights_status = unknown` tetap draft; skrip PHP bernama `.jpg` ditolak; berkas tidak ada ditolak; kegagalan tidak meninggalkan `private_files` tanpa `media_assets` |
| `tests/service/DemoSeedTest.php` | 3 | 23 tabel kembali **persis** ke jumlah semula setelah `purge`; setiap jenis entitas yang tercatat punya aturan penghapusan; penjaga `production` ada |
| `tests/http/DemoModeHttpTest.php` | 2 | Dengan `DEMO_MODE=true`, 10 halaman publik memuat bilah penanda dan `noindex`; penandanya menyebut isinya contoh dan bukan data resmi |

`DemoModeHttpTest` menjalankan servernya sendiri pada porta 8767 dengan `DEMO_MODE=true`.
Variabel proses menang atas `.env.testing` karena Dotenv dimuat dengan `createImmutable`,
sehingga saklarnya diuji lewat jalur yang sama dengan pemakaian sungguhan.

### Verifikasi manual pada basis data pengembangan

| Pemeriksaan | Hasil |
|---|---|
| 20 halaman publik | seluruhnya 200 |
| Halaman QR token sah | 200, menampilkan tag, status dan kondisi |
| Halaman QR token tak dikenal | 200 (bukan 500 atau halaman kosong) |
| Kebocoran pada 11 halaman + sitemap | tidak ditemukan nomor seri, kode register, nilai perolehan, maupun kata sandi demo |
| `tools seed` dua kali | run kedua 0 baris baru |
| `tools seed_demo` lalu `purge_demo` | seluruh tabel kembali ke jumlah semula |
| `tools health` | SEHAT |

### Yang TIDAK diuji pada sesi ini

- Tampilan visual dan responsif foto demo pada berbagai lebar layar; tidak ada peramban di sesi ini.
- Kelayakan subjek setiap foto dinilai dengan memeriksa gambarnya satu per satu, bukan dengan tes otomatis.
- Konversi `.doc` bergantung pada Microsoft Word di mesin ini; tidak ada tes otomatis untuk jalur itu.
- Pemeriksaan pasangan foto dan nama pada 31 gambar S3 belum dilakukan; itu pekerjaan pengelola.
