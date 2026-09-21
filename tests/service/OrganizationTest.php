<?php

/**
 * Tahap 7 v1.2: struktur organisasi dinamis.
 *
 * Yang dijaga: cycle dan parent lintas periode ditolak, satu jabatan hanya punya satu
 * penugasan aktif, nonaktif tidak menghapus histori, foto tanpa izin tidak tersimpan,
 * nomor SK tidak pernah keluar ke snapshot publik, dan draft tidak terbaca publik.
 */
class OrganizationTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var object */
	protected $period;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('OrganizationService', NULL, 'org');
		$existing = $this->CI->db->get_where('users', array('username' => 'struktur.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('struktur.svc.test', array('super_admin'));
		$this->clean();
		$this->period = $this->CI->org->save_period(array(
			'name' => '[Uji] Periode struktur', 'year_start' => 2024, 'year_end' => 2030,
		), $this->actor->id);
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		foreach ($this->CI->db->like('name', '[Uji]')->get('org_periods')->result() as $row)
		{
			$this->CI->db->where(array('target_type' => 'organization', 'target_id' => (int) $row->id))
				->delete('cms_publication_snapshots');
			$this->CI->db->where('period_id', (int) $row->id)->delete('org_assignments');
			$this->CI->db->where('period_id', (int) $row->id)->delete('org_positions');
			$this->CI->db->where('period_id', (int) $row->id)->delete('org_units');
			$this->CI->db->where('id', (int) $row->id)->delete('org_periods');
		}
		$this->CI->db->like('full_name', '[Uji]')->delete('people');
		$this->CI->public_cache->forget_group('listing');
	}

	protected function position($title, $parent = NULL)
	{
		return $this->CI->org->save_position($this->period, array(
			'title' => $title, 'parent_id' => $parent ? (int) $parent->id : NULL, 'active' => 1,
		), $this->actor->id);
	}

	protected function person($name)
	{
		return $this->CI->org->save_person(array('full_name' => $name), $this->actor->id);
	}

	public function test_self_parent_and_cycle_are_rejected(): void
	{
		$head = $this->position('Kepala Desa');
		$secretary = $this->position('Sekretaris Desa', $head);

		try
		{
			$this->CI->org->save_position($this->period, array(
				'title' => 'Kepala Desa', 'parent_id' => (int) $head->id, 'active' => 1,
			), $this->actor->id, $head->public_id);
			$this->fail('Self-parent harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('parent_id', $e->errors);
		}

		try
		{
			$this->CI->org->save_position($this->period, array(
				'title' => 'Kepala Desa', 'parent_id' => (int) $secretary->id, 'active' => 1,
			), $this->actor->id, $head->public_id);
			$this->fail('Cycle harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('lingkaran', $e->errors['parent_id']);
		}
	}

	public function test_parent_from_another_period_is_rejected(): void
	{
		$other = $this->CI->org->save_period(array('name' => '[Uji] Periode lain', 'year_start' => 2018), $this->actor->id);
		$foreign = $this->CI->org->save_position($other, array('title' => 'Kepala Desa lama', 'active' => 1), $this->actor->id);

		try
		{
			$this->CI->org->save_position($this->period, array(
				'title' => 'Sekretaris', 'parent_id' => (int) $foreign->id, 'active' => 1,
			), $this->actor->id);
			$this->fail('Atasan lintas periode harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('periode lain', $e->errors['parent_id']);
		}
	}

	public function test_depth_limit_is_enforced(): void
	{
		$parent = NULL;
		for ($i = 1; $i <= OrganizationService::MAX_DEPTH; $i++)
		{
			$parent = $this->position('Tingkat '.$i, $parent);
		}
		try
		{
			$this->position('Terlalu dalam', $parent);
			$this->fail('Kedalaman melebihi batas harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('Kedalaman', $e->errors['parent_id']);
		}
	}

	public function test_one_active_assignment_per_position(): void
	{
		$head = $this->position('Kepala Desa');
		$first = $this->person('[Uji] Orang Pertama');
		$second = $this->person('[Uji] Orang Kedua');

		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $head->id, 'person_id' => (int) $first->id, 'assignment_type' => 'definitive',
		), $this->actor->id);

		try
		{
			$this->CI->org->save_assignment($this->period, array(
				'position_id' => (int) $head->id, 'person_id' => (int) $second->id, 'assignment_type' => 'definitive',
			), $this->actor->id);
			$this->fail('Dua penugasan aktif pada satu jabatan harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('penugasan aktif', $e->errors['position_id']);
		}

		// Setelah yang pertama diakhiri, penugasan baru diterima dan histori tetap ada.
		$active = $this->CI->db->where('position_id', (int) $head->id)->where('status', 'active')->get('org_assignments')->row();
		$this->CI->org->end_assignment($active, '2025-01-01', 'Purnatugas', $this->actor->id);
		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $head->id, 'person_id' => (int) $second->id, 'assignment_type' => 'acting',
		), $this->actor->id);
		$this->assertSame(2, $this->CI->db->where('position_id', (int) $head->id)->count_all_results('org_assignments'),
			'Penugasan lama tetap tersimpan sebagai histori');
	}

	public function test_vacant_assignment_cannot_have_a_person(): void
	{
		$position = $this->position('Kaur Umum');
		$person = $this->person('[Uji] Orang Vakan');
		try
		{
			$this->CI->org->save_assignment($this->period, array(
				'position_id' => (int) $position->id, 'person_id' => (int) $person->id, 'assignment_type' => 'vacant',
			), $this->actor->id);
			$this->fail('Penugasan kosong dengan orang harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('person_id', $e->errors);
		}
	}

	public function test_photo_without_consent_is_rejected(): void
	{
		// Aturan izin diperiksa sebelum berkas media disentuh, jadi id mana pun cukup.
		try
		{
			$this->CI->org->save_person(array(
				'full_name' => '[Uji] Orang Berfoto', 'photo_media_id' => 4242, 'photo_consent' => 0,
			), $this->actor->id);
			$this->fail('Foto tanpa izin harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('photo_consent', $e->errors);
		}
	}

	public function test_photo_must_be_an_existing_image(): void
	{
		// Id yang tidak ada, atau media bukan gambar (PDF), ditolak walaupun izin tercatat.
		$pdf = $this->CI->db->select('id')->where('mime_type', 'application/pdf')->limit(1)->get('media_assets')->row();
		foreach (array_filter(array(987654321, $pdf ? (int) $pdf->id : NULL)) as $media_id)
		{
			try
			{
				$this->CI->org->save_person(array(
					'full_name' => '[Uji] Foto Salah', 'photo_media_id' => $media_id, 'photo_consent' => 1,
				), $this->actor->id);
				$this->fail('Media #'.$media_id.' harus ditolak sebagai foto');
			}
			catch (DomainRuleException $e)
			{
				$this->assertArrayHasKey('photo_media_id', $e->errors);
			}
		}
	}

	public function test_snapshot_carries_assignment_years_only(): void
	{
		$head = $this->position('Kepala Desa');
		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $head->id, 'person_id' => (int) $this->person('[Uji] Bertahun')->id,
			'assignment_type' => 'definitive', 'start_date' => '2024-03-15',
		), $this->actor->id);
		$node = $this->CI->org->build_snapshot($this->period)['nodes'][0];
		$this->assertSame(2024, $node['start_year']);
		$this->assertNull($node['end_year']);
		$this->assertStringNotContainsString('2024-03-15', json_encode($node), 'Tanggal persis tidak keluar ke publik');
	}

	public function test_deactivating_a_parent_with_active_children_is_blocked(): void
	{
		$head = $this->position('Kepala Desa');
		$this->position('Sekretaris Desa', $head);
		try
		{
			$this->CI->org->deactivate_position($head, $this->actor->id);
			$this->fail('Menonaktifkan jabatan yang masih punya bawahan aktif harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('bawahan aktif', $e->getMessage());
		}
	}

	public function test_draft_is_not_public_and_publish_builds_a_snapshot(): void
	{
		$head = $this->position('Kepala Desa');
		$person = $this->person('[Uji] Kepala Desa Uji');
		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $head->id, 'person_id' => (int) $person->id,
			'assignment_type' => 'definitive', 'decree_number' => 'SK/RAHASIA/2024',
		), $this->actor->id);

		$this->assertNull($this->CI->org->published_structure($this->period->public_id), 'Draft tidak boleh publik');

		$this->assertSame(1, $this->CI->org->publish($this->period, 'Publikasi uji', $this->actor->id));
		$this->CI->public_cache->forget_group('listing');

		$snapshot = $this->CI->org->published_structure($this->period->public_id);
		$this->assertNotNull($snapshot);
		$this->assertCount(1, $snapshot['nodes']);
		$this->assertSame('[Uji] Kepala Desa Uji', $snapshot['nodes'][0]['person']['name']);
		// Nomor SK internal tidak boleh muncul pada snapshot publik.
		$this->assertStringNotContainsString('SK/RAHASIA', json_encode($snapshot));
		$this->assertArrayNotHasKey('decree_number_ciphertext', $snapshot['nodes'][0]);
	}

	public function test_inactive_positions_and_ended_assignments_are_left_out(): void
	{
		$head = $this->position('Kepala Desa');
		$aide = $this->position('Staf Uji', $head);
		$person = $this->person('[Uji] Staf');
		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $aide->id, 'person_id' => (int) $person->id, 'assignment_type' => 'definitive',
		), $this->actor->id);

		$assignment = $this->CI->db->where('position_id', (int) $aide->id)->get('org_assignments')->row();
		$this->CI->org->end_assignment($assignment, '2025-06-30', 'Mutasi', $this->actor->id);
		$this->CI->org->deactivate_position($aide, $this->actor->id);

		$this->CI->org->publish($this->period, 'Publikasi setelah penonaktifan', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$snapshot = $this->CI->org->published_structure($this->period->public_id);

		$titles = array();
		foreach ($snapshot['nodes'] as $node)
		{
			$titles[] = $node['title'];
		}
		$this->assertContains('Kepala Desa', $titles);
		$this->assertNotContains('Staf Uji', $titles, 'Jabatan nonaktif tidak ikut terbit');
		$this->assertNotNull($this->CI->org->position($aide->public_id), 'Barisnya tetap ada di basis data');
	}

	public function test_cloning_copies_structure_without_assignments(): void
	{
		$head = $this->position('Kepala Desa');
		$person = $this->person('[Uji] Kepala Lama');
		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $head->id, 'person_id' => (int) $person->id, 'assignment_type' => 'definitive',
		), $this->actor->id);

		$target = $this->CI->org->save_period(array('name' => '[Uji] Periode baru', 'year_start' => 2031), $this->actor->id);
		$this->CI->org->clone_structure($this->period, $target, $this->actor->id);

		$this->assertCount(1, $this->CI->org->positions($target));
		$this->assertCount(0, $this->CI->org->assignments($target),
			'Penugasan periode lama tidak boleh ikut disalin');
	}

	public function test_only_one_period_is_the_public_default(): void
	{
		$head = $this->position('Kepala Desa');
		$this->CI->org->save_assignment($this->period, array(
			'position_id' => (int) $head->id, 'assignment_type' => 'vacant',
		), $this->actor->id);
		$this->CI->org->publish($this->period, 'Periode pertama', $this->actor->id);

		$second = $this->CI->org->save_period(array('name' => '[Uji] Periode kedua', 'year_start' => 2031), $this->actor->id);
		$head2 = $this->CI->org->save_position($second, array('title' => 'Kepala Desa', 'active' => 1), $this->actor->id);
		$this->CI->org->save_assignment($second, array(
			'position_id' => (int) $head2->id, 'assignment_type' => 'vacant',
		), $this->actor->id);
		$this->CI->org->publish($second, 'Periode kedua', $this->actor->id);

		$defaults = (int) $this->CI->db->where('is_public_default', 1)->count_all_results('org_periods');
		$this->assertSame(1, $defaults);
		$this->CI->public_cache->forget_group('listing');
		$this->assertSame('[Uji] Periode kedua', $this->CI->org->published_structure()['period']['name']);
	}
}
