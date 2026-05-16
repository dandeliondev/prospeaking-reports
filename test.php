<?php
require_once __DIR__ . '/dev/load.php';$psl1 = connectToCluster('psl1', $clusters);
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