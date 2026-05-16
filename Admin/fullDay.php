<?php
// fullDay.php — AJAX trigger for full-day importrequire_once __DIR__ . '/../dev/load.php';

// Path to skip flag file and import script
$skipFlagFile  = '/srv/www/htdocs/ProSpeaking/Reports/DPH2/skip_cron.flag';
$importScript  = '/srv/www/htdocs/ProSpeaking/Reports/DPH2/dailyDPH_import.php';

// Only run when ?date=YYYY-MM-DD is present
if (isset($_GET['date'])) {
    $date = $_GET['date'];
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Invalid date format. Please use YYYY-MM-DD.";
    } else {

      //Lets drop some breadcrumbs for the mysterious DPH Not Updating issue - 8/27/2025
      $pslw = connectToCluster('pslw', $clusters);
      $pslw->select_db( "DPH2" );
      $curTimestamp = date('Y-m-d H:i:s');      
      $ts   = date('Ymd_His');
      $snap = "DPH_snap_$ts";
      $pslw->query("CREATE TABLE `$snap` AS SELECT * FROM `DAILY`");
      $message = "$curTimestamp: <br />DPH snapshot captured: $snap<br />";

      // 1) Build and fire CLI import
      $cmd = sprintf(
          'php %s --full-day=%s > /srv/www/htdocs/ProSpeaking/Reports/DPH2/results.txt 2>&1',
          escapeshellarg($importScript),
          escapeshellarg($date)
      );
      shell_exec($cmd);
      $message .= "Full-day import triggered for {$date}. ";
      
      // 2) Create skip flag
      if (file_put_contents($skipFlagFile, "3")) {
        $message .= "Cron will skip next 3 runs.<br />";
      }
    }
}

// Determine default for the date picker
$defaultDate = isset($date) ? $date : date('Y-m-d');
?>
<div align="center"><strong>Trigger Full-Day DPH Import</strong><br /><br />
<label for="import_date">Select Date:</label>
<input
  type="date"
  id="import_date"
  name="date"
  value="<?php echo htmlspecialchars($defaultDate); ?>"
  style="line-height: 14px;"
/><br />
<button onclick="triggerFullDayDPH()">Run Full-Day Import</button></div>

<?php if (!empty($message)): ?>
  <div class="message" style="color:green;margin-top:1em;">
    <?php echo $message; ?>
  </div>
<?php elseif (!empty($error)): ?>
  <div class="error" style="color:red;margin-top:1em;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>
