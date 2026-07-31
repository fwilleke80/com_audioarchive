# Playlists

Create a menu item of type **Punga Audio Archive → Playlists**.

Playlists are stored entirely in the current browser. A visitor can maintain multiple lists without a Joomla account.

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

Playlist entries store stable clip UUIDs rather than playback URLs. The page resolves those UUIDs against the current archive, publication state, access levels, and routing. Removed or inaccessible clips remain identifiable and can be removed.

## Players

The player at the top uses the queue-oriented unified Playlist presentation.

Each row below uses the same unified Minimal play/pause control as Archive and Related Clips rows. Selecting a row still routes playback through the queue player, preserving automatic advancement and playlist analytics.

## Configuration

The dedicated **Playlists** tab controls availability and table colours. A Playlists menu item can override introductory text and table colours, including the currently playing row.
