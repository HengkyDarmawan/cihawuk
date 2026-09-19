<?php

/**
 * Basis pengujian HTTP: menjalankan PHP built-in server (APP_ENV=testing)
 * dan klien cURL dengan cookie jar terpisah per "peramban".
 */
abstract class HttpTestCase extends CiTestCase {

	const PORT = 8766;

	/** @var resource|null */
	protected static $server = NULL;
	protected static $pipes = array();

	/** @var array<string,string> nama => path cookie jar */
	protected $jars = array();

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		if (self::$server !== NULL)
		{
			return;
		}
		$root = ROOTPATH;
		$env = array_merge(getenv(), array(
			'APP_ENV' => 'testing',
			'APP_BASE_URL' => 'http://127.0.0.1:'.self::PORT.'/',
		));
		$cmd = '"'.PHP_BINARY.'" -S 127.0.0.1:'.self::PORT.' -t "'.$root.'public" "'.$root.'scripts/dev-router.php"';
		$log = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chw-http-test.log';
		self::$server = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('file', $log, 'a'), 2 => array('file', $log, 'a')), self::$pipes, $root, $env, array('bypass_shell' => TRUE));
		$deadline = microtime(TRUE) + 15;
		while (microtime(TRUE) < $deadline)
		{
			$fp = @fsockopen('127.0.0.1', self::PORT, $errno, $errstr, 0.3);
			if ($fp)
			{
				fclose($fp);
				register_shutdown_function(array(__CLASS__, 'stop_server'));
				return;
			}
			usleep(200000);
		}
		throw new RuntimeException('Server uji tidak dapat dijalankan pada port '.self::PORT);
	}

	public static function stop_server()
	{
		if (self::$server !== NULL)
		{
			$status = proc_get_status(self::$server);
			if ( ! empty($status['pid']))
			{
				// Hentikan proses server beserta anaknya (Windows).
				exec('taskkill /F /T /PID '.(int) $status['pid'].' 2>NUL');
			}
			@proc_terminate(self::$server);
			self::$server = NULL;
		}
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->db->query('DELETE FROM rate_limits');
	}

	protected function tearDown(): void
	{
		foreach ($this->jars as $jar)
		{
			@unlink($jar);
		}
		$this->jars = array();
		parent::tearDown();
	}

	protected function jar($name)
	{
		if ( ! isset($this->jars[$name]))
		{
			$this->jars[$name] = tempnam(sys_get_temp_dir(), 'chwjar');
		}
		return $this->jars[$name];
	}

	protected function url($path)
	{
		return 'http://127.0.0.1:'.self::PORT.'/'.ltrim($path, '/');
	}

	/**
	 * @return array{status:int, body:string, headers:array, location:?string}
	 */
	protected function request($method, $path, $browser = 'default', $fields = NULL, array $headers = array())
	{
		$ch = curl_init($this->url($path));
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HEADER => TRUE,
			CURLOPT_FOLLOWLOCATION => FALSE,
			CURLOPT_COOKIEJAR => $this->jar($browser),
			CURLOPT_COOKIEFILE => $this->jar($browser),
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => $headers,
		));
		if ($fields !== NULL)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		}
		$raw = curl_exec($ch);
		if ($raw === FALSE)
		{
			throw new RuntimeException('HTTP request gagal: '.curl_error($ch));
		}
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$header_size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		$header_text = substr($raw, 0, $header_size);
		$body = substr($raw, $header_size);
		$parsed = array();
		foreach (preg_split('/\r?\n/', $header_text) as $line)
		{
			if (strpos($line, ':') !== FALSE)
			{
				list($k, $v) = explode(':', $line, 2);
				$parsed[strtolower(trim($k))][] = trim($v);
			}
		}
		return array(
			'status' => $status,
			'body' => $body,
			'headers' => $parsed,
			'location' => $parsed['location'][0] ?? NULL,
		);
	}

	protected function get($path, $browser = 'default', array $headers = array())
	{
		return $this->request('GET', $path, $browser, NULL, $headers);
	}

	/** POST dengan token CSRF yang diambil dari halaman $form_path. */
	protected function post_form($form_path, $action_path, array $fields, $browser = 'default', $multipart = FALSE)
	{
		$page = $this->get($form_path, $browser);
		$fields[$this->CI->config->item('csrf_token_name')] = $this->csrf_from($page['body']);
		return $this->request('POST', $action_path, $browser, $multipart ? $fields : http_build_query($fields));
	}

	protected function csrf_from($html)
	{
		if ( ! preg_match('/name="csrf_chw" value="([a-f0-9]{32})"/', $html, $m))
		{
			$this->fail('Token CSRF tidak ditemukan pada halaman.');
		}
		return $m[1];
	}

	protected function login($identifier, $password, $browser)
	{
		$response = $this->post_form('masuk', 'masuk', array('identifier' => $identifier, 'password' => $password), $browser);
		return $response;
	}

	protected function session_cookie($browser)
	{
		$content = (string) @file_get_contents($this->jar($browser));
		return preg_match('/chw_session\s+(\S+)/', $content, $m) ? $m[1] : NULL;
	}
}
