# Joomla! Audio Archive

A native Joomla! 6 component for managing, publishing, filtering, playing, and downloading large collections of audio clips.

> **Development status:** active development  
> **Current version:** `0.4.0-dev8`  
> **Package:** `pkg_audioarchive`  
> **Component:** `com_audioarchive`

Audio Archive is designed for collections ranging from a few clips to several thousand files. It combines Joomla-native categories, tags, access levels, ACL, publication states, routing, pagination, and template overrides with protected audio storage and browser playback.

The current development package contains the main component only. Additional modules and plugins described in the roadmap are not included yet.

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation and upgrades](#installation-and-upgrades)
- [First setup](#first-setup)
- [Administrator workflows](#administrator-workflows)
- [Public Archive](#public-archive)
- [Clip detail pages](#clip-detail-pages)
- [Audio formats and metadata](#audio-formats-and-metadata)
- [Storage and security](#storage-and-security)
- [Configuration](#configuration)
- [Template overrides and styling](#template-overrides-and-styling)
- [Uninstallation](#uninstallation)
- [Development](#development)
- [Current limitations](#current-limitations)
- [Roadmap](#roadmap)
- [Licence](#licence)

## Features

### Administrator

- Dashboard with clip, publication, playback, download, storage, and system statistics
- Joomla-style clip manager with filtering, sorting, publication states, access levels, categories, and tags
- Single-file upload
- Browser bulk upload with per-file progress and results
- Server-directory import from a configured inbox
- Optional recursive import
- Optional conversion of inbox folders into nested Joomla categories
- Automatic metadata extraction using a built-in pure-PHP media inspector
- Automatic duration, format, codec, file-size, title, and recording-date detection where available
- SHA-256 duplicate detection with configurable handling
- Safe replacement of an existing clip's original file
- Protected audio preview in the Clip editor, including seeking
- Batch category assignment
- Batch tag addition, replacement, and clearing
- Searchable tag checkboxes in batch editing
- Joomla ACL and category-based permission inheritance
- English and German administrator interfaces

### Public website

- Searchable and filterable Archive menu-item type
- Text search across clip metadata
- Category filtering
- Multiple tag filtering using logical **AND**
- Searchable tag checkbox list
- Minimum and maximum duration filtering
- JavaScript-enhanced two-handle duration slider with a no-JavaScript text-field fallback
- Recording-date and upload-date ranges
- Sortable columns
- Server-side Joomla pagination
- Configurable page sizes
- Configurable Archive columns
- Optional empty categories in the category filter
- Collapsible filter panel with an expanded no-JavaScript fallback
- Protected inline playback with HTTP byte-range seeking
- One-player-at-a-time behaviour
- Responsive table/card layout
- SEF Clip detail pages
- Protected original-file downloads
- Aggregate play and download counters
- Joomla access-level and publication-date enforcement
- English and German site interfaces

## Requirements

- Joomla! 6.x
- A PHP and database environment supported by the installed Joomla! 6 version
- PHP Fileinfo extension strongly recommended
- Writable configured storage and import directories
- JavaScript for enhanced controls such as bulk upload, collapsible filters, tag-list searching, compact playback controls, and the duration slider

Core browsing, ordinary form filtering, pagination, opening Clip pages, native playback, and downloads remain usable without JavaScript where practical.

### Optional software

FFmpeg and FFprobe are detected by the System Check, but they are **not required** for the current component features.

The present development build uses its bundled pure-PHP inspector for supported metadata extraction. FFmpeg-based preview generation, waveform generation, and queued processing are planned for later releases.

## Installation and upgrades

### Install the package

1. Open **System → Install → Extensions** in the Joomla administrator.
2. Upload the package ZIP:

   ```text
   pkg_audioarchive-0.4.0-dev8.zip
   ```

3. Open **Components → Audio Archive**.
4. Review the dashboard and System Check.

Install the **package ZIP**, not the inner component ZIP, for the supported installation lifecycle.

### Upgrade a development installation

Install the newer package directly over the existing version.

> Do **not** uninstall the existing package before upgrading. Uninstallation removes component database records and is not an upgrade procedure.

Development updates preserve existing clips, file references, categories, tag assignments, settings, and counters unless a release note explicitly states otherwise.

## First setup

### 1. Review component options

Open:

```text
Components → Audio Archive → Options
```

At minimum, review:

- Default category
- Default access level
- Default publication state
- Storage paths
- Import inbox path
- Permitted extensions
- Maximum file size and duration
- Duplicate policy
- Recording-date policy
- Public Archive filters and columns
- Playback and download options
- Detail-page fields

### 2. Verify storage

Use the dashboard System Check to verify or create the configured directories:

```text
audioarchive/originals
audioarchive/previews
audioarchive/waveforms
audioarchive/import
```

The paths are configurable. Storage outside the public web root is preferred when the hosting environment permits it.

### 3. Add clips

Choose one of the available workflows:

- **New Clip** for a single item
- **Upload** for browser-based bulk upload
- **Import** for files already present in the configured server inbox

### 4. Create a public Archive menu item

1. Open the Joomla menu manager.
2. Create a new menu item.
3. Choose **Audio Archive → Audio Archive** as its menu-item type.
4. Configure any category, tag, layout, pagination, filter, column, and detail-page overrides.
5. Publish the menu item.

## Administrator workflows

### Clip manager

The Clips view supports Joomla publication states:

- Published
- Unpublished
- Archived
- Trashed

Ordinary list views show the **Trash** action. Permanent **Delete** is available only while viewing Trashed clips.

### Single Clip editing

A Clip record includes:

- Title and alias
- Description
- Joomla category
- Joomla tags
- Recording date
- Access level
- Publication state and dates
- Original audio file
- Extracted technical metadata
- Created and modified information
- Play and download counts

For existing Clips with an available original file, the **Audio file** tab contains a protected administrator player. This allows clips to be auditioned while editing metadata and tags. Administrator preview playback does not increase public playback statistics.

### Browser bulk upload

The Upload view provides:

- Drag-and-drop and multi-file selection
- One controlled request per file
- Per-file progress and status
- Retry and stop controls
- Batch category, tags, access, state, and optional recording-date override
- Duplicate warnings and direct edit links

### Server-directory import

The Import view scans only the configured inbox. It does not provide arbitrary filesystem browsing.

Import supports:

- Recursive scanning
- Hidden-file exclusion
- Symbolic-link rejection by default
- Pre-import metadata inspection
- Per-file eligibility and warning display
- Selection of individual files
- Incremental per-file import
- Optional inbox-file deletion after a successful managed-storage transfer

#### Folder-derived categories

The importer can use the inbox folder structure as the category hierarchy.

Example:

```text
import/
├── Weather/
│   ├── rain.m4a
│   └── City/
│       └── traffic.m4a
└── Animals/
    └── birds.m4a
```

This can create or reuse:

```text
Weather
└── City
Animals
```

An existing category may be selected as the base. Categories are created only when import begins, never during the scan preview.

### Batch editing

Select Clips in the administrator list and choose **Batch** from the toolbar.

The dialog can:

- Move selected Clips to a category
- Add selected tags
- Replace all existing tags
- Clear all tags by replacing them with an empty selection

Tag selection uses searchable checkboxes; Ctrl or Command is not required.

## Public Archive

The Archive performs filtering, access checks, ordering, and pagination in the database. It never loads the complete collection into JavaScript.

### Available filters

- Text search
- Category
- Multiple tags using logical AND
- Minimum duration
- Maximum duration
- Recording date from/to
- Upload date from/to

Filter forms use HTTP GET, making filtered URLs bookmarkable and shareable.

### Duration input

Duration values may be entered as:

```text
90
01:30
1:02:30
```

These represent 90 seconds, 1 minute 30 seconds, and 1 hour 2 minutes 30 seconds.

With JavaScript enabled, a two-handle slider appears above the text inputs. Its upper boundary is derived from the longest publicly eligible Clip for the current Archive menu item. The text fields remain the canonical submitted values and continue to work without JavaScript.

Duration filtering follows the whole-second values displayed in the Archive. A maximum of `20`, for example, includes exact durations from `20.000` through `20.999` seconds.

### Tags

Visitors select tags using checkboxes. Selecting several tags returns only Clips containing **every** selected tag.

The search box above the tag list narrows only the visible tag choices. It does not submit the Archive form, and checked tags remain checked when temporarily hidden by the local search.

### Columns

The following result columns can be shown or hidden independently:

- Play
- Title
- Category
- Duration
- Recording date
- Upload date
- Tags

Column visibility does not affect filtering. Tags may be hidden from the table while the tag filter remains available, for example.

### Pagination

Global and menu-item settings control:

- Default items per page
- Allowed page-size choices
- Maximum page size
- Pagination visibility
- Result-count visibility
- Page-size selector visibility

The result summary shows both the current page and the complete filtered result count.

### Empty categories

The category filter can optionally include categories without currently visible public Clips. The calculation respects publication state, dates, access levels, category restrictions, and menu-item restrictions.

## Clip detail pages

Each public Clip has a routed detail page containing configurable combinations of:

- Title
- Player
- Description
- Duration
- Category
- Tags
- Recording date
- Upload date
- Original filename
- File format
- Codec
- File size
- Play count
- Download count
- Original download action

Global detail settings can be overridden by the Archive menu item.

## Audio formats and metadata

The default accepted extensions are:

```text
m4a, mp4, aac, mp3, ogg, oga, opus, wav, flac, webm
```

The list is configurable.

The built-in inspector currently recognises audio structures including:

- M4A/MP4 audio, including common AAC and ALAC metadata
- AAC ADTS
- MP3
- Ogg Vorbis
- Ogg Opus
- WAV
- FLAC
- WebM audio

Depending on the format, the component may extract:

- Duration
- MIME type
- Container format
- Audio codec
- Sample rate
- Channel count
- Bitrate
- Embedded title
- Embedded or container date metadata

Browser playback support depends on the visitor's browser and the codec stored in the original container. Unsupported originals remain downloadable.

## Storage and security

Original client filenames are stored as metadata but are not used as managed filenames.

Managed files use generated identifiers and sharded paths, for example:

```text
originals/4f/91/4f91d8e6-9a30-4e32-97ce-50de256f7f23.m4a
```

Public requests never receive the managed filesystem path.

### Upload and import safeguards

- Extension validation
- Fileinfo MIME inspection where available
- Binary media-structure inspection
- Configurable size and duration limits
- SHA-256 checksums
- Directory-traversal prevention
- Storage-root containment checks
- Symbolic-link rejection by default
- Generated internal filenames
- Cleanup of incomplete managed files after failed operations

### Playback and downloads

Public audio is delivered through component controllers that:

- Enforce Clip and category publication state
- Enforce publication dates
- Enforce Joomla access levels
- Validate file availability and managed-path containment
- Support `HEAD` requests
- Support single HTTP byte ranges for seeking
- Stream in bounded chunks instead of loading the complete file into PHP memory
- Avoid exposing server paths

Playback streams do not count as downloads. Download counts apply only to authorised original-file download requests. Playback counts are reported when playback actually begins and are intended as aggregate, non-personal statistics.

## Configuration

Configuration is split into logical panels.

### General

- Default category
- Default access level
- Default publication state
- Default Archive ordering and direction
- Default items per page
- Enable play counts
- Enable download counts

### Storage

- Original directory
- Preview directory
- Waveform directory
- Import inbox
- Recursive scanning
- Symbolic-link policy

### Upload and import

- Permitted extensions
- Permitted MIME types
- Maximum file size
- Maximum duration
- Duplicate policy
- Title-generation policy
- Recording-date policy
- Inbox-file deletion after successful import

### Archive

- Visible filters
- Active-filter summary
- Result count
- Pagination and page-size selector
- Empty-category visibility
- Initial expanded/collapsed filter state
- Allowed and maximum page sizes

### Archive columns

- Play
- Title
- Category
- Duration
- Recording date
- Upload date
- Tags

### Playback and detail

- Original downloads
- Detail metadata fields
- Play and download counts
- Download action

### Processing

- Automatic executable detection
- FFmpeg path
- FFprobe path
- Process timeout
- Maximum processing attempts

Some processing options are present in preparation for later preview, waveform, and job-queue phases.

## Template overrides and styling

Public output is separated into Joomla layout files. A site template can override individual parts without replacing the filtering, access-control, routing, pagination, or streaming logic.

Archive overrides:

```text
templates/YOUR_TEMPLATE/html/com_audioarchive/archive/default.php
templates/YOUR_TEMPLATE/html/com_audioarchive/archive/default_filters.php
templates/YOUR_TEMPLATE/html/com_audioarchive/archive/default_active_filters.php
templates/YOUR_TEMPLATE/html/com_audioarchive/archive/default_table.php
templates/YOUR_TEMPLATE/html/com_audioarchive/archive/default_row.php
templates/YOUR_TEMPLATE/html/com_audioarchive/archive/default_pagination.php
```

Clip overrides:

```text
templates/YOUR_TEMPLATE/html/com_audioarchive/clip/default.php
templates/YOUR_TEMPLATE/html/com_audioarchive/clip/default_player.php
templates/YOUR_TEMPLATE/html/com_audioarchive/clip/default_metadata.php
templates/YOUR_TEMPLATE/html/com_audioarchive/clip/default_tags.php
templates/YOUR_TEMPLATE/html/com_audioarchive/clip/default_download.php
```

The source distribution includes an example under:

```text
docs/template-override-example/
```

The default stylesheet also exposes CSS custom properties such as:

```css
--audioarchive-accent
--audioarchive-border
--audioarchive-surface
--audioarchive-radius
--audioarchive-shadow
```

These can be overridden by the active Joomla template without copying component layouts.

## Uninstallation

Uninstallation removes the component's database records and Joomla integration metadata.

Managed original, preview, and waveform files are **preserved by default**. Component Options contains an explicit opt-in setting to remove database-recorded managed media during uninstall.

Even when media removal is enabled:

- Only known database-recorded files beneath validated storage roots are considered
- The import inbox is preserved
- Untracked files are preserved
- Global Joomla tag definitions are preserved

Back up the database and storage directories before uninstalling a site containing valuable audio.

## Development

### Source layout

```text
extensions/
├── com_audioarchive/
│   ├── administrator/
│   ├── media/
│   ├── site/
│   ├── audioarchive.xml
│   └── script.php
└── pkg_audioarchive/
    └── pkg_audioarchive.xml
```

### Build

From the repository root:

```bash
python3 build.py
```

This creates the component and package archives in `dist/`.

### Validate

The validation script requires a command-line PHP executable for PHP syntax checks.

```bash
python3 validate.py
```

Validation covers PHP syntax, XML and JSON parsing, language keys, manifest paths, package structure, Joomla service contracts, database schema contracts, upload/import behavior, storage containment, routing, Archive filtering, duration boundaries, protected streaming, HTTP ranges, administrator preview, and frontend presentation contracts.

### Documentation

- `PROJECT_SPECIFICATION.md` — complete project scope and architecture
- `CHANGELOG.md` — development release history
- `docs/template-override-example/` — example frontend override

## Current limitations

The current `0.4.0-dev8` package is a development build, not a stable production release.

Not yet included:

- Generated compatibility previews
- FFmpeg/FFprobe processing jobs
- Waveform generation and rendering
- Joomla Scheduled Tasks plugin
- Smart Search plugin
- Joomla Custom Fields integration
- Latest Clips module
- Random/Clip-of-the-Day module
- Public API
- User playlists, favourites, ratings, or comments

The database and configuration already contain some fields and options reserved for later phases. Their presence does not imply that the corresponding feature is complete.

## Roadmap

Planned major work includes:

1. Complete playback-source and compatibility-preview handling
2. Processing jobs and Joomla Scheduled Tasks
3. FFmpeg/FFprobe preview and waveform generation
4. Joomla Custom Fields integration
5. Smart Search integration
6. Latest Clips module
7. Random and Clip-of-the-Day module
8. Performance, accessibility, security, migration, and release hardening

See `PROJECT_SPECIFICATION.md` for the complete implementation plan.

## Licence

Copyright © 2026 Frank Willeke.

This project is licensed under the **GNU General Public License, version 2 or later**.

See `LICENSE.txt` for details.
