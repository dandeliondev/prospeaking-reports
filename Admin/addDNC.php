<?php
require_once __DIR__ . '/../dev/load.php';

$Scon->select_db( "Sales" );

if ( isset( $_GET[ 'camp' ] ) ) {
    $camp = $_GET[ 'camp' ];
    //parse entries
    $contentsOfFile = preg_replace( '/[^0-9]+/', '&&', $_GET[ 'number' ] );
    $delimiter = "&&";
    $splitcontents = explode( $delimiter, $contentsOfFile );
    $i = 0;
    $result = "";
    foreach ( $splitcontents as $num ) {
        if ( $num == "" ) {
            continue;
        }

        $i++;

        if ( !ctype_digit( $num ) || strlen( $num ) !== 10 ) {
            $result .= "<span style='color:red;'>Skip $i: $num</span>, ";
            continue;
        }

        //insert into Corp DB for storage
        $insert = $Scon->query( "insert into DNCs (Number, CampType, DateAdded) values ($num, '$camp', '$curDate')" );

        $result .= "$camp $i: $num, ";

        if ( $camp == "Global" || $camp == "Litigator") {
            $list = "SYSTEM_INTERNAL";
        } else {
            $list = "1000";
            $list2 = "3000";
        }
        
        $apiUrl = "{$clusters["psl1"]["url"]}/vicidial/non_agent_api.php?source=amd_updater&user=$apiUser&pass=$apiPass&function=add_dnc_phone&campaign_id=$list&phone_number=$num"; 
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $apiUrl
        ));
        $updateResponse = curl_exec($curl);
        
        if (isset($list2)) {            
            $apiUrl = "{$clusters["psl1"]["url"]}/vicidial/non_agent_api.php?source=amd_updater&user=$apiUser&pass=$apiPass&function=add_dnc_phone&campaign_id=$list2&phone_number=$num"; 
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $apiUrl
            ));
            $updateResponse = curl_exec($curl);
        }

        curl_close( $curl );

    }
    $result = substr( $result, 0, -2 );
    echo $result;
    echo "<table align='center' style='width: 100%;margin-top: {$i}px;background-color: #F0F0F0;font-weight: bold;'><tr><td>Successfully added number(s) to DNC lists</td></tr></table>";
} else {
    echo "<div style='margin:20px auto' id='results'></div><table align='center'><tr><td style='text-align:center'><span style='color:red;font-weight:bold'<span>**This Feature is currently disabled, as the VICI Campaigns are not set to use any DNC lists.<br />Contact the VICI Admins first about this and we can re-enable it**</span><br /><br />Select Campaign:<br /><select id='dncCamp'><option value=''></option><option value='Litigator'>Litigator</option><option value='Global'>Global</option><option value='1000'>Campaign</option></select></tr>
	<tr><td style='text-align:center'>Enter Number(s):<br /><textarea rows='20' cols='15' id='dncNum'></textarea></td></tr>
	<tr><td style='text-align:center'><button onclick='dncAdd()' disabled>Submit</button></td></tr></table>";
}
?>
