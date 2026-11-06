<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

$pageTitle = 'Управление смайлами';
$success = '';
$error = '';

$baseEmojiDir = ROOT_DIR . '/uploads/emoji';
if (!is_dir($baseEmojiDir)) {
    @mkdir($baseEmojiDir, 0777, true);
    @chmod($baseEmojiDir, 0777);
}

try {
    db_exec("CREATE TABLE IF NOT EXISTS `emoji_packs` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `slug` varchar(255) NOT NULL,
        `description` text,
        `sort_order` int DEFAULT 0,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    db_exec("CREATE TABLE IF NOT EXISTS `emoji_images` (
        `id` int NOT NULL AUTO_INCREMENT,
        `pack_id` int NOT NULL,
        `name` varchar(255) DEFAULT NULL,
        `code` varchar(100) NOT NULL,
        `image_url` varchar(500) NOT NULL,
        `sort_order` int DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_pack` (`pack_id`),
        FOREIGN KEY (`pack_id`) REFERENCES `emoji_packs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Функция загрузки смайлов
function uploadEmojisToPack($packId, $packSlug, $startOrder = 0) {
    $uploadDir = ROOT_DIR . '/uploads/emoji/' . $packSlug;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }
    
    $uploadedCount = 0;
    $sortOrder = $startOrder;
    
    if (isset($_FILES['emojis']) && is_array($_FILES['emojis']['name'])) {
        $files = $_FILES['emojis'];
        $totalFiles = count($files['name']);
        
        foreach ($files['name'] as $index => $fileName) {
            if (empty($fileName)) continue;
            if ($files['error'][$index] !== UPLOAD_ERR_OK) continue;
            
            $fileTmp = $files['tmp_name'][$index];
            $fileSize = $files['size'][$index];
            
            if ($fileSize <= 0 || $fileSize > 2 * 1024 * 1024) continue;
            if (!file_exists($fileTmp)) continue;
            
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!$extension) $extension = 'png';
            
            $allowedExtensions = ['png', 'webp', 'svg', 'gif', 'jpg', 'jpeg'];
            if (!in_array($extension, $allowedExtensions)) continue;
            
            $newFileName = 'emoji_' . $sortOrder . '_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadPath = $uploadDir . '/' . $newFileName;
            
            $saved = @move_uploaded_file($fileTmp, $uploadPath);
            if (!$saved) {
                $fileContent = @file_get_contents($fileTmp);
                if ($fileContent !== false) {
                    $saved = @file_put_contents($uploadPath, $fileContent) !== false;
                }
            }
            if (!$saved) {
                $saved = @copy($fileTmp, $uploadPath);
            }
            
            if ($saved) {
                @chmod($uploadPath, 0644);
                $imageUrl = '/uploads/emoji/' . $packSlug . '/' . $newFileName;
                
                // Генерируем код смайла из имени файла
                $code = ':' . preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($fileName, PATHINFO_FILENAME)) . ':';
                
                db_insert(
                    'INSERT INTO emoji_images (pack_id, name, code, image_url, sort_order) VALUES (?, ?, ?, ?, ?)',
                    [$packId, $fileName, $code, $imageUrl, $sortOrder]
                );
                $sortOrder++;
                $uploadedCount++;
            }
        }
    }
    
    return $uploadedCount;
}

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_pack') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (mb_strlen($name) < 2) {
            $error = 'Название должно быть не короче 2 символов';
        } else {
            $slug = generateSlug($name);
            
            $existing = db_one('SELECT id FROM emoji_packs WHERE slug = ?', [$slug]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
            
            // Находим максимальный sort_order, чтобы новый пак был последним
            $maxSort = db_one('SELECT MAX(sort_order) as max_order FROM emoji_packs');
            $newSortOrder = (int)($maxSort['max_order'] ?? -1) + 1;

            $packId = db_insert(
                'INSERT INTO emoji_packs (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, 1)',
                [$name, $slug, $description, $newSortOrder]
            );
            
            $uploadedCount = uploadEmojisToPack($packId, $slug, 0);
            
            if ($uploadedCount >= 20) {
                $success = 'Пакет смайлов «' . $name . '» добавлен (смайлов: ' . $uploadedCount . '). Для добавления ещё смайлов используйте форму ниже.';
            } else {
                $success = 'Пакет смайлов «' . $name . '» добавлен (смайлов: ' . $uploadedCount . ')';
            }
        }
    }
    
    elseif ($action === 'add_emojis') {
        $packId = (int)($_POST['pack_id'] ?? 0);
        
        if ($packId <= 0) {
            $error = 'Не указан пакет смайлов';
        } else {
            $pack = db_one('SELECT * FROM emoji_packs WHERE id = ?', [$packId]);
            if (!$pack) {
                $error = 'Пакет смайлов не найден';
            } else {
                $maxOrder = db_one('SELECT MAX(sort_order) as max_order FROM emoji_images WHERE pack_id = ?', [$packId]);
                $startOrder = (int)($maxOrder['max_order'] ?? -1) + 1;
                
                $uploadedCount = uploadEmojisToPack($packId, $pack['slug'], $startOrder);
                
                if ($uploadedCount >= 20) {
                    $success = 'Добавлено смайлов: ' . $uploadedCount . '. Можно добавить ещё партию.';
                } else {
                    $success = 'Добавлено смайлов: ' . $uploadedCount;
                }
            }
        }
    }
    
    elseif ($action === 'delete_emoji') {
        $emojiId = (int)($_POST['emoji_id'] ?? 0);
        if ($emojiId > 0) {
            $emoji = db_one('SELECT * FROM emoji_images WHERE id = ?', [$emojiId]);
            if ($emoji) {
                $filePath = ROOT_DIR . $emoji['image_url'];
                if (file_exists($filePath)) @unlink($filePath);
                db_exec('DELETE FROM emoji_images WHERE id = ?', [$emojiId]);
                $success = 'Смайл удалён';
            }
        }
    }
    
    elseif ($action === 'delete_pack') {
        $packId = (int)($_POST['pack_id'] ?? 0);
        if ($packId > 0) {
            $pack = db_one('SELECT * FROM emoji_packs WHERE id = ?', [$packId]);
            if ($pack) {
                $emojis = db_all('SELECT * FROM emoji_images WHERE pack_id = ?', [$packId]);
                foreach ($emojis as $emoji) {
                    $filePath = ROOT_DIR . $emoji['image_url'];
                    if (file_exists($filePath)) @unlink($filePath);
                }
                db_exec('DELETE FROM emoji_images WHERE pack_id = ?', [$packId]);
                db_exec('DELETE FROM emoji_packs WHERE id = ?', [$packId]);
                $success = 'Пакет смайлов удалён';
            }
        }
    }
    
    elseif ($action === 'toggle_pack') {
        $packId = (int)($_POST['pack_id'] ?? 0);
        if ($packId > 0) {
            db_exec('UPDATE emoji_packs SET is_active = NOT is_active WHERE id = ?', [$packId]);
            $success = 'Статус обновлён';
        }
    }
    
    elseif ($action === 'update_emoji_code') {
        $emojiId = (int)($_POST['emoji_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        
        if ($emojiId > 0 && !empty($code)) {
            // Проверяем, что код начинается с : и заканчивается на :
            if (!preg_match('/^:[a-zA-Z0-9_]+:$/', $code)) {
                $error = 'Код должен быть в формате :smile:';
            } else {
                db_exec('UPDATE emoji_images SET code = ? WHERE id = ?', [$code, $emojiId]);
                $success = 'Код смайла обновлён';
            }
        }
    }
}

// Получаем пакеты смайлов
$emojiPacks = db_all(
    "SELECT ep.*, 
            (SELECT COUNT(*) FROM emoji_images ei WHERE ei.pack_id = ep.id) as emojis_count,
            (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC LIMIT 1) as first_emoji
     FROM emoji_packs ep 
     ORDER BY ep.sort_order ASC, ep.id DESC"
);

$allEmojis = db_all(
    "SELECT ei.*, ep.name as pack_name
     FROM emoji_images ei
     JOIN emoji_packs ep ON ep.id = ei.pack_id
     ORDER BY ep.id, ei.sort_order ASC"
);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.drop-zone {
    transition: all 0.3s ease;
    position: relative;
    min-height: 100px;
}
.drop-zone.drag-over {
    background-color: #fef3c7;
    border-color: #f59e0b;
    transform: scale(1.02);
}
.drop-zone.drag-over::after {
    content: 'Отпустите файлы для загрузки';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9);
    color: #f59e0b;
    font-weight: bold;
    font-size: 1.1rem;
    border-radius: 12px;
    pointer-events: none;
    z-index: 10;
}
.drop-zone-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
.drop-zone-preview img {
    width: 60px;
    height: 60px;
    object-fit: contain;
    border-radius: 8px;
    border: 2px solid #e5e7eb;
    background: #f9fafb;
    padding: 4px;
}
.file-item {
    position: relative;
    display: inline-block;
}
.file-item .remove-file {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ef4444;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
}
.file-item:hover .remove-file {
    opacity: 1;
}

/* Сетка смайлов */
.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: 12px;
}
@media (max-width: 1024px) { .emoji-grid { grid-template-columns: repeat(6, 1fr); } }
@media (max-width: 768px) { .emoji-grid { grid-template-columns: repeat(4, 1fr); } }

.emoji-item {
    position: relative;
    background: #f9fafb;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    aspect-ratio: 1;
}
.emoji-item img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.emoji-item .delete-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #ef4444;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
    font-size: 14px;
}
.emoji-item:hover .delete-btn {
    opacity: 1;
}
</style>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('emoji', 'happy', 'Смайлы', 'PNG-смайлы и пакеты'); ?>
    <?php admin_flash($success, $error); ?>

    <!-- Форма добавления пакета смайлов -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 inline-flex items-center gap-2">
            <?= icon('plus', 'gc-icon-sm') ?> Добавить пакет смайлов
        </h2>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4" id="addPackForm">
            <input type="hidden" name="action" value="add_pack">
            
            <div>
                <label class="<?= admin_cls('label') ?>">Название *</label>
                <input type="text" name="name" required class="<?= admin_cls('input') ?>" placeholder="Например: Смайлы">
            </div>
            
            <div>
                <label class="<?= admin_cls('label') ?>">Описание</label>
                <input type="text" name="description" class="<?= admin_cls('input') ?>" placeholder="Описание пакета">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-2">Смайлы (PNG, максимум 20 за раз)</label>
                
                <!-- Drag & Drop зона -->
                <div class="drop-zone border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-amber-300"
                     id="dropZoneMain"
                     onclick="document.getElementById('fileInputMain').click()">
                    <input type="file" name="emojis[]" id="fileInputMain" multiple accept=".png,.webp,.svg,.gif,.jpg,.jpeg" class="hidden">
                    <div class="text-gray-400">
                        <div class="mb-2 flex justify-center text-4xl"><?= icon('folder', 'gc-icon-2xl') ?></div>
                        <p class="text-sm font-medium">Перетащите файлы сюда или кликните для выбора</p>
                        <p class="text-xs mt-1">PNG, WebP, SVG, GIF, JPG, JPEG (макс. 2MB каждый)</p>
                    </div>
                </div>
                
                <!-- Предпросмотр выбранных файлов -->
                <div class="drop-zone-preview" id="filePreviewMain"></div>
            </div>
            
            <button type="submit" class="<?= admin_cls('btn btn-primary') ?> w-full py-3">
                <?= icon('diskette', 'gc-icon-sm') ?> Создать пакет смайлов
            </button>
        </form>
    </div>

    <!-- Список пакетов -->
    <div class="space-y-6">
        <?php foreach ($emojiPacks as $pack): ?>
        <div class="bg-white rounded-2xl shadow-md p-6" x-data="{ showAll: false }">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="font-bold text-gray-800"><?= e($pack['name']) ?></h3>
                    <p class="text-xs text-gray-400"><?= e($pack['slug']) ?> · <?= (int)$pack['emojis_count'] ?> смайлов</p>
                </div>
                <div class="flex gap-2">
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_pack">
                        <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
                        <button class="px-3 py-1.5 rounded-lg text-xs <?= $pack['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?> inline-flex items-center gap-1">
                            <?= $pack['is_active'] ? icon('check', 'gc-icon-xs') . ' Активен' : 'Неактивен' ?>
                        </button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Удалить пакет смайлов?')">
                        <input type="hidden" name="action" value="delete_pack">
                        <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
                        <button class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-500 text-xs inline-flex items-center justify-center">
                            <?= icon('trash', 'gc-icon-sm') ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Смайлы (показываем 2 ряда, остальные скрываем) -->
            <div class="emoji-grid mb-2" 
                 x-show="showAll" 
                 x-transition>
                <?php foreach ($allEmojis as $emoji): ?>
                    <?php if ($emoji['pack_id'] == $pack['id']): ?>
                    <div class="emoji-item group">
                        <img src="<?= e($emoji['image_url']) ?>" alt="<?= e($emoji['name']) ?>">
                        
                        <!-- Кнопка удаления -->
                        <form method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                            <input type="hidden" name="action" value="delete_emoji">
                            <input type="hidden" name="emoji_id" value="<?= (int)$emoji['id'] ?>">
                            <button class="w-6 h-6 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center" title="Удалить">
                                <?= icon('close', 'gc-icon-xs') ?>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Первые 2 ряда (16 штук) -->
            <div class="emoji-grid mb-4" 
                 x-show="!showAll" 
                 x-transition>
                <?php $count = 0; foreach ($allEmojis as $emoji): ?>
                    <?php if ($emoji['pack_id'] == $pack['id'] && $count < 16): $count++; ?>
                    <div class="emoji-item group">
                        <img src="<?= e($emoji['image_url']) ?>" alt="<?= e($emoji['name']) ?>">
                        
                        <!-- Кнопка удаления -->
                        <form method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                            <input type="hidden" name="action" value="delete_emoji">
                            <input type="hidden" name="emoji_id" value="<?= (int)$emoji['id'] ?>">
                            <button class="w-6 h-6 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center" title="Удалить">
                                <?= icon('close', 'gc-icon-xs') ?>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Кнопка "Показать ещё" -->
            <?php if ((int)$pack['emojis_count'] > 16): ?>
                <button @click="showAll = !showAll"
                        class="mb-4 text-sm text-violet-600 hover:text-violet-800 font-semibold inline-flex items-center gap-1.5">
                    <span x-show="!showAll" class="inline-flex items-center gap-1.5">
                        <?= icon('expand', 'gc-icon-sm') ?>
                        <span>Показать ещё (<?= (int)$pack['emojis_count'] - 16 ?>)</span>
                    </span>
                    <span x-show="showAll" style="display:none" class="inline-flex items-center gap-1.5">
                        <?= icon('minimize', 'gc-icon-sm') ?>
                        <span>Свернуть</span>
                    </span>
                </button>
            <?php endif; ?>
            
            <!-- Добавить ещё смайлы -->
            <form method="POST" enctype="multipart/form-data" class="border-t pt-4" id="addEmojisForm_<?= (int)$pack['id'] ?>">
                <input type="hidden" name="action" value="add_emojis">
                <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
                
                <!-- Drag & Drop зона для пакета -->
                <div class="drop-zone border-2 border-dashed border-gray-200 rounded-xl p-3 text-center cursor-pointer hover:border-amber-300 transition"
                     id="dropZone_<?= (int)$pack['id'] ?>"
                     onclick="document.getElementById('fileInput_<?= (int)$pack['id'] ?>').click()">
                    <input type="file" name="emojis[]" id="fileInput_<?= (int)$pack['id'] ?>" multiple accept=".png,.webp,.svg,.gif,.jpg,.jpeg" class="hidden">
                    <span class="text-sm text-gray-400 inline-flex items-center justify-center gap-1">
                        <?= icon('plus', 'gc-icon-xs') ?> Перетащите смайлы сюда или кликните для выбора
                    </span>
                    <span class="file-count text-xs text-amber-600 font-semibold block mt-1"></span>
                </div>
                
                <!-- Предпросмотр -->
                <div class="drop-zone-preview" id="filePreview_<?= (int)$pack['id'] ?>"></div>
                
                <button type="submit" class="submit-btn mt-2 w-full py-2 rounded-xl bg-amber-400 text-amber-900 font-bold text-sm hover:bg-amber-500 transition inline-flex items-center justify-center gap-2" style="display:none;">
                    <?= icon('upload') ?> Загрузить выбранные смайлы
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
// Drag & Drop функциональность
document.addEventListener('DOMContentLoaded', function() {
    // Для основной зоны
    setupDragDrop('dropZoneMain', 'fileInputMain', 'filePreviewMain', 'addPackForm');
    
    // Для каждой зоны пакета
    document.querySelectorAll('.drop-zone[id^="dropZone_"]').forEach(function(zone) {
        const packId = zone.id.replace('dropZone_', '');
        setupDragDrop(
            zone.id,
            'fileInput_' + packId,
            'filePreview_' + packId,
            'addEmojisForm_' + packId
        );
    });
});

function setupDragDrop(zoneId, inputId, previewId, formId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const form = formId ? document.getElementById(formId) : null;
    
    if (!zone || !input || !preview) {
        console.error('Не найдены элементы:', { zoneId, inputId, previewId });
        return;
    }
    
    let selectedFiles = [];
    
    // Предотвращаем стандартное поведение браузера
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        zone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });
    
    // Подсветка зоны при перетаскивании
    ['dragenter', 'dragover'].forEach(eventName => {
        zone.addEventListener(eventName, function() {
            zone.classList.add('drag-over');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        zone.addEventListener(eventName, function() {
            zone.classList.remove('drag-over');
        }, false);
    });
    
    // Обработка drop
    zone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const droppedFiles = dt.files;
        
        if (droppedFiles.length > 0) {
            handleFiles(droppedFiles);
        }
    }, false);
    
    // Обработка выбора файлов через input
    input.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(fileList) {
        const validFiles = [];
        
        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];
            
            // Проверяем тип файла
            if (!file.type.startsWith('image/')) {
                alert('Файл ' + file.name + ' не является изображением');
                continue;
            }
            
            // Проверяем размер (макс 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('Файл ' + file.name + ' слишком большой (макс 2MB)');
                continue;
            }
            
            // Проверяем расширение
            const extension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['png', 'webp', 'svg', 'gif', 'jpg', 'jpeg'];
            if (!allowedExtensions.includes(extension)) {
                alert('Файл ' + file.name + ' имеет недопустимое расширение');
                continue;
            }
            
            // Проверяем, не добавлен ли уже этот файл
            const isDuplicate = selectedFiles.some(f => f.name === file.name && f.size === file.size);
            
            if (!isDuplicate) {
                validFiles.push(file);
            }
        }
        
        // Добавляем валидные файлы
        selectedFiles = selectedFiles.concat(validFiles);
        
        // Ограничиваем количество файлов (максимум 20)
        if (selectedFiles.length > 20) {
            alert('Максимум 20 файлов за раз');
            selectedFiles = selectedFiles.slice(0, 20);
        }
        
        // Обновляем input
        updateInputFiles();
        
        // Обновляем предпросмотр
        updatePreview();
        
        // Показываем кнопку отправки
        if (form) {
            const submitBtn = form.querySelector('.submit-btn');
            if (submitBtn) {
                submitBtn.style.display = selectedFiles.length > 0 ? 'flex' : 'none';
            }
            
            const fileCount = form.querySelector('.file-count');
            if (fileCount) {
                fileCount.textContent = selectedFiles.length > 0 ? selectedFiles.length + ' файлов выбрано' : '';
            }
        }
    }
    
    function updateInputFiles() {
        // Создаем новый DataTransfer и добавляем все файлы
        const dt = new DataTransfer();
        selectedFiles.forEach(file => {
            dt.items.add(file);
        });
        input.files = dt.files;
    }
    
    function updatePreview() {
        preview.innerHTML = '';
        
        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;
                img.title = file.name;
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-file';
                removeBtn.innerHTML = <?= json_encode(icon('close', 'gc-icon-xs'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                removeBtn.title = 'Удалить ' + file.name;
                removeBtn.type = 'button';
                removeBtn.onclick = function() {
                    removeFile(index);
                };
                
                fileItem.appendChild(img);
                fileItem.appendChild(removeBtn);
                preview.appendChild(fileItem);
            };
            
            reader.readAsDataURL(file);
        });
    }
    
    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateInputFiles();
        updatePreview();
        
        // Обновляем кнопку и счетчик
        if (form) {
            const submitBtn = form.querySelector('.submit-btn');
            if (submitBtn) {
                submitBtn.style.display = selectedFiles.length > 0 ? 'flex' : 'none';
            }
            
            const fileCount = form.querySelector('.file-count');
            if (fileCount) {
                fileCount.textContent = selectedFiles.length > 0 ? selectedFiles.length + ' файлов выбрано' : '';
            }
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>