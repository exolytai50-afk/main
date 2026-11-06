<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$userEmail = $user['email'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$email = $data['email'] ?? '';
$items = $data['items'] ?? [];
$recipeTitle = trim($data['recipe_title'] ?? '');
$recipeId = (int)($data['recipe_id'] ?? 0);

// Если название не пришло, но есть ID — достаем из базы
if (empty($recipeTitle) && $recipeId > 0) {
    $recipe = db_one('SELECT title FROM recipes WHERE id = ?', [$recipeId]);
    if ($recipe) {
        $recipeTitle = $recipe['title'];
    }
}

// Если и это не сработало, ставим заглушку
if (empty($recipeTitle)) {
    $recipeTitle = 'Ваш рецепт';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Некорректный email']);
    exit;
}

if (empty($items)) {
    echo json_encode(['ok' => false, 'error' => 'Список пуст']);
    exit;
}

// Безопасный вывод названия рецепта
$safeRecipeTitle = htmlspecialchars($recipeTitle, ENT_QUOTES, 'UTF-8');

// Формируем HTML-письмо
$htmlMessage = '
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список покупок: ' . $safeRecipeTitle . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 10px;">
        <tr>
            <td align="center">
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto;">
                    
                    <!-- Шапка -->
                    <tr>
                        <td style="background-color: #f59e0b; padding: 25px 30px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="50" style="vertical-align: middle; padding-right: 15px;">
                                        <img src="https://gigachef.ru/assets/icon/chef_128.png" width="48" height="48" alt="👨‍🍳" style="display: block; border-radius: 12px; border: 2px solid #ffffff; background-color: #ffffff;">
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Список покупок</h1>
                                        <p style="color: #fffbeb; margin: 4px 0 0 0; font-size: 14px; font-weight: 500;">Для рецепта: ' . $safeRecipeTitle . '</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Контент -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #4b5563; font-size: 16px; margin-top: 0; margin-bottom: 20px; line-height: 1.5;">
                                Здравствуйте! Мы подготовили для вас удобный список продуктов для рецепта «' . $safeRecipeTitle . '». Отмечайте купленное прямо в этом письме или просто распечатайте его.
                            </p>
                            
                            <!-- Таблица с продуктами -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                                ';

foreach ($items as $index => $item) {
    $text = htmlspecialchars(strip_tags($item['text'] ?? ''), ENT_QUOTES, 'UTF-8');
    $isChecked = !empty($item['checked']);
    
    $checkbox = $isChecked ? '☑' : '☐';
    $color = $isChecked ? '#9ca3af' : '#374151';
    $textDecoration = $isChecked ? 'line-through' : 'none';
    $bgColor = $isChecked ? '#f9fafb' : '#ffffff';
    $borderBottom = ($index < count($items) - 1) ? 'border-bottom: 1px solid #e5e7eb;' : '';

    $htmlMessage .= '
                                <tr>
                                    <td style="padding: 14px 20px; background-color: ' . $bgColor . '; ' . $borderBottom . '">
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td width="30" style="font-size: 20px; color: #f59e0b; vertical-align: middle;">' . $checkbox . '</td>
                                                <td style="font-size: 16px; color: ' . $color . '; text-decoration: ' . $textDecoration . '; vertical-align: middle; font-weight: 500;">' . $text . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>';
}

$htmlMessage .= '
                            </table>
                            
                            <!-- Кнопка -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-top: 30px;">
                                <tr>
                                    <td align="center">
                                        <a href="https://gigachef.ru" style="display: inline-block; background-color: #f59e0b; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-size: 16px; font-weight: bold;">Открыть GigaChef</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Подвал -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 5px 0; font-weight: bold;">Приятного аппетита!</p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">С уважением, команда GigaChef</p>
                            <p style="color: #9ca3af; font-size: 11px; margin: 10px 0 0 0;">Это письмо сгенерировано автоматически. Пожалуйста, не отвечайте на него.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

// Тема письма теперь содержит название рецепта
$subject = 'Список покупок: ' . $recipeTitle;

// Заголовки
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=utf-8\r\n";
$headers .= "From: GigaChef <no-reply@gigachef.ru>\r\n";
$headers .= "Reply-To: no-reply@gigachef.ru\r\n";

// Отправляем
$sent = mail($email, $subject, $htmlMessage, $headers, '-fno-reply@gigachef.ru');

if ($sent) {
    echo json_encode(['ok' => true, 'message' => 'Письмо отправлено! Проверьте папку «Спам».']);
} else {
    echo json_encode(['ok' => false, 'error' => 'Ошибка при отправке на сервере.']);
}