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
			'view' => $budget ? $this->explain($budget) : NULL,
			'extra_css' => array('site/css/anggaran.css'),
			'extra_js' => array('vendor/chartjs/chart.umd.min.js', 'site/js/charts.js'),
		), 'site');
	}

	/**
	 * Susun snapshot menjadi bahan tampilan yang mudah dibaca: pohon bidang/subbidang/kegiatan,
	 * data grafik, dan sorotan. Total simpul selalu dihitung dari daun, sehingga baris induk
	 * tidak pernah terhitung dua kali, berapa pun kedalamannya.
	 */
	protected function explain(array $budget)
	{
		$revisions = $budget['revisions'] ?? array();
		$base = ($revisions['amended'] ?? NULL) ?: ($revisions['original'] ?? NULL);
		if ( ! $base)
		{
			return NULL;
		}
		$income = array();
		$bidang = array();
		foreach ($this->tree($base['lines']) as $node)
		{
			if ($node['section'] === 'income') { $income[] = $node; }
			elseif ($node['section'] === 'expenditure') { $bidang[] = $node; }
		}
		$spend = array_sum(array_column($bidang, 'total'));
		foreach ($bidang as &$node)
		{
			$node['share'] = $spend > 0 ? $node['total'] / $spend : 0;
			$node['short'] = $this->short_name($node['name']);
			$node['about'] = $this->bidang_about($node['name']);
		}
		unset($node);

		$ranked = $bidang;
		usort($ranked, function ($a, $b) { return $b['total'] <=> $a['total']; });
		$per100 = array();
		foreach (array_slice($ranked, 0, 3) as $node)
		{
			$per100[] = array('name' => $node['short'], 'amount' => round($node['share'] * 100000, -3));
		}

		// Rencana vs realisasi per bidang, dicocokkan lewat category_id (kategori dipakai
		// bersama oleh semua revisi dalam satu tahun).
		$compare = NULL;
		if ( ! empty($revisions['realization']))
		{
			$real = array();
			foreach ($this->tree($revisions['realization']['lines']) as $node)
			{
				$real[$node['id']] = $node['total'];
			}
			$compare = array('labels' => array(), 'plan' => array(), 'real' => array(),
				'plan_label' => $base['label'], 'real_label' => $revisions['realization']['label']);
			foreach ($bidang as $node)
			{
				$compare['labels'][] = $node['short'];
				$compare['plan'][] = $node['total'];
				$compare['real'][] = $real[$node['id']] ?? 0;
			}
		}

		$highlights = array();
		$specs = array(
			array('pattern' => '/bantuan|\bBLT\b/i', 'title' => 'Bantuan untuk warga', 'icon' => 'heart',
				'intro' => 'Bagian anggaran yang langsung diterima warga, misalnya bantuan tunai atau bantuan saat bencana.'),
			array('pattern' => '/transportasi|\bjalan\b/i', 'title' => 'Transportasi dan jalan desa', 'icon' => 'truck',
				'intro' => 'Pembangunan dan perawatan jalan yang dipakai warga sehari-hari, termasuk akses ke kebun dan pasar.'),
		);
		foreach ($specs as $spec)
		{
			$found = array();
			$this->find_nodes($bidang, $spec['pattern'], $found);
			if ($found)
			{
				$spec['nodes'] = $found;
				$spec['total'] = array_sum(array_column($found, 'total'));
				$highlights[] = $spec;
			}
		}

		$ordered = array();
		foreach ($revisions as $type => $revision)
		{
			$ordered[$type] = array();
			$this->flatten($this->tree($revision['lines']), $ordered[$type]);
		}

		return array(
			'base' => $base,
			'is_demo' => stripos((string) ($budget['year']['note'] ?? ''), 'contoh') !== FALSE,
			'income' => $income,
			'bidang' => $bidang,
			'spend' => $spend,
			'per100' => $per100,
			'compare' => $compare,
			'highlights' => $highlights,
			'ordered' => $ordered,
		);
	}

	/** Pohon dari baris snapshot lewat parent_id; urutan saudara mengikuti urutan baris. */
	protected function tree(array $lines)
	{
		$nodes = array();
		foreach ($lines as $line)
		{
			$id = (int) ($line['category_id'] ?? 0);
			$nodes[$id] = $line + array('id' => $id, 'child_ids' => array());
		}
		$roots = array();
		foreach (array_keys($nodes) as $id)
		{
			$parent = $nodes[$id]['parent_id'];
			if ($parent && isset($nodes[$parent]))
			{
				$nodes[$parent]['child_ids'][] = $id;
			}
			else
			{
				$roots[] = $id;
			}
		}
		$build = function ($id) use (&$build, &$nodes) {
			$node = $nodes[$id];
			$node['children'] = array_map($build, $node['child_ids']);
			unset($node['child_ids']);
			$node['total'] = $node['children'] ? array_sum(array_column($node['children'], 'total')) : (float) $node['amount'];
			return $node;
		};
		return array_map($build, $roots);
	}

	protected function flatten(array $nodes, array &$out, $depth = 0)
	{
		foreach ($nodes as $node)
		{
			$children = $node['children'];
			unset($node['children']);
			$node['depth'] = $depth;
			$out[] = $node;
			$this->flatten($children, $out, $depth + 1);
		}
	}

	/** Simpul tertinggi (di bawah bidang) yang cocok; turunannya tidak diambil lagi. */
	protected function find_nodes(array $nodes, $pattern, array &$found)
	{
		foreach ($nodes as $node)
		{
			if ((int) $node['level'] > 1 && preg_match($pattern, $node['name']))
			{
				$found[] = $node;
				continue;
			}
			$this->find_nodes($node['children'], $pattern, $found);
		}
	}

	protected function short_name($name)
	{
		$map = array('/pemerintahan/i' => 'Pemerintahan', '/pembangunan/i' => 'Pembangunan', '/pembinaan/i' => 'Pembinaan masyarakat',
			'/pemberdayaan/i' => 'Pemberdayaan', '/bencana|darurat|mendesak/i' => 'Bencana & mendesak');
		foreach ($map as $pattern => $short)
		{
			if (preg_match($pattern, $name)) { return $short; }
		}
		return $name;
	}

	/** Penjelasan umum lima bidang belanja APBDes (cakupan bidang, bukan angka). */
	protected function bidang_about($name)
	{
		$about = array(
			'/pemerintahan/i' => 'Biaya menjalankan kantor desa: penghasilan perangkat, operasional kantor, BPD, serta RT dan RW.',
			'/pembangunan/i' => 'Membangun dan memperbaiki sarana yang dipakai bersama: jalan, air bersih, drainase, dan fasilitas kesehatan.',
			'/pembinaan/i' => 'Kegiatan kemasyarakatan: keamanan lingkungan, kepemudaan dan olahraga, serta lembaga adat dan keagamaan.',
			'/pemberdayaan/i' => 'Meningkatkan kemampuan warga: pelatihan petani dan UMKM, kesehatan ibu dan anak, serta kapasitas aparatur.',
			'/bencana|darurat|mendesak/i' => 'Cadangan untuk bencana dan keadaan mendesak, termasuk bantuan langsung kepada warga yang membutuhkan.',
		);
		foreach ($about as $pattern => $text)
		{
			if (preg_match($pattern, $name)) { return $text; }
		}
		return '';
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
