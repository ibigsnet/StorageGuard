# Storage Guard — Documentation

How thresholds, Main-tab coloring, and alerts work—plus examples for the array and BTRFS pools.

**Install:** Plugins → Install Plugin →  
`https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg`

**Support / source:** [github.com/ibigsnet/StorageGuard](https://github.com/ibigsnet/StorageGuard)  
**Support development:** [Patreon](https://www.patreon.com/cw/IBIGSNet) · [PayPal](https://www.paypal.com/paypalme/RifleJock)

`README.md` is only the short Unraid Plugins-list blurb (`**Name**` + one paragraph). Unraid runs it through real Markdown for the entire Plugins description—keep it tiny. This file is the full documentation.

---

## What it does

Storage Guard watches **remaining free space** on:

- the **array** (data disks), and  
- each **pool** Unraid reports (whatever names you configured)

You set a **Warning** and/or **Critical** free-space threshold for each. Thresholds mean: “how much free space is left?” Units can be whatever fits (`8T`, `500G`, `1.5T`, …)—not only terabytes.

When free space crosses a threshold, Storage Guard paints that target’s **total free space bar** on the Main tab (array totals Free bar, or a pool’s data Free bar—not the whole page):

| Free space | Main free bar | Alerts (if enabled) |
|------------|---------------|---------------------|
| **Above** all thresholds (healthy) | Normal Unraid look, **or** optional **green outline** (Outline style + “green when OK”) | Silent |
| At or below **Warning** | **Yellow** | Unraid **warning** notification |
| At or below **Critical** | **Red** | Unraid **alert** notification |
| Back **above** all thresholds after a warn/alert | Normal / optional green outline | Unraid **normal** notification (“recovered”) — same severity family as parity complete |

**None** (or a blank custom field) means that level is unused.

Coloring on Main and notifications are **independent**: you can color without alerting, alert without coloring, or both.

### Recommended threshold order

Think of free space falling over time:

1. **Warning** — larger free amount (earlier heads-up), e.g. `8T`  
2. **Critical** — smaller free amount (more severe), e.g. `2T`

**Example (recommended):** Warning `8T`, Critical `2T`

- Free above 8T → healthy (normal, or green outline if enabled)  
- Free at or below 8T → yellow (warning)  
- Free at or below 2T → red (critical)  

### If Critical free space is *higher* than Warning

That is an unusual setup (e.g. Warning `2T`, Critical `8T`). The Settings page shows a notice when this happens.

**What the plugin does:** it does **not** follow the dropdown labels literally for severity. It always ranks by **how low free space is**:

- The **lower** free-space amount → **critical (red)** on Main, and critical alerts if enabled  
- The **higher** free-space amount → **warning (yellow)** on Main, and warning alerts if enabled  

So with Warning `2T` and Critical `8T`, free space at 5T is still treated as **warning** (because 5 ≤ 8 and 5 > 2), and free at 1T is **critical**. In other words: severity follows free-space math, not which field you typed the number into.

**Tip:** keep Warning free amount **greater than** Critical free amount so the labels match what you expect.

---

## Array thresholds

Dropdowns list **unique sizes of your array data disks** (parity and pools are not included), largest first.

**Defaults (first use):**

- Warning → size of your **largest data disk** (core idea: free space left should still cover losing that disk)  
- Critical → **None** (you opt in)  

### Why “largest disk” is a useful default

If a data disk fails, you often want enough free space elsewhere to evacuate or reshuffle data. Matching Warning to the largest data disk is a common starting point: below that free space, a full-size failure may leave you short.

### Mixed sizes

Example array (data only): 8T, 8T, 8T, 4T, 2T

- **Warning = 8T** — free space is less than one large disk; evacuating a large disk may force a purchase  
- **Critical = 2T** — free space is less than your smallest data disk; even a small failure may leave no room to move data  

You can set Critical to something between those (e.g. `4T`) if a particular drive worries you—or to match the **combined size of a few questionable drives**, so free space still covers evacuating more than one at-risk disk. Critical should still be a **smaller free amount** than Warning.

### Custom values

Use **Custom free-space values** when the right number is not a disk size—for example `7.5T` or `500G`. Accepted forms include `1.5T`, `7.5T`, `500G`, `26T`. Leave a field blank for None.

---

## Pool thresholds (WIP — advanced)

> **Work in progress** (array is primary). Pool UI is **hidden by default** — open **Show advanced pool settings (WIP)**.  
> **OK to use:** custom free-space thresholds, member disk-size thresholds (except mirrors — below), Main coloring, and alerts.  
> **Profile-aware today:** alert *wording* by profile class; **mirrored pools (RAID1 / RAID1cN / dup) ignore disk-size thresholds** for paint/alerts (evacuate-room model does not apply). Custom values still apply.  
> **Not yet:** BTRFS profile-aware *suggested* thresholds / recovery math.  
> Defaults leave pools inactive until you set them.

Pools are detected live from Unraid—nothing is hard-coded. New installs often ship with a first pool named **`cache`**, but that is only a common default: the first pool and every other pool can use **any** name Unraid allows. Storage Guard lists whatever your server actually has.

For each pool you can:

- Include it in Main coloring (All / individual checkboxes)  
- Set its own **highlight style** (Outline or Solid)  
- Use **member disk sizes** or **custom free-space values**  
- Enable Warning / Critical **alerts** separately  

**Defaults:** Main pool coloring = **No**; Warning = **None**, Critical = **None** (pools are opt-in — turn on coloring and set thresholds only when you want them).

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
A conservative pair for a busy mixed pool might be Warning in the **10–12T** free range and Critical around **6–8T** free (Warning free amount still larger than Critical)—tune to your used capacity and risk tolerance.

**Rules of thumb**

- **Warning** ≥ size of the **largest member** (plus a little headroom if you rebalance often)  
- **Critical** a **smaller free amount** than Warning, still high enough that you can remove a failed device / start recovery without the pool filling solid  
- Always keep backups; a degraded pool is not the time to discover bitrot  

For detailed speed/capacity tables with mixed drives, this community spreadsheet is useful:  
https://docs.google.com/spreadsheets/d/1_hyQBpp4EpSqxYUCarDHSfYkRkGiAHMIHbHz4uuAZHs/edit?usp=sharing

---

## Highlight styles (Main)

Each target (array, and every pool) has its own style:

| Style | Effect |
|--------|--------|
| **Outline** (default) | **Border only** — yellow/red around the free bar; Unraid’s free **fill** is left alone. Optional green border when still OK. |
| **Solid** | **Recolor free fill** yellow or red when a threshold is hit. Healthy = normal Unraid fill (no green fill). No border. |

**Global options:**

| Option | Default | Applies to | Effect |
|--------|---------|------------|--------|
| **Pulse** | Off | Outline **and** Solid | Flasher for Warning/Critical. Outline: pulses the border. Solid: pulses the free fill. Never pulses healthy/OK. |
| **Green when OK** | Off | Outline only | Static **green** border when free space is still **above** your thresholds |

| Level (Outline) | Look |
|-----------------|------|
| OK + green-when-OK | Green border (static, never pulses) |
| OK without that option | No Storage Guard paint |
| Warning | Yellow border (static or pulse) |
| Critical | Red border (static or pulse) |

| Level (Solid) | Look |
|---------------|------|
| OK | Normal Unraid free fill (no Storage Guard paint) |
| Warning | Free fill tinted yellow (static or pulse) |
| Critical | Free fill tinted red (static or pulse) |

Array can be Solid while one pool is Outline, and so on. Pulse is shared.

---

## Alerts

On the settings page, a small matrix of **checkboxes** chooses who gets notifications:

- Rows: Array, each pool  
- Columns: Warning, Critical  

**Default:** only **Array → Warning** is checked. Array Critical and all pool alerts are off until you enable them.

Checked = send an Unraid notification when that target hits that level.  
Nothing checked for a row = silent for that target.  
Alerts use the **same free-space thresholds and severity ranking** as Main coloring (including custom values and the “lower free amount = critical” rule). They do **not** require Main coloring to be on.

If free space is in the critical band but only the warning checkbox is enabled, you get a **warning** notification (highest enabled severity that matches).

### Recovery (cleared) notifications

When free space was at warning or critical and later rises **above** your thresholds again, Storage Guard sends one **recovered** notification using Unraid importance `normal` (the green/OK style used when parity finishes successfully).

- Only fires after a prior warning/critical for that target (array or a pool).
- Does **not** fire on first install just because free space is already healthy.
- Warning/critical can re-notify about once per hour while still degraded; recovery fires once when you return to OK.

### What notifications say (current)

#### Array Warning

**Subject:** `Storage Guard: Array free space warning`

**Body covers:**

1. Current free space and your warning threshold  
2. **If you lost …**  
   - Disk-size match: names matching data disks, e.g. `disk1 (26T) or disk2 (26T)`  
   - Custom threshold: “your custom free-space threshold of 7.5T”  
   - No exact match: “a data disk of about {threshold}”  
3. Risk: may not have enough free space on the **rest of the array** to move that disk’s data off without a replacement  
4. Parity can keep the array online (emulated disk); this is about **evacuation room**, not instant total data loss  

#### Array Critical

**Subject:** `Storage Guard: Array free space critical`

Same structure as warning, stronger language (“likely not enough…”, plan replacement or free substantial space).

#### Pool Warning / Critical

**Subject:** `Storage Guard: Pool {name} free space warning|critical`

Always includes free space, threshold, and detected **BTRFS data profile** (when available). Wording then follows **profile class**:

| Class | Profiles (examples) | Message focus |
|-------|---------------------|---------------|
| **Mirror** | RAID1, RAID1c3, RAID1c4 | One-disk loss usually still leaves a full copy; free space matters for **rebuild after replace** / balance / profile change—not array-style evacuate |
| **Parity** | RAID5, RAID6 | Free space ≈ **recovery/rebalance headroom** after failure (closer to array anxiety) |
| **Striped mirror** | RAID10 | One failure often OK for data; restoring full redundancy may need free space |
| **No redundancy** | single, RAID0 | Capacity policy only; disk loss **risks data** |
| **Unknown** | other / non-BTRFS | Generic free-space threshold text |

Pool thresholds remain **manual** (default None). Profile-aware **suggested** thresholds are planned (below).

### Notification outcomes (summary)

| Condition | Main | Notify (if that level enabled) |
|-----------|------|--------------------------------|
| Free above all thresholds | OK / optional green outline | none |
| Free ≤ Warning, > Critical | Yellow | Array/Pool **warning** text |
| Free ≤ Critical | Red | **Critical** text (or warning if only warning enabled) |
| Array threshold = disk size(s) | same | Names those disk(s) |
| Array custom threshold | same | Custom free wording, no fake disk names |
| Pool RAID1 + free low | same paint | Mirror narrative (not “evacuate like array”) |
| Pool RAID5 + free low | same paint | Rebalance/recovery headroom narrative |

---

## Pool free-space logic (today vs planned)

### Today (simple)

- Paint and thresholds: **raw free space** on `/mnt/{pool}` vs your Warning/Critical values (same math as array).  
- Defaults: pool thresholds **None**; pool alerts **off**.  
- Notifications: **profile-class wording** so RAID1 is not described like parity array evacuate.  
- **Mirror class (RAID1 / RAID1cN / dup):** member **disk-size** dropdown values are **ignored** for paint and alerts. Surviving a single disk failure does not require free space to evacuate data off the failed disk. Use **Custom free-space values** if you still want a capacity-policy threshold (e.g. room for rebuild/rebalance after replace).  
- **Parity / RAID10 / other:** disk-size and custom thresholds apply as configured.

### Why profile matters (scenarios)

| Layout | One disk fails — data? | Need free space to “move data off” failed disk? | Free-space meaning |
|--------|------------------------|--------------------------------------------------|--------------------|
| **Array (parity)** | Yes (emulated) | **Yes**, to evacuate without buying a disk | Evacuation room |
| **RAID1 (2 equal disks)** | Yes (mirror) | **No** for data access | Rebuild/rebalance after replace; policy |
| **RAID1c3 / RAID1c4** | Yes (extra copies) | **No** for single loss | Same as mirror |
| **RAID5 / RAID6** | Yes if within tolerance | Not array-evacuate; need room to **rebalance/replace** | Recovery headroom |
| **RAID10** | Often yes | Often need free space to **restore full redundancy** | Layout-dependent |
| **single / RAID0** | **No** | N/A | Capacity only; data at risk |

**Profile conversion** (e.g. RAID10 → RAID5) can **gain usable free space** after or before a failure, at the cost of different failure tolerance. Storage Guard will never auto-convert; guidance is planned.

---

## Planned / future

1. **Profile-aware suggested thresholds** for pools (opt-in “Suggest” or soft defaults by class).  
2. **Per-failure-device analysis** (“if disk X fails…”).  
3. **Approximate BTRFS rebalance free-space estimator.**  
4. **Profile conversion guidance** in Settings/docs/notifications.  
5. **Array vs cache free space education** (cache free ≠ array evacuate room).  
6. **ZFS pool awareness.**  
7. Richer notify formatting if Unraid allows.

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
| No colors on Main | Hard-refresh Main; confirm coloring is Yes; free space must be at/below a threshold (or enable green-when-OK for Outline healthy state) |
| Array never colors | Confirm array coloring Yes and Warning/Critical set; free space above both thresholds stays unpainted in Solid mode |
| Wrong pool / no pool color | Confirm the pool is checked under “Which pools to color”; open the pool page and verify free space |
| Yellow/red seem “swapped” vs labels | Check whether Critical free amount is higher than Warning—the plugin ranks by free-space severity (see notice on Settings) |
| Alerts never fire | Check the alert matrix; confirm thresholds; Unraid notification settings must allow warnings/alerts |
| Settings look empty | Array/pool details hide when Coloration is **No**—turn Yes to edit thresholds (saved values still apply to alerts) |

Config path: `/boot/config/plugins/StorageGuard/StorageGuard.cfg`  
Plugin files: `/usr/local/emhttp/plugins/StorageGuard/`
