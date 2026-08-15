# Storage Guard — install & releases

## Install

### Community Applications (recommended)

1. Unraid **Apps** → search **Storage Guard**
2. **Install** or **Update**
3. Hard-refresh the browser

CA is fed from [unraid-templates](https://github.com/ibigsnet/unraid-templates). Updates may lag a short time after a GitHub push.

### Manual install (raw plugin URL)

**Plugins → Install Plugin** → paste a **raw** URL ending in `.plg`:

| Channel | Use when | URL |
|---------|----------|-----|
| **Production (`stable`)** | Normal install / CA channel | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable/storageguard.plg` |
| **Lab (`main`)** | Newest development tree | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/main/storageguard.plg` |
| **Recommended freeze** | Known-good pin (`2026.07.29aa`) | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.29aa/storageguard.plg` |
| **Older rollback** (`2026.07.10bt`) | Earlier known-good line | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/stable-recommended-2026.07.10bt/storageguard.plg` |
| **Pinned version** | Fixed tag | `https://raw.githubusercontent.com/ibigsnet/StorageGuard/vVERSION/storageguard.plg` |

- **`stable`** — what CA installs; production updates.
- **`main`** — lab only; can be ahead of CA.
- **Tags / freezes** — exact trees that never change.

### Recommended freezes

| Tag | Version | Notes |
|-----|---------|--------|
| [`stable-recommended-2026.07.29aa`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.29aa) | `2026.07.29aa` | Current recommended freeze |
| [`stable-recommended-2026.07.10bt`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.10bt) | `2026.07.10bt` | Earlier known-good (BTRFS capacity math line) |
| [`stable-recommended-2026.07.10ar`](https://github.com/ibigsnet/StorageGuard/releases/tag/stable-recommended-2026.07.10ar) | `2026.07.10ar` | Earlier baseline |

### Roll back

Paste a freeze or `vVERSION` raw `.plg` URL under **Plugins → Install Plugin**, then hard-refresh. If Unraid reports **same version**, you are already on that build.

---

## Version numbers

Plugin versions look like `2026.08.14af` (date + two-letter suffix). Unraid compares them as plain strings for “update available.”

Changelog bullets ship on the **Plugins** page and optionally as [GitHub Releases](https://github.com/ibigsnet/StorageGuard/releases).

---

## Links

| | |
|--|--|
| **GitHub** | https://github.com/ibigsnet/StorageGuard |
| **Releases** | https://github.com/ibigsnet/StorageGuard/releases |
| **Docs** | [DOCS.md](DOCS.md) (if present) · README |
