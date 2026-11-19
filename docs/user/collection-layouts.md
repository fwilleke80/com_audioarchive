# Sound Board and Recorder layout (0.18.0)

The Sound Board heading and introduction precede its toolbar. The toolbar groups board selection, New board and Clear board; its **…** menu holds Rename, Delete, Set as default, Save as playlist and JSON Import/Export. Share board and Revoke share link stay together below the pads. Browser migration notices appear above the heading, as on Playlists.

The Recorder is a single collapsible card. Import JSON sits in its header; the selected recording has a distinct workspace with a shared transport bar for New recording, Play/Stop, Record overdub, available Undo overdub, time, Edit mode and Actions. Playing shows elapsed and total duration. Recording shows elapsed time. The recordings library is a table with named selection buttons and per-row actions.

Edit mode exposes Layer, Source pad, Save changes, Cancel and Undo edit in a toolbar. Instructions appear below the roll. Zoom controls occupy the roll’s upper-right header space without covering notes. Existing edit-mode playback remains available. Shared recording pages expose playback only.

Collection toolbars, notifications and menus share CSS classes. `media/js/collection-ui.js` supplies overflow positioning, Escape, outside-click dismissal and resize handling for both pages. Menus use native disclosure elements and ordinary keyboard-focusable buttons.

The custom Playlists introduction is configured in the Playlists menu item’s basic options under **Intro text** (`playlist_header_text`). Save/Cancel help belongs to the cover editor and is separate from this introduction.
