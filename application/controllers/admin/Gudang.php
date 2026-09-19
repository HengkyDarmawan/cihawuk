<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Gudang barang persediaan (prompt-master 18.5).
 *
 * Pemisahan izin: melihat `warehouse.view`; penerimaan `warehouse.receive`; pengeluaran
 * `warehouse.issue`; transfer `warehouse.transfer`; penyesuaian `warehouse.adjust`;
 * stock opname `warehouse.stocktake`.
 */
class Gudang extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('WarehouseService', NULL, 'warehouse');
		$this->layout_data['nav_active'] = 'gudang';
	}

	public function index()
	{
		$this->require_permission('warehouse.view');
		$items = $this->warehouse->items(array('q' => (string) $this->input->get('q')));
		foreach ($items as $item)
		{
			$item->balances = $this->warehouse->balances($item);
		}
		$this->render('admin/gudang_index', array(
			'page_title' => 'Gudang Persediaan',
			'items' => $items,
			'locations' => $this->warehouse->locations(),
			'transactions' => $this->warehouse->transactions(20),
			'stocktakes' => $this->warehouse->stocktakes(10),
			'below_minimum' => $this->warehouse->below_minimum(),
			'types' => WarehouseService::TYPES,
			'statuses' => WarehouseService::STATUSES,
			'filters' => array('q' => (string) $this->input->get('q')),
			'can_receive' => $this->authz->can('warehouse.receive'),
			'can_stocktake' => $this->authz->can('warehouse.stocktake'),
		), 'dashboard');
	}

	public function item($public_id)
	{
		$this->require_permission('warehouse.view');
		$item = $this->require_item($public_id);
		$location_id = (int) $this->input->get('lokasi');
		$this->render('admin/gudang_item', array(
			'page_title' => 'Barang: '.$item->name,
			'item' => $item,
			'balances' => $this->warehouse->balances($item),
			'conversions' => $this->warehouse->conversions($item),
			'locations' => $this->warehouse->locations(),
			'selected_location' => $location_id,
			'card' => $location_id ? $this->warehouse->stock_card((int) $item->id, $location_id) : array(),
			'types' => WarehouseService::TYPES,
			'can_edit' => $this->authz->can('warehouse.receive'),
		), 'dashboard');
	}

	public function save_item()
	{
		$this->require_method('post');
		$this->require_permission('warehouse.receive');
		$public_id = $this->post_string('public_id', 26) ?: NULL;
		$item = $this->warehouse->save_item($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Barang persediaan disimpan.');
		redirect(site_url('admin/gudang/barang/'.rawurlencode($item->public_id)), 'location', 303);
	}

	public function save_location()
	{
		$this->require_method('post');
		$this->require_permission('warehouse.receive');
		$this->warehouse->save_location($this->input->post(NULL, FALSE) ?: array());
		$this->flash('success', 'Lokasi gudang disimpan.');
		redirect(site_url('admin/gudang'), 'location', 303);
	}

	public function save_conversion($public_id)
	{
		$this->require_method('post');
		$this->require_permission('warehouse.receive');
		$item = $this->require_item($public_id);
		$this->warehouse->save_conversion($item, $this->input->post(NULL, FALSE) ?: array());
		$this->flash('success', 'Konversi satuan disimpan.');
		redirect(site_url('admin/gudang/barang/'.rawurlencode($public_id)), 'location', 303);
	}

	public function create_transaction()
	{
		$this->require_method('post');
		$type = (string) $this->input->post('transaction_type');
		$this->require_permission($this->permission_for($type));
		$transaction = $this->warehouse->create_transaction($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Transaksi dibuat sebagai draft. Tambahkan barisnya lalu posting.');
		redirect(site_url('admin/gudang/transaksi/'.rawurlencode($transaction->public_id)), 'location', 303);
	}

	public function transaction($public_id)
	{
		$this->require_permission('warehouse.view');
		$transaction = $this->require_transaction($public_id);
		$this->render('admin/gudang_transaksi', array(
			'page_title' => 'Transaksi '.(WarehouseService::TYPES[$transaction->transaction_type] ?? ''),
			'transaction' => $transaction,
			'lines' => $this->warehouse->lines($transaction),
			'items' => $this->warehouse->items(),
			'types' => WarehouseService::TYPES,
			'statuses' => WarehouseService::STATUSES,
			'can_edit' => $this->authz->can($this->permission_for($transaction->transaction_type)),
		), 'dashboard');
	}

	public function add_line($public_id)
	{
		$this->require_method('post');
		$transaction = $this->require_transaction($public_id);
		$this->require_permission($this->permission_for($transaction->transaction_type));
		if ($transaction->transaction_type === 'adjustment')
		{
			$this->warehouse->add_adjustment_line($transaction, $this->input->post(NULL, FALSE) ?: array());
		}
		else
		{
			$this->warehouse->add_line($transaction, $this->input->post(NULL, FALSE) ?: array());
		}
		$this->flash('success', 'Baris barang ditambahkan.');
		redirect(site_url('admin/gudang/transaksi/'.rawurlencode($public_id)), 'location', 303);
	}

	public function post_transaction($public_id)
	{
		$this->require_method('post');
		$transaction = $this->require_transaction($public_id);
		$this->require_permission($this->permission_for($transaction->transaction_type));
		$this->warehouse->post($transaction, (int) $this->user->id);
		$this->flash('success', 'Transaksi diposting. Saldo dihitung ulang dari ledger.');
		redirect(site_url('admin/gudang/transaksi/'.rawurlencode($public_id)), 'location', 303);
	}

	public function open_stocktake()
	{
		$this->require_method('post');
		$this->require_permission('warehouse.stocktake');
		$stocktake = $this->warehouse->open_stocktake($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Opname dibuka. Saldo harapan dibekukan.');
		redirect(site_url('admin/gudang/opname/'.rawurlencode($stocktake->public_id)), 'location', 303);
	}

	public function stocktake($public_id)
	{
		$this->require_permission('warehouse.view');
		$stocktake = $this->require_stocktake($public_id);
		$this->render('admin/gudang_opname', array(
			'page_title' => 'Opname: '.$stocktake->name,
			'stocktake' => $stocktake,
			'lines' => $this->warehouse->stocktake_lines($stocktake),
			'can_count' => $this->authz->can('warehouse.stocktake'),
		), 'dashboard');
	}

	public function count_line($public_id)
	{
		$this->require_method('post');
		$this->require_permission('warehouse.stocktake');
		$stocktake = $this->require_stocktake($public_id);
		$this->warehouse->count_line($stocktake, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Hasil hitung disimpan. Selisih dihitung server.');
		redirect(site_url('admin/gudang/opname/'.rawurlencode($public_id)), 'location', 303);
	}

	public function close_stocktake($public_id)
	{
		$this->require_method('post');
		$this->require_permission('warehouse.stocktake');
		$stocktake = $this->require_stocktake($public_id);
		$transaction = $this->warehouse->close_stocktake($stocktake, (int) $this->user->id);
		$this->flash('success', $transaction
			? 'Opname ditutup dan penyesuaian diposting.'
			: 'Opname ditutup tanpa selisih.');
		redirect(site_url('admin/gudang/opname/'.rawurlencode($public_id)), 'location', 303);
	}

	protected function permission_for($type)
	{
		$map = array(
			'receipt' => 'warehouse.receive',
			'return' => 'warehouse.receive',
			'issue' => 'warehouse.issue',
			'transfer' => 'warehouse.transfer',
			'adjustment' => 'warehouse.adjust',
		);
		return $map[$type] ?? 'warehouse.adjust';
	}

	protected function require_item($public_id)
	{
		$item = $this->warehouse->item($public_id);
		if ( ! $item)
		{
			throw new DomainRuleException('Barang tidak ditemukan.', 404);
		}
		return $item;
	}

	protected function require_transaction($public_id)
	{
		$transaction = $this->warehouse->transaction($public_id);
		if ( ! $transaction)
		{
			throw new DomainRuleException('Transaksi tidak ditemukan.', 404);
		}
		return $transaction;
	}

	protected function require_stocktake($public_id)
	{
		$stocktake = $this->warehouse->stocktake($public_id);
		if ( ! $stocktake)
		{
			throw new DomainRuleException('Opname tidak ditemukan.', 404);
		}
		return $stocktake;
	}
}
