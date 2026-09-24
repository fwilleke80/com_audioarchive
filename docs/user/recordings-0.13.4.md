# Recordings in 0.13.4

## Storage

Guests keep browser recordings. Signed-in users save recordings to their account and can open them on other devices. Existing browser recordings are offered for explicit import on the Sound Board page. Successful imports remove acknowledged browser copies only; invalid, conflicting or excess entries stay in the browser. The account limit is 100 recordings and 4 MiB of combined performance JSON (not audio files), with at most 20,000 events and two hours per recording. Server request-size limits also apply.

Account writes use the existing collection revision checks. A stale tab or expired login produces an error instead of overwriting newer data or falling back to browser storage. Keep the page open on failure; use Save recordings to retry or Export JSON to recover a performance before reloading. Another-tab conflicts require reconciliation using export/reload/import. Wait for saving to complete before leaving the page; page-close network delivery cannot be guaranteed.

## Playback and overdubs

Play uses the recording’s embedded clips without replacing your displayed board or its name. You can play live pads and select another saved board during ordinary playback. Stopping or finishing stops backing voices only. Existing transport is Play/Stop; this release does not add pause.

Overdubs use the current visible board. Each overdub layer stores its own clip mappings, preserving earlier performances when the boards differ. Up to 64 layers are supported. Undo is available for the most recent overdub in the current page session. Keep newer recordings with layered boards in this version or later; older releases do not understand the added layer mappings.

The active recording toolbar exposes Play, Record overdub and Undo. List rows only select a recording. Actions contains Rename, Export JSON, Load recording’s sound board and Delete. Loading is explicit and temporary: Return to my sound board restores your saved board, while signed-in users can Save as new sound board. The loaded board is the recording’s base board; separate overdub layer mappings remain part of the recording.

Current clip access permissions are enforced by route resolution and streaming. A recording does not grant access to private or removed clips. Recording sharing links are not part of this release.

## Backup and deletion

Full archive backups include recordings and their overdub boards. Restore resolves owners by username and clips by UUID and invalidates stale tabs. Unmatched owners are warned about and skipped. Deleting a Joomla account retains archival recording rows; they are inaccessible through ordinary account endpoints. JSON export/import remains available for individual recordings.

## Test on Joomla

1. Upgrade the complete installer and open Sound Board as a guest, then signed in.
2. Import existing browser recordings, reload, and check them on a second device.
3. Play a recording made with another board: visible pads and board name must remain unchanged. Test live jamming and Stop/natural completion.
4. Record an overdub using a different visible board, replay it, undo, export/import, and reload across devices.
5. Exercise Actions, temporary board load, Return, Save as new, rename and delete.
6. Test expired login/offline saving and a stale second tab; ensure an error appears and unsaved work remains exportable.
7. Check full archive export/restore on a test installation and clip permission changes.

Automated PHP/SQLite and JavaScript fixtures verify ownership, conflicts, bounds, portability, save failure recovery and independent playback routing. Live Joomla/MySQL and mobile audio tests remain necessary.
