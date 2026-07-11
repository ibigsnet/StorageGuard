<?php

function sg_format_size_kb($kb) {
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
        return round($bytes / (1024 * 1024)) . 'M';
    }
    return round($bytes / 1024) . 'K';
}

function sg_kb_to_tb($kb) {
    if ($kb <= 0) return 0.0;
    return ($kb * 1024.0) / 1e12;
}

function sg_array_data_disks() {
    $disks_ini = '/var/local/emhttp/disks.ini';
    if (!file_exists($disks_ini)) return [];
    $disks = @parse_ini_file($disks_ini, true) ?: [];
    $out = [];
    foreach ($disks as $key => $d) {
        if (empty($d['device'])) continue;
        $type = $d['type'] ?? '';
        $name = $d['name'] ?? $key;
        $is_data = ($type === 'Data') || preg_match('/^disk\d+$/', $name) || preg_match('/^disk\d+$/', $key);
        if (!$is_data) continue;
        $kb = isset($d['size']) ? (int)$d['size'] : 0;
        if ($kb <= 0) continue;
        // Unraid disks.ini "device" is the kernel id (sda, nvme0n1, …), not /dev/…
        $dev = trim((string)($d['device'] ?? ''));
        $dev = preg_replace('#^/dev/#', '', $dev);
        $out[] = [
            'name' => $name,
            'device' => $dev,
            'label' => sg_format_size_kb($kb),
            'tb' => sg_kb_to_tb($kb),
            'kb' => $kb,
        ];
    }
    return $out;
}

/** True when Unraid has array data disks (not pools-only / no-array). */
function sg_array_present() {
    return !empty(sg_array_data_disks());
}

function sg_disks_matching_threshold($label, $tb) {
    $label = trim((string)$label);
    $tb = (float)$tb;
    $matches = [];
    foreach (sg_array_data_disks() as $disk) {
        if ($label !== '' && strcasecmp($disk['label'], $label) === 0) {
            $matches[] = $disk;
            continue;
        }
        if ($tb > 0 && $disk['tb'] > 0) {
            $diff = abs($disk['tb'] - $tb) / max($tb, $disk['tb']);
            if ($diff <= 0.03) $matches[] = $disk;
        }
    }
    $seen = [];
    $uniq = [];
    foreach ($matches as $d) {
        if (isset($seen[$d['name']])) continue;
        $seen[$d['name']] = true;
        $uniq[] = $d;
    }
    return $uniq;
}

/**
 * Human list for array alerts, e.g. "disk1 | sdc (26T) or disk2 | sdb (26T)".
 * Device id is Unraid disks.ini "device" (sda, nvme0n1, …) when present.
 */
function sg_format_disk_list($disks) {
    if (empty($disks)) return '';
    $parts = [];
    foreach ($disks as $d) {
        $name = $d['name'] ?? '';
        $label = $d['label'] ?? '';
        $dev = trim((string)($d['device'] ?? ''));
        if ($dev !== '') {
            $parts[] = $name . ' | ' . $dev . ' (' . $label . ')';
        } else {
            $parts[] = $name . ' (' . $label . ')';
        }
    }
    if (count($parts) === 1) return $parts[0];
    if (count($parts) === 2) return $parts[0] . ' or ' . $parts[1];
    $last = array_pop($parts);
    return implode(', ', $parts) . ', or ' . $last;
}

function sg_human_free($tb) {
    $tb = (float)$tb;
    if ($tb >= 1) {
        $v = round($tb, 1);
        return rtrim(rtrim(sprintf('%.1f', $v), '0'), '.') . 'T';
    }
    $g = round($tb * 1000, 0);
    return $g . 'G';
}

function sg_array_failure_context($label, $tb, $custom) {
    if ($custom) {
        $L = $label !== '' ? $label : sg_human_free($tb);
        return "your custom free-space threshold of {$L}";
    }
    $disks = sg_disks_matching_threshold($label, $tb);
    if (!empty($disks)) {
        $list = sg_format_disk_list($disks);
        $n = count($disks);
        if ($n === 1) {
            return "data disk {$list}";
        }
        return "any of these data disks: {$list}";
    }
    $L = $label !== '' ? $label : sg_human_free($tb);
    return "a data disk of about {$L}";
}

function sg_array_notify_body($severity, $free_tb, $th) {
    $is_crit = ($severity === 'critical');
    $label = $is_crit ? ($th['crit_label'] ?? '') : ($th['warn_label'] ?? '');
    $tb = $is_crit ? (float)($th['crit'] ?? 0) : (float)($th['warn'] ?? 0);
    $custom = !empty($th['custom']);
    $free_h = sg_human_free($free_tb);
    $thresh_h = $label !== '' ? $label : sg_human_free($tb);
    $fail = sg_array_failure_context($label, $tb, $custom);

    $line1 = "Array free space is {$free_h}, at or below your " .
        ($is_crit ? 'critical' : 'warning') . " free-space threshold of {$thresh_h}.";

    if ($is_crit) {
        $line2 = "If you lost {$fail}, there is likely not enough free space on the rest of the array to move that disk's data off without buying a replacement (or freeing a large amount of space first).";
    } else {
        $line2 = "If you lost {$fail}, there may not be enough free space on the rest of the array to move that disk's data off without buying a replacement.";
    }

    $line3 = "Parity can keep the array online with an emulated disk; this alert is about evacuation room, not immediate total data loss.";

    return $line1 . ' ' . $line2 . ' ' . $line3;
}

function sg_pool_btrfs_profile($pool) {
    $mount = '/mnt/' . $pool;
    if (!is_dir($mount)) return '';
    $output = @shell_exec('btrfs fi df ' . escapeshellarg($mount) . ' 2>/dev/null');
    if ($output && preg_match('/Data,\s*(\S+):/i', $output, $m)) {
        return rtrim($m[1], ':');
    }
    return '';
}

function sg_pool_profile_class($profile) {
    $p = strtolower(trim((string)$profile));
    if ($p === '' || $p === 'unknown') return 'unknown';
    if (strpos($p, 'raid10') !== false) return 'striped_mirror';
    if (preg_match('/raid1c[34]/', $p) || preg_match('/\braid1\b/', $p)) return 'mirror';
    if (strpos($p, 'raid5') !== false || strpos($p, 'raid6') !== false) return 'parity';
    if (strpos($p, 'raid0') !== false || strpos($p, 'single') !== false) return 'none';
    if (strpos($p, 'dup') !== false) return 'mirror';
    return 'unknown';
}

/**
 * Disk-size thresholds model array-style "evacuate room" (largest member free).
 * On mirrored pools (RAID1/1cN/dup) that model does not apply: a single disk
 * failure usually leaves a full copy, so free space is not required to move
 * data off the failed disk. Ignore disk-size dropdown values for paint/alerts;
 * custom free-space values still apply as capacity policy.
 */
function sg_pool_ignore_disk_size_thresholds($class) {
    return $class === 'mirror';
}

function sg_pool_notify_body($severity, $pname, $free_tb, $th, $profile, $class) {
    $is_crit = ($severity === 'critical');
    $label = $is_crit ? ($th['crit_label'] ?? '') : ($th['warn_label'] ?? '');
    $tb = $is_crit ? (float)($th['crit'] ?? 0) : (float)($th['warn'] ?? 0);
    $free_h = sg_human_free($free_tb);
    $thresh_h = $label !== '' ? $label : sg_human_free($tb);
    $level = $is_crit ? 'critical' : 'warning';
    $prof = $profile !== '' ? $profile : 'unknown';

    $line1 = "Pool '{$pname}' free space is {$free_h}, at or below your {$level} free-space threshold of {$thresh_h}.";
    $line1 .= " Layout: {$prof}.";

    switch ($class) {
        case 'mirror':
            $line2 = $is_crit
                ? "On BTRFS RAID1/RAID1cN, each chunk has multiple copies on different devices—a single disk loss usually still leaves data online. Free space is not 'evacuate the failed disk' headroom (unlike the array); it matters for capacity after the loss, optional remove/rebalance, replace, or profile convert."
                : "On BTRFS RAID1/RAID1cN, losing one disk usually still leaves your data on the remaining copy/copies—you typically do not need free space to 'move data off' the failed disk the way you do on the array. Free still matters so used data fits at the reduced usable capacity, and for optional remove/rebalance, replace, or profile changes.";
            break;
        case 'parity':
            $line2 = $is_crit
                ? "On BTRFS RAID5/RAID6, data can survive a limited number of failures while degraded. Free space this low may block remove/rebalance or replace recovery, or leave used data larger than post-loss usable capacity. See Unraid pool docs and BTRFS RAID56 notes for profile behavior."
                : "On BTRFS RAID5/RAID6, free space is capacity + recovery headroom: after a loss, used data must still fit, and replace/remove/rebalance often needs room. Closer to the array's 'room to recover' idea than RAID1. See Unraid pool docs and BTRFS RAID56 notes for profile behavior.";
            break;
        case 'striped_mirror':
            $line2 = $is_crit
                ? "On BTRFS RAID10 (two copies + striping, not fixed mirror pairs), a single disk loss usually leaves data available. Free space this low may mean used data no longer fits after capacity drops, or block remove-to-remaining-disks / rebalance / convert without adding a drive."
                : "On BTRFS RAID10, one disk loss usually leaves data online; you are not forced to replace immediately if remaining disks can hold the data. Free space is wiggle room so used data still fits after the capacity drop, and so optional remove/rebalance or profile convert can succeed.";
            break;
        case 'none':
            $line2 = "This pool has little or no redundancy (single/RAID0). Free-space thresholds here are capacity policy only: a disk failure risks data—there is no parity-style evacuate-to-siblings story.";
            break;
        default:
            $line2 = $is_crit
                ? "Free space is critically low for this pool's free-space threshold. Check the pool's filesystem/profile on Main for what a disk failure would mean."
                : "Free space is at or below your warning threshold for this pool. Check the pool profile on Settings/Main for failure implications.";
            break;
    }

    return $line1 . ' ' . $line2;
}
