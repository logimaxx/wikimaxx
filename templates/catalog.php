<?php
use WikiMaxx\View;
/** @var list<array{title: string, href: string}> $libraries */
/** @var string $base */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<h1>Biblioteci</h1>
<?php if ($libraries === []): ?>
  <p class="muted">Nicio bibliotecă disponibilă.</p>
<?php else: ?>
  <ul class="dir-list">
    <?php foreach ($libraries as $lib): ?>
      <li><a href="<?= View::e($prefix . $lib['href']) ?>"><?= View::e($lib['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
