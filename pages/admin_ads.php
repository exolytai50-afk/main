<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_once __DIR__ . '/../includes/ads.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ads'])) {
    if (isset($_POST['ads']) && is_array($_POST['ads'])) {
        foreach ($_POST['ads'] as $id => $data) {
            $isActive = isset($data['is_active']) ? 1 : 0;
            $code     = (string)($data['code'] ?? '');
            $minDur   = (int)($data['min_duration_ms'] ?? 10000);
            
            db_exec(
                "UPDATE ad_blocks SET code = ?, is_active = ?, min_duration_ms = ? WHERE id = ?",
                [$code, $isActive, $minDur, (int)$id]
            );
        }
    }
    $message = 'Настройки рекламы сохранены.';
}

$ads = db_all("SELECT b.*, p.title as provider_title 
               FROM ad_blocks b 
               LEFT JOIN ad_providers p ON p.id = b.provider_id 
               ORDER BY b.placement ASC, b.sort_order ASC");

$pageTitle = 'Управление рекламой';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?php admin_top('ads', 'notifications', 'Реклама', 'Рекламные блоки и места размещения'); ?>
    <?php admin_flash($message, ''); ?>

    <form method="POST" class="space-y-6 max-w-5xl mx-auto">
        <?php foreach ($ads as $ad): ?>
            <div class="bg-white rounded-2xl shadow-md p-6 border-l-4 <?= $ad['is_active'] ? 'border-l-emerald-500' : 'border-l-gray-300' ?>">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($ad['title']) ?></h2>
                        <p class="text-xs text-gray-400 mt-1">
                            Провайдер: <b class="text-violet-600"><?= htmlspecialchars($ad['provider_title'] ?: 'Не указан') ?></b> | 
                            Ключ: <code class="bg-gray-100 px-1 rounded"><?= htmlspecialchars($ad['placement']) ?></code> | 
                            Слот: <code class="bg-gray-100 px-1 rounded"><?= htmlspecialchars($ad['slot']) ?></code>
                        </p>
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                        <input type="checkbox" name="ads[<?= $ad['id'] ?>][is_active]" value="1" <?= $ad['is_active'] ? 'checked' : '' ?> class="w-5 h-5 rounded accent-emerald-500">
                        <span class="text-sm font-bold text-gray-700">Включено</span>
                    </label>
                </div>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 mb-1">Минимальное время показа (мс)</label>
                    <input type="number" name="ads[<?= $ad['id'] ?>][min_duration_ms]" value="<?= (int)$ad['min_duration_ms'] ?>" step="1000" min="1000" 
                           class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">HTML / JS Код блока</label>
                    <textarea name="ads[<?= $ad['id'] ?>][code]" rows="5" 
                              class="w-full px-3 py-2 rounded-lg border border-gray-200 font-mono text-sm"
                              placeholder="Вставьте HTML/JS код рекламы"><?= htmlspecialchars($ad['code'] ?? '') ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="flex justify-end">
            <button type="submit" name="save_ads" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-400 to-orange-400 text-white font-bold shadow-md hover:shadow-lg transition">
                Сохранить изменения
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>