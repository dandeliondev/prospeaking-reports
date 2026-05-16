<?php
require_once __DIR__ . '/../dev/load.php';$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "Sales");

if ( isset( $_GET[ 'date' ] ) ) {
    $date = date("Y-m-d", strtotime($_GET[ 'date' ]));
} else {
    $date = $curDate;
}
$curFileDate = str_replace( "-", "", $date );

$header = array('PHONE #','REPCODE','REPNAME','SALES DATE','SALES TIME','FIRST NAME','LAST NAME','COMPANY','ADDRESS1','ADDRESS2','CITY','STATE','POSTAL CODE','PROJECT','SALE AMOUNT','VERIFIER','PAYMENT TYPE','OCCUPATION','EMPLOYER','EMAIL','MOB','RENEWAL CODE','DEPARTMENT_CODE','CONTENT_CODE','MAIL_CODE','AUTH_CODE');

//$sql = "select a.PHONE, a.AGENT, a.AGENT_NAME, date_format(a.START_TIME, '%m/%d/%Y') as DATE, time(a.START_TIME) as TIME, a.FIRST_NAME, a.LAST_NAME, '', a.ADDRESS, '', a.CITY, a.STATE, a.ZIP, b.NAMS, a.AMOUNT, a.VERIFIER, a.CC, a.OCCUPATION, a.EMPLOYER, a.EMAIL, '', '', a.DEPTKEY from Sales a, CAMPAIGNS b where a.CAMPAIGN = b.CODE and  a.START_DATE = '$date' and a.TYPE not like '%ROUST' order by a.PHONE ASC;";

$sql = "select PHONE, AGENT, AGENT_NAME, date_format(START_TIME, '%m/%d/%Y') as DATE, time(START_TIME) as TIME, FIRST_NAME, LAST_NAME, '', ADDRESS, '', CITY, STATE, ZIP, CAMPAIGN, AMOUNT, VERIFIER, CC, OCCUPATION, EMPLOYER, EMAIL, '', '', DEPTKEY, '', '', '' from Sales where START_DATE = '$date' and TYPE not like '%ROUST' order by CAMPAIGN ASC, TIME ASC;";

$query = $pslw->query( $sql );
$totalCount = 0;
$totalAmount = 0;
$camps = [];
if ( $query->num_rows > 0 ) {

    echo "\r\n\r\n\r\n$curTimeStamp\r\n\r\n";
    
    $fc = fopen( '/srv/www/htdocs/ProSpeaking/Sales/salesFiles/34_' . $curFileDate . '.csv', 'w' );
    
    if ( $fc ) {
        fwrite( $fc, implode( ",", $header ) . PHP_EOL );
        while ( $row = $query->fetch_array( MYSQLI_NUM ) ) {
            fwrite( $fc, implode( ",", $row ) . PHP_EOL );
            
            $camp = $row[ 13 ];
            $amount = $row[ 14 ];

            $totalCount++;
            $totalAmount += $amount;

            if (!isset($camps[$camp])) {
                $camps[$camp] = [
                    'count' => 0,
                    'sum' => 0
                ];
            }

            $camps[$camp]['count']++;
            $camps[$camp]['sum'] += $amount;
        }
        fclose( $fc );
    }
    sleep( 5 );
    
    $body = "";
    foreach ($camps as $camp => $data) {
        $count = number_format($data['count'], 0);
        $sum = number_format($data['sum'], 0);
        $body .= "$camp: $count Pieces for $$sum\r\n";
    }
    $totalCount = number_format($totalCount, 0);
    $totalAmount = number_format($totalAmount, 0);
    $body .=  "\r\nTotal: $totalCount Pieces for $$totalAmount";

    $remote = "34_$curFileDate.csv";
    $local = "/srv/www/htdocs/ProSpeaking/Sales/salesFiles/34_$curFileDate.csv";

    // connect and login to FTP server	
    $ftp_server = "$ftp_server/$remote";

    $ch = curl_init();
    $fp = fopen( $local, 'r' );

    curl_setopt( $ch, CURLOPT_URL, $ftp_server );
    curl_setopt( $ch, CURLOPT_USERPWD, $ftp_username . ':' . $ftp_userpass );
    curl_setopt( $ch, CURLOPT_UPLOAD, 1 );
    curl_setopt( $ch, CURLOPT_INFILE, $fp );
    curl_setopt( $ch, CURLOPT_INFILESIZE, filesize( $local ) );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
    curl_setopt( $ch, CURLOPT_USE_SSL, FALSE );
    curl_setopt( $ch, CURLOPT_VERBOSE, TRUE );

    curl_exec( $ch );
    $httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $error_no = curl_errno( $ch );
    curl_close( $ch );
    if ( $error_no == 0 && $totalAmount > 0) { 

        $to      = 'import@nafulfillment.com, jamieprspeaking2019@gmail.com, JBmarketing617@gmail.com; jasonniesen@gmail.com';
        //$to = 'jasonniesen@gmail.com';
        
        $subject = "Office 34: Sales File Ready - $date";
        $message = "Hello,\r\n\r\nThe sales file (34_$curFileDate.csv) for Office 34 has been uploaded to ShareFile.\r\n\r\n$body\r\n\r\nThank you!\r\n\r\nSystem Admin\r\nProSpeaking, LLC.";
        $headers = 'From: noreply@psl1-admin.baretechs.com' . "\r\n" .
        'Reply-To: noreply@psl1-admin.baretechs.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    
        if (mail($to, $subject, $message, $headers)){
            echo $body;
        } else {
            echo "Email not sent";
        }
                
        
        
    } else {
        $to = 'jasonniesen@gmail.com';
                
        $subject = "Office 34: No Sales File!! - $date";
        $message = "Hello,\r\n\r\nThe sales file (34_$curFileDate.csv) for Office 34 did NOT upload to ShareFile.\r\n\r\n$body\r\n\r\nThank you!\r\n\r\nSystem Admin\r\nProSpeaking, LLC.";
        $headers = 'From: noreply@psl1-admin.baretechs.com' . "\r\n" .
        'Reply-To: noreply@psl1-admin.baretechs.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    
        mail($to, $subject, $message, $headers);
        echo "<div align='center' style='margin-top: 50px;'>Failed! 34_$curFileDate.csv was not uploaded to the NAMS server.\r\n$httpcode\r\n$error_no</div>";
    }
} else {
    echo $sql;
}


mysqli_close( $psl1 );