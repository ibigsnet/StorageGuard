# BTRFS pool math

Planning estimates for **Unraid multi-device BTRFS pools**.  
These are **not** mdadm / hardware-RAID formulas. Metadata is ignored; mixed-size layouts are simplified.

**How to read these pages**

1. **Above the break** — pure math and BTRFS profile concepts (what the numbers *mean*).  
2. **Below the break** — what **Storage Guard** does with those numbers in the plugin.

Per-profile detail: [single](single.md) · [RAID0](raid0.md) · [RAID1](raid1.md) · [RAID1c3](raid1c3.md) · [RAID1c4](raid1c4.md) · [RAID10](raid10.md) · [RAID5](raid5.md) · [RAID6](raid6.md)  
Scenarios walkthrough: [scenarios.md](scenarios.md)  
Unraid pool I/O (single- vs multi-stream): [unraid-io.md](unraid-io.md)

---

## Math & concepts

### BTRFS is not traditional RAID

| Traditional RAID (md/hardware) | BTRFS profiles |
|--------------------------------|----------------|
| Whole-disk stripes / fixed mirror pairs | **Chunk** (block group) allocation — typically ~1 GiB groups |
| One layout for the whole array | **Data** and **metadata** can use **different** profiles |
| “RAID1 of N disks” = N copies | **RAID1** = exactly **two** copies on **different** devices (any N ≥ 2) |
| RAID10 = fixed stripe of mirrors | **RAID10** = two copies **plus striping** at chunk level (not fixed pairs) |
| Replace disk to rebuild the set | Can often **run degraded**, **remove** a device if free space allows, **replace**, and/or **convert** profile via balance |

Official profile table: [mkfs.btrfs — PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

### Data vs metadata

Capacity math targets the pool’s **data** profile (what free space on Main is about). Metadata is usually smaller but should stay **redundant** (e.g. RAID1 / RAID1c3 even if data is RAID0/single). Free-threshold math does **not** model metadata overhead.

### Mixed device sizes

BTRFS can use uneven disks. Usable space is **not** always “sum of smallest × N”. Real allocation follows free space and profile constraints; [carfax btrfs-usage](https://carfax.org.uk/btrfs-usage/) is useful for mutt layouts. First-order formulas below stay simple and deterministic.

### Quantities

| Quantity | Meaning |
|----------|---------|
| **U(P, disks)** | Estimated **usable** data capacity under data profile **P** with member sizes |
| **Δ_fit(i)** | Free space so **used** still fits after **losing disk i**, **same profile P**: `U(P, all) − U(P, all without i)` |
| **Used** | `Used ≈ U − Free` ⇒ `Used ≤ U_after` is the same as `Free ≥ Δ_fit` |

### Fit free vs rebalance free

| Name | Question | If free is too low… |
|------|----------|---------------------|
| **Fit free** \(\Delta_{\mathrm{fit}}\) | After worst single disk loss, does **used** still fit remaining usable? | Used can exceed post-loss \(U\) |
| **Rebalance free** (planning) | After that loss, is there **working room** to remove/rebalance / restore multi-copy / convert without sitting at 100% full? | Data may still fit and stay online, but recovery actions are tight |

After a loss you may: run degraded · remove+rebalance (if free) · replace · convert profile.  
**Δ_fit does not mean “you must replace the disk.”** It is not Unraid-array “evacuate unique disk contents.”

### Same-profile loss math

\[
U_{\mathrm{full}} = U(P, S_1,\ldots,S_N)
\]
\[
U_{\mathrm{after}}(i) = U(P,\text{without disk }i)
\]
\[
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
\]

First-order **product planning rule** (equal disks: Critical = \(\Delta\), Warning = \(2\Delta\)):

\[
\mathrm{Critical} = \max_i \Delta_{\mathrm{fit}}(i)
\]
\[
\mathrm{Warning} = 2 \times \max_i \Delta_{\mathrm{fit}}(i)
\]

Full walkthroughs: [scenarios.md](scenarios.md) (includes **6×2 TB RAID1** → usable **6 TB**, fit **1 T**, comfort **2 T**).

### Speeds (comparison only)

When shown, speeds are **best-case multi-stream ceilings** from the storage path (SATA rate, NVMe gen×width), not lab sequential results. For **RAID1**, ideal multi-stream write scales like \(N/2\) (each logical write uses two devices), not \(1\times W\). Single-stream writes stay nearer one-disk \(W\). Real results are lower.

### Profile summary

| Profile | Usable (first-order) | 1-disk data online? | Notes |
|---------|----------------------|---------------------|--------|
| single / RAID0 | \(\sum S_i\) | **No** | No recovery free model |
| RAID1 | \(\sum S_i / 2\) | Usually yes | **2** copies, not N |
| RAID1c3 | \(\sum S_i / 3\) | Usually yes (2 losses) | 3 copies |
| RAID1c4 | \(\sum S_i / 4\) | Usually yes (3 losses) | 4 copies |
| RAID10 | \(\sum S_i / 2\) | Usually yes | 2 copies + striping |
| RAID5 | \(\sum S_i - \max S_i\) | If within tolerance | ⚠ stability caveats |
| RAID6 | \(\sum S_i - 2\max S_i\) | If within tolerance | ⚠ stability caveats |

### Generic Examples

- **Equal mid-size:** 6 × 2 TB  
- **Equal:** 4 × 4 TB  
- **Mixed:** 4 × 4 TB + 2 × 8 TB  
- **Small RAID10:** 3 × 4 TB  

### Caveats

- Ignores metadata, system chunks, global reserve, checksum overhead  
- Unallocated space can look free on a device but not fully usable until balance  
- ENOSPC can occur with “space left” under allocation constraints  
- RAID5/6 have known stability history  
- Redundancy is **not** a backup  

---

# What Storage Guard does

Everything below is **plugin behavior**: Settings, paint, alerts, and code.

## Suggest free thresholds

For profiles where one disk loss can keep data online but **shrinks** usable capacity:

| Level | Rule | User meaning |
|-------|------|--------------|
| **Critical** | \(\max_i \Delta_{\mathrm{fit}}(i)\) | Capacity **fit** after worst one-disk loss |
| **Warning** | \(2 \times \max_i \Delta_{\mathrm{fit}}(i)\) | Fit + first-order **rebalance comfort** |

| Class | Profiles | Suggest button? |
|-------|----------|-----------------|
| mirror | RAID1, RAID1c3, RAID1c4 | **Yes** |
| striped_mirror | RAID10 | **Yes** |
| parity | RAID5, RAID6 | **Yes** (⚠ caveats in help) |
| none | single, RAID0 | **No** — Custom only |

- Suggest fills **Custom** free Warning/Critical; user clicks **Apply** to save.  
- **Disk-size** dropdowns on **mirror** pools are **ignored** for paint/alerts (array evacuate semantics are wrong).  
- Per-pool tables in Advanced pools: usable now, fit free, rebalance comfort, per-member loss, profile comparison.

## Where it shows up

| Surface | Role |
|---------|------|
| Settings → Advanced pools | Suggest button, loss table, alternate-profile table |
| Main free bars | Paint from configured free thresholds |
| Unraid notifications | Profile-class wording (mirror / RAID10 / parity / none) |
| `get-config` → `_status.pools.*.math` | Machine-readable package for UI |

## Code

`sg-pool-math.php` — `sg_usable_tb`, `sg_capacity_delta_tb`, `sg_pool_threshold_suggestions`, `sg_pool_profile_alternatives`, `sg_pool_math_package`.

## Docs map

| Doc | Focus |
|-----|--------|
| This file | Index + global math + Storage Guard product map |
| [scenarios.md](scenarios.md) | Fit vs rebalance language + worked examples |
| Profile `*.md` | Per-profile math, then Storage Guard section |
