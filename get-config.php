<?php
/**
 * Storage Guard config + live free-space status for Main-tab coloring.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$cfgFile = '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
$cfg = [];
if (file_exists($cfgFile)) {
  $cfg = @parse_ini_file($cfgFile, false, INI_SCANNER_RAW) ?: [];
}

/** Parse size strings (1.5T, 500G, 7.5T, …) to decimal TB for comparison with free space. */
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

/** Free space on mount, as decimal TB (aligned with Unraid Main free display). */
function sg_free_tb_mount($mount) {
  if (!is_dir($mount)) return null;
  $out = @shell_exec("df -B1 --output=avail " . escapeshellarg($mount) . " 2>/dev/null | tail -1");
  $bytes = (float)trim((string)$out);
  if ($bytes < 0) return null;
  return $bytes / 1000.0 / 1000.0 / 1000.0 / 1000.0;
}

/**
 * Level from free space vs warning/critical thresholds (any order).
 * Lower free-space threshold = more severe (critical); higher = warning.
 */
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

/** solid (default) | outline — per target; falls back to legacy color_style */
function sg_style($cfg, $key) {
  $legacy = $cfg['color_style'] ?? 'solid';
  $s = $cfg[$key] ?? $legacy;
  return ($s === 'outline') ? 'outline' : 'solid';
}

/** Largest array data disk size in TB (decimal), or 0 if none. */
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
    $sz = isset($d['size']) ? (int)$d['size'] : 0; // KB
    if ($sz > $max_kb) $max_kb = $sz;
  }
  if ($max_kb <= 0) return 0.0;
  // size in KB → bytes → decimal TB
  return ($max_kb * 1024.0) / 1e12;
}

/**
 * Array warning threshold string from cfg.
 * Missing key → default largest data disk. Present empty → None (user choice).
 */
function sg_array_warning_str($cfg) {
  if (($cfg['array_use_custom'] ?? 'no') === 'yes') {
    return $cfg['array_warning_custom'] ?? '';
  }
  if (array_key_exists('array_warning', $cfg)) {
    return $cfg['array_warning'];
  }
  // Unset: default to largest data disk label via TB parse of computed size
  $tb = sg_largest_data_disk_tb();
  if ($tb <= 0) return '';
  // Prefer one-decimal T labels like the Settings UI (e.g. 23.6T)
  $val = round($tb, 1);
  return rtrim(rtrim(sprintf('%.1f', $val), '0'), '.') . 'T';
}

$array_free = sg_free_tb_mount('/mnt/user0');
if ($array_free === null || $array_free <= 0) {
  $array_free = sg_free_tb_mount('/mnt/user');
}

$use_custom = ($cfg['array_use_custom'] ?? 'no') === 'yes';
if ($use_custom) {
  $arr_warn = sg_parse_to_tb($cfg['array_warning_custom'] ?? '');
  $arr_crit = sg_parse_to_tb($cfg['array_critical_custom'] ?? '');
} else {
  if (array_key_exists('array_warning', $cfg)) {
    $arr_warn = sg_parse_to_tb($cfg['array_warning']);
  } else {
    $arr_warn = sg_largest_data_disk_tb();
  }
  $arr_crit = sg_parse_to_tb($cfg['array_critical'] ?? '');
}

$array_coloring = ($cfg['array_coloring'] ?? 'yes') === 'yes';
$pool_coloring = ($cfg['pool_coloring'] ?? 'yes') === 'yes';
$pools_to_color = $cfg['pools_to_color'] ?? 'all';
$array_style = sg_style($cfg, 'array_color_style');

$status = [
  'array' => [
    'enabled' => $array_coloring,
    'free_tb' => $array_free !== null ? round($array_free, 3) : null,
    'warn_tb' => round($arr_warn, 3),
    'crit_tb' => round($arr_crit, 3),
    'level'   => $array_coloring ? sg_level($array_free, $arr_warn, $arr_crit) : 'ok',
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
  $free = sg_free_tb_mount('/mnt/' . $pname);
  $pool_status[$pname] = [
    'enabled' => $enabled,
    'free_tb' => $free !== null ? round($free, 3) : null,
    'warn_tb' => round($warn, 3),
    'crit_tb' => round($crit, 3),
    'level'   => $enabled ? sg_level($free, $warn, $crit) : 'ok',
    'style'   => sg_style($cfg, "pool_{$safe}_color_style"),
  ];
}
$status['pools'] = $pool_status ?: new stdClass();

$out = $cfg;
$out['_status'] = $status;
echo json_encode($out);
