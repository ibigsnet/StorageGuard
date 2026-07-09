<?php
/**
 * Storage Guard config + live free-space status for Main-tab coloring.
 * Returns JSON: { ...cfg fields..., _status: { array: {...}, pools: { name: {...} } } }
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$cfgFile = '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
$cfg = [];
if (file_exists($cfgFile)) {
  $cfg = @parse_ini_file($cfgFile, false, INI_SCANNER_RAW) ?: [];
}

function sg_parse_to_tb($str) {
  if ($str === null || $str === '') return 0.0;
  if (!preg_match('/([0-9]*\.?[0-9]+)\s*([TGMKtgmk]?)/', (string)$str, $m)) return 0.0;
  $num = (float)$m[1];
  $u = strtoupper($m[2] ?: 'T');
  if ($u === 'T') return $num;
  if ($u === 'G') return $num / 1024.0;
  if ($u === 'M') return $num / 1024.0 / 1024.0;
  if ($u === 'K') return $num / 1024.0 / 1024.0 / 1024.0;
  return $num;
}

function sg_free_tb_mount($mount) {
  if (!is_dir($mount)) return null;
  // Prefer df -B1 for precision
  $out = @shell_exec("df -B1 --output=avail " . escapeshellarg($mount) . " 2>/dev/null | tail -1");
  $bytes = (float)trim((string)$out);
  if ($bytes <= 0) return 0.0;
  return $bytes / (1024.0 ** 4); // TiB-ish using binary; close enough for thresholds in T
}

function sg_level($free_tb, $warn_tb, $crit_tb) {
  if ($free_tb === null) return 'ok';
  // Critical is the lower/more severe threshold when both set
  if ($crit_tb > 0 && $free_tb <= $crit_tb) return 'critical';
  if ($warn_tb > 0 && $free_tb <= $warn_tb) return 'warning';
  return 'ok';
}

// Array free: user0 = array only when present, else user
$array_free = sg_free_tb_mount('/mnt/user0');
if ($array_free === null) $array_free = sg_free_tb_mount('/mnt/user');

$use_custom = ($cfg['array_use_custom'] ?? 'no') === 'yes';
if ($use_custom) {
  $arr_warn = sg_parse_to_tb($cfg['array_warning_custom'] ?? '');
  $arr_crit = sg_parse_to_tb($cfg['array_critical_custom'] ?? '');
} else {
  $arr_warn = sg_parse_to_tb($cfg['array_warning'] ?? '');
  $arr_crit = sg_parse_to_tb($cfg['array_critical'] ?? '');
}

$array_coloring = ($cfg['array_coloring'] ?? 'yes') === 'yes';
$pool_coloring = ($cfg['pool_coloring'] ?? 'yes') === 'yes';
$pools_to_color = $cfg['pools_to_color'] ?? 'all';

$status = [
  'array' => [
    'enabled' => $array_coloring,
    'free_tb' => $array_free !== null ? round($array_free, 3) : null,
    'warn_tb' => round($arr_warn, 3),
    'crit_tb' => round($arr_crit, 3),
    'level'   => $array_coloring ? sg_level($array_free, $arr_warn, $arr_crit) : 'ok',
  ],
  'pools' => new stdClass(), // object for JSON {}
];

$pool_status = [];
// Discover pools from threshold keys and/or disks.ini Cache types
$pool_names = [];
foreach ($cfg as $k => $v) {
  if (preg_match('/^pool_([a-zA-Z0-9_]+)_warning$/', $k, $m)) {
    $pool_names[$m[1]] = true;
  }
}
$disks_ini = '/var/local/emhttp/disks.ini';
if (file_exists($disks_ini)) {
  $disks = @parse_ini_file($disks_ini, true) ?: [];
  foreach ($disks as $key => $d) {
    if (($d['type'] ?? '') !== 'Cache') continue;
    if (empty($d['device'])) continue;
    $pname = preg_replace('/\d+$/', '', $key);
    if ($pname !== '' && $pname !== 'flash') $pool_names[$pname] = true;
  }
}

foreach (array_keys($pool_names) as $pname) {
  $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $pname);
  // pools_to_color filter
  $include = ($pools_to_color === 'all' || $pools_to_color === '');
  if (!$include) {
    $list = array_map('trim', explode(',', $pools_to_color));
    $include = in_array($pname, $list, true) || in_array($safe, $list, true);
  }
  $enabled = $pool_coloring && $include;
  $warn = sg_parse_to_tb($cfg["pool_{$safe}_warning"] ?? $cfg["pool_{$pname}_warning"] ?? '');
  $crit = sg_parse_to_tb($cfg["pool_{$safe}_critical"] ?? $cfg["pool_{$pname}_critical"] ?? '');
  $free = sg_free_tb_mount('/mnt/' . $pname);
  $pool_status[$pname] = [
    'enabled' => $enabled,
    'free_tb' => $free !== null ? round($free, 3) : null,
    'warn_tb' => round($warn, 3),
    'crit_tb' => round($crit, 3),
    'level'   => $enabled ? sg_level($free, $warn, $crit) : 'ok',
  ];
}
$status['pools'] = $pool_status ?: new stdClass();

$out = $cfg;
$out['_status'] = $status;
echo json_encode($out);
