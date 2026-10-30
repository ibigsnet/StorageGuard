<?php
/**
 * Shared bootstrap for the Storage Guard Settings tabs.
 * Absolute paths: Unraid evaluates .page files from its own directory.
 * Saved values always win; a missing key shows the machine default.
 */
$plugin = 'StorageGuard';
require_once '/usr/local/emhttp/plugins/StorageGuard/sg-lib.php';

sg_sync_pool_pages();
$cfg = sg_load_cfg();

function sg_resolve_style(array $cfg, string $key): string {
    return sg_style($cfg, $key);
}

/** <option> list for a disk-size select. A saved value not in the list is kept and shown. */
function sg_size_options(array $sizes, $current, bool $include_none = true): string {
    $current = (string)$current;
    $html = $include_none ? '<option value=""' . ($current === '' ? ' selected' : '') . '>None</option>' : '';
    $found = ($current === '');
    foreach ($sizes as $s) {
        $sel = ($s === $current) ? ' selected' : '';
        $found = $found || $sel !== '';
        $html .= '<option value="' . htmlspecialchars($s) . '"' . $sel . '>' . htmlspecialchars($s) . '</option>';
    }
    if (!$found) {
        $html .= '<option value="' . htmlspecialchars($current) . '" selected>' . htmlspecialchars($current) . '</option>';
    }
    return $html;
}

function sg_csrf_field(): string {
    $t = (string)($GLOBALS['var']['csrf_token'] ?? (sg_var()['csrf_token'] ?? ''));
    return $t === '' ? '' : '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES) . '">' . "\n";
}

/** Page CSS and the form script, once per page even though every tab includes this file. */
function sg_page_assets(): void {
    if (!empty($GLOBALS['sg_page_assets_done'])) {
        return;
    }
    $GLOBALS['sg_page_assets_done'] = true;
    echo <<<'CSS'
<style>
.tabs { justify-content: center !important; }
.tabs .tabs-container { justify-content: center !important; width: auto !important; max-width: 100%; margin: 0 auto; }
.sg-wrap { max-width: 64em; margin-left: auto; margin-right: auto; }
.sg-wrap .sg-section-lead { margin: 0 0 0.85em; font-size: 0.92em; opacity: 0.85; line-height: 1.4; }
</style>
CSS;
    echo '<script src="' . autov('/plugins/StorageGuard/storageguard.js', true) . '"></script>' . "\n";
}

$array_raw_sizes = sg_array_data_disk_size_kbs();
$array_sizes = sg_size_labels_desc($array_raw_sizes);
$has_array_disks = (bool)$array_raw_sizes;

$pool_names = sg_list_pool_names();
$pools = [];
$pools_raw = [];
foreach ($pool_names as $pname) {
    $pools_raw[$pname] = sg_pool_member_size_kbs($pname);
    $pools[$pname] = sg_size_labels_desc($pools_raw[$pname]);
}

$dev_mode = !is_file(sg_path('disks'));
if ($dev_mode) {
    // Preview without a live disks.ini (development only).
    $array_sizes = ['26T', '12T', '8T', '4T', '2T'];
    $pools = ['cache' => ['2T']];
    $pool_names = ['cache'];
    $pools_raw = ['cache' => [1953125000, 1953125000]];
}

$array_defaults = [
    'warning' => $array_sizes ? $array_sizes[0] : '',
    'critical' => $array_sizes ? $array_sizes[count($array_sizes) - 1] : '',
];
$pool_defaults = [];
foreach ($pools as $pname => $sizes) {
    $pool_defaults[$pname] = [
        'warning' => $sizes ? $sizes[0] : '',
        'critical' => $sizes ? $sizes[count($sizes) - 1] : '',
    ];
}

$array_use_custom = $cfg['array_use_custom'] ?? 'no';
$array_warning_custom = $cfg['array_warning_custom'] ?? '';
$array_critical_custom = $cfg['array_critical_custom'] ?? '';
$array_warning = array_key_exists('array_warning', $cfg)
    ? sg_migrate_disk_size_label($cfg['array_warning'], $array_raw_sizes) : $array_defaults['warning'];
$array_critical = array_key_exists('array_critical', $cfg)
    ? sg_migrate_disk_size_label($cfg['array_critical'], $array_raw_sizes) : $array_defaults['critical'];

$array_coloring = (($cfg['array_coloring'] ?? ($has_array_disks ? 'yes' : 'no')) === 'yes') ? 'yes' : 'no';
$pool_coloring = (($cfg['pool_coloring'] ?? ($cfg['cache_coloring'] ?? 'yes')) === 'yes') ? 'yes' : 'no';
$array_color_style = sg_resolve_style($cfg, 'array_color_style');
$color_style = sg_resolve_style($cfg, 'color_style');
$sg_styles_agree = [$array_color_style];
foreach ($pool_names as $pn) {
    $sg_styles_agree[] = sg_resolve_style($cfg, 'pool_' . sg_pool_page_safe($pn) . '_color_style');
}
$sg_styles_agree = array_values(array_unique($sg_styles_agree));
if (count($sg_styles_agree) === 1) {
    $color_style = $sg_styles_agree[0];
}
$outline_pulse = (($cfg['outline_pulse'] ?? 'no') === 'yes') ? 'yes' : 'no';
$outline_show_ok = (($cfg['outline_show_ok'] ?? 'yes') === 'yes') ? 'yes' : 'no';
$pools_to_color = $cfg['pools_to_color'] ?? 'all';

$alerts_array_warning = $cfg['alerts_array_warning'] ?? ($has_array_disks ? 'yes' : 'no');
$alerts_array_critical = $cfg['alerts_array_critical'] ?? ($has_array_disks ? 'yes' : 'no');

$pool_alert_cfg = [];
foreach ($pool_names as $p) {
    $safe = sg_pool_page_safe($p);
    $pool_alert_cfg[$p] = [
        'safe' => $safe,
        'warning' => $cfg["alerts_pool_{$safe}_warning"] ?? 'yes',
        'critical' => $cfg["alerts_pool_{$safe}_critical"] ?? 'yes',
    ];
}
