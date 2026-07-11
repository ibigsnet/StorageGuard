# Profile: RAID1 (BTRFS) and DUP

---

## Math & concepts

### What BTRFS RAID1 actually is

**Not** “N-way mirror of all disks.”

On BTRFS, **RAID1** means: each data (or metadata) chunk is stored as **exactly two copies on two different devices**. With 2, 3, or **8** devices, every chunk still has **two** copies — not N.

- Writing a chunk only needs **two** devices.  
- Reading can use either copy (scrub/self-heal can repair from the good copy).  
- **Reliable survival: one device loss**, even on a large pool. Two losses can destroy chunks whose only two copies lived on those two devices.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID1: 2 copies, ~50% space, min 2 devices.

### DUP (completeness)

**DUP** = two copies on the **same** device. Helps some media corruption cases; **does not** protect against whole-disk failure. Unraid multi-disk pools usually use multi-device profiles for data, not DUP.

### Usable capacity (estimate)

\[
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
\]

| Layout | Raw | Usable (est.) | Copies |
|--------|-----|---------------|--------|
| 2 × 1 TB | 2 TB | **1 TB** | 2 |
| 8 × 1 TB | 8 TB | **4 TB** | 2 (not 8) |
| 4 × 4 TB | 16 TB | **8 TB** | 2 |

**Yes — with 8×1 TB, the data pool is about 4 TB, not 1 TB.** More drives add capacity (~half of each new disk), not more copies of the same small volume.

Mixed sizes: half-raw is a first-order bound; real usable can be lower when one disk is much larger ([btrfs-usage calculator](https://carfax.org.uk/btrfs-usage/)).

### After one disk loss

- Data usually stays **online** (surviving copy).  
- Usable capacity **drops** (half-raw on remaining members).  
- With enough survivors, RAID1 can still place **two** copies — replace is **optional** for “RAID1 to exist.”  
- Options: run degraded · remove+rebalance (if free) · replace · convert profile.

\[
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
\]

Equal disks of size \(S\): \(\Delta_{\mathrm{fit}} \approx S/2\).

### Example: 8 × 1 TB RAID1

| | |
|--|--|
| Healthy usable | **4 TB** |
| After one loss | **3.5 TB** |
| \(\Delta_{\mathrm{fit}}\) | **0.5 TB** |
| Planning Critical / Warning | **500 G** / **1 T** (\(2\Delta\)) |

| Free now | Used now | After 1 disk dies | Fit? | Rebalance room? |
|----------|----------|-------------------|------|-----------------|
| 500 G | 3.5 TB | ~0 free on 3.5 TB usable | Barely | Essentially none |
| 1 T | 3.0 TB | ~0.5 T free | Yes | Some |
| 0 | 4.0 TB | used 4 TB > 3.5 TB usable | **No** | N/A |

Full scenario language: [scenarios.md](scenarios.md).

### Example: 4 × 4 TB RAID1

| Healthy | After one loss | \(\Delta_{\mathrm{fit}}\) | Critical / Warning |
|---------|----------------|---------------------------|--------------------|
| 8 TB | 6 TB | **2 TB** | **2 T** / **4 T** |

### Example: 4 × 4 TB + 2 × 8 TB (first-order)

| Healthy | Worst \(\Delta_{\mathrm{fit}}\) (lose 8 TB) | Critical / Warning |
|---------|---------------------------------------------|--------------------|
| ~16 TB | ~4 TB | **4 T** / **8 T** |

### Speeds (best-case bus ceiling)

- Read ≈ \(N\cdot R\)  
- Write ≈ \(W\) (two copies; simple model does not scale write with N)

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest free thresholds** | **Yes** for RAID1 / RAID1c3 / RAID1c4 |
| Critical | \(\max\Delta_{\mathrm{fit}}\) — capacity still **fits** after worst one-disk loss |
| Warning | \(2\times\max\Delta_{\mathrm{fit}}\) — fit + rebalance comfort |
| Disk-size dropdowns | **Ignored** for paint/alerts (array evacuate model is wrong) |
| Custom free | Still works; Suggest writes Custom values |
| Settings tables | Usable now, fit free, rebalance comfort, per-member loss rows |
| Alerts | Mirror-class wording: data usually online; free ≠ “evacuate failed disk” |

Not array-style “leave free ≥ full disk size.”  
Crossing Critical means capacity risk after a loss, not “RAID1 stopped working.”

Code: `sg_pool_threshold_suggestions` when class is `mirror`.
