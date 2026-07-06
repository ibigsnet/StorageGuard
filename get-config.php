<?php
header('Content-Type: application/json');
$cfgFile = '/boot/config/plugins/storageguard/storageguard.cfg';
$cfg = [];
if (file_exists($cfgFile)) {
  $cfg = parse_ini_file($cfgFile, false, INI_SCANNER_RAW);
}
echo json_encode($cfg);