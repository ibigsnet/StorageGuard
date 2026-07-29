# Storage Guard releases

## Install / update (Unraid web UI)

**Plugins → Install Plugin** → paste the raw `.plg` URL → **Install**.

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg` |
| **Stable rollback** (`2026.07.10ar`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10ar/storageguard.plg` |

Must be the **raw** URL ending in `.plg` (not a GitHub repo or blob page). After install, hard-refresh the browser. If Unraid reports **same version**, you are already on that build.

Unraid plugin updates use **string version** (`YYYY.MM.DD…`) via `strcmp`. Normal day-to-day installs track **`main`**.

## Stable baselines (rollback targets)

When we call a build **stable**, we also pin a **Git tag** so you can reinstall that exact code later without pulling newer assets from `main`.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| [`stable-recommended-2026.07.10ar`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.10ar) | `2026.07.10ar` | Last known-good host build before BTRFS capacity-math work. Solid+Pulse over native free fill; RAID1 ignores disk-size evacuate thresholds; label cleanup. |

Asset downloads for the stable tag are locked to commit `731bc29` (same tree as the working host build), not live `main`.

### Roll back from a newer plugin version

1. Paste the **stable** URL above into **Plugins → Install Plugin**.  
2. Hard-refresh the browser.  
3. Optional confirm on the host:  
   `grep 'ENTITY version' /boot/config/plugins/storageguard.plg`

## How we mark a stable

1. Hosts verified on a specific plugin version.  
2. Git commit of that tree noted.  
3. Annotated tag `stable-recommended-<version>` (and optional branch `release/stable-recommended-<version>`).  
4. Plugin `raw` entity for that tag points at the **commit SHA** (or tag) so FILE URLs cannot drift to newer `main`.  
5. Row added to this file.

## Next major line of work

After `stable-recommended-2026.07.10ar`: **BTRFS pool capacity math** (usable $U$, free headroom $\Delta$ after single-disk loss, suggested warn/crit, bus-ceiling speed notes for profile comparison, Settings “Suggest”). See [docs/math/](docs/math/README.md).
