<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Profil desa sebagai blok terstruktur berversi (modul-backend 10.1 dan 10.2).
 *
 * Alur: editor mengisi field dari registry tertutup -> setiap simpan membuat versi baru ->
 * penerbit memverifikasi blok -> seluruh profil diterbitkan sebagai SATU snapshot
 * (`cms_publication_snapshots`, target_type `profile`, target_id 0).
 *
 * Halaman publik hanya membaca snapshot. Pengelola tidak dapat menulis HTML; seluruh isi
 * disimpan sebagai data terstruktur dan dirender oleh template.
 */
class ProfileService {

	/** @var CI_Controller */
	protected $CI;

	const STATUSES = array(
		'draft' => 'Draft',
		'in_review' => 'Menunggu review',
		'published' => 'Terbit',
		'archived' => 'Diarsipkan',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('profile_blocks', TRUE);
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
	}

	// ------------------------------------------------------------------
	// Registry
	// ------------------------------------------------------------------

	public function definitions()
	{
		return (array) $this->CI->config->item('profile_blocks', 'profile_blocks');
	}

	public function definition($block_key)
	{
		$all = $this->definitions();
		if ( ! isset($all[$block_key]))
		{
			throw new DomainRuleException('Blok profil tidak dikenal.', 404);
		}
		return $all[$block_key];
	}

	/** Halaman publik yang memuat blok ini; dipakai untuk invalidasi cache terarah. */
	public function pages()
	{
		$pages = array();
		foreach ($this->definitions() as $definition)
		{
			$pages[$definition['page']] = TRUE;
		}
		return array_keys($pages);
	}

	// ------------------------------------------------------------------
	// Blok dan versi
	// ------------------------------------------------------------------

	public function blocks()
	{
		$rows = $this->CI->db->order_by('sort_order')->order_by('id')->get('profile_blocks')->result();
		foreach ($rows as $row)
		{
			$row->body = $this->body($row);
		}
		return $rows;
	}

	public function block($block_key)
	{
		$row = $this->CI->db->get_where('profile_blocks', array('block_key' => (string) $block_key))->row();
		if ($row)
		{
			$row->body = $this->body($row);
		}
		return $row;
	}

	public function block_by_public_id($public_id)
	{
		$row = $this->CI->db->get_where('profile_blocks', array('public_id' => (string) $public_id))->row();
		if ($row)
		{
			$row->body = $this->body($row);
		}
		return $row;
	}

	public function versions($block)
	{
		return $this->CI->db->where('block_id', (int) $block->id)
			->order_by('version_no', 'DESC')->limit(20)->get('profile_block_versions')->result();
	}

	protected function body($block)
	{
		if ( ! $block OR ! $block->current_version_id)
		{
			return array();
		}
		$version = $this->CI->db->get_where('profile_block_versions', array('id' => (int) $block->current_version_id))->row();
		$body = $version ? json_decode((string) $version->body_json, TRUE) : NULL;
		return is_array($body) ? $body : array();
	}

	/**
	 * Simpan versi baru sebuah blok. Menyimpan draft TIDAK mengubah halaman publik;
	 * blok yang sudah terbit tetap menampilkan snapshot lama sampai diterbitkan ulang.
	 */
	public function save_block($block, array $input, $user_id)
	{
		$definition = $this->definition($block->block_key);
		$body = $this->validate_body($block->block_key, $input);
		$now = utc_now();
		$version_id = NULL;

		db_transaction(function () use ($block, $body, $input, $user_id, $now, &$version_id) {
			$next = (int) $this->CI->db->select_max('version_no')
				->where('block_id', (int) $block->id)->get('profile_block_versions')->row('version_no') + 1;
			db_must($this->CI->db->insert('profile_block_versions', array(
				'block_id' => (int) $block->id,
				'version_no' => $next,
				'body_json' => json_encode($body, JSON_UNESCAPED_UNICODE),
				'change_note' => mb_substr(trim((string) ($input['change_note'] ?? '')), 0, 255) ?: NULL,
				'created_by' => $user_id ? (int) $user_id : NULL,
				'created_at' => $now,
			)), 'profile_block_versions.insert');
			$version_id = (int) $this->CI->db->insert_id();

			// Isi berubah, jadi verifikasi sebelumnya tidak lagi berlaku.
			db_must($this->CI->db->where('id', (int) $block->id)->update('profile_blocks', array(
				'current_version_id' => $version_id,
				'title' => mb_substr(trim((string) ($input['title'] ?? $block->title)), 0, 160) ?: $block->title,
				'period_start' => $this->nullable_year($input['period_start'] ?? NULL),
				'period_end' => $this->nullable_year($input['period_end'] ?? NULL),
				'source_id' => empty($input['source_id']) ? NULL : (int) $input['source_id'],
				'source_note' => mb_substr(trim((string) ($input['source_note'] ?? '')), 0, 255) ?: NULL,
				'verification_status' => 'unverified',
				'verified_by' => NULL,
				'verified_at' => NULL,
				'updated_at' => $now,
			)), 'profile_blocks.update');
		});

		$this->CI->audit->log('profile.block_saved', 'profile_block', $block->public_id,
			array('block' => $block->block_key, 'version_id' => $version_id), FALSE, 'public_website');
		return $this->block($block->block_key);
	}

	protected function nullable_year($value)
	{
		$year = (int) $value;
		return ($year >= 1800 && $year <= 2100) ? $year : NULL;
	}

	// ------------------------------------------------------------------
	// Validasi isi blok
	// ------------------------------------------------------------------

	/**
	 * Hanya field pada registry yang diterima; kunci asing dibuang tanpa pemberitahuan
	 * agar form yang dimodifikasi tidak dapat menyelundupkan data.
	 */
	public function validate_body($block_key, array $input)
	{
		$definition = $this->definition($block_key);
		$body = array();
		$errors = array();

		foreach ($definition['fields'] as $field => $meta)
		{
			$raw = $input[$field] ?? NULL;
			switch ($meta['type'])
			{
				case 'text':
				case 'textarea':
				case 'code':
					$value = trim((string) $raw);
					if ($value === '')
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi.';
						}
						break;
					}
					$body[$field] = mb_substr($value, 0, (int) ($meta['max'] ?? 1000));
					break;

				case 'number':
					if ($raw === NULL OR trim((string) $raw) === '')
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi.';
						}
						break;
					}
					$value = (int) $raw;
					if ((isset($meta['min']) && $value < $meta['min']) OR (isset($meta['max']) && $value > $meta['max']))
					{
						$errors[$field] = $meta['label'].' di luar rentang yang diizinkan.';
						break;
					}
					$body[$field] = $value;
					break;

				case 'decimal':
					if ($raw === NULL OR trim((string) $raw) === '')
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi.';
						}
						break;
					}
					// Terima koma desimal Indonesia tanpa mengubah maknanya.
					$normalized = str_replace(',', '.', trim((string) $raw));
					if ( ! is_numeric($normalized))
					{
						$errors[$field] = $meta['label'].' harus berupa angka.';
						break;
					}
					$body[$field] = round((float) $normalized, 2);
					break;

				case 'string_list':
					$items = array();
					foreach ((array) $raw as $item)
					{
						$item = trim((string) $item);
						if ($item !== '')
						{
							$items[] = mb_substr($item, 0, (int) ($meta['max'] ?? 400));
						}
					}
					if (empty($items))
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi minimal satu butir.';
						}
						break;
					}
					if (count($items) > (int) ($meta['max_items'] ?? 20))
					{
						$errors[$field] = 'Maksimal '.(int) $meta['max_items'].' butir.';
						break;
					}
					$body[$field] = $items;
					break;

				case 'pair_list':
					$pairs = array();
					foreach ((array) $raw as $item)
					{
						$label = trim((string) ($item['label'] ?? ''));
						$value = trim((string) ($item['value'] ?? ''));
						if ($label === '' && $value === '')
						{
							continue;
						}
						if ($label === '' OR $value === '')
						{
							$errors[$field] = 'Setiap baris '.mb_strtolower($meta['label']).' membutuhkan label dan nilai.';
							break 2;
						}
						$pairs[] = array('label' => mb_substr($label, 0, 120), 'value' => mb_substr($value, 0, 200));
					}
					if (empty($pairs))
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi.';
						}
						break;
					}
					if (count($pairs) > (int) ($meta['max_items'] ?? 20))
					{
						$errors[$field] = 'Maksimal '.(int) $meta['max_items'].' baris.';
						break;
					}
					$body[$field] = $pairs;
					break;
			}
		}

		$this->validate_block_rules($block_key, $body, $errors);
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali isian blok profil.', 422, $errors);
		}
		return $body;
	}

	/** Aturan khusus per blok (modul-frontend 5 dan 14). */
	protected function validate_block_rules($block_key, array $body, array &$errors)
	{
		if ($block_key === 'vision')
		{
			$village = trim((string) ($body['village_vision'] ?? ''));
			$district = trim((string) ($body['district_vision'] ?? ''));
			if ($district !== '' && $district === $village)
			{
				$errors['district_vision'] = 'Visi kecamatan dan visi desa tidak boleh disalin sama; keduanya dokumen berbeda.';
			}
		}

		if ($block_key === 'geography' && ! empty($body['land_use']))
		{
			$sum = 0.0;
			foreach ($body['land_use'] as $row)
			{
				$sum += (float) str_replace(',', '.', (string) $row['value']);
			}
			// Sumber mencatat beberapa angka luas yang berbeda. Selama masih ada observasi
			// luas yang tidak sama dengan jumlah komposisi ini, konfliknya wajib dijelaskan
			// dan tidak boleh diselesaikan sepihak oleh kode.
			if ($this->area_conflicts_with($sum) && trim((string) ($body['area_conflict_note'] ?? '')) === '')
			{
				$errors['area_conflict_note'] = 'Jumlah penggunaan lahan ('.number_format($sum, 2, ',', '.').' ha) berbeda dari angka luas pada dokumen sumber. Jelaskan konfliknya, jangan memilih satu angka.';
			}
		}

		if ($block_key === 'greeting' && trim((string) ($body['author_position'] ?? '')) !== '')
		{
			// Jabatan disimpan sebagai jabatan SAAT dokumen dibuat; blok wajib punya periode.
			// Periode diperiksa saat penerbitan, bukan di sini, supaya draft tetap bisa disimpan.
		}
	}

	/** TRUE bila ada observasi luas wilayah yang berbeda dari jumlah komposisi lahan. */
	protected function area_conflicts_with($sum)
	{
		$rows = $this->CI->db->select('o.normalized_value')->from('source_observations o')
			->where('o.field_key', 'area_total_ha')->where('o.normalized_value IS NOT NULL', NULL, FALSE)
			->get()->result();
		foreach ($rows as $row)
		{
			if (abs((float) $row->normalized_value - (float) $sum) > 0.005)
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	// ------------------------------------------------------------------
	// Verifikasi dan alur terbit
	// ------------------------------------------------------------------

	public function submit_review($block, $user_id)
	{
		if ( ! $block->current_version_id)
		{
			throw new DomainRuleException('Blok belum memiliki isi.', 409);
		}
		db_must($this->CI->db->where('id', (int) $block->id)->update('profile_blocks', array(
			'status' => 'in_review', 'updated_at' => utc_now(),
		)), 'profile_blocks.submit');
		$this->CI->audit->log('profile.block_submitted', 'profile_block', $block->public_id,
			array('block' => $block->block_key), FALSE, 'public_website');
	}

	/** Verifikasi isi blok terhadap dokumen sumber; terpisah dari penerbitan. */
	public function verify_block($block, $verified, $user_id, $note = '')
	{
		if ( ! $block->current_version_id)
		{
			throw new DomainRuleException('Blok belum memiliki isi.', 409);
		}
		db_must($this->CI->db->where('id', (int) $block->id)->update('profile_blocks', array(
			'verification_status' => $verified ? 'verified' : 'unverified',
			'verified_by' => $verified && $user_id ? (int) $user_id : NULL,
			'verified_at' => $verified ? utc_now() : NULL,
			'updated_at' => utc_now(),
		)), 'profile_blocks.verify');
		$this->CI->audit->log($verified ? 'profile.block_verified' : 'profile.block_unverified',
			'profile_block', $block->public_id,
			array('block' => $block->block_key, 'note' => mb_substr((string) $note, 0, 200)), FALSE, 'public_website');
	}

	public function archive_block($block, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $block->id)->update('profile_blocks', array(
			'status' => 'archived', 'updated_at' => utc_now(),
		)), 'profile_blocks.archive');
		$this->CI->audit->log('profile.block_archived', 'profile_block', $block->public_id,
			array('block' => $block->block_key), FALSE, 'public_website');
	}

	/**
	 * Pemeriksaan sebelum profil boleh diterbitkan.
	 * @return array{errors: string[], warnings: string[]}
	 */
	public function validate_profile()
	{
		$errors = array();
		$warnings = array();
		$candidates = $this->publishable_blocks();

		if (empty($candidates))
		{
			$errors[] = 'Belum ada blok profil yang siap diterbitkan.';
		}
		foreach ($candidates as $block)
		{
			$label = $this->definition($block->block_key)['label'];
			if ($block->verification_status !== 'verified')
			{
				$errors[] = 'Blok "'.$label.'" belum diverifikasi terhadap dokumen sumber.';
			}
			if (empty($block->body))
			{
				$errors[] = 'Blok "'.$label.'" belum memiliki isi.';
			}
			if ($block->block_key === 'greeting' && ! $block->period_start)
			{
				$errors[] = 'Blok "'.$label.'" wajib punya tahun periode: sambutan lama tidak boleh tampil seolah sambutan pejabat saat ini.';
			}
			if ( ! $block->source_id && ! $block->source_note)
			{
				$warnings[] = 'Blok "'.$label.'" belum mencantumkan sumber.';
			}
		}

		foreach ($this->terms(TRUE) as $term)
		{
			if ($term->verification_status !== 'verified')
			{
				$errors[] = 'Periode kepemimpinan "'.$term->person_name.'" belum diverifikasi.';
			}
			if ($term->ongoing_claim && $term->year_end !== NULL)
			{
				$errors[] = 'Periode "'.$term->person_name.'" ditandai "sampai sekarang" menurut sumber tetapi juga punya tahun selesai. Pilih salah satu.';
			}
		}
		foreach ($this->duplicate_terms() as $name)
		{
			$warnings[] = 'Nama "'.$name.'" muncul pada lebih dari satu periode. Gabungkan hanya setelah masa jabatannya dipastikan.';
		}

		return array('errors' => $errors, 'warnings' => $warnings);
	}

	protected function publishable_blocks()
	{
		$out = array();
		foreach ($this->blocks() as $block)
		{
			if (in_array($block->status, array('in_review', 'published'), TRUE))
			{
				$out[] = $block;
			}
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Linimasa kepemimpinan
	// ------------------------------------------------------------------

	public function terms($publishable_only = FALSE)
	{
		$this->CI->db->order_by('year_start')->order_by('sort_order')->order_by('id');
		if ($publishable_only)
		{
			$this->CI->db->where_in('publication_status', array('in_review', 'published'));
		}
		return $this->CI->db->get('leadership_terms')->result();
	}

	public function term($public_id)
	{
		return $this->CI->db->get_where('leadership_terms', array('public_id' => (string) $public_id))->row();
	}

	public function save_term(array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['person_name'] ?? '')), 0, 150);
		$title = mb_substr(trim((string) ($input['position_title'] ?? 'Kepala Desa')), 0, 150);
		$start = (int) ($input['year_start'] ?? 0);
		$end = trim((string) ($input['year_end'] ?? ''));
		$end = ($end === '') ? NULL : (int) $end;
		$ongoing = ! empty($input['ongoing_claim']);

		if ($name === '')
		{
			$errors['person_name'] = 'Nama wajib diisi.';
		}
		if ($start < 1800 OR $start > 2100)
		{
			$errors['year_start'] = 'Tahun mulai tidak wajar.';
		}
		if ($end !== NULL && ($end < 1800 OR $end > 2100))
		{
			$errors['year_end'] = 'Tahun selesai tidak wajar.';
		}
		if ($end !== NULL && $end < $start)
		{
			$errors['year_end'] = 'Tahun selesai lebih awal dari tahun mulai.';
		}
		if ($ongoing && $end !== NULL)
		{
			// "Sampai sekarang" pada dokumen lama berarti sampai dokumen itu dibuat.
			$errors['ongoing_claim'] = 'Periode bertanda "sampai sekarang menurut sumber" tidak boleh punya tahun selesai.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data periode kepemimpinan.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'person_name' => $name,
			'position_title' => $title ?: 'Kepala Desa',
			'year_start' => $start,
			'year_end' => $end,
			'ongoing_claim' => $ongoing ? 1 : 0,
			'summary' => mb_substr(trim((string) ($input['summary'] ?? '')), 0, 600) ?: NULL,
			'photo_media_id' => empty($input['photo_media_id']) ? NULL : (int) $input['photo_media_id'],
			'source_id' => empty($input['source_id']) ? NULL : (int) $input['source_id'],
			'source_note' => mb_substr(trim((string) ($input['source_note'] ?? '')), 0, 255) ?: NULL,
			'sort_order' => (int) ($input['sort_order'] ?? 0),
			'updated_at' => $now,
		);

		$existing = $public_id ? $this->term($public_id) : NULL;
		if ($existing)
		{
			// Isi berubah, verifikasi sebelumnya gugur.
			$data['verification_status'] = 'unverified';
			db_must($this->CI->db->where('id', (int) $existing->id)->update('leadership_terms', $data), 'leadership_terms.update');
			$this->CI->audit->log('profile.term_saved', 'leadership_term', $existing->public_id, array('name' => $name), FALSE, 'public_website');
			return $this->term($existing->public_id);
		}

		$data['public_id'] = $this->CI->crypto->public_id();
		$data['publication_status'] = 'draft';
		$data['verification_status'] = 'unverified';
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('leadership_terms', $data), 'leadership_terms.insert');
		$this->CI->audit->log('profile.term_created', 'leadership_term', $data['public_id'], array('name' => $name), FALSE, 'public_website');
		return $this->term($data['public_id']);
	}

	public function verify_term($term, $verified, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $term->id)->update('leadership_terms', array(
			'verification_status' => $verified ? 'verified' : 'unverified',
			'publication_status' => $verified ? 'in_review' : 'draft',
			'updated_at' => utc_now(),
		)), 'leadership_terms.verify');
		$this->CI->audit->log($verified ? 'profile.term_verified' : 'profile.term_unverified',
			'leadership_term', $term->public_id, array('name' => $term->person_name), FALSE, 'public_website');
	}

	/** Menonaktifkan periode tidak menghapus histori; barisnya tetap ada sebagai draft. */
	public function withdraw_term($term, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $term->id)->update('leadership_terms', array(
			'publication_status' => 'draft', 'updated_at' => utc_now(),
		)), 'leadership_terms.withdraw');
		$this->CI->audit->log('profile.term_withdrawn', 'leadership_term', $term->public_id,
			array('name' => $term->person_name), FALSE, 'public_website');
	}

	/** Nama yang muncul pada lebih dari satu periode; tidak digabung otomatis. */
	protected function duplicate_terms()
	{
		$seen = array();
		$duplicates = array();
		foreach ($this->terms(TRUE) as $term)
		{
			$key = mb_strtolower($term->person_name);
			if (isset($seen[$key]))
			{
				$duplicates[$key] = $term->person_name;
			}
			$seen[$key] = TRUE;
		}
		return array_values($duplicates);
	}

	// ------------------------------------------------------------------
	// Snapshot publikasi
	// ------------------------------------------------------------------

	public function build_snapshot()
	{
		$blocks = array();
		foreach ($this->publishable_blocks() as $block)
		{
			$definition = $this->definition($block->block_key);
			$source = $block->source_id
				? $this->CI->db->select('source_code, title')->get_where('source_documents', array('id' => (int) $block->source_id))->row()
				: NULL;
			$blocks[$block->block_key] = array(
				'block_key' => $block->block_key,
				'label' => $definition['label'],
				'page' => $definition['page'],
				'title' => $block->title,
				'body' => $block->body,
				'period_start' => $block->period_start ? (int) $block->period_start : NULL,
				'period_end' => $block->period_end ? (int) $block->period_end : NULL,
				'source_code' => $source ? $source->source_code : NULL,
				'source_title' => $source ? $source->title : NULL,
				'source_note' => $block->source_note,
				'version_id' => (int) $block->current_version_id,
			);
		}

		$terms = array();
		foreach ($this->terms(TRUE) as $term)
		{
			if ($term->verification_status !== 'verified')
			{
				continue;
			}
			$terms[] = array(
				'public_id' => $term->public_id,
				'person_name' => $term->person_name,
				'position_title' => $term->position_title,
				'year_start' => (int) $term->year_start,
				'year_end' => $term->year_end === NULL ? NULL : (int) $term->year_end,
				'ongoing_claim' => (int) $term->ongoing_claim === 1,
				'summary' => $term->summary,
				'source_note' => $term->source_note,
			);
		}

		return array('blocks' => $blocks, 'terms' => $terms);
	}

	public function publish($reason, $user_id)
	{
		$report = $this->validate_profile();
		if ( ! empty($report['errors']))
		{
			throw new DomainRuleException('Profil belum lolos pemeriksaan: '.implode(' ', $report['errors']), 422);
		}
		$snapshot = $this->build_snapshot();
		$now = utc_now();
		$revision = NULL;

		db_transaction(function () use ($snapshot, $reason, $user_id, $now, &$revision) {
			$revision = (int) $this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'profile', 'target_id' => 0))
				->get('cms_publication_snapshots')->row('revision_no') + 1;
			$this->CI->db->where(array('target_type' => 'profile', 'target_id' => 0))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'profile',
				'target_id' => 0,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
				'reason' => mb_substr((string) $reason, 0, 500),
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_publication_snapshots.profile');

			foreach (array_keys($snapshot['blocks']) as $block_key)
			{
				$version_id = $this->CI->db->select('current_version_id')
					->get_where('profile_blocks', array('block_key' => $block_key))->row('current_version_id');
				$this->CI->db->where('block_key', $block_key)->update('profile_blocks', array(
					'status' => 'published',
					'published_version_id' => $version_id,
					'published_at' => $now,
					'updated_at' => $now,
				));
			}
			$this->CI->db->where('verification_status', 'verified')->where('publication_status', 'in_review')
				->update('leadership_terms', array('publication_status' => 'published', 'updated_at' => $now));
		});

		$this->after_change('profile.published', array('revision' => $revision, 'reason' => mb_substr((string) $reason, 0, 200)));
		return $revision;
	}

	/** Menarik profil: halaman publik kembali kosong, riwayat tetap tersimpan. */
	public function unpublish($reason, $user_id)
	{
		$now = utc_now();
		db_transaction(function () use ($now) {
			$this->CI->db->where(array('target_type' => 'profile', 'target_id' => 0))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			$this->CI->db->where('status', 'published')->update('profile_blocks', array(
				'status' => 'draft', 'published_at' => NULL, 'updated_at' => $now,
			));
		});
		$this->after_change('profile.unpublished', array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	/** Rollback menerbitkan ulang isi snapshot lama sebagai revisi baru. */
	public function rollback($snapshot_id, $reason, $user_id)
	{
		$old = $this->CI->db->get_where('cms_publication_snapshots', array(
			'id' => (int) $snapshot_id, 'target_type' => 'profile',
		))->row();
		if ( ! $old)
		{
			throw new DomainRuleException('Snapshot profil tidak ditemukan.', 404);
		}
		$now = utc_now();
		$revision = NULL;
		db_transaction(function () use ($old, $reason, $user_id, $now, &$revision) {
			$revision = (int) $this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'profile', 'target_id' => 0))
				->get('cms_publication_snapshots')->row('revision_no') + 1;
			$this->CI->db->where(array('target_type' => 'profile', 'target_id' => 0))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'profile',
				'target_id' => 0,
				'revision_no' => $revision,
				'snapshot_json' => $old->snapshot_json,
				'reason' => mb_substr((string) $reason, 0, 500),
				'rolled_back_from' => (int) $old->id,
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_publication_snapshots.profile_rollback');
		});
		$this->after_change('profile.rolled_back', array('revision' => $revision, 'from' => (int) $old->revision_no));
		return $revision;
	}

	public function snapshots($limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'profile', 's.target_id' => 0))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	protected function after_change($action, array $meta)
	{
		$this->CI->audit->log($action, 'profile', 'profile', $meta, FALSE, 'public_website');
		$this->CI->audit->event('info', 'Profil desa: '.$action.'.', 'public_website', $meta);
		foreach ($this->pages() as $page)
		{
			$this->CI->public_cache->invalidate_page($page);
		}
		$this->CI->public_cache->forget_group('listing');
	}

	// ------------------------------------------------------------------
	// Pembacaan publik
	// ------------------------------------------------------------------

	/** Snapshot profil yang sedang terbit, atau NULL bila belum pernah diterbitkan. */
	public function published()
	{
		$cached = $this->CI->public_cache->get('listing', 'profile');
		if (is_array($cached))
		{
			return $cached;
		}
		$row = $this->CI->db->where(array('target_type' => 'profile', 'target_id' => 0))
			->where('superseded_at IS NULL', NULL, FALSE)
			->order_by('revision_no', 'DESC')->limit(1)->get('cms_publication_snapshots')->row();
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
		$this->CI->public_cache->set('listing', 'profile', $snapshot, 1800);
		return $snapshot;
	}

	/** Blok terbit untuk satu halaman publik. */
	public function published_blocks_for($page)
	{
		$snapshot = $this->published();
		if ( ! $snapshot)
		{
			return array();
		}
		$out = array();
		foreach ($snapshot['blocks'] as $key => $block)
		{
			if ($block['page'] === $page)
			{
				$out[$key] = $block;
			}
		}
		return $out;
	}
}
