# Playlists

Create a menu item of type **Punga Audio Archive → Playlists**.

Signed-in users store playlists in their account. Guests store playlists in the current browser and can maintain multiple lists without a Joomla account. Clearing browser data removes guest playlists unless exported.

## Editing and saving (0.15.6)

On the Playlists page, changes to names, clip order and membership, new/deleted playlists, imported JSON and account cover images remain pending until **Save**. You can switch between playlists without losing pending edits. Save applies all pending changes on this page; **Cancel** discards them and loads the current saved state. Account data and cover changes are committed together. Failed saves retain the draft for retry; conflicts from another tab require Cancel/reloading before editing again.

Cover selections show a local preview, with **Remove cover** and **Undo cover change**. Nothing is uploaded just by selecting an image. Artwork is stored privately; public rendering is still deferred.

Playback and JSON export can use your pending list. Save changes before sharing a playlist link. Leaving the page with unsaved changes triggers the browser's navigation warning. Explicit **Add to playlist** actions on archive/detail pages and **Save as playlist** on the Sound Board still perform their named action immediately; they are not part of this editor's draft.

## Features

- Create, rename, and delete playlists
- Add clips from Archive and Clip Detail actions
- Remove clips
- Manual ordering with move controls
- Sequential playback
- Previous and Next controls
- Automatic advancement
- JSON import and export
- Share through URL fragments
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
