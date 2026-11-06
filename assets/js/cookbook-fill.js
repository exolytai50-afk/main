/**
 * GigaChef — общая клиентская логика наполнения кулинарной книги
 * (используется ai_cookbook_generator.php и cookbook_detail.php).
 *
 * Поток: 1) поиск в базе по ключевым словам → 2) если не хватило — AI:
 *        один дешёвый запрос-план + рецепты параллельно (до 3 одновременно).
 * Рекламную паузу (gcAdPause / adHold) вызывает сама страница — файл ad-pause.js не затрагивается.
 */
window.cbFill = (function () {
  'use strict';

  async function api(action, payload) {
    const res = await fetch('/api/cookbook_ai.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
      body: JSON.stringify(Object.assign({ action: action }, payload || {}))
    });
    let data;
    try { data = await res.json(); } catch (e) { throw new Error('Ошибка сервера (' + res.status + ')'); }
    if (!data.ok) throw new Error(data.error || 'Ошибка запроса');
    return data;
  }

  /** Выполняет worker для каждого элемента, не более `limit` одновременно. Ошибки не прерывают очередь. */
  async function pool(items, worker, limit) {
    const results = new Array(items.length);
    let next = 0;
    async function run() {
      while (next < items.length) {
        const i = next++;
        try { results[i] = await worker(items[i], i); }
        catch (e) { results[i] = { error: (e && e.message) || 'Ошибка' }; }
      }
    }
    await Promise.all(Array.from({ length: Math.min(limit || 3, items.length) }, run));
    return results;
  }

  function plural(n, f) {
    const a = Math.abs(n) % 100, b = a % 10;
    if (a > 10 && a < 20) return f[2];
    if (b > 1 && b < 5) return f[1];
    if (b === 1) return f[0];
    return f[2];
  }

  /**
   * Наполняет книгу bookId. Возвращает { fromDb, fromAi, total, errors }.
   * ctx: { keywords, tags:{category,cuisine,diet,time}, notes, count, bookId, onStep(text) }
   * preFound — сколько уже привязано из базы на этом шаге (для режима «новая книга»).
   */
  async function fillBook(ctx, preFound) {
    const step = ctx.onStep || function () {};
    const want = Math.max(1, parseInt(ctx.count, 10) || 3);
    let fromDb = preFound || 0;
    let fromAi = 0;
    const errors = [];

    if (preFound === undefined || preFound === null) {
      step('Ищем в базе…');
      const s = await api('search', {
        book_id: ctx.bookId, keywords: ctx.keywords, tags: ctx.tags, limit: want
      });
      fromDb = s.linked || 0;
    }

    let need = want - fromDb;
    if (need > 0) {
      step('Подбираем блюда…');
      const plan = await api('plan', {
        book_id: ctx.bookId, need: need, tags: ctx.tags, notes: ctx.notes
      });
      fromDb += plan.linked || 0;           // совпали по названию с рецептами сайта → привязаны готовые
      const queue = (plan.queue || []).slice(0, Math.max(0, want - fromDb));
      let done = 0;
      if (queue.length) {
        step('Готовим 0 из ' + queue.length + '…');
        const res = await pool(queue, async function (item) {
          const r = await api('recipe', { token: plan.token, title: item.title });
          done++;
          step('Готовим ' + done + ' из ' + queue.length + '…');
          return r;
        }, 3);
        res.forEach(function (r) {
          if (!r || r.error) { errors.push((r && r.error) || 'Ошибка'); return; }
          if (r.result === 'ai') fromAi++;
          else if (r.result === 'linked') fromDb++;
        });
      }
    }
    return { fromDb: fromDb, fromAi: fromAi, total: fromDb + fromAi, errors: errors };
  }

  function summary(r) {
    const parts = [];
    if (r.fromDb) parts.push('из базы: ' + r.fromDb);
    if (r.fromAi) parts.push('создано AI: ' + r.fromAi);
    return parts.join(', ');
  }

  return { api: api, pool: pool, fillBook: fillBook, summary: summary, plural: plural };
})();
