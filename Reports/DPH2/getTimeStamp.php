<?php
include_once( "/srv/www/php_include.php" );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
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


mysqli_close($pslw);