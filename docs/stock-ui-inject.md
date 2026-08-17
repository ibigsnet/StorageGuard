# Stock UI inject — Main free-bar coloring

How Storage Guard hooks Unraid’s Main page, and what it **never** touches.

## What problem this solves

Unraid Main free-space bars are not a plugin API. To paint Warning/Critical colors, the plugin adds a small CSS + JS include to the stock page head.

## Exact files (and only these)

| Path | Role |
|------|------|
| `/usr/local/emhttp/webGui/include/DefaultPageLayout/HeadInlineJS.php` | Primary inject target |
| `/usr/local/emhttp/plugins/dynamix/include/DefaultPageLayout/HeadInlineJS.php` | Alternate layout path (strip if present) |

Legacy clean-up may also strip matching **lines** from `HeadInclude.php` if an older build left anything there.

**Not searched:** `/mnt/*`, user shares, `/boot` except the plugin’s own config dir, Docker appdata, or any path that merely **contains** the words Storage Guard.

### “Storage Guard” folder on the array

A share or folder like:

```text
/mnt/user/Backups/My Backups/Storage Guard/Private files
```

is **never** opened, listed, or `sed`’d by this plugin. The install/remove scripts use a **fixed list of absolute emhttp paths**. Folder names are irrelevant.

## Marker block (2026.08.17aa+)

```html
<!-- StorageGuard-inject begin -->
<link rel="stylesheet" href="/plugins/StorageGuard/storageguard.css?v=…">
<script src="/plugins/StorageGuard/storageguard-color.js?v=…"></script>
<!-- StorageGuard-inject end -->
```

Strip on upgrade/uninstall matches:

- Lines containing `StorageGuard-inject`
- Lines containing `storageguard-color.js` / `storageguard.css` / `plugins/StorageGuard/storageguard`

Older builds used an HTML comment `<!-- Storage Guard -->`. Upgrade strips asset-path lines; the new markers replace them.

## Stock backup

First successful inject (per file basename):

```text
/boot/config/plugins/StorageGuard/stock-backup/HeadInlineJS.php.stock
```

Full uninstall removes the plugin flash tree including this backup after stripping inject lines from live layout files.

## Failure modes

| Event | Result |
|-------|--------|
| Unraid OS upgrade replaces HeadInlineJS | Coloring may stop until plugin reinstall/upgrade re-injects |
| User edits HeadInlineJS by hand | Our markers may still be stripped on uninstall; keep changes outside the marker block |
| Plugin not installed | No markers; stock file unchanged by us |

## Related

- [SECURITY.md](../SECURITY.md) — privilege model and uninstall  
- Unraid **Safe Mode** — plugins (and this inject) not loaded  
