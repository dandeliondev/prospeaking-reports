<?phprequire_once __DIR__ . '/../../dev/load.php';

$psl1 = connectToCluster('psl1', $clusters);
$pslv = connectToCluster('pslv', $clusters);
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "VFR");

if (isset($_GET['date'])) {
    $date = date("Y-m-d", strtotime($_GET['date']));
    $table = ($date == $curDate) ? "DAILY_INSERT" : "ARCHIVE";
    $pslw->query("DELETE FROM $table WHERE Date = '$date'");
    $archive = ($date == $curDate) ? "" : "_archive";
    $daily = ($date == $curDate) ? 1 : 0;
} else {
    $date = $curDate;
    $table = "DAILY_INSERT";
    $archive = "";
    $pslw->query("TRUNCATE DAILY_INSERT");
    $daily = 1;
}

$sql = "
    SELECT 
        a.user AS fronter,
        b.security_phrase, 
        b.$departmentKeyField, 
        b.list_id, 
        vl.list_name, 
        b.$leadTypeField,
        COUNT(a.lead_id) AS total_calls,
        SUM(CASE WHEN a.status = 'MPLEDG' THEN 1 else 0 END) AS mail,
        SUM(CASE WHEN a.status = 'MPLEDG' THEN b.province else 0 END) as mailAmt,
        SUM(CASE WHEN a.status = 'CCPLED' THEN 1 else 0 END) AS cc,
        SUM(CASE WHEN a.status = 'CCPLED' THEN b.province else 0 END) as ccAmt
    FROM asterisk.vicidial_closer_log$archive a
    JOIN asterisk.vicidial_list b ON a.lead_id = b.lead_id
    LEFT JOIN asterisk.vicidial_lists vl ON b.list_id = vl.list_id
    WHERE 
      ((a.user >= 4000 AND a.user < 5000) OR (a.user >= 8000 AND a.user < 9000))
      AND a.call_date BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    GROUP BY 
        a.user, b.security_phrase, b.list_id, b.$departmentKeyField, vl.list_name,
        CASE WHEN b.$leadTypeField LIKE '%R' THEN 'ROUST_R' ELSE b.$leadTypeField END
    ORDER BY a.user;
";

$getStats = $pslv->query($sql);
$stmt = $pslw->prepare("
    INSERT INTO VFR.$table (
        AGENT, CAMPAIGN_ID, DEPTKEY, NUM_SALES, TOTAL_AMOUNT,  
        TOTAL_CALLS, CCs, CC_AMT, AGENT_TYPE, LIST_ID, LIST_NAME, DATE, TYPE
    ) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        DATE_TIME = CURRENT_TIMESTAMP,
        NUM_SALES = VALUES(NUM_SALES),
        TOTAL_AMOUNT = VALUES(TOTAL_AMOUNT),
        TOTAL_CALLS = VALUES(TOTAL_CALLS),
        CCs = VALUES(CCs),
        CC_AMT = VALUES(CC_AMT),
        LIST_NAME = VALUES(LIST_NAME)
");

foreach ($getStats as $stat) {
    $total_calls = $stat['total_calls'];
    $num_mail = $stat['mail'];
    $amt_mail = $stat['mailAmt'];
    $num_cc = $stat['cc'];
    $amt_cc = $stat['ccAmt'];
    $num_sales = $num_mail + $num_cc;
    $total_amount = $amt_mail + $amt_cc;
    $type = explode("_", $stat["$leadTypeField"])[1] ?? '';
    $agentType = $stat['list_id'] < 1000 ? 2 : 1;
    $campaign_id = $stat['security_phrase'];

    if ($type == "R") {
        $query = "AND b.$leadTypeField LIKE '%$type'";
    } else {
        $query = "AND b.$leadTypeField = '{$stat["$leadTypeField"]}'";
    }

    $sql = "
    SELECT  
        COUNT(a.lead_id) AS total_calls,
        SUM(CASE WHEN a.status = 'MPLEDG' THEN 1 else 0 END) AS mail,
        SUM(CASE WHEN a.status = 'MPLEDG' THEN b.province else 0 END) as mailAmt,
        SUM(CASE WHEN a.status = 'CCPLED' THEN 1 else 0 END) AS cc,
        SUM(CASE WHEN a.status = 'CCPLED' THEN b.province else 0 END) as ccAmt
    FROM asterisk.vicidial_closer_log$archive a
    JOIN asterisk.vicidial_list b ON a.lead_id = b.lead_id
    LEFT JOIN asterisk.vicidial_lists vl ON b.list_id = vl.list_id
    WHERE 
      a.user = {$stat['fronter']}
      AND vl.campaign_id = $campaign_id
      AND b.$departmentKeyField  = '{$stat["$departmentKeyField"]}'
      AND b.list_id = {$stat['list_id']}
      $query
      AND a.call_date BETWEEN '$date 00:00:00' AND '$date 23:59:59';"; 

      echo $sql . "<br /><br />";

      $getMoreStats = $psl1->query($sql);
      foreach ($getMoreStats as $stat2) {
        $total_calls += $stat2['total_calls'];
        $num_mail += $stat2['mail'];
        $amt_mail += $stat2['mailAmt'];
        $num_cc += $stat2['cc'];
        $amt_cc += $stat2['ccAmt'];
        $num_sales += $num_mail + $num_cc;
        $total_amount += $amt_mail + $amt_cc;
      }


    $stmt->bind_param(
        "iisiiiiiiisss", 
        $stat['fronter'], 
        $campaign_id, 
        $stat["$departmentKeyField"], 
        $num_sales, 
        $total_amount,
        $total_calls, 
        $num_cc,
        $amt_cc,
        $agentType, 
        $stat['list_id'], 
        $stat['list_name'], 
        $date, 
        $type
    );
    $stmt->execute();
}

if ($daily == 1) {
    echo "$curDate: Successfully imported $date DPH data for closers. Please wait... <br />";
    $pslw->query("truncate DAILY;");
    $pslw->query("insert into DAILY select * from DAILY_INSERT");
    echo "$curDate: Successfully updated $date DPH.";
} else {
    echo "$curDate: Successfully imported $date DPH data for closers into ARCHIVE.";
}


mysqli_close($pslw);
mysqli_close($pslv);
