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

\[
U(\mathrm{RAID1c4}, S_1,\ldots,S_N) \approx \frac{1}{4}\sum_i S_i \quad (N \ge 4)
\]

| Layout | Raw | Usable (est.) |
|--------|-----|---------------|
| 4 × 4 TB | 16 TB | **~4 TB** |
| 4 × 4 TB + 2 × 8 TB | 32 TB | **~8 TB** |

### After disk loss

Degraded / remove / replace / convert — same BTRFS menu as other multi-copy profiles.  
\(\Delta_{\mathrm{fit}}\) and Critical / Warning planning rule: [scenarios.md](scenarios.md).

### Speeds (best-case bus ceiling)

Read ≈ \(N\cdot R\), write ≈ \(W\) (simple model).

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest** | **Yes** (mirror class) |
| Critical / Warning | \(\max\Delta_{\mathrm{fit}}\) / \(2\times\max\Delta_{\mathrm{fit}}\) |
| Disk-size dropdowns | **Ignored** for paint/alerts |
| Alerts | Mirror-class wording |

Code: profile key `raid1c4`, class `mirror`.
