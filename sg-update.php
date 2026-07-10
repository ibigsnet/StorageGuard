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

if (!function_exists('sg_update_format_size')) {
    function sg_update_format_size($kb) {
        if (!$kb || $kb <= 0) return '0';
        $bytes = $kb * 1024;
        if ($bytes >= 1024 * 1024 * 1024 * 1024) {
            $val = round($bytes / (1024 * 1024 * 1024 * 1024), 1);
            return rtrim(rtrim((string)$val, '0'), '.') . 'T';
        }
        if ($bytes >= 1024 * 1024 * 1024) {
            $val = round($bytes / (1024 * 1024 * 1024), 1);
            return rtrim(rtrim((string)$val, '0'), '.') . 'G';
        }
        if ($bytes >= 1024 * 1024) {
            return (string)round($bytes / (1024 * 1024)) . 'M';
        }
        return (string)round($bytes / 1024) . 'K';
    }
}

$largest_warn = '';
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
        $sz = isset($d['size']) ? (int)$d['size'] : 0;
        if ($sz > 0) $raw[] = $sz;
    }
    if (!empty($raw)) {
        rsort($raw, SORT_NUMERIC);
        $largest_warn = sg_update_format_size($raw[0]);
    }
}

if (!is_array($default)) {
    $default = [];
}

$default['array_warning'] = $largest_warn;
$default['array_critical'] = '';
$default['array_use_custom'] = 'no';
$default['array_warning_custom'] = '';
$default['array_critical_custom'] = '';
$default['array_color_style'] = 'outline';
$default['array_coloring'] = 'yes';
$default['outline_pulse'] = 'no';
$default['outline_show_ok'] = 'no';
$default['pool_coloring'] = 'no';
$default['pools_to_color'] = 'all';
$default['alerts_array_warning'] = $largest_warn !== '' ? 'yes' : 'no';
$default['alerts_array_critical'] = 'no';
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
