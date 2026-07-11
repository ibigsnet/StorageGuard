# Profile: RAID10

## Redundancy
Striped mirrors. A single device loss often leaves data available; restoring full redundancy needs replace and/or rebalance (and free space if usable capacity shrinks).

## Usable capacity (estimate)

\[
U(\mathrm{RAID10}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i \quad (N \ge 2)
\]

## Free headroom after losing disk \(i\) (same profile)

\[
\Delta(i) = U(\mathrm{RAID10}, \text{all}) - U(\mathrm{RAID10}, \text{all without } i)
\]

Suggested free thresholds:

- **Warning** = \(\max_i \Delta(i)\) (worst disk loss)
- **Critical** = \(\min_i \Delta(i)\) (mildest disk loss; equals Warning when all members match)

### Example A — 4 × 4 TB
| State | Usable |
|-------|--------|
| Healthy | \(16/2 = 8\) TB |
| After one loss (3 × 4 TB) | \(12/2 = 6\) TB |
| **Δ** | **2 TB** free needed so used ≤ 6 TB |

Warning ≈ Critical ≈ **2 TB**.

### Example B — 4 × 4 TB + 2 × 8 TB
| Event | Usable after | **Δ** free |
|-------|--------------|------------|
| Healthy | 16 TB | — |
| Lose one **8 TB** | 12 TB | **4 TB** (worst → Warning) |
| Lose one **4 TB** | 14 TB | **2 TB** (mild → Critical) |

## Profile conversion (education, not free formula)
After a loss, converting (e.g. to RAID5) can **raise** usable on the remaining disks. That is a trade (usually write throughput), not the same as \(\Delta\) for staying RAID10. Storage Guard can list alternate \(U\) values; free **suggestions** stay tied to **same-profile** \(\Delta\).

## Speeds (best-case bus ceiling)
With path ceiling \(R,W\) and \(N\) devices:

- Read ≈ \(N \cdot R\)
- Write ≈ \((N/2) \cdot W\)
