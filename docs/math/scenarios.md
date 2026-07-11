# Free-space scenarios (BTRFS)

Worked examples for **capacity after a disk loss**.  
Same formulas as [README.md](README.md). Abstract layouts only (not a real server).

---

## Math & concepts

### Two different free numbers

| Name | Question | If free is too low… |
|------|----------|---------------------|
| **Fit free** $\Delta_{\mathrm{fit}}$ | After the worst single disk loss, **does used data still fit** on remaining usable capacity while **staying on the same profile**? | Used data is larger than post-loss $U$. Free cannot invent capacity. |
| **Rebalance free** (planning) | After that loss, is there **working room** to restore multi-copy/parity placement — remove/rebalance, replace+restripe, or convert — without sitting at 100% full? | Data may still *fit* and stay online, but recovery actions are tight. |

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

Planning rule used later by the product:

$$
\mathrm{Critical} = \max_i \Delta_{\mathrm{fit}}(i), \qquad
\mathrm{Warning} = 2 \times \max_i \Delta_{\mathrm{fit}}(i)
$$

**Used ↔ free:** $\mathrm{Used} \approx U_{\mathrm{full}} - \mathrm{Free}$.  
If free $\ge \Delta_{\mathrm{fit}}(i)$ before losing disk $i$, then $\mathrm{Used} \le U_{\mathrm{after}}(i)$.

Why $2\Delta$ for comfort? After a loss, free left is roughly $\mathrm{free}_{\mathrm{before}} - \Delta_{\mathrm{fit}}$. Keeping extra free ≈ $\Delta_{\mathrm{fit}}$ after the drop leaves rewrite room instead of landing at zero free the moment the disk dies. **Estimate**, not a kernel guarantee.

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

Planning numbers for **this 6 × 2 TB layout only**: Critical **1 T**, Warning **2 T** ($2\Delta$).

Crossing Critical does not mean “RAID1 dies.” It means that if a disk fails now, used may already exceed post-loss usable capacity.

Free is not array-style full-disk evacuate headroom. A second copy already exists on another device; free is capacity fit plus working room for rebalance/remove/convert.

---

## Worked example: 4 × 4 TB, BTRFS **RAID10**

| | |
|--|--|
| Usable | $16/2 =$ **8 TB** |
| After one loss | $12/2 =$ **6 TB** |
| $\Delta_{\mathrm{fit}}$ | **2 TB** |
| Critical / Warning (planning) | **2 T** / **4 T** |

---

## Worked example: 4 × 4 TB + 2 × 8 TB, BTRFS **RAID10**

| Loss | $U_{\mathrm{after}}$ | $\Delta_{\mathrm{fit}}$ |
|------|------------------------|---------------------------|
| Healthy $U=16$ TB | — | — |
| Worst: lose 8 TB | 12 TB | **4 TB** |
| Mild: lose 4 TB | 14 TB | **2 TB** |

Planning: Critical **4 T** ($\max\Delta$), Warning **8 T** ($2\times\max\Delta$).

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
(`crit = max Δ`, `warn = 2 × max Δ`, `apply` for mirror / RAID10 / RAID5/6).

Index: [README.md](README.md).
