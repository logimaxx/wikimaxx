<?php
use WikiMaxx\View;
/** @var string $title */
/** @var string $content */
/** @var array|null $user */
/** @var array $nav */
/** @var array|null $flash */
/** @var string $csrf */
/** @var string $base */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title) ?> · <?= View::e($siteBrand ?? 'WikiMaxx') ?></title>
  <link rel="stylesheet" href="<?= View::e($prefix) ?>/assets/style.css">
  <link rel="icon" href="<?= View::e($prefix) ?>/favicon.svg" type="image/svg+xml">
</head>
<body>
  <header class="top">
    <a class="brand" href="<?= View::e($prefix) ?>/"><?= View::e($siteBrand ?? 'WikiMaxx') ?></a>
    <nav class="top-actions">
      <?php if ($user): ?>
        <span class="who"><?= View::e($user['username']) ?></span>
        <?php
          $canEdit = in_array('admin', $user['roles'], true) || in_array('editor', $user['roles'], true);
          if ($canEdit):
        ?>
          <a href="<?= View::e($prefix) ?>/admin">Admin</a>
        <?php endif; ?>
        <?php if (in_array('admin', $user['roles'], true)): ?>
          <a href="<?= View::e($prefix) ?>/admin/users">Utilizatori</a>
        <?php endif; ?>
        <a href="<?= View::e($prefix) ?>/logout">Ieșire</a>
      <?php else: ?>
        <a href="<?= View::e($prefix) ?>/login">Autentificare</a>
      <?php endif; ?>
    </nav>
  </header>

  <?php if ($flash): ?>
    <div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="shell">
    <aside class="sidebar">
      <?php require $templatesDir . '/_nav.php'; ?>
    </aside>
    <main class="main">
      <?= $content ?>
    </main>
  </div>
</body>
</html>
