<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Basis seeder: helper idempotent yang tidak menimpa data yang sudah diedit.
 */
abstract class Seeder {

	/** @var MY_Controller */
	protected $CI;

	/** @var array<string,int> */
	protected $counts = array();

	public function __construct($CI)
	{
		$this->CI = $CI;
	}

	abstract public function run();

	protected function now()
	{
		return utc_now();
	}

	protected function out($text)
	{
		if (is_cli() && defined('STDOUT') && ! defined('CHW_TESTING'))
		{
			fwrite(STDOUT, $text.PHP_EOL);
		}
	}

	protected function count($table, $inserted)
	{
		if ( ! isset($this->counts[$table]))
		{
			$this->counts[$table] = 0;
		}
		$this->counts[$table] += $inserted ? 1 : 0;
	}

	/**
	 * Insert bila baris dengan $unique belum ada. Kembalikan id baris (lama/baru).
	 */
	protected function insert_if_missing($table, array $unique, array $data, $id_column = 'id')
	{
		$existing = $this->CI->db->get_where($table, $unique)->row_array();
		if ($existing)
		{
			$this->count($table, FALSE);
			return isset($existing[$id_column]) ? $existing[$id_column] : TRUE;
		}
		db_must($this->CI->db->insert($table, array_merge($unique, $data)), 'seed '.$table);
		$this->count($table, TRUE);
		return ($id_column === 'id') ? (int) $this->CI->db->insert_id() : TRUE;
	}

	protected function id_of($table, array $where)
	{
		$row = $this->CI->db->select('id')->get_where($table, $where)->row();
		return $row ? (int) $row->id : NULL;
	}

	protected function summary()
	{
		foreach ($this->counts as $table => $n)
		{
			$this->out(sprintf('  %-28s %d baris baru', $table, $n));
		}
	}
}
