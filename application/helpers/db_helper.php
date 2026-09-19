<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Helper transaksi. db_debug dinonaktifkan agar error DB tidak tampil ke pengguna,
 * sehingga setiap query tulis harus diperiksa hasilnya secara eksplisit.
 */

if ( ! function_exists('db_must'))
{
	/**
	 * Lempar exception bila query gagal. Detail error hanya ke log.
	 * @return mixed hasil query
	 */
	function db_must($result, $context = 'database write')
	{
		if ($result === FALSE)
		{
			$CI =& get_instance();
			$error = $CI->db->error();
			log_message('error', 'DB failure ['.$context.']: '.($error['code'] ?? '').' '.($error['message'] ?? ''));
			throw new DbWriteException('Penyimpanan data gagal ('.$context.').', (int) ($error['code'] ?? 0));
		}
		return $result;
	}
}

if ( ! function_exists('db_transaction'))
{
	/**
	 * Jalankan callback dalam transaction. Exception => rollback lalu dilempar ulang.
	 * Mendukung pemanggilan bersarang (hanya level terluar yang commit).
	 */
	function db_transaction(callable $callback)
	{
		$CI =& get_instance();
		static $depth = 0;
		if ($depth === 0)
		{
			$CI->db->trans_begin();
		}
		$depth++;
		try
		{
			$result = $callback();
			$depth--;
			if ($depth === 0)
			{
				if ($CI->db->trans_status() === FALSE)
				{
					$CI->db->trans_rollback();
					throw new DbWriteException('Transaksi dibatalkan karena ada query yang gagal.');
				}
				$CI->db->trans_commit();
			}
			return $result;
		}
		catch (Throwable $e)
		{
			$depth--;
			if ($depth === 0)
			{
				$CI->db->trans_rollback();
			}
			throw $e;
		}
	}
}

if ( ! class_exists('DbWriteException', FALSE))
{
	class DbWriteException extends RuntimeException {}
}

if ( ! class_exists('DomainRuleException', FALSE))
{
	/** Pelanggaran aturan bisnis yang aman ditampilkan ke pengguna. */
	class DomainRuleException extends RuntimeException {

		/** @var int HTTP status yang disarankan */
		public $http_status = 422;

		/** @var array error per field */
		public $errors = array();

		public function __construct($message, $http_status = 422, array $errors = array())
		{
			parent::__construct($message);
			$this->http_status = (int) $http_status;
			$this->errors = $errors;
		}
	}
}

if ( ! class_exists('AccessDeniedException', FALSE))
{
	class AccessDeniedException extends RuntimeException {}
}

if ( ! class_exists('VersionConflictException', FALSE))
{
	class VersionConflictException extends RuntimeException {}
}
