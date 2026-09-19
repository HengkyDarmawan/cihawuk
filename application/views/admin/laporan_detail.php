<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->helper('ticket');
$timeline = ticket_timeline($history, $messages, 'staff');
$can_action = function ($action) use ($actions) { return in_array($action, $actions, TRUE); };
$url = site_url('admin/laporan/'.rawurlencode($ticket->public_code));
$version_field = '<input type="hidden" name="version" value="'.(int) $ticket->version.'">';
$receipt = $this->session->flashdata('front_desk_receipt');
$milestone_labels = array('verification' => 'Verifikasi', 'first_response' => 'Respons awal', 'confirmation' => 'Tanggapan pelapor', 'resolution' => 'Penyelesaian');
?>
<div class="page-heading">
	<div>
		<h1><?= e($ticket->title) ?></h1>
		<p>
			<strong><?= e($ticket->public_code) ?></strong> ·
			<?= e(config_label('report_types', $ticket->report_type)) ?> ·
			<?= e($category ? $category->name : '—') ?> ·
			<?= e(config_label('intake_channels', $ticket->intake_channel)) ?>
		</p>
	</div>
	<div class="text-right">
		<?= ticket_status_badge($ticket->status, 'staff') ?>
		<div class="mt-2">
			<?php if ($ticket->confidentiality === 'restricted'): ?><span class="chip-flag is-warning"><i class="fas fa-lock mr-1" aria-hidden="true"></i>rahasia</span><?php endif; ?>
			<?php if ($ticket->identity_mode === 'anonymous'): ?><span class="chip-flag">anonim</span><?php endif; ?>
			<?php if ($ticket->identity_mode === 'masked'): ?><span class="chip-flag is-info">identitas disembunyikan</span><?php endif; ?>
			<?php if ((int) $ticket->current_episode > 1): ?><span class="chip-flag is-info">episode <?= (int) $ticket->current_episode ?></span><?php endif; ?>
			<span class="chip-flag">prioritas: <?= e(config_label('priorities', $ticket->priority)) ?></span>
		</div>
		<a class="btn btn-outline-primary btn-sm mt-2" href="<?= site_url('admin/laporan') ?>">Kembali ke daftar</a>
	</div>
</div>

<?php if (is_array($receipt)): ?>
<div class="card shadow-sm mb-4 border-left-accent">
	<div class="card-body">
		<h2 class="h6">Bukti untuk pelapor (tampil sekali)</h2>
		<p class="small">Berikan nomor tiket dan kode akses berikut kepada pelapor. Setelah halaman ini ditinggalkan, kode tidak dapat ditampilkan ulang oleh petugas mana pun.</p>
		<p class="mb-1"><strong>Nomor tiket:</strong> <?= e($receipt['code']) ?></p>
		<div class="code-display" id="fd-code"><?= e($receipt['access_code']) ?></div>
		<button class="btn btn-outline-primary btn-sm mt-2" type="button" data-copy-target="fd-code">Salin kode akses</button>
	</div>
</div>
<?php endif; ?>

<?php if ( ! empty($action_error)): ?><div class="alert alert-danger" role="alert"><?= e($action_error) ?></div><?php endif; ?>
<?php if ($ticket->withdrawal_requested_at !== NULL && ! in_array($ticket->status, array('withdrawn', 'resolved'), TRUE)): ?>
	<div class="alert alert-warning" role="status">Pelapor meminta penarikan pada <?= e(format_wib($ticket->withdrawal_requested_at)) ?>. Tinjau permintaan ini sebelum melanjutkan penanganan.</div>
<?php endif; ?>
<?php if ( ! empty($conflicts)): ?>
	<div class="alert alert-warning" role="status">
		<strong>Konflik kepentingan tercatat:</strong>
		<?php foreach ($conflicts as $c): ?><span class="chip-flag is-warning"><?= e($c->user_name) ?></span> <?php endforeach; ?>
		<div class="small">Petugas tersebut tidak dapat ditugaskan maupun membuka laporan ini.</div>
	</div>
<?php endif; ?>

<div class="row">
	<div class="col-lg-8">
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Isi laporan</h2></div>
			<div class="card-body">
				<p class="pre-wrap"><?= e($ticket->description) ?></p>
				<dl class="dl-grid mt-3">
					<dt>Diterima</dt><dd><?= e(format_wib($ticket->submitted_at)) ?></dd>
					<?php if ($ticket->incident_date): ?><dt>Tanggal kejadian</dt><dd><?= e(format_date_id($ticket->incident_date)) ?></dd><?php endif; ?>
					<?php if ($ticket->location_text): ?><dt>Lokasi</dt><dd><?= e($ticket->location_text) ?></dd><?php endif; ?>
					<?php if ($ticket->latitude !== NULL): ?><dt>Titik lokasi</dt><dd><?= e($ticket->latitude) ?>, <?= e($ticket->longitude) ?> <span class="small text-muted">(dikirim pelapor)</span></dd><?php endif; ?>
					<dt>Kanal</dt><dd><?= e(config_label('intake_channels', $ticket->intake_channel)) ?></dd>
					<?php if ($ticket->duplicate_of_ticket_id): ?>
						<dt>Duplikat dari</dt><dd><?= e($this->db->select('public_code')->where('id', (int) $ticket->duplicate_of_ticket_id)->get('tickets')->row('public_code')) ?></dd>
					<?php endif; ?>
				</dl>

				<h3 class="h6 mt-4">Lampiran</h3>
				<?= ticket_attachment_list($attachments) ?>
			</div>
		</div>

		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Riwayat penanganan</h2></div>
			<div class="card-body"><?= ticket_timeline_html($timeline, 'staff') ?></div>
		</div>

		<?php if ( ! empty($assignments) OR ! empty($references) OR $feedback): ?>
		<div class="card shadow-sm">
			<div class="card-header"><h2>Catatan tambahan</h2></div>
			<div class="card-body">
				<?php if ( ! empty($assignments)): ?>
					<h3 class="h6">Riwayat penugasan</h3>
					<ul class="list-unstyled">
						<?php foreach ($assignments as $a): ?>
						<li class="small border-bottom py-1">
							<?= e($a->assignee_name) ?><?= $a->unit_name ? ' · '.e($a->unit_name) : '' ?> —
							<?= e(format_wib($a->assigned_at, 'short')) ?><?= $a->ended_at ? ' s.d. '.e(format_wib($a->ended_at, 'short')) : ' <span class="chip-flag is-info">aktif</span>' ?>
							<?= $a->reason ? '<div class="text-muted">'.e($a->reason).'</div>' : '' ?>
						</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( ! empty($references)): ?>
					<h3 class="h6 mt-3">Rujukan</h3>
					<ul class="list-unstyled">
						<?php foreach ($references as $r): ?>
						<li class="small border-bottom py-1">
							<?= e($r->target_name) ?> —
							<span class="chip-flag <?= $r->reference_type === 'actual_forwarding' ? 'is-info' : '' ?>"><?= $r->reference_type === 'actual_forwarding' ? 'diteruskan' : 'petunjuk rujukan' ?></span>
							<?php if ($r->external_reference): ?><div>Bukti penerusan: <?= e($r->external_reference) ?></div><?php endif; ?>
							<?php if ($r->target_url): ?><div><a href="<?= nav_href($r->target_url) ?>" rel="noopener noreferrer" target="_blank"><?= e($r->target_url) ?></a></div><?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ($feedback): ?>
					<h3 class="h6 mt-3">Tanggapan pelapor (episode <?= (int) $feedback->handling_episode ?>)</h3>
					<p class="small mb-1">Respons: <strong><?= e($feedback->response === 'accepted' ? 'Menerima hasil' : 'Meminta tindak lanjut') ?></strong><?= $feedback->rating ? ' · penilaian '.(int) $feedback->rating.'/5' : '' ?></p>
					<?php if ($feedback->comment): ?><p class="small pre-wrap mb-0"><?= e($feedback->comment) ?></p><?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<div class="col-lg-4">
		<?php if ($sla): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Target layanan (episode <?= (int) $sla['episode_no'] ?>)</h2></div>
			<div class="card-body">
				<?php if ($sla['paused']): ?><p class="chip-flag is-warning mb-2">SLA penyelesaian dijeda (menunggu pelapor)</p><?php endif; ?>
				<ul class="list-unstyled mb-2">
					<?php foreach ($milestone_labels as $key => $label): $m = $sla[$key]; if ( ! $m) { continue; } ?>
					<li class="border-bottom py-1 small">
						<div class="d-flex justify-content-between">
							<span><?= e($label) ?></span>
							<?php if ($m['met_at']): ?><span class="chip-flag is-info">tercapai</span>
							<?php elseif ($m['overdue']): ?><span class="chip-flag is-danger">terlambat</span>
							<?php else: ?><span class="chip-flag">berjalan</span><?php endif; ?>
						</div>
						<div class="text-muted">Tenggat <?= e(format_wib($m['due_at'])) ?><?= $m['met_at'] ? ' · tercapai '.e(format_wib($m['met_at'])) : '' ?></div>
					</li>
					<?php endforeach; ?>
				</ul>
				<p class="small text-muted mb-0">Kebijakan <?= e($sla['policy']['policy_code'] ?? '—') ?> di-snapshot saat episode dimulai; perubahan konfigurasi tidak mengubah tenggat kasus ini.</p>
			</div>
		</div>
		<?php endif; ?>

		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Pelapor</h2></div>
			<div class="card-body">
				<?php if ($ticket->identity_mode === 'anonymous'): ?>
					<p class="mb-0">Laporan dikirim tanpa identitas. Sistem tidak menyimpan nama, NIK, atau kontak pelapor.
					<?php if ($ticket->intake_channel === 'front_desk'): ?><br><span class="small text-muted">Dicatat petugas loket; identitas pencatat tersimpan pada audit.</span><?php endif; ?></p>
				<?php elseif ( ! $abilities['view_identity']): ?>
					<p class="mb-0">Identitas pelapor dibatasi. Anda tidak memiliki izin <code>tickets.view_identity</code>.</p>
				<?php elseif ($identity_revealed): ?>
					<dl class="dl-grid mb-2">
						<?php if ($reporter): ?>
							<dt>Akun</dt><dd><?= e($reporter->display_name) ?> (<?= e($reporter->username) ?>)</dd>
						<?php endif; ?>
						<?php if ($private_contact): ?>
							<?php if ($private_contact['name']): ?><dt>Nama</dt><dd><?= e($private_contact['name']) ?></dd><?php endif; ?>
							<?php if ($private_contact['phone']): ?><dt>Telepon</dt><dd><?= e($private_contact['phone']) ?></dd><?php endif; ?>
							<?php if ($private_contact['email']): ?><dt>Email</dt><dd><?= e($private_contact['email']) ?></dd><?php endif; ?>
						<?php endif; ?>
					</dl>
					<p class="small text-muted mb-0">Akses identitas ini tercatat pada log audit.</p>
				<?php else: ?>
					<p class="small">Identitas pelapor disembunyikan secara bawaan. Buka hanya bila diperlukan untuk penanganan; akses akan tercatat.</p>
					<form method="post" action="<?= $url ?>/identitas" data-confirm="Buka identitas pelapor? Tindakan ini tercatat pada log audit." data-confirm-ok="Buka identitas">
						<?= csrf_field() ?><?= $version_field ?>
						<?= ui_textarea(array('name' => 'reason', 'label' => 'Alasan membuka identitas', 'required' => TRUE, 'rows' => 2, 'maxlength' => 500, 'value' => '')) ?>
						<button class="btn btn-outline-primary btn-sm btn-block" type="submit">Buka identitas</button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty($actions)): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Tindakan</h2></div>
			<div class="card-body">
				<?php if ($can_action('start_verification')): ?>
					<form method="post" action="<?= $url ?>/verifikasi" class="mb-3">
						<?= csrf_field() ?><?= $version_field ?>
						<button class="btn btn-primary btn-block" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i> Mulai verifikasi</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('accept_work')): ?>
					<form method="post" action="<?= $url ?>/status" class="mb-3">
						<?= csrf_field() ?><?= $version_field ?>
						<input type="hidden" name="action" value="accept_work">
						<button class="btn btn-primary btn-block" type="submit"><i class="fas fa-play mr-1" aria-hidden="true"></i> Terima penugasan &amp; mulai tangani</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('assign')): ?>
					<form method="post" action="<?= $url ?>/disposisi" class="mb-3">
						<?= csrf_field() ?><?= $version_field ?>
						<h3 class="h6"><?= $ticket->assigned_user_id ? 'Pindahkan penugasan' : 'Disposisi ke petugas' ?></h3>
						<?php if (empty($assignable)): ?>
							<p class="small text-muted">Belum ada petugas yang memenuhi syarat (izin menangani, bukan pelapor, tanpa konflik kepentingan<?= $ticket->confidentiality === 'restricted' ? ', serta berizin laporan rahasia' : '' ?>).</p>
						<?php else: ?>
							<?= ui_select(array('name' => 'assignee_id', 'label' => 'Petugas', 'required' => TRUE, 'options' => $assignable, 'placeholder_option' => 'Pilih petugas…', 'value' => (string) $ticket->assigned_user_id)) ?>
							<?= ui_select(array('name' => 'unit_id', 'label' => 'Unit', 'options' => $units, 'placeholder_option' => 'Tanpa unit', 'value' => (string) $ticket->assigned_unit_id)) ?>
							<?= ui_textarea(array('name' => 'reason', 'label' => 'Alasan / catatan disposisi', 'rows' => 2, 'maxlength' => 500, 'value' => '', 'required' => $ticket->assigned_user_id !== NULL)) ?>
							<button class="btn btn-primary btn-block" type="submit">Simpan disposisi</button>
						<?php endif; ?>
					</form>
				<?php endif; ?>

				<?php if ($can_action('request_information')): ?>
					<form method="post" action="<?= $url ?>/status" class="mb-3">
						<?= csrf_field() ?><?= $version_field ?>
						<input type="hidden" name="action" value="request_information">
						<h3 class="h6">Minta kelengkapan</h3>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Pertanyaan untuk pelapor', 'required' => TRUE, 'rows' => 3, 'minlength' => 10, 'maxlength' => 5000, 'value' => '')) ?>
						<button class="btn btn-outline-primary btn-block" type="submit">Kirim permintaan</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('propose_resolution')): ?>
					<form method="post" action="<?= $url ?>/status" class="mb-3">
						<?= csrf_field() ?><?= $version_field ?>
						<input type="hidden" name="action" value="propose_resolution">
						<h3 class="h6">Sampaikan hasil penanganan</h3>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Hasil untuk ditanggapi pelapor', 'required' => TRUE, 'rows' => 4, 'minlength' => 15, 'maxlength' => 5000, 'value' => '')) ?>
						<button class="btn btn-primary btn-block" type="submit">Kirim hasil</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('close_by_policy')): ?>
					<form method="post" action="<?= $url ?>/status" class="mb-3" data-confirm="Tutup laporan tanpa konfirmasi pelapor? Alasan dan dasar kebijakan akan tercatat." data-confirm-ok="Tutup laporan">
						<?= csrf_field() ?><?= $version_field ?>
						<input type="hidden" name="action" value="close_by_policy">
						<h3 class="h6">Tutup sesuai kebijakan</h3>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Alasan dan dasar kebijakan', 'required' => TRUE, 'rows' => 3, 'minlength' => 15, 'maxlength' => 5000, 'value' => '')) ?>
						<button class="btn btn-outline-primary btn-block" type="submit">Tutup laporan</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('resume_work')): ?>
					<form method="post" action="<?= $url ?>/status" class="mb-3">
						<?= csrf_field() ?><?= $version_field ?>
						<input type="hidden" name="action" value="resume_work">
						<button class="btn btn-outline-primary btn-block" type="submit">Lanjutkan penanganan</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('reopen')): ?>
					<form method="post" action="<?= $url ?>/status" class="mb-3" data-confirm="Buka kembali laporan ini pada episode penanganan baru?" data-confirm-ok="Buka kembali">
						<?= csrf_field() ?><?= $version_field ?>
						<input type="hidden" name="action" value="reopen">
						<h3 class="h6">Buka kembali</h3>
						<?= ui_textarea(array('name' => 'message', 'label' => 'Alasan membuka kembali', 'required' => TRUE, 'rows' => 3, 'minlength' => 10, 'maxlength' => 5000, 'value' => '')) ?>
						<button class="btn btn-outline-primary btn-block" type="submit">Buka kembali</button>
					</form>
				<?php endif; ?>

				<?php if ($can_action('reject')): ?>
					<details class="mb-3">
						<summary class="font-weight-bold">Tolak laporan</summary>
						<form method="post" action="<?= $url ?>/status" class="mt-2" data-confirm="Tandai laporan tidak dapat diproses?" data-confirm-ok="Tolak laporan">
							<?= csrf_field() ?><?= $version_field ?>
							<input type="hidden" name="action" value="reject">
							<?= ui_select(array('name' => 'reason_code', 'label' => 'Alasan', 'required' => TRUE, 'options' => $rejection_reasons, 'placeholder_option' => 'Pilih alasan…', 'value' => '', 'data' => array('toggle-panel' => 'reject-reason'))) ?>
							<div data-panel-for="reject-reason" data-panel-value="duplicate" hidden>
								<?= ui_input(array('name' => 'duplicate_of', 'label' => 'Nomor tiket utama', 'maxlength' => 20, 'value' => '', 'raw_attrs' => 'data-required-when-visible')) ?>
							</div>
							<?= ui_textarea(array('name' => 'message', 'label' => 'Penjelasan untuk pelapor', 'required' => TRUE, 'rows' => 3, 'minlength' => 15, 'maxlength' => 5000, 'value' => '')) ?>
							<button class="btn btn-outline-danger btn-block" type="submit">Tolak laporan</button>
						</form>
					</details>
				<?php endif; ?>

				<?php if ($can_action('refer')): ?>
					<details class="mb-3">
						<summary class="font-weight-bold">Rujuk ke layanan lain</summary>
						<form method="post" action="<?= $url ?>/status" class="mt-2">
							<?= csrf_field() ?><?= $version_field ?>
							<input type="hidden" name="action" value="refer">
							<?= ui_input(array('name' => 'target_name', 'label' => 'Instansi/layanan tujuan', 'required' => TRUE, 'maxlength' => 191, 'value' => '')) ?>
							<?= ui_select(array('name' => 'reference_type', 'label' => 'Jenis rujukan', 'required' => TRUE, 'value' => '',
								'options' => array('guidance_only' => 'Petunjuk rujukan (belum diteruskan)', 'actual_forwarding' => 'Benar-benar diteruskan (ada bukti)'),
								'placeholder_option' => 'Pilih jenis…', 'data' => array('toggle-panel' => 'refer-type'))) ?>
							<div data-panel-for="refer-type" data-panel-value="actual_forwarding" hidden>
								<?= ui_input(array('name' => 'external_reference', 'label' => 'Nomor/bukti penerusan', 'maxlength' => 191, 'value' => '', 'raw_attrs' => 'data-required-when-visible')) ?>
							</div>
							<?= ui_input(array('name' => 'target_url', 'label' => 'Tautan layanan tujuan', 'type' => 'url', 'maxlength' => 500, 'value' => '')) ?>
							<?= ui_textarea(array('name' => 'message', 'label' => 'Penjelasan untuk pelapor', 'required' => TRUE, 'rows' => 3, 'minlength' => 15, 'maxlength' => 5000, 'value' => '')) ?>
							<button class="btn btn-outline-primary btn-block" type="submit">Simpan rujukan</button>
						</form>
					</details>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($can_action('follow_up')): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Tindak lanjut / catatan</h2></div>
			<div class="card-body">
				<form method="post" action="<?= $url ?>/tindak-lanjut">
					<?= csrf_field() ?><?= $version_field ?>
					<?= ui_textarea(array('name' => 'message', 'label' => 'Catatan', 'required' => TRUE, 'rows' => 3, 'minlength' => 5, 'maxlength' => 5000, 'value' => '')) ?>
					<fieldset class="form-group">
						<legend class="col-form-label p-0">Visibilitas</legend>
						<div class="custom-control custom-radio">
							<input class="custom-control-input" type="radio" name="visibility" id="vis-reporter" value="reporter" checked>
							<label class="custom-control-label" for="vis-reporter">Dibaca pelapor (dihitung sebagai respons)</label>
						</div>
						<div class="custom-control custom-radio">
							<input class="custom-control-input" type="radio" name="visibility" id="vis-internal" value="internal">
							<label class="custom-control-label" for="vis-internal">Catatan internal (tidak terlihat pelapor)</label>
						</div>
					</fieldset>
					<button class="btn btn-primary btn-block" type="submit">Simpan catatan</button>
				</form>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($abilities['verify'] OR $abilities['assign'] OR $abilities['monitor']): ?>
		<div class="card shadow-sm">
			<div class="card-header"><h2>Pengaturan internal</h2></div>
			<div class="card-body">
				<form method="post" action="<?= $url ?>/prioritas" class="mb-3">
					<?= csrf_field() ?><?= $version_field ?>
					<?= ui_select(array('name' => 'priority', 'label' => 'Prioritas internal', 'options' => $priorities, 'value' => $ticket->priority)) ?>
					<button class="btn btn-outline-primary btn-sm btn-block" type="submit">Simpan prioritas</button>
				</form>
				<details>
					<summary class="font-weight-bold">Catat konflik kepentingan</summary>
					<form method="post" action="<?= $url ?>/konflik" class="mt-2">
						<?= csrf_field() ?><?= $version_field ?>
						<?= ui_select(array('name' => 'user_id', 'label' => 'Petugas terkait', 'required' => TRUE, 'options' => $assignable, 'placeholder_option' => 'Pilih petugas…', 'value' => '')) ?>
						<?= ui_textarea(array('name' => 'reason', 'label' => 'Alasan', 'required' => TRUE, 'rows' => 2, 'maxlength' => 500, 'value' => '')) ?>
						<button class="btn btn-outline-danger btn-sm btn-block" type="submit">Catat konflik</button>
					</form>
					<p class="small text-muted mt-2 mb-0">Petugas yang dilaporkan tidak boleh menangani laporannya sendiri. Koordinator akan menerima notifikasi untuk penugasan ulang.</p>
				</details>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
