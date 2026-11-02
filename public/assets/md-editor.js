(function () {
  document.querySelectorAll('[data-md-editor]').forEach(init);

  function init(root) {
    var source = root.querySelector('.md-source');
    var preview = root.querySelector('.md-preview');
    var pos = root.querySelector('[data-md-pos]');
    var state = root.querySelector('[data-md-state]');
    var previewWrap = root.querySelector('.md-preview-wrap');
    if (!source || !preview) return;

    var timer = 0;
    var seq = 0;

    function caret() {
      var value = source.value;
      var at = source.selectionStart;
      var line = value.slice(0, at).split('\n').length;
      var col = at - value.lastIndexOf('\n', at - 1);
      if (pos) pos.textContent = 'Ln ' + line + ', Col ' + col;
    }

    function setView(mode) {
      root.classList.remove('is-write', 'is-split', 'is-preview');
      root.classList.add(mode === 'write' || mode === 'preview' ? 'is-' + mode : 'is-split');
      root.querySelectorAll('[data-view]').forEach(function (btn) {
        btn.setAttribute('aria-pressed', btn.getAttribute('data-view') === (mode === 'write' || mode === 'preview' ? mode : 'split') ? 'true' : 'false');
      });
      if (mode !== 'preview') source.focus();
    }

    function lineRange() {
      var value = source.value;
      var start = source.selectionStart;
      var end = source.selectionEnd;
      var from = value.lastIndexOf('\n', start - 1) + 1;
      var to = value.indexOf('\n', end);
      if (to === -1) to = value.length;
      if (end > start && value.charAt(end - 1) === '\n') to = end - 1;
      return { from: from, to: to };
    }

    function replaceRange(from, to, text, selFrom, selTo) {
      source.setRangeText(text, from, to, 'end');
      source.setSelectionRange(selFrom, selTo);
      source.focus();
      source.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function mapLines(fn) {
      var range = lineRange();
      var block = source.value.slice(range.from, range.to);
      var lines = block.split('\n');
      var next = lines.map(fn).join('\n');
      replaceRange(range.from, range.to, next, range.from, range.from + next.length);
    }

    function heading(level) {
      var mark = '#'.repeat(level) + ' ';
      mapLines(function (line) {
        var current = line.match(/^(#{1,6})\s+/);
        if (current && current[1].length === level) return line.replace(/^#{1,6}\s+/, '');
        if (current) return line.replace(/^#{1,6}\s+/, mark);
        return mark + line;
      });
    }

    function list(ordered) {
      mapLines(function (line, i) {
        var indent = (line.match(/^\s*/) || [''])[0];
        var rest = line.slice(indent.length).replace(/^([-*+]|\d+\.)\s+/, '');
        var bullet = /^(\s*)[-*+]\s+/.test(line);
        var numbered = /^(\s*)\d+\.\s+/.test(line);
        if ((ordered && numbered) || (!ordered && bullet)) return indent + rest;
        return indent + (ordered ? (i + 1) + '. ' : '- ') + rest;
      });
    }

    function quote() {
      mapLines(function (line) {
        if (/^\s*>\s?/.test(line)) return line.replace(/^\s*>\s?/, '');
        return '> ' + line;
      });
    }

    function surround(before, after, placeholder) {
      var start = source.selectionStart;
      var end = source.selectionEnd;
      var value = source.value;
      var selected = value.slice(start, end);
      if (
        selected &&
        value.slice(start - before.length, start) === before &&
        value.slice(end, end + after.length) === after
      ) {
        replaceRange(start - before.length, end + after.length, selected, start - before.length, end - before.length);
        return;
      }
      var inner = selected || placeholder;
      var text = before + inner + after;
      replaceRange(start, end, text, start + before.length, start + before.length + inner.length);
    }

    function link(image) {
      var start = source.selectionStart;
      var end = source.selectionEnd;
      var selected = source.value.slice(start, end);
      var bang = image ? '!' : '';
      if (/^(https?:\/\/|\/)/.test(selected)) {
        var labeled = bang + '[text](' + selected + ')';
        replaceRange(start, end, labeled, start + bang.length + 1, start + bang.length + 5);
        return;
      }
      var text = selected || (image ? 'alt' : 'text');
      var url = 'https://';
      var chunk = bang + '[' + text + '](' + url + ')';
      var urlFrom = start + bang.length + 1 + text.length + 2;
      replaceRange(start, end, chunk, urlFrom, urlFrom + url.length);
    }

    function fence() {
      var selected = source.value.slice(source.selectionStart, source.selectionEnd) || 'cod';
      var start = source.selectionStart;
      var chunk = '```\n' + selected + '\n```';
      replaceRange(start, source.selectionEnd, chunk, start + 4, start + 4 + selected.length);
    }

    var actions = {
      h2: function () { heading(2); },
      h3: function () { heading(3); },
      bold: function () { surround('**', '**', 'text'); },
      italic: function () { surround('*', '*', 'text'); },
      code: function () { surround('`', '`', 'cod'); },
      link: function () { link(false); },
      image: function () { link(true); },
      ul: function () { list(false); },
      ol: function () { list(true); },
      quote: quote,
      fence: fence,
      hr: function () {
        var start = source.selectionStart;
        var chunk = '\n\n---\n\n';
        replaceRange(start, source.selectionEnd, chunk, start + chunk.length, start + chunk.length);
      }
    };

    root.addEventListener('mousedown', function (event) {
      if (event.target.closest('.md-toolbar button')) event.preventDefault();
    });

    root.addEventListener('click', function (event) {
      var tool = event.target.closest('[data-md]');
      if (tool && root.contains(tool)) {
        event.preventDefault();
        var run = actions[tool.getAttribute('data-md')];
        if (run) run();
        return;
      }
      var view = event.target.closest('[data-view]');
      if (view && root.contains(view)) {
        event.preventDefault();
        setView(view.getAttribute('data-view'));
      }
    });

    source.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && !event.altKey) {
        var key = event.key.toLowerCase();
        if (key === 'b') { event.preventDefault(); actions.bold(); return; }
        if (key === 'i') { event.preventDefault(); actions.italic(); return; }
        if (key === 'k') { event.preventDefault(); actions.link(); return; }
        if (key === 's') { event.preventDefault(); source.form.requestSubmit(); return; }
      }
      if (event.key !== 'Tab') return;
      event.preventDefault();
      var range = lineRange();
      if (event.shiftKey) {
        mapLines(function (line) { return line.replace(/^( {1,2}|\t)/, ''); });
        return;
      }
      if (source.selectionStart !== source.selectionEnd && source.value.slice(range.from, range.to).indexOf('\n') !== -1) {
        mapLines(function (line) { return '  ' + line; });
        return;
      }
      var at = source.selectionStart;
      var end = source.selectionEnd;
      replaceRange(at, at, '  ', at + 2, end + 2);
    });

    function rebase(html) {
      var page = root.getAttribute('data-page') || location.href;
      var base;
      try { base = new URL(page, location.origin); } catch (e) { return html; }
      var doc = new DOMParser().parseFromString(html, 'text/html');
      doc.body.querySelectorAll('[src], a[href]').forEach(function (el) {
        ['src', 'href'].forEach(function (attr) {
          if (!el.hasAttribute(attr)) return;
          var val = el.getAttribute(attr);
          if (!val || /^(?:[a-z]+:|\/\/|#|\/)/i.test(val)) return;
          try { el.setAttribute(attr, new URL(val, base).href); } catch (e) { /* keep */ }
        });
        if (el.tagName === 'A') {
          el.setAttribute('target', '_blank');
          el.setAttribute('rel', 'noopener');
        }
      });
      return doc.body.innerHTML;
    }

    function render() {
      var mine = ++seq;
      if (state) state.textContent = 'Se actualizează…';
      var body = new FormData();
      body.append('_csrf', root.getAttribute('data-csrf') || '');
      body.append('action', 'preview_md');
      body.append('body', source.value);
      var lib = root.getAttribute('data-lib');
      if (lib) body.append('lib', lib);
      fetch(root.getAttribute('data-action') || '/admin', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) throw new Error((data && data.error) || 'Previzualizarea a eșuat');
          return data;
        });
      }).then(function (data) {
        if (mine !== seq) return;
        var html = (data && data.html) || '';
        preview.innerHTML = html.trim() === '' ? '<p class="muted">Nimic de afișat.</p>' : rebase(html);
        if (state) state.textContent = '';
      }).catch(function (err) {
        if (mine !== seq) return;
        preview.innerHTML = '<p class="muted"></p>';
        preview.querySelector('p').textContent = err.message || 'Previzualizarea a eșuat';
        if (state) state.textContent = '';
      });
    }

    function schedule() {
      caret();
      window.clearTimeout(timer);
      timer = window.setTimeout(render, 220);
    }

    source.addEventListener('input', schedule);
    source.addEventListener('keyup', caret);
    source.addEventListener('click', caret);
    source.addEventListener('scroll', function () {
      if (!previewWrap || source.scrollHeight <= source.clientHeight) return;
      var ratio = source.scrollTop / (source.scrollHeight - source.clientHeight);
      var max = previewWrap.scrollHeight - previewWrap.clientHeight;
      if (max > 0) previewWrap.scrollTop = ratio * max;
    });

    caret();
    render();
  }
})();
