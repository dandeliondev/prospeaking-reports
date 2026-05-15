<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once("/srv/www/php_include.php");
$psl1 = connectToCluster('psl1', $clusters);
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "Sales");

if (isset($_GET['date'])) {
    $date = "'{$_GET['date']}'";
} else {
    $date = "CURDATE()";
}

$getLeads = $psl1->query("SELECT address3 AS Invoice, CASE WHEN status = 'CCPLED' THEN province ELSE 0 END AS Amount, status, DATE(last_local_call_time) AS Date, user AS Rep, CONCAT('34_', REPLACE(vendor_lead_code, '_R', '')) AS Camp, phone_number AS Phone FROM vicidial_list WHERE list_id BETWEEN 90000 AND 99999 AND status IN ('NI', 'VOID', 'DMNI', 'CCPLED') and DATE(last_local_call_time) = $date");

$insert = $pslw->prepare("INSERT INTO Sales.Roust (Invoice, Amount, Note, `Key`, `Date`, Rep, Camp, Phone, `Status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE Amount = VALUES(Amount), Note = VALUES(Note), `Key` = VALUES(`Key`), Rep = VALUES(Rep), `Status` = VALUES(`Status`)");

$insert->bind_param("iisssisis", $invoice, $amount, $note, $key, $date, $rep, $camp, $phone, $status);

$s = 0; //roust sale count
$t = 0; //roust sale total
$r = 0; //record count

foreach ($getLeads as $lead) {
    $invoice = $lead['Invoice'];
    $amount = $lead['Amount'];
    $note = $lead['status'] == "CCPLED" ? "Paid CC" : "Void";
    $key = $lead['status'] == "CCPLED" ? "C" : "V";
    $date = $lead['Date'];
    $rep = $lead['Rep'];
    $camp = $lead['Camp'];
    $phone = $lead['Phone'];
    $status = $lead['status'];

    if ($status == "CCPLED") {
        $s++;
        $t += $amount;
    }

    $r++;

    $insert->execute();
}

echo "$curTimeStamp \r\n
Successfully imported $r records. \r\n
$s total Roust Sales for $$t\r\n\r\n\r\n";

$insert->close();
