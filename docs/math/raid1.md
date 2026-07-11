# Profile: RAID1 (BTRFS) and DUP

## What BTRFS RAID1 actually is

**Not** “N-way mirror of all disks.”

On BTRFS, **RAID1** means: each data (or metadata) chunk is stored as **exactly two copies on two different devices**. With 2, 3, or **8** devices, every chunk still has **two** copies — not N.

- Writing a chunk only needs **two** devices.  
- Reading can use either copy (scrub/self-heal can repair from the good copy).  
- **Reliable survival: one device loss**, even on a large pool. Two losses can destroy chunks whose only two copies lived on those two devices.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID1: 2 copies, ~50% space, min 2 devices.

### DUP (mentioned for completeness)

**DUP** = two copies on the **same** device. Helps some media corruption cases; **does not** protect against whole-disk failure. Unraid multi-disk pools usually use multi-device profiles for data, not DUP.

## Usable capacity (estimate)

\[
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
\]

| Layout | Raw | Usable (est.) | Copies |
|--------|-----|---------------|--------|
| 2 × 1 TB | 2 TB | **1 TB** | 2 |
| 8 × 1 TB | 8 TB | **4 TB** | 2 (not 8) |
| 4 × 4 TB | 16 TB | **8 TB** | 2 |

**Yes — with 8×1 TB, the data pool is about 4 TB, not 1 TB.** More drives add capacity (about half of each new disk), not more copies of the same small volume.

Mixed sizes: half-raw is a first-order bound; real usable can be lower when one disk is much larger ([btrfs-usage calculator](https://carfax.org.uk/btrfs-usage/)).

## After one disk loss

- Data usually stays **online** (surviving copy).  
- Usable capacity **drops** (half-raw on remaining members).  
- With **N ≥ 3** after the loss, RAID1 can still place **two** copies on survivors — replace is **optional**, not required for “RAID1 to exist.”  
- Options: run degraded · remove+rebalance (if free) · replace · convert profile.

See [scenarios.md](scenarios.md) for **fit free** vs **rebalance free**.

## Free threshold suggestion (Storage Guard)

**Yes — Suggest applies to RAID1 / RAID1c3 / RAID1c4** using capacity math (not array-style evacuate).

| Level | Rule | Meaning |
|-------|------|---------|
| **Critical** | \(\max_i \Delta_{\mathrm{fit}}(i)\) | Free below this: after worst single disk loss, **used may not fit** remaining usable |
| **Warning** | \(2 \times \max_i \Delta_{\mathrm{fit}}(i)\) | Extra room so after a loss you are less likely to be **full** when rebalancing / restoring 2-copy placement |

Disk-size dropdown thresholds are still **ignored** for paint/alerts on mirrors (evacuate semantics are wrong). Use **Custom** or **Suggest** free amounts.

### Example: 8 × 1 TB RAID1

| | |
|--|--|
| Healthy usable | **4 TB** |
| After one loss | **3.5 TB** |
| \(\Delta_{\mathrm{fit}}\) | **0.5 TB** |
| **Suggest Critical** | **500 G** |
| **Suggest Warning** | **1 T** |

| Free now | Used now | After 1 disk dies | Fit? | Rebalance room? |
|----------|----------|-------------------|------|-----------------|
| 500 G | 3.5 TB | ~0 free on 3.5 TB usable | Barely | Essentially none |
| 1 T | 3.0 TB | ~0.5 T free | Yes | Some |
| 0 | 4.0 TB | used 4 TB > 3.5 TB usable | **No** | N/A |

Crossing **Critical (500 G)** does **not** mean “RAID1 stops working.” It means capacity math says a disk failure may leave **too much used data for the remaining pool size**.  
You do **not** need ~1 TB free for “array evacuate of the whole disk.” You need free so **used ≤ post-loss U**, plus extra if you want comfortable rebalance.

### Example: 4 × 4 TB RAID1

| | |
|--|--|
| Healthy | 8 TB |
| After one loss | 6 TB |
| \(\Delta_{\mathrm{fit}}\) | **2 TB** |
| Suggest Critical / Warning | **2 T** / **4 T** |

### Example: 4 × 4 TB + 2 × 8 TB (first-order)

| | |
|--|--|
| Healthy | ~16 TB |
| \(\Delta_{\mathrm{fit}}\) worst (lose 8 TB) | ~4 TB |
| Suggest Critical / Warning | **4 T** / **8 T** |

## Speeds (best-case bus ceiling)

- Read ≈ \(N\cdot R\) (can fan out across devices holding copies)  
- Write ≈ \(W\) (two copies; this simple model does not scale write with N)
