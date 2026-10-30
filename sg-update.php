<?php
/**
 * Unraid update.php #include hook for the Storage Guard forms.
 * Runs inside update.php ($keys = saved cfg, $default = default.cfg on #default).
 */
require_once '/usr/local/emhttp/plugins/StorageGuard/sg-lib.php';

// Form-only fields never go to the cfg.
if (is_array($keys)) {
    foreach (array_keys($keys) as $k) {
        if (in_array($k, ['pool_all', 'sg_target', 'sg_target_coloring', 'sg_tab', 'cache_coloring'], true)
            || preg_match('/^pool_color_/', (string)$k)) {
            unset($keys[$k]);
        }
    }
}

if (!isset($_POST['#default'])) {
    $cur_cfg = sg_load_cfg();
    // Settings tab: one outline/fill style for every target.
    if (($_POST['sg_tab'] ?? '') === 'settings') {
        $style = (strtolower((string)($_POST['color_style'] ?? 'outline')) === 'solid') ? 'solid' : 'outline';
        $_POST['color_style'] = $style;
        $_POST['array_color_style'] = $style;
        foreach (array_keys($cur_cfg) as $ck) {
            if (preg_match('/^pool_(.+)_color_style$/', (string)$ck, $m)) {
                $_POST['pool_' . $m[1] . '_color_style'] = $style;
            }
        }
        foreach (sg_list_pool_names() as $pn) {
            $_POST['pool_' . sg_pool_page_safe($pn) . '_color_style'] = $style;
        }
    }
    unset($_POST['sg_tab']);
    // Pool tab: "Color Main free bar" edits this pool's entry in pools_to_color.
    $target = trim((string)($_POST['sg_target'] ?? ''));
    if ($target !== '' && $target !== 'array') {
        $want = (strtolower((string)($_POST['sg_target_coloring'] ?? 'no')) === 'yes');
        $live = sg_list_pool_names();
        $cur = (string)($cur_cfg['pools_to_color'] ?? 'all');
        $set = $cur === 'all' ? $live : ($cur === '' ? [] : preg_split('/\s*,\s*/', $cur, -1, PREG_SPLIT_NO_EMPTY));
        $set = array_values(array_unique($set));
        if ($want && !in_array($target, $set, true)) {
            $set[] = $target;
        } elseif (!$want) {
            $set = array_values(array_filter($set, static function ($x) use ($target) {
                return $x !== $target;
            }));
        }
        $all = ($live && !array_diff($live, $set));
        $_POST['pools_to_color'] = ($all && $set) ? 'all' : implode(',', $set);
        $_POST['pool_coloring'] = $set ? 'yes' : 'no';
    }
    unset($_POST['sg_target'], $_POST['sg_target_coloring']);
    return;
}

// Default button: machine-aware product defaults for every posted key.
if (!is_array($default)) {
    $default = [];
}
foreach (sg_product_defaults_map() as $k => $v) {
    $default[$k] = $v;
}
$default['sg_defaults'] = '';

foreach ($_POST as $key => $value) {
    if (!is_string($key) || $key === '' || $key[0] === '#') {
        continue;
    }
    if (preg_match('/^alerts_pool_.+_(warning|critical)$/', $key)) {
        $default[$key] = $default[$key] ?? 'yes';
    } elseif (preg_match('/^pool_.+_(warning_custom|critical_custom)$/', $key)) {
        $default[$key] = '';
    } elseif (preg_match('/^pool_.+_use_custom$/', $key)) {
        $default[$key] = $default[$key] ?? 'no';
    } elseif (preg_match('/^pool_.+_color_style$/', $key)) {
        $default[$key] = 'outline';
    }
}

if (is_array($keys)) {
    foreach (array_keys($keys) as $k) {
        if (preg_match('/^(pool_|alerts_pool_)/', (string)$k) && !array_key_exists($k, $_POST)) {
            unset($keys[$k]);
        }
    }
}
