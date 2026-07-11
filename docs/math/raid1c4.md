# Profile: RAID1c4

## Redundancy
Four copies. Can typically survive three device losses (layout-dependent).

## Usable capacity (estimate)

\[
U(\mathrm{RAID1c4}, S_1,\ldots,S_N) \approx \frac{1}{4}\sum_i S_i
\]

Minimum devices: **4**.

## Free threshold suggestion
**Mirror** class: no automatic free defaults. Custom free optional.

## Example: 4 × 4 TB (16 TB raw)
- Usable ≈ **4 TB**

## Example: 4 × 4 TB + 2 × 8 TB (32 TB raw)
- Usable ≈ **8 TB**

## Speeds (best-case bus ceiling)
Read ≈ \(N\cdot R\), write ≈ \(W\).
