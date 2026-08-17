<?php

if (is_array($keys)) {
    foreach (array_keys($keys) as $k) {
        if (!is_string($k)) continue;
        if ($k === 'pool_all' || preg_match('/^pool_color_/', $k)) {
            unset($keys[$k]);
            continue;
        }
        if ($k === 'color_style' || $k === 'cache_coloring') {
            unset($keys[$k]);
        }
    }
}

if (!isset($_POST['#default'])) {
    return;
}

// Prefer shared SI formatter (matches Unraid Main); fallback only if lib missing.
if (!function_exists('sg_format_size_kb')) {
    $sg_lib = '/usr/local/emhttp/plugins/StorageGuard/sg-lib.php';
    if (is_file($sg_lib)) {
        @require_once $sg_lib;
    }
}
if (!function_exists('sg_update_format_size')) {
    function sg_update_format_size($kb) {
        if (function_exists('sg_format_size_kb')) {
            return sg_format_size_kb($kb);
        }
        // Fallback: Main Size column rules (my_scale kilo=1000, decimals=-1)
        if (!$kb || $kb <= 0) return '0';
        $value = (float)$kb * 1024.0;
        $kilo = 1000.0;
        $units = ['', 'K', 'M', 'G', 'T', 'P'];
        $base = $value > 0 ? (int)floor(log($value, $kilo)) : 0;
        if ($base > 5) $base = 5;
        if ($base < 0) $base = 0;
        $value /= pow($kilo, $base);
        if ($value >= 100 || ((int)round($value * 10) % 10) === 0) $decimals = 0;
        else $decimals = 1;
        if (round($value, -1) == 1000 && $base < 5) { $value = 1; $base++; $decimals = 0; }
        $formatted = number_format($value, $decimals, '.', '');
        if (strpos($formatted, '.') !== false) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        return $units[$base] === '' ? $formatted : ($formatted . $units[$base]);
    }
}

$largest_warn = '';
$smallest_crit = '';
$disks_ini = '/var/local/emhttp/disks.ini';
if (is_file($disks_ini)) {
    $disks = @parse_ini_file($disks_ini, true) ?: [];
    $raw = [];
    foreach ($disks as $key => $d) {
        if (empty($d['device'])) continue;
        $type = $d['type'] ?? '';
        $name = $d['name'] ?? $key;
        $is_data = ($type === 'Data') || preg_match('/^disk\d+$/', $name) || preg_match('/^disk\d+$/', $key);
        if (!$is_data) continue;
        $sz = function_exists('sg_disk_capacity_kb') ? sg_disk_capacity_kb($d) : (isset($d['size']) ? (int)$d['size'] : 0);
        if ($sz > 0) $raw[] = $sz;
    }
    if (!empty($raw)) {
        rsort($raw, SORT_NUMERIC);
        $largest_warn = sg_update_format_size($raw[0]);
        $smallest_crit = sg_update_format_size($raw[count($raw) - 1]);
    }
}

if (!is_array($default)) {
    $default = [];
}

// Array product defaults depend on whether data disks exist (pools-only hosts hide Array UI).
// Warning = largest data disk (evacuate room); Critical = smallest data disk; both alerts on.
$has_array = ($largest_warn !== '');
$default['array_warning'] = $largest_warn;
$default['array_critical'] = $smallest_crit;
$default['array_use_custom'] = 'no';
$default['array_warning_custom'] = '';
$default['array_critical_custom'] = '';
$default['array_color_style'] = 'outline';
// Yes only when array data disks exist — no-array / cache-only → No (inactive paint)
$default['array_coloring'] = $has_array ? 'yes' : 'no';
$default['outline_pulse'] = 'yes';
$default['outline_show_ok'] = 'yes';
// Pool free-bar paint on by default (all pools); pool *alerts* stay off below
$default['pool_coloring'] = 'yes';
$default['pools_to_color'] = 'all';
$default['alerts_array_warning'] = $has_array ? 'yes' : 'no';
$default['alerts_array_critical'] = $has_array ? 'yes' : 'no';
$default['sg_defaults'] = '';

foreach ($_POST as $key => $value) {
    if (!is_string($key) || $key === '' || $key[0] === '#') continue;

    if (preg_match('/^alerts_pool_.+_(warning|critical)$/', $key)) {
        $default[$key] = 'no';
        continue;
    }

    if (preg_match('/^pool_.+_(warning|critical|warning_custom|critical_custom)$/', $key)) {
        $default[$key] = '';
        continue;
    }
    if (preg_match('/^pool_.+_use_custom$/', $key)) {
        $default[$key] = 'no';
        continue;
    }
    if (preg_match('/^pool_.+_color_style$/', $key)) {
        $default[$key] = 'outline';
    }
}

if (is_array($keys)) {
    foreach (array_keys($keys) as $k) {
        if (!is_string($k)) continue;
        if (preg_match('/^(pool_|alerts_pool_)/', $k) && !array_key_exists($k, $_POST)) {
            unset($keys[$k]);
        }
    }
}
