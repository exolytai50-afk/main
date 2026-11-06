<?php
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "function_exists('add_user_points'): ";
var_dump(function_exists('add_user_points'));

echo "\nfunction_exists('award_points'): ";
var_dump(function_exists('award_points'));

echo "\nfunction_exists('check_achievements'): ";
var_dump(function_exists('check_achievements'));

echo "\n\nReflectionFunction add_user_points:\n";
if (function_exists('add_user_points')) {
    $ref = new ReflectionFunction('add_user_points');
    echo "File: " . $ref->getFileName() . "\n";
    echo "Lines: " . $ref->getStartLine() . " - " . $ref->getEndLine() . "\n";
}

echo "\n\nDB test:\n";
$user = db_one('SELECT id, email FROM users LIMIT 1');
var_dump($user);

if ($user) {
    echo "\nTrying add_user_points...\n";
    $result = add_user_points((int)$user['id'], 1, 'test_reason', 'Тестовая запись');
    var_dump($result);
    
    echo "\nLast points_log rows:\n";
    $rows = db_all('SELECT * FROM points_log ORDER BY id DESC LIMIT 5');
    print_r($rows);
}