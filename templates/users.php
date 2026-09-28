<?php
use WikiMaxx\View;
/** @var string $csrf */
/** @var string $base */
/** @var list<array{id:int,username:string,email:string,roles:list<string>}> $users */
/** @var list<array{id:string,label:string,builtin:bool,capability:?string}> $allRoles */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<h1>Utilizatori WikiMaxx</h1>
<p><a href="<?= View::e($prefix) ?>/admin">← Admin conținut</a> · <a href="<?= View::e($prefix) ?>/admin/roles">Roluri</a></p>

<table class="users">
  <thead>
    <tr><th>User</th><th>Email</th><th>Roluri</th><th>Parolă</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u):
    $fid = 'user-' . (int) $u['id'];
  ?>
    <tr>
      <td>
        <form id="<?= View::e($fid) ?>" method="post" action="<?= View::e($prefix) ?>/admin/users">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        </form>
        <strong><?= View::e($u['username']) ?></strong>
      </td>
      <td>
        <input form="<?= View::e($fid) ?>" type="email" name="email" value="<?= View::e($u['email']) ?>" placeholder="email">
      </td>
      <td>
        <span class="roles">
          <?php foreach ($allRoles as $r): ?>
            <label><input form="<?= View::e($fid) ?>" type="checkbox" name="roles[]" value="<?= View::e($r['id']) ?>"
              <?= in_array($r['id'], $u['roles'], true) ? 'checked' : '' ?>> <?= View::e($r['label']) ?></label>
          <?php endforeach; ?>
        </span>
      </td>
      <td>
        <input form="<?= View::e($fid) ?>" type="password" name="password" placeholder="parolă nouă (opțional)" autocomplete="new-password">
      </td>
      <td>
        <button form="<?= View::e($fid) ?>" type="submit">Salvează</button>
        <form method="post" action="<?= View::e($prefix) ?>/admin/users" class="inline" onsubmit="return confirm('Ștergi userul?');">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button type="submit" class="linkish danger">șterge</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>User nou</h2>
<form method="post" action="<?= View::e($prefix) ?>/admin/users" class="form">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
  <input type="hidden" name="action" value="create">
  <label>Username <input type="text" name="username" required></label>
  <label>Email <input type="email" name="email"></label>
  <label>Parolă <input type="password" name="password" required autocomplete="new-password"></label>
  <div class="roles">
    <?php foreach ($allRoles as $r): ?>
      <label><input type="checkbox" name="roles[]" value="<?= View::e($r['id']) ?>" <?= $r['id'] === 'colaborator' ? 'checked' : '' ?>> <?= View::e($r['label']) ?></label>
    <?php endforeach; ?>
  </div>
  <button type="submit">Creează</button>
</form>
