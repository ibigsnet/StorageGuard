# Profile: RAID1c3 (BTRFS)

---

## Math & concepts

### What it is

**RAID1c3** = each chunk stored as **three copies on three different devices** (chunk-level, not “triple mirror of the whole pool” in the md sense).

- Min devices: **3**  
- Space utilization ≈ **33%** of raw  
- Typical resiliency: **two** device failures (layout-dependent edge cases exist; plan for two)

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

Often used for **metadata** while data uses RAID1 or RAID10.

### Usable capacity (estimate)

Each chunk goes to the **three** devices with the most free space. With $T_j$ = sum of the $j$ largest members:

$$
U(\mathrm{RAID1c3}) = \min\Big(\frac{\sum_i S_i}{3},\ \frac{\sum_i S_i - T_1}{2},\ \sum_i S_i - T_2\Big) \quad (N \ge 3)
$$

| Layout | Raw | Usable |
|--------|-----|--------|
| 4 × 4 TB | 16 TB | **~5.33 TB** |
| 4 × 4 TB + 2 × 8 TB | 32 TB | **~10.67 TB** |
| 8 + 1 + 1 TB | 10 TB | **1 TB** |
| 10 + 2 + 2 + 2 TB | 16 TB | **3 TB** |
| 8 + 4 + 4 + 4 TB | 20 TB | **6 TB** |
| 6 + 4 + 2 TB | 12 TB | **2 TB** |

### After disk loss

Same recovery menu as RAID1: degraded mount, optional remove/rebalance/replace/convert.  
$\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)$. With two devices left, Storage Guard counts RAID1 capacity.  
Planning: Warning = $\max\Delta$ (largest-member loss), Critical = $\min\Delta$ (smallest-member loss) — [scenarios.md](scenarios.md).  
4 × 4 TB + 2 × 8 TB: lose an 8 TB → 8 TB ($\Delta \approx 2.67$ TB); lose a 4 TB → ~9.33 TB ($\Delta \approx 1.33$ TB).

### Speeds (best-case multi-stream ceiling)

| Direction | Ideal ceiling |
|-----------|----------------|
| Read | ≈ $N \cdot R$ |
| Write | ≈ $(N/3) \cdot W$ (three copies per logical write) |

Single-stream write remains closer to ~$W$.

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest** | **Yes** (mirror class) |
| Critical / Warning | $\max\Delta_{\mathrm{fit}}$ / $2\times\max\Delta_{\mathrm{fit}}$ |
| Disk-size dropdowns | **Ignored** for paint/alerts |
| Alerts | Mirror-class wording |

Code: profile key `raid1c3`, class `mirror`.
