<?php
require_once __DIR__ . '/../../config/bootstrap.php';
$pslw = connectToCluster('pslw', $clusters);
$pslw->select_db( "DPH2" );

if ( $_GET[ 'range' ] == "DAILY" ) {
    $range = "DAILY";
} else {
    $range = "ARCHIVE";
} 


//get Last Updated Date from DB
$timestamp = $pslw->query("select max(DATE_TIME) from $range")->fetch_assoc();
echo "Last Updated (Eastern):<br />{$timestamp['max(DATE_TIME)']}";


prospeaking_close($pslw);