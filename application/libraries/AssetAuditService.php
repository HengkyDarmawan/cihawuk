<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit fisik aset (prompt-master 18.4.3).
 *
 * Alur: pengurus membuat draft dan menentukan scope -> verifikator menerbitkan sesi dan
 * daftar targetnya DIBEKUKAN sebagai snapshot -> auditor mencatat temuan -> sistem
 * menampilkan perbedaan tanpa menimpa master -> verifikator menerima atau menolak per
 * temuan -> perubahan master dibuat sebagai koreksi terpisah -> sesi ditutup dan laporannya
 * dibekukan; koreksi setelah penutupan memakai addendum.
 */
class AssetAuditService {

	/** @var CI_Controller */
	protected $CI;

	const EXISTENCE = array(
		'match' => 'Sesuai',
		'not_found' => 'Tidak ditemukan',
		'moved' => 'Berpindah',
		'unlabeled' => 'Belum berlabel',
		'label_unreadable' => 'Label tidak terbaca',
		'data_differs' => 'Data berbeda',
		'new_asset' => 'Aset baru belum terdaftar',
	);

	const SESSION_STATUSES = array(
		'draft' => 'Draft',
		'published' => 'Berjalan',
		'closed' => 'Ditutup',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('AssetService', NULL, 'assets');
	}

	public function sessions($limit = 50)
	{
		return $this->CI->db->order_by('id', 'DESC')->limit((int) $limit)->get('asset_audit_sessions')->result();
	}

	public function session($public_id)
	{
		return $this->CI->db->get_where('asset_audit_sessions', array('public_id' => (string) $public_id))->row();
	}

	public function create_session(array $input, $user_id)
	{
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 220);
		if ($name === '')
		{
			throw new DomainRuleException('Nama sesi audit wajib diisi.', 422, array('name' => 'Wajib diisi.'));
		}
		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('asset_audit_sessions', array(
			'public_id' => $public_id,
			'name' => $name,
			'scope_snapshot_json' => json_encode(array(
				'category_id' => empty($input['category_id']) ? NULL : (int) $input['category_id'],
				'location_id' => empty($input['location_id']) ? NULL : (int) $input['location_id'],
			)),
			'starts_at' => $now,
			'status' => 'draft',
			'created_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'asset_audit_sessions.insert');
		$this->CI->audit->log('asset_audits.created', 'asset_audit_session', $public_id,
			array('name' => $name), FALSE, 'assets');
		return $this->session($public_id);
	}

	/**
	 * Menerbitkan sesi membekukan daftar target beserta snapshot kondisi saat itu.
	 * Perubahan master sesudahnya tidak mengubah snapshot ini.
	 */
	public function publish_session($session, $user_id)
	{
		if ($session->status !== 'draft')
		{
			throw new DomainRuleException('Sesi audit itu sudah diterbitkan.', 409);
		}
		$scope = json_decode((string) $session->scope_snapshot_json, TRUE) ?: array();
		$this->CI->db->select('u.*')->from('asset_units u')->join('asset_registers r', 'r.id = u.register_id');
		if ( ! empty($scope['category_id']))
		{
			$this->CI->db->where('r.category_id', (int) $scope['category_id']);
		}
		if ( ! empty($scope['location_id']))
		{
			$this->CI->db->where('u.location_id', (int) $scope['location_id']);
		}
		$units = $this->CI->db->where_in('u.lifecycle_status', array('active', 'in_maintenance'))->get()->result();
		if (empty($units))
		{
			throw new DomainRuleException('Tidak ada unit aktif pada scope itu.', 409);
		}

		$now = utc_now();
		db_transaction(function () use ($session, $units, $user_id, $now) {
			foreach ($units as $unit)
			{
				db_must($this->CI->db->insert('asset_audit_targets', array(
					'audit_session_id' => (int) $session->id,
					'asset_unit_id' => (int) $unit->id,
					'expected_location_id' => $unit->location_id ? (int) $unit->location_id : NULL,
					'expected_condition' => $unit->condition_status,
					'expected_lifecycle' => $unit->lifecycle_status,
					'asset_snapshot_json' => json_encode(array(
						'asset_tag' => $unit->asset_tag,
						'brand' => $unit->brand,
						'model' => $unit->model,
						'location_id' => $unit->location_id ? (int) $unit->location_id : NULL,
						'condition_status' => $unit->condition_status,
						'lifecycle_status' => $unit->lifecycle_status,
						'version' => (int) $unit->version,
					), JSON_UNESCAPED_UNICODE),
					'created_at' => $now,
				)), 'asset_audit_targets.insert');
			}
			db_must($this->CI->db->where('id', (int) $session->id)->update('asset_audit_sessions', array(
				'status' => 'published', 'published_by' => $user_id ? (int) $user_id : NULL, 'updated_at' => $now,
			)), 'asset_audit_sessions.publish');
		});

		$this->CI->audit->log('asset_audits.published', 'asset_audit_session', $session->public_id,
			array('targets' => count($units)), FALSE, 'assets');
		return count($units);
	}

	public function targets($session)
	{
		return $this->CI->db->select('t.*, u.asset_tag, u.public_id AS unit_public_id, l.name AS expected_location')
			->from('asset_audit_targets t')
			->join('asset_units u', 'u.id = t.asset_unit_id')
			->join('asset_locations l', 'l.id = t.expected_location_id', 'left')
			->where('t.audit_session_id', (int) $session->id)
			->order_by('u.asset_tag')->get()->result();
	}

	public function findings($session)
	{
		return $this->CI->db->select('f.*, t.asset_unit_id, t.expected_condition, t.expected_location_id, u.asset_tag')
			->from('asset_audit_findings f')
			->join('asset_audit_targets t', 't.id = f.audit_target_id')
			->join('asset_units u', 'u.id = t.asset_unit_id')
			->where('t.audit_session_id', (int) $session->id)
			->order_by('f.id', 'DESC')->get()->result();
	}

	public function finding($public_id)
	{
		return $this->CI->db->get_where('asset_audit_findings', array('public_id' => (string) $public_id))->row();
	}

	/**
	 * Catat temuan auditor. Temuan TIDAK menimpa master; perbedaannya dihitung terpisah
	 * lewat `differences()` dan baru berlaku setelah diverifikasi lalu dikoreksi.
	 */
	public function record_finding($session, array $input, $user_id)
	{
		if ($session->status !== 'published')
		{
			throw new DomainRuleException('Sesi audit belum berjalan atau sudah ditutup.', 409);
		}
		$target = $this->CI->db->get_where('asset_audit_targets', array(
			'id' => (int) ($input['target_id'] ?? 0), 'audit_session_id' => (int) $session->id,
		))->row();
		if ( ! $target)
		{
			throw new DomainRuleException('Target audit tidak ditemukan pada sesi ini.', 404);
		}
		$existence = (string) ($input['existence_result'] ?? '');
		$condition = (string) ($input['observed_condition'] ?? '');
		if ( ! isset(self::EXISTENCE[$existence]))
		{
			throw new DomainRuleException('Hasil keberadaan tidak dikenal.', 422, array('existence_result' => 'Tidak dikenal.'));
		}
		if ( ! isset(AssetService::CONDITIONS[$condition]))
		{
			throw new DomainRuleException('Kondisi hasil pemeriksaan tidak dikenal.', 422,
				array('observed_condition' => 'Tidak dikenal.'));
		}

		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('asset_audit_findings', array(
			'public_id' => $public_id,
			'audit_target_id' => (int) $target->id,
			'auditor_id' => $user_id ? (int) $user_id : NULL,
			'existence_result' => $existence,
			'observed_condition' => $condition,
			'observed_location_id' => empty($input['observed_location_id']) ? NULL : (int) $input['observed_location_id'],
			'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 600) ?: NULL,
			'status' => 'submitted',
			'submitted_at' => $now,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'asset_audit_findings.insert');
		$this->CI->audit->log('asset_audits.finding_recorded', 'asset_audit_finding', $public_id,
			array('existence' => $existence), FALSE, 'assets');
		return $this->finding($public_id);
	}

	/** Perbedaan antara temuan dan snapshot target; hanya informasi, bukan perubahan. */
	public function differences($session)
	{
		$out = array();
		foreach ($this->findings($session) as $finding)
		{
			$diff = array();
			if ($finding->observed_condition !== $finding->expected_condition)
			{
				$diff['kondisi'] = array('diharapkan' => $finding->expected_condition, 'ditemukan' => $finding->observed_condition);
			}
			if ($finding->observed_location_id !== NULL
				&& (int) $finding->observed_location_id !== (int) $finding->expected_location_id)
			{
				$diff['lokasi'] = array('diharapkan' => (int) $finding->expected_location_id, 'ditemukan' => (int) $finding->observed_location_id);
			}
			if ($finding->existence_result !== 'match')
			{
				$diff['keberadaan'] = self::EXISTENCE[$finding->existence_result] ?? $finding->existence_result;
			}
			if ($diff)
			{
				$out[] = array('finding' => $finding, 'diff' => $diff);
			}
		}
		return $out;
	}

	/**
	 * Verifikator menerima atau menolak satu temuan. Menerima TIDAK langsung menimpa master;
	 * koreksi master dibuat terpisah dan ber-histori lewat `apply_finding()`.
	 */
	public function verify_finding($finding, $accepted, $note, $user_id)
	{
		if ($finding->status !== 'submitted')
		{
			throw new DomainRuleException('Temuan itu sudah diproses.', 409);
		}
		db_must($this->CI->db->where('id', (int) $finding->id)->update('asset_audit_findings', array(
			'status' => $accepted ? 'verified' : 'rejected',
			'verified_by' => $user_id ? (int) $user_id : NULL,
			'verified_at' => utc_now(),
			'verification_note' => mb_substr(trim((string) $note), 0, 500) ?: NULL,
			'version' => (int) $finding->version + 1,
			'updated_at' => utc_now(),
		)), 'asset_audit_findings.verify');
		$this->CI->audit->log($accepted ? 'asset_audits.finding_verified' : 'asset_audits.finding_rejected',
			'asset_audit_finding', $finding->public_id, array('note' => mb_substr((string) $note, 0, 200)), FALSE, 'assets');
	}

	/** Koreksi master berdasarkan temuan yang sudah diverifikasi, dengan historinya sendiri. */
	public function apply_finding($finding, $user_id)
	{
		if ($finding->status !== 'verified')
		{
			throw new DomainRuleException('Hanya temuan yang sudah diverifikasi yang dapat dijadikan koreksi.', 409);
		}
		$target = $this->CI->db->get_where('asset_audit_targets', array('id' => (int) $finding->audit_target_id))->row();
		$unit = $this->CI->db->get_where('asset_units', array('id' => (int) $target->asset_unit_id))->row();

		$lifecycle = ($finding->existence_result === 'not_found') ? 'lost' : $unit->lifecycle_status;
		if ($lifecycle !== $unit->lifecycle_status OR $finding->observed_condition !== $unit->condition_status)
		{
			$this->CI->assets->change_status($unit, array(
				'to_lifecycle' => $lifecycle,
				'to_condition' => $finding->observed_condition,
				'reason' => 'Koreksi hasil audit fisik temuan '.$finding->public_id,
			), $user_id);
		}
		if ($finding->observed_location_id && (int) $finding->observed_location_id !== (int) $unit->location_id)
		{
			db_must($this->CI->db->where('id', (int) $unit->id)->update('asset_units', array(
				'location_id' => (int) $finding->observed_location_id, 'updated_at' => utc_now(),
			)), 'asset_units.audit_location');
		}
		$this->CI->audit->log('asset_audits.finding_applied', 'asset_audit_finding', $finding->public_id,
			array('unit' => $unit->public_id), FALSE, 'assets');
	}

	public function close_session($session, $user_id)
	{
		if ($session->status !== 'published')
		{
			throw new DomainRuleException('Hanya sesi yang sedang berjalan yang dapat ditutup.', 409);
		}
		$pending = $this->CI->db->select('f.id')->from('asset_audit_findings f')
			->join('asset_audit_targets t', 't.id = f.audit_target_id')
			->where('t.audit_session_id', (int) $session->id)->where('f.status', 'submitted')
			->count_all_results();
		if ($pending > 0)
		{
			throw new DomainRuleException('Masih ada '.$pending.' temuan yang belum diverifikasi.', 409);
		}
		db_must($this->CI->db->where('id', (int) $session->id)->update('asset_audit_sessions', array(
			'status' => 'closed', 'closed_by' => $user_id ? (int) $user_id : NULL,
			'closed_at' => utc_now(), 'ends_at' => utc_now(), 'updated_at' => utc_now(),
		)), 'asset_audit_sessions.close');
		$this->CI->audit->log('asset_audits.closed', 'asset_audit_session', $session->public_id, array(), FALSE, 'assets');
	}

	/** Setelah sesi ditutup, satu-satunya jalan koreksi adalah addendum yang teraudit. */
	public function add_addendum($session, array $input, $user_id)
	{
		if ($session->status !== 'closed')
		{
			throw new DomainRuleException('Addendum hanya untuk sesi audit yang sudah ditutup.', 409);
		}
		$reason = trim((string) ($input['reason'] ?? ''));
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Alasan addendum wajib diisi minimal 10 karakter.', 422, array('reason' => 'Terlalu pendek.'));
		}
		db_must($this->CI->db->insert('asset_audit_addenda', array(
			'audit_session_id' => (int) $session->id,
			'finding_id' => empty($input['finding_id']) ? NULL : (int) $input['finding_id'],
			'reason' => mb_substr($reason, 0, 600),
			'change_snapshot_json' => isset($input['change']) ? json_encode($input['change'], JSON_UNESCAPED_UNICODE) : NULL,
			'approved_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => utc_now(),
		)), 'asset_audit_addenda.insert');
		$this->CI->audit->log('asset_audits.addendum_added', 'asset_audit_session', $session->public_id,
			array('reason' => mb_substr($reason, 0, 200)), FALSE, 'assets');
	}

	public function addenda($session)
	{
		return $this->CI->db->where('audit_session_id', (int) $session->id)
			->order_by('id', 'DESC')->get('asset_audit_addenda')->result();
	}
}
