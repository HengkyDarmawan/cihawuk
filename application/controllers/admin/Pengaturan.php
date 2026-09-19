<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengaturan aplikasi: umum, kalender kerja, kebijakan SLA, kategori layanan,
 * dan informasi sistem. Rahasia (key, kredensial SMTP/DB) tidak pernah ditampilkan.
 */
class Pengaturan extends Admin_Controller {

	protected $sections = array(
		'umum' => 'Umum &amp; situs',
		'modul' => 'Modul aplikasi',
		'kalender' => 'Kalender kerja &amp; hari libur',
		'sla' => 'Kebijakan target layanan',
		'kategori' => 'Kategori layanan',
		'pemeliharaan' => 'Pemeliharaan',
		'sistem' => 'Informasi sistem',
	);

	public function __construct()
	{
		parent::__construct();
		$this->layout_data['nav_active'] = 'pengaturan';
	}

	public function index()
	{
		$this->require_permission('settings.manage');
		redirect(site_url('admin/pengaturan/umum'), 'location', 303);
	}

	public function section($section)
	{
		$this->require_permission($section === 'modul' ? 'settings.feature.manage' : 'settings.manage');
		if ( ! isset($this->sections[$section]))
		{
			$this->not_found_response();
			return;
		}
		$this->layout_data['nav_active'] = ($section === 'modul') ? 'modul' : 'pengaturan';
		$this->render('admin/pengaturan', $this->section_data($section), 'dashboard');
	}

	protected function section_data($section, array $extra = array())
	{
		$data = array(
			'page_title' => 'Pengaturan',
			'sections' => $this->sections,
			'section' => $section,
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		);
		switch ($section)
		{
			case 'umum':
				$data['values'] = array(
					'tagline' => (string) $this->settings->get('site.tagline', ''),
					'anonymous_enabled' => (bool) $this->settings->get('tickets.anonymous_enabled', TRUE),
					'hero_mode' => (string) $this->settings->get('home.hero_mode', 'three'),
					'default_sla' => (string) $this->settings->get('tickets.default_sla_policy', 'DEFAULT'),
				);
				$data['sla_codes'] = array();
				foreach ($this->db->where('active', 1)->get('sla_policies')->result() as $policy)
				{
					$data['sla_codes'][$policy->code] = $policy->name;
				}
				break;

			case 'modul':
				$this->load->library('FeatureModuleService', NULL, 'modules');
				$data['modules'] = array_values($this->modules->all());
				$data['module_states'] = $this->modules->state_labels();
				$data['module_history'] = $this->db->select('h.*, u.display_name AS actor_name')
					->from('feature_module_histories h')
					->join('users u', 'u.id = h.actor_user_id', 'left')
					->order_by('h.id', 'DESC')->limit(15)->get()->result();
				break;

			case 'kalender':
				$data['calendars'] = $this->db->order_by('id')->get('business_calendars')->result();
				foreach ($data['calendars'] as $calendar)
				{
					$calendar->schedule = json_decode($calendar->weekly_schedule_json, TRUE) ?: array();
					$calendar->holidays = $this->db->where('calendar_id', (int) $calendar->id)->order_by('date')->get('business_holidays')->result();
				}
				$data['days'] = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');
				break;

			case 'sla':
				$data['policies'] = $this->db->order_by('code')->get('sla_policies')->result();
				$data['calendars'] = $this->db->order_by('id')->get('business_calendars')->result();
				break;

			case 'kategori':
				$data['categories'] = $this->db->select('c.*, o.name AS unit_name')->from('ticket_categories c')
					->join('organizational_units o', 'o.id = c.default_unit_id', 'left')
					->order_by('c.sort_order')->get()->result();
				$data['units'] = array();
				foreach ($this->db->where('active', 1)->order_by('name')->get('organizational_units')->result() as $unit)
				{
					$data['units'][(string) $unit->id] = $unit->name;
				}
				$data['sla_options'] = array();
				foreach ($this->db->where('active', 1)->get('sla_policies')->result() as $policy)
				{
					$data['sla_options'][(string) $policy->id] = $policy->name;
				}
				break;

			case 'pemeliharaan':
				$data['maintenance'] = $this->maintenance_snapshot();
				break;

			case 'sistem':
				$data['system'] = array(
					'php' => PHP_VERSION,
					'environment' => ENVIRONMENT,
					'database' => $this->db->query('SELECT VERSION() AS v')->row('v'),
					'ci_version' => CI_VERSION,
					'timezone' => (string) app_env('APP_TIMEZONE', 'Asia/Jakarta'),
					'mail' => $this->mailer->enabled() ? 'SMTP aktif' : 'tidak dikonfigurasi',
					'upload_max' => ini_get('upload_max_filesize'),
					'post_max' => ini_get('post_max_size'),
					'features' => $this->config->item('features', 'app'),
					'schema_version' => (int) ($this->db->table_exists('migrations') ? $this->db->get('migrations')->row('version') : 0),
				);
				$data['jobs'] = $this->db->order_by('job_key')->get('job_locks')->result();
				$data['outbox'] = $this->db->select('status, COUNT(*) AS total')->group_by('status')->get('notification_outbox')->result();
				break;
		}
		return array_merge($data, $extra);
	}

	/**
	 * Ringkasan pemeliharaan (modul-backend 25): antrean, cache, penyimpanan, modul,
	 * pekerjaan yang menunggu verifikasi, dan hasil pemeriksa tautan.
	 */
	protected function maintenance_snapshot()
	{
		$this->load->library('FeatureModuleService', NULL, 'modules');
		$this->load->library('PublicCache', NULL, 'public_cache');
		$this->load->library('LinkCheckService', NULL, 'link_check');

		$disabled = array();
		foreach ($this->modules->all() as $module)
		{
			if ($module->state !== 'active')
			{
				$disabled[] = $module->name.' ('.$module->state.')';
			}
		}

		$pending = array(
			'Dataset belum terbit' => (int) $this->db->where_in('status', array('draft', 'in_review'))->count_all_results('datasets'),
			'Blok profil belum diverifikasi' => (int) $this->db->where('verification_status', 'unverified')->count_all_results('profile_blocks'),
			'Fasilitas belum terbit' => (int) $this->db->where('publication_status', 'draft')->where('archived_at IS NULL', NULL, FALSE)->count_all_results('facilities'),
			'Tahun anggaran belum terbit' => (int) $this->db->where_in('status', array('draft', 'reconciling', 'verified', 'approved'))->count_all_results('budget_years'),
			'Baris impor aset menunggu review' => (int) $this->db->where_in('validation_status', array('pending', 'conflict'))->count_all_results('asset_import_rows'),
		);

		$without_qr = (int) $this->db->query('SELECT COUNT(*) AS total FROM asset_units u
			WHERE u.lifecycle_status = "active" AND NOT EXISTS (
				SELECT 1 FROM asset_qr_tokens q WHERE q.asset_unit_id = u.id AND q.status = "active")')->row('total');

		$this->load->library('WarehouseService', NULL, 'warehouse');
		$low_stock = array();
		foreach ($this->warehouse->below_minimum() as $row)
		{
			$low_stock[] = $row->name;
		}

		return array(
			'jobs' => $this->db->order_by('job_key')->get('job_locks')->result(),
			'outbox_failed' => (int) $this->db->where('status', 'failed')->count_all_results('notification_outbox'),
			'exports_pending' => (int) $this->db->where_in('status', array('queued', 'running'))->count_all_results('export_jobs'),
			'cache_enabled' => $this->public_cache->enabled(),
			'cache_last_invalidation' => $this->public_cache->last_invalidation(),
			'storage' => $this->storage_sizes(),
			'disabled_modules' => $disabled,
			'pending' => $pending,
			'assets_without_qr' => $without_qr,
			'low_stock' => $low_stock,
			'link_check_last' => $this->link_check->last_run(),
			'broken_links' => $this->link_check->results('broken', 50),
		);
	}

	/** Ukuran direktori penyimpanan; dihitung dangkal supaya murah. */
	protected function storage_sizes()
	{
		$out = array();
		foreach (array('storage/private', 'storage/logs', 'storage/cache', 'public/media') as $relative)
		{
			$path = ROOTPATH.$relative;
			$bytes = 0;
			if (is_dir($path))
			{
				$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
				foreach ($iterator as $file)
				{
					if ($file->isFile())
					{
						$bytes += $file->getSize();
					}
				}
			}
			$out[$relative] = $bytes;
		}
		return $out;
	}

	public function save($section)
	{
		$this->require_method('post');
		$this->require_permission($section === 'modul' ? 'settings.feature.manage' : 'settings.manage');
		if ( ! isset($this->sections[$section]))
		{
			$this->not_found_response();
			return;
		}
		$method = 'save_'.$section;
		if ( ! method_exists($this, $method))
		{
			throw new DomainRuleException('Bagian pengaturan ini tidak dapat disimpan.', 409);
		}
		$this->{$method}();
		$this->audit->log('settings.saved', 'setting', $section, array());
		$this->flash('success', 'Pengaturan disimpan.');
		redirect(site_url('admin/pengaturan/'.$section), 'location', 303);
	}

	protected function save_umum()
	{
		$this->settings->set('site.tagline', $this->post_string('tagline', 150), 'site', TRUE, (int) $this->user->id);
		$this->settings->set('tickets.anonymous_enabled', (bool) $this->input->post('anonymous_enabled'), 'tickets', TRUE, (int) $this->user->id);
		$hero = (string) $this->input->post('hero_mode');
		if (in_array($hero, array('image', 'video', 'three'), TRUE))
		{
			$this->settings->set('home.hero_mode', $hero, 'home', TRUE, (int) $this->user->id);
		}
		$policy = (string) $this->input->post('default_sla');
		if ($this->db->where(array('code' => $policy, 'active' => 1))->count_all_results('sla_policies') > 0)
		{
			$this->settings->set('tickets.default_sla_policy', $policy, 'tickets', FALSE, (int) $this->user->id);
		}
		$this->load->library('ContentService', NULL, 'content_service');
		$this->content_service->invalidate_cache();
	}

	protected function save_modul()
	{
		$this->load->library('FeatureModuleService', NULL, 'modules');
		$this->modules->set_state(
			(string) $this->input->post('code'),
			(string) $this->input->post('state'),
			$this->post_string('reason', 500),
			(int) $this->user->id
		);
	}

	protected function save_kalender()
	{
		$calendar_id = (int) $this->input->post('calendar_id');
		$calendar = $this->db->get_where('business_calendars', array('id' => $calendar_id))->row();
		if ( ! $calendar)
		{
			throw new DomainRuleException('Kalender tidak ditemukan.', 404);
		}
		$action = (string) $this->input->post('action');

		if ($action === 'holiday_add')
		{
			$date = (string) $this->input->post('date');
			$label = $this->post_string('label', 150);
			if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) OR trim($label) === '')
			{
				throw new DomainRuleException('Tanggal dan nama hari libur wajib diisi.', 422);
			}
			db_must($this->db->query(
				'INSERT INTO business_holidays (calendar_id, date, label, is_working_override, created_at) VALUES (?, ?, ?, ?, ?)
				 ON DUPLICATE KEY UPDATE label = VALUES(label), is_working_override = VALUES(is_working_override)',
				array($calendar_id, $date, $label, $this->input->post('is_working_override') ? 1 : 0, utc_now())
			), 'business_holidays.add');
			return;
		}
		if ($action === 'holiday_remove')
		{
			$this->db->delete('business_holidays', array('id' => (int) $this->input->post('holiday_id'), 'calendar_id' => $calendar_id));
			return;
		}

		// Simpan jadwal mingguan.
		$schedule = array();
		foreach (range(1, 7) as $day)
		{
			$start = (string) $this->input->post('start_'.$day);
			$end = (string) $this->input->post('end_'.$day);
			if ( ! $this->input->post('active_'.$day))
			{
				continue;
			}
			if ( ! preg_match('/^\d{2}:\d{2}$/', $start) OR ! preg_match('/^\d{2}:\d{2}$/', $end) OR $start >= $end)
			{
				throw new DomainRuleException('Jam kerja hari ke-'.$day.' tidak valid.', 422);
			}
			$schedule[(string) $day] = array(array('start' => $start, 'end' => $end));
		}
		db_must($this->db->where('id', $calendar_id)->update('business_calendars', array(
			'name' => $this->post_string('name', 100) ?: $calendar->name,
			'weekly_schedule_json' => json_encode($schedule),
			'is_example' => $this->input->post('is_example') ? 1 : 0,
			'updated_at' => utc_now(),
		)), 'business_calendars.update');
	}

	protected function save_sla()
	{
		$id = (int) $this->input->post('policy_id');
		$policy = $this->db->get_where('sla_policies', array('id' => $id))->row();
		if ( ! $policy)
		{
			throw new DomainRuleException('Kebijakan tidak ditemukan.', 404);
		}
		$fields = array('verification_days', 'first_response_days', 'confirmation_days');
		$data = array();
		foreach ($fields as $field)
		{
			$value = (int) $this->input->post($field);
			if ($value < 1 OR $value > 365)
			{
				throw new DomainRuleException('Nilai '.$field.' harus antara 1 dan 365 hari.', 422);
			}
			$data[$field] = $value;
		}
		$resolution = (string) $this->input->post('resolution_days');
		$data['resolution_days'] = ($resolution === '') ? NULL : max(1, min(365, (int) $resolution));
		$data['auto_close_enabled'] = $this->input->post('auto_close_enabled') ? 1 : 0;
		$data['pause_rules_json'] = json_encode(array(
			'pause_resolution_on_needs_information' => (bool) $this->input->post('pause_on_needs_information'),
		));
		$data['name'] = $this->post_string('name', 100) ?: $policy->name;
		$data['updated_at'] = utc_now();
		db_must($this->db->where('id', $id)->update('sla_policies', $data), 'sla_policies.update');
	}

	protected function save_kategori()
	{
		$action = (string) $this->input->post('action');
		if ($action === 'create')
		{
			$code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->input->post('code')));
			$name = $this->post_string('name', 100);
			if ($code === '' OR trim($name) === '')
			{
				throw new DomainRuleException('Kode dan nama kategori wajib diisi.', 422);
			}
			if ($this->db->where('code', $code)->count_all_results('ticket_categories') > 0)
			{
				throw new DomainRuleException('Kode kategori sudah dipakai.', 422);
			}
			$now = utc_now();
			db_must($this->db->insert('ticket_categories', array(
				'code' => $code,
				'name' => $name,
				'description' => $this->post_string('description', 255),
				'report_type' => in_array($this->input->post('report_type'), array_keys(app_config('report_types', array())), TRUE) ? $this->input->post('report_type') : NULL,
				'default_unit_id' => ((int) $this->input->post('default_unit_id')) ?: NULL,
				'is_sensitive' => $this->input->post('is_sensitive') ? 1 : 0,
				'location_required' => $this->input->post('location_required') ? 1 : 0,
				'sla_policy_id' => ((int) $this->input->post('sla_policy_id')) ?: NULL,
				'sort_order' => (int) $this->input->post('sort_order'),
				'active' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			)), 'ticket_categories.insert');
			return;
		}

		$id = (int) $this->input->post('category_id');
		if ($this->db->where('id', $id)->count_all_results('ticket_categories') === 0)
		{
			throw new DomainRuleException('Kategori tidak ditemukan.', 404);
		}
		db_must($this->db->where('id', $id)->update('ticket_categories', array(
			'name' => $this->post_string('name', 100),
			'description' => $this->post_string('description', 255),
			'default_unit_id' => ((int) $this->input->post('default_unit_id')) ?: NULL,
			'is_sensitive' => $this->input->post('is_sensitive') ? 1 : 0,
			'location_required' => $this->input->post('location_required') ? 1 : 0,
			'sla_policy_id' => ((int) $this->input->post('sla_policy_id')) ?: NULL,
			'sort_order' => (int) $this->input->post('sort_order'),
			'active' => $this->input->post('active') ? 1 : 0,
			'updated_at' => utc_now(),
		)), 'ticket_categories.update');
	}
}
