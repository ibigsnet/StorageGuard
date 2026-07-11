# How Unraid handles multi-disk BTRFS pool I/O

This page is about **what actually moves data** on an Unraid **BTRFS pool** (e.g. cache), and why real-world speeds often match “one stream ≈ one disk” even when multi-stream ceilings say $N/2$ or $N$.

Capacity math stays in [README.md](README.md) and the profile pages. This is **I/O behavior**.

---

## Math & concepts

### Unraid does not reimplement pool RAID

On a multi-device **BTRFS pool**, Unraid:

- creates and manages the device set,  
- mounts the filesystem (e.g. under `/mnt/cache`),  
- shows free/used on Unraid’s main page,  
- exposes balance / replace UI,

but **does not** run its own stripe engine for pool data the way the **array** uses parity for `disk1`…`diskN`.

**Kernel BTRFS** allocates chunks, places copies, and issues the reads/writes. “How Unraid handles RAID1 on six disks” ≈ “how BTRFS RAID1 handles six disks,” with Unraid sitting above the mount.

(ZFS pools are a different stack; these notes are for **BTRFS** pools.)

### Data vs metadata

BTRFS can use different **profiles** for data and metadata (e.g. data RAID1, metadata RAID1c3).  
Speed discussion below is mainly **data**. Metadata is smaller but can add latency on small random I/O.

### Write path (BTRFS RAID1)

1. App writes through the Unraid mount (Docker, VM, SMB, etc.).  
2. BTRFS turns that into **extents** and **chunks** (block groups, often ~1 GiB).  
3. For **data RAID1**, each chunk is stored as **two copies on two different devices**.  
4. Those two devices receive the physical writes (checksums, CoW as usual).

| Workload | Devices busy | Ideal logical write ceiling |
|----------|--------------|-----------------------------|
| **One** sequential stream (one big file) | **~2** disks | ≈ **1×** single-disk write $W$ |
| **Many** independent streams (several files/jobs) | up to **$N$** disks as pairs | ≈ **$(N/2) \times W$** |

**Why one stream is ~1× $W$:** that stream only needs two devices. The other four on a six-disk pool stay mostly idle for that write.

**Why multi-stream can approach $N/2$:** different chunks pick different pairs. Six disks ≈ three concurrent pairs → about **3×** $W$ in a perfect parallel case.

**Physical vs logical:** logical $L$ bytes written ⇒ roughly **2L** physical bytes on RAID1. Tools reporting FS throughput are usually **logical**; `iostat` / Unraid per-disk rates show **physical** per device.

### Read path (BTRFS RAID1)

1. BTRFS knows both devices hold a copy.  
2. It can read **either** copy (and repair from the other if a checksum fails).  
3. Parallel readers / multiple files can hit **different** disks at once.

| Workload | Ideal logical read ceiling |
|----------|----------------------------|
| **One** sequential stream | ≈ **1×** single-disk read $R$ |
| **Many** parallel streams | up to ≈ **$N \times R$** |

### Same idea for other profiles (sketch)

| Profile | One sequential write (order of magnitude) | Multi-stream write ceiling (ideal) |
|---------|-------------------------------------------|-------------------------------------|
| RAID1 | ~1× $W$ (2 disks) | $(N/2) \times W$ |
| RAID1c3 | ~1× $W$ (3 disks) | $(N/3) \times W$ |
| RAID1c4 | ~1× $W$ (4 disks) | $(N/4) \times W$ |
| RAID10 | often higher than RAID1 for one stream (striping) | ~$(N/2) \times W$ multi-stream ideal |
| RAID0 / single | can approach $N \times W$ if striped/spread | $N \times W$ |

Exact RAID10/5/6 single-stream behavior is workload- and layout-dependent; treat multi-stream formulas as **comparison ceilings**, not guarantees.

### What you see on Unraid in practice

Matches the model above:

- One large copy/mover/VM disk write on a multi-disk **RAID1** pool often looks like **~single-disk** speed, with **two** members active.  
- Parallel Docker/VM/SMB activity can light up **more** disks and raise aggregate throughput toward the multi-stream ceiling.  
- “I have six disks so I should always get 3× write” is only fair for **multi-stream** ideal, not for one sequential job.

### Simple test design

Let $R_1, W_1$ be a measured (or vendor) single-disk sequential read/write for the same media class.

| Test | Expectation on RAID1, $N=6$ |
|------|-------------------------------|
| One large sequential write to the pool | ~$W_1$ class, **~2** disks hot |
| Several parallel large writes (different files) | between $W_1$ and $\sim 3 W_1$, more disks hot |
| One large sequential read | ~$R_1$ |
| Several parallel reads | up toward $\sim 6 R_1$ in theory, often less |

Watch Unraid’s per-disk rates or `iostat` to confirm how many members are actually busy.

### Spreadsheet columns that match reality

| Column | Formula | When it matches Unraid “feel” |
|--------|---------|--------------------------------|
| Write multi-stream ceiling | $(N/2)\times W$ | Many parallel writers |
| Write single-stream | $W$ | One sequential writer (common) |
| Read multi-stream ceiling | $N\times R$ | Many parallel readers |
| Read single-stream | $R$ | One sequential reader |

---

# What Storage Guard does

| Behavior | Detail |
|----------|--------|
| Speed numbers in Settings (when shown) | **Multi-stream bus-ceiling** estimates for **profile comparison** only |
| RAID1 / RAID10 write ceiling | $(N/2) \times W$ path model |
| RAID1c3 / RAID1c4 write ceiling | $(N/3) \times W$ / $(N/4) \times W$ |
| Not claimed | Measured disk sequential speed, or that one `dd` will hit the multi-stream ceiling |
| Capacity / free Suggest | Separate from speed — see [scenarios.md](scenarios.md) |

Code: `sg_pool_profile_speed_ceiling` in `sg-pool-math.php`.

Unraid’s array (parity disks) is a **different** free-space and rebuild story; pool math and this I/O note apply to **BTRFS pools**, not the classic array.
