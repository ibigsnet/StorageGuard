<?php
/**
 * Storage Guard shared library: config, disk inventory, thresholds, and the
 * evaluator used by both Main paint (get-config.php) and alerts (include/sg-alerts.php).
 */

/** Runtime paths. Tests may override entries through $GLOBALS['SG_PATHS']. */
function sg_path(string $key): string {
    static $defaults = [
        'cfg' => '/boot/config/plugins/StorageGuard/StorageGuard.cfg',
        'state' => '/boot/config/plugins/StorageGuard/alert-state.json',
        'old_state_dir' => '/tmp/storageguard_alerts',
        'lock' => '/var/run/storageguard-alerts.lock',
        'disks' => '/var/local/emhttp/disks.ini',
        'var' => '/var/local/emhttp/var.ini',
        'mnt' => '/mnt',
        'plugin' => '/usr/local/emhttp/plugins/StorageGuard',
        'has_array_flag' => '/var/tmp/storageguard-has-array',
        'notify' => '/usr/local/emhttp/webGui/scripts/notify',
    ];
    $over = $GLOBALS['SG_PATHS'] ?? [];
    return (string)($over[$key] ?? $defaults[$key]);
}

/**
 * Read an ini file. $raw keeps unquoted yes/no as strings (our cfg); Unraid's own
 * state files (disks.ini, var.ini) are read in normal mode, as Unraid does.
 */
function sg_read_ini(string $file, bool $sections = false, bool $raw = true): array {
    if (!is_file($file)) {
        return [];
    }
    $parsed = @parse_ini_file($file, $sections, $raw ? INI_SCANNER_RAW : INI_SCANNER_NORMAL);
    return is_array($parsed) ? $parsed : [];
}

function sg_load_cfg(): array {
    return sg_read_ini(sg_path('cfg'));
}

/** disks.ini, parsed once per request. */
function sg_disks(bool $reset = false): array {
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $cache = sg_read_ini(sg_path('disks'), true, false);
    }
    return $cache;
}

/** var.ini, parsed once per request. */
function sg_var(bool $reset = false): array {
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $cache = sg_read_ini(sg_path('var'), false, false);
    }
    return $cache;
}

/* ------------------------------------------------------------------ sizes */

/**
 * Disk capacity in KiB, as Unraid Main shows it: BTRFS used+free when mounted,
 * else fsSize, else disks.ini size, else sectors x sector size.
 */
function sg_disk_capacity_kb($d): int {
    if (!is_array($d)) {
        return 0;
    }
    $fsType = str_replace('luks:', '', (string)($d['fsType'] ?? ''));
    $used = isset($d['fsUsed']) && is_numeric($d['fsUsed']) ? (int)$d['fsUsed'] : null;
    $free = isset($d['fsFree']) && is_numeric($d['fsFree']) ? (int)$d['fsFree'] : null;
    if (strcasecmp($fsType, 'btrfs') === 0 && $used !== null && $free !== null && $used + $free > 0) {
        return $used + $free;
    }
    foreach (['fsSize', 'size'] as $k) {
        if (isset($d[$k]) && (int)$d[$k] > 0) {
            return (int)$d[$k];
        }
    }
    $sectors = (float)($d['sectors'] ?? 0);
    $secSz = (float)($d['sector_size'] ?? 0);
    if ($sectors > 0 && $secSz > 0) {
        return (int)floor(($sectors * $secSz) / 1024.0);
    }
    return 0;
}

/**
 * KiB as Unraid Main's Size column (my_scale, kilo=1000, decimals=-1), short suffix: 25.9T, 500G.
 */
function sg_format_size_kb($kb): string {
    if (!$kb || $kb <= 0) {
        return '0';
    }
    $value = (float)$kb * 1024.0;
    $units = ['', 'K', 'M', 'G', 'T', 'P', 'E'];
    $max = count($units) - 1;
    $base = max(0, min($max, (int)floor(log($value, 1000.0))));
    $value /= pow(1000.0, $base);
    $decimals = ($value >= 100 || ((int)round($value * 10) % 10) === 0) ? 0 : 1;
    if (round($value, -1) == 1000 && $base < $max) {
        $value = 1;
        $base++;
        $decimals = 0;
    }
    $formatted = number_format($value, $decimals, '.', '');
    if (strpos($formatted, '.') !== false) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    return $formatted . $units[$base];
}

/** Pre-2026.08.16 binary (1024^n) label. Only used to migrate saved disk-size values. */
function sg_format_size_kb_legacy_binary($kb): string {
    if (!$kb || $kb <= 0) {
        return '0';
    }
    $bytes = (float)$kb * 1024.0;
    foreach ([['T', 4], ['G', 3]] as [$u, $p]) {
        $div = pow(1024.0, $p);
        if ($bytes >= $div) {
            return rtrim(rtrim(sprintf('%.1f', round($bytes / $div, 1)), '0'), '.') . $u;
        }
    }
    if ($bytes >= 1048576.0) {
        return (string)round($bytes / 1048576.0) . 'M';
    }
    return (string)round($bytes / 1024.0) . 'K';
}

/** Map a saved legacy binary disk-size label to the SI label of the same live disk. */
function sg_migrate_disk_size_label($label, array $size_kbs): string {
    $label = trim((string)$label);
    if ($label === '' || $label === '0' || !$size_kbs) {
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
        if ($bin !== $si) {
            $bin_to_si[$bin] = $si;
        }
    }
    if (isset($si_set[$label])) {
        return $label;
    }
    return $bin_to_si[$label] ?? $label;
}

/** Free-space strings like 3.6T / 500G to decimal TB. Empty or unparsable = 0 (None). */
function sg_parse_to_tb($str): float {
    if ($str === null || $str === '') {
        return 0.0;
    }
    if (!preg_match('/([0-9]*\.?[0-9]+)\s*([TGMKtgmk]?)/', (string)$str, $m)) {
        return 0.0;
    }
    $num = (float)$m[1];
    switch (strtoupper($m[2] ?: 'T')) {
        case 'G': return $num / 1e3;
        case 'M': return $num / 1e6;
        case 'K': return $num / 1e9;
        default: return $num;
    }
}

function sg_kb_to_tb($kb): float {
    return $kb > 0 ? ((float)$kb * 1024.0) / 1e12 : 0.0;
}

function sg_human_free($tb): string {
    $tb = (float)$tb;
    if ($tb >= 1) {
        return rtrim(rtrim(sprintf('%.1f', round($tb, 1)), '0'), '.') . 'T';
    }
    return round($tb * 1000, 0) . 'G';
}

/** 1.99 -> "1.99T", 0.18 -> "180G". */
function sg_format_tb_short($tb): string {
    $tb = (float)$tb;
    if ($tb <= 0) {
        return '';
    }
    if ($tb >= 1.0) {
        return rtrim(rtrim(number_format($tb, 2, '.', ''), '0'), '.') . 'T';
    }
    return number_format($tb * 1000.0, 0, '.', '') . 'G';
}

/* ------------------------------------------------------------- inventory */

function sg_is_data_disk(string $key, array $d): bool {
    $name = (string)($d['name'] ?? $key);
    return ($d['type'] ?? '') === 'Data' || preg_match('/^disk\d+$/', $name) || preg_match('/^disk\d+$/', $key);
}

/** Pool name from a disks.ini Cache slot key (cache2 -> cache). */
function sg_pool_key_prefix(string $key): string {
    return (string)preg_replace('/\d+$/', '', $key);
}

/** @return list<array{name:string,device:string,label:string,tb:float,kb:int}> */
function sg_array_data_disks(): array {
    $out = [];
    foreach (sg_disks() as $key => $d) {
        if (!is_array($d) || empty($d['device']) || !sg_is_data_disk((string)$key, $d)) {
            continue;
        }
        $kb = sg_disk_capacity_kb($d);
        if ($kb <= 0) {
            continue;
        }
        $out[] = [
            'name' => (string)($d['name'] ?? $key),
            'device' => (string)preg_replace('#^/dev/#', '', trim((string)$d['device'])),
            'label' => sg_format_size_kb($kb),
            'tb' => sg_kb_to_tb($kb),
            'kb' => $kb,
        ];
    }
    return $out;
}

/** @return int[] array data-disk sizes (KiB) */
function sg_array_data_disk_size_kbs(): array {
    return array_column(sg_array_data_disks(), 'kb');
}

function sg_array_present(): bool {
    return (bool)sg_array_data_disks();
}

/** Present pool members of one pool, as disks.ini sections keyed by slot. */
function sg_pool_members(string $pool): array {
    $out = [];
    foreach (sg_disks() as $key => $d) {
        if (!is_array($d) || ($d['type'] ?? '') !== 'Cache' || empty($d['device'])) {
            continue;
        }
        if (strpos((string)($d['status'] ?? ''), '_NP') !== false) {
            continue;
        }
        if (sg_pool_key_prefix((string)$key) !== $pool || sg_disk_capacity_kb($d) <= 0) {
            continue;
        }
        $out[(string)$key] = $d;
    }
    return $out;
}

/** @return int[] pool member sizes (KiB) */
function sg_pool_member_size_kbs(string $pool): array {
    return array_values(array_map('sg_disk_capacity_kb', sg_pool_members($pool)));
}

function sg_pool_member_count(string $pool): int {
    return count(sg_pool_members($pool));
}

/** File system of the pool (first member), e.g. btrfs, xfs, zfs. */
function sg_pool_fs_type(string $pool): string {
    foreach (sg_pool_members($pool) as $d) {
        $fs = strtolower(str_replace('luks:', '', (string)($d['fsType'] ?? '')));
        if ($fs !== '') {
            return $fs;
        }
    }
    return '';
}

/** Live pool names (Cache slots with a device, not missing, not flash). */
function sg_list_pool_names(): array {
    $names = [];
    foreach (sg_disks() as $key => $d) {
        if (!is_array($d) || ($d['type'] ?? '') !== 'Cache' || empty($d['device'])) {
            continue;
        }
        if (strpos((string)($d['status'] ?? ''), '_NP') !== false || sg_disk_capacity_kb($d) <= 0) {
            continue;
        }
        $pname = sg_pool_key_prefix((string)$key);
        if ($pname !== '' && $pname !== 'flash') {
            $names[$pname] = true;
        }
    }
    $out = array_keys($names);
    natcasesort($out);
    return array_values($out);
}

/** Mount path of a pool, from its real (unsanitized) name. */
function sg_pool_mount(string $pool): string {
    return sg_path('mnt') . '/' . $pool;
}

/** Config key part for a pool name (cache-ssd -> cache_ssd). */
function sg_pool_page_safe($pname): string {
    return (string)preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$pname);
}

/** Display name: cache -> Cache. */
function sg_pool_title($name): string {
    $name = (string)$name;
    return $name === '' ? $name : strtoupper($name[0]) . substr($name, 1);
}

/**
 * True when Unraid storage is fully up: fsState=Started, mdState=STARTED (when set),
 * and not Maintenance. Otherwise mounts are missing and free space must not read as 0.
 */
function sg_storage_online(): bool {
    $var = sg_var();
    if (!$var) {
        return false;
    }
    if (strcasecmp(trim((string)($var['fsState'] ?? '')), 'Started') !== 0) {
        return false;
    }
    $md = trim((string)($var['mdState'] ?? ''));
    if ($md !== '' && strcasecmp($md, 'STARTED') !== 0) {
        return false;
    }
    return strcasecmp(trim((string)($var['startMode'] ?? 'Normal')), 'Maintenance') !== 0;
}

/** Available bytes on a mount as decimal TB, or null when unknown. */
function sg_free_tb(string $mount): ?float {
    if (!is_dir($mount)) {
        return null;
    }
    $out = @shell_exec('df -B1 --output=avail ' . escapeshellarg($mount) . ' 2>/dev/null | tail -1');
    $out = trim((string)$out);
    if ($out === '' || !is_numeric($out) || (float)$out < 0) {
        return null;
    }
    return (float)$out / 1e12;
}

/** Array-only free space (/mnt/user0); /mnt/user only when user0 does not exist. */
function sg_array_free_tb(): ?float {
    $user0 = sg_path('mnt') . '/user0';
    return is_dir($user0) ? sg_free_tb($user0) : sg_free_tb(sg_path('mnt') . '/user');
}

/* ----------------------------------------------------------- pool layout */

/** BTRFS data profile (e.g. RAID1), '' when not BTRFS or not mounted. Cached per request. */
function sg_pool_btrfs_profile(string $pool): string {
    static $cache = [];
    if (array_key_exists($pool, $cache)) {
        return $cache[$pool];
    }
    $mount = sg_pool_mount($pool);
    $profile = '';
    if (is_dir($mount)) {
        $output = @shell_exec('btrfs filesystem df ' . escapeshellarg($mount) . ' 2>/dev/null');
        if ($output && preg_match('/Data,\s*(\S+):/i', $output, $m)) {
            $profile = rtrim($m[1], ':');
        }
    }
    return $cache[$pool] = $profile;
}

function sg_pool_profile_class($profile): string {
    $p = strtolower(trim((string)$profile));
    if ($p === '' || strpos($p, 'unknown') === 0) {
        return 'unknown';
    }
    if (strpos($p, 'raid10') !== false) {
        return 'striped_mirror';
    }
    if (preg_match('/raid1c[34]/', $p) || preg_match('/\braid1\b/', $p)) {
        return 'mirror';
    }
    if (strpos($p, 'raid5') !== false || strpos($p, 'raid6') !== false) {
        return 'parity';
    }
    // RAID0 / single / DUP: no copy on another disk (DUP is two copies on the same device).
    if (strpos($p, 'raid0') !== false || strpos($p, 'single') !== false || strpos($p, 'dup') !== false) {
        return 'none';
    }
    return 'unknown';
}

/**
 * Can the pool keep data online after losing one whole disk?
 * @return string survives|nonsurvival|unknown
 */
function sg_pool_one_disk_mode($profile, $member_count): string {
    if ((int)$member_count <= 1) {
        return 'nonsurvival';
    }
    $class = sg_pool_profile_class($profile);
    if ($class === 'none') {
        return 'nonsurvival';
    }
    if ($class === 'unknown') {
        return 'unknown';
    }
    return (int)$member_count < sg_math_min_devices(sg_math_profile_key($profile)) ? 'nonsurvival' : 'survives';
}

/**
 * Disk-size thresholds ("free >= largest member") only make sense where a failed member's
 * data must move onto free space. Mirror/RAID10/parity keep it online, and layouts that
 * cannot survive a loss have nowhere to move it: those pools use Custom free amounts only.
 */
function sg_pool_uses_disk_sizes(string $class, string $one_disk, string $fs_type = ''): bool {
    // ZFS layouts are not modelled (a mirror/raidz would read as "free <= member size" forever).
    return $one_disk !== 'nonsurvival' && $fs_type !== 'zfs' && !in_array($class, ['mirror', 'striped_mirror', 'parity'], true);
}

/* ------------------------------------------------------------- thresholds */

/** Severity from free space: lower free floor = critical, higher = warning, regardless of field. */
function sg_level(?float $free_tb, float $warn_tb, float $crit_tb): string {
    [$severe, $mild] = sg_floors($warn_tb, $crit_tb);
    if ($free_tb === null) {
        return 'ok';
    }
    if ($severe !== null && $free_tb <= $severe) {
        return 'critical';
    }
    if ($mild !== null && $free_tb <= $mild) {
        return 'warning';
    }
    return 'ok';
}

/** @return array{0:?float,1:?float} [severe (critical) floor, mild (warning) floor] */
function sg_floors(float $warn_tb, float $crit_tb): array {
    $w = $warn_tb > 0 ? $warn_tb : null;
    $c = $crit_tb > 0 ? $crit_tb : null;
    if ($w === null && $c === null) {
        return [null, null];
    }
    if ($w === null) {
        return [$c, null];
    }
    if ($c === null) {
        return [null, $w];
    }
    return [min($w, $c), max($w, $c)];
}

function sg_threshold_pair(string $wl, string $cl, bool $custom, string $source = ''): array {
    $w = sg_parse_to_tb($wl);
    $c = sg_parse_to_tb($cl);
    return [
        'warn' => $w,
        'crit' => $c,
        'warn_label' => $wl,
        'crit_label' => $cl,
        'custom' => $custom,
        'source' => $source !== '' ? $source : (($w > 0 || $c > 0) ? ($custom ? 'custom' : 'disk_size') : 'none'),
    ];
}

/** Array thresholds: Custom, or saved disk-size labels (missing key = largest / smallest data disk). */
function sg_array_thresholds(array $cfg): array {
    if (($cfg['array_use_custom'] ?? 'no') === 'yes') {
        return sg_threshold_pair((string)($cfg['array_warning_custom'] ?? ''), (string)($cfg['array_critical_custom'] ?? ''), true);
    }
    $kbs = sg_array_data_disk_size_kbs();
    rsort($kbs, SORT_NUMERIC);
    $largest = $kbs ? sg_format_size_kb($kbs[0]) : '';
    $smallest = $kbs ? sg_format_size_kb($kbs[count($kbs) - 1]) : '';
    $wl = array_key_exists('array_warning', $cfg) ? (string)$cfg['array_warning'] : $largest;
    $cl = array_key_exists('array_critical', $cfg) ? (string)$cfg['array_critical'] : $smallest;
    return sg_threshold_pair(sg_migrate_disk_size_label($wl, $kbs), sg_migrate_disk_size_label($cl, $kbs), false);
}

/** Pool thresholds. Saved values always win; empty = None. */
function sg_pool_thresholds(array $cfg, string $pool, string $class, string $one_disk, string $fs_type = ''): array {
    $safe = sg_pool_page_safe($pool);
    $custom = !sg_pool_uses_disk_sizes($class, $one_disk, $fs_type) || ($cfg["pool_{$safe}_use_custom"] ?? 'no') === 'yes';
    if ($custom) {
        return sg_threshold_pair((string)($cfg["pool_{$safe}_warning_custom"] ?? ''), (string)($cfg["pool_{$safe}_critical_custom"] ?? ''), true);
    }
    $kbs = sg_pool_member_size_kbs($pool);
    return sg_threshold_pair(
        sg_migrate_disk_size_label((string)($cfg["pool_{$safe}_warning"] ?? ''), $kbs),
        sg_migrate_disk_size_label((string)($cfg["pool_{$safe}_critical"] ?? ''), $kbs),
        false
    );
}

function sg_style(array $cfg, string $key): string {
    $s = $cfg[$key] ?? ($cfg['color_style'] ?? 'outline');
    return $s === 'solid' ? 'solid' : 'outline';
}

function sg_pool_in_color_list(array $cfg, string $pool): bool {
    if (($cfg['pool_coloring'] ?? 'yes') !== 'yes') {
        return false;
    }
    $list = (string)($cfg['pools_to_color'] ?? 'all');
    if ($list === 'all') {
        return true;
    }
    $items = array_map('trim', explode(',', $list));
    return in_array($pool, $items, true) || in_array(sg_pool_page_safe($pool), $items, true);
}

/* -------------------------------------------------------------- evaluator */

/**
 * One evaluation of every target, shared by Main paint and alerts.
 * Free-space levels here have no hysteresis; alerts add it on top (include/sg-alerts.php).
 */
function sg_evaluate(array $cfg): array {
    $online = sg_storage_online();
    $array_present = sg_array_present();
    $ath = sg_array_thresholds($cfg);
    $afree = ($online && $array_present) ? sg_array_free_tb() : null;
    $array = [
        'present' => $array_present,
        'free_tb' => $afree,
        'th' => $ath,
        'level' => sg_level($afree, $ath['warn'], $ath['crit']),
        'coloring' => $array_present && ($cfg['array_coloring'] ?? 'yes') === 'yes',
        'style' => sg_style($cfg, 'array_color_style'),
        'alerts' => [
            'warning' => ($cfg['alerts_array_warning'] ?? ($array_present ? 'yes' : 'no')) === 'yes',
            'critical' => ($cfg['alerts_array_critical'] ?? ($array_present ? 'yes' : 'no')) === 'yes',
        ],
    ];

    $pools = [];
    foreach (sg_list_pool_names() as $name) {
        $safe = sg_pool_page_safe($name);
        $profile = $online ? sg_pool_btrfs_profile($name) : '';
        $members = sg_pool_member_count($name);
        $class = sg_pool_profile_class($profile);
        $one_disk = sg_pool_one_disk_mode($profile, $members);
        $math = sg_pool_math_package($name, $profile);
        $fs_type = sg_pool_fs_type($name);
        $th = sg_pool_thresholds($cfg, $name, $class, $one_disk, $fs_type);
        $th['suggest'] = $math['suggest'];
        $free = $online ? sg_free_tb(sg_pool_mount($name)) : null;
        $free_level = sg_level($free, $th['warn'], $th['crit']);
        $layout = ($free !== null && $one_disk === 'nonsurvival');
        $pools[$name] = [
            'safe' => $safe,
            'free_tb' => $free,
            'profile' => $profile,
            'class' => $class,
            'members' => $members,
            'one_disk' => $one_disk,
            'fs_type' => $fs_type,
            'th' => $th,
            'math' => $math,
            'free_level' => $free_level,
            // Main paint: a layout that cannot survive a disk loss is always Critical.
            'level' => $layout ? 'critical' : $free_level,
            'level_reason' => $layout ? 'layout' : ($free_level !== 'ok' ? 'free' : 'none'),
            'coloring' => sg_pool_in_color_list($cfg, $name),
            'style' => sg_style($cfg, "pool_{$safe}_color_style"),
            'alerts' => [
                'warning' => ($cfg["alerts_pool_{$safe}_warning"] ?? 'yes') === 'yes',
                'critical' => ($cfg["alerts_pool_{$safe}_critical"] ?? 'yes') === 'yes',
            ],
        ];
    }
    return ['storage_online' => $online, 'array' => $array, 'pools' => $pools];
}

/* ------------------------------------------------------- notification text */

function sg_disks_matching_threshold(string $label, float $tb): array {
    $label = trim($label);
    $out = [];
    foreach (sg_array_data_disks() as $disk) {
        $hit = ($label !== '' && strcasecmp($disk['label'], $label) === 0)
            || ($tb > 0 && $disk['tb'] > 0 && abs($disk['tb'] - $tb) / max($tb, $disk['tb']) <= 0.03);
        if ($hit) {
            $out[$disk['name']] = $disk;
        }
    }
    return array_values($out);
}

/** "disk1 | sdc (26T) or disk2 | sdb (26T)" */
function sg_format_disk_list(array $disks): string {
    $parts = [];
    foreach ($disks as $d) {
        $dev = trim((string)($d['device'] ?? ''));
        $parts[] = ($d['name'] ?? '') . ($dev !== '' ? ' | ' . $dev : '') . ' (' . ($d['label'] ?? '') . ')';
    }
    if (count($parts) <= 2) {
        return implode(' or ', $parts);
    }
    $last = array_pop($parts);
    return implode(', ', $parts) . ', or ' . $last;
}

function sg_array_failure_context(string $label, float $tb, bool $custom): string {
    $L = $label !== '' ? $label : sg_human_free($tb);
    if ($custom) {
        return "your custom free-space threshold of {$L}";
    }
    $disks = sg_disks_matching_threshold($label, $tb);
    if ($disks) {
        return (count($disks) === 1 ? 'data disk ' : 'any of these data disks: ') . sg_format_disk_list($disks);
    }
    return "a data disk of about {$L}";
}

function sg_array_notify_body(string $severity, float $free_tb, array $th): string {
    $is_crit = ($severity === 'critical');
    $label = (string)($is_crit ? $th['crit_label'] : $th['warn_label']);
    $tb = (float)($is_crit ? $th['crit'] : $th['warn']);
    $thresh_h = $label !== '' ? $label : sg_human_free($tb);
    $fail = sg_array_failure_context($label, $tb, !empty($th['custom']));
    $line1 = 'Array free space is ' . sg_human_free($free_tb) . ', at or below your '
        . ($is_crit ? 'critical' : 'warning') . " free-space threshold of {$thresh_h}.";
    $line2 = $is_crit
        ? "If you lost {$fail}, there is likely not enough free space on the rest of the array to move that disk's data off without buying a replacement (or freeing a large amount of space first)."
        : "If you lost {$fail}, there may not be enough free space on the rest of the array to move that disk's data off without buying a replacement.";
    $line3 = 'Parity can keep the array online with an emulated disk; this alert is about evacuation room, not immediate total data loss.';
    return $line1 . ' ' . $line2 . ' ' . $line3;
}

function sg_pool_notify_body(string $severity, string $pname, float $free_tb, array $th, string $profile, string $class): string {
    $is_crit = ($severity === 'critical');
    $label = (string)($is_crit ? $th['crit_label'] : $th['warn_label']);
    $tb = (float)($is_crit ? $th['crit'] : $th['warn']);
    $thresh_h = $label !== '' ? $label : sg_human_free($tb);
    [$severe, $mild] = sg_floors((float)$th['warn'], (float)$th['crit']);
    $prof = $profile !== '' ? $profile : 'unknown';

    $line1 = "Pool '{$pname}' free space is " . sg_human_free($free_tb) . ', at or below your '
        . ($is_crit ? 'critical' : 'warning') . " free-space threshold of {$thresh_h}. Layout: {$prof}."
        . ' Warning/Critical here mean free-space severity (yellow vs red), not which disk already failed.';

    $guide = '';
    $sug = $th['suggest'] ?? null;
    if (is_array($sug) && !empty($sug['apply'])) {
        $lg = sg_format_tb_short((float)$sug['largest_loss_delta_tb']);
        $sm = sg_format_tb_short((float)$sug['smallest_loss_delta_tb']);
        if ($lg !== '' && $sm !== '') {
            $guide = $lg === $sm
                ? " Capacity-fit guide (optional): members are equal size — about {$lg} free so used data still fits after any one member loss."
                : " Capacity-fit guide (optional): ~{$lg} free so used data still fits after losing the largest member; ~{$sm} free after losing the smallest. You can map either number to Warning or Critical (or use your own free amounts).";
        }
    }

    switch ($class) {
        case 'mirror':
            $line2 = 'On BTRFS RAID1/RAID1cN, a single disk loss usually leaves data online (extra copies). Free space is about whether used data still fits after usable capacity shrinks — not Unraid-array “evacuate this disk onto free space.”';
            break;
        case 'parity':
            $line2 = 'On BTRFS RAID5/RAID6, free space is capacity after a loss plus room for replace/remove/rebalance. See Unraid pool docs and BTRFS RAID56 notes for profile behavior.';
            break;
        case 'striped_mirror':
            $line2 = 'On BTRFS RAID10 (two copies + striping), one disk loss usually leaves data online. Free space is whether used data still fits after usable capacity drops, and whether remove/rebalance/convert has room.';
            break;
        case 'none':
            $line2 = 'This pool has no whole-disk redundancy (RAID0, single, or DUP). Free thresholds are capacity policy only; Storage Guard already treats a disk failure as data loss.';
            break;
        default:
            $line2 = 'Check the pool profile on Main/Settings for what a disk failure would mean on this layout.';
    }

    $line3 = $is_crit
        ? ' Critical free means free is at or below your more severe free floor' . ($severe !== null ? ' (' . sg_human_free($severe) . ')' : '')
            . ' — used data may not fit after a capacity-shrinking member loss if you stay this full.'
        : ' Warning free means free is at or below your milder free floor' . ($mild !== null ? ' (' . sg_human_free($mild) . ')' : '')
            . ' — still time to free space or adjust thresholds before the more severe floor.';
    return $line1 . ' ' . $line2 . $guide . $line3;
}

/** Notify body when the pool cannot survive one whole-disk failure (layout, not free space). */
function sg_pool_layout_notify_body(string $pname, string $profile, int $members): string {
    $prof = trim($profile);
    if ($prof === '' || stripos($prof, 'unknown') !== false) {
        $prof = 'unknown';
    }
    $line1 = "Pool '{$pname}' cannot keep data online if any one member disk fails. Layout: {$prof}"
        . ($members > 0 ? ', ' . $members . ' device' . ($members === 1 ? '' : 's') : '') . '.';
    $p = strtolower($prof);
    if (strpos($p, 'raid0') !== false) {
        $line2 = ' BTRFS RAID0 has no copies; losing any disk loses the pool.';
    } elseif (preg_match('/\bsingle\b/', $p)) {
        $line2 = ' BTRFS single stores one copy of each chunk; a failed disk loses the chunks that lived only there.';
    } elseif (strpos($p, 'dup') !== false) {
        $line2 = ' BTRFS DUP keeps two copies on the same device, so a whole-disk failure still loses the data.';
    } elseif ($members <= 1) {
        $line2 = ' A one-disk pool has nowhere else for that data to live.';
    } else {
        $line2 = ' This pool does not have enough devices for the data profile to survive one disk loss.';
    }
    return $line1 . $line2 . ' Main paints this pool Critical for that reason, not because free space is low.';
}

/* ---------------------------------------------------- defaults and seeding */

/**
 * Machine-aware product defaults: Yes toggles, outline + green OK, no pulse,
 * Array/Pool Warning = largest disk, Critical = smallest.
 *
 * @return array<string,string>
 */
function sg_product_defaults_map(): array {
    $out = [
        'sg_defaults' => '1',
        'array_coloring' => 'no',
        'pool_coloring' => 'yes',
        'array_color_style' => 'outline',
        'color_style' => 'outline',
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
    ];
    $labels = sg_size_labels_desc(sg_array_data_disk_size_kbs());
    if ($labels) {
        $out['array_warning'] = $labels[0];
        $out['array_critical'] = $labels[count($labels) - 1];
        $out['array_coloring'] = 'yes';
        $out['alerts_array_warning'] = 'yes';
        $out['alerts_array_critical'] = 'yes';
    }
    foreach (sg_list_pool_names() as $pname) {
        $labels = sg_size_labels_desc(sg_pool_member_size_kbs($pname));
        if (!$labels) {
            continue;
        }
        $safe = sg_pool_page_safe($pname);
        $out["pool_{$safe}_use_custom"] = 'no';
        $out["pool_{$safe}_warning"] = $labels[0];
        $out["pool_{$safe}_critical"] = $labels[count($labels) - 1];
        $out["pool_{$safe}_warning_custom"] = '';
        $out["pool_{$safe}_critical_custom"] = '';
        $out["pool_{$safe}_color_style"] = 'outline';
        $out["alerts_pool_{$safe}_warning"] = 'yes';
        $out["alerts_pool_{$safe}_critical"] = 'yes';
    }
    return $out;
}

/** Unique SI labels, largest first. */
function sg_size_labels_desc(array $kbs): array {
    rsort($kbs, SORT_NUMERIC);
    return array_values(array_unique(array_map('sg_format_size_kb', $kbs)));
}

/** Pre-2026.08 alert keys -> per-target alert keys (only where the new key is missing). */
function sg_migrate_legacy_alert_keys(array $cfg): array {
    if (!isset($cfg['alerts_enabled']) && !isset($cfg['alerts_for'])
        && !isset($cfg['warning_alerts_enabled']) && !isset($cfg['critical_alerts_enabled'])) {
        return $cfg;
    }
    $on = ($cfg['alerts_enabled'] ?? 'yes') === 'yes';
    $for = array_map('trim', explode(',', (string)($cfg['alerts_for'] ?? 'all')));
    $all = in_array('all', $for, true);
    foreach (['warning', 'critical'] as $lvl) {
        $lvl_on = $on && (($cfg["{$lvl}_alerts_enabled"] ?? 'yes') === 'yes');
        if (!isset($cfg["alerts_array_{$lvl}"])) {
            $cfg["alerts_array_{$lvl}"] = ($lvl_on && ($all || in_array('array', $for, true))) ? 'yes' : 'no';
        }
        foreach (sg_list_pool_names() as $p) {
            $k = 'alerts_pool_' . sg_pool_page_safe($p) . "_{$lvl}";
            if (!isset($cfg[$k])) {
                $cfg[$k] = ($lvl_on && ($all || in_array($p, $for, true))) ? 'yes' : 'no';
            }
        }
    }
    return $cfg;
}

/**
 * Write product defaults to the flash cfg.
 *
 * Fresh install: the full product map. Upgrade/boot: add keys that are missing; an empty
 * value is a saved choice (None) and is never refilled. The file is written only when
 * something changed.
 */
function sg_seed_product_cfg(bool $fresh = true): bool {
    $path = sg_path('cfg');
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0755, true);
    }
    $want = sg_product_defaults_map();
    $cur = $fresh ? [] : sg_read_ini($path);
    if (!$cur) {
        $merged = $want;
    } else {
        $merged = sg_migrate_legacy_alert_keys($cur);
        foreach ($want as $k => $v) {
            if (!array_key_exists($k, $merged)) {
                $merged[$k] = $v;
            }
        }
        if ($merged === $cur) {
            return true;
        }
    }
    $lines = ['; Storage Guard — managed by plugin', ''];
    foreach ($merged as $k => $v) {
        if (is_string($k) && preg_match('/^[A-Za-z0-9_]+$/', $k)) {
            $lines[] = $k . '="' . str_replace(['"', "\n", "\r"], '', (string)$v) . '"';
        }
    }
    return @file_put_contents($path, implode("\n", $lines) . "\n") !== false;
}

/* ------------------------------------------------------------ pool pages */

/**
 * Write SGPool_<name>.page tabs for each live pool (runtime only, RAM) and the
 * has-array flag used by the Array tab's Cond.
 */
function sg_sync_pool_pages(): void {
    $root = sg_path('plugin');
    if (!is_dir($root)) {
        return;
    }
    $flag = sg_path('has_array_flag');
    if (sg_array_present()) {
        if (!is_file($flag)) {
            @file_put_contents($flag, "1\n");
        }
    } elseif (is_file($flag)) {
        @unlink($flag);
    }
    $keep = [];
    $i = 0;
    foreach (sg_list_pool_names() as $pname) {
        $safe = sg_pool_page_safe($pname);
        if ($safe === '') {
            continue;
        }
        $keep[$safe] = true;
        $menu = 10 + $i++;
        $title = str_replace(['"', '<', '>'], '', sg_pool_title($pname));
        $pname_php = str_replace(['\\', "'"], ['\\\\', "\\'"], $pname);
        $body = <<<PAGE
Menu="StorageGuard:{$menu}"
Title="{$title}"
Tag="database"
Markdown="false"
---
<?php
\$sg_target_kind = 'pool';
\$sg_pool = '{$pname_php}';
require '/usr/local/emhttp/plugins/StorageGuard/sg-target-page.php';

PAGE;
        $page = $root . '/SGPool_' . $safe . '.page';
        if (@file_get_contents($page) !== $body) {
            @file_put_contents($page, $body);
        }
    }
    foreach (glob($root . '/SGPool_*.page') ?: [] as $f) {
        if (preg_match('/^SGPool_(.+)\.page$/', basename($f), $m) && empty($keep[$m[1]])) {
            @unlink($f);
        }
    }
}

require_once __DIR__ . '/sg-pool-math.php';
