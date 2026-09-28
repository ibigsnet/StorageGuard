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

## Main page free-bar coloring

CSS/JS load from the plugin's own `StorageGuardHead.page` (Unraid `Buttons` hook). No stock Unraid file is edited.

| Property | Behavior |
|----------|----------|
| **Stock files** | Not modified. Versions up to 2026.09.28aa patched `HeadInlineJS.php`; install/upgrade and remove put it back (flash backup if it matches, else strip our lines only). |
| **Which files the cleanup reads** | Fixed Unraid layout paths only. **Never** walks `/mnt`, user shares, or any directory whose **name** is “Storage Guard”. |
| **Uninstall** | Removes plugin emhttp + **all** plugin flash state. |

Details: [docs/main-paint.md](docs/main-paint.md).

## Uninstall

- Puts back stock HeadInlineJS.php if an older version patched it.
- Removes emhttp plugin tree.
- **Removes** `/boot/config/plugins/StorageGuard/` entirely (config, backups) so reinstall is clean.
- Does **not** touch user data, shares, or folders outside the plugin paths.

Export or screenshot settings before uninstall if you want them later.

## Install channel

Production / Community Applications: GitHub branch **`main`**.  
WIP: branch **`testing`**.

## Contact

- **Support (forum):** https://forums.unraid.net/topic/199796-plugin-storage-guard-free-space-thresholds-so-you-know-if-a-failed-disk-still-leaves-room-to-move-data/  
- **Project:** https://github.com/ibigsnet/StorageGuard  
