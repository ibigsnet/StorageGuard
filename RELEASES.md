# Storage Guard releases

## Install / update (Unraid web UI)

**Recommended:** **Apps** (Community Applications) → search **Storage Guard** → **Install** / **Update**.

**Manual / rollback:** **Plugins → Install Plugin** → paste a raw `.plg` URL → **Install**.

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable/storageguard.plg` |
| **Recommended freeze** (`2026.07.29aa`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.29aa/storageguard.plg` |
| **Stable rollback** (`2026.07.10bt`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10bt/storageguard.plg` |
| Older stable (`2026.07.10ar`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10ar/storageguard.plg` |

Manual installs must use the **raw** URL ending in `.plg` (not a GitHub repo or blob page). After install, hard-refresh the browser. If Unraid reports **same version**, you are already on that build.

Unraid plugin updates use **lexicographic `strcmp()`**, not PHP `version_compare()`.

| Form | Meaning |
|------|---------|
| `YYYY.MM.DD` | First ship that **calendar day** |
| `YYYY.MM.DDaa` | 2nd ship same day, then `ab` … `az`, `ba`, `bb`, … |

### Calendar day (do not skip)

The date in the version string is the **lab wall-clock calendar day**, not UTC and not “yesterday’s line + 1”.

| Do | Don’t |
|----|--------|
| Read **lab host** date before bumping (`date` on Unraid; timezone **America/Chicago** for this fleet) | Use the agent/CI machine UTC date if it differs from lab |
| Use **today’s** date on that clock | Invent **tomorrow** (`…14` while lab is still the 13th) |
| Same calendar day → next **two-letter** suffix (`aa`, `ab`, …) | Jump the day number to “make room” for more ships |
| If a wrong future date already shipped, **stay on that line** for strcmp and note the mistake in CHANGES — do not rewind | Mint an older date after a newer one is installed (updates will not offer) |

**Historical miss:** bare `2026.08.14` / `14aa` / `14ab` were cut while lab was still **2026-08-13** (continued a day-ahead TBN line instead of checking lab `date`). Same class of bug as keeping letter suffixes on an old day (Storage Guard once had to “roll to calendar date”).

### Other hard rules

- No hyphens in the version string.
- After the bare date, **two-letter** suffixes only — never single `a`–`z` (strcmp treats `"aa"` as **older** than `"z"`).
- Bump **only** `<!ENTITY version "…">` in the `.plg`; asset URLs use `?v=&version;`.
- Add a `###&version;` block under `<CHANGES>` in the same ship.

### Pre-ship version checklist (agents + humans)

1. On lab: `date` → record `YYYY-MM-DD` in lab TZ (America/Chicago).
2. Read current `<!ENTITY version>` on the branch you ship.
3. Same lab date as version prefix → next two-letter suffix only.
4. Lab date newer → first ship that day = bare `YYYY.MM.DD` (if it sorts after current; else `…aa`).
5. Lab date older than a mistaken future version already out → **do not rewind**; continue suffixes on the shipped date.
6. Never set version by “latest string + one day” without looking at the lab clock.


## Stable baselines (rollback targets)

When we call a build **stable**, we also pin a **Git tag** so you can reinstall that exact code later without pulling newer assets from `main`.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| [`stable-recommended-2026.07.29aa`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.29aa) | `2026.07.29aa` | **Current recommended freeze** (fleet pin with TBN / Fabric Routing / NBD 2026.08.13 line). |
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
