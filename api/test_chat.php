<?php
header('Content-Type: text/plain; charset=utf-8');
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "default_socket_timeout: " . ini_get('default_socket_timeout') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "set_time_limit: " . (function_exists('set_time_limit') ? 'yes' : 'no') . "\n";