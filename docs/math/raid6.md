# Profile: RAID6 (BTRFS)

## Status warning

Same as RAID5: BTRFS **RAID5/6** are **not** broadly recommended for production. See [BTRFS Status](https://btrfs.readthedocs.io/en/latest/Status.html). For “survive two disks” with more predictable behavior, many operators prefer **RAID1c3** (three copies) despite lower space efficiency.

## What it is

Chunk-level striping with **two parity** syndromes. Space efficiency approaches \((N-2)/N\) on equal disks.

- Min devices: **3** (practical layouts usually **4+**)  
- Typical resiliency: **two** device failures while degraded  

## Redundancy / recovery

Degraded operation after one or two losses is the design goal; real-world recovery has historically been the hard part (write hole, replace ordering). Plan backups regardless.

Δ still models **capacity fit** if you stay on RAID6 after losing one member (Suggest uses single-disk Δ, not double-failure).

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

Suggest: **Critical** = \(\max\Delta_{\mathrm{fit}}\), **Warning** = \(2\times\max\Delta_{\mathrm{fit}}\). See [scenarios.md](scenarios.md).

### Example: 4 × 4 TB
- Healthy: \(16 - 8 = 8\) TB  
- After one loss (3 × 4 TB): \(12 - 8 = 4\) TB → **Δ = 4 TB**

### Example: 4 × 4 TB + 2 × 8 TB
- Healthy: \(32 - 16 = 16\) TB  
- After losing an 8 TB: raw 24, max 8 → \(24 - 16 = 8\) TB → **Δ = 8 TB**

## Speeds (best-case bus ceiling)
- Read ≈ \(N \cdot R\)  
- Write ≈ \((N/6)\cdot W\) for larger \(N\) (very rough parity cost model)
