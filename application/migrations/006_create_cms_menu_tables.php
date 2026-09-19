<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 3 spesifikasi v1.2 (modul-backend §9.3): menu navigasi yang dikelola pengelola.
 *
 * Menu mengikuti pola yang sama dengan halaman: item disimpan sebagai draft, lalu
 * diterbitkan menjadi snapshot pada `cms_publication_snapshots` (target_type = 'menu').
 * Frontend membaca snapshot, bukan tabel draft, sehingga mengubah menu tidak langsung
 * mengubah situs publik.
 *
 * link_type: route (path internal aplikasi), page (halaman CMS), external (http/https).
 */
class Migration_Create_cms_menu_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		// location: header, mobile, footer_primary, footer_secondary, quick_link
		$this->db->query("CREATE TABLE cms_menus (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			location VARCHAR(30) NOT NULL,
			label VARCHAR(80) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_menus_public (public_id),
			UNIQUE KEY uq_cms_menus_location (location)
		) $t");

		$this->db->query("CREATE TABLE cms_menu_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			menu_id BIGINT UNSIGNED NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			label VARCHAR(80) NOT NULL,
			link_type VARCHAR(20) NOT NULL DEFAULT 'route',
			route_path VARCHAR(191) NULL,
			cms_page_id BIGINT UNSIGNED NULL,
			external_url VARCHAR(500) NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			is_enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_menu_items_public (public_id),
			KEY idx_cms_menu_items_menu (menu_id, parent_id, sort_order),
			CONSTRAINT fk_cms_menu_items_menu FOREIGN KEY (menu_id) REFERENCES cms_menus (id) ON DELETE CASCADE,
			CONSTRAINT fk_cms_menu_items_parent FOREIGN KEY (parent_id) REFERENCES cms_menu_items (id) ON DELETE CASCADE,
			CONSTRAINT fk_cms_menu_items_page FOREIGN KEY (cms_page_id) REFERENCES cms_pages (id) ON DELETE RESTRICT,
			CONSTRAINT fk_cms_menu_items_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('cms_menu_items', 'cms_menus') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		// Snapshot menu dan situs milik migration ini; halaman tetap dipertahankan.
		if ($this->db->table_exists('cms_publication_snapshots'))
		{
			$this->db->where_in('target_type', array('menu', 'site'))->delete('cms_publication_snapshots');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
