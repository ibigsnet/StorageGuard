# Profile: RAID1 (BTRFS) and DUP

## What BTRFS RAID1 actually is

**Not** “N-way mirror of all disks.”

On BTRFS, **RAID1** means: each data (or metadata) chunk is stored as **exactly two copies on two different devices**. With 2, 3, or 10 devices, every chunk still has **two** copies — not N.

- Writing a chunk only needs **two** devices.  
- Reading can use either copy (scrub/self-heal can repair from the good copy).  
- **Reliable survival: one device loss**, even on a large pool. Two losses can destroy chunks whose only two copies lived on those two devices.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID1: 2 copies, ~50% space, min 2 devices.

### DUP (mentioned for completeness)

**DUP** = two copies on the **same** device. Helps some media corruption cases; **does not** protect against whole-disk failure. Unraid multi-disk pools usually use multi-device profiles for data, not DUP.

## Redundancy after a disk loss

- Data remains available from the surviving copy (for chunks that had a copy on the failed disk).  
- With **N ≥ 3** remaining devices still online after one loss, BTRFS can **continue placing two copies** on the survivors — full RAID1 semantics without an immediate replacement.  
- **Replace** restores raw capacity and spreads load; it is not required merely “to have RAID1 work again” if enough devices remain.  
- **Remove** + rebalance shrinks the pool if free space allows.

## Usable capacity (estimate)

\[
U(\mathrm{RAID1}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i
\]

Mixed sizes: half-raw is a first-order bound; real usable can be lower when one disk is much larger (see [btrfs-usage calculator](https://carfax.org.uk/btrfs-usage/)). Example intuition: 2×4 TB + 1×8 TB RAID1 often tops out near **8 TB** usable, not 16/2 in a naive way if allocation cannot pair free space.

## Free threshold suggestion (Storage Guard)

**No automatic disk-size / capacity-drop free defaults** (mirror class).

Why: after one disk dies, **used data is already fully present** on remaining copies. The Unraid-array “leave free ≥ largest disk so you can rebuild onto the array” model does **not** apply.

Capacity **does** shrink roughly by half the lost member’s contribution under the half-raw model (`Δ ≈ S_i / 2` for equal disks), so free still matters for:

- growing the pool later,  
- **replace/rebalance** working space,  
- **profile conversion**,  
- avoiding ENOSPC on a tight pool.

Use **Custom** free thresholds if you want a policy alert — not Suggest-from-Δ.

## Example: 4 × 4 TB
- Usable ≈ **8 TB**  
- After one loss (3 × 4 TB): usable ≈ **6 TB** (capacity drop, not “total data loss”)

## Example: 4 × 4 TB + 2 × 8 TB (32 TB raw)
- Usable ≈ **16 TB** (first-order)

## Speeds (best-case bus ceiling)
- Read ≈ \(N\cdot R\) (can fan out across devices holding copies)  
- Write ≈ \(W\) (two copies; this simple model does not scale write with N)
