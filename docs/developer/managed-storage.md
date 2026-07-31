# Managed storage

Punga Audio Archive stores original and derived media under administrator-configured roots and records relative storage keys in the database.

## Principles

- Never trust client filenames as managed paths.
- Preserve the original filename separately for display and downloads.
- Normalise and validate every path.
- Prevent traversal outside configured roots.
- Disable symbolic-link traversal by default.
- Do not expose managed filesystem paths or filenames publicly.
- Stream files in bounded chunks rather than reading complete files into PHP memory.

## Original files

Originals are mandatory and preserved. Replacing an original updates its file record while retaining the clip identity and public route. Previous originals may optionally remain for maintenance review.

## Derived data

Waveforms and spectra are optional. Missing, failed, or stale analyses do not block publication or playback. Derivatives are delivered through protected analysis controllers.

## Cleanup

Stale-file cleanup operates only on regenerated and revalidated candidates. Referenced current originals are never eligible. Unreferenced retained originals and stale derivatives can be removed through maintenance.

## Export and restore

Archive export stores portable paths and checksums. Restore stages files in protected storage, validates paths and hashes, and coordinates file movement with a database transaction and compensating cleanup.
