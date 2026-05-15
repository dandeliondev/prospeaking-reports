<?php
include_once( "/srv/www/php_include.php" );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$psl1 = connectToCluster('psl1', $clusters);
$pslv = connectToCluster('pslv', $clusters);
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "Sales");

if ( isset( $_GET[ 'date' ] ) ) {
    $date = date("Y-m-d", strtotime($_GET[ 'date' ]));
    if ( $date <> $curDate ) {
        //$archive = "_archive";
        $archive = "";
    }
} else {
    $date = $curDate;
    $archive = "";
}

//external 
$stmt = $pslw->prepare( "insert ignore into Sales.Sales (START_TIME, PHONE, AGENT, AGENT_NAME, LAST_NAME, FIRST_NAME, ADDRESS, CITY, STATE, ZIP, CAMPAIGN, AMOUNT, VERIFIER, DEPTKEY, TYPE, DISPOSITION, CC, INVOICE, START_DATE, LEAD_ID, EMAIL, OCCUPATION, EMPLOYER) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" );

$stmt->bind_param( "siissssssisiissssisisss", $last_local_call_time, $phone_number, $user, $name, $last_name, $first_name, $address1, $city, $state, $postal_code, $camp, $$amountField, $verifier, $$departmentKeyField, $type, $status, $cc, $invoice, $date, $lead_id, $email, $$occupationField, $$employerField );

$sql = "select lead_id, status, last_local_call_time, gmt_offset_now, phone_number, user, title, last_name, first_name, address1, city, state, postal_code, $amountField, $leadTypeField, $employerField, $occupationField, email, $departmentKeyField from asterisk.vicidial_list where lead_id in (select lead_id from vicidial_agent_log$archive where event_time > '$date 00:00:00' and event_time < '$date 23:59:59' and campaign_id <> 7200) and status in ('$mSale', '$cSale', '$bSale') and $amountField  <> '' and date(last_local_call_time) = '$date'";



$getSales = $pslv->query( $sql );

$i = 0;

foreach ( $getSales as $sales ) {
    foreach ( $sales as $key => $a ) {
        $$key = $a;
    }
    
    $$departmentKeyField = ($$departmentKeyField == "CJ" || $$departmentKeyField == "AG") ? "DJ" : ( $$departmentKeyField == "LL" ? "DL" : "DC");
/*
    $user = ($user == "" || is_null($user)) ? 1001 : $user;
   
    $verifier = $user;

    $user = $pslv->query("select user, max(event_time) from vicidial_agent_log$archive where lead_id = $lead_id and event_time > '$date 00:00:00' and event_time < '$date 23:59:59' and user <> 'VDAD' and user <> 'VDCL' and user <> $verifier group by user order by event_time desc limit 1")->fetch_assoc();
    $user = $user['user'];
*/ 

    $verifier = $user;
    $name = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $title );
    $lastName = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $last_name );
    $firstName = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $first_name );
    $address = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $address1 );
    $city = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $city );
    $state = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $state );
    $zip = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $postal_code );
    $cc = $status == "$cSale" || $status == "$bSale" ? "CC" : "";
    $split = explode("_", ${$leadTypeField});
    $camp = $campArr[$split[0]]['nams'];
    $type = $split[1];
    $email = (strpos($email, "@") !== false) ? $email : "";
     

    if ($type == "XTAP" || $type == "T4T") {
        $$departmentKeyField = "HD"; //set Dept Code to "HD" (House Data) for any XTAPs / T4Ts
    }

    $insert = $stmt->execute();
    
    if ( $insert ) {
        $i++;
    }    
}


$getSales = $psl1->query( $sql );

foreach ( $getSales as $sales ) {
    foreach ( $sales as $key => $a ) {
        $$key = $a;
    }
    
    $$departmentKeyField = ($$departmentKeyField == "CJ" || $$departmentKeyField == "AG") ? "DJ" : ( $$departmentKeyField == "LL" ? "DL" : "DC");
/*
    $user = ($user == "" || is_null($user)) ? 1001 : $user;
   
    $verifier = $user;

    $user = $pslv->query("select user, max(event_time) from vicidial_agent_log$archive where lead_id = $lead_id and event_time > '$date 00:00:00' and event_time < '$date 23:59:59' and user <> 'VDAD' and user <> 'VDCL' and user <> $verifier group by user order by event_time desc limit 1")->fetch_assoc();
    $user = $user['user'];
*/ 

    $verifier = $user;
    $name = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $title );
    $lastName = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $last_name );
    $firstName = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $first_name );
    $address = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $address1 );
    $city = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $city );
    $state = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $state );
    $zip = preg_replace( '/[^A-Z a-z 0-9\- ]/', '', $postal_code );
    $cc = $status == "$cSale" || $status == "$bSale" ? "CC" : "";
    $split = explode("_", ${$leadTypeField});
    $camp = $campArr[$split[0]]['nams'];
    $type = $split[1];
    $email = (strpos($email, "@") !== false) ? $email : "";
     

    if ($type == "XTAP" || $type == "T4T") {
        $$departmentKeyField = "HD"; //set Dept Code to "HD" (House Data) for any XTAPs / T4Ts
    }

    $insert = $stmt->execute();
    
    if ( $insert ) {
        if ($stmt->affected_rows > 0) {
            $i++;
        } else {
            echo "Bound Parameters: " . implode(", ", array($last_local_call_time, $phone_number, $user, $name, $last_name, $first_name, $address1, $city, $state, $postal_code, $camp, $amountField, $verifier, $departmentKeyField, $type, $status, $cc, $invoice, $date, $lead_id, $email, $occupationField, $employerField)) . PHP_EOL;
        }
    }    
}

if ( $i > 0 ) {
	echo "\r\n\r\n\r\n$curTimeStamp\r\n Successly inserted $i records.";
} else {
	
    echo "\r\n\r\n\r\n$curTimeStamp\r\nInsert Error: " . $stmt->error . PHP_EOL;
    echo "Error Code: " . $stmt->errno . PHP_EOL;
    echo "Executed SQL Query: " . $stmt->sqlstate . PHP_EOL;
    echo "Bound Parameters: " . implode(", ", array($last_local_call_time, $phone_number, $user, $name, $last_name, $first_name, $address1, $city, $state, $postal_code, $camp, $amountField, $verifier, $departmentKeyField, $type, $status, $cc, $invoice, $date, $lead_id, $email, $occupationField, $employerField)) . PHP_EOL;

}


