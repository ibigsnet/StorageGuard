# Security — Storage Guard

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Runs as root (Unraid plugin model).
- Does **not** modify disks, mounts, `network.cfg`, Docker, or VMs.
- Read-only use of Unraid free-space data for thresholds and optional alerts.

## Defaults

- Coloring and alerts only when the array is fully started.
- No network listeners.
- No package downloads.

## Main page free-bar coloring (stock UI inject)

Install appends a **marker-bounded** CSS/JS include so free bars on **Main** can be colored.

| Property | Behavior |
|----------|----------|
| **Which files** | Only fixed Unraid layout paths under `/usr/local/emhttp/…/HeadInlineJS.php` (and strip of legacy lines there). **Never** walks `/mnt`, user shares, or any directory whose **name** is “Storage Guard”. |
| **How strip works** | Line match on plugin asset paths / `StorageGuard-inject` markers only — not a filesystem search for folders. |
| **Backup** | First inject copies stock `HeadInlineJS.php` to `/boot/config/plugins/StorageGuard/stock-backup/` (removed on full uninstall). |
| **Uninstall** | Removes the marker block from those layout files, then removes plugin emhttp + **all** plugin flash state. |

A future Unraid UI path change may require a plugin update for coloring to re-attach.

Details: [docs/stock-ui-inject.md](docs/stock-ui-inject.md).

## Uninstall

- Stops using Main free-bar hooks (strip markers from layout files above).
- Removes emhttp plugin tree.
- **Removes** `/boot/config/plugins/StorageGuard/` entirely (config, backups) so reinstall is clean.
- Does **not** touch user data, shares, or folders outside the plugin paths.

Export or screenshot settings before uninstall if you want them later.

## Install channel

Production / Community Applications: GitHub branch **`stable`**.  
Lab / development: branch **`main`**.

## Contact

- **Support (forum):** https://forums.unraid.net/topic/199796-plugin-storage-guard-free-space-thresholds-so-you-know-if-a-failed-disk-still-leaves-room-to-move-data/  
- **Project:** https://github.com/ibigsnet/StorageGuard  
