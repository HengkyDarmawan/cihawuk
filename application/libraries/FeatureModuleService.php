<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Status modul aplikasi (modul-backend §6).
 *
 * - disabled        : route publik dan backend modul tidak tersedia
 * - internal_only   : tersedia di backend, tidak punya halaman publik
 * - public_readonly : data terbit dapat dibaca publik, aksi interaktif dimatikan
 * - active          : backend dan halaman publik tersedia
 * - maintenance     : perubahan dibatasi; publik menerima halaman pemeliharaan modul
 *
 * Menonaktifkan modul tidak menghapus data, berkas, histori, permission atau audit log.
 */
class FeatureModuleService {

	const STATES = array('disabled', 'internal_only', 'public_readonly', 'active', 'maintenance');

	/** State yang dianggap "hidup" sehingga dependensinya harus ikut hidup. */
	const ENABLED_STATES = array('internal_only', 'public_readonly', 'active', 'maintenance');

	/** @var CI_Controller */
	protected $CI;

	/** @var array|null cache baris modul per request */
	protected $cache = NULL;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function state_labels()
	{
		return array(
			'disabled' => 'Nonaktif',
			'internal_only' => 'Hanya internal',
			'public_readonly' => 'Publik baca saja',
			'active' => 'Aktif',
			'maintenance' => 'Pemeliharaan',
		);
	}

	/** @return array<string,object> modul terindeks berdasarkan code */
	public function all()
	{
		if ($this->cache === NULL)
		{
			$this->cache = array();
			foreach ($this->CI->db->order_by('sort_order', 'ASC')->get('feature_modules')->result() as $row)
			{
				$row->depends_on = json_decode((string) $row->depends_on_json, TRUE) ?: array();
				$this->cache[$row->code] = $row;
			}
		}
		return $this->cache;
	}

	public function flush()
	{
		$this->cache = NULL;
	}

	public function module($code)
	{
		$all = $this->all();
		return isset($all[$code]) ? $all[$code] : NULL;
	}

	/**
	 * Pemutus darurat dari environment (master §25.2). Nilai `false` memaksa modul nonaktif;
	 * nilai `true` tidak memaksa aktif karena status sebenarnya dikelola di database.
	 */
	protected $env_kill_switch = array(
		'assets' => 'FEATURE_ASSET_MANAGEMENT',
		'public_asset_qr' => 'FEATURE_PUBLIC_ASSET_QR',
		'warehouse' => 'FEATURE_WAREHOUSE',
		'organization' => 'FEATURE_ORGANIZATION_TREE',
		'village_letters' => 'FEATURE_LETTERS',
	);

	/** Modul yang tidak terdaftar diperlakukan sebagai nonaktif (deny by default). */
	public function state($code)
	{
		$module = $this->module($code);
		if ( ! $module)
		{
			return 'disabled';
		}
		if (isset($this->env_kill_switch[$code]) && ! app_env_bool($this->env_kill_switch[$code], TRUE))
		{
			return 'disabled';
		}
		return $module->state;
	}

	public function is_enabled($code)
	{
		return in_array($this->state($code), self::ENABLED_STATES, TRUE);
	}

	/** Modul dapat dibuka dari dashboard pengelola. */
	public function backend_available($code)
	{
		return $this->is_enabled($code);
	}

	/** available | maintenance | unavailable */
	public function public_status($code)
	{
		$state = $this->state($code);
		if ($state === 'maintenance')
		{
			return 'maintenance';
		}
		return in_array($state, array('active', 'public_readonly'), TRUE) ? 'available' : 'unavailable';
	}

	/** Modul `public_readonly` tetap menolak aksi tulis dari publik. */
	public function public_writes_allowed($code)
	{
		return $this->state($code) === 'active';
	}

	/**
	 * Cari modul yang memiliki route prefix paling panjang yang cocok dengan URI.
	 * @return array{0:object,1:string}|null [modul, area] dengan area admin|public
	 */
	public function match_route($uri)
	{
		$uri = trim((string) $uri, '/');
		$best = NULL;
		$best_len = -1;
		foreach ($this->all() as $module)
		{
			// Satu modul dapat memiliki beberapa prefix, dipisah koma (mis. "lapor,lacak").
			foreach (array('admin' => $module->admin_route_prefix, 'public' => $module->public_route_prefix) as $area => $prefixes)
			{
				foreach (explode(',', (string) $prefixes) as $prefix)
				{
					$prefix = trim($prefix, " \t/");
					if ($prefix === '')
					{
						continue;
					}
					if (($uri === $prefix || strpos($uri, $prefix.'/') === 0) && strlen($prefix) > $best_len)
					{
						$best = array($module, $area);
						$best_len = strlen($prefix);
					}
				}
			}
		}
		return $best;
	}

	/**
	 * Ubah state modul beserta alasan. Mengembalikan state sebelumnya.
	 * @throws DomainRuleException
	 */
	public function set_state($code, $state, $reason, $actor_user_id)
	{
		$module = $this->module($code);
		if ( ! $module)
		{
			throw new DomainRuleException('Modul tidak dikenal.', 404);
		}
		if ( ! in_array($state, self::STATES, TRUE))
		{
			throw new DomainRuleException('Status modul tidak valid.', 422, array('state' => 'Pilih status yang tersedia.'));
		}
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan perubahan status modul (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		if ($module->state === $state)
		{
			throw new DomainRuleException('Modul sudah berstatus '.$this->state_labels()[$state].'.', 409);
		}

		$enabling = in_array($state, self::ENABLED_STATES, TRUE);
		if ($enabling)
		{
			foreach ($module->depends_on as $dependency)
			{
				if ( ! $this->is_enabled($dependency))
				{
					$name = $this->module($dependency) ? $this->module($dependency)->name : $dependency;
					throw new DomainRuleException('Modul "'.$module->name.'" membutuhkan modul "'.$name.'" aktif terlebih dahulu.', 409);
				}
			}
		}
		else
		{
			$blockers = array();
			foreach ($this->all() as $other)
			{
				if ($other->code !== $code && in_array($code, $other->depends_on, TRUE) && $this->is_enabled($other->code))
				{
					$blockers[] = $other->name;
				}
			}
			if ($blockers)
			{
				throw new DomainRuleException('Nonaktifkan dahulu modul yang bergantung padanya: '.implode(', ', $blockers).'.', 409);
			}
		}

		$from = $module->state;
		$now = utc_now();
		db_transaction(function () use ($module, $state, $from, $reason, $actor_user_id, $now) {
			db_must($this->CI->db->where('id', (int) $module->id)->update('feature_modules', array(
				'state' => $state,
				'effective_from' => $now,
				'updated_by' => $actor_user_id ? (int) $actor_user_id : NULL,
				'updated_at' => $now,
			)), 'feature_modules.update');
			db_must($this->CI->db->insert('feature_module_histories', array(
				'module_code' => $module->code,
				'from_state' => $from,
				'to_state' => $state,
				'reason' => mb_substr($reason, 0, 500),
				'actor_user_id' => $actor_user_id ? (int) $actor_user_id : NULL,
				'effective_at' => $now,
				'created_at' => $now,
			)), 'feature_module_histories.insert');
		});

		$this->flush();
		$this->CI->load->library('AuditService', NULL, 'audit');
		$this->CI->audit->log('module.state_changed', 'feature_module', $module->code, array(
			'from' => $from, 'to' => $state, 'reason' => mb_substr($reason, 0, 200),
		), $actor_user_id ? (int) $actor_user_id : NULL, $module->code);
		$this->CI->audit->event('warning', 'Status modul '.$module->name.' diubah menjadi '.$this->state_labels()[$state].'.', $module->code, array(
			'from' => $from, 'to' => $state,
		), $actor_user_id ? (int) $actor_user_id : NULL);

		return $from;
	}

	public function history($code, $limit = 20)
	{
		return $this->CI->db->where('module_code', (string) $code)
			->order_by('id', 'DESC')->limit((int) $limit)->get('feature_module_histories')->result();
	}
}
