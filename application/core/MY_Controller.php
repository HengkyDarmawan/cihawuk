<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller bersama: koneksi DB (UTC), request ID, header keamanan,
 * layout dan penanganan exception terpusat.
 *
 * @property CI_DB_query_builder $db
 * @property CI_Session $session
 * @property Clock $clock
 * @property Crypto $crypto
 * @property Settings $settings
 * @property AuditService $audit
 * @property FeatureModuleService $modules
 * @property RateLimiter $rate_limiter
 * @property AuthService $auth
 * @property AuthorizationService $authz
 * @property NotificationService $notifications
 * @property Mailer $mailer
 * @property Totp $totp
 */
class MY_Controller extends CI_Controller {

	/** @var string */
	public $request_id;

	/** @var array error validasi per field untuk view */
	public $form_errors = array();

	/** @var array nilai input sebelumnya untuk view */
	public $old_input = array();

	/** @var array data yang dibagikan ke layout */
	protected $layout_data = array();

	/** @var bool apakah respons harus no-store */
	protected $no_store = FALSE;

	/** @var string nilai Referrer-Policy */
	protected $referrer_policy = 'strict-origin-when-cross-origin';

	public function __construct()
	{
		parent::__construct();
		$this->request_id = bin2hex(random_bytes(16));

		$this->load->database();
		$this->db->query("SET time_zone = '+00:00'");

		$this->load->helper(array('url', 'app', 'db', 'form', 'form_ui'));
		$this->config->load('app', TRUE);
		$this->load->library('Clock', NULL, 'clock');
		$this->load->library('Crypto', NULL, 'crypto');
		$this->load->library('Settings', NULL, 'settings');
		$this->load->library('AuditService', NULL, 'audit');
		$this->load->library('RateLimiter', NULL, 'rate_limiter');
		$this->load->library('Mailer', NULL, 'mailer');
		$this->load->library('NotificationService', NULL, 'notifications');

		$this->audit->request_id = $this->request_id;

		if ( ! is_cli())
		{
			$this->send_security_headers();
		}
	}

	protected function start_session()
	{
		if ( ! isset($this->session))
		{
			$this->load->library('session');
		}
		$this->load->library('Totp', NULL, 'totp');
		$this->load->library('AuthService', NULL, 'auth');
		$this->load->library('AuthorizationService', NULL, 'authz');
		$user = $this->auth->user();
		$this->audit->actor_user_id = $user ? (int) $user->id : NULL;
	}

	protected function send_security_headers()
	{
		$tile_host = parse_url((string) app_env('MAP_TILE_URL', ''), PHP_URL_HOST);
		$img_src = "'self' data: blob:";
		if ($tile_host)
		{
			$img_src .= ' https://'.str_replace('{s}.', '*.', $tile_host);
		}
		$csp = array(
			"default-src 'self'",
			"script-src 'self'",
			"style-src 'self' 'unsafe-inline'",
			'img-src '.$img_src,
			"font-src 'self'",
			"connect-src 'self'",
			"media-src 'self'",
			"frame-src https://www.youtube-nocookie.com",
			"object-src 'none'",
			"base-uri 'self'",
			"form-action 'self'",
			"frame-ancestors 'none'",
		);
		$this->output->set_header('Content-Security-Policy: '.implode('; ', $csp));
		$this->output->set_header('X-Content-Type-Options: nosniff');
		$this->output->set_header('X-Frame-Options: DENY');
		$this->output->set_header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=()');
		$this->output->set_header('X-Request-Id: '.$this->request_id);
	}

	/** Terapkan header cache/referrer final sebelum output. */
	protected function finalize_headers()
	{
		$this->output->set_header('Referrer-Policy: '.$this->referrer_policy);
		if ($this->no_store)
		{
			$this->output->set_header('Cache-Control: no-store, max-age=0');
			$this->output->set_header('Pragma: no-cache');
			$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		}
	}

	/**
	 * Dispatch terpusat: hanya method publik yang dideklarasikan controller konkret,
	 * serta konversi exception domain menjadi respons HTTP yang aman.
	 */
	public function _remap($method, $params = array())
	{
		if ( ! $this->is_dispatchable($method))
		{
			$this->not_found();
			return;
		}
		if ( ! $this->module_route_allowed())
		{
			return;
		}
		try
		{
			call_user_func_array(array($this, $method), $params);
		}
		catch (AccessDeniedException $e)
		{
			log_message('info', 'Access denied ['.$this->request_id.']: '.$e->getMessage());
			$this->forbidden();
		}
		catch (VersionConflictException $e)
		{
			$this->respond_error(409, 'Data telah diubah oleh pengguna lain. Muat ulang halaman untuk melihat versi terbaru.');
		}
		catch (DomainRuleException $e)
		{
			$this->respond_error($e->http_status, $e->getMessage(), $e->errors);
		}
		catch (DbWriteException $e)
		{
			log_message('error', 'DbWriteException ['.$this->request_id.']: '.$e->getMessage());
			$this->respond_error(500, 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.');
		}
		finally
		{
			$this->finalize_headers();
		}
	}

	/**
	 * Guard status modul: URL langsung tetap diperiksa, bukan hanya menu yang disembunyikan.
	 * Modul nonaktif → 404; modul internal_only → 404 pada area publik; maintenance → 503.
	 */
	protected function module_route_allowed()
	{
		if (is_cli())
		{
			return TRUE;
		}
		$this->load->library('FeatureModuleService', NULL, 'modules');
		$match = $this->modules->match_route($this->uri->uri_string());
		if ($match === NULL)
		{
			return TRUE;
		}
		list($module, $area) = $match;

		if ($area === 'admin')
		{
			if ($this->modules->backend_available($module->code))
			{
				return TRUE;
			}
			$this->not_found();
			$this->finalize_headers();
			return FALSE;
		}

		$status = $this->modules->public_status($module->code);
		if ($status === 'available')
		{
			return TRUE;
		}
		if ($status === 'maintenance')
		{
			$this->respond_error(503, 'Layanan '.$module->name.' sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.');
			$this->finalize_headers();
			return FALSE;
		}
		$this->not_found();
		$this->finalize_headers();
		return FALSE;
	}

	protected function is_dispatchable($method)
	{
		if ( ! is_string($method) OR $method === '' OR $method[0] === '_' OR ! method_exists($this, $method))
		{
			return FALSE;
		}
		$ref = new ReflectionMethod($this, $method);
		if ( ! $ref->isPublic() OR $ref->isStatic())
		{
			return FALSE;
		}
		$declaring = $ref->getDeclaringClass()->getName();
		return ! in_array($declaring, array('CI_Controller', 'MY_Controller', 'Public_Controller', 'Resident_Controller', 'Admin_Controller', 'Cli_Controller'), TRUE);
	}

	// ------------------------------------------------------------------
	// Request helpers
	// ------------------------------------------------------------------

	protected function is_ajax()
	{
		return $this->input->is_ajax_request() OR stripos((string) $this->input->get_request_header('Accept'), 'application/json') !== FALSE;
	}

	/** Pastikan metode HTTP sesuai; selain itu 405. */
	protected function require_method($method)
	{
		if ($this->input->method(TRUE) !== strtoupper($method))
		{
			$this->output->set_header('Allow: '.strtoupper($method));
			throw new DomainRuleException('Metode tidak diizinkan.', 405);
		}
	}

	protected function post_string($key, $max = 10000)
	{
		$value = $this->input->post($key, FALSE);
		if ( ! is_string($value))
		{
			return '';
		}
		// Normalisasi baris dan buang karakter kontrol selain tab/baris baru.
		$value = str_replace(array("\r\n", "\r"), "\n", $value);
		$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
		return mb_substr($value, 0, $max + 1);
	}

	// ------------------------------------------------------------------
	// Responses
	// ------------------------------------------------------------------

	protected function render($view, array $data = array(), $layout = 'site')
	{
		$data = array_merge($this->layout_data, $data);
		$data['content'] = $this->load->view($view, $data, TRUE);
		$this->load->view('layouts/'.$layout, $data);
	}

	protected function json($status, array $payload)
	{
		$payload['csrf'] = array(
			'name' => $this->security->get_csrf_token_name(),
			'hash' => $this->security->get_csrf_hash(),
		);
		$this->no_store = TRUE;
		$this->output->set_status_header($status)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	protected function flash($type, $message)
	{
		if (isset($this->session))
		{
			$this->session->set_flashdata('flash', array('type' => $type, 'message' => $message));
		}
	}

	protected function redirect_to($path, $code = 303)
	{
		redirect(site_url(ltrim($path, '/')), 'location', $code);
	}

	protected function respond_error($status, $message, array $errors = array())
	{
		if ($this->is_ajax())
		{
			$this->json($status, array('success' => FALSE, 'message' => $message, 'errors' => $errors));
			return;
		}
		if ($status === 422 && ! empty($errors) && isset($this->session))
		{
			$this->form_errors = $errors;
		}
		$this->output->set_status_header($status);
		$this->no_store = TRUE;
		$this->render('errors/app_error', array(
			'status' => $status,
			'title' => $this->status_title($status),
			'message' => $message,
			'errors' => $errors,
			'page_title' => $this->status_title($status),
		), $this->error_layout());
	}

	protected function status_title($status)
	{
		$titles = array(
			401 => 'Perlu masuk', 403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan', 405 => 'Metode tidak diizinkan',
			409 => 'Terjadi konflik data', 410 => 'Tidak lagi tersedia', 413 => 'Berkas terlalu besar', 419 => 'Sesi formulir kedaluwarsa',
			422 => 'Data belum valid', 429 => 'Terlalu banyak percobaan', 500 => 'Terjadi kesalahan',
		);
		return isset($titles[$status]) ? $titles[$status] : 'Terjadi kesalahan';
	}

	protected function error_layout()
	{
		return 'site';
	}

	public function not_found_response()
	{
		$this->not_found();
	}

	protected function not_found()
	{
		$this->respond_error(404, 'Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.');
	}

	protected function forbidden()
	{
		$this->respond_error(403, 'Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini.');
	}

	protected function too_many($retry_after)
	{
		$this->output->set_header('Retry-After: '.(int) $retry_after);
		$minutes = max(1, (int) ceil($retry_after / 60));
		$this->respond_error(429, 'Terlalu banyak percobaan dari jaringan atau akun ini. Silakan coba lagi dalam sekitar '.$minutes.' menit.');
	}
}

require_once APPPATH.'core/Public_Controller.php';
require_once APPPATH.'core/Resident_Controller.php';
require_once APPPATH.'core/Admin_Controller.php';
require_once APPPATH.'core/Cli_Controller.php';
