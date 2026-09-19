<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Perintah CLI proyek. Ditolak bila dipanggil melalui HTTP.
 *
 *   php public/index.php tools health
 *   php public/index.php tools generate_keys
 *   php public/index.php tools migrate [versi]
 *   php public/index.php tools seed
 *   php public/index.php tools seed_demo
 *   php public/index.php tools create_admin
 *   php public/index.php tools run_jobs [nama_job]
 *   php public/index.php tools import_sources [S1|S2|S3|S4]
 */
class Tools extends Cli_Controller {

	public function index()
	{
		$this->line('Perintah: health, generate_keys, migrate, seed, seed_demo, create_admin, run_jobs, import_sources, import_assets, import_media, purge_demo');
	}

	/** Dipakai bootstrap pengujian untuk memuat instance CI tanpa efek samping. */
	public function noop()
	{
	}

	public function health()
	{
		$ok = TRUE;
		$check = function ($label, $pass, $detail = '') use (&$ok) {
			$ok = $ok && $pass;
			$this->line(sprintf('  [%s] %s%s', $pass ? 'OK' : 'GAGAL', $label, $detail !== '' ? ' — '.$detail : ''));
		};

		$this->line('Kesehatan aplikasi Desa Cihawuk');
		$check('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>='), PHP_SAPI);
		$check('Environment', in_array(ENVIRONMENT, array('development', 'testing', 'production'), TRUE), ENVIRONMENT);
		foreach (array('mysqli', 'mbstring', 'fileinfo', 'openssl', 'gd', 'sodium', 'zip', 'dom', 'intl') as $ext)
		{
			$check('Ekstensi '.$ext, extension_loaded($ext));
		}
		$version = $this->db->query('SELECT VERSION() AS v')->row();
		$check('Database terhubung', (bool) $version, $version ? $version->v.' / '.$this->db->database : '');
		$tz = $this->db->query('SELECT @@session.time_zone AS tz')->row();
		$check('Zona waktu sesi DB UTC', $tz && $tz->tz === '+00:00', $tz ? $tz->tz : '');
		$check('Tabel migrasi', $this->db->table_exists('migrations'), $this->db->table_exists('migrations') ? 'versi '.(int) $this->db->get('migrations')->row('version') : 'jalankan tools migrate');

		foreach (array('APP_ENCRYPTION_KEY', 'APP_MFA_KEY', 'APP_TOKEN_PEPPER', 'APP_RATE_LIMIT_KEY', 'APP_CI_ENCRYPTION_KEY') as $key)
		{
			$raw = base64_decode((string) app_env($key, ''), TRUE);
			$check('Key '.$key, $raw !== FALSE && strlen($raw) >= 32, 'nilai tidak ditampilkan');
		}
		$private = $this->storage_private_path();
		$check('Storage privat dapat ditulis', $private !== NULL && is_dir($private) && is_writable($private), $private !== NULL ? 'di luar public: '.(strpos(realpath($private), realpath(FCPATH)) === FALSE ? 'ya' : 'TIDAK') : 'belum dikonfigurasi');
		foreach (array('storage/logs', 'storage/cache', 'storage/tmp') as $dir)
		{
			$check('Folder '.$dir.' dapat ditulis', is_writable(ROOTPATH.$dir));
		}
		$check('Upload PHP ≥ 5M', $this->ini_bytes(ini_get('upload_max_filesize')) >= 5 * 1048576, ini_get('upload_max_filesize'));
		$check('post_max_size ≥ 20M', $this->ini_bytes(ini_get('post_max_size')) >= 20 * 1048576, ini_get('post_max_size'));
		$check('SMTP', TRUE, $this->mailer->enabled() ? 'aktif' : 'tidak dikonfigurasi (notifikasi dalam aplikasi tetap berjalan)');
		$check('COOKIE_SECURE sesuai', ENVIRONMENT !== 'production' OR app_env_bool('COOKIE_SECURE'), app_env_bool('COOKIE_SECURE') ? 'true' : 'false');

		if ($this->db->table_exists('notification_outbox'))
		{
			$failed = $this->db->where('status', 'failed')->count_all_results('notification_outbox');
			$check('Outbox gagal', TRUE, $failed.' pesan');
		}
		$this->line($ok ? 'Status: SEHAT' : 'Status: ADA MASALAH');
		exit($ok ? EXIT_SUCCESS : EXIT_ERROR);
	}

	protected function ini_bytes($value)
	{
		$value = trim((string) $value);
		$unit = strtolower(substr($value, -1));
		$n = (int) $value;
		return ($unit === 'g') ? $n * 1073741824 : (($unit === 'm') ? $n * 1048576 : (($unit === 'k') ? $n * 1024 : $n));
	}

	protected function storage_private_path()
	{
		$path = trim((string) app_env('STORAGE_PRIVATE_PATH', ''));
		return ($path === '') ? NULL : rtrim(str_replace('\\', '/', $path), '/');
	}

	/**
	 * Isi key kosong pada file .env aktif dengan nilai acak. Nilai tidak dicetak.
	 */
	public function generate_keys()
	{
		$file = (getenv('APP_ENV') === 'testing') ? ROOTPATH.'.env.testing' : ROOTPATH.'.env';
		if ( ! is_file($file))
		{
			$this->error('File '.basename($file).' tidak ditemukan. Salin .env.example terlebih dahulu.');
			exit(EXIT_ERROR);
		}
		$content = file_get_contents($file);
		$generated = array();
		foreach (array('APP_ENCRYPTION_KEY' => 32, 'APP_MFA_KEY' => 32, 'APP_TOKEN_PEPPER' => 32, 'APP_RATE_LIMIT_KEY' => 32, 'APP_CI_ENCRYPTION_KEY' => 32) as $key => $bytes)
		{
			if (preg_match('/^'.$key.'=(.*)$/m', $content, $m) && trim($m[1]) !== '')
			{
				continue;
			}
			$value = base64_encode(random_bytes($bytes));
			if (preg_match('/^'.$key.'=.*$/m', $content))
			{
				$content = preg_replace('/^'.$key.'=.*$/m', $key.'='.$value, $content);
			}
			else
			{
				$content .= PHP_EOL.$key.'='.$value;
			}
			$generated[] = $key;
		}
		file_put_contents($file, $content, LOCK_EX);
		$this->line(empty($generated) ? 'Semua key sudah terisi; tidak ada yang diubah.' : 'Key dibuat (nilai tidak ditampilkan): '.implode(', ', $generated));
		$this->line('Simpan salinan .env di lokasi backup terpisah dengan akses terbatas.');
	}

	/** Jalankan migration menggunakan kredensial DDL (grup database "migrate"). */
	public function migrate($target = NULL)
	{
		$this->db = $this->load->database('migrate', TRUE);
		$this->db->query("SET time_zone = '+00:00'");
		$this->load->library('migration');
		$result = ($target === NULL) ? $this->migration->latest() : $this->migration->version((int) $target);
		if ($result === FALSE)
		{
			$this->error('Migration gagal: '.strip_tags($this->migration->error_string()));
			$error = $this->db->error();
			if ( ! empty($error['message']))
			{
				$this->error('DB: '.$error['message']);
			}
			exit(EXIT_ERROR);
		}
		$this->line('Migration selesai. Versi skema: '.(int) $this->db->get('migrations')->row('version'));
	}

	public function seed()
	{
		require_once ROOTPATH.'database/seeds/MasterSeeder.php';
		$seeder = new MasterSeeder($this);
		$seeder->run();
	}

	public function seed_demo()
	{
		if (ENVIRONMENT === 'production')
		{
			$this->error('Seed demo hanya untuk development/testing.');
			exit(EXIT_ERROR);
		}
		require_once ROOTPATH.'database/seeds/DemoSeeder.php';
		$seeder = new DemoSeeder($this);
		$seeder->run();
	}

	/** Buat Super Admin pertama secara interaktif. Tidak ada password bawaan. */
	public function create_admin()
	{
		$this->load->library('Totp', NULL, 'totp');
		$this->load->library('AuthService', NULL, 'auth');
		$this->load->model('User_model', 'user_model');

		if ($this->user_model->count_active_super_admins() > 0)
		{
			$this->line('Sudah ada Super Admin aktif. Perintah ini hanya untuk akun admin awal; kelola akun lain melalui dashboard.');
			exit(EXIT_SUCCESS);
		}

		$display_name = $this->ask('Nama tampilan');
		$username = $this->user_model->normalize_username($this->ask('Username (huruf kecil/angka/titik/garis bawah)'));
		$email = $this->ask('Email (opsional, kosongkan bila tidak ada)', '');
		$password = $this->ask_secret('Password (minimal 12 karakter, tidak ditampilkan)');
		$confirm = $this->ask_secret('Ulangi password');

		$errors = $this->auth->validate_account_fields(array(
			'display_name' => $display_name, 'username' => $username, 'email' => $email,
			'password' => $password, 'password_confirm' => $confirm,
		), TRUE);
		if ( ! empty($errors))
		{
			foreach ($errors as $message)
			{
				$this->error('- '.$message);
			}
			exit(EXIT_ERROR);
		}

		$user_id = db_transaction(function () use ($display_name, $username, $email, $password) {
			$id = $this->user_model->create(array(
				'public_id' => $this->crypto->public_id(),
				'username' => $username,
				'display_name' => $display_name,
				'email' => $this->user_model->normalize_email($email),
				'password_hash' => $this->auth->hash_password($password),
				'account_status' => 'active',
				'registration_channel' => 'cli',
			));
			$this->user_model->assign_role($id, 'super_admin', NULL);
			return $id;
		});
		unset($password, $confirm);
		$user = $this->user_model->find($user_id);
		$this->audit->log('account.super_admin_created_cli', 'user', $user->public_id, array('username' => $username));
		$this->line('Super Admin "'.$username.'" dibuat. Masuk melalui /masuk lalu aktifkan MFA di menu Akun.');
	}

	/**
	 * Job berkala: outbox, pengingat/eskalasi SLA, ekspor tertunda dan pembersihan.
	 * Menggunakan job_locks agar tidak berjalan ganda.
	 */
	public function run_jobs($only = NULL)
	{
		require_once APPPATH.'libraries/JobRunner.php';
		$runner = new JobRunner();
		$summary = $runner->run($only);
		foreach ($summary as $job => $result)
		{
			$this->line(str_pad($job, 22).' '.(is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : $result));
		}
	}

	public function import_sources($code = NULL)
	{
		$this->load->library('SourceImportService', NULL, 'source_import');
		$results = $this->source_import->import_reference_documents($code, NULL);
		foreach ($results as $source_code => $result)
		{
			$this->line($source_code.': '.$result);
		}
	}

	/**
	 * Impor register aset dari berkas CSV ke staging.
	 *
	 * Berkas S5 berformat .xlsx; proyek ini tidak memaketkan pembaca xlsx, jadi berkas itu
	 * harus diekspor ke CSV lebih dulu. Impor ulang berkas yang sama tidak menggandakan.
	 */
	public function import_assets($file = NULL, $source_code = 'S5')
	{
		// Berkas dicari di reference/imports/ supaya argumen CLI tidak perlu memuat garis miring.
		$dir = ROOTPATH.'reference/imports/';
		if ($file === NULL)
		{
			$this->error('Pemakaian: tools import_assets <berkas.csv> [kode_sumber]');
			$this->line('Berkas dibaca dari '.$dir);
			exit(EXIT_ERROR);
		}
		$path = $dir.basename($file);
		$source = $this->db->get_where('source_documents', array('source_code' => strtoupper($source_code)))->row();
		if ( ! $source)
		{
			$source = $this->db->order_by('id')->limit(1)->get('source_documents')->row();
		}
		if ( ! $source)
		{
			$this->error('Belum ada dokumen sumber terdaftar. Jalankan tools seed lebih dulu.');
			exit(EXIT_ERROR);
		}

		$this->load->library('AssetImportService', NULL, 'asset_import');
		$summary = $this->asset_import->import_csv($path, (int) $source->id, NULL);
		$this->line('Batch '.$summary['batch_id'].' pada sumber '.$source->source_code.':');
		foreach (array('valid', 'conflict', 'failed', 'empty', 'skipped') as $key)
		{
			$this->line('  '.str_pad($key, 10).' '.(int) $summary[$key]);
		}
		$this->line('Baris staging menunggu review di Aset > impor sebelum dijadikan register.');
	}

	/**
	 * Impor media dari `reference/media/<folder>/` memakai manifest.csv.
	 *
	 * Kolom manifest: filename, alt_text, caption, source_credit, source_year,
	 * license_note, rights_status, people_shown. Idempotent lewat checksum berkas asli.
	 */
	public function import_media($folder = NULL)
	{
		$base = ROOTPATH.'reference/media/';
		if ($folder === NULL)
		{
			$this->error('Pemakaian: tools import_media <folder>');
			$this->line('Folder dibaca dari '.$base.' dan wajib memuat manifest.csv');
			exit(EXIT_ERROR);
		}
		$dir = $base.basename($folder).'/';
		$manifest = $dir.'manifest.csv';
		if ( ! is_file($manifest))
		{
			$this->error('Manifest tidak ditemukan: '.$manifest);
			exit(EXIT_ERROR);
		}

		$this->load->library('UploadService', NULL, 'uploads');
		$handle = fopen($manifest, 'r');
		$header = fgetcsv($handle);
		if ( ! $header)
		{
			$this->error('Manifest kosong.');
			exit(EXIT_ERROR);
		}
		$header = array_map(function ($h) { return strtolower(trim((string) $h)); }, $header);

		$imported = 0; $skipped = 0; $failed = 0;
		while (($row = fgetcsv($handle)) !== FALSE)
		{
			if (count(array_filter($row, function ($c) { return trim((string) $c) !== ''; })) === 0)
			{
				continue;
			}
			$data = array();
			foreach ($header as $i => $key)
			{
				$data[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
			}
			$name = basename((string) ($data['filename'] ?? ''));
			$path = $dir.$name;
			if ($name === '' OR ! is_file($path))
			{
				$this->line('  '.str_pad('GAGAL', 8).$name.' — berkas tidak ada');
				$failed++;
				continue;
			}

			// Berkas yang isinya sudah pernah masuk tidak diimpor dua kali.
			$checksum = hash_file('sha256', $path);
			$existing = $this->db->select('pf.id')->from('private_files pf')
				->where('pf.checksum', $checksum)->where('pf.purpose', 'media_original')
				->limit(1)->get()->row();
			if ($existing)
			{
				$skipped++;
				continue;
			}

			try
			{
				$media_id = $this->uploads->store_media_file($path, $name, array(
					'alt_text' => $data['alt_text'] ?? '',
					'caption' => $data['caption'] ?? '',
					'source_credit' => $data['source_credit'] ?? '',
					'source_year' => ($data['source_year'] ?? '') !== '' ? (int) $data['source_year'] : NULL,
					'license_note' => $data['license_note'] ?? '',
					'rights_status' => $data['rights_status'] ?? 'unknown',
					'people_shown' => $data['people_shown'] ?? '',
					'is_placeholder' => ! empty($data['is_placeholder']) ? 1 : 0,
				), NULL);
				$this->uploads->commit_staged();
				$this->line('  '.str_pad('OK', 8).str_pad($name, 42).'media #'.$media_id);
				$imported++;
			}
			catch (Exception $e)
			{
				$this->uploads->discard_staged();
				$this->line('  '.str_pad('GAGAL', 8).str_pad($name, 42).$e->getMessage());
				$failed++;
			}
		}
		fclose($handle);
		$this->line('Selesai: '.$imported.' diimpor, '.$skipped.' dilewati (sudah ada), '.$failed.' gagal.');
	}

	/** Hapus data demo (development/testing). */	public function purge_demo()
	{
		if (ENVIRONMENT === 'production')
		{
			$this->error('Tidak tersedia pada production.');
			exit(EXIT_ERROR);
		}
		require_once ROOTPATH.'database/seeds/DemoSeeder.php';
		$seeder = new DemoSeeder($this);
		$seeder->purge();
	}
}
