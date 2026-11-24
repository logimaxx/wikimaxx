<?php
use WikiMaxx\View;
/** @var string $pageTitle */
/** @var string $html */
/** @var list<array{title:string,href:string}> $breadcrumb */
/** @var bool $canEdit */
/** @var string|null $editRel */
/** @var string|null $orderRel */
/** @var string $base */
/** @var string $libraryId */
$prefix = ($base === '' || $base === '/') ? '' : $base;
$libraryId = $libraryId ?? '';
$adminQ = static function (array $q) use ($libraryId): string {
    if ($libraryId !== '') {
        $q = ['lib' => $libraryId] + $q;
    }
    return '/admin?' . http_build_query($q);
};
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
      <a href="<?= View::e($prefix . $adminQ(['edit' => $editRel])) ?>">Editează</a>
    <?php endif; ?>
    <?php if ($orderRel !== null): ?>
      <?php if ($editRel): ?><span class="sep">·</span><?php endif; ?>
      <a href="<?= View::e($prefix . $adminQ(['path' => $orderRel, 'order' => '1'])) ?>">Ordonează</a>
    <?php endif; ?>
  </p>
<?php endif; ?>

<?php
$toc = $toc ?? [];
$updatedAt = $updatedAt ?? null;
$neighbors = $neighbors ?? ['prev' => null, 'next' => null];
$prev = $neighbors['prev'] ?? null;
$next = $neighbors['next'] ?? null;
?>
<?php if ($updatedAt): ?>
  <p class="doc-updated">Actualizat <?= View::e($updatedAt) ?></p>
<?php endif; ?>

<div class="doc-layout<?= $toc !== [] ? ' has-toc' : '' ?>">
  <div class="doc-main">
    <article class="prose">
      <?= $html ?>
    </article>
    <?php if ($prev || $next): ?>
      <nav class="pager" aria-label="Pagini">
        <?php if ($prev): ?>
          <a class="pager-prev" href="<?= View::e($prefix . $prev['href']) ?>">
            <span class="pager-kicker">Anterior</span>
            <span class="pager-title"><?= View::e($prev['title']) ?></span>
          </a>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <?php if ($next): ?>
          <a class="pager-next" href="<?= View::e($prefix . $next['href']) ?>">
            <span class="pager-kicker">Următor</span>
            <span class="pager-title"><?= View::e($next['title']) ?></span>
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
  <?php if ($toc !== []): ?>
    <nav class="toc" aria-label="Pe această pagină">
      <p class="toc-title">Pe această pagină</p>
      <ol>
        <?php foreach ($toc as $heading): ?>
          <li class="toc-l<?= (int) $heading['level'] ?>">
            <a href="#<?= View::e($heading['id']) ?>"><?= View::e($heading['text']) ?></a>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
  <?php endif; ?>
</div>
