# Punga Audio Archive

Punga Audio Archive is a native Joomla! 6 extension package for managing and publishing collections of audio clips. It combines protected media storage, bulk importing, metadata management, waveform, spectrogram, and frequency-profile analysis, responsive frontend players, ratings, related clips, Sound Boards, playlists, and optional Punga Analytics integration.

> **Current version:** 0.12.1  
> **Package:** `pkg_audioarchive`  
> **Licence:** GNU General Public License version 2 or later

## Highlights

- Native Joomla categories, tags, access levels, ACL, routing, pagination, Custom Fields, and Smart Search integration
- Single upload, browser bulk upload, and protected server-inbox import
- Safe bulk replacement of existing originals without changing clip URLs or metadata
- Managed original-file storage with protected streaming, byte-range seeking, and downloads
- Unified responsive player with Minimal, Compact, Default, Featured, and Playlist presentations; Featured playback can expose live ±2-octave varispeed and shareable start/pitch state
- Optional FFmpeg waveform, spectrogram, and averaged frequency-profile analysis with database-backed processing queues
- Archive search and filtering by text, category, tags, duration, and dates
- Tag Directory, clip ratings, related clips, Previous/Next navigation, and frontend editing
- Browser-local Sound Boards with Pad Trigger and MIDI/onscreen chromatic modes, board-wide visitor-selectable mono/poly playback, and shareable mode/octave/pad/polyphony state
- Browser-local Sound Board performance recordings with compact DAW-style piano-roll visualization, visible instrument changes, independent live jamming, overdub/undo, and portable JSON import/export containing the board snapshot
- Manually ordered playlists with import, export, sharing, and two-way Sound Board conversion
- Archive integrity checks, codec inventory, cleanup tools, and portable export/restore archives
- Optional privacy-conscious event dispatch to Punga Analytics
- English and German interfaces

## Requirements

- Joomla! 6.x
- PHP 8.3 or later
- MySQL or MariaDB
- PHP Fileinfo
- Writable configured storage and import directories

FFmpeg is optional and required only for waveform, spectrogram, and frequency-profile generation. PHP `ZipArchive` is required for archive export and restore.

## Installation

1. Download the versioned installer ZIP, for example `pkg_audioarchive_v0-12-1.zip`.
2. In Joomla Administrator, open **System → Install → Extensions**.
3. Upload the package ZIP.
4. Open **Components → Punga Audio Archive** and review the dashboard and component options.

Install updates directly over the existing package. Do not uninstall the component as an update procedure.

## Documentation

- [Documentation overview](docs/index.md)
- [User guide](docs/user/index.md)
- [Developer documentation](docs/developer/index.md)
- [Installation and updates](docs/user/installation.md)
- [Troubleshooting](docs/user/troubleshooting.md)
- [Build and release process](docs/developer/build-and-release.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)

## Package contents

| Extension | Purpose |
| --- | --- |
| `com_audioarchive` | Administration, storage, importing, maintenance, public archive, players, Sound Boards, playlists, ratings, related clips, playback and downloads |
| `mod_audioarchive` | Displays selected clips in latest, longest, shortest, random, daily, most-played, most-downloaded, or specific-clip modes |
| `mod_audioarchive_tags` | Displays Audio Archive tags with descriptions, counts, and Archive links |
| `plg_content_audioarchive` | Embeds clips, archive counts, and total playtime in prepared content |
| `plg_finder_audioarchive` | Adds eligible clips to Joomla Smart Search |
| `plg_quickicon_audioarchive` | Adds an administrator dashboard shortcut |

Install the package ZIP rather than installing the contained extensions separately.

## Source tree

The source archive contains an expanded `pkg_audioarchive/` directory and the project documentation:

```text
pkg_audioarchive/
    com_audioarchive/
    mod_audioarchive/
    mod_audioarchive_tags/
    plg_content_audioarchive/
    plg_finder_audioarchive/
    plg_quickicon_audioarchive/
docs/
README.md
CHANGELOG.md
CONTRIBUTING.md
LICENSE
build_package.py
```

Run `python3 build_package.py` from the project root to create the versioned Joomla installer package.

## Licence

Copyright © 2026 Frank Willeke.

Punga Audio Archive is free software licensed under the GNU General Public License version 2 or later. See [LICENSE](LICENSE).
