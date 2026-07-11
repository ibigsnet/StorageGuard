# Profile: RAID5 (BTRFS)

## Status warning

BTRFS **RAID5/6** have a long history of **write-hole** and recovery edge cases. Kernel/docs still treat them as **not production-recommended** for many setups. Prefer **RAID1 / RAID1c3 / RAID10** for important data unless you have explicitly accepted the risk and tested recovery.

Official status notes: [BTRFS Status](https://btrfs.readthedocs.io/en/latest/Status.html). mkfs warns against casual RAID5/6 use.

## What it is

Chunk-level **striping with one parity** stripe (not whole-disk md RAID5 geometry). Space efficiency approaches \((N-1)/N\) on equal disks.

- Min devices: **2** (with 2 devices, parity largely wastes space — effectively RAID1-like overhead; **3+** is the practical case)  
- Typical resiliency: **one** device failure while degraded  

## Redundancy / recovery

After one loss, data can remain available if parity reconstruction works and the filesystem mounts degraded. Restoring full redundancy usually means **replace** (or add) a device and rebalance. **Remove** without replacement needs remaining devices and free space to rebuild parity layouts — tighter than RAID1/10 “just keep two copies on survivors.”

Same-profile **Δ** still answers: *will used data fit after one member is gone if we stay on RAID5?*

## Usable capacity (estimate)

First-order mixed-size model (common “sum − largest” tables):

\[
U(\mathrm{RAID5}, S_1,\ldots,S_N) \approx \sum_i S_i - \max_i S_i \quad (N \ge 2)
\]

Equal disks of size \(S\): \((N-1)\cdot S\).

## Free headroom after losing disk \(i\) (same profile)

\[
\Delta(i) = U(\mathrm{RAID5}, \text{all}) - U(\mathrm{RAID5}, \text{without } i)
\]

Warning = \(\max \Delta\), Critical = \(\min \Delta\). Storage Guard may **Suggest** these for free thresholds.

### Example: 4 × 4 TB
- Healthy: \(16 - 4 = 12\) TB  
- After one loss (3 × 4 TB): \(12 - 4 = 8\) TB  
- **Δ = 4 TB**

### Example: 4 × 4 TB + 2 × 8 TB
- Healthy: \(32 - 8 = 24\) TB  
- After losing an 8 TB: remaining raw 24, largest 8 → \(24 - 8 = 16\) TB → **Δ = 8 TB**  
- After losing a 4 TB: remaining raw 28, largest 8 → \(28 - 8 = 20\) TB → **Δ = 4 TB**

## Speeds (best-case bus ceiling)
Simple comparison model (not a controller datasheet):

- Read ≈ \(N \cdot R\)  
- Write ≈ \((N/4)\cdot W\) for \(N \ge 3\) (parity cost; intentionally rough)
