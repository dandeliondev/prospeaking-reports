<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once("/srv/www/php_include.php");

// Timestamp for logging
$curTimestamp = date('Y-m-d H:i:s');

// Connect clusters
$pslv = connectToCluster('pslv', $clusters);
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "DPH2");

// CLI flag parsing
$options = getopt("", ["full-day::"]);
if (array_key_exists("full-day", $options)) {
    $fullDay       = true;
    $requestedDate = $options["full-day"] ?: '';
    $date          = $requestedDate ?: date('Y-m-d');
} else {
    $fullDay = false;
    $date    = $curDate;
}

// Validate date
if ($fullDay && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
    echo "{$curTimestamp} Invalid date format. Use YYYY-MM-DD.\n";
    exit(1);
}

// Determine tables & suffix
if ($fullDay) {
    echo "Full Date Import:\n";
    if ($date === $curDate) {
        $table   = 'DAILY_INSERT';
        $archive = 0;
    } else {
        $table   = 'ARCHIVE';
        $archive = 1;
    }
} else {
    $table   = 'DAILY_INSERT';
    $archive = 0;
}

// Skip logic for cron
$flag = '/srv/www/htdocs/ProSpeaking/Reports/DPH2/skip_cron.flag';
if (file_exists($flag)) {
    $remaining = (int)file_get_contents($flag);
    if ($remaining > 0) {
        file_put_contents($flag, $remaining - 1);
        echo "{$curTimestamp} Skipping import (flag set: {$remaining})\n";
        exit;
    }
}

echo "{$curTimestamp} Starting import for {$date} (full-day=".($fullDay?'yes':'no').")\n";

// Time condition
if ($fullDay) {
    $timeCondition   = "BETWEEN '{$date} 00:00:00' AND '{$date} 23:59:59'";
} else {
    $start           = date('Y-m-d H:00:00', strtotime('-30 minutes'));
    $timeCondition   = ">= '{$start}'";
}

echo "Time Condition: $timeCondition\n";

// Cleanup for full-day
if ($fullDay) {
    if ($table === 'DAILY_INSERT') {
        $pslw->query("TRUNCATE {$table}");
    } else {
        $pslw->query("DELETE FROM {$table} WHERE DATE = '{$date}'");
    }
}

// Get psl clusters
$pslKeys = preg_grep('/^psl\\d+$/', array_keys($clusters));
natsort($pslKeys);

$i = 0; //counting total inserts

foreach ($pslKeys as $clusterName) {

    $archiveTable = ($archive == 1 && $clusterName == "psl1") ? "_archive" : "";
    $curTimestamp = date('Y-m-d H:i:s');
    echo "{$curTimestamp} Connecting to {$clusterName}\n";

    $psl       = connectToCluster($clusterName, $clusters);
    $agentType = (int)filter_var($clusterName, FILTER_SANITIZE_NUMBER_INT);

    // Increase concat length
    $psl->query("SET SESSION group_concat_max_len = 1000000");

    // Aggregate stats and get lead_ids per hour
    $statsSql = "
        SELECT
            a.user           AS fronter,
            a.campaign_id,
            b.list_id,
            vl.list_name,
            CASE WHEN b.{$leadTypeField} LIKE '%R' THEN 'ROUST_R' ELSE b.{$leadTypeField} END   AS {$leadTypeField},
            HOUR(a.event_time)    AS hour,
            GROUP_CONCAT(DISTINCT a.lead_id) AS lead_ids,
            SUM(a.wait_sec)      AS wait_sec,
            SUM(a.talk_sec)      AS talk_sec,
            SUM(a.dispo_sec)     AS dispo_sec,
            COUNT(a.lead_id)     AS total_calls,
            SUM(CASE WHEN a.status IN ('NI','XFER') THEN 1 ELSE 0 END) AS final_dispos,
            SUM(CASE WHEN a.status = 'XFER' THEN 1 ELSE 0 END)       AS xfers
        FROM asterisk.vicidial_agent_log{$archiveTable} a
        JOIN asterisk.vicidial_list      b  ON a.lead_id = b.lead_id
        LEFT JOIN asterisk.vicidial_lists vl ON b.list_id = vl.list_id
        WHERE b.list_id >= 1000
          AND a.event_time {$timeCondition}
        GROUP BY a.user, a.campaign_id, b.list_id, vl.list_name,
                 CASE WHEN b.{$leadTypeField} LIKE '%R' THEN 'ROUST_R' ELSE b.{$leadTypeField} END,
                 HOUR(a.event_time)
        ORDER BY a.user";

        /* 
        AI 
          AND (a.user <= 7001 OR a.user >= 7002)
          AND (a.user <= 8101 OR a.user >= 8150)

        user limitations not needed since VFRs all login to pslv
          AND (a.user < 4000 OR a.user >= 5000)
          AND (a.user < 8000 OR a.user >= 9000)
          AND (a.user < 6000 OR a.user > 6100)
          */

        //echo $statsSql; exit;

    $getStats = $psl->query($statsSql);

    // Prepare insert
    $insertSql = "
        INSERT INTO {$table} (
            AGENT, AGENT_NAME, CAMPAIGN_ID, NUM_SALES, TOTAL_AMOUNT,
            FINAL_DISPOS, TOTAL_HOURS, TOTAL_CALLS, RRPM, WRAP,
            TALK, WAIT, CCs, CC_AMT, AGENT_TYPE, PARENT,
            LIST_ID, LIST_NAME, DATE, TYPE, XFERS, HOUR
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        ) ON DUPLICATE KEY UPDATE
            AGENT_NAME   = VALUES(AGENT_NAME),
            NUM_SALES    = VALUES(NUM_SALES),
            TOTAL_AMOUNT = VALUES(TOTAL_AMOUNT),
            FINAL_DISPOS = VALUES(FINAL_DISPOS),
            TOTAL_HOURS  = VALUES(TOTAL_HOURS),
            TOTAL_CALLS  = VALUES(TOTAL_CALLS),
            RRPM         = VALUES(RRPM),
            WRAP         = VALUES(WRAP),
            TALK         = VALUES(TALK),
            WAIT         = VALUES(WAIT),
            CCs          = VALUES(CCs),
            CC_AMT       = VALUES(CC_AMT),
            LIST_NAME    = VALUES(LIST_NAME),
            XFERS        = VALUES(XFERS)";
    $stmt = $pslw->prepare($insertSql);

    // Process each bucket directly
    while ($group = $getStats->fetch_assoc()) {
        // Extract per-hour bucket data
        $fronter     = $group['fronter'];
        $leadIdsCsv  = $group['lead_ids'] ?: '0';
        $typeCode    = explode('_', $group[$leadTypeField])[1] ?? '';
        $userCol   = $typeCode === 'R' ? 'user' : 'title';
        $leadIdCol   = $typeCode === 'R' ? 'lead_id' : 'address3';
        $leadIdsCsv = $typeCode === 'R' ? "select lead_id from asterisk.vicidial_agent_log{$archiveTable} where lead_id in ($leadIdsCsv)
        and hour(event_time) = {$group['hour']}
        and user = $fronter
        and status = 'CCPLED' group by lead_id" : $leadIdsCsv;

        // Fetch sales for these leads
        $saleSql = "
            SELECT
                COUNT(*) AS num_sales,
                SUM({$amountField}) AS total_amount,
                SUM(CASE WHEN status = 'CCPLED' THEN 1 ELSE 0 END) AS CCs,
                SUM(CASE WHEN status = 'CCPLED' THEN {$amountField} ELSE 0 END) AS ccAmt
            FROM asterisk.vicidial_list
            WHERE status IN ('MPLEDG','CCPLED')
              AND $userCol = $fronter
              AND $leadIdCol IN ($leadIdsCsv)";

              if ($fronter == 5001 ) {
                //echo $saleSql . '\r\n';
              }
        $salesRes = ($typeCode === 'R' ? $psl : $pslv)->query($saleSql);
        $saleData = $salesRes->fetch_assoc();

        $finalDispos   = $typeCode === 'R' ? $group['final_dispos'] + $saleData['num_sales'] : $group['final_dispos'];
        
        // Metrics
        $num_sales    = $saleData['num_sales']    ?? 0;
        $total_amount = $saleData['total_amount'] ?? 0;
        $CCs          = $saleData['CCs']          ?? 0;
        $ccAmt        = $saleData['ccAmt']        ?? 0;
        
        $secTime     = $group['wait_sec'] + $group['talk_sec'] + $group['dispo_sec'];
        $totalHours  = $secTime / 3600;
        $rrpm        = $secTime > 0 ? $finalDispos / $secTime / 60 : 0;
        $parent      = $fronter % 2 === 0 ? $fronter : $fronter - 1;

        //since non-roust conv rate is XFERS/Pitches, we need to set roust conv rate as Sales/Pitches. We will just stick the num_sales in the xfer column and let the report work it out
        $xfers   = $typeCode === 'R' ? $num_sales : $group['xfers'];

        // Bind and execute insert
        $stmt->bind_param(
            "iiiiiididiiiiiiiisssii",
            $fronter, $fronter, $group['campaign_id'],
            $num_sales, $total_amount, $finalDispos,
            $totalHours, $group['total_calls'], $rrpm, $group['dispo_sec'],
            $group['talk_sec'], $group['wait_sec'], $CCs, $ccAmt,
            $agentType, $parent, $group['list_id'],
            $group['list_name'], $date, $typeCode,
            $xfers, $group['hour']
        );
        $stmt->execute();
        $i++;
    }

    $stmt->close();
    mysqli_close($psl);
    echo date('Y-m-d H:i:s') . " Imported cluster {$clusterName}\n";
}




///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
/////////////////AI Agent Import////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////





/*
foreach ($pslKeys as $clusterName) {
    $archiveTable = ($archive == 1 && $clusterName == "psl1") ? "_archive" : "";
    $curTimestamp = date('Y-m-d H:i:s');
    echo "{$curTimestamp} Connecting to {$clusterName}\n";

    $psl       = connectToCluster($clusterName, $clusters);
    $agentType = (int)filter_var($clusterName, FILTER_SANITIZE_NUMBER_INT);

    // Increase concat length
    $psl->query("SET SESSION group_concat_max_len = 1000000");

    // Aggregate stats and get lead_ids per hour
    $statsSql = "
        SELECT
            a.campaign_id,
            b.list_id,
            vl.list_name,
            b.{$leadTypeField}    AS {$leadTypeField},
            HOUR(a.event_time)    AS hour,
            GROUP_CONCAT(DISTINCT a.lead_id) AS lead_ids
        FROM asterisk.vicidial_agent_log{$archiveTable} a
        JOIN asterisk.vicidial_list      b  ON a.lead_id = b.lead_id
        LEFT JOIN asterisk.vicidial_lists vl ON b.list_id = vl.list_id
        WHERE 
          ((a.user >= 7001 AND a.user <= 7002)
          OR (a.user >= 8101 AND a.user <= 8150))
          AND a.event_time {$timeCondition}
        GROUP BY a.campaign_id, b.list_id, vl.list_name,
                 b.{$leadTypeField},
                 HOUR(a.event_time)";

        //echo $statsSql; exit;

    $getStats = $psl->query($statsSql);

    // Prepare insert
    $insertSql = "
        INSERT INTO {$table} (
            AGENT, AGENT_NAME, CAMPAIGN_ID, NUM_SALES, TOTAL_AMOUNT,
            FINAL_DISPOS, TOTAL_HOURS, TOTAL_CALLS, RRPM, WRAP,
            TALK, WAIT, CCs, CC_AMT, AGENT_TYPE, PARENT,
            LIST_ID, LIST_NAME, DATE, TYPE, XFERS, HOUR
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        ) ON DUPLICATE KEY UPDATE
            AGENT_NAME   = VALUES(AGENT_NAME),
            NUM_SALES    = VALUES(NUM_SALES),
            TOTAL_AMOUNT = VALUES(TOTAL_AMOUNT),
            FINAL_DISPOS = VALUES(FINAL_DISPOS),
            TOTAL_HOURS  = VALUES(TOTAL_HOURS),
            TOTAL_CALLS  = VALUES(TOTAL_CALLS),
            RRPM         = VALUES(RRPM),
            WRAP         = VALUES(WRAP),
            TALK         = VALUES(TALK),
            WAIT         = VALUES(WAIT),
            CCs          = VALUES(CCs),
            CC_AMT       = VALUES(CC_AMT),
            LIST_NAME    = VALUES(LIST_NAME),
            XFERS        = VALUES(XFERS)";
    $stmt = $pslw->prepare($insertSql);

    // Process each bucket directly
    while ($group = $getStats->fetch_assoc()) {
        // Extract per-hour bucket data
        $fronter     = 9999;
        $leadIdsCsv  = $group['lead_ids'] ?: '0';
        $typeCode    = explode('_', $group[$leadTypeField])[1] ?? '';
        $userCol   = 'title';
        $leadIdCol   = 'address3';

        $getTimeStats = $psl->query("select 
            SUM(a.wait_sec)      AS wait_sec,
            SUM(a.talk_sec)      AS talk_sec,
            SUM(a.dispo_sec)     AS dispo_sec,
            COUNT(a.lead_id)     AS total_calls,
            SUM(CASE WHEN a.status IN ('NI','XFER') THEN 1 ELSE 0 END) AS final_dispos,
            SUM(CASE WHEN a.status = 'XFER' THEN 1 ELSE 0 END)       AS xfers
            from asterisk.vicidial_agent_log a
            where a.lead_id in ($leadIdsCsv);")->fetch_assoc();

        // Fetch sales for these leads
        $saleSql = "
            SELECT
                COUNT(*) AS num_sales,
                SUM({$amountField}) AS total_amount,
                SUM(CASE WHEN status = 'CCPLED' THEN 1 ELSE 0 END) AS CCs,
                SUM(CASE WHEN status = 'CCPLED' THEN {$amountField} ELSE 0 END) AS ccAmt
            FROM asterisk.vicidial_list
            WHERE status IN ('MPLEDG','CCPLED')
              AND $leadIdCol IN ($leadIdsCsv)";

              //echo $saleSql; exit;
        $salesRes = ($typeCode === 'R' ? $psl : $pslv)->query($saleSql);
        $saleData = $salesRes->fetch_assoc();
        
        // Metrics
        $num_sales    = $saleData['num_sales']    ?? 0;
        $total_amount = $saleData['total_amount'] ?? 0;
        $CCs          = $saleData['CCs']          ?? 0;
        $ccAmt        = $saleData['ccAmt']        ?? 0;
        
        $secTime     = $getTimeStats['wait_sec'] + $getTimeStats['talk_sec'] + $getTimeStats['dispo_sec'];
        $totalHours  = $secTime / 3600;
        $rrpm        = $secTime > 0 ? $getTimeStats['final_dispos'] / $secTime / 60 : 0;
        $parent      = $fronter % 2 === 0 ? $fronter : $fronter - 1;

        // Bind and execute insert
        $stmt->bind_param(
            "iiiiiididiiiiiiiisssii",
            $fronter, $fronter, $group['campaign_id'],
            $num_sales, $total_amount, $getTimeStats['final_dispos'],
            $totalHours, $getTimeStats['total_calls'], $rrpm, $getTimeStats['dispo_sec'],
            $getTimeStats['talk_sec'], $getTimeStats['wait_sec'], $CCs, $ccAmt,
            $agentType, $parent, $group['list_id'],
            $group['list_name'], $date, $typeCode,
            $getTimeStats['xfers'], $group['hour']
        );
        $stmt->execute();
    }

    $stmt->close();
    mysqli_close($psl);
    echo date('Y-m-d H:i:s') . " Imported cluster {$clusterName}\n";
}
    */

// Final sync
if ($fullDay && $table === 'DAILY_INSERT') {
    $pslw->query("TRUNCATE DAILY");
    $pslw->query("INSERT INTO DAILY SELECT * FROM DAILY_INSERT");
} elseif ($table === 'DAILY_INSERT') {
    $pslw->query("INSERT INTO DAILY (AGENT,AGENT_NAME,CAMPAIGN_ID,NUM_SALES,TOTAL_AMOUNT,FINAL_DISPOS,TOTAL_HOURS,TOTAL_CALLS,RRPM,WRAP,TALK,WAIT,CCs,CC_AMT,AGENT_TYPE,PARENT,LIST_ID,LIST_NAME,DATE,TYPE,XFERS,HOUR)
        SELECT AGENT,AGENT_NAME,CAMPAIGN_ID,NUM_SALES,TOTAL_AMOUNT,FINAL_DISPOS,TOTAL_HOURS,TOTAL_CALLS,RRPM,WRAP,TALK,WAIT,CCs,CC_AMT,AGENT_TYPE,PARENT,LIST_ID,LIST_NAME,DATE,TYPE,XFERS,HOUR FROM {$table}
        ON DUPLICATE KEY UPDATE AGENT_NAME=VALUES(AGENT_NAME),NUM_SALES=VALUES(NUM_SALES),TOTAL_AMOUNT=VALUES(TOTAL_AMOUNT),FINAL_DISPOS=VALUES(FINAL_DISPOS),TOTAL_HOURS=VALUES(TOTAL_HOURS),TOTAL_CALLS=VALUES(TOTAL_CALLS),RRPM=VALUES(RRPM),WRAP=VALUES(WRAP),TALK=VALUES(TALK),WAIT=VALUES(WAIT),CCs=VALUES(CCs),CC_AMT=VALUES(CC_AMT),LIST_NAME=VALUES(LIST_NAME),XFERS=VALUES(XFERS)");
}

mysqli_close($pslv);
mysqli_close($pslw);
echo date('Y-m-d H:i:s') . " Import complete for {$date} with $i rows\n";
