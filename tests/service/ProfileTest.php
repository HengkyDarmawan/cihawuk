<?php

/**
 * Tahap 5 v1.2: profil desa sebagai blok terstruktur berversi.
 *
 * Yang dijaga: draft tidak pernah publik, blok wajib diverifikasi sebelum terbit,
 * konflik luas wilayah tidak boleh disembunyikan, dan "sampai sekarang" pada dokumen
 * lama tidak otomatis menjadi jabatan aktif hari ini.
 */
class ProfileTest extends CiTestCase {

	/** @var object */
	protected $actor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('ProfileService', NULL, 'profile_service');
		$existing = $this->CI->db->get_where('users', array('username' => 'profil.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('profil.svc.test', array('content_editor', 'content_publisher'));
		$this->restore();
	}

	protected function tearDown(): void
	{
		$this->restore();
		parent::tearDown();
	}

	protected function restore()
	{
		$this->CI->db->where('target_type', 'profile')->delete('cms_publication_snapshots');
		$this->CI->db->update('profile_blocks', array(
			'status' => 'draft', 'published_version_id' => NULL, 'published_at' => NULL,
			'verification_status' => 'unverified', 'verified_by' => NULL, 'verified_at' => NULL,
		));
		$this->CI->db->update('leadership_terms', array(
			'publication_status' => 'draft', 'verification_status' => 'unverified',
		));
		// Kembalikan setiap blok ke versi pertama hasil seed supaya tes tidak saling mewarisi isi.
		foreach ($this->CI->db->get('profile_blocks')->result() as $row)
		{
			$first = $this->CI->db->where('block_id', (int) $row->id)->order_by('version_no')
				->limit(1)->get('profile_block_versions')->row();
			if ( ! $first)
			{
				continue;
			}
			$this->CI->db->where('id', (int) $row->id)->update('profile_blocks', array('current_version_id' => (int) $first->id));
			$this->CI->db->where('block_id', (int) $row->id)->where('version_no >', 1)->delete('profile_block_versions');
		}
		$this->CI->public_cache->forget_group('listing');
	}

	protected function block($key)
	{
		return $this->CI->profile_service->block($key);
	}

	/** Siapkan tiga blok hasil seed menjadi siap terbit. */
	protected function prepare_publishable()
	{
		foreach (array('identity', 'history', 'geography') as $key)
		{
			$block = $this->block($key);
			$this->CI->profile_service->submit_review($block, $this->actor->id);
			$this->CI->profile_service->verify_block($this->block($key), TRUE, $this->actor->id);
		}
		foreach ($this->CI->profile_service->terms() as $term)
		{
			$this->CI->profile_service->verify_term($term, TRUE, $this->actor->id);
		}
	}

	public function test_greeting_without_reviewed_text_renders_original_without_warning(): void
	{
		// Blok sambutan yang belum punya versi review tidak memuat kunci edited_text.
		// failOnWarning membuat tes ini gagal bila view membaca kunci itu tanpa pengaman.
		$html = $this->CI->load->view('site/profil', array(
			'blocks' => array('greeting' => array('body' => array(
				'author_name' => 'Kepala Desa', 'original_text' => "Paragraf pertama.

Paragraf kedua.",
			))),
			'contact' => NULL,
		), TRUE);
		$this->assertStringContainsString('<p>Paragraf pertama.</p>', $html);
		$this->assertStringContainsString('<p>Paragraf kedua.</p>', $html);
	}

	public function test_seeded_profile_is_draft_and_not_public(): void
	{
		$this->assertNull($this->CI->profile_service->published(), 'Profil hasil seed tidak boleh langsung publik');
		$this->assertSame(array(), $this->CI->profile_service->published_blocks_for('profil'));

		$identity = $this->block('identity');
		$this->assertSame('draft', $identity->status);
		$this->assertSame('unverified', $identity->verification_status);
		// Kode PUM disimpan sebagai string, bukan angka.
		$this->assertSame('320431.2006', $identity->body['pum_code']);
	}

	public function test_publication_requires_verified_blocks(): void
	{
		$block = $this->block('identity');
		$this->CI->profile_service->submit_review($block, $this->actor->id);

		$report = $this->CI->profile_service->validate_profile();
		$this->assertNotEmpty($report['errors']);
		$this->assertStringContainsString('belum diverifikasi', implode(' ', $report['errors']));

		try
		{
			$this->CI->profile_service->publish('Coba terbit', $this->actor->id);
			$this->fail('Profil dengan blok belum diverifikasi harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(422, $e->http_status ?? 422);
		}
		$this->assertNull($this->CI->profile_service->published());
	}

	public function test_publish_then_rollback_keeps_history(): void
	{
		$this->prepare_publishable();
		$this->assertSame(1, $this->CI->profile_service->publish('Publikasi pertama', $this->actor->id));
		$this->CI->public_cache->forget_group('listing');

		$published = $this->CI->profile_service->published();
		$this->assertSame('Cihawuk', $published['blocks']['identity']['body']['village_name']);
		$this->assertCount(9, $published['terms']);

		// Ubah draft lalu terbitkan lagi: revisi naik, isi lama tetap tersimpan.
		$block = $this->block('identity');
		$body = $block->body;
		$body['summary'] = 'Ringkasan yang sudah disunting untuk pengujian.';
		$this->CI->profile_service->save_block($block, $body + array('title' => $block->title), $this->actor->id);
		$this->CI->profile_service->verify_block($this->block('identity'), TRUE, $this->actor->id);
		$this->assertSame(2, $this->CI->profile_service->publish('Publikasi kedua', $this->actor->id));
		$this->CI->public_cache->forget_group('listing');
		$this->assertStringContainsString('disunting', $this->CI->profile_service->published()['blocks']['identity']['body']['summary']);

		$first = $this->CI->db->where(array('target_type' => 'profile', 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		$this->assertSame(3, $this->CI->profile_service->rollback((int) $first->id, 'Kembalikan revisi pertama', $this->actor->id));
		$this->CI->public_cache->forget_group('listing');
		$this->assertStringNotContainsString('disunting', (string) $this->CI->profile_service->published()['blocks']['identity']['body']['summary']);
		$this->assertSame(3, $this->CI->db->where('target_type', 'profile')->count_all_results('cms_publication_snapshots'));
	}

	public function test_saving_a_block_cancels_its_verification(): void
	{
		$block = $this->block('geography');
		$this->CI->profile_service->verify_block($block, TRUE, $this->actor->id);
		$this->assertSame('verified', $this->block('geography')->verification_status);

		$body = $this->block('geography')->body;
		$body['rainy_months'] = 7;
		$this->CI->profile_service->save_block($this->block('geography'), $body + array('title' => 'Geografi'), $this->actor->id);
		$this->assertSame('unverified', $this->block('geography')->verification_status,
			'Isi berubah, jadi verifikasi lama tidak boleh tetap berlaku');
	}

	public function test_land_use_conflict_must_be_explained(): void
	{
		$block = $this->block('geography');
		$body = $block->body;
		unset($body['area_conflict_note']);

		try
		{
			$this->CI->profile_service->save_block($block, $body + array('title' => 'Geografi'), $this->actor->id);
			$this->fail('Konflik luas wilayah tanpa catatan harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('area_conflict_note', $e->errors);
		}

		// Dengan catatan, penyimpanan diterima dan catatannya ikut tersimpan apa adanya.
		$body['area_conflict_note'] = 'S1 dan S2 mencatat 932,35 ha; total penggunaan lahan 931,35 ha; S4 931,00 ha.';
		$saved = $this->CI->profile_service->save_block($block, $body + array('title' => 'Geografi'), $this->actor->id);
		$this->assertStringContainsString('931,00', $saved->body['area_conflict_note']);
	}

	public function test_vision_of_district_cannot_be_copied_from_village(): void
	{
		$block = $this->block('vision');
		try
		{
			$this->CI->profile_service->save_block($block, array(
				'title' => 'Visi',
				'village_vision' => 'Contoh visi untuk pengujian.',
				'district_vision' => 'Contoh visi untuk pengujian.',
			), $this->actor->id);
			$this->fail('Visi kecamatan yang sama persis dengan visi desa harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('district_vision', $e->errors);
		}
	}

	public function test_ongoing_claim_is_not_an_end_year(): void
	{
		$term = $this->CI->db->where('person_name', 'Yaya Dores')->get('leadership_terms')->row();
		$this->assertSame(1, (int) $term->ongoing_claim);
		$this->assertNull($term->year_end, 'Periode terakhir tidak boleh diberi tahun selesai karangan');

		try
		{
			$this->CI->profile_service->save_term(array(
				'person_name' => 'Yaya Dores', 'year_start' => 2019, 'year_end' => 2026, 'ongoing_claim' => 1,
			), $this->actor->id, $term->public_id);
			$this->fail('Klaim "sampai sekarang" dengan tahun selesai harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('ongoing_claim', $e->errors);
		}
	}

	public function test_duplicate_names_are_warned_not_merged(): void
	{
		$this->prepare_publishable();
		$report = $this->CI->profile_service->validate_profile();
		$this->assertStringContainsString('Aep Saepuloh', implode(' ', $report['warnings']),
			'Nama yang muncul dua kali harus diperingatkan, bukan digabung otomatis');
		$this->assertEmpty($report['errors']);

		$this->CI->profile_service->publish('Publikasi uji', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$names = array();
		foreach ($this->CI->profile_service->published()['terms'] as $term)
		{
			$names[] = $term['person_name'].' '.$term['year_start'];
		}
		$this->assertContains('Aep Saepuloh 2006', $names);
		$this->assertContains('Aep Saepuloh 2012', $names, 'Dua periode orang yang sama tetap terpisah');
	}

	public function test_unpublish_empties_the_public_profile(): void
	{
		$this->prepare_publishable();
		$this->CI->profile_service->publish('Publikasi uji', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertNotEmpty($this->CI->profile_service->published_blocks_for('profil'));

		$this->CI->profile_service->unpublish('Ditarik untuk pengujian', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertNull($this->CI->profile_service->published());
		$this->assertSame(1, $this->CI->db->where('target_type', 'profile')->count_all_results('cms_publication_snapshots'),
			'Riwayat snapshot tidak dihapus saat ditarik');
	}

	public function test_unknown_fields_are_dropped(): void
	{
		$block = $this->block('identity');
		$saved = $this->CI->profile_service->save_block($block, array(
			'title' => 'Identitas desa',
			'village_name' => 'Cihawuk', 'district' => 'Kertasari', 'regency' => 'Bandung', 'province' => 'Jawa Barat',
			'script_payload' => '<script>alert(1)</script>',
		), $this->actor->id);
		$this->assertArrayNotHasKey('script_payload', $saved->body);
	}
}
