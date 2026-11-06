<?php
/**
 * GigaChefAI — Восстановление пароля
 */
declare(strict_types=1);

function send_password_reset(string $email): array
{
    $user = db_one('SELECT id, display_name FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    
    if (!$user) {
        // Не говорим что пользователь не найден (безопасность)
        return ['ok' => true, 'message' => 'Если такой email существует, ссылка отправлена'];
    }
    
    // Генерируем токен
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Сохраняем токен в БД
    db_exec(
        'INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE token = ?, expires_at = ?',
        [$email, $token, $expires, $token, $expires]
    );
    
    // Отправляем email
    $resetUrl = SITE_URL . '/login?token=' . $token;
    
    $subject = '=?UTF-8?B?' . base64_encode('Восстановление пароля GigaChefAI') . '?=';
    $message = "Здравствуйте, {$user['display_name']}!\n\n"
             . "Вы запросили восстановление пароля на сайте GigaChefAI.\n\n"
             . "Для установки нового пароля перейдите по ссылке:\n"
             . "{$resetUrl}\n\n"
             . "Ссылка действительна 1 час.\n\n"
             . "Если вы не запрашивали восстановление пароля, проигнорируйте это письмо.\n\n"
             . "---\nGigaChefAI";
    
    $headers = "From: GigaChefAI <no-reply@cookai.ru>\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $sent = @mail($email, $subject, $message, $headers);
    
    // Сохраняем в таблицу даже если почта не отправилась
    // (для отладки можно посмотреть токен в логах)
    if (!$sent) {
        error_log("Password reset email failed to send to: {$email}, token: {$token}");
    }
    
    return ['ok' => true, 'message' => 'Ссылка отправлена'];
}

function reset_password_with_token(string $token, string $newPassword): array
{
    if (strlen($newPassword) < 6) {
        return ['ok' => false, 'error' => 'Пароль должен быть не короче 6 символов'];
    }
    
    // Ищем действительный токен
    $reset = db_one(
        'SELECT email, expires_at FROM password_resets WHERE token = ? AND used = 0',
        [$token]
    );
    
    if (!$reset) {
        return ['ok' => false, 'error' => 'Недействительная или использованная ссылка'];
    }
    
    if (strtotime($reset['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'Срок действия ссылки истёк. Запросите новую.'];
    }
    
    // Обновляем пароль
    db_exec(
        'UPDATE users SET password_hash = ? WHERE email = ?',
        [password_hash($newPassword, PASSWORD_DEFAULT), $reset['email']]
    );
    
    // Помечаем токен как использованный
    db_exec('UPDATE password_resets SET used = 1 WHERE token = ?', [$token]);
    
    return ['ok' => true];
}