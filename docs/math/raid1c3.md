# Profile: RAID1c3

## Redundancy
Three copies. Can typically survive two device losses (layout-dependent).

## Usable capacity (estimate)

\[
U(\mathrm{RAID1c3}, S_1,\ldots,S_N) \approx \frac{1}{3}\sum_i S_i
\]

Minimum devices: **3**.

## Free threshold suggestion
Treated as **mirror** class: **no** automatic evacuate-style free defaults. Custom free optional.

## Example: 4 × 4 TB (16 TB raw)
- Usable ≈ **5.33 TB**

## Example: 4 × 4 TB + 2 × 8 TB (32 TB raw)
- Usable ≈ **10.67 TB**

## Speeds (best-case bus ceiling)
Same simple model as RAID1: read ≈ \(N\cdot R\), write ≈ \(W\).
