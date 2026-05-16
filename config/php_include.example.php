<?php
/**
 * Production-style override example.
 * Copy to config/php_include.local.php (gitignored) to set $clusters and API URLs
 * without editing php_include.php.
 *
 *   cp config/php_include.example.php config/php_include.local.php
 */

$clusters = [
    'pslw' => [
        'url' => 'https://pslw-admin.example.com',
        'host' => '127.0.0.1',
        'user' => 'report_user',
        'pass' => 'secret',
    ],
    'psl1' => ['host' => '10.0.0.1', 'user' => '…', 'pass' => '…'],
    'psl2' => ['host' => '10.0.0.2', 'user' => '…', 'pass' => '…'],
    'pslv' => ['host' => '10.0.0.3', 'user' => '…', 'pass' => '…'],
];

// Optional: $apiUser, $apiPass, $departmentKeyField, $Scon, etc. from your live php_include.php
