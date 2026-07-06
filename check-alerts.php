<?php
// Storage Guard - Alert checker
// Called from the color injector to send notifications if thresholds breached.
// Respects enabled categories and uses throttling.

header('Content-Type: application/json');

$cfg_file = '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
$cfg = [];
if (file_exists($cfg_file)) {
    $cfg = parse_ini_file($cfg_file);
}

$alerts_enabled = ($cfg['alerts_enabled'] ?? 'yes') === 'yes';
if (!$alerts_enabled) {
    echo json_encode(['sent' => false, 'reason' => 'alerts disabled']);
    exit;
}

$warning_enabled = ($cfg['warning_alerts_enabled'] ?? 'yes') === 'yes';
$critical_enabled = ($cfg['critical_alerts_enabled'] ?? 'yes') === 'yes';

$alerts_for = $cfg['alerts_for'] ?? 'all';
$enabled_targets = [];
if ($alerts_for === 'all') {
    $enabled_targets = ['array'];
    // will add pools later
} else {
    $enabled_targets = explode(',', $alerts_for);
    $enabled_targets = array_map('trim', $enabled_targets);
}

$state_dir = '/tmp/storageguard_alerts';
@mkdir($state_dir, 0755, true);

function get_current_array_free_tb() {
    $cmd = "df -BG --output=avail /mnt/user 2>/dev/null | tail -1 | tr -d 'G '";
    $gb = (int)trim(@shell_exec($cmd));
    return $gb / 1024.0;
}

function get_pool_free_tb($pool) {
    $mount = "/mnt/" . $pool;
    if (!is_dir($mount)) return 0;
    $cmd = "df -BG --output=avail " . escapeshellarg($mount) . " 2>/dev/null | tail -1 | tr -d 'G '";
    $gb = (int)trim(@shell_exec($cmd));
    return $gb / 1024.0;
}

function parse_to_tb($str) {
    if (!$str) return 0;
    $num = (float)$str;
    $u = strtoupper($str);
    if (strpos($u, 'T') !== false) return $num;
    if (strpos($u, 'G') !== false) return $num / 1024;
    if (strpos($u, 'M') !== false) return $num / 1024 / 1024;
    return $num;
}

function send_notify($subject, $desc, $priority = 'warning') {
    $cmd = "/usr/local/emhttp/webGui/scripts/notify -e 'Storage Guard' -s " . escapeshellarg($subject) . " -d " . escapeshellarg($desc) . " -i " . escapeshellarg($priority) . " -l '/Main'";
    @shell_exec($cmd);
}

function should_send($key, $min_interval = 3600) { // 1 hour
    global $state_dir;
    $file = $state_dir . '/' . $key;
    $last = @filemtime($file);
    if ($last && (time() - $last) < $min_interval) return false;
    @touch($file);
    return true;
}

$sent = [];
$array_free = get_current_array_free_tb();
$pool_frees = 0;

// Array alerts
if (in_array('array', $enabled_targets) || $enabled_targets === ['array'] || in_array('all', $enabled_targets)) {
    $use_custom = ($cfg['array_use_custom'] ?? 'no') === 'yes';
    $warn = parse_to_tb($cfg['array_warning'] ?? ($use_custom ? $cfg['array_warning_custom'] : ''));
    $crit = parse_to_tb($cfg['array_critical'] ?? ($use_custom ? $cfg['array_critical_custom'] : ''));

    if ($crit > 0 && $array_free <= $crit && $critical_enabled) {
        $key = 'array_critical';
        if (should_send($key)) {
            if ($use_custom) {
                $msg = "You've set your custom critical threshold to {$cfg['array_critical_custom']}. Current array free space is " . round($array_free,1) . " TB which is below it. You may still have options using pool space.";
            } else {
                $msg = "Your largest disk is the critical size you chose. Current array free space is " . round($array_free,1) . " TB which falls below the remaining capacity should you lose your largest disk. Check if pools have space for migration.";
            }
            send_notify("Array Critical Low Space", $msg, 'alert');
            $sent[] = 'array_critical';
        }
    } elseif ($warn > 0 && $array_free <= $warn && $warning_enabled) {
        $key = 'array_warning';
        if (should_send($key)) {
            if ($use_custom) {
                $msg = "You've set your custom warning threshold to {$cfg['array_warning_custom']}. Current array free space is " . round($array_free,1) . " TB which is below it.";
            } else {
                $msg = "Your largest disk is " . ($cfg['array_warning'] ?? '') . ". Current array free space is " . round($array_free,1) . " TB which falls below the remaining capacity should you lose your largest disk.";
            }
            send_notify("Array Warning Low Space", $msg, 'warning');
            $sent[] = 'array_warning';
        }
    }
}

// Pools
$pools_to_color = $cfg['pools_to_color'] ?? 'all';
$pool_list = ($pools_to_color === 'all') ? [] : explode(',', $pools_to_color); // for simplicity, check all configured

// For demo, check configured pools from keys
foreach ($cfg as $k => $v) {
    if (strpos($k, 'pool_') === 0 && strpos($k, '_warning') !== false) {
        $pname = str_replace(['pool_', '_warning'], '', $k);
        if ($pools_to_color !== 'all' && !in_array($pname, $pool_list)) continue;

        $pool_free = get_pool_free_tb($pname);
        $pool_frees += $pool_free;
        $use_custom = false; // for pools we use the disk based
        $warn = parse_to_tb($cfg["pool_{$pname}_warning"] ?? '2T');
        $crit = parse_to_tb($cfg["pool_{$pname}_critical"] ?? '1T');

        if ($crit > 0 && $pool_free <= $crit && $critical_enabled) {
            $key = "pool_{$pname}_critical";
            if (should_send($key)) {
                $msg = "Pool '{$pname}' critical: current free " . round($pool_free,1) . " TB below threshold " . round($crit,1) . " TB. You may not have space to rebalance after losing a drive. Check array space for migration options.";
                send_notify("Pool {$pname} Critical", $msg, 'alert');
                $sent[] = $key;
            }
        } elseif ($warn > 0 && $pool_free <= $warn && $warning_enabled) {
            $key = "pool_{$pname}_warning";
            if (should_send($key)) {
                $msg = "Pool '{$pname}' warning: current free " . round($pool_free,1) . " TB below " . round($warn,1) . " TB. Array space may still allow migration.";
                send_notify("Pool {$pname} Warning", $msg, 'warning');
                $sent[] = $key;
            }
        }
    }
}

// Cross global awareness
if ($array_free <= $warn && $pool_frees > 0 && $warning_enabled) {
  $total = $array_free + $pool_frees;
  if ($total > $warn) {
    // msgs already note to check cross space for migration
  }
}

echo json_encode(['sent' => !empty($sent), 'alerts' => $sent]);
