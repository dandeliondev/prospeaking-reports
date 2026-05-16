<?phprequire_once __DIR__ . '/../../dev/load.php';
$pslw = connectToCluster('pslw', $clusters);
prospeaking_select_db($pslw, 'DPH');

// Function to dynamically build WHERE clause, table selection, grouping, and sorting
function buildQueryParams($params, &$table) {
    global $curDate, $teamArr;

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
    if (!empty($params['team'])) $conditions[] = $teamArr[$params['team']];

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
        $sortOrder = $_GET['sort'] == "" ? "DPH desc, XFERS desc, TOTAL_HOURS desc" : str_replace("|"," ",$_GET['sort']) . ", XFERS desc, TOTAL_HOURS desc";
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



if ( $_GET[ 'combSB' ] == "true" ) {
    $agentSelect = "distinct(PARENT) as ID";
    $agentAVG = "distinct(PARENT)";
    $agentGroup = "group by PARENT";
    if ($_GET['hours'] == "true") {
        $hours = "(GREATEST(SUM(CASE WHEN AGENT = PARENT THEN TOTAL_HOURS ELSE 0 END),SUM(CASE WHEN AGENT = AGENT THEN TOTAL_HOURS ELSE 0 END)))";
    } else {
        $hours = "(GREATEST(SUM(CASE WHEN AGENT = PARENT THEN TOTAL_HOURS ELSE 0 END),SUM(CASE WHEN AGENT = AGENT THEN TOTAL_HOURS ELSE 0 END))-LEAST(SUM(CASE WHEN AGENT = PARENT THEN TOTAL_HOURS ELSE 0 END),SUM(CASE WHEN AGENT = AGENT THEN TOTAL_HOURS ELSE 0 END)))";
    }
    
} else {
    $agentSelect = "AGENT as ID";
    $agentAVG = "AGENT";
    $agentGroup = "group by ID";
    $hours = "SUM(TOTAL_HOURS)";
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
    'team' => $_GET['team'] ?? ''
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
        AGENT_NAME, 
        SUM(NUM_SALES) AS NUM_SALES, 
        SUM(TOTAL_AMOUNT) AS TOTAL_AMOUNT, 
        SUM(XFERS) AS XFERS, 
        (SUM(XFERS) / SUM(FINAL_DISPOS)) * 100 AS CONV_RATE,
        SUM(TOTAL_AMOUNT) / SUM(TOTAL_HOURS) AS DPH, 
        SUM(TOTAL_HOURS) AS TOTAL_HOURS,
        SUM(TOTAL_CALLS) AS TOTAL_CALLS,
        SUM(WRAP) / SUM(TOTAL_CALLS) AS AVG_WRAP,
        SUM(TALK) / SUM(TOTAL_CALLS) AS AVG_TALK,
        SUM(WAIT) / SUM(TOTAL_CALLS) AS AVG_WAIT,
        SUM(CCs) AS CCs,
        SUM(CC_AMT) AS CC_AMT,
        SUM(CC_AMT) / SUM(TOTAL_HOURS) AS CPH,
        SUM(TOTAL_AMOUNT) / SUM(NUM_SALES) AS AVG_DONATION,
        SUM(FINAL_DISPOS) / SUM(TOTAL_HOURS) / 60 AS RRPM
    FROM $table $whereClause
    GROUP BY $groupBy
    ORDER BY $sortOrder";

// Totals query
$totalsSql = "
    SELECT 
        SUM(NUM_SALES) AS NUM_SALES, 
        SUM(TOTAL_AMOUNT) AS TOTAL_AMOUNT,
        SUM(TOTAL_AMOUNT) / SUM(TOTAL_HOURS) AS DPH, 
        SUM(XFERS) AS XFERS,
        SUM(TOTAL_HOURS) AS TOTAL_HOURS,
        SUM(TOTAL_CALLS) AS TOTAL_CALLS,
        SUM(CCs) AS CCs,
        SUM(CC_AMT) AS CC_AMT,
        SUM(CC_AMT) / SUM(TOTAL_HOURS) AS CPH,
        SUM(TOTAL_AMOUNT) / SUM(NUM_SALES) AS AVG_DONATION,
        SUM(FINAL_DISPOS) / SUM(TOTAL_HOURS) / 60 AS RRPM
    FROM $table 
    $whereClause";

    $getDistinctCount = $pslw->query("select count(distinct($groupBy)) as count from $table $whereClause ")->fetch_assoc();

    $distinctCount = $getDistinctCount['count'];

// Averages query
$avgSql = "
    select sum(NUM_SALES) / $distinctCount as NUM_SALES, SUM(TOTAL_AMOUNT) / $distinctCount as TOTAL_AMOUNT, SUM(XFERS) / $distinctCount as XFERS, (SUM(XFERS)/SUM(FINAL_DISPOS))*100 as CONV_RATE, SUM(TOTAL_AMOUNT) / $hours as DPH, (SUM(TOTAL_AMOUNT) / SUM(NUM_SALES)) as AVG_DONATION, $hours / $distinctCount as TOTAL_HOURS, SUM(TOTAL_CALLS) / $distinctCount as TOTAL_CALLS, SUM(FINAL_DISPOS) / SUM(TOTAL_HOURS) / 60 as RRPM, SUM(WRAP) / SUM(TOTAL_CALLS) as AVG_WRAP, SUM(TALK) / SUM(TOTAL_CALLS) as AVG_TALK, SUM(WAIT) / SUM(TOTAL_CALLS) as AVG_WAIT, SUM(CCs) / $distinctCount as CCs, SUM(CCs)/ SUM(NUM_SALES) as CC_PCT, SUM(CC_AMT) / $distinctCount as CC_AMT, SUM(CC_AMT) / SUM(TOTAL_HOURS) as CPH, SUM(FINAL_DISPOS) / count($groupBy) as FINAL_DISPOS
    FROM $table 
    $whereClause";

// Execute queries
$getResults = $pslw->query($sql);
$totalsResult = $pslw->query($totalsSql)->fetch_assoc();
$avgResult = $pslw->query($avgSql)->fetch_assoc();

$colgroup = "
            <colgroup>
                <col width='100px'>
                <col width='124px'>
                <col width='70px'>
                <col width='50px'>
                <col width='50px'>
                <col width='50px'>
                <col width='50px'>
                <col width='60px'>
                <col width='60px'>
                <col width='60px'>
                <col width='50px'>
                <col width='50px'>
                <col width='50px'>
                <col width='50px'>
                <col width='40px'>
                <col width='50px'>
                <col width='60px'>
            </colgroup>";
echo "<div id='collHeaders'>
        <table class='collHeaders' style='position:fixed;width:1024px;background-color:#eee;z-index:99'>
        $colgroup
            <tr style='background-color:#ddd'>
                <th style='border-left:1px solid #ccc'><span id='$groupBy|ASC' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>$header</span> </th>
                <th><span id='AGENT_NAME|ASC' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Agent Name</span> </th>
                <th><span id='NUM_SALES|DESC' title='Total number of Verified Sales' style='cursor:pointer' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'># of<br />Sales</span> </th>
                <th><span id='TOTAL_AMOUNT|DESC' title='Total dollar amount of Verified Sales' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Total Amount</span> </th>
                <th><span id='DPH|DESC' title='Total dollar amount sold per hour calling (SPH)' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id)'>DPH</span> </th>
                <th><span id='XFERS|DESC' title='Total number of XFERs' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Xfers</span> </th>
                <th><span id='CONV_RATE|DESC' title='Total number of sales / total number of pitches' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Conv. Rate</span> </th>
                <th><span id='AVG_DONATION|DESC' title='Average donation amount of all Verified Sales' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Avg. Donation</span> </th>
                <th><span id='TOTAL_HOURS|DESC' title='Total number of dialed hours (may differ from total PAY hours' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Total<br />
                    Hours</span> </th>
                <th><span id='TOTAL_CALLS|DESC' title='Total number of calls taken' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Total<br />
                    Calls</span> </th>
                <th><span id='RRPM|DESC' title='Number of pitches per minute' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>RPM</span> </th>
                <th><span id='AVG_WAIT|DESC' title='Average time spent waiting for the next call' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Avg. Wait</span> </th>
                <th><span id='AVG_TALK|DESC' title='Average talk time' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Avg. Talk</span> </th>
                <th><span id='AVG_WRAP|DESC' title='Average time spent after the cx hungup before setting dispo' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>Avg. Wrap</span> </th>
                <th><span id='CCs|DESC' title='Total CCs in given interval' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);' >CCs</span> </th>
                <th><span id='CC_AMT|DESC' title='Total Amount of CCs in given interval' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>CC<br />Amt</span> </th>
                <th><span id='CPH|DESC' title='Total CCs per Hour' style='cursor:pointer' onclick='clearIntervals(); showReportInterval(this.id);'>CPH</span> </th>
            </tr>
            </table></div><div id='collBody'><table>$colgroup";
echo "<tr id='sql' style='display:none'><td>$sql</td></tr><tr id='avg' style='display:none'><td>$avgSql</td></tr><tr id='total' style='display:none'><td>$totalsSql</td></tr>";
$i = 0;
foreach ($getResults as $result) {   
    $i++; 
    if ( $result['DPH'] >= $avgResult['DPH'] ) {
        $dphColor = "#66b266";
    } else if ( $result['DPH'] >= $avgResult['DPH'] - ( $avgResult['DPH'] * 0.25 ) ) {
        $dphColor = "#eaea32";
    } else {
        $dphColor = "#f00; color:white";
    }
    echo "<tr class='trHover'>
        <td>{$result['ID']}</td>
        <td>{$result['AGENT_NAME']}</td>
        <td>" . number_format($result['NUM_SALES'], 0) . "</td>
        <td>$" . number_format($result['TOTAL_AMOUNT'], 0) . "</td>
        <td style='font-weight:bold;background-color:$dphColor'>$" . number_format($result['DPH'], 2) . "</td>
        <td>" . number_format($result['XFERS'], 0) . "</td>
        <td>" . number_format($result['CONV_RATE'], 2) . "%</td>
        <td>$" . number_format($result['AVG_DONATION'], 2) . "</td>
        <td>" . number_format($result['TOTAL_HOURS'], 2) . "</td>
        <td>" . number_format($result['TOTAL_CALLS'], 0) . "</td>
        <td>" . number_format($result['RRPM'], 2) . "</td>
        <td>" . number_format($result['AVG_WAIT'], 2) . "</td>
        <td>" . number_format($result['AVG_TALK'], 2) . "</td>
        <td>" . number_format($result['AVG_WRAP'], 2) . "</td>
        <td>" . number_format($result['CCs'], 0) . "</td>
        <td>$" . number_format($result['CC_AMT'], 0) . "</td>
        <td>$" . number_format($result['CPH'], 2) . "</td>
    </tr>";
}
echo "<tr><td>-</td></tr><tr><td>-</td></tr></table></div>";

// Totals and Averages Rows
echo "<div class='collFoot'><table>$colgroup
    <tr>
        <th>Averages:</th>
        <th>-</td>
        <th>" . number_format($avgResult['NUM_SALES'], 0) . "</td>
        <th>$" . number_format($avgResult['TOTAL_AMOUNT'], 0) . "</td>
        <th>$" . number_format($avgResult['DPH'], 2) . "</td>
        <th>" . number_format($avgResult['XFERS'], 0) . "</td>
        <th>" . number_format($avgResult['CONV_RATE'], 2) . "%</td>
        <th>$" . number_format($avgResult['AVG_DONATION'], 2) . "</td>
        <th>" . number_format($avgResult['TOTAL_HOURS'], 2) . "</td>
        <th>" . number_format($avgResult['TOTAL_CALLS'], 0) . "</td>
        <th>" . number_format($avgResult['RRPM'], 2) . "</td>
        <th>" . number_format($avgResult['AVG_WAIT'], 2) . "</td>
        <th>" . number_format($avgResult['AVG_TALK'], 2) . "</td>
        <th>" . number_format($avgResult['AVG_WRAP'], 2) . "</td>
        <th>" . number_format($avgResult['CCs'], 0) . "</td>
        <th>$" . number_format($avgResult['CC_AMT'], 0) . "</td>
        <th>$" . number_format($avgResult['CPH'], 2) . "</td>
    </tr>
    <tr>
        <th>Totals:</th>
        <th>$i</th>
        <th>" . number_format($totalsResult['NUM_SALES'], 0) . "</td>
        <th>$" . number_format($totalsResult['TOTAL_AMOUNT'], 0) . "</td>
        <th>-</td>
        <th>" . number_format($totalsResult['XFERS'], 0) . "</td>
        <th>-</td>
        <th>-</td>
        <th>" . number_format($totalsResult['TOTAL_HOURS'], 2) . "</td>
        <th>" . number_format($totalsResult['TOTAL_CALLS'], 0) . "</td>
        <th>-</td>
        <th>-</td>
        <th>-</td>
        <th>-</td>
        <th>" . number_format($totalsResult['CCs'], 0) . "</td>
        <th>$" . number_format($totalsResult['CC_AMT'], 0) . "</td>
        <th>-</td>
    </tr>
</table></div>";



prospeaking_close($pslw);