<?php
/**
 * Shared bootstrap for Storage Guard Settings tabs.
 * Absolute paths — Unraid .page eval is not this file's directory.
 */
$plugin = 'StorageGuard';
if (is_file('/usr/local/emhttp/plugins/StorageGuard/sg-lib.php')) {
    require_once '/usr/local/emhttp/plugins/StorageGuard/sg-lib.php';
}
if (is_file('/usr/local/emhttp/plugins/StorageGuard/sg-pool-math.php')) {
    require_once '/usr/local/emhttp/plugins/StorageGuard/sg-pool-math.php';
}

if (function_exists('sg_sync_pool_pages')) {
    sg_sync_pool_pages();
}

$cfg = function_exists('parse_plugin_cfg') ? parse_plugin_cfg($plugin) : [];
if (!is_array($cfg)) {
    $cfg = [];
}

if (!function_exists('sg_format_size')) {
    function sg_format_size($kb) {
        if (function_exists('sg_format_size_kb')) {
            return sg_format_size_kb($kb);
        }
        return (string)$kb;
    }
}

if (!function_exists('sg_sort_sizes_desc')) {
    function sg_sort_sizes_desc($sizes) {
        $sizes = array_values(array_unique($sizes));
        usort($sizes, function ($a, $b) {
            $va = (float)preg_replace('/[^0-9.]/', '', $a);
            $ua = (strpos($a, 'T') !== false) ? 1000 : ((strpos($a, 'G') !== false) ? 1 : 0.001);
            $vb = (float)preg_replace('/[^0-9.]/', '', $b);
            $ub = (strpos($b, 'T') !== false) ? 1000 : ((strpos($b, 'G') !== false) ? 1 : 0.001);
            return ($vb * $ub) <=> ($va * $ua);
        });
        return $sizes;
    }
}

if (!function_exists('sg_resolve_style')) {
    function sg_resolve_style($cfg, $key) {
        $legacy = $cfg['color_style'] ?? 'outline';
        $s = $cfg[$key] ?? $legacy;
        return ($s === 'solid') ? 'solid' : 'outline';
    }
}

if (!function_exists('sg_size_options')) {
    function sg_size_options($sizes, $current, $include_none = true) {
        $html = '';
        if ($include_none) {
            $sel = ($current === '' || $current === null) ? ' selected' : '';
            $html .= '<option value=""' . $sel . '>None</option>';
        }
        $found = ($current === '' || $current === null);
        foreach ($sizes as $s) {
            $sel = ($s === $current) ? ' selected' : '';
            if ($sel) {
                $found = true;
            }
            $html .= '<option value="' . htmlspecialchars($s) . '"' . $sel . '>' . htmlspecialchars($s) . '</option>';
        }
        if (!$found && $current !== '' && $current !== null) {
            $html .= '<option value="' . htmlspecialchars($current) . '" selected>' . htmlspecialchars($current) . '</option>';
        }
        return $html;
    }
}

if (!function_exists('sg_csrf_token')) {
    function sg_csrf_token() {
        if (!empty($GLOBALS['var']['csrf_token'])) {
            return (string)$GLOBALS['var']['csrf_token'];
        }
        if (is_readable('/var/local/emhttp/var.ini')) {
            $vi = @parse_ini_file('/var/local/emhttp/var.ini');
            if (is_array($vi) && !empty($vi['csrf_token'])) {
                return (string)$vi['csrf_token'];
            }
        }
        return '';
    }
}

if (!function_exists('sg_csrf_field')) {
    function sg_csrf_field() {
        $t = sg_csrf_token();
        if ($t === '') {
            return '';
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES) . '">' . "\n";
    }
}

if (!function_exists('sg_page_styles')) {
    function sg_page_styles() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        echo <<<'CSS'
<style>
.tabs { justify-content: center !important; }
.tabs .tabs-container {
  justify-content: center !important;
  width: auto !important;
  max-width: 100%;
  margin: 0 auto;
}
.sg-wrap { max-width: 64em; margin-left: auto; margin-right: auto; }
.sg-wrap .sg-section-lead {
  margin: 0 0 0.85em; font-size: 0.92em; opacity: 0.85; line-height: 1.4;
}
</style>
CSS;
    }
}

$array_raw_sizes = function_exists('sg_array_data_disk_size_kbs') ? sg_array_data_disk_size_kbs() : [];
$array_sizes = [];
foreach ($array_raw_sizes as $kb) {
    $array_sizes[] = sg_format_size($kb);
}
$array_sizes = sg_sort_sizes_desc($array_sizes);

$pool_names = function_exists('sg_list_pool_names') ? sg_list_pool_names() : [];
$pools = [];
$pools_raw = [];
foreach ($pool_names as $pname) {
    $raws = function_exists('sg_pool_member_size_kbs') ? sg_pool_member_size_kbs($pname) : [];
    $pools_raw[$pname] = $raws;
    $labels = [];
    foreach ($raws as $kb) {
        $labels[] = sg_format_size($kb);
    }
    $pools[$pname] = sg_sort_sizes_desc($labels);
}

$disks_ini = '/var/local/emhttp/disks.ini';
$has_disks = is_file($disks_ini);
$dev_mode = false;
if (!$has_disks) {
    $array_sizes = ['26T', '12T', '8T', '4T', '2T'];
    $pools = ['cache' => ['2T', '2T']];
    $pool_names = ['cache'];
    $pools_raw = ['cache' => [2000000000, 2000000000]];
    $dev_mode = true;
}

$array_defaults = ['warning' => '', 'critical' => ''];
if (!empty($array_raw_sizes)) {
    $sorted = $array_raw_sizes;
    rsort($sorted, SORT_NUMERIC);
    $array_defaults = [
        'warning' => sg_format_size($sorted[0]),
        'critical' => sg_format_size($sorted[count($sorted) - 1]),
    ];
} elseif (!$has_disks && !empty($array_sizes)) {
    $array_defaults = [
        'warning' => $array_sizes[0],
        'critical' => $array_sizes[count($array_sizes) - 1],
    ];
}

$pool_defaults = [];
foreach ($pools as $pname => $sizes) {
    $pool_defaults[$pname] = !empty($sizes)
        ? ['warning' => $sizes[0], 'critical' => $sizes[count($sizes) - 1]]
        : ['warning' => '', 'critical' => ''];
}

$array_use_custom = $cfg['array_use_custom'] ?? 'no';
$array_warning_custom = $cfg['array_warning_custom'] ?? '';
$array_critical_custom = $cfg['array_critical_custom'] ?? '';
$sg_defaults_ok = (($cfg['sg_defaults'] ?? '') === '1');
$has_array_disks = !empty($array_raw_sizes);

if ($sg_defaults_ok && array_key_exists('array_warning', $cfg)) {
    $array_warning = $cfg['array_warning'];
    if ($array_use_custom !== 'yes' && $array_warning !== '' && function_exists('sg_migrate_disk_size_label')) {
        $array_warning = sg_migrate_disk_size_label($array_warning, $array_raw_sizes);
    }
    if ($array_use_custom !== 'yes' && $array_warning !== '' && !empty($array_sizes) && !in_array($array_warning, $array_sizes, true)) {
        $array_warning = $array_defaults['warning'] ?? '';
    }
} else {
    $array_warning = $has_array_disks ? ($array_defaults['warning'] ?? '') : '';
}

if ($sg_defaults_ok && array_key_exists('array_critical', $cfg)) {
    $array_critical = $cfg['array_critical'];
    if ($array_use_custom !== 'yes' && $array_critical !== '' && function_exists('sg_migrate_disk_size_label')) {
        $array_critical = sg_migrate_disk_size_label($array_critical, $array_raw_sizes);
    }
    if ($array_use_custom !== 'yes' && $array_critical !== '' && !empty($array_sizes) && !in_array($array_critical, $array_sizes, true)) {
        $array_critical = $array_defaults['critical'] ?? '';
    }
} else {
    $array_critical = $has_array_disks ? ($array_defaults['critical'] ?? '') : '';
}

if ($sg_defaults_ok && isset($cfg['array_coloring'])) {
    $array_coloring = (($cfg['array_coloring'] ?? '') === 'yes') ? 'yes' : 'no';
} else {
    $array_coloring = $has_array_disks ? 'yes' : 'no';
}
$pool_coloring = $cfg['pool_coloring'] ?? $cfg['cache_coloring'] ?? 'yes';
if ($pool_coloring !== 'yes') {
    $pool_coloring = 'no';
}
$array_color_style = sg_resolve_style($cfg, 'array_color_style');
$outline_pulse = (($cfg['outline_pulse'] ?? 'no') === 'yes') ? 'yes' : 'no';
$outline_show_ok = (($cfg['outline_show_ok'] ?? 'yes') === 'yes') ? 'yes' : 'no';
$pools_to_color = $cfg['pools_to_color'] ?? 'all';

if ($sg_defaults_ok && isset($cfg['alerts_array_warning'])) {
    $alerts_array_warning = $cfg['alerts_array_warning'];
} else {
    $alerts_array_warning = $has_array_disks ? 'yes' : 'no';
}
if ($sg_defaults_ok && isset($cfg['alerts_array_critical'])) {
    $alerts_array_critical = $cfg['alerts_array_critical'];
} else {
    $alerts_array_critical = $has_array_disks ? 'yes' : 'no';
}
if (!$has_array_disks && !$sg_defaults_ok) {
    $alerts_array_warning = 'no';
    $alerts_array_critical = 'no';
}

$pool_alert_cfg = [];
foreach ($pool_names as $p) {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $p);
    $wk = "alerts_pool_{$safe}_warning";
    $ck = "alerts_pool_{$safe}_critical";
    $pw = ($sg_defaults_ok && isset($cfg[$wk])) ? $cfg[$wk] : 'yes';
    $pc = ($sg_defaults_ok && isset($cfg[$ck])) ? $cfg[$ck] : 'yes';
    $pool_alert_cfg[$p] = [
        'safe' => $safe,
        'warning' => $pw,
        'critical' => $pc,
    ];
}
