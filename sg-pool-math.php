<?php
/**
 * BTRFS pool capacity / recovery-headroom math (estimates).
 * See docs/math/ for formulas. Speeds are bus-capped ceilings for profile comparison only.
 */

if (!function_exists('sg_pool_profile_class')) {
    require_once __DIR__ . '/sg-lib.php';
}

/** @return float[] member sizes in TB (decimal, from Unraid KB size) */
function sg_pool_member_sizes_tb($pool) {
    $pool = preg_replace('/\d+$/', '', (string)$pool);
    $disks_ini = '/var/local/emhttp/disks.ini';
    if (!is_file($disks_ini)) return [];
    $disks = @parse_ini_file($disks_ini, true) ?: [];
    $out = [];
    foreach ($disks as $key => $d) {
        if (($d['type'] ?? '') !== 'Cache') continue;
        if (empty($d['device'])) continue;
        $status = $d['status'] ?? '';
        if (strpos($status, '_NP') !== false) continue;
        $prefix = preg_replace('/\d+$/', '', $key);
        if ($prefix !== $pool) continue;
        $kb = isset($d['size']) ? (int)$d['size'] : 0;
        if ($kb <= 0) continue;
        // Unraid size is KiB; present as decimal TB for human pool math
        $out[] = ($kb * 1024.0) / 1e12;
    }
    rsort($out, SORT_NUMERIC);
    return $out;
}

/** Format TB float as short label for Settings custom fields (e.g. 2T, 4.5T, 500G). */
function sg_format_tb_label($tb) {
    $tb = (float)$tb;
    if ($tb <= 0) return '';
    if ($tb >= 1) {
        $v = round($tb, 1);
        return rtrim(rtrim(sprintf('%.1f', $v), '0'), '.') . 'T';
    }
    $g = round($tb * 1000.0, 0);
    if ($g >= 1) return (string)$g . 'G';
    $m = round($tb * 1e6, 0);
    return (string)$m . 'M';
}

function sg_math_sum($sizes) {
    $s = 0.0;
    foreach ($sizes as $x) $s += (float)$x;
    return $s;
}

function sg_math_max($sizes) {
    $m = 0.0;
    foreach ($sizes as $x) {
        $x = (float)$x;
        if ($x > $m) $m = $x;
    }
    return $m;
}

/**
 * Normalize profile string to a math key.
 * @return string single|raid0|raid1|raid1c3|raid1c4|raid10|raid5|raid6|unknown
 */
function sg_math_profile_key($profile) {
    $p = strtolower(trim((string)$profile));
    if ($p === '' || $p === 'unknown') return 'unknown';
    if (strpos($p, 'raid10') !== false) return 'raid10';
    if (preg_match('/raid1c3/', $p)) return 'raid1c3';
    if (preg_match('/raid1c4/', $p)) return 'raid1c4';
    if (preg_match('/\braid1\b/', $p) || strpos($p, 'dup') !== false) return 'raid1';
    if (strpos($p, 'raid5') !== false) return 'raid5';
    if (strpos($p, 'raid6') !== false) return 'raid6';
    if (strpos($p, 'raid0') !== false) return 'raid0';
    if (strpos($p, 'single') !== false) return 'single';
    return 'unknown';
}

/**
 * Estimated usable capacity (TB) for a BTRFS data profile and member sizes.
 * First-order estimates; ignore metadata. Mixed-size models are simplified.
 *
 * @param string $profile_or_key raw btrfs profile or math key
 * @param float[] $sizes_tb
 */
function sg_usable_tb($profile_or_key, $sizes_tb) {
    $sizes = [];
    foreach ($sizes_tb as $x) {
        $x = (float)$x;
        if ($x > 0) $sizes[] = $x;
    }
    $n = count($sizes);
    if ($n === 0) return 0.0;

    $key = sg_math_profile_key($profile_or_key);
    // If already a math key, use directly
    $known = ['single','raid0','raid1','raid1c3','raid1c4','raid10','raid5','raid6','unknown'];
    if (in_array(strtolower((string)$profile_or_key), $known, true)) {
        $key = strtolower((string)$profile_or_key);
    }

    $sum = sg_math_sum($sizes);
    $max = sg_math_max($sizes);

    switch ($key) {
        case 'single':
        case 'raid0':
            return $sum;
        case 'raid1':
            // BTRFS RAID1: exactly two copies on different devices (any N). ≈ half raw.
            return $sum / 2.0;
        case 'raid1c3':
            return $sum / 3.0;
        case 'raid1c4':
            return $sum / 4.0;
        case 'raid10':
            // BTRFS RAID10: two copies + striping (not fixed mirror pairs). ≈ half raw.
            if ($n < 2) return 0.0;
            return $sum / 2.0;
        case 'raid5':
            // One parity: classic sum − largest (equal disks: (n−1)×S).
            if ($n < 2) return 0.0;
            return max(0.0, $sum - $max);
        case 'raid6':
            // Two parity: equal-disk (n−2)×S; mixed first-order sum − 2×largest.
            if ($n < 3) return 0.0;
            return max(0.0, $sum - 2.0 * $max);
        default:
            return 0.0;
    }
}

/**
 * Free space needed so used data still fits after losing disk at index $i (same profile).
 * Δ = U_full − U_without_i  (0 if capacity does not shrink)
 *
 * @param float[] $sizes_tb
 * @return float
 */
function sg_capacity_delta_tb($profile_or_key, $sizes_tb, $i) {
    $sizes = array_values(array_map('floatval', $sizes_tb));
    if (!isset($sizes[$i])) return 0.0;
    $u0 = sg_usable_tb($profile_or_key, $sizes);
    $rest = $sizes;
    array_splice($rest, $i, 1);
    $u1 = sg_usable_tb($profile_or_key, $rest);
    $d = $u0 - $u1;
    return $d > 0 ? $d : 0.0;
}

/**
 * Per-disk and warn/crit free suggestions for staying on the same profile after one loss.
 *
 * Product rule (docs/math/scenarios.md):
 *   Critical = max(Δ_fit)  — capacity still fits after worst single-disk loss
 *   Warning  = 2 × max(Δ_fit) — fit + first-order rebalance comfort
 *
 * Applies to mirror (RAID1/1c3/1c4), striped_mirror (RAID10), and parity (RAID5/6).
 * Does not apply to single/RAID0 (no recovery model) or unknown.
 * Not Unraid-array "evacuate largest disk" semantics.
 *
 * @return array{
 *   profile_key:string, class:string, usable_tb:float,
 *   warn_tb:float, crit_tb:float, fit_free_tb:float, rebalance_free_tb:float,
 *   apply:bool, rule:string,
 *   losses: list<array{size_tb:float, usable_after_tb:float, delta_tb:float}>
 * }
 */
function sg_pool_threshold_suggestions($profile, $sizes_tb) {
    $key = sg_math_profile_key($profile);
    $class = function_exists('sg_pool_profile_class')
        ? sg_pool_profile_class($profile)
        : 'unknown';
    $sizes = [];
    foreach ($sizes_tb as $x) {
        $x = (float)$x;
        if ($x > 0) $sizes[] = $x;
    }
    $n = count($sizes);
    $u0 = sg_usable_tb($key, $sizes);
    $losses = [];
    $deltas = [];
    foreach ($sizes as $i => $sz) {
        $delta = sg_capacity_delta_tb($key, $sizes, $i);
        $rest = $sizes;
        array_splice($rest, $i, 1);
        $losses[] = [
            'size_tb' => round($sz, 3),
            'usable_after_tb' => round(sg_usable_tb($key, $rest), 3),
            'delta_tb' => round($delta, 3),
        ];
        $deltas[] = $delta;
    }

    $max_delta = !empty($deltas) ? max($deltas) : 0.0;
    $min_delta = !empty($deltas) ? min($deltas) : 0.0;
    // Suggest for multi-device profiles where one loss shrinks U but data can remain online
    $apply = (
        $n >= 2
        && $max_delta > 0.0
        && $key !== 'unknown'
        && $class !== 'none'
        && in_array($class, ['mirror', 'striped_mirror', 'parity'], true)
    );
    // Warning free amount > Critical free amount (alert earlier as free shrinks)
    $crit = $max_delta;
    $warn = 2.0 * $max_delta;

    return [
        'profile_key' => $key,
        'class' => $class,
        'usable_tb' => round($u0, 3),
        'warn_tb' => $apply ? round($warn, 3) : 0.0,
        'crit_tb' => $apply ? round($crit, 3) : 0.0,
        'fit_free_tb' => round($max_delta, 3),
        'rebalance_free_tb' => round(2.0 * $max_delta, 3),
        'mildest_delta_tb' => round($min_delta, 3),
        'apply' => $apply,
        'rule' => 'crit=max_fit_delta; warn=2*max_fit_delta',
        'losses' => $losses,
    ];
}

/**
 * Alternate profiles: usable capacity on full set and after worst single loss.
 * Speeds are optional bus-ceiling based estimates for comparison only.
 *
 * @param float[] $sizes_tb
 * @param float|null $read_mbs_ceiling single-disk-equivalent sequential read ceiling
 * @param float|null $write_mbs_ceiling single-disk-equivalent sequential write ceiling
 */
function sg_pool_profile_alternatives($sizes_tb, $read_mbs_ceiling = null, $write_mbs_ceiling = null) {
    $sizes = [];
    foreach ($sizes_tb as $x) {
        $x = (float)$x;
        if ($x > 0) $sizes[] = $x;
    }
    $n = count($sizes);
    $profiles = ['raid10', 'raid1', 'raid1c3', 'raid1c4', 'raid5', 'raid6', 'raid0', 'single'];
    $worst_i = 0;
    $max_sz = -1.0;
    foreach ($sizes as $i => $sz) {
        if ($sz > $max_sz) {
            $max_sz = $sz;
            $worst_i = $i;
        }
    }
    $rest = $sizes;
    if ($n > 0) array_splice($rest, $worst_i, 1);

    $out = [];
    foreach ($profiles as $pk) {
        $u_full = sg_usable_tb($pk, $sizes);
        $u_after = $n > 1 ? sg_usable_tb($pk, $rest) : 0.0;
        $row = [
            'profile' => $pk,
            'usable_tb' => round($u_full, 3),
            'usable_after_worst_loss_tb' => round($u_after, 3),
            'delta_worst_tb' => round(max(0.0, $u_full - $u_after), 3),
            'min_devices' => sg_math_min_devices($pk),
            'viable_full' => $n >= sg_math_min_devices($pk) && $u_full > 0,
            'viable_after_worst' => count($rest) >= sg_math_min_devices($pk) && $u_after > 0,
        ];
        if ($read_mbs_ceiling !== null && $write_mbs_ceiling !== null && $n > 0) {
            $spd = sg_pool_profile_speed_ceiling($pk, $n, (float)$read_mbs_ceiling, (float)$write_mbs_ceiling);
            $row['read_mbs_ceiling'] = $spd['read_mbs'];
            $row['write_mbs_ceiling'] = $spd['write_mbs'];
            $spd_after = sg_pool_profile_speed_ceiling($pk, max(0, $n - 1), (float)$read_mbs_ceiling, (float)$write_mbs_ceiling);
            $row['read_mbs_ceiling_after_worst'] = $spd_after['read_mbs'];
            $row['write_mbs_ceiling_after_worst'] = $spd_after['write_mbs'];
        }
        $out[] = $row;
    }
    return $out;
}

function sg_math_min_devices($profile_key) {
    switch (sg_math_profile_key($profile_key)) {
        case 'raid6': return 3;
        case 'raid5': return 2;
        case 'raid10': return 2;
        case 'raid1c4': return 4;
        case 'raid1c3': return 3;
        case 'raid1': return 2;
        default: return 1;
    }
}

/**
 * Rough pool sequential ceilings from a single-disk-equivalent R/W ceiling and device count.
 * These are absolute best-case hardware-path limits for comparing profiles — not measured disk speeds.
 *
 * @return array{read_mbs:float, write_mbs:float}
 */
function sg_pool_profile_speed_ceiling($profile_key, $n, $disk_read_mbs, $disk_write_mbs) {
    $n = max(0, (int)$n);
    $r = max(0.0, (float)$disk_read_mbs);
    $w = max(0.0, (float)$disk_write_mbs);
    $key = sg_math_profile_key($profile_key);
    if ($n <= 0 || ($r <= 0 && $w <= 0)) {
        return ['read_mbs' => 0.0, 'write_mbs' => 0.0];
    }
    switch ($key) {
        case 'raid0':
        case 'single':
            return ['read_mbs' => round($n * $r, 0), 'write_mbs' => round($n * $w, 0)];
        case 'raid1':
        case 'raid1c3':
        case 'raid1c4':
            // Copies: read can fan out; write limited by one stream class (all mirrors)
            return ['read_mbs' => round($n * $r, 0), 'write_mbs' => round($w, 0)];
        case 'raid10':
            // Stripe of mirrors: ~N read, ~N/2 write
            return ['read_mbs' => round($n * $r, 0), 'write_mbs' => round(($n / 2.0) * $w, 0)];
        case 'raid5':
            // Simplified: read ~N, write much lower (parity); ~N/4 write class if N>=3 else lower
            $ww = $n >= 3 ? ($n / 4.0) * $w : 0.5 * $w;
            return ['read_mbs' => round($n * $r, 0), 'write_mbs' => round($ww, 0)];
        case 'raid6':
            $ww = $n >= 4 ? ($n / 6.0) * $w : 0.4 * $w;
            return ['read_mbs' => round($n * $r, 0), 'write_mbs' => round($ww, 0)];
        default:
            return ['read_mbs' => 0.0, 'write_mbs' => 0.0];
    }
}

/**
 * Bus-only sequential ceiling for a block device (MB/s). Best-case path limit, not disk media.
 * @param string $dev e.g. sda, nvme0n1
 */
function sg_device_bus_ceiling_mbs($dev) {
    $dev = preg_replace('/[^a-zA-Z0-9_\\-]/', '', (string)$dev);
    if ($dev === '') return null;

    // NVMe PCIe
    if (preg_match('/^(nvme\\d+)n\\d+$/', $dev, $m)) {
        $nvme = $m[1];
        $base = '/sys/class/nvme/' . $nvme . '/device';
        $speed = @file_get_contents($base . '/current_link_speed');
        $width = @file_get_contents($base . '/current_link_width');
        if ($speed === false) {
            // walk parents
            $real = @realpath('/sys/class/nvme/' . $nvme . '/device');
            if ($real) {
                $p = $real;
                for ($i = 0; $i < 8; $i++) {
                    if (is_file($p . '/current_link_speed')) {
                        $speed = @file_get_contents($p . '/current_link_speed');
                        $width = @file_get_contents($p . '/current_link_width');
                        break;
                    }
                    $p = dirname($p);
                }
            }
        }
        $gt = 0.0;
        if ($speed && preg_match('/([0-9.]+)\\s*GT/i', $speed, $sm)) $gt = (float)$sm[1];
        $lanes = $width !== false && $width !== null ? (int)trim((string)$width) : 0;
        if ($gt > 0 && $lanes > 0) {
            // PCIe payload rough: GT/s * lanes * encoding efficiency ≈ 0.985 for gen3+, bytes/transfer≈1
            // Gen3 8GT ≈ 1 GB/s per lane; Gen4 16GT ≈ 2 GB/s per lane (rough)
            $per_lane_mbs = ($gt / 8.0) * 1000.0; // very rough
            if ($gt >= 15) $per_lane_mbs = 2000.0;      // gen4-ish
            elseif ($gt >= 7) $per_lane_mbs = 1000.0;   // gen3-ish
            elseif ($gt >= 4) $per_lane_mbs = 500.0;    // gen2-ish
            return round($per_lane_mbs * $lanes, 0);
        }
        return 2000.0; // unknown NVMe fallback ceiling class
    }

    // SATA via smartctl current speed
    $out = @shell_exec('smartctl -i ' . escapeshellarg('/dev/' . $dev) . ' 2>/dev/null');
    if ($out && preg_match('/SATA Version.*current:\\s*([0-9.]+)\\s*Gb/i', $out, $m)) {
        $gbps = (float)$m[1];
        // 8b/10b-ish usable ~100 MB/s per Gb/s raw bit rate / 10 * 8... practical:
        if ($gbps >= 6) return 550.0;
        if ($gbps >= 3) return 280.0;
        if ($gbps >= 1.5) return 140.0;
        return 100.0;
    }
    // sysfs ata_link (best effort)
    // default SATA3 class if rotational disk and sata
    return null;
}

/**
 * Representative single-disk ceiling for a pool: min positive bus ceiling among members, else null.
 * @param string $pool
 * @return array{read_mbs:?float, write_mbs:?float, note:string}
 */
function sg_pool_bus_ceiling_estimate($pool) {
    $disks_ini = '/var/local/emhttp/disks.ini';
    $pool = preg_replace('/\\d+$/', '', (string)$pool);
    $caps = [];
    if (is_file($disks_ini)) {
        $disks = @parse_ini_file($disks_ini, true) ?: [];
        foreach ($disks as $key => $d) {
            if (($d['type'] ?? '') !== 'Cache') continue;
            if (empty($d['device'])) continue;
            $prefix = preg_replace('/\\d+$/', '', $key);
            if ($prefix !== $pool) continue;
            $dev = $d['device']; // e.g. sdb or nvme0n1
            $dev = preg_replace('#^/dev/#', '', $dev);
            $c = sg_device_bus_ceiling_mbs($dev);
            if ($c !== null && $c > 0) $caps[] = $c;
        }
    }
    if (empty($caps)) {
        return [
            'read_mbs' => null,
            'write_mbs' => null,
            'note' => 'Absolute best-case bus ceilings unavailable; capacity math still applies. Speed columns omitted.',
        ];
    }
    $min = min($caps);
    return [
        'read_mbs' => $min,
        'write_mbs' => $min,
        'note' => 'Absolute best-case scenario from hardware path findings (bus/link ceilings). '
            . 'These figures help compare BTRFS profiles on this pool; they are not measured disk sequential speeds '
            . 'and real workloads will be lower.',
    ];
}

/**
 * Full math package for one pool (for get-config / future Settings suggest UI).
 */
function sg_pool_math_package($pool, $profile = null) {
    if ($profile === null || $profile === '') {
        $profile = function_exists('sg_pool_btrfs_profile') ? sg_pool_btrfs_profile($pool) : '';
    }
    $sizes = sg_pool_member_sizes_tb($pool);
    $suggest = sg_pool_threshold_suggestions($profile, $sizes);
    $bus = sg_pool_bus_ceiling_estimate($pool);
    $alts = sg_pool_profile_alternatives(
        $sizes,
        $bus['read_mbs'],
        $bus['write_mbs']
    );
    return [
        'pool' => $pool,
        'profile' => $profile,
        'members_tb' => array_map(function ($x) { return round($x, 3); }, $sizes),
        'suggest' => $suggest,
        'bus' => $bus,
        'alternatives' => $alts,
    ];
}
