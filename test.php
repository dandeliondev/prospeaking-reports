<?php
include( "/srv/www/php_include.php" );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );

$psl1 = connectToCluster('psl1', $clusters);
$psl2 = connectToCluster('psl2', $clusters);
//$psl3 = connectToCluster('psl3', $clusters);
$pslv = connectToCluster('pslv', $clusters);
$pslw = connectToCluster('pslw', $clusters);

 
//echo "nothing to test";



echo "Starting... <br/><br/>";


$test = $psl1->query("select lead_id from vicidial_list limit 1")->fetch_assoc();
echo $test['lead_id'];


echo "<br/><br/> ...Done";


mysqli_close($psl1);
mysqli_close($psl2);
mysqli_close($pslv);
mysqli_close($pslw);