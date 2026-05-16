<?php
/**
 * ProSpeaking application config (DB, Vicidial field names, cluster connection).
 * Copy config/.env.example to config/.env and set credentials.
 */

require_once __DIR__ . '/mock_mysqli.php';

$curDate = date('Y-m-d');
$today   = $curDate;

$teamArr = [
    'Davao'  => "AGENT LIKE '4%'",
    'Digos'  => "AGENT LIKE '5%'",
    'Iloilo' => "AGENT LIKE '6%'",
    'Live'   => "PARENT = AGENT",
    'Roust'  => "TYPE = 'R'",
];

$mSale         = 'SALE';
$cSale         = 'CSALE';
$bSale         = 'BSALE';
$amountField   = 'comments';
$leadTypeField = 'vendor_lead_code';
$departmentKeyField = 'vendor_lead_code';
$occupationField    = 'title';
$employerField      = 'address3';

$clusters = [];

/** @var bool */
$prospeaking_using_mock = false;

if (is_readable(__DIR__ . '/php_include.local.php')) {
    require __DIR__ . '/php_include.local.php';
}

function prospeaking_wants_mock(): bool
{
    $flag = getenv('DEV_MOCK');
    if ($flag === '1' || $flag === 'true') {
        return true;
    }
    if ($flag === '0' || $flag === 'false') {
        return false;
    }
    return !prospeaking_env_file_exists();
}

function prospeaking_db_config(): array
{
    return [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
    ];
}

function connectToCluster($name, &$clusters)
{
    global $prospeaking_using_mock;

    static $conn = null;
    if ($conn !== null) {
        if ($name === 'pslv' && !$prospeaking_using_mock) {
            $conn->select_db('vicidial');
        }
        return $conn;
    }

    if (prospeaking_wants_mock()) {
        $prospeaking_using_mock = true;
        $conn = new DevMockMysqli();
        return $conn;
    }

    $cfg = prospeaking_db_config();
    $reporting = mysqli_report(MYSQLI_REPORT_OFF);

    try {
        $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], '', $cfg['port']);
        if ($conn->connect_error) {
            throw new mysqli_sql_exception($conn->connect_error, $conn->connect_errno);
        }
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        if (prospeaking_env_file_exists()) {
            mysqli_report($reporting);
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>Database connection failed</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p>Edit <code>config/.env</code> with correct <code>DB_*</code> values.</p>';
            exit(1);
        }

        $prospeaking_using_mock = true;
        $conn = new DevMockMysqli();
    }

    mysqli_report($reporting);

    if ($name === 'pslv' && !$prospeaking_using_mock) {
        $conn->select_db('vicidial');
    }

    return $conn;
}

function prospeaking_select_db($conn, string $database): bool
{
    if ($conn instanceof DevMockMysqli) {
        return $conn->select_db($database);
    }
    return mysqli_select_db($conn, $database);
}

function prospeaking_close($conn): void
{
    if ($conn instanceof DevMockMysqli) {
        return;
    }
    mysqli_close($conn);
}

function prospeaking_mock_banner(): string
{
    global $prospeaking_using_mock;
    if (!$prospeaking_using_mock) {
        return '';
    }
    return '<div style="display:block;background:#fff3cd;color:#664d03;padding:6px 12px;text-align:center;font-size:13px;border-bottom:1px solid #ffc107">'
        . 'Demo mode — dummy data. Set <code>config/.env</code> for a real database.'
        . '</div>';
}
