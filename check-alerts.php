<?php
// Storage Guard — send Unraid notifications when free space hits a threshold.
// Per-target flags: alerts_array_*, alerts_pool_<name>_* (nothing set = silent).

header('Content-Type: application/json');

$cfg_file = '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
$cfg = [];
if (file_exists($cfg_file)) {
    $cfg = @parse_ini_file($cfg_file) ?: [];
}

// Legacy global master still respected if present and off
if (isset($cfg['alerts_enabled']) && $cfg['alerts_enabled'] !== 'yes'
    && !isset($cfg['alerts_array_warning']) && !isset($cfg['alerts_array_critical'])) {
    // Old install with master off and no new keys: stay silent
    $has_new = false;
    foreach ($cfg as $k => $v) {
        if (strpos($k, 'alerts_pool_') === 0 || strpos($k, 'alerts_array_') === 0) {
            $has_new = true;
            break;
        }
    }
    if (!$has_new) {
        echo json_encode(['sent' => false, 'reason' => 'alerts disabled (legacy)']);
        exit;
    }
}

$state_dir = '/tmp/storageguard_alerts';
@mkdir($state_dir, 0755, true);

function sg_get_array_free_tb() {
    foreach (['/mnt/user0', '/mnt/user'] as $mnt) {
        if (!is_dir($mnt)) continue;
        $out = @shell_exec("df -B1 --output=avail " . escapeshellarg($mnt) . " 2>/dev/null | tail -1");
        $bytes = (float)trim((string)$out);
        if ($bytes > 0 || $mnt === '/mnt/user') return $bytes / 1e12; // decimal TB
    }
    return 0.0;
}

function sg_get_pool_free_tb($pool) {
    $mount = "/mnt/" . $pool;
    if (!is_dir($mount)) return 0.0;
    $out = @shell_exec("df -B1 --output=avail " . escapeshellarg($mount) . " 2>/dev/null | tail -1");
    $bytes = (float)trim((string)$out);
    return $bytes / 1e12;
}

function sg_parse_to_tb($str) {
    if (!$str) return 0.0;
    if (!preg_match('/([0-9]*\.?[0-9]+)\s*([TGMKtgmk]?)/', $str, $m)) return 0.0;
    $num = (float)$m[1];
    $u = strtoupper($m[2] ?: 'T');
    if ($u === 'T') return $num;
    if ($u === 'G') return $num / 1000.0;
    if ($u === 'M') return $num / 1e6;
    if ($u === 'K') return $num / 1e9;
    return $num;
}

/** Same free-space level logic as get-config.php */
function sg_level($free_tb, $warn_tb, $crit_tb) {
    if ($free_tb === null) return 'ok';
    $w = ($warn_tb > 0) ? (float)$warn_tb : null;
    $c = ($crit_tb > 0) ? (float)$crit_tb : null;
    if ($w === null && $c === null) return 'ok';
    if ($w === null) return ($free_tb <= $c) ? 'critical' : 'ok';
    if ($c === null) return ($free_tb <= $w) ? 'warning' : 'ok';
    $severe = min($w, $c);
    $mild = max($w, $c);
    if ($free_tb <= $severe) return 'critical';
    if ($free_tb <= $mild) return 'warning';
    return 'ok';
}

function sg_send_notify($subject, $desc, $priority = 'warning') {
    $cmd = "/usr/local/emhttp/webGui/scripts/notify -e 'Storage Guard' -s " . escapeshellarg($subject)
        . " -d " . escapeshellarg($desc) . " -i " . escapeshellarg($priority) . " -l '/Main'";
    @shell_exec($cmd);
}

function sg_should_send($key, $min_interval = 3600) {
    global $state_dir;
    $file = $state_dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    $last = @filemtime($file);
    if ($last && (time() - $last) < $min_interval) return false;
    @touch($file);
    return true;
}

function sg_flag($cfg, $key) {
    return ($cfg[$key] ?? 'no') === 'yes';
}

function sg_array_thresholds($cfg) {
    $use_custom = ($cfg['array_use_custom'] ?? 'no') === 'yes';
    if ($use_custom) {
        return [
            'warn' => sg_parse_to_tb($cfg['array_warning_custom'] ?? ''),
            'crit' => sg_parse_to_tb($cfg['array_critical_custom'] ?? ''),
            'warn_label' => $cfg['array_warning_custom'] ?? '',
            'crit_label' => $cfg['array_critical_custom'] ?? '',
            'custom' => true,
        ];
    }
    return [
        'warn' => sg_parse_to_tb($cfg['array_warning'] ?? ''),
        'crit' => sg_parse_to_tb($cfg['array_critical'] ?? ''),
        'warn_label' => $cfg['array_warning'] ?? '',
        'crit_label' => $cfg['array_critical'] ?? '',
        'custom' => false,
    ];
}

function sg_pool_thresholds($cfg, $safe) {
    $use_custom = ($cfg["pool_{$safe}_use_custom"] ?? 'no') === 'yes';
    if ($use_custom) {
        return [
            'warn' => sg_parse_to_tb($cfg["pool_{$safe}_warning_custom"] ?? ''),
            'crit' => sg_parse_to_tb($cfg["pool_{$safe}_critical_custom"] ?? ''),
            'warn_label' => $cfg["pool_{$safe}_warning_custom"] ?? '',
            'crit_label' => $cfg["pool_{$safe}_critical_custom"] ?? '',
            'custom' => true,
        ];
    }
    return [
        'warn' => sg_parse_to_tb($cfg["pool_{$safe}_warning"] ?? ''),
        'crit' => sg_parse_to_tb($cfg["pool_{$safe}_critical"] ?? ''),
        'warn_label' => $cfg["pool_{$safe}_warning"] ?? '',
        'crit_label' => $cfg["pool_{$safe}_critical"] ?? '',
        'custom' => false,
    ];
}

$sent = [];
$array_free = sg_get_array_free_tb();

// --- Array ---
$arr_warn_on = sg_flag($cfg, 'alerts_array_warning');
$arr_crit_on = sg_flag($cfg, 'alerts_array_critical');
// Legacy fallback if new keys absent
if (!isset($cfg['alerts_array_warning']) && !isset($cfg['alerts_array_critical'])) {
    $legacy_on = ($cfg['alerts_enabled'] ?? 'yes') === 'yes';
    $legacy_for = $cfg['alerts_for'] ?? 'all';
    $array_selected = ($legacy_for === 'all') || in_array('array', array_map('trim', explode(',', $legacy_for)), true);
    $arr_warn_on = $legacy_on && $array_selected && (($cfg['warning_alerts_enabled'] ?? 'yes') === 'yes');
    $arr_crit_on = $legacy_on && $array_selected && (($cfg['critical_alerts_enabled'] ?? 'yes') === 'yes');
}

if ($arr_warn_on || $arr_crit_on) {
    $th = sg_array_thresholds($cfg);
    $level = sg_level($array_free, $th['warn'], $th['crit']);
    // Respect which severities the user enabled for this target
    if ($level === 'critical' && !$arr_crit_on) $level = $arr_warn_on ? 'warning' : 'ok';
    if ($level === 'warning' && !$arr_warn_on) $level = 'ok';

    if ($level === 'critical' && sg_should_send('array_critical')) {
        $msg = $th['custom']
            ? "Custom critical threshold {$th['crit_label']}. Array free space is " . round($array_free, 1) . " TB (below critical)."
            : "Array free space is " . round($array_free, 1) . " TB, at or below critical threshold {$th['crit_label']}. You may not have room to move data off a failed disk without replacement.";
        sg_send_notify('Array Critical Low Space', $msg, 'alert');
        $sent[] = 'array_critical';
    } elseif ($level === 'warning' && sg_should_send('array_warning')) {
        $msg = $th['custom']
            ? "Custom warning threshold {$th['warn_label']}. Array free space is " . round($array_free, 1) . " TB."
            : "Array free space is " . round($array_free, 1) . " TB, at or below warning threshold {$th['warn_label']}.";
        sg_send_notify('Array Warning Low Space', $msg, 'warning');
        $sent[] = 'array_warning';
    }
}

// --- Pools (from threshold keys + per-pool alert flags) ---
$seen_pools = [];
foreach ($cfg as $k => $v) {
    if (!preg_match('/^pool_([a-zA-Z0-9_]+)_(warning|warning_custom)$/', $k, $m)) continue;
    $safe = $m[1];
    if (isset($seen_pools[$safe])) continue;
    $seen_pools[$safe] = true;
    $pname = $safe;

    $warn_key = "alerts_pool_{$safe}_warning";
    $crit_key = "alerts_pool_{$safe}_critical";
    $p_warn_on = sg_flag($cfg, $warn_key);
    $p_crit_on = sg_flag($cfg, $crit_key);

    // Legacy fallback
    if (!isset($cfg[$warn_key]) && !isset($cfg[$crit_key])) {
        $legacy_on = ($cfg['alerts_enabled'] ?? 'yes') === 'yes';
        $legacy_for = $cfg['alerts_for'] ?? 'all';
        $selected = ($legacy_for === 'all') || in_array($pname, array_map('trim', explode(',', $legacy_for)), true);
        $p_warn_on = $legacy_on && $selected && (($cfg['warning_alerts_enabled'] ?? 'yes') === 'yes');
        $p_crit_on = $legacy_on && $selected && (($cfg['critical_alerts_enabled'] ?? 'yes') === 'yes');
    }

    if (!$p_warn_on && !$p_crit_on) continue;

    $pool_free = sg_get_pool_free_tb($pname);
    $th = sg_pool_thresholds($cfg, $safe);
    $level = sg_level($pool_free, $th['warn'], $th['crit']);
    if ($level === 'critical' && !$p_crit_on) $level = $p_warn_on ? 'warning' : 'ok';
    if ($level === 'warning' && !$p_warn_on) $level = 'ok';

    if ($level === 'critical' && sg_should_send("pool_{$safe}_critical")) {
        $msg = $th['custom']
            ? "Custom critical threshold {$th['crit_label']}. Pool '{$pname}' free space is " . round($pool_free, 1) . " TB."
            : "Pool '{$pname}' free space is " . round($pool_free, 1) . " TB, at or below critical threshold {$th['crit_label']}. Rebalance after a drive failure may not fit.";
        sg_send_notify("Pool {$pname} Critical", $msg, 'alert');
        $sent[] = "pool_{$safe}_critical";
    } elseif ($level === 'warning' && sg_should_send("pool_{$safe}_warning")) {
        $msg = $th['custom']
            ? "Custom warning threshold {$th['warn_label']}. Pool '{$pname}' free space is " . round($pool_free, 1) . " TB."
            : "Pool '{$pname}' free space is " . round($pool_free, 1) . " TB, at or below warning threshold {$th['warn_label']}.";
        sg_send_notify("Pool {$pname} Warning", $msg, 'warning');
        $sent[] = "pool_{$safe}_warning";
    }
}

echo json_encode(['sent' => !empty($sent), 'alerts' => $sent, 'array_free_tb' => round($array_free, 3)]);
