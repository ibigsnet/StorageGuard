# Profile: single (BTRFS)

---

## Math & concepts

### What it is

**Single** = one copy of each chunk, **no striping requirement** and **no multi-device redundancy**. On multiple devices BTRFS can still place different chunks on different disks (JBOD-like span), but each chunk lives in **one** place.

Default **data** profile on multi-device filesystems in modern mkfs is often **single** (metadata default **RAID1**).

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

### Redundancy

**None for data.** Device loss destroys chunks that lived only on that device. If **metadata** is RAID1 (typical), you may still mount and discover which files are incomplete — you do not magically recover single-profile data.

### Usable capacity

\[
U(\mathrm{single}, S_1,\ldots,S_N) = \sum_i S_i
\]

### Example: 4 × 4 TB

- Usable ≈ **16 TB**  
- After losing one disk: raw left ≈ **12 TB**, but extents only on the failed disk are **lost** (not the same as RAID1/10 “copy still exists”).

### Speeds (best-case bus ceiling)

Rough upper bound ≈ \(N\cdot R\) read / \(N\cdot W\) write when work fans across devices — not guaranteed striping like RAID0.

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest free thresholds** | **No** — no recovery free model |
| Thresholds | **Custom** only (capacity policy) |
| Alerts | “None” class: free is policy; disk loss risks data |
| Paint | Uses whatever free thresholds you set |

Code: class `none` → `apply = false` in `sg_pool_threshold_suggestions`.
