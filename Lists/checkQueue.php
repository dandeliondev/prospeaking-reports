<?php

$directory = '/srv/www/htdocs/ProSpeaking/Lists/pending'; 

$directory2 = '/srv/www/htdocs/ProSpeaking/Lists/roust'; 

if (isset($_GET['deleteList']) && isset($_GET['id'])) {
    // Sanitize the input to prevent security issues
    $file = basename(substr($_GET['id'], 4));
    $filePath = $directory . "/" . $file;
    $filePath2 = $directory2 . "/" . $file;

    // Check if the file exists before trying to delete it
    if (file_exists($filePath)) {
        unlink($filePath);
    } else if(file_exists($filePath2)) {
        unlink($filePath2);
    } else {
        die("File not found.");
    }

    // Update crontab.txt
    $crontabFilePath = "/srv/www/htdocs/ProSpeaking/Lists/txt/crontab.txt";
    $lines = file($crontabFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        die("Failed to read the crontab file.");
    }

    // Find and remove the last instance of the file reference
    $lastIndex = -1;
    foreach ($lines as $index => $line) {
        if (strpos($line, $file) !== false) {
            $lastIndex = $index;
        }
    }

    if ($lastIndex !== -1) {
        unset($lines[$lastIndex]);
    }

    // Write the updated lines back to crontab.txt
    file_put_contents($crontabFilePath, implode(PHP_EOL, $lines));

    // Update the crontab
    $exec = exec('crontab ' . $crontabFilePath . ' 2>&1', $out, $var);

    echo "<script type='text/javascript'>
            getRange('Lists/checkQueue');
          </script>";
} else {
    // Display the contents of the directory
    $entries = scandir($directory);
    echo "<span style='font-weight:bold'>Normal List Queue:</span><br />";
    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            echo htmlspecialchars($entry) . " <button id='file$entry' onclick='deleteQueue(this.id)' style='line-height: 10px;'>Delete List</button><br />";
        }
    }

    
    // Display the contents of the roust directory
    $entries = scandir($directory2);
    echo "<br /><br /><span style='font-weight:bold'>Roust Queue:</span><br />";
    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            echo htmlspecialchars($entry) . " <button id='file$entry' onclick='deleteQueue(this.id)' style='line-height: 10px;'>Delete List</button><br />";
        }
    }
}