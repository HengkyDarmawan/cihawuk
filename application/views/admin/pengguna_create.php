<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Daftarkan akun warga</h1>
		<p>Akun dibuat tanpa password. Warga menetapkan passwordnya sendiri melalui kode aktivasi sekali pakai.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/pengguna') ?>">Kembali</a>
</div>

<?php if ( ! empty($create_error)): ?><div class="alert alert-danger" role="alert"><?= e($create_error) ?></div><?php endif; ?>
<?= ui_error_summary(array('display_name' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'phone' => 'Telepon')) ?>

<div class="row">
	<div class="col-lg-7">
		<form class="card shadow-sm" method="post" action="<?= site_url('admin/pengguna/buat') ?>" novalidate data-once>
			<div class="card-body">
				<?= csrf_field() ?>
				<?= ui_input(array('name' => 'display_name', 'label' => 'Nama warga', 'required' => TRUE, 'maxlength' => 100, 'minlength' => 3)) ?>
				<?= ui_input(array('name' => 'username', 'label' => 'Username', 'required' => TRUE, 'maxlength' => 50, 'minlength' => 4,
					'help' => 'Huruf kecil, angka, titik atau garis bawah. Jangan memakai NIK sebagai username.', 'raw_attrs' => 'autocapitalize="none" spellcheck="false"')) ?>
				<?= ui_input(array('name' => 'email', 'label' => 'Email', 'type' => 'email', 'maxlength' => 191, 'help' => 'Opsional. Bila diisi dan SMTP aktif, warga dapat memulihkan password sendiri.')) ?>
				<?= ui_input(array('name' => 'phone', 'label' => 'Telepon', 'type' => 'tel', 'maxlength' => 20, 'inputmode' => 'tel')) ?>
				<div class="alert alert-info small mb-0">
					Akun dibuat dengan role <strong>Warga</strong> saja. Role petugas hanya dapat diberikan Super Admin melalui halaman detail pengguna.
					Tidak ada password seragam: sistem membuat kode aktivasi acak yang tampil satu kali.
				</div>
			</div>
			<div class="card-footer bg-white text-right">
				<button class="btn btn-primary" type="submit">Buat akun &amp; kode aktivasi</button>
			</div>
		</form>
	</div>
	<div class="col-lg-5">
		<div class="card shadow-sm">
			<div class="card-header"><h2>Prosedur loket</h2></div>
			<div class="card-body">
				<ol class="small mb-0 pl-3">
					<li>Periksa identitas warga sesuai prosedur desa.</li>
					<li>Isi formulir ini, lalu serahkan kode aktivasi kepada warga secara langsung.</li>
					<li>Warga membuka halaman aktivasi dan menetapkan password sendiri.</li>
					<li>Jangan menuliskan atau menyimpan password warga. Petugas tidak pernah mengetahui password.</li>
				</ol>
			</div>
		</div>
	</div>
</div>
