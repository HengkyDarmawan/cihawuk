<?php defined('BASEPATH') OR exit('No direct script access allowed');
$severity_labels = array('high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah');
?>
<div class="page-heading">
	<div>
		<h1>Masalah data</h1>
		<p>Catatan perbedaan dan ketidakjelasan pada dokumen sumber beserta keputusan sistem.</p>
	</div>
	<div>
		<a class="btn btn-outline-primary mr-2" href="<?= site_url('admin/statistik/isu?status=open') ?>">Terbuka</a>
		<a class="btn btn-outline-primary" href="<?= site_url('admin/statistik/isu?status=all') ?>">Semua</a>
	</div>
</div>

<?php if (empty($issues)): ?>
	<div class="card shadow-sm"><div class="card-body empty-box"><i class="fas fa-check-circle" aria-hidden="true"></i><p class="mb-0">Tidak ada masalah data pada filter ini.</p></div></div>
<?php else: ?>
	<?php foreach ($issues as $issue): ?>
	<div class="card shadow-sm mb-3">
		<div class="card-body">
			<div class="d-flex flex-wrap justify-content-between gap-2">
				<div>
					<h2 class="h6 mb-1"><?= e($issue->title) ?></h2>
					<p class="small text-muted mb-1">
						<code><?= e($issue->issue_code) ?></code>
						<?= $issue->source_code ? ' · sumber '.e($issue->source_code) : '' ?>
						· severity <?= e($severity_labels[$issue->severity] ?? $issue->severity) ?>
					</p>
				</div>
				<span class="chip-flag <?= $issue->status === 'open' ? 'is-warning' : 'is-info' ?>"><?= e($issue->status) ?></span>
			</div>
			<p class="mb-2"><?= e($issue->description) ?></p>
			<?php if ($issue->system_decision): ?>
				<p class="small mb-2"><strong>Keputusan sistem:</strong> <?= e($issue->system_decision) ?></p>
			<?php endif; ?>
			<?php if ($issue->resolution_note): ?>
				<p class="small mb-2"><strong>Catatan penyelesaian:</strong> <?= e($issue->resolution_note) ?><?= $issue->resolver ? ' — '.e($issue->resolver) : '' ?><?= $issue->resolved_at ? ' ('.e(format_wib($issue->resolved_at, 'short')).')' : '' ?></p>
			<?php endif; ?>

			<?php if ($can_review): ?>
			<form method="post" action="<?= site_url('admin/statistik/isu/'.(int) $issue->id) ?>" class="form-row align-items-end">
				<?= csrf_field() ?>
				<div class="col-md-3"><?= ui_select(array('name' => 'status', 'id' => 'is-'.$issue->id, 'label' => 'Status', 'options' => array('open' => 'Terbuka', 'accepted' => 'Diterima sebagai catatan', 'resolved' => 'Selesai'), 'value' => $issue->status, 'wrap_class' => 'mb-2')) ?></div>
				<div class="col-md-7"><?= ui_input(array('name' => 'resolution_note', 'id' => 'rn-'.$issue->id, 'label' => 'Catatan penyelesaian', 'maxlength' => 1000, 'value' => $issue->resolution_note, 'wrap_class' => 'mb-2')) ?></div>
				<div class="col-md-2 mb-2"><button class="btn btn-outline-primary btn-block" type="submit">Simpan</button></div>
			</form>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
<?php endif; ?>
