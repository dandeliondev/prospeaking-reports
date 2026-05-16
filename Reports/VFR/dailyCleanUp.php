<?phprequire_once __DIR__ . '/../../dev/load.php';
$pslw = connectToCluster('pslw', $clusters);
mysqli_select_db($pslw, "VFR");

$date = isset($_GET['date']) ? date("Y-m-d", strtotime($_GET['date'])) : $yestDate;
$table = (isset($_GET['date']) && date("Y-m-d", strtotime($_GET['date'])) != $yestDate) ? "ARCHIVE" : "DAILY";
$archive = ($table === "ARCHIVE") ? "_archive" : "";

// Copy DAILY data to ARCHIVE if applicable, then truncate DAILY
if ($table === "DAILY") {
    $insertDaily = $pslw->query("INSERT INTO ARCHIVE SELECT * FROM DAILY");
    if ($insertDaily) {
        $pslw->query("TRUNCATE DAILY");
    }
}

// Close the statement and the database connection
$stmt->close();
mysqli_close($pslw);