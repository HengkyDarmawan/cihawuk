<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Konfigurasi domain aplikasi. Kode teknis berbahasa Inggris; label Indonesia.
*/

$config['app_name'] = (string) app_env('APP_NAME', 'Desa Cihawuk');
$config['app_timezone'] = (string) app_env('APP_TIMEZONE', 'Asia/Jakarta');

$config['features'] = array(
	'letters' => app_env_bool('FEATURE_LETTERS', FALSE),
	'remember_me' => app_env_bool('FEATURE_REMEMBER_ME', FALSE),
	'public_service_stats' => app_env_bool('FEATURE_PUBLIC_SERVICE_STATS', FALSE),
	'three_hero' => app_env_bool('FEATURE_THREE_HERO', TRUE),
	'mfa_enforce_privileged' => app_env_bool('FEATURE_MFA_ENFORCE_PRIVILEGED', FALSE),
	'mail' => app_env_bool('MAIL_ENABLED', FALSE),
	// Cache halaman publik; dimatikan pada .env.testing agar tes membaca keadaan terbaru.
	'public_cache' => app_env_bool('FEATURE_PUBLIC_CACHE', TRUE),
	// Mode demonstrasi: memberi tanda pada setiap halaman publik bahwa isinya contoh,
	// dan memaksa noindex supaya angka contoh tidak pernah terindeks mesin pencari.
	'demo_mode' => app_env_bool('DEMO_MODE', FALSE),
);

// Titik pusat perkiraan desa untuk peta publik selama belum ada titik terverifikasi.
// Sumber: Dokumen Profil Desa 2023 mencatat "7,1999 / 107,7050" tanpa tanda lintang
// (docs/data-issues.md LATITUDE_SIGN); lintang selatan satu-satunya yang jatuh di Jawa Barat.
// Selalu ditampilkan dengan label "perkiraan". Timpa lewat VILLAGE_LAT / VILLAGE_LNG.
$config['village_center'] = array(
	'lat' => (float) app_env('VILLAGE_LAT', '-7.1999'),
	'lng' => (float) app_env('VILLAGE_LNG', '107.7050'),
	'zoom' => 14,
	'radius_m' => 1000,
	'approximate' => TRUE,
	'source' => 'Dokumen Profil Desa 2023',
);

// Batas sesi (menit)
$config['session_limits'] = array(
	'admin' => array('idle' => app_env_int('SESSION_ADMIN_IDLE', 30), 'absolute' => app_env_int('SESSION_ADMIN_ABSOLUTE', 480)),
	'resident' => array('idle' => app_env_int('SESSION_RESIDENT_IDLE', 60), 'absolute' => app_env_int('SESSION_RESIDENT_ABSOLUTE', 720)),
);

$config['password_min_length'] = 12;
$config['password_max_length'] = 256;

$config['account_statuses'] = array(
	'pending_activation' => 'Menunggu aktivasi',
	'active' => 'Aktif',
	'suspended' => 'Ditangguhkan',
	'closed' => 'Ditutup',
);

$config['resident_verification_statuses'] = array(
	'unverified' => 'Belum diverifikasi',
	'pending' => 'Menunggu review',
	'verified' => 'Terverifikasi',
	'rejected' => 'Ditolak',
);

$config['report_types'] = array(
	'complaint' => 'Pengaduan',
	'aspiration' => 'Aspirasi',
	'information_request' => 'Permintaan Informasi',
);

$config['intake_channels'] = array(
	'public_anonymous' => 'Formulir publik',
	'resident_dashboard' => 'Dashboard warga',
	'front_desk' => 'Loket kantor desa',
);

$config['identity_modes'] = array(
	'anonymous' => 'Anonim',
	'identified' => 'Identitas tercatat',
	'masked' => 'Identitas disembunyikan',
);

$config['confidentiality_levels'] = array(
	'private' => 'Privat',
	'restricted' => 'Rahasia (akses terbatas)',
);

$config['priorities'] = array(
	'low' => 'Rendah',
	'normal' => 'Normal',
	'high' => 'Tinggi',
	'urgent' => 'Mendesak',
);

/*
| Status tiket: label untuk pelapor, label internal, ikon dan warna (bukan satu-satunya penanda).
*/
$config['ticket_statuses'] = array(
	'submitted' => array('label' => 'Terkirim', 'staff' => 'Menunggu verifikasi', 'icon' => 'send', 'tone' => 'secondary'),
	'verifying' => array('label' => 'Sedang diverifikasi', 'staff' => 'Diverifikasi', 'icon' => 'search', 'tone' => 'info'),
	'needs_information' => array('label' => 'Perlu dilengkapi', 'staff' => 'Menunggu kelengkapan pelapor', 'icon' => 'help-circle', 'tone' => 'warning'),
	'assigned' => array('label' => 'Diteruskan ke petugas', 'staff' => 'Didisposisikan', 'icon' => 'user-check', 'tone' => 'primary'),
	'in_progress' => array('label' => 'Sedang ditangani', 'staff' => 'Dalam penanganan', 'icon' => 'tool', 'tone' => 'primary'),
	'awaiting_confirmation' => array('label' => 'Menunggu tanggapan Anda', 'staff' => 'Menunggu konfirmasi pelapor', 'icon' => 'message-circle', 'tone' => 'warning'),
	'resolved' => array('label' => 'Selesai', 'staff' => 'Selesai', 'icon' => 'check-circle', 'tone' => 'success'),
	'rejected' => array('label' => 'Tidak dapat diproses', 'staff' => 'Ditolak', 'icon' => 'x-circle', 'tone' => 'danger'),
	'referred' => array('label' => 'Dirujuk ke layanan lain', 'staff' => 'Dirujuk', 'icon' => 'external-link', 'tone' => 'dark'),
	'withdrawn' => array('label' => 'Ditarik pelapor', 'staff' => 'Ditarik pelapor', 'icon' => 'corner-up-left', 'tone' => 'secondary'),
);

$config['ticket_terminal_statuses'] = array('resolved', 'rejected', 'referred', 'withdrawn');

$config['rejection_reasons'] = array(
	'incomplete' => 'Informasi tidak cukup setelah diminta',
	'duplicate' => 'Duplikat laporan lain',
	'out_of_scope' => 'Bukan kewenangan desa',
	'spam' => 'Spam atau penyalahgunaan (hasil review)',
	'not_actionable' => 'Tidak dapat ditindaklanjuti',
	'other' => 'Lainnya',
);

$config['publication_statuses'] = array(
	'draft' => 'Draft',
	'in_review' => 'Menunggu review',
	'published' => 'Terbit',
	'archived' => 'Diarsipkan',
);

/* Status akses pengunjung untuk potensi (modul-frontend 7.2). */
$config['potential_access_statuses'] = array(
	'unknown' => 'Belum diketahui',
	'open' => 'Terbuka untuk umum',
	'limited' => 'Terbatas atau perlu izin',
	'closed' => 'Tidak terbuka untuk umum',
);

$config['verification_statuses'] = array(
	'unverified' => 'Belum diverifikasi',
	'pending' => 'Menunggu review',
	'verified' => 'Terverifikasi',
	'rejected' => 'Ditolak',
);

// Upload lampiran laporan (kebijakan proyek, dapat disesuaikan)
$config['ticket_upload'] = array(
	'max_files' => 3,
	'max_file_bytes' => 5 * 1024 * 1024,
	'max_total_bytes' => 15 * 1024 * 1024,
	'max_image_pixels' => 40000000,
	'max_image_side' => 10000,
	'types' => array(
		'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
	),
);

// Anti-spam kanal publik (per sumber jaringan). Nilai awal dapat diubah di app_settings.
$config['rate_limits'] = array(
	'public_report' => array('limit' => 5, 'window' => 900, 'block' => 900),
	'public_report_global' => array('limit' => 200, 'window' => 900, 'block' => 300),
	'track_ip' => array('limit' => 10, 'window' => 900, 'block' => 900),
	'track_ticket' => array('limit' => 5, 'window' => 900, 'block' => 1800),
	'login_identifier' => array('limit' => 5, 'window' => 900, 'block' => 900),
	'login_ip' => array('limit' => 30, 'window' => 900, 'block' => 900),
	'mfa' => array('limit' => 5, 'window' => 300, 'block' => 600),
	'register_ip' => array('limit' => 5, 'window' => 3600, 'block' => 3600),
	'forgot_ip' => array('limit' => 5, 'window' => 900, 'block' => 900),
	'activation' => array('limit' => 10, 'window' => 900, 'block' => 900),
	'search_ip' => array('limit' => 60, 'window' => 300, 'block' => 120),
	'resident_report' => array('limit' => 10, 'window' => 3600, 'block' => 900),
	// Pemindaian QR aset: cukup longgar untuk audit massal petugas, tetapi menutup penebakan token.
	'asset_qr_ip' => array('limit' => 120, 'window' => 900, 'block' => 600),
);

$config['anonymous_grant_ttl'] = 1800;
$config['receipt_ttl'] = 900;
$config['reauth_ttl'] = 600;
