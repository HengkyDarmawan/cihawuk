<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Profil warga</h1>
		<p>Data ini membantu petugas menindaklanjuti laporan Anda. NIK tidak diminta.</p>
	</div>
</div>

<?php if ( ! empty($save_error)): ?><div class="alert alert-danger" role="alert"><?= e($save_error) ?></div><?php endif; ?>

<div class="row">
	<div class="col-lg-7">
		<form class="card shadow-sm" method="post" action="<?= site_url('warga/profil') ?>" novalidate data-once>
			<div class="card-body">
				<?= csrf_field() ?>
				<?= ui_input(array('name' => 'display_name', 'label' => 'Nama', 'required' => TRUE, 'maxlength' => 100, 'minlength' => 3,
					'value' => old('display_name', $profile ? $profile->display_name : $user->display_name), 'autocomplete' => 'name')) ?>
				<?= ui_input(array('name' => 'address', 'label' => 'Alamat', 'maxlength' => 255,
					'value' => old('address', $profile ? $profile->address : ''), 'autocomplete' => 'street-address')) ?>
				<div class="form-row">
					<div class="col-md-6">
						<?= ui_select(array('name' => 'hamlet_id', 'label' => 'Dusun', 'options' => $hamlets, 'placeholder_option' => empty($hamlets) ? 'Daftar dusun belum tersedia' : 'Pilih dusun…',
							'value' => (string) ($profile && $profile->hamlet_id ? $profile->hamlet_id : ''), 'disabled' => empty($hamlets),
							'help' => empty($hamlets) ? 'Nama dusun belum dikonfirmasi pengelola desa.' : NULL)) ?>
					</div>
					<div class="col-md-3">
						<?= ui_input(array('name' => 'rt', 'label' => 'RT', 'maxlength' => 4, 'inputmode' => 'numeric', 'value' => old('rt', $profile ? $profile->rt : ''))) ?>
					</div>
					<div class="col-md-3">
						<?= ui_input(array('name' => 'rw', 'label' => 'RW', 'maxlength' => 4, 'inputmode' => 'numeric', 'value' => old('rw', $profile ? $profile->rw : ''))) ?>
					</div>
				</div>
				<?= ui_input(array('name' => 'phone', 'label' => 'Nomor telepon', 'type' => 'tel', 'maxlength' => 20, 'inputmode' => 'tel',
					'value' => old('phone_raw', $user->phone), 'help' => 'Contoh: 081234567890. Tidak dipakai untuk pesan otomatis.')) ?>
			</div>
			<div class="card-footer bg-white text-right">
				<button class="btn btn-primary" type="submit">Simpan profil</button>
			</div>
		</form>
	</div>

	<div class="col-lg-5">
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Status akun</h2></div>
			<div class="card-body">
				<dl class="dl-grid mb-0">
					<dt>Username</dt><dd><?= e($user->username) ?></dd>
					<dt>Status akun</dt><dd><?= e(config_label('account_statuses', $user->account_status)) ?></dd>
					<dt>Email</dt><dd><?= $user->email ? e($user->email).($user->email_verified_at ? ' <span class="chip-flag is-info">terverifikasi</span>' : ' <span class="chip-flag">belum diverifikasi</span>') : '—' ?></dd>
					<dt>Verifikasi profil</dt><dd><?= e(config_label('resident_verification_statuses', $profile ? $profile->verification_status : 'unverified')) ?></dd>
				</dl>
			</div>
		</div>
		<div class="card shadow-sm">
			<div class="card-header"><h2>Keamanan</h2></div>
			<div class="card-body">
				<p class="small">Ubah password, kelola sesi perangkat, dan aktifkan autentikasi dua langkah.</p>
				<a class="btn btn-outline-primary btn-sm" href="<?= site_url('warga/akun') ?>">Buka keamanan akun</a>
			</div>
		</div>
	</div>
</div>
