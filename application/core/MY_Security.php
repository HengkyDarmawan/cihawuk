<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penyesuaian respons CSRF CI3:
 * - Request AJAX menerima JSON 403 terstruktur beserta token terbaru.
 * - Unggahan melebihi post_max_size dilaporkan sebagai 413, bukan kesalahan token.
 */
class MY_Security extends CI_Security {

	public function csrf_verify()
	{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && $this->post_body_too_large())
		{
			$this->csrf_set_cookie();
			$this->respond(413, 'payload_too_large', 'Ukuran data atau berkas yang dikirim melebihi batas server (maksimal total 15 MB).');
		}
		return parent::csrf_verify();
	}

	public function csrf_show_error()
	{
		$this->respond(403, 'csrf_invalid', 'Sesi formulir sudah kedaluwarsa atau dibuka di tab lain. Muat ulang halaman, periksa isian Anda, lalu kirim kembali.');
	}

	protected function post_body_too_large()
	{
		$length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		$limit = $this->ini_bytes((string) ini_get('post_max_size'));
		return $limit > 0 && $length > $limit;
	}

	protected function ini_bytes($value)
	{
		$value = trim($value);
		$unit = strtolower(substr($value, -1));
		$number = (int) $value;
		switch ($unit)
		{
			case 'g': return $number * 1073741824;
			case 'm': return $number * 1048576;
			case 'k': return $number * 1024;
			default: return (int) $value;
		}
	}

	protected function respond($status, $code, $message)
	{
		$accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
		$is_ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest' || stripos($accept, 'application/json') !== FALSE;
		log_message('info', 'Security rejection '.$code);
		if ($is_ajax)
		{
			http_response_code($status);
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store');
			echo json_encode(array(
				'success' => FALSE,
				'code' => $code,
				'message' => $message,
				'csrf' => array('name' => $this->get_csrf_token_name(), 'hash' => $this->get_csrf_hash()),
				'refresh_url' => '/csrf-token',
			), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit(EXIT_ERROR);
		}
		show_error($message, $status, ($status === 413) ? 'Berkas terlalu besar' : 'Sesi formulir kedaluwarsa');
	}
}
