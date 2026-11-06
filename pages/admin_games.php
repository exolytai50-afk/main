<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

$pageTitle = 'Управление играми';
$success = '';
$error = '';

// Список категорий
$gameCategories = [
    'cooking' => 'Готовка',
    'puzzle' => 'Головоломки',
    'arcade' => 'Аркады',
    'strategy' => 'Стратегии',
    'simulation' => 'Симуляторы',
    'management' => 'Управление',
    'time_management' => 'Тайм-менеджмент',
    'match3' => 'Три в ряд',
    'platformer' => 'Платформеры',
    'runner' => 'Раннеры',
    'shooter' => 'Шутеры',
    'racing' => 'Гонки',
    'sports' => 'Спортивные',
    'board' => 'Настольные',
    'card' => 'Карточные',
    'quiz' => 'Викторины',
    'educational' => 'Обучающие',
    'adventure' => 'Приключения',
    'rpg' => 'RPG',
    'action' => 'Экшн',
    'idle' => 'Кликеры',
    'multiplayer' => 'Мультиплеер',
    'kids' => 'Для детей',
    'casual' => 'Казуальные',
];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_game') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categories = $_POST['categories'] ?? [];
        $primaryCategory = !empty($categories) ? $categories[0] : 'casual';
        $iframeUrl = trim($_POST['iframe_url'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $rating = (float)($_POST['rating'] ?? 4.5);
        $players = trim($_POST['players'] ?? '1000+');
        
        $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE);
        
        if ($title && $iframeUrl) {
            db_exec(
                'INSERT INTO games (title, description, category, categories, iframe_url, image_url, icon, rating, players_count, rating_count, rating_sum) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$title, $description, $primaryCategory, $categoriesJson, $iframeUrl, $imageUrl, $icon, $rating, $players, 1, $rating]
            );
            $success = 'Игра добавлена';
        } else {
            $error = 'Заполните обязательные поля';
        }
    } elseif ($_POST['action'] === 'update_game') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categories = $_POST['categories'] ?? [];
        $primaryCategory = !empty($categories) ? $categories[0] : 'casual';
        $iframeUrl = trim($_POST['iframe_url'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $players = trim($_POST['players'] ?? '1000+');
        
        $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE);
        
        if ($id > 0) {
            db_exec(
                'UPDATE games SET title=?, description=?, category=?, categories=?, iframe_url=?, image_url=?, icon=?, players_count=? WHERE id=?',
                [$title, $description, $primaryCategory, $categoriesJson, $iframeUrl, $imageUrl, $icon, $players, $id]
            );
            $success = 'Игра обновлена';
        }
    } elseif ($_POST['action'] === 'delete_game') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_exec('DELETE FROM games WHERE id=?', [$id]);
            $success = 'Игра удалена';
        }
    } elseif ($_POST['action'] === 'toggle_game') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_exec('UPDATE games SET is_active = 1 - is_active WHERE id=?', [$id]);
            $success = 'Статус обновлен';
        }
    }
}

// Получаем все игры
$games = db_all('SELECT * FROM games ORDER BY sort_order, id DESC');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('games', 'games', 'Игры', 'Каталог игр, категории и видимость'); ?>
    <?php admin_flash($success, $error); ?>

    <!-- Форма добавления -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6" x-data="gameForm()">
        <button type="button" @click="showForm = !showForm" class="<?= admin_cls('btn btn-soft') ?> mb-4">
            <span x-show="!showForm" class="inline-flex items-center gap-2"><?= icon('plus', 'gc-icon-sm') ?> Добавить игру</span>
            <span x-show="showForm" style="display:none" class="inline-flex items-center gap-2"><?= icon('minimize', 'gc-icon-sm') ?> Скрыть форму</span>
        </button>
        
        <form method="POST" x-show="showForm" style="display:none" class="space-y-4">
            <input type="hidden" name="action" value="add_game">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="<?= admin_cls('label') ?>">Название игры *</label>
                    <input type="text" name="title" required class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Iframe URL *</label>
                    <input type="url" name="iframe_url" required placeholder="https://www.crazygames.com/embed/game-name" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">URL картинки</label>
                    <input type="url" name="image_url" placeholder="https://example.com/image.jpg" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Эмодзи (необязательно)</label>
                    <input type="text" name="icon" placeholder="Если пусто — иконка по умолчанию" maxlength="10" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Рейтинг</label>
                    <input type="number" name="rating" step="0.1" min="0" max="5" value="4.5" class="<?= admin_cls('input') ?>">
                </div>
                <div>
                    <label class="<?= admin_cls('label') ?>">Количество игроков</label>
                    <input type="text" name="players" placeholder="1000+" class="<?= admin_cls('input') ?>">
                </div>
                
                <!-- Категории (множественный выбор) -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Категории (выберите одну или несколько)</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($gameCategories as $value => $label): ?>
                        <label class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer hover:bg-violet-50 transition text-sm"
                               x-data="{ checked: false }"
                               :class="checked ? 'border-violet-300 bg-violet-50' : 'border-gray-200'">
                            <input type="checkbox" name="categories[]" value="<?= $value ?>" 
                                   class="hidden" 
                                   @change="checked = $event.target.checked">
                            <?= icon($value, 'gc-icon-sm') ?><span><?= e($label) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Первая выбранная категория будет основной</p>
                </div>
                
                <div class="md:col-span-2">
                    <label class="<?= admin_cls('label') ?>">Описание</label>
                    <textarea name="description" rows="3" class="<?= admin_cls('input') ?>"></textarea>
                </div>
            </div>
            <button type="submit" class="<?= admin_cls('btn btn-primary') ?>">
                <?= icon('plus', 'gc-icon-sm') ?> Добавить игру
            </button>
        </form>
    </div>

    <!-- Список игр -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2"><?= icon('list', 'gc-icon-sm') ?> Игры (<?= count($games) ?>)</div>
        
        <?php foreach ($games as $game): 
            $gameCats = !empty($game['categories']) ? json_decode($game['categories'], true) : [];
            if (!is_array($gameCats)) $gameCats = [$game['category']];
        ?>
        <div class="border-b border-gray-50 p-5" x-data="gameEditForm(<?= htmlspecialchars(json_encode([
            'id' => $game['id'],
            'title' => $game['title'],
            'description' => $game['description'] ?? '',
            'categories' => $gameCats,
            'iframe_url' => $game['iframe_url'],
            'image_url' => $game['image_url'] ?? '',
            'icon' => $game['icon'] ?? '',
            'players_count' => $game['players_count'] ?? '1000+',
        ]), ENT_QUOTES) ?>)">
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-xl overflow-hidden shrink-0 bg-gray-50 flex items-center justify-center">
                    <?php if (!empty($game['image_url'])): ?>
                        <img src="<?= e($game['image_url']) ?>" alt="<?= e($game['title']) ?>" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="text-3xl text-amber-500 items-center justify-center" style="display:none"><?= !empty($game['icon']) ? e($game['icon']) : icon('games', 'gc-icon-xl') ?></span>
                    <?php else: ?>
                        <span class="text-3xl text-amber-500 inline-flex items-center justify-center"><?= !empty($game['icon']) ? e($game['icon']) : icon('games', 'gc-icon-xl') ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-800"><?= e($game['title']) ?></h3>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <?php foreach ($gameCats as $cat): ?>
                            <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600 inline-flex items-center gap-1">
                                <?php if (isset($gameCategories[$cat])): ?><?= icon($cat, 'gc-icon-xs') ?><?php endif; ?>
                                <?= e($gameCategories[$cat] ?? $cat) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-x-3 gap-y-1 flex-wrap">
                        <span class="inline-flex items-center gap-1"><?= icon('star', 'gc-icon-xs') ?> <?= e((string)$game['rating']) ?> (<?= (int)($game['rating_count'] ?? 1) ?> оценок)</span>
                        <span class="inline-flex items-center gap-1"><?= icon('team', 'gc-icon-xs') ?> <?= e((string)$game['players_count']) ?></span>
                    </p>
                    <p class="text-sm text-gray-500 mt-1 clamp-2"><?= e($game['description'] ?? '') ?></p>
                    <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded-lg text-xs font-bold <?= $game['is_active'] ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-50 text-gray-400' ?>">
                        <?= $game['is_active'] ? icon('check', 'gc-icon-xs') . ' Активна' : icon('eye-off', 'gc-icon-xs') . ' Скрыта' ?>
                    </span>
                </div>
                
                <div class="flex gap-2 shrink-0">
                    <button type="button" @click="editing = !editing" class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Редактировать" aria-label="Редактировать">
                        <?= icon('edit', 'gc-icon-sm') ?>
                    </button>
                    <form method="POST" onsubmit="return confirm('Удалить игру?')">
                        <input type="hidden" name="action" value="delete_game">
                        <input type="hidden" name="id" value="<?= $game['id'] ?>">
                        <button type="submit" class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_game">
                        <input type="hidden" name="id" value="<?= $game['id'] ?>">
                        <button type="submit" class="<?= admin_cls('ibtn') ?> text-gray-500 hover:bg-gray-100"
                                title="<?= $game['is_active'] ? 'Скрыть' : 'Показать' ?>" aria-label="<?= $game['is_active'] ? 'Скрыть' : 'Показать' ?>">
                            <?= $game['is_active'] ? icon('eye', 'gc-icon-sm') : icon('eye-off', 'gc-icon-sm') ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Форма редактирования -->
            <form method="POST" x-show="editing" style="display:none" class="mt-4 space-y-3 bg-gray-50 rounded-xl p-4">
                <input type="hidden" name="action" value="update_game">
                <input type="hidden" name="id" value="<?= $game['id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="<?= admin_cls('label') ?>">Название</label>
                        <input type="text" name="title" x-model="form.title" class="<?= admin_cls('input') ?>">
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">Iframe URL</label>
                        <input type="url" name="iframe_url" x-model="form.iframe_url" class="<?= admin_cls('input') ?>">
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">URL картинки</label>
                        <input type="url" name="image_url" x-model="form.image_url" class="<?= admin_cls('input') ?>">
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">Эмодзи</label>
                        <input type="text" name="icon" x-model="form.icon" class="<?= admin_cls('input') ?>">
                    </div>
                    <div>
                        <label class="<?= admin_cls('label') ?>">Количество игроков</label>
                        <input type="text" name="players" x-model="form.players_count" class="<?= admin_cls('input') ?>">
                    </div>
                    
                    <!-- Категории (множественный выбор) -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Категории</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($gameCategories as $value => $label): ?>
                            <label class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer hover:bg-violet-50 transition text-sm"
                                   :class="form.categories.includes('<?= $value ?>') ? 'border-violet-300 bg-violet-50' : 'border-gray-200'">
                                <input type="checkbox" name="categories[]" value="<?= $value ?>" 
                                       class="hidden" 
                                       x-model="form.categories">
                                <?= icon($value, 'gc-icon-sm') ?><span><?= e($label) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="<?= admin_cls('label') ?>">Описание</label>
                        <textarea name="description" rows="2" x-model="form.description" class="<?= admin_cls('input') ?>"></textarea>
                    </div>
                </div>
                <button type="submit" class="<?= admin_cls('btn btn-success') ?>">
                    <?= icon('diskette', 'gc-icon-sm') ?> Сохранить
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($games)): ?>
            <div class="text-center py-12 text-gray-400">
                <div class="mb-3 flex justify-center text-gray-300"><?= icon('games', 'gc-icon-3xl') ?></div>
                <p>Игры не добавлены. Используйте форму выше.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('gameForm', () => ({
        showForm: false
    }));
    
    Alpine.data('gameEditForm', (initialData) => ({
        editing: false,
        form: {
            ...initialData,
            categories: initialData.categories || []
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>