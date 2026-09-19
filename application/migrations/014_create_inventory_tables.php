<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 11 spesifikasi v1.2 (prompt-master 18.5 dan 19.7): gudang barang persediaan.
 *
 * Domainnya berbeda dari aset tetap: di sini kuantitas bertambah dan berkurang.
 * `inventory_ledger` bersifat append-only dan menjadi sumber saldo kanonis; tidak ada kolom
 * saldo yang dapat disunting bebas. Konversi satuan memakai numerator dan denominator
 * bilangan bulat supaya tidak muncul saldo pecahan yang tidak sah.
 */
class Migration_Create_inventory_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE warehouse_locations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(40) NOT NULL,
			name VARCHAR(180) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_warehouse_locations_code (code),
			CONSTRAINT fk_warehouse_locations_parent FOREIGN KEY (parent_id) REFERENCES warehouse_locations (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE inventory_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			sku VARCHAR(60) NOT NULL,
			name VARCHAR(200) NOT NULL,
			category VARCHAR(60) NULL,
			base_unit VARCHAR(30) NOT NULL,
			minimum_stock DECIMAL(18,3) NULL,
			track_batch TINYINT(1) NOT NULL DEFAULT 0,
			photo_media_id BIGINT UNSIGNED NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_items_public (public_id),
			UNIQUE KEY uq_inventory_items_sku (sku),
			CONSTRAINT fk_inventory_items_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE inventory_unit_conversions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			item_id BIGINT UNSIGNED NOT NULL,
			from_unit VARCHAR(30) NOT NULL,
			to_unit VARCHAR(30) NOT NULL,
			numerator INT UNSIGNED NOT NULL,
			denominator INT UNSIGNED NOT NULL DEFAULT 1,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_conversions (item_id, from_unit, to_unit),
			CONSTRAINT fk_inventory_conversions_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE inventory_transactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			transaction_type VARCHAR(20) NOT NULL,
			reference_no VARCHAR(60) NULL,
			transaction_at DATETIME NOT NULL,
			from_location_id BIGINT UNSIGNED NULL,
			to_location_id BIGINT UNSIGNED NULL,
			source_fund VARCHAR(120) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			requested_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL,
			posted_by BIGINT UNSIGNED NULL,
			posted_at DATETIME NULL,
			reason VARCHAR(500) NULL,
			version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_transactions_public (public_id),
			KEY idx_inventory_transactions_status (status, transaction_at),
			CONSTRAINT fk_inventory_transactions_from FOREIGN KEY (from_location_id) REFERENCES warehouse_locations (id) ON DELETE RESTRICT,
			CONSTRAINT fk_inventory_transactions_to FOREIGN KEY (to_location_id) REFERENCES warehouse_locations (id) ON DELETE RESTRICT,
			CONSTRAINT fk_inventory_transactions_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_transactions_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_transactions_poster FOREIGN KEY (posted_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE inventory_transaction_lines (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			transaction_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			quantity_base DECIMAL(18,3) NOT NULL,
			input_quantity DECIMAL(18,3) NULL,
			input_unit VARCHAR(30) NULL,
			batch_no VARCHAR(60) NULL,
			expires_at DATE NULL,
			note VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_inventory_lines_transaction (transaction_id),
			KEY idx_inventory_lines_item (item_id),
			CONSTRAINT fk_inventory_lines_transaction FOREIGN KEY (transaction_id) REFERENCES inventory_transactions (id) ON DELETE CASCADE,
			CONSTRAINT fk_inventory_lines_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT
		) $t");

		// Append-only. Saldo dihitung dari jumlah `quantity_delta`, bukan dari kolom saldo.
		$this->db->query("CREATE TABLE inventory_ledger (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			transaction_line_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			location_id BIGINT UNSIGNED NOT NULL,
			quantity_delta DECIMAL(18,3) NOT NULL,
			running_projection DECIMAL(18,3) NULL,
			posted_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_ledger (transaction_line_id, location_id, quantity_delta),
			KEY idx_inventory_ledger_balance (item_id, location_id, posted_at),
			CONSTRAINT fk_inventory_ledger_line FOREIGN KEY (transaction_line_id) REFERENCES inventory_transaction_lines (id) ON DELETE CASCADE,
			CONSTRAINT fk_inventory_ledger_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT,
			CONSTRAINT fk_inventory_ledger_location FOREIGN KEY (location_id) REFERENCES warehouse_locations (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE inventory_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			requesting_unit_id BIGINT UNSIGNED NULL,
			requested_by BIGINT UNSIGNED NULL,
			needed_at DATE NULL,
			purpose VARCHAR(500) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'submitted',
			approved_by BIGINT UNSIGNED NULL,
			fulfilled_transaction_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_requests_public (public_id),
			KEY idx_inventory_requests_status (status, created_at),
			CONSTRAINT fk_inventory_requests_unit FOREIGN KEY (requesting_unit_id) REFERENCES organizational_units (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_requests_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_requests_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_requests_transaction FOREIGN KEY (fulfilled_transaction_id) REFERENCES inventory_transactions (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE inventory_request_lines (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			requested_quantity_base DECIMAL(18,3) NOT NULL,
			approved_quantity_base DECIMAL(18,3) NULL,
			issued_quantity_base DECIMAL(18,3) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_request_lines (request_id, item_id),
			CONSTRAINT fk_inventory_request_lines_request FOREIGN KEY (request_id) REFERENCES inventory_requests (id) ON DELETE CASCADE,
			CONSTRAINT fk_inventory_request_lines_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE inventory_stocktakes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			location_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(200) NOT NULL,
			snapshot_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL,
			posted_adjustment_transaction_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_stocktakes_public (public_id),
			KEY idx_inventory_stocktakes_location (location_id, status),
			CONSTRAINT fk_inventory_stocktakes_location FOREIGN KEY (location_id) REFERENCES warehouse_locations (id) ON DELETE RESTRICT,
			CONSTRAINT fk_inventory_stocktakes_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_stocktakes_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_inventory_stocktakes_transaction FOREIGN KEY (posted_adjustment_transaction_id) REFERENCES inventory_transactions (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE inventory_stocktake_lines (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			stocktake_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			expected_quantity_base DECIMAL(18,3) NOT NULL,
			counted_quantity_base DECIMAL(18,3) NULL,
			variance_quantity_base DECIMAL(18,3) NULL,
			counted_by BIGINT UNSIGNED NULL,
			counted_at DATETIME NULL,
			note VARCHAR(255) NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_inventory_stocktake_lines (stocktake_id, item_id),
			CONSTRAINT fk_inventory_stocktake_lines_stocktake FOREIGN KEY (stocktake_id) REFERENCES inventory_stocktakes (id) ON DELETE CASCADE,
			CONSTRAINT fk_inventory_stocktake_lines_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT,
			CONSTRAINT fk_inventory_stocktake_lines_counter FOREIGN KEY (counted_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('inventory_stocktake_lines', 'inventory_stocktakes', 'inventory_request_lines',
			'inventory_requests', 'inventory_ledger', 'inventory_transaction_lines', 'inventory_transactions',
			'inventory_unit_conversions', 'inventory_items', 'warehouse_locations') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
