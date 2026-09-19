<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Registry blok profil desa (modul-backend 10.1, modul-frontend 5).
|
| Tertutup: pengelola mengisi field terstruktur, bukan HTML bebas. Field yang tidak ada di
| sini dibuang server. Setiap blok punya halaman publik yang sudah ditentukan supaya
| penerbitan tidak pernah membuat alamat baru yang tak terduga.
|
| Tipe field: text, textarea, number, decimal, string_list, pair_list, code.
| `code` disimpan dan ditampilkan sebagai string apa adanya (mis. kode PUM 320431.2006).
*/

$config['profile_blocks'] = array(

	'identity' => array(
		'label' => 'Identitas desa',
		'page' => 'profil',
		'sort_order' => 10,
		'fields' => array(
			'village_name' => array('type' => 'text', 'label' => 'Nama desa', 'max' => 100, 'required' => TRUE),
			'district' => array('type' => 'text', 'label' => 'Kecamatan', 'max' => 100, 'required' => TRUE),
			'regency' => array('type' => 'text', 'label' => 'Kabupaten', 'max' => 100, 'required' => TRUE),
			'province' => array('type' => 'text', 'label' => 'Provinsi', 'max' => 100, 'required' => TRUE),
			'pum_code' => array('type' => 'code', 'label' => 'Kode PUM', 'max' => 30),
			'summary' => array('type' => 'textarea', 'label' => 'Ringkasan', 'max' => 1200),
			'coordinate_latitude_raw' => array('type' => 'code', 'label' => 'Lintang (nilai mentah)', 'max' => 30,
				'help' => 'Disimpan apa adanya. Tanda lintang tidak ditentukan otomatis.'),
			'coordinate_longitude_raw' => array('type' => 'code', 'label' => 'Bujur (nilai mentah)', 'max' => 30),
		),
	),

	'greeting' => array(
		'label' => 'Sambutan',
		'page' => 'profil',
		'sort_order' => 20,
		'fields' => array(
			'author_name' => array('type' => 'text', 'label' => 'Nama penyampai', 'max' => 150, 'required' => TRUE),
			'author_position' => array('type' => 'text', 'label' => 'Jabatan saat dokumen dibuat', 'max' => 150, 'required' => TRUE),
			'document_date' => array('type' => 'text', 'label' => 'Tanggal dokumen', 'max' => 60),
			'original_text' => array('type' => 'textarea', 'label' => 'Naskah asli', 'max' => 6000, 'required' => TRUE),
			'edited_text' => array('type' => 'textarea', 'label' => 'Versi yang sudah direview', 'max' => 6000),
		),
	),

	'history' => array(
		'label' => 'Sejarah desa',
		'page' => 'profil/sejarah',
		'sort_order' => 30,
		'fields' => array(
			'founded_year' => array('type' => 'number', 'label' => 'Tahun pembentukan menurut sumber', 'min' => 1800, 'max' => 2100),
			'founded_claim_note' => array('type' => 'textarea', 'label' => 'Catatan klaim pembentukan', 'max' => 800,
				'help' => 'Tanggal dan nomor keputusan yang belum diverifikasi ditulis di sini sebagai klaim sumber.'),
			'narrative' => array('type' => 'textarea', 'label' => 'Narasi sejarah', 'max' => 8000, 'required' => TRUE),
		),
	),

	'vision' => array(
		'label' => 'Visi',
		'page' => 'profil/visi-misi',
		'sort_order' => 40,
		'fields' => array(
			'village_vision' => array('type' => 'textarea', 'label' => 'Visi Desa Cihawuk', 'max' => 1200, 'required' => TRUE),
			'district_vision' => array('type' => 'textarea', 'label' => 'Visi Kecamatan Kertasari', 'max' => 1200,
				'help' => 'Disimpan terpisah dan diberi label sendiri; jangan digabung dengan visi desa.'),
			'basis_document' => array('type' => 'text', 'label' => 'Dokumen dasar', 'max' => 200),
		),
	),

	'mission' => array(
		'label' => 'Misi',
		'page' => 'profil/visi-misi',
		'sort_order' => 50,
		'fields' => array(
			'items' => array('type' => 'string_list', 'label' => 'Butir misi', 'max_items' => 15, 'max' => 400, 'required' => TRUE),
			'basis_document' => array('type' => 'text', 'label' => 'Dokumen dasar', 'max' => 200),
		),
	),

	'geography' => array(
		'label' => 'Geografi',
		'page' => 'profil/geografi',
		'sort_order' => 60,
		'fields' => array(
			'elevation_masl' => array('type' => 'number', 'label' => 'Ketinggian (mdpl)', 'min' => 0, 'max' => 5000),
			'avg_temperature_c' => array('type' => 'decimal', 'label' => 'Suhu rata-rata harian (derajat C)'),
			'rainfall_mm' => array('type' => 'number', 'label' => 'Curah hujan (mm)', 'min' => 0, 'max' => 20000),
			'rainy_months' => array('type' => 'number', 'label' => 'Jumlah bulan hujan', 'min' => 0, 'max' => 12),
			'land_use' => array('type' => 'pair_list', 'label' => 'Penggunaan lahan (hektare)', 'max_items' => 20),
			'boundaries' => array('type' => 'pair_list', 'label' => 'Batas wilayah', 'max_items' => 8),
			'area_conflict_note' => array('type' => 'textarea', 'label' => 'Catatan konflik luas wilayah', 'max' => 1000,
				'help' => 'Wajib diisi bila angka luas antar sumber berbeda. Jangan memilih satu angka secara sepihak.'),
		),
	),

	'contact' => array(
		'label' => 'Kontak dan pelayanan',
		'page' => 'profil',
		'sort_order' => 70,
		'fields' => array(
			'office_address' => array('type' => 'textarea', 'label' => 'Alamat kantor', 'max' => 400),
			'phone_public' => array('type' => 'text', 'label' => 'Telepon publik', 'max' => 40),
			'email_public' => array('type' => 'text', 'label' => 'Surel publik', 'max' => 120),
			'service_hours' => array('type' => 'pair_list', 'label' => 'Jam pelayanan', 'max_items' => 8),
		),
	),
);
