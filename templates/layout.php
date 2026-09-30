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
  <style>
    #page-loader {
      position: fixed; inset: 0; z-index: 9999;
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;
      background: #fff; color: #0b1f2e;
      font-family: "Segoe UI", system-ui, sans-serif;
      transition: opacity .28s ease, visibility .28s ease;
    }
    html.is-ready #page-loader { opacity: 0; visibility: hidden; pointer-events: none; }
    #page-loader .loader-brand { font-weight: 700; font-size: 1.15rem; letter-spacing: -.03em; }
    #page-loader .loader-spin {
      width: 1.5rem; height: 1.5rem; border-radius: 50%;
      border: 2px solid #d7dee4; border-top-color: #ff6b35;
      animation: page-loader-spin .7s linear infinite;
    }
    @keyframes page-loader-spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div id="page-loader" aria-live="polite" aria-busy="true">
    <div class="loader-brand"><?= View::e($siteBrand ?? 'WikiMaxx') ?></div>
    <div class="loader-spin" aria-hidden="true"></div>
  </div>
  <header class="top">
    <a class="brand" href="<?= View::e($prefix) ?>/"><?= View::e($siteBrand ?? 'WikiMaxx') ?></a>
    <form class="top-search" method="get" action="<?= View::e($prefix) ?>/search" role="search">
      <label class="sr-only" for="top-search-q">Caută</label>
      <input id="top-search-q" type="search" name="q"
             value="<?= View::e($searchQuery ?? '') ?>"
             placeholder="Caută…" autocomplete="off">
    </form>
    <nav class="top-actions">
      <?php if ($user): ?>
        <span class="who"><?= View::e($user['username']) ?></span>
        <?php
          $canEdit = \WikiMaxx\Roles::hasWriteCapability($user['roles'] ?? []);
          if ($canEdit):
        ?>
          <a href="<?= View::e($prefix) ?>/admin">Admin</a>
        <?php endif; ?>
        <?php if (in_array('admin', $user['roles'] ?? [], true)): ?>
          <a href="<?= View::e($prefix) ?>/admin/users">Utilizatori</a>
          <a href="<?= View::e($prefix) ?>/admin/roles">Roluri</a>
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
  <script>
  (function () {
    var done = false;
    function ready() {
      if (done) return;
      done = true;
      document.documentElement.classList.add('is-ready');
      var el = document.getElementById('page-loader');
      if (!el) return;
      el.setAttribute('aria-busy', 'false');
      el.addEventListener('transitionend', function () { el.hidden = true; }, { once: true });
    }
    if (document.readyState === 'complete') ready();
    else window.addEventListener('load', ready);
    setTimeout(ready, 10000);
  })();
  </script>
</body>
</html>
