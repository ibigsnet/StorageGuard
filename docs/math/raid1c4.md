# Profile: RAID1c4 (BTRFS)

---

## Math & concepts

### What it is

**RAID1c4** = each chunk stored as **four copies on four different devices**.

- Min devices: **4**  
- Space utilization ≈ **25%** of raw  
- Typical resiliency: **three** device failures (within layout assumptions)

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

Rare for bulk **data** (expensive). Sometimes chosen for critical **metadata**.

### Usable capacity (estimate)

Each chunk goes to the **four** devices with the most free space. With $T_j$ = sum of the $j$ largest members:

$$
U(\mathrm{RAID1c4}) = \min_{j=0..3} \frac{\sum_i S_i - T_j}{4 - j} \quad (N \ge 4)
$$

| Layout | Raw | Usable |
|--------|-----|--------|
| 4 × 4 TB | 16 TB | **4 TB** |
| 4 × 4 TB + 2 × 8 TB | 32 TB | **8 TB** |
| 10 + 2 + 2 + 2 TB | 16 TB | **2 TB** |
| 8 + 4 + 4 + 4 TB | 20 TB | **4 TB** |

### After disk loss

Degraded / remove / replace / convert — same BTRFS menu as other multi-copy profiles.  
With three devices left, Storage Guard counts RAID1c3 capacity. $\Delta_{\mathrm{fit}}$ and the Warning / Critical rule: [scenarios.md](scenarios.md).

### Speeds (best-case multi-stream ceiling)

| Direction | Ideal ceiling |
|-----------|----------------|
| Read | ≈ $N \cdot R$ |
| Write | ≈ $(N/4) \cdot W$ (four copies per logical write) |

Single-stream write remains closer to ~$W$.

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest** | **Yes** (mirror class) |
| Critical / Warning | $\max\Delta_{\mathrm{fit}}$ / $2\times\max\Delta_{\mathrm{fit}}$ |
| Disk-size dropdowns | **Ignored** for paint/alerts |
| Alerts | Mirror-class wording |

Code: profile key `raid1c4`, class `mirror`.
