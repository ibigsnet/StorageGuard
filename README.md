####Storage Guard####
Free-space thresholds for your array and pools. Colors free bars on Main (yellow/red, optional green outline when healthy) and optional Unraid alerts—so you can see before a drive fails whether you still have room to move data or rebalance.

---

# Storage Guard

Unraid plugin for **redundancy awareness**: set free-space thresholds on the array and each pool, then the **total free space bar** on Main shows status at a glance.

| Free space | Main free bar |
|------------|---------------|
| Above thresholds | Normal, or optional **green outline** (Outline + “green when OK”) |
| At/below Warning | **Yellow** |
| At/below Critical | **Red** |

Optional Unraid notifications use the same thresholds.

The idea is simple. Disk prices are rising. If a drive fails, you may still have enough free space to move data or rebalance **without** buying a replacement the same day. Storage Guard makes that buffer visible at a glance.

**Usual threshold order:** Warning = larger free amount (earlier heads-up); Critical = smaller free amount (more severe). If you reverse them, the plugin still ranks by free-space severity (lower free amount = critical)—the Settings page warns when that happens.

## Features

- Thresholds from real disk sizes (dropdowns) or custom values (`1.5T`, `500G`, `7.5T`, …)
- **Warning** and **Critical** levels, or **None** to leave a level unused
- Main coloring: **Solid** (default) or **Outline** per array/pool; optional outline pulse; optional **green outline when free space is still OK**
- Optional Unraid notifications (default: Array Warning only)
- **Dynamic array alerts** name matching data disks and explain evacuate-room risk
- **Pool alerts** adapt wording by BTRFS profile class (RAID1 vs RAID5/6 vs RAID10 vs none)
- Default Array Warning = largest data disk; pool thresholds start at None
- Works with the array and all your pools (any Unraid pool names)

## Install

**Plugins → Install Plugin**, paste:

```
https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg
```

Or: `plugin install` with that URL from the Unraid console.

**Update** (preferred over remove/reinstall):

```
plugin check storageguard
plugin update storageguard
```

Or **Plugins → check for updates → Update**. Settings stay in `/boot/config/plugins/StorageGuard/`.

**Requirements:** plugin metadata allows Unraid **6.12.0+**. Tested primarily on **Unraid 7.x** (Main free-bar coloring targets Unraid 7’s page layout). Reports from other versions are welcome.

## Where to configure

**Settings → User Utilities → Storage Guard**

1. Outline options (pulse / green when OK) if you use Outline style  
2. **Array** — style, free-space Warning/Critical  
3. **Pools** — which pools, style, thresholds (opt-in)  
4. **Alerts** — who gets notified at warning and/or critical  

Then open **Main** and hard-refresh if colors do not appear yet.

## Documentation

Full usage notes, array/pool examples, threshold order, and BTRFS free-space guidance:

**[DOCS.md](DOCS.md)**

## Support

- GitHub: [ibigsnet/StorageGuard](https://github.com/ibigsnet/StorageGuard)
- Issues and questions welcome via GitHub Issues (and an Unraid forum thread when published)

## Supporting development

Storage Guard is free. If it helps your setup and you want to support continued work:

- [Patreon](https://www.patreon.com/cw/IBIGSNet)
- [PayPal](https://www.paypal.com/paypalme/RifleJock)

## Versioning

Releases use `YYYY.MM.DD`, with an optional letter for same-day updates (`2026.07.09`, `2026.07.09a`, …)—the usual Unraid community plugin style.
