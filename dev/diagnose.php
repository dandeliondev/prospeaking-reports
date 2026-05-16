<?php
/**
 * Deployment check — remove or block when not debugging.
 * Requires APP_DEBUG=1 in dev/.env (or setenv APP_DEBUG=1 in php-fpm pool).
 */
require_once __DIR__ . '/load.php';

if (!prospeaking_is_debug()) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

echo "ProSpeaking diagnose\n";
echo "====================\n\n";
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'Document root (guess): ' . ($_SERVER['DOCUMENT_ROOT'] ?? '(not set)') . "\n";
echo 'Script: ' . (__FILE__) . "\n";
echo 'Repo root: ' . dirname(__DIR__) . "\n";
echo 'index.php exists: ' . (is_readable(dirname(__DIR__) . '/index.php') ? 'yes' : 'no') . "\n";
echo 'dev/load.php exists: ' . (is_readable(__DIR__ . '/load.php') ? 'yes' : 'no') . "\n";
echo 'php_include: ';
foreach (['/srv/www/php_include.php', dirname(__DIR__) . '/dev/php_include.php'] as $p) {
    echo $p . ' => ' . (is_readable($p) ? "OK\n" : "missing\n       ");
}
echo "\nAPP_DEBUG: " . (prospeaking_is_debug() ? 'on' : 'off') . "\n";
echo 'display_errors: ' . ini_get('display_errors') . "\n\n";

if (function_exists('connectToCluster')) {
    echo "DB test: ";
    try {
        global $clusters;
        $conn = connectToCluster('pslw', $clusters);
        echo $conn instanceof mysqli ? "pslw connect OK\n" : "connect returned mock/other\n";
    } catch (Throwable $e) {
        echo "FAILED — " . $e->getMessage() . "\n";
    }
}

echo "\n(remove dev/diagnose.php when finished)\n";
