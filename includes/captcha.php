<?php
/**
 * Капча "Собери блюдо"
 *
 * Публичный API:
 *   captcha_create()                       → массив для отрисовки пазла
 *   captcha_mark_solved(string $nonce, array $tiles): bool
 *   captcha_issue_token(string $nonce): ?string
 *   captcha_validate_token(?string $token): bool
 *   captcha_consume_token(string $token): bool
 */
declare(strict_types=1);

const CAPTCHA_GRID       = 4;    // 2×2
const CAPTCHA_TOKEN_TTL  = 900;  // 15 минут
const CAPTCHA_NONCE_TTL  = 900;

/**
 * Список доступных картинок (имя файла без пути).
 * Можно сканировать папку или задать вручную.
 */
function captcha_dish_files(): array
{
    $dir = __DIR__ . '/../assets/img/captcha/dishes';
    $out = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
                $out[] = $f;
            }
        }
    }
    // Фолбэк — если папки нет, будет использован SVG из шаблона
    return $out;
}

/**
 * URL картинки для отрисовки.
 */
function captcha_dish_url(string $file): string
{
    return url('assets/img/captcha/dishes/' . rawurlencode($file));
}

/**
 * Создаёт новое задание. Возвращает массив:
 *   [
 *     'nonce'      => '...',
 *     'image'      => 'URL картинки или null (тогда SVG)',
 *     'tiles'      => [2, 0, 3, 1],   // стартовая раскладка (какой piece в какой ячейке)
 *   ]
 */
function captcha_create(): array
{
    captcha_gc();

    $nonce = bin2hex(random_bytes(16)); // 32 символа
    $dishes = captcha_dish_files();
    $image  = $dishes ? $dishes[random_int(0, count($dishes) - 1)] : null;

    // Правильная раскладка: [0,1,2,3] (piece 0 в ячейке 0 и т.д.)
    $solution = range(0, CAPTCHA_GRID - 1);

    // Стартовая — гарантированно не собранная
    do {
        $start = $solution;
        shuffle($start);
    } while ($start === $solution);

    captcha_storage_save($nonce, [
        'solution' => $solution,
        'image'    => $image,
        'solved'   => 0,
        'used'     => 0,
        'created'  => time(),
    ]);

    return [
        'nonce' => $nonce,
        'image' => $image ? captcha_dish_url($image) : null,
        'tiles' => $start,
    ];
}

/**
 * Помечает nonce как решённый (если присланная раскладка совпадает с решением).
 */
function captcha_mark_solved(string $nonce, array $tiles): bool
{
    $data = captcha_storage_load($nonce);
    if (!$data) return false;
    if (!empty($data['used'])) return false;

    // Приводим к int, проверяем длину
    $tiles = array_map('intval', $tiles);
    if (count($tiles) !== CAPTCHA_GRID) return false;
    if ($tiles !== $data['solution']) return false;

    $data['solved'] = 1;
    captcha_storage_save($nonce, $data);
    return true;
}

/**
 * Выдаёт HMAC-подписанный токен для формы, если nonce решён.
 * Формат: base64(json) . '.' . hmac
 */
function captcha_issue_token(string $nonce): ?string
{
    $data = captcha_storage_load($nonce);
    if (!$data || empty($data['solved']) || !empty($data['used'])) {
        return null;
    }

    $payload = [
        'nonce' => $nonce,
        'sid'   => session_id(),
        'exp'   => time() + CAPTCHA_TOKEN_TTL,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $b64  = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig  = hash_hmac('sha256', $b64, captcha_secret());

    return $b64 . '.' . $sig;
}

/**
 * Проверяет подпись и срок годности токена (не помечает использованным).
 */
function captcha_validate_token(?string $token): bool
{
    if (!$token || strpos($token, '.') === false) return false;

    [$b64, $sig] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $b64, captcha_secret());
    if (!hash_equals($expected, $sig)) return false;

    $json = base64_decode(strtr($b64, '-_', '+/'), true);
    if ($json === false) return false;

    $data = json_decode($json, true);
    if (!is_array($data)) return false;
    if (($data['sid'] ?? '') !== session_id()) return false;
    if (($data['exp'] ?? 0) < time()) return false;

    return true;
}

/**
 * Проверяет токен И помечает nonce использованным — чтобы нельзя было
 * отправить одну и ту же капчу дважды.
 */
function captcha_consume_token(?string $token): bool
{
    if (!captcha_validate_token($token)) return false;

    [$b64, ] = explode('.', $token, 2);
    $json = base64_decode(strtr($b64, '-_', '+/'), true);
    $data = json_decode($json, true);

    $nonce = $data['nonce'] ?? '';
    if (!$nonce) return false;

    $row = captcha_storage_load($nonce);
    if (!$row || !empty($row['used'])) return false;

    $row['used'] = 1;
    captcha_storage_save($nonce, $row);

    return true;
}

/* ============================================================
 *  Хранилище: сессия ИЛИ БД (авто-выбор)
 * ============================================================ */

function captcha_storage_save(string $nonce, array $data): void
{
    if (captcha_use_db()) {
        db_exec(
            'INSERT INTO captcha_tokens (nonce, session_id, solved, used)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE solved = VALUES(solved), used = VALUES(used)',
            [$nonce, session_id(), (int)$data['solved'], (int)$data['used']]
        );
        // solution и image держим в сессии, чтобы не расширять схему
        $_SESSION['captcha'][$nonce] = $data;
    } else {
        $_SESSION['captcha'][$nonce] = $data;
    }
}

function captcha_storage_load(string $nonce): ?array
{
    if (captcha_use_db()) {
        $row = db_one(
            'SELECT nonce, solved, used FROM captcha_tokens WHERE nonce = ? AND session_id = ?',
            [$nonce, session_id()]
        );
        if (!$row) return null;
        $data = $_SESSION['captcha'][$nonce] ?? null;
        if (!$data) return null;
        $data['solved'] = (int)$row['solved'];
        $data['used']   = (int)$row['used'];
        return $data;
    }
    return $_SESSION['captcha'][$nonce] ?? null;
}

function captcha_use_db(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        db_one('SELECT 1 FROM captcha_tokens LIMIT 1');
        $has = true;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

function captcha_gc(): void
{
    // Чистим старые nonce из сессии
    if (!empty($_SESSION['captcha'])) {
        $now = time();
        foreach ($_SESSION['captcha'] as $n => $d) {
            if (($d['created'] ?? 0) + CAPTCHA_NONCE_TTL < $now) {
                unset($_SESSION['captcha'][$n]);
            }
        }
    }
}

function captcha_secret(): string
{
    if (defined('CAPTCHA_SECRET')) {
        return CAPTCHA_SECRET;
    }
    // Фолбэк — из env или фиксированный (НЕ безопасно для прода)
    $s = getenv('CAPTCHA_SECRET');
    if ($s) return $s;

    // Последний фолбэк: app-ключ
    if (defined('APP_KEY')) return APP_KEY;

    return 'change-me-captcha-secret-' . __DIR__;
}