# Profile: RAID5 (BTRFS)

---

## Math & concepts

### What it is

Chunk-level **striping with one parity** stripe. Space efficiency approaches $(N-1)/N$ on equal disks.

- Min devices: **2** (with 2 devices, mostly wasted overhead; **3+** practical)  
- Typical resiliency: **one** device failure while degraded  

### Docs to read (RAID5 / RAID6)

Storage Guard still plans free space for these profiles the same way as other multi-device layouts. For how the **profile itself** behaves on your OS and filesystem, use the vendor docs (Unraid labels BTRFS RAID5/6 **experimental** for pools; BTRFS has a dedicated RAID56 status section):

- Unraid: [Cache pools](https://docs.unraid.net/unraid-os/using-unraid-to/manage-storage/cache-pools/) · [File systems](https://docs.unraid.net/unraid-os/using-unraid-to/manage-storage/file-systems/)  
- BTRFS: [RAID56 status and practices](https://btrfs.readthedocs.io/en/latest/btrfs-man5.html#raid56-status-and-recommended-practices) · [Status](https://btrfs.readthedocs.io/en/latest/Status.html)  

This plugin’s job is free-space planning, not choosing a RAID profile for you.

### Usable capacity (estimate)

Each chunk stripes across every device that still has space, with one device's worth of parity per stripe. Stripes keep going while two or more devices have space, so this works out to exactly:

$$
U(\mathrm{RAID5}, S_1,\ldots,S_N) = \sum_i S_i - \max_i S_i \quad (N \ge 2)
$$

Equal disks of size $S$: $(N-1)\cdot S$. It holds with more than one oversized disk too (10 + 10 + 1 TB → 11 TB; 12 + 8 + 2 + 2 + 2 TB → 14 TB).

| Layout | Usable |
|--------|--------|
| 4 × 4 TB | **12 TB** |
| 8 + 1 + 1 TB | **2 TB** |
| 10 + 2 + 2 + 2 TB | **6 TB** |
| 8 + 4 + 4 + 4 TB | **12 TB** |
| 6 + 4 + 2 TB | **6 TB** |

### Free headroom after losing disk $i$

$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
$$

Planning: Warning = $\max\Delta_{\mathrm{fit}}$ (largest-member loss), Critical = $\min\Delta_{\mathrm{fit}}$ (smallest-member loss) ([scenarios.md](scenarios.md)).

### Example: 4 × 4 TB

- Healthy: $16 - 4 = 12$ TB  
- After one loss (3 × 4 TB): $12 - 4 = 8$ TB  
- $\Delta_{\mathrm{fit}} = 4$ TB → Warning = Critical **4 T** (equal disks)

### Example: 4 × 4 TB + 2 × 8 TB

- Healthy: $32 - 8 = 24$ TB  
- Lose an 8 TB: $\Delta = 8$ TB  
- Lose a 4 TB: $\Delta = 4$ TB  
- Warning **8 T**, Critical **4 T**

### Speeds (best-case bus ceiling)

- Read ≈ $N \cdot R$  
- Write ≈ $(N/4)\cdot W$ for $N \ge 3$ (intentionally rough)

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest** | **Yes** (parity class) |
| Critical / Warning | $\max\Delta_{\mathrm{fit}}$ / $2\times\max\Delta_{\mathrm{fit}}$ |
| Help / alerts | Capacity + recovery headroom; points at Unraid/BTRFS RAID5/6 docs |

Code: profile key `raid5`, class `parity`.
