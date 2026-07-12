# Joomla! Audio Archive

## Overview

Joomla! Audio Archive is a Joomla 6.x extension package for managing and publishing a large archive of audio clips.

The extension provides:

- Administrator interfaces for clip management, metadata editing, and archive maintenance
- Public archive browsing, search, filtering, sorting, playback, and downloads
- Native Joomla integration with categories, tags, access levels, states, and custom fields
- Support for original audio files plus optional derived preview files and waveforms
- Graceful operation when optional media tooling such as FFmpeg or FFprobe is unavailable

## Package contents

The installable package is located in `pkg_audioarchive` and includes the main component `com_audioarchive`.

### Component structure

- `pkg_audioarchive/com_audioarchive/`
  - `administrator/` — backend forms, controllers, models, views, language files, services, and SQL installers
  - `site/` — frontend controllers, models, views, templates, and language files
  - `media/` — extension media assets and CSS
  - `audioarchive.xml` — Joomla installer manifest
  - `script.php` — installer script

## Requirements

- Joomla 6.x
- PHP version compatible with Joomla 6
- MySQL / MariaDB compatible with Joomla 6

Optional features may require:

- FFmpeg / FFprobe for automatic audio metadata extraction and preview generation

## Installation

1. Compress the contents of `pkg_audioarchive` into a ZIP archive.
2. Install the package through Joomla Administrator > System > Install > Extensions.
3. After installation, use Joomla Administrator > Components > Audio Archive to start managing clips.

## Features

### Administrator

- Clip creation and editing
- Metadata management (title, description, recording date, publication date, category, tags, access level, state)
- Audio file uploads and archive imports
- Database-driven filtering, sorting, and pagination
- Installer and database update scripts for schema management

### Public site

- Audio archive browsing and search
- Server-side filtering and sorting
- Inline playback for browser-supported audio formats
- Clip detail pages and original-file downloads
- Joomla-native presentation with public templates

## License

This project is licensed under the GNU General Public License version 2 or later.
