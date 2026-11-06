<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Пользовательское соглашение';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6">📋 Пользовательское соглашение</h1>
    
    <div class="bg-white rounded-2xl shadow-md p-8 space-y-6 text-gray-600 leading-relaxed">
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">1. Общие положения</h2>
            <p>Настоящее Пользовательское соглашение регулирует отношения между GigaChef и пользователями платформы. Используя GigaChef, вы соглашаетесь с условиями данного соглашения.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">2. Регистрация</h2>
            <p>Для доступа к полному функционалу платформы необходима регистрация. Пользователь обязуется предоставлять достоверную информацию при регистрации.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">3. Пользовательский контент</h2>
            <p>Публикуя рецепты, фотографии и комментарии, пользователь гарантирует, что контент не нарушает права третьих лиц и законодательство РФ.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">4. AI-инструменты</h2>
            <p>Результаты работы AI-инструментов носят рекомендательный характер. GigaChef не несёт ответственности за точность сгенерированного контента.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">5. Ограничения</h2>
            <p>Запрещается публикация оскорбительного, незаконного контента, спама и материалов, нарушающих авторские права.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">6. Ответственность</h2>
            <p>GigaChef предоставляет платформу «как есть». Администрация вправе удалять контент и блокировать пользователей, нарушающих правила.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">7. Изменения</h2>
            <p>GigaChef вправе изменять условия соглашения. Продолжение использования платформы после изменений означает согласие с новыми условиями.</p>
        </div>
        
        <div class="pt-4 border-t border-gray-100 text-sm text-gray-400">
            Последнее обновление: <?= date('d.m.Y') ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>