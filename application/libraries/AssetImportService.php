<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Impor register aset ke staging (prompt-master 4.6 dan 19.6).
 *
 * Berkas sumber dibaca sebagai CSV. Berkas S5 asli berformat .xlsx dan proyek ini tidak
 * memaketkan pustaka pembaca xlsx, jadi berkas itu harus diekspor ke CSV lebih dulu.
 * Keterbatasan ini dicatat apa adanya, bukan disembunyikan.
 *
 * Baris mentah tidak pernah diubah: `raw_json` disimpan apa adanya dan usulan register
 * disimpan terpisah pada `proposed_register_json`. Impor idempotent lewat checksum berkas
 * dan nomor baris, sehingga mengulang impor tidak menggandakan staging.
 */
class AssetImportService {

	/** @var CI_Controller */
	protected $CI;

	const PARSER_VERSION = 'asset-csv-1';

	/** Judul kolom yang dikenali, dalam huruf kecil tanpa spasi ganda. */
	const COLUMNS = array(
		'kode barang' => 'legacy_code',
		'kode' => 'legacy_code',
		'nama barang' => 'name',
		'nama' => 'name',
		'uraian' => 'description',
		'merk' => 'brand',
		'merek' => 'brand',
		'volume' => 'volume',
		'jumlah' => 'volume',
		'satuan' => 'unit_label',
		'tahun' => 'acquisition_year',
		'tahun perolehan' => 'acquisition_year',
		'asal usul' => 'acquisition_source',
		'asal-usul' => 'acquisition_source',
		'harga' => 'acquisition_value',
		'nilai' => 'acquisition_value',
		'keadaan' => 'condition_raw',
		'kondisi' => 'condition_raw',
		'keberadaan' => 'existence_raw',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * Baca satu berkas CSV ke staging.
	 * @return array ringkasan valid, empty, conflict, failed, skipped
	 */
	public function import_csv($path, $source_id, $user_id)
	{
		if ( ! is_file($path))
		{
			throw new DomainRuleException('Berkas impor tidak ditemukan: '.basename((string) $path), 404);
		}
		$checksum = hash_file('sha256', $path);
		$batch = $this->CI->db->where(array('source_id' => (int) $source_id, 'parser_version' => self::PARSER_VERSION))
			->where('error_message', $checksum)->get('import_batches')->row();

		$now = utc_now();
		if ( ! $batch)
		{
			db_must($this->CI->db->insert('import_batches', array(
				'source_id' => (int) $source_id,
				'status' => 'running',
				'parser_version' => self::PARSER_VERSION,
				// Checksum disimpan pada kolom pesan agar batch yang sama dikenali ulang.
				'error_message' => $checksum,
				'started_by' => $user_id ? (int) $user_id : NULL,
				'started_at' => $now,
			)), 'import_batches.insert');
			$batch = $this->CI->db->get_where('import_batches', array('id' => (int) $this->CI->db->insert_id()))->row();
		}

		$summary = array('valid' => 0, 'empty' => 0, 'conflict' => 0, 'failed' => 0, 'skipped' => 0);
		$handle = fopen($path, 'r');
		if ($handle === FALSE)
		{
			throw new DomainRuleException('Berkas impor tidak dapat dibaca.', 422);
		}

		$header = NULL;
		$row_no = 0;
		while (($row = fgetcsv($handle)) !== FALSE)
		{
			$row_no++;
			if ($header === NULL)
			{
				$header = $this->map_header($row);
				continue;
			}
			if ($this->is_blank($row))
			{
				$summary['empty']++;
				continue;
			}
			$result = $this->stage_row($batch, $row_no, $header, $row);
			$summary[$result]++;
		}
		fclose($handle);

		db_must($this->CI->db->where('id', (int) $batch->id)->update('import_batches', array(
			'status' => 'completed',
			'row_count' => $row_no > 0 ? $row_no - 1 : 0,
			'accepted_count' => $summary['valid'],
			'conflict_count' => $summary['conflict'],
			'empty_count' => $summary['empty'],
			'completed_at' => utc_now(),
		)), 'import_batches.update');

		$this->CI->audit->log('assets.import_staged', 'import_batch', (string) $batch->id, $summary, FALSE, 'assets');
		return $summary + array('batch_id' => (int) $batch->id);
	}

	protected function map_header(array $row)
	{
		$map = array();
		foreach ($row as $index => $label)
		{
			$key = trim(mb_strtolower(preg_replace('/\s+/', ' ', (string) $label)));
			if (isset(self::COLUMNS[$key]))
			{
				$map[self::COLUMNS[$key]] = $index;
			}
		}
		return $map;
	}

	protected function is_blank(array $row)
	{
		foreach ($row as $value)
		{
			if (trim((string) $value) !== '')
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	/** @return string valid, conflict, failed, atau skipped */
	protected function stage_row($batch, $row_no, array $header, array $row)
	{
		$value = function ($field) use ($header, $row) {
			return isset($header[$field]) && isset($row[$header[$field]]) ? trim((string) $row[$header[$field]]) : '';
		};

		if ($this->CI->db->where(array('import_batch_id' => (int) $batch->id, 'source_row_no' => (int) $row_no))
			->count_all_results('asset_import_rows') > 0)
		{
			// Impor ulang berkas yang sama tidak menggandakan baris staging.
			return 'skipped';
		}

		$name = $value('name');
		$legacy = $value('legacy_code');
		$status = 'pending';
		$notes = array();

		if ($name === '')
		{
			$status = 'failed';
			$notes[] = 'Nama barang kosong.';
		}
		if ($legacy !== '' && $this->CI->db->where('legacy_asset_code', $legacy)->count_all_results('asset_registers') > 0)
		{
			$status = 'conflict';
			$notes[] = 'Kode barang sudah ada pada register.';
		}
		if ($legacy !== '' && $this->CI->db->where('legacy_code', $legacy)
			->where('import_batch_id <>', (int) $batch->id)->count_all_results('asset_import_rows') > 0)
		{
			$status = 'conflict';
			$notes[] = 'Kode barang muncul pada batch impor lain.';
		}
		$year = $value('acquisition_year');
		if ($year !== '' && ( ! ctype_digit($year) OR (int) $year < 1900 OR (int) $year > 2100))
		{
			$status = ($status === 'failed') ? 'failed' : 'conflict';
			$notes[] = 'Tahun perolehan tidak wajar: '.$year;
		}
		$raw_value = $value('acquisition_value');
		if ($raw_value === '')
		{
			$notes[] = 'Harga kosong; nilai tidak diisi.';
		}
		if ($value('existence_raw') === '')
		{
			$notes[] = 'Keberadaan barang belum diisi pada sumber.';
		}
		$volume = $value('volume');
		if ($volume !== '' && ! preg_match('/^\d+([.,]\d+)?$/', $volume))
		{
			// Volume seperti "I Set" atau "1 paket" tidak dipecah otomatis menjadi unit.
			$notes[] = 'Volume bukan angka murni; pemecahan unit harus diputuskan manual.';
		}

		$proposed = array(
			'name' => $name,
			'description' => $value('description'),
			'legacy_asset_code' => $legacy ?: NULL,
			'source_volume_raw' => $volume ?: NULL,
			'acquisition_year' => ($year !== '' && ctype_digit($year)) ? (int) $year : NULL,
			'acquisition_source' => $value('acquisition_source') ?: NULL,
			'acquisition_value' => $this->parse_amount($raw_value),
			'brand' => $value('brand') ?: NULL,
		);

		$now = utc_now();
		db_must($this->CI->db->insert('asset_import_rows', array(
			'import_batch_id' => (int) $batch->id,
			'source_row_no' => (int) $row_no,
			// Nilai mentah disimpan apa adanya dan tidak pernah dikoreksi diam-diam.
			'raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
			'legacy_code' => $legacy ?: NULL,
			'proposed_register_json' => json_encode($proposed, JSON_UNESCAPED_UNICODE),
			'validation_status' => $status,
			'review_note' => $notes ? mb_substr(implode(' ', $notes), 0, 600) : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'asset_import_rows.insert');

		return $status === 'pending' ? 'valid' : $status;
	}

	protected function parse_amount($raw)
	{
		$clean = str_replace(array('Rp', ' ', '.'), '', (string) $raw);
		$clean = str_replace(',', '.', $clean);
		return ($clean !== '' && is_numeric($clean)) ? round((float) $clean, 2) : NULL;
	}

	public function rows($batch_id, $status = NULL)
	{
		$this->CI->db->where('import_batch_id', (int) $batch_id);
		if ($status !== NULL)
		{
			$this->CI->db->where('validation_status', $status);
		}
		return $this->CI->db->order_by('source_row_no')->get('asset_import_rows')->result();
	}

	/**
	 * Komit satu baris staging menjadi register. Idempotent: baris yang sudah dikomit tidak
	 * pernah menghasilkan register kedua.
	 */
	public function commit_row($row, $category_id, $user_id)
	{
		if ($row->committed_register_id)
		{
			return $this->CI->db->get_where('asset_registers', array('id' => (int) $row->committed_register_id))->row();
		}
		if ($row->validation_status === 'failed')
		{
			throw new DomainRuleException('Baris bertanda gagal tidak dapat dikomit sebelum diperbaiki.', 409);
		}
		$proposed = json_decode((string) $row->proposed_register_json, TRUE) ?: array();
		$this->CI->load->library('AssetService', NULL, 'assets');
		$register = $this->CI->assets->save_register($proposed + array('category_id' => (int) $category_id), $user_id);

		db_must($this->CI->db->where('id', (int) $row->id)->update('asset_import_rows', array(
			'committed_register_id' => (int) $register->id,
			'validation_status' => 'committed',
			'reviewed_by' => $user_id ? (int) $user_id : NULL,
			'updated_at' => utc_now(),
		)), 'asset_import_rows.commit');
		return $register;
	}
}
