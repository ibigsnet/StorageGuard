# Main free-bar coloring

How Storage Guard colors the free-space bars on Unraid's Main page, and what it **never** touches.

## How it loads (2026.09.28ab+)

The plugin ships its own page, `StorageGuardHead.page`, with `Menu="Buttons"`. Unraid loads every Buttons page on each page view, so this page adds:

```html
<link rel="stylesheet" href="/plugins/StorageGuard/storageguard.css?v=…">
<script src="/plugins/StorageGuard/storageguard-color.js?v=…"></script>
```

It sets `Link`, so no button shows in the header. No stock Unraid file is edited. Removing the plugin removes the page, and the colors go with it.

`storageguard-color.js` only paints on Main (it checks for the array / pool device tables). Main rewrites those tables on each refresh; a `MutationObserver` re-paints them before the browser draws, so bars do not flash. No jQuery function is overridden.

## Cleanup of older versions

Versions up to 2026.09.28aa appended a marker block to the stock layout file:

```text
/usr/local/emhttp/webGui/include/DefaultPageLayout/HeadInlineJS.php
```

and kept a copy at `/boot/config/plugins/StorageGuard/stock-backup/HeadInlineJS.php.stock`.

Install/upgrade and remove run `scripts/sg-head-restore`:

| File state | Action |
|------------|--------|
| No Storage Guard lines | Left alone (stock, or Unraid already replaced it) |
| Lines present, backup matches the file without them | Backup written back (exact stock file) |
| Lines present, backup missing or from another Unraid build | Our lines stripped in place |

Only lines with `StorageGuard-inject`, `<!-- Storage Guard -->`, or the plugin's own asset paths are removed. The file is rewritten in place (owner and mode kept). The old backup directory is then deleted. `sg-head-restore status` lists each layout file as clean or patched.

**Not searched:** `/mnt/*`, user shares, `/boot` except the plugin's own config dir, Docker appdata, or any path that merely **contains** the words Storage Guard. A share or folder named "Storage Guard" is never opened or edited.

## Related

- [SECURITY.md](../SECURITY.md) — privilege model and uninstall
- Unraid **Safe Mode** — plugins (and Main coloring) not loaded
