<?php
/**
 * Single entry point for ProSpeaking config. Include this from every PHP script.
 *
 *   require_once __DIR__ . '/config/bootstrap.php';       (from repo root)
 *   require_once __DIR__ . '/../config/bootstrap.php';    (from Admin/, Lists/, Sales/, VICI/)
 *   require_once __DIR__ . '/../../config/bootstrap.php'; (from Reports/DPH/, etc.)
 */

if (defined('PROSPEAKING_BOOTSTRAP_LOADED')) {
    return;
}
define('PROSPEAKING_BOOTSTRAP_LOADED', true);

if (!defined('PROSPEAKING_ROOT')) {
    define('PROSPEAKING_ROOT', dirname(__DIR__));
}

require_once PROSPEAKING_ROOT . '/config/env.php';
prospeaking_load_env();
prospeaking_configure_errors();

if (!function_exists('connectToCluster')) {
    // php_include.local.php is merged inside php_include.php (clusters, API creds).
    $configCandidates = [
        PROSPEAKING_ROOT . '/config/php_include.php',
        '/srv/www/php_include.php',
    ];

    $loaded = false;
    foreach ($configCandidates as $path) {
        if (is_readable($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }

    if (!$loaded) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "ProSpeaking: missing config.\n";
        echo "Copy config/.env.example to config/.env and ensure config/php_include.php exists.\n";
        exit(1);
    }
}

prospeaking_configure_errors();

if (!function_exists('prospeaking_load_vfr_include')) {
    function prospeaking_load_vfr_include(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $candidates = [
            PROSPEAKING_ROOT . '/VICI/vfr_include.php',
            '/srv/www/vfr_include.php',
            '/srv/www/htdocs/ProSpeaking/VICI/vfr_include.php',
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                require_once $path;
                $loaded = true;
                return;
            }
        }
    }
}
