# Storage Guard releases

## Stable baselines (rollback targets)

Unraid plugin updates use **string version** (`YYYY.MM.DD…`) and normally track **`main`**.  
When we call a build **stable**, we also pin a **Git tag** so you can reinstall that exact code later without pulling newer assets from `main`.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| [`stable-recommended-2026.07.10ar`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.10ar) | `2026.07.10ar` | Last known-good host build before BTRFS capacity-math work. Solid+Pulse over native free fill; RAID1 ignores disk-size evacuate thresholds; label cleanup. |

### Install / stay on current stable (`2026.07.10ar`)

```bash
plugin install https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10ar/storageguard.plg
```

Asset downloads for this tag are locked to commit `731bc29` (same tree as the working host build), not live `main`.

Hard-refresh Unraid’s **main page** after install. If Unraid reports **same version**, you are already on `2026.07.10ar`.

### Roll back from a newer plugin version

1. Run the stable install command above (or pick a newer stable tag when published).  
2. Hard-refresh browser.  
3. Confirm:  
   `grep 'ENTITY version' /boot/config/plugins/storageguard.plg`

### Normal updates (development / latest)

```bash
plugin install https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg
```

## How we mark a stable

1. Hosts verified on a specific plugin version.  
2. Git commit of that tree noted.  
3. Annotated tag `stable-recommended-<version>` (and optional branch `release/stable-recommended-<version>`).  
4. Plugin `raw` entity for that tag points at the **commit SHA** (or tag) so FILE URLs cannot drift to newer `main`.  
5. Row added to this file.

## Next major line of work

After `stable-recommended-2026.07.10ar`: **BTRFS pool capacity math** (usable \(U\), free headroom \(\Delta\) after single-disk loss, suggested warn/crit, bus-ceiling speed notes for profile comparison, Settings “Suggest”). See [docs/math/](docs/math/README.md).
