<?php
/**
 * GigaChefAI — OAuth авторизация (Google, Yandex, VK)
 */
declare(strict_types=1);

function get_oauth_config(string $provider): array
{
    $configs = [
        'google' => [
            'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri'  => SITE_URL . '/login?oauth=google',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'userinfo_url'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scopes'        => ['email', 'profile'],
        ],
        'yandex' => [
            'client_id'     => getenv('YANDEX_OAUTH_CLIENT_ID') ?: '',
            'client_secret' => getenv('YANDEX_OAUTH_CLIENT_SECRET') ?: '',
            'redirect_uri'  => SITE_URL . '/oauth_callback', // добавьте этот адрес в «Callback URI» приложения Яндекс OAuth
            'auth_url'      => 'https://oauth.yandex.ru/authorize',
            'token_url'     => 'https://oauth.yandex.ru/token',
            'userinfo_url'  => 'https://login.yandex.ru/info?format=json',
            'scopes'        => ['login:email', 'login:info'],
        ],
        'vk' => [
            'client_id'     => getenv('VK_CLIENT_ID') ?: '',
            'client_secret' => getenv('VK_CLIENT_SECRET') ?: '',
            'redirect_uri'  => SITE_URL . '/oauth_callback', // ДОЛЖЕН точно совпадать с «Доверенный Redirect URL» в кабинете VK ID
            'auth_url'      => 'https://id.vk.ru/authorize',
            'token_url'     => 'https://id.vk.ru/oauth2/auth',
            'userinfo_url'  => 'https://id.vk.ru/oauth2/user_info',
            'scopes'        => ['email', 'vkid.personal_info'],
        ],
    ];
    
    return $configs[$provider] ?? [];
}

function oauth_vk_secret(array $config): string
{
    return 'gc-vk-oauth|' . ($config['client_secret'] ?: $config['client_id']) . '|' . (defined('SITE_URL') ? SITE_URL : '');
}

/** state вида vk_<nonce32>_<время hex>; время нужно для срока жизни 15 минут */
function oauth_vk_make_state(array $config): string
{
    return 'vk_' . bin2hex(random_bytes(16)) . '_' . dechex(time());
}

/** code_verifier (43 символа, base64url) = HMAC(state) — одинаков при выдаче и обмене кода */
function oauth_vk_verifier(string $state, array $config): string
{
    return rtrim(strtr(base64_encode(hash_hmac('sha256', $state, oauth_vk_secret($config), true)), '+/', '-_'), '=');
}

/** Проверка формата и срока state. Возвращает code_verifier или null */
function oauth_vk_check_state(string $state, array $config): ?string
{
    if (!preg_match('/^vk_[0-9a-f]{32}_([0-9a-f]{1,8})$/', $state, $m)) return null;
    $age = time() - (int) hexdec($m[1]);
    if ($age < -60 || $age > 900) return null;
    return oauth_vk_verifier($state, $config);
}

/** state вида <prefix>_<nonce>_<время hex>_<подпись>: проверяется без сессии, срок 15 минут */
function oauth_signed_state(string $prefix, array $config): string
{
    $body = $prefix . '_' . bin2hex(random_bytes(12)) . '_' . dechex(time());
    return $body . '_' . substr(hash_hmac('sha256', $body, oauth_vk_secret($config), false), 0, 16);
}

function oauth_signed_state_valid(string $state, string $prefix, array $config): bool
{
    if (!preg_match('/^(' . preg_quote($prefix, '/') . '_[0-9a-f]{24}_([0-9a-f]{1,8}))_([0-9a-f]{16})$/', $state, $m)) return false;
    if (!hash_equals(substr(hash_hmac('sha256', $m[1], oauth_vk_secret($config), false), 0, 16), $m[3])) return false;
    $age = time() - (int) hexdec($m[2]);
    return $age >= -60 && $age <= 900;
}

function get_oauth_urls(): array
{
    $urls = [];
    
    foreach (['google', 'yandex', 'vk'] as $provider) {
        $config = get_oauth_config($provider);
        if (empty($config['client_id'])) continue;
        
        $query = [
            'client_id'     => $config['client_id'],
            'redirect_uri'  => $config['redirect_uri'],
            'response_type' => 'code',
            'scope'         => implode(' ', $config['scopes']),
            'state'         => csrf_token(),
        ];
        
        if ($provider === 'yandex') {
            $query['state'] = oauth_signed_state('ya', $config);
        }
        
        if ($provider === 'vk') {
            // VK ID: обязателен PKCE. Без сессии: state подписан, verifier выводится из него HMAC-ом
            $state     = oauth_vk_make_state($config);
            $verifier  = oauth_vk_verifier($state, $config);
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $query['state']                 = $state;
            $query['code_challenge']        = $challenge;
            $query['code_challenge_method'] = 'S256';
        }
        
        $urls[$provider] = $config['auth_url'] . '?' . http_build_query($query);
    }
    
    return $urls;
}

/**
 * Определяет провайдера по параметрам колбэка.
 * Google/Yandex: ?oauth=<provider>&code=...
 * VK ID: ?code=...&state=vk_...&device_id=... (redirect_uri = /oauth_callback без query)
 */
function oauth_detect_provider(array $get): ?string
{
    if (!empty($get['oauth']) && !empty($get['code'])) {
        return (string) $get['oauth'];
    }
    if (!empty($get['code']) && str_starts_with((string)($get['state'] ?? ''), 'ya_')) {
        return 'yandex';
    }
    if (!empty($get['code']) && !empty($get['device_id']) && str_starts_with((string)($get['state'] ?? ''), 'vk_')) {
        return 'vk';
    }
    return null;
}

function oauth_handle_callback(string $provider, string $code, array $extra = []): array
{
    $config = get_oauth_config($provider);
    if (empty($config['client_id'])) {
        return ['ok' => false, 'error' => 'Провайдер не настроен'];
    }
    
    if ($provider === 'yandex' && !oauth_signed_state_valid((string)($extra['state'] ?? ''), 'ya', $config)) {
        return ['ok' => false, 'error' => 'Сессия входа истекла. Начните вход через Яндекс заново.'];
    }
    
    $tokenData = oauth_get_token($provider, $code, $config, $extra);
    
    if (empty($tokenData['access_token'])) {
        error_log("OAuth token error for {$provider}: " . json_encode($tokenData));
        $dbg = $GLOBALS['oauth_last_token_debug'] ?? '';
        return ['ok' => false, 'error' => 'Не удалось получить токен' . ($dbg !== '' ? ' [' . $provider . ': ' . $dbg . ']' : '')];
    }
    
    $userData = oauth_get_user_info($provider, $tokenData['access_token'], $config);
    
    // VK ID может не отдать email (нет доступа в кабинете или у аккаунта нет почты):
    // создаём стабильный технический адрес по ID пользователя VK
    if (empty($userData['email']) && $provider === 'vk' && !empty($userData['provider_id'])) {
        $userData['email'] = 'vk' . preg_replace('/\D/', '', (string)$userData['provider_id']) . '@users.gigachef.ru';
    }
    
    if (empty($userData['email'])) {
        error_log("OAuth userinfo error for {$provider}: " . json_encode($userData));
        $dbg = $GLOBALS['oauth_last_userinfo_debug'] ?? '';
        return ['ok' => false, 'error' => 'Не удалось получить email' . ($dbg !== '' ? ' [' . $provider . ': ' . $dbg . ']' : '')];
    }
    
    return oauth_login_or_register($provider, $userData);
}

/** Список ключей ответа провайдера (вложенные через точку), значения не раскрываются. */
function oauth_debug_keys(array $data, string $prefix = ''): string
{
    $out = [];
    foreach ($data as $k => $v) {
        $name = $prefix . $k;
        if (is_array($v)) {
            $out[] = $name . '{' . oauth_debug_keys($v, '') . '}';
        } else {
            $out[] = $name . ($v === '' || $v === null ? '=пусто' : '');
        }
    }
    return implode(', ', $out);
}

function oauth_get_token(string $provider, string $code, array $config, array $extra = []): array
{
    $params = [
        'client_id'     => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri'  => $config['redirect_uri'],
        'code'          => $code,
        'grant_type'    => 'authorization_code',
    ];
    
    if ($provider === 'vk') {
        $verifier = oauth_vk_check_state((string)($extra['state'] ?? ''), $config);
        if ($verifier === null) {
            error_log('OAuth VK: bad or expired state');
            $GLOBALS['oauth_last_token_debug'] = 'state некорректен или истёк (больше 15 минут). Начните вход заново.';
            return [];
        }
        // VK ID: client_secret не передаётся, нужны code_verifier, device_id, state
        unset($params['client_secret']);
        $params['code_verifier'] = $verifier;
        $params['device_id']     = (string)($extra['device_id'] ?? '');
        $params['state']         = (string)$extra['state'];
    }
    
    $ch = curl_init($config['token_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("OAuth token response [{$httpCode}]: " . mb_substr($resp, 0, 500));
    
    $json = json_decode((string)$resp, true) ?: [];
    // Для диагностики: код ответа, error/error_description от провайдера (токены не раскрываются)
    $GLOBALS['oauth_last_token_debug'] = 'HTTP ' . $httpCode
        . (isset($json['error']) ? '; error=' . (is_scalar($json['error']) ? $json['error'] : json_encode($json['error'], JSON_UNESCAPED_UNICODE)) : '')
        . (isset($json['error_description']) ? '; ' . (is_scalar($json['error_description']) ? $json['error_description'] : '') : '')
        . '; ' . oauth_debug_keys($json);
    
    return $json;
}

function oauth_get_user_info(string $provider, string $token, array $config): array
{
    $ch = curl_init($config['userinfo_url']);
    
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    ];
    
    if ($provider === 'vk') {
        // VK ID: POST, access_token и client_id в теле запроса
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query([
            'client_id'    => $config['client_id'],
            'access_token' => $token,
        ]);
    }
    
    curl_setopt_array($ch, $opts);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("OAuth userinfo response [{$httpCode}]: " . mb_substr($resp, 0, 500));
    
    $data = json_decode($resp, true) ?: [];
    
    // Для диагностики: код ответа и список полей (без значений)
    $GLOBALS['oauth_last_userinfo_debug'] = 'HTTP ' . $httpCode . '; ' . oauth_debug_keys($data);
    
    switch ($provider) {
        case 'google':
            return [
                'email'      => $data['email'] ?? '',
                'name'       => $data['name'] ?? '',
                'avatar_url' => $data['picture'] ?? '',
                'provider'   => 'google',
                'provider_id'=> $data['sub'] ?? '',
            ];
        case 'yandex':
            return [
                'email'      => $data['default_email'] ?? $data['emails'][0] ?? '',
                'name'       => $data['real_name'] ?? $data['display_name'] ?? '',
                // Если своего фото нет, Яндекс отдаёт серую заглушку — её не сохраняем, пусть работает аватар по полу
                'avatar_url' => (!empty($data['default_avatar_id']) && empty($data['is_avatar_empty']))
                    ? 'https://avatars.yandex.net/get-yapic/' . $data['default_avatar_id'] . '/islands-200'
                    : '',
                'provider'   => 'yandex',
                'provider_id'=> $data['id'] ?? '',
            ];
        case 'vk':
            $user = $data['user'] ?? $data;
            return [
                'email'      => $user['email'] ?? '',
                'name'       => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'avatar_url' => $user['avatar'] ?? '',
                'provider'   => 'vk',
                'provider_id'=> $user['user_id'] ?? '',
            ];
    }
    
    return [];
}

function oauth_login_or_register(string $provider, array $userData): array
{
    $email = mb_strtolower(trim($userData['email']));
    
    if (empty($email)) {
        return ['ok' => false, 'error' => 'Email не получен от провайдера'];
    }
    
    $user = db_one('SELECT id FROM users WHERE email = ?', [$email]);
    
    if ($user) {
        $_SESSION['user_id'] = (int) $user['id'];
        db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        
        if (!empty($userData['avatar_url'])) {
            db_exec("UPDATE users SET avatar_url = ? WHERE id = ? AND (avatar_url IS NULL OR avatar_url = '')", 
                [$userData['avatar_url'], $user['id']]);
        }
        
        return ['ok' => true, 'user_id' => (int) $user['id']];
    }
    
    $name = !empty($userData['name']) ? $userData['name'] : explode('@', $email)[0];
    $randomPassword = bin2hex(random_bytes(16));
    
    // Генерируем уникальный никнейм из email
    $baseNickname = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]);
    if (strlen($baseNickname) < 3) $baseNickname = 'user_' . $baseNickname;
    
    $nickname = $baseNickname;
    $counter = 1;
    while (db_one('SELECT id FROM users WHERE nickname = ?', [$nickname])) {
        $nickname = $baseNickname . '_' . $counter;
        $counter++;
    }
    
    $id = db_insert(
        'INSERT INTO users (email, nickname, password_hash, display_name, avatar_url) VALUES (?, ?, ?, ?, ?)',
        [$email, $nickname, password_hash($randomPassword, PASSWORD_DEFAULT), $name, ($userData['avatar_url'] ?? '') !== '' ? $userData['avatar_url'] : null]
    );
    
    db_exec('INSERT INTO user_stats (user_id) VALUES (?)', [$id]);
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    
    $_SESSION['user_id'] = $id;
    
    return ['ok' => true, 'user_id' => $id];
}