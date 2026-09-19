<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Profil warga: hanya field yang boleh diubah sendiri (allowlist). */
class Profil extends Resident_Controller {

	public function index()
	{
		$this->render('warga/profil', $this->data(), 'dashboard');
	}

	protected function data(array $extra = array())
	{
		$profile = $this->user_model->resident_profile($this->user->id);
		$areas = array();
		foreach ($this->db->where('type', 'hamlet')->order_by('name')->get('administrative_areas')->result() as $area)
		{
			$areas[(string) $area->id] = $area->name;
		}
		return array_merge(array(
			'page_title' => 'Profil Warga',
			'profile' => $profile,
			'hamlets' => $areas,
			'statuses' => app_config('resident_verification_statuses'),
		), $extra);
	}

	public function update()
	{
		$this->require_method('post');
		$display_name = trim($this->post_string('display_name', 100));
		$address = trim($this->post_string('address', 255));
		$rt = trim($this->post_string('rt', 5));
		$rw = trim($this->post_string('rw', 5));
		$hamlet_id = (int) $this->input->post('hamlet_id');
		$phone_raw = trim($this->post_string('phone', 30));
		$this->old_input = compact('display_name', 'address', 'rt', 'rw', 'phone_raw');

		$errors = array();
		if (mb_strlen($display_name) < 3 OR mb_strlen($display_name) > 100)
		{
			$errors['display_name'] = 'Nama wajib diisi, 3–100 karakter.';
		}
		foreach (array('rt' => $rt, 'rw' => $rw) as $key => $value)
		{
			if ($value !== '' && ! preg_match('/^[0-9]{1,4}$/', $value))
			{
				$errors[$key] = strtoupper($key).' berupa angka (maksimal 4 digit).';
			}
		}
		$phone = NULL;
		if ($phone_raw !== '')
		{
			$phone = $this->user_model->normalize_phone($phone_raw);
			if ($phone === FALSE)
			{
				$errors['phone'] = 'Nomor telepon tidak valid. Contoh: 081234567890.';
			}
		}
		if ($hamlet_id > 0 && $this->db->where(array('id' => $hamlet_id, 'type' => 'hamlet'))->count_all_results('administrative_areas') === 0)
		{
			$errors['hamlet_id'] = 'Pilih dusun yang tersedia.';
		}
		if ( ! empty($errors))
		{
			$this->form_errors = $errors;
			$this->output->set_status_header(422);
			$this->render('warga/profil', $this->data(array('save_error' => 'Periksa kembali isian profil Anda.')), 'dashboard');
			return;
		}

		db_transaction(function () use ($display_name, $address, $rt, $rw, $hamlet_id, $phone) {
			$this->user_model->update($this->user->id, array('display_name' => $display_name, 'phone' => $phone));
			db_must($this->db->where('user_id', (int) $this->user->id)->update('resident_profiles', array(
				'display_name' => $display_name,
				'address' => ($address === '') ? NULL : $address,
				'hamlet_id' => $hamlet_id > 0 ? $hamlet_id : NULL,
				'rt' => ($rt === '') ? NULL : $rt,
				'rw' => ($rw === '') ? NULL : $rw,
				'updated_at' => utc_now(),
			)), 'resident_profiles.update');
		});
		$this->audit->log('profile.updated', 'user', $this->user->public_id, array('fields' => array('display_name', 'address', 'hamlet', 'rt', 'rw', 'phone')));
		$this->flash('success', 'Profil Anda diperbarui.');
		redirect(site_url('warga/profil'), 'location', 303);
	}
}
