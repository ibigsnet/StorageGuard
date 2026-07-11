# Storage Guard — Documentation

How free-space thresholds, free-bar coloring on Unraid’s **main page**, and alerts work—for the **array** and for **BTRFS pools**.

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

When free space crosses a threshold, Storage Guard paints that target’s **total free space bar** on Unraid’s **main page** (the array’s free bar, or a pool’s data free bar—not the whole page):

| Free space | Free bar on main page | Alerts (if enabled) |
|------------|----------------------|---------------------|
| **Above** all thresholds (healthy) | Normal Unraid look, **or** optional **green outline** (Outline style + green-when-OK) | Silent |
| At or below **Warning** | **Yellow** | Unraid **warning** notification |
| At or below **Critical** | **Red** | Unraid **alert** notification |
| Back **above** all thresholds after a warn/alert | Normal / optional green outline | Unraid **normal** notification (“recovered”) — same severity family as parity complete |

**None** (or a blank custom field) means that level is unused.

**Main-page free-bar coloring** and **notifications** are **independent**: you can color without alerting, **alert without coloring**, or both. Free-space thresholds are always editable in Settings. Appearance options (array/pool coloring Yes/No, highlight style, which pools, pulse, green-when-OK) control paint only.

### Product defaults (Default button / fresh install)

| Setting | Default |
|---------|---------|
| **Array** free-bar coloring (main page) | **Yes** |
| **Pool** free-bar coloring (main page) | **No** |
| Array Warning free | Size of **largest array data disk** (machine-specific) |
| Array Critical free | **None** |
| Pool Warning / Critical free | **None** (opt-in) |
| Alerts | **Array Warning only** (array Critical off; all pool alerts off) |
| Highlight style | Outline (where applicable) |
| Pulse / green-when-OK | Off |

Pools are **opt-in** for paint and thresholds: turn on pool coloring and set free thresholds only when you want them. Array paint defaults on with a largest-disk Warning.

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

- The **lower** free-space amount → **critical (red)** on the main-page free bar, and critical alerts if enabled  
- The **higher** free-space amount → **warning (yellow)** on the main-page free bar, and warning alerts if enabled  

So with Warning `2T` and Critical `8T`, free space at 5T is still treated as **warning** (because 5 ≤ 8 and 5 > 2), and free at 1T is **critical**. Severity follows free-space math, not which field you typed the number into.

**Tip:** keep Warning free amount **greater than** Critical free amount so the labels match what you expect.

---

## Array thresholds

Dropdowns list **unique sizes of your array data disks** (parity and pools are not included), largest first.

**Defaults (first use / product Default):**

- Warning → size of your **largest array data disk** (core idea: free space left should still cover losing that disk)  
- Critical → **None** (you opt in)  

### Why “largest disk” is a useful default

If an array data disk fails, you often want enough free space elsewhere to evacuate or reshuffle data. Matching Warning to the largest data disk is a common starting point: below that free space, a full-size failure may leave you short.

### Mixed sizes

Example array (data only): 8T, 8T, 8T, 4T, 2T

- **Warning = 8T** — free space is less than one large disk; evacuating a large disk may force a purchase  
- **Critical = 2T** — free space is less than your smallest data disk; even a small failure may leave no room to move data  

You can set Critical to something between those (e.g. `4T`) if a particular drive worries you—or to match the **combined size of a few questionable drives**, so free space still covers evacuating more than one at-risk disk. Critical should still be a **smaller free amount** than Warning.

### Custom values

Use **Custom free-space values** when the right number is not a disk size—for example `7.5T` or `500G`. Accepted forms include `1.5T`, `7.5T`, `500G`, `26T`. Leave a field blank for None.

---

## Pool thresholds (WIP — advanced)

> **Work in progress** (array remains primary). Pool UI is **hidden by default** — open **Show advanced pools (WIP)**.  
> **OK to use:** custom free-space thresholds, member disk-size thresholds (except mirrors — below), free-bar coloring on Unraid’s main page, and alerts.  
> **Profile-aware today:** alert *wording* by profile class; **mirrored pools (RAID1 / RAID1cN / dup) ignore disk-size thresholds** for paint/alerts.  
> **Capacity math / Suggest:** Settings can fill Custom Warning/Critical from same-profile $\Delta$ for RAID1/1cN, RAID10, RAID5, and RAID6. Formulas: [docs/math/](docs/math/README.md).

Pools are detected live from Unraid—nothing is hard-coded. New installs often ship with a first pool named **`cache`**, but that is only a common Unraid default: every pool can use **any** name Unraid allows. Storage Guard lists whatever your server actually has.

For each pool you can:

- Include it in **pool free-bar coloring** on the main page (All / individual checkboxes), when pool coloring is Yes  
- Set its own **highlight style** (Outline or Solid)  
- Use **member disk sizes** or **custom free-space values**  
- Enable Warning / Critical **alerts** separately  
- Use **Suggest free thresholds** where the profile supports capacity-Δ math  

**Pool defaults:** free-bar coloring on the main page = **No**; Warning free = **None**; Critical free = **None**; pool alerts off. Turn on pool coloring and set thresholds only when you want them.

### Why free space matters on BTRFS pools

Unraid pools are often BTRFS. **BTRFS profiles are not mdadm/hardware RAID:**

- Allocation is **chunk / block-group** based (~1 GiB), not whole-disk stripes  
- **Data** and **metadata** can use different profiles  
- **RAID1** = exactly **two** copies on different devices (not N-way mirror of all disks)  
- **RAID10** = two copies **plus striping** at chunk level (not fixed mirror pairs)  
- Odd device counts and mixed sizes are normal  

After a drive fails, the pool can stay online **degraded** when the profile’s copies/parity still cover every chunk. You are **not always forced to replace** immediately.

Typical options:

- **Keep running degraded** until you can act  
- **Remove** the device and rebalance onto remaining disks **if free space and device count allow** (usable capacity shrinks)  
- **Replace** the member to restore capacity and full redundancy  
- **Convert** profile via balance (needs unallocated space; different tradeoffs)

**Storage Guard free thresholds** for pools (especially **Suggest**) answer: *if this disk is gone and you stay on the same profile, does used data still fit?* That **Δ** is capacity wiggle room — not “evacuate one full disk of unique data” like the Unraid **array**.

Detailed formulas: [docs/math/](docs/math/README.md). Unraid pool I/O (single- vs multi-stream): [docs/math/unraid-io.md](docs/math/unraid-io.md).

Useful Unraid UI: **main page → click the pool name → Balance Status**  
(direct link form: `http://your-server/Main/Device?name=yourpoolname`)

### BTRFS profiles (short reference)

| Profile | Rough usable | Failure tolerance (typical) | Notes |
|---------|----------------|-----------------------------|--------|
| single / RAID0 | ~100% | None — any loss can mean data loss | |
| RAID1 | ~50% | **1** device (always 2 copies) | N disks still only 2 copies per chunk |
| RAID1c3 / RAID1c4 | ~33% / ~25% | 2 / 3 devices | Often used for metadata |
| RAID10 | ~50% | **1** guaranteed | Not fixed pairs; odd N OK |
| RAID5 | ~(N−1)/N | 1 device | ⚠ stability caveats |
| RAID6 | ~(N−2)/N | 2 devices | ⚠ stability caveats |

**Mixed sizes:** BTRFS can combine e.g. 4T and 8T drives; usable is layout-dependent ([btrfs-usage calculator](https://carfax.org.uk/btrfs-usage/)). First-order Suggest math is simplified — see docs/math.

### Example: 4×4TB + 2×8TB (~32 TB raw)

Approximate usable (first-order, data profile only):

| Profile | ~Usable | Notes |
|---------|---------|--------|
| RAID0 / single | ~32 TB | Max space, no safety |
| RAID1 / RAID10 | ~16 TB | Two copies (+ striping for RAID10) |
| RAID5 | ~24 TB | sum − largest |
| RAID6 | ~16 TB | sum − 2×largest |

**Lose one 8T on RAID10 (est.):** usable 16 T → 12 T → need **~4T free** so used still fits (Δ).  
**Lose one 4T on RAID10:** Δ ≈ **2T**.  
You may still run on remaining disks without an emergency replace if used ≤ post-loss U.

**Rules of thumb (Suggest-style)**

- Prefer **Δ-based** free (worst single-disk capacity drop), not “always free ≥ largest disk”  
- **Warning** free amount larger than **Critical** when sizes differ (product rule: Warning ≈ 2× Critical fit free)  
- Always keep **backups**; redundancy is not a backup  

For detailed speed/capacity tables with mixed drives, this community spreadsheet is useful:  
https://docs.google.com/spreadsheets/d/1_hyQBpp4EpSqxYUCarDHSfYkRkGiAHMIHbHz4uuAZHs/edit?usp=sharing

---

## Highlight styles (main-page free bars)

Each target (**array**, and every **pool**) has its own style:

| Style | Effect |
|--------|--------|
| **Outline** (default) | **Border only** — yellow/red around the free bar; Unraid’s free **fill** is left alone. Optional green border when still OK. |
| **Solid** | **Recolor free fill** yellow or red when a threshold is hit. Healthy = normal Unraid fill (no green fill). No border. |

**Global options** (apply only where that target has coloring enabled):

| Option | Default | Applies to | Effect |
|--------|---------|------------|--------|
| **Color outlines green when OK/Normal** | Off | Outline only | Static **green** border when free space is still **above** your thresholds |
| **Pulse free-bar colors on warn/crit** | Off | Outline **and** Solid | Flasher for Warning/Critical. Outline: pulses the border. Solid: pulses the free fill. Never pulses healthy/OK. |

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

Array can be Solid while one pool is Outline, and so on. Pulse is shared across targets that have coloring on.

---

## Alerts

On the Settings page, a small matrix of **checkboxes** chooses who gets notifications:

- Rows: **Array**, each **pool**  
- Columns: Warning, Critical  

**Default:** only **Array → Warning** is checked. Array Critical and all pool alerts are off until you enable them.

Checked = send an Unraid notification when that target hits that level.  
Nothing checked for a row = silent for that target.  
Alerts use the **same free-space thresholds and severity ranking** as main-page free-bar coloring (including custom values and the “lower free amount = critical” rule). They do **not** require free-bar coloring to be on.

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
   - Disk-size match: Unraid disk name plus kernel device id, e.g. `disk1 | sdc (26T) or disk2 | sdb (26T)` (also `nvme0n1`, USB block ids, etc. when present)  
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
| **Mirror** | RAID1, RAID1c3, RAID1c4 | Multi-copy chunks; one-disk loss usually leaves data online; free = capacity/policy, not array-style evacuate |
| **Parity** | RAID5, RAID6 | Free ≈ capacity fit after loss + recovery headroom (⚠ profile stability caveats) |
| **Striped mirror** | RAID10 | BTRFS two copies + striping; one failure usually OK; free = post-loss fit / remove-rebalance wiggle room |
| **No redundancy** | single, RAID0 | Capacity policy only; disk loss **risks data** |
| **Unknown** | other / non-BTRFS | Generic free-space threshold text |

Pool free thresholds default to **None**. **Suggest** can fill Custom Warning/Critical from capacity math where the profile supports it (see docs/math).

### Notification outcomes (summary)

| Condition | Main-page free bar | Notify (if that level enabled) |
|-----------|--------------------|--------------------------------|
| Free above all thresholds | OK / optional green outline | none |
| Free ≤ Warning, > Critical | Yellow | Array/pool **warning** text |
| Free ≤ Critical | Red | **Critical** text (or warning if only warning enabled) |
| Array threshold = disk size(s) | same | Names those disk(s) |
| Array custom threshold | same | Custom free wording, no fake disk names |
| Pool RAID1 + free low | same paint | Mirror narrative (not “evacuate like array”) |
| Pool RAID5 + free low | same paint | Rebalance/recovery headroom narrative |

---

## Pool free-space logic (today vs planned)

### Today

- Paint and thresholds: **raw free space** on `/mnt/{pool}` vs your Warning/Critical values (same comparison style as the array).  
- Defaults: pool free-bar coloring **off**; pool thresholds **None**; pool alerts **off**.  
- Notifications: **profile-class wording** so RAID1 is not described like array evacuate.  
- **Mirror class (RAID1 / RAID1cN / dup):** member **disk-size** dropdown values are **ignored** for paint and alerts. Surviving a single disk failure does not require free space to evacuate data off the failed disk. Use **Custom** or **Suggest** for capacity-policy free amounts.  
- **Parity / RAID10 / other:** disk-size and custom thresholds apply as configured.  
- **Capacity math / Suggest:** Critical = $\max\Delta_{\mathrm{fit}}$, Warning = $2\times\max\Delta_{\mathrm{fit}}$ for RAID1/1cN, RAID10, RAID5, RAID6. See [docs/math/](docs/math/README.md).  
- **Speeds:** optional best-case **bus/link multi-stream ceilings** for comparing profiles only — not measured disk sequential throughput. See [docs/math/unraid-io.md](docs/math/unraid-io.md).

### Why profile matters (scenarios)

| Layout | One disk fails — data? | Need free space to “move data off” failed disk? | Free-space meaning |
|--------|------------------------|--------------------------------------------------|--------------------|
| **Array (parity)** | Yes (emulated) | **Yes**, to evacuate without buying a disk | Evacuation room |
| **BTRFS RAID1 (any N≥2)** | Yes (2 copies) | **No** for data access | Capacity drop; optional remove/rebalance/replace/convert |
| **RAID1c3 / RAID1c4** | Yes (extra copies) | **No** for single loss | Same as mirror class |
| **RAID5 / RAID6** | Yes if within tolerance | Not array-evacuate; capacity + recovery room | Recovery headroom (⚠ stability) |
| **BTRFS RAID10** | Usually yes (1 disk) | **No** forced replace; free so used still fits | Same-profile Δ (Suggest) |
| **single / RAID0** | **No** | N/A | Capacity only; data at risk |

**Profile conversion** (e.g. RAID10 → RAID1 or RAID5) can **change** usable free space after or before a failure, at the cost of different failure tolerance and write shape. Storage Guard will never auto-convert.

---

## Planned / future

1. **Richer rebalance free-space estimator** (beyond capacity-fit $\Delta$ and $2\times$ comfort).  
2. **Better speed estimates** — model JSON by drive name, optional read-only probe, user overrides.  
3. **Per-failure-device analysis** (“if disk X fails…”).  
4. **Profile conversion guidance** in Settings UI (capacity + bus-ceiling speeds).  
5. **Array vs pool free-space education** in UI (pool free ≠ array evacuate room).  
6. **ZFS pool awareness.**  
7. Richer notify formatting if Unraid allows.

---

## How coloring works (technical, brief)

- Config is stored under `/boot/config/plugins/StorageGuard/`  
- A small script on Unraid’s main page loads config + live free space and paints the **Free** usage bars (array totals row; pool **Data Partition** free—not “Pool of N devices”, not Internal Boot)  
- Alerts are evaluated on a timer and use Unraid’s normal notify system  

Hard-refresh the **main page** after install or update if styles look stale.

---

## Troubleshooting

| Symptom | What to try |
|---------|-------------|
| No colors on main-page free bars | Hard-refresh the main page; confirm **array** and/or **pool** coloring is Yes; free space must be at/below a threshold (or enable green-when-OK for Outline healthy state) |
| Array free bar never colors | Confirm array coloring Yes and Warning/Critical set; free space above both thresholds stays unpainted in Solid mode |
| Wrong pool / no pool free-bar color | Confirm pool coloring Yes and the pool is checked under pools to color; open the pool page and verify free space |
| Yellow/red seem “swapped” vs labels | Check whether Critical free amount is higher than Warning—the plugin ranks by free-space severity (see notice on Settings) |
| Alerts never fire | Check the alert matrix; confirm thresholds; Unraid notification settings must allow warnings/alerts |
| Settings look empty | Array thresholds stay visible. Pool thresholds live under **Show advanced pools (WIP)**. Appearance holds array/pool coloring toggles. |

Config path: `/boot/config/plugins/StorageGuard/StorageGuard.cfg`  
Plugin files: `/usr/local/emhttp/plugins/StorageGuard/`
