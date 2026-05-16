<?php
require_once __DIR__ . '/../dev/load.php';
/**/

if ( isset( $_GET['dispo'])) {
    $dispo = $_GET['dispo'];
    $secs = "{$dispo}secs";
    $secs = $_GET[$secs];
    $cluster = $_GET['cluster'];
    $psl = connectToCluster($cluster, $clusters);
    $query = $dispo == 'A' ? "campaign_id <> 2000 and status = 'AM' and length_in_sec > $secs" : ($dispo == 'NI' ? "campaign_id <> 2000 and status = 'NI' and length_in_sec < $secs" : "campaign_id = 2000 and status = 'DEC'");
    $sql = "select lead_id, user, time(call_date) as time, campaign_id, length_in_sec, uniqueid from vicidial_log where  $query and call_date > '$curDate 00:00:00' order by length_in_sec desc";
    $getCallss = $psl->query($sql);
    $dispo = $dispo == 'A' ? "AM" : $dispo;
    $gtlt = $dispo == 'A' ? "more than $secs sec" : ($dispo == 'NI' ? "less thansecs $secs sec" : "");
    echo "<style>audio {height:16px;width:585px}</style><div style='padding: 0 15px'><table align='center'><colgroup><col width='75px'><col width='60px'><col width='85px'><col width='60px'><col width='585px'></colgroup><tr><th colspan='5'>{$dispo}s $gtlt</th></tr><tr><th>LeadID</th><th>User</th><th>Time</th><th>Talk</th><th>Recording</th></tr><tr style='display:none'><td>$sql</td></tr>";
    foreach ($getCallss as $Ns) {
        $getRec = $psl->query("select location from recording_log where vicidial_id = '{$Ns['uniqueid']}'")->fetch_array();
        echo "<tr class='trHover'><td>{$Ns['lead_id']}</td><td>{$Ns['user']}</td><td>{$Ns['time']}</td><td>{$Ns['length_in_sec']}</td><td><audio class='audioPlayback' controls preload='none'><source src='{$getRec['location']}' type='audio/mpeg'></audio></td></tr>";
    }
    echo "</table></div></div>";
} else {
    echo "<div style='width:300px;margin: 20px auto;text-align:center'>Select Cluster: <select id='cluster'><option></option>";

    $pslKeys = preg_grep('/^psl\d+$/', array_keys($clusters));
    natsort($pslKeys);
    foreach ($pslKeys as $clusterName) {
        echo "<option value='$clusterName'>$clusterName</option>";
    }
    
    echo "</select></div><div style='width:300px;margin: 20px auto;text-align:center'>Select Dispo to review: <select id='dispo' onchange='updateSecDisplay()'><option></option><option value='A'>AMs</option><option value='DEC'>DECs</option><option value='NI'>NIs</option></select><br /><br /><div id='AMs' style='display:none'>AMs greater than: <input type='number' id='Asecs' style='width:55px;' value='30' placeholder='30' /></div><div id='NIs' style='display:none'>NIs less than: <input type='number' id='NIsecs' style='width:55px;' value='4' placeholder='4' /></div><br /><br /><button data-v='Admin/misDispos?return=results&cluster=&dispo=&Asecs=&NIsecs=' onclick='fetchURL(this.getAttribute(\"data-v\"));'>Run</button></div><div style='position:relative;width:100%;margin: 50px auto;text-align:center' id='results'></div>";
}