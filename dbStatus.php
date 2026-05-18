<?php
/**
 * Database and integration diagnostics for ProSpeaking.
 * Visit: /dbStatus.php (repo root)
 */
require_once __DIR__ . '/config/bootstrap.php';

// Avoid mysqli exceptions from APP_DEBUG strict mode — this page handles errors inline.
mysqli_report(MYSQLI_REPORT_OFF);

$diagKey = getenv('DIAG_KEY') ?: ($_ENV['DIAG_KEY'] ?? '');
if ($diagKey !== '') {
    $provided = $_GET['key'] ?? '';
    if (!is_string($provided) || !hash_equals($diagKey, $provided)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><body><h1>Forbidden</h1><p>Set <code>DIAG_KEY</code> in <code>config/.env</code> and open <code>dbStatus.php?key=…</code></p></body></html>';
        exit;
    }
}

/** @return 'ok'|'warn'|'fail' */
function diag_status($s)
{
    if ($s === 'ok' || $s === 'warn') {
        return $s;
    }
    return 'fail';
}

function diag_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function diag_mask_secret(string $value): string
{
    if ($value === '') {
        return '(empty)';
    }
    $len = strlen($value);
    if ($len <= 2) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, 1) . str_repeat('*', min(8, $len - 2)) . substr($value, -1);
}

/**
 * @return array{status: string, message: string, detail: string, ms: float|null}
 */
function diag_test_mysqli(array $cfg, $schema = null, $table = null, $sampleSql = null)
{
    $start = microtime(true);

    try {
        $mysqli = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], '', $cfg['port']);
        if ($mysqli->connect_errno) {
            return [
                'status' => 'fail',
                'message' => 'Connection failed',
                'detail' => $mysqli->connect_error,
                'ms' => null,
            ];
        }

        $mysqli->set_charset('utf8mb4');
        $details = ['Server: ' . $mysqli->server_info];

        if ($schema !== null) {
            if (!$mysqli->select_db($schema)) {
                $mysqli->close();
                return [
                    'status' => 'fail',
                    'message' => "Schema missing: {$schema}",
                    'detail' => $mysqli->error,
                    'ms' => round((microtime(true) - $start) * 1000, 1),
                ];
            }
            $details[] = "Schema: {$schema}";
        }

        if ($table !== null && $schema !== null) {
            $escaped = '`' . str_replace('`', '``', $table) . '`';
            $res = $mysqli->query("SELECT COUNT(*) AS c FROM {$escaped}");
            if (!$res) {
                $mysqli->close();
                return [
                    'status' => 'fail',
                    'message' => "Table missing: {$schema}.{$table}",
                    'detail' => $mysqli->error,
                    'ms' => round((microtime(true) - $start) * 1000, 1),
                ];
            }
            $row = $res->fetch_assoc();
            $details[] = "{$table} rows: " . (isset($row['c']) ? $row['c'] : '?');
            $res->free();
        }

        if ($sampleSql !== null) {
            $res = $mysqli->query($sampleSql);
            if (!$res) {
                $mysqli->close();
                return [
                    'status' => 'fail',
                    'message' => 'Sample query failed',
                    'detail' => $mysqli->error,
                    'ms' => round((microtime(true) - $start) * 1000, 1),
                ];
            }
            $res->free();
            $details[] = 'Sample query: OK';
        }

        $mysqli->close();

        return [
            'status' => 'ok',
            'message' => 'Connected',
            'detail' => implode(' · ', $details),
            'ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'fail',
            'message' => 'MySQL error',
            'detail' => $e->getMessage(),
            'ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }
}

/**
 * @return array{status: string, message: string, detail: string, ms: float|null}
 */
function diag_test_http_url(string $url): array
{
    $url = rtrim($url, '/');
    if ($url === '') {
        return ['status' => 'warn', 'message' => 'No URL', 'detail' => '', 'ms' => null];
    }

    $target = $url . '/vicidial/non_agent_api.php';
    $start = microtime(true);

    if (!function_exists('curl_init')) {
        return ['status' => 'warn', 'message' => 'cURL not available', 'detail' => $target, 'ms' => null];
    }

    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ms = round((microtime(true) - $start) * 1000, 1);

    if ($errno !== 0) {
        return ['status' => 'fail', 'message' => 'Unreachable', 'detail' => "{$target} — {$err}", 'ms' => $ms];
    }

    if ($code >= 200 && $code < 500) {
        return [
            'status' => 'ok',
            'message' => "HTTP {$code}",
            'detail' => $target,
            'ms' => $ms,
        ];
    }

    return [
        'status' => 'warn',
        'message' => "HTTP {$code}",
        'detail' => $target,
        'ms' => $ms,
    ];
}

$rows = [];
$diagFatal = null;

try {
$cfg = prospeaking_db_config();
$mockWanted = prospeaking_wants_mock();
$envExists = prospeaking_env_file_exists();
$localConfig = is_readable(PROSPEAKING_ROOT . '/config/php_include.local.php');

$rows[] = [
    'group' => 'Environment',
    'name' => 'config/.env',
    'status' => $envExists ? 'ok' : 'warn',
    'message' => $envExists ? 'Present' : 'Missing',
    'detail' => $envExists ? PROSPEAKING_ROOT . '/config/.env' : 'Without .env, app falls back to mock data when connection fails',
    'ms' => null,
];

$rows[] = [
    'group' => 'Environment',
    'name' => 'DEV_MOCK',
    'status' => $mockWanted ? 'warn' : 'ok',
    'message' => $mockWanted ? 'Enabled (mock data)' : 'Disabled (real DB)',
    'detail' => 'getenv(DEV_MOCK)=' . diag_h(getenv('DEV_MOCK') !== false ? (string) getenv('DEV_MOCK') : '(not set)'),
    'ms' => null,
];

$rows[] = [
    'group' => 'Environment',
    'name' => 'php_include.local.php',
    'status' => $localConfig ? 'ok' : 'warn',
    'message' => $localConfig ? 'Loaded' : 'Not present',
    'detail' => $localConfig ? 'Cluster URLs and production overrides' : 'Optional — copy from config/php_include.example.php',
    'ms' => null,
];

$rows[] = [
    'group' => 'Environment',
    'name' => 'MySQL credentials source',
    'status' => 'ok',
    'message' => 'config/.env (DB_*)',
    'detail' => sprintf(
        'Host %s:%d · User %s · Pass %s',
        diag_h($cfg['host']),
        $cfg['port'],
        diag_h($cfg['user']),
        diag_h(diag_mask_secret($cfg['pass']))
    ),
    'ms' => null,
];

if ($mockWanted) {
    $rows[] = [
        'group' => 'Application DB',
        'name' => 'connectToCluster()',
        'status' => 'warn',
        'message' => 'Mock mode — no real MySQL',
        'detail' => 'Reports use DevMockMysqli. Set DEV_MOCK=0 and fix DB_* to test real schemas.',
        'ms' => null,
    ];
} else {
    $base = diag_test_mysqli($cfg);
    $rows[] = [
        'group' => 'Application DB',
        'name' => 'MySQL (all clusters use this)',
        'status' => diag_status($base['status']),
        'message' => $base['message'],
        'detail' => $base['detail'] . ' — connectToCluster(pslw|psl1|pslv) shares one mysqli via .env',
        'ms' => $base['ms'],
    ];

    $reportingTables = [
        ['DPH', 'DAILY'],
        ['DPH2', 'DAILY'],
        ['VFR', 'DAILY'],
        ['Sales', 'Sales'],
    ];
    foreach ($reportingTables as $pair) {
        $schema = $pair[0];
        $table = $pair[1];
        $t = diag_test_mysqli($cfg, $schema, $table);
        $rows[] = [
            'group' => 'Reporting schemas (pslw)',
            'name' => "{$schema}.{$table}",
            'status' => diag_status($t['status']),
            'message' => $t['message'],
            'detail' => $t['detail'],
            'ms' => $t['ms'],
        ];
    }

    $vicidial = diag_test_mysqli(
        $cfg,
        'vicidial',
        'vicidial_list',
        'SELECT lead_id FROM vicidial_list LIMIT 1'
    );
    $rows[] = [
        'group' => 'Vicidial (pslv)',
        'name' => 'vicidial.vicidial_list',
        'status' => diag_status($vicidial['status']),
        'message' => $vicidial['message'],
        'detail' => $vicidial['detail'] . ' — used after connectToCluster(pslv)',
        'ms' => $vicidial['ms'],
    ];

    $asterisk = diag_test_mysqli(
        $cfg,
        null,
        null,
        'SELECT lead_id FROM asterisk.vicidial_list LIMIT 1'
    );
    $rows[] = [
        'group' => 'Vicidial dialer (psl1/psl2)',
        'name' => 'asterisk.vicidial_list',
        'status' => diag_status($asterisk['status']),
        'message' => $asterisk['message'],
        'detail' => $asterisk['detail'] . ' — imports read dialer logs from asterisk.*',
        'ms' => $asterisk['ms'],
    ];
}

$clusterKeys = array_keys($clusters);
if ($clusterKeys === []) {
    $rows[] = [
        'group' => 'Cluster config',
        'name' => '$clusters',
        'status' => 'warn',
        'message' => 'Empty',
        'detail' => 'Define pslw, psl1, psl2, pslv in config/php_include.local.php for imports and API URLs',
        'ms' => null,
    ];
} else {
    foreach ($clusterKeys as $key) {
        $c = $clusters[$key];
        $url = is_array($c) ? (string) ($c['url'] ?? '') : '';
        $host = is_array($c) ? (string) ($c['host'] ?? '') : '';
        $user = is_array($c) ? (string) ($c['user'] ?? '') : '';

        $rows[] = [
            'group' => 'Cluster config',
            'name' => "\${clusters['{$key}']}",
            'status' => 'ok',
            'message' => 'Defined',
            'detail' => sprintf(
                'url=%s · host=%s (not used for mysqli) · user=%s',
                $url !== '' ? $url : '(none)',
                $host !== '' ? $host : '(none)',
                $user !== '' ? $user : '(none)'
            ),
            'ms' => null,
        ];

        if ($url !== '') {
            $http = diag_test_http_url($url);
            $rows[] = [
                'group' => 'Vicidial HTTP API',
                'name' => "{$key} API base",
                'status' => diag_status($http['status']),
                'message' => $http['message'],
                'detail' => $http['detail'],
                'ms' => $http['ms'],
            ];
        }
    }
}

global $Scon;
if (isset($Scon) && $Scon instanceof mysqli) {
    $rows[] = [
        'group' => 'Legacy',
        'name' => '$Scon',
        'status' => $Scon->connect_errno ? 'fail' : 'ok',
        'message' => $Scon->connect_errno ? 'Error' : 'mysqli instance set',
        'detail' => $Scon->connect_errno ? $Scon->connect_error : 'Used by Admin/addDNC.php (Sales / asterisk)',
        'ms' => null,
    ];
} else {
    $rows[] = [
        'group' => 'Legacy',
        'name' => '$Scon',
        'status' => 'warn',
        'message' => 'Not configured',
        'detail' => 'Optional second connection in php_include.local.php',
        'ms' => null,
    ];
}

$apiUserSet = isset($apiUser) && (string) $apiUser !== '';
$rows[] = [
    'group' => 'Vicidial API auth',
    'name' => '$apiUser / $apiPass',
    'status' => $apiUserSet ? 'ok' : 'warn',
    'message' => $apiUserSet ? 'Configured' : 'Not set',
    'detail' => $apiUserSet
        ? 'User ' . diag_h((string) $apiUser) . ' · Pass ' . diag_h(diag_mask_secret((string) ($apiPass ?? '')))
        : 'Set in php_include.local.php for list upload / DNC API calls',
    'ms' => null,
];

$failCount = 0;
$warnCount = 0;
$okCount = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'fail') {
        $failCount++;
    } elseif ($r['status'] === 'warn') {
        $warnCount++;
    } elseif ($r['status'] === 'ok') {
        $okCount++;
    }
}
$overall = $failCount > 0 ? 'fail' : ($warnCount > 0 ? 'warn' : 'ok');

} catch (Throwable $e) {
    $diagFatal = $e->getMessage();
    $rows = [[
        'group' => 'Fatal',
        'name' => 'dbStatus.php',
        'status' => 'fail',
        'message' => 'Uncaught error',
        'detail' => $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
        'ms' => null,
    ]];
    $failCount = 1;
    $warnCount = 0;
    $okCount = 0;
    $overall = 'fail';
    $diagKey = getenv('DIAG_KEY') ?: ($_ENV['DIAG_KEY'] ?? '');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>ProSpeaking — Database diagnostics</title>
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="style.css" type="text/css"/>
    <style>
        body { padding-top: 70px; padding-bottom: 40px; }
        .diag-summary { margin-bottom: 24px; }
        .diag-summary .label { font-size: 14px; display: inline-block; margin-right: 8px; }
        table.diag-table { background: #fff; }
        table.diag-table th { background: #f5f5f5; }
        .status-ok { color: #3c763d; font-weight: bold; }
        .status-warn { color: #8a6d3b; font-weight: bold; }
        .status-fail { color: #a94442; font-weight: bold; }
        .diag-detail { color: #666; font-size: 12px; max-width: 520px; word-break: break-word; }
        .diag-ms { color: #999; font-size: 12px; white-space: nowrap; }
    </style>
</head>
<body>
<?php echo prospeaking_mock_banner(); ?>
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;">
        <a href="adminTools.php" class="prospeaking-logo" title="ProSpeaking">ProSpeaking</a>
        <span class="text-muted">Database diagnostics</span>
        <a href="dbStatus.php<?php echo $diagKey !== '' ? '?key=' . rawurlencode((string) ($_GET['key'] ?? '')) : ''; ?>" class="btn btn-default btn-sm">Refresh</a>
    </div>
</nav>

<div class="container" style="max-width: 1100px;">
    <div class="diag-summary">
        <h1 style="margin-top:0;">Connection status</h1>
        <p class="text-muted">Generated <?php echo diag_h(date('Y-m-d H:i:s T')); ?> · PHP <?php echo diag_h(PHP_VERSION); ?></p>
        <p>
            <span class="label label-<?php echo $overall === 'ok' ? 'success' : ($overall === 'warn' ? 'warning' : 'danger'); ?>">
                <?php echo $overall === 'ok' ? 'All critical checks passed' : ($overall === 'warn' ? 'Warnings present' : 'Failures detected'); ?>
            </span>
            <span class="text-muted"><?php echo (int) $okCount; ?> ok · <?php echo (int) $warnCount; ?> warn · <?php echo (int) $failCount; ?> fail</span>
        </p>
        <?php if ($diagFatal !== null) : ?>
        <p class="alert alert-danger" style="font-size:13px;">
            <strong>Page error:</strong> <?php echo diag_h($diagFatal); ?>
            — often <code>php_include.local.php</code> syntax, PHP version, or <code>APP_DEBUG</code> + mysqli strict mode (now caught).
        </p>
        <?php endif; ?>
        <?php if ($diagKey === '') : ?>
        <p class="alert alert-info" style="font-size:13px;">
            This page shows hostnames and schema names. To restrict access in production, set <code>DIAG_KEY=your-secret</code> in <code>config/.env</code> and open <code>dbStatus.php?key=your-secret</code>.
        </p>
        <?php endif; ?>
    </div>

    <table class="table table-bordered table-striped diag-table">
        <thead>
            <tr>
                <th>Area</th>
                <th>Check</th>
                <th>Status</th>
                <th>Result</th>
                <th>Details</th>
                <th>ms</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $lastGroup = null;
        foreach ($rows as $row) :
            ?>
            <tr>
                <td><?php echo $row['group'] !== $lastGroup ? diag_h($row['group']) : ''; ?></td>
                <td><code><?php echo diag_h($row['name']); ?></code></td>
                <td class="status-<?php echo diag_h($row['status']); ?>"><?php echo strtoupper(diag_h($row['status'])); ?></td>
                <td><?php echo diag_h($row['message']); ?></td>
                <td class="diag-detail"><?php echo diag_h($row['detail']); ?></td>
                <td class="diag-ms"><?php echo $row['ms'] !== null ? diag_h((string) $row['ms']) : '—'; ?></td>
            </tr>
            <?php
            $lastGroup = $row['group'];
        endforeach;
        ?>
        </tbody>
    </table>

    <p class="text-muted" style="font-size:12px;">
        <a href="adminTools.php">Admin tools</a> ·
        <a href="Reports/DPH/index.php">DPH report</a> ·
        <a href="README.md">README</a> (setup)
    </p>
</div>
</body>
</html>
