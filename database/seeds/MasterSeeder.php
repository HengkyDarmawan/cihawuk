<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__.'/Seeder.php';

/**
 * Seed master: permission, role, unit contoh, kategori layanan, kalender & SLA contoh,
 * pengaturan, menu, register sumber S1–S4, fakta historis draft (§4.2) dan data issue (§4.3).
 *
 * Idempotent: menjalankan ulang tidak menimpa data yang sudah diedit pengelola.
 * Semua data sumber dimulai sebagai draft/pending; publikasi adalah aksi terpisah.
 */
class MasterSeeder extends Seeder {

	public function run()
	{
		$this->out('Seed master…');
		db_transaction(function () {
			$this->permissions_and_roles();
			$this->feature_modules();
			$this->units_and_areas();
			$this->calendar_and_sla();
			$this->ticket_categories();
			$this->content_categories();
			$this->navigation();
			$this->settings();
			$sources = $this->source_documents();
			$this->village_profile($sources);
			$this->statistics($sources);
			$this->data_issues($sources);
			$this->government($sources);
			$this->potentials($sources);
			$this->hero();
			$this->datasets($sources);
			$this->profile_blocks($sources);
			$this->organization($sources);
			$this->asset_master();
			$this->cms_home_page();
			$this->repair_home_statistics_draft();
			$this->cms_menus();
			$this->site_identity();
		});
		$this->summary();
		$this->out('Selesai.');
	}

	protected function permissions_and_roles()
	{
		$this->CI->config->load('rbac', TRUE);
		$now = $this->now();
		foreach ($this->CI->config->item('permissions', 'rbac') as $code => $description)
		{
			$this->insert_if_missing('permissions', array('code' => $code), array('description' => $description));
		}
		$preset_version = (int) $this->CI->config->item('preset_version', 'rbac');
		foreach ($this->CI->config->item('roles', 'rbac') as $code => $role)
		{
			$existing = $this->CI->db->select('id, preset_version')->get_where('roles', array('code' => $code))->row();
			$role_id = $this->insert_if_missing('roles', array('code' => $code), array(
				'name' => $role['name'], 'description' => $role['description'], 'is_system' => 1,
				'is_staff' => $role['is_staff'], 'preset_version' => $preset_version,
				'created_at' => $now, 'updated_at' => $now,
			));

			// Preset dipasang saat role dibuat, dan ditambahkan lagi hanya bila versi preset naik.
			// Permission yang sudah dicabut pengelola pada versi yang sama tidak dipasang ulang,
			// dan seeder tidak pernah mencabut permission.
			if ($existing !== NULL && (int) $existing->preset_version >= $preset_version)
			{
				continue;
			}
			foreach ($role['permissions'] as $perm)
			{
				$perm_id = $this->id_of('permissions', array('code' => $perm));
				if ($perm_id === NULL)
				{
					throw new RuntimeException('Permission preset tidak dikenal: '.$perm);
				}
				db_must($this->CI->db->query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', array($role_id, $perm_id)), 'seed role_permissions');
			}
			if ($existing !== NULL)
			{
				db_must($this->CI->db->where('id', (int) $existing->id)->update('roles', array(
					'preset_version' => $preset_version, 'updated_at' => $now,
				)), 'roles.preset_version');
			}
		}
		$this->consolidate_legacy_roles();
	}

	/**
	 * Pindahkan pemegang role sistem lama ke role pengganti (config rbac legacy_role_map),
	 * lalu hapus role lama. Role buatan pengelola (is_system = 0) tidak disentuh.
	 */
	protected function consolidate_legacy_roles()
	{
		foreach ((array) $this->CI->config->item('legacy_role_map', 'rbac') as $old_code => $new_code)
		{
			$old = $this->CI->db->get_where('roles', array('code' => $old_code, 'is_system' => 1))->row();
			if ( ! $old)
			{
				continue;
			}
			$new_id = $this->id_of('roles', array('code' => $new_code));
			if ($new_id === NULL)
			{
				throw new RuntimeException('Role pengganti tidak dikenal: '.$new_code);
			}
			db_must($this->CI->db->query(
				'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by, assigned_at)
					SELECT user_id, ?, assigned_by, assigned_at FROM user_roles WHERE role_id = ?',
				array((int) $new_id, (int) $old->id)
			), 'seed consolidate user_roles');
			$moved = (int) $this->CI->db->where('role_id', (int) $old->id)->count_all_results('user_roles');
			foreach (array('user_roles', 'role_permissions', 'role_menu_hidden') as $table)
			{
				db_must($this->CI->db->delete($table, array('role_id' => (int) $old->id)), 'seed consolidate '.$table);
			}
			db_must($this->CI->db->delete('roles', array('id' => (int) $old->id)), 'seed consolidate roles');
			$this->out('Role '.$old_code.' digabung ke '.$new_code.' ('.$moved.' pengguna).');
			$consolidated = TRUE;
		}
		if (empty($consolidated))
		{
			return;
		}
		// Setelah penggabungan, pengelola cukup memegang satu role pengelola: yang tertinggi.
		$ranked = array('super_admin', 'admin_desa', 'petugas');
		for ($i = 0; $i < count($ranked) - 1; $i++)
		{
			$higher_id = $this->id_of('roles', array('code' => $ranked[$i]));
			foreach (array_slice($ranked, $i + 1) as $lower)
			{
				$lower_id = $this->id_of('roles', array('code' => $lower));
				if ($higher_id === NULL OR $lower_id === NULL)
				{
					continue;
				}
				db_must($this->CI->db->query(
					'DELETE low FROM user_roles low JOIN user_roles high ON high.user_id = low.user_id AND high.role_id = ?
						WHERE low.role_id = ?',
					array((int) $higher_id, (int) $lower_id)
				), 'seed consolidate single staff role');
			}
		}
	}

	/**
	 * Halaman beranda sebagai data CMS (modul-backend §7): susunan section berasal dari database,
	 * bukan urutan yang ditulis permanen di view. Idempotent: halaman yang sudah ada tidak ditimpa.
	 */
	protected function cms_home_page()
	{
		if ($this->id_of('cms_pages', array('page_key' => 'home')) !== NULL)
		{
			$this->count('cms_pages', FALSE);
			return;
		}
		$now = $this->now();

		db_must($this->CI->db->insert('cms_pages', array(
			'public_id' => $this->CI->crypto->public_id(),
			'page_key' => 'home',
			'is_system' => 1,
			'sort_order' => 10,
			'status' => 'draft',
			'created_at' => $now,
			'updated_at' => $now,
		)), 'seed cms_pages');
		$page_id = (int) $this->CI->db->insert_id();
		$this->count('cms_pages', TRUE);
		$page_public_id = $this->CI->db->select('public_id')->get_where('cms_pages', array('id' => $page_id))->row('public_id');

		db_must($this->CI->db->insert('cms_page_versions', array(
			'page_id' => $page_id,
			'version_no' => 1,
			'nav_title' => 'Beranda',
			'title' => 'Beranda',
			'slug' => 'beranda',
			'summary' => 'Pintu masuk layanan dan informasi Desa Cihawuk.',
			'template_code' => 'page.landing',
			'seo_description' => 'Profil, data, potensi, dan layanan pengaduan Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung.',
			'search_indexable' => 1,
			'created_at' => $now,
		)), 'seed cms_page_versions');
		$version_id = (int) $this->CI->db->insert_id();
		$this->count('cms_page_versions', TRUE);

		// Susunan awal sama dengan beranda sebelum CMS; pengelola dapat mengubahnya tanpa sentuh kode.
		$sections = array(
			array('hero', 'hero.standard', 'Selamat Datang di Desa Cihawuk', 'Kecamatan Kertasari, Kabupaten Bandung', array(
				'mode' => 'three',
				'cta' => array(
					array('label' => 'Jelajahi Desa', 'url' => '/profil'),
					array('label' => 'Layanan Warga', 'url' => '/layanan'),
				),
			)),
			array('quick_links', 'cards.grid_3', 'Akses cepat layanan', NULL, array(
				'links' => array(
					array('label' => 'Buat Laporan', 'url' => '/lapor', 'description' => 'Pengaduan, aspirasi, atau permintaan informasi — bisa tanpa akun.'),
					array('label' => 'Lacak Laporan', 'url' => '/lacak', 'description' => 'Pantau perkembangan dengan nomor tiket dan kode akses.'),
					array('label' => 'Data Desa', 'url' => '/data-desa', 'description' => 'Statistik dari dokumen profil desa beserta tahun sumbernya.'),
					array('label' => 'Informasi Pelayanan', 'url' => '/layanan', 'description' => 'Alur, jenis laporan, dan jam pelayanan kantor desa.'),
				),
			)),
			array('profile_summary', 'text_image.image_left', 'Desa di dataran tinggi Kertasari', 'Profil desa', array(
				'cta' => array(
					array('label' => 'Profil lengkap', 'url' => '/profil'),
					array('label' => 'Baca sejarah desa', 'url' => '/profil'),
				),
			)),
			array('statistics', 'statistics.cards_4', 'Cihawuk dalam angka', 'Data desa', array(
				'dataset_slug' => 'kependudukan-2023',
				'series' => array('population_total', 'population_male', 'population_female', 'households'),
				'source_note' => 'Data Profil Desa 2023',
			)),
			array('featured_potentials', 'cards.carousel', 'Kekayaan alam dan karya warga', 'Jelajah potensi', array(
				'selection' => 'auto',
				'limit' => 9,
				'cta' => array(array('label' => 'Semua potensi', 'url' => '/potensi')),
			)),
			array('featured_news', 'cards.grid_3', 'Berita dan pengumuman', 'Cerita desa', array(
				'limit' => 4,
				'cta' => array(array('label' => 'Semua berita', 'url' => '/berita')),
			)),
			array('upcoming_agenda', 'cards.grid_3', 'Kegiatan mendatang', 'Agenda', array(
				'limit' => 4,
			)),
			array('verified_map', 'text_image.image_right', 'Kantor Desa Cihawuk', 'Lokasi layanan', array(
				'feature_types' => 'office',
			)),
			array('service_cta', 'cards.grid_3', 'Sampaikan keluhan, usulan, atau pertanyaan Anda', NULL, array(
				'body' => 'Laporan bersifat privat. Anda dapat mengirim tanpa akun dan memantau tindak lanjutnya dengan kode akses rahasia.',
				'cta' => array(
					array('label' => 'Buat Laporan', 'url' => '/lapor'),
					array('label' => 'Lacak Laporan', 'url' => '/lacak'),
				),
			)),
		);

		$snapshot_sections = array();
		foreach ($sections as $i => $section)
		{
			list($type, $layout, $title, $subtitle, $config) = $section;
			$public_id = $this->CI->crypto->public_id();
			db_must($this->CI->db->insert('cms_sections', array(
				'public_id' => $public_id,
				'page_id' => $page_id,
				'section_type' => $type,
				'sort_order' => ($i + 1) * 10,
				'is_enabled' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			)), 'seed cms_sections');
			$section_id = (int) $this->CI->db->insert_id();
			$this->count('cms_sections', TRUE);

			db_must($this->CI->db->insert('cms_section_versions', array(
				'section_id' => $section_id,
				'version_no' => 1,
				'title' => $title,
				'subtitle' => $subtitle,
				'layout_variant' => $layout,
				'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
				'created_at' => $now,
			)), 'seed cms_section_versions');
			$section_version_id = (int) $this->CI->db->insert_id();
			db_must($this->CI->db->where('id', $section_id)->update('cms_sections', array('current_version_id' => $section_version_id)), 'seed cms_sections.version');

			$snapshot_sections[] = array(
				'public_id' => $public_id,
				'type' => $type,
				'title' => $title,
				'subtitle' => $subtitle,
				'layout' => $layout,
				'config' => $config,
				'version_no' => 1,
			);
		}

		$snapshot = array(
			'page' => array(
				'public_id' => $page_public_id,
				'page_key' => 'home',
				'version_no' => 1,
				'title' => 'Beranda',
				'nav_title' => 'Beranda',
				'slug' => 'beranda',
				'summary' => 'Pintu masuk layanan dan informasi Desa Cihawuk.',
				'template_code' => 'page.landing',
				'seo_title' => NULL,
				'seo_description' => 'Profil, data, potensi, dan layanan pengaduan Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung.',
				'cover_media_id' => NULL,
				'search_indexable' => 1,
			),
			'sections' => $snapshot_sections,
		);
		db_must($this->CI->db->insert('cms_publication_snapshots', array(
			'target_type' => 'page',
			'target_id' => $page_id,
			'page_version_id' => $version_id,
			'revision_no' => 1,
			'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
			'reason' => 'Susunan awal beranda dari seed master.',
			'published_at' => $now,
		)), 'seed cms_publication_snapshots');
		$this->count('cms_publication_snapshots', TRUE);

		db_must($this->CI->db->where('id', $page_id)->update('cms_pages', array(
			'current_version_id' => $version_id,
			'published_version_id' => $version_id,
			'status' => 'published',
			'published_at' => $now,
			'updated_at' => $now,
		)), 'seed cms_pages.publish');
	}

	/**
	 * Blok profil desa dan linimasa kepemimpinan (modul-frontend 5).
	 *
	 * Seluruhnya DRAFT dan belum terverifikasi. Blok yang teksnya tidak ada pada dokumen
	 * sumber (sambutan, visi, misi, kontak) sengaja dibuat kosong: mengarang isinya
	 * dilarang spesifikasi. Konflik luas wilayah ditulis sebagai catatan, bukan dipilih.
	 */
	protected function profile_blocks(array $sources)
	{
		$now = $this->now();
		$this->CI->config->load('profile_blocks', TRUE);
		$definitions = $this->CI->config->item('profile_blocks', 'profile_blocks');

		$bodies = array(
			'identity' => array(
				'village_name' => 'Cihawuk',
				'district' => 'Kertasari',
				'regency' => 'Bandung',
				'province' => 'Jawa Barat',
				'pum_code' => '320431.2006',
				'summary' => 'Desa Cihawuk berada di Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat.',
				'coordinate_latitude_raw' => '7,1999',
				'coordinate_longitude_raw' => '107,7050',
			),
			// Sambutan, visi dan misi berasal dari S3 (Kata Pengantar Profil Cihawuk 2023),
			// yang baru dapat dibaca setelah .doc lama dikonversi menjadi .docx. Naskah
			// disalin apa adanya; ejaan sumber tidak diperbaiki diam-diam.
			'greeting' => array(
				'author_name' => 'Yaya Dores',
				'author_position' => 'Kepala Desa Cihawuk',
				'document_date' => 'Januari 2024',
				'original_text' => "\u{201C} Assalamualaikum Wr.Wb \u{201D}\n\n"
					.'Puji dan syukur kita panjatkan kehadirat illahi Robbi karena dengan ijinnya, Pemerintah Desa Cihawuk Kecamatan Kertasari sudah dapat menyelesaikan Profil Desa Cihawuk Tahun 2023 sebagai bahan untuk penunjang pembangunan diwilayah Desa Cihawuk Kecamatan Kertasari.'."\n\n"
					.'Dalam kesempatan ini saya ucapkan terima kasih kepada seluruh dinas terkait, Perangkat Desa, Lembaga Desa serta Organisasi kemasyarakatan yang ada di Desa Cihawuk yang telah membantu sebagai sumber data bagi kami sehingga kami mampu menerbitkan Profil ini, walapun saya menyadari masih banyak lagi data yang perlu perbaikan, namun saya juga maklum dengan segala keterbatasan baik waktu, tenaga dan sumber data yang tersedia.'."\n\n"
					.'Untuk itu kepada pengguna data semoga dengan terbitnya Profil Desa Cihawuk tahun 2023 ini dapat membantu saudara dan mudah-mudahan di waktu yang akan datang akan ada lagi publikasi Profil Desa Cihawuk yang diterbitkan untuk penunjang bahan dasar pembangunan.'."\n\n"
					.'Demikian kata sambutan dari saya selaku Kepala Desa Cihawuk semoga buku ini dapat dipergunakan sebagaimana mestinya atas segala perhatiannya saya ucapkan terima kasih.'."\n\n"
					."\u{201C} Billahitaufik wal hidayah wasalamualaikum Wr.Wb. \u{201D}",
			),
			'vision' => array(
				'village_vision' => "\u{201C}MEMANTAPKAN DESA CIHAWUK YANG AMAN, MAJU MANDIRI DAN BERDAYA SAING, MELALUI TATA KELOLA PEMERINTAH DAN MASYARAKAT YANG SEMANGAT, BAIK DAN BERSATU. PEMBANGUNAN DILINGKUNGAN MASYARAKAT YANG BERLANDASKAN KEIMANAN DAN KETAQWAAN, BUDAYA DAN BERWAWASAN LINGKUNGAN\u{201D}",
				'district_vision' => "\u{201C}Terlaksananya Pelimpahan Kewenangan secara Optimal Meningkatnya Profesionalisme Kinerja apparat, Terwujudnya Pelayanan Prima Serta Terwujudnya Masyarakat Kecamatan Kertasari Yang Repeh Rapih Kertaraharja Melalui Pembangunan Persitipatif Yang Berbasis Religius Kultural Dan Berwawasan Lingkungan.\u{201D}",
				'basis_document' => 'Profil Desa Cihawuk 2023 (S3)',
			),
			// Tujuh butir misi membentuk akronim C-I-H-A-W-U-K pada dokumen sumber.
			'mission' => array(
				'items' => array(
					'Ciptakan lingkungan masyarakat desa Cihawuk yang kondusif',
					'Ikuti semua program pemerintah dengan masyarakat yang berkesinambungan',
					'Hindari dari korupsi dan praktek KKN',
					'Aspirasi masyarakat dan potensi yang ada dilingkungan desa Cihawuk kita gali bersama',
					'Wujudkan pembangunan dan kesejahteraan lingkungan desa Cihawuk yang maju dan berdaya saing',
					'Utamakan kebersamaan masyarakat dengan pemerintah desa Cihawuk yang maju, mandiri dan berbudaya',
					'Kembangkan karakter yang baik, jujur, adil dan transparansi. Mengedepankan kejujuran, keadilan, transparansi dalam kehidupan sehari-hari baik dalam pemerintahan maupun dengan masyarakat Desa.',
				),
				'basis_document' => 'Profil Desa Cihawuk 2023 (S3). Misi Kecamatan Kertasari pada dokumen yang sama tidak digabung ke sini.',
			),
			'history' => array(
				'founded_year' => 1984,
				'founded_claim_note' => 'Profil Desa 2023 menyebut Cihawuk sebagai hasil pemekaran Desa Sukapura pada 1984. Tanggal 14 April dan dasar keputusannya masih berupa klaim dokumen dan memerlukan verifikasi nomor surat.',
				'narrative' => 'Menurut Profil Desa Cihawuk 2023, Desa Cihawuk merupakan hasil pemekaran dari Desa Sukapura pada tahun 1984. Naskah sejarah lengkap belum diverifikasi dan masih menunggu pemeriksaan dokumen pendukung oleh pengelola desa.',
			),
			'geography' => array(
				'elevation_masl' => 1514,
				'avg_temperature_c' => 16.3,
				'rainfall_mm' => 1565,
				'rainy_months' => 6,
				'land_use' => array(
					array('label' => 'Tanah kering', 'value' => '327,41'),
					array('label' => 'Tanah basah', 'value' => '0,42'),
					array('label' => 'Perkebunan', 'value' => '10,00'),
					array('label' => 'Fasilitas umum', 'value' => '47,61'),
					array('label' => 'Hutan', 'value' => '545,91'),
				),
				'boundaries' => array(
					array('label' => 'Utara', 'value' => 'Desa Resmi tingal (ejaan asli sumber)'),
					array('label' => 'Selatan', 'value' => 'Desa Padaawas'),
					array('label' => 'Timur', 'value' => 'Desa Pasirwangi'),
					array('label' => 'Barat', 'value' => 'Desa Sukapura, Desa Cibeureum, Desa Cikembang'),
				),
				'area_conflict_note' => 'Luas wilayah belum punya angka kanonis. Identitas S1 dan S2 mencatat 932,35 ha, total penggunaan lahan S1 931,35 ha, dan S4 mencatat 931,00 ha. Ketiganya disimpan apa adanya sampai pengelola merekonsiliasi.',
			),
		);

		// Blok naratif berasal dari S3; blok data berasal dari S1.
		$block_sources = array(
			'greeting' => 'S3', 'vision' => 'S3', 'mission' => 'S3',
			'identity' => 'S1', 'history' => 'S1', 'geography' => 'S1',
		);
		$source_titles = array('S1' => 'Potensi Desa Cihawuk 2023', 'S3' => 'Kata Pengantar Profil Cihawuk 2023');

		$order = 0;
		foreach ($definitions as $key => $definition)
		{
			$order += 10;
			$source_code = isset($block_sources[$key]) ? $block_sources[$key] : 'S1';
			$existing = $this->id_of('profile_blocks', array('block_key' => $key));
			$block_id = $this->insert_if_missing('profile_blocks', array('block_key' => $key), array(
				'public_id' => $this->CI->crypto->public_id(),
				'title' => $definition['label'],
				'status' => 'draft',
				'verification_status' => 'unverified',
				'source_id' => isset($bodies[$key]) ? $sources[$source_code] : NULL,
				'source_note' => isset($bodies[$key]) ? $source_titles[$source_code] : NULL,
				// Sambutan wajib berperiode supaya naskah lama tidak tampil seolah sambutan
				// pejabat yang menjabat hari ini (ProfileService menolak terbit tanpa ini).
				'period_start' => ($key === 'greeting') ? 2024 : NULL,
				'sort_order' => $definition['sort_order'],
				'created_at' => $now,
				'updated_at' => $now,
			));
			if ( ! isset($bodies[$key]))
			{
				continue;
			}
			// Blok yang sudah pernah diisi tidak ditimpa. Blok yang terlanjur dibuat kosong
			// (mis. sambutan/visi/misi sebelum S3 dapat dibaca) diberi versi pertamanya.
			if ($existing !== NULL)
			{
				$has_version = $this->CI->db->where('block_id', $block_id)->count_all_results('profile_block_versions');
				if ($has_version > 0)
				{
					continue;
				}
			}
			db_must($this->CI->db->insert('profile_block_versions', array(
				'block_id' => $block_id,
				'version_no' => 1,
				'body_json' => json_encode($bodies[$key], JSON_UNESCAPED_UNICODE),
				'change_note' => 'Seed dari dokumen sumber; belum diverifikasi.',
				'created_at' => $now,
			)), 'seed profile_block_versions');
			$version_id = (int) $this->CI->db->insert_id();
			$this->count('profile_block_versions', TRUE);
			db_must($this->CI->db->where('id', $block_id)->update('profile_blocks', array(
				'current_version_id' => $version_id,
				// Blok yang baru diisi ulang ikut mendapat provenans dan periodenya.
				'source_id' => $sources[$source_code],
				'source_note' => $source_titles[$source_code],
				'period_start' => ($key === 'greeting') ? 2024 : NULL,
				'updated_at' => $now,
			)), 'seed profile_blocks.version');
		}

		// Linimasa kepala desa menurut Profil Desa 2023. "Sampai sekarang" pada dokumen itu
		// berarti sampai dokumen dibuat, bukan sampai hari ini, jadi ditandai sebagai klaim.
		$terms = array(
			array('Buloh', 1984, 1994, 0),
			array('Ebi Rahmat', 1994, 1998, 0),
			array('Ate Suhanda', 1998, 1999, 0),
			array('U. Juhana', 1999, 2005, 0),
			array('S. Tahyadi', 2005, 2006, 0),
			array('Aep Saepuloh', 2006, 2012, 0),
			array('Aep Saepuloh', 2012, 2018, 0),
			array('Aep Saepudin S.Pd S.E', 2018, 2019, 0),
			array('Yaya Dores', 2019, NULL, 1),
		);
		$sort = 0;
		foreach ($terms as $t)
		{
			$sort += 10;
			$this->insert_if_missing('leadership_terms', array(
				'person_name' => $t[0], 'year_start' => $t[1],
			), array(
				'public_id' => $this->CI->crypto->public_id(),
				'position_title' => 'Kepala Desa',
				'year_end' => $t[2],
				'ongoing_claim' => $t[3],
				'summary' => NULL,
				'source_id' => $sources['S1'],
				'source_note' => $t[3] ? 'Profil Desa 2023 menulis "sampai sekarang"; berarti sampai dokumen dibuat.' : 'Profil Desa Cihawuk 2023',
				'verification_status' => 'unverified',
				'publication_status' => 'draft',
				'sort_order' => $sort,
				'created_at' => $now,
				'updated_at' => $now,
			));
		}
	}

	/**
	 * Dataset awal Kependudukan 2023 (modul-backend bagian 11).
	 *
	 * Sengaja berstatus DRAFT dengan nilai pending: dokumen sumber belum diverifikasi,
	 * dan spesifikasi mewajibkan pengelola menekan verifikasi lalu terbit sendiri.
	 * Menjalankan seeder tidak boleh menerbitkan angka ke halaman publik.
	 */
	protected function datasets(array $sources)
	{
		if ($this->id_of('datasets', array('slug' => 'kependudukan-2023')) !== NULL)
		{
			$this->count('datasets', FALSE);
			return;
		}
		$now = $this->now();

		db_must($this->CI->db->insert('datasets', array(
			'public_id' => $this->CI->crypto->public_id(),
			'slug' => 'kependudukan-2023',
			'name' => 'Kependudukan Desa Cihawuk',
			'theme' => 'population',
			'description' => 'Jumlah penduduk dan kepala keluarga menurut dokumen profil desa tahun 2023.',
			'coverage' => 'Desa Cihawuk',
			'sensitivity' => 'public',
			'status' => 'draft',
			'sort_order' => 10,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'seed datasets');
		$dataset_id = (int) $this->CI->db->insert_id();
		$this->count('datasets', TRUE);

		db_must($this->CI->db->insert('dataset_versions', array(
			'dataset_id' => $dataset_id,
			'version_no' => 1,
			'period_year' => 2023,
			'period_label' => 'Kondisi Desember 2023 menurut dokumen sumber',
			'methodology' => 'Angka disalin apa adanya dari rekap penduduk pada Profil Desa Cihawuk 2023 tanpa mengubah definisi indikator.',
			'quality_note' => 'Belum direkonsiliasi dengan S2 dan S4. Total pendidikan pada sumber melebihi jumlah penduduk sehingga kategorinya berpotensi tumpang tindih.',
			'source_id' => $sources['S1'],
			'source_note' => 'Profil Desa Cihawuk 2023',
			'validation_status' => 'pending',
			'created_at' => $now,
		)), 'seed dataset_versions');
		$version_id = (int) $this->CI->db->insert_id();
		$this->count('dataset_versions', TRUE);
		db_must($this->CI->db->where('id', $dataset_id)->update('datasets', array('current_version_id' => $version_id)), 'seed datasets.version');

		// code => chart_type. Laki-laki dan perempuan membentuk komposisi population_sex.
		$series = array(
			'population_total' => 'number',
			'population_male' => 'donut',
			'population_female' => 'donut',
			'households' => 'number',
		);
		$order = 10;
		foreach ($series as $code => $chart)
		{
			$indicator_id = $this->id_of('statistic_indicators', array('code' => $code));
			if ($indicator_id === NULL)
			{
				continue;
			}
			db_must($this->CI->db->insert('dataset_series', array(
				'dataset_version_id' => $version_id,
				'indicator_id' => $indicator_id,
				'display_order' => $order,
				'chart_type' => $chart,
				'is_composition' => ($chart === 'donut') ? 1 : 0,
				'created_at' => $now,
			)), 'seed dataset_series');
			$this->count('dataset_series', TRUE);
			$order += 10;

			// Nilai 2023 milik indikator ini ikut versi dataset, tetapi statusnya tetap pending.
			db_must($this->CI->db->where(array('indicator_id' => $indicator_id, 'source_year' => 2023))
				->update('statistic_values', array('dataset_version_id' => $version_id)), 'seed statistic_values.dataset');
		}
	}

	/**
	 * Instalasi lama menyimpan section statistik sebagai source_year + indicators.
	 * Draftnya ditulis ulang ke bentuk dataset; snapshot publikasi TIDAK disentuh supaya
	 * halaman publik hanya berubah ketika pengelola menerbitkan ulang beranda.
	 */
	protected function repair_home_statistics_draft()
	{
		$page_id = $this->id_of('cms_pages', array('page_key' => 'home'));
		if ($page_id === NULL)
		{
			return;
		}
		$section = $this->CI->db->where(array('page_id' => $page_id, 'section_type' => 'statistics'))
			->where('archived_at IS NULL', NULL, FALSE)->order_by('id')->limit(1)->get('cms_sections')->row();
		if ( ! $section OR ! $section->current_version_id)
		{
			return;
		}
		$version = $this->CI->db->get_where('cms_section_versions', array('id' => (int) $section->current_version_id))->row();
		$config = $version ? json_decode((string) $version->config_json, TRUE) : NULL;
		if ( ! is_array($config) OR ! array_key_exists('indicators', $config))
		{
			$this->count('cms_section_versions', FALSE);
			return;
		}

		$config = array(
			'dataset_slug' => 'kependudukan-2023',
			'series' => array_slice(array_values((array) $config['indicators']), 0, 4),
			'source_note' => (string) ($config['source_note'] ?? 'Data Profil Desa 2023'),
		);
		$now = $this->now();
		db_must($this->CI->db->insert('cms_section_versions', array(
			'section_id' => (int) $section->id,
			'version_no' => (int) $version->version_no + 1,
			'title' => $version->title,
			'subtitle' => $version->subtitle,
			'layout_variant' => $version->layout_variant,
			'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
			'created_at' => $now,
		)), 'seed cms_section_versions.repair');
		$new_version_id = (int) $this->CI->db->insert_id();
		$this->count('cms_section_versions', TRUE);
		db_must($this->CI->db->where('id', (int) $section->id)->update('cms_sections', array(
			'current_version_id' => $new_version_id,
			'updated_at' => $now,
		)), 'seed cms_sections.repair');
	}

	/**
	 * Modul aplikasi (modul-backend §6.2). Modul yang belum dibangun tetap `disabled`
	 * agar tidak ada klaim fitur yang belum ada.
	 */
	protected function feature_modules()
	{
		$now = $this->now();
		// code, nama, state awal, depends_on, prefix route publik, prefix route admin
		$modules = array(
			array('public_website', 'Website publik', 'active', array(), '', NULL),
			array('complaints', 'Pengaduan dan aspirasi', 'active', array(), 'lapor,lacak,layanan', 'admin/laporan'),
			array('citizen_accounts', 'Akun warga', 'active', array(), 'daftar', 'warga'),
			array('news', 'Berita dan pengumuman', 'active', array('public_website'), 'berita', 'admin/konten/berita'),
			array('agenda', 'Agenda kegiatan', 'active', array('public_website'), 'agenda', 'admin/konten/agenda'),
			array('gallery', 'Galeri', 'active', array('public_website'), 'galeri', 'admin/konten/galeri'),
			array('public_documents', 'Dokumen publik', 'active', array('public_website'), 'dokumen', 'admin/konten/dokumen'),
			array('village_data', 'Data desa dan statistik', 'active', array('public_website'), 'data-desa', 'admin/statistik,admin/dataset'),
			array('village_potentials', 'Potensi desa', 'active', array('public_website'), 'potensi', 'admin/konten/potensi'),
			array('organization', 'Struktur organisasi', 'active', array(), 'pemerintahan', 'admin/struktur'),
			array('facilities', 'Direktori fasilitas', 'active', array('village_data'), 'fasilitas', 'admin/fasilitas'),
			array('budget_transparency', 'Transparansi anggaran', 'active', array('public_website'), 'transparansi', 'admin/keuangan'),
			array('assets', 'Aset dan inventaris', 'active', array(), NULL, 'admin/aset,admin/inventaris,admin/audit-aset'),
			array('public_asset_qr', 'Halaman publik QR aset', 'active', array('assets'), 'aset', NULL),
			array('warehouse', 'Gudang persediaan', 'active', array(), NULL, 'admin/gudang'),
			array('umkm_directory', 'Direktori UMKM', 'active', array('village_potentials'), 'umkm', 'admin/umkm'),
			array('village_letters', 'Surat desa', 'disabled', array('citizen_accounts'), 'surat', 'admin/surat'),
		);
		foreach ($modules as $i => $m)
		{
			$public_prefix = ($m[4] === NULL || $m[4] === '') ? NULL : $m[4];
			$existing = $this->id_of('feature_modules', array('code' => $m[0]));
			$this->insert_if_missing('feature_modules', array('code' => $m[0]), array(
				'name' => $m[1],
				'state' => $m[2],
				'depends_on_json' => json_encode($m[3]),
				'public_route_prefix' => $public_prefix,
				'admin_route_prefix' => $m[5],
				'sort_order' => ($i + 1) * 10,
				'created_at' => $now,
				'updated_at' => $now,
			));
			if ($existing !== NULL)
			{
				// Prefix route dan dependency adalah fakta kode, bukan pilihan pengelola:
				// disinkronkan ulang agar modul baru langsung terjaga guard route.
				// `state` sengaja TIDAK disentuh supaya keputusan pengelola tidak ditimpa.
				db_must($this->CI->db->where('id', $existing)->update('feature_modules', array(
					'name' => $m[1],
					'depends_on_json' => json_encode($m[3]),
					'public_route_prefix' => $public_prefix,
					'admin_route_prefix' => $m[5],
					'sort_order' => ($i + 1) * 10,
				)), 'seed feature_modules.sync');
			}
		}
	}

	protected function units_and_areas()
	{
		$now = $this->now();
		// Unit contoh untuk disposisi; sesuaikan dengan SOTK desa yang berlaku.
		$units = array(
			'SEKRETARIAT' => 'Sekretariat Desa',
			'PELAYANAN' => 'Pelayanan Umum',
			'PEMERINTAHAN' => 'Pemerintahan dan Ketertiban',
			'KESRA' => 'Kesejahteraan Masyarakat',
			'PEMBANGUNAN' => 'Pembangunan dan Lingkungan',
		);
		foreach ($units as $code => $name)
		{
			$this->insert_if_missing('organizational_units', array('code' => $code), array('name' => $name, 'active' => 1, 'created_at' => $now, 'updated_at' => $now));
		}
		// Root wilayah desa untuk statistik. Nama dusun tidak dibuat karena belum terkonfirmasi.
		$this->insert_if_missing('administrative_areas', array('code' => 'DESA-CIHAWUK'), array(
			'type' => 'village', 'name' => 'Desa Cihawuk', 'parent_id' => NULL, 'verification_status' => 'verified',
			'created_at' => $now, 'updated_at' => $now,
		));
	}

	protected function calendar_and_sla()
	{
		$now = $this->now();
		$calendar_id = $this->id_of('business_calendars', array('name' => 'Kalender Pelayanan Desa (contoh)'));
		if ($calendar_id === NULL)
		{
			$schedule = array();
			foreach (array(1, 2, 3, 4, 5) as $dow)
			{
				$schedule[(string) $dow] = array(array('start' => '08:00', 'end' => '16:00'));
			}
			db_must($this->CI->db->insert('business_calendars', array(
				'name' => 'Kalender Pelayanan Desa (contoh)',
				'timezone' => 'Asia/Jakarta',
				'weekly_schedule_json' => json_encode($schedule),
				'is_example' => 1,
				'active' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			)), 'seed business_calendars');
			$calendar_id = (int) $this->CI->db->insert_id();
			$this->count('business_calendars', TRUE);
		}
		// Usulan konfigurasi Cihawuk untuk diskusi operasional, bukan tenggat hukum nasional.
		$this->insert_if_missing('sla_policies', array('code' => 'DEFAULT'), array(
			'name' => 'Kebijakan layanan standar (usulan)',
			'calendar_id' => $calendar_id,
			'verification_days' => 3,
			'first_response_days' => 5,
			'confirmation_days' => 10,
			'resolution_days' => NULL,
			'pause_rules_json' => json_encode(array('pause_resolution_on_needs_information' => TRUE)),
			'auto_close_enabled' => 0,
			'active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		));
	}

	protected function ticket_categories()
	{
		$now = $this->now();
		$sla = $this->id_of('sla_policies', array('code' => 'DEFAULT'));
		$unit = function ($code) { return $this->id_of('organizational_units', array('code' => $code)); };
		$categories = array(
			// code, name, report_type, unit, sensitive, location_required, description
			array('INFRA', 'Jalan, jembatan dan infrastruktur', 'complaint', 'PEMBANGUNAN', 0, 1, 'Kerusakan jalan, saluran air, penerangan, jembatan dan fasilitas umum.'),
			array('LINGKUNGAN', 'Kebersihan dan lingkungan', 'complaint', 'PEMBANGUNAN', 0, 1, 'Sampah, pencemaran, longsor ringan, dan kebersihan lingkungan.'),
			array('ADMINISTRASI', 'Pelayanan administrasi desa', 'complaint', 'PELAYANAN', 0, 0, 'Keluhan terkait pelayanan kantor desa.'),
			array('KETERTIBAN', 'Keamanan dan ketertiban', 'complaint', 'PEMERINTAHAN', 0, 0, 'Gangguan ketertiban lingkungan.'),
			array('BANSOS', 'Bantuan sosial', 'complaint', 'KESRA', 0, 0, 'Pertanyaan atau keluhan penyaluran bantuan sosial.'),
			array('PERILAKU_APARAT', 'Perilaku aparat atau petugas', 'complaint', 'SEKRETARIAT', 1, 0, 'Laporan mengenai perilaku aparat. Otomatis bersifat rahasia.'),
			array('PERLINDUNGAN', 'Perlindungan dan kasus sensitif', 'complaint', 'KESRA', 1, 0, 'Kasus yang menyangkut keselamatan atau privasi seseorang. Otomatis bersifat rahasia.'),
			array('USULAN_PEMBANGUNAN', 'Usulan pembangunan', 'aspiration', 'PEMBANGUNAN', 0, 0, 'Usulan kegiatan pembangunan atau perbaikan fasilitas.'),
			array('USULAN_KEGIATAN', 'Usulan kegiatan warga', 'aspiration', 'KESRA', 0, 0, 'Usulan kegiatan sosial, budaya, pemuda dan ekonomi warga.'),
			array('INFO_PROGRAM', 'Informasi program desa', 'information_request', 'SEKRETARIAT', 0, 0, 'Permintaan informasi program dan kegiatan desa.'),
			array('INFO_LAYANAN', 'Informasi layanan administrasi', 'information_request', 'PELAYANAN', 0, 0, 'Pertanyaan persyaratan dan alur layanan.'),
			array('LAINNYA', 'Lainnya', NULL, 'PELAYANAN', 0, 0, 'Topik yang tidak termasuk kategori lain.'),
		);
		foreach ($categories as $i => $c)
		{
			$this->insert_if_missing('ticket_categories', array('code' => $c[0]), array(
				'name' => $c[1], 'report_type' => $c[2], 'default_unit_id' => $unit($c[3]), 'is_sensitive' => $c[4],
				'location_required' => $c[5], 'description' => $c[6], 'sla_policy_id' => $sla, 'sort_order' => ($i + 1) * 10,
				'active' => 1, 'created_at' => $now, 'updated_at' => $now,
			));
		}
	}

	protected function content_categories()
	{
		$now = $this->now();
		$items = array(
			array('potential', 'Pertanian', 'pertanian'),
			array('potential', 'Alam', 'alam'),
			array('potential', 'UMKM', 'umkm'),
			array('potential', 'Budaya', 'budaya'),
			array('potential', 'Fasilitas Desa', 'fasilitas-desa'),
			array('news', 'Berita Desa', 'berita-desa'),
			array('news', 'Pengumuman', 'pengumuman'),
			array('news', 'Kegiatan Warga', 'kegiatan-warga'),
			array('document', 'Perencanaan', 'perencanaan'),
			array('document', 'Laporan', 'laporan'),
			array('document', 'Peraturan Desa', 'peraturan-desa'),
		);
		foreach ($items as $i => $item)
		{
			$this->insert_if_missing('content_categories', array('content_type' => $item[0], 'slug' => $item[2]), array(
				'name' => $item[1], 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
			));
		}
	}

	protected function navigation()
	{
		if ($this->CI->db->count_all('navigation_items') > 0)
		{
			return;
		}
		$now = $this->now();
		$insert = function ($menu, $label, $url, $order, $parent = NULL) use ($now) {
			db_must($this->CI->db->insert('navigation_items', array(
				'menu_key' => $menu, 'parent_id' => $parent, 'label' => $label, 'target_url' => $url,
				'sort_order' => $order, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
			)), 'seed navigation');
			$this->count('navigation_items', TRUE);
			return (int) $this->CI->db->insert_id();
		};
		$profil = $insert('main', 'Profil', '/profil', 10);
		$insert('main', 'Profil Desa', '/profil', 1, $profil);
		$insert('main', 'Sejarah', '/profil#sejarah', 2, $profil);
		$insert('main', 'Visi dan Misi', '/profil#visi-misi', 3, $profil);
		$insert('main', 'Kontak', '/kontak', 4, $profil);
		$insert('main', 'Pemerintahan', '/pemerintahan', 20);
		$insert('main', 'Potensi', '/potensi', 30);
		$info = $insert('main', 'Informasi', '/informasi', 40);
		$insert('main', 'Berita', '/berita', 1, $info);
		$insert('main', 'Agenda', '/agenda', 2, $info);
		$insert('main', 'Galeri', '/galeri', 3, $info);
		$insert('main', 'Dokumen Publik', '/dokumen', 4, $info);
		$insert('main', 'Data Desa', '/data-desa', 50);
		$layanan = $insert('main', 'Layanan Warga', '/layanan', 60);
		$insert('main', 'Panduan Layanan', '/layanan', 1, $layanan);
		$insert('main', 'Buat Laporan', '/lapor', 2, $layanan);
		$insert('main', 'Lacak Laporan', '/lacak', 3, $layanan);
		$insert('footer', 'Layanan Warga', '/layanan', 10);
		$insert('footer', 'Buat Laporan', '/lapor', 20);
		$insert('footer', 'Lacak Laporan', '/lacak', 30);
		$insert('footer', 'Data Desa', '/data-desa', 40);
		$insert('footer', 'Kebijakan Privasi', '/privasi', 50);
		$insert('footer', 'Ketentuan Layanan', '/ketentuan', 60);
	}

	/**
	 * Menu publik hasil seed: isinya sama dengan navigasi bawaan sehingga situs tidak berubah,
	 * tetapi kini dapat diatur pengelola. Idempotent: hanya dibuat bila lokasi menu masih kosong.
	 */
	protected function cms_menus()
	{
		$now = $this->now();
		$menus = array(
			'header' => array(
				array('Profil', '/profil', array(
					array('Profil Desa', '/profil'),
					array('Sejarah', '/profil#sejarah'),
					array('Visi dan Misi', '/profil#visi-misi'),
					array('Kontak', '/kontak'),
				)),
				array('Pemerintahan', '/pemerintahan', array()),
				array('Potensi', '/potensi', array()),
				array('Informasi', '/informasi', array(
					array('Berita', '/berita'),
					array('Agenda', '/agenda'),
					array('Galeri', '/galeri'),
					array('Dokumen Publik', '/dokumen'),
				)),
				array('Data Desa', '/data-desa', array()),
				array('Layanan Warga', '/layanan', array(
					array('Panduan Layanan', '/layanan'),
					array('Buat Laporan', '/lapor'),
					array('Lacak Laporan', '/lacak'),
				)),
			),
			'footer_primary' => array(
				array('Layanan Warga', '/layanan', array()),
				array('Buat Laporan', '/lapor', array()),
				array('Lacak Laporan', '/lacak', array()),
				array('Data Desa', '/data-desa', array()),
				array('Kebijakan Privasi', '/privasi', array()),
				array('Ketentuan Layanan', '/ketentuan', array()),
			),
		);

		foreach ($menus as $location => $items)
		{
			$menu_id = $this->id_of('cms_menus', array('location' => $location));
			if ($menu_id !== NULL)
			{
				$this->count('cms_menus', FALSE);
				continue;
			}
			db_must($this->CI->db->insert('cms_menus', array(
				'public_id' => $this->CI->crypto->public_id(),
				'location' => $location,
				'label' => $location === 'header' ? 'Menu utama (header)' : 'Footer — tautan utama',
				'status' => 'draft',
				'created_at' => $now,
				'updated_at' => $now,
			)), 'seed cms_menus');
			$menu_id = (int) $this->CI->db->insert_id();
			$this->count('cms_menus', TRUE);

			$order = 10;
			$snapshot_items = array();
			foreach ($items as $item)
			{
				list($label, $path, $children) = $item;
				$parent_id = $this->insert_menu_item($menu_id, NULL, $label, $path, $order, $now);
				$order += 10;
				$node = array('label' => $label, 'href' => $path, 'link_type' => 'route', 'children' => array());
				$child_order = 10;
				foreach ($children as $child)
				{
					$this->insert_menu_item($menu_id, $parent_id, $child[0], $child[1], $child_order, $now);
					$child_order += 10;
					$node['children'][] = array('label' => $child[0], 'href' => $child[1], 'link_type' => 'route');
				}
				$snapshot_items[] = $node;
			}

			// Terbitkan revisi 1 supaya situs publik langsung memakai menu ini.
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'menu',
				'target_id' => $menu_id,
				'revision_no' => 1,
				'snapshot_json' => json_encode(array(
					'location' => $location,
					'label' => $location === 'header' ? 'Menu utama (header)' : 'Footer — tautan utama',
					'items' => $snapshot_items,
				), JSON_UNESCAPED_UNICODE),
				'reason' => 'Seed awal menu navigasi.',
				'published_at' => $now,
			)), 'seed cms_publication_snapshots');
			$this->count('cms_publication_snapshots', TRUE);
			db_must($this->CI->db->where('id', $menu_id)->update('cms_menus', array(
				'status' => 'published', 'published_at' => $now, 'updated_at' => $now,
			)), 'seed cms_menus.publish');
		}
	}

	protected function insert_menu_item($menu_id, $parent_id, $label, $path, $order, $now)
	{
		db_must($this->CI->db->insert('cms_menu_items', array(
			'public_id' => $this->CI->crypto->public_id(),
			'menu_id' => (int) $menu_id,
			'parent_id' => $parent_id === NULL ? NULL : (int) $parent_id,
			'label' => $label,
			'link_type' => 'route',
			'route_path' => $path,
			'sort_order' => (int) $order,
			'is_enabled' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'seed cms_menu_items');
		$this->count('cms_menu_items', TRUE);
		return (int) $this->CI->db->insert_id();
	}

	/**
	 * Identitas dan tema awal = nilai yang sekarang dipakai situs, diterbitkan sebagai revisi 1
	 * sehingga pengelola punya titik rollback sejak awal. Logo desa sengaja dikosongkan:
	 * lambang kabupaten tidak boleh dipakai sebagai logo desa.
	 */
	protected function site_identity()
	{
		if ($this->CI->db->where(array('target_type' => 'site', 'target_id' => 0))->count_all_results('cms_publication_snapshots') > 0)
		{
			$this->count('cms_publication_snapshots', FALSE);
			return;
		}
		$this->CI->load->library('SiteSettingsService', NULL, 'site_service');
		$values = $this->CI->site_service->defaults();
		$hours = $this->CI->settings->get('site.service_hours');
		if (is_array($hours) && ! empty($hours['label']))
		{
			$values['identity']['service_hours'] = $hours['label'];
		}
		$this->CI->settings->set_default(SiteSettingsService::DRAFT_KEY, $values, 'site', FALSE);
		db_must($this->CI->db->insert('cms_publication_snapshots', array(
			'target_type' => 'site',
			'target_id' => 0,
			'revision_no' => 1,
			'snapshot_json' => json_encode($values, JSON_UNESCAPED_UNICODE),
			'reason' => 'Seed awal identitas dan tema situs.',
			'published_at' => $this->now(),
		)), 'seed cms_publication_snapshots');
		$this->count('cms_publication_snapshots', TRUE);
	}

	protected function settings()
	{
		$s = $this->CI->settings;
		$s->set_default('site.tagline', 'Kecamatan Kertasari, Kabupaten Bandung', 'site', TRUE);
		$s->set_default('site.contact', array('phone' => NULL, 'email' => NULL, 'whatsapp_public' => NULL, 'confirmed' => FALSE), 'site', TRUE);
		$s->set_default('site.service_hours', array('label' => 'Senin–Jumat, 08.00–16.00 WIB', 'is_example' => TRUE), 'site', TRUE);
		$s->set_default('site.privacy_contact', array('name' => 'Pengelola Layanan Digital Desa Cihawuk', 'channel' => 'Kantor Desa Cihawuk', 'confirmed' => FALSE), 'site', TRUE);
		$s->set_default('tickets.default_sla_policy', 'DEFAULT', 'tickets', FALSE);
		// Aturan field per jenis laporan (allowlist; bukan aturan PHP/SQL dari database).
		$s->set_default('tickets.field_rules', array(
			'complaint' => array('incident_date' => 'optional', 'location' => 'category', 'map_point' => 'optional'),
			'aspiration' => array('incident_date' => 'hidden', 'location' => 'optional', 'map_point' => 'optional'),
			'information_request' => array('incident_date' => 'hidden', 'location' => 'hidden', 'map_point' => 'hidden'),
		), 'tickets', FALSE);
		$s->set_default('tickets.anonymous_enabled', TRUE, 'tickets', TRUE);
		$s->set_default('home.hero_mode', 'three', 'home', TRUE);
		$s->set_default('map.default_center', NULL, 'map', TRUE);
	}

	/** @return array<string,int> kode sumber => id */
	/**
	 * Kategori dan lokasi aset awal (prompt-master 19.6).
	 *
	 * Tidak ada satu pun register aset yang diseed: berkas S5 belum tersedia dan mengarang
	 * daftar aset dilarang. Kategori disiapkan agar impor staging punya tempat berlabuh.
	 */
	protected function asset_master()
	{
		$now = $this->now();
		$categories = array(
			array('TANAH', 'Tanah', 'land'),
			array('PERALATAN', 'Peralatan dan mesin', 'equipment'),
			array('GEDUNG', 'Gedung dan bangunan', 'building'),
			array('JIJ', 'Jalan, irigasi, dan jaringan', 'infrastructure'),
			array('ASET-LAIN', 'Aset tetap lainnya', 'other'),
		);
		foreach ($categories as $i => $c)
		{
			$this->insert_if_missing('asset_categories', array('code' => $c[0]), array(
				'name' => $c[1],
				'parent_id' => NULL,
				'asset_class' => $c[2],
				'active' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			));
		}

		$this->insert_if_missing('asset_locations', array('code' => 'KANTOR-DESA'), array(
			'name' => 'Kantor Desa Cihawuk',
			'location_type' => 'building',
			'parent_id' => NULL,
			'address' => NULL,
			'is_sensitive' => 0,
			'active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		));
	}

	protected function source_documents()
	{
		$now = $this->now();
		$register = array(
			'S1' => array('3 Potensi Desa Cihawuk 2023(1).docx', 'Potensi Desa Cihawuk 2023', 2023, 'Identitas, wilayah, SDA, penduduk, komoditas, kelembagaan, sarana', '/^3 Potensi Desa Cihawuk 2023.*\.docx$/i'),
			'S2' => array('4 Perkembangan Desa Cihawuk 2023(1).docx', 'Perkembangan Desa Cihawuk 2023', 2023, 'Perkembangan penduduk, keluarga, ekonomi, pendidikan, layanan dan indikator', '/^4 Perkembangan Desa Cihawuk 2023.*\.docx$/i'),
			'S3' => array('2.Kata Pengantar Profil Cihawuk 2023 (1)(1).docx', 'Kata Pengantar Profil Cihawuk 2023', 2023, 'Pengantar, visi misi, sejarah, struktur dan aset visual yang perlu pemeriksaan', '/^2\.Kata Pengantar Profil Cihawuk 2023.*\.docx?$/i'),
			'S4' => array('Profil Desa dan Kelurahan(1).docx', 'Profil Desa dan Kelurahan (Desember 2021)', 2021, 'Arsip pembanding; tidak digabung sebagai statistik 2023', '/^Profil Desa dan Kelurahan.*\.docx$/i'),
		);
		$dir = ROOTPATH.'reference/documents/';
		$files = is_dir($dir) ? array_values(array_filter(scandir($dir), function ($f) use ($dir) { return is_file($dir.$f) && $f[0] !== '.'; })) : array();
		$ids = array();
		foreach ($register as $code => $r)
		{
			$checksum = NULL;
			$filename = $r[0];
			foreach ($files as $f)
			{
				if ( ! preg_match($r[4], $f))
				{
					continue;
				}
				// Bila format lama dan hasil konversinya sama-sama ada, parser hanya membaca
				// .docx, jadi checksum wajib menunjuk berkas yang benar-benar diimpor.
				$is_docx = (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'docx');
				if ($checksum !== NULL && ! $is_docx)
				{
					continue;
				}
				$checksum = hash_file('sha256', $dir.$f);
				$filename = $f;
				if ($is_docx)
				{
					break;
				}
			}
			$ids[$code] = $this->insert_if_missing('source_documents', array('source_code' => $code), array(
				'original_filename' => $filename,
				'title' => $r[1],
				'source_year' => $r[2],
				'usage_note' => $r[3].(($filename !== $r[0]) ? ' Nama file register awal: '.$r[0].'.' : ''),
				'checksum' => $checksum,
				'import_status' => 'not_imported',
				'created_at' => $now,
				'updated_at' => $now,
			));

			// Berkas sumber dapat berganti bentuk (mis. .doc dikonversi menjadi .docx agar
			// dapat diparse). Selama belum terimpor, register mengikuti berkas yang ada
			// sekarang supaya checksum tidak menunjuk berkas yang tidak pernah dibaca.
			$current = $this->CI->db->select('original_filename, checksum, import_status')
				->get_where('source_documents', array('source_code' => $code))->row();
			if ($current && $current->import_status !== 'imported' && $checksum !== NULL
				&& ($current->checksum !== $checksum OR $current->original_filename !== $filename))
			{
				db_must($this->CI->db->where('source_code', $code)->update('source_documents', array(
					'original_filename' => $filename,
					'checksum' => $checksum,
					'updated_at' => $now,
				)), 'seed source_documents.refresh');
			}
		}
		return $ids;
	}

	protected function village_profile(array $sources)
	{
		if ($this->CI->db->count_all('village_profiles') > 0)
		{
			return;
		}
		$now = $this->now();
		db_must($this->CI->db->insert('village_profiles', array(
			'village_name' => 'Cihawuk',
			'district' => 'Kertasari',
			'regency' => 'Bandung',
			'province' => 'Jawa Barat',
			'pum_code' => '320431.2006',
			'summary' => 'Desa Cihawuk berada di Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat. Data Profil Desa 2023 mencatat ketinggian sekitar 1.514 meter di atas permukaan laut dan pertanian hortikultura dataran tinggi sebagai bagian penting kehidupan warga.',
			'history_html' => '<p>Menurut Profil Desa 2023, Desa Cihawuk merupakan hasil pemekaran Desa Sukapura pada tahun 1984. Dokumen tersebut menyebut keputusan bupati bertanggal 14 April 1984; nomor keputusan belum terverifikasi.</p>',
			'vision_official' => NULL,
			'vision_summary' => 'Memantapkan Desa Cihawuk yang aman, maju, mandiri, dan berdaya saing melalui tata kelola pemerintah dan masyarakat yang semangat, baik, dan bersatu, dengan pembangunan yang berlandaskan keimanan, ketakwaan, budaya, dan wawasan lingkungan.',
			'mission_json' => json_encode(array(
				'Menciptakan lingkungan yang kondusif.',
				'Menjaga kesinambungan program pemerintah dan masyarakat.',
				'Mencegah praktik korupsi, kolusi, dan nepotisme.',
				'Menggali aspirasi dan potensi masyarakat.',
				'Mendorong pembangunan dan kesejahteraan warga.',
				'Memperkuat kebersamaan warga.',
				'Menjunjung kejujuran dan transparansi.',
			), JSON_UNESCAPED_UNICODE),
			'office_address' => NULL,
			'contacts_json' => NULL,
			'service_hours_json' => json_encode(array('label' => 'Senin–Jumat, 08.00–16.00 WIB', 'is_example' => TRUE), JSON_UNESCAPED_UNICODE),
			'source_year' => 2023,
			'source_note' => 'Ringkasan editorial dari S1–S3 (Profil Desa 2023). Bukan pengganti teks resmi.',
			'review_note' => "Perlu review sebelum terbit:\n- Teks resmi visi belum disalin dari S3 (versi di atas adalah ringkasan editorial).\n- Rumusan misi adalah tema untuk review, bukan kutipan resmi.\n- Nomor keputusan pemekaran 1984 belum terverifikasi.\n- Alamat kantor, kontak dan jam layanan belum dikonfirmasi (jam layanan contoh).",
			'publication_status' => 'draft',
			'created_at' => $now,
			'updated_at' => $now,
		)), 'seed village_profiles');
		$this->count('village_profiles', TRUE);
	}

	protected function statistics(array $sources)
	{
		$now = $this->now();
		$area_id = $this->id_of('administrative_areas', array('code' => 'DESA-CIHAWUK'));

		$indicators = array(
			// code, label, group, unit, type, composition, chart, definition, order
			array('population_total', 'Jumlah penduduk', 'population', 'orang', 'integer', NULL, 'bar', 'Jumlah seluruh penduduk menurut dokumen profil desa pada tahun sumber.', 10),
			array('population_male', 'Penduduk laki-laki', 'population', 'orang', 'integer', 'population_sex', 'bar', 'Jumlah penduduk laki-laki menurut dokumen profil desa.', 20),
			array('population_female', 'Penduduk perempuan', 'population', 'orang', 'integer', 'population_sex', 'bar', 'Jumlah penduduk perempuan menurut dokumen profil desa.', 30),
			array('households', 'Kepala keluarga', 'population', 'KK', 'integer', NULL, 'bar', 'Jumlah kepala keluarga menurut dokumen profil desa.', 40),
			array('hamlet_count', 'Jumlah dusun', 'government', 'dusun', 'integer', NULL, 'bar', 'Jumlah dusun menurut bagian pemerintahan dokumen profil desa. Nama dusun belum dikonfirmasi.', 50),
			array('elevation_masl', 'Ketinggian wilayah', 'geography', 'mdpl', 'integer', NULL, 'none', 'Ketinggian wilayah menurut identitas dokumen profil desa.', 60),
			array('crop_area_potato', 'Luas tanaman kentang', 'agriculture', 'ha', 'decimal', NULL, 'bar', 'Luas tanaman pada tahun sumber. Dapat terkait musim/pola tanam; tidak dijumlahkan sebagai luas wilayah.', 70),
			array('crop_area_cabbage', 'Luas tanaman kubis', 'agriculture', 'ha', 'decimal', NULL, 'bar', 'Luas tanaman pada tahun sumber. Dapat terkait musim/pola tanam; tidak dijumlahkan sebagai luas wilayah.', 80),
			array('crop_area_carrot', 'Luas tanaman wortel', 'agriculture', 'ha', 'decimal', NULL, 'bar', 'Luas tanaman pada tahun sumber. Dapat terkait musim/pola tanam; tidak dijumlahkan sebagai luas wilayah.', 90),
			array('crop_area_chili', 'Luas tanaman cabai', 'agriculture', 'ha', 'decimal', NULL, 'bar', 'Luas tanaman pada tahun sumber. Dapat terkait musim/pola tanam; tidak dijumlahkan sebagai luas wilayah.', 100),
			array('area_total_ha', 'Luas wilayah', 'geography', 'ha', 'decimal', NULL, 'none', 'Luas wilayah desa. Nilai kanonis belum ditetapkan karena sumber tidak konsisten.', 110),
		);
		foreach ($indicators as $i)
		{
			$this->insert_if_missing('statistic_indicators', array('code' => $i[0]), array(
				'label' => $i[1], 'group_code' => $i[2], 'unit' => $i[3], 'value_type' => $i[4], 'composition_group' => $i[5],
				'chart_type' => $i[6], 'definition' => $i[7], 'display_order' => $i[8], 'created_at' => $now, 'updated_at' => $now,
			));
		}

		// Observasi dari fakta awal §4.2 (dibaca penyusun spesifikasi dari dokumen sumber).
		// field_key, source, locator, raw, normalized, type, unit, year, year_label, status, create_value
		$observations = array(
			array('population_male', 'S1', 'S1 › Penduduk › Jumlah › Laki-laki', '3.510', '3510', 'integer', 'orang', 2023, NULL, 'pending', TRUE),
			array('population_female', 'S1', 'S1 › Penduduk › Jumlah › Perempuan', '3.299', '3299', 'integer', 'orang', 2023, NULL, 'pending', TRUE),
			array('population_total', 'S1', 'S1 › Penduduk › Jumlah › Total', '6.809', '6809', 'integer', 'orang', 2023, NULL, 'pending', TRUE),
			array('households', 'S1', 'S1 › Penduduk › Jumlah › Kepala Keluarga', '2.174', '2174', 'integer', 'KK', 2023, NULL, 'pending', TRUE),
			array('population_male', 'S2', 'S2 › Perkembangan Penduduk › Jumlah tahun ini › Laki-laki', '3.510', '3510', 'integer', 'orang', 2023, NULL, 'pending', FALSE),
			array('population_female', 'S2', 'S2 › Perkembangan Penduduk › Jumlah tahun ini › Perempuan', '3.299', '3299', 'integer', 'orang', 2023, NULL, 'pending', FALSE),
			array('households', 'S2', 'S2 › Perkembangan Penduduk › Jumlah tahun ini › Kepala Keluarga', '2.174', '2174', 'integer', 'KK', 2023, NULL, 'pending', FALSE),
			array('population_male', 'S2', 'S2 › Perkembangan Penduduk › Jumlah tahun lalu › Laki-laki', '3.454', '3454', 'integer', 'orang', 2022, 'Tahun lalu (label S2; pemetaan ke 2022 adalah inferensi)', 'pending', TRUE),
			array('population_female', 'S2', 'S2 › Perkembangan Penduduk › Jumlah tahun lalu › Perempuan', '3.263', '3263', 'integer', 'orang', 2022, 'Tahun lalu (label S2; pemetaan ke 2022 adalah inferensi)', 'pending', TRUE),
			array('hamlet_count', 'S1', 'S1 › Pemerintahan › Jumlah dusun', '4', '4', 'integer', 'dusun', 2023, NULL, 'pending', TRUE),
			array('elevation_masl', 'S1', 'S1 › Identitas › Ketinggian', '1.514', '1514', 'integer', 'mdpl', 2023, NULL, 'pending', TRUE),
			array('population_male', 'S4', 'S4 › Penduduk › Jumlah › Laki-laki', '3.508', '3508', 'integer', 'orang', 2021, NULL, 'pending', TRUE),
			array('population_female', 'S4', 'S4 › Penduduk › Jumlah › Perempuan', '3.288', '3288', 'integer', 'orang', 2021, NULL, 'pending', TRUE),
			array('population_total', 'S4', 'S4 › Penduduk › Jumlah › Total', '6.796', '6796', 'integer', 'orang', 2021, NULL, 'pending', TRUE),
			array('households', 'S4', 'S4 › Penduduk › Jumlah › Kepala Keluarga', '2.011', '2011', 'integer', 'KK', 2021, NULL, 'pending', TRUE),
			array('crop_area_potato', 'S1', 'S1 › Komoditas › Tanaman Pangan/Hortikultura › Kentang › Luas', '80', '80.00', 'decimal', 'ha', 2023, NULL, 'pending', TRUE),
			array('crop_area_cabbage', 'S1', 'S1 › Komoditas › Tanaman Pangan/Hortikultura › Kubis › Luas', '70', '70.00', 'decimal', 'ha', 2023, NULL, 'pending', TRUE),
			array('crop_area_carrot', 'S1', 'S1 › Komoditas › Tanaman Pangan/Hortikultura › Wortel › Luas', '70', '70.00', 'decimal', 'ha', 2023, NULL, 'pending', TRUE),
			array('crop_area_chili', 'S1', 'S1 › Komoditas › Tanaman Pangan/Hortikultura › Cabai › Luas', '15', '15.00', 'decimal', 'ha', 2023, NULL, 'pending', TRUE),
			// Konflik luas: disimpan sebagai observasi terpisah, tanpa nilai kanonis.
			array('area_total_ha', 'S1', 'S1 › Identitas › Luas wilayah', '932,35', '932.35', 'decimal', 'ha', 2023, NULL, 'conflict', FALSE),
			array('area_total_ha', 'S1', 'S1 › Penggunaan Lahan › Total', '931,35', '931.35', 'decimal', 'ha', 2023, NULL, 'conflict', FALSE),
			array('area_total_ha', 'S2', 'S2 › Identitas › Luas wilayah', '932,35', '932.35', 'decimal', 'ha', 2023, NULL, 'conflict', FALSE),
			array('area_total_ha', 'S4', 'S4 › Identitas › Luas wilayah', '931,00', '931.00', 'decimal', 'ha', 2021, NULL, 'conflict', FALSE),
			// Koordinat mentah: tanda lintang belum dipastikan, tidak dijadikan pin peta.
			array('coordinate_latitude_raw', 'S1', 'S1 › Identitas › Koordinat › Lintang', '7,1999', NULL, 'text', 'derajat', 2023, NULL, 'conflict', FALSE),
			array('coordinate_longitude_raw', 'S1', 'S1 › Identitas › Koordinat › Bujur', '107,7050', NULL, 'text', 'derajat', 2023, NULL, 'pending', FALSE),
			array('pum_code', 'S1', 'S1 › Identitas › Kode PUM', '320431.2006', '320431.2006', 'code', NULL, 2023, NULL, 'pending', FALSE),
		);
		foreach ($observations as $o)
		{
			$obs_id = $this->insert_if_missing('source_observations', array(
				'source_id' => $sources[$o[1]], 'source_locator' => $o[2], 'field_key' => $o[0],
			), array(
				'batch_id' => NULL, 'field_label' => NULL, 'raw_value' => $o[3], 'normalized_value' => $o[4], 'value_type' => $o[5],
				'unit' => $o[6], 'source_year' => $o[7], 'year_label' => $o[8], 'validation_status' => $o[9],
				'review_note' => 'Seed dari fakta awal spesifikasi §4.2; cocokkan dengan dokumen sumber sebelum diterima.',
				'created_at' => $now, 'updated_at' => $now,
			));
			if ($o[10])
			{
				$indicator_id = $this->id_of('statistic_indicators', array('code' => $o[0]));
				$this->insert_if_missing('statistic_values', array('indicator_id' => $indicator_id, 'source_year' => $o[7], 'area_id' => $area_id), array(
					'year_label' => $o[8], 'numeric_value' => $o[4], 'text_value' => NULL, 'canonical_observation_id' => $obs_id,
					'source_id' => $sources[$o[1]], 'verification_status' => 'pending', 'publication_status' => 'draft',
					'created_at' => $now, 'updated_at' => $now,
				));
			}
		}
	}

	protected function data_issues(array $sources)
	{
		$now = $this->now();
		$issues = array(
			array('AREA_INCONSISTENT', 'S1', 'Luas wilayah tidak konsisten', 'Identitas S1/S2 mencatat 932,35 ha; total penggunaan lahan S1 931,35 ha; S4 931,00 ha.', 'Disimpan sebagai observasi terpisah; nilai kanonis belum ditetapkan.', 'high'),
			array('PUBLIC_FACILITY_SUBTOTAL', 'S1', 'Subtotal fasilitas umum perlu diperiksa', 'S1 memuat tanah kas desa dan tanah bengkok masing-masing 15,50 ha serta total 47,61 ha.', 'Periksa struktur induk-anak agar tanah kas desa tidak terhitung dua kali.', 'medium'),
			array('LATITUDE_SIGN', 'S1', 'Tanda lintang koordinat belum pasti', 'S1/S2 menulis lintang 7,1999 tanpa penanda lintang selatan; bujur 107,7050.', 'Nilai mentah disimpan; tanda tidak diubah otomatis dan pin peta final tidak dipasang sampai dikonfirmasi.', 'high'),
			array('FOREIGN_VILLAGE_SOURCE', 'S2', 'Rujukan sumber desa lain', 'S2 mencantumkan "Data Desa Tribaktimulya".', 'Dicatat sebagai issue; teks asli tidak diganti diam-diam.', 'medium'),
			array('BOUNDARY_MISMATCH', 'S1', 'Batas wilayah berbeda antar sumber', 'Daftar batas S1 berbeda dari S4. S1: utara Desa Resmi tingal, selatan Desa Padaawas, timur Desa Pasirwangi, barat Desa Sukapura/Desa Cibeureum/Desa Cikembang (ejaan asli).', 'Ditampilkan per tahun di area review; polygon resmi tidak digambar dari teks.', 'medium'),
			array('OFFICIALS_HISTORICAL', 'S1', 'Data pejabat bersifat historis', 'Dokumen menyebut Yaya Dores pada data 2023 dan narasi sejak 2019.', 'Tidak dinyatakan masih menjabat pada 2026 tanpa pembaruan dari pengelola.', 'medium'),
			array('REGULATION_LABEL', 'S3', 'Label aturan pada narasi struktur', 'Narasi struktur S3 memuat "PP No. 84 Tahun 2015".', 'Tidak disalin sebagai rujukan hukum terverifikasi; teks asli dan catatan review dipisahkan.', 'low'),
			array('EDUCATION_TOTALS', 'S1', 'Kategori dan total pendidikan perlu rekonsiliasi', 'Beberapa kategori/total demografi perlu rekonsiliasi; S1 menampilkan total pendidikan 10.598.', 'Tidak dibuat pie chart yang mengasumsikan kategori saling lepas.', 'medium'),
			array('EMPTY_AND_DASH_CELLS', 'S1', 'Sel kosong, tanda minus dan pilihan Ada/Tidak', 'Banyak sel berisi kosong, "-", atau pilihan "Ada/Tidak".', 'Tidak otomatis dianggap nol atau jawaban terpilih; tanda minus ambigu menjadi null dengan penanda sumber.', 'low'),
			array('DOC_VISUAL_ASSETS', 'S3', 'Aset visual dalam DOC belum diinspeksi', 'S3 mengandung gambar, nama, struktur dan peta.', 'Ekstraksi teks belum membuktikan pasangan foto-nama atau batas peta; inspeksi visual sebelum digunakan.', 'medium'),
			array('ORG_CHART_MISMATCH', 'S3', 'Dua susunan perangkat yang berbeda dalam satu dokumen', 'S3 memuat halaman foto perangkat (nama diikuti jabatan) dan bagan "PP 84/2015" yang memberi pasangan berbeda. Contoh: halaman foto menempatkan Agus Haryadi sebagai Sekretaris Desa, sedangkan bagan menempatkan Cahya Setiadi. Bagan juga memuat nama yang tidak ada di halaman foto (Imam Sihabudin, Egi Nugraha, Ajid Achmadi, Ujang Juhana).', 'Yang dipakai adalah pasangan halaman foto, karena urutannya adalah alur teks dokumen dan dikuatkan tanda tangan sambutan, bagan BPD serta nama berkas foto. Teks bagan tersimpan di kotak gambar yang berurutan menurut lapisan, bukan menurut bacaan, sehingga pasangannya tidak diturunkan secara mekanis. Perlu pengelola membaca gambar bagannya langsung.', 'high'),
			array('BPD_TERM_LABEL', 'S3', 'Periode BPD tertulis 2019-2027', 'Bagan BPD S3 menulis periode 2019-2027 dengan Ketua Eneng Santi Fatmawati, Sekretaris Budi Kusnadi, Wakil Ketua Anjar Fauji dan empat anggota.', 'Dicatat sebagai klaim sumber. BPD belum dibuat sebagai unit terpisah karena susunannya berasal dari kotak gambar yang sama dan belum dikonfirmasi.', 'medium'),
		);
		foreach ($issues as $i)
		{
			$this->insert_if_missing('data_issues', array('issue_code' => $i[0]), array(
				'source_id' => $sources[$i[1]], 'title' => $i[2], 'description' => $i[3], 'system_decision' => $i[4],
				'severity' => $i[5], 'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
			));
		}
	}

	protected function government(array $sources)
	{
		$now = $this->now();
		$head = $this->insert_if_missing('official_positions', array('title' => 'Kepala Desa'), array(
			'parent_id' => NULL, 'unit_id' => NULL, 'duties_summary' => NULL, 'sort_order' => 10, 'publication_status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
		));
		$this->insert_if_missing('official_positions', array('title' => 'Sekretaris Desa'), array(
			'parent_id' => $head, 'unit_id' => $this->id_of('organizational_units', array('code' => 'SEKRETARIAT')), 'duties_summary' => NULL,
			'sort_order' => 20, 'publication_status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
		));
		$this->insert_if_missing('officials', array('position_id' => $head, 'name' => 'Yaya Dores'), array(
			'photo_media_id' => NULL, 'term_start' => NULL, 'term_end' => NULL, 'source_id' => $sources['S1'],
			'source_note' => 'Disebut pada data 2023 dan narasi sejak 2019. Status jabatan 2026 belum dikonfirmasi.',
			'verification_status' => 'unverified', 'publication_status' => 'draft', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
		));
	}

	/**
	 * Periode struktur organisasi 2019 sampai dokumen 2023 (prompt-master 18.6).
	 *
	 * Seluruhnya DRAFT. Hanya jabatan yang namanya benar-benar tertulis pada dokumen sumber
	 * yang dibuat; jabatan lain tidak dikarang. Foto tidak dipasangkan karena S3 memuat foto
	 * dari desa lain dan pasangan foto-nama belum diperiksa manual.
	 */
	protected function organization(array $sources)
	{
		$now = $this->now();
		$period_id = $this->insert_if_missing('org_periods', array('name' => 'Periode dokumen 2023'), array(
			'public_id' => $this->CI->crypto->public_id(),
			'year_start' => 2019,
			'year_end' => NULL,
			'status' => 'draft',
			'is_public_default' => 0,
			'note' => 'Disusun dari halaman foto perangkat pada S3 (Kata Pengantar Profil Cihawuk 2023). Bagan PP 84/2015 pada dokumen yang sama memberi pasangan nama-jabatan yang berbeda; lihat isu data ORG_CHART_MISMATCH.',
			'created_at' => $now,
			'updated_at' => $now,
		));

		$unit_id = $this->insert_if_missing('org_units', array('period_id' => $period_id, 'name' => 'Pemerintah Desa Cihawuk'), array(
			'public_id' => $this->CI->crypto->public_id(),
			'unit_type' => 'village_government',
			'parent_id' => NULL,
			'sort_order' => 10,
			'active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		));

		$head_id = $this->insert_if_missing('org_positions', array('period_id' => $period_id, 'title' => 'Kepala Desa'), array(
			'public_id' => $this->CI->crypto->public_id(),
			'unit_id' => $unit_id,
			'parent_id' => NULL,
			'level' => 1,
			'duties_public' => NULL,
			'sort_order' => 10,
			'active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		));

		/*
		| Susunan perangkat desa menurut halaman foto S3, yang dibaca berpasangan
		| nama -> jabatan. Arah pembacaan itu dikuatkan empat bukti bebas: tanda tangan
		| sambutan (Yaya Dores = Kepala Desa), bagan BPD (Eneng Santi Fatmawati =
		| Ketua BPD), serta nama berkas `Agus haryadi (sekdes).png` dan
		| `Aang nujaman (kaur perencanaan).png` yang tercatat di docs/data-issues.md.
		|
		| Bagan "PP 84/2015" pada dokumen yang sama memberi pasangan berbeda, tetapi
		| teksnya tersimpan di dalam kotak gambar yang berurutan menurut lapisan, bukan
		| menurut bacaan, sehingga pasangannya tidak dapat diturunkan secara mekanis.
		| Perbedaan itu dicatat sebagai isu data, bukan diputuskan sepihak di sini.
		*/
		$structure = array(
			// jabatan, induk, urutan, nama, tipe unit
			array('Kepala Desa', NULL, 10, 'Yaya Dores'),
			array('Sekretaris Desa', 'Kepala Desa', 20, 'Agus Haryadi'),
			array('Kaur Keuangan', 'Sekretaris Desa', 30, 'Rika Indriani'),
			array('Kaur Perencanaan', 'Sekretaris Desa', 40, 'Aang Nurjaman'),
			array('Kaur Umum', 'Sekretaris Desa', 50, 'Sylvia Indri Sahada'),
			array('Kasi Pemerintahan', 'Kepala Desa', 60, 'Cahya Setiadi'),
			array('Kasi Pelayanan', 'Kepala Desa', 70, 'Diat Supriadi'),
			array('Kasi Kesejahteraan', 'Kepala Desa', 80, 'Heryana'),
			array('Staf', 'Sekretaris Desa', 90, 'Cahya Gumilang'),
			array('Staf Kasi', 'Sekretaris Desa', 100, 'Elsa Safitri'),
			array('Kepala Dusun 1', 'Kepala Desa', 110, 'U. Juhana'),
			array('Kepala Dusun 2', 'Kepala Desa', 120, 'Ujang Suryana'),
			array('Kepala Dusun 3', 'Kepala Desa', 130, 'Andi Rustandi'),
			array('Kepala Dusun 4', 'Kepala Desa', 140, 'Uu Abduloh'),
		);

		$position_ids = array('Kepala Desa' => $head_id, 'Sekretaris Desa' => NULL);
		foreach ($structure as $row)
		{
			list($title, $parent_title, $sort, $person_name) = $row;
			$parent_id = ($parent_title === NULL) ? NULL : ($position_ids[$parent_title] ?? NULL);
			$position_id = $this->insert_if_missing('org_positions', array('period_id' => $period_id, 'title' => $title), array(
				'public_id' => $this->CI->crypto->public_id(),
				'unit_id' => $unit_id,
				'parent_id' => $parent_id,
				'level' => ($parent_id === NULL) ? 1 : 2,
				'duties_public' => NULL,
				'sort_order' => $sort,
				'active' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			));
			$position_ids[$title] = $position_id;

			// Foto tidak dipasangkan: gambar S3 memuat jejak berkas dari desa lain
			// (docs/data-issues.md), jadi photo_consent tetap 0 dan tanpa foto.
			$person_id = $this->insert_if_missing('people', array('full_name' => $person_name), array(
				'public_id' => $this->CI->crypto->public_id(),
				'title_prefix' => NULL,
				'title_suffix' => NULL,
				'photo_media_id' => NULL,
				'photo_consent' => 0,
				'bio_public' => NULL,
				'data_status' => 'draft',
				'created_at' => $now,
				'updated_at' => $now,
			));
			$this->insert_if_missing('org_assignments', array('period_id' => $period_id, 'position_id' => $position_id), array(
				'public_id' => $this->CI->crypto->public_id(),
				'person_id' => $person_id,
				'assignment_type' => 'definitive',
				'start_date' => NULL,
				'end_date' => NULL,
				'decree_number_ciphertext' => NULL,
				'status' => 'active',
				'end_reason' => NULL,
				'created_at' => $now,
				'updated_at' => $now,
			));
		}
	}

	protected function potentials(array $sources)
	{
		$now = $this->now();
		$category = $this->id_of('content_categories', array('content_type' => 'potential', 'slug' => 'pertanian'));
		$this->insert_if_missing('potentials', array('slug' => 'hortikultura-dataran-tinggi'), array(
			'category_id' => $category,
			'title' => 'Hortikultura dataran tinggi',
			'summary' => 'Profil Desa 2023 mencatat komoditas seperti kentang, kubis, wortel, cabai, tomat, jagung, dan ubi jalar.',
			'body_html' => '<p>Dokumen Potensi Desa Cihawuk 2023 mencantumkan komoditas pertanian antara lain kentang, kubis, wortel, cabai, tomat, jagung, dan ubi jalar.</p>'
				.'<p>Contoh luas tanaman tahun 2023: kentang 80 ha, kubis 70 ha, wortel 70 ha, dan cabai 15 ha. Luas komoditas dapat terkait musim atau pola tanam sehingga tidak dijumlahkan sebagai pembagian luas wilayah.</p>',
			'cover_media_id' => NULL,
			'source_id' => $sources['S1'],
			'source_year' => 2023,
			'verification_status' => 'unverified',
			'publication_status' => 'draft',
			'sort_order' => 10,
			'created_at' => $now,
			'updated_at' => $now,
		));
	}

	protected function hero()
	{
		if ($this->CI->db->count_all('hero_slides') > 0)
		{
			return;
		}
		$now = $this->now();
		db_must($this->CI->db->insert('hero_slides', array(
			'title' => 'Selamat Datang di Desa Cihawuk',
			'subtitle' => 'Kecamatan Kertasari, Kabupaten Bandung',
			'image_media_id' => NULL,
			'video_media_id' => NULL,
			'cta_primary_json' => json_encode(array('label' => 'Jelajahi Desa', 'url' => '/profil')),
			'cta_secondary_json' => json_encode(array('label' => 'Layanan Warga', 'url' => '/layanan')),
			'animation_mode' => 'three',
			'sort_order' => 10,
			'active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'seed hero_slides');
		$this->count('hero_slides', TRUE);
	}
}
