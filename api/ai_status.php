<?php
/**
 * GET /api/ai_status.php?feature=calorie
 * → остаток AI-лимита + баннер плановых техработ.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/ai_guard.php';

$feature = (string)($_GET['feature'] ?? 'generate');
if (!array_key_exists($feature, AI_LIMITS)) $feature = 'generate';

json_response([
    'ai'          => ai_usage_status($feature),
    'maintenance' => cookai_maintenance_notice(),
]);