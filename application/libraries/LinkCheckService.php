<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pemeriksa tautan internal yang rusak (modul-backend 25).
 *
 * Yang diperiksa: item menu terbit, tautan pada section halaman terbit, dan dokumen publik.
 * Hanya path internal yang ditelusuri; tautan `http(s)://` ke luar tidak dipanggil dari
 * server supaya pemeriksaan tidak berubah menjadi permintaan keluar yang tidak diminta.
 */
class LinkCheckService {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('CmsMenuService', NULL, 'menu_service');
	}

	/**
	 * Jalankan pemeriksaan penuh dan simpan hasilnya.
	 * @return array ringkasan ok, broken, external
	 */
	public function run()
	{
		$now = utc_now();
		$results = array();

		foreach ($this->CI->menu_service->locations() as $location => $label)
		{
			$snapshot = $this->CI->menu_service->published_menu($location);
			foreach ($this->flatten_menu(is_array($snapshot) ? $snapshot : array()) as $item)
			{
				$results[] = $this->check('menu', $label.': '.$item['label'], $location, (string) $item['href']);
			}
		}

		foreach ($this->CI->cms->published_pages() as $page)
		{
			$layout = $this->CI->db->select('snapshot_json')->from('cms_publication_snapshots')
				->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->where('superseded_at IS NULL', NULL, FALSE)->limit(1)->get()->row();
			if ( ! $layout)
			{
				continue;
			}
			$snapshot = json_decode((string) $layout->snapshot_json, TRUE);
			foreach (($snapshot['sections'] ?? array()) as $section)
			{
				foreach (array('cta', 'links') as $field)
				{
					foreach (($section['config'][$field] ?? array()) as $link)
					{
						$results[] = $this->check('section', ($page->page_key ?: $page->public_id).': '.(string) ($link['label'] ?? ''),
							(string) ($section['public_id'] ?? ''), (string) ($link['url'] ?? ''));
					}
				}
			}
		}

		foreach ($this->CI->db->where('publication_status', 'published')
			->where('deleted_at IS NULL', NULL, FALSE)->get('public_documents')->result() as $document)
		{
			$results[] = $this->check('document', $document->title, (string) $document->id,
				'/dokumen/'.(int) $document->id.'/unduh');
		}

		$summary = array('ok' => 0, 'broken' => 0, 'external' => 0);
		db_transaction(function () use ($results, $now, &$summary) {
			$this->CI->db->empty_table('link_check_results');
			foreach ($results as $result)
			{
				$summary[$result['status']] = ($summary[$result['status']] ?? 0) + 1;
				db_must($this->CI->db->insert('link_check_results', array(
					'source_type' => $result['source_type'],
					'source_label' => mb_substr($result['source_label'], 0, 220),
					'source_reference' => mb_substr((string) $result['source_reference'], 0, 120) ?: NULL,
					'target_path' => mb_substr($result['target_path'], 0, 500),
					'status' => $result['status'],
					'detail' => $result['detail'] ? mb_substr($result['detail'], 0, 500) : NULL,
					'checked_at' => $now,
				)), 'link_check_results.insert');
			}
		});

		$this->CI->audit->log('maintenance.link_check', 'link_check', 'all', $summary, FALSE, 'public_website');
		return $summary;
	}

	protected function flatten_menu(array $items)
	{
		$out = array();
		foreach ($items as $item)
		{
			$out[] = $item;
			foreach ($this->flatten_menu($item['children'] ?? array()) as $child)
			{
				$out[] = $child;
			}
		}
		return $out;
	}

	/** @return array{source_type:string, source_label:string, source_reference:string, target_path:string, status:string, detail:?string} */
	protected function check($type, $label, $reference, $path)
	{
		$row = array(
			'source_type' => $type,
			'source_label' => (string) $label,
			'source_reference' => (string) $reference,
			'target_path' => (string) $path,
			'status' => 'ok',
			'detail' => NULL,
		);
		$path = trim((string) $path);

		if ($path === '')
		{
			$row['status'] = 'broken';
			$row['detail'] = 'Tautan kosong.';
			return $row;
		}
		if (preg_match('#^https?://#i', $path))
		{
			// Tautan luar dicatat, tidak dipanggil.
			$row['status'] = 'external';
			$row['detail'] = 'Tautan eksternal tidak diperiksa dari server.';
			return $row;
		}
		if (strpos($path, '#') === 0 OR strpos($path, 'mailto:') === 0 OR strpos($path, 'tel:') === 0)
		{
			$row['status'] = 'ok';
			return $row;
		}

		$clean = trim(parse_url($path, PHP_URL_PATH) ?: '', '/');
		if ($clean === '')
		{
			return $row;
		}
		if ($this->matches_route($clean))
		{
			return $row;
		}
		if ($this->CI->cms->page_by_path($clean))
		{
			return $row;
		}
		if ($this->CI->db->where('old_path', '/'.$clean)->count_all_results('cms_redirects') > 0)
		{
			$row['status'] = 'ok';
			$row['detail'] = 'Dialihkan lewat redirect slug lama.';
			return $row;
		}

		$row['status'] = 'broken';
		$row['detail'] = 'Tidak cocok dengan route mana pun dan bukan halaman CMS terbit.';
		return $row;
	}

	/** @var array<string,bool>|null daftar pola route, dibaca sekali per permintaan */
	protected $route_patterns = NULL;

	/** Cocokkan path dengan daftar route literal dan wildcard satu segmen. */
	protected function matches_route($path)
	{
		if ($this->route_patterns === NULL)
		{
			// Berkas ini mengisi $route; wildcard CI3 (:any) berarti satu segmen.
			$route = array();
			require APPPATH.'config/routes.php';
			$this->route_patterns = array();
			foreach (array_keys($route) as $pattern)
			{
				if (in_array($pattern, array('default_controller', '404_override', 'translate_uri_dashes'), TRUE))
				{
					continue;
				}
				// Pola yang diawali wildcard menangkap SEMUA path: dua route halaman CMS dan
				// route penangkap terakhir. Kalau ikut dipakai di sini, setiap tautan akan
				// terlihat sehat. Halaman CMS diperiksa terpisah lewat `page_by_path()`.
				if ($pattern[0] === '(')
				{
					continue;
				}
				$this->route_patterns[] = str_replace(array('(:any)', '(:num)'), array('[^/]+', '[0-9]+'), $pattern);
			}
		}
		foreach ($this->route_patterns as $regex)
		{
			if (preg_match('#^'.$regex.'$#', $path))
			{
				return TRUE;
			}
		}
		// Berkas statis di public/ juga dianggap sah.
		return is_file(FCPATH.$path);
	}

	public function results($status = NULL, $limit = 200)
	{
		if ($status !== NULL)
		{
			$this->CI->db->where('status', $status);
		}
		return $this->CI->db->order_by('status')->order_by('source_type')
			->limit((int) $limit)->get('link_check_results')->result();
	}

	public function last_run()
	{
		return $this->CI->db->select_max('checked_at')->get('link_check_results')->row('checked_at');
	}
}
