<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Direktori fasilitas desa dan lokasi publik (modul-backend 12.2, modul-frontend 9).
 *
 * Aturan pokok: angka agregat bukan fasilitas. Satu entri hanya boleh terbit bila punya
 * identitas yang cukup — nama, kategori, pengelola, alamat atau lokasi, tahun data, sumber,
 * dan verifikasi. Kontak publik hanya tampil bila izinnya tercatat, dan koordinat lokasi
 * yang ditandai sensitif tidak pernah keluar ke HTML maupun JSON publik.
 */
class FacilityService {

	/** @var CI_Controller */
	protected $CI;

	const STATUSES = array(
		'draft' => 'Draft',
		'in_review' => 'Menunggu verifikasi',
		'published' => 'Terbit',
		'archived' => 'Diarsipkan',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('facilities', TRUE);
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
		$this->CI->load->library('ContentService', NULL, 'content_service');
	}

	public function categories()
	{
		return (array) $this->CI->config->item('facility_categories', 'facilities');
	}

	public function place_types()
	{
		return (array) $this->CI->config->item('place_types', 'facilities');
	}

	public function access_statuses()
	{
		return (array) $this->CI->config->item('potential_access_statuses', 'facilities');
	}

	// ------------------------------------------------------------------
	// Lokasi
	// ------------------------------------------------------------------

	public function places($only_verified = FALSE)
	{
		$this->CI->db->where('active', 1)->order_by('name');
		if ($only_verified)
		{
			$this->CI->db->where('verification_status', 'verified');
		}
		return $this->CI->db->get('places')->result();
	}

	public function place($public_id)
	{
		return $this->CI->db->get_where('places', array('public_id' => (string) $public_id))->row();
	}

	public function save_place(array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 180);
		$type = (string) ($input['place_type'] ?? '');
		if ($name === '')
		{
			$errors['name'] = 'Nama lokasi wajib diisi.';
		}
		if ( ! isset($this->place_types()[$type]))
		{
			$errors['place_type'] = 'Jenis lokasi tidak dikenal.';
		}
		$lat = trim((string) ($input['latitude'] ?? ''));
		$lng = trim((string) ($input['longitude'] ?? ''));
		if (($lat === '') !== ($lng === ''))
		{
			$errors['latitude'] = 'Isi lintang dan bujur sekaligus, atau kosongkan keduanya.';
		}
		if ($lat !== '' && ( ! is_numeric(str_replace(',', '.', $lat)) OR ! is_numeric(str_replace(',', '.', $lng))))
		{
			$errors['latitude'] = 'Koordinat harus berupa angka desimal.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data lokasi.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'name' => $name,
			'place_type' => $type,
			'address' => mb_substr(trim((string) ($input['address'] ?? '')), 0, 400) ?: NULL,
			'area_note' => mb_substr(trim((string) ($input['area_note'] ?? '')), 0, 200) ?: NULL,
			'latitude' => $lat === '' ? NULL : round((float) str_replace(',', '.', $lat), 7),
			'longitude' => $lng === '' ? NULL : round((float) str_replace(',', '.', $lng), 7),
			'is_sensitive' => empty($input['is_sensitive']) ? 0 : 1,
			'source_id' => empty($input['source_id']) ? NULL : (int) $input['source_id'],
			'source_note' => mb_substr(trim((string) ($input['source_note'] ?? '')), 0, 255) ?: NULL,
			'updated_at' => $now,
		);

		$existing = $public_id ? $this->place($public_id) : NULL;
		if ($existing)
		{
			// Koordinat berubah berarti verifikasi lokasi gugur.
			$data['verification_status'] = 'unverified';
			db_must($this->CI->db->where('id', (int) $existing->id)->update('places', $data), 'places.update');
			$this->CI->audit->log('place.saved', 'place', $existing->public_id, array('name' => $name), FALSE, 'facilities');
			return $this->place($existing->public_id);
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['verification_status'] = 'unverified';
		$data['active'] = 1;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('places', $data), 'places.insert');
		$this->CI->audit->log('place.created', 'place', $data['public_id'], array('name' => $name), FALSE, 'facilities');
		return $this->place($data['public_id']);
	}

	public function verify_place($place, $verified, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $place->id)->update('places', array(
			'verification_status' => $verified ? 'verified' : 'unverified',
			'updated_at' => utc_now(),
		)), 'places.verify');
		$this->CI->audit->log($verified ? 'place.verified' : 'place.unverified', 'place', $place->public_id,
			array('name' => $place->name), FALSE, 'facilities');
		$this->CI->public_cache->forget_group('listing');
	}

	// ------------------------------------------------------------------
	// Fasilitas
	// ------------------------------------------------------------------

	public function facilities(array $filters = array())
	{
		$this->CI->db->select('f.*, p.name AS place_name')->from('facilities f')
			->join('places p', 'p.id = f.place_id', 'left')
			->where('f.archived_at IS NULL', NULL, FALSE);
		if ( ! empty($filters['category']))
		{
			$this->CI->db->where('f.category', (string) $filters['category']);
		}
		if ( ! empty($filters['status']))
		{
			$this->CI->db->where('f.publication_status', (string) $filters['status']);
		}
		return $this->CI->db->order_by('f.sort_order')->order_by('f.name')->get()->result();
	}

	public function facility($public_id)
	{
		$row = $this->CI->db->get_where('facilities', array('public_id' => (string) $public_id))->row();
		if ($row)
		{
			$row->services = $this->services($row);
		}
		return $row;
	}

	public function services($facility)
	{
		return $this->CI->db->where('facility_id', (int) $facility->id)
			->order_by('sort_order')->order_by('id')->get('facility_services')->result();
	}

	public function save_facility(array $input, $user_id, $public_id = NULL)
	{
		$clean = $this->validate_facility($input);
		$existing = $public_id ? $this->facility($public_id) : NULL;
		$now = utc_now();

		if ($existing)
		{
			if ($existing->slug !== $clean['slug'] && $this->slug_taken($clean['slug'], (int) $existing->id))
			{
				throw new DomainRuleException('Slug fasilitas sudah dipakai.', 409, array('slug' => 'Sudah dipakai.'));
			}
			// Isi berubah: verifikasi sebelumnya gugur supaya tidak ada data lama yang
			// ikut terbit dengan stempel verifikasi yang sudah tidak berlaku.
			$clean['verification_status'] = 'unverified';
			$clean['verified_by'] = NULL;
			$clean['verified_at'] = NULL;
			$clean['updated_at'] = $now;
			db_must($this->CI->db->where('id', (int) $existing->id)->update('facilities', $clean), 'facilities.update');
			$this->CI->audit->log('facility.saved', 'facility', $existing->public_id, array('name' => $clean['name']), FALSE, 'facilities');
			$this->CI->public_cache->forget_group('listing');
			return $this->facility($existing->public_id);
		}

		if ($this->slug_taken($clean['slug'], 0))
		{
			throw new DomainRuleException('Slug fasilitas sudah dipakai.', 409, array('slug' => 'Sudah dipakai.'));
		}
		$clean['public_id'] = $this->CI->crypto->public_id();
		$clean['publication_status'] = 'draft';
		$clean['verification_status'] = 'unverified';
		$clean['sort_order'] = (int) ($this->CI->db->select_max('sort_order')->get('facilities')->row('sort_order') ?: 0) + 10;
		$clean['created_by'] = $user_id ? (int) $user_id : NULL;
		$clean['created_at'] = $now;
		$clean['updated_at'] = $now;
		db_must($this->CI->db->insert('facilities', $clean), 'facilities.insert');
		$this->CI->audit->log('facility.created', 'facility', $clean['public_id'], array('name' => $clean['name']), FALSE, 'facilities');
		return $this->facility($clean['public_id']);
	}

	protected function slug_taken($slug, $except_id)
	{
		$this->CI->db->where('slug', $slug);
		if ($except_id)
		{
			$this->CI->db->where('id <>', (int) $except_id);
		}
		return $this->CI->db->count_all_results('facilities') > 0;
	}

	protected function validate_facility(array $input)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 200);
		$category = (string) ($input['category'] ?? '');
		$address = mb_substr(trim((string) ($input['address'] ?? '')), 0, 400);
		$place_id = empty($input['place_id']) ? NULL : (int) $input['place_id'];
		$contact = mb_substr(trim((string) ($input['public_contact'] ?? '')), 0, 255);
		$permission = empty($input['contact_permission']) ? 0 : 1;

		if ($name === '')
		{
			$errors['name'] = 'Nama fasilitas wajib diisi.';
		}
		// Nama yang hanya berupa hitungan ("4 SD") adalah angka agregat, bukan entitas.
		if ($name !== '' && preg_match('/^\d+\s+\S+$/u', $name))
		{
			$errors['name'] = 'Itu terbaca sebagai angka agregat, bukan nama fasilitas. Angka agregat tetap berada di Data Desa.';
		}
		if ( ! isset($this->categories()[$category]))
		{
			$errors['category'] = 'Kategori fasilitas tidak dikenal.';
		}
		if ($address === '' && $place_id === NULL)
		{
			$errors['address'] = 'Isi alamat atau pilih lokasi yang sudah terdaftar.';
		}
		if ($contact !== '' && ! $permission)
		{
			$errors['contact_permission'] = 'Kontak publik hanya boleh disimpan bila izinnya sudah dicatat.';
		}
		if ($place_id !== NULL && ! $this->CI->db->where('id', $place_id)->count_all_results('places'))
		{
			$errors['place_id'] = 'Lokasi tidak ditemukan.';
		}

		$hours = array();
		foreach ((array) ($input['service_hours'] ?? array()) as $row)
		{
			$label = trim((string) ($row['label'] ?? ''));
			$value = trim((string) ($row['value'] ?? ''));
			if ($label === '' && $value === '')
			{
				continue;
			}
			if ($label === '' OR $value === '')
			{
				$errors['service_hours'] = 'Setiap baris jam layanan membutuhkan hari dan jamnya.';
				break;
			}
			$hours[] = array('label' => mb_substr($label, 0, 60), 'value' => mb_substr($value, 0, 80));
		}

		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data fasilitas.', 422, $errors);
		}

		return array(
			'slug' => $this->CI->content_service->slugify((string) ($input['slug'] ?? $name), 'facility'),
			'name' => $name,
			'category' => $category,
			'manager_name' => mb_substr(trim((string) ($input['manager_name'] ?? '')), 0, 180) ?: NULL,
			'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 1000) ?: NULL,
			'place_id' => $place_id,
			'address' => $address ?: NULL,
			'service_hours_json' => $hours ? json_encode($hours, JSON_UNESCAPED_UNICODE) : NULL,
			'public_contact' => $contact ?: NULL,
			'contact_permission' => $permission,
			'accessibility' => mb_substr(trim((string) ($input['accessibility'] ?? '')), 0, 600) ?: NULL,
			'photo_media_id' => empty($input['photo_media_id']) ? NULL : (int) $input['photo_media_id'],
			'source_year' => empty($input['source_year']) ? NULL : (int) $input['source_year'],
			'source_id' => empty($input['source_id']) ? NULL : (int) $input['source_id'],
			'source_note' => mb_substr(trim((string) ($input['source_note'] ?? '')), 0, 255) ?: NULL,
			'is_active' => empty($input['is_active']) ? 0 : 1,
		);
	}

	public function save_service($facility, array $input)
	{
		$label = mb_substr(trim((string) ($input['label'] ?? '')), 0, 160);
		if ($label === '')
		{
			throw new DomainRuleException('Nama layanan wajib diisi.', 422, array('label' => 'Wajib diisi.'));
		}
		db_must($this->CI->db->insert('facility_services', array(
			'facility_id' => (int) $facility->id,
			'label' => $label,
			'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 400) ?: NULL,
			'sort_order' => (int) ($this->CI->db->select_max('sort_order')
				->where('facility_id', (int) $facility->id)->get('facility_services')->row('sort_order') ?: 0) + 10,
			'created_at' => utc_now(),
		)), 'facility_services.insert');
		$this->CI->public_cache->forget_group('listing');
	}

	public function delete_service($facility, $service_id)
	{
		db_must($this->CI->db->where(array('id' => (int) $service_id, 'facility_id' => (int) $facility->id))
			->delete('facility_services'), 'facility_services.delete');
		$this->CI->public_cache->forget_group('listing');
	}

	// ------------------------------------------------------------------
	// Verifikasi dan publikasi
	// ------------------------------------------------------------------

	public function verify($facility, $verified, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $facility->id)->update('facilities', array(
			'verification_status' => $verified ? 'verified' : 'unverified',
			'verified_by' => $verified && $user_id ? (int) $user_id : NULL,
			'verified_at' => $verified ? utc_now() : NULL,
			'publication_status' => $verified && $facility->publication_status === 'draft' ? 'in_review' : $facility->publication_status,
			'updated_at' => utc_now(),
		)), 'facilities.verify');
		$this->CI->audit->log($verified ? 'facility.verified' : 'facility.unverified', 'facility',
			$facility->public_id, array('name' => $facility->name), FALSE, 'facilities');
		$this->CI->public_cache->forget_group('listing');
	}

	/** @return string[] alasan mengapa fasilitas belum boleh terbit */
	public function publish_blockers($facility)
	{
		$blockers = array();
		if ($facility->verification_status !== 'verified')
		{
			$blockers[] = 'Belum diverifikasi terhadap kondisi lapangan.';
		}
		if ( ! $facility->address && ! $facility->place_id)
		{
			$blockers[] = 'Belum punya alamat maupun lokasi terdaftar.';
		}
		if ( ! $facility->source_year)
		{
			$blockers[] = 'Tahun data belum diisi.';
		}
		if ( ! $facility->source_id && ! $facility->source_note)
		{
			$blockers[] = 'Sumber data belum dicantumkan.';
		}
		if ($facility->public_contact && ! (int) $facility->contact_permission)
		{
			$blockers[] = 'Kontak publik belum punya izin publikasi.';
		}
		return $blockers;
	}

	public function publish($facility, $user_id)
	{
		$blockers = $this->publish_blockers($facility);
		if ($blockers)
		{
			throw new DomainRuleException('Fasilitas belum siap terbit: '.implode(' ', $blockers), 422);
		}
		db_must($this->CI->db->where('id', (int) $facility->id)->update('facilities', array(
			'publication_status' => 'published',
			'published_at' => utc_now(),
			'updated_at' => utc_now(),
		)), 'facilities.publish');
		$this->after_change('facility.published', $facility);
	}

	public function unpublish($facility, $reason, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $facility->id)->update('facilities', array(
			'publication_status' => 'draft',
			'published_at' => NULL,
			'updated_at' => utc_now(),
		)), 'facilities.unpublish');
		$this->after_change('facility.unpublished', $facility, array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	/** Mengarsipkan tidak menghapus barisnya; histori tetap ada. */
	public function archive($facility, $reason, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $facility->id)->update('facilities', array(
			'publication_status' => 'archived',
			'archived_at' => utc_now(),
			'published_at' => NULL,
			'updated_at' => utc_now(),
		)), 'facilities.archive');
		$this->after_change('facility.archived', $facility, array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	protected function after_change($action, $facility, array $meta = array())
	{
		$this->CI->audit->log($action, 'facility', $facility->public_id,
			$meta + array('name' => $facility->name), FALSE, 'facilities');
		$this->CI->public_cache->invalidate_page('fasilitas');
		$this->CI->public_cache->forget_group('listing');
	}

	// ------------------------------------------------------------------
	// Pembacaan publik
	// ------------------------------------------------------------------

	/**
	 * Fasilitas terbit untuk halaman publik. Koordinat lokasi sensitif dan kontak tanpa izin
	 * tidak pernah masuk ke array ini, sehingga tidak mungkin bocor ke HTML, JSON, atau peta.
	 */
	public function published_facilities()
	{
		$cached = $this->CI->public_cache->get('listing', 'facilities');
		if (is_array($cached))
		{
			return $cached;
		}
		$rows = $this->CI->db->select('f.*, p.name AS place_name, p.address AS place_address, p.area_note,
				p.latitude, p.longitude, p.is_sensitive, p.verification_status AS place_verification')
			->from('facilities f')->join('places p', 'p.id = f.place_id', 'left')
			->where('f.publication_status', 'published')->where('f.is_active', 1)
			->where('f.archived_at IS NULL', NULL, FALSE)
			->order_by('f.sort_order')->order_by('f.name')->get()->result();

		$out = array();
		foreach ($rows as $row)
		{
			// Peta hanya boleh memakai titik yang sudah diverifikasi dan tidak sensitif.
			$mappable = $row->latitude !== NULL && $row->longitude !== NULL
				&& (int) $row->is_sensitive === 0 && $row->place_verification === 'verified';
			$out[] = array(
				'public_id' => $row->public_id,
				'slug' => $row->slug,
				'name' => $row->name,
				'category' => $row->category,
				'category_label' => $this->categories()[$row->category] ?? $row->category,
				'manager_name' => $row->manager_name,
				'description' => $row->description,
				'address' => $row->address ?: $row->place_address,
				'area_note' => $row->area_note,
				'place_name' => $row->place_name,
				'latitude' => $mappable ? (float) $row->latitude : NULL,
				'longitude' => $mappable ? (float) $row->longitude : NULL,
				'service_hours' => $row->service_hours_json ? json_decode($row->service_hours_json, TRUE) : array(),
				'public_contact' => (int) $row->contact_permission === 1 ? $row->public_contact : NULL,
				'accessibility' => $row->accessibility,
				'photo_media_id' => $row->photo_media_id ? (int) $row->photo_media_id : NULL,
				'source_year' => $row->source_year ? (int) $row->source_year : NULL,
				'source_note' => $row->source_note,
				'verified_at' => $row->verified_at,
				'published_at' => $row->published_at,
				'services' => $this->published_services((int) $row->id),
			);
		}
		$this->CI->public_cache->set('listing', 'facilities', $out, 1800);
		return $out;
	}

	protected function published_services($facility_id)
	{
		$out = array();
		foreach ($this->CI->db->where('facility_id', $facility_id)->order_by('sort_order')->order_by('id')
			->get('facility_services')->result() as $row)
		{
			$out[] = array('label' => $row->label, 'description' => $row->description);
		}
		return $out;
	}

	public function published_facility($slug)
	{
		foreach ($this->published_facilities() as $facility)
		{
			if ($facility['slug'] === (string) $slug)
			{
				return $facility;
			}
		}
		return NULL;
	}
}
