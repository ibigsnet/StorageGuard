<?php
/**
 * One Settings tab: Array, or a single named pool (Cache, ...).
 * require (not require_once): Unraid evaluates every sibling tab in one request, so
 * every id on this page is suffixed with the tab's pair key.
 */
require_once '/usr/local/emhttp/plugins/StorageGuard/sg-page-boot.php';
sg_page_assets();

$kind = ($sg_target_kind ?? 'array') === 'pool' ? 'pool' : 'array';
$pname = ($kind === 'pool') ? (string)($sg_pool ?? '') : '';
if ($kind === 'pool' && $pname === '') {
    echo '<p class="sg-page-lead">Missing pool.</p>';
    return;
}

$safe = ($kind === 'pool') ? sg_pool_page_safe($pname) : '';
$pair = ($kind === 'pool') ? ('pool-' . $safe) : 'array';
$label = ($kind === 'pool') ? sg_pool_title($pname) : 'Array';

if ($kind === 'pool') {
    $use_key = "pool_{$safe}_use_custom";
    $w_key = "pool_{$safe}_warning";
    $c_key = "pool_{$safe}_critical";
    $wc_key = "pool_{$safe}_warning_custom";
    $cc_key = "pool_{$safe}_critical_custom";
    $style_key = "pool_{$safe}_color_style";
    $p_raws = $pools_raw[$pname] ?? [];
    $p_sizes = $pools[$pname] ?? [];
    $p_use_custom = $cfg[$use_key] ?? 'no';
    $w_val = array_key_exists($w_key, $cfg) ? sg_migrate_disk_size_label($cfg[$w_key], $p_raws) : ($pool_defaults[$pname]['warning'] ?? '');
    $c_val = array_key_exists($c_key, $cfg) ? sg_migrate_disk_size_label($cfg[$c_key], $p_raws) : ($pool_defaults[$pname]['critical'] ?? '');
    $profile = sg_pool_btrfs_profile($pname);
    $current_profile = $profile !== '' ? $profile : 'unknown';
    $fs_type = sg_pool_fs_type($pname);
    $p_class = sg_pool_profile_class($profile);
    $p_one_disk = sg_pool_one_disk_mode($profile, count($p_raws));
    $p_nonsurvival = ($p_one_disk === 'nonsurvival');
    $p_disk_sizes = sg_pool_uses_disk_sizes($p_class, $p_one_disk, $fs_type);
    if (!$p_disk_sizes) {
        $p_use_custom = 'yes';
    }
    $p_style = sg_resolve_style($cfg, $style_key);
    $target_coloring = sg_pool_in_color_list($cfg + ['pool_coloring' => $pool_coloring], $pname) ? 'yes' : 'no';
    $al_w = $pool_alert_cfg[$pname]['warning'] ?? 'yes';
    $al_c = $pool_alert_cfg[$pname]['critical'] ?? 'yes';
    $sug = sg_pool_math_package($pname, $profile)['suggest'];
    $warn_lbl = !empty($sug['apply']) ? sg_format_tb_label($sug['warn_tb']) : '';
    $crit_lbl = !empty($sug['apply']) ? sg_format_tb_label($sug['crit_tb']) : '';
    $devices_summary = $p_sizes ? implode(', ', $p_sizes) : 'no devices detected';
}
$h = 'htmlspecialchars';
?>
<div class="sg-wrap">
<?php if (!empty($dev_mode)): ?>
  <p class="sg-page-lead">Preview mode — example sizes only (no live disks.ini).</p>
<?php endif; ?>
  <div class="sg-order-note" style="display:none" role="status"></div>

  <form method="POST" action="/update.php" target="progressFrame" class="sg-form" id="sg-form-<?= $h($pair) ?>"
    data-sg-pair="<?= $h($pair) ?>" data-sg-label="<?= $h($label) ?>" data-sg-has-array="<?= $has_array_disks ? '1' : '0' ?>">
    <?= sg_csrf_field() ?>
    <input type="hidden" name="#file" value="StorageGuard/StorageGuard.cfg">
    <input type="hidden" name="#include" value="plugins/StorageGuard/sg-update.php">
    <input type="hidden" name="sg_defaults" value="1">
    <input type="hidden" name="sg_target" value="<?= $kind === 'pool' ? $h($pname, ENT_QUOTES) : 'array' ?>">

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
        <select name="array_use_custom" id="array_use_custom" class="sg-use-custom">
          <?= mk_option($array_use_custom, 'no', _('Disk sizes')) ?>
          <?= mk_option($array_use_custom, 'yes', _('Custom values')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Disk sizes</strong> — Warning = largest data disk, Critical = smallest.
      <strong>Custom</strong> — remaining free space (e.g. <code>7.5T</code>, <code>500G</code>).
    </blockquote>

    <div class="sg-disk-selects">
      <dl>
        <dt>Warning:</dt>
        <dd>
          <select name="array_warning" id="array_warning" class="sg-thresh" data-sg-level="warn"
            data-sg-default="<?= $h($array_defaults['warning']) ?>">
            <?= sg_size_options($array_sizes, $array_warning) ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>Critical:</dt>
        <dd>
          <select name="array_critical" id="array_critical" class="sg-thresh" data-sg-level="crit"
            data-sg-default="<?= $h($array_defaults['critical']) ?>">
            <?= sg_size_options($array_sizes, $array_critical) ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Paint/alert when free space is at or below this amount. Prefer Warning &gt; Critical. <strong>None</strong> = off.
      </blockquote>
    </div>

    <div class="sg-custom-fields" style="display:none">
      <dl>
        <dt>Warning (custom):</dt>
        <dd>
          <input type="text" name="array_warning_custom" id="array_warning_custom" class="sg-thresh" data-sg-level="warn"
            value="<?= $h($array_warning_custom) ?>" placeholder="e.g. 7.5T — blank = None">
        </dd>
      </dl>
      <dl>
        <dt>Critical (custom):</dt>
        <dd>
          <input type="text" name="array_critical_custom" id="array_critical_custom" class="sg-thresh" data-sg-level="crit"
            value="<?= $h($array_critical_custom) ?>" placeholder="e.g. 2T — blank = None">
        </dd>
      </dl>
      <blockquote class="inline_help">
        Remaining free space. Blank = None. Prefer Warning &gt; Critical.
      </blockquote>
    </div>

    <dl>
      <dt>Notify warning:</dt>
      <dd>
        <select name="alerts_array_warning" data-sg-default="<?= $has_array_disks ? 'yes' : 'no' ?>">
          <?= mk_option($alerts_array_warning, 'yes', _('Yes')) ?>
          <?= mk_option($alerts_array_warning, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <dl>
      <dt>Notify critical:</dt>
      <dd>
        <select name="alerts_array_critical" data-sg-default="<?= $has_array_disks ? 'yes' : 'no' ?>">
          <?= mk_option($alerts_array_critical, 'yes', _('Yes')) ?>
          <?= mk_option($alerts_array_critical, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Unraid notifications when this target hits Warning or Critical, checked every 5 minutes. One notify per level change. Independent of coloring.
    </blockquote>

    <dl>
      <dt>Color Main free bar:</dt>
      <dd>
        <select name="array_coloring" id="array_coloring" data-sg-default="<?= $has_array_disks ? 'yes' : 'no' ?>">
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
        <select name="array_color_style" id="array_color_style" data-sg-default="outline">
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
      <?= $h($current_profile) ?> · <?= $h($devices_summary) ?>
      · <a href="/Main/Device?name=<?= $h(rawurlencode($pname), ENT_QUOTES) ?>">Open pool</a>
    </p>

<?php if ($p_disk_sizes): ?>
    <dl>
      <dt>Thresholds from:</dt>
      <dd>
        <select name="<?= $h($use_key) ?>" id="<?= $h($use_key) ?>" class="sg-use-custom">
          <?= mk_option($p_use_custom, 'no', _('Disk sizes')) ?>
          <?= mk_option($p_use_custom, 'yes', _('Custom values')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Default Warning = largest member, Critical = smallest. Custom: <code>7.5T</code>, <code>500G</code>.
      <strong>None</strong> turns that level off.
    </blockquote>

    <div class="sg-disk-selects">
      <dl>
        <dt>Warning:</dt>
        <dd>
          <select name="<?= $h($w_key) ?>" id="<?= $h($w_key) ?>" class="sg-thresh" data-sg-level="warn"
            data-sg-default="<?= $h($pool_defaults[$pname]['warning'] ?? '') ?>">
            <?= sg_size_options($p_sizes, $w_val) ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>Critical:</dt>
        <dd>
          <select name="<?= $h($c_key) ?>" id="<?= $h($c_key) ?>" class="sg-thresh" data-sg-level="crit"
            data-sg-default="<?= $h($pool_defaults[$pname]['critical'] ?? '') ?>">
            <?= sg_size_options($p_sizes, $c_val) ?>
          </select>
        </dd>
      </dl>
    </div>
<?php else: ?>
    <input type="hidden" name="<?= $h($use_key) ?>" value="yes" class="sg-use-custom">
<?php endif; ?>

    <div class="sg-custom-fields"<?= $p_disk_sizes ? ' style="display:none"' : '' ?>>
      <dl>
        <dt>Warning<?= $p_disk_sizes ? ' (custom)' : '' ?>:</dt>
        <dd>
          <input type="text" name="<?= $h($wc_key) ?>" id="<?= $h($wc_key) ?>" class="sg-thresh" data-sg-level="warn"
            value="<?= $h($cfg[$wc_key] ?? '') ?>" placeholder="e.g. 7.5T — blank = None">
        </dd>
      </dl>
      <dl>
        <dt>Critical<?= $p_disk_sizes ? ' (custom)' : '' ?>:</dt>
        <dd>
          <input type="text" name="<?= $h($cc_key) ?>" id="<?= $h($cc_key) ?>" class="sg-thresh" data-sg-level="crit"
            value="<?= $h($cfg[$cc_key] ?? '') ?>" placeholder="e.g. 500G — blank = None">
        </dd>
      </dl>
<?php if (!$p_disk_sizes): ?>
      <blockquote class="inline_help">
        Remaining free space (e.g. <code>7.5T</code>, <code>500G</code>). Blank = None. Disk-size floors do not apply to this layout.
      </blockquote>
<?php endif; ?>
    </div>

<?php if ($fs_type === 'zfs'): ?>
    <blockquote class="inline_help">
      ZFS layouts are not modelled. Thresholds are plain free-space floors.
    </blockquote>
<?php endif; ?>

<?php if (!empty($sug['apply']) && $warn_lbl !== ''): ?>
    <p class="sg-pool-math">
      <input type="button" class="sg-suggest-pool" data-warn="<?= $h($warn_lbl) ?>" data-crit="<?= $h($crit_lbl) ?>"
        value="Suggest free thresholds">
    </p>
    <blockquote class="inline_help">
      Fills the free amounts with capacity-fit floors (Warning <?= $h($warn_lbl) ?><?php if ($crit_lbl !== '' && $crit_lbl !== $warn_lbl): ?>, Critical <?= $h($crit_lbl) ?><?php endif; ?>). Apply to save.
    </blockquote>
<?php elseif ($p_nonsurvival): ?>
    <blockquote class="inline_help">
      This layout cannot survive a disk loss — Main paints Critical while the pool stays this profile. Free amounts are optional capacity policy only; they do not turn the bar green.
    </blockquote>
<?php endif; ?>

    <dl>
      <dt>Notify warning:</dt>
      <dd>
        <select name="alerts_pool_<?= $h($safe) ?>_warning" data-sg-default="yes">
          <?= mk_option($al_w, 'yes', _('Yes')) ?>
          <?= mk_option($al_w, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <dl>
      <dt>Notify critical:</dt>
      <dd>
        <select name="alerts_pool_<?= $h($safe) ?>_critical" data-sg-default="yes">
          <?= mk_option($al_c, 'yes', _('Yes')) ?>
          <?= mk_option($al_c, 'no', _('No')) ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Unraid notifications for this pool, checked every 5 minutes. One notify per level change. Independent of coloring.
    </blockquote>

    <dl>
      <dt>Color Main free bar:</dt>
      <dd>
        <select name="sg_target_coloring" id="sg-coloring-<?= $h($pair) ?>" data-sg-default="yes">
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
        <select name="<?= $h($style_key) ?>" id="<?= $h($style_key) ?>" data-sg-default="outline">
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
