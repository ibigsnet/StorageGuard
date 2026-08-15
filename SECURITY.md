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

- Install hooks a small CSS/JS include so free bars on **Main** can be colored.
- Uninstall removes those hooks and the plugin emhttp tree.
- Flash config under `/boot/config/plugins/StorageGuard/` is kept on uninstall (easy reinstall).
- A future Unraid UI path change may require a plugin update for coloring to re-attach.

## Install channel

Production / Community Applications: GitHub branch **`stable`**.  
Lab / development: branch **`main`**.

## Contact

- Support: Unraid forum thread on the plugin’s Apps card  
- Project: https://github.com/ibigsnet/StorageGuard  
