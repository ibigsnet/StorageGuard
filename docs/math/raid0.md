# Profile: RAID0 (BTRFS)

---

## Math & concepts

### What it is

Chunk-level **striping with no redundancy**. One copy of each chunk, spread for throughput. Space utilization ≈ **100%** of raw (minus metadata).

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

### Redundancy

**None.** Losing any device that held unique chunks can mean **permanent data loss** for those extents. Metadata should still use a redundant profile (e.g. RAID1) so the filesystem has a chance to mount and report damage — but **data** on RAID0 is not recoverable from parity or mirrors.

No “run degraded and rebuild later” safety net like RAID1/10.

### Usable capacity

$$
U(\mathrm{RAID0}, S_1,\ldots,S_N) = \sum_i S_i
$$

### Example: 4 × 4 TB

- Usable ≈ **16 TB**  
- After one loss: remaining raw ≈ 12 TB, but **missing chunks are gone** — not a clean “$U$ drops by 4 TB and everything remounts happily.”

### Speeds (best-case bus ceiling)

≈ $N\cdot R$ read / $N\cdot W$ write for equal path ceilings $R,W$.

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest free thresholds** | **No** — no recovery free model |
| Thresholds | Optional Custom (capacity policy only) |
| Paint | **Always Critical** on Main — losing any disk loses the pool |
| Alerts | One layout-critical notify if pool Critical (or Warning) alerts are on; not hourly |

Code: class `none` → `apply = false` in `sg_pool_threshold_suggestions`; `sg_pool_one_disk_mode` → `nonsurvival`.
