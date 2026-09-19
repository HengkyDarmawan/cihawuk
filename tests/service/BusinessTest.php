<?php

/**
 * Tahap 9 v1.2: direktori UMKM.
 *
 * Yang dijaga: profil tidak boleh terbit tanpa persetujuan pemilik yang tercatat, kontak
 * tanpa persetujuan tidak disimpan, dan mencabut persetujuan langsung menurunkan profilnya.
 */
class BusinessTest extends CiTestCase {

	/** @var object */
	protected $actor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('BusinessService', NULL, 'businesses');
		$existing = $this->CI->db->get_where('users', array('username' => 'umkm.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('umkm.svc.test', array('website_admin', 'content_publisher'));
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
	}

	protected function make_business(array $overrides = array())
	{
		return $this->CI->businesses->save(array_merge(array(
			'name' => 'Keripik Cihawuk',
			'slug' => 'uji-keripik-cihawuk',
			'category' => 'food',
			'owner_name' => 'Ibu Contoh',
			'description' => 'Keripik singkong produksi rumah tangga.',
			'products' => 'Keripik singkong, keripik pisang',
			'public_location' => 'Dusun contoh',
			'is_active' => 1,
		), $overrides), $this->actor->id);
	}

	public function test_directory_is_empty_by_default(): void
	{
		// Dokumen sumber hanya memuat kategori ekonomi agregat, bukan daftar usaha.
		$this->assertSame(array(), $this->CI->businesses->published_businesses());
	}

	public function test_contact_without_consent_is_rejected(): void
	{
		try
		{
			$this->make_business(array('public_contact' => '0812xxxxxxx'));
			$this->fail('Kontak tanpa persetujuan pemilik harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('owner_consent', $e->errors);
		}
	}

	public function test_consent_requires_a_traceable_note(): void
	{
		try
		{
			$this->make_business(array('owner_consent' => 1));
			$this->fail('Persetujuan tanpa catatan harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('consent_note', $e->errors);
		}
	}

	public function test_publish_requires_owner_consent(): void
	{
		$business = $this->make_business();
		$this->assertNotEmpty($this->CI->businesses->publish_blockers($business));
		try
		{
			$this->CI->businesses->publish($business, $this->actor->id);
			$this->fail('Terbit tanpa persetujuan pemilik harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('belum menyetujui', $e->getMessage());
		}
		$this->assertSame(array(), $this->CI->businesses->published_businesses());

		$business = $this->CI->businesses->save(array(
			'name' => 'Keripik Cihawuk', 'slug' => 'uji-keripik-cihawuk', 'category' => 'food',
			'owner_name' => 'Ibu Contoh', 'description' => 'Keripik singkong produksi rumah tangga.',
			'products' => 'Keripik singkong, keripik pisang', 'public_location' => 'Dusun contoh', 'is_active' => 1,
			'owner_consent' => 1, 'consent_note' => 'Pemilik menyetujui lewat surat pernyataan 12 Sep 2026.',
			'public_contact' => '0812xxxxxxx',
		), $this->actor->id, $business->public_id);
		$this->CI->businesses->publish($this->CI->businesses->business($business->public_id), $this->actor->id);
		$this->CI->public_cache->forget_group('listing');

		$published = $this->CI->businesses->published_businesses();
		$this->assertCount(1, $published);
		$this->assertSame('Keripik Cihawuk', $published[0]['name']);
		$this->assertSame('0812xxxxxxx', $published[0]['public_contact']);
	}

	public function test_revoking_consent_takes_it_down_immediately(): void
	{
		$business = $this->publish_one();
		$this->assertCount(1, $this->CI->businesses->published_businesses());

		$this->CI->businesses->revoke_consent($this->CI->businesses->business($business->public_id),
			'Pemilik meminta datanya dihapus.', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');

		$this->assertSame(array(), $this->CI->businesses->published_businesses());
		$after = $this->CI->businesses->business($business->public_id);
		$this->assertSame(0, (int) $after->owner_consent);
		$this->assertSame('draft', $after->publication_status);
		$this->assertStringContainsString('dicabut', (string) $after->consent_note);
	}

	public function test_inactive_business_is_not_public(): void
	{
		$business = $this->publish_one();
		$this->CI->db->where('id', (int) $business->id)->update('businesses', array('is_active' => 0));
		$this->CI->public_cache->forget_group('listing');
		$this->assertSame(array(), $this->CI->businesses->published_businesses());
	}

	public function test_archive_keeps_the_row(): void
	{
		$business = $this->publish_one();
		$this->CI->businesses->archive($this->CI->businesses->business($business->public_id),
			'Usaha sudah tutup.', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertSame(array(), $this->CI->businesses->published_businesses());
		$this->assertNotNull($this->CI->businesses->business($business->public_id));
	}

	/** Buat satu usaha berizin lalu terbitkan. */
	protected function publish_one()
	{
		$business = $this->make_business(array(
			'owner_consent' => 1, 'consent_note' => 'Persetujuan tertulis pemilik, 12 Sep 2026.',
		));
		$this->CI->businesses->publish($business, $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		return $this->CI->businesses->business($business->public_id);
	}
}
