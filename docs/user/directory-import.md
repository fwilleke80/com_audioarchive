# Directory import and bulk replacement

Open **Components → Punga Audio Archive → Import**. The importer only scans the configured inbox and does not expose arbitrary filesystem browsing.

## Importing new clips

The importer can:

- Scan recursively when enabled
- Exclude hidden files and symbolic links
- Preview media metadata before import
- Select individual files
- Apply shared category, tags, access, and state
- Derive nested Joomla categories from inbox folders
- Override duplicate handling for the current import
- Remove inbox files only after successful managed-storage transfer

Successfully imported clips receive jobs for every globally enabled analysis.

## Replacing existing originals

**Replace existing clip files** is intended for migrations such as replacing ALAC originals with externally converted browser-compatible files.

Replacement matching uses the original filename without its extension. Matching is case-insensitive and normalises spaces, hyphens, underscores, repeated separators, and Unicode dash characters.

The operation requires exactly one existing clip match. Ambiguous matches are blocked, identical files are detected, and duration differences are reported.

A successful replacement preserves the clip ID, public URL, metadata, category, tags, access, publication state, and counters. Existing derivatives become stale, and enabled analyses are queued again.

The old original can be removed or retained for later cleanup. Retained files appear as unreferenced candidates in [Integrity and maintenance](maintenance.md).

Punga Audio Archive does not transcode originals. Conversion is performed externally before placing replacement files in the inbox.
