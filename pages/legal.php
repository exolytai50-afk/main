<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Правовая информация';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6">⚖️ Правовая информация</h1>
    
    <div class="bg-white rounded-2xl shadow-md p-8 space-y-6 text-gray-600 leading-relaxed">
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">ООО «GigaChef»</h2>
            <p>Юридический адрес: г. Москва, ул. Примерная, д. 1</p>
            <p>ИНН: 7700000000</p>
            <p>ОГРН: 1234567890123</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Интеллектуальная собственность</h2>
            <p>Все материалы платформы, включая логотип, дизайн и программное обеспечение, являются собственностью GigaChef и защищены законодательством РФ.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Отказ от ответственности</h2>
            <p>Рецепты и рекомендации, сгенерированные AI, носят информационный характер. Перед применением рекомендаций проконсультируйтесь со специалистом.</p>
        </div>
        
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Контакты</h2>
            <p>Email: support@cookai.ru</p>
            <p>Телефон: +7 (999) 000-00-00</p>
        </div>
        
        <div class="pt-4 border-t border-gray-100 text-sm text-gray-400">
            Последнее обновление: <?= date('d.m.Y') ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>