<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$recipes = db_all('SELECT id, title, image_url, calories FROM recipes ORDER BY RAND() LIMIT 30');
$pageTitle = 'Планировщик питания';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-8" x-data="mealPlanner(<?= e(json_encode($recipes, JSON_UNESCAPED_UNICODE)) ?>)">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6" data-aos="fade-up">🗓 Планировщик питания</h1>

    <div class="grid grid-cols-1 lg:grid-cols-7 gap-3">
        <template x-for="(day, di) in days" :key="di">
            <div class="bg-white rounded-2xl shadow-sm p-3">
                <div class="font-bold text-gray-700 text-sm mb-2 text-center" x-text="day.name"></div>
                <template x-for="(meal, mi) in day.meals" :key="mi">
                    <div class="mb-2">
                        <div class="text-xs text-gray-400 mb-1" x-text="meal.label"></div>
                        <select @change="assign(di, mi, $event.target.value)" class="w-full text-xs px-2 py-1.5 rounded-lg border border-gray-200 outline-none">
                            <option value="">—</option>
                            <template x-for="r in recipes" :key="r.id"><option :value="r.id" x-text="r.title"></option></template>
                        </select>
                    </div>
                </template>
                <div class="text-xs text-center text-amber-600 font-bold mt-1" x-text="'🔥 ' + dayCalories(di) + ' ккал'"></div>
            </div>
        </template>
    </div>
</section>
<script src="<?= url('assets/js/social/meal-planner.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>