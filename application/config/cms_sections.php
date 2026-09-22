<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Registry section CMS (modul-backend §7.1 dan §8.3).
|
| Hanya jenis section di daftar ini yang dapat dibuat pengelola. Tidak ada section HTML/JS/PHP
| bebas, tidak ada CSS arbitrer, dan tidak ada template expression. Nilai konfigurasi divalidasi
| server terhadap `fields` di bawah; kunci yang tidak dikenal dibuang.
|
| Tipe field: text, textarea, select, number, checkbox, media, links, ids, indicators.
| `module` berisi kode feature module yang wajib hidup agar section dapat dipakai.
*/

$config['cms_templates'] = array(
	'page.standard' => 'Halaman standar',
	'page.landing' => 'Halaman beranda / landing',
);

$config['cms_section_types'] = array(

	'hero' => array(
		'label' => 'Hero',
		'description' => 'Bagian pembuka dengan judul, subjudul, dan maksimal dua tombol.',
		'singleton' => TRUE,
		'layouts' => array('hero.standard' => 'Standar', 'hero.editorial' => 'Editorial'),
		'fields' => array(
			'mode' => array('type' => 'select', 'label' => 'Mode tampilan', 'required' => TRUE,
				'options' => array('image' => 'Foto', 'video' => 'Video', 'three' => 'Animasi Three.js'), 'default' => 'three'),
			'fallback_media_id' => array('type' => 'media', 'label' => 'Foto fallback',
				'help' => 'Wajib untuk mode video. Mode animasi memakai ilustrasi bawaan bila foto belum ada.'),
			'video_media_id' => array('type' => 'media', 'label' => 'Video'),
			'side_media_id' => array('type' => 'media', 'label' => 'Foto sisi kanan',
				'help' => 'Foto desa di samping judul. Bila kosong, tampil foto kawasan Kec. Kertasari berlisensi bebas.'),
			'cta' => array('type' => 'links', 'label' => 'Tombol', 'max_items' => 2),
		),
	),

	'quick_links' => array(
		'label' => 'Akses cepat',
		'description' => 'Tautan layanan utama; selalu HTML biasa agar tetap berfungsi tanpa JavaScript.',
		'singleton' => TRUE,
		'layouts' => array('cards.grid_3' => 'Grid', 'cards.carousel' => 'Carousel'),
		'fields' => array(
			'links' => array('type' => 'links', 'label' => 'Tautan', 'max_items' => 8, 'required' => TRUE),
		),
	),

	'profile_summary' => array(
		'label' => 'Profil singkat',
		'description' => 'Ringkasan profil desa dengan satu foto dan tautan detail.',
		'singleton' => TRUE,
		'layouts' => array('text_image.image_left' => 'Foto kiri', 'text_image.image_right' => 'Foto kanan'),
		'fields' => array(
			'body' => array('type' => 'textarea', 'label' => 'Ringkasan', 'max' => 1200),
			'media_id' => array('type' => 'media', 'label' => 'Foto'),
			'cta' => array('type' => 'links', 'label' => 'Tautan', 'max_items' => 2),
		),
	),
	/*
	| Statistik beranda mengikuti modul-frontend 4.2: empat kartu dari SATU dataset yang
	| sudah terbit. Tahun, satuan, sumber dan tanggal publikasi dibaca dari snapshot
	| dataset, bukan diketik ulang pengelola, supaya kartu tidak pernah menyimpang.
	*/
	'statistics' => array(
		'label' => 'Statistik',
		'description' => 'Maksimal empat indikator dari satu dataset yang sudah terbit.',
		'singleton' => FALSE,
		'layouts' => array('statistics.cards_4' => 'Empat kartu', 'statistics.split_chart' => 'Kartu + grafik'),
		'fields' => array(
			'dataset_slug' => array('type' => 'dataset', 'label' => 'Dataset terbit', 'required' => TRUE),
			'series' => array('type' => 'dataset_series', 'label' => 'Indikator', 'max_items' => 4, 'required' => TRUE),
			'source_note' => array('type' => 'text', 'label' => 'Catatan tambahan', 'max' => 200),
		),
	),

	'text_image' => array(
		'label' => 'Teks dan gambar',
		'description' => 'Blok naratif dengan satu gambar pendukung.',
		'singleton' => FALSE,
		'layouts' => array('text_image.image_left' => 'Gambar kiri', 'text_image.image_right' => 'Gambar kanan'),
		'fields' => array(
			'body' => array('type' => 'textarea', 'label' => 'Isi', 'max' => 2000, 'required' => TRUE),
			'media_id' => array('type' => 'media', 'label' => 'Gambar'),
			'cta' => array('type' => 'links', 'label' => 'Tautan', 'max_items' => 1),
		),
	),

	'featured_potentials' => array(
		'label' => 'Potensi unggulan',
		'description' => 'Hanya potensi berstatus terbit dan terverifikasi yang dapat tampil.',
		'singleton' => FALSE,
		'module' => 'village_potentials',
		'layouts' => array('cards.grid_3' => 'Grid 3 kolom', 'cards.carousel' => 'Carousel'),
		'fields' => array(
			'selection' => array('type' => 'select', 'label' => 'Pemilihan', 'required' => TRUE,
				'options' => array('auto' => 'Otomatis (terbaru)', 'manual' => 'Manual'), 'default' => 'auto'),
			'limit' => array('type' => 'number', 'label' => 'Jumlah kartu', 'min' => 1, 'max' => 12, 'default' => 6),
			'item_ids' => array('type' => 'ids', 'label' => 'Potensi terpilih', 'entity' => 'potential', 'max_items' => 12),
			'cta' => array('type' => 'links', 'label' => 'Tautan', 'max_items' => 1),
		),
	),

	'featured_facilities' => array(
		'label' => 'Fasilitas unggulan',
		'description' => 'Direktori fasilitas; modul fasilitas harus aktif.',
		'singleton' => FALSE,
		'module' => 'facilities',
		'layouts' => array('cards.grid_3' => 'Grid 3 kolom'),
		'fields' => array(
			'limit' => array('type' => 'number', 'label' => 'Jumlah kartu', 'min' => 1, 'max' => 12, 'default' => 6),
		),
	),

	'featured_news' => array(
		'label' => 'Berita terbaru',
		'description' => 'Artikel terbit; artikel terjadwal tidak tampil sebelum waktunya.',
		'singleton' => FALSE,
		'module' => 'news',
		'layouts' => array('cards.grid_3' => 'Grid 3 kolom', 'cards.carousel' => 'Carousel'),
		'fields' => array(
			'limit' => array('type' => 'number', 'label' => 'Jumlah artikel', 'min' => 1, 'max' => 12, 'default' => 4),
			'cta' => array('type' => 'links', 'label' => 'Tautan', 'max_items' => 1),
		),
	),

	'upcoming_agenda' => array(
		'label' => 'Agenda mendatang',
		'description' => 'Kegiatan yang akan datang dalam waktu WIB; agenda selesai otomatis diarsipkan.',
		'singleton' => FALSE,
		'module' => 'agenda',
		'layouts' => array('cards.grid_3' => 'Daftar'),
		'fields' => array(
			'limit' => array('type' => 'number', 'label' => 'Jumlah agenda', 'min' => 1, 'max' => 10, 'default' => 4),
		),
	),

	'gallery_preview' => array(
		'label' => 'Cuplikan galeri',
		'description' => 'Beberapa media dari satu album yang sudah disetujui.',
		'singleton' => FALSE,
		'module' => 'gallery',
		'layouts' => array('cards.grid_3' => 'Grid 3 kolom'),
		'fields' => array(
			'gallery_id' => array('type' => 'ids', 'label' => 'Album', 'entity' => 'gallery', 'max_items' => 1, 'required' => TRUE),
			'limit' => array('type' => 'number', 'label' => 'Jumlah media', 'min' => 1, 'max' => 12, 'default' => 6),
		),
	),

	'verified_map' => array(
		'label' => 'Peta terverifikasi',
		'description' => 'Hanya titik peta berstatus terverifikasi; fallback alamat bila belum ada.',
		'singleton' => TRUE,
		'layouts' => array('text_image.image_right' => 'Peta kanan', 'text_image.image_left' => 'Peta kiri'),
		'fields' => array(
			'feature_types' => array('type' => 'select', 'label' => 'Jenis titik', 'required' => TRUE,
				'options' => array('office' => 'Kantor desa', 'facility' => 'Fasilitas', 'potential' => 'Potensi'), 'default' => 'office'),
			'body' => array('type' => 'textarea', 'label' => 'Keterangan', 'max' => 600),
		),
	),

	'budget_summary' => array(
		'label' => 'Ringkasan anggaran',
		'description' => 'Membaca snapshot APBDes yang sudah terbit; modul transparansi anggaran harus aktif.',
		'singleton' => FALSE,
		'module' => 'budget_transparency',
		'layouts' => array('statistics.cards_4' => 'Empat kartu'),
		'fields' => array(
			'budget_year' => array('type' => 'number', 'label' => 'Tahun anggaran', 'required' => TRUE, 'min' => 2000, 'max' => 2100),
		),
	),

	'public_documents' => array(
		'label' => 'Dokumen publik',
		'description' => 'Dokumen yang sudah direview dan disetujui untuk publik.',
		'singleton' => FALSE,
		'module' => 'public_documents',
		'layouts' => array('cards.grid_3' => 'Grid 3 kolom'),
		'fields' => array(
			'limit' => array('type' => 'number', 'label' => 'Jumlah dokumen', 'min' => 1, 'max' => 12, 'default' => 4),
		),
	),

	'service_cta' => array(
		'label' => 'Ajakan layanan',
		'description' => 'Banner ajakan; tidak boleh menggantikan akses Buat Laporan dan Lacak Laporan.',
		'singleton' => FALSE,
		'layouts' => array('cards.grid_3' => 'Standar'),
		'fields' => array(
			'body' => array('type' => 'textarea', 'label' => 'Isi', 'max' => 600),
			'cta' => array('type' => 'links', 'label' => 'Tombol', 'max_items' => 2, 'required' => TRUE),
		),
	),

	'custom_notice' => array(
		'label' => 'Pengumuman singkat',
		'description' => 'Pesan pendek dengan tingkat kepentingan; tidak menerima HTML mentah.',
		'singleton' => FALSE,
		'layouts' => array('cards.grid_3' => 'Standar'),
		'fields' => array(
			'severity' => array('type' => 'select', 'label' => 'Tingkat', 'required' => TRUE,
				'options' => array('info' => 'Informasi', 'warning' => 'Perhatian', 'urgent' => 'Penting'), 'default' => 'info'),
			'body' => array('type' => 'textarea', 'label' => 'Pesan', 'max' => 600, 'required' => TRUE),
		),
	),
);
