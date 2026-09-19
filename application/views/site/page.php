<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Halaman publik CMS: judul dan ringkasan dari versi yang diterbitkan, lalu section
 * yang sama persis dengan yang dipakai beranda. Tidak ada HTML bebas dari pengelola.
 */
$page = $layout['page'] ?? array();
$has_hero = FALSE;
foreach ($layout['sections'] as $section)
{
	if ($section['type'] === 'hero') { $has_hero = TRUE; break; }
}
?>
<?php if ( ! $has_hero): ?>
<section class="section-sm page-head">
	<div class="container-site">
		<h1 class="page-title"><?= e($page['title'] ?? '') ?></h1>
		<?php if ( ! empty($page['summary'])): ?>
		<p class="page-lede"><?= e($page['summary']) ?></p>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if (empty($layout['sections'])): ?>
	<section class="section">
		<div class="container-site">
			<div class="empty-state">
				<?= icon('layout') ?>
				<h2 class="h5">Halaman ini belum berisi apa pun</h2>
				<p class="mb-0">Pengelola desa sedang menyiapkan isinya.</p>
			</div>
		</div>
	</section>
<?php else: ?>
	<?php foreach ($layout['sections'] as $section): ?>
		<?php if (is_file(APPPATH.'views/site/sections/'.$section['type'].'.php')): ?>
			<?php $this->load->view('site/sections/'.$section['type'], array('s' => $section, 'd' => $section['data'])); ?>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?>
