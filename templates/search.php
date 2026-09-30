<?php
use WikiMaxx\View;
/** @var string $q */
/** @var list<array{title: string, href: string, path: string, snippet: string, score: int}> $results */
/** @var bool $tooShort */
/** @var int $minQueryLen */
/** @var string $base */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<h1>Căutare</h1>

<form class="search-page-form" method="get" action="<?= View::e($prefix) ?>/search" role="search">
  <label class="sr-only" for="search-q">Caută în documentație</label>
  <input id="search-q" type="search" name="q" value="<?= View::e($q) ?>"
         placeholder="Caută…" minlength="<?= (int) $minQueryLen ?>" autofocus>
  <button type="submit">Caută</button>
</form>

<?php if ($q === ''): ?>
  <p class="muted">Introdu cel puțin <?= (int) $minQueryLen ?> caractere.</p>
<?php elseif ($tooShort): ?>
  <p class="muted">Interogarea trebuie să aibă cel puțin <?= (int) $minQueryLen ?> caractere.</p>
<?php elseif ($results === []): ?>
  <p class="muted">Niciun rezultat pentru „<?= View::e($q) ?>”.</p>
<?php else: ?>
  <p class="muted search-count"><?= count($results) ?> rezultat<?= count($results) === 1 ? '' : 'e' ?></p>
  <ol class="search-results">
    <?php foreach ($results as $hit): ?>
      <li class="search-hit">
        <a class="search-hit-title" href="<?= View::e($prefix . $hit['href']) ?>"><?= View::e($hit['title']) ?></a>
        <div class="search-hit-path"><?= View::e($hit['path']) ?></div>
        <?php if ($hit['snippet'] !== ''): ?>
          <p class="search-hit-snippet"><?= View::e($hit['snippet']) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
