<?php
require_once __DIR__ . '/../../config/bootstrap.php';

$pslv = connectToCluster('pslv', $clusters); // verifier cluster

// Query for sales with no amount
$noAmtQuery = "
    SELECT lead_id 
    FROM vicidial_list 
    WHERE status IN ('$mSale', '$cSale', '$bSale')
      AND lead_id IN (SELECT lead_id FROM vicidial_agent_log)
      AND $amountField = ''
";
$noAmtResult = $pslv->query($noAmtQuery);
$noAmtCount = $noAmtResult->num_rows;

// Query for duplicate sales using inner join
$dupQuery = "
    SELECT v.lead_id
    FROM vicidial_list v
    JOIN (
        SELECT phone_number, LEFT($leadTypeField, 3) AS prefix
        FROM vicidial_list
        WHERE status IN ('$mSale', '$cSale', '$bSale')
        AND last_local_call_time > '$curDate 00:00:00'
        GROUP BY phone_number, prefix
        HAVING COUNT(*) > 1
    ) dup ON v.phone_number = dup.phone_number 
         AND LEFT(v.$leadTypeField, 3) = dup.prefix
    JOIN vicidial_agent_log ag ON ag.lead_id = v.lead_id
    WHERE v.status IN ('$mSale', '$cSale', '$bSale')
    AND ag.event_time > '$curDate 00:00:00'
";
$dupResult = $pslv->query($dupQuery);
$dupCount = $dupResult->num_rows;

if ($noAmtCount > 0) {
    echo "<span style='color:red'>$noAmtCount Sales with no Amt!</span><br />";
}

if ($dupCount > 0) {
    echo "<span style='color:red'>$dupCount Duplicate Sales!</span>";
}

if ($noAmtCount == 0 && $dupCount == 0) {
   echo "VFR Report";
}

prospeaking_close($pslv);
