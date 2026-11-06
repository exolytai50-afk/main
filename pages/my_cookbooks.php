<?php
/**
 * GigaChef — Мои кулинарные книги
 * 
 * Возможности:
 *  - список книг
 *  - фильтр (все / с фьюжн / публичные)
 *  - удаление, редактирование
 *  - быстрое создание новой
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/cookbook_helpers.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$user = current_user();
$userId = (int)$user['id'];

$pageTitle = 'Мои кулинарные книги — GigaChef';

// Фильтр
$filter = trim((string)($_GET['filter'] ?? 'all')); // all | public | fusion | private

$allBooks = get_user_cookbooks($userId);

// Обогащаем данными
$books = [];
foreach ($allBooks as $book) {
    $recipes = json_field($book['recipes_json']);
    $recipeCount = count($recipes);
    
    // Считаем фьюжн-рецепты
    $fusionCount = 0;
    foreach ($recipes as $r) {
        if (!empty($r['cuisine_a']) || !empty($r['fusion'])) {
            $fusionCount++;
        }
    }
    
    $book['_recipe_count'] = $recipeCount;
    $book['_fusion_count'] = $fusionCount;
    
    // Фильтрация
    if ($filter === 'public'  && empty($book['is_public'])) continue;
    if ($filter === 'private' && !empty($book['is_public'])) continue;
    if ($filter === 'fusion'  && $fusionCount === 0) continue;
    
    $books[] = $book;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Заголовок -->
    <div class="mb-4" data-aos="fade-up">
        <h1 class="text-3xl font-extrabold text-gray-600 inline-flex items-center gap-2">
            <?= icon('cookbook', 'gc-icon-lg') ?> Мои кулинарные книги
        </h1>
    </div>

    <!-- Кнопки действий -->
    <div class="flex flex-wrap gap-2 mb-6" data-aos="fade-up">
        <a href="<?= url('ai-cookbook') ?>" 
           class="px-4 py-2 rounded-xl bg-violet-100 text-violet-700 font-bold text-sm hover:bg-violet-200 transition inline-flex items-center gap-1.5">
            <?= icon('plus', 'gc-icon-sm') ?> Новая книга
        </a>
        <a href="<?= url('fusion') ?>" 
           class="px-4 py-2 rounded-xl bg-purple-100 text-purple-700 font-bold text-sm hover:bg-purple-200 transition inline-flex items-center gap-1.5">
            <?= icon('flask', 'gc-icon-md') ?> Фьюжн
        </a>
    </div>
    
    <!-- Фильтры -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="?filter=all" 
           class="<?= $filter === 'all' ? 'bg-violet-500 text-white' : 'bg-white text-gray-600 hover:bg-violet-50' ?> px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-1.5">
            Все (<?= count($allBooks) ?>)
        </a>
        <a href="?filter=public" 
           class="<?= $filter === 'public' ? 'bg-violet-500 text-white' : 'bg-white text-gray-600 hover:bg-violet-50' ?> px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-1.5">
            <?= icon('globe', 'gc-icon-md') ?> Публичные
        </a>
        <a href="?filter=private" 
           class="<?= $filter === 'private' ? 'bg-violet-500 text-white' : 'bg-white text-gray-600 hover:bg-violet-50' ?> px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-1.5">
            <?= icon('lock', 'gc-icon-md') ?> Приватные
        </a>
        <a href="?filter=fusion" 
           class="<?= $filter === 'fusion' ? 'bg-violet-500 text-white' : 'bg-white text-gray-600 hover:bg-violet-50' ?> px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-1.5">
            <?= icon('flask', 'gc-icon-md') ?> С фьюжн
        </a>
    </div>

    <?php if (empty($books)): ?>
        <!-- ... блок пустого состояния без изменений ... -->
        <div class="text-center py-20" data-aos="fade-up">
            <div class="mb-4 flex justify-center text-gray-300"><?= icon('cookbook', 'gc-icon-3xl') ?></div>
            <p class="text-gray-400 text-lg">
                <?= $filter === 'all' ? 'У вас пока нет кулинарных книг' : 'Нет книг по этому фильтру' ?>
            </p>
            <a href="<?= url('ai-cookbook') ?>" 
               class="inline-block mt-4 px-6 py-3 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold hover:shadow-md transition inline-flex items-center gap-2">
                <?= icon('sparkle', 'gc-icon-sm') ?> Создать первую книгу
            </a>
        </div>
    <?php else: ?>
        <!-- ... сетка книг без изменений ... -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($books as $book): 
                $recipes = json_field($book['recipes_json']);
                $recipeCount = $book['_recipe_count'];
                $fusionCount = $book['_fusion_count'];
            ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition" data-aos="fade-up">
                <div class="bg-gradient-to-br from-violet-200 to-purple-200 p-6 relative">
                    <div class="absolute top-3 right-3">
                        <?php if (!empty($book['is_public'])): ?>
                            <span class="px-2 py-1 rounded-full bg-white/70 text-xs font-bold text-gray-600 flex items-center">
                                <?= icon('globe', 'gc-icon-md') ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded-full bg-white/70 text-xs font-bold text-gray-600 flex items-center">
                                <?= icon('lock', 'gc-icon-md') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($fusionCount > 0): ?>
                        <div class="absolute top-3 left-3">
                            <span class="px-2 py-1 rounded-full bg-purple-500/90 text-white text-xs font-bold flex items-center gap-1">
                                <?= icon('flask', 'gc-icon-md') ?> <?= $fusionCount ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="text-xl font-extrabold text-gray-600 mt-4 line-clamp-2">
                        <?= e($book['title']) ?>
                    </h3>
                    <?php if (!empty($book['description'])): ?>
                        <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?= e($book['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="flex gap-3 mt-4 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1">
                            <?= icon('openbook', 'gc-icon-sm') ?> <?= $recipeCount ?> рец.
                        </span>
                        <?php if ($fusionCount > 0): ?>
                            <span class="inline-flex items-center gap-1">
                                <?= icon('flask', 'gc-icon-sm') ?> <?= $fusionCount ?> фьюжн
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-4">
                    <?php if (!empty($recipes) && is_array($recipes)): ?>
                        <h4 class="text-xs font-bold text-gray-500 uppercase mb-2">Рецепты (<?= count($recipes) ?>):</h4>
                        <ul class="space-y-1">
                            <?php foreach (array_slice($recipes, 0, 3) as $index => $recipe): ?>
                                <?php 
                                    // Защита от любого формата JSON (массив или объект)
                                    $recipeTitle = 'Без названия';
                                    if (is_array($recipe) && isset($recipe['title'])) {
                                        $recipeTitle = (string)$recipe['title'];
                                    } elseif (is_string($recipe)) {
                                        $recipeTitle = $recipe;
                                    }
                                ?>
                                <li class="text-sm text-gray-600 flex items-center gap-2 w-full">
                                    <span class="text-violet-400 font-bold shrink-0"><?= $index + 1 ?>.</span>
                                    <!-- Убрали truncate, поставили word-break для мобильных -->
                                    <span class="break-words flex-1" style="word-break: break-word;"><?= e($recipeTitle) ?></span>
                                    <?php if (is_array($recipe) && !empty($recipe['created_recipe_id'])): ?>
                                        <span class="text-emerald-500 text-xs shrink-0" title="Создан рецепт"><?= icon('check', 'gc-icon-xs') ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($recipeCount > 3): ?>
                                <li class="text-xs text-gray-400 italic">... и ещё <?= $recipeCount - 3 ?></li>
                            <?php endif; ?>
                        </ul>                        
                    <?php else: ?>
                        <!-- Покажем, если массив пуст или это не массив -->
                        <p class="text-sm text-gray-400 italic py-3">
                            Книга пуста 
                            <?php if (APP_DEBUG): ?>
                                (DEBUG: <?= gettype($recipes) ?>, raw: <?= htmlspecialchars(mb_substr($book['recipes_json'] ?? '', 0, 50)) ?>)
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="px-4 pb-4 flex gap-2">
                    <a href="<?= url('cookbook/' . ($book['slug'] ?: $book['id'])) ?>" 
                       class="flex-1 text-center py-2 rounded-xl bg-violet-100 text-violet-700 font-bold text-sm hover:bg-violet-200 transition flex items-center justify-center gap-1.5">
                        <?= icon('openbook', 'gc-icon-sm') ?> Открыть
                    </a>
                    <button onclick="deleteCookbook(<?= (int)$book['id'] ?>, '<?= e(addslashes($book['title'])) ?>')"
                            class="px-3 py-2 rounded-xl bg-rose-100 text-rose-600 text-sm hover:bg-rose-200 transition flex items-center justify-center"
                            title="Удалить">
                        <?= icon('trash', 'gc-icon-sm') ?>
                    </button>
                </div>
                
                <div class="px-4 pb-3 text-xs text-gray-400">
                    Обновлена: <?= date('d.m.Y H:i', strtotime($book['updated_at'] ?? $book['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
async function deleteCookbook(id, title) {
    if (!confirm('Удалить книгу "' + title + '"?')) return;
    
    try {
        const res = await fetch('/api/cookbook_manage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', book_id: id })
        });
        const data = await res.json();
        
        if (data.ok) {
            // 🔹 Сбрасываем кеш через уникальный параметр
            location.href = '/my-cookbooks?v=' + Date.now();
        } else {
            alert('Ошибка: ' + data.error);
        }
    } catch (e) {
        alert('Ошибка соединения');
    }
}
</script>
<?php require __DIR__ . '/../components/share-block.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>