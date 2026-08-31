<?php

/**
 * Disk capacity in KiB for labels/thresholds — same source Unraid Main prefers.
 *
 * Main Size column uses effective_fs_size (fsSize, or used+free on BTRFS) when mounted.
 * Falls back to disks.ini size, then sectors×sector_size.
 *
 * @param array $d one disks.ini section
 * @return int KiB (0 if unknown)
 */
function sg_disk_capacity_kb($d) {
    if (!is_array($d)) {
        return 0;
    }
    $fsType = str_replace('luks:', '', (string)($d['fsType'] ?? ''));
    $fsSize = isset($d['fsSize']) ? (int)$d['fsSize'] : 0;
    $used = isset($d['fsUsed']) && is_numeric($d['fsUsed']) ? (int)$d['fsUsed'] : null;
    $free = isset($d['fsFree']) && is_numeric($d['fsFree']) ? (int)$d['fsFree'] : null;
    // Match Unraid effective_fs_size(): BTRFS → used+free when both present
    if (strcasecmp($fsType, 'btrfs') === 0 && $used !== null && $free !== null) {
        $eff = $used + $free;
        if ($eff > 0) {
            return $eff;
        }
    }
    if ($fsSize > 0) {
        return $fsSize;
    }
    $size = isset($d['size']) ? (int)$d['size'] : 0;
    if ($size > 0) {
        return $size;
    }
    $sectors = isset($d['sectors']) ? (float)$d['sectors'] : 0.0;
    $secSz = isset($d['sector_size']) ? (float)$d['sector_size'] : 0.0;
    if ($sectors > 0 && $secSz > 0) {
        return (int)floor(($sectors * $secSz) / 1024.0);
    }
    return 0;
}

/**
 * Format capacity KiB like Unraid Main Size (Helpers.php my_scale, kilo=1000, decimals=-1).
 *
 * Main calls: my_scale($fsSize * 1024, $unit, -1) → SI powers of 1000 with:
 *   decimals=-1 → 0 decimals if ≥100 or value is whole to 1 place; else 1 decimal.
 * Short suffix for dropdowns: 25.9T / 500G (Main shows "25.9 TB").
 */
function sg_format_size_kb($kb) {
    if (!$kb || $kb <= 0) {
        return '0';
    }
    $value = (float)$kb * 1024.0; // bytes
    $kilo = 1000.0;
    $units = ['', 'K', 'M', 'G', 'T', 'P', 'E'];
    $base = $value > 0 ? (int)floor(log($value, $kilo)) : 0;
    $max = count($units) - 1;
    if ($base > $max) {
        $base = $max;
    }
    if ($base < 0) {
        $base = 0;
    }
    $value /= pow($kilo, $base);
    // my_scale($bytes, $unit, -1): special decimals mode used by Main Size column
    if ($value >= 100 || ((int)round($value * 10) % 10) === 0) {
        $decimals = 0;
    } else {
        $decimals = 1;
    }
    // Auto-scale unit bump when value rounds to 1000 at this unit (my_scale scale<0 path)
    if (round($value, -1) == 1000 && $base < $max) {
        $value = 1;
        $base++;
        $decimals = 0;
    }
    $formatted = number_format($value, $decimals, '.', '');
    // Drop trailing zeros after decimal for cleaner dropdown labels (25.0 → 25)
    if (strpos($formatted, '.') !== false) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    $unit = $units[$base];
    return $unit === '' ? $formatted : ($formatted . $unit);
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

/** @return int[] data-disk sizes from disks.ini (KiB; Main-aligned capacity) */
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
        $kb = sg_disk_capacity_kb($d);
        if ($kb > 0) {
            $out[] = $kb;
        }
    }
    return $out;
}

/** @return int[] pool member sizes from disks.ini (KiB; Main-aligned capacity) for a pool name */
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
        $kb = sg_disk_capacity_kb($d);
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

/** Largest array data disk size (decimal TB), or 0. */
function sg_largest_data_disk_tb() {
    $max = 0.0;
    foreach (sg_array_data_disks() as $d) {
        if (($d['tb'] ?? 0) > $max) {
            $max = (float)$d['tb'];
        }
    }
    return $max;
}

/** Smallest array data disk size (decimal TB), or 0. */
function sg_smallest_data_disk_tb() {
    $min = 0.0;
    foreach (sg_array_data_disks() as $d) {
        $tb = (float)($d['tb'] ?? 0);
        if ($tb <= 0) {
            continue;
        }
        if ($min <= 0 || $tb < $min) {
            $min = $tb;
        }
    }
    return $min;
}

/** SI label for largest data disk (e.g. 18T), or ''. */
function sg_largest_data_disk_label() {
    $kbs = sg_array_data_disk_size_kbs();
    if (empty($kbs)) {
        return '';
    }
    rsort($kbs, SORT_NUMERIC);
    return sg_format_size_kb($kbs[0]);
}

/** SI label for smallest data disk (e.g. 4T), or ''. */
function sg_smallest_data_disk_label() {
    $kbs = sg_array_data_disk_size_kbs();
    if (empty($kbs)) {
        return '';
    }
    sort($kbs, SORT_NUMERIC);
    return sg_format_size_kb($kbs[0]);
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
        $kb = sg_disk_capacity_kb($d);
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
    if ($p === '' || $p === 'unknown' || strpos($p, 'unknown') === 0) return 'unknown';
    if (strpos($p, 'raid10') !== false) return 'striped_mirror';
    if (preg_match('/raid1c[34]/', $p) || preg_match('/\braid1\b/', $p)) return 'mirror';
    if (strpos($p, 'raid5') !== false || strpos($p, 'raid6') !== false) return 'parity';
    // RAID0 / single / DUP: no extra copy on another disk (DUP is two copies on the same device).
    if (strpos($p, 'raid0') !== false || strpos($p, 'single') !== false || strpos($p, 'dup') !== false) {
        return 'none';
    }
    return 'unknown';
}

/** Count present pool members (disks.ini Cache slots, skip _NP). */
function sg_pool_member_count($pool) {
    return count(sg_pool_member_size_kbs($pool));
}

/**
 * Can this pool keep data online after losing one whole disk?
 *
 * @return string survives|nonsurvival|unknown
 */
function sg_pool_one_disk_mode($profile, $member_count) {
    $n = (int)$member_count;
    if ($n <= 1) {
        return 'nonsurvival';
    }
    $class = sg_pool_profile_class($profile);
    if ($class === 'none') {
        return 'nonsurvival';
    }
    if ($class === 'unknown') {
        return 'unknown';
    }
    $min = 2;
    if (function_exists('sg_math_min_devices')) {
        $key = function_exists('sg_math_profile_key') ? sg_math_profile_key($profile) : '';
        $min = (int)sg_math_min_devices($key);
    } else {
        $p = strtolower(trim((string)$profile));
        if (strpos($p, 'raid6') !== false) {
            $min = 3;
        } elseif (preg_match('/raid1c4/', $p)) {
            $min = 4;
        } elseif (preg_match('/raid1c3/', $p)) {
            $min = 3;
        }
    }
    if ($n < $min) {
        return 'nonsurvival';
    }
    return 'survives';
}

/** Layout with no one-disk survival always paints/alerts critical (not a free-space floor). */
function sg_pool_effective_level($free_level, $one_disk_mode) {
    if ($one_disk_mode === 'nonsurvival') {
        return 'critical';
    }
    return $free_level;
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
 *
 * User values always win. Empty Warning/Critical = **None** (no yellow/red).
 * Product Default / first install autofills Warning=largest member, Critical=smallest
 * (same model as Array) and turns pool alerts on — user can still choose None after.
 * Capacity-fit **Suggest** remains optional Custom floors (math UI).
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

    // Empty = None. Do not auto-apply capacity-fit Δ for paint/alerts (Suggest button only).
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
            $line2 = "This pool has no whole-disk redundancy (RAID0, single, or DUP). Free thresholds are capacity policy only; Storage Guard already treats a disk failure as data loss.";
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

/**
 * Notify body when the pool cannot survive one whole-disk failure (layout, not free space).
 */
function sg_pool_layout_notify_body($pname, $profile, $member_count) {
    $n = (int)$member_count;
    $prof = trim((string)$profile);
    if ($prof === '' || stripos($prof, 'unknown') !== false) {
        $prof = 'unknown';
    }
    $line1 = "Pool '{$pname}' cannot keep data online if any one member disk fails.";
    $line1 .= " Layout: {$prof}";
    if ($n > 0) {
        $line1 .= ', ' . $n . ' device' . ($n === 1 ? '' : 's');
    }
    $line1 .= '.';

    $p = strtolower($prof);
    if (strpos($p, 'raid0') !== false) {
        $line2 = " BTRFS RAID0 has no copies; losing any disk loses the pool.";
    } elseif (preg_match('/\bsingle\b/', $p)) {
        $line2 = " BTRFS single stores one copy of each chunk; a failed disk loses the chunks that lived only there.";
    } elseif (strpos($p, 'dup') !== false) {
        $line2 = " BTRFS DUP keeps two copies on the same device, so a whole-disk failure still loses the data.";
    } elseif ($n <= 1) {
        $line2 = " A one-disk pool has nowhere else for that data to live.";
    } else {
        $line2 = " This pool does not have enough devices for the data profile to survive one disk loss.";
    }

    return $line1 . $line2 . " Main paints this pool Critical for that reason, not because free space is low.";
}

/**
 * Flash cfg path.
 */
function sg_cfg_path() {
    return '/boot/config/plugins/StorageGuard/StorageGuard.cfg';
}

/**
 * Build machine-aware product defaults (Yes toggles, outline OK, no pulse,
 * Array/Pool thresholds = largest warn / smallest crit when disks exist).
 *
 * @return array<string,string>
 */
function sg_product_defaults_map() {
    $out = [
        'sg_defaults' => '1',
        'array_coloring' => 'no',
        'pool_coloring' => 'yes',
        'array_color_style' => 'outline',
        'outline_pulse' => 'no',
        'outline_show_ok' => 'yes',
        'array_warning' => '',
        'array_critical' => '',
        'array_use_custom' => 'no',
        'array_warning_custom' => '',
        'array_critical_custom' => '',
        'pools_to_color' => 'all',
        'alerts_array_warning' => 'no',
        'alerts_array_critical' => 'no',
        'btrfs_hints_enabled' => 'yes',
    ];

    $arr_labels = [];
    if (function_exists('sg_array_data_disk_size_kbs')) {
        $kbs = sg_array_data_disk_size_kbs();
        if (is_array($kbs) && $kbs) {
            rsort($kbs, SORT_NUMERIC);
            foreach ($kbs as $kb) {
                $arr_labels[] = sg_format_size_kb($kb);
            }
            $arr_labels = array_values(array_unique($arr_labels));
        }
    }
    if ($arr_labels) {
        $out['array_warning'] = $arr_labels[0];
        $out['array_critical'] = $arr_labels[count($arr_labels) - 1];
        $out['array_coloring'] = 'yes';
        $out['alerts_array_warning'] = 'yes';
        $out['alerts_array_critical'] = 'yes';
    }

    // Pools: same largest/smallest member model; alerts Yes
    $disks_ini = '/var/local/emhttp/disks.ini';
    if (is_file($disks_ini)) {
        $disks = @parse_ini_file($disks_ini, true) ?: [];
        $by_pool = [];
        foreach ($disks as $key => $d) {
            if (!is_array($d) || empty($d['device'])) {
                continue;
            }
            if (($d['type'] ?? '') !== 'Cache') {
                continue;
            }
            $status = (string)($d['status'] ?? '');
            if (strpos($status, '_NP') !== false) {
                continue;
            }
            $pname = preg_replace('/\d+$/', '', (string)$key);
            if ($pname === '' || $pname === 'flash') {
                continue;
            }
            $sz = sg_disk_capacity_kb($d);
            if ($sz > 0) {
                $by_pool[$pname][] = $sz;
            }
        }
        foreach ($by_pool as $pname => $raws) {
            rsort($raws, SORT_NUMERIC);
            $labels = [];
            foreach ($raws as $kb) {
                $labels[] = sg_format_size_kb($kb);
            }
            $labels = array_values(array_unique($labels));
            if (!$labels) {
                continue;
            }
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $pname);
            $out["pool_{$safe}_use_custom"] = 'no';
            $out["pool_{$safe}_warning"] = $labels[0];
            $out["pool_{$safe}_critical"] = $labels[count($labels) - 1];
            $out["pool_{$safe}_warning_custom"] = '';
            $out["pool_{$safe}_critical_custom"] = '';
            $out["pool_{$safe}_color_style"] = 'outline';
            $out["alerts_pool_{$safe}_warning"] = 'yes';
            $out["alerts_pool_{$safe}_critical"] = 'yes';
        }
    }

    return $out;
}

/**
 * Write product defaults to flash cfg.
 *
 * @param bool $fresh true = replace with product map; false = fill only missing/empty threshold keys
 * @return bool
 */
function sg_seed_product_cfg($fresh = true) {
    $path = sg_cfg_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $want = sg_product_defaults_map();
    $cur = [];
    if (!$fresh && is_readable($path)) {
        $parsed = @parse_ini_file($path);
        if (is_array($parsed)) {
            $cur = $parsed;
        }
    }
    if ($fresh || !$cur) {
        $merged = $want;
    } else {
        $merged = $cur;
        foreach ($want as $k => $v) {
            if (!array_key_exists($k, $merged)) {
                $merged[$k] = $v;
                continue;
            }
            // Heal empty disk-size thresholds when we have a product value
            if (($merged[$k] === '' || $merged[$k] === null)
                && $v !== ''
                && preg_match('/^(array_|pool_).*(warning|critical)$/', $k)
                && strpos($k, '_custom') === false) {
                $merged[$k] = $v;
            }
        }
        // Always refresh product Yes/outline defaults when healing an unseeded or partial cfg
        if (($merged['sg_defaults'] ?? '') !== '1') {
            foreach (['array_coloring', 'pool_coloring', 'outline_pulse', 'outline_show_ok',
                'alerts_array_warning', 'alerts_array_critical', 'array_color_style', 'pools_to_color',
                'btrfs_hints_enabled'] as $k) {
                if (isset($want[$k])) {
                    $merged[$k] = $want[$k];
                }
            }
            foreach ($want as $k => $v) {
                if (strpos($k, 'alerts_pool_') === 0) {
                    $merged[$k] = $v;
                }
            }
            $merged['sg_defaults'] = '1';
        }
    }

    $lines = ['; Storage Guard — managed by plugin', ''];
    foreach ($merged as $k => $v) {
        if (!is_string($k) || !preg_match('/^[A-Za-z0-9_]+$/', $k)) {
            continue;
        }
        $lines[] = $k . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$v) . '"';
    }
    return @file_put_contents($path, implode("\n", $lines) . "\n") !== false;
}
