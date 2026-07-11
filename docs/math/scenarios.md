# Free-space scenarios (BTRFS pools)

Storage Guard free thresholds are **not** Unraid-array “evacuate the failed disk.”  
They answer **capacity and recovery wiggle room** for **BTRFS data profiles**.

This page is the **scenario language** we use in docs and in Settings. Use it with [README.md](README.md) and each profile page.

## Two different free numbers

| Name | Question | If free is too low… |
|------|----------|---------------------|
| **Fit free** \(\Delta_{\mathrm{fit}}\) | After the worst single disk loss, **does used data still fit** on remaining usable capacity while **staying on the same profile**? | Used data is larger than post-loss \(U\). You are in a corner: free space cannot invent capacity; you need delete data, add a disk, or convert profile. |
| **Rebalance free** \(\Delta_{\mathrm{rebal}}\) | After that loss, is there **working room** to restore full multi-copy (or parity) placement — remove/rebalance, replace+restripe, or convert — without sitting at 100% full? | Data may still *fit* and stay online, but remove/rebalance/convert is tight or fails with ENOSPC-style errors. |

### Formulas (same-profile, one disk \(i\))

\[
U_{\mathrm{full}} = U(P, S_1,\ldots,S_N)
\]
\[
U_{\mathrm{after}}(i) = U(P, S_1,\ldots,\widehat{S_i},\ldots,S_N)
\]
\[
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
\]

**Critical suggestion (capacity floor):**

\[
\mathrm{Critical} = \max_i \Delta_{\mathrm{fit}}(i)
\]

Meaning: if free falls below this while the pool is otherwise “full-ish,” a worst-disk loss can leave **used > \(U_{\mathrm{after}}\)**.

**Warning suggestion (rebalance comfort) — first-order product rule:**

\[
\mathrm{Warning} = 2 \times \max_i \Delta_{\mathrm{fit}}(i)
\]

(Equal disks: Warning = \(2\Delta\), Critical = \(\Delta\).)

Why \(2\Delta\)? After a loss, free *left on the pool* is roughly \(\mathrm{free}_{\mathrm{before}} - \Delta_{\mathrm{fit}}\). Keeping **extra** free ≈ \(\Delta_{\mathrm{fit}}\) after the capacity drop leaves room to rewrite second copies / restripe instead of landing at zero free the moment the disk dies. This is a **planning estimate**, not a kernel guarantee — real remove/balance needs unallocated space on the right devices and can need more or less.

**Used ↔ free:**

\[
\mathrm{Used} \approx U_{\mathrm{full}} - \mathrm{Free}
\]

After losing disk \(i\) with free \(\ge \Delta_{\mathrm{fit}}(i)\):

\[
\mathrm{Used} \le U_{\mathrm{after}}(i)
\]

---

## Worked example: 8 × 1 TB, BTRFS **RAID1**

### Copies and usable (you are not keeping 8 copies)

| | |
|--|--|
| Profile | RAID1 = **two** copies per chunk on **different** devices |
| Raw | 8 TB |
| Usable \(U\) | \(8/2 =\) **4 TB** |
| Not | 1 TB usable, and not 8 mirrors of the same 1 TB |

### After one 1 TB disk is gone (stay on RAID1)

| | |
|--|--|
| Remaining | 7 × 1 TB |
| Usable after | \(7/2 =\) **3.5 TB** |
| \(\Delta_{\mathrm{fit}}\) | \(4 - 3.5 =\) **0.5 TB** |

So:

| Free before loss | Used before | After one loss | Fit? | Room to rebalance? |
|------------------|-------------|----------------|------|---------------------|
| **0.5 TB** | 3.5 TB | Usable 3.5 TB, free ~0 | **Yes (tight)** | **No** — full; restore second copies is painful |
| **1.0 TB** | 3.0 TB | Usable 3.5 TB, free ~0.5 TB | Yes | **Marginal** — some rewrite room |
| **1.5 TB** | 2.5 TB | free ~1.0 TB | Yes | **More comfortable** |
| **0 TB** | 4.0 TB | Used 4.0 > 3.5 usable | **No** | Data no longer fits on remaining capacity |

### Storage Guard Suggest for this pool

| Level | Free | Role |
|-------|------|------|
| **Critical** | **500 G** (\(\Delta_{\mathrm{fit}}\)) | Below this: a single disk loss can make used data **not fit** on remaining RAID1 capacity |
| **Warning** | **1 T** (\(2\Delta\)) | Soft headroom so after a loss you are less likely to be **completely full** when trying to rebalance / restore 2-copy placement |

Crossing **Critical** does **not** mean “RAID1 instantly dies.” It means: *if a disk fails now, capacity math says you may already be over the post-loss usable size.*  
Crossing **Warning** means: *you are entering the zone where recovery actions have little free working space.*

### What rebalance does **not** require

You do **not** need free ≈ **1 TB full disk evacuate** (array style). The second copy of each chunk already exists on another device. Rebalance after a loss is about **writing new second copies** for chunks that lost a mirror, and/or **redistributing** — not copying an entire unique disk of unreplicated data.

---

## Worked example: 4 × 4 TB, BTRFS **RAID10**

| | |
|--|--|
| Usable | \(16/2 =\) **8 TB** |
| After one loss | \(12/2 =\) **6 TB** |
| \(\Delta_{\mathrm{fit}}\) | **2 TB** |
| Suggest Critical | **2 T** |
| Suggest Warning | **4 T** (\(2\Delta\)) |

Same story: Critical = still fits after loss; Warning = fit + planning room for remove/rebalance/convert.

---

## Worked example: 4 × 4 TB + 2 × 8 TB, BTRFS **RAID10**

| Loss | \(U_{\mathrm{after}}\) | \(\Delta_{\mathrm{fit}}\) |
|------|------------------------|---------------------------|
| Healthy \(U=16\) TB | — | — |
| Worst: lose 8 TB | 12 TB | **4 TB** |
| Mild: lose 4 TB | 14 TB | **2 TB** |

| Suggest | Free |
|---------|------|
| Critical | **4 T** (\(\max\Delta\)) |
| Warning | **8 T** (\(2\times\max\Delta\)) |

---

## Profile cheatsheet: what fails if free is too low

| Profile | Data online after 1 loss? | Fit free matters? | Rebalance free matters? | Suggest? |
|---------|---------------------------|-------------------|---------------------------|----------|
| RAID1 / 1c3 / 1c4 | Usually yes | Yes (capacity drops) | Yes (restore multi-copy / remove) | **Yes** (fit + \(2\times\) warn) |
| RAID10 | Usually yes | Yes | Yes | **Yes** |
| RAID5 / RAID6 | If within tolerance (⚠ stability) | Yes | Yes (often more painful) | **Yes** |
| single / RAID0 | **No** (unique chunks gone) | Policy only | N/A recovery | **No** |

## Options after a single disk loss (all redundant profiles)

1. **Run degraded** — data available if profile still covers chunks.  
2. **Remove** dead/empty device and rebalance onto remaining (**needs free + enough devices**).  
3. **Replace** device to restore raw capacity and full redundancy.  
4. **Convert** profile (`balance convert=`) when another profile fits remaining disks better (**needs unallocated space**).

## What we still do **not** claim

- Exact kernel free-space tree / unallocated placement  
- Metadata/system chunk overhead  
- Perfect “ENOSPC will never happen if free ≥ Warning”  
- That RAID5/6 are production-safe  

Estimates make users **fluent** in tradeoffs; backups remain mandatory.

## Implementation map

| Concept | Code / UI |
|---------|-----------|
| \(U\), \(\Delta_{\mathrm{fit}}\) | `sg_usable_tb`, `sg_capacity_delta_tb` |
| Suggest Critical / Warning | `sg_pool_threshold_suggestions` → crit = \(\max\Delta\), warn = \(2\max\Delta\) |
| Per-disk loss table | `suggest.losses[]` |
| Other profiles on same disks | `sg_pool_profile_alternatives` |
| Profile pages | [raid1.md](raid1.md), [raid10.md](raid10.md), … |
