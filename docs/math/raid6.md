# Profile: RAID6

## Redundancy
Two parity. Typically survives two device losses while degraded.

## Usable capacity (estimate)

Equal-disk style first-order for mixed:

\[
U(\mathrm{RAID6}, S_1,\ldots,S_N) \approx \sum_i S_i - 2\cdot\max_i S_i \quad (N \ge 3)
\]

Equal disks of size \(S\): \((N-2)\cdot S\).

## Free headroom after losing disk \(i\) (same profile)

\[
\Delta(i) = U(\mathrm{RAID6}, \text{all}) - U(\mathrm{RAID6}, \text{without } i)
\]

Warning = \(\max \Delta\), Critical = \(\min \Delta\).

### Example: 4 × 4 TB
- Healthy: \(16 - 8 = 8\) TB  
- After one loss (3 × 4 TB): needs \(N \ge 3\); \(12 - 8 = 4\) TB → **Δ = 4 TB**

### Example: 4 × 4 TB + 2 × 8 TB
- Healthy: \(32 - 16 = 16\) TB  
- After losing an 8 TB: raw 24, max 8 → \(24 - 16 = 8\) TB → **Δ = 8 TB**

## Speeds (best-case bus ceiling)
- Read ≈ \(N \cdot R\)
- Write ≈ \((N/6)\cdot W\) for larger \(N\) (very rough parity cost model)
