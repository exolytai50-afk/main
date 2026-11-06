<?php
/**
 * GigaChef — страница оформления подписки GigaChef Pro (с промокодами).
 * Изолированная страница (без общего header/footer).
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();

$email  = current_user()['email'];
$active = check_subscription();

$sub = db_one(
    "SELECT subscription_end_date, auto_renew, next_charge_date, plan
     FROM subscriptions
     WHERE user_email = ? AND status = 'active' AND subscription_end_date >= CURDATE()
     ORDER BY subscription_end_date DESC LIMIT 1",
    [$email]
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подписка GigaChef Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <style>body { font-family: 'Nunito', sans-serif; }</style>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-violet-50 flex items-center justify-center p-4">

<div class="max-w-3xl w-full"
     x-data="paymentPage(<?= $active ? 'true' : 'false' ?>, <?= (int)PLAN_MONTHLY_PRICE ?>, <?= (int)PLAN_YEARLY_PRICE ?>)">

    <a href="<?= url('') ?>" class="inline-flex items-center gap-2 text-amber-700 font-bold mb-6 hover:gap-3 transition-all">← На главную</a>

    <!-- ============ АКТИВНАЯ ПОДПИСКА ============ -->
    <template x-if="active">
        <div class="bg-white rounded-3xl shadow-xl p-10 text-center">
            <div class="text-6xl mb-4">✅</div>
            <h1 class="text-2xl font-extrabold text-gray-800">GigaChef Pro активен</h1>

            <?php if ($sub && $sub['subscription_end_date']): ?>
                <p class="text-gray-500 mt-2">
                    Действует до <span class="font-semibold text-gray-700"><?= date('d.m.Y', strtotime($sub['subscription_end_date'])) ?></span>
                </p>
            <?php endif; ?>

            <?php if ($sub && (int)$sub['auto_renew'] === 1): ?>
                <div class="mt-3 inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-50 text-violet-700 text-sm font-semibold">
                    🔄 Автопродление включено
                    <?php if (!empty($sub['next_charge_date'])): ?>
                        · следующее списание <?= date('d.m.Y', strtotime($sub['next_charge_date'])) ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mt-3 inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-gray-50 text-gray-500 text-sm font-semibold">
                    Автопродление отключено
                </div>
            <?php endif; ?>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= url('billing') ?>" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-300 to-orange-300 text-amber-900 font-bold hover:shadow-md transition">
                    ⚙️ Управлять подпиской
                </a>
                <a href="<?= url('') ?>" class="px-6 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition">
                    Начать готовить
                </a>
            </div>
        </div>
    </template>

    <!-- ============ ОФОРМЛЕНИЕ ПОДПИСКИ ============ -->
    <template x-if="!active">
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">

            <!-- Шапка -->
            <div class="bg-gradient-to-r from-violet-200 to-purple-200 p-8 text-center">
                <h1 class="text-3xl font-extrabold text-violet-900">GigaChef Pro ✨</h1>
                <p class="text-violet-800/80 mt-1">Безлимитный AI, генерация фото блюд, сканер калорий</p>
            </div>

            <!-- Тарифы -->
            <div class="p-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button @click="selectPlan('monthly')"
                        :class="plan === 'monthly' ? 'border-violet-400 ring-2 ring-violet-200' : 'border-gray-200'"
                        class="text-left rounded-2xl border-2 p-5 transition hover:border-violet-300">
                    <div class="font-bold text-gray-800">Месяц</div>
                    <div class="text-3xl font-extrabold text-violet-600 mt-1">
                        <?= number_format(PLAN_MONTHLY_PRICE, 0) ?>₽<span class="text-sm text-gray-400 font-semibold">/мес</span>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">Гибко, без обязательств</div>
                </button>

                <button @click="selectPlan('yearly')"
                        :class="plan === 'yearly' ? 'border-violet-400 ring-2 ring-violet-200' : 'border-gray-200'"
                        class="text-left rounded-2xl border-2 p-5 transition hover:border-violet-300 relative">
                    <span class="absolute top-3 right-3 px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold">Выгодно −17%</span>
                    <div class="font-bold text-gray-800">Год</div>
                    <div class="text-3xl font-extrabold text-violet-600 mt-1">
                        <?= number_format(PLAN_YEARLY_PRICE, 0) ?>₽<span class="text-sm text-gray-400 font-semibold">/год</span>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">≈ <?= number_format(PLAN_YEARLY_PRICE / 12, 0) ?>₽/мес</div>
                </button>
            </div>

            <div class="px-8 pb-8">
                <!-- Преимущества -->
                <ul class="text-sm text-gray-600 space-y-2 mb-6">
                    <li>✓ Безлимитная генерация рецептов</li>
                    <li>✓ AI-фото блюд (YandexART)</li>
                    <li>✓ Сканер калорий по фото</li>
                    <li>✓ Персональная AI-книга рецептов</li>
                    <li>✓ Приоритетная поддержка</li>
                </ul>

                <!-- Промокод -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-600 mb-1.5">Промокод</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="promo"
                               @input="promoApplied = false; promoError = ''"
                               placeholder="Например, WELCOME20"
                               class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-violet-300 uppercase text-sm tracking-wide">
                        <button @click="checkPromo()" :disabled="promoChecking || !promo.trim()"
                                class="px-4 py-2.5 rounded-xl bg-violet-100 text-violet-700 font-bold text-sm hover:bg-violet-200 transition disabled:opacity-50">
                            <span x-show="!promoChecking">Применить</span>
                            <span x-show="promoChecking" style="display:none">…</span>
                        </button>
                    </div>
                    <p x-show="promoError" style="display:none" class="text-rose-500 text-xs mt-1.5" x-text="promoError"></p>
                    <p x-show="promoApplied" style="display:none" class="text-emerald-600 text-xs mt-1.5">
                        ✓ Промокод применён: <span class="font-bold" x-text="promoLabel"></span>
                    </p>
                </div>

                <!-- Итоговая сумма -->
                <div class="bg-gray-50 rounded-2xl p-4 mb-5 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Стоимость</span>
                        <span x-text="money(basePrice())"></span>
                    </div>
                    <div x-show="promoApplied && discount > 0" style="display:none" class="flex justify-between text-emerald-600 mt-1">
                        <span>Скидка</span>
                        <span x-text="'−' + money(discount)"></span>
                    </div>
                    <div class="flex justify-between font-extrabold text-gray-800 text-base mt-2 pt-2 border-t border-gray-200">
                        <span>Итого</span>
                        <span x-text="money(finalPrice())"></span>
                    </div>
                </div>

                <!-- Согласие на автопродление -->
                <label class="flex items-start gap-2 mb-4 cursor-pointer select-none">
                    <input type="checkbox" x-model="autoRenew" class="mt-0.5 w-5 h-5 rounded accent-violet-500 shrink-0">
                    <span class="text-sm text-gray-600">
                        Включить <b>автопродление</b>. Спишем оплату по той же карте в конце периода.
                        Мы предупредим письмом за <?= RENEW_NOTIFY_DAYS ?> дня перед списанием.
                        Отменить можно в любой момент в разделе «Платежи».
                    </span>
                </label>

                <!-- Кнопка оплаты -->
                <button @click="pay()" :disabled="processing"
                        class="w-full py-4 rounded-xl bg-gradient-to-r from-violet-400 to-purple-400 text-white font-extrabold text-lg hover:shadow-lg transition disabled:opacity-60">
                    <span x-show="!processing">Оплатить <span x-text="money(finalPrice())"></span></span>
                    <span x-show="processing" style="display:none">Переходим к оплате…</span>
                </button>

                <!-- Правовые примечания -->
                <p class="text-xs text-gray-400 text-center mt-3">
                    Оплата через защищённый шлюз ЮKassa. Нажимая «Оплатить», вы соглашаетесь
                    с <a href="<?= url('terms') ?>" target="_blank" class="underline hover:text-gray-600">условиями</a><span x-show="autoRenew" style="display:none"> и рекуррентным платежом</span>.
                </p>
                <?php if (!PAYMENT_LIVE_MODE): ?>
                    <p class="text-xs text-amber-500 text-center mt-2">
                        ⚠️ Тестовый режим · карта: 5555 5555 5555 4444, любой срок/CVC
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </template>
</div>

<script src="<?= url('assets/js/api-client.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script>
function paymentPage(isActive, monthlyPrice, yearlyPrice) {
    return {
        active: isActive,
        prices: { monthly: monthlyPrice, yearly: yearlyPrice },
        plan: 'monthly',
        autoRenew: true,
        processing: false,

        // промокод
        promo: '',
        promoApplied: false,
        promoChecking: false,
        promoError: '',
        promoLabel: '',
        discount: 0,

        basePrice() { return this.prices[this.plan]; },
        finalPrice() {
            const base = this.basePrice();
            return this.promoApplied ? Math.max(1, base - this.discount) : base;
        },
        money(v) { return new Intl.NumberFormat('ru-RU').format(Math.round(v)) + '₽'; },

        selectPlan(p) {
            this.plan = p;
            // при смене тарифа перепроверяем промокод (скидка могла измениться)
            if (this.promoApplied) this.checkPromo();
        },

        async checkPromo() {
            if (!this.promo.trim()) return;
            this.promoChecking = true; this.promoError = ''; this.promoApplied = false;
            try {
                const r = await CookAPI.post('/api/promo_check.php', {
                    code: this.promo.trim(),
                    plan: this.plan
                });
                this.discount = r.discount;
                this.promoLabel = r.label;
                this.promoApplied = true;
                toast('Промокод применён', 'success');
            } catch (e) {
                this.promoError = e.message || 'Промокод недействителен';
                this.discount = 0;
            } finally {
                this.promoChecking = false;
            }
        },

        async pay() {
            this.processing = true;
            try {
                const res = await CookAPI.post('/api/payment_create.php', {
                    plan: this.plan,
                    auto_renew: this.autoRenew,
                    promo: this.promoApplied ? this.promo.trim() : ''
                });
                if (res.confirmation_url) {
                    location.href = res.confirmation_url;
                } else {
                    toast('Не удалось создать платёж', 'error');
                    this.processing = false;
                }
            } catch (e) {
                toast(e.message || 'Ошибка оплаты', 'error');
                this.processing = false;
            }
        }
    };
}
</script>
</body>
</html>
