<?php
include_once( "/srv/www/php_include.php" );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );

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
        SELECT phone_number, SUBSTRING_INDEX($leadTypeField, '_', 1) AS prefix
        FROM vicidial_list
        WHERE status IN ('$mSale', '$cSale', '$bSale')
        AND last_local_call_time > '$curDate 00:00:00'
        GROUP BY phone_number, prefix
        HAVING COUNT(*) > 1
    ) dup ON v.phone_number = dup.phone_number 
         AND SUBSTRING_INDEX($leadTypeField, '_', 1) = dup.prefix
    JOIN vicidial_agent_log ag ON ag.lead_id = v.lead_id
    WHERE v.status IN ('$mSale', '$cSale', '$bSale')
    AND ag.event_time > '$curDate 00:00:00'
";
$dupResult = $pslv->query($dupQuery);
$dupCount = $dupResult->num_rows;


if ($dupCount > 0) {
    echo "<span style='color:red'>$dupCount Duplicate Sales!</span><br />";
}

if ($noAmtCount > 0) {
    $leadIDs = "";
    foreach ( $noAmtResult as $row ) {
        $leadIDs .= "{$row['lead_id']}<br />";
    }
    $leadIDs = substr($leadIDs, 0, -6);
    echo "<span style='color:red'>$noAmtCount Sales with no Amt!</span><br /><div align='left' style='font-size:10px'>$leadIDs</div>";
}

if ($noAmtCount == 0 && $dupCount == 0) {
   echo "DPH Report";
}

mysqli_close($pslv);
