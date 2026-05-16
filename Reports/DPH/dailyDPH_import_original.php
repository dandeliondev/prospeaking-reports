<?php
require_once __DIR__ . '/../../config/bootstrap.php';
$psl1 = connectToCluster('psl1', $clusters);
$pslv = connectToCluster('pslv', $clusters);
$pslw = connectToCluster('pslw', $clusters);
prospeaking_select_db($pslw, "DPH");

if (isset($_GET['date'])) {
    $date = date("Y-m-d", strtotime($_GET['date']));
    $table = ($date == $curDate) ? "DAILY_INSERT" : "ARCHIVE";
    $clean = $pslw->query("delete from $table where Date = '$date'");
    $archive = ($date == $curDate) ? "" : "_archive";
    $daily = $date == $curDate ? 1 : 0;
} else {
    $date = $curDate;
    $table = "DAILY_INSERT";
    $archive = "";
    $truncate = $pslw->query("truncate DAILY_INSERT");
    $daily = 1;
}


$getStats = $psl1->query("
    SELECT a.user AS fronter, a.campaign_id, b.$departmentKeyField, b.list_id, vl.list_name, b.$leadTypeField,
           SUM(a.wait_sec) AS wait_sec,
           SUM(a.talk_sec) AS talk_sec,
           SUM(a.dispo_sec) AS dispo_sec,
           COUNT(a.lead_id) AS total_calls,
           COUNT(CASE WHEN a.status IN ('NI', 'XFER') THEN 1 END) AS final_dispos,
           COUNT(CASE WHEN a.status = 'XFER' THEN 1 END) AS xfers
    FROM asterisk.vicidial_agent_log$archive a
    JOIN asterisk.vicidial_list b ON a.lead_id = b.lead_id
    LEFT JOIN asterisk.vicidial_lists vl ON b.list_id = vl.list_id
    WHERE b.list_id >= 1000
      AND (a.user < 4000 OR a.user >= 5000)
      AND (a.user < 8000 or a.user >= 9000)
      AND a.event_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    GROUP BY a.user, a.campaign_id, b.list_id, b.$departmentKeyField, vl.list_name,
             CASE WHEN b.$leadTypeField LIKE '%R' THEN 'ROUST_R' ELSE b.$leadTypeField END
    ORDER BY a.user;
");


// Store fronter stats to handle sales separately
$fronterStats = [];
foreach ($getStats as $stat) {
    // Append stats to each fronter as an array of records
    $fronterStats[$stat['fronter']][] = $stat;
}

$stmt = $pslw->prepare("INSERT INTO DPH.$table (
    AGENT, AGENT_NAME, CAMPAIGN_ID, DEPTKEY, NUM_SALES, TOTAL_AMOUNT, FINAL_DISPOS, TOTAL_HOURS, TOTAL_CALLS, RRPM, WRAP, TALK, WAIT, CCs, CC_AMT, AGENT_TYPE, PARENT, LIST_ID, LIST_NAME, DATE, TYPE, XFERS) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    DATE_TIME = CURRENT_TIMESTAMP, 
    AGENT_NAME = VALUES(AGENT_NAME), 
    NUM_SALES = VALUES(NUM_SALES), 
    TOTAL_AMOUNT = VALUES(TOTAL_AMOUNT), 
    FINAL_DISPOS = VALUES(FINAL_DISPOS), 
    TOTAL_HOURS = VALUES(TOTAL_HOURS), 
    TOTAL_CALLS = VALUES(TOTAL_CALLS), 
    RRPM = VALUES(RRPM),
    WRAP = VALUES(WRAP), 
    TALK = VALUES(TALK), 
    WAIT = VALUES(WAIT), 
    CCs = VALUES(CCs),  
    CC_AMT = VALUES(CC_AMT),
    LIST_NAME = VALUES(LIST_NAME)"
);


$agentType = 1;

foreach ($fronterStats as $fronter => $stats) {
    $groupedStats = [];

    foreach ($stats as $stat) {
        $groupKey = $stat['campaign_id'] . '_' . $stat[$departmentKeyField] . '_' . $stat['list_id'] . '_' . $stat[$leadTypeField];
        
        if (!isset($groupedStats[$groupKey])) {
            $groupedStats[$groupKey] = [
                'wait_sec' => 0,
                'talk_sec' => 0,
                'dispo_sec' => 0,
                'total_calls' => 0,
                'final_dispos' => 0,
                'xfers' => 0,
                'campaign_id' => $stat['campaign_id'],
                '$departmentKeyField' => $stat[$departmentKeyField],
                'list_id' => $stat['list_id'],
                'list_name' => $stat['list_name'], // Add list_name
                $leadTypeField => $stat[$leadTypeField],
            ];
        }
        

        $groupedStats[$groupKey]['wait_sec'] += $stat['wait_sec'];
        $groupedStats[$groupKey]['talk_sec'] += $stat['talk_sec'];
        $groupedStats[$groupKey]['dispo_sec'] += $stat['dispo_sec'];
        $groupedStats[$groupKey]['total_calls'] += $stat['total_calls'];
        $groupedStats[$groupKey]['final_dispos'] += $stat['final_dispos'];
        $groupedStats[$groupKey]['xfers'] += $stat['xfers'];
    }

    foreach ($groupedStats as $groupKey => $group) {
        
        $type = explode("_", $group[$leadTypeField]);
        $type = isset($type[1]) ? $type[1] : '';

        $listCheck = $type == "R" ? "list_id" : "security_phrase";
        $userCheck = $type == "R" ? "user" : "title";

        $sql = "SELECT COUNT(*) AS num_sales, SUM($amountField) AS total_amount, 
                       SUM(CASE WHEN status = 'CCPLED' THEN 1 ELSE 0 END) AS CCs, 
                       SUM(CASE WHEN status = 'CCPLED' THEN $amountField ELSE 0 END) AS ccAmt
                FROM asterisk.vicidial_list
                WHERE status IN ('MPLEDG', 'CCPLED')
                  AND last_local_call_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
                  AND $userCheck = $fronter
                  AND $listCheck = '{$group['list_id']}'
                  AND $departmentKeyField = '{$group['$departmentKeyField']}'
                  AND $leadTypeField like '%$type';";

        if ($type == "R") {
            $getSales = $psl1->query($sql);
        } else {
            $getSales = $pslv->query($sql);
        }
        
        $saleData = $getSales->fetch_assoc();
        
        $num_sales = isset($saleData['num_sales']) ? $saleData['num_sales'] : 0;
        $total_amount = isset($saleData['total_amount']) ? $saleData['total_amount'] : 0;
        $CCs = isset($saleData['CCs']) ? $saleData['CCs'] : 0;
        $ccAmt = isset($saleData['ccAmt']) ? $saleData['ccAmt'] : 0;
        
        /*
        //get Sales data from psl1 (for roust)
        $getSales = $psl1->query($sql);
        $saleData = $getSales->fetch_assoc();
        
        $num_sales2 = isset($saleData['num_sales']) ? $saleData['num_sales'] : 0;
        $total_amount2 = isset($saleData['total_amount']) ? $saleData['total_amount'] : 0;
        $CCs2 = isset($saleData['CCs']) ? $saleData['CCs'] : 0;
        $ccAmt2 = isset($saleData['ccAmt']) ? $saleData['ccAmt'] : 0;

        //combine totals
        $num_sales += $num_sales2;
        $total_amount += $total_amount2;
        $CCs += $CCs2;
        $ccAmt += $ccAmt2; */

        $time = $group['wait_sec'] + $group['talk_sec'] + $group['dispo_sec'];
        $totalHours = $time / 3600;
        $rrpm = ($time == 0) ? 0 : $group['final_dispos'] / $time / 60;

        $campaign_id = $group['campaign_id'];
        $list_id = $group['list_id'];
        $$departmentKeyField = $group['$departmentKeyField'];
        $parent = $fronter % 2 == 0 ? $fronter : $fronter - 1;

        // Bind LIST_NAME parameter
        $stmt->bind_param("isssssssisssssssissssi",
            $fronter, $fronter, $campaign_id, $$departmentKeyField, $num_sales, $total_amount,
            $group['final_dispos'], $totalHours, $group['total_calls'], $rrpm, $group['dispo_sec'],
            $group['talk_sec'], $group['wait_sec'], $CCs, $ccAmt, $agentType,
            $parent, $list_id, $group['list_name'], $date, $type, $group['xfers']
        );

        $stmt->execute();
    }
}

echo "$curDate: Successfully imported $date psl1 DPH data. Please wait... <br />";

$psl2 = connectToCluster('psl2', $clusters);

$getStats = $psl2->query("
    SELECT a.user AS fronter, a.campaign_id, b.$departmentKeyField, b.list_id, vl.list_name, b.$leadTypeField,
           SUM(a.wait_sec) AS wait_sec,
           SUM(a.talk_sec) AS talk_sec,
           SUM(a.dispo_sec) AS dispo_sec,
           COUNT(a.lead_id) AS total_calls,
           COUNT(CASE WHEN a.status IN ('NI', 'XFER') THEN 1 END) AS final_dispos,
           COUNT(CASE WHEN a.status = 'XFER' THEN 1 END) AS xfers
    FROM asterisk.vicidial_agent_log a
    JOIN asterisk.vicidial_list b ON a.lead_id = b.lead_id
    LEFT JOIN asterisk.vicidial_lists vl ON b.list_id = vl.list_id
    WHERE b.list_id >= 1000
      AND (a.user < 4000 OR a.user >= 5000)
      AND a.event_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    GROUP BY a.user, a.campaign_id, b.list_id, b.$departmentKeyField, vl.list_name,
             CASE WHEN b.$leadTypeField LIKE '%R' THEN 'ROUST_R' ELSE b.$leadTypeField END
    ORDER BY a.user;
");



// Store fronter stats to handle sales separately
$fronterStats = [];
foreach ($getStats as $stat) {
    // Append stats to each fronter as an array of records
    $fronterStats[$stat['fronter']][] = $stat;
}

$stmt = $pslw->prepare("INSERT INTO DPH.$table (
    AGENT, AGENT_NAME, CAMPAIGN_ID, DEPTKEY, NUM_SALES, TOTAL_AMOUNT, FINAL_DISPOS, TOTAL_HOURS, TOTAL_CALLS, RRPM, WRAP, TALK, WAIT, CCs, CC_AMT, AGENT_TYPE, PARENT, LIST_ID, LIST_NAME, DATE, TYPE, XFERS) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    DATE_TIME = CURRENT_TIMESTAMP, 
    AGENT_NAME = VALUES(AGENT_NAME), 
    NUM_SALES = VALUES(NUM_SALES), 
    TOTAL_AMOUNT = VALUES(TOTAL_AMOUNT), 
    FINAL_DISPOS = VALUES(FINAL_DISPOS), 
    TOTAL_HOURS = VALUES(TOTAL_HOURS), 
    TOTAL_CALLS = VALUES(TOTAL_CALLS), 
    RRPM = VALUES(RRPM),
    WRAP = VALUES(WRAP), 
    TALK = VALUES(TALK), 
    WAIT = VALUES(WAIT), 
    CCs = VALUES(CCs),  
    CC_AMT = VALUES(CC_AMT),
    LIST_NAME = VALUES(LIST_NAME)"
);

$agentType = 2;

foreach ($fronterStats as $fronter => $stats) {
    $groupedStats = [];

    foreach ($stats as $stat) {
        $groupKey = $stat['campaign_id'] . '_' . $stat[$departmentKeyField] . '_' . $stat['list_id'] . '_' . $stat[$leadTypeField];
        
        if (!isset($groupedStats[$groupKey])) {
            $groupedStats[$groupKey] = [
                'wait_sec' => 0,
                'talk_sec' => 0,
                'dispo_sec' => 0,
                'total_calls' => 0,
                'final_dispos' => 0,
                'xfers' => 0,
                'campaign_id' => $stat['campaign_id'],
                '$departmentKeyField' => $stat[$departmentKeyField],
                'list_id' => $stat['list_id'],
                'list_name' => $stat['list_name'], // Add list_name
                $leadTypeField => $stat[$leadTypeField],
            ];
        }
        

        $groupedStats[$groupKey]['wait_sec'] += $stat['wait_sec'];
        $groupedStats[$groupKey]['talk_sec'] += $stat['talk_sec'];
        $groupedStats[$groupKey]['dispo_sec'] += $stat['dispo_sec'];
        $groupedStats[$groupKey]['total_calls'] += $stat['total_calls'];
        $groupedStats[$groupKey]['final_dispos'] += $stat['final_dispos'];
        $groupedStats[$groupKey]['xfers'] += $stat['xfers'];
    }

    foreach ($groupedStats as $groupKey => $group) {
        
        $type = explode("_", $group[$leadTypeField]);
        $type = isset($type[1]) ? $type[1] : '';        

        $sql = "SELECT COUNT(*) AS num_sales, SUM($amountField) AS total_amount, 
                       SUM(CASE WHEN status = 'CCPLED' THEN 1 ELSE 0 END) AS CCs, 
                       SUM(CASE WHEN status = 'CCPLED' THEN $amountField ELSE 0 END) AS ccAmt
                FROM asterisk.vicidial_list
                WHERE status IN ('MPLEDG', 'CCPLED')
                  AND last_local_call_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
                  AND title = $fronter
                  AND security_phrase = '{$group['list_id']}'
                  AND $departmentKeyField = '{$group['$departmentKeyField']}'
                  AND $leadTypeField like '%$type';";
        
        $getSales = $pslv->query($sql);
        $saleData = $getSales->fetch_assoc();
        
        $num_sales = isset($saleData['num_sales']) ? $saleData['num_sales'] : 0;
        $total_amount = isset($saleData['total_amount']) ? $saleData['total_amount'] : 0;
        $CCs = isset($saleData['CCs']) ? $saleData['CCs'] : 0;
        $ccAmt = isset($saleData['ccAmt']) ? $saleData['ccAmt'] : 0;
        

        $time = $group['wait_sec'] + $group['talk_sec'] + $group['dispo_sec'];
        $totalHours = $time / 3600;
        $rrpm = ($time == 0) ? 0 : $group['final_dispos'] / $time / 60;

        $campaign_id = $group['campaign_id'];
        $list_id = $group['list_id'];
        $$departmentKeyField = $group['$departmentKeyField'];
        $parent = $fronter % 2 == 0 ? $fronter : $fronter - 1;

        // Bind LIST_NAME parameter
        $stmt->bind_param("isssssssisssssssissssi",
            $fronter, $fronter, $campaign_id, $$departmentKeyField, $num_sales, $total_amount,
            $group['final_dispos'], $totalHours, $group['total_calls'], $rrpm, $group['dispo_sec'],
            $group['talk_sec'], $group['wait_sec'], $CCs, $ccAmt, $agentType,
            $parent, $list_id, $group['list_name'], $date, $type, $group['xfers']
        );

        $stmt->execute();


    }
}

if ($daily == 1) {
    echo "$curDate: Successfully imported $date psl2 DPH data. Please wait again...<br />";
    $pslw->query("truncate DAILY;");
    $pslw->query("insert into DAILY select * from DAILY_INSERT");
    echo "$curDate: Successfully updated $date DPH.";
} else {
    echo "$curDate: Successfully imported $date psl2 DPH data and updated $date DPH";
}


prospeaking_close($psl1);
prospeaking_close($psl2);
prospeaking_close($pslv);
prospeaking_close($pslw);
