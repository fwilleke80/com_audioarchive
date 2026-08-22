# Browser bulk upload

Open **Components → Punga Audio Archive → Upload** to add several files through the browser.

## Workflow

1. Select or drag audio files into the upload view.
2. Choose shared category, tags, access level, publication state, and optional recording-date override.
3. Start the upload queue.
4. Wait while enabled waveform and spectral analyses are generated automatically.
5. Review the per-file result and edit link.

Files are processed individually. A failed file does not invalidate the rest of the batch.

## Validation and metadata

Each file is checked against configured extension, MIME-type, size, duration, and duplicate rules. Punga Audio Archive calculates SHA-256 and extracts technical metadata with its bundled PHP media inspector.

## Automatic analyses

Every successfully stored clip receives jobs for all globally enabled analysis types. After the file queue has finished, the Bulk Upload page automatically processes those jobs one at a time and reports the result. If processing is interrupted or fails, unprocessed jobs remain available in the maintenance queue.

## Server limits

Browser uploads remain subject to PHP and web-server settings such as `upload_max_filesize`, `post_max_size`, `max_file_uploads`, and request timeouts. For very large collections or files already present on the server, use [Directory import](directory-import.md).
