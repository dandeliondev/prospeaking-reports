<?php
/**
 * Verifier date range for VFR / Celerity reports.
 * Copy to VICI/vfr_include.php (gitignored) or keep on server at /srv/www/vfr_include.php.
 */
$PSD = $_GET['PSD'] ?? $_POST['PSD'] ?? date('Y-m-d', strtotime('-6 days'));
$PED = $_GET['PED'] ?? $_POST['PED'] ?? date('Y-m-d');
