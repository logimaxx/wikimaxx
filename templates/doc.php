<?php
use WikiMaxx\View;
/** @var string $pageTitle */
/** @var string $html */
/** @var list<array{title:string,href:string}> $breadcrumb */
/** @var bool $canEdit */
/** @var string|null $editRel */
/** @var string|null $orderRel */
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

<?php if ($canEdit && ($editRel || $orderRel !== null)): ?>
  <p class="edit-link">
    <?php if ($editRel): ?>
      <a href="<?= View::e($prefix) ?>/admin?edit=<?= View::e(rawurlencode($editRel)) ?>">Editează</a>
    <?php endif; ?>
    <?php if ($orderRel !== null): ?>
      <?php if ($editRel): ?><span class="sep">·</span><?php endif; ?>
      <a href="<?= View::e($prefix) ?>/admin?<?= View::e(http_build_query(['path' => $orderRel, 'order' => '1'])) ?>">Ordonează</a>
    <?php endif; ?>
  </p>
<?php endif; ?>

<article class="prose">
  <?= $html ?>
</article>
