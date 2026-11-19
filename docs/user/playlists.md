# Playlists

Create a menu item of type **Punga Audio Archive → Playlists**.

Signed-in users store playlists in their account. Guests store playlists in the current browser and can maintain multiple lists without a Joomla account. Clearing browser data removes guest playlists unless exported.

## Editing and saving (0.18.0)

Creating, renaming, deleting and importing playlists, and adding/removing/reordering clips, save immediately. Failed ordinary edits are rolled back and an error is shown. There are no playlist-wide Save/Cancel controls.

The toolbar above the player holds the selector, Create, Edit cover image, Share and a **…** menu for secondary actions. Each clip row has its own **…** menu for moving, sharing, adding to a Sound Board and removing clips. Playback and clip links remain directly accessible. There are no collection tools below the playlist table.

For an account playlist, **Edit cover image** expands the cover controls. Selecting a file or requesting removal remains pending until **Save cover**; **Cancel** discards only that playlist’s cover draft. These buttons appear only when that cover has pending changes. Switching playlists preserves each cover draft independently. Failed saves retain it for retry, and leaving with pending cover changes triggers the browser’s navigation warning. Selecting a file alone never uploads it. Cover placement in public players remains deferred.

Browser collections can be imported explicitly through the notification at the top. Completed imports remove only the acknowledged browser copies.

## Features

- Create, rename, and delete playlists
- Add clips from Archive and Clip Detail actions
- Remove clips
- Manual ordering with move controls
- Sequential playback
- Previous and Next controls
- Automatic advancement
- JSON import and export
- Share account playlists through short links, and browser playlists through URL fragments
- Save a received temporary playlist locally
- Load the current playlist into the Sound Board

Playlist entries store stable clip UUIDs rather than playback URLs. The page resolves those UUIDs against the current archive, publication state, access levels, and routing. Removed or inaccessible clips remain identifiable and can be removed.

## Sound Board conversion

When the Sound Board is enabled, **Load into Sound Board** copies available playlist clips into pads in playlist order. It skips removed or inaccessible clips and stops at the configured Sound Board pad count. If the current board contains clips, replacement requires confirmation.

Loading a playlist stores the current resolved numeric clip IDs required by Sound Board playback. The reverse action, **Save as playlist**, is available on the Sound Board when Playlists are enabled.

## Players

The player at the top uses the queue-oriented unified Playlist presentation.

Each row below uses the same unified Minimal play/pause control as Archive and Related Clips rows. Selecting a row still routes playback through the queue player, preserving automatic advancement and playlist analytics.

## Configuration

The dedicated **Playlists** tab controls availability and table colours. A Playlists menu item can override introductory text and table colours, including the currently playing row.
