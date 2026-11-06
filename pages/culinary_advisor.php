<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/advisor');
if ($meta) {
    apply_meta($meta);
}

// Устанавливаем заголовок страницы
$pageTitle = $GLOBALS['pageTitle'] ?? 'Кулинарный советник — онлайн помощь | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Кулинарный советник';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Задайте любой вопрос о готовке — AI подскажет. Онлайн кулинарный советник GigaChef.';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Выравнивание SVG-иконок */
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
    display: inline-block;
    vertical-align: -0.125em;
    flex-shrink: 0;
}
</style>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8" x-data="culinaryAdvisor()">
    <div data-aos="fade-up" class="text-center mb-6">
        <span class="inline-flex items-center justify-center gc-icon-xl" style="width:4em;height:4em">
            <?= icon('advisor', 'gc-icon-xl', 4) ?>
        </span>
        <h1 class="text-3xl font-extrabold text-gray-800 mt-2"><?= e($pageH1) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Задайте любой вопрос о готовке — AI подскажет.</p>
    </div>

    <div class="bg-white rounded-3xl shadow-md flex flex-col h-[70vh]">
        <!-- Сообщения -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4" x-ref="scroll">
            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'user'
                        ? 'bg-gradient-to-r from-amber-200 to-orange-200 text-amber-900'
                        : 'bg-gray-50 text-gray-700'"
                        class="max-w-[80%] px-4 py-2.5 rounded-2xl text-sm whitespace-pre-line"
                        x-html="renderMessage(m.text)"></div>
                </div>
            </template>
            <div x-show="loading" style="display:none" class="flex justify-start">
                <div class="bg-gray-50 text-gray-400 px-4 py-2.5 rounded-2xl text-sm inline-flex items-center gap-2">
                    <span>Печатает…</span>
                    <?= icon('hourglass') ?>
                </div>
            </div>
        </div>

        <!-- Быстрые вопросы -->
        <div class="px-4 pb-2 flex flex-wrap gap-2" x-show="messages.length <= 1">
            <template x-for="q in suggestions" :key="q">
                <button @click="input=q; send()" 
                        class="px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-xs hover:bg-amber-100 transition"
                        x-text="q"></button>
            </template>
        </div>

        <!-- Ввод -->
        <div class="border-t border-gray-100 p-3 flex gap-2 items-end">
            <textarea x-model="input" 
                      @keydown.enter.prevent="send()" 
                      placeholder="Спросите что-нибудь…"
                      rows="1"
                      @input="
                          let el = $event.target;
                          el.style.height = 'auto'; 
                          el.style.height = (el.scrollHeight) + 'px';
                      "
                      class="flex-1 px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-amber-300 resize-none overflow-hidden bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                      style="max-height: 150px; min-height: 46px;"
            ></textarea>
            <button @click="send()" :disabled="loading" 
                    class="px-5 py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center shrink-0"
                    aria-label="Отправить сообщение">
                <?= icon('send', '', '1.6') ?>
            </button>
        </div>
    </div>
</section>
<script>
// Безопасная передача иконок из PHP в JS
window.__SVG_CHEF__ = <?= json_encode(function_exists('icon') ? icon('chef',  'gc-icon-sm') : '') ?>;
window.__SVG_WARN__ = <?= json_encode(function_exists('icon') ? icon('warning') : '') ?>;
window.__SVG_SPARK__ = <?= json_encode(function_exists('icon') ? icon('ai') : '') ?>;
</script>
<script src="<?= url('assets/js/ai/culinary-advisor.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>