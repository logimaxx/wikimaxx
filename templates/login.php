<?php
use WikiMaxx\View;
/** @var string $csrf */
/** @var string $base */
$prefix = ($base === '' || $base === '/') ? '' : $base;
?>
<article class="auth-box">
  <h1>Autentificare</h1>
  <form method="post" action="<?= View::e($prefix) ?>/login" class="form">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <label>Utilizator
      <input type="text" name="username" required autocomplete="username" autofocus>
    </label>
    <label>Parolă
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit">Intră</button>
  </form>
</article>
