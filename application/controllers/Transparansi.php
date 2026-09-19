<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Transparansi anggaran publik (modul-frontend 11). Halaman ini hanya membaca snapshot
 * yang sudah diterbitkan; tabel kerja keuangan tidak pernah dibaca langsung.
 */
class Transparansi extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('BudgetService', NULL, 'budgets');
	}

	public function anggaran()
	{
		$years = $this->budgets->published_years();
		$selected = (int) $this->input->get('tahun');
		$budget = $this->budgets->published_budget($selected ?: NULL);

		$this->render('site/transparansi_anggaran', array(
			'page_title' => 'Transparansi Anggaran',
			'meta_description' => 'Ringkasan, perbandingan, dan rincian APBDes Desa Cihawuk yang sudah disetujui untuk publikasi.',
			'years' => $years,
			'selected' => $selected,
			'budget' => $budget,
			'sections' => BudgetService::SECTIONS,
			'revision_types' => BudgetService::REVISION_TYPES,
		), 'site');
	}

	/** Unduhan JSON snapshot publik; isinya sama persis dengan yang dirender halaman. */
	public function anggaran_json($fiscal_year = '')
	{
		$budget = $this->budgets->published_budget((int) $fiscal_year ?: NULL);
		if ( ! $budget)
		{
			$this->not_found_response();
			return;
		}
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('X-Content-Type-Options: nosniff')
			->set_output(json_encode($budget, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	}

	/** Unduhan CSV rincian satu revisi, dengan pengaman formula injection. */
	public function anggaran_csv($fiscal_year = '', $revision_type = 'original')
	{
		$budget = $this->budgets->published_budget((int) $fiscal_year ?: NULL);
		if ( ! $budget OR empty($budget['revisions'][$revision_type]))
		{
			$this->not_found_response();
			return;
		}
		$revision = $budget['revisions'][$revision_type];
		$handle = fopen('php://temp', 'r+');
		fputcsv($handle, array('kelompok', 'kode', 'uraian', 'tingkat', 'jumlah', 'catatan'));
		foreach ($revision['lines'] as $line)
		{
			fputcsv($handle, array_map(array($this, 'csv_cell'), array(
				BudgetService::SECTIONS[$line['section']] ?? $line['section'],
				(string) $line['code'],
				$line['name'],
				(string) $line['level'],
				number_format((float) $line['amount'], 2, '.', ''),
				(string) $line['variance_note'],
			)));
		}
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		$this->output
			->set_content_type('text/csv', 'utf-8')
			->set_header('Content-Disposition: attachment; filename="apbdes-'.(int) $budget['year']['fiscal_year'].'-'.preg_replace('/[^a-z]/', '', $revision_type).'.csv"')
			->set_header('X-Content-Type-Options: nosniff')
			->set_output("\xEF\xBB\xBF".$csv);
	}

	protected function csv_cell($value)
	{
		$value = (string) $value;
		return ($value !== '' && strpos('=+-@', $value[0]) !== FALSE) ? "'".$value : $value;
	}
}
