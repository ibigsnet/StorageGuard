# Profile: single

## Redundancy
None. Losing a device that holds unique chunks can mean data loss.

## Usable capacity

\[
U(\mathrm{single}, S_1,\ldots,S_N) = \sum_i S_i
\]

## Free threshold suggestion
Storage Guard does **not** auto-suggest recovery free headroom for single (class **none**). Thresholds are capacity-policy only (custom values).

## Example: 4 × 4 TB
- Usable ≈ **16 TB**
- After losing one disk: usable ≈ **12 TB** if the filesystem could still mount remaining devices (data risk depends on what lived only on the failed disk)

## Speeds (best-case bus ceiling)
With single-disk path ceiling \(R, W\) and \(N\) devices: pool ≈ \(N\cdot R\) read / \(N\cdot W\) write (striped-like when multiple devices).
