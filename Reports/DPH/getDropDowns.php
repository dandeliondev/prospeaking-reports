<?php
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
include_once( "/srv/www/php_include.php" );

$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "DPH");

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
    if (!empty($params['campaign'])) $conditions[] = "CAMPAIGN_ID = '{$params['campaign']}'";
    if (!empty($params['leadType'])) $conditions[] = "TYPE = '{$params['leadType']}'";
    if (!empty($params['list'])) $conditions[] = "LIST_ID = '{$params['list']}'";
    if (!empty($params['cluster'])) $conditions[] = "AGENT_TYPE = '{$params['cluster']}'";

    // Adjust grouping and sorting based on agent/agentCamp
    if (!empty($params['agent']) || !empty($params['agentCamp'])) {
        $conditions[] = "AGENT = '{$params['agent']}{$params['agentCamp']}'";
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

    return $whereClause;
}


// Uncomment this array and comment out the next to turn on dynamic dropdowns
/*$params = [
    'range' => $_GET['range'] ?? 'DAILY',
    'start' => $_GET['start'] ?? '',
    'end' => $_GET['end'] ?? '',
    'dept' => $_GET['dept'] ?? '',
    'campaign' => $_GET['campaign'] ?? '',
    'leadType' => $_GET['leadType'] ?? '',
    'list' => $_GET['list'] ?? '',
    'agent' => $_GET['agent'] ?? '',
    'agentCamp' => $_GET['agentCamp'] ?? '',
    'cluster' => $_GET['cluster'] ?? ''
];*/
$params = [
    'range' => $_GET['range'] ?? 'DAILY',
    'start' => $_GET['start'] ?? '',
    'end' => $_GET['end'] ?? ''
];

$whereClause = buildQueryParams($params, $table);

$results = "";

//cluster
$clusters = $pslw->query( "select distinct(AGENT_TYPE) from $table $whereClause order by AGENT_TYPE asc");
$results .= "<option value selected style='background-color:#ccc'>--Cluster--</option>";
foreach ( $clusters as $row ) {
    $selected = $_GET['cluster'] == $row[ 'AGENT_TYPE' ] ? "selected" : "";
    $results .= "<option $selected value='{$row[ 'AGENT_TYPE' ]}'>psl{$row['AGENT_TYPE' ]}</option>";
};

$results .= "|"; //delimiter for responseText

//dept
$depts = $pslw->query( "select distinct(DEPTKEY) from $table $whereClause order by DEPTKEY asc" );
$results .= "<option value selected style='background-color:#ccc'>--DeptKey--</option>";
foreach ( $depts as $row ) {
    if ($row[ 'DEPTKEY' ] == "CD") {
        $deptName = "C-Data";
    } else {
        $deptName = $row[ 'DEPTKEY' ];
    }
    $selected = $_GET['dept'] == $row[ 'DEPTKEY' ] ? "selected" : "";
    $results .= "<option $selected value='{$row[ 'DEPTKEY' ]}'>$deptName</option>";
};

$results .= "|"; //delimiter for responseText

//list
$listIDs = $pslw->query( "select distinct(LIST_ID), LIST_NAME from $table $whereClause order by LIST_ID asc" );
$results .= "<option value selected style='background-color:#ccc'>--ListName--</option>";
foreach ( $listIDs as $list ) {
    $selected = $_GET['list'] == $list[ 'LIST_ID' ] ? "selected" : "";
    $results .= "<option $selected value='{$list[ 'LIST_ID' ]}'>{$list['LIST_NAME' ]}</option>";
};

$results .= "|"; //delimiter for responseText

//agentCamp
$agents = $pslw->query( "select distinct(AGENT), AGENT_NAME from $table $whereClause order by AGENT asc" );
$results .= "<option value selected style='background-color:#ccc'>--Agent by Campaign--</option>";
foreach ( $agents as $row ) {
    $selected = $_GET['agentCamp'] == $row[ 'AGENT' ] ? "selected" : "";
    $results .= "<option $selected value='{$row[ 'AGENT' ]}'>{$row[ 'AGENT' ]} - {$row[ 'AGENT_NAME' ]}</option>";
}

$results .= "|"; //delimiter for responseText

//team
$results .= "<option value='' selected='selected' style='background-color:#ccc'>--Team--</option>
            <option value='Davao'>Davao</option>
            <option value='Digos'>Digos</option>
            <option value='Iloilo'>Iloilo</option>
            <option value='Roust'>Roust</option>";

$results .= "|"; //delimiter for responseText

//campaign
$camps = $pslw->query( "select distinct(CAMPAIGN_ID) from $table $whereClause order by CAMPAIGN_ID asc" );
$results .= "<option value selected style='background-color:#ccc'>--Campaign--</option>";
foreach ( $camps as $row ) {
    $selected = $_GET['campaign'] == $row[ 'CAMPAIGN_ID' ] ? "selected" : "";
    $results .= "<option $selected value='{$row[ 'CAMPAIGN_ID' ]}'>{$row[ 'CAMPAIGN_ID' ]}</option>";
};

$results .= "|"; //delimiter for responseText

//leadType
$leadTypes = $pslw->query( "select distinct(TYPE) from $table $whereClause order by TYPE asc" );
$results .= "<option value selected style='background-color:#ccc'>--LeadType--</option>";
foreach ( $leadTypes as $type ) {
    $selected = $_GET['leadType'] == $type[ 'TYPE' ] ? "selected" : "";
    $display = $type[ 'TYPE' ] == "R" ? "ROUST" : $type[ 'TYPE' ];
    $results .= "<option $selected value='{$type[ 'TYPE' ]}'>$display</option>";
};

$results .= "|"; //delimiter for responseText

//agent
$agents = $pslw->query( "select distinct(AGENT), AGENT_NAME from $table $whereClause order by AGENT asc" );
$results .= "<option value selected style='background-color:#ccc'>--Agent by Day--</option>";
foreach ( $agents as $row ) {
    $selected = $_GET['agent'] == $row[ 'AGENT' ] ? "selected" : "";
    $results .= "<option $selected value='{$row[ 'AGENT' ]}'>{$row[ 'AGENT' ]} - {$row[ 'AGENT_NAME' ]}</option>";
}





echo $results;





