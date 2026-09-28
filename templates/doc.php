<?php
use WikiMaxx\View;
/** @var string $pageTitle */
/** @var string $html */
/** @var list<array{title:string,href:string}> $breadcrumb */
/** @var bool $canEdit */
/** @var string|null $editRel */
/** @var string $base */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<?php if ($breadcrumb): ?>
<nav class="crumbs" aria-label="Breadcrumb">
  <?php foreach ($breadcrumb as $i => $c): ?>
    <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
    <a href="<?= View::e($prefix . $c['href']) ?>"><?= View::e($c['title']) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ($canEdit && $editRel): ?>
  <p class="edit-link"><a href="<?= View::e($prefix) ?>/admin?edit=<?= View::e(rawurlencode($editRel)) ?>">Editează</a></p>
<?php endif; ?>

<article class="prose">
  <?= $html ?>
</article>
