# Profile: RAID1 (and dup)

## Redundancy
Two copies of data. Typical single-disk loss still leaves a full copy online.

## Usable capacity (estimate)

\[
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
\]

Mixed sizes are more subtle in real BTRFS allocation; half-raw is a first-order estimate.

## Free threshold suggestion
**No automatic disk-size / capacity-drop free defaults.**  
Mirrors do not need array-style “evacuate the failed disk onto free space.” Free still matters for replace+rebuild, balance, or profile conversion — use **custom** free values if you want a policy alert.

## Example: 4 × 4 TB
- Usable ≈ **8 TB**

## Example: 4 × 4 TB + 2 × 8 TB (32 TB raw)
- Usable ≈ **16 TB**

## Speeds (best-case bus ceiling)
- Read ≈ \(N\cdot R\) (can fan out)
- Write ≈ \(W\) (all copies; limited by one stream class in this simple model)
