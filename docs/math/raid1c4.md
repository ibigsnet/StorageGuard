# Profile: RAID1c4 (BTRFS)

## What it is

**RAID1c4** = each chunk stored as **four copies on four different devices**.

- Min devices: **4**  
- Space utilization ≈ **25%** of raw  
- Typical resiliency: **three** device failures (within layout assumptions)

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

Rare for bulk **data** (expensive). Sometimes chosen for critical **metadata** on large multi-device pools.

## Redundancy / recovery

Same BTRFS recovery menu as other mirror-like profiles: degraded mount, optional replace, optional remove+rebalance if free space allows, optional profile convert. Access does not wait on a rebuild the way classic RAID rebuilds do for “the array to exist,” but **redundancy and free space** still matter.

## Usable capacity (estimate)

\[
U(\mathrm{RAID1c4}, S_1,\ldots,S_N) \approx \frac{1}{4}\sum_i S_i \quad (N \ge 4)
\]

## Free threshold suggestion

**Mirror** class: Suggest = Critical \(\max\Delta_{\mathrm{fit}}\), Warning \(2\times\max\Delta_{\mathrm{fit}}\) ([scenarios.md](scenarios.md)).

## Example: 4 × 4 TB (16 TB raw)
- Usable ≈ **4 TB**

## Example: 4 × 4 TB + 2 × 8 TB (32 TB raw)
- Usable ≈ **8 TB**

## Speeds (best-case bus ceiling)
Read ≈ \(N\cdot R\), write ≈ \(W\) (simple model).
