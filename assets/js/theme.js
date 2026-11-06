(function () {
    'use strict';

    const THEMES = {
        light: {
            '--bg-body': '#e5e7eb',
            '--bg-app': '#f9fafb',
            '--bg-card': '#ffffff',
            '--bg-hover': '#f3f4f6',
            '--text-main': '#374151',
            '--text-muted': '#6b7280',
            '--text-light': '#9ca3af',
            '--border-main': '#e5e7eb',
            '--accent-amber-bg': '#fef3c7',
            '--accent-amber-text': '#b45309'
        },
        dark: {
            '--bg-body': '#020617',
            '--bg-app': '#0f172a',
            '--bg-card': '#1e293b',
            '--bg-hover': '#334155',
            '--text-main': '#f1f5f9',
            '--text-muted': '#94a3b8',
            '--text-light': '#64748b',
            '--border-main': '#334155',
            '--accent-amber-bg': 'rgba(245, 158, 11, 0.2)',
            '--accent-amber-text': '#fbbf24'
        }
    };

    function applyTheme(theme) {
        const root = document.documentElement;
        const vars = THEMES[theme] || THEMES.light;

        // 1. Устанавливаем CSS-переменные
        for (const [key, value] of Object.entries(vars)) {
            root.style.setProperty(key, value);
        }

        // 2. Переключаем класс
        if (theme === 'dark') {
            root.classList.add('theme-dark');
        } else {
            root.classList.remove('theme-dark');
        }

        // 3. Сохраняем
        localStorage.setItem('gc_theme', theme);

        // 4. Обновляем UI кнопки
        const lightIcon = document.querySelector('.gc-theme-icon-light');
        const darkIcon  = document.querySelector('.gc-theme-icon-dark');
        const label     = document.querySelector('.gc-theme-label');

        if (theme === 'dark') {
            if (lightIcon) lightIcon.style.display = 'none';
            if (darkIcon)  darkIcon.style.display  = 'block';
            if (label)     label.textContent       = 'Светлая тема';
        } else {
            if (lightIcon) lightIcon.style.display = 'block';
            if (darkIcon)  darkIcon.style.display  = 'none';
            if (label)     label.textContent       = 'Тёмная тема';
        }
        
        // 5. ПРИНУДИТЕЛЬНОЕ ОБНОВЛЕНИЕ СТИЛЕЙ
        forceUpdateStyles(theme);
    }

    function forceUpdateStyles(theme) {
        const body = document.body;
        const isDark = theme === 'dark';

        // Меняем фон body напрямую
        body.style.backgroundColor = isDark ? '#0f172a' : '#f9fafb';
        body.style.color = isDark ? '#f1f5f9' : '#374151';

        // Меняем фон всех карточек
        document.querySelectorAll('.bg-white').forEach(el => {
            el.style.backgroundColor = isDark ? '#1e293b' : '#ffffff';
        });

        // Меняем фон всех серых панелей
        document.querySelectorAll('.bg-gray-50, .bg-gray-100').forEach(el => {
            el.style.backgroundColor = isDark ? '#334155' : '#f3f4f6';
        });

        // Меняем текст
        document.querySelectorAll('.text-gray-800, .text-gray-700, .text-gray-600').forEach(el => {
            el.style.color = isDark ? '#f1f5f9' : '#374151';
        });
        document.querySelectorAll('.text-gray-500, .text-gray-400').forEach(el => {
            el.style.color = isDark ? '#94a3b8' : '#6b7280';
        });

        // Меняем границы
        document.querySelectorAll('.border-gray-200, .border-gray-100').forEach(el => {
            el.style.borderColor = isDark ? '#334155' : '#e5e7eb';
        });

        // Меняем чаты
        document.querySelectorAll('.chat-header-fixed, .chat-messages-scroll, .chat-input-fixed').forEach(el => {
            el.style.backgroundColor = isDark ? '#1e293b' : '#ffffff';
            el.style.borderColor = isDark ? '#334155' : '#e5e7eb';
        });
    }

    window.gcToggleTheme = function () {
        const isDark = document.documentElement.classList.contains('theme-dark');
        applyTheme(isDark ? 'light' : 'dark');
    };

    // При загрузке
    document.addEventListener('DOMContentLoaded', function () {
        const saved = localStorage.getItem('gc_theme');
        if (saved) {
            applyTheme(saved);
        }
    });

})();