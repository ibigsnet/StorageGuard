# Profile: RAID1 (BTRFS) and DUP

---

## Math & concepts

### What BTRFS RAID1 is

BTRFS **RAID1** is **not** an N-way mirror of all disks.

Each data (or metadata) chunk is stored as **exactly two copies on two different devices**. With 2, 3, or 6 devices, every chunk still has **two** copies — not one per disk and not \(N\) copies.

- Writing a chunk only needs **two** devices.  
- Reading can use either copy; scrub/self-heal can repair from the good copy.  
- A single device loss typically leaves data available. Two losses can destroy chunks if both holders of a chunk fail.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID1: 2 copies, ~50% space, min 2 devices.

### DUP

**DUP** puts two copies on the **same** device. That can help with some media corruption, but it does **not** protect against whole-disk failure. Unraid multi-disk pools usually use multi-device profiles for data, not DUP.

### Usable capacity (estimate)

\[
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
\]

| Layout | Raw | Usable (est.) | Copies |
|--------|-----|---------------|--------|
| 2 × 2 TB | 4 TB | **2 TB** | 2 |
| 6 × 2 TB | 12 TB | **6 TB** | 2 (not 6) |
| 4 × 4 TB | 16 TB | **8 TB** | 2 |

With **6×2 TB**, usable capacity is about **6 TB**, not 2 TB. Extra drives add capacity (roughly half of each new disk), rather than more copies of the same small volume.

Mixed sizes: half-raw is a first-order bound; real usable can be lower when one disk is much larger ([btrfs-usage calculator](https://carfax.org.uk/btrfs-usage/)).

### After one disk loss (any equal-disk RAID1)

In general:

- Data usually stays **online** (surviving copy).  
- Usable capacity **drops** to half-raw of the **remaining** members.  
- With enough survivors, RAID1 can still place **two** copies — replace is optional for access.  
- Options: run degraded · remove+rebalance (if free) · replace · convert profile.

\[
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
\]

Equal disks of size \(S\): \(\Delta_{\mathrm{fit}} \approx S/2\).

The worked numbers below are for **6 × 2 TB only** (not the older 8 × 1 TB example).

### Worked example: 6 × 2 TB RAID1

Pool: **six 2 TB members**, data profile RAID1.

| Step | Calculation | Result |
|------|-------------|--------|
| Raw | \(6 \times 2\) | 12 TB |
| Healthy usable \(U\) | \(12/2\) | **6 TB** |
| After losing one 2 TB disk | five members left → \(10/2\) | **5 TB** usable |
| Fit free \(\Delta_{\mathrm{fit}}\) | \(6 - 5\) | **1 TB** |
| Planning Critical / Warning | \(\Delta\) / \(2\Delta\) | **1 T** / **2 T** |

Same pool, different free levels **before** the disk fails (`Used ≈ 6 TB − Free`):

| Free now (on 6 TB usable) | Used now | After the 2 TB disk dies | Fit on 5 TB usable? | Rebalance room? |
|---------------------------|----------|--------------------------|---------------------|-----------------|
| 1 T | 5 TB | ~0 free left | Barely | Essentially none |
| 2 T | 4 TB | ~1 T free left | Yes | Some |
| 0 | 6 TB | used 6 TB > 5 TB | **No** | N/A |

Longer walkthrough of this same layout: [scenarios.md](scenarios.md).

### Other layouts (same formulas)

| Layout | Healthy \(U\) | After one equal loss | \(\Delta_{\mathrm{fit}}\) | Critical / Warning |
|--------|---------------|----------------------|---------------------------|--------------------|
| 4 × 4 TB | 8 TB | 6 TB | 2 TB | 2 T / 4 T |
| 4 × 4 TB + 2 × 8 TB (first-order) | ~16 TB | worst ≈ 12 TB (lose 8 TB) | ~4 TB | 4 T / 8 T |

### Speeds (best-case bus ceiling)

- Read ≈ \(N\cdot R\) (can fan out across devices holding copies)  
- Write ≈ \(W\) (two copies; this simple model does not scale write with \(N\))

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
