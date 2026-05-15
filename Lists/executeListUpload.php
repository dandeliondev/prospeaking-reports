<?php
include( "/srv/www/php_include.php" );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
ini_set('memory_limit', '1024M');

echo "$curTimeStamp\nStarting List Upload...\r\n";

function normalizeNumber($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    return $digits;
}

function isNonExcludedNumber($phone) {
    global $dupArr, $camp;
    
    $areaCode = substr($phone, 0, 3);
    
    $excludedAreaCodes = [
        '907', // Alaska
        '808', // Hawaii
		'204', '226', '236', '249', '250', '263', '289', '306', '343', '354', '365', '367', '368', '382', '403', '416', '418', '428', '431', '437', '438', '450', '468', '474', '506', '514', '519', '548', '579', '581', '584', '587', '604', '613', '639', '647', '672', '683', '705', '709', '742', '753', '778', '780', '782', '807', '819', '825', '867', '873', '879', '902', '905' // Canada
    ];
    
    return !in_array($areaCode, $excludedAreaCodes);
}

$camp = $argv[1];
$dept = $argv[2];
$type = $argv[3];
$listName = $argv[4];
$leadType = "{$camp}_{$type}";
$id = 0;
//$dup = $camp == "ROUST" ? "" : ($dept == "AG" ? "DUPCAMP" : "DUPLIST"); 
$dup = "";
$dupArr = array();
$listID_select = $type == "XTAP" ? ">" : "<";

if ($camp == "ROUST") {
    $min = $campArr[$camp]["r"]["min"];
    $max = $campArr[$camp]["r"]["max"];
} else {
    $min = $campArr[$camp][strtolower($type)]["min"];
    $max = $campArr[$camp][strtolower($type)]["max"];
}

$clustersToQuery = ['pslw', 'psl1', 'psl2'];
$ids = [];

foreach ($clustersToQuery as $clusterName) {
    $db = connectToCluster($clusterName, $clusters);
    if ($clusterName == "pslw") {
        $getListID = "SELECT IFNULL(MAX(list_id), $min) + 1 as ID FROM asterisk.vicidial_lists WHERE list_id BETWEEN $min AND $max";
    } else {
        $getListID = "SELECT GREATEST(
            (SELECT IFNULL(MAX(list_id), $min) FROM asterisk.vicidial_lists WHERE list_id BETWEEN $min AND $max),
            (SELECT IFNULL(MAX(list_id), $min) FROM asterisk.vicidial_lists_archive WHERE list_id BETWEEN $min AND $max)
        ) + 1 AS ID";
    }
    $result = $db->query($getListID);
    $row = $result->fetch_assoc();
    // If the query returns null, we default to 0
    $ids[] = isset($row['ID']) ? $row['ID'] : 0;
    mysqli_close($db);
}

$listID = max($ids);
$listID = ($listID == 0) ? $min + 1 : $listID;
$listName = "$camp+-+" . str_replace( "NPSF+", "", str_replace( "+LG+", "+ALG+", $listName ) );
$file = $argv[5];
$camp = $camp == "ROUST" ? 7200 : (isset($campArr[$camp]["vici"][$dept]) ? $campArr[$camp]["vici"][$dept] : $campArr[$camp]["vici"]["C"]);
$file = $camp == 7200 ? "/srv/www/htdocs/ProSpeaking/Lists/roust/$file" : "/srv/www/htdocs/ProSpeaking/Lists/pending/$file";
$target = "/srv/www/htdocs/ProSpeaking/Lists/uploaded/$listID.csv";
$createList = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listCreate&user=$apiUser&pass=$apiPass&function=add_list&list_id=$listID&list_name=$listName&campaign_id=testcamp&tz_method=POSTAL_CODE";
$curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $createList
        ));
        $updateResponse = curl_exec($curl);
        echo "\r\n\r\n" . $curTimeStamp . "\r\n" . $updateResponse . "\r\n" . $createList . "\r\n" . $getListID;
        curl_close( $curl );
$array = array_map( 'str_getcsv', file( $file ) );
$h = 0; //total rows
$i = 0; //successful imports
$j = 0; //API error
$k = 0; //duplicates
$l = 0; //unknown type
$m = 0; //excluded number
$listName = str_replace("+", " ", $listName);
$errors = array();
$curl = curl_init();
foreach ( $array as $row ) {
    if ( $row[0] == "FN" ||  $row[0] == "PHONE" ||  $row[0] == "NUMBER" ||  $row[0] == "NUMBER1" ||  $row[0] == "PHONE_NUMBER" ||  $row[0] == "FIRST" ||  $row[0] == "FIRST_NAME" ||  $row[0] == "ID" || $row[0] == "fn" ||  $row[0] == "phone" ||  $row[0] == "number" ||  $row[0] == "number1" ||  $row[0] == "phone_number" ||  $row[0] == "first" ||  $row[0] == "first_name" ||  $row[0] == "id" ) {
        continue;
    }
    $h++;
    if ( is_numeric( $row[ 0 ] ) ) {
        if ( strlen( $row[ 0 ] ) === 10 ) {
            if ( strlen( $row[ 5 ] ) === 2 && is_numeric( $row[ 6 ] ) ) { //everything in A-H
                $first = urlencode( $row[ 1 ] );
                $last = urlencode( $row[ 2 ] );
                $address1 = urlencode( $row[ 3 ] );
                $city = urlencode( $row[ 4 ] );
                $state = urlencode( $row[ 5 ] );               
                $amt = str_replace( "$", "", str_replace( ".00", "", urlencode( $row[ 7 ] ) ) );
                $zip = strlen( $row[ 6 ] ) == 4 ? "0" . $row[ 6 ] : $row[ 6 ];
                $phone = normalizeNumber($row[0]);
                if (! isNonExcludedNumber($phone)) {
                    $m++;
                    continue;
                }
                if (isset($dupArr[$phone])) {
                    $k++;
                    continue;
                }
                $dupArr[$phone] = true;
                $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number=$phone&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&$amountField=$amt&$leadTypeField=$leadType&$departmentKeyField=$dept&duplicate_check=$dup&nanpa_ac_prefix_check=Y&dnc_check=Y";
                $id = 1;
            } else if ( strlen( $row[ 8 ] ) == 2 && is_numeric( $row[ 9 ] ) ) {
                if ($camp == 7200) { //NAMS ROUST File
                    $first = urlencode( $row[ 3 ] );
                    $last = urlencode( $row[ 4 ] );
                    $address1 = urlencode( $row[ 6 ] );
                    $city = urlencode( $row[ 7 ] );
                    $state = urlencode( $row[ 8 ] );              
                    $amt = str_replace( "$", "", str_replace( ".00", "", urlencode( $row[ 12 ] ) ) );
                    $zip = strlen( $row[ 9 ] ) == 4 ? "0" . $row[ 9 ] : $row[ 9 ];
                    $payType = "U";
                    $saleDate = urlencode( date('Y-m-d',strtotime($row[ 11 ] )));
                    $invoice = urlencode( $row[ 17 ] );
                    $leadType = urlencode( substr($row[ 18 ], 3) . "_R");

                    $key = $leadType . "_" . $invoice;
                    $phone = normalizeNumber($row[0]);
                    if (! isNonExcludedNumber($phone)) {
                        $m++;
                        continue;
                    }
                    if (isset($dupArr[$phone])) {
                        $k++;
                        continue;
                    }
                    $dupArr[$phone] = true;
                    $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number=$phone&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&$amountField=$amt&gender=$payType&$leadTypeField=$leadType&$departmentKeyField=$dept&address3=$invoice&date_of_birth=$saleDate&nanpa_ac_prefix_check=Y&dnc_check=Y";
                    $id = 2;
                } else { //NAMS special order
                    $first = urlencode( $row[ 3 ] );
                    $last = urlencode( $row[ 4 ] );
                    $address1 = urlencode( $row[ 6 ] );
                    $city = urlencode( $row[ 7 ] );
                    $state = urlencode( $row[ 8 ] );              
                    $amt = str_replace( "$", "", str_replace( ".00", "", urlencode( $row[ 14 ] ) ) );
                    $zip = strlen( $row[ 9 ] ) == 4 ? "0" . $row[ 9 ] : $row[ 9 ];
                    $payType = ( strpos( $row[ 24 ], "CC" ) !== false ) ? "F" : "M";
                    $phone = normalizeNumber($row[0]);
                    if (! isNonExcludedNumber($phone)) {
                        $m++;
                        continue;
                    }
                    if (isset($dupArr[$phone])) {
                        $k++;
                        continue;
                    }
                    $dupArr[$phone] = true;
                    $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number=$phone&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&$amountField=$amt&gender=$payType&$leadTypeField=$leadType&$departmentKeyField=$dept&duplicate_check=$dup&nanpa_ac_prefix_check=Y&dnc_check=Y";
                    $id = 3;
                }
            }
        } else { //IDs in A, everything else in B - I
            $first = urlencode( $row[ 2 ] );
            $last = urlencode( $row[ 3 ] );
            $address1 = urlencode( $row[ 4 ] );
            $city = urlencode( $row[ 5 ] );
            $state = urlencode( $row[ 6 ] );                
            $zip = strlen( $row[ 6 ] ) == 4 ? "0" . $row[ 7 ] : $row[ 7 ];
            $amt = str_replace( "$", "", str_replace( ".00", "", urlencode( $row[ 14 ] ) ) );
            $phone = normalizeNumber($row[1]);
            if (! isNonExcludedNumber($phone)) {
                $m++;
                continue;
            }
            if (isset($dupArr[$phone])) {
                $k++;
                continue;
            }
            $dupArr[$phone] = true;
            $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number=$phone&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&$amountField=$amt&$leadTypeField=$leadType&$departmentKeyField=$dept&duplicate_check=$dup&nanpa_ac_prefix_check=Y&dnc_check=Y";
            $id = 4;
        }
    } else if ( is_numeric( str_replace( "-", "", $row[ 0 ] ) ) && strlen( str_replace( "-", "", $row[ 0 ] ) ) == 10 ) { //NAMS regular file
        $first = urlencode( $row[ 1 ] );
        $last = urlencode( $row[ 2 ] );
        $address1 = urlencode( $row[ 3 ] );
        $city = urlencode( $row[ 4 ] );
        $state = urlencode( $row[ 5 ] );               
        $amt = str_replace( "$", "", str_replace( ".00", "", urlencode( $row[ 8 ] ) ) );
        $zip = strlen( $row[ 6 ] ) == 4 ? "0" . $row[ 6 ] : $row[ 6 ];
        $phone = normalizeNumber($row[0]);
        if (! isNonExcludedNumber($phone)) {
            $m++;
            continue;
        }
        if (isset($dupArr[$phone])) {
            $k++;
            continue;
        }
        $dupArr[$phone] = true;
        $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number=$phone&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&$amountField=$amt&email={$row[11]}&$leadTypeField=$leadType&$departmentKeyField=$dept&duplicate_check=$dup&nanpa_ac_prefix_check=Y&dnc_check=Y";
        $id = 5;
    } else if ( is_numeric( $row[ 6 ] ) && strlen( $row[ 6 ] ) == 10 ) { //LL Zip Files
        $first = urlencode( $row[ 0 ] );
        $last = urlencode( $row[ 1 ] );
        $address1 = urlencode( $row[ 4 ] );
        $city = urlencode( $row[ 3 ] );
        $state = urlencode( $row[ 4 ] );               
        $email = urlencode( $row[ 8 ] );
        $zip = strlen( $row[ 5 ] ) == 4 ? "0" . $row[ 5 ] : $row[ 5 ];
        $phone = normalizeNumber($row[6]);
        if (! isNonExcludedNumber($phone)) {
            $m++;
            continue;
        }
        if (isset($dupArr[$phone])) {
            $k++;
            continue;
        }
        $dupArr[$phone] = true;
        $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number={$row[6]}&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&email=$email&$leadTypeField=$leadType&$departmentKeyField=$dept&duplicate_check=$dup&nanpa_ac_prefix_check=Y&dnc_check=Y";
        $id = 6;
    } else if ( is_numeric( $row[ 9 ] ) && is_numeric( $row[ 22 ] ) && strlen( $row[ 21 ] ) == 2 && strlen( $row[ 0 ] ) == 2 ) { //NAMS renewal data        
        $first = urlencode( $row[ 17 ] );
        $last = urlencode( $row[ 18 ] );
        $address1 = urlencode( $row[ 19 ] );
        $city = urlencode( $row[ 20 ] );
        $state = urlencode( $row[ 21 ] );              
        $amt = str_replace( "$", "", str_replace( ".00", "", urlencode( $row[ 7 ] ) ) );
        $zip = strlen( $row[ 22 ] ) == 4 ? "0" . $row[ 22 ] : $row[ 22 ];
        $payType = ( strpos( $row[ 25 ], "CC" ) !== false ) ? "F" : "M";
        $phone = normalizeNumber($row[0]);
        if (! isNonExcludedNumber($phone)) {
            $m++;
            continue;
        }
        if (isset($dupArr[$phone])) {
            $k++;
            continue;
        }
        $dupArr[$phone] = true;
        $addLead = "{$clusters["pslw"]["url"]}/vicidial/non_agent_api.php?source=listUpdate&user=$apiUser&pass=$apiPass&function=add_lead&list_id=$listID&phone_code=1&phone_number={$row[0]}&first_name=$first&last_name=$last&address1=$address1&city=$city&state=$state&postal_code=$zip&$amountField=$amt&gender=$payType&$leadTypeField=$leadType&$departmentKeyField=$dept&duplicate_check=$dup&nanpa_ac_prefix_check=Y&dnc_check=Y";
        $id = 7;
    } else {
        continue;
    }
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $addLead
    ));
    $updateResponse = curl_exec($curl);
    if ( strpos( $updateResponse, "SUCCESS" ) !== false ) {
        $i++;
    } else if (strpos( $updateResponse, "ERROR" ) !== false) {
        $j++;
        $errors[] = "$addLead == $updateResponse \r\n\r\n";
    } else {
        $l++;
    }

}
unset($dupArr);
curl_close( $curl );
$curTimestamp = date('Y-m-d H:i:s');
echo "\r\n$listID -- $listName \r\n
$curTimestamp: Succesfully uploaded $i records out of $h rows. \r\n
     $m records were excluded area codes. \r\n
     $k records were duplicates. \r\n
     $l records returned an unknown result \r\n
     $j API errors \r\n";
    $apis = implode( "\r\n", $errors );
    echo "\r\n$apis";

echo "\r\n \r\n";
rename($file, $target);