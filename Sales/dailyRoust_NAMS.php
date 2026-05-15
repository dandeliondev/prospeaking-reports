<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once("/srv/www/php_include.php");
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "Sales");

setlocale(LC_ALL, 'en_US.UTF-8');
mysqli_set_charset($pslw, "utf8mb4");

$filename = '/srv/www/htdocs/ProSpeaking/Sales/roustFiles/' . $curFileDate . '.csv';
$file = fopen($filename, 'w');

fputcsv($file, ['Invoice', 'Amount', 'Note', 'Key', 'Date', 'Rep', 'Camp', 'Phone']);


// Fetch data from the database
$result = $pslw->query("SELECT Invoice, Amount, Note, `Key`, `Date`, Rep, Camp, Phone FROM Roust WHERE Submitted IS NULL ORDER BY Note DESC");

$records = [];  // Array to hold Invoice and Camp pairs for updating Submitted status

$count = 0;
$sales = 0;
$amount = 0;

// Write each row to the CSV and collect Invoice and Camp pairs
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($file, $row);
    $records[] = ['Invoice' => $row['Invoice'], 'Camp' => $row['Camp']];  // Collect Invoice and Camp pairs
    $count++;
    if ($row['Amount'] > 0) {
        $sales++;
        $amount += $row['Amount'];
    }
}

$body = "$count total records submitted. $sales roust sales for $$amount";

fclose($file);

$api_url = 'https://tools.nams-inc.com/postUpload/34/34_API/3a7161a4-dc7c-4754-b46d-115b4e01bac9/34_Format/1/run';

$ch = curl_init();  // Initialize cURL

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: multipart/form-data'
]);
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Attach the CSV file
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'file1' => new CURLFile($filename)  // Attaches the file correctly for a POST request
]);


$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$error_no = curl_errno($ch);




// Check for errors
if (curl_errno($ch)) {
    //echo '\r\n\r\n' . $curTimeStamp . '\r\n cURL error: ' . curl_error($ch);
    echo PHP_EOL . PHP_EOL . $curTimeStamp . PHP_EOL . 'Error Initializing cURL'  . PHP_EOL . 'Response: ' . $response . PHP_EOL;

    
    $to = 'jasonniesen@gmail.com';
                
    $subject = "Office 34: Roust Failure!! - $curDate";
    $message = "Hello,\r\n\r\nThe rousting data for Office 34 did NOT post to NAMS.\r\n\r\ncURL error: " . curl_error($ch) . "\r\n$response\r\nThank you!\r\n\r\nSystem Admin\r\nProSpeaking, LLC.";
    $headers = 'From: noreply@pslw-warehouse.baretechs.com' . "\r\n" .
    'Reply-To: noreply@pslw-warehouse.baretechs.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

    mail($to, $subject, $message, $headers);
} else {
    // Update Submitted status if the response is successful
    if (strpos($response, 'Uploaded file successfully') !== false) {  // Adjust based on actual success response
        echo PHP_EOL . PHP_EOL . $curTimeStamp . PHP_EOL . 'Succesfully Uploaded to NAMS'  . PHP_EOL .  'Response: ' . $response . PHP_EOL;
        // Build the WHERE clause for updating based on Invoice and Camp pairs
        $updateConditions = [];
        foreach ($records as $record) {
            $invoice = $record['Invoice'];
            $camp = $record['Camp'];
            $updateConditions[] = "(Invoice = '$invoice' AND Camp = '$camp')";
        }

        // Join the conditions with OR to update matching rows
        $whereClause = implode(' OR ', $updateConditions);
        $updateQuery = "UPDATE Roust SET Submitted = 1 WHERE $whereClause";

        $pslw->query($updateQuery);  // Execute the update query
        echo 'Records marked as submitted.\r\n';        

        $to      = 'jamieprspeaking2019@gmail.com, JBmarketing617@gmail.com, jasonniesen@gmail.com';
        //$to = 'jasonniesen@gmail.com';
        
        $subject = "Office 34: Rousting Data Posted - $curDate";
        $message = "Hello,\r\n\r\nThe $curDate Roust Data for Office 34 has been posted to NAMS.\r\n\r\n$body\r\n\r\nThank you!\r\n\r\nSystem Admin\r\nProSpeaking, LLC.";
        $headers = 'From: noreply@pslw-warehouse.baretechs.com' . "\r\n" .
        'Reply-To: noreply@pslw-warehouse.baretechs.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

        if (mail($to, $subject, $message, $headers)){
            echo $body . PHP_EOL;
        } else {
            echo "Email not sent\r\n";
        }
    } else {
        echo PHP_EOL . PHP_EOL . $curTimeStamp . PHP_EOL . 'Error Uploading to NAMS'  . PHP_EOL .  'Response: ' . $response . PHP_EOL;
        echo PHP_EOL . "HTTP Status Code: $http_code" . PHP_EOL;
        echo "cURL Error No: $error_no" . PHP_EOL;
        echo "cURL Error: $curl_error" . PHP_EOL;
        echo "Response: $response" . PHP_EOL;
        
        $to = 'jasonniesen@gmail.com';
                
        $subject = "Office 34: Roust Failure!! - $curDate";
        $message = "Hello,\r\n\r\nThe rousting data for Office 34 did NOT post to NAMS.\r\n\r\n$response\r\nThank you!\r\n\r\nSystem Admin\r\nProSpeaking, LLC.";
        $headers = 'From: noreply@pslw-warehouse.baretechs.com' . "\r\n" .
        'Reply-To: noreply@pslw-warehouse.baretechs.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

        mail($to, $subject, $message, $headers);
    }
}

curl_close($ch);  // Close cURL session

// Optionally delete the file after upload
// unlink($filename);
