<?php
require_once __DIR__ . '/../config/config.php';

// Проверяем, откуда пришел запрос
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Если это API запрос - возвращаем JSON
if (strpos($requestUri, '/api/') !== false) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Доступ запрещен'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Если это запрос к download_backup.php
if (strpos($requestUri, 'download_backup') !== false) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Доступ запрещен. Необходимы права администратора.';
    exit;
}

// Если это запрос к /backups/ напрямую
if (strpos($requestUri, '/backups/') !== false) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Прямой доступ к файлам бэкапов запрещен. Используйте админ-панель.';
    exit;
}

// Обычная страница 403
$pageTitle = 'Доступ запрещен';
$pageDescription = 'У вас недостаточно прав для просмотра этой страницы';

// Если пользователь авторизован - используем обычный header
if (is_logged_in()) {
    require_once __DIR__ . '/../includes/header.php';
} else {
    // Если не авторизован - используем auth_header
    require_once __DIR__ . '/../includes/auth_header.php';
}
?>

<div class="max-w-lg mx-auto px-4 py-20 text-center">
    <div class="text-6xl mb-4">🚫</div>
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Доступ запрещён</h1>
    <p class="text-gray-500 mb-6">У вас недостаточно прав для просмотра этой страницы.</p>
    <div class="flex gap-3 justify-center">
        <a href="<?= url('') ?>" class="inline-block px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
            На главную
        </a>
        <?php if (!is_logged_in()): ?>
        <a href="<?= url('login') ?>" class="inline-block px-6 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition">
            Войти
        </a>
        <?php endif; ?>
    </div>
</div>

<?php
if (is_logged_in()) {
    require_once __DIR__ . '/../includes/footer.php';
} else {
    require_once __DIR__ . '/../includes/auth_footer.php';
}
?>