# Profile: RAID5

## Redundancy
One parity. Typically survives one device loss while degraded; restore needs replace + rebalance and free-space headroom.

## Usable capacity (estimate)

First-order mixed-size model (matches common “sum − largest” tables):

\[
U(\mathrm{RAID5}, S_1,\ldots,S_N) \approx \sum_i S_i - \max_i S_i \quad (N \ge 2)
\]

Equal disks of size \(S\): \((N-1)\cdot S\).

## Free headroom after losing disk \(i\) (same profile)

\[
\Delta(i) = U(\mathrm{RAID5}, \text{all}) - U(\mathrm{RAID5}, \text{without } i)
\]

Warning = \(\max \Delta\), Critical = \(\min \Delta\).

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
