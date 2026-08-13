# Security / CA review notes — Storage Guard

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Plugin runs as root (Unraid plugin model).
- Does **not** modify disks, mounts, `network.cfg`, Docker, or VMs.
- Read-only use of Unraid disk free-space data for thresholds and optional alerts.

## Defaults

- Coloring and alerts only when the array is fully started.
- No network listeners.
- No package downloads.

## Core WebGUI touch (important)

- Install appends a small CSS/JS include into Unraid `HeadInlineJS.php` / `HeadInclude.php` so free bars on **Main** can be colored.
- Uninstall removes those inject lines and deletes the plugin emhttp tree.
- Flash config under `/boot/config/plugins/StorageGuard/` is **kept** on uninstall (easy reinstall).
- Future Unraid UI path renames may require a plugin update for coloring to re-attach.

## Install / update supply chain

- PluginURL: `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable/storageguard.plg`
- All FILE sources: GitHub `stable` branch, version-pinned via `?v=`
- Development happens on `main`; store users only receive `stable`.

## What to read (5 minutes)

1. `storageguard.plg` — install inject + Method=remove un-inject
2. `sg-lib.php` / `check-alerts.php` — thresholds and notifications only
3. This file

## Contact

- Support: Unraid forum thread linked from the CA template / Plugins page
- Project: https://github.com/ibigsnet/StorageGuard
