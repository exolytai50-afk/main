<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$pageTitle = $GLOBALS['pageTitle'] ?? 'Платежи и подписка | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Платежи и подписка';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Платежи и подписка | GigaChef';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8" x-data="billing()" x-init="load()">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 text-center" data-aos="fade-up"><?= icon('payment') ?> <?= e($pageH1) ?></h1>

    <!-- Активная подписка -->
    <template x-if="active">
        <div class="bg-white rounded-3xl shadow-md p-6 mb-6" data-aos="fade-up">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="text-emerald-600 font-extrabold text-lg">GigaChefAI Pro активен <?= icon('star') ?></div>
                    <div class="text-sm text-gray-500">Действует до <span class="font-semibold" x-text="fmt(active.subscription_end_date)"></span></div>
                    <div class="text-sm mt-1">
                        <span x-show="active.auto_renew==1" style="display:none" class="text-violet-600"><?= icon('recycle') ?> Автопродление · <span x-text="active.next_charge_date ? fmt(active.next_charge_date) : ''"></span></span>
                        <span x-show="active.auto_renew!=1" style="display:none" class="text-gray-400">Автопродление отключено</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <button @click="toggleAuto()" :disabled="busy"
                            :class="active.auto_renew==1 ? 'bg-gray-100 text-gray-600' : 'bg-violet-100 text-violet-700'"
                            class="px-4 py-2 rounded-xl text-sm font-bold transition disabled:opacity-60">
                        <span x-text="active.auto_renew==1 ? 'Отключить автопродление' : 'Включить автопродление'"></span>
                    </button>
                    <button @click="cancel()" :disabled="busy"
                            class="px-4 py-2 rounded-xl bg-rose-50 text-rose-500 text-sm font-bold hover:bg-rose-100 transition disabled:opacity-60">
                        Отменить подписку
                    </button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="!active">
        <div class="bg-white rounded-3xl shadow-md p-6 mb-6 text-center" data-aos="fade-up">
            <p class="text-gray-500">Активной подписки нет.</p>
            <a href="<?= url('payment') ?>" class="inline-block mt-3 px-6 py-2.5 rounded-xl bg-gradient-to-r from-violet-300 to-purple-300 text-violet-900 font-bold">Оформить GigaChefAI Pro</a>
        </div>
    </template>

    <!-- История -->
    <h2 class="text-xl font-extrabold text-gray-800 mb-3">История операций</h2>
    <div class="bg-white rounded-3xl shadow-md overflow-hidden" data-aos="fade-up" data-aos-delay="100">
        <template x-for="row in history" :key="row.id">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-50">
                <div class="min-w-0">
                    <div class="font-bold text-gray-800">
                        <span x-text="row.plan==='yearly' ? 'Годовая подписка' : 'Месячная подписка'"></span>
                    </div>
                    <div class="text-xs text-gray-400" x-text="fmtDateTime(row.created_at)"></div>
                    <div x-show="row.promo_code" style="display:none" class="text-xs text-emerald-500 mt-0.5"><?= icon('qr') ?> <span x-text="row.promo_code"></span></div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-extrabold" :class="statusColor(row.status)">
                        <span x-text="money(row.amount)"></span>
                    </div>
                    <div x-show="row.original_amount && +row.original_amount>+row.amount" style="display:none" class="text-xs text-gray-400 line-through" x-text="money(row.original_amount)"></div>
                    <div x-show="+row.refunded_amount>0" style="display:none" class="text-xs text-rose-400">возврат <span x-text="money(row.refunded_amount)"></span></div>
                    <div class="text-xs" :class="statusColor(row.status)" x-text="statusLabel(row.status)"></div>
                    <div class="flex gap-2 justify-end mt-1">
                        <a x-show="hasReceipt(row)" style="display:none" :href="'/api/receipt.php?id=' + row.id" class="text-xs text-amber-600 font-semibold hover:underline"><?= icon('invoice') ?> Квитанция</a>
                        <button x-show="canRefund(row)" style="display:none" @click="refund(row.id)" :disabled="busy"
                                class="text-xs text-rose-500 font-semibold hover:underline">Возврат</button>
                    </div>
                </div>
            </div>
        </template>
        <p x-show="!history.length" style="display:none" class="text-center text-gray-400 py-10">Операций пока нет</p>
    </div>
</section>

<script>
function billing() {
    return {
        history: [], active: null, refundDays: 14, busy: false,

        async load() {
            try {
                const r = await CookAPI.get('/api/billing_history.php');
                this.history = r.history || [];
                this.active = r.active || null;
                this.refundDays = r.refund_days || 14;
            } catch (e) { toast(e.message, 'error'); }
        },

        fmt(d) { return d ? new Date(d).toLocaleDateString('ru-RU') : '—'; },
        fmtDateTime(d) { return d ? new Date(d.replace(' ', 'T')).toLocaleString('ru-RU', {dateStyle:'short', timeStyle:'short'}) : ''; },
        money(v) { return new Intl.NumberFormat('ru-RU').format(Math.round(v)) + '₽'; },
        statusLabel(s) { return ({active:'Оплачено',expired:'Завершено',pending:'В обработке',canceled:'Отменено',refunded:'Возврат'})[s] || s; },
        statusColor(s) { return ({active:'text-emerald-600',expired:'text-gray-500',pending:'text-amber-500',canceled:'text-gray-400',refunded:'text-rose-500'})[s] || 'text-gray-600'; },

        hasReceipt(row) {
            return !!row.paid_at && ['active','expired','refunded'].includes(row.status);
        },
        canRefund(row) {
            if (row.status !== 'active' || !row.paid_at) return false;
            const days = (Date.now() - new Date(row.paid_at.replace(' ', 'T')).getTime()) / 86400000;
            return days <= this.refundDays && (+row.refunded_amount) < (+row.amount);
        },

        async toggleAuto() {
            this.busy = true;
            try {
                const enable = this.active.auto_renew != 1;
                const r = await CookAPI.post('/api/subscription_autorenew.php', { enabled: enable });
                this.active.auto_renew = r.auto_renew ? 1 : 0;
                toast(r.auto_renew ? 'Автопродление включено' : 'Автопродление отключено', 'success');
            } catch (e) { toast(e.message, 'error'); }
            finally { this.busy = false; }
        },
        async cancel() {
            if (!confirm('Отменить подписку? Доступ сохранится до конца оплаченного периода.')) return;
            this.busy = true;
            try {
                await CookAPI.post('/api/subscription_cancel.php', {});
                toast('Подписка отменена', 'success');
                CookAPI.clearCache(); await this.load();
            } catch (e) { toast(e.message, 'error'); }
            finally { this.busy = false; }
        },
        async refund(id) {
            if (!confirm('Оформить возврат средств? Подписка будет отменена.')) return;
            this.busy = true;
            try {
                const r = await CookAPI.post('/api/payment_refund.php', { subscription_id: id });
                toast(r.status === 'succeeded' ? 'Возврат выполнен' : 'Возврат в обработке', 'success');
                CookAPI.clearCache(); await this.load();
            } catch (e) { toast(e.message, 'error'); }
            finally { this.busy = false; }
        }
    };
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>