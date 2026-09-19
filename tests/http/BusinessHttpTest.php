<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 9 v1.2 lewat HTTP: direktori UMKM publik dan pemisahan izin susun vs terbit.
 */
class BusinessHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';
	const SLUG = 'uji-keripik-http';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('BusinessService', NULL, 'businesses');
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		$this->CI->db->like('slug', 'uji-')->delete('businesses');
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('umkm');
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.umkm.test'))->row();
		return $user ?: $this->make_user('aktor.umkm.test', array('website_admin', 'content_publisher'));
	}

	protected function publish_one()
	{
		$actor = $this->actor();
		$business = $this->CI->businesses->save(array(
			'name' => 'Keripik Uji HTTP', 'slug' => self::SLUG, 'category' => 'food',
			'owner_name' => 'Ibu Contoh', 'description' => 'Keripik singkong produksi rumah tangga.',
			'products' => 'Keripik singkong', 'public_location' => 'Dusun contoh',
			'public_contact' => '0812-uji-kontak', 'is_active' => 1,
			'owner_consent' => 1, 'consent_note' => 'Surat pernyataan pemilik, 12 Sep 2026.',
		), (int) $actor->id);
		$this->CI->businesses->publish($business, (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('umkm');
		return $this->CI->businesses->business($business->public_id);
	}

	public function test_directory_is_empty_then_shows_published_business(): void
	{
		$before = $this->get('umkm');
		$this->assertSame(200, $before['status']);
		$this->assertStringContainsString('Direktori UMKM sedang disusun', $before['body']);
		$this->assertStringContainsString('bukan disalin dari sumber lain', $before['body']);
		$this->assertSame(404, $this->get('umkm/'.self::SLUG)['status']);

		$this->publish_one();

		$listing = $this->get('umkm');
		$this->assertStringContainsString('Keripik Uji HTTP', $listing['body']);
		$detail = $this->get('umkm/'.self::SLUG);
		$this->assertSame(200, $detail['status']);
		$this->assertStringContainsString('0812-uji-kontak', $detail['body']);
		$this->assertStringContainsString('dapat dicabut kapan saja', $detail['body']);
	}

	public function test_revoked_consent_removes_it_from_the_public_site(): void
	{
		$business = $this->publish_one();
		$this->assertStringContainsString('Keripik Uji HTTP', $this->get('umkm')['body']);

		$actor = $this->actor();
		$this->CI->businesses->revoke_consent($this->CI->businesses->business($business->public_id),
			'Pemilik meminta datanya diturunkan.', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');

		$this->assertStringNotContainsString('Keripik Uji HTTP', $this->get('umkm')['body']);
		$this->assertStringNotContainsString('0812-uji-kontak', $this->get('umkm')['body']);
		$this->assertSame(404, $this->get('umkm/'.self::SLUG)['status']);
	}

	public function test_edit_and_publish_permissions_are_separate(): void
	{
		// `umkm.edit` dipegang Admin Website; Editor Konten memang tidak memegangnya.
		$this->make_user('editor.umkm.test', array('website_admin'), 'active');
		$this->make_user('penerbit.umkm.test', array('content_publisher'), 'active');
		$this->make_user('auditor.umkm.test', array('system_auditor'), 'active');

		$actor = $this->actor();
		$business = $this->CI->businesses->save(array(
			'name' => 'Warung Uji Izin', 'slug' => 'uji-warung-izin', 'category' => 'retail',
			'description' => 'Warung kelontong untuk pengujian izin.', 'is_active' => 1,
			'owner_consent' => 1, 'consent_note' => 'Persetujuan lisan direkam 12 Sep 2026.',
		), (int) $actor->id);
		$path = 'admin/umkm/'.rawurlencode($business->public_id);

		$this->login('auditor.umkm.test', self::PASSWORD, 'auditor');
		$this->assertSame(403, $this->get('admin/umkm', 'auditor')['status']);

		$this->login('editor.umkm.test', self::PASSWORD, 'editor');
		$this->assertSame(200, $this->get($path, 'editor')['status']);
		$this->assertSame(403, $this->post_form($path, $path.'/alur/terbitkan', array(), 'editor')['status']);
		$this->assertSame('draft', $this->CI->businesses->business($business->public_id)->publication_status);

		$this->login('penerbit.umkm.test', self::PASSWORD, 'publisher');
		$this->assertSame(303, $this->post_form($path, $path.'/alur/terbitkan', array(), 'publisher')['status']);
		$this->assertSame('published', $this->CI->businesses->business($business->public_id)->publication_status);
	}
}
