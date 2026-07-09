<?php
header('Content-Type: application/json');
// Match $plugin = "StorageGuard" / parse_plugin_cfg path
$cfgFile = '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
$cfg = [];
if (file_exists($cfgFile)) {
  $cfg = @parse_ini_file($cfgFile, false, INI_SCANNER_RAW) ?: [];
}
echo json_encode($cfg ?: new stdClass());
