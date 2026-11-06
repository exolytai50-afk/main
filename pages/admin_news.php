<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

$pageTitle = 'Управление новостями';
$success = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_news') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        
        // Обработка загрузки изображения
        if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['news_image'];
            
            // Проверяем тип файла
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'Недопустимый тип файла. Разрешены: JPEG, PNG, WebP, GIF';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Файл слишком большой (максимум 5MB)';
            } else {
                // Создаем папку, если её нет
                $uploadDir = ROOT_DIR . '/uploads/news';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Генерируем уникальное имя файла
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'news_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = '/uploads/news/' . $filename;
                
                // Перемещаем файл
                if (move_uploaded_file($file['tmp_name'], ROOT_DIR . $uploadPath)) {
                    $imageUrl = $uploadPath;
                } else {
                    $error = 'Ошибка при загрузке файла';
                }
            }
        }
        
        // Если нет ошибок - сохраняем новость
        if (empty($error)) {
            if ($title && $content) {
                db_exec(
                    'INSERT INTO news (title, content, image_url, author_email, is_pinned) VALUES (?,?,?,?,?)',
                    [$title, $content, $imageUrl, current_user()['email'], $isPinned]
                );
                $success = 'Новость добавлена';
            } else {
                $error = 'Заполните обязательные поля (заголовок и текст)';
            }
        }
    } elseif ($_POST['action'] === 'toggle_pin') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec('UPDATE news SET is_pinned = 1 - is_pinned WHERE id = ?', [$id]);
        $success = 'Статус обновлен';
    } elseif ($_POST['action'] === 'delete_news') {
        $id = (int)($_POST['id'] ?? 0);
        
        // Получаем информацию о новости для удаления изображения
        $news = db_one('SELECT image_url FROM news WHERE id = ?', [$id]);
        if ($news && !empty($news['image_url']) && str_starts_with($news['image_url'], '/uploads/news/')) {
            $filePath = ROOT_DIR . $news['image_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        db_exec('DELETE FROM news WHERE id = ?', [$id]);
        $success = 'Новость удалена';
    }
}

$allNews = db_all('SELECT * FROM news ORDER BY is_pinned DESC, created_at DESC');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('news', 'news', 'Новости', 'Публикации, закрепление и удаление'); ?>
    <?php admin_flash($success, $error); ?>
    <div class="max-w-4xl mx-auto">
    
    <!-- Форма добавления -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6" x-data="newsForm()">
        <button type="button" @click="showForm = !showForm" class="<?= admin_cls('btn btn-soft') ?> mb-4">
            <span x-show="!showForm" class="inline-flex items-center gap-2"><?= icon('plus', 'gc-icon-sm') ?> Добавить новость</span>
            <span x-show="showForm" style="display:none" class="inline-flex items-center gap-2"><?= icon('minimize', 'gc-icon-sm') ?> Скрыть форму</span>
        </button>
        
        <form method="post" x-show="showForm" style="display:none" class="space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_news">
            
            <div>
                <label class="<?= admin_cls('label') ?>">Заголовок *</label>
                <input type="text" name="title" required 
                       class="<?= admin_cls('input') ?>"
                       placeholder="Заголовок новости">
            </div>
            
            <div>
                <label class="<?= admin_cls('label') ?>">Текст новости *</label>
                <textarea name="content" rows="5" required 
                          class="<?= admin_cls('input') ?>"
                          placeholder="Текст новости..."></textarea>
            </div>
            
            <!-- Изображение: загрузка или URL -->
            <div>
                <label class="<?= admin_cls('label') ?>">Изображение</label>
                
                <!-- Превью загруженного изображения -->
                <div x-show="previewUrl" style="display:none" class="relative mb-3">
                    <img :src="previewUrl" class="w-full max-h-48 object-cover rounded-xl shadow-md" alt="Превью">
                    <button type="button" @click="previewUrl = ''; $refs.fileInput.value = ''; $refs.urlInput.value = ''" aria-label="Убрать изображение"
                            class="absolute top-2 right-2 bg-white/90 hover:bg-rose-50 rounded-full p-1.5 shadow-md transition text-rose-500 inline-flex">
                        <?= icon('close', 'gc-icon-sm') ?>
                    </button>
                </div>
                
                <!-- Загрузка файла -->
                <div class="mb-3">
                    <label class="flex items-center justify-center gap-2 w-full px-4 py-4 rounded-xl border-2 border-dashed border-gray-200 cursor-pointer hover:border-amber-300 transition text-sm text-gray-500">
                        <input type="file" name="news_image" accept="image/*" 
                               class="hidden" 
                               x-ref="fileInput"
                               @change="handleFileUpload($event)">
                        <?= icon('upload', 'gc-icon-md') ?>
                        <span>Загрузить изображение</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1">JPEG, PNG, WebP, GIF до 5MB. Сохраняется в /uploads/news/</p>
                </div>
                
                <!-- Или URL -->
                <div>
                    <div class="relative mb-2">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-xs">
                            <span class="bg-white px-3 text-gray-400">или</span>
                        </div>
                    </div>
                    
                    <label class="text-xs text-gray-500 mb-1 block">Ссылка на изображение</label>
                    <input type="url" name="image_url" 
                           x-ref="urlInput"
                           @input="previewUrl = $event.target.value"
                           placeholder="https://example.com/image.jpg"
                           class="<?= admin_cls('input') ?>">
                </div>
            </div>
            
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_pinned" class="w-5 h-5 rounded accent-amber-500">
                <span class="text-sm inline-flex items-center gap-1.5"><?= icon('pin', 'gc-icon-sm') ?> Закрепить вверху</span>
            </label>
            
            <button type="submit" class="<?= admin_cls('btn btn-primary') ?>">
                <?= icon('send', 'gc-icon-sm') ?> Опубликовать новость
            </button>
        </form>
    </div>
    
    <!-- Список новостей -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="px-5 py-4 border-b font-bold text-gray-800 flex items-center gap-2"><?= icon('list', 'gc-icon-sm') ?> Все новости (<?= count($allNews) ?>)</div>
        
        <?php if (empty($allNews)): ?>
            <div class="text-center py-12 text-gray-400">
                <div class="mb-3 flex justify-center text-gray-300"><?= icon('news', 'gc-icon-3xl') ?></div>
                <p>Новостей пока нет. Добавьте первую!</p>
            </div>
        <?php else: ?>
            <?php foreach ($allNews as $news): ?>
            <div class="border-b border-gray-50 p-4">
                <div class="flex items-center gap-4">
                    <?php if (!empty($news['image_url'])): ?>
                        <div class="w-20 h-16 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                            <img src="<?= e($news['image_url']) ?>" 
                                 onerror="this.src='/uploads/static/static.jpg'"
                                 class="w-full h-full object-cover" 
                                 alt="<?= e($news['title']) ?>">
                        </div>
                    <?php else: ?>
                        <div class="w-20 h-16 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center shrink-0"><?= icon('news', 'gc-icon-lg') ?></div>
                    <?php endif; ?>
                    
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-800 truncate flex items-center gap-1.5">
                            <?php if ($news['is_pinned']): ?><span class="text-amber-500 inline-flex shrink-0" title="Закреплено"><?= icon('pin', 'gc-icon-sm') ?></span><?php endif; ?>
                            <span class="truncate"><?= e($news['title']) ?></span>
                        </h3>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-x-3 gap-y-1 flex-wrap">
                            <span><?= date('d.m.Y H:i', strtotime($news['created_at'])) ?></span>
                            <span class="inline-flex items-center gap-1"><?= icon('eye', 'gc-icon-xs') ?> <?= (int)$news['views_count'] ?></span>
                            <span class="inline-flex items-center gap-1"><?= icon('star', 'gc-icon-xs') ?> <?= (int)$news['rating_count'] ?> оценок</span>
                        </p>
                    </div>
                    
                    <div class="flex gap-2 shrink-0">
                        <a href="<?= url('news/' . $news['id']) ?>" class="<?= admin_cls('ibtn ibtn-info') ?>" title="Просмотр" aria-label="Просмотр"><?= icon('eye', 'gc-icon-sm') ?></a>
                        <form method="post">
                            <input type="hidden" name="action" value="toggle_pin">
                            <input type="hidden" name="id" value="<?= $news['id'] ?>">
                            <button class="<?= admin_cls('ibtn ibtn-edit') ?>" title="Закрепить/открепить" aria-label="Закрепить/открепить"><?= icon('pin', 'gc-icon-sm') ?></button>
                        </form>
                        <form method="post" onsubmit="return confirm('Удалить новость?')">
                            <input type="hidden" name="action" value="delete_news">
                            <input type="hidden" name="id" value="<?= $news['id'] ?>">
                            <button class="<?= admin_cls('ibtn ibtn-del') ?>" title="Удалить" aria-label="Удалить"><?= icon('trash', 'gc-icon-sm') ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('newsForm', () => ({
        showForm: false,
        previewUrl: '',
        
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Проверяем размер
            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (максимум 5MB)');
                event.target.value = '';
                return;
            }
            
            // Проверяем тип
            if (!file.type.startsWith('image/')) {
                alert('Выберите изображение');
                event.target.value = '';
                return;
            }
            
            // Показываем превью
            const reader = new FileReader();
            reader.onload = (e) => {
                this.previewUrl = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>