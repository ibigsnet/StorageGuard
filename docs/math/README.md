# BTRFS pool math (Storage Guard)

These notes describe **how Storage Guard estimates** usable capacity and free-space headroom for Unraid BTRFS pools. They are **planning estimates** (metadata ignored; mixed-size layouts simplified).

## What we calculate

| Quantity | Meaning |
|----------|---------|
| **U(P, disks)** | Estimated **usable** capacity under profile **P** with the given member sizes |
| **Δ(i)** | Free space needed so current **used** data still fits after **losing disk i**, **staying on profile P**: `U(P, all) − U(P, all without i)` |
| **Warning free** | `max(Δ)` — worst single-disk loss (earliest alert) |
| **Critical free** | `min(Δ)` — mildest single-disk loss when sizes differ (equal disks → same as Warning) |

**Used** relates to free by `Used ≈ U − Free`, so needing `Used ≤ U_after` is the same as `Free ≥ Δ`.

## What we do **not** use for free thresholds

- Array-style “largest disk evacuate” on **RAID1 / RAID1cN / dup** (mirrors keep a copy; that model does not apply)
- Measured sequential disk benchmarks (optional later)
- Speed estimates (informational only)

## Speeds (profile comparison only)

When shown, speeds are **absolute best-case ceilings from the storage path** (SATA link rate, NVMe PCIe gen × width), not lab disk sequential results. Real workloads are lower. They exist so you can **compare profiles** (e.g. RAID10 vs RAID5 writes), not to promise throughput.

## Profile pages

| Profile | Doc |
|---------|-----|
| single | [single.md](single.md) |
| RAID0 | [raid0.md](raid0.md) |
| RAID1 | [raid1.md](raid1.md) |
| RAID1c3 | [raid1c3.md](raid1c3.md) |
| RAID1c4 | [raid1c4.md](raid1c4.md) |
| RAID10 | [raid10.md](raid10.md) |
| RAID5 | [raid5.md](raid5.md) |
| RAID6 | [raid6.md](raid6.md) |

## Generic examples (not anyone’s real server)

- **Equal:** 4 × 4 TB  
- **Mixed:** 4 × 4 TB + 2 × 8 TB  

## Code

Implementation: `sg-pool-math.php` (`sg_usable_tb`, `sg_capacity_delta_tb`, `sg_pool_threshold_suggestions`, …).
