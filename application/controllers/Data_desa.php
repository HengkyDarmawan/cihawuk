<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Data desa: hanya dataset yang sudah diterbitkan (modul-frontend bagian 8).
 *
 * Setiap dataset menampilkan grafik beserta tabel angka yang setara, satuan, tahun,
 * sumber, waktu penerbitan, dan unduhan CSV. Nilai yang disamarkan karena ambang data
 * kecil ditandai terbuka, bukan dihilangkan diam-diam.
 */
class Data_desa extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('DatasetService', NULL, 'datasets');
	}

	public function index()
	{
		$this->listing(NULL);
	}

	/**
	 * Alamat tetap per tema (modul-frontend bagian 3): /data-desa/{tema}.
	 * Tema di luar registry menghasilkan 404, bukan daftar kosong.
	 */
	public function tema($theme = '')
	{
		$themes = $this->datasets->themes();
		if ( ! isset($themes[$theme]))
		{
			$this->not_found_response();
			return;
		}
		$this->listing($theme);
	}

	protected function listing($locked_theme)
	{
		$all = $this->datasets->published_datasets();
		$filters = array(
			'tahun' => (int) $this->input->get('tahun'),
			'tema' => $locked_theme !== NULL ? $locked_theme : (string) $this->input->get('tema'),
			'sumber' => (string) $this->input->get('sumber'),
			'indikator' => (string) $this->input->get('indikator'),
			'q' => trim((string) $this->input->get('q')),
		);

		$years = array();
		$themes_present = array();
		$sources_present = array();
		$indicators_present = array();
		foreach ($all as $snapshot)
		{
			$years[(int) $snapshot['version']['period_year']] = TRUE;
			$themes_present[$snapshot['dataset']['theme']] = TRUE;
			if ( ! empty($snapshot['version']['source_code']))
			{
				$sources_present[$snapshot['version']['source_code']] = TRUE;
			}
			foreach ($snapshot['series'] as $series)
			{
				$indicators_present[$series['code']] = $series['label'];
			}
		}
		krsort($years);
		asort($indicators_present);

		$visible = array();
		foreach ($all as $snapshot)
		{
			if ($filters['tahun'] && (int) $snapshot['version']['period_year'] !== $filters['tahun'])
			{
				continue;
			}
			if ($filters['tema'] !== '' && $snapshot['dataset']['theme'] !== $filters['tema'])
			{
				continue;
			}
			if ($filters['sumber'] !== '' && (string) $snapshot['version']['source_code'] !== $filters['sumber'])
			{
				continue;
			}
			if ($filters['indikator'] !== '' && ! $this->has_indicator($snapshot, $filters['indikator']))
			{
				continue;
			}
			if ($filters['q'] !== '' && ! $this->matches($snapshot, $filters['q']))
			{
				continue;
			}
			$visible[] = $snapshot;
		}

		$themes = $this->datasets->themes();
		$title = $locked_theme !== NULL ? 'Data Desa: '.$themes[$locked_theme] : 'Data Desa';

		$this->render('site/data_desa', array(
			'page_title' => $title,
			'meta_description' => 'Data dan statistik Desa Cihawuk yang sudah diverifikasi, lengkap dengan tahun, sumber, dan tabel angka.',
			'datasets' => $visible,
			'total' => count($all),
			'years' => array_keys($years),
			'themes' => $themes,
			'themes_present' => array_keys($themes_present),
			'sources_present' => array_keys($sources_present),
			'indicators_present' => $indicators_present,
			'locked_theme' => $locked_theme,
			'filters' => $filters,
			'extra_js' => array('vendor/chartjs/chart.umd.min.js', 'site/js/charts.js'),
		), 'site');
	}

	protected function has_indicator(array $snapshot, $code)
	{
		foreach ($snapshot['series'] as $series)
		{
			if ($series['code'] === $code)
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	/** Pencarian sederhana pada nama dataset dan label indikator. */
	protected function matches(array $snapshot, $keyword)
	{
		if (mb_stripos($snapshot['dataset']['name'], $keyword) !== FALSE
			OR mb_stripos((string) $snapshot['dataset']['description'], $keyword) !== FALSE)
		{
			return TRUE;
		}
		foreach ($snapshot['series'] as $series)
		{
			if (mb_stripos($series['label'], $keyword) !== FALSE)
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	/** Unduhan CSV satu dataset terbit; isinya sama dengan tabel di halaman. */
	public function unduh($slug = '')
	{
		$snapshot = $this->datasets->published_dataset($slug);
		if ( ! $snapshot)
		{
			$this->not_found_response();
			return;
		}
		$filename = 'data-'.preg_replace('/[^a-z0-9\-]/', '', (string) $slug).'-'.$snapshot['version']['period_year'].'.csv';
		$handle = fopen('php://temp', 'r+');
		foreach ($this->datasets->csv_rows($snapshot) as $row)
		{
			fputcsv($handle, array_map(array($this, 'csv_cell'), $row));
		}
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		$this->output
			->set_content_type('text/csv', 'utf-8')
			->set_header('Content-Disposition: attachment; filename="'.$filename.'"')
			->set_header('X-Content-Type-Options: nosniff')
			// BOM agar Excel membaca UTF-8 dengan benar.
			->set_output("\xEF\xBB\xBF".$csv);
	}

	/** Cegah formula injection pada pembaca spreadsheet. */
	protected function csv_cell($value)
	{
		$value = (string) $value;
		return ($value !== '' && strpos('=+-@', $value[0]) !== FALSE) ? "'".$value : $value;
	}
}
