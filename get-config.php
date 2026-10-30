<?php
/**
 * Main free-bar paint data: saved cfg plus _status from the shared evaluator.
 * Read-only (no notifications, no writes, no smartctl).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/sg-lib.php';

$cfg = sg_load_cfg();
$ev = sg_evaluate($cfg);
$online = $ev['storage_online'];

$a = $ev['array'];
$array_enabled = $online && $a['coloring'];
$status = [
    'storage_online' => $online,
    'array' => [
        'present' => $a['present'],
        'enabled' => $array_enabled,
        'free_tb' => ($array_enabled && $a['free_tb'] !== null) ? round($a['free_tb'], 3) : null,
        'warn_tb' => round($a['th']['warn'], 3),
        'crit_tb' => round($a['th']['crit'], 3),
        'level' => $array_enabled ? $a['level'] : 'ok',
        'style' => $a['style'],
    ],
    'pools' => new stdClass(),
];

$pools = [];
foreach ($ev['pools'] as $name => $p) {
    $enabled = $online && $p['coloring'];
    $pools[$name] = [
        'enabled' => $enabled,
        'free_tb' => $p['free_tb'] !== null ? round($p['free_tb'], 3) : null,
        'warn_tb' => round($p['th']['warn'], 3),
        'crit_tb' => round($p['th']['crit'], 3),
        'level' => $enabled ? $p['level'] : 'ok',
        'level_reason' => $enabled ? $p['level_reason'] : 'none',
        'one_disk_mode' => $p['one_disk'],
        'members' => $p['members'],
        'style' => $p['style'],
        'profile' => $p['profile'],
        'fs_type' => $p['fs_type'],
        'threshold_source' => $p['th']['source'],
        'math' => $p['math'],
    ];
}
if ($pools) {
    $status['pools'] = $pools;
}

$out = $cfg;
$out['_status'] = $status;
echo json_encode($out);
