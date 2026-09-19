<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 12 spesifikasi v1.2 (modul-backend 25): hasil pemeriksa tautan rusak.
 *
 * Pemeriksa hanya menelusuri tautan INTERNAL: item menu, tautan pada section halaman, dan
 * dokumen publik. Tautan eksternal tidak dipanggil dari server supaya pemeriksaan tidak
 * berubah menjadi permintaan keluar yang tidak diminta.
 */
class Migration_Create_link_check_table extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE link_check_results (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_type VARCHAR(30) NOT NULL,
			source_label VARCHAR(220) NOT NULL,
			source_reference VARCHAR(120) NULL,
			target_path VARCHAR(500) NOT NULL,
			status VARCHAR(20) NOT NULL,
			detail VARCHAR(500) NULL,
			checked_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_link_check_status (status, checked_at),
			KEY idx_link_check_source (source_type)
		) $t");
	}

	public function down()
	{
		$this->db->query('DROP TABLE IF EXISTS `link_check_results`');
	}
}
