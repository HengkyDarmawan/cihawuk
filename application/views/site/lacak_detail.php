<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->helper('ticket');
$t = $ticket;
$timeline = ticket_timeline($history, $messages, 'reporter');
$can = function ($action) use ($actions) { return in_array($action, $actions, TRUE); };
?>
<section class="page-hero">
	<div class="container-site">
		<p class="eyebrow" style="color:#F5D9A6">Laporan anonim</p>
		<h1><?= e($t['code']) ?></h1>
		<p><?= e(config_label('report_types', $t['report_type'])) ?> · <?= e($t['category']) ?> · dikirim <?= e(format_wib($t['submitted_at'], 'date')) ?></p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ( ! empty($action_error)): ?>
			<div class="alert alert-danger d-flex gap-2" role="alert"><?= icon('alert-circle') ?><div><?= e($action_error) ?></div></div>
		<?php endif; ?>

		<div class="row g-4">
			<div class="col-lg-8">
				<div class="card-soft card-pad mb-4">
					<div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
						<h2 class="h4 mb-0"><?= e($t['title']) ?></h2>
						<?= ticket_status_badge($t['status'], 'reporter') ?>
					</div>
					<?php if ($t['confidentiality'] === 'restricted'): ?>
						<p class="chip-flag mb-3"><?= icon('lock') ?> Laporan rahasia — hanya petugas berizin khusus yang dapat membukanya.</p>
					<?php endif; ?>
					<p class="pre-wrap"><?= e($t['description']) ?></p>
					<dl class="fact-list">
						<?php if ($t['location_text']): ?><div><dt>Lokasi</dt><dd><?= e($t['location_text']) ?></dd></div><?php endif; ?>
						<?php if ($t['incident_date']): ?><div><dt>Tanggal kejadian</dt><dd><?= e(format_date_id($t['incident_date'])) ?></dd></div><?php endif; ?>
						<div><dt>Dikirim</dt><dd><?= e(format_wib($t['submitted_at'])) ?></dd></div>
						<?php if ($t['resolved_at']): ?><div><dt>Selesai</dt><dd><?= e(format_wib($t['resolved_at'])) ?></dd></div><?php endif; ?>
					</dl>
					<h3 class="h6 mt-4">Lampiran</h3>
					<?= ticket_attachment_list($attachments) ?>
				</div>

				<div class="card-soft card-pad">
					<h2 class="h5 mb-3">Perkembangan laporan</h2>
					<?= ticket_timeline_html($timeline, 'reporter') ?>
				</div>
			</div>

			<aside class="col-lg-4">
				<?php if ($t['withdrawal_requested']): ?>
					<div class="alert alert-info d-flex gap-2" role="status"><?= icon('info') ?><div>Permintaan penarikan Anda sudah dicatat dan sedang ditinjau petugas.</div></div>
				<?php endif; ?>

				<?php if ($can('provide_information')): ?>
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('help-circle') ?> Petugas meminta kelengkapan</h2>
					<form method="post" action="<?= site_url('lacak/balas') ?>" data-once>
						<?= csrf_field() ?>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Jawaban Anda', 'required' => TRUE, 'minlength' => 5, 'maxlength' => 5000, 'rows' => 5, 'value' => '')) ?>
						<button class="btn btn-primary w-100" type="submit">Kirim kelengkapan</button>
					</form>
				</div>
				<?php elseif ($can('reply')): ?>
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('message-square') ?> Tambahkan keterangan</h2>
					<form method="post" action="<?= site_url('lacak/balas') ?>" data-once>
						<?= csrf_field() ?>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Pesan untuk petugas', 'required' => TRUE, 'minlength' => 5, 'maxlength' => 5000, 'rows' => 4, 'value' => '')) ?>
						<button class="btn btn-primary w-100" type="submit">Kirim balasan</button>
					</form>
				</div>
				<?php endif; ?>

				<?php if ($can('accept_result')): ?>
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('check-circle') ?> Tanggapi hasil penanganan</h2>
					<form method="post" action="<?= site_url('lacak/konfirmasi') ?>" data-once>
						<?= csrf_field() ?>
						<fieldset class="mb-3">
							<legend class="form-label">Tanggapan Anda</legend>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="choice" id="choice-accept" value="accept" checked>
								<label class="form-check-label" for="choice-accept">Saya menerima hasil ini</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="choice" id="choice-followup" value="followup">
								<label class="form-check-label" for="choice-followup">Saya meminta tindak lanjut</label>
							</div>
						</fieldset>
						<?= ui_select(array('name' => 'rating', 'label' => 'Penilaian layanan', 'options' => array('5' => '5 — Sangat baik', '4' => '4 — Baik', '3' => '3 — Cukup', '2' => '2 — Kurang', '1' => '1 — Buruk'), 'placeholder_option' => 'Tidak menilai', 'value' => '')) ?>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Catatan', 'rows' => 3, 'maxlength' => 5000, 'value' => '', 'help' => 'Wajib diisi bila Anda meminta tindak lanjut.')) ?>
						<button class="btn btn-primary w-100" type="submit">Kirim tanggapan</button>
					</form>
				</div>
				<?php endif; ?>

				<?php if ($can('withdraw') OR $can('request_withdrawal')): ?>
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('corner-up-left') ?> <?= $can('withdraw') ? 'Tarik laporan' : 'Minta penarikan' ?></h2>
					<p class="small text-muted"><?= $can('withdraw')
						? 'Laporan tidak akan diproses lebih lanjut. Riwayat penerimaan tetap tersimpan.'
						: 'Penanganan sudah berjalan, sehingga penarikan menjadi permintaan kepada petugas.' ?></p>
					<form method="post" action="<?= site_url('lacak/tarik') ?>" data-once>
						<?= csrf_field() ?>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Alasan', 'rows' => 3, 'maxlength' => 1000, 'value' => '', 'required' => ! $can('withdraw'))) ?>
						<button class="btn btn-outline-primary w-100" type="submit"><?= $can('withdraw') ? 'Tarik laporan' : 'Kirim permintaan penarikan' ?></button>
					</form>
				</div>
				<?php endif; ?>

				<?php if ($logged_in): ?>
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('link') ?> Kaitkan ke akun Anda</h2>
					<p class="small text-muted">Laporan ini dapat dihubungkan dengan akun yang sedang Anda pakai agar muncul di dashboard warga. Identitas Anda tetap disembunyikan dari petugas biasa.</p>
					<form method="post" action="<?= site_url('lacak/klaim') ?>" data-once>
						<?= csrf_field() ?>
						<div class="form-check mb-2">
							<input class="form-check-input" type="checkbox" id="agree-claim" name="agree" value="1" required>
							<label class="form-check-label small" for="agree-claim">Saya setuju laporan ini dikaitkan dengan akun saya mulai sekarang.</label>
						</div>
						<button class="btn btn-outline-primary btn-sm w-100" type="submit">Kaitkan ke akun</button>
					</form>
				</div>
				<?php endif; ?>

				<div class="card-soft card-pad">
					<h2 class="h6"><?= icon('log-out') ?> Selesai memeriksa?</h2>
					<p class="small text-muted">Tutup sesi pelacakan bila Anda memakai perangkat bersama.</p>
					<form method="post" action="<?= site_url('lacak/keluar') ?>">
						<?= csrf_field() ?>
						<button class="btn btn-outline-primary btn-sm w-100" type="submit">Keluar dari sesi pelacakan</button>
					</form>
				</div>
			</aside>
		</div>
	</div>
</section>
