<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/yandex_ai.php';
require_once __DIR__ . '/../includes/yandex.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$input = json_input();
$recipeId = (int)($input['recipe_id'] ?? 0);
$diabetesType = trim((string)($input['diabetes_type'] ?? 'type2'));
$force = !empty($input['force']);

// ─── ОБРАБОТКА СКРЫТИЯ (СБРОСА) ───
$delete = !empty($input['delete']);
if ($delete) {
    if (!$recipeId) {
        json_response(['ok' => false, 'error' => 'Не указан ID рецепта'], 400);
    }
    if (!is_logged_in()) {
        json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);
    }
    
    // Скрываем (не удаляем) адаптацию
    db_exec(
        'UPDATE diabetes_adapted_recipes SET is_hidden = 1 WHERE original_recipe_id = ? AND user_email = ?',
        [$recipeId, current_user()['email']]
    );
    
    json_response(['ok' => true, 'message' => 'Адаптации скрыты']);
}

$validTypes = [
    'type1'       => 'Диабет 1 типа',
    'type2'       => 'Диабет 2 типа',
    'gestational' => 'Гестационный диабет',
    'prediabetes' => 'Преддиабет',
];

if (!isset($validTypes[$diabetesType])) {
    json_response(['ok' => false, 'error' => 'Неверный тип: ' . $diabetesType], 400);
}
if (!$recipeId) {
    json_response(['ok' => false, 'error' => 'Не указан ID рецепта'], 400);
}

$recipe = db_one('SELECT * FROM recipes WHERE id = ?', [$recipeId]);
if (!$recipe) {
    json_response(['ok' => false, 'error' => 'Рецепт не найден'], 404);
}

// Проверяем, есть ли УЖЕ единая адаптация для этого рецепта (и она НЕ скрыта)
if (is_logged_in() && !$force) {
    $existing = db_one(
        'SELECT * FROM diabetes_adapted_recipes
         WHERE original_recipe_id = ? AND user_email = ? AND is_hidden = 0
         ORDER BY created_at DESC LIMIT 1',
        [$recipeId, current_user()['email']]
    );
    
    if ($existing) {
        $existingRecommendations = json_field($existing['recommendations'] ?? '{}');
        if (isset($existingRecommendations[$diabetesType])) {
            $content = json_field($existing['adapted_content']);
            json_response([
                'ok'         => true,
                'message'    => 'Адаптация и рекомендация уже существуют',
                'type'       => $diabetesType,
                'type_label' => $validTypes[$diabetesType],
                'recipe_id'  => (int)$existing['id'],
                'recipe'     => $content,
                'recommendations' => $existingRecommendations
            ]);
        }
    }
}

$ingredients = json_field($recipe['ingredients']);
$steps = json_field($recipe['steps']);

// ─── Таблица замен продуктов (единая для всех типов) ───
$replacementMap = [
    'сахар' => ['replacement' => 'стевия', 'ratio' => '1/10', 'note' => 'Заменили сахар на стевию (в 10 раз меньше)'],
    'мука' => ['replacement' => 'цельнозерновая мука', 'ratio' => '1:1', 'note' => 'Заменили белую муку на цельнозерновую'],
    'рис' => ['replacement' => 'бурый рис', 'ratio' => '1:1', 'note' => 'Заменили белый рис на бурый'],
    'картофел' => ['replacement' => 'батат', 'ratio' => '1:1', 'note' => 'Заменили картофель на батат'],
    'сметана' => ['replacement' => 'греческий йогурт', 'ratio' => '1:1', 'note' => 'Заменили сметану на греческий йогурт'],
    'сливки' => ['replacement' => 'кокосовые сливки', 'ratio' => '1:1', 'note' => 'Заменили сливки на кокосовые'],
    'майонез' => ['replacement' => 'греческий йогурт', 'ratio' => '1:1', 'note' => 'Заменили майонез на греческий йогурт'],
    'макароны' => ['replacement' => 'цельнозерновые макароны', 'ratio' => '1:1', 'note' => 'Заменили макароны на цельнозерновые'],
    'паста' => ['replacement' => 'цельнозерновая паста', 'ratio' => '1:1', 'note' => 'Заменили пасту на цельнозерновую'],
    'хлеб' => ['replacement' => 'цельнозерновой хлеб', 'ratio' => '1:1', 'note' => 'Заменили белый хлеб на цельнозерновой'],
    'ветчина' => ['replacement' => 'куриная грудка', 'ratio' => '1:1', 'note' => 'Заменили ветчину на куриную грудку'],
    'колбаса' => ['replacement' => 'куриная грудка', 'ratio' => '1:1', 'note' => 'Заменили колбасу на куриную грудку'],
    'сыр' => ['replacement' => 'нежирный сыр (до 20%)', 'ratio' => '1:1', 'note' => 'Заменили жирный сыр на нежирный'],
    'молоко' => ['replacement' => 'миндальное молоко', 'ratio' => '1:1', 'note' => 'Заменили молоко на миндальное'],
    'сливочное масло' => ['replacement' => 'оливковое масло', 'ratio' => '1:1', 'note' => 'Заменили сливочное масло на оливковое'],
    'жирная говядина' => ['replacement' => 'постная говядина', 'ratio' => '1:1', 'note' => 'Заменили жирную говядину на постную'],
    'свинина' => ['replacement' => 'куриная грудка', 'ratio' => '1:1', 'note' => 'Заменили свинину на куриную грудку'],
    'сок' => ['replacement' => 'свежие фрукты', 'ratio' => '1:1', 'note' => 'Заменили сок на свежие фрукты'],
    'газировка' => ['replacement' => 'минеральная вода', 'ratio' => '1:1', 'note' => 'Заменили газировку на минеральную воду'],
];

// ─── Адаптация ингредиентов (единая для всех) ───
$adaptedIngredients = [];
$replacementSummary = [];

foreach ($ingredients as $ing) {
    $name = $ing['name'] ?? '';
    $nameLower = mb_strtolower($name);
    $amount = $ing['amount'] ?? '';
    $unit = $ing['unit'] ?? '';
    $isOptional = !empty($ing['is_optional']);
    
    $newName = $name;
    $newAmount = $amount;
    $replacementNote = '';
    
    foreach ($replacementMap as $keyword => $replacement) {
        if (mb_strpos($nameLower, $keyword) !== false) {
            $newName = $replacement['replacement'];
            $replacementNote = $replacement['note'];
            
            if ($keyword === 'сахар') {
                $newAmount = is_numeric($amount) ? round($amount / 10, 2) : '1/10 от ' . $amount;
            }
            
            $replacementSummary[] = $replacement['note'];
            break;
        }
    }
    
    $adaptedIngredients[] = [
        'name' => $newName,
        'amount' => (string)$newAmount,
        'unit' => $unit,
        'replacement_note' => $replacementNote,
        'is_optional' => $isOptional
    ];
}

// ─── Адаптация шагов ───
$adaptedSteps = [];
foreach ($steps as $step) {
    $adaptedSteps[] = [
        'instruction' => $step['instruction'] ?? '',
        'tip' => $step['tip'] ?? 'Контролируйте порцию углеводов.'
    ];
}

// ─── Рекомендации для КАЖДОГО типа диабета ───
$recommendations = [
    'type1' => [
        'title' => 'Рекомендации при диабете 1 типа',
        'items' => [
            'Рассчитайте хлебные единицы (ХЕ) перед приёмом пищи',
            'Доза инсулина должна соответствовать количеству углеводов',
            'Контролируйте уровень сахара до и через 2 часа после еды',
            'Имейте при себе быстрые углеводы на случай гипогликемии'
        ],
        'warning' => 'Обязательно согласуйте рацион с вашим эндокринологом.'
    ],
    'type2' => [
        'title' => 'Рекомендации при диабете 2 типа',
        'items' => [
            'Контролируйте размер порции — не переедайте',
            'Сочетайте углеводы с белками и клетчаткой',
            'Регулярная физическая активность после еды снижает сахар',
            'Избегайте сладких напитков и продуктов с добавленным сахаром'
        ],
        'warning' => 'При приёме сахароснижающих препаратов проконсультируйтесь с врачом.'
    ],
    'gestational' => [
        'title' => 'Рекомендации при гестационном диабете',
        'items' => [
            'Питайтесь 5-6 раз в день небольшими порциями',
            'Исключите полностью сахар и сладкие напитки',
            'Увеличьте потребление овощей и клетчатки',
            'Контролируйте уровень сахара 4 раза в день',
            'Регулярные прогулки после еды помогают снизить сахар'
        ],
        'warning' => 'Строго следуйте рекомендациям вашего акушера-гинеколога.'
    ],
    'prediabetes' => [
        'title' => 'Рекомендации при преддиабете',
        'items' => [
            'Снизьте общее потребление углеводов на 10-15%',
            'Увеличьте физическую активность до 150 минут в неделю',
            'Следите за весом — снижение на 5-7% значительно улучшает показатели',
            'Включите в рацион больше овощей и цельнозерновых продуктов'
        ],
        'warning' => 'Регулярно проверяйте уровень глюкозы крови (раз в 3-6 месяцев).'
    ]
];

// ─── Общие примечания ───
$generalNotes = "Произведены следующие замены:\n";
if (!empty($replacementSummary)) {
    foreach (array_unique($replacementSummary) as $summary) {
        $generalNotes .= "• " . $summary . "\n";
    }
} else {
    $generalNotes = "В рецепте не найдено продуктов, требующих замены.\n";
}

// ─── Формирование единого контента ───
$adaptedRecipe = [
    'ingredients' => $adaptedIngredients,
    'steps' => $adaptedSteps,
    'general_notes' => $generalNotes,
    'recommendations' => $recommendations
];

// ─── Сохранение ───
$userEmail = current_user()['email'] ?? null;
$adaptedContent = json_encode($adaptedRecipe, JSON_UNESCAPED_UNICODE);
$recommendationsJson = json_encode($recommendations, JSON_UNESCAPED_UNICODE);

// Ищем ЛЮБУЮ адаптацию (даже скрытую), чтобы обновить её
$existingAdaptation = db_one(
    'SELECT id FROM diabetes_adapted_recipes WHERE original_recipe_id = ? AND user_email = ? ORDER BY created_at DESC LIMIT 1',
    [$recipeId, $userEmail]
);

if ($existingAdaptation) {
    // Обновляем и ОБЯЗАТЕЛЬНО ставим is_hidden = 0, чтобы она снова появилась
    db_exec(
        'UPDATE diabetes_adapted_recipes 
         SET adapted_content = ?, recommendations = ?, diabetes_type = ?, notes = ?, is_hidden = 0, created_at = NOW() 
         WHERE id = ?',
        [$adaptedContent, $recommendationsJson, $diabetesType, $generalNotes, $existingAdaptation['id']]
    );
    $adaptationId = $existingAdaptation['id'];
} else {
    // Создаем новую с is_hidden = 0
    $adaptationId = db_insert(
        'INSERT INTO diabetes_adapted_recipes
            (original_recipe_id, user_email, diabetes_type, adapted_content, recommendations, notes, is_hidden, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())',
        [$recipeId, $userEmail, $diabetesType, $adaptedContent, $recommendationsJson, $generalNotes]
    );
}

json_response([
    'ok'            => true,
    'type'          => $diabetesType,
    'type_label'    => $validTypes[$diabetesType],
    'recipe'        => $adaptedRecipe,
    'recommendations' => $recommendations,
    'adaptation_id' => $adaptationId
]);