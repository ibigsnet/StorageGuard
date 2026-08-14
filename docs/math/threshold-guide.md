# Pool free thresholds — guide & scenarios

This page is **how to choose** Warning / Critical free amounts for BTRFS pools.  
Formulas live in [scenarios.md](scenarios.md) and [README.md](README.md).  
Storage Guard **suggests** capacity-fit numbers; it does **not** lock you into largest = Warning and smallest = Critical.

---

## What “Warning” and “Critical” mean in the product

| Label | Meaning |
|-------|---------|
| **Warning** | Milder free-space floor (yellow paint / warning notification when free is at or below this amount) |
| **Critical** | More severe free-space floor (red paint / alert notification when free is at or below this amount) |

They are **not** named after “largest disk” vs “smallest disk.”  
They are **how urgent free space is** while the pool is still healthy.

### How paint ranks free amounts

Regardless of which form field you type into:

- The **higher free amount** acts as the **warning** (yellow) line  
- The **lower free amount** acts as the **critical** (red) line  

So if you set Warning = `2T` and Critical = `4T`, paint still treats **2T as red** and **4T as yellow** (lower free = more severe).  
**Tip:** keep **Warning free ≥ Critical free** as numbers (e.g. Warning `4T`, Critical `2T`) so field labels match severity.

Alerts only fire for levels you enable under **Alerts** (pool Warning / Critical checkboxes).

---

## Capacity-fit guide (optional math)

For RAID1 / RAID1cN / RAID10 / RAID5 / RAID6, Storage Guard estimates for each member $i$:

$$
\Delta_{\mathrm{fit}}(i) = U_{\mathrm{full}} - U_{\mathrm{without\ }i}
$$

| Quantity | Typical meaning |
|----------|-----------------|
| $\max_i \Delta$ | Free needed so used data still fits after losing the **largest** member |
| $\min_i \Delta$ | Free needed so used data still fits after losing the **smallest** member |
| Equal disks | $\max = \min$ — one shared free floor |

### Recommended mapping (default suggestion)

| Form field | Suggested free amount |
|------------|------------------------|
| **Warning** | $\max\Delta$ (largest-member loss) |
| **Critical** | $\min\Delta$ (smallest-member loss) |

**Suggest free thresholds** fills **Custom** with that pair. You can reverse, change, or ignore it.

### Soft default / disk-size on RAID1–RAID10–RAID5/6

- **Disk-size** dropdown floors (e.g. Warning = `7.3T` one full member) are **ignored** for paint/alerts on RAID1 / RAID10 / RAID5–6 — that is array-style “evacuate a disk,” not capacity-fit.  
- **Custom free** always wins (your explicit policy).  
- If nothing is in Custom and capacity math finds **Δ > 0**, paint soft-defaults to the recommended pair.  
- **2-disk RAID1:** after one loss the survivor still holds a full copy of used data (if used ≤ remaining disk size) → **Δ ≈ 0** → **no** soft free floor from capacity-fit alone. A flashing bar with only a disk-size Warning set was the evacuate model — clear it or use Custom if you want a free floor.

**Blank Critical does not disable Warning.** A Warning free of `7.3T` with Critical empty still paints warning whenever free ≤ 7.3T.

---

## Example Unraid notifications (wording)

### Warning (yellow)

**Subject:** `Storage Guard: Pool cache free space warning`

**Body (shape):**

> Pool 'cache' free space is 2.8T, at or below your warning free-space threshold of 4.0T. Layout: RAID10. Warning/Critical here mean free-space severity (yellow vs red), not which disk already failed. On BTRFS RAID10 … Capacity-fit guide (optional): ~4T free so used data still fits after losing the largest member; ~2T free after losing the smallest. … Warning free means free is at or below your milder free floor (4.0T) — still time to free space or adjust thresholds before the more severe floor.

### Critical (red)

**Subject:** `Storage Guard: Pool cache free space critical`

**Body (shape):** same structure; “critical free-space threshold” and “more severe free floor.”

### Recovered

**Subject:** `Storage Guard: Pool cache free space recovered`

> Pool cache free space is back above your thresholds (3.1T free). No longer at warning or critical free-space levels.

Array alerts keep **evacuate / largest data disk** wording (different model). See array help on the Settings page.

---

## Scenarios (follow the guide or not)

### A — Follow the recommendation (largest → Warning, smallest → Critical)

- **4 × 4 TB RAID10:** suggest Warning = Critical ≈ **2T** (equal disks). Free **2.8T** → OK. Free **1.5T** → critical.  
- **4 × 4 TB + 2 × 8 TB RAID10:** Warning **4T**, Critical **2T**. Free **3T** → warning (won’t fit if an 8 TB dies; still fits if a 4 TB dies). Free **1T** → critical.

### B — Reverse the mapping

Put **2T** in Warning and **4T** in Critical. Paint still yellow below 4T and red below 2T (by free amount). Field labels no longer match “mild/severe” names — avoid unless you understand ranking.

### C — Same free for both (strict)

Warning = Critical = $\max\Delta$ (e.g. both **4T**). Any free ≤ 4T is **critical** (no separate yellow band).

### D — Policy free (ignore capacity-fit)

e.g. always warn below **1T** free regardless of disk sizes. Use **Custom** (`1T` / `500G`). Capacity-fit table remains educational only.

### E — Disk-size dropdown (array-style sizes)

Pick a member size as free floor. That is **not** the same as $\Delta_{\mathrm{fit}}$ unless you chose a size that happens to match. Fine for simple policy; not the capacity-fit guide.

### F — Alerts off, paint on (or reverse)

Coloring and notifications are independent. You can paint without Unraid notifications, or notify without paint.

### G — Single / RAID0

No capacity-fit suggestion. Free thresholds are policy only (no “still online after one loss” model).

### H — Array (not a pool)

Array Warning default = largest **data** disk free (evacuate room). Different product story; pool guide does not apply.

---

## Related

| Doc | Role |
|-----|------|
| [scenarios.md](scenarios.md) | Fit math + worked examples |
| [raid10.md](raid10.md) / [raid1.md](raid1.md) | Profile notes + product behavior |
| [README.md](README.md) | Index of math docs |
| Settings → Storage Guard → Advanced pools | Suggest button + per-pool loss table |
