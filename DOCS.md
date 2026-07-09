# Storage Guard — Documentation

How thresholds, Main-tab coloring, and alerts work—plus examples for the array and BTRFS pools.

For install steps and a short overview, see [README.md](README.md).

---

## What it does

Storage Guard watches **remaining free space** on:

- the **array** (data disks), and  
- each **pool** Unraid reports (often named `cache`, or names you assigned)

You set a **Warning** and/or **Critical** free-space threshold for each. When free space falls to or below a threshold, Storage Guard colors that target’s **total free space bar** on the Main tab (the Free usage bar for the array totals row, or for a pool’s data free space—not the whole page):

| Level | Main tab (total free space bar) | Alerts (if enabled) |
|--------|----------------------------------|---------------------|
| Warning | Colored **yellow** | Unraid warning notification |
| Critical | Colored **red** | Unraid alert notification |

How the bar is colored (outline vs solid fill) is a separate setting per array/pool—see [Highlight styles](#highlight-styles-main).

**None** (or a blank custom field) means that level is not used.

If **both** Warning and Critical are set, the **lower free-space amount** is always treated as critical (more severe), and the **higher** as warning—order in the form does not matter.

**Example:** Warning `8T`, Critical `2T`  
- Free above 8T → free bar looks normal  
- Free at or below 8T → free bar warning (yellow)  
- Free at or below 2T → free bar critical (red)

Units can be whatever fits your setup (`26T`, `7.5T`, `500G`, `1.5T`, …)—not only terabytes.

Coloring on Main and notifications are **independent**: you can color without alerting, alert without coloring, or both.

---

## Array thresholds

Dropdowns list **unique sizes of your array data disks** (parity and pools are not included), largest first.

**Defaults (first use):**

- Warning → size of your **largest data disk**  
- Critical → **None** (you opt in)

### Why “largest disk” is a useful default

If a data disk fails, you often want enough free space elsewhere to rebuild, evacuate, or reshuffle data. Matching Warning to the largest data disk is a common starting point: below that free space, a full-size failure may leave you short.

### Mixed sizes

Example array (data only): 8T, 8T, 8T, 4T, 2T

- **Warning = 8T** — free space is less than one large disk; replacing/evacuating a large disk may force a purchase  
- **Critical = 2T** — free space is less than your smallest data disk; even a small failure may leave no room to move data  

You can set Critical higher (e.g. 4T) if a particular drive worries you and you want an earlier red flag.

### Custom values

Use **Custom free-space values** when the right number is not a disk size—for example `7.5T` or `500G`. Accepted forms include `1.5T`, `7.5T`, `500G`, `26T`. Leave a field blank for None.

---

## Pool thresholds

Pools are detected from Unraid (not hard-coded names). The default pool is usually **`cache`**; extra pools use the names you gave them.

For each pool you can:

- Include it in Main coloring (All / individual checkboxes)  
- Set its own **highlight style** (Outline or Solid)  
- Use **member disk sizes** or **custom free-space values**  
- Enable Warning / Critical **alerts** separately  

**Defaults:** Warning = largest member of that pool; Critical = None.

### Why free space matters on BTRFS pools

Unraid pools are often BTRFS. BTRFS is not classic mdadm RAID: it allocates **chunks**, supports **mixed disk sizes**, and can use different profiles for data and metadata (RAID1, RAID10, RAID5/6, single, …).

After a drive fails, the pool can stay online **degraded**. Restoring full redundancy usually means replace + rebalance (or a profile change). **Rebalance needs free space**—often on the order of the data that must be rewritten (roughly “size of what was on the failed device,” plus overhead).

So the same idea as the array applies: thresholds tell you *before* a failure whether you still have room to recover without an emergency purchase.

Useful Unraid UI: **Main → click the pool name → Balance Status**  
(direct link form: `http://your-server/Main/Device?name=yourpoolname`)

### BTRFS profiles (short reference)

| Profile | Rough usable | Failure tolerance (typical) |
|---------|----------------|-----------------------------|
| single / RAID0 | High | None — any loss can mean data loss |
| RAID1 | ~50% | 1 device |
| RAID1c3 / RAID1c4 | ~33% / ~25% | 2 / 3 devices |
| RAID10 | ~50% | Layout-dependent (often 1+) |
| RAID5 | ~(N−1)/N | 1 device |
| RAID6 | ~(N−2)/N | 2 devices |

When a device fails, the pool goes **degraded** but can stay usable. Full recovery needs either:

1. Replace the device and rebalance / replace, or  
2. Sometimes convert profile (e.g. RAID10 → RAID5) to free capacity—**conversion itself needs free space**

**Mixed sizes:** BTRFS can combine e.g. 4T and 8T drives; larger drives hold more chunks. Larger failures need more free space to re-mirror or re-stripe.

### Example: 4×4TB + 2×8TB (~32 TB raw)

Approximate usable capacity (varies with metadata and allocation):

| Profile | ~Usable | Notes |
|---------|---------|--------|
| RAID0 | ~32 TB | Max space/speed, no safety |
| RAID1 / RAID10 | ~16 TB | Mirroring / striped mirrors |
| RAID5 | ~higher | One parity |
| RAID6 | ~lower than RAID5 | Two parity |

**Lose a 4T member:** rebalance often wants on the order of **~4T free** (plus overhead).  
**Lose an 8T member:** plan for **more** free space (often several TB more).  
A conservative pair for a busy mixed pool might be Warning in the **10–12T** free range and Critical around **6–8T** free—tune to your used capacity and risk tolerance.

**Rules of thumb**

- **Warning** ≥ size of the **largest member** (plus a little headroom if you rebalance often)  
- **Critical** high enough that you can still remove a failed device / start recovery without the pool filling solid  
- Always keep backups; a degraded pool is not the time to discover bitrot  

For detailed speed/capacity tables with mixed drives, this community spreadsheet is useful:  
https://docs.google.com/spreadsheets/d/1_hyQBpp4EpSqxYUCarDHSfYkRkGiAHMIHbHz4uuAZHs/edit?usp=sharing

---

## Highlight styles (Main)

Each target (array, and every pool) has its own style:

| Style | Effect |
|--------|--------|
| **Outline** (default) | Keeps Unraid’s green free fill; yellow/red outline pulses around the free bar |
| **Solid** | Free bar fill becomes yellow or red |

Array can be Outline while `cache` is Solid, and so on.

---

## Alerts

On the settings page, a small matrix of **checkboxes** chooses who gets notifications:

- Rows: Array, each pool  
- Columns: Warning, Critical  

Checked = send an Unraid notification when that target hits that level.  
Nothing checked for a row = silent for that target.  
Alerts use the **same free-space thresholds** as coloring (including custom values). They do **not** require Main coloring to be on.

---

## How coloring works (technical, brief)

- Config is stored under `/boot/config/plugins/StorageGuard/`  
- A small script on Main loads config + live free space and paints the **Free** usage bars (array totals row; pool **Data Partition** free—not “Pool of N devices”, not Internal Boot)  
- Alerts are evaluated on a timer and use Unraid’s normal notify system  

Hard-refresh Main after install or update if styles look stale.

---

## Troubleshooting

| Symptom | What to try |
|---------|-------------|
| No colors on Main | Hard-refresh Main; confirm coloring is Yes for array/pools; confirm free space is actually at/below a threshold |
| Wrong pool / no pool color | Confirm the pool is checked under “Which pools to color”; open the pool page and verify free space |
| Alerts never fire | Check the alert matrix; confirm thresholds are set; Unraid notification settings must allow warnings/alerts |
| Settings look empty | Array/pool details hide when Coloration is **No**—turn Yes to edit thresholds (saved values still apply to alerts) |

Config path: `/boot/config/plugins/StorageGuard/StorageGuard.cfg`  
Plugin files: `/usr/local/emhttp/plugins/StorageGuard/`
