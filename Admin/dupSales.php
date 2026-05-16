<?php
require_once __DIR__ . '/../dev/load.php';$pslv = connectToCluster('pslv', $clusters); // verifier cluster

// Query for duplicate sales using inner join
$dupQuery = "
    SELECT v.lead_id, v.phone_number, v.list_id, v.security_phrase, v.title, v.province, date(last_local_call_time) as date, v.$leadTypeField, v.status
    FROM vicidial_list v
    JOIN (
        SELECT phone_number, SUBSTRING_INDEX(vendor_lead_code, '_', 1) AS prefix
        FROM vicidial_list
        WHERE status IN ('$mSale', '$cSale', '$bSale')
        AND last_local_call_time > '$curDate 00:00:00'
        GROUP BY phone_number, prefix
        HAVING COUNT(*) > 1
    ) dup ON v.phone_number = dup.phone_number 
         AND SUBSTRING_INDEX(v.vendor_lead_code, '_', 1) = dup.prefix
    JOIN vicidial_agent_log ag ON ag.lead_id = v.lead_id
    WHERE v.status IN ('$mSale', '$cSale', '$bSale')
    AND ag.event_time > '$curDate 00:00:00'
";
$dupResult = $pslv->query($dupQuery);
echo "
<style type='text/css'>
    table {
        background: #eee;
        border-collapse: collapse; 
        margin-bottom: 50px;
    }
    td, th {
        text-align: center;
        padding: 5px;
        border: 1px #ccc solid;
    }
    th {background: #ddd}
</style><div align='center'>
<table>
    <tr style='display:none'><td>$dupQuery</td></tr>
    <tr>
        <th>LeadID</th><th>Phone</th><th>Camp_Type</th><th>Clusters</th><th>List</th><th>Status</th><th>Amount</th><th>Agent</th><th>Date</th></tr>";
$phone = "";
$border = "";
foreach ($dupResult as $row) {
    if ($phone !== $row['phone_number']) {
        if ($phone == "") {
            $phone = $row['phone_number'];
        } else {
            $phone = $row['phone_number'];
            $border = "style='border-top:2px #ccc solid'";
            //$border = "style='background-color:#ddd'";
        }
    } else {
        $border = "";
    }
    $cluster = $row['list_id'] >= 200 ? 2 : 1;
    $amount = $row['province'];
    echo "<tr class='trHover' $border><td>{$row['lead_id']}</td><td>{$row['phone_number']}</td><td>{$row[$leadTypeField]}</td><td>$cluster</td><td>{$row['security_phrase']}</td><td>{$row['status']}</td><td>$$amount</td><td>{$row['title']}</td><td>{$row['date']}</td></tr>";
}

echo "</table><div>";


mysqli_close($pslv);
