<?php
/**
 * DEPRECATED: This file is kept for backward compatibility only.
 * All functions have been moved to includes/functions.php
 * 
 * Please update your code to require 'includes/functions.php' instead.
 */
declare(strict_types=1);

if (!function_exists('json_response')) {
    require_once __DIR__ . '/functions.php';
}
