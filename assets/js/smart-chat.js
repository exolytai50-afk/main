/**
 * assets/js/smart-chat.js — умный чат с администрацией.
 * Сценарий вопросов (кнопки) -> самопомощь -> сбор деталей -> итог уходит администратору
 * обычной отправкой чата (adminChat.send), поэтому серверный код не меняется.
 * Хранилище Alpine: $store.sc
 */
document.addEventListener('alpine:init', function () {
  'use strict';

  var CFG   = window.GC_SC || {};
  var ICONS = CFG.icons || {};
  var URLS  = CFG.urls  || {};
  var DELAY = typeof CFG.delay === 'number' ? CFG.delay : 320;
  var KEY   = 'gc_sc_state_v1';

  var CAT = {
    pay:  'Оплата и Pro',
    acc:  'Аккаунт и вход',
    ai:   'ИИ-инструменты',
    ct:   'Контент и рецепты',
    soc:  'Друзья и общение',
    bug:  'Ошибка на сайте',
    idea: 'Предложение',
    free: 'Свободный вопрос'
  };

  /* ---------- helpers для описания сценария ---------- */
  function opt(label, next, extra) { return Object.assign({ label: label, next: next }, extra || {}); }
  function choices(arr, next, key, asSub) {
    return arr.map(function (l) { return opt(l, next, asSub ? { sub: l } : { ans: { k: key } }); });
  }
  function help(next) {
    return [
      opt('Это помогло', 'resolved', { icon: 'check' }),
      opt('Нужна помощь человека', next, { icon: 'chats' })
    ];
  }
  function text(textStr, key, min, next, extra) {
    return Object.assign({ type: 'text', text: textStr, key: key, min: min, next: next, last: key === 'Описание' }, extra || {});
  }

  /* ---------- дерево сценария ---------- */
  var FLOW = {
    root: { text: 'Здравствуйте! Я автоматический помощник. Выберите тему: подскажу решение или передам обращение администратору.', options: [
      opt('Оплата и Pro',       'pay',       { icon: 'payment',  cat: CAT.pay }),
      opt('Аккаунт и вход',     'acc',       { icon: 'profile',  cat: CAT.acc }),
      opt('ИИ-инструменты',     'ai',        { icon: 'ai',       cat: CAT.ai }),
      opt('Рецепты и контент',  'ct',        { icon: 'openbook', cat: CAT.ct }),
      opt('Друзья и общение',   'soc',       { icon: 'friends',  cat: CAT.soc }),
      opt('Ошибка на сайте',    'bug',       { icon: 'technical',cat: CAT.bug }),
      opt('Предложение',        'idea_text', { icon: 'sparkle',  cat: CAT.idea, prio: 'низкий' }),
      opt('Другой вопрос',      'free_text', { icon: 'chats',    cat: CAT.free })
    ] },

    /* --- Оплата и Pro --- */
    pay: { text: 'Что случилось с оплатой?', options: [
      opt('Платёж не прошёл',        'when_pay',   { sub: 'Платёж не прошёл', prio: 'высокий' }),
      opt('Деньги списали, Pro нет', 'when_pay',   { sub: 'Списали деньги, Pro не включён', prio: 'высокий' }),
      opt('Отменить подписку',       'pay_cancel', { sub: 'Отмена подписки' }),
      opt('Нужна квитанция',         'pay_receipt',{ sub: 'Квитанция или история платежей' }),
      opt('Вернуть деньги',          'pay_refund', { sub: 'Возврат' }),
      opt('Не работает промокод',    'when_pay',   { sub: 'Промокод' })
    ] },
    when_pay: { text: 'Когда это произошло?', options: choices(['Только что', 'Сегодня', 'Вчера', 'Раньше'], 'pay_details', 'Когда') },
    pay_details: text('Опишите ситуацию: сумму, способ оплаты и что вы видите на экране. Данные карты и пароли не присылайте.', 'Описание', 10, 'prio', { placeholder: 'Что произошло…' }),
    pay_cancel:  { text: 'Автопродление отключается в любой момент на странице оплаты. После отмены доступ Pro сохраняется до конца оплаченного периода.',
                   links: [{ label: 'Открыть страницу оплаты', url: 'payment' }], options: help('pay_details') },
    pay_receipt: { text: 'История платежей и электронные квитанции доступны на странице оплаты.',
                   links: [{ label: 'Открыть страницу оплаты', url: 'payment' }], options: help('pay_details') },
    pay_refund:  { text: 'Возврат возможен в течение 14 дней после оплаты. Если этот срок ещё не вышел, передам запрос администратору.',
                   options: [opt('Запросить возврат', 'when_pay', { icon: 'chats' }), opt('Понятно, спасибо', 'resolved', { icon: 'check' })] },

    /* --- Аккаунт и вход --- */
    acc: { text: 'Что с аккаунтом?', options: [
      opt('Не могу войти',            'acc_login',  { sub: 'Не могу войти', prio: 'высокий' }),
      opt('Изменить данные профиля',  'acc_profile',{ sub: 'Изменение профиля' }),
      opt('Удалить аккаунт',          'acc_delete', { sub: 'Удаление аккаунта' }),
      opt('Другое',                   'acc_details',{ sub: 'Другое' })
    ] },
    acc_login:   { text: 'Что происходит при входе?', options: choices(['Неверный пароль', 'Не приходит письмо', 'Аккаунт заблокирован', 'Другое'], 'acc_details', 'Проблема') },
    acc_profile: { text: 'Имя, аватар и другие данные меняются на странице профиля.',
                   links: [{ label: 'Открыть профиль', url: 'profile' }], options: help('acc_details') },
    acc_details: text('Опишите ситуацию. Пароль присылать не нужно.', 'Описание', 10, 'prio', { placeholder: 'Что происходит…' }),
    acc_delete:  text('Опишите, что нужно удалить: аккаунт целиком или отдельные данные.', 'Описание', 5, 'prio'),

    /* --- ИИ --- */
    ai: { text: 'С каким инструментом вопрос?', options: choices(['Генератор рецептов', 'Что приготовить?', 'Сканер калорий', 'Фьюжн-кухня', 'Диетолог', 'AI-книга', 'Другое'], 'ai_issue', 'Инструмент') },
    ai_issue: { text: 'Что именно не так?', options: [
      opt('Ошибка или нет ответа', 'ai_details', { sub: 'Ошибка в работе ИИ' }),
      opt('Закончился лимит',      'ai_limit',   { sub: 'Лимит ИИ' }),
      opt('Результат неточный',    'ai_details', { sub: 'Качество результата' }),
      opt('Как этим пользоваться', 'ai_details', { sub: 'Вопрос по использованию' })
    ] },
    ai_limit:   { text: 'У ИИ-инструментов есть дневные лимиты. Подписка Pro снимает ограничения.',
                  links: [{ label: 'Тарифы Pro', url: 'payment' }], options: help('ai_details') },
    ai_details: text('Опишите, что вы вводили и что получили вместо ожидаемого.', 'Описание', 10, 'prio', { placeholder: 'Ваш запрос и результат…' }),

    /* --- Рецепты и контент --- */
    ct: { text: 'О чём вопрос?', options: [
      opt('Жалоба на материал',            'ct_link',   { sub: 'Жалоба на материал' }),
      opt('Не загружается фото или видео', 'ct_upload', { sub: 'Проблема с загрузкой' }),
      opt('Удалить мой рецепт',            'ct_details',{ sub: 'Удаление рецепта' }),
      opt('Не нашёл рецепт',               'ct_search', { sub: 'Поиск рецепта' })
    ] },
    ct_link:   text('Вставьте ссылку на рецепт, комментарий или профиль.', 'Ссылка', 5, 'ct_reason', { placeholder: 'https://…' }),
    ct_reason: { text: 'В чём причина жалобы?', options: choices(['Спам или реклама', 'Оскорбления', 'Нарушение авторских прав', 'Неприемлемый контент', 'Другое'], 'ct_comment', 'Причина') },
    ct_comment:text('Добавьте комментарий, если нужно.', 'Описание', 3, 'prio', { optional: true, placeholder: 'Комментарий…' }),
    ct_upload: { text: 'Фото в историях до 5 МБ, видео до 50 МБ. Для сканера подходят JPEG, PNG и WebP до 5 МБ. Проверьте размер и формат файла.', options: help('ct_details') },
    ct_search: { text: 'Попробуйте поиск с фильтрами по кухне, диете и времени или предложите блюдо ИИ в разделе «Что приготовить?».',
                 links: [{ label: 'Поиск', url: 'search' }, { label: 'Что приготовить?', url: 'what-to-cook' }], options: help('ct_details') },
    ct_details:text('Опишите вопрос подробнее.', 'Описание', 10, 'prio'),

    /* --- Друзья и общение --- */
    soc: { text: 'Что случилось?', options: [
      opt('Заявки и список друзей',     'soc_friends', { sub: 'Друзья' }),
      opt('Не приходят уведомления',    'soc_notif',   { sub: 'Уведомления' }),
      opt('Заблокировать человека',     'soc_block',   { sub: 'Блокировка' }),
      opt('Пожаловаться на пользователя','ct_link',    { sub: 'Жалоба на пользователя' }),
      opt('Проблема с чатом',           'soc_details', { sub: 'Чаты' })
    ] },
    soc_friends: { text: 'Заявки в друзья, список друзей и чёрный список собраны на странице «Друзья».',
                   links: [{ label: 'Открыть «Друзья»', url: 'friends' }], options: help('soc_details') },
    soc_notif:   { text: 'Уведомления и счётчики обновляются автоматически примерно раз в 10 секунд. Если ничего не меняется, обновите страницу.',
                   links: [{ label: 'Уведомления', url: 'notifications' }], options: help('soc_details') },
    soc_block:   { text: 'Заблокировать человека можно на странице «Друзья». Там же, во вкладке «Чёрный список», его можно разблокировать.',
                   links: [{ label: 'Открыть «Друзья»', url: 'friends' }], options: help('soc_details') },
    soc_details: text('Опишите ситуацию подробнее.', 'Описание', 10, 'prio'),

    /* --- Ошибка на сайте --- */
    bug: { text: 'Что случилось?', options: choices(['Страница не открывается', 'Кнопка не работает', 'Неверные данные', 'Съехала вёрстка', 'Другое'], 'bug_where', null, true) },
    bug_where: { text: 'Это произошло на странице, с которой вы открыли чат?', options: [opt('Да, на этой', 'bug_details'), opt('Нет, на другой', 'bug_url')] },
    bug_url:   text('Укажите страницу или ссылку.', 'Страница', 3, 'bug_details', { placeholder: 'Адрес страницы…' }),
    bug_details: text('Что вы делали перед ошибкой? Это поможет воспроизвести её.', 'Описание', 10, 'prio', { placeholder: 'Ваши действия…' }),

    /* --- Предложение / свободный вопрос --- */
    idea_text: text('Опишите идею: что добавить или улучшить и зачем.', 'Описание', 15, 'confirm', { placeholder: 'Ваша идея…' }),
    free_text: text('Опишите вопрос одним сообщением.', 'Описание', 5, 'prio', { placeholder: 'Ваш вопрос…' }),

    /* --- Срочность (пропускается, если приоритет «высокий» выставлен автоматически) --- */
    prio: { text: 'Насколько срочно нужен ответ?', options: [
      opt('Не срочно',                    'confirm', { prio: 'низкий' }),
      opt('Обычная срочность',            'confirm', { prio: 'обычный' }),
      opt('Срочно, не могу пользоваться', 'confirm', { prio: 'высокий' })
    ] },

    /* --- Финал --- */
    confirm:  { text: function (st) {
                  return st.auto
                    ? 'Проверьте обращение. Приоритет «высокий» выставлен автоматически: вопрос касается оплаты или входа в аккаунт. Отправить администратору?'
                    : 'Проверьте обращение. Отправить администратору?';
                }, summary: true, options: [
      opt('Отправить', '', { icon: 'send', action: 'send' }),
      opt('Начать заново', '', { icon: 'refresh', action: 'restart' })
    ] },
    resolved: { text: 'Рад, что получилось! Если появится вопрос, я рядом.', options: [
      opt('Новый вопрос', '', { icon: 'refresh', action: 'restart' }),
      opt('Закрыть чат',  '', { icon: 'close',   action: 'close' })
    ] }
  };

  /* ---------- контекст ---------- */
  function pagePath() { try { return location.pathname || '/'; } catch (e) { return '/'; } }
  function device() {
    var ua = '', mob = false, b = 'браузер';
    try { ua = navigator.userAgent || ''; mob = /Android|iPhone|iPad|Mobile/i.test(ua) || window.matchMedia('(max-width:1023px)').matches; } catch (e) {}
    if (/Edg\//.test(ua)) b = 'Edge';
    else if (/OPR\/|Opera/.test(ua)) b = 'Opera';
    else if (/YaBrowser/.test(ua)) b = 'Яндекс Браузер';
    else if (/Firefox\//.test(ua)) b = 'Firefox';
    else if (/Chrome\//.test(ua)) b = 'Chrome';
    else if (/Safari\//.test(ua)) b = 'Safari';
    return (mob ? 'телефон' : 'компьютер') + ', ' + b;
  }
  var autoTimer = null, lastSig = '';
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  function clone(x) { return JSON.parse(JSON.stringify(x)); }

  Alpine.store('sc', {
    flow: FLOW,
    chat: null,
    active: false, started: false, busy: false, sending: false,
    run: 0,
    cur: '', log: [], hist: [], answers: [],
    cat: '', sub: '', prio: 'обычный', auto: false, notice: '',

    ic: function (n) { return ICONS[n] || ''; },

    bind: function (chat) { this.chat = chat; this.restore(); },

    persist: function () {
      try {
        if (!this.active) { sessionStorage.removeItem(KEY); return; }
        sessionStorage.setItem(KEY, JSON.stringify({ c: this.cur, l: this.log, h: this.hist, r: this.answers, t: this.cat, s: this.sub, p: this.prio, u: this.auto }));
      } catch (e) {}
    },
    restore: function () {
      try {
        var d = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        if (!d || !FLOW[d.c]) return;
        this.cur = d.c; this.log = d.l || []; this.hist = d.h || []; this.answers = d.r || [];
        this.cat = d.t || ''; this.sub = d.s || ''; this.prio = d.p || 'обычный'; this.auto = !!d.u;
        this.active = true; this.started = true;
      } catch (e) {}
    },

    /* авто-старт и прокрутка (вызывается из x-effect) */
    sync: function (open, loading, n) {
      void this.log.length; void this.busy; void this.cur;
      var self = this;
      /* стартуем только если диалог реально пуст: ждём, пока adminChat загрузит историю */
      if (open && !loading && !n && !this.started && !this.active && !autoTimer) {
        autoTimer = setTimeout(function () {
          autoTimer = null;
          var c = self.chat;
          if (c && c.open && !c.loading && !(c.messages && c.messages.length) && !self.started && !self.active) self.start();
        }, 500);
      }
      /* прокручиваем вниз только когда изменился сценарий (опрос сообщений каждые 3 с не должен дёргать ленту) */
      var sig = this.log.length + '|' + this.cur + '|' + this.busy;
      if (sig !== lastSig) {
        lastSig = sig;
        Alpine.nextTick(function () {
          var el = document.getElementById('sc-scroll');
          if (el) el.scrollTop = el.scrollHeight;
        });
      }
    },

    reset: function () {
      this.run++; this.cur = ''; this.log = []; this.hist = []; this.answers = [];
      this.cat = ''; this.sub = ''; this.prio = 'обычный'; this.auto = false; this.busy = false;
    },
    start: async function () {
      this.reset(); this.notice = ''; this.started = true; this.active = true;
      await this.goto('root');
    },
    stop: function () {
      this.reset(); this.active = false; this.persist();
      var i = document.querySelector('[data-sc-input]'); if (i) i.focus();
    },
    finish: function (notice) {
      this.reset(); this.active = false; this.notice = notice || ''; this.persist();
    },

    say: async function (t, extra, tok) {
      this.busy = true;
      if (DELAY) await sleep(DELAY);
      if (tok !== this.run) { this.busy = false; return false; }
      this.log.push(Object.assign({ w: 'bot', t: t }, extra || {}));
      this.busy = false;
      return true;
    },

    goto: async function (id) {
      var tok = this.run, n = FLOW[id];
      if (!n) return;
      if (id === 'prio' && this.auto) return this.goto('confirm');
      var t = typeof n.text === 'function' ? n.text(this) : n.text;
      var links = (n.links || []).map(function (l) { return { label: l.label, href: URLS[l.url] || '#' }; });
      if (!(await this.say(t, { links: links }, tok))) return;
      if (n.summary) this.log.push({ w: 'sum', title: this.title(), rows: this.lines().slice(1) });
      this.cur = id;
      this.hist.push({ id: id, log: this.log.length, answers: clone(this.answers), cat: this.cat, sub: this.sub, prio: this.prio, auto: this.auto });
      this.persist();
      if (n.type === 'text') { var i = document.querySelector('[data-sc-input]'); if (i) i.focus(); }
    },

    options: function () {
      var n = FLOW[this.cur]; if (!n) return [];
      var o = (n.options || []).slice();
      if (n.type === 'text' && n.optional) o.push({ label: 'Пропустить', skip: true, next: n.next, icon: 'right' });
      if (this.hist.length > 1) o.push({ label: 'Назад', kind: 'back', icon: 'left' });
      if (this.cur !== 'resolved') o.push({ label: 'Написать самому', kind: 'ctl', action: 'stop', icon: 'chats' });
      return o;
    },

    pick: async function (o) {
      if (this.busy || this.sending) return;
      if (o.kind === 'back') return this.back();
      if (o.action === 'restart') return this.start();
      if (o.action === 'stop') return this.stop();
      if (o.action === 'send') return this.send();
      if (o.action === 'close') { this.finish(''); if (this.chat) this.chat.open = false; return; }
      this.log.push({ w: 'me', t: o.label });
      if (o.skip) return this.goto(o.next);
      if (o.cat)  this.cat = o.cat;
      if (o.sub)  this.sub = o.sub;
      if (o.prio) { this.prio = o.prio; this.auto = (o.prio === 'высокий' && !!o.sub); }
      if (o.ans)  this.setAns(o.ans.k, o.ans.v !== undefined ? o.ans.v : o.label, false);
      await this.goto(o.next);
    },

    back: function () {
      if (this.hist.length < 2) return;
      this.run++; this.busy = false;
      this.hist.pop();
      var top = this.hist[this.hist.length - 1];
      this.answers = clone(top.answers); this.cat = top.cat; this.sub = top.sub; this.prio = top.prio; this.auto = !!top.auto;
      this.log.length = top.log; this.cur = top.id;
      this.persist();
    },

    setAns: function (k, v, last) {
      this.answers = this.answers.filter(function (a) { return a.k !== k; });
      this.answers.push({ k: k, v: v, last: !!last });
    },

    placeholder: function () {
      if (!this.active) return 'Ваше сообщение…';
      var n = FLOW[this.cur];
      if (n && n.type === 'text') return n.placeholder || 'Ваш ответ…';
      return 'Выберите вариант или напишите своё…';
    },

    onSubmit: async function () {
      var c = this.chat; if (!c) return;
      if (!this.active) return c.send();
      var v = (c.input || '').trim();
      if (!v || this.busy) return;
      c.input = '';
      var n = FLOW[this.cur];
      this.log.push({ w: 'me', t: v });
      if (n && n.type === 'text') {
        if (v.length < (n.min || 1)) {
          await this.say('Нужно чуть подробнее: минимум ' + n.min + ' символов.', {}, this.run);
          return;
        }
        this.setAns(n.key, v, !!n.last);
        return this.goto(n.next);
      }
      /* свободный текст на шаге с выбором */
      if (!this.cat) this.cat = CAT.free;
      this.setAns('Описание', v, true);
      await this.goto('prio');
    },

    title: function () { return [this.cat || CAT.free, this.sub].filter(Boolean).join(' › '); },

    lines: function () {
      var L = [['Обращение', this.title()], ['Приоритет', this.prio]];
      var has = function (list, k) { return list.some(function (a) { return a.k === k; }); };
      var norm = function (v) { return String(v).replace(/\s+/g, ' ').trim(); };
      if (!has(this.answers, 'Страница')) L.push(['Страница', pagePath()]);
      L.push(['Устройство', device()]);
      this.answers.filter(function (a) { return !a.last; }).forEach(function (a) { L.push([a.k, norm(a.v)]); });
      this.answers.filter(function (a) { return a.last; }).forEach(function (a) { L.push([a.k, String(a.v).trim()]); });
      return L;
    },
    message: function () { return this.lines().map(function (l) { return l[0] + ': ' + l[1]; }).join('\n'); },

    send: async function () {
      if (this.sending || !this.chat) return;
      this.sending = true;
      var text = this.message(), c = this.chat;
      c.input = text;
      try { await c.send(); } catch (e) {}
      /* adminChat при ошибке добавляет в messages служебное сообщение с error:true (или с префиксом предупреждения) */
      var last = Array.isArray(c.messages) ? c.messages.slice(-2) : [];
      var failed = last.some(function (m) { return m && m.is_admin && (m.error || /^\u26A0/.test(m.text || '')); });
      if (failed) {
        this.sending = false;
        await this.say('Не удалось отправить. Проверьте соединение и нажмите «Отправить» ещё раз.', {}, this.run);
        return;
      }
      this.sending = false;
      this.finish('Обращение передано администратору. Ответ появится в этом чате.');
    }
  });
});
