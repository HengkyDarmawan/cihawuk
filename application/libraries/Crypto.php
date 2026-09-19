<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Primitive kriptografi aplikasi berbasis libsodium dan hash_hmac.
 *
 * - Enkripsi terautentikasi (secretbox) untuk data privat; key per keperluan.
 * - HMAC dengan key terpisah untuk token, kode akses dan limiter.
 * - Pembangkit kode acak kriptografis (Crockford Base32).
 */
class Crypto {

	const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

	/** Pemetaan purpose => nama variabel environment key. */
	protected $key_env = array(
		'data' => 'APP_ENCRYPTION_KEY',
		'mfa' => 'APP_MFA_KEY',
	);

	protected $hmac_env = array(
		'token' => 'APP_TOKEN_PEPPER',
		'rate_limit' => 'APP_RATE_LIMIT_KEY',
	);

	/** Versi key aktif untuk enkripsi baru. */
	public function key_version()
	{
		return max(1, app_env_int('APP_ENCRYPTION_KEY_VERSION', 1));
	}

	protected function key($purpose, $version = NULL)
	{
		if ( ! isset($this->key_env[$purpose]))
		{
			throw new InvalidArgumentException('Unknown encryption purpose.');
		}
		$env = $this->key_env[$purpose];
		$version = ($version === NULL) ? $this->key_version() : (int) $version;
		// Key versi lama (untuk rotasi) disimpan sebagai APP_ENCRYPTION_KEY_V1, dst.
		$raw = ($version === $this->key_version()) ? app_env($env) : app_env($env.'_V'.$version);
		$key = base64_decode((string) $raw, TRUE);
		if ($key === FALSE OR strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
		{
			throw new RuntimeException('Encryption key is not configured. Run: php public/index.php tools generate_keys');
		}
		return $key;
	}

	protected function hmac_key($purpose)
	{
		if ( ! isset($this->hmac_env[$purpose]))
		{
			throw new InvalidArgumentException('Unknown HMAC purpose.');
		}
		$key = base64_decode((string) app_env($this->hmac_env[$purpose]), TRUE);
		if ($key === FALSE OR strlen($key) < 32)
		{
			throw new RuntimeException('HMAC key is not configured. Run: php public/index.php tools generate_keys');
		}
		return $key;
	}

	/** @return string format "v{versi}:{base64(nonce|ciphertext)}" */
	public function encrypt($plaintext, $purpose = 'data')
	{
		if ($plaintext === NULL)
		{
			return NULL;
		}
		$version = $this->key_version();
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$cipher = sodium_crypto_secretbox((string) $plaintext, $nonce, $this->key($purpose, $version));
		return 'v'.$version.':'.base64_encode($nonce.$cipher);
	}

	public function decrypt($payload, $purpose = 'data')
	{
		if ($payload === NULL OR $payload === '')
		{
			return NULL;
		}
		if ( ! preg_match('/^v(\d+):([A-Za-z0-9+\/=]+)$/', (string) $payload, $m))
		{
			throw new RuntimeException('Invalid encrypted payload.');
		}
		$raw = base64_decode($m[2], TRUE);
		if ($raw === FALSE OR strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)
		{
			throw new RuntimeException('Invalid encrypted payload.');
		}
		$nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key($purpose, (int) $m[1]));
		if ($plain === FALSE)
		{
			throw new RuntimeException('Decryption failed.');
		}
		return $plain;
	}

	public function hmac($value, $purpose = 'token')
	{
		return hash_hmac('sha256', (string) $value, $this->hmac_key($purpose));
	}

	public function equals($known, $user)
	{
		return is_string($known) && is_string($user) && hash_equals($known, $user);
	}

	/** Base32 Crockford tanpa padding. */
	public function base32_encode($bytes)
	{
		$bits = '';
		foreach (str_split($bytes) as $char)
		{
			$bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
		}
		$out = '';
		foreach (str_split($bits, 5) as $chunk)
		{
			$out .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
		}
		return $out;
	}

	/** String acak dari alfabet Base32 Crockford sepanjang $length karakter. */
	public function random_code($length)
	{
		$out = '';
		$max = strlen(self::BASE32_ALPHABET) - 1;
		for ($i = 0; $i < $length; $i++)
		{
			$out .= self::BASE32_ALPHABET[random_int(0, $max)];
		}
		return $out;
	}

	/**
	 * Normalisasi kode masukan pengguna: huruf besar, buang spasi/tanda hubung,
	 * I/L -> 1, O -> 0 (aturan Crockford). Karakter di luar alfabet -> kosong.
	 */
	public function normalize_code($input)
	{
		$code = strtoupper(preg_replace('/[\s\-]+/', '', (string) $input));
		$code = strtr($code, array('I' => '1', 'L' => '1', 'O' => '0'));
		return preg_match('/^['.self::BASE32_ALPHABET.']+$/', $code) ? $code : '';
	}

	public function group_code($code, $size = 4)
	{
		return implode('-', str_split((string) $code, $size));
	}

	/** ULID-like id publik 26 karakter (tidak berurutan untuk ditebak). */
	public function public_id()
	{
		return substr($this->base32_encode(random_bytes(17)), 0, 26);
	}

	public function random_hex($bytes = 16)
	{
		return bin2hex(random_bytes($bytes));
	}

	/**
	 * Token selector/validator. Selector (16 hex) untuk lookup, validator disimpan
	 * sebagai HMAC. Kembalian: [selector, validator, token_string, validator_hash].
	 */
	public function split_token($validator_bytes = 24)
	{
		$selector = bin2hex(random_bytes(8));
		$validator = $this->base32_encode(random_bytes($validator_bytes));
		return array($selector, $validator, $selector.'.'.$validator, $this->hmac($selector.'.'.$validator, 'token'));
	}
}
