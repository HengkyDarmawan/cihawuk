<?php

/**
 * Tahap 6 v1.2: direktori fasilitas dan lokasi publik.
 *
 * Yang dijaga: angka agregat tidak menjadi entitas, entri tanpa identitas cukup tidak
 * dapat terbit, kontak tanpa izin tidak disimpan, dan koordinat sensitif tidak pernah
 * keluar ke pembacaan publik.
 */
class FacilityTest extends CiTestCase {

	/** @var object */
	protected $actor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('FacilityService', NULL, 'facilities');
		$existing = $this->CI->db->get_where('users', array('username' => 'fasilitas.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('fasilitas.svc.test', array('website_admin', 'content_publisher'));
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		foreach ($this->CI->db->like('slug', 'uji-')->get('facilities')->result() as $row)
		{
			$this->CI->db->where('facility_id', (int) $row->id)->delete('facility_services');
			$this->CI->db->where('id', (int) $row->id)->delete('facilities');
		}
		$this->CI->db->like('name', '[Uji]')->delete('places');
		$this->CI->public_cache->forget_group('listing');
	}

	protected function make_facility(array $overrides = array())
	{
		return $this->CI->facilities->save_facility(array_merge(array(
			'name' => 'Puskesmas Pembantu Cihawuk',
			'slug' => 'uji-pustu-cihawuk',
			'category' => 'health',
			'address' => 'Jalan Raya Cihawuk',
			'manager_name' => 'Pemerintah Desa Cihawuk',
			'source_year' => 2023,
			'source_note' => 'Verifikasi lapangan pengelola desa',
			'is_active' => 1,
		), $overrides), $this->actor->id);
	}

	public function test_aggregate_count_is_rejected_as_a_facility_name(): void
	{
		try
		{
			$this->make_facility(array('name' => '4 SD', 'slug' => 'uji-4-sd'));
			$this->fail('Angka agregat tidak boleh menjadi entitas fasilitas');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('name', $e->errors);
			$this->assertStringContainsString('agregat', $e->errors['name']);
		}
	}

	public function test_facility_without_address_or_place_is_rejected(): void
	{
		try
		{
			$this->make_facility(array('address' => '', 'place_id' => NULL));
			$this->fail('Fasilitas tanpa alamat maupun lokasi harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('address', $e->errors);
		}
	}

	public function test_contact_without_permission_is_rejected(): void
	{
		try
		{
			$this->make_facility(array('public_contact' => '0812xxxxxxx', 'contact_permission' => 0));
			$this->fail('Kontak tanpa izin harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('contact_permission', $e->errors);
		}
	}

	public function test_draft_is_not_public_and_publish_needs_verification(): void
	{
		$facility = $this->make_facility();
		$this->assertSame(array(), $this->CI->facilities->published_facilities());

		$blockers = $this->CI->facilities->publish_blockers($facility);
		$this->assertNotEmpty($blockers);
		try
		{
			$this->CI->facilities->publish($facility, $this->actor->id);
			$this->fail('Fasilitas belum terverifikasi tidak boleh terbit');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('Belum diverifikasi', $e->getMessage());
		}

		$this->CI->facilities->verify($facility, TRUE, $this->actor->id);
		$facility = $this->CI->facilities->facility($facility->public_id);
		$this->assertSame(array(), $this->CI->facilities->publish_blockers($facility));
		$this->CI->facilities->publish($facility, $this->actor->id);
		$this->CI->public_cache->forget_group('listing');

		$published = $this->CI->facilities->published_facilities();
		$this->assertCount(1, $published);
		$this->assertSame('Puskesmas Pembantu Cihawuk', $published[0]['name']);
		$this->assertSame('Kesehatan', $published[0]['category_label']);
	}

	public function test_editing_a_published_facility_cancels_verification(): void
	{
		$facility = $this->make_facility();
		$this->CI->facilities->verify($facility, TRUE, $this->actor->id);
		$this->CI->facilities->publish($this->CI->facilities->facility($facility->public_id), $this->actor->id);

		$this->CI->facilities->save_facility(array(
			'name' => 'Puskesmas Pembantu Cihawuk', 'slug' => 'uji-pustu-cihawuk', 'category' => 'health',
			'address' => 'Alamat baru hasil pemeriksaan lapangan', 'source_year' => 2023,
			'source_note' => 'Verifikasi lapangan', 'is_active' => 1,
		), $this->actor->id, $facility->public_id);

		$after = $this->CI->facilities->facility($facility->public_id);
		$this->assertSame('unverified', $after->verification_status);
		$this->assertNotEmpty($this->CI->facilities->publish_blockers($after));
	}

	public function test_sensitive_or_unverified_coordinates_never_reach_the_public(): void
	{
		$place = $this->CI->facilities->save_place(array(
			'name' => '[Uji] Gudang logistik', 'place_type' => 'facility',
			'address' => 'Lokasi internal', 'latitude' => '-7.1999', 'longitude' => '107.7050',
			'is_sensitive' => 1,
		), $this->actor->id);

		$facility = $this->make_facility(array('place_id' => (int) $place->id, 'address' => ''));
		$this->CI->facilities->verify($facility, TRUE, $this->actor->id);
		$this->CI->facilities->publish($this->CI->facilities->facility($facility->public_id), $this->actor->id);
		$this->CI->public_cache->forget_group('listing');

		$published = $this->CI->facilities->published_facilities();
		$this->assertCount(1, $published);
		$this->assertNull($published[0]['latitude'], 'Lokasi sensitif tidak boleh dipetakan publik');
		$this->assertNull($published[0]['longitude']);

		// Walau sensitifnya dicabut, koordinat tetap tertahan sampai lokasi diverifikasi.
		$this->CI->db->where('id', (int) $place->id)->update('places', array('is_sensitive' => 0));
		$this->CI->public_cache->forget_group('listing');
		$this->assertNull($this->CI->facilities->published_facilities()[0]['latitude']);

		$this->CI->facilities->verify_place($this->CI->facilities->place($place->public_id), TRUE, $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertSame(-7.1999, $this->CI->facilities->published_facilities()[0]['latitude']);
	}

	public function test_saving_a_place_cancels_its_verification(): void
	{
		$place = $this->CI->facilities->save_place(array(
			'name' => '[Uji] Kantor desa', 'place_type' => 'office', 'address' => 'Jalan Raya Cihawuk',
		), $this->actor->id);
		$this->CI->facilities->verify_place($place, TRUE, $this->actor->id);
		$this->assertSame('verified', $this->CI->facilities->place($place->public_id)->verification_status);

		$this->CI->facilities->save_place(array(
			'name' => '[Uji] Kantor desa', 'place_type' => 'office', 'address' => 'Jalan Raya Cihawuk',
			'latitude' => '-7.2', 'longitude' => '107.7',
		), $this->actor->id, $place->public_id);
		$this->assertSame('unverified', $this->CI->facilities->place($place->public_id)->verification_status,
			'Koordinat berubah berarti verifikasi lokasi gugur');
	}

	public function test_half_filled_coordinates_are_rejected(): void
	{
		try
		{
			$this->CI->facilities->save_place(array(
				'name' => '[Uji] Titik sebagian', 'place_type' => 'other', 'latitude' => '-7.2', 'longitude' => '',
			), $this->actor->id);
			$this->fail('Koordinat setengah terisi harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('latitude', $e->errors);
		}
	}

	public function test_unpublish_and_archive_keep_the_row(): void
	{
		$facility = $this->make_facility();
		$this->CI->facilities->verify($facility, TRUE, $this->actor->id);
		$this->CI->facilities->publish($this->CI->facilities->facility($facility->public_id), $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertCount(1, $this->CI->facilities->published_facilities());

		$this->CI->facilities->unpublish($this->CI->facilities->facility($facility->public_id), 'Ditarik untuk pengujian', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertSame(array(), $this->CI->facilities->published_facilities());
		$this->assertNotNull($this->CI->facilities->facility($facility->public_id), 'Barisnya tidak boleh hilang');

		$this->CI->facilities->archive($this->CI->facilities->facility($facility->public_id), 'Fasilitas sudah tidak ada', $this->actor->id);
		$this->assertSame('archived', $this->CI->facilities->facility($facility->public_id)->publication_status);
	}

	public function test_services_are_listed_for_published_facility(): void
	{
		$facility = $this->make_facility();
		$this->CI->facilities->save_service($facility, array('label' => 'Pemeriksaan ibu hamil', 'description' => 'Setiap Selasa'));
		$this->CI->facilities->verify($facility, TRUE, $this->actor->id);
		$this->CI->facilities->publish($this->CI->facilities->facility($facility->public_id), $this->actor->id);
		$this->CI->public_cache->forget_group('listing');

		$published = $this->CI->facilities->published_facility('uji-pustu-cihawuk');
		$this->assertCount(1, $published['services']);
		$this->assertSame('Pemeriksaan ibu hamil', $published['services'][0]['label']);
	}

	public function test_featured_potentials_require_a_cover_photo(): void
	{
		$this->CI->load->library('CmsService', NULL, 'cms');
		$potential = $this->CI->db->where('deleted_at IS NULL', NULL, FALSE)->limit(1)->get('potentials')->row();
		$this->assertNotNull($potential, 'Data seed harus punya minimal satu potensi');
		$before = array(
			'publication_status' => $potential->publication_status,
			'verification_status' => $potential->verification_status,
			'cover_media_id' => $potential->cover_media_id,
		);
		// Terbit dan terverifikasi, tetapi tanpa foto sampul.
		$this->CI->db->where('id', (int) $potential->id)->update('potentials', array(
			'publication_status' => 'published', 'verification_status' => 'verified', 'cover_media_id' => NULL,
		));
		try
		{
			$errors = array();
			$result = $this->call_validate_ids(array((int) $potential->id), $errors);
			$this->assertNull($result, 'Potensi tanpa cover tidak boleh lolos menjadi unggulan');
			$this->assertStringContainsString('foto sampul', $errors['item_ids']);
		}
		finally
		{
			$this->CI->db->where('id', (int) $potential->id)->update('potentials', $before);
		}
	}

	/** Pembungkus kecil agar aturan cover dapat diuji tanpa membuat section penuh. */
	protected function call_validate_ids(array $ids, array &$errors)
	{
		$method = new ReflectionMethod('CmsService', 'validate_ids');
		$method->setAccessible(TRUE);
		return $method->invokeArgs($this->CI->cms, array($ids, array(
			'label' => 'Potensi', 'entity' => 'potential', 'max_items' => 6,
		), 'item_ids', &$errors));
	}
}
