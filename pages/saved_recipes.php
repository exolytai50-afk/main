<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$userEmail = $user['email'];

// Получаем сохраненные рецепты
$savedRecipes = [];
try {
    $savedRecipes = db_all(
        "SELECT r.id, r.slug, r.title, r.image_url, r.likes_count, r.views_count, r.created_at,
                r.description, r.cuisine, r.difficulty, r.prep_time, r.cook_time, r.servings, r.calories,
                f.created_at as saved_at
         FROM favorites f
         INNER JOIN recipes r ON r.id = f.recipe_id
         WHERE f.user_email = ?
         ORDER BY f.created_at DESC",
        [$userEmail]
    );
} catch (Throwable $e) {
    error_log('Error fetching saved recipes: ' . $e->getMessage());
    $savedRecipes = [];
}

$pageTitle = 'Сохраненные рецепты — GigaChef';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6" data-aos="fade-up">❤️ Сохраненные рецепты</h1>
    
    <?php if (empty($savedRecipes)): ?>
        <div class="bg-white rounded-2xl shadow-md p-12 text-center" data-aos="fade-up">
            <div class="text-6xl mb-4">🤍</div>
            <p class="text-gray-400 text-lg">У вас пока нет сохраненных рецептов</p>
            <a href="<?= url('') ?>" class="inline-block mt-4 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
                Найти рецепты
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <?php foreach ($savedRecipes as $recipe): 
                $recipeUrl = !empty($recipe['slug']) ? url('recipe/' . $recipe['slug']) : url('recipe?id=' . (int)$recipe['id']);
                $totalTime = (int)($recipe['prep_time'] ?? 0) + (int)($recipe['cook_time'] ?? 0);
            ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition group" data-aos="fade-up">
                <a href="<?= $recipeUrl ?>" class="block relative aspect-[4/3] overflow-hidden bg-gray-100">
                    <?php if (!empty($recipe['image_url'])): ?>
                        <img src="<?= e($recipe['image_url']) ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                             alt="<?= e($recipe['title']) ?>"
                             loading="lazy"
                             onerror="this.src='/uploads/static/static.jpg'">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-5xl">🍽️</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($recipe['difficulty'])): ?>
<span class="absolute top-3 right-3 px-2 py-1 rounded-lg text-xs font-bold <?= difficulty_color($recipe['difficulty']) ?>">
    <?= e(difficulty_label($recipe['difficulty'])) ?>
</span>
<?php endif; ?>
                </a>
                
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-1 text-xs text-gray-400">
                        <?php if (!empty($recipe['cuisine'])): ?>
                        <span><?= e($recipe['cuisine']) ?></span>
                        <span>•</span>
                        <?php endif; ?>
                        <span>⏱ <?= $totalTime ?> мин</span>
                    </div>
                    
                    <a href="<?= $recipeUrl ?>" class="block">
                        <h2 class="font-bold text-gray-800 leading-snug clamp-2 hover:text-amber-600 transition">
                            <?= e($recipe['title']) ?>
                        </h2>
                    </a>
                    
                    <?php if (!empty($recipe['description'])): ?>
                    <p class="text-sm text-gray-500 clamp-2 mt-1"><?= e($recipe['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50">
                        <div class="flex gap-3 text-xs text-gray-400">
                            <span>❤️ <?= (int)($recipe['likes_count'] ?? 0) ?></span>
                            <span>👁️ <?= (int)($recipe['views_count'] ?? 0) ?></span>
                        </div>
                        
                        <button onclick="removeSaved(<?= (int)$recipe['id'] ?>, this)" 
                                class="text-xs font-semibold text-rose-500 hover:text-rose-600 transition"
                                title="Убрать из сохраненных">
                            ✕ Убрать
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
async function removeSaved(recipeId, btn) {
    if (!confirm('Убрать рецепт из сохраненных?')) return;
    
    try {
        const response = await fetch('/api/saved_recipes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ recipe_id: recipeId })
        });
        
        const result = await response.json();
        
        if (result.ok) {
            // Удаляем карточку из DOM
            const card = btn.closest('.bg-white.rounded-2xl');
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                card.style.transition = 'all 0.3s';
                setTimeout(() => card.remove(), 300);
            }
            
            // Показываем сообщение
            if (typeof toast === 'function') {
                toast('Рецепт убран из сохраненных', 'info');
            }
            
            // Если карточек больше нет, перезагружаем страницу
            const remainingCards = document.querySelectorAll('.bg-white.rounded-2xl');
            if (remainingCards.length <= 1) {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            throw new Error(result.error || 'Ошибка');
        }
    } catch(e) {
        if (typeof toast === 'function') {
            toast('Не удалось убрать рецепт', 'error');
        } else {
            alert('Не удалось убрать рецепт');
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>