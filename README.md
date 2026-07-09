####Storage Guard####
Free-space thresholds for your array and pools. Colors free bars on Main (and optional Unraid alerts) so you can see—before a drive fails—whether you still have room to move data or rebalance.

---

# Storage Guard

Unraid plugin for **redundancy awareness**: set free-space thresholds on the array and each pool, then the **total free space bar** on Main is colored yellow (warning) or red (critical). Optional notifications use the same thresholds.

The idea is simple. Disk prices are rising. If a drive fails, you may still have enough free space to move data or rebalance **without** buying a replacement the same day. Storage Guard makes that buffer visible at a glance.

## Features

- Thresholds from real disk sizes (dropdowns) or custom values (`1.5T`, `500G`, `7.5T`, …)
- **Warning** and **Critical** levels, or **None** to leave a level unused
- Main-tab coloring: **Outline** (pulse border, keep green fill) or **Solid** (recolor the free bar) — set **per array / per pool**
- Optional Unraid notifications, independent of coloring
- Works with the array and all your pools (names come from Unraid—any pool name, not just `cache`)

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

Requires Unraid **6.12.0+**.

## Where to configure

**Settings → User Utilities → Storage Guard**

1. **Array** — color the array free bar, pick highlight style, set warning/critical free-space thresholds  
2. **Pools** — same for each pool (which pools, style, thresholds, custom values)  
3. **Alerts** — check which targets should notify at warning and/or critical  

Then open **Main** and hard-refresh if colors do not appear yet.

## Documentation

Full usage notes, array/pool examples, and BTRFS free-space guidance:

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
