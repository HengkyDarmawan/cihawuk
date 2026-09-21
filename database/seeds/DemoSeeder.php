<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__.'/Seeder.php';

/**
 * Data demonstrasi untuk development/testing.
 *
 * - Semua akun memakai domain email `.test` dan username berakhiran `.demo`.
 * - Tidak memakai NIK nyata, foto KTP, atau nama pejabat sebagai akun login.
 * - Tiket demo tidak pernah ditampilkan pada halaman publik (semua tiket privat).
 */
class DemoSeeder extends Seeder {

	const PASSWORD = 'DemoCihawuk2026!aman';

	protected $users = array();

	/** Penghitung avatar ilustrasi per kelompok. */
	protected $avatar_next = array();

	public function run()
	{
		if (ENVIRONMENT === 'production')
		{
			throw new RuntimeException('Demo seeder is not allowed on production.');
		}
		$this->CI->load->library('AuthService', NULL, 'auth');
		$this->CI->load->library('TicketWorkflowService', NULL, 'workflow');
		$this->CI->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->CI->load->model(array('User_model' => 'user_model', 'Ticket_model' => 'tickets'));

		$this->out('Seed demo (development/testing)…');
		$this->accounts();
		$this->scenarios();
		$this->profile();
		$this->organization();
		$this->facilities();
		$this->potentials();
		$this->businesses();
		$this->budget();
		$this->asset_lifecycle((array) $this->assets());
		$this->warehouse_movements($this->warehouse());
		$this->content();
		$this->datasets();
		$this->homepage();
		$this->summary();
		$this->out('Password seluruh akun demo: '.self::PASSWORD);
		$this->out('Hapus data demo dengan: php public/index.php tools purge_demo');
	}

	protected function accounts()
	{
		$definitions = array(
			array('admin.demo', 'Admin Sistem (Demo)', array('super_admin')),
			array('pelayanan.demo', 'Petugas Pelayanan (Demo)', array('admin_desa')),
			array('koordinator.demo', 'Koordinator Pelayanan (Demo)', array('admin_desa'), 'all'),
			array('petugas.demo', 'Petugas Lapangan (Demo)', array('petugas'), 'PEMBANGUNAN'),
			array('petugas2.demo', 'Petugas Pelayanan Umum (Demo)', array('petugas'), 'PELAYANAN'),
			array('loket.demo', 'Petugas Loket (Demo)', array('petugas')),
			array('kades.demo', 'Kepala Desa (Demo)', array('admin_desa'), 'all'),
			array('webadmin.demo', 'Admin Website (Demo)', array('admin_desa')),
			array('editor.demo', 'Editor Konten (Demo)', array('petugas')),
			array('penerbit.demo', 'Penerbit Konten (Demo)', array('admin_desa')),
			array('verifikator.demo', 'Verifikator Data (Demo)', array('admin_desa')),
			array('keuangan.demo', 'Pengelola Keuangan (Demo)', array('admin_desa')),
			array('auditkeuangan.demo', 'Verifikator Keuangan (Demo)', array('admin_desa')),
			array('aset.demo', 'Pengurus Aset (Demo)', array('admin_desa')),
			array('gudang.demo', 'Petugas Gudang (Demo)', array('petugas')),
			array('rahasia.demo', 'Penangan Laporan Rahasia (Demo)', array('admin_desa'), 'KESRA'),
			array('warga.demo', 'Warga Contoh Satu', array('resident')),
			array('warga2.demo', 'Warga Contoh Dua', array('resident')),
		);
		foreach ($definitions as $def)
		{
			list($username, $name, $roles) = $def;
			$scope = $def[3] ?? NULL;
			$existing = $this->CI->user_model->find_by_username($username);
			if ($existing)
			{
				$this->users[$username] = $existing;
				$this->count('users', FALSE);
				continue;
			}
			$user_id = $this->CI->user_model->create(array(
				'public_id' => $this->CI->crypto->public_id(),
				'username' => $username,
				'display_name' => $name,
				'email' => str_replace('.demo', '', $username).'@contoh.test',
				'password_hash' => $this->CI->auth->hash_password(self::PASSWORD),
				'account_status' => 'active',
				'email_verified_at' => $this->now(),
				'registration_channel' => 'demo',
			));
			$this->count('users', TRUE);
			foreach ($roles as $role)
			{
				$this->CI->user_model->assign_role($user_id, $role, NULL);
			}
			if (in_array('resident', $roles, TRUE))
			{
				db_must($this->CI->db->insert('resident_profiles', array(
					'user_id' => $user_id, 'display_name' => $name, 'verification_status' => 'verified',
					'verified_at' => $this->now(), 'created_at' => $this->now(), 'updated_at' => $this->now(),
				)), 'demo resident_profiles');
			}
			if ($scope !== NULL)
			{
				$unit_id = ($scope === 'all')
					? (int) $this->CI->db->select('id')->order_by('id')->limit(1)->get('organizational_units')->row('id')
					: $this->id_of('organizational_units', array('code' => $scope));
				db_must($this->CI->db->insert('user_unit_scopes', array(
					'user_id' => $user_id, 'unit_id' => $unit_id,
					'scope_type' => ($scope === 'all') ? 'all' : 'member', 'created_at' => $this->now(),
				)), 'demo user_unit_scopes');
			}
			$this->users[$username] = $this->CI->user_model->find($user_id);
		}
	}

	protected function category($code)
	{
		return $this->id_of('ticket_categories', array('code' => $code));
	}

	// ------------------------------------------------------------------
	// Jejak data demo
	// ------------------------------------------------------------------

	/**
	 * Catat satu entitas akar buatan seeder demo.
	 *
	 * Modul baru sengaja tidak memakai awalan "[Demo]" pada namanya supaya tampilannya
	 * wajar saat diperagakan; jejak inilah yang membuat `purge_demo` tetap pasti.
	 */
	protected function track($type, $id, $public_id = NULL, $label = NULL)
	{
		if ( ! $id)
		{
			return $id;
		}
		$this->CI->db->replace('demo_records', array(
			'entity_type' => $type,
			'entity_id' => (int) $id,
			'public_id' => $public_id !== NULL ? (string) $public_id : NULL,
			'label' => $label !== NULL ? mb_substr((string) $label, 0, 200) : NULL,
			'created_at' => $this->now(),
		));
		return $id;
	}

	/** Id entitas demo per jenis, urut sesuai pembuatan. */
	protected function tracked($type)
	{
		$out = array();
		foreach ($this->CI->db->select('entity_id')->where('entity_type', $type)
			->order_by('id')->get('demo_records')->result() as $row)
		{
			$out[] = (int) $row->entity_id;
		}
		return $out;
	}

	protected function has_demo($type)
	{
		return $this->CI->db->where('entity_type', $type)->count_all_results('demo_records') > 0;
	}

	/** Media demo berdasarkan nama berkas aslinya, mis. 'kebun-teh.jpg'. */
	protected function media($basename)
	{
		$row = $this->CI->db->select('id')->where('original_name', $basename)
			->where('publication_status', 'published')->limit(1)->get('media_assets')->row();
		return $row ? (int) $row->id : NULL;
	}

	protected function actor_id($username)
	{
		return isset($this->users[$username]) ? (int) $this->users[$username]->id : NULL;
	}

	protected function staff_actor($username)
	{
		return array('kind' => 'staff', 'user_id' => (int) $this->users[$username]->id, 'type' => 'staff');
	}

	protected function reporter_actor($username = NULL)
	{
		return ($username === NULL)
			? array('kind' => 'reporter', 'user_id' => NULL, 'type' => 'anonymous')
			: array('kind' => 'reporter', 'user_id' => (int) $this->users[$username]->id, 'type' => 'resident');
	}

	/** Buat tiket demo langsung melalui service agar riwayat dan SLA realistis. */
	protected function make_ticket(array $input, array $context, $days_ago = 0)
	{
		if ($days_ago > 0)
		{
			$this->CI->clock->set(gmdate('Y-m-d H:i:s', time() - $days_ago * 86400));
		}
		$result = $this->CI->workflow->submit($input, $context);
		$this->CI->clock->set(NULL);
		$this->count('tickets', TRUE);
		return $result;
	}

	protected function base_input(array $override)
	{
		return array_merge(array(
			'report_type' => 'complaint',
			'category_id' => $this->category('INFRA'),
			'title' => 'Judul laporan contoh untuk pengujian',
			'description' => 'Uraian laporan contoh yang dibuat oleh seeder demo untuk menguji alur layanan. Isi ini bukan laporan warga sungguhan.',
			'statement' => TRUE,
		), $override);
	}

	protected function anon_context(array $override = array())
	{
		return array_merge(array(
			'intake_channel' => 'public_anonymous', 'identity_mode' => 'anonymous',
			'reporter_user_id' => NULL, 'created_by_user_id' => NULL, 'actor_type' => 'anonymous',
		), $override);
	}

	protected function scenarios()
	{
		if ($this->CI->db->where('title LIKE', '[Demo]%')->count_all_results('tickets') > 0)
		{
			$this->out('  Skenario demo sudah ada; dilewati.');
			return;
		}

		// 1. Anonim baru menunggu verifikasi.
		$this->make_ticket($this->base_input(array(
			'title' => '[Demo] Lampu penerangan jalan mati di jalur kebun',
			'description' => 'Sejak beberapa hari lalu lampu penerangan jalan pada jalur menuju kebun tidak menyala, sehingga warga kesulitan melintas pada malam hari. Data demo untuk pengujian.',
			'location_text' => 'Jalur kebun (data demo)',
		)), $this->anon_context(), 1);

		// 2. Anonim yang diminta melengkapi informasi.
		$t2 = $this->make_ticket($this->base_input(array(
			'title' => '[Demo] Saluran air tersumbat dekat jalan utama',
			'description' => 'Saluran air tersumbat sehingga air meluap ke jalan saat hujan. Laporan demo untuk menguji status perlu kelengkapan.',
			'location_text' => 'Dekat jalan utama (data demo)',
		)), $this->anon_context(), 3);
		$t2t = $this->CI->workflow->perform($t2['ticket'], 'start_verification', array(), $this->staff_actor('pelayanan.demo'));
		$this->CI->workflow->perform($t2t, 'request_information', array(
			'message' => 'Mohon informasikan titik saluran yang tersumbat dan sejak kapan kejadiannya agar petugas dapat menyiapkan penanganan.',
		), $this->staff_actor('pelayanan.demo'));

		// 3. Laporan warga berakun yang sudah ditugaskan dan ditangani.
		$t3 = $this->make_ticket($this->base_input(array(
			'title' => '[Demo] Jalan berlubang di jalur penghubung dusun',
			'description' => 'Terdapat lubang cukup dalam pada jalur penghubung yang membahayakan pengendara motor. Laporan demo untuk menguji alur penanganan.',
			'location_text' => 'Jalur penghubung dusun (data demo)',
		)), array(
			'intake_channel' => 'resident_dashboard', 'identity_mode' => 'identified',
			'reporter_user_id' => (int) $this->users['warga.demo']->id, 'created_by_user_id' => NULL, 'actor_type' => 'resident',
		), 5);
		$t3t = $this->CI->workflow->perform($t3['ticket'], 'start_verification', array(), $this->staff_actor('pelayanan.demo'));
		$t3t = $this->CI->workflow->perform($t3t, 'assign', array(
			'assignee_id' => (int) $this->users['petugas.demo']->id,
			'unit_id' => $this->id_of('organizational_units', array('code' => 'PEMBANGUNAN')),
		), $this->staff_actor('pelayanan.demo'));
		$this->CI->workflow->perform($t3t, 'accept_work', array(), $this->staff_actor('petugas.demo'));

		// 4. Laporan yang dicatat petugas loket dengan kontak pelapor.
		$this->make_ticket($this->base_input(array(
			'report_type' => 'information_request',
			'category_id' => $this->category('INFO_LAYANAN'),
			'title' => '[Demo] Menanyakan persyaratan surat keterangan domisili',
			'description' => 'Warga datang ke kantor desa menanyakan persyaratan dan alur pengurusan surat keterangan domisili. Dicatat petugas loket sebagai data demo.',
		)), array(
			'intake_channel' => 'front_desk', 'identity_mode' => 'identified',
			'reporter_user_id' => NULL, 'created_by_user_id' => (int) $this->users['loket.demo']->id, 'actor_type' => 'staff',
			'contact' => array('name' => 'Warga Demo Loket', 'phone' => '+628123456789', 'email' => NULL),
		), 2);

		// 5. Laporan rahasia (kategori sensitif).
		$this->make_ticket($this->base_input(array(
			'category_id' => $this->category('PERILAKU_APARAT'),
			'title' => '[Demo] Keluhan mengenai pelayanan petugas',
			'description' => 'Laporan demo berkategori sensitif untuk menguji pembatasan akses. Isi ini bukan pengaduan nyata terhadap siapa pun.',
		)), $this->anon_context(), 4);

		// 6. Laporan di luar kewenangan desa: dirujuk dengan petunjuk.
		$t6 = $this->make_ticket($this->base_input(array(
			'title' => '[Demo] Gangguan aliran listrik di beberapa rumah',
			'description' => 'Aliran listrik sering padam di beberapa rumah warga. Laporan demo untuk menguji alur rujukan ke instansi berwenang.',
			'location_text' => 'Permukiman warga (data demo)',
		)), $this->anon_context(), 6);
		$t6t = $this->CI->workflow->perform($t6['ticket'], 'start_verification', array(), $this->staff_actor('pelayanan.demo'));
		$this->CI->workflow->perform($t6t, 'refer', array(
			'target_name' => 'Penyedia layanan kelistrikan (data demo)',
			'reference_type' => 'guidance_only',
			'message' => 'Gangguan kelistrikan bukan kewenangan pemerintah desa. Silakan laporkan ke penyedia layanan kelistrikan melalui kanal resminya; desa siap membantu bila perlu surat pengantar.',
		), $this->staff_actor('pelayanan.demo'));

		// 7. Laporan duplikat: ditolak dengan tautan ke tiket utama.
		$t7 = $this->make_ticket($this->base_input(array(
			'title' => '[Demo] Lampu jalan masih mati (laporan kedua)',
			'description' => 'Laporan demo yang sengaja dibuat menyerupai laporan pertama untuk menguji penanganan duplikat pada sistem tiket.',
			'location_text' => 'Jalur kebun (data demo)',
		)), $this->anon_context(), 1);
		$first = $this->CI->db->where('title LIKE', '[Demo] Lampu penerangan%')->get('tickets')->row();
		$t7t = $this->CI->workflow->perform($t7['ticket'], 'start_verification', array(), $this->staff_actor('pelayanan.demo'));
		$this->CI->workflow->perform($t7t, 'reject', array(
			'reason_code' => 'duplicate',
			'duplicate_of' => $first->public_code,
			'message' => 'Laporan ini merupakan duplikat dari laporan yang sudah diterima sebelumnya dan sedang ditangani. Silakan pantau perkembangan pada laporan pertama.',
		), $this->staff_actor('pelayanan.demo'));

		// 8. Laporan selesai dengan konfirmasi pelapor.
		$t8 = $this->make_ticket($this->base_input(array(
			'report_type' => 'aspiration',
			'category_id' => $this->category('USULAN_KEGIATAN'),
			'title' => '[Demo] Usulan kegiatan kerja bakti rutin bulanan',
			'description' => 'Usulan demo untuk mengadakan kerja bakti rutin setiap bulan agar lingkungan lebih tertata. Data ini dibuat seeder untuk pengujian.',
		)), array(
			'intake_channel' => 'resident_dashboard', 'identity_mode' => 'identified',
			'reporter_user_id' => (int) $this->users['warga2.demo']->id, 'created_by_user_id' => NULL, 'actor_type' => 'resident',
		), 20);
		$t8t = $this->CI->workflow->perform($t8['ticket'], 'start_verification', array(), $this->staff_actor('pelayanan.demo'));
		$t8t = $this->CI->workflow->perform($t8t, 'assign', array(
			'assignee_id' => (int) $this->users['petugas2.demo']->id,
			'unit_id' => $this->id_of('organizational_units', array('code' => 'PELAYANAN')),
		), $this->staff_actor('pelayanan.demo'));
		$t8t = $this->CI->workflow->perform($t8t, 'accept_work', array(), $this->staff_actor('petugas2.demo'));
		$t8t = $this->CI->workflow->perform($t8t, 'propose_resolution', array(
			'message' => 'Usulan sudah dibahas bersama pengurus RT dan disepakati menjadi agenda kerja bakti bulanan. Jadwal akan diumumkan melalui pengumuman desa.',
		), $this->staff_actor('petugas2.demo'));
		$t8t = $this->CI->workflow->perform($t8t, 'accept_result', array(
			'message' => 'Terima kasih, usulan sudah ditindaklanjuti.', 'rating' => 5,
		), $this->reporter_actor('warga2.demo'));

		// 9. Laporan yang dibuka kembali (episode kedua).
		$this->CI->workflow->perform($t8t, 'reopen', array(
			'message' => 'Pelapor menyampaikan bahwa jadwal kerja bakti belum diumumkan, sehingga penanganan dibuka kembali untuk dituntaskan.',
		), $this->staff_actor('koordinator.demo'));

		// 10. Laporan lama yang melewati tenggat verifikasi (indikator overdue).
		$this->make_ticket($this->base_input(array(
			'category_id' => $this->category('LINGKUNGAN'),
			'title' => '[Demo] Tumpukan sampah di tepi jalan belum terangkut',
			'description' => 'Tumpukan sampah di tepi jalan belum terangkut selama beberapa waktu. Laporan demo ini sengaja dibuat lama agar indikator keterlambatan verifikasi dapat diuji.',
			'location_text' => 'Tepi jalan (data demo)',
		)), $this->anon_context(), 14);
	}

	// ------------------------------------------------------------------
	// Profil desa: kontak contoh, lalu verifikasi dan penerbitan
	// ------------------------------------------------------------------

	protected function profile()
	{
		$this->CI->load->library('ProfileService', NULL, 'profile_service');
		$svc = $this->CI->profile_service;
		$actor = $this->actor_id('penerbit.demo');

		// Sambutan, visi, misi, sejarah dan geografi berasal dari dokumen sumber dan sudah
		// diisi seeder master. Hanya kontak yang benar-benar karangan.
		$contact = $svc->block('contact');
		if ($contact && ! $this->has_demo('profile_block'))
		{
			$svc->save_block($contact, array(
				'change_note' => 'Kontak contoh untuk demonstrasi.',
				'office_address' => 'Jalan Raya Cihawuk No. 1, Dusun Cihawuk, Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat 40386',
				'phone_public' => '(022) 5000 1234',
				'email_public' => 'kontak@cihawuk.example.id',
				'service_hours' => array(
					array('label' => 'Senin sampai Kamis', 'value' => '08.00 - 15.00 WIB'),
					array('label' => 'Jumat', 'value' => '08.00 - 11.30 WIB'),
					array('label' => 'Sabtu, Minggu dan hari libur', 'value' => 'Tutup'),
				),
			), $actor);
			$this->track('profile_block', $contact->id, $contact->public_id, 'contact');
			$this->count('profile_block_versions', TRUE);
		}

		// Semua blok yang sudah berisi diverifikasi supaya profil dapat diterbitkan.
		foreach ($svc->blocks() as $block)
		{
			if ($block->current_version_id === NULL)
			{
				continue;
			}
			// Hanya blok berstatus in_review/published yang masuk snapshot, jadi draft
			// harus diajukan lebih dulu; alurnya sama dengan yang dilakukan pengelola.
			if ($block->status === 'draft')
			{
				$svc->submit_review($block, $actor);
				$block = $svc->block($block->block_key);
			}
			if ($block->verification_status !== 'verified')
			{
				$svc->verify_block($block, TRUE, $actor, 'Diverifikasi untuk demonstrasi.');
			}
		}
		foreach ($svc->terms() as $term)
		{
			if ($term->verification_status !== 'verified')
			{
				$svc->verify_term($term, TRUE, $actor);
			}
		}

		$check = $svc->validate_profile();
		if ( ! empty($check['errors']))
		{
			$this->out('  Profil belum dapat diterbitkan: '.implode(' | ', $check['errors']));
			return;
		}
		$svc->publish('Penerbitan untuk demonstrasi kepada pemerintah desa.', $actor);
		$this->out('  Profil desa diterbitkan.');
	}

	// ------------------------------------------------------------------
	// Struktur organisasi
	// ------------------------------------------------------------------

	/**
	 * Lengkapi struktur dari seed master supaya enak diperagakan, lalu terbitkan.
	 *
	 * - Jabatan, penugasan, dan orang ASLI hanya diisi pada kolom yang masih kosong; kolom
	 *   yang diisi dicatat di `demo_records.label` sehingga `purge_demo` dapat
	 *   mengosongkannya lagi tanpa menyentuh kolom yang diisi pengelola.
	 * - Foto memakai avatar ilustrasi (scripts/fetch-demo-avatars.php), bukan potret orang
	 *   sungguhan, karena nama perangkat pada seed master adalah nama asli.
	 * - BPD dan lembaga desa (LPM, PKK, Karang Taruna) adalah entitas demo baru dan dihapus
	 *   seluruhnya saat purge.
	 */
	protected function organization()
	{
		$this->CI->load->library('OrganizationService', NULL, 'org');
		$svc = $this->CI->org;
		$actor = $this->actor_id('penerbit.demo');

		$period = $this->CI->db->get_where('org_periods', array('name' => 'Periode dokumen 2023'))->row();
		if ( ! $period)
		{
			$this->out('  Periode organisasi belum ada; jalankan tools seed lebih dulu.');
			return;
		}

		if ( ! $this->has_demo('org_unit'))
		{
			$this->organization_fill_real($period);
			$this->organization_institutions($period, $actor);
		}

		$check = $svc->validate_period($period);
		if ( ! empty($check['errors']))
		{
			$this->out('  Struktur belum dapat diterbitkan: '.implode(' | ', $check['errors']));
			return;
		}
		$svc->publish($period, 'Penerbitan struktur untuk demonstrasi.', $actor, TRUE);
		$this->out('  Struktur organisasi dilengkapi dan diterbitkan.');
	}

	/** Avatar ilustrasi berikutnya dari satu kelompok ('p' atau 'w'); NULL bila belum diimpor. */
	protected function avatar($pool)
	{
		$this->avatar_next[$pool] = ($this->avatar_next[$pool] ?? 0) + 1;
		return $this->media(sprintf('avatar-%s-%02d.png', $pool, $this->avatar_next[$pool]));
	}

	/** Tulis kolom yang masih kosong saja, lalu catat kolom mana yang diisi demo. */
	protected function fill_empty($table, $type, $row, array $values)
	{
		$filled = array();
		foreach ($values as $column => $value)
		{
			if ($value !== NULL && $column !== 'photo_consent' && ($row->$column === NULL OR $row->$column === ''))
			{
				$filled[$column] = $value;
			}
		}
		// Izin foto hanya ikut dinyalakan bila fotonya memang dipasang oleh demo.
		if (isset($filled['photo_media_id']) && ! empty($values['photo_consent']) && (int) $row->photo_consent === 0)
		{
			$filled['photo_consent'] = 1;
		}
		if (empty($filled))
		{
			return;
		}
		db_must($this->CI->db->where('id', (int) $row->id)->update($table, $filled + array('updated_at' => $this->now())), 'demo '.$table.'.fill');
		$this->track($type, $row->id, NULL, implode(',', array_keys($filled)));
	}

	protected function organization_fill_real($period)
	{
		// Ringkasan tugas umum per jabatan menurut Permendagri 84/2015; bukan uraian resmi desa.
		$duties = array(
			'Kepala Desa' => 'Memimpin penyelenggaraan pemerintahan desa, pembangunan, pembinaan kemasyarakatan, dan pemberdayaan masyarakat desa.',
			'Sekretaris Desa' => 'Membantu kepala desa di bidang administrasi pemerintahan: tata naskah, arsip, keuangan, perencanaan, dan urusan umum.',
			'Kaur Keuangan' => 'Mengelola administrasi keuangan desa: penerimaan, pengeluaran, pembukuan, dan laporan pertanggungjawaban.',
			'Kaur Perencanaan' => 'Menyusun rencana anggaran dan program kerja desa, serta memantau dan mengevaluasi pelaksanaannya.',
			'Kaur Umum' => 'Mengurus tata usaha, arsip, perlengkapan, inventaris aset desa, dan pelayanan umum kantor desa.',
			'Kasi Pemerintahan' => 'Melaksanakan urusan pemerintahan: administrasi kependudukan, pertanahan, ketenteraman, dan ketertiban.',
			'Kasi Pelayanan' => 'Melaksanakan penyuluhan dan motivasi pemenuhan hak warga, serta pelayanan sosial kemasyarakatan.',
			'Kasi Kesejahteraan' => 'Melaksanakan pembangunan sarana-prasarana desa dan pemberdayaan masyarakat di bidang pendidikan, kesehatan, dan ekonomi.',
			'Staf' => 'Membantu pelaksanaan tugas sekretariat desa dan pelayanan administrasi harian.',
			'Staf Kasi' => 'Membantu pelaksanaan tugas seksi, pendataan, dan pelayanan warga di kantor desa.',
		);
		for ($i = 1; $i <= 4; $i++)
		{
			$duties['Kepala Dusun '.$i] = 'Membantu kepala desa di wilayah Dusun '.$i.': pembinaan ketenteraman, pelaksanaan program desa, dan penyerapan aspirasi warga.';
		}
		// Kelompok avatar per nama, hanya supaya ilustrasinya tidak janggal.
		$w = array('Rika Indriani', 'Sylvia Indri Sahada', 'Elsa Safitri');

		$rows = $this->CI->db->select('a.id AS assignment_id, p.id AS position_id, p.title, pe.id AS person_id, pe.full_name')
			->from('org_assignments a')->join('org_positions p', 'p.id = a.position_id')
			->join('people pe', 'pe.id = a.person_id', 'left')
			->where('a.period_id', (int) $period->id)->where('a.status', 'active')
			->order_by('p.sort_order')->get()->result();
		foreach ($rows as $r)
		{
			$this->fill_empty('org_positions', 'org_position_fields',
				$this->CI->db->get_where('org_positions', array('id' => (int) $r->position_id))->row(),
				array('duties_public' => $duties[$r->title] ?? NULL));
			$this->fill_empty('org_assignments', 'org_assignment_fields',
				$this->CI->db->get_where('org_assignments', array('id' => (int) $r->assignment_id))->row(),
				array('start_date' => $r->title === 'Kepala Desa' ? '2019-11-12' : '2020-01-06'));
			if ($r->person_id)
			{
				$this->fill_empty('people', 'person_fields',
					$this->CI->db->get_where('people', array('id' => (int) $r->person_id))->row(),
					array(
						'photo_media_id' => $this->avatar(in_array($r->full_name, $w, TRUE) ? 'w' : 'p'),
						'photo_consent' => 1,
						'bio_public' => 'Menjabat sebagai '.$r->title.' Desa Cihawuk. Profil ini contoh untuk peragaan dan belum diverifikasi.',
					));
			}
		}
	}

	/** BPD dan lembaga desa: unit, jabatan, orang, dan penugasan demo. */
	protected function organization_institutions($period, $actor)
	{
		$svc = $this->CI->org;
		/*
		| Susunan BPD mengikuti isu data BPD_TERM_LABEL (docs/data-issues.md): ketua,
		| wakil, dan sekretaris disebut pada S3 tetapi belum dikonfirmasi; empat anggota
		| tidak bernama di sumber, jadi namanya karangan. Lembaga lain seluruhnya karangan.
		| Kolom: jabatan, jabatan atasan (indeks baris), nama, kelompok avatar.
		*/
		$institutions = array(
			array('Badan Permusyawaratan Desa', 'bpd', 20, 'Menyalurkan aspirasi warga, membahas dan menyepakati rancangan peraturan desa, serta mengawasi kinerja kepala desa.', array(
				array('Ketua BPD', NULL, 'Eneng Santi Fatmawati', 'w'),
				array('Wakil Ketua BPD', 0, 'Anjar Fauji', 'p'),
				array('Sekretaris BPD', 0, 'Budi Kusnadi', 'p'),
				array('Anggota BPD', 0, 'Dede Rohman', 'p'),
				array('Anggota BPD', 0, 'Nenden Sumiati', 'w'),
				array('Anggota BPD', 0, 'Asep Saepuloh', 'p'),
				array('Anggota BPD', 0, 'Iis Rosita', 'w'),
			)),
			array('Lembaga Pemberdayaan Masyarakat (LPM)', 'institution', 30, 'Menyusun rencana pembangunan partisipatif dan menggerakkan swadaya gotong royong warga.', array(
				array('Ketua LPM', NULL, 'Ade Mulyana', 'p'),
				array('Sekretaris LPM', 0, 'Rudi Hermawan', 'p'),
				array('Bendahara LPM', 0, 'Yeni Marlina', 'w'),
			)),
			array('Tim Penggerak PKK', 'institution', 40, 'Menggerakkan program kesejahteraan keluarga: kesehatan, gizi, pendidikan keluarga, dan ekonomi rumah tangga.', array(
				array('Ketua TP PKK', NULL, 'Euis Komariah', 'w'),
				array('Sekretaris TP PKK', 0, 'Rina Nurlaela', 'w'),
				array('Bendahara TP PKK', 0, 'Siti Aminah', 'w'),
			)),
			array('Karang Taruna', 'institution', 50, 'Wadah pengembangan generasi muda: kegiatan sosial, olahraga, seni, dan kewirausahaan pemuda.', array(
				array('Ketua Karang Taruna', NULL, 'Rizki Firmansyah', 'p'),
				array('Sekretaris Karang Taruna', 0, 'Dini Apriliani', 'w'),
				array('Bendahara Karang Taruna', 0, 'Fajar Nugraha', 'p'),
			)),
		);

		foreach ($institutions as $inst)
		{
			list($unit_name, $unit_type, $sort, $mandate, $members) = $inst;
			$unit = $svc->save_unit($period, array('name' => $unit_name, 'unit_type' => $unit_type, 'sort_order' => $sort, 'active' => 1), $actor);
			$this->track('org_unit', $unit->id, $unit->public_id, $unit_name);

			$ids = array();
			foreach ($members as $i => $m)
			{
				list($title, $parent_index, $name, $pool) = $m;
				$position = $svc->save_position($period, array(
					'title' => $title,
					'unit_id' => $unit->id,
					'parent_id' => $parent_index === NULL ? NULL : $ids[$parent_index],
					'duties_public' => $parent_index === NULL ? $mandate : $this->member_duty($title),
					'sort_order' => $sort * 10 + $i,
					'active' => 1,
				), $actor);
				$ids[$i] = (int) $position->id;
				$this->track('org_position', $position->id, $position->public_id, $title);

				$person = $svc->save_person(array(
					'full_name' => $name,
					'photo_media_id' => $this->avatar($pool),
					'photo_consent' => 1,
					'bio_public' => $title.' periode 2019–2027. Profil ini contoh untuk peragaan dan belum diverifikasi.',
					'data_status' => 'draft',
				), $actor);
				$this->track('person', $person->id, $person->public_id, $name);

				$assignment = $svc->save_assignment($period, array(
					'position_id' => $position->id,
					'person_id' => $person->id,
					'assignment_type' => 'definitive',
					'start_date' => '2019-12-02',
					'end_date' => '2027-12-01',
				), $actor);
				$this->track('org_assignment', $assignment->id, $assignment->public_id, $title);
			}
		}
	}

	protected function member_duty($title)
	{
		if (strpos($title, 'Wakil') === 0) { return 'Membantu ketua dan memimpin rapat ketika ketua berhalangan.'; }
		if (strpos($title, 'Sekretaris') === 0) { return 'Mengelola administrasi, notulen rapat, dan surat-menyurat lembaga.'; }
		if (strpos($title, 'Bendahara') === 0) { return 'Mengelola keuangan dan laporan pertanggungjawaban lembaga.'; }
		return 'Menyerap aspirasi warga di wilayahnya dan ikut membahas rancangan peraturan desa.';
	}

	/**
	 * Hapus struktur demo dan kosongkan lagi kolom yang diisi demo pada data asli.
	 *
	 * Urutan: penugasan, jabatan (dari daun, karena parent_id RESTRICT), orang, unit.
	 * Media avatar tetap di pustaka seperti foto demo lain; hanya rujukannya yang dilepas.
	 */
	protected function purge_organization()
	{
		$removed = array();
		$assignments = $this->tracked('org_assignment');
		if ( ! empty($assignments))
		{
			$this->CI->db->where_in('id', $assignments)->delete('org_assignments');
			$removed['org_assignment'] = count($assignments);
		}
		$positions = $this->tracked('org_position');
		$left = $positions;
		while ( ! empty($left))
		{
			$parents = array();
			foreach ($this->CI->db->select('parent_id')->where_in('parent_id', $left)->get('org_positions')->result() as $row)
			{
				$parents[(int) $row->parent_id] = TRUE;
			}
			$leaves = array_values(array_filter($left, function ($id) use ($parents) { return ! isset($parents[$id]); }));
			if (empty($leaves))
			{
				break;
			}
			$this->CI->db->where_in('position_id', $leaves)->delete('org_assignments');
			$this->CI->db->where_in('id', $leaves)->delete('org_positions');
			$left = array_values(array_diff($left, $leaves));
		}
		if ( ! empty($positions))
		{
			$removed['org_position'] = count($positions);
		}
		$people = $this->tracked('person');
		if ( ! empty($people))
		{
			$this->CI->db->where_in('person_id', $people)->delete('org_assignments');
			$this->CI->db->where_in('id', $people)->delete('people');
			$removed['person'] = count($people);
		}
		$units = $this->tracked('org_unit');
		if ( ! empty($units))
		{
			$this->CI->db->where_in('unit_id', $units)->update('org_positions', array('unit_id' => NULL));
			$this->CI->db->where_in('id', $units)->delete('org_units');
			$removed['org_unit'] = count($units);
		}

		$tables = array('person_fields' => 'people', 'org_position_fields' => 'org_positions', 'org_assignment_fields' => 'org_assignments');
		foreach ($tables as $type => $table)
		{
			foreach ($this->CI->db->where('entity_type', $type)->get('demo_records')->result() as $record)
			{
				$reset = array();
				foreach (array_filter(explode(',', (string) $record->label)) as $column)
				{
					$reset[$column] = ($column === 'photo_consent') ? 0 : NULL;
				}
				if ( ! empty($reset))
				{
					$this->CI->db->where('id', (int) $record->entity_id)->update($table, $reset);
				}
			}
		}
		foreach (array_merge(array('org_assignment', 'org_position', 'person', 'org_unit'), array_keys($tables)) as $type)
		{
			$this->CI->db->where('entity_type', $type)->delete('demo_records');
		}
		return $removed;
	}

	// ------------------------------------------------------------------
	// Direktori UMKM
	// ------------------------------------------------------------------

	protected function businesses()
	{
		if ($this->has_demo('business'))
		{
			$this->out('  UMKM demo sudah ada; dilewati.');
			return;
		}
		$this->CI->load->library('BusinessService', NULL, 'business_service');
		$svc = $this->CI->business_service;
		$actor = $this->actor_id('editor.demo');

		// Persetujuan pemilik wajib dapat ditelusuri. Untuk data contoh, catatannya
		// menyatakan terang-terangan bahwa usahanya tidak nyata, supaya tidak ada yang
		// mengira ada pemilik sungguhan yang pernah dimintai izin.
		$consent = 'DATA CONTOH DEMONSTRASI. Usaha ini tidak nyata dan tidak ada pemilik yang dimintai persetujuan. '
			.'Ganti seluruh entri ini dengan data usaha sungguhan beserta bukti persetujuan tertulis sebelum dipakai.';

		$rows = array(
			array('Keripik Kentang Bu Icih', 'food', 'Bu Icih', 'Keripik kentang dan singkong', 'Keripik kentang, keripik singkong, rengginang', 'Dusun Cihawuk RT 02 RW 01', 'sayuran.jpg'),
			array('Kopi Bubuk Cihawuk Jaya', 'food', 'Pak Dadang', 'Pengolahan kopi bubuk rakyat', 'Kopi bubuk arabika, kopi robusta', 'Dusun Puncakmulya RT 01 RW 04', 'kopi.jpg'),
			array('Teh Rakyat Pucuk Hijau', 'agriculture', 'Kelompok Tani Pucuk Hijau', 'Pengolahan daun teh rakyat', 'Teh kering, teh celup', 'Dusun Ciakar RT 03 RW 02', 'teh-produk.jpg'),
			array('Anyaman Bambu Ciakar', 'craft', 'Pak Endang', 'Kerajinan anyaman bambu', 'Boboko, aseupan, besek, tampah', 'Dusun Ciakar RT 01 RW 03', 'kerajinan-bambu.jpg'),
			array('Sayur Segar Puncakmulya', 'agriculture', 'Kelompok Wanita Tani Puncakmulya', 'Pengepul sayuran dataran tinggi', 'Kentang, kubis, wortel, tomat, cabai', 'Dusun Puncakmulya RT 03 RW 05', 'sayuran.jpg'),
			array('Warung Nasi Barokah', 'food', 'Bu Eneng', 'Warung nasi harian', 'Nasi campur, lalapan, minuman hangat', 'Jalan Raya Cihawuk No. 33', 'warung.jpg'),
			array('Stroberi Kebun Pinggirsari', 'agriculture', 'Pak Asep', 'Kebun stroberi petik sendiri', 'Stroberi segar, selai stroberi', 'Dusun Pinggirsari RT 02 RW 06', 'stroberi.jpg'),
			array('Bengkel Motor Saluyu', 'service', 'Pak Ujang', 'Servis dan suku cadang sepeda motor', 'Servis ringan, ganti oli, tambal ban', 'Jalan Raya Cihawuk No. 57', NULL),
			array('Toko Kelontong Rukun Tani', 'retail', 'Bu Wati', 'Toko kebutuhan harian', 'Sembako, pupuk, alat pertanian ringan', 'Dusun Cihawuk RT 04 RW 01', 'pasar.jpg'),
			array('Susu Sapi Segar Lembah Hijau', 'agriculture', 'Kelompok Peternak Lembah Hijau', 'Penjualan susu sapi segar', 'Susu segar, yoghurt sederhana', 'Dusun Puncakmulya RT 02 RW 04', 'ternak-sapi.jpg'),
		);

		foreach ($rows as $r)
		{
			list($name, $cat, $owner, $desc, $products, $location, $photo) = $r;
			$business = $svc->save(array(
				'name' => $name,
				'slug' => url_title(mb_strtolower($name), '-', TRUE),
				'category' => $cat,
				'owner_name' => $owner,
				'description' => $desc.'. Entri contoh untuk memperagakan direktori UMKM.',
				'products' => $products,
				'public_location' => $location,
				'public_contact' => '',
				'opening_hours' => '08.00 - 17.00 WIB',
				'photo_media_id' => $photo ? $this->media($photo) : NULL,
				'owner_consent' => 1,
				'consent_note' => $consent,
				'is_active' => 1,
			), $actor);
			$svc->publish($business, $actor);
			$this->track('business', $business->id, $business->public_id, $name);
			$this->count('businesses', TRUE);
		}
	}

	// ------------------------------------------------------------------
	// Potensi desa
	// ------------------------------------------------------------------

	protected function potentials()
	{
		if ($this->has_demo('potential'))
		{
			$this->out('  Potensi demo sudah ada; dilewati.');
			return;
		}
		$this->CI->load->library('ContentService', NULL, 'content_service');
		$svc = $this->CI->content_service;
		$actor = $this->actor_id('penerbit.demo');
		$category = $this->id_of('content_categories', array('content_type' => 'potential', 'slug' => 'pertanian'));
		$tail = '<p>Uraian ini adalah contoh demonstrasi, bukan hasil pendataan resmi.</p>';

		$rows = array(
			array('Perkebunan teh rakyat', 'kebun-teh.jpg', 'open', 'Kelompok Tani Pucuk Hijau',
				'Hamparan kebun teh rakyat di lereng utara desa.',
				'<p>Kebun teh rakyat dikelola kelompok tani dan menjadi salah satu sumber penghasilan utama warga. Sebagian hasil dijual ke pengepul, sebagian diolah menjadi teh kering oleh UMKM desa.</p>'),
			array('Hortikultura dataran tinggi', 'sayuran.jpg', 'open', 'Kelompok Wanita Tani Puncakmulya',
				'Kentang, kubis, wortel, tomat dan cabai dari lahan dataran tinggi.',
				'<p>Ketinggian dan suhu harian mendukung komoditas hortikultura. Panen berlangsung bergiliran sepanjang tahun sehingga pasokan relatif stabil.</p>'),
			array('Kopi rakyat Cihawuk', 'kopi.jpg', 'limited', 'Pak Dadang',
				'Kopi arabika dan robusta yang diolah warga menjadi kopi bubuk.',
				'<p>Kopi ditanam di sela tanaman keras dan diolah dalam skala rumah tangga. Kunjungan ke tempat pengolahan perlu perjanjian lebih dulu.</p>'),
			array('Peternakan sapi perah', 'ternak-sapi.jpg', 'limited', 'Kelompok Peternak Lembah Hijau',
				'Susu segar dari peternakan rakyat di Dusun Puncakmulya.',
				'<p>Peternakan rakyat memasok susu segar ke koperasi dan warung sekitar. Kunjungan kandang dibatasi untuk menjaga kesehatan ternak.</p>'),
			array('Wisata alam dan air panas', 'air-panas.jpg', 'limited', 'Karang Taruna Desa Cihawuk',
				'Lanskap pegunungan dan sumber air panas di sekitar desa.',
				'<p>Pemandangan pegunungan dan sumber air panas berpotensi dikembangkan sebagai wisata alam terbatas. Belum ada pengelolaan resmi maupun retribusi.</p>'),
		);

		foreach ($rows as $i => $r)
		{
			list($title, $photo, $access, $manager, $summary, $body) = $r;
			$id = $svc->save('potensi', NULL, array(
				'category_id' => $category,
				'title' => $title,
				'slug' => url_title(mb_strtolower($title), '-', TRUE),
				'summary' => $summary,
				'body_html' => $body.$tail,
				'cover_media_id' => $this->media($photo),
				'access_status' => $access,
				'manager_name' => $manager,
				'safety_note' => 'Jalur menuju lokasi menanjak dan licin saat hujan.',
				'public_contact' => '',
				'contact_permission' => 0,
				'source_year' => 2023,
				'verification_status' => 'verified',
				'sort_order' => ($i + 1) * 10,
			), $actor);
			$svc->set_status('potensi', $id, 'published', $actor);
			$this->track('potential', $id, NULL, $title);
			$this->count('potentials', TRUE);
		}
	}

	// ------------------------------------------------------------------
	// Transparansi anggaran
	// ------------------------------------------------------------------

	/**
	 * Susunan APBDes contoh.
	 *
	 * Angka daun ditulis tetap; nilai induk dan pembiayaan keluar DIHITUNG dari angka daun
	 * itu, supaya identitas (pendapatan - belanja) + (pembiayaan masuk - pembiayaan keluar)
	 * benar-benar nol dan induk selalu sama dengan jumlah anaknya. Dengan begitu data contoh
	 * lolos rekonsiliasi karena memang seimbang, bukan karena pemeriksaannya dilewati.
	 */
	protected function budget_plan($scale, $type)
	{
		$amended = in_array($type, array('amended', 'realization'), TRUE);
		$realization = ($type === 'realization');
		$r = function ($n) use ($scale) { return round($n * $scale / 1000000) * 1000000; };

		$income = array(
			array('Dana Desa', 'PEN.01', $r(1100000000)),
			array('Alokasi Dana Desa', 'PEN.02', $r(620000000)),
			array('Bagi hasil pajak dan retribusi daerah', 'PEN.03', $r(95000000)),
			array('Bantuan keuangan provinsi', 'PEN.04', $r($realization ? 215000000 : ($amended ? 230000000 : 130000000))),
			array('Pendapatan asli desa', 'PEN.05', $r(55000000)),
		);

		$expenditure = array(
			array('Penyelenggaraan Pemerintahan Desa', 'BID.01', array(
				array('Penghasilan tetap dan tunjangan', 'BID.01.01', $r(380000000)),
				array('Operasional pemerintah desa', 'BID.01.02', $r($realization ? 110000000 : 120000000)),
				array('Operasional BPD', 'BID.01.03', $r(45000000)),
				array('Tunjangan dan operasional RT dan RW', 'BID.01.04', $r(75000000)),
			)),
			array('Pelaksanaan Pembangunan Desa', 'BID.02', array(
				array('Perbaikan jalan lingkungan', 'BID.02.01', $r($realization ? 380000000 : ($amended ? 420000000 : 350000000))),
				array('Sarana air bersih', 'BID.02.02', $r($realization ? 245000000 : ($amended ? 240000000 : 210000000))),
				array('Rehabilitasi gedung posyandu', 'BID.02.03', $r(120000000)),
				array('Drainase dan talud', 'BID.02.04', $r($realization ? 170000000 : 200000000)),
			)),
			array('Pembinaan Kemasyarakatan', 'BID.03', array(
				array('Pembinaan keamanan dan ketertiban', 'BID.03.01', $r(55000000)),
				array('Pembinaan kepemudaan dan olahraga', 'BID.03.02', $r(60000000)),
				array('Pembinaan lembaga adat dan keagamaan', 'BID.03.03', $r(40000000)),
			)),
			array('Pemberdayaan Masyarakat', 'BID.04', array(
				array('Pelatihan kelompok tani', 'BID.04.01', $r(95000000)),
				array('Pelatihan UMKM dan pemasaran', 'BID.04.02', $r(80000000)),
				array('Peningkatan kapasitas aparatur desa', 'BID.04.03', $r(60000000)),
				array('Dukungan Posyandu dan kesehatan', 'BID.04.04', $r(60000000)),
			)),
			array('Penanggulangan Bencana, Keadaan Darurat dan Mendesak', 'BID.05', array(
				array('Tanggap darurat bencana', 'BID.05.01', $r($realization ? 85000000 : 60000000)),
				// Realisasi di bawah pagu tidak perlu penjelasan; hanya kelebihan yang wajib.
				array('Bantuan keadaan mendesak', 'BID.05.02', $r(40000000)),
			)),
		);

		$income_total = 0;
		foreach ($income as $row) { $income_total += $row[2]; }
		$expenditure_total = 0;
		foreach ($expenditure as $bidang)
		{
			foreach ($bidang[2] as $child) { $expenditure_total += $child[2]; }
		}
		$financing_in = $r(70000000);
		// Pembiayaan keluar menutup selisihnya; inilah yang membuat identitas benar-benar nol.
		$financing_out = $financing_in + $income_total - $expenditure_total;
		if ($financing_out < 0)
		{
			// Lebih baik gagal terang-terangan daripada menghasilkan APBDes contoh yang
			// tidak mungkin ditutup; angkanya harus diperbaiki di tabel di atas.
			throw new RuntimeException(sprintf(
				'Rencana anggaran contoh (%s) tidak dapat ditutup: belanja %s melebihi pendapatan %s ditambah pembiayaan masuk %s.',
				$type, number_format($expenditure_total, 0, ',', '.'), number_format($income_total, 0, ',', '.'),
				number_format($financing_in, 0, ',', '.')));
		}

		return array(
			'income' => $income,
			'expenditure' => $expenditure,
			'financing_in' => $financing_in,
			'financing_out' => $financing_out,
		);
	}

	protected function budget()
	{
		if ($this->has_demo('budget_year'))
		{
			$this->out('  Anggaran demo sudah ada; dilewati.');
			return;
		}
		$this->CI->load->library('BudgetService', NULL, 'budget_service');
		$svc = $this->CI->budget_service;
		$manager = $this->actor_id('keuangan.demo');
		$verifier = $this->actor_id('auditkeuangan.demo');
		$publisher = $this->actor_id('penerbit.demo');

		// 2024 dan 2025 lengkap sampai realisasi; 2026 masih berjalan sehingga hanya murni.
		$years = array(
			2024 => array('scale' => 1.00, 'revisions' => array('original', 'amended', 'realization')),
			2025 => array('scale' => 1.08, 'revisions' => array('original', 'amended', 'realization')),
			2026 => array('scale' => 1.15, 'revisions' => array('original')),
		);
		$labels = array(
			'original' => 'APBDes Murni',
			'amended' => 'APBDes Perubahan',
			'realization' => 'Laporan Realisasi',
		);
		$variance = 'Melebihi pagu perubahan karena pekerjaan tambahan yang disetujui dalam musyawarah desa. Data contoh demonstrasi.';

		foreach ($years as $fiscal => $spec)
		{
			$year = $svc->create_year(array(
				'fiscal_year' => $fiscal,
				'note' => 'Angka contoh demonstrasi. Bukan APBDes resmi Desa Cihawuk.',
			), $manager);
			$this->track('budget_year', $year->id, $year->public_id, 'APBDes '.$fiscal);
			$this->count('budget_years', TRUE);

			// Kategori dibuat sekali per tahun dan dipakai ulang oleh semua revisi.
			$plan = $this->budget_plan($spec['scale'], 'original');
			$cats = array();
			$order = 0;
			foreach ($plan['income'] as $row)
			{
				$order += 10;
				$c = $svc->save_category($year, array('name' => $row[0], 'section' => 'income', 'code' => $row[1], 'sort_order' => $order), $manager);
				$cats['income:'.$row[1]] = $c;
			}
			foreach ($plan['expenditure'] as $bidang)
			{
				$order += 10;
				$parent = $svc->save_category($year, array('name' => $bidang[0], 'section' => 'expenditure', 'code' => $bidang[1], 'sort_order' => $order), $manager);
				$cats['expenditure:'.$bidang[1]] = $parent;
				foreach ($bidang[2] as $child)
				{
					$order += 1;
					$c = $svc->save_category($year, array('name' => $child[0], 'section' => 'expenditure', 'code' => $child[1],
						'parent_id' => (int) $parent->id, 'sort_order' => $order), $manager);
					$cats['expenditure:'.$child[1]] = $c;
				}
			}
			$fin_in = $svc->save_category($year, array('name' => 'Sisa lebih perhitungan anggaran tahun sebelumnya', 'section' => 'financing_in', 'code' => 'BIA.01', 'sort_order' => 900), $manager);
			$fin_out = $svc->save_category($year, array('name' => 'Penyertaan modal BUMDes', 'section' => 'financing_out', 'code' => 'BIA.02', 'sort_order' => 910), $manager);

			// Dasar pembanding realisasi adalah APBDes Perubahan bila ada.
			$baseline = array();
			if (in_array('realization', $spec['revisions'], TRUE))
			{
				$base_plan = $this->budget_plan($spec['scale'], in_array('amended', $spec['revisions'], TRUE) ? 'amended' : 'original');
				foreach ($base_plan['income'] as $row) { $baseline[$row[1]] = $row[2]; }
				foreach ($base_plan['expenditure'] as $bidang)
				{
					$sum = 0;
					foreach ($bidang[2] as $child) { $baseline[$child[1]] = $child[2]; $sum += $child[2]; }
					$baseline[$bidang[1]] = $sum;
				}
				$baseline['BIA.01'] = $base_plan['financing_in'];
				$baseline['BIA.02'] = $base_plan['financing_out'];
			}
			// Penjelasan hanya ditempelkan pada baris yang benar-benar melampaui pagu.
			$note_for = function ($code, $amount) use (&$baseline, $variance) {
				return (isset($baseline[$code]) && $amount > $baseline[$code] + 0.005) ? $variance : '';
			};

			foreach ($spec['revisions'] as $type)
			{
				$is_real = ($type === 'realization');
				$plan = $this->budget_plan($spec['scale'], $type);
				$revision = $svc->save_revision($year, array(
					'revision_type' => $type,
					'label' => $labels[$type].' '.$fiscal,
					'document_year' => $fiscal,
					'document_note' => 'Dokumen contoh demonstrasi.',
				), $manager);

				foreach ($plan['income'] as $row)
				{
					$svc->save_line($year, $revision, array('category_public_id' => $cats['income:'.$row[1]]->public_id,
						'amount' => $row[2], 'variance_note' => $is_real ? $note_for($row[1], $row[2]) : ''), $manager);
				}
				foreach ($plan['expenditure'] as $bidang)
				{
					$sum = 0;
					foreach ($bidang[2] as $child) { $sum += $child[2]; }
					// Baris induk sengaja sama persis dengan jumlah anaknya; total tetap
					// dihitung dari baris daun saja sehingga tidak terhitung dua kali.
					$svc->save_line($year, $revision, array('category_public_id' => $cats['expenditure:'.$bidang[1]]->public_id,
						'amount' => $sum, 'variance_note' => $is_real ? $note_for($bidang[1], $sum) : ''), $manager);
					foreach ($bidang[2] as $child)
					{
						$svc->save_line($year, $revision, array(
							'category_public_id' => $cats['expenditure:'.$child[1]]->public_id,
							'amount' => $child[2],
							'variance_note' => $is_real ? $note_for($child[1], $child[2]) : '',
						), $manager);
					}
				}
				$svc->save_line($year, $revision, array('category_public_id' => $fin_in->public_id,
					'amount' => $plan['financing_in'], 'variance_note' => $is_real ? $note_for('BIA.01', $plan['financing_in']) : ''), $manager);
				$svc->save_line($year, $revision, array('category_public_id' => $fin_out->public_id,
					'amount' => $plan['financing_out'], 'variance_note' => $is_real ? $note_for('BIA.02', $plan['financing_out']) : ''), $manager);
				$this->count('budget_revisions', TRUE);
			}

			$year = $svc->year($year->public_id);
			$check = $svc->validate_year($year);
			if ( ! empty($check['errors']))
			{
				$this->out('  APBDes '.$fiscal.' belum lolos: '.implode(' | ', $check['errors']));
				continue;
			}
			$svc->transition($year, 'rekonsiliasi', $manager, 'Rekonsiliasi data contoh.');
			$svc->transition($svc->year($year->public_id), 'verifikasi', $verifier, 'Verifikasi data contoh.');
			$svc->transition($svc->year($year->public_id), 'setujui', $publisher, 'Persetujuan data contoh.');
			$svc->publish($svc->year($year->public_id), 'Penerbitan untuk demonstrasi.', $publisher);
			$this->out('  APBDes '.$fiscal.' diterbitkan.');
		}
	}

	// ------------------------------------------------------------------
	// Aset desa, unit fisik dan QR publik
	// ------------------------------------------------------------------

	protected function assets()
	{
		$this->CI->load->library('AssetService', NULL, 'assets');
		if ($this->has_demo('asset_register'))
		{
			$this->out('  Aset demo sudah ada; dilewati.');
			$out = array();
			foreach ($this->CI->db->select('u.public_id')->from('asset_units u')
				->join('demo_records d', "d.entity_type = 'asset_register' AND d.entity_id = u.register_id")
				->order_by('u.id')->get()->result() as $row)
			{
				$out[] = $this->CI->assets->unit($row->public_id);
			}
			return $out;
		}
		$svc = $this->CI->assets;
		$actor = $this->actor_id('aset.demo');
		$cat = function ($code) { return $this->id_of('asset_categories', array('code' => $code)); };

		$locations = array(
			array('BALAI-DESA', 'Balai Desa Cihawuk', 'building', 0),
			array('RUANG-KADES', 'Ruang Kepala Desa', 'room', 0),
			array('RUANG-PELAYANAN', 'Ruang Pelayanan', 'room', 0),
			array('GUDANG-DESA', 'Gudang Desa', 'room', 0),
			array('POSYANDU-MAWAR', 'Posyandu Mawar Ciakar', 'building', 0),
			array('LAPANGAN-DESA', 'Lapangan Serbaguna', 'field', 0),
			array('BRANKAS-DESA', 'Brankas arsip', 'room', 1),
		);
		$loc = array();
		foreach ($locations as $l)
		{
			$row = $svc->save_location(array(
				'code' => $l[0], 'name' => $l[1], 'location_type' => $l[2],
				'parent_id' => NULL, 'address' => NULL, 'is_sensitive' => $l[3], 'active' => 1,
			));
			$loc[$l[0]] = (int) (is_object($row) ? $row->id : $this->id_of('asset_locations', array('code' => $l[0])));
			$this->track('asset_location', $loc[$l[0]], NULL, $l[1]);
		}

		/* nama, kategori, tahun, nilai, sumber dana, jumlah unit, lokasi, merek */
		$rows = array(
			array('Komputer desktop pelayanan', 'PERALATAN', 2022, 9500000, 'Dana Desa', 3, 'RUANG-PELAYANAN', 'Contoh'),
			array('Laptop kerja perangkat desa', 'PERALATAN', 2023, 11200000, 'Dana Desa', 4, 'RUANG-KADES', 'Contoh'),
			array('Printer multifungsi', 'PERALATAN', 2023, 3400000, 'Alokasi Dana Desa', 2, 'RUANG-PELAYANAN', 'Contoh'),
			array('Meja kerja kayu', 'PERALATAN', 2021, 1250000, 'Alokasi Dana Desa', 8, 'BALAI-DESA', NULL),
			array('Kursi kerja putar', 'PERALATAN', 2021, 850000, 'Alokasi Dana Desa', 10, 'BALAI-DESA', NULL),
			array('Lemari arsip besi', 'PERALATAN', 2020, 2600000, 'Alokasi Dana Desa', 4, 'BRANKAS-DESA', NULL),
			array('Genset portabel', 'PERALATAN', 2022, 7800000, 'Dana Desa', 1, 'GUDANG-DESA', 'Contoh'),
			array('Proyektor dan layar', 'PERALATAN', 2023, 6200000, 'Dana Desa', 1, 'BALAI-DESA', 'Contoh'),
			array('Sound system balai desa', 'PERALATAN', 2022, 5400000, 'Dana Desa', 1, 'BALAI-DESA', NULL),
			array('Sepeda motor operasional', 'PERALATAN', 2021, 18500000, 'Dana Desa', 2, 'BALAI-DESA', 'Contoh'),
			array('Timbangan bayi digital', 'PERALATAN', 2023, 1150000, 'Bantuan Provinsi', 4, 'POSYANDU-MAWAR', NULL),
			array('Tenda kegiatan desa', 'ASET-LAIN', 2022, 4200000, 'Dana Desa', 6, 'GUDANG-DESA', NULL),
			array('Gedung Balai Desa Cihawuk', 'GEDUNG', 2018, 685000000, 'Dana Desa', 1, 'BALAI-DESA', NULL),
			array('Gedung Posyandu Mawar', 'GEDUNG', 2020, 145000000, 'Dana Desa', 1, 'POSYANDU-MAWAR', NULL),
			array('Tanah kantor desa', 'TANAH', 2015, 420000000, 'Hibah', 1, 'BALAI-DESA', NULL),
			array('Jalan lingkungan beton Dusun Ciakar', 'JIJ', 2023, 310000000, 'Dana Desa', 1, 'LAPANGAN-DESA', NULL),
			array('Saluran drainase Dusun Cihawuk', 'JIJ', 2022, 185000000, 'Dana Desa', 1, 'LAPANGAN-DESA', NULL),
		);

		$units = array();
		foreach ($rows as $i => $r)
		{
			list($name, $code, $year, $value, $fund, $count, $location, $brand) = $r;
			$register = $svc->save_register(array(
				'name' => $name,
				'category_id' => $cat($code),
				'description' => 'Register contoh demonstrasi.',
				'legacy_asset_code' => sprintf('CTH-%03d', $i + 1),
				'source_volume_raw' => $count.' unit',
				'acquisition_year' => $year,
				'acquisition_value' => $value,
				'acquisition_source' => $fund,
				'ownership_status' => 'owned',
				'source_locator' => 'Data contoh, bukan dokumen aset resmi.',
			), $actor);
			$svc->verify_register($register, TRUE, $actor);
			$this->track('asset_register', $register->id, $register->public_id, $name);
			$this->count('asset_registers', TRUE);

			for ($n = 1; $n <= $count; $n++)
			{
				$unit = $svc->create_unit($register, array(
					'asset_tag' => sprintf('CHW-%03d-%02d', $i + 1, $n),
					'unit_sequence' => $n,
					'serial_number' => $brand ? sprintf('SN-CONTOH-%03d%02d', $i + 1, $n) : NULL,
					'brand' => $brand,
					'model' => $brand ? 'Model contoh' : NULL,
					'location_id' => $loc[$location],
					'custodian_unit_id' => NULL,
					'custodian_user_id' => NULL,
					'public_note' => 'Data contoh demonstrasi.',
				), $actor);
				$units[] = $unit;
				$this->count('asset_units', TRUE);
			}
		}
		$this->out('  Aset: '.count($rows).' register, '.count($units).' unit.');
		return $units;
	}

	/** Riwayat aset: mutasi, peminjaman, pemeliharaan, token QR dan batch label. */
	protected function asset_lifecycle(array $units)
	{
		if (empty($units))
		{
			return;
		}
		if ($this->has_demo('asset_lifecycle'))
		{
			return;
		}
		$svc = $this->CI->assets;
		$actor = $this->actor_id('aset.demo');
		$gudang = $this->id_of('asset_locations', array('code' => 'GUDANG-DESA'));
		$balai = $this->id_of('asset_locations', array('code' => 'BALAI-DESA'));

		// Satu unit dipindahkan lewat dua langkah: permintaan lalu penerimaan.
		$move = $svc->request_movement($units[0], array(
			'to_location_id' => $gudang, 'to_custodian_unit_id' => NULL,
			'reason' => 'Dipindahkan sementara ke gudang selama ruang pelayanan dicat ulang.',
		), $actor);
		$svc->accept_movement($move, $actor);

		// Satu unit dipinjam lalu dikembalikan.
		if (isset($units[3]))
		{
			$loan = $svc->checkout($units[3], array(
				'borrower_name' => 'Panitia kegiatan desa',
				'borrower_unit' => 'Karang Taruna Desa Cihawuk',
				'purpose' => 'Dipakai untuk kegiatan musyawarah desa.',
				'checkout_condition' => 'good',
				'due_at' => gmdate('Y-m-d', time() + 7 * 86400),
			), $actor);
			$svc->return_loan($loan, array('return_condition' => 'good'), $actor);
		}

		// Satu unit masuk pemeliharaan dan selesai.
		if (isset($units[6]))
		{
			$maint = $svc->create_maintenance($units[6], array(
				'complaint' => 'Servis rutin genset dan penggantian oli.',
				'vendor' => 'Bengkel contoh',
				'planned_at' => gmdate('Y-m-d', time() - 14 * 86400),
			), $actor);
			$svc->complete_maintenance($maint, array(
				'action_taken' => 'Servis selesai, oli diganti dan genset berfungsi normal.',
				'condition_after' => 'good',
				'cost' => 450000,
			), $actor);
		}

		// Satu unit dinonaktifkan supaya halaman QU menampilkan status selain aktif.
		if (isset($units[5]))
		{
			$svc->change_status($units[5], array(
				'to_lifecycle' => 'inactive',
				'to_condition' => 'major_damage',
				'reason' => 'Lemari arsip rusak berat dan menunggu usulan penghapusan.',
			), $actor);
		}

		// Token QR untuk sebagian unit; token asli hanya ada saat diterbitkan.
		$with_token = array();
		foreach (array_slice($units, 0, 20) as $unit)
		{
			$svc->issue_token($unit, $actor, 'Penerbitan token untuk demonstrasi.');
			$with_token[] = $unit->public_id;
		}
		$batch = $svc->create_label_batch(array_slice($with_token, 0, 12), array(
			'label_size' => 'small',
			'copies' => 1,
			'start_offset' => 0,
			'reason' => 'Cetak label contoh untuk demonstrasi.',
		), $actor);
		$svc->mark_batch_printed($batch, $actor);

		// Satu token dicabut setelah label dicetak, supaya keadaan "tidak berlaku" dapat
		// diperagakan. Batch label menolak unit yang tidak punya token aktif, jadi urutannya
		// memang harus sesudah pencetakan.
		if (isset($units[1]))
		{
			$svc->revoke_token($units[1], 'Token contoh dicabut untuk memperagakan halaman QR tidak berlaku.', $actor);
		}
		$this->track('asset_lifecycle', $batch->id, NULL, 'Riwayat aset contoh');
		$this->out('  Aset: token QR '.count($with_token).' unit, 1 batch label.');
	}

	// ------------------------------------------------------------------
	// Gudang persediaan
	// ------------------------------------------------------------------

	protected function warehouse()
	{
		$this->CI->load->library('WarehouseService', NULL, 'warehouse');
		$svc = $this->CI->warehouse;
		$actor = $this->actor_id('gudang.demo');
		if ($this->has_demo('inventory_item'))
		{
			$this->out('  Gudang demo sudah ada; dilewati.');
			// Tetap kembalikan bundelnya supaya transaksinya dapat menyusul bila belum ada.
			$items = array();
			foreach ($svc->items() as $row) { $items[$row->sku] = $row; }
			$loc = array();
			foreach ($svc->locations() as $row) { $loc[$row->code] = (int) $row->id; }
			return array($svc, $items, $loc, $actor);
		}

		$locations = array(
			array('GD-UTAMA', 'Gudang Utama Desa'),
			array('GD-ATK', 'Lemari ATK Kantor'),
			array('GD-POSYANDU', 'Gudang Posyandu'),
			array('GD-KEBERSIHAN', 'Gudang Alat Kebersihan'),
		);
		$loc = array();
		foreach ($locations as $l)
		{
			$svc->save_location(array('code' => $l[0], 'name' => $l[1]));
			$loc[$l[0]] = $this->id_of('warehouse_locations', array('code' => $l[0]));
			$this->track('inventory_location', $loc[$l[0]], NULL, $l[1]);
		}

		/* sku, nama, satuan dasar, kategori, stok minimum, konversi */
		$rows = array(
			array('ATK-001', 'Kertas HVS A4 70 gram', 'lembar', 'ATK', 2000, array('rim', 500)),
			array('ATK-002', 'Pulpen tinta hitam', 'buah', 'ATK', 50, array('lusin', 12)),
			array('ATK-003', 'Map arsip kertas', 'buah', 'ATK', 100, array('pak', 25)),
			array('ATK-004', 'Tinta printer hitam', 'botol', 'ATK', 6, NULL),
			array('ATK-005', 'Amplop putih', 'buah', 'ATK', 200, array('pak', 100)),
			array('ATK-006', 'Stapler besar', 'buah', 'ATK', 3, NULL),
			array('ATK-007', 'Isi stapler', 'kotak', 'ATK', 10, NULL),
			array('ATK-008', 'Spidol papan tulis', 'buah', 'ATK', 12, array('lusin', 12)),
			array('KBR-001', 'Sapu ijuk', 'buah', 'Kebersihan', 5, NULL),
			array('KBR-002', 'Pengki plastik', 'buah', 'Kebersihan', 5, NULL),
			array('KBR-003', 'Kantong sampah besar', 'lembar', 'Kebersihan', 100, array('pak', 50)),
			array('KBR-004', 'Cairan pembersih lantai', 'botol', 'Kebersihan', 8, array('dus', 12)),
			array('KBR-005', 'Sarung tangan karet', 'pasang', 'Kebersihan', 20, NULL),
			array('PSY-001', 'Vitamin A balita', 'kapsul', 'Posyandu', 200, array('botol', 100)),
			array('PSY-002', 'Buku KIA', 'buah', 'Posyandu', 50, array('pak', 25)),
			array('PSY-003', 'Timbangan gantung', 'buah', 'Posyandu', 2, NULL),
			array('PSY-004', 'Masker medis', 'lembar', 'Posyandu', 300, array('kotak', 50)),
			array('PSY-005', 'Sabun cuci tangan', 'botol', 'Posyandu', 10, array('dus', 12)),
			array('UMU-001', 'Air minum kemasan galon', 'galon', 'Umum', 6, NULL),
			array('UMU-002', 'Gula pasir', 'kilogram', 'Umum', 10, array('karung', 25)),
			array('UMU-003', 'Kopi bubuk', 'bungkus', 'Umum', 12, array('dus', 24)),
			array('UMU-004', 'Teh celup', 'kotak', 'Umum', 10, NULL),
			array('UMU-005', 'Lampu LED 12 watt', 'buah', 'Umum', 8, array('dus', 20)),
			array('UMU-006', 'Kabel listrik serabut', 'meter', 'Umum', 50, array('roll', 50)),
			array('UMU-007', 'Bendera merah putih', 'lembar', 'Umum', 10, NULL),
		);

		$items = array();
		foreach ($rows as $r)
		{
			list($sku, $name, $unit, $cat, $min, $conv) = $r;
			$item = $svc->save_item(array(
				'sku' => $sku, 'name' => $name, 'base_unit' => $unit, 'category' => $cat,
				'minimum_stock' => $min, 'track_batch' => 0, 'active' => 1,
			), $actor);
			if ($conv !== NULL)
			{
				// Konversi memakai pembilang dan penyebut bulat, bukan desimal bebas.
				$svc->save_conversion($item, array('from_unit' => $conv[0], 'numerator' => $conv[1], 'denominator' => 1));
			}
			$items[$sku] = $item;
			$this->track('inventory_item', $item->id, $item->public_id, $name);
			$this->count('inventory_items', TRUE);
		}
		return array($svc, $items, $loc, $actor);
	}

	/** Transaksi gudang: penerimaan, pengeluaran, transfer, penyesuaian dan opname. */
	protected function warehouse_movements($bundle)
	{
		if ( ! is_array($bundle) OR $this->has_demo('inventory_transaction'))
		{
			return;
		}
		list($svc, $items, $loc, $actor) = $bundle;
		$utama = $loc['GD-UTAMA'];
		$atk = $loc['GD-ATK'];
		$posyandu = $loc['GD-POSYANDU'];

		$post = function ($input, $lines) use ($svc, $actor) {
			$trx = $svc->create_transaction($input, $actor);
			foreach ($lines as $l)
			{
				$svc->add_line($trx, $l);
			}
			$svc->post($trx, $actor);
			$this->track('inventory_transaction', $trx->id, $trx->public_id, $input['reference_no']);
			$this->count('inventory_transactions', TRUE);
			return $trx;
		};

		// 1. Penerimaan awal ke gudang utama, sebagian memakai satuan besar.
		$post(array(
			'transaction_type' => 'receipt', 'from_location_id' => NULL, 'to_location_id' => $utama,
			'reference_no' => 'BTB-2026-001', 'source_fund' => 'Dana Desa',
			'reason' => 'Penerimaan pengadaan ATK dan kebersihan triwulan pertama.',
		), array(
			array('item_public_id' => $items['ATK-001']->public_id, 'quantity' => 20, 'input_unit' => 'rim'),
			array('item_public_id' => $items['ATK-002']->public_id, 'quantity' => 10, 'input_unit' => 'lusin'),
			array('item_public_id' => $items['ATK-003']->public_id, 'quantity' => 8, 'input_unit' => 'pak'),
			array('item_public_id' => $items['ATK-004']->public_id, 'quantity' => 24, 'input_unit' => 'botol'),
			array('item_public_id' => $items['ATK-005']->public_id, 'quantity' => 6, 'input_unit' => 'pak'),
			array('item_public_id' => $items['KBR-003']->public_id, 'quantity' => 10, 'input_unit' => 'pak'),
			array('item_public_id' => $items['KBR-004']->public_id, 'quantity' => 4, 'input_unit' => 'dus'),
			array('item_public_id' => $items['UMU-001']->public_id, 'quantity' => 30, 'input_unit' => 'galon'),
			array('item_public_id' => $items['UMU-005']->public_id, 'quantity' => 3, 'input_unit' => 'dus'),
		));

		// 2. Penerimaan bahan posyandu.
		$post(array(
			'transaction_type' => 'receipt', 'from_location_id' => NULL, 'to_location_id' => $posyandu,
			'reference_no' => 'BTB-2026-002', 'source_fund' => 'Bantuan Provinsi',
			'reason' => 'Penerimaan bahan kegiatan posyandu dari puskesmas kecamatan.',
		), array(
			array('item_public_id' => $items['PSY-001']->public_id, 'quantity' => 6, 'input_unit' => 'botol'),
			array('item_public_id' => $items['PSY-002']->public_id, 'quantity' => 4, 'input_unit' => 'pak'),
			array('item_public_id' => $items['PSY-004']->public_id, 'quantity' => 10, 'input_unit' => 'kotak'),
			array('item_public_id' => $items['PSY-005']->public_id, 'quantity' => 2, 'input_unit' => 'dus'),
		));

		// 3. Transfer sebagian ATK ke lemari kantor.
		$post(array(
			'transaction_type' => 'transfer', 'from_location_id' => $utama, 'to_location_id' => $atk,
			'reference_no' => 'TRF-2026-001', 'source_fund' => NULL,
			'reason' => 'Pemindahan ATK ke lemari kantor untuk pemakaian harian.',
		), array(
			array('item_public_id' => $items['ATK-001']->public_id, 'quantity' => 6, 'input_unit' => 'rim'),
			array('item_public_id' => $items['ATK-002']->public_id, 'quantity' => 4, 'input_unit' => 'lusin'),
			array('item_public_id' => $items['ATK-005']->public_id, 'quantity' => 2, 'input_unit' => 'pak'),
		));

		// 4. Pengeluaran pemakaian harian.
		$post(array(
			'transaction_type' => 'issue', 'from_location_id' => $atk, 'to_location_id' => NULL,
			'reference_no' => 'BPB-2026-001', 'source_fund' => NULL,
			'reason' => 'Pemakaian ATK untuk pelayanan administrasi bulan berjalan.',
		), array(
			array('item_public_id' => $items['ATK-001']->public_id, 'quantity' => 1200, 'input_unit' => 'lembar'),
			array('item_public_id' => $items['ATK-002']->public_id, 'quantity' => 18, 'input_unit' => 'buah'),
			array('item_public_id' => $items['ATK-005']->public_id, 'quantity' => 120, 'input_unit' => 'buah'),
		));

		// 5. Pengeluaran bahan posyandu.
		$post(array(
			'transaction_type' => 'issue', 'from_location_id' => $posyandu, 'to_location_id' => NULL,
			'reference_no' => 'BPB-2026-002', 'source_fund' => NULL,
			'reason' => 'Pemakaian bahan pada kegiatan posyandu bulan berjalan.',
		), array(
			array('item_public_id' => $items['PSY-001']->public_id, 'quantity' => 320, 'input_unit' => 'kapsul'),
			array('item_public_id' => $items['PSY-004']->public_id, 'quantity' => 260, 'input_unit' => 'lembar'),
		));

		// 6. Penyesuaian karena temuan fisik; alasan wajib diisi.
		$trx = $svc->create_transaction(array(
			'transaction_type' => 'adjustment', 'from_location_id' => $utama, 'to_location_id' => NULL,
			'reference_no' => 'ADJ-2026-001', 'source_fund' => NULL,
			'reason' => 'Penyesuaian setelah pemeriksaan fisik: sebagian kantong sampah rusak dan tidak dapat dipakai.',
		), $actor);
		$svc->add_adjustment_line($trx, array(
			'item_public_id' => $items['KBR-003']->public_id, 'quantity' => -25,
			'note' => 'Rusak saat penyimpanan, dibuang dengan berita acara contoh.',
		));
		$svc->post($trx, $actor);
		$this->track('inventory_transaction', $trx->id, $trx->public_id, 'ADJ-2026-001');
		$this->count('inventory_transactions', TRUE);

		// 7. Opname tertutup: selisih dihitung server dan ditutup dengan penyesuaian.
		$stocktake = $svc->open_stocktake(array('name' => 'Opname Gudang Utama contoh', 'location_id' => $utama), $actor);
		foreach ($svc->stocktake_lines($stocktake) as $line)
		{
			$counted = (float) $line->expected_quantity_base;
			if ((string) $line->sku === 'ATK-004')
			{
				$counted = max(0, $counted - 2);
			}
			$svc->count_line($stocktake, array(
				'line_id' => (int) $line->id, 'counted' => $counted,
				'note' => ((string) $line->sku === 'ATK-004') ? 'Dua botol tidak ditemukan saat pencacahan.' : '',
			), $actor);
		}
		$svc->close_stocktake($stocktake, $actor);
		$this->track('inventory_stocktake', $stocktake->id, $stocktake->public_id, 'Opname contoh');
		$this->out('  Gudang: 6 transaksi diposting, 1 opname ditutup.');
	}

	// ------------------------------------------------------------------
	// Berita, agenda dan galeri
	// ------------------------------------------------------------------

	protected function content()
	{
		if ($this->has_demo('post'))
		{
			$this->out('  Konten demo sudah ada; dilewati.');
			return;
		}
		$this->CI->load->library('ContentService', NULL, 'content_service');
		$svc = $this->CI->content_service;
		$actor = $this->actor_id('penerbit.demo');
		$cat = $this->id_of('content_categories', array('content_type' => 'post', 'slug' => 'pengumuman'));
		if ($cat === NULL)
		{
			$cat = (int) $this->CI->db->where('content_type', 'post')->order_by('id')->limit(1)
				->get('content_categories')->row('id');
		}
		$tail = '<p><em>Isi berita ini adalah contoh demonstrasi.</em></p>';

		$posts = array(
			array('Musyawarah desa penetapan APBDes berjalan lancar', 'musyawarah.jpg', 'Musyawarah desa membahas prioritas pembangunan tahun berjalan.', '<p>Pemerintah desa bersama BPD dan perwakilan warga menggelar musyawarah untuk menetapkan prioritas pembangunan. Usulan terbanyak berkaitan dengan perbaikan jalan lingkungan dan sarana air bersih.</p>'),
			array('Perbaikan jalan lingkungan Dusun Ciakar dimulai', 'jalan-desa.jpg', 'Pengerjaan jalan beton sepanjang jalur penghubung dusun mulai berjalan.', '<p>Pekerjaan pengecoran jalan lingkungan dimulai setelah bahan material tiba di lokasi. Warga diimbau berhati-hati saat melintas selama masa pengerjaan.</p>'),
			array('Posyandu rutin bulanan di empat dusun', 'posyandu.jpg', 'Penimbangan balita dan pemeriksaan ibu hamil kembali digelar.', '<p>Kegiatan posyandu berlangsung di empat dusun dengan dukungan kader dan bidan desa. Orang tua diimbau membawa buku KIA saat datang.</p>'),
			array('Pelatihan pengolahan hasil kebun untuk kelompok tani', 'kebun-teh.jpg', 'Kelompok tani mengikuti pelatihan pengolahan pascapanen.', '<p>Pelatihan membahas penanganan pascapanen agar harga jual hasil kebun lebih baik. Peserta berasal dari kelompok tani di seluruh dusun.</p>'),
			array('Pembangunan bak penampungan air bersih rampung', 'air-bersih.jpg', 'Bak penampungan baru melayani tiga dusun.', '<p>Bak penampungan air bersih selesai dibangun dan mulai dimanfaatkan warga. Pengelolaan dilakukan kelompok pengelola air minum desa.</p>'),
			array('Kerja bakti bersama menjelang peringatan hari besar', 'lapangan.jpg', 'Warga bergotong royong membersihkan lingkungan desa.', '<p>Kegiatan kerja bakti diikuti warga dari seluruh dusun. Fokus kegiatan adalah pembersihan saluran air dan penataan lapangan desa.</p>'),
			array('Pendataan UMKM desa mulai dilakukan', 'pasar.jpg', 'Pemerintah desa mendata pelaku usaha untuk direktori UMKM.', '<p>Pendataan dilakukan untuk menyusun direktori usaha mikro desa. Pendaftaran bersifat sukarela dan memerlukan persetujuan pemilik usaha.</p>'),
			array('Layanan administrasi kependudukan kembali normal', 'kantor-desa.jpg', 'Pelayanan di kantor desa berjalan sesuai jam kerja.', '<p>Pelayanan administrasi kependudukan berjalan normal pada hari kerja. Warga diimbau membawa berkas persyaratan yang lengkap.</p>'),
		);

		foreach ($posts as $i => $p)
		{
			list($title, $photo, $excerpt, $body) = $p;
			$id = $svc->save('berita', NULL, array(
				'type' => 'news',
				'category_id' => $cat,
				'title' => $title,
				'slug' => url_title(mb_strtolower($title), '-', TRUE),
				'excerpt' => $excerpt,
				'body_html' => $body.$tail,
				'cover_media_id' => $this->media($photo),
				'is_featured' => ($i < 2) ? 1 : 0,
				'publish_at' => gmdate('Y-m-d H:i:s', time() - ($i + 1) * 3 * 86400),
			), $actor);
			$svc->set_status('berita', $id, 'published', $actor);
			$this->track('post', $id, NULL, $title);
			$this->count('posts', TRUE);
		}

		$events = array(
			array('Musyawarah desa triwulan', 7, 'Balai Desa Cihawuk', 'Pemerintah Desa Cihawuk', 'musyawarah.jpg'),
			array('Posyandu Dusun Cihawuk', 10, 'Posyandu Mawar Ciakar', 'Kader Posyandu', 'posyandu.jpg'),
			array('Kerja bakti lingkungan', 14, 'Lapangan Serbaguna Cihawuk', 'Karang Taruna', 'lapangan.jpg'),
			array('Pelatihan UMKM dan pemasaran daring', 21, 'Balai Desa Cihawuk', 'Pemerintah Desa Cihawuk', 'pasar.jpg'),
			array('Pemeriksaan kesehatan lansia', 28, 'Puskesmas Pembantu Cihawuk', 'Puskesmas Kertasari', 'puskesmas.jpg'),
			array('Rapat koordinasi ketua RT dan RW', 35, 'Balai Desa Cihawuk', 'Pemerintah Desa Cihawuk', NULL),
		);
		foreach ($events as $e)
		{
			list($title, $days, $place, $organizer, $photo) = $e;
			$start = time() + $days * 86400;
			$id = $svc->save('agenda', NULL, array(
				'title' => $title,
				'slug' => url_title(mb_strtolower($title), '-', TRUE),
				'summary' => 'Agenda contoh demonstrasi di '.$place.'.',
				'description_html' => '<p>Agenda ini adalah contoh demonstrasi. Ganti dengan jadwal kegiatan desa yang sebenarnya.</p>',
				'starts_at' => gmdate('Y-m-d H:i:s', $start),
				'ends_at' => gmdate('Y-m-d H:i:s', $start + 3 * 3600),
				'location_text' => $place,
				'organizer' => $organizer,
				'poster_media_id' => $photo ? $this->media($photo) : NULL,
			), $actor);
			$svc->set_status('agenda', $id, 'published', $actor);
			$this->track('event', $id, NULL, $title);
			$this->count('events', TRUE);
		}
		$this->out('  Konten: '.count($posts).' berita, '.count($events).' agenda.');
	}

	// ------------------------------------------------------------------
	// Dataset statistik
	// ------------------------------------------------------------------

	protected function datasets()
	{
		$this->CI->load->library('DatasetService', NULL, 'datasets');
		$svc = $this->CI->datasets;
		$verifier = $this->actor_id('verifikator.demo');
		$publisher = $this->actor_id('penerbit.demo');

		$dataset = $svc->dataset_by_slug('kependudukan-2023');
		if ( ! $dataset OR $dataset->status === 'published')
		{
			return;
		}
		$versions = $svc->versions($dataset);
		if (empty($versions))
		{
			return;
		}
		$version = $versions[0];

		// Nilai harus terverifikasi sebelum dapat diterbitkan; ini alur pemeriksa yang asli.
		foreach ($svc->values($version->id) as $value)
		{
			if ($value->verification_status !== 'verified')
			{
				$svc->verify_value($version, (int) $value->indicator_id, TRUE, $verifier,
					'Diverifikasi untuk demonstrasi berdasarkan angka Profil Desa 2023.');
			}
		}

		$report = $svc->validate_version($dataset, $version);
		if ( ! empty($report['errors']))
		{
			$this->out('  Dataset belum dapat diterbitkan: '.implode(' | ', $report['errors']));
			return;
		}
		if ($dataset->status === 'draft')
		{
			$svc->submit_review($dataset, $verifier);
			$dataset = $svc->dataset_by_slug('kependudukan-2023');
		}
		$svc->publish($dataset, 'Penerbitan dataset kependudukan untuk demonstrasi.', $publisher);
		$this->track('dataset', $dataset->id, $dataset->public_id, 'Kependudukan 2023');
		$this->out('  Dataset kependudukan 2023 diterbitkan.');
	}

	// ------------------------------------------------------------------
	// Beranda: foto hero dan identitas situs
	// ------------------------------------------------------------------

	protected function homepage()
	{
		if ($this->has_demo('hero_slide'))
		{
			return;
		}
		$hero = $this->CI->db->order_by('sort_order')->limit(1)->get('hero_slides')->row();
		$image = $this->media('lanskap-pegunungan.jpg');
		if ( ! $hero OR $image === NULL)
		{
			return;
		}
		db_must($this->CI->db->where('id', (int) $hero->id)->update('hero_slides', array(
			'image_media_id' => $image,
			'updated_at' => $this->now(),
		)), 'demo hero_slides.image');
		$this->track('hero_slide', $hero->id, NULL, 'Foto hero contoh');

		// Foto profil desa dipakai pada halaman profil dan kartu berbagi.
		$profile = $this->CI->db->order_by('id')->limit(1)->get('village_profiles')->row();
		$office = $this->media('kantor-desa.jpg');
		if ($profile && $office !== NULL && $profile->profile_media_id === NULL)
		{
			db_must($this->CI->db->where('id', (int) $profile->id)->update('village_profiles', array(
				'profile_media_id' => $office,
				'updated_at' => $this->now(),
			)), 'demo village_profiles.photo');
			$this->track('village_profile_photo', $profile->id, NULL, 'Foto kantor contoh');
		}
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
		$this->CI->public_cache->flush();
		$this->out('  Beranda: foto hero dan foto kantor dipasang.');
	}

	// ------------------------------------------------------------------
	// Lokasi dan direktori fasilitas
	// ------------------------------------------------------------------

	protected function facilities()
	{
		if ($this->has_demo('facility'))
		{
			$this->out('  Fasilitas demo sudah ada; dilewati.');
			return;
		}
		$this->CI->load->library('FacilityService', NULL, 'facility_service');
		$svc = $this->CI->facility_service;
		$actor = $this->actor_id('webadmin.demo');
		$note = 'Data contoh demonstrasi, bukan pendataan resmi.';

		// Dusun sesuai empat peta pada S3; statusnya belum dikonfirmasi pengelola.
		$places = array(
			array('Kantor Desa Cihawuk', 'office', 'Jalan Raya Cihawuk, Dusun Cihawuk'),
			array('Dusun Cihawuk', 'other', 'Wilayah Dusun Cihawuk'),
			array('Dusun Ciakar', 'other', 'Wilayah Dusun Ciakar'),
			array('Dusun Puncakmulya', 'other', 'Wilayah Dusun Puncakmulya'),
			array('Dusun Pinggirsari', 'other', 'Wilayah Dusun Pinggirsari'),
		);
		$place_ids = array();
		foreach ($places as $p)
		{
			$place = $svc->save_place(array(
				'name' => $p[0], 'place_type' => $p[1], 'address' => $p[2],
				'area_note' => 'Nama dusun mengikuti peta pada S3; belum dikonfirmasi resmi.',
				'latitude' => NULL, 'longitude' => NULL, 'is_sensitive' => 0,
				'source_id' => NULL, 'source_note' => $note,
			), $actor);
			$svc->verify_place($place, TRUE, $actor);
			$place_ids[$p[0]] = (int) $place->id;
			$this->track('place', $place->id, $place->public_id, $p[0]);
			$this->count('places', TRUE);
		}

		/* nama, kategori, pengelola, alamat, foto, layanan */
		$rows = array(
			array('SD Negeri Cihawuk 01', 'education', 'Dinas Pendidikan Kabupaten Bandung', 'Jalan Raya Cihawuk No. 12, Dusun Cihawuk', 'sekolah.jpg', array('Kelas 1 sampai 6', 'Perpustakaan sekolah')),
			array('SD Negeri Ciakar', 'education', 'Dinas Pendidikan Kabupaten Bandung', 'Jalan Ciakar, Dusun Ciakar', 'sekolah.jpg', array('Kelas 1 sampai 6')),
			array('MTs Al-Hidayah Cihawuk', 'religious_education', 'Yayasan Al-Hidayah', 'Jalan Raya Cihawuk No. 40, Dusun Cihawuk', 'sekolah.jpg', array('Kelas 7 sampai 9', 'Asrama putra')),
			array('PAUD Melati Puncakmulya', 'education', 'PKK Desa Cihawuk', 'Dusun Puncakmulya RT 02 RW 05', NULL, array('Kelompok bermain', 'Taman kanak-kanak')),
			array('Puskesmas Pembantu Cihawuk', 'health', 'Puskesmas Kecamatan Kertasari', 'Jalan Raya Cihawuk No. 8, Dusun Cihawuk', 'puskesmas.jpg', array('Pemeriksaan umum', 'Kesehatan ibu dan anak', 'Rujukan')),
			array('Posyandu Mawar Ciakar', 'health', 'Kader Posyandu Dusun Ciakar', 'Dusun Ciakar RT 01 RW 03', 'posyandu.jpg', array('Penimbangan balita', 'Imunisasi', 'Pemeriksaan ibu hamil')),
			array('Posyandu Anggrek Pinggirsari', 'health', 'Kader Posyandu Dusun Pinggirsari', 'Dusun Pinggirsari RT 03 RW 07', 'posyandu.jpg', array('Penimbangan balita', 'Pemberian makanan tambahan')),
			array('Masjid Jami Al-Ikhlas', 'worship', 'DKM Al-Ikhlas', 'Jalan Raya Cihawuk No. 21, Dusun Cihawuk', 'masjid.jpg', array('Salat lima waktu', 'Salat Jumat', 'Pengajian rutin')),
			array('Masjid Nurul Huda Puncakmulya', 'worship', 'DKM Nurul Huda', 'Dusun Puncakmulya RT 01 RW 04', 'masjid.jpg', array('Salat lima waktu', 'Taman pendidikan Al-Quran')),
			array('Lapangan Serbaguna Cihawuk', 'sport', 'Karang Taruna Desa Cihawuk', 'Belakang Kantor Desa, Dusun Cihawuk', 'lapangan.jpg', array('Sepak bola', 'Bola voli', 'Kegiatan desa')),
			array('Bak Penampungan Air Cikahuripan', 'water', 'Kelompok Pengelola Air Minum Desa', 'Dusun Puncakmulya, kaki bukit Cikahuripan', 'air-bersih.jpg', array('Distribusi air bersih ke 3 dusun')),
			array('Kantor Desa Cihawuk', 'government', 'Pemerintah Desa Cihawuk', 'Jalan Raya Cihawuk No. 1, Dusun Cihawuk', 'kantor-desa.jpg', array('Administrasi kependudukan', 'Surat pengantar', 'Pelayanan umum')),
		);

		$hours = array(
			array('label' => 'Senin sampai Kamis', 'value' => '08.00 - 15.00 WIB'),
			array('label' => 'Jumat', 'value' => '08.00 - 11.30 WIB'),
			array('label' => 'Sabtu dan Minggu', 'value' => 'Tutup'),
		);

		foreach ($rows as $i => $r)
		{
			list($name, $cat, $manager, $address, $photo, $services) = $r;
			$slug = url_title(mb_strtolower($name), '-', TRUE);
			$facility = $svc->save_facility(array(
				'name' => $name,
				'slug' => $slug,
				'category' => $cat,
				'manager_name' => $manager,
				'description' => 'Entri contoh untuk memperagakan direktori fasilitas. Alamat, pengelola dan jam layanan di sini belum tentu sesuai keadaan sebenarnya.',
				'place_id' => isset($place_ids['Kantor Desa Cihawuk']) && $cat === 'government' ? $place_ids['Kantor Desa Cihawuk'] : NULL,
				'address' => $address,
				'service_hours' => ($cat === 'government' OR $cat === 'health') ? $hours : array(),
				'public_contact' => '',
				'contact_permission' => 0,
				'accessibility' => ($i % 3 === 0) ? 'Terdapat jalur landai menuju pintu masuk.' : '',
				'photo_media_id' => $photo ? $this->media($photo) : NULL,
				'source_year' => 2023,
				'source_id' => NULL,
				'source_note' => $note,
				'is_active' => 1,
			), $actor);
			foreach ($services as $s)
			{
				$svc->save_service($facility, array('label' => $s, 'description' => ''));
			}
			$svc->verify($facility, TRUE, $actor);
			$facility = $svc->facility($facility->public_id);
			$svc->publish($facility, $actor);
			$this->track('facility', $facility->id, $facility->public_id, $name);
			$this->count('facilities', TRUE);
		}
	}

	/**
	 * Hapus entitas demo yang tercatat di `demo_records`, beserta turunannya.
	 *
	 * Urutannya dari turunan ke induk supaya kunci asing tidak menghalangi. Baris yang
	 * tidak tercatat di sini tidak pernah disentuh, jadi data nyata tidak dapat ikut hilang.
	 */
	protected function purge_tracked()
	{
		// entity_type => array(array(tabel_anak, kolom_fk), ...) lalu tabel induknya.
		$plan = array(
			'facility' => array('parent' => 'facilities', 'children' => array(
				array('facility_services', 'facility_id'),
			)),
			'place' => array('parent' => 'places', 'children' => array()),
			'business' => array('parent' => 'businesses', 'children' => array()),
			'post' => array('parent' => 'posts', 'children' => array()),
			// Hero dan foto profil hanya dilepas rujukannya; barisnya milik seed master.
			'hero_slide' => array('parent' => NULL, 'children' => array(), 'nullify' => array(
				array('hero_slides', 'image_media_id', 'id'),
			)),
			'village_profile_photo' => array('parent' => NULL, 'children' => array(), 'nullify' => array(
				array('village_profiles', 'profile_media_id', 'id'),
			)),
			'event' => array('parent' => 'events', 'children' => array()),
			// asset_units memakai RESTRICT ke asset_registers, jadi unit harus dihapus lebih
			// dulu; mutasi, peminjaman, pemeliharaan dan token ikut lewat CASCADE dari unit.
			'asset_register' => array('parent' => 'asset_registers', 'children' => array(
				array('asset_units', 'register_id'),
			)),
			// asset_movements.to_location_id juga RESTRICT, tetapi mutasinya sudah ikut
			// terhapus bersama unit di atas.
			'asset_location' => array('parent' => 'asset_locations', 'children' => array(), 'nullify' => array(
				array('asset_locations', 'parent_id', 'id'),
			)),
			'asset_lifecycle' => array('parent' => 'asset_label_batches', 'children' => array(
				array('asset_label_batch_items', 'batch_id'),
			)),
			// Ledger dan baris transaksi ikut lewat CASCADE dari transaksinya.
			'inventory_stocktake' => array('parent' => 'inventory_stocktakes', 'children' => array()),
			'inventory_transaction' => array('parent' => 'inventory_transactions', 'children' => array()),
			'inventory_item' => array('parent' => 'inventory_items', 'children' => array(
				array('inventory_unit_conversions', 'item_id'),
			)),
			'inventory_location' => array('parent' => 'warehouse_locations', 'children' => array()),
			'budget_year' => array('parent' => 'budget_years', 'children' => array(), 'nullify' => array(
				// budget_categories.parent_id menunjuk dirinya sendiri dengan RESTRICT, sehingga
				// CASCADE dari budget_years tidak dapat menghapus induk selama anaknya ada.
				array('budget_categories', 'parent_id', 'budget_year_id'),
			)),
			'potential' => array('parent' => 'potentials', 'children' => array(
				array('potential_media', 'potential_id'),
			)),
		);

		$this->purge_inventory();
		$this->purge_publications();

		$removed = $this->purge_organization();
		foreach ($plan as $type => $spec)
		{
			$ids = $this->tracked($type);
			if (empty($ids))
			{
				continue;
			}
			foreach ((isset($spec['nullify']) ? $spec['nullify'] : array()) as $n)
			{
				$this->CI->db->where_in($n[2], $ids)->update($n[0], array($n[1] => NULL));
			}
			foreach ($spec['children'] as $child)
			{
				$this->CI->db->where_in($child[1], $ids)->delete($child[0]);
			}
			if ($spec['parent'] !== NULL)
			{
				$this->CI->db->where_in('id', $ids)->delete($spec['parent']);
			}
			$this->CI->db->where('entity_type', $type)->delete('demo_records');
			$removed[$type] = count($ids);
		}
		return $removed;
	}

	/**
	 * Bersihkan pergerakan gudang sebelum barang dan lokasinya dihapus.
	 *
	 * inventory_ledger memakai RESTRICT ke barang dan lokasi, dan penutupan opname
	 * membuat transaksi penyesuaian sendiri yang tidak ikut tercatat sebagai entitas akar.
	 * Karena itu transaksi dicari lewat barang demo, bukan lewat jejak.
	 */
	protected function purge_inventory()
	{
		$items = $this->tracked('inventory_item');
		if (empty($items))
		{
			return;
		}
		$this->CI->db->where_in('item_id', $items)->delete('inventory_stocktake_lines');
		$this->CI->db->where_in('item_id', $items)->delete('inventory_request_lines');
		$trx = array();
		foreach ($this->CI->db->select('DISTINCT(transaction_id) AS transaction_id', FALSE)
			->where_in('item_id', $items)->get('inventory_transaction_lines')->result() as $row)
		{
			$trx[] = (int) $row->transaction_id;
		}
		// Opname menunjuk transaksi penyesuaiannya; lepaskan dulu supaya urutan hapus bebas.
		$this->CI->db->update('inventory_stocktakes', array('posted_adjustment_transaction_id' => NULL));
		$this->CI->db->update('inventory_requests', array('fulfilled_transaction_id' => NULL));
		$this->CI->db->where_in('item_id', $items)->delete('inventory_ledger');
		if ( ! empty($trx))
		{
			$this->CI->db->where_in('id', $trx)->delete('inventory_transactions');
		}
		$this->CI->db->where('entity_type', 'inventory_transaction')->delete('demo_records');
	}

	/**
	 * Batalkan penerbitan yang dilakukan seeder demo.
	 *
	 * Isi profil dan struktur berasal dari dokumen sumber, jadi barisnya tidak dihapus;
	 * yang dibatalkan hanya penerbitannya, karena menerbitkan memang aksi seeder demo.
	 * Versi blok kontak ikut dihapus sebab isinya benar-benar karangan.
	 */
	protected function purge_publications()
	{
		$now = $this->now();

		// 1. Profil desa kembali tidak terbit.
		$this->CI->db->where(array('target_type' => 'profile', 'target_id' => 0))
			->where('superseded_at IS NULL', NULL, FALSE)
			->update('cms_publication_snapshots', array('superseded_at' => $now));
		$this->CI->db->where('status', 'published')->update('profile_blocks', array(
			'status' => 'draft', 'published_version_id' => NULL, 'published_at' => NULL, 'updated_at' => $now,
		));

		// 2. Blok kontak: versinya karangan, jadi dibuang dan bloknya dikosongkan lagi.
		foreach ($this->tracked('profile_block') as $block_id)
		{
			$this->CI->db->where('id', (int) $block_id)->update('profile_blocks', array(
				'current_version_id' => NULL, 'published_version_id' => NULL,
			));
			$this->CI->db->where('block_id', (int) $block_id)
				->where('change_note', 'Kontak contoh untuk demonstrasi.')
				->delete('profile_block_versions');
			$remaining = $this->CI->db->select('id')->where('block_id', (int) $block_id)
				->order_by('version_no', 'DESC')->limit(1)->get('profile_block_versions')->row();
			$this->CI->db->where('id', (int) $block_id)->update('profile_blocks', array(
				'current_version_id' => $remaining ? (int) $remaining->id : NULL,
				'verification_status' => 'unverified', 'verified_by' => NULL, 'verified_at' => NULL,
				'status' => 'draft', 'updated_at' => $now,
			));
		}
		$this->CI->db->where('entity_type', 'profile_block')->delete('demo_records');

		// 3. Struktur organisasi dan dataset kembali tidak terbit.
		$this->CI->db->where_in('target_type', array('organization', 'dataset'))
			->where('superseded_at IS NULL', NULL, FALSE)
			->update('cms_publication_snapshots', array('superseded_at' => $now));
		$this->CI->db->where('status', 'published')->update('org_periods', array(
			'status' => 'draft', 'is_public_default' => 0, 'updated_at' => $now,
		));
		foreach ($this->tracked('dataset') as $dataset_id)
		{
			$this->CI->db->where('id', (int) $dataset_id)->update('datasets', array(
				'status' => 'draft', 'published_at' => NULL, 'updated_at' => $now,
			));
		}
		$this->CI->db->where('entity_type', 'dataset')->delete('demo_records');

		// 4. Linimasa kepemimpinan kembali ke keadaan belum terbit.
		$this->CI->db->where('publication_status', 'published')
			->update('leadership_terms', array('publication_status' => 'draft', 'updated_at' => $now));
	}

	public function purge()
	{
		$this->CI->db->trans_begin();
		$tracked = $this->purge_tracked();
		$ticket_ids = array();
		foreach ($this->CI->db->select('id')->where('title LIKE', '[Demo]%')->get('tickets')->result() as $row)
		{
			$ticket_ids[] = (int) $row->id;
		}
		if ( ! empty($ticket_ids))
		{
			$instance_ids = array();
			foreach ($this->CI->db->select('id')->where_in('ticket_id', $ticket_ids)->get('ticket_sla_instances')->result() as $row)
			{
				$instance_ids[] = (int) $row->id;
			}
			if ( ! empty($instance_ids))
			{
				$this->CI->db->where_in('sla_instance_id', $instance_ids)->delete('ticket_sla_pauses');
			}
			foreach (array('ticket_escalations', 'ticket_feedback', 'ticket_references', 'ticket_attachments',
				'ticket_status_history', 'ticket_messages', 'ticket_assignments', 'ticket_conflicts', 'ticket_private_contacts',
				'anonymous_access_grants', 'ticket_access_secrets', 'ticket_resolution_episodes') as $table)
			{
				$this->CI->db->where_in('ticket_id', $ticket_ids)->delete($table);
			}
			$this->CI->db->where_in('ticket_id', $ticket_ids)->delete('ticket_sla_instances');
			$this->CI->db->where_in('id', $ticket_ids)->update('tickets', array('duplicate_of_ticket_id' => NULL));
			$this->CI->db->where_in('id', $ticket_ids)->delete('tickets');
		}
		$user_ids = array();
		foreach ($this->CI->db->select('id')->where('username LIKE', '%.demo')->get('users')->result() as $row)
		{
			$user_ids[] = (int) $row->id;
		}
		if ( ! empty($user_ids))
		{
			$this->CI->db->where_in('recipient_user_id', $user_ids)->delete('notifications');
			// export_jobs memakai kunci asing RESTRICT ke users, jadi harus dibuang lebih dulu.
			foreach (array('user_sessions', 'mfa_recovery_codes', 'account_tokens', 'remember_tokens', 'export_jobs') as $table)
			{
				$column = ($table === 'export_jobs') ? 'requested_by' : 'user_id';
				$this->CI->db->where_in($column, $user_ids)->delete($table);
			}
			$this->CI->db->where_in('created_by', $user_ids)->update('account_tokens', array('created_by' => NULL));
			$this->CI->db->where_in('created_by', $user_ids)->update('users', array('created_by' => NULL));
			$this->CI->db->where_in('user_id', $user_ids)->delete('user_mfa');
			$this->CI->db->where_in('user_id', $user_ids)->delete('user_unit_scopes');
			$this->CI->db->where_in('user_id', $user_ids)->delete('resident_profiles');
			$this->CI->db->where_in('user_id', $user_ids)->delete('user_roles');
			$this->CI->db->where_in('recipient_user_id', $user_ids)->delete('notification_outbox');
			$this->CI->db->where_in('actor_user_id', $user_ids)->update('audit_logs', array('actor_user_id' => NULL));
			$this->CI->db->where_in('id', $user_ids)->delete('users');
		}
		if ($this->CI->db->trans_status() === FALSE)
		{
			$err = $this->CI->db->error();
			$this->CI->db->trans_rollback();
			$this->out('Gagal menghapus data demo: '.($err['message'] ?: 'penyebab tidak dilaporkan driver').' (kode '.$err['code'].').');
			return;
		}
		$this->CI->db->trans_commit();
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
		$this->CI->public_cache->flush();
		$parts = array(count($ticket_ids).' tiket', count($user_ids).' akun');
		foreach ($tracked as $type => $n) { $parts[] = $n.' '.$type; }
		$this->out('Data demo dihapus: '.implode(', ', $parts).'.');
	}
}
