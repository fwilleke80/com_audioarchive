# Sound Board and Recorder layout (0.18.1)

The Sound Board heading and introduction precede its toolbar. The toolbar groups board selection, New board and Clear board; its **…** menu holds Rename, Delete, Set as default, Save as playlist and JSON Import/Export. Share board and Revoke share link stay together below the pads. Browser migration notices appear above the heading, as on Playlists.

The Recorder is a single collapsible card. New recording and Import JSON sit in its header; the selected recording has a distinct workspace with a shared transport bar for Record/Stop, Play/Stop, available Undo overdub, time, Edit mode and Actions. Playing shows elapsed and total duration. Recording shows elapsed time. The recordings library is a table with named selection buttons and per-row actions.

Edit mode exposes Layer, Source pad, Save changes, Cancel and Undo edit in a toolbar. Instructions appear below the roll. Zoom controls occupy the roll’s upper-right header space without covering notes. Existing edit-mode playback remains available. Shared recording pages expose playback only.

Collection toolbars, notifications and menus share CSS classes. `media/js/collection-ui.js` supplies overflow positioning, Escape, outside-click dismissal and resize handling for both pages. Menus use native disclosure elements and ordinary keyboard-focusable buttons.

The custom Playlists introduction is configured in the Playlists menu item’s basic options under **Intro text** (`playlist_header_text`). Save/Cancel help belongs to the cover editor and is separate from this introduction.

No recording is selected initially. Select one in the library or choose **New recording** to name and save an empty recording. **Record** captures a first take into an empty recording, or overdubs a populated recording from the playhead. The original notes remain intact; recording continues past the original end until stopped. **Undo overdub** restores the previous version. Playback and editing become available after note events exist.

Toolbar buttons and disclosure toggles share a common height. Menu items are borderless. Playlist row reorder arrows remain directly accessible on the left; **Load into Sound Board** opens a destination chooser for account boards and confirms replacement of an occupied board.
