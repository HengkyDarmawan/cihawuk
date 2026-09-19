<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller khusus CLI. Menolak HTTP sebelum menjalankan method apa pun.
 */
class Cli_Controller extends MY_Controller {

	public function __construct()
	{
		if ( ! is_cli())
		{
			header('HTTP/1.1 404 Not Found', TRUE, 404);
			exit(EXIT_UNKNOWN_METHOD);
		}
		parent::__construct();
	}

	public function _remap($method, $params = array())
	{
		if ( ! $this->is_dispatchable($method))
		{
			$this->line('Perintah tidak dikenal: '.$method);
			exit(EXIT_UNKNOWN_METHOD);
		}
		call_user_func_array(array($this, $method), $params);
	}

	protected function line($text = '')
	{
		fwrite(STDOUT, $text.PHP_EOL);
	}

	protected function error($text)
	{
		fwrite(STDERR, $text.PHP_EOL);
	}

	protected function ask($question, $default = NULL)
	{
		fwrite(STDOUT, $question.($default !== NULL ? ' ['.$default.']' : '').': ');
		$answer = trim((string) fgets(STDIN));
		return ($answer === '' && $default !== NULL) ? $default : $answer;
	}

	/**
	 * Baca rahasia tanpa menampilkan ketikan bila berjalan di terminal interaktif.
	 * Nilai tidak pernah diterima lewat argumen command line (tidak masuk history).
	 */
	protected function ask_secret($question)
	{
		$interactive = function_exists('stream_isatty') && stream_isatty(STDIN);
		if ($interactive && DIRECTORY_SEPARATOR === '\\')
		{
			fwrite(STDOUT, $question.': ');
			$cmd = 'powershell -NoProfile -NonInteractive -Command "$s = Read-Host -AsSecureString; '
				.'[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))"';
			$value = shell_exec($cmd);
			if (is_string($value))
			{
				return rtrim($value, "\r\n");
			}
		}
		elseif ($interactive)
		{
			fwrite(STDOUT, $question.': ');
			shell_exec('stty -echo');
			$value = rtrim((string) fgets(STDIN), "\r\n");
			shell_exec('stty echo');
			fwrite(STDOUT, PHP_EOL);
			return $value;
		}
		fwrite(STDOUT, $question.': ');
		return rtrim((string) fgets(STDIN), "\r\n");
	}
}
