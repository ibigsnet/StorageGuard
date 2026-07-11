# BTRFS pool math (Storage Guard)

These notes describe **how Storage Guard estimates** usable capacity and free-space headroom for **Unraid BTRFS multi-device pools**. They are **planning estimates** (metadata ignored; mixed-size layouts simplified). They are **not** mdadm / hardware-RAID formulas.

## BTRFS is not traditional RAID

| Traditional RAID (md/hardware) | BTRFS profiles |
|--------------------------------|----------------|
| Whole-disk stripes / fixed mirror pairs | **Chunk** (block group) allocation — typically ~1 GiB groups |
| One layout for the whole array | **Data** and **metadata** can use **different** profiles |
| “RAID1 of N disks” = N copies | **RAID1** = exactly **two** copies on **different** devices (any N ≥ 2) |
| RAID10 = fixed stripe of mirrors | **RAID10** = two copies **plus striping** at chunk level (not fixed pairs) |
| Replace disk to rebuild the set | Can often **keep running degraded**, **remove** a device if free space allows, **replace**, and/or **convert** profile via balance |

Official profile table: [mkfs.btrfs — PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

### Data vs metadata

Capacity math in Storage Guard targets the pool’s **data** profile (what free space on Main is about). Metadata is usually smaller but should stay **redundant** (e.g. RAID1 / RAID1c3 even if data is RAID0/single). Our free-threshold suggestions do **not** model metadata overhead.

### Mixed device sizes

BTRFS can use uneven disks. Usable space is **not** always “sum of smallest × N”. Real allocation follows free space and profile constraints; calculators such as [carfax btrfs-usage](https://carfax.org.uk/btrfs-usage/) are useful for mutt layouts. Storage Guard uses **first-order** formulas so Settings “Suggest” stays simple and deterministic.

## What we calculate

| Quantity | Meaning |
|----------|---------|
| **U(P, disks)** | Estimated **usable** data capacity under data profile **P** with member sizes |
| **Δ(i)** | Free space needed so current **used** data still fits after **losing disk i**, **staying on the same profile P**: `U(P, all) − U(P, all without i)` |
| **Warning free** | `max(Δ)` — worst single-disk loss (earliest capacity alert) |
| **Critical free** | `min(Δ)` — mildest single-disk loss when sizes differ (equal disks → same as Warning) |

**Used** relates to free by `Used ≈ U − Free`, so needing `Used ≤ U_after` is the same as `Free ≥ Δ`.

### Same-profile Δ vs “must replace”

Δ answers only: *if this disk is gone and we stay on profile P, how much free do we need so existing used data still fits on the remaining members?*

It does **not** mean:

- you must replace the disk immediately, or  
- traditional “evacuate the failed disk’s unique contents onto free space” (that’s the Unraid **array** / largest-disk story).

On BTRFS, after a loss you may:

1. **Run degraded** — data still available if the profile’s redundancy still covers every chunk.  
2. **Remove** the bad device and rebalance onto remaining disks (**if** free space and device count still satisfy the profile).  
3. **Replace** the device (`btrfs replace` / Unraid pool replace) to restore capacity and full redundancy.  
4. **Convert** data (or metadata) profile with `btrfs balance … convert=` when another profile fits remaining disks better — needs unallocated space; **not** the same formula as Δ.

## Free threshold Suggest (product rule)

For profiles where a single disk loss **keeps data online** but **shrinks usable capacity**, Suggest uses two layers (see [scenarios.md](scenarios.md)):

| Level | Formula | Meaning |
|-------|---------|---------|
| **Critical** | \(\max_i \Delta_{\mathrm{fit}}(i)\) | Capacity **fit** after worst one-disk loss |
| **Warning** | \(2 \times \max_i \Delta_{\mathrm{fit}}(i)\) | Fit + first-order **rebalance comfort** |

| Class | Profiles | Suggest? |
|-------|----------|----------|
| **mirror** | RAID1, RAID1c3, RAID1c4 | **Yes** (fit / \(2\times\) warn) — **not** array evacuate |
| **striped_mirror** | RAID10 | **Yes** |
| **parity** | RAID5, RAID6 | **Yes** (⚠ profile stability caveats) |
| **none** | single, RAID0 | **No** — no recovery model; Custom only |

**dup** is multi-copy on one device (no disk-failure protection); treated like unknown/policy, not multi-device Suggest.

Disk-size dropdowns on **mirror** pools stay ignored for paint/alerts (evacuate semantics). Suggest writes **Custom** free amounts.

## Speeds (profile comparison only)

When shown, speeds are **absolute best-case ceilings from the storage path** (SATA link rate, NVMe PCIe gen × width), not lab disk sequential results. Real workloads are lower. They exist so you can **compare profiles** (e.g. RAID10 vs RAID1 writes), not to promise throughput.

## Scenario language (start here for “what free means”)

**[scenarios.md](scenarios.md)** — fit free vs rebalance free, **8×1 TB RAID1** walkthrough (4 TB usable, Critical **500 G**, Warning **1 T**), RAID10 mixed examples, recovery options.

## Profile pages

| Profile | Doc | Redundancy (typical) | Suggest free? |
|---------|-----|----------------------|---------------|
| single | [single.md](single.md) | None | No |
| RAID0 | [raid0.md](raid0.md) | None | No |
| RAID1 | [raid1.md](raid1.md) | 2 copies (not N) | **Yes** (fit / \(2\times\)) |
| RAID1c3 | [raid1c3.md](raid1c3.md) | 3 copies | **Yes** |
| RAID1c4 | [raid1c4.md](raid1c4.md) | 4 copies | **Yes** |
| RAID10 | [raid10.md](raid10.md) | 2 copies + striping | **Yes** |
| RAID5 | [raid5.md](raid5.md) | 1 parity (⚠ caution) | **Yes** |
| RAID6 | [raid6.md](raid6.md) | 2 parity (⚠ caution) | **Yes** |

## Generic examples (not anyone’s real server)

- **Many small equal:** 8 × 1 TB (great for “is usable 1 TB or 4 TB?”)  
- **Equal:** 4 × 4 TB  
- **Mixed:** 4 × 4 TB + 2 × 8 TB  
- **Small RAID10:** 3 × 4 TB (odd/small pools are valid on BTRFS)

## Code

Implementation: `sg-pool-math.php` (`sg_usable_tb`, `sg_capacity_delta_tb`, `sg_pool_threshold_suggestions`, …).

## Caveats (all profiles)

- Estimates ignore **metadata**, **system** chunks, **global reserve**, and **checksum** overhead.  
- **Unallocated** space can look “free on the disk” but not fully usable under a given profile until balance.  
- **ENOSPC** can appear with “space left” when allocation constraints fail — not modeled here.  
- **RAID5/6** write-hole / stability history: see kernel/docs status before relying on them.  
- Redundant profiles are **not backups** (deletes, ransomware, and multi-device disasters still need off-pool copies).
