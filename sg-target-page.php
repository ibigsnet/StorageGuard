<?php
/**
 * One Settings tab: Array, or a single named pool (Cache, …).
 * require (not require_once): Unraid may eval every sibling tab in one request.
 */
require_once '/usr/local/emhttp/plugins/StorageGuard/sg-page-boot.php';
sg_page_styles();

$kind = ($sg_target_kind ?? 'array') === 'pool' ? 'pool' : 'array';
$pname = ($kind === 'pool') ? (string)($sg_pool ?? '') : '';
if ($kind === 'pool' && $pname === '') {
    echo '<p class="sg-page-lead">Missing pool.</p>';
    return;
}

$safe = ($kind === 'pool') ? preg_replace('/[^a-zA-Z0-9_]/', '_', $pname) : '';
$title = ($kind === 'pool') ? sg_pool_title($pname) : 'Array';

if ($kind === 'pool') {
    $use_key = "pool_{$safe}_use_custom";
    $w_key = "pool_{$safe}_warning";
    $c_key = "pool_{$safe}_critical";
    $wc_key = "pool_{$safe}_warning_custom";
    $cc_key = "pool_{$safe}_critical_custom";
    $style_key = "pool_{$safe}_color_style";
    $p_use_custom = $cfg[$use_key] ?? 'no';
    $p_warn_custom = $cfg[$wc_key] ?? '';
    $p_crit_custom = $cfg[$cc_key] ?? '';
    $p_raws = $pools_raw[$pname] ?? [];
    $p_sizes = $pools[$pname] ?? [];
    if ($sg_defaults_ok && array_key_exists($w_key, $cfg)) {
        $w_val = $cfg[$w_key];
    } else {
        $w_val = $pool_defaults[$pname]['warning'] ?? '';
    }
    if ($sg_defaults_ok && array_key_exists($c_key, $cfg)) {
        $c_val = $cfg[$c_key];
    } else {
        $c_val = $pool_defaults[$pname]['critical'] ?? '';
    }
    if ($p_use_custom !== 'yes' && function_exists('sg_migrate_disk_size_label') && !empty($p_raws)) {
        if ($w_val !== '') {
            $w_val = sg_migrate_disk_size_label($w_val, $p_raws);
        }
        if ($c_val !== '') {
            $c_val = sg_migrate_disk_size_label($c_val, $p_raws);
        }
    }
    if ($p_use_custom !== 'yes' && $w_val !== '' && !empty($p_sizes) && !in_array($w_val, $p_sizes, true)) {
        $w_val = '';
    }
    if ($p_use_custom !== 'yes' && $c_val !== '' && !empty($p_sizes) && !in_array($c_val, $p_sizes, true)) {
        $c_val = '';
    }
    $current_profile = function_exists('sg_pool_btrfs_profile') ? sg_pool_btrfs_profile($pname) : '';
    if ($current_profile === '') {
        $current_profile = 'unknown';
    }
    $devices_summary = !empty($p_sizes) ? implode(', ', $p_sizes) : 'no devices detected';
    $p_members = count($p_raws);
    $p_one_disk = function_exists('sg_pool_one_disk_mode')
        ? sg_pool_one_disk_mode($current_profile, $p_members)
        : 'unknown';
    $p_nonsurvival = ($p_one_disk === 'nonsurvival');
    $p_style = sg_resolve_style($cfg, $style_key);
    $in_color = ($pool_coloring === 'yes') && ($pools_to_color === 'all'
        || strpos(',' . $pools_to_color . ',', ',' . $pname . ',') !== false);
    $target_coloring = $in_color ? 'yes' : 'no';
    $al_w = $pool_alert_cfg[$pname]['warning'] ?? 'yes';
    $al_c = $pool_alert_cfg[$pname]['critical'] ?? 'yes';
    $p_math = function_exists('sg_pool_math_package') ? sg_pool_math_package($pname, $current_profile) : null;
    $sug = is_array($p_math) ? ($p_math['suggest'] ?? null) : null;
    $warn_lbl = ($sug && !empty($sug['apply']) && function_exists('sg_format_tb_label'))
        ? sg_format_tb_label($sug['warn_tb']) : '';
    $crit_lbl = ($sug && !empty($sug['apply']) && function_exists('sg_format_tb_label'))
        ? sg_format_tb_label($sug['crit_tb']) : '';
}

$pair = ($kind === 'pool') ? ('pool-' . $safe) : 'array';
?>
<div class="sg-wrap">
<?php if (!empty($dev_mode)): ?>
  <p class="sg-page-lead">Preview mode — example sizes only (no live disks.ini).</p>
<?php endif; ?>
  <div id="sg-order-note" class="sg-order-note" style="display:none" role="status"></div>

  <form method="POST" action="/update.php" target="progressFrame" id="storageguard-form"
    data-sg-has-array="<?= $has_array_disks ? '1' : '0' ?>"
    data-sg-target="<?= $kind === 'pool' ? htmlspecialchars($pname, ENT_QUOTES) : 'array' ?>">
    <?= sg_csrf_field() ?>
    <input type="hidden" name="#file" value="StorageGuard/StorageGuard.cfg">
    <input type="hidden" name="#include" value="plugins/StorageGuard/sg-update.php">
    <input type="hidden" name="sg_defaults" value="1">
    <input type="hidden" name="sg_target" value="<?= $kind === 'pool' ? htmlspecialchars($pname, ENT_QUOTES) : 'array' ?>">

<?php if ($kind === 'array'): ?>
    <p class="sg-section-lead">
<?php if (!$has_array_disks): ?>
      No array data disks. Thresholds still save if you set them.
<?php else: ?>
      Remaining free space on the array. Warning = largest data disk, Critical = smallest, until you change them.
<?php endif; ?>
    </p>

    <dl>
      <dt>Thresholds from:</dt>
      <dd>
        <select name="array_use_custom" id="array_use_custom">
          <?= mk_option($array_use_custom, 'no', _('Disk sizes')) ?>
          <?= mk_option($array_use_custom, 'yes', _('Custom values')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Disk sizes</strong> — Warning = largest data disk, Critical = smallest.
      <strong>Custom</strong> — remaining free space (e.g. <code>7.5T</code>, <code>500G</code>).
    </blockquote>

    <div id="array-disk-selects">
      <dl>
        <dt>Warning:</dt>
        <dd>
          <select name="array_warning" id="array_warning" class="sg-thresh" data-sg-pair="array" data-sg-level="warn"
            data-sg-default-warn="<?= htmlspecialchars($array_defaults['warning'] ?? '') ?>"
            data-sg-default-crit="<?= htmlspecialchars($array_defaults['critical'] ?? '') ?>">
            <?= sg_size_options($array_sizes, $array_warning) ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>Critical:</dt>
        <dd>
          <select name="array_critical" id="array_critical" class="sg-thresh" data-sg-pair="array" data-sg-level="crit"
            data-sg-default-crit="<?= htmlspecialchars($array_defaults['critical'] ?? '') ?>">
            <?= sg_size_options($array_sizes, $array_critical) ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Paint/alert when free space is at or below this amount. Prefer Warning &gt; Critical. <strong>None</strong> = off.
      </blockquote>
    </div>

    <div id="array-custom-fields" style="display:none">
      <dl>
        <dt>Warning (custom):</dt>
        <dd>
          <input type="text" name="array_warning_custom" id="array_warning_custom" class="sg-thresh"
            data-sg-pair="array" data-sg-level="warn"
            value="<?= htmlspecialchars($array_warning_custom) ?>" placeholder="e.g. 7.5T — blank = None">
        </dd>
      </dl>
      <dl>
        <dt>Critical (custom):</dt>
        <dd>
          <input type="text" name="array_critical_custom" id="array_critical_custom" class="sg-thresh"
            data-sg-pair="array" data-sg-level="crit"
            value="<?= htmlspecialchars($array_critical_custom) ?>" placeholder="e.g. 2T — blank = None">
        </dd>
      </dl>
      <blockquote class="inline_help">
        Remaining free space. Blank = None. Prefer Warning &gt; Critical.
      </blockquote>
    </div>

    <dl>
      <dt>Notify warning:</dt>
      <dd>
        <select name="alerts_array_warning">
          <?= mk_option($alerts_array_warning, 'yes', _('Yes')) ?>
          <?= mk_option($alerts_array_warning, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <dl>
      <dt>Notify critical:</dt>
      <dd>
        <select name="alerts_array_critical">
          <?= mk_option($alerts_array_critical, 'yes', _('Yes')) ?>
          <?= mk_option($alerts_array_critical, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Unraid notifications when this target hits Warning or Critical. One notify per level change. Independent of coloring.
    </blockquote>

    <dl>
      <dt>Color Main free bar:</dt>
      <dd>
        <select name="array_coloring" id="array_coloring">
          <?= mk_option($array_coloring, 'yes', _('Yes')) ?>
          <?= mk_option($array_coloring, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Yes</strong> = color the array Total free bar on Main.
    </blockquote>
    <dl>
      <dt>Outline or fill:</dt>
      <dd>
        <select name="array_color_style" id="array_color_style">
          <?= mk_option($array_color_style, 'outline', _('Outline')) ?>
          <?= mk_option($array_color_style, 'solid', _('Solid (fill)')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Outline = border only. Solid = recolor the free fill.
    </blockquote>

<?php else: ?>
    <p class="sg-section-lead">
      <?= htmlspecialchars($current_profile) ?> · <?= htmlspecialchars($devices_summary) ?>
      · <a href="/Main/Device?name=<?= htmlspecialchars(rawurlencode($pname), ENT_QUOTES) ?>">Open pool</a>
    </p>

    <dl>
      <dt>Thresholds from:</dt>
      <dd>
        <select name="<?= htmlspecialchars($use_key) ?>" id="<?= htmlspecialchars($use_key) ?>"
          class="pool-use-custom" data-pool-safe="<?= htmlspecialchars($safe) ?>">
          <?= mk_option($p_use_custom, 'no', _('Disk sizes')) ?>
          <?= mk_option($p_use_custom, 'yes', _('Custom values')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Default Warning = largest member, Critical = smallest. Custom: <code>7.5T</code>, <code>500G</code>.
      <strong>None</strong> turns that level off.
    </blockquote>

    <div id="pool-<?= htmlspecialchars($safe) ?>-disk-selects">
      <dl>
        <dt>Warning:</dt>
        <dd>
          <select name="<?= htmlspecialchars($w_key) ?>" id="<?= htmlspecialchars($w_key) ?>"
            class="pool-size-select sg-thresh" data-sg-pair="<?= htmlspecialchars($pair) ?>" data-sg-level="warn"
            data-sg-label="<?= htmlspecialchars($pname) ?>">
            <?= sg_size_options($p_sizes, $w_val) ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>Critical:</dt>
        <dd>
          <select name="<?= htmlspecialchars($c_key) ?>" id="<?= htmlspecialchars($c_key) ?>"
            class="pool-size-select sg-thresh" data-sg-pair="<?= htmlspecialchars($pair) ?>" data-sg-level="crit"
            data-sg-label="<?= htmlspecialchars($pname) ?>">
            <?= sg_size_options($p_sizes, $c_val) ?>
          </select>
        </dd>
      </dl>
    </div>

    <div id="pool-<?= htmlspecialchars($safe) ?>-custom-fields" style="display:none">
      <dl>
        <dt>Warning (custom):</dt>
        <dd>
          <input type="text" name="<?= htmlspecialchars($wc_key) ?>" id="<?= htmlspecialchars($wc_key) ?>"
            class="sg-thresh" data-sg-pair="<?= htmlspecialchars($pair) ?>" data-sg-level="warn"
            data-sg-label="<?= htmlspecialchars($pname) ?>"
            value="<?= htmlspecialchars($p_warn_custom) ?>" placeholder="e.g. 7.5T — blank = None">
        </dd>
      </dl>
      <dl>
        <dt>Critical (custom):</dt>
        <dd>
          <input type="text" name="<?= htmlspecialchars($cc_key) ?>" id="<?= htmlspecialchars($cc_key) ?>"
            class="sg-thresh" data-sg-pair="<?= htmlspecialchars($pair) ?>" data-sg-level="crit"
            data-sg-label="<?= htmlspecialchars($pname) ?>"
            value="<?= htmlspecialchars($p_crit_custom) ?>" placeholder="e.g. 500G — blank = None">
        </dd>
      </dl>
    </div>

<?php if ($sug && !empty($sug['apply']) && $warn_lbl !== ''): ?>
    <p class="sg-pool-math" id="pool-<?= htmlspecialchars($safe) ?>-math" data-pool-safe="<?= htmlspecialchars($safe) ?>">
      <input type="button" class="sg-suggest-pool" data-pool-safe="<?= htmlspecialchars($safe) ?>"
        data-warn="<?= htmlspecialchars($warn_lbl) ?>" data-crit="<?= htmlspecialchars($crit_lbl) ?>"
        value="Suggest free thresholds">
    </p>
    <blockquote class="inline_help">
      Fills Custom with capacity-fit free floors (Warning <?= htmlspecialchars($warn_lbl) ?><?php if ($crit_lbl !== '' && $crit_lbl !== $warn_lbl): ?>, Critical <?= htmlspecialchars($crit_lbl) ?><?php endif; ?>). Apply to save.
    </blockquote>
<?php elseif ($p_nonsurvival): ?>
    <blockquote class="inline_help">
      This layout cannot survive a disk loss — Main paints Critical while the pool stays this profile. Custom free amounts are optional capacity policy only; they do not turn the bar green.
    </blockquote>
<?php endif; ?>

    <dl>
      <dt>Notify warning:</dt>
      <dd>
        <select name="alerts_pool_<?= htmlspecialchars($safe) ?>_warning">
          <?= mk_option($al_w, 'yes', _('Yes')) ?>
          <?= mk_option($al_w, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <dl>
      <dt>Notify critical:</dt>
      <dd>
        <select name="alerts_pool_<?= htmlspecialchars($safe) ?>_critical">
          <?= mk_option($al_c, 'yes', _('Yes')) ?>
          <?= mk_option($al_c, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Unraid notifications for this pool. One notify per level change. Independent of coloring.
    </blockquote>

    <dl>
      <dt>Color Main free bar:</dt>
      <dd>
        <select name="sg_target_coloring" id="pool_coloring">
          <?= mk_option($target_coloring, 'yes', _('Yes')) ?>
          <?= mk_option($target_coloring, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Yes</strong> = color this pool’s free bar on Main.
    </blockquote>
    <dl>
      <dt>Outline or fill:</dt>
      <dd>
        <select name="<?= htmlspecialchars($style_key) ?>" id="<?= htmlspecialchars($style_key) ?>">
          <?= mk_option($p_style, 'outline', _('Outline')) ?>
          <?= mk_option($p_style, 'solid', _('Solid (fill)')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Outline = border only. Solid = recolor the free fill.
    </blockquote>
<?php endif; ?>

    <dl>
      <dt>&nbsp;</dt>
      <dd>
        <input type="submit" name="#default" value="<?= _('Default') ?>">
        <input type="submit" name="#apply" value="<?= _('Apply') ?>" disabled>
        <input type="button" value="<?= _('Done') ?>" onclick="done()">
      </dd>
    </dl>
  </form>
</div>
<script src="<?autov('/plugins/StorageGuard/storageguard.js')?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof initStorageGuardUI === 'function') initStorageGuardUI();
});
</script>
