# Storage Guard — agent notes

## Channels

- **NIROG** (`192.168.1.3`): **main** (Plugins tab).
- **HoloX3D** (`192.168.254.4`): **CA / stable** only.
- **10.1.0.1**: production — **read-only**.

**main:** push often OK. **stable:** only when asked after soak.

When bumping version: `pack-txz.sh` **and commit** `archive/StorageGuard-&version;-x86_64-1.txz` with the `.plg`.

## CHANGELOG vs CA `<CHANGES>`

- **`CHANGELOG.md`**: rolling full history.
- **`.plg` `<CHANGES>`**: ~**7** summarized notes; bundle rapid micro-ships as **version ranges** with a short generalized line. Standout fixes stay their own `###`.
- **Older releases** → link full `CHANGELOG.md` on GitHub.

Shared rule: `~/.grok/rules/plugin-changes-and-changelog.md`.

## Settings UI

No essay/tip prose in the form. Short Unraid `inline_help` only; details in `DOCS.md` / `docs/math/`. Do not delete verified product facts when cleaning copy.
