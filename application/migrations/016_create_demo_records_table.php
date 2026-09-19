<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Jejak entitas yang dibuat seeder demonstrasi.
 *
 * Data demo untuk modul baru (fasilitas, UMKM, anggaran, aset, gudang, konten) sengaja
 * TIDAK diberi awalan "[Demo]" pada namanya, supaya tampilannya wajar saat diperagakan
 * kepada pemerintah desa. Sebagai gantinya setiap entitas akar dicatat di sini, sehingga
 * `tools purge_demo` tetap dapat menghapusnya dengan pasti dan tanpa menebak.
 *
 * Hanya entitas AKAR yang dicatat; turunannya dihapus oleh rutin per modul pada DemoSeeder
 * atau oleh ON DELETE CASCADE.
 */
class Migration_Create_demo_records_table extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE demo_records (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type VARCHAR(40) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			public_id VARCHAR(40) NULL,
			label VARCHAR(200) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_demo_records_entity (entity_type, entity_id),
			KEY idx_demo_records_type (entity_type)
		) $t");
	}

	public function down()
	{
		$this->db->query('DROP TABLE IF EXISTS `demo_records`');
	}
}
