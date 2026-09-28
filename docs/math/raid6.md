# Profile: RAID6 (BTRFS)

---

## Math & concepts

### What it is

Chunk-level striping with **two parity** syndromes. Space efficiency approaches $(N-2)/N$ on equal disks.

- Min devices: **3** (practical layouts usually **4+**)  
- Typical resiliency: **two** device failures while degraded  

### Docs to read (RAID5 / RAID6)

Same as RAID5: we compute free-space headroom for planning; profile behavior is documented upstream.

- Unraid: [Cache pools](https://docs.unraid.net/unraid-os/using-unraid-to/manage-storage/cache-pools/) · [File systems](https://docs.unraid.net/unraid-os/using-unraid-to/manage-storage/file-systems/)  
- BTRFS: [RAID56 status and practices](https://btrfs.readthedocs.io/en/latest/btrfs-man5.html#raid56-status-and-recommended-practices) · [Status](https://btrfs.readthedocs.io/en/latest/Status.html)  

See also [raid5.md](raid5.md).

### Usable capacity (estimate)

Each chunk stripes across every device that still has space, with two devices' worth of parity per stripe. Stripes keep going while three or more devices have space, so this works out to:

$$
U(\mathrm{RAID6}, S_1,\ldots,S_N) = \sum_i S_i - S_{(1)} - S_{(2)} \quad (N \ge 3)
$$

where $S_{(1)}, S_{(2)}$ are the two largest members. Equal disks of size $S$: $(N-2)\cdot S$.

| Layout | Usable |
|--------|--------|
| 4 × 4 TB | **8 TB** |
| 8 + 1 + 1 TB | **1 TB** |
| 10 + 2 + 2 + 2 TB | **4 TB** |
| 8 + 4 + 4 + 4 TB | **8 TB** |
| 6 + 4 + 2 TB | **2 TB** |

### Free headroom after losing disk $i$

$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
$$

Planning: Warning = $\max\Delta_{\mathrm{fit}}$ (largest-member loss), Critical = $\min\Delta_{\mathrm{fit}}$ (smallest-member loss) ([scenarios.md](scenarios.md)).  
Suggest uses **single-disk** Δ (not simultaneous double failure).

### Example: 4 × 4 TB

- Healthy: $16 - 8 = 8$ TB  
- After one loss: $12 - 8 = 4$ TB → $\Delta = 4$ TB  
- Warning = Critical **4 T** (equal disks)

### Example: 4 × 4 TB + 2 × 8 TB

- Healthy: $32 - 16 = 16$ TB  
- Lose an 8 TB: $24 - 8 - 4 = 12$ TB → $\Delta = 4$ TB  
- Lose a 4 TB: $28 - 16 = 12$ TB → $\Delta = 4$ TB  
- Warning = Critical **4 T**

### Speeds (best-case bus ceiling)

- Read ≈ $N \cdot R$  
- Write ≈ $(N/6)\cdot W$ for larger $N$ (very rough)

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest** | **Yes** (parity class) |
| Critical / Warning | $\max\Delta_{\mathrm{fit}}$ / $2\times\max\Delta_{\mathrm{fit}}$ |
| Help / alerts | Capacity + recovery headroom; points at Unraid/BTRFS RAID5/6 docs |
| Not claimed | Simultaneous double-failure capacity model (Suggest is single-disk Δ) |

Code: profile key `raid6`, class `parity`.
