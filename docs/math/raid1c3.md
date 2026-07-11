# Profile: RAID1c3 (BTRFS)

## What it is

**RAID1c3** = each chunk stored as **three copies on three different devices** (not “triple mirror of the whole pool” in the md sense — still chunk-level).

- Min devices: **3**  
- Space utilization ≈ **33%** of raw  
- Typical resiliency: **two** device failures (every chunk still has a copy if any two of its three holders fail — layout-dependent edge cases exist; plan for two)

Official: [mkfs.btrfs PROFILES](https://btrfs.readthedocs.io/en/latest/mkfs.btrfs.html#profiles).

Often used for **metadata** (or small high-value pools) while data uses RAID1 or RAID10 — higher metadata redundancy while data stays cheaper.

## Redundancy / recovery

Same BTRFS ideas as RAID1:

- Surviving copies keep data online after loss(es) within the profile’s tolerance.  
- **Replace is optional** for immediate access if remaining devices still hold enough copies and capacity.  
- **Remove** + rebalance or **replace** restores full three-copy spread and capacity.  
- Converting away from RAID1c3 frees space but lowers resiliency.

## Usable capacity (estimate)

\[
U(\mathrm{RAID1c3}, S_1,\ldots,S_N) \approx \frac{1}{3}\sum_i S_i \quad (N \ge 3)
\]

## Free threshold suggestion

**Mirror** class in Storage Guard: **no** automatic Δ-based Suggest. Capacity still shrinks when devices leave; use **Custom** free if you want policy alerts. Another full copy already exists for each chunk (until multiple failures).

## Example: 4 × 4 TB (16 TB raw)
- Usable ≈ **5.33 TB**

## Example: 4 × 4 TB + 2 × 8 TB (32 TB raw)
- Usable ≈ **10.67 TB**

## Speeds (best-case bus ceiling)
Same simple model as RAID1: read ≈ \(N\cdot R\), write ≈ \(W\) (three copies limit write fan-out in this model).
