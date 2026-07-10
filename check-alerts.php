<?php

header('Content-Type: application/json');

require_once __DIR__ . '/sg-lib.php';

$cfg_file = '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
$cfg = [];
if (file_exists($cfg_file)) {
    $cfg = @parse_ini_file($cfg_file) ?: [];
}

if (isset($cfg['alerts_enabled']) && $cfg['alerts_enabled'] !== 'yes'
    && !isset($cfg['alerts_array_warning']) && !isset($cfg['alerts_array_critical'])) {
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
        if ($bytes > 0 || $mnt === '/mnt/user') return $bytes / 1e12;
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

function sg_state_key($key) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
}

function sg_should_send($key, $min_interval = 3600) {
    global $state_dir;
    $file = $state_dir . '/' . sg_state_key($key);
    $last = @filemtime($file);
    if ($last && (time() - $last) < $min_interval) return false;
    @touch($file);
    return true;
}

function sg_get_last_level($key) {
    global $state_dir;
    $file = $state_dir . '/' . sg_state_key($key) . '.level';
    if (!is_file($file)) return '';
    $v = strtolower(trim((string)@file_get_contents($file)));
    return in_array($v, ['ok', 'warning', 'critical'], true) ? $v : '';
}

function sg_set_last_level($key, $level) {
    global $state_dir;
    $file = $state_dir . '/' . sg_state_key($key) . '.level';
    @file_put_contents($file, $level);
}

function sg_process_level($key, $level, $warn_subject, $crit_subject, $ok_subject, $warn_body, $crit_body, $ok_body) {
    global $sent, $state_dir;
    $last = sg_get_last_level($key);

    if ($level === 'critical') {
        if ($last !== 'critical') {
            sg_send_notify($crit_subject, $crit_body, 'alert');
            @touch($state_dir . '/' . sg_state_key($key));
            $sent[] = $key . '_critical';
        } elseif (sg_should_send($key)) {
            sg_send_notify($crit_subject, $crit_body, 'alert');
            $sent[] = $key . '_critical';
        }
        sg_set_last_level($key, 'critical');
        return;
    }

    if ($level === 'warning') {
        if ($last !== 'warning') {
            sg_send_notify($warn_subject, $warn_body, 'warning');
            @touch($state_dir . '/' . sg_state_key($key));
            $sent[] = $key . '_warning';
        } elseif (sg_should_send($key)) {
            sg_send_notify($warn_subject, $warn_body, 'warning');
            $sent[] = $key . '_warning';
        }
        sg_set_last_level($key, 'warning');
        return;
    }

    if ($last === 'warning' || $last === 'critical') {
        sg_send_notify($ok_subject, $ok_body, 'normal');
        $sent[] = $key . '_recovered';
    }
    sg_set_last_level($key, 'ok');
}

function sg_flag($cfg, $key) {
    return ($cfg[$key] ?? 'no') === 'yes';
}

function sg_largest_data_disk_tb() {
    $max = 0.0;
    foreach (sg_array_data_disks() as $d) {
        if ($d['tb'] > $max) $max = $d['tb'];
    }
    return $max;
}

function sg_largest_data_disk_label() {
    $tb = sg_largest_data_disk_tb();
    if ($tb <= 0) return '';
    $val = round($tb, 1);
    return rtrim(rtrim(sprintf('%.1f', $val), '0'), '.') . 'T';
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
    $sg_ok = (($cfg['sg_defaults'] ?? '') === '1');
    if ($sg_ok && array_key_exists('array_warning', $cfg)) {
        $warn_label = $cfg['array_warning'];
        $warn = sg_parse_to_tb($warn_label);
    } else {
        $warn_label = sg_largest_data_disk_label();
        $warn = sg_largest_data_disk_tb();
    }
    return [
        'warn' => $warn,
        'crit' => sg_parse_to_tb($cfg['array_critical'] ?? ''),
        'warn_label' => $warn_label,
        'crit_label' => $cfg['array_critical'] ?? '',
        'custom' => false,
    ];
}

function sg_pool_thresholds($cfg, $safe, $pname = null) {
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
    $th = [
        'warn' => sg_parse_to_tb($cfg["pool_{$safe}_warning"] ?? ''),
        'crit' => sg_parse_to_tb($cfg["pool_{$safe}_critical"] ?? ''),
        'warn_label' => $cfg["pool_{$safe}_warning"] ?? '',
        'crit_label' => $cfg["pool_{$safe}_critical"] ?? '',
        'custom' => false,
    ];
    // RAID1/mirror: disk-size dropdown is evacuate-room semantics — do not apply
    $pool = $pname !== null ? $pname : $safe;
    $class = sg_pool_profile_class(sg_pool_btrfs_profile($pool));
    if (sg_pool_ignore_disk_size_thresholds($class)) {
        $th['warn'] = 0.0;
        $th['crit'] = 0.0;
        $th['warn_label'] = '';
        $th['crit_label'] = '';
    }
    return $th;
}

$sent = [];
$array_free = sg_get_array_free_tb();

$has_legacy_alerts = isset($cfg['alerts_enabled']) || isset($cfg['alerts_for'])
    || isset($cfg['warning_alerts_enabled']) || isset($cfg['critical_alerts_enabled']);
$sg_defaults_ok = (($cfg['sg_defaults'] ?? '') === '1');
if ($sg_defaults_ok && isset($cfg['alerts_array_warning'])) {
    $arr_warn_on = sg_flag($cfg, 'alerts_array_warning');
} elseif ($has_legacy_alerts && $sg_defaults_ok) {
    $legacy_on = ($cfg['alerts_enabled'] ?? 'yes') === 'yes';
    $legacy_for = $cfg['alerts_for'] ?? 'all';
    $array_selected = ($legacy_for === 'all') || in_array('array', array_map('trim', explode(',', $legacy_for)), true);
    $arr_warn_on = $legacy_on && $array_selected && (($cfg['warning_alerts_enabled'] ?? 'yes') === 'yes');
} else {
    $arr_warn_on = true;
}
if ($sg_defaults_ok && isset($cfg['alerts_array_critical'])) {
    $arr_crit_on = sg_flag($cfg, 'alerts_array_critical');
} elseif ($has_legacy_alerts && $sg_defaults_ok) {
    $legacy_on = ($cfg['alerts_enabled'] ?? 'yes') === 'yes';
    $legacy_for = $cfg['alerts_for'] ?? 'all';
    $array_selected = ($legacy_for === 'all') || in_array('array', array_map('trim', explode(',', $legacy_for)), true);
    $arr_crit_on = $legacy_on && $array_selected && (($cfg['critical_alerts_enabled'] ?? 'yes') === 'yes');
} else {
    $arr_crit_on = false;
}

if ($arr_warn_on || $arr_crit_on) {
    $th = sg_array_thresholds($cfg);
    $level = sg_level($array_free, $th['warn'], $th['crit']);
    if ($level === 'critical' && !$arr_crit_on) $level = $arr_warn_on ? 'warning' : 'ok';
    if ($level === 'warning' && !$arr_warn_on) $level = 'ok';

    if ($level === 'critical' || $level === 'warning' || $level === 'ok') {
        $warn_body = sg_array_notify_body('warning', $array_free, $th);
        $crit_body = sg_array_notify_body('critical', $array_free, $th);
        $free_h = function_exists('sg_human_free') ? sg_human_free($array_free) : (round($array_free, 2) . 'T');
        $ok_body = "Array free space is back above your thresholds ({$free_h} free). No longer at warning or critical free-space levels.";
        sg_process_level(
            'array',
            $level,
            'Storage Guard: Array free space warning',
            'Storage Guard: Array free space critical',
            'Storage Guard: Array free space recovered',
            $warn_body,
            $crit_body,
            $ok_body
        );
    }
}

$seen_pools = [];
foreach ($cfg as $k => $v) {
    if (!preg_match('/^pool_([a-zA-Z0-9_]+)_(warning|warning_custom)$/', $k, $m)) continue;
    $safe = $m[1];
    if (isset($seen_pools[$safe])) continue;
    $seen_pools[$safe] = true;
    $pname = $safe;

    $warn_key = "alerts_pool_{$safe}_warning";
    $crit_key = "alerts_pool_{$safe}_critical";
    if ($sg_defaults_ok && isset($cfg[$warn_key])) {
        $p_warn_on = sg_flag($cfg, $warn_key);
    } else {
        $p_warn_on = false;
    }
    if ($sg_defaults_ok && isset($cfg[$crit_key])) {
        $p_crit_on = sg_flag($cfg, $crit_key);
    } else {
        $p_crit_on = false;
    }

    if (!$p_warn_on && !$p_crit_on) continue;

    $pool_free = sg_get_pool_free_tb($pname);
    $th = sg_pool_thresholds($cfg, $safe, $pname);
    $level = sg_level($pool_free, $th['warn'], $th['crit']);
    if ($level === 'critical' && !$p_crit_on) $level = $p_warn_on ? 'warning' : 'ok';
    if ($level === 'warning' && !$p_warn_on) $level = 'ok';

    $profile = sg_pool_btrfs_profile($pname);
    $class = sg_pool_profile_class($profile);

    if ($level === 'critical' || $level === 'warning' || $level === 'ok') {
        $warn_body = sg_pool_notify_body('warning', $pname, $pool_free, $th, $profile, $class);
        $crit_body = sg_pool_notify_body('critical', $pname, $pool_free, $th, $profile, $class);
        $free_h = function_exists('sg_human_free') ? sg_human_free($pool_free) : (round($pool_free, 2) . 'T');
        $ok_body = "Pool {$pname} free space is back above your thresholds ({$free_h} free). No longer at warning or critical free-space levels.";
        sg_process_level(
            "pool_{$safe}",
            $level,
            "Storage Guard: Pool {$pname} free space warning",
            "Storage Guard: Pool {$pname} free space critical",
            "Storage Guard: Pool {$pname} free space recovered",
            $warn_body,
            $crit_body,
            $ok_body
        );
    }
}

echo json_encode([
    'sent' => !empty($sent),
    'alerts' => $sent,
    'array_free_tb' => round($array_free, 3),
]);
