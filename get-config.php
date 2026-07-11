<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/sg-lib.php';
require_once __DIR__ . '/sg-pool-math.php';

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
  if ($u === 'G') return $num / 1000.0;
  if ($u === 'M') return $num / 1000.0 / 1000.0;
  if ($u === 'K') return $num / 1000.0 / 1000.0 / 1000.0;
  return $num;
}

function sg_free_tb_mount($mount) {
  if (!is_dir($mount)) return null;
  $out = @shell_exec("df -B1 --output=avail " . escapeshellarg($mount) . " 2>/dev/null | tail -1");
  $bytes = (float)trim((string)$out);
  if ($bytes < 0) return null;
  return $bytes / 1000.0 / 1000.0 / 1000.0 / 1000.0;
}

function sg_level($free_tb, $warn_tb, $crit_tb) {
  if ($free_tb === null) return 'ok';
  $w = ($warn_tb > 0) ? (float)$warn_tb : null;
  $c = ($crit_tb > 0) ? (float)$crit_tb : null;
  if ($w === null && $c === null) return 'ok';
  if ($w === null) return ($free_tb <= $c) ? 'critical' : 'ok';
  if ($c === null) return ($free_tb <= $w) ? 'warning' : 'ok';
  $severe = min($w, $c);
  $mild   = max($w, $c);
  if ($free_tb <= $severe) return 'critical';
  if ($free_tb <= $mild) return 'warning';
  return 'ok';
}

function sg_style($cfg, $key) {
  $legacy = $cfg['color_style'] ?? 'outline';
  $s = $cfg[$key] ?? $legacy;
  return ($s === 'solid') ? 'solid' : 'outline';
}

function sg_largest_data_disk_tb() {
  $disks_ini = '/var/local/emhttp/disks.ini';
  if (!file_exists($disks_ini)) return 0.0;
  $disks = @parse_ini_file($disks_ini, true) ?: [];
  $max_kb = 0;
  foreach ($disks as $key => $d) {
    if (empty($d['device'])) continue;
    $type = $d['type'] ?? '';
    $name = $d['name'] ?? $key;
    $is_data = ($type === 'Data') || preg_match('/^disk\d+$/', $name) || preg_match('/^disk\d+$/', $key);
    if (!$is_data) continue;
    $sz = isset($d['size']) ? (int)$d['size'] : 0;
    if ($sz > $max_kb) $max_kb = $sz;
  }
  if ($max_kb <= 0) return 0.0;
  return ($max_kb * 1024.0) / 1e12;
}

$array_present = function_exists('sg_array_present') ? sg_array_present() : (sg_largest_data_disk_tb() > 0);

// Prefer array-only mount; do not treat pools-only /mnt/user as the array
$array_free = null;
if ($array_present) {
  $array_free = sg_free_tb_mount('/mnt/user0');
  if ($array_free === null || $array_free <= 0) {
    $array_free = sg_free_tb_mount('/mnt/user');
  }
}

$sg_defaults_ok = (($cfg['sg_defaults'] ?? '') === '1');
$use_custom = ($cfg['array_use_custom'] ?? 'no') === 'yes';
if ($use_custom) {
  $arr_warn = sg_parse_to_tb($cfg['array_warning_custom'] ?? '');
  $arr_crit = sg_parse_to_tb($cfg['array_critical_custom'] ?? '');
} else {
  if ($sg_defaults_ok && array_key_exists('array_warning', $cfg)) {
    $arr_warn = sg_parse_to_tb($cfg['array_warning']);
  } else {
    $arr_warn = $array_present ? sg_largest_data_disk_tb() : 0.0;
  }
  $arr_crit = sg_parse_to_tb($cfg['array_critical'] ?? '');
}

$array_coloring = ($cfg['array_coloring'] ?? 'yes') === 'yes';
$pool_coloring = ($cfg['pool_coloring'] ?? 'no') === 'yes';
$pools_to_color = $cfg['pools_to_color'] ?? 'all';
$array_style = sg_style($cfg, 'array_color_style');

// No array: keep cfg values in payload but do not paint / treat as active
$array_enabled = $array_present && $array_coloring;
$status = [
  'array' => [
    'present' => $array_present,
    'enabled' => $array_enabled,
    'free_tb' => ($array_present && $array_free !== null) ? round($array_free, 3) : null,
    'warn_tb' => round($arr_warn, 3),
    'crit_tb' => round($arr_crit, 3),
    'level'   => $array_enabled ? sg_level($array_free, $arr_warn, $arr_crit) : 'ok',
    'style'   => $array_style,
  ],
  'pools' => new stdClass(),
];

$pool_names = [];
foreach ($cfg as $k => $v) {
  if (preg_match('/^pool_([a-zA-Z0-9_]+)_warning$/', $k, $m)) {
    $pool_names[$m[1]] = true;
  }
  if (preg_match('/^pool_([a-zA-Z0-9_]+)_warning_custom$/', $k, $m)) {
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

$pool_status = [];
foreach (array_keys($pool_names) as $pname) {
  $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $pname);
  $include = ($pools_to_color === 'all' || $pools_to_color === '');
  if (!$include) {
    $list = array_map('trim', explode(',', $pools_to_color));
    $include = in_array($pname, $list, true) || in_array($safe, $list, true);
  }
  $enabled = $pool_coloring && $include;
  $p_custom = ($cfg["pool_{$safe}_use_custom"] ?? 'no') === 'yes';
  if ($p_custom) {
    $warn = sg_parse_to_tb($cfg["pool_{$safe}_warning_custom"] ?? '');
    $crit = sg_parse_to_tb($cfg["pool_{$safe}_critical_custom"] ?? '');
  } else {
    $warn = sg_parse_to_tb($cfg["pool_{$safe}_warning"] ?? $cfg["pool_{$pname}_warning"] ?? '');
    $crit = sg_parse_to_tb($cfg["pool_{$safe}_critical"] ?? $cfg["pool_{$pname}_critical"] ?? '');
  }
  // RAID1/mirror: disk-size dropdown is evacuate-room semantics — do not apply
  $profile = sg_pool_btrfs_profile($pname);
  $p_class = sg_pool_profile_class($profile);
  if (!$p_custom && sg_pool_ignore_disk_size_thresholds($p_class)) {
    $warn = 0.0;
    $crit = 0.0;
  }
  $free = sg_free_tb_mount('/mnt/' . $pname);
  $math = function_exists('sg_pool_math_package') ? sg_pool_math_package($pname, $profile) : null;
  $pool_status[$pname] = [
    'enabled' => $enabled,
    'free_tb' => $free !== null ? round($free, 3) : null,
    'warn_tb' => round($warn, 3),
    'crit_tb' => round($crit, 3),
    'level'   => $enabled ? sg_level($free, $warn, $crit) : 'ok',
    'style'   => sg_style($cfg, "pool_{$safe}_color_style"),
    'profile' => $profile,
    'math'    => $math,
  ];
}
$status['pools'] = $pool_status ?: new stdClass();

$out = $cfg;
$out['_status'] = $status;
echo json_encode($out);
