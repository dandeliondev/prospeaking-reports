<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// Enable error reporting for debugging
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
prospeaking_load_vfr_include();
$pslw = connectToCluster('pslw', $clusters);
prospeaking_select_db($pslw, "VFR");

// Function to dynamically build WHERE clause, table selection, grouping, and sorting
function buildQueryParams($params, &$table) {
    global $curDate;

    // Determine the table and date condition based on the range
    if ($params['range'] === 'DAILY') {
        $table = 'DAILY';
        $dateCondition = '';
    } elseif ($params['range'] === 'CUSTOM') {
        if ($params['end'] == $curDate) {
            $table = "(select * from ARCHIVE union all select * from DAILY) a";
        } else {
            $table = "ARCHIVE";
        }
        $dateCondition = "DATE between '{$params['start']}' and '{$params['end']}'";
    } elseif ($params['range'] < 7) {
        $table = "ARCHIVE";
        $dateCondition = "DATE = date_sub(CURDATE(), interval {$params['range']} day)";
    } else {
        $table = 'ARCHIVE';
        $dateCondition = "DATE > date_sub(CURDATE(), interval {$params['range']} day)";
    }

    // Build conditions array
    $conditions = [];
    if (!empty($params['dept'])) $conditions[] = "DEPTKEY = '{$params['dept']}'";
    if (!empty($params['camp'])) $conditions[] = "CAMPAIGN_ID = '{$params['camp']}'";
    if (!empty($params['leadType'])) $conditions[] = "TYPE = '{$params['leadType']}'";
    if (!empty($params['list'])) $conditions[] = "LIST_ID = '{$params['list']}'";
    if (!empty($params['cluster'])) $conditions[] = "AGENT_TYPE = '{$params['cluster']}'";

    // Adjust grouping and sorting based on agent/agentCamp
    if (!empty($params['agent'])) {
        $conditions[] = "AGENT = '{$params['agent']}'";
        $groupBy = "DATE";
        $header = "Date";
        $sortOrder = $_GET['sort'] == "" ? "DATE DESC" : str_replace("|"," ",$_GET['sort']) . ", DATE DESC";
    } elseif (!empty($params['agentCamp'])) {
        $conditions[] = "AGENT = '{$params['agentCamp']}'";
        $groupBy = "CAMPAIGN_ID";
        $header = "Campaign";
        $sortOrder = $_GET['sort'] == "" ? "CAMPAIGN_ID ASC" : str_replace("|"," ",$_GET['sort']) . ", CAMPAIGN_ID ASC";
    } else {
        $groupBy = "AGENT";
        $header = "Agent ID";
        $sortOrder = $_GET['sort'] == "" ? "CONV_RATE desc, NUM_SALES desc" : str_replace("|"," ",$_GET['sort']) . ", NUM_SALES desc";
    }
    
    if (!empty($params['type'])) {        
        if ($params['type'] == "sb") {
            $conditions[] = "AGENT < 8000";            
        } else {
            $conditions[] = "AGENT >= 8000";
        }
    }

    // Build the WHERE clause
    if (!empty($conditions) && !empty($dateCondition)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions) . " AND $dateCondition";
    } elseif (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    } elseif (!empty($dateCondition)) {
        $whereClause = "WHERE $dateCondition";
    } else {
        $whereClause = ''; // No conditions or date range
    }

    return [
        'whereClause' => $whereClause,
        'groupBy' => $groupBy,
        'header' => $header,
        'sortOrder' => $sortOrder
    ];
}


// Capture parameters from GET request
$params = [
    'range' => $_GET['range'] ?? 'DAILY',
    'start' => $_GET['start'] ?? '',
    'end' => $_GET['end'] ?? '',
    'dept' => $_GET['dept'] ?? '',
    'camp' => $_GET['camp'] ?? '',
    'leadType' => $_GET['leadType'] ?? '',
    'list' => $_GET['list'] ?? '',
    'agent' => $_GET['agent'] ?? '',
    'agentCamp' => $_GET['agentCamp'] ?? '',
    'cluster' => $_GET['cluster'] ?? '',
    'type' => $_GET['type']
];

$queryParams = buildQueryParams($params, $table);
$whereClause = $queryParams['whereClause'];
$groupBy = $queryParams['groupBy'];
$header = $queryParams['header'];
$sortOrder = $queryParams['sortOrder'];



// Generate main query
$sql = "
    SELECT 
        $groupBy AS ID, 
        SUM(NUM_SALES) AS NUM_SALES, 
        SUM(TOTAL_AMOUNT) AS TOTAL_AMOUNT, 
        (SUM(NUM_SALES) / SUM(TOTAL_CALLS)) * 100 AS CONV_RATE,
        SUM(TOTAL_CALLS) AS TOTAL_CALLS,
        SUM(CCs) AS CCs,
        SUM(CCs) / SUM(NUM_SALES) as CC_PCT, 
        SUM(CC_AMT) AS CC_AMT,
        SUM(TOTAL_AMOUNT) / SUM(NUM_SALES) AS AVG_DONATION
    FROM $table $whereClause
    GROUP BY $groupBy
    ORDER BY $sortOrder";

// Totals query
$totalsSql = "
    SELECT 
        SUM(NUM_SALES) AS NUM_SALES, 
        SUM(TOTAL_AMOUNT) AS TOTAL_AMOUNT, 
        SUM(TOTAL_CALLS) AS TOTAL_CALLS,
        SUM(CCs) AS CCs,
        SUM(CC_AMT) AS CC_AMT
    FROM $table 
    $whereClause";

    $getDistinctCount = $pslw->query("select count(distinct($groupBy)) as count from $table $whereClause ")->fetch_assoc();

    $distinctCount = $getDistinctCount['count'];

// Averages query
$avgSql = "
    select 
        sum(NUM_SALES) / $distinctCount as NUM_SALES, 
        SUM(TOTAL_AMOUNT) / $distinctCount as TOTAL_AMOUNT, 
        (SUM(NUM_SALES) / SUM(TOTAL_CALLS)) * 100 as CONV_RATE, 
        (SUM(TOTAL_AMOUNT) / SUM(NUM_SALES)) as AVG_DONATION, 
        SUM(TOTAL_CALLS) / $distinctCount as TOTAL_CALLS, 
        SUM(CCs) / $distinctCount as CCs, 
        SUM(CCs) / SUM(NUM_SALES) as CC_PCT, 
        SUM(CC_AMT) / $distinctCount as CC_AMT
    FROM $table 
    $whereClause";

// Execute queries
$getResults = $pslw->query($sql);
$totalsResult = $pslw->query($totalsSql)->fetch_assoc();
$avgResult = $pslw->query($avgSql)->fetch_assoc();

$colgroup = "
            <colgroup>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
                <col width='11%'>
            </colgroup>";
echo "<div id='collHeaders'>
        <table class='collHeaders' style='position:fixed;width:1024px;background-color:#eee;z-index:99'>
        $colgroup
            <tr style='background-color:#ddd; line-height:30px'>
                <th style='border-left:1px solid #ccc'><span id='$groupBy|ASC' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>$header</span> </th>
                <th><span id='NUM_SALES|DESC' title='Total number of Verified Sales' style='cursor:pointer' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'># of Sales</span> </th>
                <th><span id='TOTAL_AMOUNT|DESC' title='Total dollar amount of Verified Sales' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Total Amount</span> </th>
                <th><span id='CONV_RATE|DESC' title='Total number of sales / total number of calls' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Conv. Rate</span> </th>
                <th><span id='CC_PCT|DESC' title='Percentage of CC sales in given interval' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);' >CC %</span> </th>
                <th><span id='CCs|DESC' title='Total CC sales in given interval' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);' ># CCs</span> </th>
                <th><span id='CC_AMT|DESC' title='Total Dollar Amount of CCs in given interval' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>CC Amt</span> </th>
                <th><span id='AVG_DONATION|DESC' title='Average donation amount of all Verified Sales' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Avg. Donation</span> </th>
                <th><span id='TOTAL_CALLS|DESC' title='Total number of calls taken' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Total Calls</span> </th>
            </tr>
            </table></div><div id='collBody'><table>$colgroup";
echo "<tr id='sql' style='display:none'><td>$sql</td></tr><tr id='avg' style='display:none'><td>$avgSql</td></tr><tr id='total' style='display:none'><td>$totalsSql</td></tr>";
$i = 0;
foreach ($getResults as $result) {   
    $i++; 
    if ( $result['CONV_RATE'] >= 80 ) {
        $dphColor = "#66b266";
    } else if ( $result['CONV_RATE'] >= 75 ) {
        $dphColor = "#eaea32";
    } else {
        $dphColor = "#f00; color:white";
    }
    if ( $result['CC_PCT'] >= .30 ) {
        $ccpColor = "#66b266";
    } else if ( $result['CC_PCT'] >= .25 ) {
        $ccpColor = "#eaea32";
    } else {
        $ccpColor = "#f00; color:white";
    }
    $name = key_exists($result['ID'], $nameArr) ? $nameArr[$result['ID']]["name"] : "SB";
    echo "<tr class='trHover'>
        <td title='$name'>{$result['ID']}</td>
        <td>" . number_format($result['NUM_SALES'], 0) . "</td>
        <td>$" . number_format($result['TOTAL_AMOUNT'], 0) . "</td>
        <td style='font-weight:bold;background-color:$dphColor'>" . number_format($result['CONV_RATE'], 2) . "%</td>
        <td style='font-weight:bold;background-color:$ccpColor;border-left: 1px #ccc solid;'>" . number_format(($result['CC_PCT'] * 100), 2) . "%</td>
        <td>" . number_format($result['CCs'], 0) . "</td>
        <td>$" . number_format($result['CC_AMT'], 0) . "</td>
        <td>$" . number_format($result['AVG_DONATION'], 2) . "</td>
        <td>" . number_format($result['TOTAL_CALLS'], 0) . "</td>
    </tr>";
}
echo "<tr><td>-</td></tr><tr><td>-</td></tr></table></div>";

// Totals and Averages Rows
echo "<div class='collFoot'><table>$colgroup
    <tr>
        <th>Averages:</th>
        <th>" . number_format($avgResult['NUM_SALES'], 0) . "</td>
        <th>$" . number_format($avgResult['TOTAL_AMOUNT'], 0) . "</td>
        <th>" . number_format($avgResult['CONV_RATE'], 2) . "%</td>
        <th>" . number_format(($avgResult['CC_PCT'] * 100), 2) . "%</td>
        <th>" . number_format($avgResult['CCs'], 0) . "</td>
        <th>$" . number_format($avgResult['CC_AMT'], 0) . "</td>
        <th>$" . number_format($avgResult['AVG_DONATION'], 2) . "</td>
        <th>" . number_format($avgResult['TOTAL_CALLS'], 0) . "</td>
    </tr>
    <tr>
        <th>Totals:</th>
        <th>" . number_format($totalsResult['NUM_SALES'], 0) . "</td>
        <th>$" . number_format($totalsResult['TOTAL_AMOUNT'], 0) . "</td>
        <th>-</td>
        <th>-</td>
        <th>" . number_format($totalsResult['CCs'], 0) . "</td>
        <th>$" . number_format($totalsResult['CC_AMT'], 0) . "</td>
        <th>-</td>
        <th>" . number_format($totalsResult['TOTAL_CALLS'], 0) . "</td>
    </tr>
</table></div>";
