# Storage Guard — Full documentation

Detailed usage notes, BTRFS RAID deep dive, and examples. For the short Plugins-page blurb, see [README.md](README.md).

Storage Guard helps you maintain **redundancy awareness** on your Unraid array and pools.

We are enhancing the awareness of redundancy, and how much space we have pre-failure leaving us options for migration of data to other disks, should a disk fail. Prices of disks are rising, so this plugin aims to aid users in understanding that they still have space left to move data in the event of a disk failure.

These thresholds can be set to make clear to the user at first glance on the main page, that they are still safe, even after a disk failure to move data around and not have to worry about replacing the disks right away.

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

## Versioning
Unraid plugins use a **calendar version** (string-compared by the plugin manager):

| Form | Meaning |
|------|---------|
| `YYYY.MM.DD` | First release shipped that calendar day |
| `YYYY.MM.DD-N` | Same-day revision (`-1`, `-2`, …) |

**Rules when shipping:**
1. Set `<!ENTITY version "…">` in `storageguard.plg` to **today’s date** (not the project-start date).
2. Same day, another change → bump the `-N` suffix (`2026.07.09` → `2026.07.09-1`).
3. Do not keep incrementing an old day (`2026.07.06-17` is wrong if you ship on 07.09).
4. Asset `?v=` query strings and inject tags read that entity — bump only the entity.

**Update on a server that already has the plugin** (preferred over remove/reinstall):

```bash
plugin check storageguard
plugin update storageguard
```

Or Plugins tab → check for updates → Update. Settings under `/boot/config/plugins/StorageGuard/` are kept.

## Usage Notes (Array)
- Uniform disks (e.g. 3×26T data): both dropdowns show 26T.
- Mixed disks (Parity 12T, 8T×3, 4T, 2T data): choose Warning=8T, Critical=2T so you know when you no longer have space to "move data off" a failed drive of that size.
- You may even want to choose Critical=4T if you don't trust a disk in your array, or suspect a disk is on the way out. Set your threshold here to know that you still have space across the rest of the array for moving that data over.
- Use custom capacity when you want to set a value that doesn't exactly match a disk size (e.g. 7.5T or 4T for extra safety on a suspect drive).

The core idea: set thresholds so that *before* any drive fails, the Main tab already tells you whether you will still have enough space across the rest of the array (or pool) to move data off a failed drive and rebalance—without being forced to buy a replacement disk right away. Rising disk prices make having this safe headroom more valuable than ever.

## Usage Notes (Pools) — BTRFS RAID Deep Dive & Recommendations

**BTRFS RAID is not traditional mdadm RAID.** It uses a sophisticated chunk allocator that natively supports **mixed disk sizes** in one pool. Data and metadata are allocated in chunks (usually 1 GiB for data), and different profiles can be used for Data vs Metadata. 

The same core idea applies as the array: your thresholds exist so you know *in advance* whether you will still have enough total free space in the pool to rebalance or evacuate data after a drive failure—without being forced to buy a replacement disk right away. Rising drive prices make having this buffer even more valuable.

https://docs.google.com/spreadsheets/d/1_hyQBpp4EpSqxYUCarDHSfYkRkGiAHMIHbHz4uuAZHs/edit?usp=sharing

For example with 4x4TB + 2x8TB disks (base ~540/550 MB/s for 4TB, ~520/540 for 8TB):

- RAID 0: 32.0 TB usable, ~2,080 MB/s read, ~2,160 MB/s write (0 disk tolerance)
- RAID 1: 16.0 TB, ~2,080 read, ~540 write (1 disk)
- RAID 5: 24.0 TB, ~2,080 read, ~820 write (1 disk)
- RAID 6: 16.0 TB, ~2,080 read, ~547 write (2 disks)
- RAID 1c3: 10.667 TB, ~2,080 read, ~540 write (2 disks)
- RAID 1c4: 8.0 TB, ~2,080 read, ~540 write (3 disks)
- RAID 10: 16.0 TB, ~2,080 read, ~1,080 write (1+ disks)

In mixed setups (like this 4x4TB + 2x8TB pools), the slowest disk often limits stripe performance, and usable space depends on the profile's allocation rules. BTRFS does a good job balancing across unequal drives after a `btrfs balance`.

This calculates pool speeds from base single-disk numbers per RAID type (e.g. full sum for RAID0 reads/writes, limited to single for RAID1 writes, etc.). We reflect similar logic in explanations here. For your exact drives, refer to the sheet for precise numbers.

The sheet calculates pool speeds from base single-disk numbers per RAID type (e.g. full sum for RAID0 reads/writes, limited to single for RAID1 writes, etc.). Use the sheet to plug in your exact disk speeds for accurate numbers. We can use similar math in the per-pool help texts for dynamic speed impact estimates based on detected profile and your disk sizes.

### Core BTRFS RAID Concepts
- **Profiles** (set at mkfs or via balance convert):
  - **Single**: No redundancy. 100% usable. Any failure = data loss.
  - **RAID 0**: Striping only. Max space & speed. No redundancy.
  - **RAID 1**: 2 copies on different devices. ~50% usable. Tolerates 1 failure.
  - **RAID 1c3**: 3 copies. ~33% usable. Tolerates 2 failures. (3 disks minimum)
  - **RAID 1c4**: 4 copies. ~25% usable. Tolerates 3 failures. (4 disks minimum)
  - **RAID 10**: Striped + mirrored. ~50% usable. Good performance. Tolerates multiple failures (layout dependent).
  - **RAID 5**: Striping + 1 parity. ~(N-1)/N usable. Tolerates 1 failure. (3 disks minimum)
  - **RAID 6**: Striping + 2 parity. ~(N-2)/N usable. Tolerates 2 failures. (4 disks minimum)
- **Redundancy & Failure Behavior**:
  - When a disk fails the pool goes **degraded** but remains usable (reads from remaining copies/parity, writes with reduced redundancy).
  - To recover full redundancy you must either:
    1. Replace the failed device and run `btrfs replace` or `btrfs device delete missing` + rebalance, **or**
    2. Temporarily convert to a lower-redundancy profile (e.g. raid10 → raid5) to free space.
  - **Rebalancing** (`btrfs balance start -f /mnt/yourpool`) redistributes chunks. This **requires free space** roughly equal to the amount of data that needs new copies/placement.
- **Mixed-size specifics**: BTRFS will happily use 4 TB and 8 TB drives together. Larger drives simply hold more chunks. This is a big advantage over traditional RAID.

### Real-World Example: 4×4TB + 2×8TB (32 TB raw capacity)

**Possible profiles & approximate usable space** (actual numbers vary slightly with chunk allocation and metadata):

| Profile   | Approx Usable | Redundancy          | Notes |
|-----------|---------------|---------------------|-------|
| raid0     | 32 TB        | 0 failures         | Max speed & space, no safety |
| raid1     | ~16 TB       | 1 failure          | Simple mirroring |
| raid10    | ~16 TB       | 1–2 failures (layout dependent) | Striped mirrors, good perf |
| raid5     | ~28 TB       | 1 failure          | Parity, excellent space |
| raid6     | ~24 TB       | 2 failures         | Double parity |

**If you lose a 4 TB drive:**

- **RAID10**: The stripes that used that drive are now single-copy. Performance drops (less striping available). To fully rebalance and restore redundancy you typically need ~4 TB free (the data volume that was on the failed drive needs new mirror copies placed on remaining devices). You can continue operating in degraded mode.
- **RAID5**: Parity allows on-the-fly reconstruction. Rebalance still needs ~4 TB + some overhead. Conversion or full rebuild is possible.
- **RAID1**: Similar to RAID10 — ~4 TB free for rebalance.
- **After recovery options**: You can stay degraded, replace the disk, or convert profile (e.g. raid10 → raid5) to regain usable space.

**If you lose an 8 TB drive (worse case):**
- Larger device usually held more chunks → bigger rebalance requirement (often 6–8 TB free recommended).
- RAID10/RAID1: Significant performance hit and larger space need to re-mirror.
- Recommendation: Set **Warning ≈ 10–12 TB**, **Critical ≈ 6–8 TB** for this pool so you have breathing room.

**Changing profiles (example: RAID10 → RAID5)**
- RAID10 usable ≈ 16 TB → RAID5 usable ≈ 28 TB → you **gain** ~12 TB of usable capacity.
- Trade-off: you go from tolerating multiple failures (in good layout) to only 1 failure.
- **Rebalance cost**: You will need substantial free space during conversion (often 8–12+ TB recommended depending on used data) because BTRFS must rewrite chunks with the new parity layout.
- Read performance: RAID5/6 generally good for sequential; RAID10 often better for random I/O.
- Write performance: RAID5/6 has parity calculation overhead (noticeable on small random writes). RAID10 is usually snappier for mixed workloads.
- After successful conversion you have more space but should raise your warning/critical thresholds accordingly because you have less redundancy.

**Our recommendations as the plugin (general rules of thumb)**
- **Warning** should be at least the size of your **largest device** + headroom for rebalance (so you can survive losing your biggest disk and still have room to fix).
- **Critical** should be high enough that you still have space to at least remove the failed device (`btrfs device delete missing`) without the pool filling up.
- For RAID10 with mixed sizes: be especially conservative around the size of your smallest devices (they limit stripe width).
- After a failure or before converting profiles, check our colored warning — it is designed exactly to tell you "you still have room to maneuver".
- Always have a recent backup. BTRFS is great but degraded pools + rebalance is not the time to discover bitrot or other issues.

**Future enhancement (planned)**: We will detect the **actual BTRFS profile** and device list for each of your pools at runtime and give you **dynamic, pool-specific recommendations** right in the settings (e.g. "Your intelligence pool is currently RAID10 with 4×4T + 2×8T. After losing a 4T drive you will need ≈4–5 TB free to rebalance. We recommend Warning=8T, Critical=5T.").

More advanced BTRFS guidance, a built-in mini calculator, and profile-change impact estimates are coming. In the meantime the per-pool help text below (in the settings) and this README are your best reference.

**View Balance Status in Unraid**: Go to the Main tab → click the pool name → scroll to the **Balance Status** section. Direct link example: `http://your-unraid-ip/Main/Device?name=yourpoolname`

**Pro tip**: After any disk failure or profile change, run `btrfs balance start -dusage=50 /mnt/yourpool` (or similar) to clean up. Monitor with `btrfs fi usage /mnt/yourpool` and `btrfs fi show`.

## How the coloring & alerts work
Storage Guard uses lightweight client-side injection (JS + CSS) to color the *existing* Array and Pool sections directly on Unraid's Main tab.

- Your thresholds are read from the plugin config.
- The injector looks at the live free-space numbers Unraid already displays.
- It applies yellow (warning) or red (critical) to the array/pool areas when total free space drops below your safe buffer.

These thresholds can be set to make clear to you at first glance on the main page that you are still safe, even after a disk failure, to move data around and not have to worry about replacing the disks right away.

The alerts section lets you enable native Unraid notifications (System / Warning / Alert) for the same thresholds. No new dashboard is built — we enhance the awareness of the redundancy you already have so you know you still have space left to move data in the event of a disk failure.
