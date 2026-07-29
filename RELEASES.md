# Storage Guard releases

## Install / update (Unraid web UI)

**Plugins → Install Plugin** → paste the raw `.plg` URL → **Install**.

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg` |
| **Stable rollback** (`2026.07.10bt`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10bt/storageguard.plg` |
| Older stable (`2026.07.10ar`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10ar/storageguard.plg` |

Must be the **raw** URL ending in `.plg` (not a GitHub repo or blob page). After install, hard-refresh the browser. If Unraid reports **same version**, you are already on that build.

Unraid plugin updates use **string version** (`YYYY.MM.DD…`) via `strcmp`. Normal day-to-day installs track **`main`**.

## Stable baselines (rollback targets)

When we call a build **stable**, we also pin a **Git tag** so you can reinstall that exact code later without pulling newer assets from `main`.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| [`stable-recommended-2026.07.10bt`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.10bt) | `2026.07.10bt` | Last known-good host build from the 2026.07.10 line (through calm RAID5/6 doc pointers). Full BTRFS capacity math + Suggest (including RAID1), Settings Appearance polish, array alerts with `diskN \| sdx (size)`, multi-stream speed notes. Prefer this over `ar` for rollback of that era. |
| [`stable-recommended-2026.07.10ar`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.10ar) | `2026.07.10ar` | Earlier baseline: Solid+Pulse over native free fill; RAID1 ignores disk-size evacuate thresholds; label cleanup. Pre–BTRFS capacity-math work. |

Asset downloads for **`2026.07.10bt`** are locked to commit `8fcb806` (tag tip `4619c07` only rewrites the plg pin). Asset downloads for **`2026.07.10ar`** are locked to commit `731bc29`.

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

After `stable-recommended-2026.07.10bt`: **array-online gate** (no alerts/paint when array stopped or in maintenance; calendar version `2026.07.29+` on `main`). Earlier line after `ar` was BTRFS pool capacity math — that work shipped through `bt`.
