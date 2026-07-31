# Integrity and maintenance

Open **Components → Punga Audio Archive → Integrity & Maintenance**.

The page opens without scanning the full archive. Run the required check explicitly.

## Checks

- **Integrity check** inspects database relationships, managed paths, file state, tags, checksums, and abandoned jobs.
- **Codec inventory** groups originals by codec, container, and extension and lists matching clips.
- **Stale-file check** scans managed storage for stale derivatives, unreferenced files, and abandoned temporary files.

Results are current snapshots rather than stored historical reports. Integrity findings can be exported as UTF-8 CSV.

## Repair actions

Selected clips can be verified, reanalysed, or have checksum and size recalculated. Repair operations use bounded batches.

## Cleanup

Current referenced originals are never stale-file cleanup candidates. Before deletion, selected candidates are regenerated and revalidated. Large selections are processed through sequential AJAX batches.

## Analysis queues

Separate waveform and spectral-analysis summaries report available, missing, pending, failed, stale, queued, and recorded storage totals.

Actions include queue missing, queue stale, retry failed, regenerate all, and delete all analysis data.

**Process analysis queue** handles jobs incrementally. Closing the page stops the browser loop but leaves untouched jobs in the database. Reopen the page and continue processing. Expired running locks can be recovered and retried up to the configured attempt limit.
