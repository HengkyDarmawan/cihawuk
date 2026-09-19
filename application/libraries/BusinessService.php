<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Direktori UMKM (modul-backend 12.3).
 *
 * Aturan pokok: profil usaha tidak boleh terbit tanpa persetujuan pemiliknya yang tercatat,
 * dan kontak tidak boleh diambil dari sumber lain. Mencabut persetujuan langsung menurunkan
 * usaha itu dari direktori publik.
 */
class BusinessService {

	/** @var CI_Controller */
	protected $CI;

	const STATUSES = array(
		'draft' => 'Draft',
		'published' => 'Terbit',
		'archived' => 'Diarsipkan',
	);

	const CATEGORIES = array(
		'food' => 'Makanan dan minuman',
		'agriculture' => 'Pertanian dan hasil tani',
		'craft' => 'Kerajinan',
		'service' => 'Jasa',
		'retail' => 'Perdagangan',
		'other' => 'Lainnya',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
		$this->CI->load->library('ContentService', NULL, 'content_service');
	}

	public function businesses(array $filters = array())
	{
		$this->CI->db->where('archived_at IS NULL', NULL, FALSE);
		if ( ! empty($filters['category']))
		{
			$this->CI->db->where('category', (string) $filters['category']);
		}
		return $this->CI->db->order_by('sort_order')->order_by('name')->get('businesses')->result();
	}

	public function business($public_id)
	{
		return $this->CI->db->get_where('businesses', array('public_id' => (string) $public_id))->row();
	}

	public function save(array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 200);
		$category = (string) ($input['category'] ?? '');
		$contact = mb_substr(trim((string) ($input['public_contact'] ?? '')), 0, 255);
		$consent = empty($input['owner_consent']) ? 0 : 1;

		if ($name === '')
		{
			$errors['name'] = 'Nama usaha wajib diisi.';
		}
		if ( ! isset(self::CATEGORIES[$category]))
		{
			$errors['category'] = 'Kategori usaha tidak dikenal.';
		}
		if ($contact !== '' && ! $consent)
		{
			$errors['owner_consent'] = 'Kontak usaha hanya boleh disimpan bila pemiliknya sudah menyetujui.';
		}
		if ($consent && trim((string) ($input['consent_note'] ?? '')) === '')
		{
			// Persetujuan harus dapat ditelusuri, bukan sekadar centang.
			$errors['consent_note'] = 'Tuliskan bagaimana dan kapan persetujuan pemilik diperoleh.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data usaha.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'slug' => $this->CI->content_service->slugify((string) ($input['slug'] ?? $name), 'business'),
			'name' => $name,
			'category' => $category,
			'owner_name' => mb_substr(trim((string) ($input['owner_name'] ?? '')), 0, 180) ?: NULL,
			'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 1000) ?: NULL,
			'products' => mb_substr(trim((string) ($input['products'] ?? '')), 0, 600) ?: NULL,
			'public_location' => mb_substr(trim((string) ($input['public_location'] ?? '')), 0, 400) ?: NULL,
			'public_contact' => $contact ?: NULL,
			'opening_hours' => mb_substr(trim((string) ($input['opening_hours'] ?? '')), 0, 255) ?: NULL,
			'photo_media_id' => empty($input['photo_media_id']) ? NULL : (int) $input['photo_media_id'],
			'owner_consent' => $consent,
			'consent_recorded_at' => $consent ? $now : NULL,
			'consent_note' => $consent ? mb_substr(trim((string) $input['consent_note']), 0, 500) : NULL,
			'is_active' => empty($input['is_active']) ? 0 : 1,
			'updated_at' => $now,
		);

		$existing = $public_id ? $this->business($public_id) : NULL;
		if ($existing)
		{
			if ($data['slug'] !== $existing->slug && $this->slug_taken($data['slug'], (int) $existing->id))
			{
				throw new DomainRuleException('Slug usaha sudah dipakai.', 409, array('slug' => 'Sudah dipakai.'));
			}
			db_must($this->CI->db->where('id', (int) $existing->id)->update('businesses', $data), 'businesses.update');
			$this->CI->audit->log('umkm.saved', 'business', $existing->public_id, array('name' => $name), FALSE, 'umkm_directory');
			$this->CI->public_cache->forget_group('listing');
			return $this->business($existing->public_id);
		}

		if ($this->slug_taken($data['slug'], 0))
		{
			throw new DomainRuleException('Slug usaha sudah dipakai.', 409, array('slug' => 'Sudah dipakai.'));
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['publication_status'] = 'draft';
		$data['sort_order'] = (int) ($this->CI->db->select_max('sort_order')->get('businesses')->row('sort_order') ?: 0) + 10;
		$data['created_by'] = $user_id ? (int) $user_id : NULL;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('businesses', $data), 'businesses.insert');
		$this->CI->audit->log('umkm.created', 'business', $data['public_id'], array('name' => $name), FALSE, 'umkm_directory');
		return $this->business($data['public_id']);
	}

	protected function slug_taken($slug, $except_id)
	{
		$this->CI->db->where('slug', $slug);
		if ($except_id)
		{
			$this->CI->db->where('id <>', (int) $except_id);
		}
		return $this->CI->db->count_all_results('businesses') > 0;
	}

	/** @return string[] */
	public function publish_blockers($business)
	{
		$blockers = array();
		if ((int) $business->owner_consent !== 1)
		{
			$blockers[] = 'Pemilik usaha belum menyetujui publikasi.';
		}
		if ( ! $business->description && ! $business->products)
		{
			$blockers[] = 'Belum ada deskripsi maupun daftar produk.';
		}
		return $blockers;
	}

	public function publish($business, $user_id)
	{
		$blockers = $this->publish_blockers($business);
		if ($blockers)
		{
			throw new DomainRuleException('Usaha belum siap terbit: '.implode(' ', $blockers), 422);
		}
		db_must($this->CI->db->where('id', (int) $business->id)->update('businesses', array(
			'publication_status' => 'published', 'published_at' => utc_now(), 'updated_at' => utc_now(),
		)), 'businesses.publish');
		$this->after_change('umkm.published', $business);
	}

	public function unpublish($business, $reason, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $business->id)->update('businesses', array(
			'publication_status' => 'draft', 'published_at' => NULL, 'updated_at' => utc_now(),
		)), 'businesses.unpublish');
		$this->after_change('umkm.unpublished', $business, array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	/** Pemilik mencabut persetujuan: usaha langsung turun dari direktori publik. */
	public function revoke_consent($business, $reason, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $business->id)->update('businesses', array(
			'owner_consent' => 0,
			'consent_note' => mb_substr('Persetujuan dicabut: '.trim((string) $reason), 0, 500),
			'publication_status' => 'draft',
			'published_at' => NULL,
			'updated_at' => utc_now(),
		)), 'businesses.revoke');
		$this->after_change('umkm.consent_revoked', $business, array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	public function archive($business, $reason, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $business->id)->update('businesses', array(
			'publication_status' => 'archived', 'archived_at' => utc_now(),
			'published_at' => NULL, 'updated_at' => utc_now(),
		)), 'businesses.archive');
		$this->after_change('umkm.archived', $business, array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	protected function after_change($action, $business, array $meta = array())
	{
		$this->CI->audit->log($action, 'business', $business->public_id,
			$meta + array('name' => $business->name), FALSE, 'umkm_directory');
		$this->CI->public_cache->invalidate_page('umkm');
		$this->CI->public_cache->forget_group('listing');
	}

	/** Hanya usaha terbit, aktif, dan yang persetujuannya masih berlaku. */
	public function published_businesses()
	{
		$cached = $this->CI->public_cache->get('listing', 'businesses');
		if (is_array($cached))
		{
			return $cached;
		}
		$rows = $this->CI->db->where('publication_status', 'published')->where('is_active', 1)
			->where('owner_consent', 1)->where('archived_at IS NULL', NULL, FALSE)
			->order_by('sort_order')->order_by('name')->get('businesses')->result();
		$out = array();
		foreach ($rows as $row)
		{
			$out[] = array(
				'slug' => $row->slug,
				'name' => $row->name,
				'category' => $row->category,
				'category_label' => self::CATEGORIES[$row->category] ?? $row->category,
				'owner_name' => $row->owner_name,
				'description' => $row->description,
				'products' => $row->products,
				'public_location' => $row->public_location,
				'public_contact' => $row->public_contact,
				'opening_hours' => $row->opening_hours,
				'photo_media_id' => $row->photo_media_id ? (int) $row->photo_media_id : NULL,
				'published_at' => $row->published_at,
			);
		}
		$this->CI->public_cache->set('listing', 'businesses', $out, 1800);
		return $out;
	}

	public function published_business($slug)
	{
		foreach ($this->published_businesses() as $business)
		{
			if ($business['slug'] === (string) $slug)
			{
				return $business;
			}
		}
		return NULL;
	}
}
