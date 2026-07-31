# Archive export and restore

Archive export and restore are available on **Integrity & Maintenance** and require PHP `ZipArchive`.

## Export scopes

| Scope | Contents |
| --- | --- |
| Metadata only | Clips, categories, tags, relations, ACL data, Custom Fields, counters, file/analysis records, and portable configuration |
| Metadata and analyses | Metadata plus generated waveform and spectral-analysis files |
| Complete archive | Metadata, analyses, originals, and any legacy compatibility-preview files |

Exports use a versioned manifest and SHA-256 checksums. Requested files are not silently omitted: an export fails when required managed files cannot be included.

## Inspection

Select an export through browser upload or the configured inbox. Inspection copies it into protected staging and validates format, version, paths, symbolic-link/traversal safety, sizes, and checksums before any archive data changes.

## Restore modes

- Restore into an empty archive
- Merge into the current archive
- Replace current archive after explicit confirmation

Merge conflict policies are skip existing, update public metadata only, or update metadata and included files.

Clip identity uses persistent UUIDs. Categories, tags, fields, ACL, users, and access structures are mapped to the destination installation rather than relying on source numeric IDs.

## Safety

Restore uses database transactions and protected file staging. Newly copied files are removed on failure, database changes are rolled back, and superseded files are deleted only after commit.

After a successful restore, rebuild the Joomla Smart Search index.
