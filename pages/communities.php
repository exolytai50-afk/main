<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/communities');
if ($meta) {
    apply_meta($meta);
}

$pageTitle = $GLOBALS['pageTitle'] ?? 'Кулинарные сообщества | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Сообщества';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Кулинарные сообщества GigaChef. Общайтесь с единомышленниками, делитесь рецептами и опытом.';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';

$communities = db_all('SELECT * FROM communities ORDER BY members_count DESC LIMIT 50');
$currentUserEmail = is_logged_in() ? current_user()['email'] : '';

$defaultCommunityCover = '/uploads/static/static-communities.jpg';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    
    <!-- H1 по центру -->
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 text-center inline-flex items-center justify-center gap-2 w-full" data-aos="fade-up">
        <?= icon('communities') ?>
        <span><?= e($pageH1) ?></span>
    </h1>
    
    <!-- Кнопка создания ниже справа -->
    <?php if (is_logged_in()): ?>
    <div class="flex justify-end mb-6">
        <button onclick="openCreateModal()"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold text-sm hover:bg-amber-200 transition">
            <?= icon('plus') ?>
            <span>Создать сообщество</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($communities)): ?>
        <div class="text-center py-20">
            <div class="mb-4 flex justify-center">
                <span class="gc-icon-xl" style="display:inline-flex;width:4em;height:4em">
                    <?= icon('communities', 'gc-icon-xl', 4) ?>
                </span>
            </div>
            <p class="text-gray-400 text-lg">Сообщества пока не созданы</p>
            <?php if (is_logged_in()): ?>
                <p class="text-gray-400 text-sm mt-2">Будьте первым — создайте своё сообщество!</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($communities as $c): 
                $isOwner = $currentUserEmail === ($c['author_email'] ?? '');
                $coverSrc = !empty($c['cover_image']) ? $c['cover_image'] : $defaultCommunityCover;
            ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition" data-aos="fade-up">
                <!-- Cover -->
                <a href="<?= url('community/' . $c['slug']) ?>" class="block h-36 bg-gradient-to-br from-amber-100 to-orange-100 relative overflow-hidden">
                    <img src="<?= e($coverSrc) ?>" class="w-full h-full object-cover" alt="<?= e($c['name']) ?>" loading="lazy">
                    
                    <?php if (!empty($c['category'])): ?>
                    <span class="absolute top-3 left-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-white/80 backdrop-blur-sm text-gray-700">
                        <?= e($c['category']) ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <!-- Контент -->
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <a href="<?= url('community/' . $c['slug']) ?>" class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-800 text-lg truncate hover:text-amber-600 transition">
                                <?= e($c['name']) ?>
                            </h3>
                        </a>
                        
                        <?php if ($isOwner): ?>
                        <button onclick="openEditModal(<?= (int)$c['id'] ?>, '<?= e($c['name']) ?>', '<?= e($c['description'] ?? '') ?>', '<?= e($c['cover_image'] ?? '') ?>', '<?= e($c['category'] ?? '') ?>')" 
                                class="shrink-0 w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition" 
                                title="Редактировать" aria-label="Редактировать сообщество">
                            <?= icon('pen') ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= e($c['description'] ?? '') ?></p>
                    
                    <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
                        <span class="inline-flex items-center gap-1">
                            <?= icon('friends') ?>
                            <span><?= (int)$c['members_count'] ?> участников</span>
                        </span>
                        <?php if (!empty($c['slug'])): ?>
                            <span class="inline-flex items-center gap-1 truncate">
                                <?= icon('pin') ?>
                                <span class="truncate"><?= e($c['slug']) ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Модальное окно создания/редактирования сообщества -->
<?php if (is_logged_in()): ?>
<div id="communityModal" style="display:none" class="fixed inset-0 z-[100] bg-black/50 flex items-center justify-center p-4" 
     onclick="if(event.target===this)closeModal()">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <h3 class="font-bold text-gray-800 text-lg mb-4" id="modalTitle">Создать сообщество</h3>
        
        <form id="communityForm" onsubmit="saveCommunity(event)" class="space-y-3">
            <input type="hidden" name="community_id" value="">
            
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Название *</label>
                <input type="text" name="name" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Описание</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm resize-none"></textarea>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Категория</label>
                <select name="category" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    <option value="">Общее</option>
                    <option value="Кулинария">Кулинария</option>
                    <option value="Выпечка">Выпечка</option>
                    <option value="Здоровое питание">Здоровое питание</option>
                    <option value="Вегетарианство">Вегетарианство</option>
                    <option value="Десерты">Десерты</option>
                    <option value="Напитки">Напитки</option>
                    <option value="Национальные кухни">Национальные кухни</option>
                </select>
            </div>
            
            <!-- Загрузка cover-изображения -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2">Cover-изображение</label>
                <label class="block border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-amber-300 transition">
                    <input type="file" name="cover_image" accept="image/*" class="hidden" onchange="previewCoverImage(this)">
                    <div class="text-gray-400 flex flex-col items-center">
                        <div class="mb-1 flex justify-center"><?= icon('image') ?></div>
                        <div class="text-sm">Загрузить обложку</div>
                        <div class="text-xs mt-0.5">JPG, PNG, WebP · до 5 МБ</div>
                    </div>
                </label>

                <!-- Превью новой загруженной обложки -->
                <div id="coverPreview" style="display:none" class="mt-2 relative">
                    <img id="coverPreviewImg" src="" class="w-full h-32 object-cover rounded-xl border" alt="Превью обложки">
                    <button type="button" onclick="removeCoverImage()" 
                            class="absolute top-2 right-2 bg-white/90 rounded-full p-1 text-rose-500 hover:bg-rose-50 transition inline-flex items-center justify-center w-7 h-7"
                            aria-label="Убрать обложку">
                        <?= icon('close') ?>
                    </button>
                </div>

                <!-- Текущая обложка (при редактировании) -->
                <div id="currentCover" style="display:none" class="mt-2 relative">
                    <img id="currentCoverImg" src="" class="w-full h-32 object-cover rounded-xl border" alt="Текущая обложка">
                    <button type="button" onclick="removeCurrentCover()" 
                            class="absolute top-2 right-2 bg-white/90 rounded-full px-2 py-1 text-rose-500 text-xs hover:bg-rose-50 transition inline-flex items-center gap-1"
                            aria-label="Удалить текущую обложку">
                        <?= icon('trash') ?>
                        <span>Удалить</span>
                    </button>
                </div>

                <!-- Подсказка о стоковой обложке -->
                <p id="defaultCoverHint" class="text-xs text-gray-400 mt-2">
                    Если изображение не добавлено, будет использована стандартная обложка.
                </p>
            </div>
            
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" onclick="closeModal()"
                        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm">
                    Отмена
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg bg-amber-300 text-amber-900 font-bold text-sm inline-flex items-center gap-1">
                    <?= icon('check') ?>
                    <span>Сохранить</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const DEFAULT_COMMUNITY_COVER = '/uploads/static/static-communities.jpg';

let coverImageFile = null;
let coverImageRemoved = false;

function openCreateModal() {
    coverImageFile = null;
    coverImageRemoved = false;
    document.getElementById('modalTitle').textContent = 'Создать сообщество';
    document.getElementById('communityForm').reset();
    document.getElementById('communityForm').community_id.value = '';
    document.getElementById('coverPreview').style.display = 'none';
    document.getElementById('currentCover').style.display = 'none';
    document.getElementById('communityModal').style.display = 'flex';
}

function openEditModal(id, name, description, coverImage, category) {
    coverImageFile = null;
    coverImageRemoved = false;
    document.getElementById('modalTitle').textContent = 'Редактировать сообщество';
    const form = document.getElementById('communityForm');
    form.community_id.value = id;
    form.name.value = name;
    form.description.value = description;
    form.category.value = category;
    
    document.getElementById('coverPreview').style.display = 'none';
    
    // Показываем текущую обложку (или стоковую), если она есть
    const src = coverImage && coverImage.length > 0 ? coverImage : DEFAULT_COMMUNITY_COVER;
    document.getElementById('currentCoverImg').src = src;
    document.getElementById('currentCover').style.display = 'block';
    
    document.getElementById('communityModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('communityModal').style.display = 'none';
}

function previewCoverImage(input) {
    const file = input.files[0];
    if (!file) return;
    
    if (file.size > 5 * 1024 * 1024) {
        alert('Файл больше 5 МБ');
        input.value = '';
        return;
    }
    
    coverImageFile = file;
    coverImageRemoved = false;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('coverPreviewImg').src = e.target.result;
        document.getElementById('coverPreview').style.display = 'block';
        document.getElementById('currentCover').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function removeCoverImage() {
    coverImageFile = null;
    document.getElementById('coverPreview').style.display = 'none';
    document.getElementById('communityForm').querySelector('input[name="cover_image"]').value = '';
}

function removeCurrentCover() {
    coverImageRemoved = true;
    // Показываем стоковую обложку вместо текущей
    document.getElementById('currentCoverImg').src = DEFAULT_COMMUNITY_COVER;
    // Скрывать блок не нужно — пусть пользователь видит, что будет использована стоковая
}

async function saveCommunity(e) {
    e.preventDefault();
    const form = e.target;
    
    const formData = new FormData();
    formData.append('name', form.name.value);
    formData.append('description', form.description.value);
    formData.append('category', form.category.value);
    
    if (form.community_id.value) {
        formData.append('community_id', form.community_id.value);
        formData.append('cover_removed', coverImageRemoved ? '1' : '0');
    }
    
    if (coverImageFile) {
        formData.append('cover_image', coverImageFile);
    }
    
    try {
        const res = await fetch('/api/communities.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        
        if (result.ok) {
            window.location.href = '/community/' + result.slug;
        } else {
            alert(result.error || 'Ошибка');
        }
    } catch (e) {
        alert('Ошибка соединения');
    }
}
</script>

<style>
/* Выравнивание SVG-иконок */
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
    display: inline-block;
    vertical-align: -0.125em;
    flex-shrink: 0;
}
</style>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>