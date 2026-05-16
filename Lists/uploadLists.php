<?php
require_once __DIR__ . '/../config/bootstrap.php';



ini_set('memory_limit', '1024M');

function validateForm() {
    if ($_POST['type'] !== "ROUST") {
        if (empty($_POST['camp']) || empty($_POST['dept']) || empty($_POST['type'])) {
            echo "All fields are required. Please fill out the form completely. <a href='javascript:history.back()'>Click Here</a> to start over.";
            exit;
        }
    }
}

function getFileExtension($fileName) {
    return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
}

function isHeaderRow($row) {
    $headerArray = array("FN", "PHONE", "NUMBER", "NUMBER1", "PHONE_NUMBER", "FIRST", "FIRST_NAME", "ID", "PHONE10");
    return in_array(strtoupper($row), $headerArray);
}

function previewCSV($csvFilePath) {
    global $campArr;
    if ( isset( $_POST[ 'preview' ] ) ) {
        if (isset($_POST['dept'])) {
            if ( $_POST['dept'] == "LL" || $_POST['dept'] == "CJ" ) {
                if(!isset($campArr[$_POST['camp']]["vici"][$_POST['dept']])) {
                    echo "There are no definitions for this Campaign/DeptCode combo. <a href='javascript:history.back()'>Click Here</a> to start over.";
                    
                } 
            }
        }    
        /*
        $directory = dirname($csvFilePath);; 
        $fileNo = 1;
        $entries = array_filter(scandir($directory), function($entry) {
            return $entry !== '.' && $entry !== '..';
        });
        $entries = $entries == "" ? 0 : $entries;
        $fileNo = max(array_map(function($entry) {
            return (int)explode(".", $entry)[0];
        }, $entries)) + 1;
            
        $file = "$fileNo.csv";*/
        $target = basename($csvFilePath);
        $tmpFile = $csvFilePath;
        if (!file_exists($tmpFile)) {
            echo "Uploaded file does not exist.";
            exit;
        }       
        $listName = explode( ".", str_replace( " ", "+", $_FILES[ 'file' ][ 'name' ] ) );
        $listName = $listName[ 0 ];
        $array = array_map( 'str_getcsv', file( $tmpFile ) );
        foreach ( $array as $row ) {
            if (isHeaderRow($row[ 0 ])) {
                continue;
            }
            //echo $row[0];
            //exit;
            if ( is_numeric( $row[ 0 ] ) ) {
                if ( strlen( $row[ 0 ] ) == 10 ) {
                    if ( strlen( $row[ 5 ] ) == 2 && is_numeric( $row[ 6 ] ) ) { //everything in A-H
                        echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: {$row[0]}<br /><strong>First</strong>: {$row[1]}<br /><strong>Last</strong>: {$row[2]}<br /><strong>Address</strong>: {$row[3]}<br /><strong>City</strong>: {$row[4]}<br /><strong>State</strong>: {$row[5]}<br /><strong>ZIP</strong>: {$row[6]}<br /><strong>Amount</strong>: {$row[7]}<br /><strong>Type</strong>: {$_POST['camp']}_{$_POST['type']}<br /><strong>Dept</strong>: {$_POST['dept']}<br /><br />";
                        break;
                    } else if ( strlen( $row[ 8 ] ) == 2 && is_numeric( $row[ 9 ] ) ) {
                        if ($_POST[ 'type' ] == "ROUST") { //ROUST
                            $type = $_POST[ 'type' ];
                            $payType = "U";
                            $dept = $row[21];
                            $roust = substr($row[18], 3) . "_R";
                            echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: {$row[0]}<br /><strong>First</strong>: {$row[3]}<br /><strong>Last</strong>: {$row[4]}<br /><strong>Address</strong>: {$row[6]}<br /><strong>City</strong>: {$row[7]}<br /><strong>State</strong>: {$row[8]}<br /><strong>ZIP</strong>: {$row[9]}<br /><strong>Sale Amount</strong>: {$row[12]}<br /><strong>Sale Date</strong>: {$row[11]}<br /><strong>Invoice #</strong>: {$row[17]}<br /><strong>PayType</strong>: $payType<br /><strong>Type Example</strong>: $roust<br /><strong>Dept</strong>: $dept<br /><br />";
                            break;
                        } else { //NAMS special order
                            $type = $_POST[ 'type' ];
                            $payType = ( strpos( $row[ 24 ], "CC" ) !== false ) ? "CC" : "Mail";
                            echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: {$row[0]}<br /><strong>First</strong>: {$row[3]}<br /><strong>Last</strong>: {$row[4]}<br /><strong>Address</strong>: {$row[6]}<br /><strong>City</strong>: {$row[7]}<br /><strong>State</strong>: {$row[8]}<br /><strong>ZIP</strong>: {$row[9]}<br /><strong>Amount</strong>: {$row[14]}<br /><strong>PayType</strong>: $payType<br /><strong>Type</strong>: {$_POST['camp']}_{$_POST['type']}<br /><strong>Dept</strong>: {$_POST['dept']}<br /><br />";
                            break;
                        }
                    }
                } else { //IDs in A, everything else in B - I
                    echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: {$row[1]}<br /><strong>First</strong>: {$row[2]}<br /><strong>Last</strong>: {$row[3]}<br /><strong>Address</strong>: {$row[4]}<br /><strong>City</strong>: {$row[5]}<br /><strong>State</strong>: {$row[6]}<br /><strong>ZIP</strong>: {$row[7]}<br /><strong>Type</strong>: {$_POST['camp']}_{$_POST['type']}<br /><strong>Dept</strong>: {$_POST['dept']}<br /><br />";
                    break;
                }
            } else if ( is_numeric( str_replace( "-", "", $row[ 0 ] ) ) && strlen( str_replace( "-", "", $row[ 0 ] ) ) == 10 ) { //NAMS regular file
                $phone = str_replace( "-", "", $row[ 0 ] );
                echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: $phone<br /><strong>First</strong>: {$row[1]}<br /><strong>Last</strong>: {$row[2]}<br /><strong>Address</strong>: {$row[3]}<br /><strong>City</strong>: {$row[4]}<br /><strong>State</strong>: {$row[5]}<br /><strong>ZIP</strong>: {$row[6]}<br /><strong>Amount</strong>: {$row[8]}<br /><strong>Email</strong>: {$row[11]}<br /><strong>Type</strong>: {$_POST['camp']}_{$_POST['type']}<br /><strong>Dept</strong>: {$_POST['dept']}<br /><br />";
                break;
            } else if ( is_numeric( $row[ 6 ] ) && strlen( trim( $row[ 6 ] ) ) == 10 ) { //LL Zip Files
                echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: {$row[6]}<br /><strong>First</strong>: {$row[0]}<br /><strong>Last</strong>: {$row[1]}<br /><strong>Address</strong>: {$row[2]}<br /><strong>City</strong>: {$row[3]}<br /><strong>State</strong>: {$row[4]}<br /><strong>ZIP</strong>: {$row[5]}<br /><strong>Email</strong>: {$row[8]}<br /><strong>Type</strong>: {$_POST['camp']}_{$_POST['type']}<br /><strong>Dept</strong>: {$_POST['dept']}<br /><br />";
                break;
            } else if ( is_numeric( $row[ 9 ] ) && is_numeric( $row[ 22 ] ) && strlen( $row[ 21 ] ) == 2 && strlen( $row[ 0 ] ) == 2 ) { //NAMS renewal data
                echo "<strong>List Name</strong>: $listName<br /><strong>Phone</strong>: {$row[9]}<br /><strong>First</strong>: {$row[17]}<br /><strong>Last</strong>: {$row[18]}<br /><strong>Address</strong>: {$row[19]}<br /><strong>City</strong>: {$row[20]}<br /><strong>State</strong>: {$row[21]}<br /><strong>ZIP</strong>: {$row[22]}<br /><strong>Type</strong>: {$_POST['camp']}_{$_POST['type']}<br /><strong>Dept</strong>: {$_POST['dept']}<br /><br />";
                break;
            } else {
                echo "This list does not match any of the pre-defined list formats. Try again or see J.  <a href='javascript:history.back()'>Click Here</a> to start over.";
                unlink($tmpFile);
                exit;
                
            }
        }
        move_uploaded_file( $tmpFile, $target );
        $unlinkTarget = urlencode($csvFilePath);
        $camp = isset($_POST['camp']) ? $_POST['camp'] : "ROUST";
        $dept = isset($_POST['dept']) ? $_POST['dept'] : $row[21];
        $type = isset($roust) ? $roust : $_POST['type'];
        echo "<div align='center'>This is the first row of your data. If everything looks good, click Execute below:</div>
            <form action='uploadLists.php' method='post' enctype='multipart/form-data'>
            <input type='text' name='camp' value='$camp' hidden />
            <input type='text' name='dept' value='$dept' hidden />
            <input type='text' name='type' value='$type' hidden />
            <input type='text' name='name' value='$listName' hidden />
            <input type='text' name='target' value='$target' hidden />
            <input type='hidden' name='execute' />
            <div align='center'><input style='line-height: 20px' type='submit' value='Execute' /></div>
            </form>
            <div align='center'>Otherwise, <a href=../adminTools.php?unlink=$unlinkTarget>Go Back</a></div>";
    }
}

function executeCSV($csvFilePath) {
    global $curDate;
    global $camp;
    global $dept;
    global $type;
    global $listName;

    $file = basename($csvFilePath);
    date_default_timezone_set( 'America/New_York' );
    $currentTime = date( 'H:i:s' );
    $explode = explode( ":", $currentTime );
    $hour = $explode[ 0 ];
    $min = $explode[ 1 ] + 2;
    //$i = ($i * 30);
    $i = 0;
    $min = $min + $i;
    if ( $min >= 60 ) {
        $min = $min - 60;
        $hour = $hour + 1;
        if ( $hour == 24 ) {
            $hour = 0;
            $curDate = date( "Y-m-d", strtotime( "+1 day", strtotime( $curDate ) ) );
        }
    }
    $explode = explode( "-", $curDate );
    $mo = $explode[ 1 ];
    $day = $explode[ 2 ];
    $cmd = "$min     $hour    $day       $mo       *       /usr/bin/php -q /srv/www/htdocs/ProSpeaking/Lists/executeListUpload.php $camp $dept $type $listName $file >> /srv/www/htdocs/ProSpeaking/Lists/results.txt 2>&1";
    $output = shell_exec( 'crontab -l' );
    file_put_contents( '/srv/www/htdocs/ProSpeaking/Lists/txt/crontab.txt', $output . $cmd . PHP_EOL );
    $exec = exec( 'crontab /srv/www/htdocs/ProSpeaking/Lists/txt/crontab.txt  2>&1', $out, $var );

    echo "<div align='center' style=',margin-top:20px'>List has been loaded and will be fully uploaded in about 15 minutes. <a href=../adminTools.php>Click here</a> to return home.</div>";
}

function cleanupDirectory($directory) {
    $files = glob($directory . '*'); // Get all file names
    foreach($files as $file){
        if(is_file($file)) unlink($file); // Delete file
    }
}

function isCSV($fileName) {
    return getFileExtension($fileName) === 'csv';
}
function handleExtractedFiles($directory) {
    // Step 1: Filter CSV files that start with "34_" and ignore numbered CSVs like 1.csv, 2.csv, etc.
    $csvFiles = array_filter(scandir($directory), function($file) use ($directory) {
        return isCSV($directory . $file) && preg_match('/^34_/', $file); // Include only files starting with 34_
    });
    
    // Step 2: Find the highest numbered CSV file (like 1.csv, 2.csv, etc.)
    $numberedFiles = array_filter(scandir($directory), function($file) {
        return preg_match('/^(\d+)\.csv$/', $file); // Match files like 1.csv, 2.csv, etc.
    });
    
    $highestNumber = 0;
    
    if (!empty($numberedFiles)) {
        // Extract the numeric parts and find the highest number
        $numbers = array_map(function($file) {
            return (int) pathinfo($file, PATHINFO_FILENAME); // Get the numeric part
        }, $numberedFiles);
        
        $highestNumber = max($numbers); // Get the highest number
    }
    
    // Step 3: Name the combined file as the next higher number
    $newFileNumber = $highestNumber + 1;
    $combinedCSVPath = $directory . $newFileNumber . '.csv';
    
    // Step 4: Open the combined CSV file for writing
    $combinedFile = fopen($combinedCSVPath, 'w');
    
    $firstFile = true;
    
    foreach ($csvFiles as $file) {
        $filePath = $directory . $file;
        
        // Open each CSV file for reading
        if (($handle = fopen($filePath, 'r')) !== false) {
            $isHeader = true;
            
            while (($row = fgetcsv($handle)) !== false) {
                // Skip the header row from all files except the first one
                if ($isHeader && !$firstFile) {
                    $isHeader = false;
                    continue;
                }
                // Write rows to the combined file
                fputcsv($combinedFile, $row);
                $isHeader = false;
            }
            
            fclose($handle);
        }
        
        // Mark the first file as processed
        $firstFile = false;

        // Delete the source file after combining
        unlink($filePath);
    }
    
    // Close the combined file
    fclose($combinedFile);
    
    // Step 5: Now process the combined file
    previewCSV($combinedCSVPath);

    // Uncomment this if you want to clean up the directory after processing
    // cleanupDirectory($directory);
}  

function processZip($zipFilePath) {
    $extractPath = '/srv/www/htdocs/ProSpeaking/Lists/roust/'; // Define your extraction path

    $zip = new ZipArchive;
    if ($zip->open($zipFilePath) === TRUE) {
        $zip->extractTo($extractPath);
        $zip->close();
        
        handleExtractedFiles($extractPath);
    } else {
        echo 'Failed to open ZIP file.';
    }
}

function handleFileUpload($file) {
    // Validate MIME type using file path
    /*$finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'text/csv' && $mimeType !== 'application/zip') {
        echo "Invalid file type. Please upload a valid CSV or ZIP file.";
        exit;
    }*/
    
    // Validate file extension
    $fileType = getFileExtension($file['name']);
    switch ($fileType) {
        case 'csv':
            
            $directory = "/srv/www/htdocs/ProSpeaking/Lists/pending/"; 
            $fileNo = 1;
            
            // Get all valid CSV files from the directory
            $entries = array_filter(scandir($directory), function($entry) use ($directory) {
                return is_file($directory . $entry) && pathinfo($entry, PATHINFO_EXTENSION) === 'csv';
            });
            
            // If there are any CSV files, find the highest number and assign the next number
            if (!empty($entries)) {
                $fileNo = max(array_map(function($entry) {
                    return (int)pathinfo($entry, PATHINFO_FILENAME); // Extract only the numeric part of the filename
                }, $entries)) + 1;
            }
            
            // Construct the new file name
            $newFile = "$fileNo.csv";
            $target = "$directory/$newFile";            
            
            move_uploaded_file( $file['tmp_name'], $target );
            previewCSV($target);
            break;
        case 'zip':
            processZip($file['tmp_name']);
            break;
        default:
            echo 'Unsupported file type. Please upload a CSV or ZIP file.';
            exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    validateForm(); // validate form data before processing
    handleFileUpload($_FILES['file']);
} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $camp = $_POST['camp'];
    $dept = $_POST['dept'];
    $type = $_POST['type'];
    $listName = $_POST['name'];
    $target = $_POST['target'];
    executeCSV($target);
} else {
    echo "
        <style>
            .holder {
                width: 100%;
                margin: 20px auto;
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-row-gap: 5px;
                grid-column-gap: 5px;
            }
            .right {
                text-align: right;
            }
            input {
                line-height: 12px;
            }
        </style>
        <form action='Lists/uploadLists.php' method='post' enctype='multipart/form-data'>
        <div class='holder'>
            <div class='right'>Campaign:</div>
            <div>
                <select name='camp' id='camp' required>
                    <option selected disabled></option>";
                    ksort($campArr);
                    foreach ($campArr as $key => $value) {
                        if ($key !== "ROUST") {
                            echo "<option value='$key'>$key</option>";
                        }
                    }
                echo "</select>
            </div>
            <div class='right'>Dept Code:</div>
            <!--<div><input style='width:60px' type='text' name='dept' id='dept' required /></div>-->
            <div><select name='dept' id='dept' required>
                <option value selected></option>
                <option value='AG'>AG</option>
                <option value='C'>C</option>
                <option value='LL'>LL</option>
                <option value='PS'>PS</option>
                <option value='TT'>TT</option>
            </select></div>
            <div class='right'>Lead Type:</div>
            <div>
                <select name='type' id='type' required onchange='checkLeadType()'>
                    <option value='' selected disabled></option>
                    <option value='COLD'>COLD</option>
                    <option value='XTAP'>XTAP</option>
                    <option value='T4T'>T4T</option>
                    <option value='ROUST'>ROUST</option>
                </select>
            </div>
            <div class='right'>Choose File:</div>
            <div><input type='file' name='file' required></div>
            <input type='hidden' name='preview' />
        </div>
        <div align='center'><input style='line-height: 20px' type='submit' value='Preview' /></div>
    </form>";
}


