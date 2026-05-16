<?phprequire_once __DIR__ . '/../dev/load.php';
include("/srv/www/vfr_include.php");

$pslv = connectToCluster('pslv', $clusters);


// -------------------------
// 1. ProSpeaking Verifier Hours
// -------------------------
$hours = array();
    
$sql = "SELECT user, SUM(wait_sec + talk_sec + dispo_sec)/3600 AS hours 
        FROM asterisk.vicidial_agent_log
        WHERE event_time BETWEEN '$PSD 00:00:00' AND '$PED 23:59:59' 
            AND user BETWEEN 8002 AND 8099 
            AND user <> 8010
        GROUP BY user;";

$getHours = $pslv->query($sql);

foreach ($getHours as $row) {
    $user = $row["user"];
    if (!isset($hours[$user])) {
        $hours[$user] = 0;
    }
    $hours[$user] += $row["hours"];
}

echo "ProSpeaking Verifier Hours for:<br /> $PSD thru $PED<br /><br />";
foreach ($hours as $user => $hrs) {
    if ($hrs > 0) {
        $name = $nameArr[$user]['name'];
        echo "<span title='$name'>$user</span> $hrs<br/>";
    }
}

// -------------------------
// 2. ProSpeaking Verifier Daily CC Bonus
// -------------------------
$bonusDailySQL = "SELECT user, COUNT(*) AS c FROM (
                     SELECT DATE(call_date) AS date, user, COUNT(*) AS c 
                     FROM asterisk.vicidial_closer_log
                     WHERE call_date BETWEEN '$PSD 00:00:00' AND '$PED 23:59:59'
                       AND status = 'CCPLED' 
                       AND user BETWEEN 8002 and 8099
                       AND user <> 8010
                     GROUP BY date, user 
                     HAVING c >= 20
                   ) a 
                   GROUP BY user;";

$dailyBonusUsers = array();
$totalDailyBonusCount = 0;

$getBonuses = $pslv->query($bonusDailySQL);
foreach ($getBonuses as $b) {
    $user = $b["user"];
    if (!isset($dailyBonusUsers[$user])) {
        $dailyBonusUsers[$user] = 0;
    }
    $dailyBonusUsers[$user] += $b["c"];
    $totalDailyBonusCount += $b["c"];
}

echo "<br /><br /><hr>ProSpeaking Verifier Daily CC Bonus:<br /> $PSD thru $PED<br />";
echo "$totalDailyBonusCount @ \$10/each<br /><br />";

$results = "";
foreach ($dailyBonusUsers as $user => $count) {
    $bonusAmount = $count * 10;
    $name = $nameArr[$user]['name'];
    $results .= "<span title='$name'>$user</span> \$" . $bonusAmount . "<br/>";
}
//echo $results;
echo "The bonus structure has changed. This will be turned off until I can automate it";

// -------------------------
// 3. ProSpeaking Verifier Weekly CC Bonus
// -------------------------
$sqlWeeklyBonus = "SELECT user, COUNT(*) AS c 
                   FROM asterisk.vicidial_closer_log
                   WHERE call_date BETWEEN '$PSD 00:00:00' AND '$PED 23:59:59' 
                     AND status = 'CCPLED' 
                     AND user BETWEEN 8002 AND 8099
                     AND user <> 8010 
                   GROUP BY user ORDER BY c desc;";

echo "<br /><br /><hr>ProSpeaking Verifier Weekly CC Bonus:<br /> $PSD thru $PED<br />";
echo "1 @ \$25/each<br /><br />";

$getWeekly = $pslv->query($sqlWeeklyBonus);
$mostCCs = 0;
foreach ($getWeekly as $row) {
    if ($row['c'] >= $mostCCs) {
        $user = $row['user'];
        $name = $nameArr[$user]['name'];
        //echo "<span title='$name'>$user</span> $25</br >";
        $mostCCs = $row['c'];
    }
}
