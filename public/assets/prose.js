(function () {
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.left = '-9999px';
      document.body.appendChild(area);
      area.select();
      var ok = document.execCommand('copy');
      area.remove();
      if (ok) resolve();
      else reject();
    });
  }

  function decorate(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.prose pre').forEach(function (pre) {
      if (pre.querySelector('.code-copy') || !pre.querySelector('code')) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'code-copy';
      btn.textContent = 'copiază';
      btn.setAttribute('aria-label', 'Copiază codul');
      btn.addEventListener('click', function () {
        var code = pre.querySelector('code');
        var text = code ? code.textContent : '';
        copyText(text || '').then(function () {
          btn.textContent = 'copiat';
          window.setTimeout(function () { btn.textContent = 'copiază'; }, 1400);
        }).catch(function () {
          btn.textContent = 'eșuat';
          window.setTimeout(function () { btn.textContent = 'copiază'; }, 1400);
        });
      });
      pre.appendChild(btn);
    });
  }

  window.WikiMaxxProse = { decorate: decorate };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { decorate(document); });
  } else {
    decorate(document);
  }
})();
