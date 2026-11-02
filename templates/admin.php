<?php
use WikiMaxx\View;
/** @var string $csrf */
/** @var string $base */
/** @var string $browseRel */
/** @var string|null $browseParent */
/** @var list<array{type:string,name:string,title:string,rel:string}> $browseItems */
/** @var list<array{rel:string,title:string}> $browseCrumbs */
/** @var callable(string, array=): string $adminPathQ */
/** @var array{rel:string,body:string}|null $editContent */
/** @var array{rel:string,json:string}|null $accessContent */
/** @var array{rel:string,items:list<array{type:string,name:string,title:string}>,manual:bool}|null $orderContent */
/** @var bool $isAdmin */
/** @var bool $chooseLibrary */
/** @var list<array{id: string, title: string, href: string}> $libraryChoices */
/** @var string $mount */
/** @var string $libraryId */
/** @var string $libraryTitle */
$prefix = ($base === '' || $base === '/') ? '' : $base;
if (!empty($chooseLibrary)) {
    $libraryChoices = $libraryChoices ?? [];
    ?>
<h1>Admin conținut</h1>
<p class="muted">Alege biblioteca.</p>
<?php if ($libraryChoices === []): ?>
  <p class="muted">Nicio bibliotecă configurată.</p>
<?php else: ?>
  <ul class="dir-list">
    <?php foreach ($libraryChoices as $lib): ?>
      <li><a href="<?= View::e($prefix . $lib['href']) ?>"><?= View::e($lib['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
    <?php
    return;
}
$libraryId = $libraryId ?? '';
$mount = $mount ?? '';
$libraryTitle = $libraryTitle ?? '';
$libInput = $libraryId !== ''
    ? '<input type="hidden" name="lib" value="' . View::e($libraryId) . '">'
    : '';
$publicHref = static function (string $rel, bool $dir = false) use ($mount): string {
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if (str_ends_with($rel, '.md')) {
        $rel = substr($rel, 0, -3);
        if ($rel === 'index' || str_ends_with($rel, '/index')) {
            $rel = $rel === 'index' ? '' : substr($rel, 0, -strlen('/index'));
            $dir = true;
        }
    }
    if ($rel === '') {
        return $mount === '' ? '/' : $mount . '/';
    }
    $path = ($mount === '' ? '' : $mount) . '/' . $rel;
    return $dir ? $path . '/' : $path;
};
$pathStay = $adminPathQ($browseRel);
$pathOrder = $adminPathQ($browseRel, ['order' => '1']);
$pathAccess = $adminPathQ($browseRel, ['access' => '1']);
?>
<h1>Admin conținut<?= $libraryTitle !== '' ? ' · ' . View::e($libraryTitle) : '' ?></h1>

<section class="admin-browse">
  <div class="browse-toolbar">
    <nav class="browse-crumbs" aria-label="Folder">
      <?php foreach ($browseCrumbs as $i => $c): ?>
        <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
        <?php if ($i === count($browseCrumbs) - 1): ?>
          <span class="current"><?= View::e($c['title']) ?></span>
        <?php else: ?>
          <a href="<?= View::e($prefix . $adminPathQ($c['rel'])) ?>"><?= View::e($c['title']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="browse-actions">
      <?php if ($browseParent !== null): ?>
        <a href="<?= View::e($prefix . $adminPathQ($browseParent)) ?>">↑ Sus</a>
      <?php endif; ?>
      <?php if ($browseRel !== ''): ?>
        <details class="rename-pop">
          <summary>redenumește</summary>
          <form method="post" action="<?= View::e($prefix) ?>/admin" class="rename-form">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <?= $libInput ?>
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="rel" value="<?= View::e($browseRel) ?>">
            <input type="hidden" name="redirect_mode" value="self">
            <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
            <input type="text" name="name" value="<?= View::e(basename($browseRel)) ?>" required aria-label="Nume folder" spellcheck="false">
            <button type="submit" class="linkish">ok</button>
          </form>
        </details>
      <?php endif; ?>
      <a href="<?= View::e($prefix . $pathOrder) ?>">Ordonează</a>
      <?php if ($isAdmin): ?>
        <a href="<?= View::e($prefix . $pathAccess) ?>">ACL</a>
      <?php endif; ?>
      <a href="<?= View::e($prefix . $publicHref($browseRel, true)) ?>">Vezi</a>
    </div>
  </div>

  <ul class="browse-list">
    <?php if ($browseParent !== null): ?>
      <li class="browse-row type-up">
        <a class="browse-main" href="<?= View::e($prefix . $adminPathQ($browseParent)) ?>">
          <span class="browse-icon" aria-hidden="true">↑</span>
          <span class="browse-title">..</span>
          <span class="muted">folder părinte</span>
        </a>
      </li>
    <?php endif; ?>
    <?php if ($browseItems === []): ?>
      <li class="browse-row empty"><span class="muted">Folder gol.</span></li>
    <?php endif; ?>
    <?php foreach ($browseItems as $item): ?>
      <li class="browse-row type-<?= View::e($item['type']) ?>">
        <?php if ($item['type'] === 'dir'): ?>
          <a class="browse-main" href="<?= View::e($prefix . $adminPathQ($item['rel'])) ?>">
            <span class="browse-icon" aria-hidden="true">dir</span>
            <span class="browse-title"><?= View::e($item['title']) ?></span>
            <code class="browse-name"><?= View::e($item['name']) ?></code>
          </a>
          <div class="browse-row-actions">
            <a href="<?= View::e($prefix . $adminPathQ($item['rel'], ['order' => '1'])) ?>">ordine</a>
            <?php if ($isAdmin): ?>
              <a href="<?= View::e($prefix . $adminPathQ($item['rel'], ['access' => '1'])) ?>">ACL</a>
            <?php endif; ?>
            <details class="rename-pop">
              <summary>redenumește</summary>
              <form method="post" action="<?= View::e($prefix) ?>/admin" class="rename-form">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <?= $libInput ?>
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="rel" value="<?= View::e($item['rel']) ?>">
                <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
                <input type="text" name="name" value="<?= View::e($item['name']) ?>" required aria-label="Nume folder" spellcheck="false">
                <button type="submit" class="linkish">ok</button>
              </form>
            </details>
          </div>
        <?php elseif ($item['type'] === 'md'): ?>
          <a class="browse-main" href="<?= View::e($prefix . $adminPathQ($browseRel, ['edit' => $item['rel']])) ?>">
            <span class="browse-icon" aria-hidden="true">md</span>
            <span class="browse-title"><?= View::e($item['title']) ?></span>
            <code class="browse-name"><?= View::e($item['name']) ?></code>
          </a>
          <div class="browse-row-actions">
            <a href="<?= View::e($prefix . $publicHref($item['rel'])) ?>">vezi</a>
            <details class="rename-pop">
              <summary>redenumește</summary>
              <form method="post" action="<?= View::e($prefix) ?>/admin" class="rename-form">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <?= $libInput ?>
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="rel" value="<?= View::e($item['rel']) ?>">
                <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
                <?php
                  $pageStem = $item['name'];
                  if (str_ends_with(strtolower($pageStem), '.md')) {
                      $pageStem = substr($pageStem, 0, -3);
                  }
                ?>
                <input type="text" name="name" value="<?= View::e($pageStem) ?>" required aria-label="Nume fișier" spellcheck="false">
                <span class="muted">.md</span>
                <button type="submit" class="linkish">ok</button>
              </form>
            </details>
            <form method="post" action="<?= View::e($prefix) ?>/admin" class="inline" onsubmit="return confirm('Ștergi?');">
              <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
              <?= $libInput ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="rel" value="<?= View::e($item['rel']) ?>">
              <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
              <button type="submit" class="linkish">șterge</button>
            </form>
          </div>
        <?php else: ?>
          <a class="browse-main" href="<?= View::e($prefix . $publicHref($item['rel'])) ?>" target="_blank" rel="noopener">
            <span class="browse-icon" aria-hidden="true">img</span>
            <span class="browse-title"><?= View::e($item['title']) ?></span>
          </a>
          <div class="browse-row-actions">
            <form method="post" action="<?= View::e($prefix) ?>/admin" class="inline" onsubmit="return confirm('Ștergi?');">
              <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
              <?= $libInput ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="rel" value="<?= View::e($item['rel']) ?>">
              <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
              <button type="submit" class="linkish">șterge</button>
            </form>
          </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($orderContent !== null): ?>
<section class="editor order-editor">
  <h2>Ordine: <?= View::e($orderContent['rel'] === '' ? '/' : $orderContent['rel']) ?></h2>
  <p class="muted">
    Trage rândurile ca să reordonezi, apoi <strong>Salvează ordinea</strong>.
    <?php if ($orderContent['manual']): ?>
      Ordine manuală activă (<code>_order.json</code>).
    <?php else: ?>
      Acum e ordinea implicită (foldere A–Z, apoi pagini A–Z). Salvarea o fixează manual.
    <?php endif; ?>
  </p>
  <?php if ($orderContent['items'] === []): ?>
    <p class="muted">Niciun copil de ordonat.</p>
  <?php else: ?>
    <form method="post" action="<?= View::e($prefix) ?>/admin" class="form" id="order-form">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <?= $libInput ?>
      <input type="hidden" name="action" value="save_order">
      <input type="hidden" name="rel" value="<?= View::e($orderContent['rel']) ?>">
      <input type="hidden" name="redirect" value="<?= View::e($pathOrder) ?>">
      <ol class="order-list" id="order-list">
        <?php foreach ($orderContent['items'] as $item): ?>
          <li class="order-item type-<?= View::e($item['type']) ?>" draggable="true">
            <span class="order-handle" title="Trage" aria-hidden="true">⋮⋮</span>
            <span class="order-title"><?= View::e($item['title']) ?></span>
            <code class="order-name"><?= View::e($item['name']) ?></code>
            <span class="muted"><?= $item['type'] === 'dir' ? 'folder' : 'pagină' ?></span>
            <input type="hidden" name="names[]" value="<?= View::e($item['name']) ?>">
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="row-form">
        <button type="submit">Salvează ordinea</button>
        <a href="<?= View::e($prefix . $pathStay) ?>">Închide</a>
      </div>
    </form>
    <?php if ($orderContent['manual']): ?>
      <form method="post" action="<?= View::e($prefix) ?>/admin" class="form" onsubmit="return confirm('Revii la ordinea alfabetică?');">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <?= $libInput ?>
        <input type="hidden" name="action" value="clear_order">
        <input type="hidden" name="rel" value="<?= View::e($orderContent['rel']) ?>">
        <input type="hidden" name="redirect" value="<?= View::e($pathOrder) ?>">
        <button type="submit" class="linkish">Șterge ordine manuală</button>
      </form>
    <?php endif; ?>
    <script>
    (function () {
      var list = document.getElementById('order-list');
      if (!list) return;
      var dragEl = null;

      list.querySelectorAll('.order-item').forEach(function (item) {
        item.addEventListener('dragstart', function (e) {
          dragEl = item;
          item.classList.add('is-dragging');
          if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            var input = item.querySelector('input');
            e.dataTransfer.setData('text/plain', input ? input.value : '');
          }
        });
        item.addEventListener('dragend', function () {
          item.classList.remove('is-dragging');
          list.querySelectorAll('.order-item.is-drop-target').forEach(function (el) {
            el.classList.remove('is-drop-target');
          });
          dragEl = null;
        });
      });

      list.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragEl) return;
        var over = e.target.closest('.order-item');
        if (!over || over === dragEl) return;
        list.querySelectorAll('.order-item.is-drop-target').forEach(function (el) {
          el.classList.remove('is-drop-target');
        });
        over.classList.add('is-drop-target');
        var rect = over.getBoundingClientRect();
        var before = (e.clientY - rect.top) < rect.height / 2;
        if (before) {
          list.insertBefore(dragEl, over);
        } else {
          list.insertBefore(dragEl, over.nextSibling);
        }
      });

      list.addEventListener('drop', function (e) {
        e.preventDefault();
      });
    })();
    </script>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="admin-grid">
  <div>
    <h2>Creează în acest folder</h2>
    <p class="muted"><code><?= View::e($browseRel === '' ? '/' : $browseRel) ?></code></p>
    <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <?= $libInput ?>
      <input type="hidden" name="action" value="create_folder">
      <input type="hidden" name="parent" value="<?= View::e($browseRel) ?>">
      <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
      <label>Nume folder
        <input type="text" name="name" required>
      </label>
      <button type="submit">Folder nou</button>
    </form>

    <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <?= $libInput ?>
      <input type="hidden" name="action" value="create_page">
      <input type="hidden" name="parent" value="<?= View::e($browseRel) ?>">
      <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
      <label>Slug
        <input type="text" name="slug" required>
      </label>
      <label>Titlu
        <input type="text" name="title">
      </label>
      <button type="submit">Pagină MD nouă</button>
    </form>

    <form method="post" action="<?= View::e($prefix) ?>/admin" enctype="multipart/form-data" class="form">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <?= $libInput ?>
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="parent" value="<?= View::e($browseRel) ?>">
      <input type="hidden" name="redirect" value="<?= View::e($pathStay) ?>">
      <label>Imagine
        <input type="file" name="file" accept="image/*" required>
      </label>
      <button type="submit">Upload</button>
    </form>
  </div>
</section>

<?php if ($editContent): ?>
<section class="editor">
  <h2>Editează <?= View::e($editContent['rel']) ?></h2>
  <?php
    $editStem = basename($editContent['rel']);
    if (str_ends_with(strtolower($editStem), '.md')) {
        $editStem = substr($editStem, 0, -3);
    }
  ?>
  <form method="post" action="<?= View::e($prefix) ?>/admin" class="rename-form">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <?= $libInput ?>
    <input type="hidden" name="action" value="rename">
    <input type="hidden" name="rel" value="<?= View::e($editContent['rel']) ?>">
    <input type="hidden" name="redirect_mode" value="edit">
    <input type="hidden" name="redirect" value="<?= View::e($adminPathQ($browseRel, ['edit' => $editContent['rel']])) ?>">
    <label>Nume fișier
      <input type="text" name="name" value="<?= View::e($editStem) ?>" required spellcheck="false">
      <span class="muted">.md</span>
    </label>
    <button type="submit">Redenumește</button>
  </form>
  <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <?= $libInput ?>
    <input type="hidden" name="action" value="save_page">
    <input type="hidden" name="rel" value="<?= View::e($editContent['rel']) ?>">
    <input type="hidden" name="redirect" value="<?= View::e($adminPathQ($browseRel, ['edit' => $editContent['rel']])) ?>">
    <div class="md-editor is-split" data-md-editor
         data-action="<?= View::e($prefix) ?>/admin"
         data-csrf="<?= View::e($csrf) ?>"
         <?php if ($libraryId !== ''): ?>data-lib="<?= View::e($libraryId) ?>"<?php endif; ?>
         data-page="<?= View::e($prefix . $publicHref($editContent['rel'])) ?>">
      <div class="md-toolbar" role="toolbar" aria-label="Markdown">
        <button type="button" data-md="h2" title="Titlu de secțiune">H2</button>
        <button type="button" data-md="h3" title="Subtitlu">H3</button>
        <span class="md-toolbar-sep" aria-hidden="true"></span>
        <button type="button" data-md="bold" title="Aldin (Ctrl+B)"><strong>B</strong></button>
        <button type="button" data-md="italic" title="Cursiv (Ctrl+I)"><em>I</em></button>
        <button type="button" data-md="code" title="Cod în linie"><code>&lt;/&gt;</code></button>
        <span class="md-toolbar-sep" aria-hidden="true"></span>
        <button type="button" data-md="link" title="Link (Ctrl+K)">link</button>
        <button type="button" data-md="image" title="Imagine">imagine</button>
        <span class="md-toolbar-sep" aria-hidden="true"></span>
        <button type="button" data-md="ul" title="Listă">listă</button>
        <button type="button" data-md="ol" title="Listă numerotată">1.</button>
        <button type="button" data-md="quote" title="Citat">citat</button>
        <button type="button" data-md="fence" title="Bloc de cod">bloc</button>
        <button type="button" data-md="hr" title="Linie orizontală">—</button>
        <span class="md-toolbar-gap"></span>
        <button type="button" data-view="write" aria-pressed="false">Sursă</button>
        <button type="button" data-view="split" aria-pressed="true">Împărțit</button>
        <button type="button" data-view="preview" aria-pressed="false">Previzualizare</button>
      </div>
      <div class="md-panes">
        <textarea name="body" class="md-source code" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off"><?= View::e($editContent['body']) ?></textarea>
        <div class="md-preview-wrap">
          <div class="md-preview-label">Previzualizare</div>
          <div class="md-preview prose"></div>
        </div>
      </div>
      <div class="md-status">
        <span data-md-pos>Ln 1, Col 1</span>
        <span data-md-state></span>
      </div>
    </div>
    <script src="<?= View::e($prefix) ?>/assets/md-editor.js"></script>
    <button type="submit">Salvează</button>
    <a href="<?= View::e($prefix . $publicHref($editContent['rel'])) ?>">Vezi pagina</a>
    <a href="<?= View::e($prefix . $pathStay) ?>">Închide</a>
  </form>
</section>
<?php endif; ?>

<?php if ($accessContent !== null): ?>
<section class="editor">
  <h2>ACL: <?= View::e($accessContent['rel'] === '' ? '/' : $accessContent['rel']) ?></h2>
  <p class="muted">Roluri cunoscute: <?php
    $ids = array_map(static fn ($r) => $r['id'], \WikiMaxx\Roles::catalog());
    echo View::e(implode(', ', $ids));
  ?></p>
  <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <?= $libInput ?>
    <input type="hidden" name="action" value="save_access">
    <input type="hidden" name="rel" value="<?= View::e($accessContent['rel']) ?>">
    <input type="hidden" name="redirect" value="<?= View::e($pathAccess) ?>">
    <textarea name="json" rows="12" class="code"><?= View::e($accessContent['json']) ?></textarea>
    <button type="submit">Salvează ACL</button>
    <a href="<?= View::e($prefix . $pathStay) ?>">Închide</a>
  </form>
</section>
<?php endif; ?>
