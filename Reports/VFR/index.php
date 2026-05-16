<?phprequire_once __DIR__ . '/../../dev/load.php';
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "VFR");

for ($i = 1; $i <= 6; $i++) {
    ${"day" . ($i + 1)} = strtoupper(date('l', strtotime("-{$i} days", strtotime($today))));
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="https://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>VFR Report</title>
<link href="../../bootstrap/css/bootstrap.min.css?v=<?php echo rand() ?>" rel="stylesheet" media="screen"/>
<link href="../../bootstrap/css/bootstrap-theme.min.css?v=<?php echo rand() ?>" rel="stylesheet" media="screen"/>
<link rel="stylesheet" href="style.css?v=<?php echo rand() ?>" type="text/css" media="screen"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js" type="text/javascript"></script> 
<script src="VFR.js?v=<?php echo rand() ?>" type="text/javascript"></script>
</head>

<body>
<div id="logo-container" style="flex-shrink: 0;">
            <a href="#" style="font-size: 22px; font-weight: bold; color: #333; text-decoration: none; line-height: 40px;">ProSpeaking</a>
        </div>        
<nav class="navbar navbar-default navbar-fixed-top" style='width:1024px;margin:0 auto;background:lavender'>
    <div class="container" style="text-align:center;">  
        <div id="UpdatedDate" style="right:20px; font-weight:bold; position:absolute; color:#F00; text-align:right"></div>
        <div style="left:10px; font-weight:bold; position:absolute; font-size:16px" id="leftHeader">VFR Report</div>
        <div id="sort" style='display:none'>CONV_RATE|DESC</div>
            <div id="center_container">
                <div style="float:left;">
                    <h2>
                        <select id="range" name="range" onchange='updateDropDowns(); checkCustom(this.value)'>
                            <option title="Shows stats for the current day" value="DAILY" selected>Daily</option>
                            <option title="Shows stats for the most recent 7 days, not including the current day" value="7">Weekly</option>
                            <option title="Shows stats for the most recent 14 days, not including the current day" value="14">Bi-Weekly</option>
                            <option title="Shows stats for the most recent 31 days, not including the current day" value="31">Monthly</option>
                            <option title="Select range to show stats" value="CUSTOM">Custom</option>
                            <option disabled>-Prev Days-</option>
                            <option title="Shows stat for the most recent <?php  echo $day2 ?>" value="1">
                            <?php  echo substr($day2,0,3) ?>
                            </option>
                            <option title="Shows stat for the most recent <?php  echo $day3 ?>" value="2">
                            <?php  echo substr($day3,0,3) ?>
                            </option>
                            <option title="Shows stat for the most recent <?php  echo $day4 ?>" value="3">
                            <?php  echo substr($day4,0,3) ?>
                            </option>
                            <option title="Shows stat for the most recent <?php  echo $day5 ?>" value="4">
                            <?php  echo substr($day5,0,3) ?>
                            </option>
                            <option title="Shows stat for the most recent <?php  echo $day6 ?>" value="5">
                            <?php  echo substr($day6,0,3) ?>
                            </option>
                            <option title="Shows stat for the most recent <?php  echo $day7 ?>" value="6">
                            <?php  echo substr($day7,0,3) ?>
                            </option>
                        </select>
                        <div style='display:none; font-size: 14px' id='custom'>
                        <input type='date' id="start" style='width:105px; line-height:31px' onchange='updateDropDowns();' /> to <input type='date' id="end" style='width:105px; line-height:31px' onchange='updateDropDowns();' /> <a onclick="checkCustom('RESET'); updateDropDowns();" style='font-size:12px; cursor: pointer'>Presets</a></div>                       
                    </h2>
                </div>
                <div style="float:left; padding-left:10px; padding-top:20px;">
                    <div>AutoRefresh</div>
                    <label class="switch">
                        <input type="radio" id="autoRefresh" onclick="clearIntervals()" checked>
                        <span class="slider round"></span> </label>
                </div>
            </div>
    </div>
    <table id="collOptions" style='width:50%'>
    <colgroup>
        <col width="20%">
        <col width="20%">
        <col width="20%">
        <col width="20%">
        <col width="20%">
    </colgroup>
    <tr>
        <th colspan="3" class="border-right" scope="col">Filters</th>
        <th scope="col">Views</th>
    </tr>
    <tr>
        <td class="border-right">
            <select name="cluster" id="cluster" onchange='updateDropDowns();'>
                <option value="" selected="selected" style="background-color:#ccc">--Cluster--</option>
                <?php
                $clusters = $pslw->query("select distinct(AGENT_TYPE) from DAILY order by AGENT_TYPE asc");
                foreach ($clusters as $row) {
                    echo "<option value='{$row['AGENT_TYPE']}'>psl{$row['AGENT_TYPE']}</option>";
                };
                ?>
            </select>
        </td>
        <td class="border-right">
            <select name="dept" id="dept" onchange='updateDropDowns();'>
                <option value="" selected="selected" style="background-color:#ccc">--DeptKey--</option>
                <?php
                $agents = $pslw->query("select distinct(DEPTKEY) from DAILY order by DEPTKEY asc");
                foreach ($agents as $row) {
                    if ($row['DEPTKEY'] == "CD") {
                        $deptName = "C-Data";
                    } else {
                        $deptName = $row['DEPTKEY'];
                    }
                    echo "<option value='{$row['DEPTKEY']}'>$deptName</option>";
                };
                ?>
            </select>
        </td>
        <td class="border-right">
            <select name="list" id="list" onchange='updateDropDowns();'>
                <option value="" selected="selected" style="background-color:#ccc">--ListName--</option>
                <?php
                $listIDs = $pslw->query("select distinct(LIST_ID), LIST_NAME, AGENT_TYPE from DAILY order by AGENT_TYPE, LIST_ID");
                echo "<option disabled style='background-color:#ccc'>psl1</option>";
                $cluster = 1;
                foreach ($listIDs as $list) {
                    if ($list['AGENT_TYPE'] == 2 && $cluster == 1) {
                        echo "<option disabled style='background-color:#ccc'>psl2</option>";
                        $cluster = 2;
                    }
                    echo "<option value='{$list['LIST_ID']}'>{$list['LIST_NAME']}</option>";
                };
                ?>
            </select>
        </td>
        <td>
            <select name="agentCamp" id="agentCamp" onchange="resetViews(this.id);updateDropDowns();">
                <option value="" selected="selected" style="background-color:#ccc">--Agent by Campaign--</option>
                <?php
                $agents = $pslw->query("select distinct(AGENT) from DAILY where AGENT <> '' order by AGENT asc");
                foreach ($agents as $row) {
                    echo "<option value='{$row['AGENT']}'>{$row['AGENT']}</option>";
                };
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td>
            <select name="vfrType" id="vfrType">
            <option value="" selected="selected" style="background-color:#ccc">--VfrType--</option>
            <option value="sb">SB VFR</option>
            <option value="live">Live VFR</option>
            </select>
         </td>
        <td class="border-right">
            <select id="campaign" onchange='updateDropDowns();'>
                <option value="" selected="selected" style="background-color:#ccc">--Campaign--</option>
                <?php 
                $campaigns = $pslw->query("select distinct(CAMPAIGN_ID) from DAILY order by CAMPAIGN_ID asc");
                foreach ($campaigns as $type) {
                    echo "<option value='{$type['CAMPAIGN_ID']}'>{$type['CAMPAIGN_ID']}</option>";
                };
                ?>
            </select>
        </td>
        
        <td class="border-right">
            <select id="leadType" onchange='updateDropDowns();'>
                <option value="" selected="selected" style="background-color:#ccc">--LeadType--</option>
                <?php 
                $leadTypes = $pslw->query("select distinct(TYPE) from DAILY order by TYPE asc");
                foreach ($leadTypes as $type) {
                    $display = $type[ 'TYPE' ] == "R" ? "ROUST" : $type[ 'TYPE' ];
                    echo "<option value='{$type[ 'TYPE' ]}'>$display</option>";
                };
                ?>
            </select>
        </td>
        <td align="center" id="agentList">
            <select name="agent" id="agent" onchange="resetViews(this.id);updateDropDowns();">
                <option value="" selected="selected" style="background-color:#ccc">--Agent by Day--</option>
                <?php
                $agents = $pslw->query("select distinct(AGENT) from DAILY where AGENT <> '' order by AGENT asc");
                foreach ($agents as $row) {
                    echo "<option value='{$row['AGENT']}'>{$row['AGENT']} </option>";
                };
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td colspan="5" align="center">
            <button id="" class="collButton" onclick="clearIntervals(); showReportInterval(this.id);">Submit</button>
            &nbsp;
            <button class="collButton" onclick="resetOptions()">Reset</button>
        </td>
    </tr>
</table>

    </div>
</nav>
<div id="results" class="collResults"></div>
</body>
</html>
