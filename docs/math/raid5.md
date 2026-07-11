# Profile: RAID5 (BTRFS)

---

## Math & concepts

### What it is

Chunk-level **striping with one parity** stripe. Space efficiency approaches $(N-1)/N$ on equal disks.

- Min devices: **2** (with 2 devices, mostly wasted overhead; **3+** practical)  
- Typical resiliency: **one** device failure while degraded  

### Usable capacity (estimate)

$$
U(\mathrm{RAID5}, S_1,\ldots,S_N) \approx \sum_i S_i - \max_i S_i \quad (N \ge 2)
$$

Equal disks of size $S$: $(N-1)\cdot S$.

### Free headroom after losing disk $i$

$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{after}}(i)
$$

Planning: Critical = $\max\Delta_{\mathrm{fit}}$, Warning = $2\times\max\Delta_{\mathrm{fit}}$ ([scenarios.md](scenarios.md)).

### Example: 4 × 4 TB

- Healthy: $16 - 4 = 12$ TB  
- After one loss (3 × 4 TB): $12 - 4 = 8$ TB  
- $\Delta_{\mathrm{fit}} = 4$ TB → Critical **4 T**, Warning **8 T**

### Example: 4 × 4 TB + 2 × 8 TB

- Healthy: $32 - 8 = 24$ TB  
- Lose an 8 TB: $\Delta = 8$ TB  
- Lose a 4 TB: $\Delta = 4$ TB  
- Critical **8 T**, Warning **16 T** (worst-case planning)

### Speeds (best-case bus ceiling)

- Read ≈ $N \cdot R$  
- Write ≈ $(N/4)\cdot W$ for $N \ge 3$ (intentionally rough)

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| **Suggest** | **Yes** (parity class) |
| Critical / Warning | $\max\Delta_{\mathrm{fit}}$ / $2\times\max\Delta_{\mathrm{fit}}$ |
| Help / alerts | Capacity + recovery headroom wording |

Code: profile key `raid5`, class `parity`.
