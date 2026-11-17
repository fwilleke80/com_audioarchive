# Clip credits and metadata (0.15.7)

The backend clip editor has a **Credits and metadata** tab. The same fields appear in the frontend upload and edit forms. All are optional and save with the rest of the form.

| Field | Meaning |
| --- | --- |
| Licence | Licence designation or short custom usage terms, such as CC BY 4.0. Blank means unspecified. |
| Creator | Creative credit, independent of the Joomla account owning the clip. |
| Contributors | Additional names and roles, one per line. |
| Recording location | A free-text place, without automatic geolocation. |
| Genre / style | Free-text genre/style description, independent of Joomla tags. |
| Year of creation | Optional integer from 1 to 9999, independent of the recording date and upload date. |
| Lyrics | Plain text with preserved line breaks. |

Populated fields appear in the existing Details box on the clip detail page for visitors allowed to view the clip. Empty fields and wholly empty metadata panels are omitted. Location details are displayed along with the other credits. These fields do not alter clip access, download or embedding permissions, or account ownership. They are manually entered; this release does not extract or write ID3 tags, geocode locations or render cover artwork.

Single-line fields allow 255 characters, contributors 8,000 characters, and lyrics up to 60,000 UTF-8 bytes (also limited to 60,000 characters by the form). HTML markup is not supported. Invalid years or oversized fields reject the save rather than silently truncating values.

Archive exports carry these values with each clip. Restore validates them; older backups without these fields restore empty descriptive metadata. Existing records gain empty fields on upgrade. Skipping a conflicting clip during merge preserves its current metadata; overwriting its metadata uses the backup values.

## Batch editing (0.15.8)

Select clips in the backend Clips list and open Batch. Under Credits and metadata, choose **Unchanged**, **Set value**, or **Clear field** separately for each field. Set applies the entered value to every selected clip; Clear removes the value (including setting creation year to blank). Unchanged ignores the input and preserves each clip's existing value. The normal metadata validation, per-clip edit permissions and batch transaction apply.

In the backend clip editor, the cover controls sit below the description on the Clip tab. They still save only with Save/Apply.
