<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$recipeId = (int)($_GET['recipe_id'] ?? 0);

if (!$recipeId) {
    json_response(['ok' => false, 'error' => 'Не указан ID рецепта'], 400);
}

// Получаем рецепт
$recipe = db_one('SELECT * FROM recipes WHERE id = ?', [$recipeId]);
if (!$recipe) {
    json_response(['ok' => false, 'error' => 'Рецепт не найден'], 404);
}

$ingredients = json_field($recipe['ingredients']);
$servings = max(1, (int)$recipe['servings']);

// Рассчитываем ХЕ и ГН для каждого ингредиента
$resultIngredients = [];

foreach ($ingredients as $ing) {
    $name = $ing['name'] ?? '';
    $amount = (float)($ing['amount'] ?? 0);
    $unit = $ing['unit'] ?? 'г';
    
    // Получаем данные о продукте
    $gi = get_glycemic_index($name);
    $carbsPer100g = get_carbs_per_100g($name);
    
    // Переводим количество в граммы (упрощенно)
    $amountInGrams = $amount;
    if ($unit === 'кг') {
        $amountInGrams = $amount * 1000;
    } elseif ($unit === 'мл') {
        $amountInGrams = $amount; // Для жидкостей примерно равно
    } elseif ($unit === 'ст.л.') {
        $amountInGrams = $amount * 15;
    } elseif ($unit === 'ч.л.') {
        $amountInGrams = $amount * 5;
    } elseif ($unit === 'шт') {
        $amountInGrams = $amount * 100; // Примерно 100 г на штуку
    }
    
    // Рассчитываем углеводы в порции
    $carbsInPortion = ($carbsPer100g * $amountInGrams / 100) / $servings;
    
    // Рассчитываем ХЕ
    $xe = calculate_bread_units($carbsInPortion);
    
    // Рассчитываем ГН
    $gl = calculate_glycemic_load($gi, $carbsInPortion);
    
    $resultIngredients[] = [
        'name' => $name,
        'amount' => $amount,
        'unit' => $unit,
        'glycemic_index' => $gi,
        'carbs_per_portion' => round($carbsInPortion, 1),
        'xe' => round($xe, 2),
        'gl' => round($gl, 1)
    ];
}

json_response([
    'ok' => true,
    'ingredients' => $resultIngredients,
    'total_xe' => round(array_sum(array_column($resultIngredients, 'xe')), 1),
    'total_gl' => round(array_sum(array_column($resultIngredients, 'gl')), 1),
    'servings' => $servings
]);