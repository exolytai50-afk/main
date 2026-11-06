<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors_direct.log');
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

// Проверка CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Недействительный токен']);
    exit;
}

$user = current_user();
$email = $user['email'];
$isEdit = !empty($_POST['edit']);

// Валидация
$cpd = (int)($_POST['cigarettes_per_day'] ?? 0);
$years = (int)($_POST['years_smoking'] ?? 0);
$quitMethod = trim($_POST['quit_method'] ?? '');
$quitDate = trim($_POST['quit_date'] ?? '');

$validMethods = [
    'cold_turkey', 'gradual', 'nrt', 'car', 'cbt',
    'mindfulness', 'meds', 'combined', 'switch',
];

if ($cpd < 1 || $cpd > 100) {
    echo json_encode(['error' => 'Укажите корректное количество сигарет (1–100)']);
    exit;
}
if ($years < 1 || $years > 80) {
    echo json_encode(['error' => 'Укажите корректный стаж курения (1–80)']);
    exit;
}
if (!in_array($quitMethod, $validMethods)) {
    echo json_encode(['error' => 'Выберите метод отказа']);
    exit;
}
if (!$quitDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $quitDate)) {
    echo json_encode(['error' => 'Укажите дату отказа']);
    exit;
}

// Подготовка данных
$data = [
    'cigarettes_per_day'   => $cpd,
    'years_smoking'        => $years,
    'cigarettes_per_pack'  => (int)($_POST['cigarettes_per_pack'] ?? 20),
    'pack_price'           => floatval($_POST['pack_price'] ?? 0),
    'wake_up_smoke'        => trim($_POST['wake_up_smoke'] ?? ''),
    'first_cigarette_time' => trim($_POST['first_cigarette_time'] ?? ''),
    'trigger_coffee'       => !empty($_POST['trigger_coffee']) ? 1 : 0,
    'trigger_stress'       => !empty($_POST['trigger_stress']) ? 1 : 0,
    'trigger_alcohol'      => !empty($_POST['trigger_alcohol']) ? 1 : 0,
    'trigger_after_meal'   => !empty($_POST['trigger_after_meal']) ? 1 : 0,
    'trigger_social'       => !empty($_POST['trigger_social']) ? 1 : 0,
    'trigger_driving'      => !empty($_POST['trigger_driving']) ? 1 : 0,
    'trigger_boredom'      => !empty($_POST['trigger_boredom']) ? 1 : 0,
    'previous_attempts'    => trim($_POST['previous_attempts'] ?? ''),
    'previous_methods'     => trim($_POST['previous_methods'] ?? ''),
    'motivation_health'    => !empty($_POST['motivation_health']) ? 1 : 0,
    'motivation_money'     => !empty($_POST['motivation_money']) ? 1 : 0,
    'motivation_family'    => !empty($_POST['motivation_family']) ? 1 : 0,
    'motivation_pregnancy' => !empty($_POST['motivation_pregnancy']) ? 1 : 0,
    'motivation_fitness'   => !empty($_POST['motivation_fitness']) ? 1 : 0,
    'quit_method'          => $quitMethod,
    'quit_date'            => $quitDate,
];

try {
    if ($isEdit) {
        // Обновляем существующий профиль
        $existing = db_one(
            'SELECT id FROM quit_smoking_profiles WHERE user_email = ? ORDER BY created_at DESC LIMIT 1',
            [$email]
        );
        if ($existing) {
            $setParts = [];
            $params = [];
            foreach ($data as $key => $value) {
                $setParts[] = "$key = ?";
                $params[] = $value;
            }
            $setParts[] = 'updated_at = NOW()';
            $params[] = $email;
            db_exec(
                'UPDATE quit_smoking_profiles SET ' . implode(', ', $setParts) . ' WHERE user_email = ?',
                $params
            );
        } else {
            // Если профиль не найден — создаём
            $isEdit = false;
        }
    }

    if (!$isEdit) {
        $columns = array_merge(['user_email'], array_keys($data));
        $placeholders = array_fill(0, count($columns), '?');
        $params = array_merge([$email], array_values($data));
        db_insert(
            'INSERT INTO quit_smoking_profiles (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('save_quit_profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Ошибка сохранения',
        'debug' => APP_DEBUG ? $e->getMessage() : null,
    ]);
}
