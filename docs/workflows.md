# Alur Kerja Layanan

Pengaduan, aspirasi, dan permintaan informasi memakai satu model tiket (`tickets.report_type`). Semua perubahan
status melewati `TicketWorkflowService::perform()`; controller tidak pernah menulis kolom status langsung.
Matriks ini adalah rancangan Cihawuk, **belum SOP resmi** pemerintah desa.

## Status

| Kode | Label pelapor | Label internal |
|---|---|---|
| `submitted` | Terkirim | Menunggu verifikasi |
| `verifying` | Sedang diverifikasi | Diverifikasi |
| `needs_information` | Perlu dilengkapi | Menunggu kelengkapan pelapor |
| `assigned` | Diteruskan ke petugas | Didisposisikan |
| `in_progress` | Sedang ditangani | Dalam penanganan |
| `awaiting_confirmation` | Menunggu tanggapan Anda | Menunggu konfirmasi pelapor |
| `resolved` | Selesai | Selesai |
| `rejected` | Tidak dapat diproses | Ditolak |
| `referred` | Dirujuk ke layanan lain | Dirujuk |
| `withdrawn` | Ditarik pelapor | Ditarik pelapor |

Status akhir: `resolved`, `rejected`, `referred`, `withdrawn` (hanya `resolved` yang dapat dibuka kembali).
Badge status selalu memakai ikon + teks, bukan warna saja.

## Transisi (allowlist `TicketWorkflowService::ALLOWED`)

```
submitted ──start_verification──► verifying ──assign──► assigned ──accept_work──► in_progress
    │                               │  │  │                                           │
    └─withdraw (pelapor)            │  │  └─reject──► rejected                        └─propose_resolution─► awaiting_confirmation
                                    │  └──refer───► referred                                                   │   │   │
                                    └─request_information─► needs_information ─provide_information─► (status asal) │   │
                                                                                                                  │   │
                     resolved ◄──accept_result (pelapor) / close_by_policy (petugas)──────────────────────────────┘   │
                        │                                   in_progress ◄──request_followup (pelapor) / resume_work ──┘
                        └──reopen──► in_progress (episode baru)
```

| Aksi | Dari | Ke | Pelaku | Syarat tambahan |
|---|---|---|---|---|
| `start_verification` | submitted | verifying | petugas (`verify`) | — |
| `request_information` | verifying, assigned, in_progress | needs_information | petugas (`verify` / penanggung jawab) | Pertanyaan untuk pelapor; status asal disimpan di `return_status`; SLA penyelesaian dijeda |
| `assign` | verifying, assigned, in_progress, needs_information | assigned | `tickets.assign` | Penanggung jawab lolos `can_be_assignee`; pemindahan wajib alasan; riwayat `ticket_assignments` |
| `reject` | verifying | rejected | `verify` | Alasan dari daftar + penjelasan ≥15 karakter; alasan `duplicate` wajib nomor tiket lain |
| `refer` | verifying | referred | `verify` | Tujuan, jenis (`guidance_only` / `actual_forwarding`), penjelasan ≥15; penerusan nyata wajib bukti/nomor |
| `accept_work` | assigned | in_progress | penanggung jawab | Menandai respons pertama SLA |
| `follow_up` | verifying … awaiting_confirmation | (tetap) | penanggung jawab / verifikator / pemantau | Catatan publik atau internal, lampiran |
| `propose_resolution` | in_progress | awaiting_confirmation | penanggung jawab / `close` | Ringkasan hasil; membuka jendela konfirmasi |
| `close_by_policy` | awaiting_confirmation | resolved | `tickets.close` | Alasan + dasar kebijakan ≥15 karakter |
| `resume_work` | awaiting_confirmation | in_progress | penanggung jawab / `close` | — |
| `reopen` | resolved | in_progress | `tickets.reopen` | Alasan; wajib ada penanggung jawab; episode + SLA baru |
| `reply` | submitted … awaiting_confirmation | (tetap) | pelapor | — |
| `provide_information` | needs_information | `return_status` | pelapor | Jeda SLA diakhiri |
| `accept_result` | awaiting_confirmation | resolved | pelapor | Penilaian/komentar opsional (`ticket_feedback`) |
| `request_followup` | awaiting_confirmation | in_progress | pelapor | Alasan |
| `withdraw` | submitted, verifying | withdrawn | pelapor | — |
| `request_withdrawal` | assigned … awaiting_confirmation | (tetap) | pelapor | Dicatat (`withdrawal_requested_at`) dan diberitahukan ke petugas, yang memutuskan tindak lanjutnya |

Setiap aksi: `SELECT … FOR UPDATE`, cek `version` yang dikirim form (beda → **409**, pengguna diminta memuat ulang),
update status + `version+1`, satu baris `ticket_status_history`, pembaruan SLA, dan notifikasi — semua dalam satu
transaksi. Aksi yang tidak tersedia pada status saat ini ditolak 409, sehingga status tidak dapat dilompati.
Tombol aksi di UI dihitung dari `staff_actions()` / `reporter_actions()`, tetapi server selalu memeriksa ulang.

## Kanal masuk

### Anonim (`/lapor`)
1. Formulir 3 langkah (jenis & kategori → isi & lokasi opsional → lampiran & pernyataan). Tidak meminta nama, NIK,
   atau kontak. Honeypot, rate limit per jaringan dan global, token idempotensi.
2. Pengguna yang sedang login dapat memilih **tidak mengaitkan akun**; tiket disimpan dengan
   `reporter_user_id = NULL` dan `identity_mode = anonymous`.
3. Setelah commit, halaman bukti (`/lapor/berhasil`) menampilkan nomor tiket + kode akses **satu kali** (disimpan di
   sesi maks. 15 menit, lalu dihapus). Kode akses tidak pernah muncul di URL, log, atau email.
4. Kirim ganda dengan token yang sama mengembalikan tiket yang sama (bukti hanya pada sesi yang sama).

### Pelacakan anonim (`/lacak`)
- POST nomor tiket + kode akses; verifikasi HMAC waktu-konstan, rate limit per IP dan per tiket, pesan gagal generik.
- Berhasil → grant sesi terikat **satu** tiket selama 30 menit (`anonymous_access_grants`), session ID diregenerasi.
- Pemegang grant dapat membaca status/pesan publik, membalas, melengkapi informasi, menerima hasil/meminta tindak
  lanjut, menarik laporan, dan **Keluar** (mencabut grant). Lampiran hanya yang berstatus publik untuk tiket itu.
- **Kaitkan ke akun** (opsional, `POST /lacak/klaim`): warga yang login dan memegang grant dapat mengaitkan tiket
  anonim ke akunnya setelah mencentang persetujuan. Tiket menjadi `identity_mode = masked`, riwayat
  `claim_by_account` dicatat, dan grant dicabut. Tidak pernah terjadi otomatis.

### Warga berakun (`/warga/laporan/buat`)
- Tiket terkait akun (`identity_mode = identified` atau `masked` bila memilih menyembunyikan identitas dari petugas
  umum). Daftar/detail hanya milik sendiri; field seperti status/owner/role dari form diabaikan (allowlist).

### Loket (`/admin/laporan/buat`, `tickets.create_on_behalf`)
- Petugas mencari warga (hasil termasking) atau mencatat tanpa akun.
- Tanpa akun: pilih anonim (sistem menerbitkan kode akses untuk diserahkan ke pelapor) atau catat kontak
  (disimpan terenkripsi di `ticket_private_contacts`).
- `created_by_user_id` = petugas loket, `reporter_user_id` = warga (atau NULL); `intake_channel = front_desk`.

## Kerahasiaan dan konflik kepentingan

- Kategori sensitif (Perilaku aparat, Perlindungan) → `confidentiality = restricted`: hanya pengguna dengan
  `tickets.handle_confidential` yang dapat melihat/ditugaskan.
- **Tandai konflik** (`/admin/laporan/{kode}/konflik`) mencatat pengguna di `ticket_conflicts`; pengguna itu
  kehilangan akses dan tidak dapat ditugaskan. Bila ia penanggung jawab aktif, tiket harus dipindahkan.
- Pelapor yang juga staf tidak dapat menangani tiketnya sendiri.

## Duplikat dan rujukan

- Duplikat: ditolak dengan alasan `duplicate` + nomor tiket induk (`duplicate_of_ticket_id`). Pelapor hanya melihat
  bahwa laporan digabung; isi tiket lain tidak dibuka.
- Rujukan: `guidance_only` (arahan ke layanan lain) atau `actual_forwarding` (diteruskan nyata, bukti wajib), tercatat
  di `ticket_references`. Tidak ada integrasi otomatis dengan LAPOR!/SP4N; rujukan hanya catatan.

## SLA

- Kebijakan `DEFAULT` (usulan): verifikasi **3** hari kerja sejak masuk, respons pertama **5** hari kerja sejak
  disposisi, jendela konfirmasi pelapor **10** hari kerja; tidak ada target penyelesaian tetap dan **tidak ada
  penutupan otomatis**.
- Kalender contoh: Senin–Jumat 08.00–16.00 WIB, bertanda "contoh" sampai diganti. Libur dan hari kerja pengganti
  diatur di **Pengaturan → Kalender**.
- Tenggat dihitung dalam WIB (akhir hari kerja ke-N) lalu disimpan UTC. Laporan setelah jam tutup tetap dihitung
  mulai hari kerja berikutnya.
- Saat dibuat, kebijakan disalin ke `ticket_sla_instances.policy_snapshot_json`; perubahan kebijakan tidak mengubah
  tiket lama.
- `needs_information` menjeda SLA (`ticket_sla_pauses`); jawaban pelapor mengakhiri jeda dan menggeser tenggat.
- `reopen` membuat episode baru dengan instance SLA baru; episode lama tetap tercatat.
- Job `sla_escalation` membuat eskalasi (dedupe per tiket/episode/milestone) dan notifikasi ke penanggung
  jawab/koordinator. Job `confirmation_reminder` mengingatkan pelapor dan petugas bila jendela konfirmasi lewat —
  petugas yang memutuskan `close_by_policy`.
- Daftar pengelola menampilkan indikator "Terlambat" (ikon + teks).

## Notifikasi

| Peristiwa | Penerima |
|---|---|
| Laporan baru | Pengguna dengan `tickets.verify` |
| Disposisi / pemindahan | Penanggung jawab baru (dan lama) |
| Perubahan status yang terlihat pelapor | Pelapor berakun (in-app + email bila SMTP aktif) |
| Balasan pelapor / permintaan tarik | Penanggung jawab atau verifikator |
| Eskalasi SLA | Penanggung jawab dan pemantau unit |
| Ekspor siap | Peminta |

Pelapor anonim tidak menerima email; mereka memantau lewat `/lacak`.
