<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengambilan token CSRF terbaru untuk pemulihan form AJAX yang tokennya kedaluwarsa.
 * GET saja, tidak mengubah apa pun, same-origin, no-store.
 */
class Csrf extends MY_Controller {

	public function token()
	{
		$this->require_method('get');
		$origin = (string) $this->input->get_request_header('Origin');
		if ($origin !== '' && rtrim($origin, '/') !== rtrim(base_url(), '/'))
		{
			throw new AccessDeniedException('Cross-origin token request');
		}
		$this->no_store = TRUE;
		$this->json(200, array('success' => TRUE, 'message' => 'Token diperbarui.'));
	}
}
