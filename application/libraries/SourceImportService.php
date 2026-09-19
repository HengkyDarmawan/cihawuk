<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Impor dokumen sumber desa (DOCX) menjadi observasi mentah yang dapat direview.
 *
 * Prinsip (§4.5 spesifikasi):
 * - Nilai mentah (raw_value) tidak pernah ditimpa; normalisasi disimpan terpisah.
 * - Ekstraksi mempertahankan heading, tabel, baris, dan sel; merged cell tidak diduplikasi.
 * - Impor idempotent berdasarkan checksum berkas dan identitas observasi
 *   (sumber + locator + field), transaksional per batch.
 * - Format `.doc` lama tidak diparse di sini; perlu konversi staging (LibreOffice).
 */
class SourceImportService {

	const PARSER_VERSION = 'docx-1.0';

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	// ------------------------------------------------------------ Register

	public function sources()
	{
		return $this->CI->db->order_by('source_code')->get('source_documents')->result();
	}

	public function source($id)
	{
		return $this->CI->db->get_where('source_documents', array('id' => (int) $id))->row();
	}

	public function source_by_code($code)
	{
		return $this->CI->db->get_where('source_documents', array('source_code' => (string) $code))->row();
	}

	public function reference_path($source)
	{
		$dir = ROOTPATH.'reference/documents/';
		$path = $dir.$source->original_filename;
		return is_file($path) ? $path : NULL;
	}

	/**
	 * Impor dokumen dari folder reference/documents.
	 * @return array kode sumber => ringkasan hasil
	 */
	public function import_reference_documents($only_code = NULL, $user_id = NULL)
	{
		$results = array();
		foreach ($this->sources() as $source)
		{
			if ($only_code !== NULL && strtoupper($only_code) !== $source->source_code)
			{
				continue;
			}
			$path = $this->reference_path($source);
			if ($path === NULL)
			{
				$results[$source->source_code] = 'berkas tidak ditemukan di reference/documents';
				continue;
			}
			if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'docx')
			{
				$this->CI->db->where('id', (int) $source->id)->update('source_documents', array(
					'import_status' => 'needs_conversion', 'updated_at' => utc_now(),
				));
				$results[$source->source_code] = 'format .doc belum didukung parser; perlu konversi ke .docx (LibreOffice) di tahap staging';
				continue;
			}
			try
			{
				$results[$source->source_code] = $this->import_docx($source, $path, $user_id);
			}
			catch (Throwable $e)
			{
				log_message('error', 'Import '.$source->source_code.' failed: '.$e->getMessage());
				$results[$source->source_code] = 'gagal: '.$e->getMessage();
			}
		}
		return $results;
	}

	/** @return string ringkasan */
	public function import_docx($source, $path, $user_id = NULL)
	{
		$checksum = hash_file('sha256', $path);
		$blocks = $this->extract_blocks($path);
		$observations = $this->blocks_to_observations($blocks);

		$batch_id = NULL;
		$counts = array('total' => 0, 'baru' => 0, 'konflik' => 0, 'kosong' => 0);

		db_transaction(function () use ($source, $checksum, $observations, $user_id, &$batch_id, &$counts) {
			db_must($this->CI->db->insert('import_batches', array(
				'source_id' => (int) $source->id,
				'status' => 'running',
				'row_count' => count($observations),
				'parser_version' => self::PARSER_VERSION,
				'started_by' => $user_id,
				'started_at' => utc_now(),
			)), 'import_batches.insert');
			$batch_id = (int) $this->CI->db->insert_id();

			foreach ($observations as $obs)
			{
				$counts['total']++;
				if ($obs['normalized_value'] === NULL && trim((string) $obs['raw_value']) === '')
				{
					$counts['kosong']++;
				}
				$existing = $this->CI->db->get_where('source_observations', array(
					'source_id' => (int) $source->id,
					'source_locator' => $obs['source_locator'],
					'field_key' => $obs['field_key'],
				))->row();

				if ($existing)
				{
					// Nilai mentah tidak ditimpa; perbedaan dicatat sebagai konflik.
					if ((string) $existing->raw_value !== (string) $obs['raw_value'])
					{
						$counts['konflik']++;
						$this->CI->db->where('id', (int) $existing->id)->update('source_observations', array(
							'validation_status' => 'conflict',
							'review_note' => 'Impor ulang menghasilkan nilai berbeda: "'.mb_substr((string) $obs['raw_value'], 0, 100).'". Nilai mentah lama dipertahankan.',
							'updated_at' => utc_now(),
						));
						$this->record_issue($source, $existing, $obs);
					}
					continue;
				}

				db_must($this->CI->db->insert('source_observations', array(
					'source_id' => (int) $source->id,
					'batch_id' => $batch_id,
					'field_key' => $obs['field_key'],
					'field_label' => $obs['field_label'],
					'source_locator' => $obs['source_locator'],
					'raw_value' => $obs['raw_value'],
					'normalized_value' => $obs['normalized_value'],
					'value_type' => $obs['value_type'],
					'unit' => $obs['unit'],
					'source_year' => $source->source_year,
					'validation_status' => 'pending',
					'created_at' => utc_now(),
					'updated_at' => utc_now(),
				)), 'source_observations.insert');
				$counts['baru']++;
			}

			$this->CI->db->where('id', $batch_id)->update('import_batches', array(
				'status' => 'completed',
				'accepted_count' => $counts['baru'],
				'conflict_count' => $counts['konflik'],
				'empty_count' => $counts['kosong'],
				'completed_at' => utc_now(),
			));
			$this->CI->db->where('id', (int) $source->id)->update('source_documents', array(
				'checksum' => $checksum,
				'import_status' => 'imported',
				'imported_by' => $user_id,
				'imported_at' => utc_now(),
				'updated_at' => utc_now(),
			));
		});

		$this->CI->audit->log('source.imported', 'source_document', $source->source_code, $counts);
		return 'batch #'.$batch_id.' — '.$counts['total'].' observasi ('.$counts['baru'].' baru, '.$counts['konflik'].' konflik, '.$counts['kosong'].' kosong)';
	}

	protected function record_issue($source, $existing, array $obs)
	{
		$code = 'IMPORT_CONFLICT_'.$source->source_code.'_'.substr(md5($existing->source_locator.$existing->field_key), 0, 8);
		$this->CI->db->query(
			'INSERT IGNORE INTO data_issues (observation_id, source_id, issue_code, title, description, system_decision, severity, status, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array(
				(int) $existing->id, (int) $source->id, $code,
				'Nilai impor berbeda pada '.$existing->field_label,
				'Impor ulang '.$source->source_code.' menghasilkan nilai "'.mb_substr((string) $obs['raw_value'], 0, 120).'" sementara nilai mentah tersimpan "'.mb_substr((string) $existing->raw_value, 0, 120).'".',
				'Nilai mentah lama dipertahankan; observasi ditandai konflik untuk direview manusia.',
				'medium', 'open', utc_now(), utc_now(),
			)
		);
	}

	// ------------------------------------------------------------ Ekstraksi DOCX

	/**
	 * Ambil blok dokumen: heading/paragraf dan tabel (baris × sel).
	 * @return array daftar blok {type: heading|paragraph|table, ...}
	 */
	public function extract_blocks($path)
	{
		$zip = new ZipArchive();
		if ($zip->open($path) !== TRUE)
		{
			throw new RuntimeException('Tidak dapat membuka berkas DOCX.');
		}
		$xml = $zip->getFromName('word/document.xml');
		$zip->close();
		if ($xml === FALSE)
		{
			throw new RuntimeException('Struktur DOCX tidak dikenali (word/document.xml tidak ada).');
		}

		$previous = libxml_use_internal_errors(TRUE);
		$dom = new DOMDocument();
		// Tidak memuat entitas eksternal.
		$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
		$body = $dom->getElementsByTagNameNS($ns, 'body')->item(0);
		if ( ! $body)
		{
			throw new RuntimeException('Body dokumen tidak ditemukan.');
		}

		$blocks = array();
		$table_index = 0;
		foreach ($body->childNodes as $node)
		{
			if ($node->nodeType !== XML_ELEMENT_NODE)
			{
				continue;
			}
			if ($node->localName === 'p')
			{
				$text = $this->node_text($node, $ns);
				if ($text === '')
				{
					continue;
				}
				$style = '';
				$pStyle = $node->getElementsByTagNameNS($ns, 'pStyle')->item(0);
				if ($pStyle)
				{
					$style = (string) $pStyle->getAttributeNS($ns, 'val');
				}
				$blocks[] = array(
					'type' => (stripos($style, 'heading') !== FALSE OR stripos($style, 'Judul') !== FALSE) ? 'heading' : 'paragraph',
					'style' => $style,
					'text' => $text,
				);
			}
			elseif ($node->localName === 'tbl')
			{
				$table_index++;
				$blocks[] = array('type' => 'table', 'index' => $table_index, 'rows' => $this->table_rows($node, $ns));
			}
		}
		return $blocks;
	}

	protected function node_text(DOMNode $node, $ns)
	{
		$text = '';
		foreach ($node->getElementsByTagNameNS($ns, 't') as $t)
		{
			$text .= $t->textContent;
		}
		foreach ($node->getElementsByTagNameNS($ns, 'tab') as $tab)
		{
			$text .= ' ';
		}
		return trim(preg_replace('/\s+/u', ' ', $text));
	}

	/** Baris tabel dengan penanganan merged cell (gridSpan/vMerge continue). */
	protected function table_rows(DOMElement $table, $ns)
	{
		$rows = array();
		$row_index = 0;
		foreach ($table->childNodes as $tr)
		{
			if ($tr->nodeType !== XML_ELEMENT_NODE OR $tr->localName !== 'tr')
			{
				continue;
			}
			$row_index++;
			$cells = array();
			$cell_index = 0;
			foreach ($tr->childNodes as $tc)
			{
				if ($tc->nodeType !== XML_ELEMENT_NODE OR $tc->localName !== 'tc')
				{
					continue;
				}
				$cell_index++;
				// vMerge continue = lanjutan sel di atasnya; jangan digandakan.
				$vmerge = $tc->getElementsByTagNameNS($ns, 'vMerge')->item(0);
				if ($vmerge && $vmerge->getAttributeNS($ns, 'val') !== 'restart')
				{
					$cells[] = array('index' => $cell_index, 'text' => '', 'merged' => TRUE, 'span' => 1);
					continue;
				}
				$span = 1;
				$grid = $tc->getElementsByTagNameNS($ns, 'gridSpan')->item(0);
				if ($grid)
				{
					$span = max(1, (int) $grid->getAttributeNS($ns, 'val'));
				}
				$cells[] = array(
					'index' => $cell_index,
					'text' => $this->node_text($tc, $ns),
					'merged' => FALSE,
					'span' => $span,
				);
			}
			if ( ! empty($cells))
			{
				$rows[] = array('index' => $row_index, 'cells' => $cells);
			}
		}
		return $rows;
	}

	/**
	 * Ubah blok menjadi kandidat observasi (pasangan label–nilai dari tabel).
	 */
	public function blocks_to_observations(array $blocks)
	{
		$observations = array();
		$heading = '';
		foreach ($blocks as $block)
		{
			if ($block['type'] === 'heading')
			{
				$heading = mb_substr($block['text'], 0, 120);
				continue;
			}
			if ($block['type'] !== 'table')
			{
				continue;
			}
			$header = array();
			foreach ($block['rows'] as $row)
			{
				$texts = array_map(function ($c) { return $c['text']; }, $row['cells']);
				$non_empty = array_values(array_filter($texts, function ($t) { return trim($t) !== ''; }));
				if (count($non_empty) < 2)
				{
					// Baris judul tabel atau baris kosong: dipakai sebagai konteks.
					if (count($non_empty) === 1 && $row['index'] <= 2)
					{
						$header = array(trim($non_empty[0]));
					}
					continue;
				}
				$label = trim($texts[0]);
				if ($label === '' && isset($texts[1]))
				{
					$label = trim($texts[1]);
				}
				if ($label === '')
				{
					continue;
				}
				for ($i = 1; $i < count($texts); $i++)
				{
					$raw = trim($texts[$i]);
					if ($row['cells'][$i]['merged'])
					{
						continue;
					}
					if ($raw === '' && $i > 1)
					{
						continue;
					}
					$normalized = $this->normalize($raw);
					$context = ($header ? $header[0].' › ' : '').$label;
					$locator = ($heading !== '' ? $heading.' › ' : '').'Tabel '.$block['index'].' › Baris '.$row['index'].' › Kolom '.($i + 1);
					$observations[] = array(
						'field_key' => $this->field_key($context, $i),
						'field_label' => mb_substr($context, 0, 255),
						'source_locator' => mb_substr($locator, 0, 191),
						'raw_value' => mb_substr($raw, 0, 2000),
						'normalized_value' => $normalized['value'],
						'value_type' => $normalized['type'],
						'unit' => $normalized['unit'],
					);
				}
			}
		}
		return $observations;
	}

	protected function field_key($label, $column)
	{
		$key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
		$key = trim(preg_replace('/_+/', '_', $key), '_');
		if ($key === '')
		{
			$key = 'kolom';
		}
		return mb_substr($key, 0, 110).'_c'.(int) $column;
	}

	/**
	 * Normalisasi sadar tipe (§4.5.4):
	 * - "6.809" (penduduk) → 6809 integer
	 * - "932,35" (hektare) → 932.35 decimal
	 * - kode PUM "320431.2006" tetap string
	 * - "-" atau kosong → NULL dengan penanda sumber
	 * - "Ada"/"Tidak" → text (bukan boolean otomatis)
	 */
	public function normalize($raw)
	{
		$value = trim((string) $raw);
		if ($value === '' OR in_array($value, array('-', '–', '—', 'n/a', 'N/A'), TRUE))
		{
			return array('value' => NULL, 'type' => 'null', 'unit' => NULL);
		}

		$unit = NULL;
		$work = $value;
		if (preg_match('/^([0-9\.,]+)\s*(ha|m2|m²|orang|jiwa|kk|mdpl|km|km2|%|unit|buah)$/iu', $value, $m))
		{
			$work = $m[1];
			$unit = strtolower($m[2]);
		}

		// Kode berformat angka.titik.angka panjang (mis. kode PUM) tetap string.
		if (preg_match('/^\d{4,}\.\d{3,}$/', $work))
		{
			return array('value' => $work, 'type' => 'code', 'unit' => NULL);
		}
		// Desimal gaya Indonesia: ribuan titik, desimal koma.
		if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $work) OR preg_match('/^-?\d+,\d+$/', $work))
		{
			$number = str_replace(array('.', ','), array('', '.'), $work);
			return array('value' => (string) (float) $number, 'type' => 'decimal', 'unit' => $unit);
		}
		// Bilangan bulat dengan pemisah ribuan titik.
		if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $work))
		{
			return array('value' => (string) (int) str_replace('.', '', $work), 'type' => 'integer', 'unit' => $unit);
		}
		if (preg_match('/^-?\d+$/', $work))
		{
			return array('value' => $work, 'type' => 'integer', 'unit' => $unit);
		}
		return array('value' => NULL, 'type' => 'text', 'unit' => $unit);
	}

	// ------------------------------------------------------------ Review

	public function observations($source_id, array $filters = array(), $limit = 50, $offset = 0)
	{
		$this->build_observation_query($source_id, $filters);
		return $this->CI->db->order_by('id')->limit((int) $limit, (int) $offset)->get()->result();
	}

	public function count_observations($source_id, array $filters = array())
	{
		$this->build_observation_query($source_id, $filters);
		return (int) $this->CI->db->count_all_results();
	}

	protected function build_observation_query($source_id, array $filters)
	{
		$this->CI->db->from('source_observations')->where('source_id', (int) $source_id);
		if ( ! empty($filters['status']))
		{
			$this->CI->db->where('validation_status', $filters['status']);
		}
		if ( ! empty($filters['keyword']))
		{
			$this->CI->db->group_start()
				->like('field_label', mb_substr($filters['keyword'], 0, 80))
				->or_like('raw_value', mb_substr($filters['keyword'], 0, 80))
				->group_end();
		}
	}

	/** Pemeriksa menerima/menolak/mengoreksi nilai. Nilai mentah tidak berubah. */
	public function review_observation($observation_id, $status, $corrected_value, $note, $reviewer_id)
	{
		if ( ! in_array($status, array('pending', 'accepted', 'rejected', 'corrected', 'conflict'), TRUE))
		{
			throw new DomainRuleException('Status review tidak valid.', 422);
		}
		$observation = $this->CI->db->get_where('source_observations', array('id' => (int) $observation_id))->row();
		if ( ! $observation)
		{
			throw new DomainRuleException('Observasi tidak ditemukan.', 404);
		}
		if ($status === 'corrected' && trim((string) $corrected_value) === '')
		{
			throw new DomainRuleException('Isi nilai koreksi.', 422, array('corrected_value' => 'Nilai koreksi wajib diisi.'));
		}
		if (in_array($status, array('rejected', 'corrected'), TRUE) && mb_strlen(trim((string) $note)) < 5)
		{
			throw new DomainRuleException('Tuliskan alasan review (minimal 5 karakter).', 422, array('review_note' => 'Alasan wajib diisi.'));
		}
		db_must($this->CI->db->where('id', (int) $observation_id)->update('source_observations', array(
			'validation_status' => $status,
			'corrected_value' => ($status === 'corrected') ? trim((string) $corrected_value) : NULL,
			'reviewer_id' => (int) $reviewer_id,
			'reviewed_at' => utc_now(),
			'review_note' => mb_substr((string) $note, 0, 500),
			'updated_at' => utc_now(),
		)), 'source_observations.review');
		$this->CI->audit->log('source.observation_'.$status, 'source_observation', (string) $observation_id, array());
		return TRUE;
	}

	/** Nilai kanonis menunjuk observasi yang sudah diterima/dikoreksi. */
	public function promote_to_statistic($observation_id, $indicator_id, $year, $area_id, $user_id)
	{
		$observation = $this->CI->db->get_where('source_observations', array('id' => (int) $observation_id))->row();
		if ( ! $observation)
		{
			throw new DomainRuleException('Observasi tidak ditemukan.', 404);
		}
		if ( ! in_array($observation->validation_status, array('accepted', 'corrected'), TRUE))
		{
			throw new DomainRuleException('Hanya observasi yang sudah diterima atau dikoreksi yang dapat dijadikan nilai statistik.', 409);
		}
		$indicator = $this->CI->db->get_where('statistic_indicators', array('id' => (int) $indicator_id))->row();
		if ( ! $indicator)
		{
			throw new DomainRuleException('Indikator tidak ditemukan.', 422);
		}
		$value = ($observation->validation_status === 'corrected') ? $observation->corrected_value : $observation->normalized_value;
		$numeric = is_numeric($value) ? $value : NULL;
		$now = utc_now();
		db_must($this->CI->db->query(
			'INSERT INTO statistic_values (indicator_id, source_year, year_label, area_id, numeric_value, text_value, canonical_observation_id, source_id, verification_status, publication_status, reviewed_by, reviewed_at, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE numeric_value = VALUES(numeric_value), text_value = VALUES(text_value),
				canonical_observation_id = VALUES(canonical_observation_id), source_id = VALUES(source_id),
				verification_status = VALUES(verification_status), reviewed_by = VALUES(reviewed_by), reviewed_at = VALUES(reviewed_at), updated_at = VALUES(updated_at)',
			array(
				(int) $indicator_id, (int) $year, $observation->year_label, (int) $area_id,
				$numeric, $numeric === NULL ? mb_substr((string) $value, 0, 255) : NULL,
				(int) $observation->id, (int) $observation->source_id,
				'verified', 'draft', (int) $user_id, $now, $now, $now,
			)
		), 'statistic_values.promote');
		$this->CI->audit->log('statistic.value_set', 'statistic_value', $indicator->code.':'.$year, array('observation_id' => (int) $observation_id));
		return TRUE;
	}
}
