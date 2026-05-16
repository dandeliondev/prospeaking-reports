<?php
/**
 * Load shared config: production path first, then repo dev/php_include.php.
 */
require_once __DIR__ . '/env.php';
prospeaking_configure_errors();
prospeaking_load_env();

if (function_exists('connectToCluster')) {
    return;
}

$candidates = [
    '/srv/www/php_include.php',
    dirname(__DIR__) . '/dev/php_include.php',
];

foreach ($candidates as $path) {
    if (is_readable($path)) {
        require_once $path;
        prospeaking_configure_errors();
        return;
    }
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "ProSpeaking: could not find php_include.php.\n";
echo "Run: ./dev/start.sh  (Docker)  or  ./dev/start-local.sh\n";
exit(1);
