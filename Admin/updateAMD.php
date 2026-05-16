<?php
require_once __DIR__ . '/../config/bootstrap.php';

$Scon->select_db("asterisk");

echo "This feature has been deactivated at the Server Admin's request";
exit;

if (isset($_GET['update'])) {
    $countArr = array(
        1 => "HUMAN,HUMAN",
        2 => "HUMAN,HUMAN\r\nMACHINE,INITIALSILENCE",
        3 => "HUMAN,HUMAN\r\nMACHINE,INITIALSILENCE\r\nMACHINE,MAXWORDS",
        4 => "HUMAN,HUMAN\r\nMACHINE,INITIALSILENCE\r\nMACHINE,MAXWORDS\r\nNOTSURE,TOOLONG",
        5 => "HUMAN,HUMAN\r\nMACHINE,INITIALSILENCE\r\nMACHINE,MAXWORDS\r\nNOTSURE,TOOLONG\r\nMACHINE,LONGGREETING"
    );
    $amd = $countArr[$_GET['amd']];
    if ($_GET['amd'] == 5) {
        $apiUrl = "{$clusters["psl1"]["url"]}/vicidial/non_agent_api.php?source=amd_updater&user=$apiUser&pass=$apiPass&function=update_campaign&campaign_id=1000&campaign_vdad_exten=8368"; // Turn AMD off
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $apiUrl
        ));
        $updateResponse = curl_exec($curl);
        curl_close( $curl );
        if (!$updateResponse) {
            echo "There was an issue turning on the AMD. Try again or see admin.";
            exit;
        } else {
            $fields = "ADD=492111111111&container_id=AMD_AGENT_OPT_1000&container_notes=AMD%20agent%20options%20for%201000%20campaign&container_type=AMD_AGENT_OPTIONS&user_group=---ALL---&container_entry=$amd&submit=SUBMIT";
      
            $url = $clusters["psl1"]["url"] . "/vicidial/admin.php";
            $curl = curl_init();
            curl_setopt_array( $curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url,
                CURLOPT_POST => 4,
                CURLOPT_POSTFIELDS => $fields,
                CURLOPT_USERPWD => $adminUser . ':' . $adminPass,
                CURLOPT_USERAGENT => 'AMD-Updater'
            ) );
            $resp = curl_exec( $curl );
            curl_close( $curl );

            if ($resp) {
                echo "Successfully turned OFF the AMD";
            } else {
                echo "I was able to turn off the AMD but there was an issue updating the slider level. Try again or see admin.";
            }
        }
    } else {
        $apiUrl = "{$clusters["psl1"]["url"]}/vicidial/non_agent_api.php?source=amd_updater&user=$apiUser&pass=$apiPass&function=update_campaign&campaign_id=1000&campaign_vdad_exten=8369";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $apiUrl
        ));
        $updateResponse = curl_exec($curl);
        if (!$updateResponse) {
            echo "There was an issue turning on the AMD. Try again or see admin.";
            exit;
        } else {
            $fields = "ADD=492111111111&container_id=AMD_AGENT_OPT_1000&container_notes=AMD%20agent%20options%20for%201000%20campaign&container_type=AMD_AGENT_OPTIONS&user_group=---ALL---&container_entry=$amd&submit=SUBMIT";
      
            $url = $clusters["psl1"]["url"] . "/vicidial/admin.php";
            $curl = curl_init();
            curl_setopt_array( $curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url,
                CURLOPT_POST => 4,
                CURLOPT_POSTFIELDS => $fields,
                CURLOPT_USERPWD => $adminUser . ':' . $adminPass,
                CURLOPT_USERAGENT => 'AMD-Updater'
            ) );
            $resp = curl_exec( $curl );
            curl_close( $curl );

            if ($resp) {
                echo "Successfully turned ON the AMD to Level " . $_GET['amd'];
            } else {
                echo "I was able to turn on the AMD but there was an issue updating the Level. Try again or see admin.";
            }
        }
    }
} else {
    $getSettings = $Scon->query("SELECT container_entry FROM vicidial_settings_containers WHERE container_id = 'AMD_AGENT_OPT_1000'")->fetch_array();
    $settings = str_replace(" ", "", $getSettings[0]);
    $count = substr_count($settings, "\r\n") + 1;
    $getAMD = $Scon->query("SELECT campaign_vdad_exten FROM vicidial_campaigns WHERE campaign_id = 1000")->fetch_array();
    $status = $getAMD[0] == 8369 ? "On" : "Off";
    $color = $status == "On" ? "green" : "red";
    echo "<div align='center' id='results'>The AMD is currently <span style='font-weight: bold; color: $color'>$status</span>.<br /><br />AMD Intensity Setting (1 = Agressive; 5 = Passive/Off):<br /><br /><div align='center' style='width:190px;'>1&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5<br /><input id='amd' type='range' min='1' max='5' value='$count'><br /><br /><button id='update' data-v='Admin/updateAMD?update&return=results&amd=' onclick='fetchURL(this.getAttribute(\"data-v\"));'>Update</button></div></div>";
}
