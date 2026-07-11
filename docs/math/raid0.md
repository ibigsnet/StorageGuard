# Profile: RAID0 (BTRFS)

## What it is

Chunk-level **striping with no redundancy**. One copy of each chunk, spread for throughput. Space utilization ≈ **100%** of raw (minus metadata).

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

## Redundancy

**None.** Losing any device that held unique chunks can mean **permanent data loss** for those extents. Metadata should still use a redundant profile (e.g. RAID1) so the filesystem has a chance to mount and report what’s gone — but **data** on RAID0 is not recoverable from parity or mirrors.

No “run degraded and rebuild later” safety net like RAID1/10.

## Usable capacity

\[
U(\mathrm{RAID0}, S_1,\ldots,S_N) = \sum_i S_i
\]

## Free threshold suggestion

**No** automatic recovery free suggestion (class **none**). Custom free thresholds only if you want capacity **policy** alerts (not rebuild headroom).

## Example: 4 × 4 TB
- Usable ≈ **16 TB**  
- After one loss: remaining raw ≈ 12 TB, but **missing chunks are gone** — not a clean “U drops by 4 TB and everything remounts happily.”

## Speeds (best-case bus ceiling)
≈ \(N\cdot R\) read / \(N\cdot W\) write for equal path ceilings \(R,W\).
