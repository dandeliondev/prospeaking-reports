<?php
require_once __DIR__ . '/dev/load.php';
if (isset($_GET['unlink'])) {
    unlink($_GET['unlink']);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="https://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Admin Tools - ProSpeaking</title>
    <link href="bootstrap/css/bootstrap.min.css?v=<?php echo rand() ?>" rel="stylesheet" media="screen"/>
    <link href="bootstrap/css/bootstrap-theme.min.css?v=<?php echo rand() ?>" rel="stylesheet" media="screen"/>
    <link rel="stylesheet" href="style.css?v=<?php echo rand() ?>" type="text/css" media="screen"/>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
    <script src="functions.js?v=<?php echo rand() ?>" type="text/javascript"></script>
</head>

<body>
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
    
     <div id="logo-container" style="flex-shrink: 0;">
            <a href="#" style="font-size: 22px; font-weight: bold; color: #333; text-decoration: none; line-height: 40px;">ProSpeaking</a>
        </div>
    
        <div id="center_container" style='display: grid;grid-template-columns: 3fr;width:400px;margin:0 auto'>
            <div><a target="_blank" href="Reports/DPH/index.php">DPH Report</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a target="_blank" href="Reports/DPH2/index.php">DPH (Hourly) Report</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a target="_blank" href="Reports/VFR/index.php">VFR Report</a>
            <h3 style="margin-top:0px">What would you like to do?<br />
                <select id='range' style='padding: 0 10px'>
                    <option value='' disabled selected></option>
					<option align='center' style='background-color:#CCC; color:#000' value='' disabled>-Lists-</option>
                    <option value='Lists/uploadLists'>List Upload</option>
                    <option value='Lists/checkQueue'>Check Queue</option>
                    <!--<option value='Lists/deleteLists'>Delete Lists</option>-->
					<option align='center' style='background-color:#CCC; color:#000' value='' disabled>-Admin-</option>
                    <option value='Reports/Sales/dailySales'>Sales Report</option>
                    <option value='Admin/misDispos'>MisDispos</option>
                    <option value='Admin/dupSales'>DupSales</option>
                    <!--<option value='Admin/addDNC'>Add DNCs</option>-->
                    <!--<option value='Admin/updateAMD'>Update AMD</option>-->
					<option align='center' style='background-color:#CCC; color:#000' value='' disabled>-DPH Options-</option>
                    <option value='Admin/fullDay'>Trigger Full Day</option>
                    <option value='Admin/revertToOriginal'>Revert to Original</option>
                </select>
                <button class="mgrSubmit" onclick="loading('mgrResults'); getRange();">Go!</button>
            </h3>
            </div>
        </div>
    </div>
</nav>
<div id="mgrResults" class="mgrResults"></div>
</body>
</html>
