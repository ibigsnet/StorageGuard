# Security — Storage Guard

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Runs as root (Unraid plugin model).
- Does **not** modify disks, mounts, `network.cfg`, Docker, or VMs.
- Read-only use of Unraid free-space data for thresholds and optional alerts.
- Commands it runs: `df`, `btrfs filesystem df` (pool profile), and Unraid's `notify` script; every argument is shell-escaped. `smartctl -n standby -i` exists only for optional speed estimates, which Main and alerts never request.

## Defaults

- Coloring and alerts only when the array is fully started.
- No network listeners.
- The only download is at install/update: Unraid fetches the plugin `.txz` from this project's GitHub Release, checked against the SHA256/MD5 in the `.plg`, and keeps it on the flash drive. Boot installs from that saved copy; the plugin itself downloads nothing at run time.

## Alerts and endpoints

- Alerts run from a plugin cron (`/boot/config/plugins/StorageGuard/storageguard.cron`, installed with Unraid's `update_cron`), not from the browser. One run at a time (`flock` on `/var/run/storageguard-alerts.lock`).
- Last alert level per target: `/boot/config/plugins/StorageGuard/alert-state.json`, written only when a level changes.
- `get-config.php` (GET) is read-only: no notifications, no writes.
- `check-alerts.php` runs the same alert check and accepts POST only; Unraid's `local_prepend.php` checks `csrf_token` on every POST before the plugin sees the request.
- Settings forms post to Unraid's `update.php` with the WebUI `csrf_token`.

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
- **Removes** `/boot/config/plugins/StorageGuard/` entirely (config, alert state, alert cron, backups) so reinstall is clean, then runs `update_cron` so the alert job is gone.
- Removes runtime files: `/tmp/storageguard_alerts`, `/var/tmp/storageguard-has-array`, `/var/run/storageguard-alerts.lock`.
- Does **not** touch user data, shares, or folders outside the plugin paths.

Export or screenshot settings before uninstall if you want them later.

## Install channel

Production / Community Applications: GitHub branch **`main`**.  
WIP: branch **`testing`**.

## Contact

- **Support (forum):** https://forums.unraid.net/topic/199796-plugin-storage-guard-free-space-thresholds-so-you-know-if-a-failed-disk-still-leaves-room-to-move-data/  
- **Project:** https://github.com/ibigsnet/StorageGuard  
