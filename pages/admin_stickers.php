<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();

$pageTitle = 'Управление стикерпаками';
$success = '';
$error = '';

$baseStickersDir = ROOT_DIR . '/uploads/stickers';
if (!is_dir($baseStickersDir)) {
    @mkdir($baseStickersDir, 0777, true);
    @chmod($baseStickersDir, 0777);
}

try {
    db_exec("CREATE TABLE IF NOT EXISTS `sticker_packs` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `slug` varchar(255) NOT NULL,
        `description` text,
        `sort_order` int DEFAULT 0,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    
    db_exec("CREATE TABLE IF NOT EXISTS `stickers` (
        `id` int NOT NULL AUTO_INCREMENT,
        `pack_id` int NOT NULL,
        `name` varchar(255) DEFAULT NULL,
        `image_url` varchar(500) NOT NULL,
        `sort_order` int DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_pack` (`pack_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
} catch (Throwable $e) {}

// Функция загрузки стикеров
function uploadStickersToPack($packId, $packSlug, $startOrder = 0) {
    $uploadDir = ROOT_DIR . '/uploads/stickers/' . $packSlug;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }
    
    $uploadedCount = 0;
    $sortOrder = $startOrder;
    
    if (isset($_FILES['stickers']) && is_array($_FILES['stickers']['name'])) {
        $files = $_FILES['stickers'];
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
            
            $newFileName = 'sticker_' . $sortOrder . '_' . time() . '_' . uniqid() . '.' . $extension;
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
                $imageUrl = '/uploads/stickers/' . $packSlug . '/' . $newFileName;
                
                db_insert(
                    'INSERT INTO stickers (pack_id, name, image_url, sort_order) VALUES (?, ?, ?, ?)',
                    [$packId, $fileName, $imageUrl, $sortOrder]
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
            
            $existing = db_one('SELECT id FROM sticker_packs WHERE slug = ?', [$slug]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
            
            $packId = db_insert(
                'INSERT INTO sticker_packs (name, slug, description, is_active) VALUES (?, ?, ?, 1)',
                [$name, $slug, $description]
            );
            
            $uploadedCount = uploadStickersToPack($packId, $slug, 0);
            
            // Если загружено 20 файлов (максимум PHP), предупреждаем
            if ($uploadedCount >= 20) {
                $success = 'Стикерпак «' . $name . '» добавлен (стикеров: ' . $uploadedCount . '). Для добавления ещё стикеров используйте форму ниже.';
            } else {
                $success = 'Стикерпак «' . $name . '» добавлен (стикеров: ' . $uploadedCount . ')';
            }
        }
    }
    
    elseif ($action === 'add_stickers') {
        $packId = (int)($_POST['pack_id'] ?? 0);
        
        if ($packId <= 0) {
            $error = 'Не указан стикерпак';
        } else {
            $pack = db_one('SELECT * FROM sticker_packs WHERE id = ?', [$packId]);
            if (!$pack) {
                $error = 'Стикерпак не найден';
            } else {
                $maxOrder = db_one('SELECT MAX(sort_order) as max_order FROM stickers WHERE pack_id = ?', [$packId]);
                $startOrder = (int)($maxOrder['max_order'] ?? -1) + 1;
                
                $uploadedCount = uploadStickersToPack($packId, $pack['slug'], $startOrder);
                
                if ($uploadedCount >= 20) {
                    $success = 'Добавлено стикеров: ' . $uploadedCount . '. Можно добавить ещё партию.';
                } else {
                    $success = 'Добавлено стикеров: ' . $uploadedCount;
                }
            }
        }
    }
    
    elseif ($action === 'delete_sticker') {
        $stickerId = (int)($_POST['sticker_id'] ?? 0);
        if ($stickerId > 0) {
            $sticker = db_one('SELECT * FROM stickers WHERE id = ?', [$stickerId]);
            if ($sticker) {
                $filePath = ROOT_DIR . $sticker['image_url'];
                if (file_exists($filePath)) @unlink($filePath);
                db_exec('DELETE FROM stickers WHERE id = ?', [$stickerId]);
                $success = 'Стикер удалён';
            }
        }
    }
    
    elseif ($action === 'delete_pack') {
        $packId = (int)($_POST['pack_id'] ?? 0);
        if ($packId > 0) {
            $pack = db_one('SELECT * FROM sticker_packs WHERE id = ?', [$packId]);
            if ($pack) {
                $stickers = db_all('SELECT * FROM stickers WHERE pack_id = ?', [$packId]);
                foreach ($stickers as $sticker) {
                    $filePath = ROOT_DIR . $sticker['image_url'];
                    if (file_exists($filePath)) @unlink($filePath);
                }
                db_exec('DELETE FROM stickers WHERE pack_id = ?', [$packId]);
                db_exec('DELETE FROM sticker_packs WHERE id = ?', [$packId]);
                $success = 'Стикерпак удалён';
            }
        }
    }
    
    elseif ($action === 'toggle_pack') {
        $packId = (int)($_POST['pack_id'] ?? 0);
        if ($packId > 0) {
            db_exec('UPDATE sticker_packs SET is_active = NOT is_active WHERE id = ?', [$packId]);
            $success = 'Статус обновлён';
        }
    }
}

// Получаем стикерпаки
$stickerPacks = db_all(
    "SELECT sp.*, 
            (SELECT COUNT(*) FROM stickers s WHERE s.pack_id = sp.id) as stickers_count,
            (SELECT image_url FROM stickers s WHERE s.pack_id = sp.id ORDER BY s.sort_order ASC LIMIT 1) as first_sticker
     FROM sticker_packs sp 
     ORDER BY sp.sort_order ASC, sp.id DESC"
);

$allStickers = db_all(
    "SELECT s.*, sp.name as pack_name
     FROM stickers s
     JOIN sticker_packs sp ON sp.id = s.pack_id
     ORDER BY sp.id, s.sort_order ASC"
);

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('stickers', 'palette', 'Стикерпаки', 'Стикеры для чата'); ?>
    <?php admin_flash($success, $error); ?>

    <!-- Форма добавления стикерпака -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 inline-flex items-center gap-2"><?= icon('plus', 'gc-icon-sm') ?> Добавить стикерпак</h2>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="add_pack">
            
            <div>
                <label class="<?= admin_cls('label') ?>">Название *</label>
                <input type="text" name="name" required class="<?= admin_cls('input') ?>">
            </div>
            
            <div>
                <label class="<?= admin_cls('label') ?>">Описание</label>
                <input type="text" name="description" class="<?= admin_cls('input') ?>">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-2">Стикеры (максимум 20 за раз, можно добавлять ещё после создания)</label>
                <label class="block border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer">
                    <input type="file" name="stickers[]" multiple accept=".png,.webp,.svg,.gif,.jpg,.jpeg" class="hidden"
                           onchange="this.parentElement.querySelector('.file-text').textContent = this.files.length + ' файлов выбрано (макс. 20)'">
                    <span class="text-gray-400 inline-flex items-center justify-center gap-2"><?= icon('folder', 'gc-icon-md') ?><span class="file-text">Выберите файлы</span></span>
                </label>
            </div>
            
            <button type="submit" class="<?= admin_cls('btn btn-primary') ?> w-full py-3">
                <?= icon('plus', 'gc-icon-sm') ?> Создать стикерпак
            </button>
        </form>
    </div>

    <!-- Список паков -->
    <div class="space-y-6">
        <?php foreach ($stickerPacks as $pack): ?>
        <div class="bg-white rounded-2xl shadow-md p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="font-bold text-gray-800"><?= e($pack['name']) ?></h3>
                    <p class="text-xs text-gray-400"><?= e($pack['slug']) ?> · <?= (int)$pack['stickers_count'] ?> стикеров</p>
                </div>
                <div class="flex gap-2">
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_pack">
                        <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
                        <button class="px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1 <?= $pack['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $pack['is_active'] ? icon('check', 'gc-icon-xs') . ' Активен' : 'Неактивен' ?>
                        </button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Удалить стикерпак?')">
                        <input type="hidden" name="action" value="delete_pack">
                        <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
                        <button class="<?= admin_cls('ibtn ibtn-del') ?> !w-8 !h-8 bg-rose-50" title="Удалить пакет" aria-label="Удалить пакет"><?= icon('trash', 'gc-icon-sm') ?></button>
                    </form>
                </div>
            </div>
            
            <!-- Стикеры -->
            <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-3 mb-4">
                <?php foreach ($allStickers as $sticker): ?>
                    <?php if ($sticker['pack_id'] == $pack['id']): ?>
                    <div class="relative group">
                        <img src="<?= e($sticker['image_url']) ?>" class="w-full aspect-square object-contain bg-gray-50 rounded-xl border">
                        <form method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100">
                            <input type="hidden" name="action" value="delete_sticker">
                            <input type="hidden" name="sticker_id" value="<?= (int)$sticker['id'] ?>">
                            <button class="w-6 h-6 rounded-full bg-rose-500 text-white inline-flex items-center justify-center" title="Удалить" aria-label="Удалить стикер"><?= icon('close', 'gc-icon-xs') ?></button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Добавить ещё стикеры -->
<form method="POST" enctype="multipart/form-data" class="border-t pt-4" id="addStickersForm_<?= (int)$pack['id'] ?>">
    <input type="hidden" name="action" value="add_stickers">
    <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
    
    <label class="block border-2 border-dashed border-gray-200 rounded-xl p-3 text-center cursor-pointer hover:border-amber-300 transition">
        <input type="file" name="stickers[]" multiple accept=".png,.webp,.svg,.gif,.jpg,.jpeg" class="hidden"
               onchange="this.closest('form').querySelector('.file-count').textContent = this.files.length + ' файлов выбрано'; this.closest('form').querySelector('.submit-btn').style.display = 'flex';">
        <span class="text-sm text-gray-400 inline-flex items-center gap-1.5"><?= icon('plus', 'gc-icon-xs') ?> Выбрать стикеры</span>
        <span class="file-count text-xs text-amber-600 font-semibold"></span>
    </label>
    
    <button type="submit" class="submit-btn mt-2 w-full <?= admin_cls('btn btn-primary') ?>" style="display:none;">
        <?= icon('upload', 'gc-icon-sm') ?> Загрузить выбранные стикеры
    </button>
</form>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>