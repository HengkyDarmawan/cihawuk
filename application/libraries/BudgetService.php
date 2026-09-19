<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Transparansi anggaran desa (modul-backend 15, modul-frontend 11).
 *
 * Anggaran murni, anggaran perubahan, dan realisasi adalah revisi terpisah di atas satu
 * tahun anggaran; kode tidak pernah menjumlahkan ketiganya. Alur: draft -> rekonsiliasi ->
 * verifikasi -> persetujuan -> snapshot publik. Periode terkunci hanya dapat dikoreksi
 * lewat revisi atau addendum baru.
 */
class BudgetService {

	/** @var CI_Controller */
	protected $CI;

	const STATUSES = array(
		'draft' => 'Draft pengelola',
		'reconciling' => 'Rekonsiliasi',
		'verified' => 'Terverifikasi',
		'approved' => 'Disetujui untuk terbit',
		'published' => 'Terbit',
		'locked' => 'Terkunci',
	);

	const REVISION_TYPES = array(
		'original' => 'Anggaran murni',
		'amended' => 'Anggaran perubahan',
		'realization' => 'Realisasi',
	);

	const SECTIONS = array(
		'income' => 'Pendapatan',
		'expenditure' => 'Belanja',
		'financing_in' => 'Penerimaan pembiayaan',
		'financing_out' => 'Pengeluaran pembiayaan',
	);

	/** Selisih yang masih dianggap pembulatan, dalam rupiah. */
	const TOLERANCE = 0.005;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
	}

	// ------------------------------------------------------------------
	// Tahun anggaran
	// ------------------------------------------------------------------

	public function years()
	{
		return $this->CI->db->order_by('fiscal_year', 'DESC')->get('budget_years')->result();
	}

	public function year($public_id)
	{
		return $this->CI->db->get_where('budget_years', array('public_id' => (string) $public_id))->row();
	}

	public function create_year(array $input, $user_id)
	{
		$year = (int) ($input['fiscal_year'] ?? 0);
		if ($year < 2000 OR $year > 2100)
		{
			throw new DomainRuleException('Tahun anggaran tidak wajar.', 422, array('fiscal_year' => 'Tidak wajar.'));
		}
		if ($this->CI->db->where('fiscal_year', $year)->count_all_results('budget_years') > 0)
		{
			throw new DomainRuleException('Tahun anggaran itu sudah ada.', 409, array('fiscal_year' => 'Sudah ada.'));
		}
		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('budget_years', array(
			'public_id' => $public_id,
			'fiscal_year' => $year,
			'status' => 'draft',
			'is_provisional' => 1,
			'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 1000) ?: NULL,
			'created_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'budget_years.insert');
		$this->CI->audit->log('finance.year_created', 'budget_year', $public_id, array('year' => $year), FALSE, 'budget_transparency');
		return $this->year($public_id);
	}

	/** Tahun terkunci tidak boleh disunting langsung. */
	protected function assert_editable($year)
	{
		if (in_array($year->status, array('locked'), TRUE))
		{
			throw new DomainRuleException('Tahun anggaran terkunci. Koreksi harus lewat revisi atau addendum baru.', 409);
		}
	}

	public function revisions($year)
	{
		return $this->CI->db->where('budget_year_id', (int) $year->id)
			->order_by('revision_type')->get('budget_revisions')->result();
	}

	public function revision($public_id)
	{
		return $this->CI->db->get_where('budget_revisions', array('public_id' => (string) $public_id))->row();
	}

	public function save_revision($year, array $input, $user_id, $public_id = NULL)
	{
		$this->assert_editable($year);
		$type = (string) ($input['revision_type'] ?? '');
		if ( ! isset(self::REVISION_TYPES[$type]))
		{
			throw new DomainRuleException('Jenis revisi tidak dikenal.', 422, array('revision_type' => 'Tidak dikenal.'));
		}
		$document_year = empty($input['document_year']) ? NULL : (int) $input['document_year'];
		$errors = array();
		if ($document_year !== NULL && ($document_year < 2000 OR $document_year > 2100))
		{
			$errors['document_year'] = 'Tahun dokumen tidak wajar.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data revisi.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'label' => mb_substr(trim((string) ($input['label'] ?? self::REVISION_TYPES[$type])), 0, 160),
			'document_year' => $document_year,
			'document_note' => mb_substr(trim((string) ($input['document_note'] ?? '')), 0, 500) ?: NULL,
			'updated_at' => $now,
		);
		$existing = $public_id ? $this->revision($public_id) : NULL;
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('budget_revisions', $data), 'budget_revisions.update');
			return $this->revision($existing->public_id);
		}
		if ($this->CI->db->where(array('budget_year_id' => (int) $year->id, 'revision_type' => $type))
			->count_all_results('budget_revisions') > 0)
		{
			throw new DomainRuleException('Revisi jenis itu sudah ada pada tahun ini.', 409);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['budget_year_id'] = (int) $year->id;
		$data['revision_type'] = $type;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('budget_revisions', $data), 'budget_revisions.insert');
		$this->CI->audit->log('finance.revision_created', 'budget_revision', $data['public_id'],
			array('type' => $type), FALSE, 'budget_transparency');
		return $this->revision($data['public_id']);
	}

	// ------------------------------------------------------------------
	// Kategori dan baris anggaran
	// ------------------------------------------------------------------

	public function categories($year, $section = NULL)
	{
		$this->CI->db->where('budget_year_id', (int) $year->id);
		if ($section !== NULL)
		{
			$this->CI->db->where('section', $section);
		}
		return $this->CI->db->order_by('section')->order_by('level')->order_by('sort_order')
			->order_by('id')->get('budget_categories')->result();
	}

	public function category($public_id)
	{
		return $this->CI->db->get_where('budget_categories', array('public_id' => (string) $public_id))->row();
	}

	public function save_category($year, array $input, $user_id, $public_id = NULL)
	{
		$this->assert_editable($year);
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 220);
		$section = (string) ($input['section'] ?? '');
		$parent = empty($input['parent_id']) ? NULL : (int) $input['parent_id'];
		$existing = $public_id ? $this->category($public_id) : NULL;
		$level = 1;

		if ($name === '')
		{
			$errors['name'] = 'Nama kategori wajib diisi.';
		}
		if ( ! isset(self::SECTIONS[$section]))
		{
			$errors['section'] = 'Kelompok anggaran tidak dikenal.';
		}
		if ($parent !== NULL)
		{
			$parent_row = $this->CI->db->get_where('budget_categories', array('id' => $parent))->row();
			if ( ! $parent_row OR (int) $parent_row->budget_year_id !== (int) $year->id)
			{
				$errors['parent_id'] = 'Kategori induk tidak ditemukan pada tahun ini.';
			}
			elseif ($parent_row->section !== $section)
			{
				$errors['parent_id'] = 'Kategori induk berada pada kelompok anggaran yang berbeda.';
			}
			elseif ($existing && (int) $parent === (int) $existing->id)
			{
				$errors['parent_id'] = 'Kategori tidak boleh menjadi induk dirinya sendiri.';
			}
			else
			{
				$level = (int) $parent_row->level + 1;
				if ($level > 3)
				{
					$errors['parent_id'] = 'Kedalaman maksimal adalah bidang, subbidang, lalu kegiatan.';
				}
			}
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data kategori.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'parent_id' => $parent,
			'section' => $section,
			'code' => mb_substr(trim((string) ($input['code'] ?? '')), 0, 40) ?: NULL,
			'name' => $name,
			'level' => $level,
			'sort_order' => (int) ($input['sort_order'] ?? 0),
			'updated_at' => $now,
		);
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('budget_categories', $data), 'budget_categories.update');
			return $this->category($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['budget_year_id'] = (int) $year->id;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('budget_categories', $data), 'budget_categories.insert');
		return $this->category($data['public_id']);
	}

	public function lines($revision)
	{
		return $this->CI->db->select('l.*, c.public_id AS category_public_id, c.name, c.section, c.level, c.parent_id, c.code')
			->from('budget_lines l')->join('budget_categories c', 'c.id = l.category_id')
			->where('l.revision_id', (int) $revision->id)
			->order_by('c.section')->order_by('c.level')->order_by('c.sort_order')->get()->result();
	}

	/** Simpan satu angka. Nilai negatif ditolak; arah ditentukan kelompok anggaran. */
	public function save_line($year, $revision, array $input, $user_id)
	{
		$this->assert_editable($year);
		$category = $this->category((string) ($input['category_public_id'] ?? ''));
		if ( ! $category OR (int) $category->budget_year_id !== (int) $year->id)
		{
			throw new DomainRuleException('Kategori tidak ditemukan pada tahun ini.', 404);
		}
		$raw = str_replace(array('.', ' '), '', trim((string) ($input['amount'] ?? '')));
		$raw = str_replace(',', '.', $raw);
		if ($raw === '' OR ! is_numeric($raw))
		{
			throw new DomainRuleException('Nilai anggaran harus berupa angka.', 422, array('amount' => 'Harus angka.'));
		}
		$amount = round((float) $raw, 2);
		if ($amount < 0)
		{
			throw new DomainRuleException('Nilai anggaran tidak boleh negatif; gunakan kelompok pengeluaran.', 422,
				array('amount' => 'Tidak boleh negatif.'));
		}

		$now = utc_now();
		$existing = $this->CI->db->where(array('revision_id' => (int) $revision->id, 'category_id' => (int) $category->id))
			->get('budget_lines')->row();
		$data = array(
			'amount' => $amount,
			'variance_note' => mb_substr(trim((string) ($input['variance_note'] ?? '')), 0, 500) ?: NULL,
			'updated_at' => $now,
		);
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('budget_lines', $data), 'budget_lines.update');
		}
		else
		{
			$data['revision_id'] = (int) $revision->id;
			$data['category_id'] = (int) $category->id;
			$data['created_at'] = $now;
			db_must($this->CI->db->insert('budget_lines', $data), 'budget_lines.insert');
		}
		// Angka berubah berarti verifikasi sebelumnya tidak lagi berlaku.
		if (in_array($year->status, array('verified', 'approved'), TRUE))
		{
			$this->CI->db->where('id', (int) $year->id)->update('budget_years', array(
				'status' => 'reconciling', 'verified_by' => NULL, 'verified_at' => NULL,
				'approved_by' => NULL, 'approved_at' => NULL, 'updated_at' => $now,
			));
		}
	}

	// ------------------------------------------------------------------
	// Rekapitulasi dan validasi
	// ------------------------------------------------------------------

	/** Jumlah per kelompok untuk satu revisi, dihitung dari baris tingkat terdalam. */
	public function totals($revision)
	{
		$totals = array('income' => 0.0, 'expenditure' => 0.0, 'financing_in' => 0.0, 'financing_out' => 0.0);
		$has_children = array();
		foreach ($this->CI->db->select('parent_id')->where('budget_year_id', (int) $revision->budget_year_id)
			->where('parent_id IS NOT NULL', NULL, FALSE)->get('budget_categories')->result() as $row)
		{
			$has_children[(int) $row->parent_id] = TRUE;
		}
		foreach ($this->lines($revision) as $line)
		{
			// Induk hanya ringkasan; yang dijumlahkan adalah baris terdalam.
			if (isset($has_children[(int) $line->category_id]))
			{
				continue;
			}
			$totals[$line->section] = ($totals[$line->section] ?? 0) + (float) $line->amount;
		}
		$totals['surplus'] = $totals['income'] - $totals['expenditure'];
		$totals['financing_net'] = $totals['financing_in'] - $totals['financing_out'];
		return $totals;
	}

	/**
	 * Pemeriksaan sebelum terbit (modul-backend 15.3).
	 * @return array{errors: string[], warnings: string[]}
	 */
	public function validate_year($year)
	{
		$errors = array();
		$warnings = array();
		$revisions = $this->revisions($year);
		if (empty($revisions))
		{
			$errors[] = 'Belum ada revisi anggaran sama sekali.';
			return array('errors' => $errors, 'warnings' => $warnings);
		}

		$by_type = array();
		foreach ($revisions as $revision)
		{
			$by_type[$revision->revision_type] = $revision;
		}
		if ( ! isset($by_type['original']))
		{
			$errors[] = 'Anggaran murni belum diisi; realisasi tidak dapat dibandingkan tanpa dasar.';
		}

		foreach ($revisions as $revision)
		{
			$label = self::REVISION_TYPES[$revision->revision_type];
			$lines = $this->lines($revision);
			if (empty($lines))
			{
				$errors[] = 'Revisi "'.$label.'" belum punya satu pun angka.';
				continue;
			}
			// Tahun judul, tahun anggaran, dan tahun dokumen tidak boleh tertukar.
			if ($revision->document_year !== NULL && (int) $revision->document_year < (int) $year->fiscal_year)
			{
				$warnings[] = 'Revisi "'.$label.'" memakai dokumen tahun '.(int) $revision->document_year
					.' untuk anggaran tahun '.(int) $year->fiscal_year.'. Pastikan tidak tertukar.';
			}
			foreach ($this->parent_mismatches($revision) as $mismatch)
			{
				$errors[] = 'Revisi "'.$label.'": jumlah komponen "'.$mismatch['name'].'" ('
					.number_format($mismatch['children'], 2, ',', '.').') tidak sama dengan nilai induknya ('
					.number_format($mismatch['parent'], 2, ',', '.').') dan belum ada catatan selisih.';
			}
			$totals = $this->totals($revision);
			// Surplus/defisit harus tertutup pembiayaan neto.
			if (abs($totals['surplus'] + $totals['financing_net']) > self::TOLERANCE)
			{
				$errors[] = 'Revisi "'.$label.'": surplus/defisit ('.number_format($totals['surplus'], 2, ',', '.')
					.') belum direkonsiliasi dengan pembiayaan neto ('.number_format($totals['financing_net'], 2, ',', '.').').';
			}
		}

		// Realisasi melebihi anggaran wajib punya penjelasan dan dasar perubahan.
		if (isset($by_type['realization']))
		{
			$base = $by_type['amended'] ?? ($by_type['original'] ?? NULL);
			if ($base)
			{
				foreach ($this->over_realizations($base, $by_type['realization']) as $over)
				{
					$errors[] = 'Realisasi "'.$over['name'].'" ('.number_format($over['realized'], 2, ',', '.')
						.') melebihi anggaran ('.number_format($over['budgeted'], 2, ',', '.')
						.') tanpa penjelasan dan dasar perubahan.';
				}
				if ( ! isset($by_type['amended']))
				{
					$warnings[] = 'Realisasi dibandingkan dengan anggaran murni karena anggaran perubahan belum diisi.';
				}
			}
		}

		foreach ($this->CI->db->where('budget_year_id', (int) $year->id)->get('budget_documents')->result() as $document)
		{
			if ($document->public_document_id && (int) $document->is_redacted !== 1)
			{
				$errors[] = 'Dokumen "'.$document->title.'" belum ditandai sudah disamarkan, jadi belum boleh publik.';
			}
		}

		if ((int) $year->is_provisional === 1)
		{
			$warnings[] = 'Data masih ditandai sementara; label "sementara" akan tampil pada halaman publik.';
		}
		return array('errors' => $errors, 'warnings' => $warnings);
	}

	/** Kategori induk yang nilainya tidak sama dengan jumlah anaknya dan tanpa catatan. */
	protected function parent_mismatches($revision)
	{
		$amounts = array();
		$notes = array();
		foreach ($this->lines($revision) as $line)
		{
			$amounts[(int) $line->category_id] = (float) $line->amount;
			$notes[(int) $line->category_id] = (string) $line->variance_note;
		}
		$children = array();
		$names = array();
		foreach ($this->CI->db->where('budget_year_id', (int) $revision->budget_year_id)->get('budget_categories')->result() as $row)
		{
			$names[(int) $row->id] = $row->name;
			if ($row->parent_id)
			{
				$children[(int) $row->parent_id][] = (int) $row->id;
			}
		}

		$out = array();
		foreach ($children as $parent_id => $child_ids)
		{
			if ( ! isset($amounts[$parent_id]))
			{
				continue;
			}
			$sum = 0.0;
			$found = FALSE;
			foreach ($child_ids as $child_id)
			{
				if (isset($amounts[$child_id]))
				{
					$sum += $amounts[$child_id];
					$found = TRUE;
				}
			}
			if ( ! $found)
			{
				continue;
			}
			if (abs($sum - $amounts[$parent_id]) > self::TOLERANCE && trim($notes[$parent_id]) === '')
			{
				$out[] = array('name' => $names[$parent_id], 'parent' => $amounts[$parent_id], 'children' => $sum);
			}
		}
		return $out;
	}

	/** Baris realisasi yang melebihi anggarannya tanpa catatan penjelasan. */
	protected function over_realizations($base_revision, $realization)
	{
		$budgeted = array();
		foreach ($this->lines($base_revision) as $line)
		{
			$budgeted[(int) $line->category_id] = (float) $line->amount;
		}
		$out = array();
		foreach ($this->lines($realization) as $line)
		{
			$limit = $budgeted[(int) $line->category_id] ?? 0.0;
			if ((float) $line->amount - $limit > self::TOLERANCE && trim((string) $line->variance_note) === '')
			{
				$out[] = array('name' => $line->name, 'realized' => (float) $line->amount, 'budgeted' => $limit);
			}
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Alur kerja
	// ------------------------------------------------------------------

	/** draft -> reconciling -> verified -> approved -> published. */
	public function transition($year, $action, $user_id, $reason = '')
	{
		$now = utc_now();
		$data = array('updated_at' => $now);

		switch ($action)
		{
			case 'rekonsiliasi':
				$data['status'] = 'reconciling';
				break;

			case 'verifikasi':
				if ( ! in_array($year->status, array('reconciling', 'draft'), TRUE))
				{
					throw new DomainRuleException('Verifikasi hanya dari tahap draft atau rekonsiliasi.', 409);
				}
				$report = $this->validate_year($year);
				if ( ! empty($report['errors']))
				{
					throw new DomainRuleException('Belum lolos pemeriksaan: '.implode(' ', $report['errors']), 422);
				}
				$data['status'] = 'verified';
				$data['verified_by'] = $user_id ? (int) $user_id : NULL;
				$data['verified_at'] = $now;
				break;

			case 'setujui':
				if ($year->status !== 'verified')
				{
					throw new DomainRuleException('Persetujuan hanya untuk tahun yang sudah diverifikasi.', 409);
				}
				$data['status'] = 'approved';
				$data['approved_by'] = $user_id ? (int) $user_id : NULL;
				$data['approved_at'] = $now;
				break;

			case 'kunci':
				$data['status'] = 'locked';
				$data['locked_at'] = $now;
				break;

			case 'buka-kunci':
				$data['status'] = 'reconciling';
				$data['locked_at'] = NULL;
				break;

			default:
				throw new DomainRuleException('Aksi alur tidak dikenal.', 404);
		}

		db_must($this->CI->db->where('id', (int) $year->id)->update('budget_years', $data), 'budget_years.transition');
		$this->CI->audit->log('finance.'.$action, 'budget_year', $year->public_id,
			array('reason' => mb_substr((string) $reason, 0, 200)), FALSE, 'budget_transparency');
	}

	// ------------------------------------------------------------------
	// Snapshot publikasi
	// ------------------------------------------------------------------

	public function build_snapshot($year)
	{
		$revisions = array();
		foreach ($this->revisions($year) as $revision)
		{
			$lines = array();
			foreach ($this->lines($revision) as $line)
			{
				$lines[] = array(
					'category_public_id' => $line->category_public_id,
					'category_id' => (int) $line->category_id,
					'parent_id' => $line->parent_id ? (int) $line->parent_id : NULL,
					'code' => $line->code,
					'name' => $line->name,
					'section' => $line->section,
					'level' => (int) $line->level,
					'amount' => (float) $line->amount,
					'variance_note' => $line->variance_note,
				);
			}
			$revisions[$revision->revision_type] = array(
				'type' => $revision->revision_type,
				'label' => $revision->label,
				'document_year' => $revision->document_year ? (int) $revision->document_year : NULL,
				'document_note' => $revision->document_note,
				'totals' => $this->totals($revision),
				'lines' => $lines,
			);
		}

		$projects = array();
		foreach ($this->CI->db->where('budget_year_id', (int) $year->id)->order_by('name')->get('budget_projects')->result() as $project)
		{
			$progress = array();
			foreach ($this->CI->db->where('project_id', (int) $project->id)->order_by('reported_on')
				->get('budget_project_progress')->result() as $row)
			{
				$progress[] = array(
					'reported_on' => $row->reported_on,
					'physical_percent' => (float) $row->physical_percent,
					'stage' => $row->stage,
					'note' => $row->note,
					'media_id' => $row->media_id ? (int) $row->media_id : NULL,
				);
			}
			$projects[] = array(
				'public_id' => $project->public_id,
				'name' => $project->name,
				'location_public' => $project->location_public,
				'target_output' => $project->target_output,
				'outcome_note' => $project->outcome_note,
				'status' => $project->status,
				'progress' => $progress,
			);
		}

		// Hanya dokumen yang sudah punya salinan publik DAN ditandai sudah disamarkan.
		$documents = array();
		foreach ($this->CI->db->where('budget_year_id', (int) $year->id)->where('is_redacted', 1)
			->where('public_document_id IS NOT NULL', NULL, FALSE)->get('budget_documents')->result() as $document)
		{
			$documents[] = array(
				'title' => $document->title,
				'document_year' => $document->document_year ? (int) $document->document_year : NULL,
				'public_document_id' => (int) $document->public_document_id,
				'note' => $document->note,
			);
		}

		return array(
			'year' => array(
				'public_id' => $year->public_id,
				'fiscal_year' => (int) $year->fiscal_year,
				'is_provisional' => (int) $year->is_provisional === 1,
				'note' => $year->note,
			),
			'revisions' => $revisions,
			'projects' => $projects,
			'documents' => $documents,
		);
	}

	public function publish($year, $reason, $user_id)
	{
		if ( ! in_array($year->status, array('approved', 'published', 'locked'), TRUE))
		{
			throw new DomainRuleException('Anggaran harus disetujui lebih dulu sebelum diterbitkan.', 409);
		}
		$report = $this->validate_year($year);
		if ( ! empty($report['errors']))
		{
			throw new DomainRuleException('Belum lolos pemeriksaan: '.implode(' ', $report['errors']), 422);
		}
		$snapshot = $this->build_snapshot($year);
		$now = utc_now();
		$revision = NULL;

		db_transaction(function () use ($year, $snapshot, $reason, $user_id, $now, &$revision) {
			$revision = (int) $this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'budget', 'target_id' => (int) $year->id))
				->get('cms_publication_snapshots')->row('revision_no') + 1;
			$this->CI->db->where(array('target_type' => 'budget', 'target_id' => (int) $year->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'budget',
				'target_id' => (int) $year->id,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
				'reason' => mb_substr((string) $reason, 0, 500),
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_publication_snapshots.budget');
			db_must($this->CI->db->where('id', (int) $year->id)->update('budget_years', array(
				'status' => 'published', 'published_at' => $now, 'updated_at' => $now,
			)), 'budget_years.publish');
		});

		$this->after_change('finance.published', $year, array('revision' => $revision));
		return $revision;
	}

	/** Menarik snapshot menghapusnya dari beranda, sitemap, pencarian, dan cache publik. */
	public function unpublish($year, $reason, $user_id)
	{
		$now = utc_now();
		db_transaction(function () use ($year, $now) {
			$this->CI->db->where(array('target_type' => 'budget', 'target_id' => (int) $year->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			$this->CI->db->where('id', (int) $year->id)->update('budget_years', array(
				'status' => 'approved', 'published_at' => NULL, 'updated_at' => $now,
			));
		});
		$this->after_change('finance.unpublished', $year, array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	public function snapshots($year, $limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'budget', 's.target_id' => (int) $year->id))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	protected function after_change($action, $year, array $meta)
	{
		$this->CI->audit->log($action, 'budget_year', $year->public_id, $meta, FALSE, 'budget_transparency');
		$this->CI->audit->event('info', 'Anggaran '.$year->fiscal_year.': '.$action.'.', 'budget_transparency', $meta);
		$this->CI->public_cache->invalidate_page('transparansi');
		$this->CI->public_cache->invalidate_page('home');
		$this->CI->public_cache->forget_group('listing');
	}

	// ------------------------------------------------------------------
	// Pembacaan publik
	// ------------------------------------------------------------------

	public function published_years()
	{
		$cached = $this->CI->public_cache->get('listing', 'budget_years');
		if (is_array($cached))
		{
			return $cached;
		}
		$rows = $this->CI->db->select('y.public_id, y.fiscal_year, y.is_provisional')
			->from('budget_years y')
			->join('cms_publication_snapshots s', "s.target_type = 'budget' AND s.target_id = y.id AND s.superseded_at IS NULL")
			->where('y.status', 'published')
			->order_by('y.fiscal_year', 'DESC')->get()->result_array();
		$this->CI->public_cache->set('listing', 'budget_years', $rows, 1800);
		return $rows;
	}

	public function published_budget($fiscal_year = NULL)
	{
		$key = 'budget_'.($fiscal_year ? (int) $fiscal_year : 'latest');
		$cached = $this->CI->public_cache->get('listing', $key);
		if (is_array($cached))
		{
			return $cached;
		}
		$this->CI->db->select('s.snapshot_json, s.revision_no, s.published_at')
			->from('cms_publication_snapshots s')->join('budget_years y', 'y.id = s.target_id')
			->where('s.target_type', 'budget')->where('s.superseded_at IS NULL', NULL, FALSE)
			->where('y.status', 'published');
		if ($fiscal_year)
		{
			$this->CI->db->where('y.fiscal_year', (int) $fiscal_year);
		}
		$row = $this->CI->db->order_by('y.fiscal_year', 'DESC')->limit(1)->get()->row();
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
}
