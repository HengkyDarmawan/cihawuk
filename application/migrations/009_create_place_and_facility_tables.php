<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 6 spesifikasi v1.2 (modul-backend 12.1 dan 12.2, modul-frontend 9).
 *
 * `places` memisahkan lokasi publik dari entitas yang memakainya, `facilities` adalah
 * direktori fasilitas yang benar-benar punya identitas, dan `potentials` dilengkapi field
 * status akses, pengelola, dan catatan keselamatan.
 *
 * Angka agregat seperti "4 SD" TIDAK menghasilkan entitas fasilitas; angka itu tetap berada
 * pada Data Desa. Direktori hanya memuat objek yang identitasnya cukup.
 */
class Migration_Create_place_and_facility_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE places (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			name VARCHAR(180) NOT NULL,
			place_type VARCHAR(30) NOT NULL,
			address VARCHAR(400) NULL,
			area_note VARCHAR(200) NULL,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			source_id BIGINT UNSIGNED NULL,
			source_note VARCHAR(255) NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_places_public (public_id),
			KEY idx_places_type (place_type, verification_status),
			CONSTRAINT fk_places_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE facilities (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			slug VARCHAR(220) NOT NULL,
			name VARCHAR(200) NOT NULL,
			category VARCHAR(40) NOT NULL,
			manager_name VARCHAR(180) NULL,
			description VARCHAR(1000) NULL,
			place_id BIGINT UNSIGNED NULL,
			address VARCHAR(400) NULL,
			service_hours_json LONGTEXT NULL,
			public_contact VARCHAR(255) NULL,
			contact_permission TINYINT(1) NOT NULL DEFAULT 0,
			accessibility VARCHAR(600) NULL,
			photo_media_id BIGINT UNSIGNED NULL,
			source_year SMALLINT UNSIGNED NULL,
			source_id BIGINT UNSIGNED NULL,
			source_note VARCHAR(255) NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			published_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_facilities_public (public_id),
			UNIQUE KEY uq_facilities_slug (slug),
			KEY idx_facilities_public (publication_status, category),
			CONSTRAINT fk_facilities_place FOREIGN KEY (place_id) REFERENCES places (id) ON DELETE SET NULL,
			CONSTRAINT fk_facilities_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id) ON DELETE SET NULL,
			CONSTRAINT fk_facilities_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_facilities_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_facilities_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE facility_services (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			facility_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(160) NOT NULL,
			description VARCHAR(400) NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_facility_services_facility (facility_id),
			CONSTRAINT fk_facility_services_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE CASCADE
		) $t");

		// Field potensi menurut modul-backend 12.1 yang belum ada.
		$this->db->query("ALTER TABLE potentials
			ADD COLUMN place_id BIGINT UNSIGNED NULL AFTER map_feature_id,
			ADD COLUMN access_status VARCHAR(30) NULL AFTER place_id,
			ADD COLUMN manager_name VARCHAR(180) NULL AFTER access_status,
			ADD COLUMN safety_note VARCHAR(600) NULL AFTER manager_name,
			ADD CONSTRAINT fk_potentials_place FOREIGN KEY (place_id) REFERENCES places (id) ON DELETE SET NULL");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		if ($this->db->field_exists('place_id', 'potentials'))
		{
			$this->db->query('ALTER TABLE potentials DROP FOREIGN KEY fk_potentials_place');
			$this->db->query('ALTER TABLE potentials DROP COLUMN place_id, DROP COLUMN access_status,
				DROP COLUMN manager_name, DROP COLUMN safety_note');
		}
		foreach (array('facility_services', 'facilities', 'places') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
