# Frontend state

Punga Audio Archive uses two different browser-state mechanisms.

## Joomla session state

Archive filters, tag mode, sorting, direction, and page size are stored independently for each Archive menu item. Explicit query parameters take precedence. Reset clears the current menu item's stored state.

## Tab-local origin state

When JavaScript is available, opening a clip from an Archive or Sound Board stores a same-origin return URL and menu title in `sessionStorage`.

Properties:

- Scoped to the current browser tab
- Does not alter the canonical clip URL
- Accepts same-origin URLs only
- Expires after 24 hours
- Used by return links, Previous/Next navigation, and frontend editing
- Falls back to resolved menu context when unavailable

## Browser-local collections

Sound Board assignments and playlists are stored in browser storage. Shared boards and playlists are encoded in URL fragments, so the fragment is not sent to the server as part of the HTTP request.

Sound Board entries contain the numeric clip ID required by direct playback plus the clip UUID and title used for stable identity/compatibility checks. Playlist entries use stable clip UUIDs and are resolved server-side before rendering playable data.

Conversion from a Sound Board to a playlist resolves occupied pad IDs to public clip UUIDs. Conversion from a playlist to a Sound Board uses the playlist page's resolved public metadata. Both directions preserve source order, skip inaccessible clips, and write only browser-local storage.

When Sound Board polyphony permits visitor choice, the board-wide mono/poly preference is stored under `com_audioarchive.soundboard.sampler_polyphony.v1`. The same value controls Pad Trigger and Chromatic Keyboard voices and cannot override an administrator-disabled polyphony setting.

Shared Sound Board fragments may carry initial UI state alongside `board=…`: `mode`, `octave`, `pad`, and `polyphony`. Clip Detail query parameters use `start` (or `t`) for initial playback position and `pitch` for the optional Featured varispeed setting. Clip Detail sharing reconstructs those parameters from the live player state instead of merely copying the canonical URL.
