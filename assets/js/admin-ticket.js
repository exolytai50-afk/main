/**
 * assets/js/admin-ticket.js — разбор «обращений» из умного чата для pages/admin_messages.php.
 * gcTicket(text)   -> { title, prio, high, rows[], category } | null
 * gcCanned(msgs)   -> массив шаблонов быстрых ответов с учётом категории последнего обращения
 */
(function (root) {
  'use strict';

  function gcTicket(text) {
    if (typeof text !== 'string') return null;
    var lines = text.split('\n');
    var m = lines[0].match(/^Обращение:\s*(.+)$/);
    if (!m) return null;
    var rows = [], prio = 'обычный';
    for (var i = 1; i < lines.length; i++) {
      var l = lines[i], p = l.indexOf(':');
      var k = p > 0 ? l.slice(0, p).trim() : '', v = p > 0 ? l.slice(p + 1).trim() : l;
      if (k === 'Описание') {                       // всё остальное - текст пользователя
        rows.push({ k: 'Описание', v: [v].concat(lines.slice(i + 1)).join('\n').trim() });
        break;
      }
      if (p < 1) { if (rows.length) rows[rows.length - 1].v += '\n' + l; continue; }
      if (k === 'Приоритет') { prio = v; continue; }
      rows.push({ k: k, v: v });
    }
    var level = prio === 'высокий' ? 'high' : (prio === 'низкий' ? 'low' : 'normal');
    var label = level === 'high' ? 'срочно' : (level === 'low' ? 'не срочно' : 'обычный');
    return { title: m[1], category: m[1].split(' › ')[0], prio: prio, level: level, label: label, high: level === 'high', rows: rows };
  }

  var GENERIC = [
    'Здравствуйте! Спасибо за обращение, уже разбираемся.',
    'Уточните, пожалуйста, детали, чтобы мы могли помочь.',
    'Попробуйте обновить страницу (Ctrl+F5) и повторить действие.',
    'Проблема исправлена. Проверьте, пожалуйста, ещё раз.',
    'Если вопрос остался, напишите здесь, мы на связи.'
  ];
  var BY_CAT = {
    'Оплата и Pro': [
      'Проверили платёж: деньги поступили, доступ Pro включён. Обновите страницу.',
      'Пришлите, пожалуйста, время платежа и последние 4 цифры карты. Полные данные карты не нужны.',
      'Оформили возврат. Срок зачисления зависит от вашего банка.'
    ],
    'Аккаунт и вход': [
      'Отправили письмо для восстановления доступа. Проверьте также папку «Спам».',
      'Подтвердите, пожалуйста, email, указанный при регистрации.'
    ],
    'ИИ-инструменты': [
      'Подскажите, какой запрос вы вводили? Попробуем воспроизвести ошибку.',
      'Лимиты ИИ обновляются каждый день, а подписка Pro снимает ограничения.'
    ],
    'Контент и рецепты': [
      'Спасибо за жалобу. Материал проверим и примем меры.',
      'Пришлите, пожалуйста, ссылку на материал.'
    ],
    'Друзья и общение': [
      'Проверим настройки и вернёмся с ответом.'
    ],
    'Ошибка на сайте': [
      'Спасибо, что сообщили. Воспроизвели ошибку, исправим в ближайшее время.',
      'Какой браузер вы используете? Попробуйте открыть страницу в режиме инкогнито.'
    ],
    'Предложение': [
      'Благодарим за идею! Передали команде, обязательно рассмотрим.'
    ]
  };

  function gcCanned(messages) {
    var cat = '';
    if (Array.isArray(messages)) {
      for (var i = messages.length - 1; i >= 0; i--) {
        var t = !messages[i].is_admin && gcTicket(messages[i].text);
        if (t) { cat = t.category; break; }
      }
    }
    return (BY_CAT[cat] || []).concat(GENERIC);
  }

  root.gcTicket = gcTicket;
  root.gcCanned = gcCanned;
  if (typeof module !== 'undefined' && module.exports) module.exports = { gcTicket: gcTicket, gcCanned: gcCanned };
})(typeof window !== 'undefined' ? window : globalThis);
