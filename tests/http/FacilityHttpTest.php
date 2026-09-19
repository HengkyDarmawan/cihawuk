<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 6 v1.2 lewat HTTP: direktori fasilitas publik, pemisahan izin menyusun vs
 * menerbitkan, dan jaminan bahwa koordinat sensitif serta kontak tanpa izin tidak
 * pernah muncul pada HTML publik.
 */
class FacilityHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';
	const SLUG = 'uji-pustu-http';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('FacilityService', NULL, 'facilities');
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
		$this->CI->public_cache->invalidate_page('fasilitas');
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.fasilitas.test'))->row();
		return $user ?: $this->make_user('aktor.fasilitas.test', array('website_admin', 'content_publisher'));
	}

	protected function publish_facility(array $overrides = array())
	{
		$actor = $this->actor();
		$facility = $this->CI->facilities->save_facility(array_merge(array(
			'name' => 'Puskesmas Pembantu Uji',
			'slug' => self::SLUG,
			'category' => 'health',
			'address' => 'Jalan Raya Cihawuk nomor 1',
			'manager_name' => 'Pemerintah Desa Cihawuk',
			'description' => 'Layanan kesehatan dasar untuk warga Desa Cihawuk.',
			'source_year' => 2023,
			'source_note' => 'Verifikasi lapangan pengelola desa',
			'is_active' => 1,
		), $overrides), (int) $actor->id);
		$this->CI->facilities->verify($facility, TRUE, (int) $actor->id);
		$this->CI->facilities->publish($this->CI->facilities->facility($facility->public_id), (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('fasilitas');
		return $this->CI->facilities->facility($facility->public_id);
	}

	public function test_directory_is_empty_until_a_facility_is_published(): void
	{
		$empty = $this->get('fasilitas');
		$this->assertSame(200, $empty['status']);
		$this->assertStringContainsString('Direktori fasilitas sedang disusun', $empty['body']);
		$this->assertStringContainsString('Data Desa', $empty['body'],
			'Keadaan kosong harus mengarahkan angka agregat ke Data Desa');
		$this->assertSame(404, $this->get('fasilitas/'.self::SLUG)['status']);

		$this->publish_facility();

		$listing = $this->get('fasilitas');
		$this->assertStringContainsString('Puskesmas Pembantu Uji', $listing['body']);
		$detail = $this->get('fasilitas/'.self::SLUG);
		$this->assertSame(200, $detail['status']);
		$this->assertStringContainsString('Jalan Raya Cihawuk nomor 1', $detail['body']);
		$this->assertStringContainsString('2023', $detail['body']);
	}

	public function test_draft_facility_is_not_reachable(): void
	{
		$actor = $this->actor();
		$this->CI->facilities->save_facility(array(
			'name' => 'Balai Uji Draft', 'slug' => 'uji-balai-draft', 'category' => 'government',
			'address' => 'Alamat uji', 'source_year' => 2023, 'source_note' => 'Uji', 'is_active' => 1,
		), (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');

		$this->assertStringNotContainsString('Balai Uji Draft', $this->get('fasilitas')['body']);
		$this->assertSame(404, $this->get('fasilitas/uji-balai-draft')['status']);
	}

	public function test_sensitive_coordinates_are_not_in_the_public_html(): void
	{
		$actor = $this->actor();
		$place = $this->CI->facilities->save_place(array(
			'name' => '[Uji] Gudang logistik desa', 'place_type' => 'facility',
			'address' => 'Lokasi internal', 'latitude' => '-7.1999', 'longitude' => '107.7050',
			'is_sensitive' => 1,
		), (int) $actor->id);
		$this->CI->facilities->verify_place($place, TRUE, (int) $actor->id);
		$this->publish_facility(array('place_id' => (int) $place->id, 'address' => ''));

		$listing = $this->get('fasilitas')['body'];
		$detail = $this->get('fasilitas/'.self::SLUG)['body'];
		foreach (array($listing, $detail) as $body)
		{
			$this->assertStringNotContainsString('107.7050', $body, 'Koordinat sensitif tidak boleh masuk HTML publik');
			$this->assertStringNotContainsString('-7.1999', $body);
		}
		$this->assertStringContainsString('Peta sedang dilengkapi', $detail);
	}

	public function test_contact_appears_only_with_recorded_permission(): void
	{
		$this->publish_facility(array('public_contact' => '(022) 000-1234', 'contact_permission' => 1));
		$this->assertStringContainsString('(022) 000-1234', $this->get('fasilitas/'.self::SLUG)['body']);

		// Izin dicabut langsung di basis data: kontaknya harus berhenti tampil.
		$this->CI->db->where('slug', self::SLUG)->update('facilities', array('contact_permission' => 0));
		$this->CI->public_cache->forget_group('listing');
		$this->assertStringNotContainsString('(022) 000-1234', $this->get('fasilitas/'.self::SLUG)['body']);
	}

	public function test_edit_and_publish_permissions_are_separate(): void
	{
		$this->make_user('editor.fasilitas.test', array('content_editor'), 'active');
		$this->make_user('penerbit.fasilitas.test', array('content_publisher'), 'active');
		$this->make_user('auditor.fasilitas.test', array('system_auditor'), 'active');

		$actor = $this->actor();
		$facility = $this->CI->facilities->save_facility(array(
			'name' => 'Balai Uji Izin', 'slug' => 'uji-balai-izin', 'category' => 'government',
			'address' => 'Alamat uji', 'source_year' => 2023, 'source_note' => 'Uji', 'is_active' => 1,
		), (int) $actor->id);
		$path = 'admin/fasilitas/'.rawurlencode($facility->public_id);

		// Tanpa permission fasilitas sama sekali.
		$this->login('auditor.fasilitas.test', self::PASSWORD, 'auditor');
		$this->assertSame(403, $this->get('admin/fasilitas', 'auditor')['status']);

		// Editor menyusun, tetapi tidak boleh memverifikasi atau menerbitkan.
		$this->login('editor.fasilitas.test', self::PASSWORD, 'editor');
		$this->assertSame(200, $this->get($path, 'editor')['status']);
		$saved = $this->post_form($path, $path.'/simpan', array(
			'name' => 'Balai Uji Izin', 'slug' => 'uji-balai-izin', 'category' => 'government',
			'address' => 'Alamat hasil suntingan editor', 'source_year' => 2023, 'source_note' => 'Uji', 'is_active' => '1',
		), 'editor');
		$this->assertSame(303, $saved['status']);
		$this->assertStringContainsString('suntingan editor', (string) $this->CI->facilities->facility($facility->public_id)->address);

		$this->assertSame(403, $this->post_form($path, $path.'/alur/verifikasi', array(), 'editor')['status']);
		$this->assertSame(403, $this->post_form($path, $path.'/alur/terbitkan', array(), 'editor')['status']);
		$this->assertSame('draft', $this->CI->facilities->facility($facility->public_id)->publication_status);

		// Penerbit memverifikasi lalu menerbitkan.
		$this->login('penerbit.fasilitas.test', self::PASSWORD, 'publisher');
		$this->assertSame(303, $this->post_form($path, $path.'/alur/verifikasi', array(), 'publisher')['status']);
		$this->assertSame(303, $this->post_form($path, $path.'/alur/terbitkan', array(), 'publisher')['status']);
		$this->assertSame('published', $this->CI->facilities->facility($facility->public_id)->publication_status);
	}

	public function test_disabled_module_closes_facility_pages(): void
	{
		$this->publish_facility();
		$this->assertSame(200, $this->get('fasilitas')['status']);

		$actor = $this->actor();
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$this->CI->modules->set_state('facilities', 'disabled', 'Dinonaktifkan untuk pengujian modul.', (int) $actor->id);
		try
		{
			$this->assertSame(404, $this->get('fasilitas')['status']);
			$this->assertSame(404, $this->get('fasilitas/'.self::SLUG)['status']);
		}
		finally
		{
			$this->CI->modules->set_state('facilities', 'active', 'Dikembalikan setelah pengujian modul.', (int) $actor->id);
		}
		$this->assertSame(200, $this->get('fasilitas')['status']);
	}
}
