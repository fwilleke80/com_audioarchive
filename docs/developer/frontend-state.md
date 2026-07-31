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

Playlist entries use stable clip UUIDs and are resolved server-side before rendering playable data.
