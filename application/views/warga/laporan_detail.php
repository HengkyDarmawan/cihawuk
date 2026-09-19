<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->helper('ticket');
$timeline = ticket_timeline($history, $messages, 'reporter');
$can = function ($action) use ($actions) { return in_array($action, $actions, TRUE); };
?>
<div class="page-heading">
	<div>
		<h1><?= e($ticket->title) ?></h1>
		<p><?= e($ticket->public_code) ?> · <?= e(config_label('report_types', $ticket->report_type)) ?> · <?= e($category ? $category->name : '—') ?></p>
	</div>
	<div class="d-flex flex-column align-items-end gap-2">
		<?= ticket_status_badge($ticket->status, 'reporter') ?>
		<a class="btn btn-outline-primary btn-sm mt-2" href="<?= site_url('warga/laporan') ?>">Kembali ke daftar</a>
	</div>
</div>

<?php if ( ! empty($action_error)): ?>
	<div class="alert alert-danger" role="alert"><?= e($action_error) ?></div>
<?php endif; ?>
<?php if ($ticket->withdrawal_requested_at !== NULL && ! in_array($ticket->status, array('withdrawn', 'resolved'), TRUE)): ?>
	<div class="alert alert-info" role="status">Permintaan penarikan Anda tercatat pada <?= e(format_wib($ticket->withdrawal_requested_at)) ?> dan sedang ditinjau petugas.</div>
<?php endif; ?>

<div class="row">
	<div class="col-lg-8">
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Isi laporan</h2></div>
			<div class="card-body">
				<p class="pre-wrap"><?= e($ticket->description) ?></p>
				<dl class="dl-grid mt-3">
					<dt>Dikirim</dt><dd><?= e(format_wib($ticket->submitted_at)) ?></dd>
					<?php if ($ticket->incident_date): ?><dt>Tanggal kejadian</dt><dd><?= e(format_date_id($ticket->incident_date)) ?></dd><?php endif; ?>
					<?php if ($ticket->location_text): ?><dt>Lokasi</dt><dd><?= e($ticket->location_text) ?></dd><?php endif; ?>
					<dt>Kerahasiaan</dt><dd><?= e(config_label('confidentiality_levels', $ticket->confidentiality)) ?></dd>
					<dt>Identitas</dt><dd><?= $ticket->identity_mode === 'masked' ? 'Disembunyikan dari petugas biasa' : 'Tercatat pada akun Anda' ?></dd>
					<?php if ($ticket->resolved_at): ?><dt>Selesai</dt><dd><?= e(format_wib($ticket->resolved_at)) ?></dd><?php endif; ?>
				</dl>
				<h3 class="h6 mt-4">Lampiran</h3>
				<?= ticket_attachment_list($attachments) ?>
			</div>
		</div>

		<div class="card shadow-sm">
			<div class="card-header"><h2>Perkembangan</h2></div>
			<div class="card-body"><?= ticket_timeline_html($timeline, 'reporter') ?></div>
		</div>
	</div>

	<div class="col-lg-4">
		<?php if ($can('provide_information')): ?>
		<div class="card shadow-sm mb-4 border-left-warning">
			<div class="card-header"><h2>Petugas meminta kelengkapan</h2></div>
			<div class="card-body">
				<form method="post" action="<?= site_url('warga/laporan/'.rawurlencode($ticket->public_code).'/balas') ?>" data-once>
					<?= csrf_field() ?>
					<?= ui_textarea(array('name' => 'message', 'label' => 'Jawaban Anda', 'required' => TRUE, 'minlength' => 5, 'maxlength' => 5000, 'rows' => 4, 'value' => '')) ?>
					<button class="btn btn-primary btn-block" type="submit">Kirim kelengkapan</button>
				</form>
			</div>
		</div>
		<?php elseif ($can('reply')): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Kirim pesan</h2></div>
			<div class="card-body">
				<form method="post" action="<?= site_url('warga/laporan/'.rawurlencode($ticket->public_code).'/balas') ?>" data-once>
					<?= csrf_field() ?>
					<?= ui_textarea(array('name' => 'message', 'label' => 'Pesan untuk petugas', 'required' => TRUE, 'minlength' => 5, 'maxlength' => 5000, 'rows' => 4, 'value' => '')) ?>
					<button class="btn btn-primary btn-block" type="submit">Kirim</button>
				</form>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($can('accept_result')): ?>
		<div class="card shadow-sm mb-4 border-left-primary">
			<div class="card-header"><h2>Tanggapi hasil</h2></div>
			<div class="card-body">
				<form method="post" action="<?= site_url('warga/laporan/'.rawurlencode($ticket->public_code).'/konfirmasi') ?>" data-once>
					<?= csrf_field() ?>
					<fieldset class="form-group">
						<legend class="col-form-label p-0">Tanggapan</legend>
						<div class="custom-control custom-radio">
							<input class="custom-control-input" type="radio" name="choice" id="c-accept" value="accept" checked>
							<label class="custom-control-label" for="c-accept">Saya menerima hasil ini</label>
						</div>
						<div class="custom-control custom-radio">
							<input class="custom-control-input" type="radio" name="choice" id="c-follow" value="followup">
							<label class="custom-control-label" for="c-follow">Saya meminta tindak lanjut</label>
						</div>
					</fieldset>
					<?= ui_select(array('name' => 'rating', 'label' => 'Penilaian layanan', 'options' => array('5' => '5 — Sangat baik', '4' => '4 — Baik', '3' => '3 — Cukup', '2' => '2 — Kurang', '1' => '1 — Buruk'), 'placeholder_option' => 'Tidak menilai', 'value' => '')) ?>
					<?= ui_textarea(array('name' => 'message', 'label' => 'Catatan', 'rows' => 3, 'maxlength' => 5000, 'value' => '', 'help' => 'Wajib bila meminta tindak lanjut.')) ?>
					<button class="btn btn-primary btn-block" type="submit">Kirim tanggapan</button>
				</form>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($can('withdraw') OR $can('request_withdrawal')): ?>
		<div class="card shadow-sm">
			<div class="card-header"><h2><?= $can('withdraw') ? 'Tarik laporan' : 'Minta penarikan' ?></h2></div>
			<div class="card-body">
				<p class="small text-muted"><?= $can('withdraw')
					? 'Laporan tidak diproses lebih lanjut; riwayat tetap tersimpan.'
					: 'Penanganan sudah berjalan, sehingga penarikan disampaikan sebagai permintaan.' ?></p>
				<form method="post" action="<?= site_url('warga/laporan/'.rawurlencode($ticket->public_code).'/tarik') ?>"
					data-confirm="<?= $can('withdraw') ? 'Tarik laporan ini? Laporan tidak akan diproses lebih lanjut.' : 'Kirim permintaan penarikan kepada petugas?' ?>" data-confirm-ok="Ya, lanjutkan">
					<?= csrf_field() ?>
					<?= ui_textarea(array('name' => 'message', 'label' => 'Alasan', 'rows' => 3, 'maxlength' => 1000, 'value' => '', 'required' => ! $can('withdraw'))) ?>
					<button class="btn btn-outline-primary btn-block" type="submit"><?= $can('withdraw') ? 'Tarik laporan' : 'Kirim permintaan' ?></button>
				</form>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
