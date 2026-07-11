# Profile: RAID10 (BTRFS)

## What BTRFS RAID10 actually is

**Not** classic “stripe of fixed mirror pairs” (md RAID10 / hardware RAID10).

On BTRFS, **RAID10** means:

- **Two copies** of each data chunk on **different devices**, and  
- Those copies are **striped** (split across devices) for throughput.

Chunks (~1 GiB block groups) pick devices independently. With many disks, different chunks use different device sets. That is why BTRFS can use **odd device counts** (e.g. 3 or 5) and mixed sizes more flexibly than traditional RAID10.

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles) — RAID10: 2 copies, striping, ~50% space, min 2 devices (kernel ≥ 5.15; older guidance often said 4).

## Redundancy

| | |
|--|--|
| **Guaranteed** | Survive **one** missing/failed device (every chunk still has another copy). |
| **Not guaranteed** | Two failures at once — depends which devices held both copies of a given chunk. Unlike fixed-pair RAID10, you do **not** get “one from each pair” math. |
| **After one loss** | Pool can usually **mount degraded** and keep serving data. You are **not** forced to replace a disk immediately if remaining members can hold the data. |

### You do not always have to “replace and rebuild”

After a single disk loss, common options:

1. **Keep running degraded** — full second copy is gone for chunks that lived on the failed disk; remaining copy still serves reads. Fix hardware when you can.  
2. **Remove the device** (`btrfs device remove` / Unraid remove from pool) and **rebalance** onto the remaining disks — only if **free space** and **device count** still allow RAID10 (or you convert profile first). This **shrinks** usable capacity permanently until you add space back.  
3. **Replace** the failed member to restore capacity and two-copy placement.  
4. **Convert** data profile (e.g. toward RAID1 on the remaining set) via balance if that fits your goals better — needs unallocated space; tradeoffs on write shape.

**Wiggle room:** if used data is well under the post-loss usable capacity, you often have time and options without an emergency replace. That is exactly what **Δ** is for.

## Usable capacity (estimate)

First-order model (equal or mixed — same as “half the raw sum” when layout can place pairs):

\[
U(\mathrm{RAID10}, S_1,\ldots,S_N) \approx \frac{1}{2}\sum_i S_i \quad (N \ge 2)
\]

Real mutt layouts can leave some raw unusable; see [btrfs disk usage calculator](https://carfax.org.uk/btrfs-usage/).

## Free headroom after losing disk \(i\) (same profile)

\[
\Delta(i) = U(\mathrm{RAID10}, \text{all}) - U(\mathrm{RAID10}, \text{all without } i)
\]

Suggested free thresholds (Storage Guard **Suggest** — see [scenarios.md](scenarios.md)):

- **Critical** = \(\max_i \Delta_{\mathrm{fit}}(i)\) — capacity **fit** after worst single-disk loss  
- **Warning** = \(2 \times \max_i \Delta_{\mathrm{fit}}(i)\) — fit + first-order **rebalance comfort**  
- When sizes differ, mildest loss \(\min\Delta\) still appears in the per-disk table; Suggest Critical uses the **worst** loss  

Meaning: *if free is below Critical and a worst disk dies while staying on RAID10, used data may not fit remaining usable.* It is **not** “evacuate one full disk of unique data.”

### Example A — 4 × 4 TB
| State | Usable (est.) |
|-------|----------------|
| Healthy | \(16/2 = 8\) TB |
| After one loss (3 × 4 TB) | \(12/2 = 6\) TB |
| **Δ** | **2 TB** free needed so used ≤ 6 TB |

\(\Delta_{\mathrm{fit}} = 2\) TB → Suggest **Critical 2 T**, **Warning 4 T**. After the loss you still have **three** devices and can keep RAID10 (or convert); replace is optional for capacity/redundancy restoration, not for “having any data left.”

### Example B — 3 × 4 TB (small / odd count)
| State | Usable (est.) |
|-------|----------------|
| Healthy | \(12/2 = 6\) TB |
| After one loss (2 × 4 TB) | \(8/2 = 4\) TB |
| **Δ** | **2 TB** |

Still online with two devices if used ≤ 4 TB. Fewer devices means less striping and less “wiggle” for further failures — but **replace is still not mandatory** solely because the profile is RAID10.

### Example C — 4 × 4 TB + 2 × 8 TB
| Event | Usable after (est.) | **Δ** free |
|-------|---------------------|------------|
| Healthy | 16 TB | — |
| Lose one **8 TB** | 12 TB | **4 TB** (worst → Warning) |
| Lose one **4 TB** | 14 TB | **2 TB** (mild → Critical) |

## Profile conversion (education, not free formula)

After a loss, converting (e.g. to RAID1 or RAID5) can **change** usable capacity on the remaining disks. That is a deliberate trade (space vs write pattern vs stability), **not** the same as Δ for **staying RAID10**. Storage Guard may list alternate \(U\) values for comparison; free **suggestions** stay tied to **same-profile** Δ.

## Speeds (best-case bus ceiling)

With path ceiling \(R,W\) and \(N\) devices (comparison model only):

- Read ≈ \(N \cdot R\) (striping + choice of mirror)  
- Write ≈ \((N/2) \cdot W\) (two copies + stripe)

Real sequential/random results are lower.

## Caveats specific to RAID10

- Guaranteed only **one** device failure; do not plan for two simultaneous failures.  
- Very full pools may not be able to **remove** a device without adding space first.  
- Metadata profile should still be redundant (often RAID1/RAID1c3) even when data is RAID10.  
- Not a backup.
