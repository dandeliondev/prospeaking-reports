<?php
include_once("php_include.php");
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
    <div class="container" style="text-align:center;">
        <div id="center_container" style='display: grid;grid-template-columns: 3fr;width:400px;margin:0 auto'>
            <div><a target="_blank" href="Reports/DPH/index.php">DPH Report</a>
            <h3 style="margin-top:0px">What would you like to do?<br />
                <select id='range' style='padding: 0 10px'>
                    <option value='' disabled selected></option>
                    <option value='Reports/Sales/dailySales'>Sales Report</option>
                    <option value='Admin/misDispos'>MisDispos</option>
                    <option value='Admin/addDNC'>Add DNCs</option>
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