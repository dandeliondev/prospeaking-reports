<?php
// fullDay.php — AJAX trigger for full-day import
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Path to skip flag file and import script
$originalDPH_skipFlag  = '/srv/www/htdocs/ProSpeaking/Reports/DPH/skip_cron.flag';
$hourlyDPH_skipFlag  = '/srv/www/htdocs/ProSpeaking/Reports/DPH2/skip_cron.flag';

// Only run when ?date=YYYY-MM-DD is present
if (isset($_GET['revert'])) {
    file_put_contents($hourlyDPH_skipFlag, "5000");    
    file_put_contents($originalDPH_skipFlag, "0");
    $message = "Successfully reverted to Original DPH Report. Please wait for 5 - 10 minutes for it to update.<br /><br />*Remember: The Original DPH uses a different link (located at the top of this page)";
}
?>
<div align="center"><strong>Revert to Original DPH Report</strong><br /><br />

<button onclick="revertToOriginal()">Revert</button></div>

<?php if (!empty($message)): ?>
  <div class="message" style="color:green;margin-top:1em;">
    <?php echo $message; ?>
  </div>
<?php elseif (!empty($error)): ?>
  <div class="error" style="color:red;margin-top:1em;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>
