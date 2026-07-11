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

$$
U(\mathrm{RAID6}, S_1,\ldots,S_N) \approx \sum_i S_i - 2\cdot\max_i S_i \quad (N \ge 3)
$$

Equal disks of size $S$: $(N-2)\cdot S$.

### Free headroom after losing disk $i$

$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
$$

Planning: Critical = $\max\Delta_{\mathrm{fit}}$, Warning = $2\times\max\Delta_{\mathrm{fit}}$ ([scenarios.md](scenarios.md)).  
Suggest uses **single-disk** Δ (not simultaneous double failure).

### Example: 4 × 4 TB

- Healthy: $16 - 8 = 8$ TB  
- After one loss: $12 - 8 = 4$ TB → $\Delta = 4$ TB  
- Critical **4 T**, Warning **8 T**

### Example: 4 × 4 TB + 2 × 8 TB

- Healthy: $32 - 16 = 16$ TB  
- After losing an 8 TB: $\Delta = 8$ TB  
- Critical **8 T**, Warning **16 T**

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
