<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once("/srv/www/php_include.php");

echo "\r\n\r\n$curTimeStamp \r\n";
/*
$flag = '/srv/www/htdocs/ProSpeaking/Reports/DPH2/skip_cron.flag';
if (file_exists($flag)) {
    $remaining = (int)file_get_contents($flag);
    if ($remaining >= 1) {
        file_put_contents($flag, $remaining - 1);
        echo "{$curTimestamp} Skipping import (flag set) $remaining time(s)\n";
        exit;
    } 
}*/

$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "DPH2");
$psl1 = connectToCluster('psl1', $clusters);
$psl2 = connectToCluster('psl2', $clusters);

$date = isset($_GET['date']) ? date("Y-m-d", strtotime($_GET['date'])) : $yestDate;
$table = (isset($_GET['date']) && date("Y-m-d", strtotime($_GET['date'])) != $yestDate) ? "ARCHIVE" : "DAILY";
//$archive = ($table === "ARCHIVE") ? "_archive" : "";
$archive = "";

// Retrieve cleaned-up stats for updating DPH database
$sql = "
    SELECT a.user, a.campaign_id, b.$departmentKeyField AS dept, b.list_id, b.$leadTypeField,
           SUM(a.wait_sec) AS wait_sec,
           SUM(a.talk_sec) AS talk_sec,
           SUM(a.dispo_sec) AS dispo_sec,
           COUNT(a.lead_id) AS total_calls,
           COUNT(CASE WHEN a.status IN ('NI', 'XFER') THEN 1 END) AS final_dispos,
           COUNT(CASE WHEN a.status = 'XFER' THEN 1 END) AS xfers
    FROM asterisk.vicidial_agent_log$archive a
    JOIN asterisk.vicidial_list b ON a.lead_id = b.lead_id
    WHERE b.list_id >= 1000
      AND a.user < 4000
      AND a.event_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    GROUP BY a.user, a.campaign_id, b.$departmentKeyField, b.list_id, b.$leadTypeField
    ORDER BY a.user;";


// Prepare the update statement to reuse in the loop
$stmt = $pslw->prepare("
    UPDATE $table 
    SET TOTAL_HOURS = ?, WAIT = ?, TALK = ?, WRAP = ? 
    WHERE AGENT = ? AND CAMPAIGN_ID = ? AND DEPTKEY = ? AND LIST_ID = ? AND DATE = ? AND AGENT_TYPE = ?
");

$getStats = $psl1->query($sql);
// Loop through stats and execute updates
foreach ($getStats as $stats) {
    $agent = $stats['user'];
    $campaign = $stats['campaign_id'];
    $dept = $stats['dept'];
    $list = $stats['list_id'];
    $totalCalls = max(1, $stats['total_calls']); // Avoid division by zero
    $wait = $stats['wait_sec'] / $totalCalls;
    $talk = $stats['talk_sec'] / $totalCalls;
    $dispo = $stats['dispo_sec'] / $totalCalls;
    $total = ($stats['wait_sec'] + $stats['talk_sec'] + $stats['dispo_sec']) / 3600;

    $agentType = 1;

    // Bind parameters and execute the prepared statement
    $stmt->bind_param("ddddissisi", $total, $wait, $talk, $dispo, $agent, $campaign, $dept, $list, $date, $agentType);
    $stmt->execute();
}

$getStats = $psl2->query($sql);
// Loop through stats and execute updates
foreach ($getStats as $stats) {
    $agent = $stats['user'];
    $campaign = $stats['campaign_id'];
    $dept = $stats['dept'];
    $list = $stats['list_id'];
    $totalCalls = max(1, $stats['total_calls']); // Avoid division by zero
    $wait = $stats['wait_sec'] / $totalCalls;
    $talk = $stats['talk_sec'] / $totalCalls;
    $dispo = $stats['dispo_sec'] / $totalCalls;
    $total = ($stats['wait_sec'] + $stats['talk_sec'] + $stats['dispo_sec']) / 3600;

    $agentType = 2;

    // Bind parameters and execute the prepared statement
    $stmt->bind_param("ddddissisi", $total, $wait, $talk, $dispo, $agent, $campaign, $dept, $list, $date, $agentType);
    $stmt->execute();
}

// Copy DAILY data to ARCHIVE if applicable, then truncate DAILY
if ($table === "DAILY") {
    $insertDaily = $pslw->query("INSERT INTO ARCHIVE SELECT * FROM DAILY");
    if ($insertDaily) {
        $pslw->query("TRUNCATE DAILY");
        $pslw->query("TRUNCATE DAILY_INSERT");
    }
}
echo "$curTimeStamp: Cleanup Complete";
// Close the statement and the database connection
$stmt->close();
mysqli_close($psl1);
mysqli_close($psl2);
mysqli_close($pslw);