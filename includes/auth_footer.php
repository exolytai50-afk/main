</main>

<!-- Футер -->
<footer class="bg-gray-900 text-gray-400 py-8 mt-auto">
    <div class="max-w-5xl mx-auto px-4">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <img src="<?= url('assets/icon/chef_32.png') ?>" alt="GigaChef" class="w-6 h-6 rounded-lg">
                <span class="text-white font-bold">GigaChef</span>
            </div>
            <div class="flex gap-4 text-sm">
                <a href="<?= url('terms') ?>" class="hover:text-white transition">Условия</a>
                <a href="<?= url('privacy') ?>" class="hover:text-white transition">Конфиденциальность</a>
                <a href="<?= url('legal') ?>" class="hover:text-white transition">Правовая информация</a>
                <a href="<?= url('about') ?>" class="hover:text-white transition">О нас</a>
            </div>
        </div>
        <div class="text-center text-xs mt-4 pt-4 border-t border-gray-800">
            © <?= date('Y') ?> GigaChef. Все права защищены.
        </div>
    </div>
</footer>
<script>
/**
 * Alpine-компонент для полей с паролем.
 * Использование:
 *   <div class="password-field" x-data="passwordField()">
 *       <input :type="shown ? 'text' : 'password'" ...>
 *       <button type="button" class="password-toggle" @click="toggle" :aria-label="...">
 *           <svg x-show="!shown" ...>  <!-- eye -->
 *           <svg x-show="shown" ...>   <!-- eye-off -->
 *       </button>
 *   </div>
 */
function passwordField() {
    return {
        shown: false,
        toggle() {
            this.shown = !this.shown;
            // Сохранить фокус на поле после переключения
            const input = this.$el.querySelector('input');
            if (input) {
                const pos = input.value.length;
                input.focus();
                input.setSelectionRange(pos, pos);
            }
        },
        get label() {
            return this.shown ? 'Скрыть пароль' : 'Показать пароль';
        }
    };
}
</script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script src="<?= url('assets/js/theme.js') ?>"></script>
<script>
    AOS.init({ once: true, duration: 600 });
</script>
</body>
</html>