<?php
require_once __DIR__ . '/../../dev/load.php';
$pslw = connectToCluster('pslw', $clusters);

$DPHTable = "DPH2";

function getSales( $date1, $date2 ) {
    global $pslw;
    global $curDate;
    global $DPHTable;
    global $campArr;
    if ( $date1 == $curDate && $date2 == $curDate) {
        $table = "$DPHTable.DAILY";
    } else if ($date2 == $curDate) {
        $table = "(select * from $DPHTable.DAILY union all select * from $DPHTable.ARCHIVE) a";
    } else {
        $table = "$DPHTable.ARCHIVE";
    }
    $fullDate1 = date( 'l M j, Y', strtotime( $date1 ) );
    $fullDate2 = date( 'l M j, Y', strtotime( $date2 ) );
    $title = $fullDate1 == $fullDate2 ? "Daily Sales Report for $fullDate1" : "Weekly Sales Report for $fullDate1 through $fullDate2";
    $body = "
    <html><head>
<style type='text/css'>
    table {
        background: #eee;
        border-collapse: collapse; 
        margin-bottom: 50px;
    }
    td, th {
        text-align: center;
        padding: 5px;
        border: 1px #ccc solid;
    }
    th {background: #ddd}
</style>
</head><body>
<table align='center'>
    <tr>
        <th colspan='14'>$title</th>
    </tr>
    <tr>
        <th>Campaign</th>
        <th>Logins</th>
        <th>Manager</th>
        <th>XFERs #</th>
        <th>XFERs/hr</th>
        <th>Conv %</th>
        <th>DPH</th>
        <th>Sales #</th>
        <th>Sales $</th>
        <th>Mail #</th>
        <th>Mail $</th>
        <th>CCs #</th>
        <th>CCs $</th>
        <th>CC %</th>
    </tr>";
    
    $aCountT = 0;
    $aXfersT = 0;
    $aHoursT = 0;
    $tHoursT = 0;
    $tSalesT = 0;
    $cSalesT = 0;
    $numSalesT = 0;
    $numCCsT = 0;

    $currentCamp = ""; 
    foreach ($campArr as $camp => $details) {

        if ($currentCamp <> $camp) {
            $body .= "<tr><td colspan='14' style='background:#bbb;font-weight:bold;line-height:8px;'>$camp</td></tr>";
            $currentCamp = $camp;
        }
        
        $uniqueValues = array_values($details['vici']);
        $uniqueValues = array_unique($uniqueValues); 

        foreach ($uniqueValues as $value) {

        $key = array_search($value, $details['vici']);

        $sql = "select count(distinct(AGENT)), sum(XFERS), (GREATEST(SUM(CASE WHEN AGENT = PARENT THEN TOTAL_HOURS ELSE 0 END),SUM(CASE WHEN AGENT = AGENT THEN TOTAL_HOURS ELSE 0 END))-LEAST(SUM(CASE WHEN AGENT = PARENT THEN TOTAL_HOURS ELSE 0 END),SUM(CASE WHEN AGENT = AGENT THEN TOTAL_HOURS ELSE 0 END))), sum(TOTAL_HOURS), sum(TOTAL_AMOUNT), sum(CC_AMT), sum(NUM_SALES), sum(CCs) from $table where DATE >= '$date1' and DATE <= '$date2' and CAMPAIGN_ID = $value";

       

        $agents = $pslw->query( $sql )->fetch_array();
        $aCount = $agents[ 0 ];
        $aXfers = $agents[ 1 ];
        $aHours = $agents[ 2 ];
        $tHours = $agents[ 3 ];
        $tSales = $agents[ 4 ];
        $cSales = $agents[ 5 ];
        $numSales = $agents[ 6 ];
        $numCCs = $agents[ 7 ];
        
        $aCountT += $aCount;
        $aXfersT += $aXfers;
        $aHoursT += $aHours;
        $tHoursT += $tHours;
        $tSalesT += $tSales;
        $cSalesT += $cSales;
        $numSalesT += $numSales;
        $numCCsT += $numCCs;

        $aDph = $tHours > 0 ? number_format( ($tSales/$tHours), 2 ) : 0;
        $xph = $tHours > 0 ? ceil( ( $aXfers / $tHours ) ): 0;
        $tSalesTotal = number_format( $tSales, 0 );
        $mSales = $numSales - $numCCs;
        $mSalesTotal = number_format( $tSales - $cSales, 0 );
        $cSalesPer = $tSales > 0 ? number_format( ( ( $cSales / $tSales ) * 100 ), 1 ) : 0;
        $conv = $aXfers > 0 ? number_format( ( ( $numSales / $aXfers ) * 100 ), 1 ): 0;
        $body.= "<tr class='trHover'><td title='$key'>$value</td><td>$aCount</td><td>-</td><td>$aXfers</td><td>$xph</td><td>$conv%</td><td title='$tHours'>$$aDph</td><td>$numSales</td><td>$$tSalesTotal</td><td>$mSales</td><td>$$mSalesTotal</td><td>$numCCs</td><td>$$cSales</td><td>$cSalesPer%</td></tr>
        ";
        }
    
    }
    
    $aDph = number_format( ($tSalesT/$tHoursT), 2 );
    $xph = ceil( ( $aXfersT / $tHoursT ) );
    $tSalesTotal = number_format( $tSalesT, 0 );
    $mSales = $numSalesT - $numCCsT;
    $mSalesTotal = number_format( $tSalesT - $cSalesT, 0 );
    $cSalesPer = number_format( ( ( $cSalesT / $tSalesT ) * 100 ), 1 );
    $conv = number_format( ( ( $numSalesT / $aXfersT ) * 100 ), 1 );
    $body.= "<tr><th>TOTAL</th><th>$aCountT</th><th>-</th><th>$aXfersT</th><th>$xph</th><th>$conv%</th><th title='$tHoursT'>$$aDph</th><th>$numSalesT</th><th>$$tSalesTotal</th><th>$mSales</th><th>$$mSalesTotal</th><th>$numCCsT</th><th>$$cSalesT</th><th>$cSalesPer%</th></tr><tr style='display:none'><td>$sql</td></tr></table></body></html>";

       
    return $body;
}

if ( isset( $argv[ 1 ] ) || isset( $_GET['email'] ) ) {
    
    $date = isset( $_GET['date'] ) ?  $_GET['date'] : $curDate;
    echo $curTimeStamp . " -- " . $date . "\r\n";

    $to      = 'jamieprspeaking2019@gmail.com, jbmarketing617@gmail.com, jasonniesen@gmail.com';
    //$to = 'jasonniesen@gmail.com';
    if ( $argv[ 1 ] == "daily" || $_GET['email'] == "daily" ) {
        $subject = "SB Team - Daily Sales for $date";
        $message = getSales( $date, $date );
    } else if ( $argv[ 1 ] == 'weekly'  || $_GET['email'] == "weekly") {
        $start = date("Y-m-d", strtotime("last Sunday", strtotime($date)));
        //check # of days worked to see if report should be sent on Friday or Saturday (for make up holidays)
        $check = $pslw->query( "select count(distinct(DATE)) as c, max(DATE) as m from (select * from $DPHTable.DAILY union all select * from $DPHTable.ARCHIVE) a where DATE between '$start' and '$date'" )->fetch_array();
        if ($today == "Friday" && $check["c"] < 5) {
            exit;
        }        
        if ($today == "Saturday") {
            if ($check["c"] == 5 && $check['m'] != $curDate) {
                exit;
            }
        }
        $subject = "SB Team - Weekly Sales for $start - $date";
        $message = getSales( $start, $date );
    };
       
    $headers = 'From: BareTechs-noreply@pslw-admin.baretechs.com' . "\r\n" .
    'Reply-To: noreply@pslw-admin.baretechs.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion() . "\r\n" .
    'MIME-Version: 1.0' . "\r\n" .
    'Content-type: text/html; charset=utf-8';

    if (mail($to, $subject, $message, $headers)) {
        echo "Email sent successfully.\r\n\r\n";
    } else {
        echo "Failed to send email.";
        // Log error message to the same file
        $errorMessage = "Failed to send email. Recipients: $to, Subject: $subject\r\n";
        echo $errorMessage . "\r\n\r\n";
    }
    
} else {
    $date1 = isset($_GET['date1']) ? $_GET['date1'] : $curDate;
    $date2 = isset($_GET['date2']) ? $_GET['date2'] : $curDate;
    echo "<div align='center' style='margin-top: 20px'>From: <input style='line-height:14px' type='date' value='$date1' id='date1' /> - To: <input style='line-height:14px' type='date' value='$date2' id='date2' />&nbsp;<button data-v='Reports/Sales/dailySales?date1=&date2=' onclick='fetchURL(this.getAttribute(\"data-v\"));'>Go</button><div>";
    echo getSales( $date1, $date2 );
}

