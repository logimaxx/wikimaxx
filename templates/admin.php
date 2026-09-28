<?php
use WikiMaxx\View;
/** @var string $csrf */
/** @var string $base */
/** @var list<array{rel:string,type:string}> $flat */
/** @var array{rel:string,body:string}|null $editContent */
/** @var array{rel:string,json:string}|null $accessContent */
/** @var bool $isAdmin */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<h1>Admin conținut</h1>

<section class="admin-grid">
  <div>
    <h2>Tree</h2>
    <ul class="flat-tree">
      <?php foreach ($flat as $item): ?>
        <li class="type-<?= View::e($item['type']) ?>">
          <code><?= View::e($item['rel']) ?></code>
          <?php if ($item['type'] === 'file' && str_ends_with($item['rel'], '.md')): ?>
            <a href="<?= View::e($prefix) ?>/admin?edit=<?= View::e(rawurlencode($item['rel'])) ?>">edit</a>
          <?php endif; ?>
          <?php if ($item['type'] === 'dir' && $isAdmin): ?>
            <a href="<?= View::e($prefix) ?>/admin?access=<?= View::e(rawurlencode($item['rel'])) ?>">ACL</a>
          <?php endif; ?>
          <?php if ($item['type'] === 'file'): ?>
            <form method="post" action="<?= View::e($prefix) ?>/admin" class="inline" onsubmit="return confirm('Ștergi?');">
              <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="rel" value="<?= View::e($item['rel']) ?>">
              <input type="hidden" name="redirect" value="/admin">
              <button type="submit" class="linkish">șterge</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($isAdmin): ?>
      <p><a href="<?= View::e($prefix) ?>/admin?access=">ACL rădăcină</a></p>
    <?php endif; ?>
  </div>

  <div>
    <h2>Creează</h2>
    <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <input type="hidden" name="action" value="create_folder">
      <input type="hidden" name="redirect" value="/admin">
      <label>Folder părinte (rel)
        <input type="text" name="parent" placeholder="echipa/onboarding">
      </label>
      <label>Nume folder
        <input type="text" name="name" required>
      </label>
      <button type="submit">Folder nou</button>
    </form>

    <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <input type="hidden" name="action" value="create_page">
      <input type="hidden" name="redirect" value="/admin">
      <label>Folder părinte (rel)
        <input type="text" name="parent" placeholder="pornire">
      </label>
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
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="redirect" value="/admin">
      <label>Folder țintă (rel)
        <input type="text" name="parent" placeholder="pornire">
      </label>
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
  <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="action" value="save_page">
    <input type="hidden" name="rel" value="<?= View::e($editContent['rel']) ?>">
    <input type="hidden" name="redirect" value="/admin?edit=<?= View::e(rawurlencode($editContent['rel'])) ?>">
    <textarea name="body" rows="28" class="code"><?= View::e($editContent['body']) ?></textarea>
    <button type="submit">Salvează</button>
    <a href="<?= View::e($prefix) ?>/<?= View::e(preg_replace('/\.md$/', '', $editContent['rel']) ?? '') ?>">Vezi pagina</a>
  </form>
</section>
<?php endif; ?>

<?php if ($accessContent !== null): ?>
<section class="editor">
  <h2>ACL: <?= View::e($accessContent['rel'] === '' ? '/' : $accessContent['rel']) ?></h2>
  <form method="post" action="<?= View::e($prefix) ?>/admin" class="form">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="action" value="save_access">
    <input type="hidden" name="rel" value="<?= View::e($accessContent['rel']) ?>">
    <input type="hidden" name="redirect" value="/admin?access=<?= View::e(rawurlencode($accessContent['rel'])) ?>">
    <textarea name="json" rows="12" class="code"><?= View::e($accessContent['json']) ?></textarea>
    <button type="submit">Salvează ACL</button>
  </form>
</section>
<?php endif; ?>
