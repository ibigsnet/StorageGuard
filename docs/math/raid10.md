# Profile: RAID10 (BTRFS)

---

## Math & concepts

### What BTRFS RAID10 actually is

**Not** classic “stripe of fixed mirror pairs” (md RAID10 / hardware RAID10).

On BTRFS, **RAID10** means:

- **Two copies** of each data chunk on **different devices**, and  
- Those copies are **striped** (split across devices) for throughput.

Chunks (~1 GiB block groups) pick devices independently. With many disks, different chunks use different device sets. That is why BTRFS can use **odd device counts** (e.g. 3 or 5) and mixed sizes more flexibly than traditional RAID10.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID10: 2 copies, striping, ~50% space, min 2 devices (kernel ≥ 5.15; older guidance often said 4).

### Redundancy

| | |
|--|--|
| **Guaranteed** | Survive **one** missing/failed device (every chunk still has another copy). |
| **Not guaranteed** | Two failures at once — depends which devices held both copies of a given chunk. Unlike fixed-pair RAID10, you do **not** get “one from each pair” math. |
| **After one loss** | Pool can usually **mount degraded** and keep serving data. You are **not** forced to replace immediately if remaining members can hold the data. |

After a single disk loss, common options:

1. Keep running degraded  
2. Remove the device and rebalance onto remaining disks (if free space + device count allow)  
3. Replace the failed member  
4. Convert data profile via balance (needs unallocated space)

**Wiggle room:** if used data is well under post-loss usable capacity, you often have time without an emergency replace. That is what \(\Delta_{\mathrm{fit}}\) measures.

### Usable capacity (estimate)

\[
U(\mathrm{RAID10}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i \quad (N \ge 2)
\]

Real mutt layouts can leave some raw unusable; see [btrfs disk usage calculator](https://carfax.org.uk/btrfs-usage/).

### Free headroom after losing disk \(i\) (same profile)

\[
\Delta_{\mathrm{fit}}(i) = U(\mathrm{RAID10}, \text{all}) - U(\mathrm{RAID10}, \text{without } i)
\]

Planning: Critical = \(\max_i\Delta_{\mathrm{fit}}\), Warning = \(2\times\max_i\Delta_{\mathrm{fit}}\)  
(see [scenarios.md](scenarios.md)). Not “evacuate one full disk of unique data.”

### Example A — 4 × 4 TB

| State | Usable (est.) |
|-------|----------------|
| Healthy | \(16/2 = 8\) TB |
| After one loss (3 × 4 TB) | \(12/2 = 6\) TB |
| \(\Delta_{\mathrm{fit}}\) | **2 TB** |

Planning: Critical **2 T**, Warning **4 T**. Three devices remain; replace optional for capacity/redundancy, not for “having any data left.”

### Example B — 3 × 4 TB (odd count)

| State | Usable (est.) |
|-------|----------------|
| Healthy | \(12/2 = 6\) TB |
| After one loss (2 × 4 TB) | \(8/2 = 4\) TB |
| \(\Delta_{\mathrm{fit}}\) | **2 TB** |

Still online with two devices if used ≤ 4 TB.

### Example C — 4 × 4 TB + 2 × 8 TB

| Event | Usable after (est.) | \(\Delta_{\mathrm{fit}}\) |
|-------|---------------------|---------------------------|
| Healthy | 16 TB | — |
| Lose one **8 TB** | 12 TB | **4 TB** (worst) |
| Lose one **4 TB** | 14 TB | **2 TB** (mild) |

Planning: Critical **4 T**, Warning **8 T**.

### Profile conversion (education)

Converting after a loss (e.g. to RAID1 or RAID5) can **change** usable on remaining disks. That is a deliberate trade — **not** the same as \(\Delta_{\mathrm{fit}}\) for **staying RAID10**.

### Speeds (best-case bus ceiling)

With path ceiling \(R,W\) and \(N\) devices (comparison only):

- Read ≈ \(N \cdot R\)  
- Write ≈ \((N/2) \cdot W\)

### Caveats

- Guaranteed only **one** device failure for planning  
- Very full pools may not **remove** a device without adding space first  
- Metadata should still be redundant (often RAID1/RAID1c3)  
- Not a backup  

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest free thresholds** | **Yes** |
| Critical / Warning | \(\max\Delta_{\mathrm{fit}}\) / \(2\times\max\Delta_{\mathrm{fit}}\) |
| Settings tables | Per-member loss Δ, usable after loss, profile comparison |
| Alerts | RAID10 / striped-mirror wording: data usually online; free = fit + recovery wiggle room |
| Not claimed | Forced immediate replace |

Code: class `striped_mirror` in `sg_pool_threshold_suggestions`.
