<?php
/**
 * GigaChef — AI/поиск для кулинарных книг.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0'); // ошибки только в лог — иначе ломается JSON

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';
require_once __DIR__ . '/../includes/cookbook_generator_helpers.php';

function cbfail(string $msg, int $code = 400): void { json_response(['ok'=>false,'error'=>$msg], $code); }
function cb_load(array $in, int $userId): array {
    $id = (int)($in['book_id'] ?? 0);
    $b  = $id > 0 ? cookbook_find_owned($id, $userId) : null;
    if (!$b) cbfail('Книга не найдена', 404);
    return $b;
}
function cb_book_url(array $book): string {
    $s = (string)($book['slug'] ?? '');
    return '/cookbook/' . ($s !== '' ? $s : (int)$book['id']);
}
/** Фильтры могут прийти и как tags{...}, и плоскими полями (category/time). */
function cb_in_tags(array $in): array {
    $t = is_array($in['tags'] ?? null) ? $in['tags'] : [];
    $out = [];
    foreach (['category', 'cuisine', 'diet', 'time'] as $k) {
        $v = $t[$k] ?? ($in[$k] ?? '');
        $v = is_string($v) ? mb_substr(trim(strip_tags($v)), 0, 100) : '';
        if ($v !== '') $out[$k] = $v;
    }
    return $out;
}
function cb_ai_error(Throwable $e): void {
    error_log('[cookbook_ai] ' . $e->getMessage());
    cbfail(APP_DEBUG || is_admin() ? $e->getMessage() : 'AI временно недоступен, попробуйте ещё раз', 502);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') cbfail('Только POST', 405);
if (!is_logged_in())                       cbfail('Требуется вход', 401);
if (!csrf_check())                         cbfail('Сессия устарела, обновите страницу', 403);

@set_time_limit(60);

$user      = current_user();
$userId    = (int)$user['id'];
$userEmail = (string)$user['email'];
$in        = json_input();
$action    = (string)($in['action'] ?? '');

try {
    switch ($action) {

        // =====================================================
        case 'create':
            $title = mb_substr(trim(strip_tags((string)($in['title'] ?? ''))), 0, 200);
            $theme = mb_substr(trim(strip_tags((string)($in['theme'] ?? $title))), 0, 200);
            $desc  = mb_substr(trim(strip_tags((string)($in['description'] ?? ''))), 0, 500);
            if (mb_strlen($title) < 3) cbfail('Название книги слишком короткое');

            $cnt = (int)db_value('SELECT COUNT(*) FROM cookbooks WHERE author_id = ?', [$userId]);
            if ($cnt >= 50) cbfail('Достигнут предел в 50 книг. Удалите ненужные.');

            $base = generate_slug($title);
            $slug = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            for ($i = 0; $i < 5 && db_one('SELECT id FROM cookbooks WHERE slug = ?', [$slug]); $i++) {
                $slug = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
            }

            $cols = [];
            foreach (db_all('SHOW COLUMNS FROM cookbooks') as $c) $cols[$c['Field']] = true;

            $data = [
                'author_email' => $userEmail,
                'author_id'    => $userId,
                'title'        => $title,
                'description'  => $desc,
                'slug'         => $slug,
                'recipes_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'is_public'    => 1,
            ];
            if (isset($cols['theme']))   $data['theme']   = $theme;
            if (isset($cols['ai_calls']))$data['ai_calls']= 0;

            $names = array_keys($data);
            $id    = db_insert(
                'INSERT INTO cookbooks (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')',
                array_values($data)
            );
            // Сразу привязываем найденные в базе рецепты (только опубликованные) — без лишних запросов
            $linked = 0;
            $linkIds = array_values(array_unique(array_filter(array_map('intval', (array)($in['link_ids'] ?? [])))));
            if ($linkIds) {
                $linkIds = array_slice($linkIds, 0, 10);
                $ph   = implode(',', array_fill(0, count($linkIds), '?'));
                $rows = db_all('SELECT id, slug, title, description, cuisine, difficulty, prep_time, cook_time, servings, calories
                                FROM recipes r WHERE ' . cb_published_sql('r') . " AND r.id IN ({$ph})", $linkIds);
                $entries = [];
                $seenT = [];
                foreach ($rows as $r) {
                    $k = cb_title_key((string)$r['title']);
                    if (isset($seenT[$k])) continue;
                    $seenT[$k] = true;
                    $entries[] = cb_book_entry_from_row($r);
                }
                if ($entries) {
                    update_cookbook_recipes((int)$id, $entries);
                    $linked = count($entries);
                }
            }
            $bk = cookbook_find_owned($id, $userId);
            json_response(['ok' => true, 'linked' => $linked, 'url' => cb_book_url($bk), 'book' => cookbook_public($bk)]);

        // =====================================================
        // Поиск в базе по ключевым словам (обязательны) + категория/кухня/диета/время.
        // Без book_id — только кандидаты (книга ещё не создана). С book_id — сразу привязывает.
        case 'search':
            $bookId = (int)($in['book_id'] ?? 0);
            $book   = $bookId > 0 ? cb_load($in, $userId) : null;

            $keywords = mb_substr(trim(strip_tags((string)($in['keywords'] ?? ''))), 0, 300);
            $tags     = cb_in_tags($in);
            $limit    = max(1, min(8, (int)($in['limit'] ?? 3)));

            if (cb_keyword_terms($keywords) === []) cbfail('Укажите ключевые слова (минимум 2 буквы)');

            $recipes = $book ? json_field($book['recipes_json']) : [];
            $bk      = cb_book_keys($recipes);

            $rows = search_recipes_for_cookbook($keywords, $tags, $limit, array_keys($bk['ids']), '', $bk['titles']);

            if (!$book) {
                json_response(['ok' => true, 'found' => count($rows), 'candidates' => array_map(function ($r) {
                    return ['recipe_id' => (int)$r['id'], 'slug' => (string)$r['slug'], 'title' => (string)$r['title']];
                }, $rows)]);
            }

            $linked = cb_with_book_lock((int)$book['id'], function () use ($rows, $book, $userId) {
                $fresh   = cookbook_find_owned((int)$book['id'], $userId);
                $recipes = json_field($fresh['recipes_json']);
                $bk      = cb_book_keys($recipes);
                $n = 0;
                foreach ($rows as $r) {
                    $k = cb_title_key((string)$r['title']);
                    if (isset($bk['ids'][(int)$r['id']]) || isset($bk['titles'][$k])) continue;
                    $bk['ids'][(int)$r['id']] = true;
                    $bk['titles'][$k] = true;
                    $recipes[] = cb_book_entry_from_row($r);
                    $n++;
                }
                if ($n > 0) update_cookbook_recipes((int)$book['id'], $recipes);
                return $n;
            });

            $fresh = cookbook_find_owned((int)$book['id'], $userId);
            json_response(['ok' => true, 'linked' => $linked, 'url' => cb_book_url($fresh), 'book' => cookbook_public($fresh)]);

        // =====================================================
        // План: 1 дешёвый AI-запрос со списком названий. Тема = НАЗВАНИЕ КНИГИ + категория/время/пожелания.
        case 'plan':
            $book  = cb_load($in, $userId);
            $theme = (string)($book['title'] ?? '');
            $need  = max(1, min(8, (int)($in['need'] ?? $in['count'] ?? 3)));
            $tags  = cb_in_tags($in);
            $notes = mb_substr(trim(strip_tags((string)($in['notes'] ?? ''))), 0, 300);

            $recipes = json_field($book['recipes_json']);
            $bk      = cb_book_keys($recipes);
            $avoid   = [];
            foreach (array_slice(array_reverse($recipes), 0, 20) as $r) {
                if (!empty($r['title'])) $avoid[] = (string)$r['title'];
            }

            $ctx = cb_build_ctx($theme, $tags, $notes);
            cb_ai_charge((int)$book['id']);
            try {
                $names = cb_ai_plan($book, $theme, $need, $tags, $notes, $bk['titles'], $ctx, $avoid);
            } catch (Throwable $e) {
                cb_ai_error($e);
            }

            // Названия, которые УЖЕ есть в базе, не генерируем заново — привязываем готовые рецепты
            $queue  = [];
            $linked = 0;
            if ($names) {
                $ph   = implode(',', array_fill(0, count($names), '?'));
                $rows = db_all('SELECT id, slug, title, description, cuisine, difficulty, prep_time, cook_time, servings, calories
                                FROM recipes r WHERE ' . cb_published_sql('r') . " AND r.title IN ({$ph})",
                    array_map(fn($n) => $n['title'], $names));
                $dbByKey = [];
                foreach ($rows as $r) $dbByKey[cb_title_key((string)$r['title'])] ??= $r;

                $toLink = [];
                foreach ($names as $n) {
                    if (count($toLink) + count($queue) >= $need) break;
                    $k = cb_title_key($n['title']);
                    if (isset($dbByKey[$k])) {
                        $r = $dbByKey[$k];
                        if (!isset($bk['ids'][(int)$r['id']])) { $toLink[] = $r; $bk['ids'][(int)$r['id']] = true; }
                    } else {
                        $queue[] = $n;
                    }
                }
                if ($toLink) {
                    $linked = cb_with_book_lock((int)$book['id'], function () use ($toLink, $book, $userId) {
                        $fresh = cookbook_find_owned((int)$book['id'], $userId);
                        $recipes = json_field($fresh['recipes_json']);
                        $keys = cb_book_keys($recipes);
                        $n = 0;
                        foreach ($toLink as $r) {
                            if (isset($keys['titles'][cb_title_key((string)$r['title'])])) continue;
                            $recipes[] = cb_book_entry_from_row($r);
                            $n++;
                        }
                        if ($n) update_cookbook_recipes((int)$book['id'], $recipes);
                        return $n;
                    });
                }
            }
            if (!$queue) json_response(['ok' => true, 'queue' => [], 'linked' => $linked]);

            $token = bin2hex(random_bytes(16));
            $_SESSION['cb_plan_' . $token] = [
                'book_id' => (int)$book['id'],
                'theme'   => $theme,
                'tags'    => $tags,
                'notes'   => $notes,
                'ctx'     => $ctx,
                'ts'      => time(),
            ];
            json_response(['ok' => true, 'queue' => $queue, 'token' => $token, 'linked' => $linked]);

        // =====================================================
        // Один AI-запрос = один рецепт. Клиент вызывает до 3 штук параллельно.
        case 'recipe':
            $token = (string)($in['token'] ?? '');
            $title = mb_substr(trim(strip_tags((string)($in['title'] ?? ''))), 0, 150);
            $p     = $_SESSION['cb_plan_' . $token] ?? null;
            session_write_close(); // не блокируем параллельные запросы этого же пользователя
            if (!$p || $p['ts'] < time() - 1800) cbfail('План устарел, повторите поиск', 409);
            if (mb_strlen($title) < 3) cbfail('Некорректное название');

            $book   = cb_load(['book_id' => (int)$p['book_id']], $userId);
            $bookId = (int)$book['id'];
            $theme  = (string)$p['theme'];
            $tags   = (array)$p['tags'];
            $notes  = (string)($p['notes'] ?? '');
            $ctx    = (array)($p['ctx'] ?? []);

            $bk = cb_book_keys(json_field($book['recipes_json']));
            if (isset($bk['titles'][cb_title_key($title)])) json_response(['ok' => true, 'skipped' => 'duplicate', 'title' => $title]);

            cb_ai_charge($bookId);
            try {
                $rec = cb_ai_recipe($book, $theme, $tags, $title, $notes, $ctx);
            } catch (Throwable $e) {
                cb_ai_error($e);
            }

            // Дубликат уже существующего рецепта на сайте → привязываем его, AI-копию не сохраняем
            $dup = null;
            try { $dup = check_recipe_duplicate($title, (array)($rec['ingredients'] ?? []), $userId, 0.75); } catch (Throwable $e) { error_log('[cookbook_ai] dup: ' . $e->getMessage()); }
            $sim = $dup ? (float)($dup['similarity'] ?? 0) : 0.0;
            if ($sim > 0 && $sim <= 1) $sim *= 100;

            $result = cb_with_book_lock($bookId, function () use ($bookId, $userId, $rec, $dup, $sim, $title) {
                $fresh   = cookbook_find_owned($bookId, $userId);
                $recipes = json_field($fresh['recipes_json']);
                $keys    = cb_book_keys($recipes);
                $tk      = cb_title_key($title);
                if (isset($keys['titles'][$tk])) return 'duplicate';

                if ($dup && $sim >= 85 && !empty($dup['id'])) {
                    $row = db_one('SELECT id, slug, title, description, cuisine, difficulty, prep_time, cook_time, servings, calories
                                   FROM recipes r WHERE r.id = ? AND ' . cb_published_sql('r'), [(int)$dup['id']]);
                    if ($row) {
                        if (isset($keys['ids'][(int)$row['id']]) || isset($keys['titles'][cb_title_key((string)$row['title'])])) return 'duplicate';
                        $recipes[] = cb_book_entry_from_row($row);
                        update_cookbook_recipes($bookId, $recipes);
                        return 'linked';
                    }
                }
                $rec['_status'] = ['exists' => false, 'duplicate' => ($dup && $sim > 0) ? $dup : null];
                $recipes[] = $rec;
                update_cookbook_recipes($bookId, $recipes);
                db_exec('UPDATE cookbooks SET last_fill_at = NOW() WHERE id = ?', [$bookId]);
                return 'ai';
            });

            json_response(['ok' => true, 'result' => $result, 'title' => $title]);

        // =====================================================
        // Убрать пустую книгу, созданную в этой же сессии генерации, если ничего не добавилось
        case 'discard':
            $book = cb_load($in, $userId);
            if (count(json_field($book['recipes_json'])) === 0) {
                db_exec('DELETE FROM cookbooks WHERE id = ? AND author_id = ?', [(int)$book['id'], $userId]);
            }
            json_response(['ok' => true]);

        // =====================================================
        case 'update_book':
            $book  = cb_load($in, $userId);
            $title = mb_substr(trim(strip_tags((string)($in['title'] ?? ''))), 0, 200);
            $desc  = mb_substr(trim(strip_tags((string)($in['description'] ?? ''))), 0, 500);
            if (mb_strlen($title) < 3) cbfail('Название книги слишком короткое');
            db_exec('UPDATE cookbooks SET title = ?, description = ? WHERE id = ? AND author_id = ?', [$title, $desc, (int)$book['id'], $userId]);
            json_response(['ok' => true, 'title' => $title, 'description' => $desc]);

        // =====================================================
        case 'remove_recipe':
            $book  = cb_load($in, $userId);
            $index = (int)($in['index'] ?? -1);
            cb_with_book_lock((int)$book['id'], function () use ($book, $userId, $index) {
                $fresh   = cookbook_find_owned((int)$book['id'], $userId);
                $recipes = json_field($fresh['recipes_json']);
                if (!isset($recipes[$index])) cbfail('Рецепт не найден', 404);
                array_splice($recipes, $index, 1);
                update_cookbook_recipes((int)$book['id'], $recipes);
            });
            json_response(['ok' => true]);

        // =====================================================
        case 'get':
            $book = cb_load($in, $userId);
            json_response([
                'ok'      => true,
                'url'     => cb_book_url($book),
                'book'    => cookbook_public($book),
                'recipes' => json_field($book['recipes_json']),
            ]);
            
        case 'create_recipe':
            $_POST = $in;
            require __DIR__ . '/cookbook_recipe_create.php';
            exit;

        default:
            cbfail('Неизвестное действие');
    }
} catch (Throwable $e) {
    error_log('[cookbook_ai] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    cbfail(APP_DEBUG || is_admin() ? 'Ошибка: ' . $e->getMessage() : 'Не удалось выполнить запрос', 500);
}