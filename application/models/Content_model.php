<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Query konten publik. Semua method publik hanya mengembalikan konten terbit
 * (dan terverifikasi bila berlaku), kecuali mode pratinjau editor aktif.
 */
class Content_model extends CI_Model {

	/** @var bool tampilkan draft/in_review (hanya untuk editor berizin) */
	public $preview = FALSE;

	protected function publication_scope($column = 'publication_status')
	{
		if ($this->preview)
		{
			$this->db->where_in($column, array('draft', 'in_review', 'published'));
		}
		else
		{
			$this->db->where($column, 'published');
		}
	}

	public function village_profile()
	{
		$this->publication_scope();
		return $this->db->order_by('id')->limit(1)->get('village_profiles')->row();
	}

	public function navigation($menu_key)
	{
		$rows = $this->db->where(array('menu_key' => $menu_key, 'active' => 1))
			->order_by('parent_id IS NULL', 'DESC', FALSE)->order_by('sort_order')->get('navigation_items')->result();
		$tree = array();
		$children = array();
		foreach ($rows as $row)
		{
			if ( ! app_is_safe_url($row->target_url))
			{
				continue;
			}
			if ($row->parent_id === NULL)
			{
				$row->children = array();
				$tree[(int) $row->id] = $row;
			}
			else
			{
				$children[] = $row;
			}
		}
		foreach ($children as $child)
		{
			if (isset($tree[(int) $child->parent_id]))
			{
				$tree[(int) $child->parent_id]->children[] = $child;
			}
		}
		return array_values($tree);
	}

	public function media($id)
	{
		if ($id === NULL)
		{
			return NULL;
		}
		$this->db->where('id', (int) $id)->where('deleted_at IS NULL', NULL, FALSE);
		if ( ! $this->preview)
		{
			$this->db->where('publication_status', 'published')
				->where_in('rights_status', array('owned', 'licensed', 'permission_granted'));
		}
		return $this->db->get('media_assets')->row();
	}

	public function hero()
	{
		$slide = $this->db->where('active', 1)->order_by('sort_order')->limit(1)->get('hero_slides')->row();
		if ($slide)
		{
			$slide->image = $this->media($slide->image_media_id);
			$slide->video = $this->media($slide->video_media_id);
			$slide->cta_primary = $this->safe_cta($slide->cta_primary_json);
			$slide->cta_secondary = $this->safe_cta($slide->cta_secondary_json);
		}
		return $slide;
	}

	protected function safe_cta($json)
	{
		$cta = json_decode((string) $json, TRUE);
		if ( ! is_array($cta) OR empty($cta['label']) OR empty($cta['url']) OR ! app_is_safe_url($cta['url']))
		{
			return NULL;
		}
		return array('label' => (string) $cta['label'], 'url' => (string) $cta['url']);
	}

	// ------------------------------------------------------------ Statistik

	/**
	 * Nilai statistik terbit + terverifikasi untuk satu tahun.
	 * @return array<string, object> kode indikator => baris
	 */
	public function statistics_for_year($year, array $codes = array())
	{
		$this->db->select('sv.*, si.code, si.label, si.unit, si.group_code, si.value_type, si.composition_group, si.chart_type, si.definition, si.display_order, sd.title AS source_title, sd.source_code')
			->from('statistic_values sv')
			->join('statistic_indicators si', 'si.id = sv.indicator_id')
			->join('source_documents sd', 'sd.id = sv.source_id', 'left')
			->where('sv.source_year', (int) $year);
		if ($this->preview)
		{
			$this->db->where_in('sv.publication_status', array('draft', 'in_review', 'published'));
		}
		else
		{
			$this->db->where('sv.publication_status', 'published')->where('sv.verification_status', 'verified');
		}
		if ( ! empty($codes))
		{
			$this->db->where_in('si.code', $codes);
		}
		$out = array();
		foreach ($this->db->order_by('si.display_order')->get()->result() as $row)
		{
			$out[$row->code] = $row;
		}
		return $out;
	}

	public function statistic_years()
	{
		$this->db->distinct()->select('source_year')->from('statistic_values');
		if ($this->preview)
		{
			$this->db->where_in('publication_status', array('draft', 'in_review', 'published'));
		}
		else
		{
			$this->db->where('publication_status', 'published')->where('verification_status', 'verified');
		}
		return array_map(function ($r) { return (int) $r->source_year; }, $this->db->order_by('source_year', 'DESC')->get()->result());
	}

	/** Nilai terbit terbaru untuk satu indikator. */
	public function latest_statistic($code)
	{
		foreach ($this->statistic_years() as $year)
		{
			$stats = $this->statistics_for_year($year, array($code));
			if (isset($stats[$code]))
			{
				return $stats[$code];
			}
		}
		return NULL;
	}

	/** Tahun terbaru yang memiliki keempat metrik beranda lengkap. */
	public function homepage_statistics()
	{
		$codes = array('population_total', 'population_male', 'population_female', 'households');
		foreach ($this->statistic_years() as $year)
		{
			$stats = $this->statistics_for_year($year, $codes);
			if (count($stats) === 4)
			{
				return array('year' => $year, 'items' => $stats);
			}
		}
		return NULL;
	}

	// ------------------------------------------------------------ Potensi

	public function potential_categories()
	{
		return $this->db->where('content_type', 'potential')->order_by('sort_order')->get('content_categories')->result();
	}

	public function potentials($category_slug = NULL, $limit = NULL)
	{
		$this->db->select('p.*, c.name AS category_name, c.slug AS category_slug')
			->from('potentials p')
			->join('content_categories c', 'c.id = p.category_id')
			->where('p.deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope('p.publication_status');
		if ($category_slug !== NULL)
		{
			$this->db->where('c.slug', $category_slug);
		}
		$this->db->order_by('p.sort_order')->order_by('p.published_at', 'DESC');
		if ($limit !== NULL)
		{
			$this->db->limit((int) $limit);
		}
		$rows = $this->db->get()->result();
		foreach ($rows as $row)
		{
			$row->cover = $this->media($row->cover_media_id);
		}
		return $rows;
	}

	public function potential_by_slug($slug)
	{
		$this->db->select('p.*, c.name AS category_name, c.slug AS category_slug, sd.title AS source_title')
			->from('potentials p')
			->join('content_categories c', 'c.id = p.category_id')
			->join('source_documents sd', 'sd.id = p.source_id', 'left')
			->where('p.slug', (string) $slug)
			->where('p.deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope('p.publication_status');
		$row = $this->db->get()->row();
		if ($row)
		{
			$row->cover = $this->media($row->cover_media_id);
			$row->gallery = array();
			$items = $this->db->order_by('sort_order')->get_where('potential_media', array('potential_id' => (int) $row->id))->result();
			foreach ($items as $item)
			{
				$media = $this->media($item->media_asset_id);
				if ($media)
				{
					$row->gallery[] = $media;
				}
			}
			$row->map_feature = $this->map_feature($row->map_feature_id);
		}
		return $row;
	}

	public function map_feature($id)
	{
		if ($id === NULL)
		{
			return NULL;
		}
		$this->db->where('id', (int) $id);
		if ( ! $this->preview)
		{
			$this->db->where('publication_status', 'published')->where('verification_status', 'verified');
		}
		return $this->db->get('map_features')->row();
	}

	public function map_features($types = array('office', 'potential', 'facility', 'boundary'))
	{
		$this->db->where_in('type', $types);
		if ( ! $this->preview)
		{
			$this->db->where('publication_status', 'published')->where('verification_status', 'verified')->where('is_demo', 0);
		}
		return $this->db->get('map_features')->result();
	}

	// ------------------------------------------------------------ Berita

	public function posts($type = 'news', $category_slug = NULL, $limit = 9, $offset = 0, $exclude_id = NULL)
	{
		$this->post_query($type, $category_slug, $exclude_id);
		$rows = $this->db->order_by('p.is_featured', 'DESC')->order_by('p.published_at', 'DESC')->order_by('p.id', 'DESC')
			->limit((int) $limit, (int) $offset)->get()->result();
		foreach ($rows as $row)
		{
			$row->cover = $this->media($row->cover_media_id);
		}
		return $rows;
	}

	public function count_posts($type = 'news', $category_slug = NULL)
	{
		$this->post_query($type, $category_slug, NULL);
		return (int) $this->db->count_all_results();
	}

	protected function post_query($type, $category_slug, $exclude_id)
	{
		$this->db->select('p.*, c.name AS category_name, c.slug AS category_slug, u.display_name AS author_name')
			->from('posts p')
			->join('content_categories c', 'c.id = p.category_id', 'left')
			->join('users u', 'u.id = p.author_id', 'left')
			->where('p.deleted_at IS NULL', NULL, FALSE);
		if (is_array($type))
		{
			$this->db->where_in('p.type', $type);
		}
		else
		{
			$this->db->where('p.type', $type);
		}
		$this->publication_scope('p.publication_status');
		if ( ! $this->preview)
		{
			$this->db->where('p.published_at <=', utc_now());
		}
		if ($category_slug !== NULL)
		{
			$this->db->where('c.slug', $category_slug);
		}
		if ($exclude_id !== NULL)
		{
			$this->db->where('p.id !=', (int) $exclude_id);
		}
	}

	public function post_by_slug($slug, $types = array('news', 'announcement'))
	{
		$this->post_query($types, NULL, NULL);
		$row = $this->db->where('p.slug', (string) $slug)->get()->row();
		if ($row)
		{
			$row->cover = $this->media($row->cover_media_id);
		}
		return $row;
	}

	public function redirect_for($content_type, $old_slug)
	{
		return $this->db->get_where('slug_redirects', array('content_type' => $content_type, 'old_slug' => (string) $old_slug))->row();
	}

	// ------------------------------------------------------------ Agenda

	public function events($upcoming = TRUE, $limit = 10, $offset = 0)
	{
		$this->event_query($upcoming);
		$this->db->order_by('starts_at', $upcoming ? 'ASC' : 'DESC')->limit((int) $limit, (int) $offset);
		$rows = $this->db->get()->result();
		foreach ($rows as $row)
		{
			$row->poster = $this->media($row->poster_media_id);
		}
		return $rows;
	}

	public function count_events($upcoming = TRUE)
	{
		$this->event_query($upcoming);
		return (int) $this->db->count_all_results();
	}

	protected function event_query($upcoming)
	{
		$this->db->from('events')->where('deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope();
		$now = utc_now();
		if ($upcoming)
		{
			$this->db->group_start()->where('starts_at >=', $now)->or_where('ends_at >=', $now)->group_end();
		}
		else
		{
			$this->db->where('COALESCE(ends_at, starts_at) <', $now);
		}
	}

	public function event_by_slug($slug)
	{
		$this->db->where('slug', (string) $slug)->where('deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope();
		$row = $this->db->get('events')->row();
		if ($row)
		{
			$row->poster = $this->media($row->poster_media_id);
		}
		return $row;
	}

	// ------------------------------------------------------------ Galeri & dokumen

	public function galleries()
	{
		$this->db->where('deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope();
		$rows = $this->db->order_by('published_at', 'DESC')->order_by('id', 'DESC')->get('galleries')->result();
		foreach ($rows as $row)
		{
			$row->items = $this->gallery_items($row->id, 4);
		}
		return array_values(array_filter($rows, function ($g) { return ! empty($g->items); }));
	}

	public function gallery_by_slug($slug)
	{
		$this->db->where('slug', (string) $slug)->where('deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope();
		$row = $this->db->get('galleries')->row();
		if ($row)
		{
			$row->items = $this->gallery_items($row->id);
		}
		return $row;
	}

	protected function gallery_items($gallery_id, $limit = NULL)
	{
		$this->db->where('gallery_id', (int) $gallery_id)->order_by('sort_order');
		if ($limit !== NULL)
		{
			$this->db->limit($limit * 3);
		}
		$items = array();
		foreach ($this->db->get('gallery_items')->result() as $item)
		{
			$media = $this->media($item->media_asset_id);
			if ($media)
			{
				$media->caption = $item->caption;
				$items[] = $media;
			}
			if ($limit !== NULL && count($items) >= $limit)
			{
				break;
			}
		}
		return $items;
	}

	public function public_documents($category_slug = NULL, $year = NULL)
	{
		$this->db->select('d.*, c.name AS category_name, c.slug AS category_slug, m.byte_size, m.mime_type, m.original_name')
			->from('public_documents d')
			->join('media_assets m', 'm.id = d.media_asset_id')
			->join('content_categories c', 'c.id = d.category_id', 'left')
			->where('d.deleted_at IS NULL', NULL, FALSE)
			->where('m.deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope('d.publication_status');
		if ( ! $this->preview)
		{
			$this->db->where('m.publication_status', 'published');
		}
		if ($category_slug !== NULL)
		{
			$this->db->where('c.slug', $category_slug);
		}
		if ($year !== NULL)
		{
			$this->db->where('d.source_year', (int) $year);
		}
		return $this->db->order_by('d.source_year', 'DESC')->order_by('d.title')->get()->result();
	}

	public function public_document($id)
	{
		$this->db->select('d.*, m.storage_key, m.mime_type, m.original_name, m.byte_size')
			->from('public_documents d')
			->join('media_assets m', 'm.id = d.media_asset_id')
			->where('d.id', (int) $id)
			->where('d.deleted_at IS NULL', NULL, FALSE)
			->where('m.deleted_at IS NULL', NULL, FALSE);
		$this->publication_scope('d.publication_status');
		return $this->db->get()->row();
	}

	// ------------------------------------------------------------ Pemerintahan

	public function government_structure()
	{
		$this->db->from('official_positions');
		$this->publication_scope();
		$positions = $this->db->order_by('sort_order')->get()->result();
		foreach ($positions as $pos)
		{
			$this->db->from('officials')->where('position_id', (int) $pos->id);
			if ( ! $this->preview)
			{
				$this->db->where('publication_status', 'published')->where('verification_status', 'verified');
			}
			$pos->officials = $this->db->order_by('term_start', 'DESC')->order_by('sort_order')->get()->result();
			foreach ($pos->officials as $official)
			{
				$official->photo = $this->media($official->photo_media_id);
			}
		}
		return $positions;
	}

	// ------------------------------------------------------------ Pencarian

	/**
	 * Pencarian konten publik terbit saja (berita, pengumuman, potensi, agenda, dokumen).
	 * Tidak pernah mencari tiket, akun, atau dokumen privat.
	 */
	public function search($keyword, $limit = 30)
	{
		$keyword = trim((string) $keyword);
		if (mb_strlen($keyword) < 3)
		{
			return array();
		}
		$results = array();
		$saved_preview = $this->preview;
		$this->preview = FALSE;

		$this->post_query(array('news', 'announcement'), NULL, NULL);
		$this->db->group_start()->like('p.title', $keyword)->or_like('p.excerpt', $keyword)->group_end();
		foreach ($this->db->limit($limit)->get()->result() as $row)
		{
			$results[] = array('type' => ($row->type === 'announcement') ? 'Pengumuman' : 'Berita', 'title' => $row->title, 'summary' => $row->excerpt, 'url' => '/berita/'.$row->slug, 'date' => $row->published_at);
		}

		$this->db->from('potentials')->where('deleted_at IS NULL', NULL, FALSE)->where('publication_status', 'published')
			->group_start()->like('title', $keyword)->or_like('summary', $keyword)->group_end();
		foreach ($this->db->limit($limit)->get()->result() as $row)
		{
			$results[] = array('type' => 'Potensi', 'title' => $row->title, 'summary' => $row->summary, 'url' => '/potensi/'.$row->slug, 'date' => $row->published_at);
		}

		$this->db->from('events')->where('deleted_at IS NULL', NULL, FALSE)->where('publication_status', 'published')
			->group_start()->like('title', $keyword)->or_like('summary', $keyword)->group_end();
		foreach ($this->db->limit($limit)->get()->result() as $row)
		{
			$results[] = array('type' => 'Agenda', 'title' => $row->title, 'summary' => $row->summary, 'url' => '/agenda/'.$row->slug, 'date' => $row->starts_at);
		}

		foreach ($this->public_documents() as $row)
		{
			if (mb_stripos($row->title, $keyword) !== FALSE OR mb_stripos((string) $row->description, $keyword) !== FALSE)
			{
				$results[] = array('type' => 'Dokumen', 'title' => $row->title, 'summary' => $row->description, 'url' => '/dokumen', 'date' => $row->published_at);
			}
		}
		$this->preview = $saved_preview;
		return array_slice($results, 0, $limit);
	}
}
