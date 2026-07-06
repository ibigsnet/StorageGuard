# Storage Guard - Unraid

Storage Guard adds configurable total free space thresholds for your Unraid Array and Pools. It colors relevant sections yellow/red when low and sends native Unraid notifications.

- Pulls real disk capacities from your array data disks and each pool.
- Dropdowns for Warning + Critical using actual disk sizes (unique, sorted).
- "Use custom capacity" toggle greys out dropdowns and lets you enter arbitrary values (26T, 1.5T, 500G, etc.).
- Per-pool controls with checkboxes for "All" or specific pools.
- Alerts section with granular enable (All / Array / specific pools).
- Helpful inline hints (blue help text) explaining the "largest disk failure" logic with concrete examples.
- Prepares for advanced BTRFS RAID rebalance awareness.

Supports largest-disk auto-detect and flexible units.

## Installation
Paste this in Plugins → Install Plugin:
https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg

## Usage Notes (Array)
- Uniform disks (e.g. 3×26T data): both dropdowns show 26T.
- Mixed disks (Parity 12T, 8T×3, 4T, 2T data): choose Warning=8T, Critical=2T so you know when you no longer have space to "move data off" a failed drive of that size.

## Usage Notes (Pools)
- Thresholds are **total free space** in the pool.
- For BTRFS RAID1/10 etc. the values should reflect rebalance space needs after a drive loss.
- The help text under each pool gives examples. More detailed RAID calculator / advice planned.

## How coloring works (injection)
Storage Guard uses client-side injection (JS + CSS loaded globally via Unraid's HeadInclude) to color the existing Array and Pool sections on the Main tab.

- It reads your saved thresholds from the config (the disk-capacity dropdowns, custom values, per-pool settings, etc.).
- Scans the live Unraid Main page DOM for free space values associated with the array and selected pools.
- Applies yellow (warning) or red (critical) styling to the relevant containers using the thresholds you defined.
- No custom UI is built — it leverages Unraid's existing elements for a clean look.

The alerts section in settings configures when notifications should fire (can be wired to Unraid's notify command).
