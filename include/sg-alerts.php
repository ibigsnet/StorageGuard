<?php
/**
 * Storage Guard alerts. Run by the plugin cron (scripts/sg-alerts), not by the browser.
 *
 * - One run at a time (flock, non-blocking).
 * - Last level per target is kept on flash (alert-state.json), written only when it changes,
 *   so a reboot does not repeat an unchanged warning.
 * - Recovery needs free space a margin above the floor (hysteresis).
 * - Escalations notify at once; a step down (or recovery) waits until the last notification
 *   for that target is at least SG_RENOTIFY_MIN_S old, so a level that flaps is not re-sent.
 * - A pool that cannot survive a disk loss gets one layout notice (priority warning) when it
 *   becomes that way. A pool that was already like that when first seen is recorded silently.
 */
require_once dirname(__DIR__) . '/sg-lib.php';

const SG_HYST_PCT = 0.02;          // recovery margin: 2% of the floor ...
const SG_HYST_MIN_TB = 0.01;       // ... and at least 10 GB
const SG_RENOTIFY_MIN_S = 3600;    // minimum time between a notification and a step down

function sg_alert_rank(string $level): int {
    return ['ok' => 0, 'warning' => 1, 'critical' => 2][$level] ?? 0;
}

function sg_alert_margin(float $floor_tb): float {
    return max($floor_tb * SG_HYST_PCT, SG_HYST_MIN_TB);
}

/**
 * Free-space level with hysteresis: once at a level, stay there until free is above that
 * level's floor plus the margin.
 */
function sg_level_hyst(?float $free_tb, float $warn_tb, float $crit_tb, string $prev): string {
    $base = sg_level($free_tb, $warn_tb, $crit_tb);
    if ($free_tb === null || sg_alert_rank($base) >= sg_alert_rank($prev)) {
        return $base;
    }
    [$severe, $mild] = sg_floors($warn_tb, $crit_tb);
    if ($prev === 'critical' && $severe !== null && $free_tb <= $severe + sg_alert_margin($severe)) {
        return 'critical';
    }
    if ($mild !== null && $free_tb <= $mild + sg_alert_margin($mild)) {
        return 'warning';
    }
    return $base;
}

/** Drop levels whose notification is switched off (critical falls back to warning if that is on). */
function sg_alert_gate(string $level, bool $warn_on, bool $crit_on): string {
    if ($level === 'critical' && !$crit_on) {
        $level = $warn_on ? 'warning' : 'ok';
    }
    if ($level === 'warning' && !$warn_on) {
        $level = 'ok';
    }
    return $level;
}

/**
 * Decide what to do for one target.
 *
 * @param array|null $prev   stored record ['level'=>..., 'sent'=>unix time of last notification]
 * @param bool       $silent_first  record the first level seen without notifying
 * @return array{send:string, record:?array}  send = ''|'warning'|'critical'|'recovered';
 *                                             record = new record, or null for no change
 */
function sg_alert_decide(?array $prev, string $level, int $now, bool $silent_first = false): array {
    if ($prev === null || !isset($prev['level'])) {
        if ($silent_first || $level === 'ok') {
            return ['send' => '', 'record' => ['level' => $level, 'sent' => 0]];
        }
        return ['send' => $level, 'record' => ['level' => $level, 'sent' => $now]];
    }
    $last = (string)$prev['level'];
    if ($level === $last) {
        return ['send' => '', 'record' => null];
    }
    if (sg_alert_rank($level) > sg_alert_rank($last)) {
        return ['send' => $level, 'record' => ['level' => $level, 'sent' => $now]];
    }
    if ($now - (int)($prev['sent'] ?? 0) < SG_RENOTIFY_MIN_S) {
        return ['send' => '', 'record' => null];   // hold the step down until the interval passes
    }
    return ['send' => $level === 'ok' ? 'recovered' : $level, 'record' => ['level' => $level, 'sent' => $now]];
}

function sg_alert_state_load(): array {
    $file = sg_path('state');
    if (is_file($file)) {
        $j = json_decode((string)@file_get_contents($file), true);
        return is_array($j) ? $j : [];
    }
    // First run after upgrading from a version that kept state in /tmp: take it over
    // (same boot only) so current warnings are not re-sent.
    $state = [];
    $dir = sg_path('old_state_dir');
    $by_safe = [];
    foreach (sg_list_pool_names() as $p) {
        $by_safe['pool_' . sg_pool_page_safe($p)] = $p;
    }
    foreach (glob($dir . '/*.level') ?: [] as $f) {
        $k = basename($f, '.level');
        $v = trim((string)@file_get_contents($f));
        if (!in_array($v, ['ok', 'warning', 'critical'], true)) {
            continue;
        }
        if ($k === 'array') {
            $state['array'] = ['level' => $v, 'sent' => 0];
        } elseif (isset($by_safe[$k])) {
            $state['pool:' . $by_safe[$k]] = ['level' => $v, 'sent' => 0];
        }
    }
    return $state;
}

function sg_alert_state_save(array $state): bool {
    $file = sg_path('state');
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0755, true);
    }
    ksort($state);
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
        return false;
    }
    return @rename($tmp, $file);
}

function sg_send_notify(string $subject, string $desc, string $priority): void {
    $cmd = escapeshellarg(sg_path('notify')) . " -e 'Storage Guard' -s " . escapeshellarg($subject)
        . ' -d ' . escapeshellarg($desc) . ' -i ' . escapeshellarg($priority) . " -l '/Main'";
    @exec($cmd . ' >/dev/null 2>&1');
}

/**
 * One alert run. Options (tests): notify => callable(subject, body, priority), now => int,
 * cfg => array, eval => array (skip sg_evaluate).
 *
 * @return array{ran:bool, reason?:string, sent:list<string>, state_written:bool}
 */
function sg_alerts_run(array $opts = []): array {
    $fp = @fopen(sg_path('lock'), 'c');
    if (!$fp) {
        return ['ran' => false, 'reason' => 'cannot open lock file ' . sg_path('lock'), 'sent' => [], 'state_written' => false];
    }
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return ['ran' => false, 'reason' => 'another run is in progress', 'sent' => [], 'state_written' => false];
    }
    try {
        return sg_alerts_run_locked($opts);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function sg_alerts_run_locked(array $opts): array {
    $notify = $opts['notify'] ?? 'sg_send_notify';
    $now = (int)($opts['now'] ?? time());
    $ev = $opts['eval'] ?? null;
    if ($ev === null) {
        if (!sg_storage_online()) {
            return ['ran' => false, 'reason' => 'storage offline (array stopped, starting/stopping, or maintenance)', 'sent' => [], 'state_written' => false];
        }
        $ev = sg_evaluate($opts['cfg'] ?? sg_load_cfg());
    }
    if (empty($ev['storage_online'])) {
        return ['ran' => false, 'reason' => 'storage offline', 'sent' => [], 'state_written' => false];
    }

    $state = sg_alert_state_load();
    $orig = $state;
    $sent = [];

    $apply = function (string $key, ?string $level, bool $silent_first, callable $message) use (&$state, &$sent, $now, $notify) {
        if ($level === null) {
            unset($state[$key]);
            return;
        }
        $d = sg_alert_decide($state[$key] ?? null, $level, $now, $silent_first);
        if ($d['send'] !== '') {
            [$subject, $body, $priority] = $message($d['send']);
            $notify($subject, $body, $priority);
            $sent[] = $key . ':' . $d['send'];
        }
        if ($d['record'] !== null) {
            $state[$key] = $d['record'];
        }
    };

    // Array free space
    $a = $ev['array'];
    $a_on = $a['present'] && ($a['alerts']['warning'] || $a['alerts']['critical']);
    if (!$a_on) {
        $apply('array', null, false, 'strval');
    } elseif ($a['free_tb'] !== null) {
        $prev = (string)($state['array']['level'] ?? '');
        $lvl = sg_alert_gate(sg_level_hyst($a['free_tb'], $a['th']['warn'], $a['th']['crit'], $prev), $a['alerts']['warning'], $a['alerts']['critical']);
        $apply('array', $lvl, false, function ($send) use ($a) {
            if ($send === 'recovered') {
                return ['Storage Guard: Array free space recovered',
                    'Array free space is back above your thresholds (' . sg_human_free($a['free_tb']) . ' free). No longer at warning or critical free-space levels.', 'normal'];
            }
            return ['Storage Guard: Array free space ' . $send, sg_array_notify_body($send, $a['free_tb'], $a['th']), $send === 'critical' ? 'alert' : 'warning'];
        });
    }

    foreach ($ev['pools'] as $name => $p) {
        $name = (string)$name;
        $on = $p['alerts']['warning'] || $p['alerts']['critical'];
        $fkey = 'pool:' . $name;
        $lkey = 'pool:' . $name . ':layout';
        if (!$on) {
            $apply($fkey, null, false, 'strval');
            $apply($lkey, null, false, 'strval');
            continue;
        }
        if ($p['free_tb'] === null) {
            continue;   // not mounted / unknown
        }
        // Free space
        $prev = (string)($state[$fkey]['level'] ?? '');
        $lvl = sg_alert_gate(sg_level_hyst($p['free_tb'], $p['th']['warn'], $p['th']['crit'], $prev), $p['alerts']['warning'], $p['alerts']['critical']);
        $apply($fkey, $lvl, false, function ($send) use ($name, $p) {
            if ($send === 'recovered') {
                return ["Storage Guard: Pool {$name} free space recovered",
                    "Pool {$name} free space is back above your thresholds (" . sg_human_free($p['free_tb']) . ' free). No longer at warning or critical free-space levels.', 'normal'];
            }
            return ["Storage Guard: Pool {$name} free space {$send}",
                sg_pool_notify_body($send, $name, $p['free_tb'], $p['th'], $p['profile'], $p['class']),
                $send === 'critical' ? 'alert' : 'warning'];
        });
        // Layout (cannot survive one disk loss). Unknown layouts are left alone.
        if ($p['one_disk'] !== 'unknown') {
            $apply($lkey, $p['one_disk'] === 'nonsurvival' ? 'critical' : 'ok', true, function ($send) use ($name, $p) {
                if ($send === 'recovered') {
                    return ["Storage Guard: Pool {$name} can survive a disk loss again",
                        "Pool {$name} layout ({$p['profile']}, {$p['members']} devices) can keep data online after one disk loss.", 'normal'];
                }
                return ["Storage Guard: Pool {$name} cannot survive a disk loss",
                    sg_pool_layout_notify_body($name, $p['profile'], (int)$p['members']), 'warning'];
            });
        }
    }

    $written = false;
    if ($state !== $orig || !is_file(sg_path('state'))) {
        $written = sg_alert_state_save($state);
        if ($written && is_dir(sg_path('old_state_dir'))) {
            array_map('unlink', glob(sg_path('old_state_dir') . '/*') ?: []);
            @rmdir(sg_path('old_state_dir'));
        }
    }
    return ['ran' => true, 'sent' => $sent, 'state_written' => $written];
}

/** Cron / command line entry. Prints one JSON line; exit 0 unless the run failed to start. */
function sg_alerts_cli(): int {
    $res = sg_alerts_run([]);
    if ($res['sent']) {
        @openlog('storageguard', LOG_PID, LOG_USER);
    }
    foreach ($res['sent'] as $s) {
        @syslog(LOG_INFO, 'notified ' . $s);
    }
    echo json_encode($res, JSON_UNESCAPED_SLASHES), "\n";
    return 0;
}
