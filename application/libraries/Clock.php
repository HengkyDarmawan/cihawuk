<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sumber waktu aplikasi (UTC). Pengujian dapat menetapkan waktu tetap
 * agar perhitungan SLA tidak bergantung pada tanggal mesin.
 */
class Clock {

	/** @var DateTimeImmutable|null */
	protected $fixed = NULL;

	public function now()
	{
		return ($this->fixed !== NULL) ? $this->fixed : new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}

	public function set($datetime)
	{
		$this->fixed = ($datetime === NULL) ? NULL : new DateTimeImmutable((string) $datetime, new DateTimeZone('UTC'));
	}

	public function advance($interval_spec)
	{
		$this->fixed = $this->now()->add(new DateInterval($interval_spec));
	}

	public function timestamp()
	{
		return $this->now()->getTimestamp();
	}

	public function format($format = 'Y-m-d H:i:s')
	{
		return $this->now()->format($format);
	}

	/** DATETIME UTC relatif terhadap sekarang, mis. plus_seconds(1800). */
	public function plus_seconds($seconds)
	{
		return $this->now()->modify(((int) $seconds >= 0 ? '+' : '').(int) $seconds.' seconds')->format('Y-m-d H:i:s');
	}
}
