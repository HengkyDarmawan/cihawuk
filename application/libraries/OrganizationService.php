<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Struktur organisasi dinamis (prompt-master 18.6, modul-backend 10.3).
 *
 * Periode, unit, jabatan, orang, dan penugasan disimpan terpisah. Server menolak
 * self-parent, cycle, parent lintas periode, kedalaman berlebihan, dan penugasan aktif
 * ganda pada satu jabatan. Menonaktifkan node tidak menghapus histori.
 *
 * Halaman publik hanya membaca snapshot periode yang diterbitkan.
 */
class OrganizationService {

	/** @var CI_Controller */
	protected $CI;

	const MAX_DEPTH = 5;

	const UNIT_TYPES = array(
		'village_government' => 'Pemerintah Desa',
		'bpd' => 'Badan Permusyawaratan Desa',
		'institution' => 'Lembaga desa lain',
	);

	const ASSIGNMENT_TYPES = array(
		'definitive' => 'Definitif',
		'acting' => 'Pelaksana tugas (Plt)',
		'vacant' => 'Kosong',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
	}

	// ------------------------------------------------------------------
	// Periode
	// ------------------------------------------------------------------

	public function periods()
	{
		return $this->CI->db->order_by('year_start', 'DESC')->order_by('id', 'DESC')->get('org_periods')->result();
	}

	public function period($public_id)
	{
		return $this->CI->db->get_where('org_periods', array('public_id' => (string) $public_id))->row();
	}

	public function save_period(array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 160);
		$start = (int) ($input['year_start'] ?? 0);
		$end = trim((string) ($input['year_end'] ?? ''));
		$end = ($end === '') ? NULL : (int) $end;

		if ($name === '')
		{
			$errors['name'] = 'Nama periode wajib diisi.';
		}
		if ($start < 1900 OR $start > 2100)
		{
			$errors['year_start'] = 'Tahun mulai tidak wajar.';
		}
		if ($end !== NULL && $end < $start)
		{
			$errors['year_end'] = 'Tahun selesai lebih awal dari tahun mulai.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data periode.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'name' => $name,
			'year_start' => $start,
			'year_end' => $end,
			'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 500) ?: NULL,
			'updated_at' => $now,
		);
		$existing = $public_id ? $this->period($public_id) : NULL;
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('org_periods', $data), 'org_periods.update');
			$this->CI->audit->log('organization.period_saved', 'org_period', $existing->public_id, array('name' => $name), FALSE, 'organization');
			return $this->period($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['status'] = 'draft';
		$data['created_by'] = $user_id ? (int) $user_id : NULL;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('org_periods', $data), 'org_periods.insert');
		$this->CI->audit->log('organization.period_created', 'org_period', $data['public_id'], array('name' => $name), FALSE, 'organization');
		return $this->period($data['public_id']);
	}

	/**
	 * Salin unit dan jabatan dari periode lain. Penugasan TIDAK ikut disalin: masa jabatan
	 * periode sebelumnya sudah berakhir dan menyalinnya akan membuat klaim palsu.
	 */
	public function clone_structure($source_period, $target_period, $user_id)
	{
		if ((int) $source_period->id === (int) $target_period->id)
		{
			throw new DomainRuleException('Periode sumber dan tujuan tidak boleh sama.', 409);
		}
		if ($this->CI->db->where('period_id', (int) $target_period->id)->count_all_results('org_positions') > 0)
		{
			throw new DomainRuleException('Periode tujuan sudah berisi jabatan; salin hanya ke periode kosong.', 409);
		}

		db_transaction(function () use ($source_period, $target_period) {
			$now = utc_now();
			$unit_map = array();
			foreach ($this->CI->db->where('period_id', (int) $source_period->id)->order_by('id')->get('org_units')->result() as $unit)
			{
				db_must($this->CI->db->insert('org_units', array(
					'public_id' => $this->CI->crypto->public_id(),
					'period_id' => (int) $target_period->id,
					'name' => $unit->name,
					'unit_type' => $unit->unit_type,
					'parent_id' => NULL,
					'sort_order' => (int) $unit->sort_order,
					'active' => (int) $unit->active,
					'created_at' => $now,
					'updated_at' => $now,
				)), 'org_units.clone');
				$unit_map[(int) $unit->id] = (int) $this->CI->db->insert_id();
			}
			foreach ($unit_map as $old_id => $new_id)
			{
				$old = $this->CI->db->get_where('org_units', array('id' => $old_id))->row();
				if ($old && $old->parent_id && isset($unit_map[(int) $old->parent_id]))
				{
					$this->CI->db->where('id', $new_id)->update('org_units', array('parent_id' => $unit_map[(int) $old->parent_id]));
				}
			}

			$position_map = array();
			foreach ($this->CI->db->where('period_id', (int) $source_period->id)->order_by('id')->get('org_positions')->result() as $position)
			{
				db_must($this->CI->db->insert('org_positions', array(
					'public_id' => $this->CI->crypto->public_id(),
					'period_id' => (int) $target_period->id,
					'unit_id' => $position->unit_id ? ($unit_map[(int) $position->unit_id] ?? NULL) : NULL,
					'parent_id' => NULL,
					'title' => $position->title,
					'level' => (int) $position->level,
					'duties_public' => $position->duties_public,
					'sort_order' => (int) $position->sort_order,
					'active' => (int) $position->active,
					'created_at' => $now,
					'updated_at' => $now,
				)), 'org_positions.clone');
				$position_map[(int) $position->id] = (int) $this->CI->db->insert_id();
			}
			foreach ($position_map as $old_id => $new_id)
			{
				$old = $this->CI->db->get_where('org_positions', array('id' => $old_id))->row();
				if ($old && $old->parent_id && isset($position_map[(int) $old->parent_id]))
				{
					$this->CI->db->where('id', $new_id)->update('org_positions', array('parent_id' => $position_map[(int) $old->parent_id]));
				}
			}
		});

		$this->CI->audit->log('organization.structure_cloned', 'org_period', $target_period->public_id,
			array('from' => $source_period->public_id), FALSE, 'organization');
	}

	// ------------------------------------------------------------------
	// Unit
	// ------------------------------------------------------------------

	public function units($period)
	{
		return $this->CI->db->where('period_id', (int) $period->id)
			->order_by('sort_order')->order_by('id')->get('org_units')->result();
	}

	public function unit($public_id)
	{
		return $this->CI->db->get_where('org_units', array('public_id' => (string) $public_id))->row();
	}

	public function save_unit($period, array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 180);
		$type = (string) ($input['unit_type'] ?? 'village_government');
		$parent = empty($input['parent_id']) ? NULL : (int) $input['parent_id'];
		$existing = $public_id ? $this->unit($public_id) : NULL;

		if ($name === '')
		{
			$errors['name'] = 'Nama unit wajib diisi.';
		}
		if ( ! isset(self::UNIT_TYPES[$type]))
		{
			$errors['unit_type'] = 'Jenis unit tidak dikenal.';
		}
		if ($parent !== NULL)
		{
			$parent_row = $this->CI->db->get_where('org_units', array('id' => $parent))->row();
			if ( ! $parent_row)
			{
				$errors['parent_id'] = 'Unit induk tidak ditemukan.';
			}
			elseif ((int) $parent_row->period_id !== (int) $period->id)
			{
				$errors['parent_id'] = 'Unit induk berasal dari periode lain.';
			}
			elseif ($existing && (int) $parent === (int) $existing->id)
			{
				$errors['parent_id'] = 'Unit tidak boleh menjadi induk dirinya sendiri.';
			}
			elseif ($existing && $this->creates_cycle('org_units', (int) $existing->id, $parent))
			{
				$errors['parent_id'] = 'Susunan itu membentuk lingkaran induk-anak.';
			}
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data unit.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'name' => $name,
			'unit_type' => $type,
			'parent_id' => $parent,
			'sort_order' => (int) ($input['sort_order'] ?? 0),
			'active' => empty($input['active']) ? 0 : 1,
			'updated_at' => $now,
		);
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('org_units', $data), 'org_units.update');
			$this->CI->audit->log('organization.unit_saved', 'org_unit', $existing->public_id, array('name' => $name), FALSE, 'organization');
			return $this->unit($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['period_id'] = (int) $period->id;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('org_units', $data), 'org_units.insert');
		$this->CI->audit->log('organization.unit_created', 'org_unit', $data['public_id'], array('name' => $name), FALSE, 'organization');
		return $this->unit($data['public_id']);
	}

	// ------------------------------------------------------------------
	// Jabatan
	// ------------------------------------------------------------------

	public function positions($period)
	{
		return $this->CI->db->select('p.*, u.name AS unit_name, u.unit_type')
			->from('org_positions p')->join('org_units u', 'u.id = p.unit_id', 'left')
			->where('p.period_id', (int) $period->id)
			->order_by('p.level')->order_by('p.sort_order')->order_by('p.id')->get()->result();
	}

	public function position($public_id)
	{
		return $this->CI->db->get_where('org_positions', array('public_id' => (string) $public_id))->row();
	}

	public function save_position($period, array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$title = mb_substr(trim((string) ($input['title'] ?? '')), 0, 180);
		$parent = empty($input['parent_id']) ? NULL : (int) $input['parent_id'];
		$unit_id = empty($input['unit_id']) ? NULL : (int) $input['unit_id'];
		$existing = $public_id ? $this->position($public_id) : NULL;
		$level = 1;

		if ($title === '')
		{
			$errors['title'] = 'Nama jabatan wajib diisi.';
		}
		if ($unit_id !== NULL)
		{
			$unit = $this->CI->db->get_where('org_units', array('id' => $unit_id))->row();
			if ( ! $unit OR (int) $unit->period_id !== (int) $period->id)
			{
				$errors['unit_id'] = 'Unit tidak ditemukan pada periode ini.';
			}
		}
		if ($parent !== NULL)
		{
			$parent_row = $this->CI->db->get_where('org_positions', array('id' => $parent))->row();
			if ( ! $parent_row)
			{
				$errors['parent_id'] = 'Jabatan atasan tidak ditemukan.';
			}
			elseif ((int) $parent_row->period_id !== (int) $period->id)
			{
				$errors['parent_id'] = 'Jabatan atasan berasal dari periode lain.';
			}
			elseif ($existing && (int) $parent === (int) $existing->id)
			{
				$errors['parent_id'] = 'Jabatan tidak boleh menjadi atasan dirinya sendiri.';
			}
			elseif ($existing && $this->creates_cycle('org_positions', (int) $existing->id, $parent))
			{
				$errors['parent_id'] = 'Susunan itu membentuk lingkaran atasan-bawahan.';
			}
			else
			{
				$level = (int) $parent_row->level + 1;
				if ($level > self::MAX_DEPTH)
				{
					$errors['parent_id'] = 'Kedalaman struktur melebihi '.self::MAX_DEPTH.' tingkat.';
				}
			}
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data jabatan.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'unit_id' => $unit_id,
			'parent_id' => $parent,
			'title' => $title,
			'level' => $level,
			'duties_public' => mb_substr(trim((string) ($input['duties_public'] ?? '')), 0, 1000) ?: NULL,
			'sort_order' => (int) ($input['sort_order'] ?? 0),
			'active' => empty($input['active']) ? 0 : 1,
			'updated_at' => $now,
		);
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('org_positions', $data), 'org_positions.update');
			$this->relevel_descendants((int) $existing->id, $level);
			$this->CI->audit->log('organization.position_saved', 'org_position', $existing->public_id, array('title' => $title), FALSE, 'organization');
			return $this->position($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['period_id'] = (int) $period->id;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('org_positions', $data), 'org_positions.insert');
		$this->CI->audit->log('organization.position_created', 'org_position', $data['public_id'], array('title' => $title), FALSE, 'organization');
		return $this->position($data['public_id']);
	}

	protected function relevel_descendants($position_id, $level)
	{
		foreach ($this->CI->db->where('parent_id', (int) $position_id)->get('org_positions')->result() as $child)
		{
			$this->CI->db->where('id', (int) $child->id)->update('org_positions', array('level' => $level + 1));
			$this->relevel_descendants((int) $child->id, $level + 1);
		}
	}

	/** Telusuri rantai induk calon; TRUE bila node itu sendiri ditemukan di dalamnya. */
	protected function creates_cycle($table, $node_id, $candidate_parent_id)
	{
		$seen = array();
		$current = (int) $candidate_parent_id;
		while ($current > 0 && ! isset($seen[$current]))
		{
			if ($current === (int) $node_id)
			{
				return TRUE;
			}
			$seen[$current] = TRUE;
			$row = $this->CI->db->select('parent_id')->get_where($table, array('id' => $current))->row();
			$current = $row && $row->parent_id ? (int) $row->parent_id : 0;
		}
		return FALSE;
	}

	/** Menonaktifkan, bukan menghapus: histori penugasan tetap utuh. */
	public function deactivate_position($position, $user_id)
	{
		if ($this->CI->db->where('parent_id', (int) $position->id)->where('active', 1)->count_all_results('org_positions') > 0)
		{
			throw new DomainRuleException('Jabatan ini masih punya bawahan aktif. Nonaktifkan bawahannya lebih dulu.', 409);
		}
		db_must($this->CI->db->where('id', (int) $position->id)->update('org_positions', array(
			'active' => 0, 'updated_at' => utc_now(),
		)), 'org_positions.deactivate');
		$this->CI->audit->log('organization.position_deactivated', 'org_position', $position->public_id,
			array('title' => $position->title), FALSE, 'organization');
	}

	// ------------------------------------------------------------------
	// Orang
	// ------------------------------------------------------------------

	public function people()
	{
		return $this->CI->db->order_by('full_name')->get('people')->result();
	}

	public function person($public_id)
	{
		return $this->CI->db->get_where('people', array('public_id' => (string) $public_id))->row();
	}

	public function save_person(array $input, $user_id, $public_id = NULL)
	{
		$name = mb_substr(trim((string) ($input['full_name'] ?? '')), 0, 180);
		if ($name === '')
		{
			throw new DomainRuleException('Nama orang wajib diisi.', 422, array('full_name' => 'Wajib diisi.'));
		}
		$photo = empty($input['photo_media_id']) ? NULL : (int) $input['photo_media_id'];
		$consent = empty($input['photo_consent']) ? 0 : 1;
		if ($photo !== NULL && ! $consent)
		{
			// Foto pejabat tidak boleh dipakai sebelum pasangan identitas dan izinnya disetujui.
			throw new DomainRuleException('Foto hanya boleh disimpan bila izin publikasinya sudah dicatat.', 422,
				array('photo_consent' => 'Izin publikasi foto belum dicatat.'));
		}
		if ($photo !== NULL)
		{
			$media = $this->CI->db->select('mime_type')->where('id', $photo)
				->where('deleted_at IS NULL', NULL, FALSE)->get('media_assets')->row();
			if ( ! $media OR strpos((string) $media->mime_type, 'image/') !== 0)
			{
				throw new DomainRuleException('Foto harus berupa gambar dari pustaka media.', 422,
					array('photo_media_id' => 'Media tidak ditemukan atau bukan gambar.'));
			}
		}

		$now = utc_now();
		$data = array(
			'full_name' => $name,
			'title_prefix' => mb_substr(trim((string) ($input['title_prefix'] ?? '')), 0, 40) ?: NULL,
			'title_suffix' => mb_substr(trim((string) ($input['title_suffix'] ?? '')), 0, 60) ?: NULL,
			'photo_media_id' => $photo,
			'photo_consent' => $consent,
			'bio_public' => mb_substr(trim((string) ($input['bio_public'] ?? '')), 0, 1000) ?: NULL,
			'data_status' => in_array((string) ($input['data_status'] ?? 'draft'), array('draft', 'reviewed'), TRUE)
				? (string) $input['data_status'] : 'draft',
			'updated_at' => $now,
		);
		$existing = $public_id ? $this->person($public_id) : NULL;
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('people', $data), 'people.update');
			$this->CI->audit->log('organization.person_saved', 'person', $existing->public_id, array('name' => $name), FALSE, 'organization');
			return $this->person($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('people', $data), 'people.insert');
		$this->CI->audit->log('organization.person_created', 'person', $data['public_id'], array('name' => $name), FALSE, 'organization');
		return $this->person($data['public_id']);
	}

	// ------------------------------------------------------------------
	// Penugasan
	// ------------------------------------------------------------------

	public function assignments($period)
	{
		return $this->CI->db->select('a.*, p.title AS position_title, pe.full_name, pe.title_prefix, pe.title_suffix')
			->from('org_assignments a')
			->join('org_positions p', 'p.id = a.position_id')
			->join('people pe', 'pe.id = a.person_id', 'left')
			->where('a.period_id', (int) $period->id)
			->order_by('p.level')->order_by('p.sort_order')->order_by('a.id')->get()->result();
	}

	public function assignment($public_id)
	{
		return $this->CI->db->get_where('org_assignments', array('public_id' => (string) $public_id))->row();
	}

	public function save_assignment($period, array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$position_id = (int) ($input['position_id'] ?? 0);
		$person_id = empty($input['person_id']) ? NULL : (int) $input['person_id'];
		$type = (string) ($input['assignment_type'] ?? 'definitive');
		$start = trim((string) ($input['start_date'] ?? '')) ?: NULL;
		$end = trim((string) ($input['end_date'] ?? '')) ?: NULL;
		$existing = $public_id ? $this->assignment($public_id) : NULL;

		$position = $this->CI->db->get_where('org_positions', array('id' => $position_id))->row();
		if ( ! $position OR (int) $position->period_id !== (int) $period->id)
		{
			$errors['position_id'] = 'Jabatan tidak ditemukan pada periode ini.';
		}
		if ( ! isset(self::ASSIGNMENT_TYPES[$type]))
		{
			$errors['assignment_type'] = 'Jenis penugasan tidak dikenal.';
		}
		if ($type === 'vacant' && $person_id !== NULL)
		{
			$errors['person_id'] = 'Penugasan kosong tidak boleh punya orang.';
		}
		if ($type !== 'vacant' && $person_id === NULL)
		{
			$errors['person_id'] = 'Pilih orang untuk penugasan definitif atau Plt.';
		}
		foreach (array('start_date' => $start, 'end_date' => $end) as $field => $value)
		{
			if ($value !== NULL && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
			{
				$errors[$field] = 'Format tanggal harus YYYY-MM-DD.';
			}
		}
		if ($start !== NULL && $end !== NULL && $end < $start)
		{
			$errors['end_date'] = 'Tanggal selesai lebih awal dari tanggal mulai.';
		}
		// Satu jabatan hanya boleh punya satu penugasan aktif pada satu waktu.
		if ( ! isset($errors['position_id']))
		{
			$this->CI->db->where('position_id', $position_id)->where('status', 'active');
			if ($existing)
			{
				$this->CI->db->where('id <>', (int) $existing->id);
			}
			if ($this->CI->db->count_all_results('org_assignments') > 0)
			{
				$errors['position_id'] = 'Jabatan itu sudah punya penugasan aktif. Akhiri penugasan sebelumnya lebih dulu.';
			}
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data penugasan.', 422, $errors);
		}

		$now = utc_now();
		$decree = trim((string) ($input['decree_number'] ?? ''));
		$data = array(
			'position_id' => $position_id,
			'person_id' => $person_id,
			'assignment_type' => $type,
			'start_date' => $start,
			'end_date' => $end,
			'status' => 'active',
			'updated_at' => $now,
		);
		if ($decree !== '')
		{
			// Nomor SK bersifat internal: disimpan terenkripsi dan tidak pernah ke publik.
			$data['decree_number_ciphertext'] = $this->CI->crypto->encrypt(mb_substr($decree, 0, 120));
		}
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('org_assignments', $data), 'org_assignments.update');
			$this->CI->audit->log('organization.assignment_saved', 'org_assignment', $existing->public_id,
				array('position_id' => $position_id), FALSE, 'organization');
			return $this->assignment($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['period_id'] = (int) $period->id;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('org_assignments', $data), 'org_assignments.insert');
		$this->CI->audit->log('organization.assignment_created', 'org_assignment', $data['public_id'],
			array('position_id' => $position_id), FALSE, 'organization');
		return $this->assignment($data['public_id']);
	}

	/** Mengakhiri penugasan tidak menghapusnya; barisnya tetap tersimpan sebagai histori. */
	public function end_assignment($assignment, $end_date, $reason, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $assignment->id)->update('org_assignments', array(
			'status' => 'ended',
			'end_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $end_date) ? $end_date : $assignment->end_date,
			'end_reason' => mb_substr(trim((string) $reason), 0, 255) ?: NULL,
			'updated_at' => utc_now(),
		)), 'org_assignments.end');
		$this->CI->audit->log('organization.assignment_ended', 'org_assignment', $assignment->public_id,
			array('reason' => mb_substr((string) $reason, 0, 200)), FALSE, 'organization');
	}

	// ------------------------------------------------------------------
	// Pemeriksaan dan snapshot
	// ------------------------------------------------------------------

	/** @return array{errors: string[], warnings: string[]} */
	public function validate_period($period)
	{
		$errors = array();
		$warnings = array();
		$active = array();
		foreach ($this->positions($period) as $position)
		{
			if ((int) $position->active === 1)
			{
				$active[] = $position;
			}
		}
		if (empty($active))
		{
			$errors[] = 'Periode belum punya jabatan aktif.';
		}
		$roots = 0;
		foreach ($active as $position)
		{
			if ($position->parent_id === NULL)
			{
				$roots++;
			}
		}
		if ($roots === 0 && $active)
		{
			$errors[] = 'Tidak ada jabatan puncak; struktur tidak dapat digambar.';
		}
		if ($roots > 1)
		{
			$warnings[] = 'Ada '.$roots.' jabatan puncak. Pastikan memang ada beberapa lembaga terpisah.';
		}

		$assigned = array();
		foreach ($this->assignments($period) as $assignment)
		{
			if ($assignment->status === 'active')
			{
				$assigned[(int) $assignment->position_id] = TRUE;
			}
		}
		foreach ($active as $position)
		{
			if ( ! isset($assigned[(int) $position->id]))
			{
				$warnings[] = 'Jabatan "'.$position->title.'" belum punya penugasan; akan tampil sebagai kosong.';
			}
		}
		return array('errors' => $errors, 'warnings' => $warnings);
	}

	/**
	 * Susun snapshot publik. Nomor SK, status data internal, dan foto tanpa izin TIDAK
	 * ikut masuk; yang keluar hanya yang memang boleh dibaca pengunjung.
	 */
	public function build_snapshot($period)
	{
		$assigned = array();
		foreach ($this->assignments($period) as $assignment)
		{
			if ($assignment->status === 'active')
			{
				$assigned[(int) $assignment->position_id] = $assignment;
			}
		}

		$nodes = array();
		foreach ($this->positions($period) as $position)
		{
			if ((int) $position->active !== 1)
			{
				continue;
			}
			$assignment = $assigned[(int) $position->id] ?? NULL;
			$person = NULL;
			if ($assignment && $assignment->person_id)
			{
				$row = $this->CI->db->get_where('people', array('id' => (int) $assignment->person_id))->row();
				if ($row)
				{
					$person = array(
						'name' => trim(($row->title_prefix ? $row->title_prefix.' ' : '').$row->full_name
							.($row->title_suffix ? ', '.$row->title_suffix : '')),
						'bio_public' => $row->bio_public,
						'photo_media_id' => ((int) $row->photo_consent === 1 && $row->photo_media_id)
							? (int) $row->photo_media_id : NULL,
					);
				}
			}
			$nodes[] = array(
				'public_id' => $position->public_id,
				'parent_public_id' => $position->parent_id
					? (string) $this->CI->db->select('public_id')->get_where('org_positions', array('id' => (int) $position->parent_id))->row('public_id')
					: NULL,
				'title' => $position->title,
				'unit_name' => $position->unit_name,
				'unit_type' => $position->unit_type,
				'level' => (int) $position->level,
				'duties_public' => $position->duties_public,
				'assignment_type' => $assignment ? $assignment->assignment_type : 'vacant',
				// Hanya tahun: tanggal persis SK tidak perlu keluar ke publik.
				'start_year' => ($assignment && $assignment->start_date) ? (int) substr($assignment->start_date, 0, 4) : NULL,
				'end_year' => ($assignment && $assignment->end_date) ? (int) substr($assignment->end_date, 0, 4) : NULL,
				'person' => $person,
			);
		}

		return array(
			'period' => array(
				'public_id' => $period->public_id,
				'name' => $period->name,
				'year_start' => (int) $period->year_start,
				'year_end' => $period->year_end === NULL ? NULL : (int) $period->year_end,
			),
			'nodes' => $nodes,
		);
	}

	public function publish($period, $reason, $user_id, $make_default = TRUE)
	{
		$report = $this->validate_period($period);
		if ( ! empty($report['errors']))
		{
			throw new DomainRuleException('Struktur belum lolos pemeriksaan: '.implode(' ', $report['errors']), 422);
		}
		$snapshot = $this->build_snapshot($period);
		$now = utc_now();
		$revision = NULL;

		db_transaction(function () use ($period, $snapshot, $reason, $user_id, $now, $make_default, &$revision) {
			$revision = (int) $this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'organization', 'target_id' => (int) $period->id))
				->get('cms_publication_snapshots')->row('revision_no') + 1;
			$this->CI->db->where(array('target_type' => 'organization', 'target_id' => (int) $period->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'organization',
				'target_id' => (int) $period->id,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
				'reason' => mb_substr((string) $reason, 0, 500),
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_publication_snapshots.organization');

			if ($make_default)
			{
				// Hanya satu periode yang menjadi tampilan publik bawaan.
				$this->CI->db->update('org_periods', array('is_public_default' => 0));
			}
			db_must($this->CI->db->where('id', (int) $period->id)->update('org_periods', array(
				'status' => 'published',
				'is_public_default' => $make_default ? 1 : (int) $period->is_public_default,
				'published_at' => $now,
				'updated_at' => $now,
			)), 'org_periods.publish');
		});

		$this->after_change('organization.published', array('period' => $period->public_id, 'revision' => $revision));
		return $revision;
	}

	public function unpublish($period, $reason, $user_id)
	{
		$now = utc_now();
		db_transaction(function () use ($period, $now) {
			$this->CI->db->where(array('target_type' => 'organization', 'target_id' => (int) $period->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			$this->CI->db->where('id', (int) $period->id)->update('org_periods', array(
				'status' => 'draft', 'is_public_default' => 0, 'published_at' => NULL, 'updated_at' => $now,
			));
		});
		$this->after_change('organization.unpublished', array('period' => $period->public_id,
			'reason' => mb_substr((string) $reason, 0, 200)));
	}

	public function snapshots($period, $limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'organization', 's.target_id' => (int) $period->id))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	protected function after_change($action, array $meta)
	{
		$this->CI->audit->log($action, 'organization', (string) ($meta['period'] ?? ''), $meta, FALSE, 'organization');
		$this->CI->audit->event('info', 'Struktur organisasi: '.$action.'.', 'organization', $meta);
		$this->CI->public_cache->invalidate_page('pemerintahan');
		$this->CI->public_cache->forget_group('listing');
	}

	// ------------------------------------------------------------------
	// Pembacaan publik
	// ------------------------------------------------------------------

	/** Periode yang terbit dan dapat dipilih pengunjung. */
	public function published_periods()
	{
		$cached = $this->CI->public_cache->get('listing', 'org_periods');
		if (is_array($cached))
		{
			return $cached;
		}
		$rows = $this->CI->db->select('p.public_id, p.name, p.year_start, p.year_end, p.is_public_default')
			->from('org_periods p')
			->join('cms_publication_snapshots s', "s.target_type = 'organization' AND s.target_id = p.id AND s.superseded_at IS NULL")
			->where('p.status', 'published')
			->order_by('p.year_start', 'DESC')->get()->result_array();
		$this->CI->public_cache->set('listing', 'org_periods', $rows, 1800);
		return $rows;
	}

	/** Snapshot terbit satu periode; NULL bila belum ada. */
	public function published_structure($period_public_id = NULL)
	{
		$key = 'org_structure_'.($period_public_id ?: 'default');
		$cached = $this->CI->public_cache->get('listing', $key);
		if (is_array($cached))
		{
			return $cached;
		}
		$this->CI->db->select('s.snapshot_json, s.revision_no, s.published_at')
			->from('cms_publication_snapshots s')->join('org_periods p', 'p.id = s.target_id')
			->where('s.target_type', 'organization')->where('s.superseded_at IS NULL', NULL, FALSE)
			->where('p.status', 'published');
		if ($period_public_id)
		{
			$this->CI->db->where('p.public_id', (string) $period_public_id);
		}
		else
		{
			$this->CI->db->where('p.is_public_default', 1);
		}
		$row = $this->CI->db->order_by('s.revision_no', 'DESC')->limit(1)->get()->row();
		if ( ! $row)
		{
			return NULL;
		}
		$snapshot = json_decode((string) $row->snapshot_json, TRUE);
		if ( ! is_array($snapshot))
		{
			return NULL;
		}
		$snapshot['revision_no'] = (int) $row->revision_no;
		$snapshot['published_at'] = $row->published_at;
		$this->CI->public_cache->set('listing', $key, $snapshot, 1800);
		return $snapshot;
	}

	/** Ubah daftar node snapshot menjadi pohon untuk daftar bertingkat yang aksesibel. */
	public function tree_from_snapshot(array $snapshot)
	{
		$children = array();
		foreach ($snapshot['nodes'] as $node)
		{
			$children[(string) $node['parent_public_id']][] = $node;
		}
		return $this->attach_children($children, '');
	}

	protected function attach_children(array $children, $parent_key)
	{
		$out = array();
		foreach ($children[$parent_key] ?? array() as $node)
		{
			$node['children'] = $this->attach_children($children, (string) $node['public_id']);
			$out[] = $node;
		}
		return $out;
	}
}
