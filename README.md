# Punga Audio Archive

A native Joomla! 6 extension for managing, publishing and playing audio collections—from a personal sound archive to a site with multiple contributors.

> **Current version:** 0.15.3  
> **Package:** `pkg_audioarchive`  
> **Licence:** GNU General Public License version 2 or later

## Features

- **Archive and discovery:** searchable clips with categories, tags, duration/date filters, sorting, pagination, related clips, ratings and Joomla Smart Search.
- **Audio players:** shared Minimal, Compact, Default and Featured presentations, waveform/spectrogram/frequency-profile displays, optional playback normalisation and pitch/speed controls.
- **Sharing and publishing:** clip links, optional external iframe players, content-plugin players and paginated clip tables, and modules/menu items for specific, latest, random or daily clips.
- **Playlists and Sound Boards:** browser-local or account-stored collections, sharing, import/export and conversion between playlists and boards; pads, chromatic keyboard, MIDI and performance recording.
- **Multiple contributors:** frontend uploads and editing, My Clips, ownership, public/restricted/private visibility, Joomla ACL and storage/clip-count quotas.
- **Administration:** bulk uploads and replacement, server-directory imports, metadata extraction, processing queues, integrity checks and portable archive export/restore.
- **Joomla integration:** categories, tags, access levels, Custom Fields, optional Punga Analytics events, and English/German interfaces.

## Requirements

- Joomla! 6.x, PHP 8.3 or later, and MySQL or MariaDB
- PHP Fileinfo and writable configured storage/import directories
- PHP ZipArchive for archive export and restore
- Optional FFmpeg for waveform, spectrogram, frequency-profile and peak analyses

## Installation and updates

1. Download the versioned **installer ZIP** rather than the source ZIP.
2. In Joomla Administrator, open **System → Install → Extensions** and upload it.
3. Open **Components → Punga Audio Archive** and configure storage, permissions and the features you need.
4. Add an Archive menu item to publish your collection.

Install updates over the existing package; do not uninstall first. External embedding is disabled globally by default. See the [installation guide](docs/user/installation.md) for setup details.

## Documentation

| Guide | Contents |
| --- | --- |
| [User guide](docs/user/index.md) | Everyday use and feature guides |
| [Configuration](docs/user/configuration.md) | Component options |
| [Modules and content plugin](docs/user/modules-and-plugins.md) | Clip players, embedded tables and placeholder parameters |
| [External embeds](docs/user/external-embeds.md) | Player presentations, permissions, embed code and analytics |
| [Multi-user administration](docs/user/multi-user.md) | Ownership, visibility and quotas |
| [Frontend contribution](docs/user/frontend-upload.md) | Upload Clip, My Clips and contributor permissions |
| [Troubleshooting](docs/user/troubleshooting.md) | Diagnostics and common issues |
| [Developer guide](docs/developer/index.md) | Architecture, services and testing |
| [Changelog](CHANGELOG.md) | Release history |
| [Roadmap](docs/roadmap.md) | Agreed future priorities |

## Development

The source ZIP contains the expanded `pkg_audioarchive/` extension tree, `docs/`, README, changelog, licence, contribution guide and build script. It is a development snapshot, not an installer.

Run `python3 build_package.py` from the project root to build the installer. See [Build and release](docs/developer/build-and-release.md) and [Contributing](CONTRIBUTING.md).

The package includes the component, clip/tag modules, content and Smart Search plugins, the administrator Quick Icon plugin and the user-profile plugin. Install them through the complete package.

## Licence

Copyright © 2026 Frank Willeke. Distributed under the GNU General Public License version 2 or later; see [LICENSE](LICENSE).
