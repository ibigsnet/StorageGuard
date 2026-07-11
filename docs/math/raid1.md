# Profile: RAID1 (BTRFS) and DUP

---

## Math & concepts

### Definition

BTRFS **RAID1** stores each data (or metadata) chunk as **exactly two copies on two different devices**, independent of pool size \(N\).

| Property | Value |
|----------|--------|
| Copies per chunk | 2 (not \(N\)) |
| Min devices | 2 |
| Space utilization | ~50% of raw |
| Typical single-device loss | Data remains available from the other copy |
| Two-device loss | Can lose chunks if both holders of a chunk fail |

This differs from md/hardware “RAID1 of N disks,” which is often an N-way mirror. Official reference: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

| Operation | Behavior |
|-----------|----------|
| Write | Two devices receive the chunk |
| Read | Either copy; scrub/self-heal can repair from the good copy |

### DUP

| | |
|--|--|
| Layout | Two copies on the **same** device |
| Protects against | Some single-device media corruption |
| Does not protect against | Whole-disk failure |
| Unraid multi-disk data | Usually multi-device profiles, not DUP |

### Usable capacity

\[
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
\]

| Layout | Raw | Usable (est.) | Copies per chunk |
|--------|-----|---------------|------------------|
| 2 × 2 TB | 4 TB | 2 TB | 2 |
| 6 × 2 TB | 12 TB | 6 TB | 2 |
| 4 × 4 TB | 16 TB | 8 TB | 2 |

Adding devices increases usable capacity by about half of each new disk’s size under this first-order model. Mixed sizes: half-raw is a bound; real usable can be lower when one disk is much larger ([btrfs-usage calculator](https://carfax.org.uk/btrfs-usage/)).

### After one disk loss

| Effect | Detail |
|--------|--------|
| Data access | Usually remains online via surviving copy |
| Usable capacity | Drops to half-raw of remaining members |
| Continued RAID1 | With ≥2 survivors, two-copy placement remains possible without an immediate replacement |
| Recovery paths | Degraded operation; remove + rebalance (if free space allows); replace; profile convert |

\[
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
\]

Equal disks of size \(S\): \(\Delta_{\mathrm{fit}} \approx S/2\).

### Example: 6 × 2 TB RAID1

| State | Value |
|-------|--------|
| Healthy usable | 6 TB |
| After one loss (5 × 2 TB) | 5 TB |
| \(\Delta_{\mathrm{fit}}\) | 1 TB |
| Planning Critical / Warning | 1 T / 2 T (\(2\Delta\)) |

| Free before loss | Used before | After one loss | Capacity fit | Rebalance room |
|------------------|-------------|----------------|--------------|----------------|
| 1 T | 5 TB | ~0 free on 5 TB usable | Marginal | None |
| 2 T | 4 TB | ~1 T free | Yes | Limited |
| 0 | 6 TB | used 6 TB > 5 TB usable | No | N/A |

See also [scenarios.md](scenarios.md).

### Example: 4 × 4 TB RAID1

| Healthy | After one loss | \(\Delta_{\mathrm{fit}}\) | Critical / Warning |
|---------|----------------|---------------------------|--------------------|
| 8 TB | 6 TB | 2 TB | 2 T / 4 T |

### Example: 4 × 4 TB + 2 × 8 TB (first-order)

| Healthy | Worst \(\Delta_{\mathrm{fit}}\) (lose 8 TB) | Critical / Warning |
|---------|---------------------------------------------|--------------------|
| ~16 TB | ~4 TB | 4 T / 8 T |

### Speeds (bus-ceiling comparison model)

| Direction | Estimate |
|-----------|----------|
| Read | ≈ \(N\cdot R\) |
| Write | ≈ \(W\) (two copies; write does not scale with \(N\) in this model) |

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| Suggest free thresholds | Yes (RAID1 / RAID1c3 / RAID1c4) |
| Critical | \(\max\Delta_{\mathrm{fit}}\) — used data still fits after worst one-disk loss |
| Warning | \(2\times\max\Delta_{\mathrm{fit}}\) — fit plus rebalance headroom |
| Disk-size dropdowns | Ignored for paint/alerts (not array-style evacuate) |
| Custom free | Supported; Suggest writes Custom values |
| Settings tables | Usable now, fit free, rebalance comfort, per-member loss |
| Alerts | Mirror-class wording |

Critical is a **capacity** threshold after loss, not a signal that RAID1 has failed.

Code: `sg_pool_threshold_suggestions` when class is `mirror`.
