<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dokumen publik: hanya salinan yang sudah disetujui. Dokumen sumber penelitian
 * tetap privat dan tidak pernah tampil di sini.
 */
class Dokumen extends Public_Controller {

	public function index()
	{
		$category = (string) $this->input->get('kategori');
		$year = (int) $this->input->get('tahun');
		$documents = $this->content->public_documents($category !== '' ? $category : NULL, $year > 0 ? $year : NULL);
		$years = array();
		foreach ($this->content->public_documents() as $doc)
		{
			if ($doc->source_year)
			{
				$years[(int) $doc->source_year] = TRUE;
			}
		}
		krsort($years);
		$this->render('site/dokumen', array(
			'apbdes' => $this->apbdes_card(),
			'extra_css' => array('site/css/anggaran.css'),
			'extra_js' => array('vendor/chartjs/chart.umd.min.js', 'site/js/charts.js'),
			'page_title' => 'Dokumen Publik',
			'documents' => $documents,
			'categories' => $this->db->where('content_type', 'document')->order_by('sort_order')->get('content_categories')->result(),
			'years' => array_keys($years),
			'active_category' => $category,
			'active_year' => $year,
		), 'site');
	}

	/**
	 * Ringkasan APBDes terbit terakhir untuk kartu sorotan: tiga angka utama dan belanja per
	 * bidang (dijumlah dari daun, sehingga baris induk tidak terhitung dua kali).
	 */
	protected function apbdes_card()
	{
		$this->load->library('BudgetService', NULL, 'budgets');
		$budget = $this->budgets->published_budget();
		$revisions = $budget['revisions'] ?? array();
		$base = ($revisions['amended'] ?? NULL) ?: ($revisions['original'] ?? NULL);
		if ( ! $base)
		{
			return NULL;
		}
		$parents = array();
		foreach ($base['lines'] as $line)
		{
			if ( ! empty($line['parent_id'])) { $parents[(int) $line['parent_id']] = TRUE; }
		}
		$by_id = array();
		foreach ($base['lines'] as $line) { $by_id[(int) $line['category_id']] = $line; }
		$bidang = array();
		foreach ($base['lines'] as $line)
		{
			if ($line['section'] !== 'expenditure' OR isset($parents[(int) $line['category_id']])) { continue; }
			$root = $line;
			while ( ! empty($root['parent_id']) && isset($by_id[(int) $root['parent_id']])) { $root = $by_id[(int) $root['parent_id']]; }
			$key = (int) $root['category_id'];
			if ( ! isset($bidang[$key])) { $bidang[$key] = array('name' => $root['name'], 'total' => 0.0); }
			$bidang[$key]['total'] += (float) $line['amount'];
		}
		return array(
			'fiscal_year' => (int) $budget['year']['fiscal_year'],
			'label' => $base['label'],
			'is_demo' => stripos((string) ($budget['year']['note'] ?? ''), 'contoh') !== FALSE,
			'totals' => $base['totals'],
			'bidang' => array_values($bidang),
		);
	}

	public function unduh($id)
	{
		$document = $this->content->public_document((int) $id);
		if ( ! $document)
		{
			$this->not_found_response();
			return;
		}
		$path = FCPATH.'media/'.ltrim($document->storage_key, '/');
		$real = realpath($path);
		if ($real === FALSE OR strpos(str_replace('\\', '/', $real), str_replace('\\', '/', realpath(FCPATH.'media')).'/') !== 0 OR ! is_file($real))
		{
			$this->not_found_response();
			return;
		}
		$this->audit->log('document.download', 'public_document', (string) $document->id, array('title' => $document->title));
		$this->output
			->set_header('Content-Type: '.$document->mime_type)
			->set_header('Content-Disposition: attachment; filename="'.str_replace('"', '', $document->original_name).'"')
			->set_header('Content-Length: '.filesize($real))
			->set_header('X-Content-Type-Options: nosniff');
		$this->output->_display();
		readfile($real);
		exit(EXIT_SUCCESS);
	}
}
