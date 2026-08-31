# Profile: RAID1 (BTRFS) and DUP

---

## Math & concepts

### What BTRFS RAID1 is

BTRFS **RAID1** is **not** an N-way mirror of all disks.

Each data (or metadata) chunk is stored as **exactly two copies on two different devices**. With 2, 3, or 6 devices, every chunk still has **two** copies — not one per disk and not $N$ copies.

- Writing a chunk only needs **two** devices.  
- Reading can use either copy; scrub/self-heal can repair from the good copy.  
- A single device loss typically leaves data available. Two losses can destroy chunks if both holders of a chunk fail.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID1: 2 copies, ~50% space, min 2 devices.

### DUP

**DUP** puts two copies on the **same** device. That can help with some media corruption, but it does **not** protect against whole-disk failure. Unraid multi-disk pools usually use multi-device profiles for data, not DUP. Storage Guard treats DUP like RAID0/single for failed-disk paint (**Critical**), not like RAID1.

### Usable capacity (estimate)

$$
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
$$

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

$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
$$

Equal disks of size $S$: $\Delta_{\mathrm{fit}} \approx S/2$.

The worked numbers below are for **6 × 2 TB only** (not the older 8 × 1 TB example).

### Worked example: 6 × 2 TB RAID1

Pool: **six 2 TB members**, data profile RAID1.

| Step | Calculation | Result |
|------|-------------|--------|
| Raw | $6 \times 2$ | 12 TB |
| Healthy usable $U$ | $12/2$ | **6 TB** |
| After losing one 2 TB disk | five members left → $10/2$ | **5 TB** usable |
| Fit free $\Delta_{\mathrm{fit}}$ | $6 - 5$ | **1 TB** |
| Suggested Warning / Critical (equal disks) | $\max\Delta$ / $\min\Delta$ | **1 T** / **1 T** (one floor) |

Same pool, different free levels **before** the disk fails (`Used ≈ 6 TB − Free`):

| Free now (on 6 TB usable) | Used now | After the 2 TB disk dies | Fit on 5 TB usable? |
|---------------------------|----------|--------------------------|---------------------|
| 1 T | 5 TB | ~0 free left | Barely |
| 2 T | 4 TB | ~1 T free left | Yes |
| 0 | 6 TB | used 6 TB > 5 TB | **No** |

### Worked example: 3 × 8 TB RAID1 (three-way)

| Step | Calculation | Result |
|------|-------------|--------|
| Raw | $3 \times 8$ | 24 TB |
| Healthy usable $U$ | $24/2$ | **12 TB** |
| After one loss | two members left → $16/2$ | **8 TB** usable |
| $\Delta_{\mathrm{fit}}$ | $12 - 8$ | **4 TB** |
| Suggested Warning / Critical | equal members | **4 T** / **4 T** |

Free **≥ ~4 TB** before the loss ⇒ used data still fits on the remaining two-disk RAID1. Data usually stays **online** the whole time; the pool’s usable capacity just shrinks.

### 2-disk RAID1 (contrast)

| Layout | Healthy $U$ | After one loss | $\Delta_{\mathrm{fit}}$ | Suggest apply? |
|--------|---------------|----------------|---------------------------|----------------|
| 2 × 8 TB | 8 TB | remaining disk ≈ **8 TB** (full copy on survivor) | **≈ 0** | **No** soft floor |

Longer walkthroughs: [scenarios.md](scenarios.md), [threshold-guide.md](threshold-guide.md).

### Other layouts (same formulas)

| Layout | Healthy $U$ | After one equal loss | $\Delta_{\mathrm{fit}}$ | Suggested W / C |
|--------|---------------|----------------------|---------------------------|-----------------|
| 3 × 8 TB | 12 TB | 8 TB | 4 TB | 4 T / 4 T |
| 4 × 4 TB | 8 TB | 6 TB | 2 TB | 2 T / 2 T |
| 4 × 4 TB + 2 × 8 TB (first-order) | ~16 TB | worst ≈ 12 TB (lose 8 TB) | ~4 TB worst / ~2 TB mild | warn 4 T / crit 2 T |

### Speeds (best-case multi-stream ceiling)

Let $R,W$ be one device’s sequential read/write path ceiling.

| Direction | Multi-stream ideal | Single-stream (typical Unraid feel) | 6 devices |
|-----------|--------------------|-------------------------------------|-----------|
| **Read** | ≈ $N \cdot R$ | ≈ $R$ | up to ~6× / ~1× |
| **Write** | ≈ $(N/2) \cdot W$ | ≈ $W$ | up to ~3× / ~1× |

On Unraid, the pool is mounted BTRFS: one big write keeps **two** members busy; parallel jobs can light up more pairs. Full write-up: [unraid-io.md](unraid-io.md).

These multi-stream figures are **upper bounds** for comparing profiles — caching, seeks, metadata, checksums, and allocation usually land lower.

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest free thresholds** | **Yes** when $\Delta > 0$ (e.g. **3+** equal disks); **not** for typical 2-disk RAID1 ($\Delta \approx 0$) |
| Recommended Warning / Critical | $\max\Delta$ / $\min\Delta$ (equal disks ⇒ one free floor) |
| User free amounts | Fully free (Custom); disk-size evacuate floors ignored for paint |
| Paint / alerts | Soft capacity-fit only if $\Delta > 0$ and Custom empty; else your numbers. **One-disk RAID1** and **DUP** paint Critical (no whole-disk survival). |
| Alerts | Mirror wording: data usually online; free ≠ “evacuate failed disk” |

Not array-style “leave free ≥ full disk size.”  
Crossing Critical means capacity risk after a loss, not “RAID1 stopped working.”

Code: `sg_pool_threshold_suggestions` when class is `mirror`.
