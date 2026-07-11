# Profile: RAID0

## Redundancy
None. Any device loss can destroy the stripe set.

## Usable capacity

\[
U(\mathrm{RAID0}, S_1,\ldots,S_N) = \sum_i S_i
\]

## Free threshold suggestion
No automatic recovery free suggestion (class **none**). Custom free thresholds only if you want capacity policy alerts.

## Example: 4 × 4 TB
- Usable ≈ **16 TB**

## Speeds (best-case bus ceiling)
≈ \(N\cdot R\) read / \(N\cdot W\) write for equal path ceilings \(R,W\).
