<?php

/**
 * Format disks.ini size (KiB) like Unraid Main — decimal SI, not binary TiB/GiB.
 *
 * Unraid stores size in KiB (1024-byte units) but Main displays advertised capacity
 * with powers of 1000 (e.g. ~25.9T for a marketed 26TB drive, not ~23.6T).
 * Free-space math and threshold parse already use SI; labels must match.
 */
function sg_format_size_kb($kb) {
    if (!$kb || $kb <= 0) return '0';
    $bytes = (float)$kb * 1024.0;
    if ($bytes >= 1e12) {
        $val = round($bytes / 1e12, 1);
        return rtrim(rtrim(sprintf('%.1f', $val), '0'), '.') . 'T';
    }
    if ($bytes >= 1e9) {
        $val = round($bytes / 1e9, 1);
        return rtrim(rtrim(sprintf('%.1f', $val), '0'), '.') . 'G';
    }
    if ($bytes >= 1e6) {
        return (string)round($bytes / 1e6) . 'M';
    }
    if ($bytes >= 1e3) {
        return (string)round($bytes / 1e3) . 'K';
    }
    return (string)max(0, (int)round($bytes)) . 'B';
}

/**
 * Pre-2026.08.16 binary (1024^n) size label — only for migrating saved disk-size cfg.
 * Do not use for new UI or thresholds.
 */
function sg_format_size_kb_legacy_binary($kb) {
    if (!$kb || $kb <= 0) return '0';
    $bytes = (float)$kb * 1024.0;
    $tib = 1024.0 * 1024.0 * 1024.0 * 1024.0;
    $gib = 1024.0 * 1024.0 * 1024.0;
    $mib = 1024.0 * 1024.0;
    $kib = 1024.0;
    if ($bytes >= $tib) {
        $val = round($bytes / $tib, 1);
        return rtrim(rtrim(sprintf('%.1f', $val), '0'), '.') . 'T';
    }
    if ($bytes >= $gib) {
        $val = round($bytes / $gib, 1);
        return rtrim(rtrim(sprintf('%.1f', $val), '0'), '.') . 'G';
    }
    if ($bytes >= $mib) {
        return (string)round($bytes / $mib) . 'M';
    }
    return (string)round($bytes / $kib) . 'K';
}

/**
 * Map a saved disk-size dropdown value to SI if it was a legacy binary label for a live disk.
 * Leaves custom / unmatched strings unchanged (e.g. intentional free floors not tied to a disk).
 *
 * @param string $label
 * @param int[]  $size_kbs disks.ini size fields (KiB)
 */
function sg_migrate_disk_size_label($label, $size_kbs) {
    $label = trim((string)$label);
    if ($label === '' || $label === '0' || empty($size_kbs)) {
        return $label;
    }
    $si_set = [];
    $bin_to_si = [];
    foreach ($size_kbs as $kb) {
        $kb = (int)$kb;
        if ($kb <= 0) {
            continue;
        }
        $si = sg_format_size_kb($kb);
        $si_set[$si] = true;
        $bin = sg_format_size_kb_legacy_binary($kb);
        if ($bin !== '' && $bin !== $si) {
            $bin_to_si[$bin] = $si;
        }
    }
    if (isset($si_set[$label])) {
        return $label;
    }
    if (isset($bin_to_si[$label])) {
        return $bin_to_si[$label];
    }
    return $label;
}

/** @return int[] data-disk sizes from disks.ini (KiB) */
function sg_array_data_disk_size_kbs() {
    $disks_ini = '/var/local/emhttp/disks.ini';
    if (!is_file($disks_ini)) {
        return [];
    }
    $disks = @parse_ini_file($disks_ini, true) ?: [];
    $out = [];
    foreach ($disks as $key => $d) {
        if (empty($d['device'])) {
            continue;
        }
        $type = $d['type'] ?? '';
        $name = $d['name'] ?? $key;
        $is_data = ($type === 'Data') || preg_match('/^disk\d+$/', $name) || preg_match('/^disk\d+$/', $key);
        if (!$is_data) {
            continue;
        }
        $kb = isset($d['size']) ? (int)$d['size'] : 0;
        if ($kb > 0) {
            $out[] = $kb;
        }
    }
    return $out;
}

/** @return int[] pool member sizes from disks.ini (KiB) for a pool name (e.g. cache) */
function sg_pool_member_size_kbs($pool) {
    $pool = preg_replace('/\d+$/', '', (string)$pool);
    if ($pool === '') {
        return [];
    }
    $disks_ini = '/var/local/emhttp/disks.ini';
    if (!is_file($disks_ini)) {
        return [];
    }
    $disks = @parse_ini_file($disks_ini, true) ?: [];
    $out = [];
    foreach ($disks as $key => $d) {
        if (($d['type'] ?? '') !== 'Cache') {
            continue;
        }
        if (empty($d['device'])) {
            continue;
        }
        $status = $d['status'] ?? '';
        if (strpos($status, '_NP') !== false) {
            continue;
        }
        $prefix = preg_replace('/\d+$/', '', $key);
        if ($prefix !== $pool) {
            continue;
        }
        $kb = isset($d['size']) ? (int)$d['size'] : 0;
        if ($kb > 0) {
            $out[] = $kb;
        }
    }
    return $out;
}

function sg_kb_to_tb($kb) {
    if ($kb <= 0) return 0.0;
    // KiB → bytes → decimal TB (SI), same scale as Unraid Main and free-space math
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

/**
 * True when Unraid storage is fully up for free-space monitoring.
 * Requires fsState=Started, mdState=STARTED (when set), and not Maintenance.
 * When stopped / starting / stopping / maintenance, mounts are missing or
 * meaningless — free space must not be treated as 0B for alerts or paint.
 */
function sg_storage_online() {
    $var_file = '/var/local/emhttp/var.ini';
    if (!is_file($var_file)) return false;
    $var = @parse_ini_file($var_file) ?: [];
    $fs = trim((string)($var['fsState'] ?? ''));
    if (strcasecmp($fs, 'Started') !== 0) return false;
    $md = trim((string)($var['mdState'] ?? ''));
    if ($md !== '' && strcasecmp($md, 'STARTED') !== 0) return false;
    $mode = trim((string)($var['startMode'] ?? 'Normal'));
    if ($mode !== '' && strcasecmp($mode, 'Maintenance') === 0) return false;
    return true;
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
 * Disk-size dropdown = array-style "evacuate largest member" free.
 * On BTRFS mirror / RAID10 / parity that model is wrong for capacity-fit (and for
 * 2-disk RAID1, Δ_fit is often 0 — data already has a full copy on the survivor).
 * Disk-size values are ignored for paint/alerts on those profiles unless the user
 * switches to Custom free (explicit policy).
 */
function sg_pool_ignore_disk_size_thresholds($class) {
    return in_array($class, ['mirror', 'striped_mirror', 'parity'], true);
}

/**
 * Format TB for labels (e.g. 1.99 → "2T", 0.18 → "180G").
 */
function sg_format_tb_short($tb) {
    $tb = (float)$tb;
    if ($tb <= 0) return '';
    if ($tb >= 1.0) {
        return rtrim(rtrim(number_format($tb, 2, '.', ''), '0'), '.') . 'T';
    }
    $g = $tb * 1000.0;
    return rtrim(rtrim(number_format($g, 0, '.', ''), '0'), '.') . 'G';
}

/** Parse free threshold strings like 3.6T / 500G to TB (decimal SI-style /1000). */
function sg_lib_parse_to_tb($str) {
    if ($str === null || $str === '') return 0.0;
    if (!preg_match('/([0-9]*\.?[0-9]+)\s*([TGMKtgmk]?)/', (string)$str, $m)) return 0.0;
    $num = (float)$m[1];
    $u = strtoupper($m[2] ?: 'T');
    if ($u === 'T') return $num;
    if ($u === 'G') return $num / 1000.0;
    if ($u === 'M') return $num / 1e6;
    if ($u === 'K') return $num / 1e9;
    return $num;
}

/**
 * Resolve pool free thresholds for paint/alerts.
 * User values always win. Empty thresholds may soft-default to capacity-fit *suggestions*
 * (recommended: larger free floor = Warning, smaller = Critical) — user may reverse or
 * pick any free amounts via Custom / disk-size.
 *
 * Severity paint rule (sg_level): lower free amount = more severe (critical), higher free = warning,
 * regardless of form field order. Prefer Warning free ≥ Critical free as amounts of free space.
 *
 * @return array{warn:float,crit:float,warn_label:string,crit_label:string,custom:bool,source:string,suggest?:array|null}
 */
function sg_pool_resolve_thresholds($cfg, $safe, $pname = null) {
    $pool = $pname !== null ? $pname : $safe;
    $use_custom = ($cfg["pool_{$safe}_use_custom"] ?? 'no') === 'yes';
    $sug = null;
    if (function_exists('sg_pool_math_package')) {
        $profile = function_exists('sg_pool_btrfs_profile') ? sg_pool_btrfs_profile($pool) : '';
        $pkg = @sg_pool_math_package($pool, $profile);
        $sug = (is_array($pkg) && isset($pkg['suggest']) && is_array($pkg['suggest'])) ? $pkg['suggest'] : null;
    }

    if ($use_custom) {
        $w = sg_lib_parse_to_tb($cfg["pool_{$safe}_warning_custom"] ?? '');
        $c = sg_lib_parse_to_tb($cfg["pool_{$safe}_critical_custom"] ?? '');
        return [
            'warn' => $w,
            'crit' => $c,
            'warn_label' => (string)($cfg["pool_{$safe}_warning_custom"] ?? ''),
            'crit_label' => (string)($cfg["pool_{$safe}_critical_custom"] ?? ''),
            'custom' => true,
            'source' => 'custom',
            'suggest' => $sug,
        ];
    }

    $wl = (string)($cfg["pool_{$safe}_warning"] ?? $cfg["pool_{$pool}_warning"] ?? '');
    $cl = (string)($cfg["pool_{$safe}_critical"] ?? $cfg["pool_{$pool}_critical"] ?? '');
    // Disk-size dropdowns: migrate legacy binary labels (23.6T) → Unraid SI (25.9T)
    $pool_kbs = function_exists('sg_pool_member_size_kbs') ? sg_pool_member_size_kbs($pool) : [];
    if (!empty($pool_kbs)) {
        $wl = sg_migrate_disk_size_label($wl, $pool_kbs);
        $cl = sg_migrate_disk_size_label($cl, $pool_kbs);
    }
    $w = sg_lib_parse_to_tb($wl);
    $c = sg_lib_parse_to_tb($cl);

    $class = is_array($sug) ? (string)($sug['class'] ?? '') : '';
    if ($class === '' && function_exists('sg_pool_btrfs_profile') && function_exists('sg_pool_profile_class')) {
        $class = sg_pool_profile_class(sg_pool_btrfs_profile($pool));
    }
    // Evacuate-style disk-size floors are not used for capacity-fit profiles (Custom only).
    if (sg_pool_ignore_disk_size_thresholds($class)) {
        $w = 0.0;
        $c = 0.0;
        $wl = '';
        $cl = '';
    }

    // Soft default only when nothing configured and capacity math has a real Δ (>0)
    if ($w <= 0 && $c <= 0 && is_array($sug) && !empty($sug['apply'])) {
        $w = (float)($sug['warn_tb'] ?? 0);
        $c = (float)($sug['crit_tb'] ?? 0);
        return [
            'warn' => $w,
            'crit' => $c,
            'warn_label' => sg_format_tb_short($w),
            'crit_label' => sg_format_tb_short($c),
            'custom' => false,
            'source' => 'capacity_suggest_default',
            'suggest' => $sug,
        ];
    }

    return [
        'warn' => $w,
        'crit' => $c,
        'warn_label' => $wl,
        'crit_label' => $cl,
        'custom' => false,
        'source' => ($w > 0 || $c > 0) ? 'disk_size' : 'none',
        'suggest' => $sug,
    ];
}

function sg_pool_notify_body($severity, $pname, $free_tb, $th, $profile, $class) {
    $is_crit = ($severity === 'critical');
    $label = $is_crit ? ($th['crit_label'] ?? '') : ($th['warn_label'] ?? '');
    $tb = $is_crit ? (float)($th['crit'] ?? 0) : (float)($th['warn'] ?? 0);
    $free_h = sg_human_free($free_tb);
    $thresh_h = $label !== '' ? $label : sg_human_free($tb);
    $level = $is_crit ? 'critical' : 'warning';
    $prof = $profile !== '' ? $profile : 'unknown';
    $w = (float)($th['warn'] ?? 0);
    $c = (float)($th['crit'] ?? 0);
    $mild = max($w, $c);
    $severe = min(array_filter([$w, $c], function ($x) { return $x > 0; }) ?: [0]);
    if ($w > 0 && $c > 0) {
        $severe = min($w, $c);
        $mild = max($w, $c);
    }

    $line1 = "Pool '{$pname}' free space is {$free_h}, at or below your {$level} free-space threshold of {$thresh_h}.";
    $line1 .= " Layout: {$prof}.";
    $line1 .= " Warning/Critical here mean free-space severity (yellow vs red), not which disk already failed.";

    $sug = $th['suggest'] ?? null;
    $guide = '';
    if (is_array($sug) && !empty($sug['apply'])) {
        $lg = sg_format_tb_short((float)($sug['largest_loss_delta_tb'] ?? $sug['warn_tb'] ?? 0));
        $sm = sg_format_tb_short((float)($sug['smallest_loss_delta_tb'] ?? $sug['crit_tb'] ?? 0));
        if ($lg !== '' && $sm !== '') {
            $guide = " Capacity-fit guide (optional): ~{$lg} free so used data still fits after losing the largest member; ~{$sm} free after losing the smallest. You can map either number to Warning or Critical (or use your own free amounts).";
            if ($lg === $sm) {
                $guide = " Capacity-fit guide (optional): members are equal size — about {$lg} free so used data still fits after any one member loss.";
            }
        }
    }

    switch ($class) {
        case 'mirror':
            $line2 = "On BTRFS RAID1/RAID1cN, a single disk loss usually leaves data online (extra copies). Free space is about whether used data still fits after usable capacity shrinks — not Unraid-array “evacuate this disk onto free space.”";
            break;
        case 'parity':
            $line2 = "On BTRFS RAID5/RAID6, free space is capacity after a loss plus room for replace/remove/rebalance. See Unraid pool docs and BTRFS RAID56 notes for profile behavior.";
            break;
        case 'striped_mirror':
            $line2 = "On BTRFS RAID10 (two copies + striping), one disk loss usually leaves data online. Free space is whether used data still fits after usable capacity drops, and whether remove/rebalance/convert has room.";
            break;
        case 'none':
            $line2 = "This pool has little or no redundancy (single/RAID0). Free thresholds are capacity policy only; a disk failure risks data.";
            break;
        default:
            $line2 = "Check the pool profile on Main/Settings for what a disk failure would mean on this layout.";
            break;
    }

    if ($is_crit) {
        $line3 = " Critical free means free is at or below your more severe free floor"
            . ($severe > 0 ? ' (' . sg_human_free($severe) . ')' : '')
            . " — used data may not fit after a capacity-shrinking member loss if you stay this full.";
    } else {
        $line3 = " Warning free means free is at or below your milder free floor"
            . ($mild > 0 ? ' (' . sg_human_free($mild) . ')' : '')
            . " — still time to free space or adjust thresholds before the more severe floor.";
    }

    return $line1 . ' ' . $line2 . $guide . $line3;
}
