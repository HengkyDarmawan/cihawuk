<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dataset statistik berversi (modul-backend §11, modul-frontend §8).
 *
 * Alur: nilai statistik diverifikasi (`data.review`) → dataset dirangkai dari indikator yang
 * sudah terverifikasi → pemeriksaan otomatis (satuan, total vs komponen, aturan grafik,
 * ambang data kecil) → penerbitan sebagai snapshot (`data.publish`).
 *
 * Halaman publik hanya membaca snapshot. Nilai mentah observasi tidak pernah ditimpa dan
 * angka tidak pernah disalin dari dokumen sumber tanpa verifikasi manusia.
 */
class DatasetService {

	/** @var CI_Controller */
	protected $CI;

	const STATUSES = array(
		'draft' => 'Draft',
		'in_review' => 'Menunggu review',
		'published' => 'Terbit',
		'superseded' => 'Digantikan versi baru',
		'archived' => 'Diarsipkan',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('datasets', TRUE);
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
		$this->CI->load->library('ContentService', NULL, 'content_service');
	}

	// ------------------------------------------------------------------
	// Registry
	// ------------------------------------------------------------------

	public function themes()
	{
		return (array) $this->CI->config->item('dataset_themes', 'datasets');
	}

	public function sensitivities()
	{
		return (array) $this->CI->config->item('dataset_sensitivity', 'datasets');
	}

	public function chart_types()
	{
		return (array) $this->CI->config->item('dataset_chart_types', 'datasets');
	}

	public function composition_charts()
	{
		return (array) $this->CI->config->item('dataset_composition_charts', 'datasets');
	}

	public function composition_totals()
	{
		return (array) $this->CI->config->item('dataset_composition_totals', 'datasets');
	}

	public function sensitive_themes()
	{
		return (array) $this->CI->config->item('dataset_sensitive_themes', 'datasets');
	}

	public function small_count_threshold()
	{
		return (int) $this->CI->config->item('dataset_small_count_threshold', 'datasets');
	}

	// ------------------------------------------------------------------
	// Dataset dan versi
	// ------------------------------------------------------------------

	public function datasets(array $filters = array())
	{
		$this->CI->db->select('d.*, v.period_year, v.validation_status')
			->from('datasets d')->join('dataset_versions v', 'v.id = d.current_version_id', 'left')
			->where('d.archived_at IS NULL', NULL, FALSE);
		if ( ! empty($filters['theme']))
		{
			$this->CI->db->where('d.theme', (string) $filters['theme']);
		}
		if ( ! empty($filters['status']))
		{
			$this->CI->db->where('d.status', (string) $filters['status']);
		}
		return $this->CI->db->order_by('d.sort_order')->order_by('d.id')->get()->result();
	}

	public function dataset($public_id)
	{
		return $this->CI->db->get_where('datasets', array('public_id' => (string) $public_id))->row();
	}

	public function dataset_by_slug($slug)
	{
		return $this->CI->db->get_where('datasets', array('slug' => (string) $slug))->row();
	}

	public function version($version_id)
	{
		return $this->CI->db->get_where('dataset_versions', array('id' => (int) $version_id))->row();
	}

	public function versions($dataset)
	{
		return $this->CI->db->where('dataset_id', (int) $dataset->id)
			->order_by('version_no', 'DESC')->get('dataset_versions')->result();
	}

	public function create_dataset(array $input, $user_id)
	{
		$clean = $this->validate_meta($input);
		$slug = $this->CI->content_service->slugify((string) ($input['slug'] ?? $clean['name']), 'dataset');
		if ($this->dataset_by_slug($slug))
		{
			throw new DomainRuleException('Slug dataset sudah dipakai.', 409, array('slug' => 'Sudah dipakai.'));
		}
		$now = utc_now();
		$dataset_id = NULL;
		db_transaction(function () use ($clean, $slug, $user_id, $now, &$dataset_id) {
			db_must($this->CI->db->insert('datasets', array_merge($clean, array(
				'public_id' => $this->CI->crypto->public_id(),
				'slug' => $slug,
				'status' => 'draft',
				'sort_order' => (int) ($this->CI->db->select_max('sort_order')->get('datasets')->row('sort_order') ?: 0) + 10,
				'created_by' => $user_id ? (int) $user_id : NULL,
				'created_at' => $now,
				'updated_at' => $now,
			))), 'datasets.insert');
			$dataset_id = (int) $this->CI->db->insert_id();
		});
		$dataset = $this->CI->db->get_where('datasets', array('id' => $dataset_id))->row();
		$this->save_version($dataset, $input, $user_id);
		$this->CI->audit->log('dataset.created', 'dataset', $dataset->public_id, array('slug' => $slug), FALSE, 'village_data');
		return $this->CI->db->get_where('datasets', array('id' => $dataset_id))->row();
	}

	protected function validate_meta(array $input)
	{
		$errors = array();
		$name = trim((string) ($input['name'] ?? ''));
		$theme = (string) ($input['theme'] ?? '');
		$sensitivity = (string) ($input['sensitivity'] ?? 'public');
		$coverage = trim((string) ($input['coverage'] ?? 'Desa Cihawuk'));

		if (mb_strlen($name) < 3)
		{
			$errors['name'] = 'Nama dataset wajib diisi (minimal 3 karakter).';
		}
		if ( ! isset($this->themes()[$theme]))
		{
			$errors['theme'] = 'Tema tidak dikenal.';
		}
		if ( ! isset($this->sensitivities()[$sensitivity]))
		{
			$errors['sensitivity'] = 'Tingkat sensitivitas tidak dikenal.';
		}
		if ($coverage === '')
		{
			$errors['coverage'] = 'Cakupan wilayah wajib diisi.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali isian dataset.', 422, $errors);
		}
		return array(
			'name' => mb_substr($name, 0, 160),
			'theme' => $theme,
			'sensitivity' => $sensitivity,
			'coverage' => mb_substr($coverage, 0, 120),
			'description' => trim((string) ($input['description'] ?? '')) === '' ? NULL : mb_substr((string) $input['description'], 0, 600),
		);
	}

	/** Simpan metadata dataset dan buat versi baru; versi lama tetap utuh. */
	public function save_version($dataset, array $input, $user_id)
	{
		$clean = $this->validate_meta($input);
		$year = (int) ($input['period_year'] ?? 0);
		if ($year < 1900 OR $year > 2100)
		{
			throw new DomainRuleException('Tahun periode tidak valid.', 422, array('period_year' => 'Isi tahun, misalnya 2023.'));
		}
		$source_id = empty($input['source_id']) ? NULL : (int) $input['source_id'];
		if ($source_id !== NULL && $this->CI->db->where('id', $source_id)->count_all_results('source_documents') === 0)
		{
			throw new DomainRuleException('Dokumen sumber tidak ditemukan.', 422, array('source_id' => 'Sumber tidak dikenal.'));
		}

		$previous = $dataset->current_version_id ? $this->version($dataset->current_version_id) : NULL;
		$now = utc_now();
		$version_id = NULL;
		db_transaction(function () use ($dataset, $clean, $input, $year, $source_id, $user_id, $now, $previous, &$version_id) {
			$next = (int) ($this->CI->db->select_max('version_no')->where('dataset_id', (int) $dataset->id)
				->get('dataset_versions')->row('version_no') ?: 0) + 1;
			db_must($this->CI->db->insert('dataset_versions', array(
				'dataset_id' => (int) $dataset->id,
				'version_no' => $next,
				'period_year' => $year,
				'period_label' => trim((string) ($input['period_label'] ?? '')) === '' ? NULL : mb_substr((string) $input['period_label'], 0, 60),
				'methodology' => trim((string) ($input['methodology'] ?? '')) === '' ? NULL : mb_substr((string) $input['methodology'], 0, 1000),
				'quality_note' => trim((string) ($input['quality_note'] ?? '')) === '' ? NULL : mb_substr((string) $input['quality_note'], 0, 1000),
				'source_id' => $source_id,
				'source_note' => trim((string) ($input['source_note'] ?? '')) === '' ? NULL : mb_substr((string) $input['source_note'], 0, 255),
				'validation_status' => 'pending',
				'created_by' => $user_id ? (int) $user_id : NULL,
				'created_at' => $now,
			)), 'dataset_versions.insert');
			$version_id = (int) $this->CI->db->insert_id();

			// Seri indikator diwariskan dari versi sebelumnya agar pengelola tidak menyusun ulang.
			if ($previous)
			{
				foreach ($this->series($previous->id) as $row)
				{
					db_must($this->CI->db->insert('dataset_series', array(
						'dataset_version_id' => $version_id,
						'indicator_id' => (int) $row->indicator_id,
						'display_order' => (int) $row->display_order,
						'chart_type' => $row->chart_type,
						'is_composition' => (int) $row->is_composition,
						'note' => $row->note,
						'created_at' => $now,
					)), 'dataset_series.copy');
				}
			}

			$status = in_array($dataset->status, array('published', 'superseded', 'archived'), TRUE) ? $dataset->status : 'draft';
			db_must($this->CI->db->where('id', (int) $dataset->id)->update('datasets', array_merge($clean, array(
				'current_version_id' => $version_id,
				'status' => $status,
				'updated_at' => $now,
			))), 'datasets.version');
		});
		$this->CI->audit->log('dataset.version_saved', 'dataset', $dataset->public_id, array('version_id' => $version_id), FALSE, 'village_data');
		return $version_id;
	}

	// ------------------------------------------------------------------
	// Seri indikator
	// ------------------------------------------------------------------

	public function series($version_id)
	{
		return $this->CI->db->select('s.*, i.code, i.label, i.unit, i.value_type, i.group_code, i.composition_group, i.definition')
			->from('dataset_series s')->join('statistic_indicators i', 'i.id = s.indicator_id')
			->where('s.dataset_version_id', (int) $version_id)
			->order_by('s.display_order')->order_by('s.id')->get()->result();
	}

	public function add_series($version, array $input)
	{
		$indicator_id = (int) ($input['indicator_id'] ?? 0);
		$indicator = $this->CI->db->get_where('statistic_indicators', array('id' => $indicator_id))->row();
		if ( ! $indicator)
		{
			throw new DomainRuleException('Indikator tidak ditemukan.', 422, array('indicator_id' => 'Indikator tidak dikenal.'));
		}
		$chart = (string) ($input['chart_type'] ?? 'bar');
		if ( ! isset($this->chart_types()[$chart]))
		{
			throw new DomainRuleException('Jenis grafik tidak dikenal.', 422, array('chart_type' => 'Pilih dari daftar.'));
		}
		if (in_array($chart, $this->composition_charts(), TRUE) && empty($indicator->composition_group))
		{
			throw new DomainRuleException(
				'Donut hanya untuk indikator yang termasuk satu komposisi. Indikator ini tidak punya kelompok komposisi, '.
				'sehingga kategorinya berpotensi tumpang tindih.', 422, array('chart_type' => 'Pilih bar atau kartu angka.')
			);
		}
		if ($this->CI->db->where(array('dataset_version_id' => (int) $version->id, 'indicator_id' => $indicator_id))
			->count_all_results('dataset_series') > 0)
		{
			throw new DomainRuleException('Indikator itu sudah ada pada dataset ini.', 409);
		}
		$order = (int) ($this->CI->db->select_max('display_order')->where('dataset_version_id', (int) $version->id)
			->get('dataset_series')->row('display_order') ?: 0) + 10;
		db_must($this->CI->db->insert('dataset_series', array(
			'dataset_version_id' => (int) $version->id,
			'indicator_id' => $indicator_id,
			'display_order' => $order,
			'chart_type' => $chart,
			'is_composition' => empty($indicator->composition_group) ? 0 : 1,
			'note' => trim((string) ($input['note'] ?? '')) === '' ? NULL : mb_substr((string) $input['note'], 0, 255),
			'created_at' => utc_now(),
		)), 'dataset_series.insert');
		$this->invalidate_validation($version);
	}

	public function remove_series($version, $series_id)
	{
		$this->CI->db->where(array('id' => (int) $series_id, 'dataset_version_id' => (int) $version->id))->delete('dataset_series');
		$this->invalidate_validation($version);
	}

	public function move_series($version, $series_id, $direction)
	{
		$rows = $this->series($version->id);
		$ids = array_map(function ($row) { return (int) $row->id; }, $rows);
		$pos = array_search((int) $series_id, $ids, TRUE);
		if ($pos === FALSE)
		{
			return;
		}
		$target = ($direction === 'up') ? $pos - 1 : $pos + 1;
		if ($target < 0 OR $target >= count($ids))
		{
			return;
		}
		$tmp = $ids[$pos];
		$ids[$pos] = $ids[$target];
		$ids[$target] = $tmp;
		db_transaction(function () use ($ids, $version) {
			$order = 10;
			foreach ($ids as $id)
			{
				db_must($this->CI->db->where(array('id' => (int) $id, 'dataset_version_id' => (int) $version->id))
					->update('dataset_series', array('display_order' => $order)), 'dataset_series.order');
				$order += 10;
			}
		});
	}

	protected function invalidate_validation($version)
	{
		$this->CI->db->where('id', (int) $version->id)->update('dataset_versions', array(
			'validation_status' => 'pending', 'validation_report' => NULL,
		));
	}

	// ------------------------------------------------------------------
	// Nilai
	// ------------------------------------------------------------------

	/** Nilai statistik untuk setiap seri pada tahun versi ini (wilayah desa). */
	public function values($version)
	{
		$series = $this->series($version->id);
		if (empty($series))
		{
			return array();
		}
		$ids = array_map(function ($row) { return (int) $row->indicator_id; }, $series);
		$rows = $this->CI->db->select('v.*, i.code')
			->from('statistic_values v')->join('statistic_indicators i', 'i.id = v.indicator_id')
			->where_in('v.indicator_id', $ids)->where('v.source_year', (int) $version->period_year)
			->get()->result();
		$by_indicator = array();
		foreach ($rows as $row)
		{
			$by_indicator[(int) $row->indicator_id] = $row;
		}
		$out = array();
		foreach ($series as $row)
		{
			$row->value = $by_indicator[(int) $row->indicator_id] ?? NULL;
			$out[] = $row;
		}
		return $out;
	}

	/** Verifikasi nilai satu indikator pada tahun versi ini; memerlukan `data.review`. */
	public function verify_value($version, $indicator_id, $verified, $user_id, $note = '')
	{
		$value = $this->CI->db->where(array(
			'indicator_id' => (int) $indicator_id, 'source_year' => (int) $version->period_year,
		))->get('statistic_values')->row();
		if ( ! $value)
		{
			throw new DomainRuleException('Nilai untuk indikator itu belum ada pada tahun ini.', 404);
		}
		db_must($this->CI->db->where('id', (int) $value->id)->update('statistic_values', array(
			'verification_status' => $verified ? 'verified' : 'pending',
			'reviewed_by' => $user_id ? (int) $user_id : NULL,
			'reviewed_at' => utc_now(),
			'updated_at' => utc_now(),
		)), 'statistic_values.verify');
		$this->CI->audit->log($verified ? 'dataset.value_verified' : 'dataset.value_unverified', 'statistic_value',
			(string) $value->id, array('note' => mb_substr((string) $note, 0, 200)), FALSE, 'village_data');
		$this->invalidate_validation($version);
	}

	// ------------------------------------------------------------------
	// Validasi sebelum terbit (modul-backend §11.5, modul-frontend §8.3)
	// ------------------------------------------------------------------

	public function validate_version($dataset, $version, $store = TRUE)
	{
		$errors = array();
		$warnings = array();
		$rows = $this->values($version);

		if (empty($rows))
		{
			$errors[] = 'Dataset belum memiliki indikator.';
		}
		if (mb_strlen(trim((string) $version->methodology)) < 20)
		{
			$errors[] = 'Metodologi wajib dijelaskan (minimal 20 karakter) agar pembaca tahu angka ini dihitung bagaimana.';
		}
		if ($version->source_id === NULL && trim((string) $version->source_note) === '')
		{
			$errors[] = 'Sumber data wajib diisi: pilih dokumen sumber atau tuliskan keterangan sumbernya.';
		}

		$threshold = $this->small_count_threshold();
		$sensitive = in_array($dataset->theme, $this->sensitive_themes(), TRUE);
		$units = array();

		foreach ($rows as $row)
		{
			$label = $row->label.' ('.$row->code.')';
			if ( ! $row->value)
			{
				$errors[] = 'Nilai '.$label.' untuk tahun '.$version->period_year.' belum ada.';
				continue;
			}
			if ($row->value->verification_status !== 'verified')
			{
				$errors[] = 'Nilai '.$label.' belum diverifikasi.';
			}
			if ($row->value->numeric_value === NULL && ($row->value->text_value === NULL OR $row->value->text_value === ''))
			{
				// Kosong berarti tidak diketahui, bukan nol.
				$errors[] = 'Nilai '.$label.' masih kosong. Kosong berarti tidak diketahui, bukan nol.';
			}
			if ($sensitive && $row->value->numeric_value !== NULL && (float) $row->value->numeric_value < $threshold
				&& (int) $row->value->suppressed !== 1)
			{
				$errors[] = 'Nilai '.$label.' di bawah ambang '.$threshold.' pada tema sensitif. '.
					'Gabungkan kategori atau tandai nilai ini disamarkan sebelum diterbitkan.';
			}
			$units[$row->unit] = TRUE;
		}

		// Aturan komposisi: donut hanya untuk komposisi lengkap dengan total yang cocok.
		$groups = array();
		foreach ($rows as $row)
		{
			if ($row->composition_group)
			{
				$groups[$row->composition_group][] = $row;
			}
			elseif (in_array($row->chart_type, $this->composition_charts(), TRUE))
			{
				$errors[] = 'Indikator '.$row->label.' dipasang sebagai donut, padahal kategorinya tidak membentuk komposisi '.
					'yang totalnya pasti. Gunakan bar.';
			}
		}
		foreach ($groups as $group => $members)
		{
			$uses_composition_chart = FALSE;
			foreach ($members as $member)
			{
				if (in_array($member->chart_type, $this->composition_charts(), TRUE))
				{
					$uses_composition_chart = TRUE;
				}
			}
			$member_units = array();
			foreach ($members as $member)
			{
				$member_units[$member->unit] = TRUE;
			}
			if (count($member_units) > 1)
			{
				$errors[] = 'Komposisi "'.$group.'" memakai satuan berbeda ('.implode(', ', array_keys($member_units)).').';
				continue;
			}
			if ( ! $uses_composition_chart)
			{
				continue;
			}
			$expected = $this->CI->db->select('id')->where('composition_group', $group)
				->get('statistic_indicators')->num_rows();
			if (count($members) < $expected)
			{
				$errors[] = 'Komposisi "'.$group.'" belum lengkap: '.count($members).' dari '.$expected.' komponen. '.
					'Donut hanya sah bila seluruh komponen ikut.';
				continue;
			}
			$total_code = $this->composition_totals()[$group] ?? NULL;
			if ($total_code === NULL)
			{
				continue;
			}
			$sum = 0.0;
			$complete = TRUE;
			foreach ($members as $member)
			{
				if ( ! $member->value OR $member->value->numeric_value === NULL)
				{
					$complete = FALSE;
					break;
				}
				$sum += (float) $member->value->numeric_value;
			}
			$total = $this->CI->db->select('v.numeric_value')->from('statistic_values v')
				->join('statistic_indicators i', 'i.id = v.indicator_id')
				->where('i.code', $total_code)->where('v.source_year', (int) $version->period_year)
				->where('v.verification_status', 'verified')->get()->row();
			if ($complete && $total && $total->numeric_value !== NULL && abs($sum - (float) $total->numeric_value) > 0.001)
			{
				$errors[] = 'Jumlah komponen "'.$group.'" ('.rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.').') '.
					'tidak sama dengan '.$total_code.' ('.rtrim(rtrim(number_format((float) $total->numeric_value, 2, '.', ''), '0'), '.').') '.
					'pada tahun '.$version->period_year.'.';
			}
			if ($complete && ! $total)
			{
				$warnings[] = 'Indikator total '.$total_code.' belum terverifikasi untuk tahun '.$version->period_year.
					', jadi jumlah komponen tidak dapat dicocokkan.';
			}
		}

		if (count($units) > 1)
		{
			$warnings[] = 'Dataset memuat lebih dari satu satuan ('.implode(', ', array_keys($units)).'); '.
				'pastikan grafiknya tidak menggabungkan satuan berbeda pada satu sumbu.';
		}
		if ($dataset->sensitivity === 'restricted')
		{
			$errors[] = 'Dataset bersensitivitas "Terbatas" tidak boleh diterbitkan ke halaman publik.';
		}

		$report = array('errors' => $errors, 'warnings' => $warnings, 'checked_at' => utc_now());
		if ($store)
		{
			$this->CI->db->where('id', (int) $version->id)->update('dataset_versions', array(
				'validation_status' => $errors ? 'failed' : 'passed',
				'validation_report' => json_encode($report, JSON_UNESCAPED_UNICODE),
			));
		}
		return $report;
	}

	public function validation_report($version)
	{
		$decoded = json_decode((string) $version->validation_report, TRUE);
		return is_array($decoded) ? $decoded : NULL;
	}

	// ------------------------------------------------------------------
	// Publikasi
	// ------------------------------------------------------------------

	public function submit_review($dataset, $user_id)
	{
		if ( ! in_array($dataset->status, array('draft', 'superseded'), TRUE))
		{
			throw new DomainRuleException('Dataset ini tidak berada pada status yang dapat diajukan.', 409);
		}
		db_must($this->CI->db->where('id', (int) $dataset->id)->update('datasets', array(
			'status' => 'in_review', 'updated_at' => utc_now(),
		)), 'datasets.submit');
		$this->CI->audit->log('dataset.submitted_review', 'dataset', $dataset->public_id, array(), FALSE, 'village_data');
	}

	public function build_snapshot($dataset, $version)
	{
		$series = array();
		foreach ($this->values($version) as $row)
		{
			$numeric = NULL;
			if ($row->value && $row->value->numeric_value !== NULL)
			{
				// Indikator bertipe integer tidak boleh tampil sebagai 6809.0 pada kartu, tabel dan CSV.
				$numeric = ($row->value_type === 'integer')
					? (int) $row->value->numeric_value
					: (float) $row->value->numeric_value;
			}
			$suppressed = $row->value ? (int) $row->value->suppressed === 1 : FALSE;
			$series[] = array(
				'code' => $row->code,
				'label' => $row->label,
				'unit' => $row->unit,
				'value_type' => $row->value_type,
				'definition' => $row->definition,
				'chart_type' => $row->chart_type,
				'composition_group' => $row->composition_group,
				'note' => $row->note,
				'value' => $suppressed ? NULL : $numeric,
				'text_value' => ($row->value && ! $suppressed) ? $row->value->text_value : NULL,
				'suppressed' => $suppressed,
			);
		}
		$source = $version->source_id
			? $this->CI->db->select('source_code, title, source_year')->get_where('source_documents', array('id' => (int) $version->source_id))->row()
			: NULL;

		return array(
			'dataset' => array(
				'public_id' => $dataset->public_id,
				'slug' => $dataset->slug,
				'name' => $dataset->name,
				'theme' => $dataset->theme,
				'description' => $dataset->description,
				'coverage' => $dataset->coverage,
				'sensitivity' => $dataset->sensitivity,
			),
			'version' => array(
				'version_no' => (int) $version->version_no,
				'period_year' => (int) $version->period_year,
				'period_label' => $version->period_label,
				'methodology' => $version->methodology,
				'quality_note' => $version->quality_note,
				'source_code' => $source ? $source->source_code : NULL,
				'source_title' => $source ? $source->title : NULL,
				'source_note' => $version->source_note,
			),
			'series' => $series,
		);
	}

	public function publish($dataset, $reason, $user_id)
	{
		if ( ! $dataset->current_version_id)
		{
			throw new DomainRuleException('Dataset belum memiliki versi.', 409);
		}
		$version = $this->version($dataset->current_version_id);
		$report = $this->validate_version($dataset, $version);
		if ( ! empty($report['errors']))
		{
			throw new DomainRuleException('Dataset belum lolos pemeriksaan: '.$report['errors'][0], 422,
				array('validation' => implode(' | ', $report['errors'])));
		}

		$snapshot = $this->build_snapshot($dataset, $version);
		$now = utc_now();
		$revision = NULL;
		db_transaction(function () use ($dataset, $version, $snapshot, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;
			db_must($this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.dataset_supersede');
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'dataset',
				'target_id' => (int) $dataset->id,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
				'reason' => ($reason === '' OR $reason === NULL) ? NULL : mb_substr((string) $reason, 0, 500),
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_snapshots.dataset_insert');

			db_must($this->CI->db->where('id', (int) $dataset->id)->update('datasets', array(
				'status' => 'published',
				'published_version_id' => (int) $version->id,
				'published_at' => $now,
				'updated_at' => $now,
			)), 'datasets.publish');

			// Nilai yang dipakai dataset ikut berstatus terbit sehingga beranda dan API internal konsisten.
			$indicator_ids = array();
			foreach ($this->series($version->id) as $row)
			{
				$indicator_ids[] = (int) $row->indicator_id;
			}
			if ($indicator_ids)
			{
				db_must($this->CI->db->where_in('indicator_id', $indicator_ids)
					->where('source_year', (int) $version->period_year)
					->update('statistic_values', array(
						'publication_status' => 'published',
						'dataset_version_id' => (int) $version->id,
						'published_at' => $now,
						'updated_at' => $now,
					)), 'statistic_values.publish');
			}
		});

		$this->after_change($dataset, 'dataset.published', array('revision' => $revision));
		return $revision;
	}

	public function unpublish($dataset, $reason, $user_id)
	{
		if ($dataset->status !== 'published')
		{
			throw new DomainRuleException('Dataset ini sedang tidak terbit.', 409);
		}
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan penarikan (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$now = utc_now();
		db_transaction(function () use ($dataset, $now) {
			db_must($this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.dataset_unpublish');
			db_must($this->CI->db->where('id', (int) $dataset->id)->update('datasets', array(
				'status' => 'draft', 'published_at' => NULL, 'updated_at' => $now,
			)), 'datasets.unpublish');
			if ($dataset->published_version_id)
			{
				$this->CI->db->where('dataset_version_id', (int) $dataset->published_version_id)
					->update('statistic_values', array('publication_status' => 'draft', 'updated_at' => $now));
			}
		});
		$this->after_change($dataset, 'dataset.unpublished', array('reason' => mb_substr($reason, 0, 200)));
	}

	public function rollback($dataset, $snapshot_id, $reason, $user_id)
	{
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan rollback (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$source = $this->CI->db->get_where('cms_publication_snapshots', array(
			'id' => (int) $snapshot_id, 'target_type' => 'dataset', 'target_id' => (int) $dataset->id,
		))->row();
		if ( ! $source)
		{
			throw new DomainRuleException('Revisi dataset tidak ditemukan.', 404);
		}
		$now = utc_now();
		$revision = NULL;
		db_transaction(function () use ($dataset, $source, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;
			db_must($this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.dataset_supersede');
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'dataset',
				'target_id' => (int) $dataset->id,
				'revision_no' => $revision,
				'snapshot_json' => $source->snapshot_json,
				'reason' => mb_substr($reason, 0, 500),
				'rolled_back_from' => (int) $source->id,
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_snapshots.dataset_rollback');
			db_must($this->CI->db->where('id', (int) $dataset->id)->update('datasets', array(
				'status' => 'published', 'published_at' => $now, 'updated_at' => $now,
			)), 'datasets.rollback');
		});
		$this->after_change($dataset, 'dataset.rolled_back', array('revision' => $revision, 'from_revision' => (int) $source->revision_no));
		return $revision;
	}

	public function archive($dataset, $reason, $user_id)
	{
		$now = utc_now();
		db_transaction(function () use ($dataset, $now) {
			$this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			db_must($this->CI->db->where('id', (int) $dataset->id)->update('datasets', array(
				'status' => 'archived', 'archived_at' => $now, 'published_at' => NULL, 'updated_at' => $now,
			)), 'datasets.archive');
		});
		$this->after_change($dataset, 'dataset.archived', array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	protected function after_change($dataset, $action, array $meta)
	{
		$this->CI->audit->log($action, 'dataset', $dataset->public_id, $meta, FALSE, 'village_data');
		$this->CI->audit->event('info', 'Dataset "'.$dataset->slug.'": '.$action.'.', 'village_data', $meta);
		$this->CI->public_cache->invalidate_page('data-desa');
		$this->CI->public_cache->forget_group('listing');
		$this->invalidate_pages_using($dataset);
	}

	/**
	 * Section statistik menyimpan rujukan dataset, bukan angkanya, dan di-resolve saat render.
	 * Karena itu halaman yang memasang dataset ini harus ikut dibuang cachenya begitu
	 * dataset terbit, ditarik, atau di-rollback. Halaman lain tidak disentuh.
	 */
	protected function invalidate_pages_using($dataset)
	{
		$rows = $this->CI->db->select('p.page_key')->from('cms_publication_snapshots s')
			->join('cms_pages p', 'p.id = s.target_id')
			->where('s.target_type', 'page')->where('s.superseded_at IS NULL', NULL, FALSE)
			->like('s.snapshot_json', '"dataset_slug":"'.$dataset->slug.'"')
			->get()->result();
		foreach ($rows as $row)
		{
			$this->CI->public_cache->invalidate_page($row->page_key);
		}
	}

	public function snapshots($dataset, $limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'dataset', 's.target_id' => (int) $dataset->id))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	// ------------------------------------------------------------------
	// Pembacaan publik
	// ------------------------------------------------------------------

	/** Seluruh dataset terbit dari snapshot aktif; dipakai halaman Data Desa dan sitemap. */
	public function published_datasets()
	{
		$cached = $this->CI->public_cache->get('listing', 'datasets');
		if (is_array($cached))
		{
			return $cached;
		}
		$rows = $this->CI->db->select('s.snapshot_json, s.revision_no, s.published_at, d.slug')
			->from('cms_publication_snapshots s')->join('datasets d', 'd.id = s.target_id')
			->where('s.target_type', 'dataset')->where('s.superseded_at IS NULL', NULL, FALSE)
			->where('d.status', 'published')
			// Lapis kedua: dataset yang sensitivitasnya dinaikkan setelah terbit tidak ikut terbaca.
			->where('d.sensitivity !=', 'restricted')
			->order_by('d.sort_order')->order_by('d.id')->get()->result();
		$out = array();
		foreach ($rows as $row)
		{
			$snapshot = json_decode((string) $row->snapshot_json, TRUE);
			if ( ! is_array($snapshot) OR empty($snapshot['dataset']))
			{
				continue;
			}
			$snapshot['revision_no'] = (int) $row->revision_no;
			$snapshot['published_at'] = $row->published_at;
			$out[] = $snapshot;
		}
		$this->CI->public_cache->set('listing', 'datasets', $out, 1800);
		return $out;
	}

	public function published_dataset($slug)
	{
		foreach ($this->published_datasets() as $snapshot)
		{
			if ($snapshot['dataset']['slug'] === (string) $slug)
			{
				return $snapshot;
			}
		}
		return NULL;
	}

	/**
	 * Bentuk tampilan satu snapshot: kartu angka, grafik, dan tabel setara.
	 * Setiap grafik selalu berpasangan dengan tabel angka yang sama isinya.
	 */
	public function presentation(array $snapshot)
	{
		$cards = array();
		$bars = array();
		$compositions = array();

		foreach ($snapshot['series'] as $series)
		{
			if ($series['chart_type'] === 'number' OR $series['value'] === NULL)
			{
				$cards[] = $series;
				continue;
			}
			if (in_array($series['chart_type'], $this->composition_charts(), TRUE) && $series['composition_group'])
			{
				$compositions[$series['composition_group']][] = $series;
				continue;
			}
			$bars[$series['chart_type']][] = $series;
		}

		$charts = array();
		foreach ($compositions as $group => $members)
		{
			$charts[] = array(
				'type' => 'donut',
				'title' => 'Komposisi '.$members[0]['label'].' dan sejenisnya',
				'unit' => $members[0]['unit'],
				'labels' => array_map(function ($m) { return $m['label']; }, $members),
				'values' => array_map(function ($m) { return $m['value']; }, $members),
				'series' => $members,
			);
		}
		foreach ($bars as $type => $members)
		{
			$charts[] = array(
				'type' => ($type === 'bar_horizontal') ? 'bar_horizontal' : 'bar',
				'title' => $snapshot['dataset']['name'],
				'unit' => $members[0]['unit'],
				'labels' => array_map(function ($m) { return $m['label']; }, $members),
				'values' => array_map(function ($m) { return $m['value']; }, $members),
				'series' => $members,
			);
		}

		return array('cards' => $cards, 'charts' => $charts, 'table' => $snapshot['series']);
	}

	/** Baris CSV untuk unduhan publik; nilai disamarkan tetap ditandai, bukan dihapus diam-diam. */
	public function csv_rows(array $snapshot)
	{
		$rows = array(array('indikator', 'kode', 'nilai', 'satuan', 'tahun', 'sumber', 'catatan'));
		$source = $snapshot['version']['source_code'] ?: (string) $snapshot['version']['source_note'];
		foreach ($snapshot['series'] as $series)
		{
			$rows[] = array(
				$series['label'],
				$series['code'],
				$series['suppressed'] ? 'disamarkan' : ($series['value'] === NULL ? '' : $series['value']),
				$series['unit'],
				$snapshot['version']['period_year'],
				$source,
				(string) $series['note'],
			);
		}
		return $rows;
	}
}
