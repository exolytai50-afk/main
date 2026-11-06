/*!
 * GigaChef — комментарии (лента, сообщества) и посты сообществ.
 * Чистый JS, без зависимостей. Работает через делегирование событий, поэтому
 * подхватывает карточки, подгруженные бесконечной прокруткой.
 *
 * Конфиг: window.GC_SOCIAL = { comments: '/api/gc_comments.php', posts: '/api/gc_community_posts.php', logged: true }
 */
(function () {
  'use strict';
  if (window.__gcSocialLoaded) return;
  window.__gcSocialLoaded = true;

  var CFG = window.GC_SOCIAL || {};
  var API_C = CFG.comments || '/api/gc_comments.php';
  var API_P = CFG.posts || '/api/gc_community_posts.php';
  var MAX_BYTES = 5 * 1024 * 1024;
  var IS_TOUCH = window.matchMedia && matchMedia('(pointer: coarse)').matches;

  /* ───────────── утилиты ───────────── */
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function plural(n, f) {
    var a = Math.abs(n) % 100, b = a % 10;
    if (a > 10 && a < 20) return f[2];
    if (b > 1 && b < 5) return f[1];
    if (b === 1) return f[0];
    return f[2];
  }
  function countLabel(n) {
    return n > 0 ? n + ' ' + plural(n, ['комментарий', 'комментария', 'комментариев']) : 'Комментировать';
  }
  function htmlToEl(html) {
    var t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  }

  var toastTimer;
  function toast(msg, isError) {
    var el = $('#gc-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'gc-toast';
      el.className = 'gc-toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.toggle('is-error', !!isError);
    el.classList.add('is-show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('is-show'); }, 3800);
  }

  function request(url, opts) {
    opts = opts || {};
    opts.credentials = 'same-origin';
    opts.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, opts.headers || {});
    return fetch(url, opts).then(function (r) {
      return r.text().then(function (txt) {
        var j;
        try { j = JSON.parse(txt); } catch (e) { throw new Error('Сервер вернул некорректный ответ'); }
        if (!r.ok || !j.ok) throw new Error(j.error || 'Ошибка запроса');
        return j;
      });
    });
  }
  function get(url, params) {
    var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return request(url + (url.indexOf('?') > -1 ? '&' : '?') + q);
  }
  function postForm(url, fields, file) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) { if (fields[k] !== undefined && fields[k] !== null) fd.append(k, fields[k]); });
    if (file) fd.append('image', file, file.name || 'image');
    return request(url, { method: 'POST', body: fd });
  }

  /* ───────────── редактор (contenteditable + фото) ───────────── */
  var savedRange = null, savedInput = null;
  document.addEventListener('selectionchange', function () {
    var s = window.getSelection();
    if (!s || !s.rangeCount) return;
    var r = s.getRangeAt(0);
    var n = r.commonAncestorContainer;
    var el = n.nodeType === 1 ? n : n.parentElement;
    var inp = el && el.closest ? el.closest('[data-gc-input]') : null;
    if (inp) { savedRange = r.cloneRange(); savedInput = inp; }
  });

  function inputOf(compose) { return $('[data-gc-input]', compose); }

  function isEmptyCompose(compose) {
    var inp = inputOf(compose);
    var hasText = inp.textContent.replace(/ /g, ' ').trim() !== '';
    var hasEmoji = !!inp.querySelector('img');
    return !hasText && !hasEmoji && !compose._file && !compose._keepExisting;
  }
  function refreshSend(compose) {
    var btn = $('[data-gc-send]', compose);
    if (btn) btn.disabled = isEmptyCompose(compose) || compose.classList.contains('is-busy');
  }
  function setBusy(compose, on) {
    compose.classList.toggle('is-busy', on);
    refreshSend(compose);
  }
  function showPreview(compose, url) {
    var box = $('[data-gc-preview]', compose);
    var img = $('[data-gc-preview-img]', compose);
    if (url) { img.src = url; box.hidden = false; } else { img.removeAttribute('src'); box.hidden = true; }
  }
  function attachFile(compose, file) {
    if (!file) return;
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) { toast('Допустимы JPG, PNG, WebP и GIF', true); return; }
    if (file.size > MAX_BYTES) { toast('Файл слишком большой (максимум 5 МБ)', true); return; }
    if (compose._objUrl) URL.revokeObjectURL(compose._objUrl);
    compose._file = file;
    compose._objUrl = URL.createObjectURL(file);
    showPreview(compose, compose._objUrl);
    refreshSend(compose);
  }
  function clearFile(compose) {
    if (compose._objUrl) URL.revokeObjectURL(compose._objUrl);
    compose._file = null;
    compose._objUrl = null;
    var f = $('[data-gc-file]', compose);
    if (f) f.value = '';
    // если редактируем и у записи было фото — убираем его совсем
    if (compose._keepExisting) { compose._keepExisting = false; compose._removeExisting = true; }
    showPreview(compose, null);
    refreshSend(compose);
  }
  function resetCompose(compose) {
    closeEmoji(compose, false);
    inputOf(compose).innerHTML = '';
    compose._keepExisting = false;
    compose._removeExisting = false;
    compose._file = null;
    if (compose._objUrl) URL.revokeObjectURL(compose._objUrl);
    compose._objUrl = null;
    var f = $('[data-gc-file]', compose);
    if (f) f.value = '';
    showPreview(compose, null);
    refreshSend(compose);
  }

  function insertNode(input, node) {
    var sel = window.getSelection();
    var range;
    if (savedRange && savedInput === input && input.contains(savedRange.commonAncestorContainer)) {
      range = savedRange;
    } else {
      range = document.createRange();
      range.selectNodeContents(input);
      range.collapse(false);
    }
    if (!isNarrow()) {
      input.focus();
      sel.removeAllRanges();
      sel.addRange(range);
    }
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
    if (!isNarrow()) { sel.removeAllRanges(); sel.addRange(range); }
    savedRange = range.cloneRange();
    savedInput = input;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function insertText(input, text) { insertNode(input, document.createTextNode(text)); }

  document.addEventListener('input', function (e) {
    var inp = e.target.closest && e.target.closest('[data-gc-input]');
    if (!inp) return;
    // Chrome оставляет <br> в «пустом» поле — чистим, чтобы работал placeholder
    if (inp.innerHTML === '<br>' || inp.innerHTML === '<div><br></div>') inp.innerHTML = '';
    var compose = inp.closest('[data-gc-compose]');
    if (compose) refreshSend(compose);
  });

  document.addEventListener('paste', function (e) {
    var inp = e.target.closest && e.target.closest('[data-gc-input]');
    if (!inp) return;
    var cd = e.clipboardData;
    if (!cd) return;
    var compose = inp.closest('[data-gc-compose]');
    var files = cd.files && cd.files.length ? cd.files : null;
    if (files && files[0] && /^image\//.test(files[0].type)) {
      e.preventDefault();
      attachFile(compose, files[0]);
      return;
    }
    e.preventDefault();
    var text = cd.getData('text/plain') || '';
    if (text) insertText(inp, text);
  });

  document.addEventListener('keydown', function (e) {
    var inp = e.target.closest && e.target.closest('[data-gc-input]');
    if (inp && e.key === 'Enter' && !e.isComposing) {
      var compose = inp.closest('[data-gc-compose]');
      var editing = compose.closest('[data-gc-editing]');
      var isPost = !!compose.closest('[data-gc-post-compose]') || (!!editing && editing.dataset.gcEditing === 'post');
      // посты — многострочные: отправка по Ctrl/Cmd+Enter; комментарии — по Enter (Shift+Enter — перенос)
      var send = isPost ? (e.ctrlKey || e.metaKey) : (!e.shiftKey && !IS_TOUCH);
      if (send) {
        e.preventDefault();
        var b = $('[data-gc-send]', compose);
        if (b && !b.disabled) b.click();
      }
    }
    if (e.key === 'Escape') { closeEmoji(null); closeMenus(); closeLightbox(); }
  });

  document.addEventListener('change', function (e) {
    var f = e.target.closest && e.target.closest('[data-gc-file]');
    if (!f) return;
    var compose = f.closest('[data-gc-compose]');
    if (f.files && f.files[0]) attachFile(compose, f.files[0]);
    f.value = '';
  });

  /* ───────────── панель смайлов (встроена в редактор, как в чате) ───────────── */
  var UNICODE_FALLBACK = ['😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😎', '🤔', '😅', '😉', '🙂', '😢', '😭', '😡', '🥰', '😋', '🤤', '👍', '👎', '👏', '🙏', '💪', '🔥', '❤️', '💔', '🎉', '🍕', '🍔', '🍟', '🍣', '🍰', '🍷', '🍺', '☕', '🥗', '🍎', '🍓', '🌶️', '🥑', '🍝', '🥘', '👨‍🍳', '👩‍🍳'];
  var emojiData = null, emojiLoading = null;
  var mqNarrow = window.matchMedia ? matchMedia('(max-width: 640px)') : { matches: false };
  function isNarrow() { return IS_TOUCH || mqNarrow.matches; }

  function loadEmojis() {
    if (emojiData) return Promise.resolve(emojiData);
    if (emojiLoading) return emojiLoading;
    emojiLoading = get(API_C, { action: 'emojis' }).then(function (j) {
      emojiData = { packs: j.packs || [], by: j.by_pack || {} };
      return emojiData;
    }).catch(function () {
      emojiData = { packs: [], by: {} };
      return emojiData;
    });
    return emojiLoading;
  }
  function panelOf(compose) { return $('[data-gc-epanel]', compose); }
  function ensurePanel(compose) {
    var p = panelOf(compose);
    if (p) return p;
    var smile = $('.gc-ic-smile', compose);
    p = document.createElement('div');
    p.className = 'gc-epanel';
    p.setAttribute('data-gc-epanel', '');
    p.hidden = true;
    p.innerHTML =
      '<div class="gc-ep-tabs"><button type="button" class="gc-ep-tab is-active" tabindex="-1">' +
      (smile ? smile.innerHTML : '') + '<span>Эмодзи</span></button></div>' +
      '<div class="gc-emoji-tabs" data-gc-emoji-tabs></div><div class="gc-emoji-grid" data-gc-emoji-grid></div>';
    // не отбираем фокус у поля на десктопе
    p.addEventListener('mousedown', function (e) { e.preventDefault(); });
    p.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-pack]');
      if (tab) { p._pack = tab.getAttribute('data-pack'); renderEmoji(p); return; }
      var it = e.target.closest('[data-emoji]');
      if (!it) return;
      var input = inputOf(compose);
      if (it.getAttribute('data-emoji') === 'u') {
        insertText(input, it.textContent);
      } else {
        var img = document.createElement('img');
        img.src = it.getAttribute('data-src');
        img.alt = it.getAttribute('data-code') || '';
        img.className = 'inline-emoji';
        img.draggable = false;
        insertNode(input, img);
      }
    });
    $('.gc-compose-box', compose).appendChild(p);
    return p;
  }
  function renderEmoji(p) {
    var d = emojiData, tabs = $('[data-gc-emoji-tabs]', p), grid = $('[data-gc-emoji-grid]', p);
    tabs.innerHTML = ''; grid.innerHTML = '';
    if (!d.packs.length) {
      tabs.hidden = true;
      UNICODE_FALLBACK.forEach(function (ch) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'gc-emoji-item gc-emoji-u'; b.setAttribute('data-emoji', 'u'); b.textContent = ch;
        grid.appendChild(b);
      });
      return;
    }
    tabs.hidden = false;
    if (!p._pack || !d.by[p._pack]) p._pack = String(d.packs[0].id);
    d.packs.forEach(function (pk) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'gc-emoji-tab' + (String(pk.id) === String(p._pack) ? ' is-active' : '');
      b.setAttribute('data-pack', pk.id);
      b.title = pk.name || '';
      if (pk.first_emoji) { var im = document.createElement('img'); im.src = pk.first_emoji; im.alt = ''; im.draggable = false; b.appendChild(im); }
      tabs.appendChild(b);
    });
    (d.by[p._pack] || []).forEach(function (em) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'gc-emoji-item';
      b.setAttribute('data-emoji', 'i'); b.setAttribute('data-src', em.image_url); b.setAttribute('data-code', em.code || '');
      var im = document.createElement('img'); im.src = em.image_url; im.alt = em.code || ''; im.draggable = false; im.loading = 'lazy';
      b.appendChild(im);
      grid.appendChild(b);
    });
    grid.scrollTop = 0;
  }
  function moveCursorToEnd(input) {
    input.focus({ preventScroll: true });
    var r = document.createRange();
    r.selectNodeContents(input); r.collapse(false);
    var s = window.getSelection();
    if (s) { s.removeAllRanges(); s.addRange(r); }
    savedRange = r.cloneRange(); savedInput = input;
  }
  function setPanelState(compose, open) {
    var btn = $('[data-gc-emoji-btn]', compose), p = panelOf(compose);
    if (p) p.hidden = !open;
    compose.classList.toggle('is-emoji-open', open);
    if (btn) { btn.classList.toggle('is-on', open); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  }
  function closeEmoji(compose, refocus) {
    if (!compose) { $$('.gc-compose.is-emoji-open').forEach(function (c) { setPanelState(c, false); }); return; }
    if (!compose.classList.contains('is-emoji-open')) return;
    setPanelState(compose, false);
    // мобильный: «клавиатура» — вернуть фокус и курсор в конец
    if (refocus) { var inp = inputOf(compose); if (inp) moveCursorToEnd(inp); }
  }
  function toggleEmoji(compose) {
    var opened = compose.classList.contains('is-emoji-open');
    if (opened) { closeEmoji(compose, isNarrow()); return; }
    closeEmoji(null);                         // одна панель на странице
    var inp = inputOf(compose);
    if (isNarrow() && inp) inp.blur();        // убираем экранную клавиатуру
    var p = ensurePanel(compose);
    setPanelState(compose, true);
    loadEmojis().then(function () { renderEmoji(p); });
    if (compose.scrollIntoView) setTimeout(function () { compose.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 60);
  }
  // тап по полю на мобильном → панель уступает место клавиатуре
  document.addEventListener('focusin', function (e) {
    var inp = e.target.closest && e.target.closest('[data-gc-input]');
    if (!inp || !isNarrow()) return;
    var c = inp.closest('[data-gc-compose]');
    if (c && c.classList.contains('is-emoji-open')) setPanelState(c, false);
  });

  /* ───────────── лайтбокс ───────────── */
  var lb = null;
  function openLightbox(src) {
    if (!lb) {
      lb = document.createElement('div');
      lb.className = 'gc-lightbox';
      lb.hidden = true;
      lb.innerHTML = '<button type="button" class="gc-lightbox-x" aria-label="Закрыть">×</button><img alt=""><a class="gc-lightbox-open" target="_blank" rel="noopener">Открыть в новой вкладке</a>';
      lb.addEventListener('click', function (e) { if (!e.target.closest('.gc-lightbox-open') && e.target.tagName !== 'IMG') closeLightbox(); });
      document.body.appendChild(lb);
    }
    $('img', lb).src = src;
    $('.gc-lightbox-open', lb).href = src;
    lb.hidden = false;
    document.documentElement.classList.add('gc-noscroll');
  }
  function closeLightbox() {
    if (lb && !lb.hidden) { lb.hidden = true; document.documentElement.classList.remove('gc-noscroll'); }
  }

  /* ───────────── меню «⋯» ───────────── */
  function closeMenus(except) {
    $$('[data-gc-menu-list]').forEach(function (m) { if (m !== except) m.hidden = true; });
  }

  /* ───────────── комментарии ───────────── */
  function shellParts(shell) {
    return {
      panel: $('[data-gc-panel]', shell), list: $('[data-gc-list]', shell),
      empty: $('[data-gc-empty]', shell), loading: $('[data-gc-loading]', shell),
      moreWrap: $('[data-gc-more-wrap]', shell), moreN: $('[data-gc-more-n]', shell)
    };
  }
  function setCount(shell, n) {
    shell.dataset.gcCount = n;
    var l = $('[data-gc-count-label]', shell);
    if (l) l.textContent = countLabel(n);
  }
  function syncEmpty(shell) {
    var p = shellParts(shell);
    p.empty.hidden = !!p.list.children.length;
  }
  function setRemaining(shell, remaining) {
    var p = shellParts(shell);
    p.moreWrap.hidden = !(remaining > 0);
    p.moreN.textContent = remaining > 0 ? '(' + remaining + ')' : '';
  }
  function loadComments(shell, before) {
    var p = shellParts(shell);
    p.loading.hidden = false;
    var params = { action: 'list', ctx: shell.dataset.gcCtx, id: shell.dataset.gcId };
    if (before) params.before = before;
    return get(API_C, params).then(function (j) {
      p.loading.hidden = true;
      if (before) {
        var h0 = document.documentElement.scrollHeight, y0 = window.pageYOffset;
        p.list.insertAdjacentHTML('afterbegin', j.html);
        // не «прыгаем» при подгрузке старых комментариев
        window.scrollTo(0, y0 + (document.documentElement.scrollHeight - h0));
      } else {
        p.list.innerHTML = j.html;
        shell._loaded = true;
      }
      setRemaining(shell, j.remaining);
      setCount(shell, j.total);
      syncEmpty(shell);
    }).catch(function (err) {
      p.loading.hidden = true;
      toast(err.message, true);
    });
  }
  function openShell(shell, focus) {
    var p = shellParts(shell);
    var wasOpen = !p.panel.hidden;
    p.panel.hidden = false;
    $$('[data-gc-toggle]', shell).forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    shell.classList.add('is-open');
    if (!shell._loaded && !shell._loading) {
      shell._loading = true;
      loadComments(shell).then(function () { shell._loading = false; });
    }
    if (focus || !wasOpen) {
      var c = $('[data-gc-compose]', p.panel);
      if (c && focus) inputOf(c).focus();
    }
  }
  function closeShell(shell) {
    shellParts(shell).panel.hidden = true;
    shell.classList.remove('is-open');
    $$('[data-gc-toggle]', shell).forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }

  function addComment(shell, compose) {
    if (compose.classList.contains('is-busy') || isEmptyCompose(compose)) return;
    setBusy(compose, true);
    postForm(API_C, {
      action: 'add', ctx: shell.dataset.gcCtx, id: shell.dataset.gcId, content: inputOf(compose).innerHTML
    }, compose._file).then(function (j) {
      var p = shellParts(shell);
      p.list.insertAdjacentHTML('beforeend', j.html);
      setCount(shell, j.count);
      syncEmpty(shell);
      resetCompose(compose);
      var last = p.list.lastElementChild;
      if (last && last.scrollIntoView) last.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }).catch(function (err) { toast(err.message, true); })
      .then(function () { setBusy(compose, false); });
  }

  function startEdit(box, kind) {
    var tpl = $('#gc-editor-template');
    if (!tpl) { toast('Редактор недоступен', true); return; }
    if (box.querySelector('[data-gc-editing]')) return;

    var wrap = document.createElement('div');
    wrap.className = 'gc-editing';
    wrap.setAttribute('data-gc-editing', kind);
    wrap.appendChild(tpl.content.cloneNode(true));
    var compose = $('[data-gc-compose]', wrap);
    var input = inputOf(compose);

    var textEl, imgEl, hideEls;
    if (kind === 'comment') {
      textEl = $('[data-gc-text]', box);
      imgEl = $('.gc-c-img img', box);
      hideEls = [$('.gc-c-bubble', box), $('.gc-c-meta', box)];
      box.querySelector('.gc-c-main').appendChild(wrap);
    } else {
      textEl = $('[data-gc-post-text]', box);
      imgEl = $('.gc-post-img img', box);
      hideEls = [$('[data-gc-post-body]', box)];
      var actions = $('.gc-post-actions', box);
      box.insertBefore(wrap, actions);
    }
    input.innerHTML = textEl ? textEl.innerHTML : '';
    if (imgEl) {
      compose._keepExisting = true;
      showPreview(compose, imgEl.getAttribute('src'));
    }
    hideEls.forEach(function (el) { if (el) el.hidden = true; });
    wrap._hidden = hideEls;
    refreshSend(compose);
    input.focus();
    var sel = window.getSelection(), r = document.createRange();
    r.selectNodeContents(input); r.collapse(false); sel.removeAllRanges(); sel.addRange(r);
  }
  function cancelEdit(wrap) {
    (wrap._hidden || []).forEach(function (el) { if (el) el.hidden = false; });
    wrap.remove();
  }
  function saveEdit(wrap, compose) {
    if (compose.classList.contains('is-busy')) return;
    if (isEmptyCompose(compose)) { toast('Нельзя сохранить пустое сообщение', true); return; }
    var kind = wrap.dataset.gcEditing;
    setBusy(compose, true);
    var p;
    if (kind === 'comment') {
      var cEl = wrap.closest('.gc-c'), shell = wrap.closest('[data-gc-shell]');
      p = postForm(API_C, {
        action: 'edit', ctx: shell.dataset.gcCtx, cid: cEl.dataset.gcCid,
        content: inputOf(compose).innerHTML, remove_image: compose._removeExisting && !compose._file ? 1 : ''
      }, compose._file).then(function (j) {
        cEl.replaceWith(htmlToEl(j.html));
      });
    } else {
      var art = wrap.closest('[data-gc-post]');
      p = postForm(API_P, {
        action: 'update', post_id: art.dataset.postId,
        content: inputOf(compose).innerHTML, remove_image: compose._removeExisting && !compose._file ? 1 : ''
      }, compose._file).then(function (j) {
        art.replaceWith(htmlToEl(j.html));
      });
    }
    p.catch(function (err) { toast(err.message, true); setBusy(compose, false); });
  }

  function deleteComment(cEl) {
    if (!confirm('Удалить комментарий?')) return;
    var shell = cEl.closest('[data-gc-shell]');
    postForm(API_C, { action: 'delete', ctx: shell.dataset.gcCtx, cid: cEl.dataset.gcCid }).then(function (j) {
      cEl.remove();
      setCount(shell, j.count);
      syncEmpty(shell);
    }).catch(function (err) { toast(err.message, true); });
  }
  function likeComment(btn) {
    var cEl = btn.closest('.gc-c'), shell = btn.closest('[data-gc-shell]');
    btn.disabled = true;
    postForm(API_C, { action: 'like', ctx: shell.dataset.gcCtx, cid: cEl.dataset.gcCid }).then(function (j) {
      btn.classList.toggle('is-on', j.liked);
      btn.setAttribute('aria-pressed', j.liked ? 'true' : 'false');
      $('[data-gc-like-n]', btn).textContent = j.likes > 0 ? j.likes : '';
      btn.classList.remove('gc-pop'); void btn.offsetWidth; btn.classList.add('gc-pop');
    }).catch(function (err) { toast(err.message, true); })
      .then(function () { btn.disabled = false; });
  }

  /* ───────────── посты сообщества ───────────── */
  function postsBox() { return $('[data-gc-posts]'); }

  function createPost(compose) {
    var box = postsBox();
    if (!box || compose.classList.contains('is-busy') || isEmptyCompose(compose)) return;
    setBusy(compose, true);
    postForm(API_P, { action: 'create', community_id: box.dataset.community, content: inputOf(compose).innerHTML }, compose._file)
      .then(function (j) {
        var el = htmlToEl(j.html);
        var firstRegular = $$('[data-gc-post]', box).filter(function (a) { return a.dataset.gcPinned !== '1'; })[0];
        if (firstRegular) box.insertBefore(el, firstRegular); else box.appendChild(el);
        var empty = $('[data-gc-posts-empty]'); if (empty) empty.hidden = true;
        $$('[data-gc-posts-count]').forEach(function (n) { n.textContent = (parseInt(n.textContent, 10) || 0) + 1; });
        box.dataset.offset = (parseInt(box.dataset.offset, 10) || 0) + 1;
        resetCompose(compose);
        var wrap = compose.closest('[data-gc-post-compose]'); if (wrap) wrap.classList.remove('is-open');
        el.classList.add('is-target'); setTimeout(function () { el.classList.remove('is-target'); }, 2000);
      }).catch(function (err) { toast(err.message, true); })
      .then(function () { setBusy(compose, false); });
  }

  var postsLoading = false;
  function loadPosts(replace) {
    var box = postsBox();
    if (!box || postsLoading) return;
    if (!replace && box.dataset.hasmore !== '1') return;
    postsLoading = true;
    var more = $('[data-gc-posts-loading]'); if (more) more.hidden = false;
    get(API_P, {
      action: 'list', community_id: box.dataset.community,
      offset: replace ? 0 : box.dataset.offset, filter: box.dataset.filter || 'all'
    }).then(function (j) {
      if (replace) $$('[data-gc-post]', box).forEach(function (a) { a.remove(); });
      var tmp = document.createElement('div'); tmp.innerHTML = j.html;
      Array.prototype.slice.call(tmp.children).forEach(function (el) {
        if (el.id && document.getElementById(el.id)) return; // дубликат (появился новый пост между страницами)
        box.appendChild(el);
      });
      box.dataset.offset = j.next_offset;
      box.dataset.hasmore = j.has_more ? '1' : '0';
      var empty = $('[data-gc-posts-empty]');
      if (empty) empty.hidden = !!$('[data-gc-post]', box);
    }).catch(function (err) { postsErrAt = Date.now(); toast(err.message, true); })
      .then(function () {
        postsLoading = false; if (more) more.hidden = true;
        setTimeout(checkMorePosts, 80); // если страница всё ещё короткая — догружаем следующую порцию
      });
  }
  var postsErrAt = 0, checkTick = 0;
  function checkMorePosts() {
    var s = $('[data-gc-sentinel]'), box = postsBox();
    if (!s || !box || postsLoading || box.dataset.hasmore !== '1') return;
    if (Date.now() - postsErrAt < 4000) return;            // после ошибки не долбим сервер
    if (s.getBoundingClientRect().top < window.innerHeight + 700) loadPosts(false);
  }
  function initInfinite() {
    var s = $('[data-gc-sentinel]');
    if (!s) return;
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { if (en[0].isIntersecting) checkMorePosts(); }, { rootMargin: '700px 0px' }).observe(s);
    }
    // запасной вариант: scroll/resize (IO срабатывает только на смену видимости)
    var onMove = function () { if (checkTick) return; checkTick = setTimeout(function () { checkTick = 0; checkMorePosts(); }, 120); };
    window.addEventListener('scroll', onMove, { passive: true });
    window.addEventListener('resize', onMove);
    checkMorePosts();
  }

  function votePost(btn) {
    var art = btn.closest('[data-gc-post]');
    btn.disabled = true;
    postForm(API_P, { action: 'vote', post_id: art.dataset.postId, vote: btn.dataset.gcVote }).then(function (j) {
      var like = $('[data-gc-vote="1"]', art), dis = $('[data-gc-vote="-1"]', art);
      like.classList.toggle('is-like', j.my_vote === 1); like.setAttribute('aria-pressed', j.my_vote === 1);
      dis.classList.toggle('is-dislike', j.my_vote === -1); dis.setAttribute('aria-pressed', j.my_vote === -1);
      $('[data-gc-likes]', art).textContent = j.likes > 0 ? j.likes : '';
      $('[data-gc-dislikes]', art).textContent = j.dislikes > 0 ? j.dislikes : '';
      btn.classList.remove('gc-pop'); void btn.offsetWidth; btn.classList.add('gc-pop');
    }).catch(function (err) { toast(err.message, true); })
      .then(function () { btn.disabled = false; });
  }
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (res, rej) {
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy') ? res() : rej(); } catch (e) { rej(e); }
      ta.remove();
    });
  }

  function highlightHash() {
    var h = location.hash;
    if (!/^#gc-(post|comment)-\d+$/.test(h)) return;
    var el = document.getElementById(h.slice(1));
    if (!el) return;
    el.classList.add('is-target');
    setTimeout(function () { el.classList.remove('is-target'); }, 2400);
  }

  /* ───────────── главный обработчик кликов ───────────── */
  document.addEventListener('click', function (e) {
    var t = e.target;
    var z = t.closest('[data-gc-zoom]');
    if (z) { e.preventDefault(); openLightbox(z.getAttribute('href')); return; }

    // закрытие всплывающих окон по клику снаружи
    if (!t.closest('[data-gc-menu]')) closeMenus();

    var b;
    if ((b = t.closest('[data-gc-emoji-btn]'))) { e.preventDefault(); toggleEmoji(b.closest('[data-gc-compose]')); return; }
    if ((b = t.closest('[data-gc-rm-file]'))) { clearFile(b.closest('[data-gc-compose]')); return; }

    if ((b = t.closest('[data-gc-send]'))) {
      var compose = b.closest('[data-gc-compose]');
      var editing = compose.closest('[data-gc-editing]');
      if (editing) saveEdit(editing, compose);
      else if (compose.closest('[data-gc-post-compose]')) createPost(compose);
      else addComment(compose.closest('[data-gc-shell]'), compose);
      return;
    }
    if ((b = t.closest('[data-gc-cancel]'))) {
      var ed = b.closest('[data-gc-editing]');
      if (ed) cancelEdit(ed);
      else {
        var pc = b.closest('[data-gc-post-compose]');
        if (pc) { resetCompose($('[data-gc-compose]', pc)); pc.classList.remove('is-open'); }
      }
      return;
    }

    // ── комментарии
    if ((b = t.closest('[data-gc-toggle]'))) {
      var shell = b.closest('[data-gc-shell]');
      var isOpen = !shellParts(shell).panel.hidden;
      if (b.hasAttribute('data-gc-focus')) openShell(shell, true);
      else if (isOpen) closeShell(shell);
      else openShell(shell, false);
      return;
    }
    if ((b = t.closest('[data-gc-more]'))) {
      var sh = b.closest('[data-gc-shell]'), first = $('.gc-c', shellParts(sh).list);
      loadComments(sh, first ? first.dataset.gcCid : 0);
      return;
    }
    if ((b = t.closest('[data-gc-like]'))) { likeComment(b); return; }
    if ((b = t.closest('[data-gc-reply]'))) {
      var c1 = b.closest('.gc-c'), sh1 = b.closest('[data-gc-shell]'), cmp = $('[data-gc-compose]', shellParts(sh1).panel);
      if (cmp) { var inp = inputOf(cmp); if (!inp.textContent.trim()) { inp.textContent = c1.dataset.gcAuthor + ', '; } inp.focus(); var r = document.createRange(); r.selectNodeContents(inp); r.collapse(false); var s = getSelection(); s.removeAllRanges(); s.addRange(r); refreshSend(cmp); }
      return;
    }
    if ((b = t.closest('[data-gc-edit]'))) { startEdit(b.closest('.gc-c'), 'comment'); return; }
    if ((b = t.closest('[data-gc-del]'))) { deleteComment(b.closest('.gc-c')); return; }

    // ── посты
    if ((b = t.closest('[data-gc-menu-btn]'))) {
      var list = $('[data-gc-menu-list]', b.closest('[data-gc-menu]'));
      closeMenus(list); list.hidden = !list.hidden; return;
    }
    if ((b = t.closest('[data-gc-post-edit]'))) { closeMenus(); startEdit(b.closest('[data-gc-post]'), 'post'); return; }
    if ((b = t.closest('[data-gc-post-del]'))) {
      closeMenus();
      var art = b.closest('[data-gc-post]');
      if (!confirm('Удалить запись вместе с комментариями?')) return;
      postForm(API_P, { action: 'delete', post_id: art.dataset.postId }).then(function () {
        art.remove();
        $$('[data-gc-posts-count]').forEach(function (n) { n.textContent = Math.max(0, (parseInt(n.textContent, 10) || 0) - 1); });
        var box = postsBox(); if (box) { box.dataset.offset = Math.max(0, (parseInt(box.dataset.offset, 10) || 0) - 1); }
        var empty = $('[data-gc-posts-empty]'); if (empty && box) empty.hidden = !!$('[data-gc-post]', box) ? true : false;
      }).catch(function (err) { toast(err.message, true); });
      return;
    }
    if ((b = t.closest('[data-gc-post-pin]'))) {
      closeMenus();
      postForm(API_P, { action: 'pin', post_id: b.closest('[data-gc-post]').dataset.postId })
        .then(function () { loadPosts(true); window.scrollTo({ top: 0, behavior: 'smooth' }); })
        .catch(function (err) { toast(err.message, true); });
      return;
    }
    if ((b = t.closest('[data-gc-vote]'))) { votePost(b); return; }
    if ((b = t.closest('[data-gc-share]'))) {
      var id = b.closest('[data-gc-post]').dataset.postId;
      var link = location.origin + location.pathname + location.search + '#gc-post-' + id;
      copyText(link).then(function () { toast('Ссылка на запись скопирована'); }, function () { toast('Не удалось скопировать ссылку', true); });
      return;
    }
    if ((b = t.closest('[data-gc-readmore]'))) {
      var tx = $('[data-gc-post-text]', b.closest('[data-gc-post]')); if (tx) tx.classList.remove('is-clamped'); b.remove(); return;
    }
    if ((b = t.closest('[data-gc-compose-open]'))) {
      var pcw = b.closest('[data-gc-post-compose]'); pcw.classList.add('is-open');
      var i2 = inputOf($('[data-gc-compose]', pcw)); i2.focus(); return;
    }
    if ((b = t.closest('[data-gc-filter]'))) {
      var box = postsBox(); if (!box || box.dataset.filter === b.dataset.gcFilter) return;
      box.dataset.filter = b.dataset.gcFilter;
      $$('[data-gc-filter]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      loadPosts(true);
      return;
    }
  });

  var booted = false;
  function boot() { if (booted) return; booted = true; initInfinite(); highlightHash(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  window.addEventListener('hashchange', highlightHash);

  window.GCSocial = { toast: toast, openLightbox: openLightbox };
})();
