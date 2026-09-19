<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Adapter email tunggal (PHPMailer SMTP). Kredensial dari environment.
 * Bila SMTP belum dikonfigurasi, enabled() = FALSE dan tidak ada yang dianggap terkirim.
 */
class Mailer {

	/** @var CI_Controller */
	protected $CI;

	/** @var string|null error ringkas terakhir (tanpa kredensial) */
	public $last_error = NULL;

	/** Untuk pengujian: kumpulkan email alih-alih mengirim. */
	public $capture = FALSE;
	public $captured = array();

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function enabled()
	{
		if ($this->capture)
		{
			return TRUE;
		}
		return app_env_bool('MAIL_ENABLED', FALSE)
			&& trim((string) app_env('MAIL_HOST', '')) !== ''
			&& filter_var((string) app_env('MAIL_FROM', ''), FILTER_VALIDATE_EMAIL) !== FALSE;
	}

	public function send($to, $subject, $html, $text)
	{
		$this->last_error = NULL;
		if ( ! $this->enabled())
		{
			$this->last_error = 'not_configured';
			return FALSE;
		}
		if ($this->capture)
		{
			$this->captured[] = compact('to', 'subject', 'html', 'text');
			return TRUE;
		}
		$mail = new PHPMailer(TRUE);
		try
		{
			$mail->isSMTP();
			$mail->Host = (string) app_env('MAIL_HOST');
			$mail->Port = app_env_int('MAIL_PORT', 587);
			$enc = strtolower((string) app_env('MAIL_ENCRYPTION', 'tls'));
			$mail->SMTPSecure = ($enc === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : (($enc === 'none') ? '' : PHPMailer::ENCRYPTION_STARTTLS);
			$mail->SMTPAutoTLS = ($enc !== 'none');
			if ((string) app_env('MAIL_USERNAME', '') !== '')
			{
				$mail->SMTPAuth = TRUE;
				$mail->Username = (string) app_env('MAIL_USERNAME');
				$mail->Password = (string) app_env('MAIL_PASSWORD');
			}
			$mail->Timeout = 15;
			$mail->CharSet = PHPMailer::CHARSET_UTF8;
			$mail->setFrom((string) app_env('MAIL_FROM'), (string) app_env('MAIL_FROM_NAME', 'Pelayanan Desa Cihawuk'));
			$mail->addAddress($to);
			$mail->Subject = $subject;
			$mail->isHTML(TRUE);
			$mail->Body = $html;
			$mail->AltBody = $text;
			$mail->send();
			return TRUE;
		}
		catch (MailException $e)
		{
			// Pesan PHPMailer bisa memuat detail server; simpan ringkas saja.
			$this->last_error = 'smtp_error';
			log_message('error', 'Mail send failed: '.preg_replace('/[^\w\s.:-]/', '', substr($mail->ErrorInfo, 0, 120)));
			return FALSE;
		}
	}

	/**
	 * Email akun (aktivasi/reset). Dikirim langsung, token tidak disimpan di outbox.
	 */
	public function send_account_email($to, $template, array $vars)
	{
		$subjects = array(
			'activation' => 'Aktivasi akun Layanan Desa Cihawuk',
			'password_reset' => 'Reset password Layanan Desa Cihawuk',
		);
		if ( ! isset($subjects[$template]))
		{
			throw new InvalidArgumentException('Unknown email template');
		}
		$html = $this->CI->load->view('email/'.$template, $vars + array('format' => 'html'), TRUE);
		$text = $this->CI->load->view('email/'.$template, $vars + array('format' => 'text'), TRUE);
		return $this->send($to, $subjects[$template], $html, $text);
	}
}
