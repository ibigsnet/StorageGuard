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

\[
U(\mathrm{RAID1c3}, S_1,\ldots,S_N) \approx \frac{1}{3}\sum_i S_i \quad (N \ge 3)
\]

| Layout | Raw | Usable (est.) |
|--------|-----|---------------|
| 4 × 4 TB | 16 TB | **~5.33 TB** |
| 4 × 4 TB + 2 × 8 TB | 32 TB | **~10.67 TB** |

### After disk loss

Same recovery menu as RAID1: degraded mount, optional remove/rebalance/replace/convert.  
\(\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)\).  
Planning Critical / Warning: \(\max\Delta\) / \(2\times\max\Delta\) — [scenarios.md](scenarios.md).

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

Code: profile key `raid1c3`, class `mirror`.
