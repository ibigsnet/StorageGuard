# Free-space scenarios (BTRFS)

Worked examples for **capacity after a disk loss**.  
Same formulas as [README.md](README.md). Abstract layouts only (not a real server).

---

## Math & concepts

### Capacity-fit free after one disk $i$

| Name | Question | If free is too low… |
|------|----------|---------------------|
| **Fit free** $\Delta_{\mathrm{fit}}(i)$ | After losing member $i$, **does used data still fit** on remaining usable capacity while **staying on the same profile**? | Used data is larger than post-loss $U$. Free cannot invent capacity. |

### Formulas (same-profile, one disk $i$)

$$
U_{\mathrm{full}} = U(P, S_1,\ldots,S_N)
$$
$$
U_{\mathrm{after}}(i) = U(P, \text{without } i)
$$
$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
$$

Product paint / alert rule (pools, capacity-fit profiles):

$$
\mathrm{Warning} = \max_i \Delta_{\mathrm{fit}}(i)
\quad\text{(typically lose the \emph{largest} member)}
$$
$$
\mathrm{Critical} = \min_i \Delta_{\mathrm{fit}}(i)
\quad\text{(typically lose the \emph{smallest} member)}
$$

**Used ↔ free:** $\mathrm{Used} \approx U_{\mathrm{full}} - \mathrm{Free}$.  
If free $\ge \Delta_{\mathrm{fit}}(i)$ before losing disk $i$, then $\mathrm{Used} \le U_{\mathrm{after}}(i)$.

Equal-size members ⇒ $\max\Delta=\min\Delta$ ⇒ one shared free floor (UI shows **critical** when free is at or below that floor).  
Unequal members ⇒ **warning** when free is only enough for a mild (small-disk) loss but not the worst (large-disk) loss; **critical** when free is below even the mildest single-disk $\Delta$.

### Options after a single disk loss (redundant profiles)

- Run degraded  
- Remove device + rebalance (needs free + enough devices)  
- Replace device  
- Convert profile (needs unallocated space)

### Profile cheatsheet

| Profile | Data online after 1 loss? | Fit free matters? | Rebalance free matters? |
|---------|---------------------------|-------------------|---------------------------|
| RAID1 / 1c3 / 1c4 | Usually yes | Yes | Yes |
| RAID10 | Usually yes | Yes | Yes |
| RAID5 / RAID6 | If within tolerance | Yes | Yes (see [raid5.md](raid5.md) / Unraid+BTRFS docs) |
| single / RAID0 | **No** | Policy only | N/A recovery |

---

## Worked example: 6 × 2 TB, BTRFS **RAID1**

This entire section is one layout: **six disks × 2 TB each**, data profile **RAID1**.  
(Replaces the older 8 × 1 TB walkthrough.)

### Copies and usable

| | |
|--|--|
| Members | 6 × 2 TB |
| Profile | RAID1 = **two** copies per chunk on different devices |
| Raw | $6 \times 2 =$ **12 TB** |
| Usable $U$ | $12/2 =$ **6 TB** |
| Not | 2 TB usable (one disk), and not six mirrors of the same 2 TB |

### After one of those 2 TB disks is gone (stay on RAID1)

| | |
|--|--|
| Remaining members | 5 × 2 TB |
| Usable after | $10/2 =$ **5 TB** |
| $\Delta_{\mathrm{fit}}$ | $6 - 5 =$ **1 TB** |

So on this 6 TB-usable pool, you need at least **1 TB free** before the failure for used data to still **fit** after the failure.

| Free now (while healthy, $U=6$ TB) | Used now | After that 2 TB disk dies ($U=5$ TB) | Fit? | Room to rebalance? |
|-------------------------------------|----------|----------------------------------------|------|---------------------|
| **1 TB** | 5 TB | free ~0 | **Yes (tight)** | **No** |
| **2 TB** | 4 TB | free ~1 TB | Yes | **Marginal / some** |
| **3 TB** | 3 TB | free ~2 TB | Yes | **More comfortable** |
| **0 TB** | 6 TB | used 6 TB > 5 TB usable | **No** | N/A |

Planning numbers for **this 6 × 2 TB layout only** (all members equal): Warning = Critical = **1 T** ($\Delta$ for any one loss).

Crossing Critical does not mean “RAID1 dies.” It means that if a disk fails now, used may already exceed post-loss usable capacity.

Free is not array-style full-disk evacuate headroom. A second copy already exists on another device; free is capacity-fit headroom after usable shrinks.

---

## Worked example: 4 × 4 TB, BTRFS **RAID10**

| | |
|--|--|
| Usable | $16/2 =$ **8 TB** |
| After one loss | $12/2 =$ **6 TB** |
| $\Delta_{\mathrm{fit}}$ (any one 4 TB loss) | **2 TB** |
| Warning / Critical (equal disks) | **2 T** / **2 T** |

With **~2.8 TB free** on this layout, used still fits after one loss → **OK** (not warning).

---

## Worked example: 4 × 4 TB + 2 × 8 TB, BTRFS **RAID10**

| Loss | $U_{\mathrm{after}}$ | $\Delta_{\mathrm{fit}}$ |
|------|------------------------|---------------------------|
| Healthy $U=16$ TB | — | — |
| Largest: lose 8 TB | 12 TB | **4 TB** |
| Smallest: lose 4 TB | 14 TB | **2 TB** |

Planning: **Warning 4 T** (largest-loss $\Delta$), **Critical 2 T** (smallest-loss $\Delta$).

---

## What we still do **not** claim (math limits)

- Exact free-space tree / unallocated placement  
- Metadata overhead  
- Perfect “ENOSPC never if free ≥ Warning”  

---

# What Storage Guard does

| Concept | In the plugin |
|---------|----------------|
| $\Delta_{\mathrm{fit}}$, Critical / Warning rule | **Suggest free thresholds** on Advanced pools |
| Per-disk loss rows | Settings table under each pool |
| Other profiles on same disks | Profile comparison table |
| Mirror disk-size dropdowns | **Ignored** for paint/alerts (evacuate model wrong) |
| single / RAID0 | No Suggest — Custom only |
| Alert text | Profile-class wording (mirror / RAID10 / parity / none) |

Code: `sg_pool_threshold_suggestions` in `sg-pool-math.php`  
(`warn = max Δ` largest-loss, `crit = min Δ` smallest-loss; `apply` for mirror / RAID10 / RAID5/6).

Index: [README.md](README.md).
