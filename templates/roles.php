<?php
use WikiMaxx\View;
/** @var string $csrf */
/** @var string $base */
/** @var list<array{id:string,label:string,builtin:bool,capability:?string}> $catalog */
/** @var list<string> $usedUsers */
/** @var list<string> $usedAcl */
$prefix = ($base === '' || $base === '/') ? '' : $base;
$usedUsersSet = array_flip($usedUsers);
$usedAclSet = array_flip($usedAcl);
?>
<h1>Roluri</h1>
<p><a href="<?= View::e($prefix) ?>/admin">← Admin conținut</a> · <a href="<?= View::e($prefix) ?>/admin/users">Utilizatori</a></p>
<p class="muted">Catalog în <code>roles.json</code>. Id-urile apar în useri și în <code>_access.json</code> — fără FK; la ștergere se verifică utilizarea.</p>

<table class="users">
  <thead>
    <tr><th>Id</th><th>Label</th><th>Capability</th><th>Folosit</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($catalog as $r):
    $fid = 'role-' . preg_replace('/[^a-z0-9_-]/i', '-', $r['id']);
  ?>
    <tr>
      <td>
        <form id="<?= View::e($fid) ?>" method="post" action="<?= View::e($prefix) ?>/admin/roles">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= View::e($r['id']) ?>">
        </form>
        <code><?= View::e($r['id']) ?></code>
        <?php if ($r['builtin']): ?><span class="muted">builtin</span><?php endif; ?>
      </td>
      <td>
        <input form="<?= View::e($fid) ?>" type="text" name="label" value="<?= View::e($r['label']) ?>" required>
      </td>
      <td>
        <?php if ($r['builtin']): ?>
          <input form="<?= View::e($fid) ?>" type="hidden" name="capability" value="<?= View::e($r['capability'] ?? '') ?>">
          <span class="muted"><?= $r['capability'] ? View::e($r['capability']) : '—' ?></span>
        <?php else: ?>
          <label><input form="<?= View::e($fid) ?>" type="checkbox" name="capability" value="write" <?= $r['capability'] === 'write' ? 'checked' : '' ?>> write</label>
        <?php endif; ?>
      </td>
      <td class="muted">
        <?= isset($usedUsersSet[$r['id']]) ? 'users' : '' ?>
        <?= isset($usedAclSet[$r['id']]) ? 'acl' : '' ?>
        <?= !isset($usedUsersSet[$r['id']]) && !isset($usedAclSet[$r['id']]) ? '—' : '' ?>
      </td>
      <td>
        <button form="<?= View::e($fid) ?>" type="submit">Salvează</button>
        <?php if (!$r['builtin']): ?>
        <form method="post" action="<?= View::e($prefix) ?>/admin/roles" class="inline" onsubmit="return confirm('Ștergi rolul?');">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= View::e($r['id']) ?>">
          <button type="submit" class="linkish danger">șterge</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Rol nou</h2>
<form method="post" action="<?= View::e($prefix) ?>/admin/roles" class="form">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
  <input type="hidden" name="action" value="create">
  <label>Id <input type="text" name="id" required pattern="[a-z][a-z0-9_-]{0,63}" placeholder="ex. integrator"></label>
  <label>Label <input type="text" name="label" required placeholder="Integrator"></label>
  <label><input type="checkbox" name="capability" value="write"> write (poate edita conținut pe zonele pe care le citește)</label>
  <button type="submit">Creează</button>
</form>
