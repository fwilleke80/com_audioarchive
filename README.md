# Joomla! Audio Archive

Audio Archive is a native Joomla! 6 extension package for managing and publishing collections of audio clips.

It is intended for archives ranging from a small collection to several thousand files. Administrators can upload or import clips, organise them with Joomla categories and tags, edit metadata, replace source files in bulk, inspect archive integrity, generate waveform and spectral analyses with optional FFmpeg support, control publication and access, and review playback and download statistics. Visitors can search and filter the archive, browse a tag directory, use consistent responsive players throughout the site, open clip detail pages, and — where permitted — download the protected original files.

> **Current version:** `0.8.9`
> **Package:** `pkg_audioarchive`

## Table of contents

- [What Audio Archive offers](#what-audio-archive-offers)
  - [Administrator features](#administrator-features)
  - [Public website features](#public-website-features)
- [Package contents](#package-contents)
- [Requirements and external tools](#requirements-and-external-tools)
  - [FFmpeg and FFprobe binaries](#ffmpeg-and-ffprobe-binaries)
- [Installing and updating the package](#installing-and-updating-the-package)
- [Using the Audio Archive component](#using-the-audio-archive-component)
  - [Initial configuration](#initial-configuration)
  - [Dashboard and system check](#dashboard-and-system-check)
  - [Adding clips](#adding-clips)
    - [Single clip](#single-clip)
    - [Browser bulk upload](#browser-bulk-upload)
    - [Server inbox import](#server-inbox-import)
    - [Bulk replacement of existing files](#bulk-replacement-of-existing-files)
  - [Managing clips](#managing-clips)
  - [Integrity and maintenance](#integrity-and-maintenance)
  - [Audio analyses](#audio-analyses)
    - [Waveform generation](#waveform-generation)
    - [Spectrum generation](#spectrum-generation)
    - [Analysis queues and regeneration](#analysis-queues-and-regeneration)
  - [Publishing the public archive](#publishing-the-public-archive)
  - [Publishing a tag directory](#publishing-a-tag-directory)
  - [Frontend access control](#frontend-access-control)
  - [Frontend clip editing](#frontend-clip-editing)
  - [Public filtering](#public-filtering)
  - [Tags and tag descriptions](#tags-and-tag-descriptions)
  - [Playback and downloads](#playback-and-downloads)
    - [Shared player presentations](#shared-player-presentations)
    - [Template overrides and custom styling](#template-overrides-and-custom-styling)
- [Using the Audio Archive module](#using-the-audio-archive-module)
  - [Selection modes](#selection-modes)
  - [Module layouts](#module-layouts)
  - [Player presentation](#player-presentation)
  - [Display options](#display-options)
- [Using the Audio Archive Tags module](#using-the-audio-archive-tags-module)
  - [Tag selection and archive linking](#tag-selection-and-archive-linking)
  - [Tag-module presentation](#tag-module-presentation)
- [Using the Smart Search plugin](#using-the-smart-search-plugin)
- [Using the Quick Icons plugin](#using-the-quick-icons-plugin)
- [Using the Content plugin](#using-the-content-plugin)
  - [Embedding clips](#embedding-clips)
  - [Longest and shortest clips](#longest-and-shortest-clips)
  - [Embedded player presentations](#embedded-player-presentations)
  - [Archive clip counts](#archive-clip-counts)
  - [Archive playtime](#archive-playtime)
  - [Content-plugin behaviour](#content-plugin-behaviour)

## What Audio Archive offers

### Administrator features

- Dashboard with clip, publication, storage, playback, download, analysis, and system information
- Dashboard display of the installed component version
- Dashboard storage summary with combined and separate original-clip, waveform, and spectral-analysis sizes
- System checks for configured storage paths, PHP capabilities, FFmpeg availability, and optional FFprobe availability
- Absolute or Joomla-root-relative FFmpeg and FFprobe paths, fallback executable searches, version probing, and detailed failure reporting
- Automatic addition of Unix execute permission to explicitly configured binaries when the hosting account permits it
- ACL-protected buttons for resetting all play counts or all download counts
- Joomla-style clip management with publication states, access levels, categories, tags, sorting, and filtering
- Single-file upload
- Browser-based bulk upload with per-file progress and results
- Import from a configurable server-side inbox
- Separate inbox modes for importing new clips and replacing files of existing clips
- Optional recursive inbox scanning
- Optional conversion of inbox folders into nested Joomla categories
- Automatic extraction of duration, format, codec, file size, embedded title, and recording date where available
- SHA-256 duplicate detection with configurable handling
- Safe replacement of an individual clip's original file
- Bulk replacement matching based on normalised original filenames
- Optional retention of previous original files for later review and cleanup
- Protected audio preview in the clip editor using the shared player system
- Configurable backend player presentation
- Manual waveform and spectral-analysis generation or regeneration from the clip editor
- Database-backed analysis queues with missing, pending, failed, stale, available, and queued status counts
- Incremental FFmpeg waveform and spectrum processing with visible progress and retry handling
- Queue-based full regeneration of every eligible waveform or spectral analysis after generation settings change
- Deletion of all generated waveform or spectral-analysis data while retaining processing-job history
- Separate recorded disk-usage totals for waveform and spectral-analysis data
- Generic derived-analysis storage and job infrastructure shared by waveform and spectral analyses
- Batch category assignment
- Batch tag addition, replacement, and clearing
- Searchable tag selection in batch operations
- On-demand integrity, codec-inventory, and stale-file checks so the maintenance page opens without scanning the archive
- Original-file codec, container, extension, clip-count, and storage-size inventory
- Filtering of maintenance results by audio codec
- Review and removal of stale derivatives, abandoned temporary files, and unreferenced managed files
- Automatic batched stale-file cleanup for large selections
- CSV export of integrity findings
- Joomla ACL and category-based permission inheritance
- English and German administrator interfaces

### Public website features

- Searchable and filterable Archive menu-item type
- Configurable Tag Directory menu-item type
- Optional menu-item introductory text above Archive filters and Tag Directory contents
- Text search across clip metadata
- Category filtering
- Multiple-tag filtering using selectable logical **AND** or **OR**
- Searchable tag checkbox list with a dedicated **Clear tag filter** action
- Minimum and maximum duration filtering
- JavaScript-enhanced duration slider with text-field fallback
- Recording-date and upload-date ranges
- Sortable result columns
- Server-side Joomla pagination
- Configurable page sizes, filters, columns, and detail-page fields
- Category names are shown in archive results only when the **Show category** list-column option is enabled
- Session persistence for the last-used filters, sorting, and page size, stored independently for each Archive menu item
- Responsive desktop table and mobile card presentation
- Mobile cards that preserve readable tag and duration layouts on narrow screens
- Protected inline playback with HTTP byte-range seeking
- One shared player implementation used by archive rows, clip pages, modules, content embeds, and backend previews
- Four player presentations: Minimal, Compact, Default, and Featured
- Featured player with switchable waveform and spectrum views
- Waveform seeking with played/unplayed colouring and a moving playhead
- Spectrum view with playback-position marker and click-to-seek interaction
- Configurable preferred Featured-player data view with automatic fallback to whichever analysis is available
- Automatic controls-only fallback when neither waveform nor spectrum data is available
- Native browser audio controls when JavaScript is disabled or fails
- Configurable player colours, corner radius, button sizes, waveform height, and preferred analysis view
- Joomla template override support for the shared frontend player markup
- One-player-at-a-time behaviour
- Clean, menu-aware SEF clip detail URLs
- Breadcrumb integration
- Page titles, metadata, canonical routes, and redirects from stale aliases or legacy URLs
- Correct routing when several Audio Archive menu items exist
- Component-wide frontend access control independent of menu-item access
- Login redirection for guests when the archive requires authentication
- Configurable protected downloads of original files
- Optional restriction of detail-page downloads to selected Joomla access levels
- Aggregate play and download counters
- Clickable tag links, with category names available as archive metadata and breadcrumb context
- Tag descriptions exposed through standard browser hover tooltips
- Native frontend clip editing for authorised users when Joomla frontend editing is enabled
- Joomla publication-date, category, and access-level enforcement
- English and German site interfaces

The component keeps original audio files and generated analysis files in managed storage and never exposes their filesystem paths. Browser playback support depends on the container and codec supported by the visitor's browser; authorised original files remain downloadable when downloads are enabled for that visitor.

## Package contents

The package installs the following Joomla extensions:

| Extension | Type | Purpose |
| --- | --- | --- |
| `com_audioarchive` | Component | Administration, importing, replacement, integrity maintenance, FFmpeg system checks, waveform and spectral analyses, shared players, public archive, tag directory, clip pages, playback, downloads, routing, access control, and statistics |
| `mod_audioarchive` | Site module | Displays selected clips using latest, longest, shortest, random, daily, most-played, most-downloaded, or specific-clip modes |
| `mod_audioarchive_tags` | Site module | Displays Audio Archive tags with descriptions, optional clip counts, and links to a filtered Archive |
| `plg_finder_audioarchive` | Smart Search plugin | Adds eligible Audio Archive clips to Joomla Smart Search |
| `plg_quickicon_audioarchive` | Quick Icons plugin | Adds an Audio Archive shortcut to the administrator Home Dashboard |
| `plg_content_audioarchive` | Content plugin | Embeds random, specific, longest, or shortest eligible clips with configurable Featured-player data views and inserts aggregate clip counts or playtime into prepared content |

Install the package ZIP rather than installing its individual extension ZIP files separately.

```text
pkg_audioarchive_v0-8-9.zip
```

## Requirements and external tools

Audio Archive 0.8.9 requires:

- Joomla! 6.x
- PHP 8.3 or later
- MySQL or MariaDB
- PHP Fileinfo for reliable MIME-type inspection
- Writable configured storage and import directories

Core archive features—including metadata editing, filters, protected playback, downloads, modules, plugins, imports, and non-generation maintenance checks—work without FFmpeg or FFprobe. Waveform and spectral-analysis generation requires FFmpeg.

`proc_open()` must be available when Audio Archive is expected to execute FFmpeg or FFprobe. Some hosting providers disable external process execution; the dashboard System Check reports this explicitly.

### FFmpeg and FFprobe binaries

Audio Archive does not bundle FFmpeg or FFprobe executables.

FFmpeg is optional but required for generating waveform peak data and spectral-analysis images. FFprobe is also detected by the System Check, but **Audio Archive 0.8.9 does not use FFprobe for metadata extraction or any other production operation**. Duration, container, codec, embedded title, recording date, and related technical metadata are read by the bundled PHP media inspector.

Recommended sources:

- [Official FFmpeg download page](https://ffmpeg.org/download.html) — the preferred starting point; FFmpeg publishes source code and links to third-party providers of ready-to-use builds.
- [FFbinaries downloads](https://ffbinaries.com/downloads) — a convenient unofficial source of separately packaged FFmpeg and FFprobe executables for several platforms.

FFbinaries is an open-source, unofficial repackaging service. Its documentation identifies the upstream build providers and stores the packaged binaries on GitHub. However, its latest listed release is FFmpeg 6.1 from December 2023, and the reviewed download pages do not present a prominent signature or checksum-verification workflow. Treat it as a convenient compatibility source rather than the primary source for a current production binary. Prefer a recent build linked from the official FFmpeg download page when the hosting platform allows it.

Store uploaded executables outside the public web root where possible. When that is impossible, place them in a server-protected directory. Configure the complete executable path under **Components → Audio Archive → Options → Processing** and confirm it through the dashboard System Check.

## Installing and updating the package

To install Audio Archive:

1. Open **System → Install → Extensions** in the Joomla administrator.
2. Upload `pkg_audioarchive_v0-8-9.zip`.
3. Open **Components → Audio Archive**.
4. Review the dashboard and component options before importing files.

Install newer package versions directly over the existing installation. Do not uninstall the existing package as an update procedure, because uninstallation removes component database records.

After an update, Joomla may retain cached administrator forms. If a newly added option is not visible, clear the Joomla administrator cache and reopen the component options.

## Using the Audio Archive component

### Initial configuration

Open:

```text
Components → Audio Archive → Options
```

Review the following settings before importing the archive:

- Frontend archive access level
- Default category
- Default access level for new clips
- Default publication state
- Original-file storage directory
- Reserved compatibility-preview storage directory (0.8.9 does not generate playback previews)
- Analysis-data storage directory for waveform and spectral-analysis files
- Import inbox directory
- Permitted extensions and MIME types
- Maximum file size and duration
- Duplicate policy
- Recording-date policy
- Public filters, result columns, ordering, and pagination
- Shared player defaults and styling
- Clip-detail player presentation
- Backend preview player presentation
- Playback and download settings
- Clip detail-page fields
- FFmpeg path and optional FFprobe diagnostic path
- Waveform and spectrum generation, detail levels, automatic queueing, process timeout, and retry limit
- Uninstallation media-retention policy

The **Processing** tab is divided into three groups:

- **Clip analysis** — waveform generation, waveform detail, and automatic waveform queueing after upload, import, or replacement
- **Spectrum generation** — spectral-analysis generation, output detail, intensity scale, frequency scale, minimum and maximum frequency, dynamic range, and automatic queueing
- **Processing** — explicit FFmpeg and FFprobe paths, external-process timeout, and maximum processing attempts

The default spectrum-generation values are:

```text
Intensity scale:   Cube root
Frequency scale:   Logarithmic
Minimum frequency: 30 Hz
Maximum frequency: 8000 Hz
Dynamic range:     80 dB
```

Changing waveform or spectrum-generation options does not silently replace existing generated data. Use **Regenerate all waveforms** or **Regenerate all spectral analyses** on the Integrity & Maintenance page to queue a complete rebuild through the normal analysis job system.

The same component configuration is also available through Joomla's Global Configuration.

Configured FFmpeg and FFprobe paths may be absolute server paths or paths relative to the Joomla root. For example:

```text
audioarchive/ffbin/ffmpeg
audioarchive/ffbin/ffprobe
```

A relative executable path must remain inside the Joomla root.

### Dashboard and system check

Open:

```text
Components → Audio Archive
```

The dashboard provides archive statistics and checks the database schema, configured directories, PHP capabilities, FFmpeg, and optional FFprobe. The shared waveform and spectral-analysis location is reported as **Analysis data storage**. Where supported, missing managed-storage directories can be created from the System Check.

For FFmpeg and FFprobe, the system check reports:

- The resolved executable path
- Whether the file exists, is readable, and is executable
- Whether PHP can launch it
- Its reported version
- Whether execute permission was added automatically

Audio Archive checks an explicitly configured path first. It then tries the executable name through the server's `PATH`, followed by `/usr/bin` and `/usr/local/bin`. If an explicitly configured Unix executable lacks execute bits, Audio Archive attempts to add them when the hosting account has sufficient permission. Otherwise, the dashboard reports the permission problem explicitly.

FFmpeg is required for waveform and spectral-analysis generation. FFprobe is optional and is currently only located and version-tested by the System Check. Audio Archive 0.8.9 performs media metadata extraction with its bundled PHP inspector, whether or not FFprobe is installed. The 0.8.9 System Check may nevertheless display FFprobe as the metadata-extraction method when it is detected; that status label is inaccurate and does not reflect the actual extraction path.

The dashboard also displays the installed Audio Archive version, provides actions for resetting all recorded play counts or all recorded download counts, and shows the combined managed-storage size with separate totals for current original clip files, waveform data, and spectral analyses. These totals use the recorded sizes of currently referenced files and therefore do not require a filesystem scan; stale or unreferenced files remain part of the manual maintenance checks.

Audio Archive 0.8.9 does not generate compatibility playback files and does not transcode originals. Public playback streams the current original file. The preview directory, preview status column, and stale-preview maintenance support remain as compatibility scaffolding for legacy or manually created preview records.

### Adding clips

Audio clips can be added or updated in several ways.

#### Single clip

Open **Clips**, select **New**, upload the original file, and enter or adjust its metadata.

A clip can contain:

- Title and alias
- Description
- Category
- Tags
- Recording date
- Access level
- Publication state and dates
- Original audio file
- Extracted technical metadata
- Play and download counts
- Generated waveform and spectral analyses

Existing clips include a protected administrator preview using the shared player presentation selected in the component options. Playback from the editor does not increase public play statistics.

When analysis generation is enabled, the edit form provides **Generate waveform** or **Regenerate waveform**, plus **Generate spectral analysis** or **Regenerate spectral analysis**. New uploads can queue either or both analyses automatically.

An existing clip can receive a replacement original file from its edit form. The replacement preserves the clip ID, title, alias, category, tags, counters, access level, and public route. Technical metadata is recalculated. Existing waveform and spectral-analysis derivatives are marked stale; a legacy preview record is also marked stale if one exists. When automatic analysis queueing is enabled, a replacement can queue new waveform and spectral-analysis jobs.

#### Browser bulk upload

Open **Upload** to select or drag several files into the browser.

The upload view processes files individually and supports shared settings for:

- Category
- Tags
- Access level
- Publication state
- Recording-date override

Each file receives its own progress, result, duplicate warning, and edit link. One failed upload does not stop the remaining queue. When automatic analysis queueing is enabled, each successfully added clip receives the configured waveform and spectral-analysis jobs.

#### Server inbox import

Place files in the configured import inbox and open **Import**.

In **Import new clips** mode, the importer can:

- Scan the inbox recursively
- Inspect files before importing them
- Exclude hidden files and symbolic links
- Select individual files
- Apply shared category, tag, access, and publication settings
- Derive nested Joomla categories from the inbox folder structure
- Create missing categories where permitted
- Override the component duplicate policy for the current import
- Remove an inbox file after a successful transfer into managed storage

The importer only works inside the configured inbox and does not provide arbitrary filesystem browsing. When automatic analysis queueing is enabled, successfully imported clips receive the configured waveform and spectral-analysis jobs.

#### Bulk replacement of existing files

The Import page also provides a distinct **Replace existing clip files** mode. It is intended for migrations such as replacing ALAC `.m4a` originals with externally converted browser-compatible files while preserving all clip records and URLs.

Replacement files are matched against the stored original filenames without their extensions. Matching:

- Is case-insensitive
- Treats spaces, hyphens, underscores, repeated separators, and Unicode dash characters as equivalent
- Requires exactly one existing clip to match
- Blocks ambiguous matches
- Detects byte-for-byte identical files
- Warns when the replacement duration differs from the current duration

A successful bulk replacement preserves:

- Clip ID
- Title and alias
- Category and tags
- Access and publication state
- Play and download counters
- Public URL

The old original can either be deleted immediately or retained. Retention is enabled by default because it is safer for large migration runs. Retained originals become unreferenced cleanup candidates on the **Integrity & Maintenance** page. Existing waveform and spectral analyses are marked stale, and automatic analysis queueing can schedule regeneration for the replacement.

Audio Archive does not transcode files itself in this workflow. Conversion is performed externally; the resulting files are then placed in the import inbox and assigned through replacement mode.

### Managing clips

The Clips view uses Joomla's standard publication states:

- Published
- Unpublished
- Archived
- Trashed

Select clips and use **Batch** to move them to another category or add, replace, or clear tags. Permanent deletion is available only while viewing trashed clips.

The list supports Joomla access levels and category permissions. Individual clip access is also enforced by archive queries, clip detail pages, playback, analysis delivery, downloads, modules, content placeholders, tag counts, and Smart Search.

On a frontend clip detail page, an authorised user sees an **Edit clip** button when Joomla's global frontend-editing option is enabled. The button opens a native site-side form for title, alias, category, tags, description, recording date, and—when `core.edit.state` is granted—publication state, access level, and publication dates. Original-file replacement, analysis generation, and other technical actions remain administrator-only.

### Integrity and maintenance

Open:

```text
Components → Audio Archive → Integrity & Maintenance
```

The page opens without scanning the archive or managed storage. This keeps it responsive as the collection grows. Database-only analysis status counts and recorded disk-usage totals remain immediately available.

Run one of the three checks explicitly when current results are needed:

- **Integrity check** — inspects database relationships, managed paths, lightweight file state, tags, duplicate checksums, and abandoned jobs
- **Codec inventory** — groups original files by detected codec, container, and extension and provides lists of matching clips
- **Stale-file check** — scans managed storage for stale derivatives, unreferenced files, and abandoned temporary files

The page displays when the current check was performed. Results can be refreshed with **Run check again**; they are not stored as a historical report.

The non-destructive integrity report can identify issues such as:

- Missing categories
- Missing or orphaned original-file records
- Invalid or unsafe storage keys
- Missing, unreadable, or unexpected managed files
- Stored file-size inconsistencies
- Missing or invalid SHA-256 checksums
- Duration inconsistencies
- Availability-flag inconsistencies
- Invalid technical metadata
- Exact duplicates
- Missing, stale, failed, or unavailable analysis derivatives
- Unreferenced managed files
- Abandoned temporary files

The report can be exported as UTF-8 CSV.

For selected clips, the integrity results provide:

- **Verify selected** — checks existence, file size, and SHA-256
- **Reanalyse selected** — refreshes technical media metadata
- **Recalculate checksum & size** — updates the stored checksum and size and marks changed content for reanalysis

Clip repair operations process at most 50 selected clips per request.

The codec inventory reports:

- Audio codec
- Container format
- File extension
- Number of clips
- Total storage size

Selecting a codec displays every matching clip. This is useful for locating formats such as ALAC before preparing a bulk replacement run.

The stale-file results contain only cleanup candidates, including:

- Stale legacy compatibility previews
- Stale waveform, spectral-analysis, or other derived-analysis files
- Unreferenced managed files
- Abandoned temporary files

Current referenced originals are never eligible for stale-file cleanup. Before deletion, Audio Archive regenerates the current candidate list and revalidates every selected item so that files whose status changed after the check are not removed.

Large cleanup selections are processed automatically in sequential AJAX batches of at most 200 files. This avoids PHP input limits and oversized single requests while preserving the server-side safety limit and per-batch validation.


### Audio analyses

Audio Archive uses a generic derived-analysis subsystem for waveform and spectral data. Both analysis types share protected managed storage, database status records, processing jobs, retry handling, and access-controlled delivery endpoints.

FFmpeg is required for generating either analysis type. Missing or failed analyses never prevent publication, playback, or downloads.

#### Waveform generation

For each clip, Audio Archive decodes the first audio stream to mono PCM and stores compact minimum/maximum peak data rather than a pre-rendered image.

Available waveform detail levels are:

```text
256
512
1024
2048
4096
```

`1024` peak pairs is the default.

Waveforms support:

- Manual generation and regeneration from the clip editor
- Automatic queueing after upload, import, or replacement
- Missing, pending, available, failed, and stale states
- Protected frontend and backend delivery
- Played and unplayed colouring
- Moving playback position
- Click-to-seek interaction in the Featured player

#### Spectrum generation

Spectral analysis is generated by FFmpeg as a protected PNG spectrogram. Time runs horizontally and frequency runs vertically.

Available spectrum detail levels are:

| Detail | Output size |
| --- | --- |
| Low | 512 × 128 |
| Standard | 1024 × 192 |
| High | 1536 × 256 |
| Very high | 2048 × 320 |

The **Spectrum generation** options control:

- Enable spectral analysis
- Spectral-analysis detail
- Intensity scale
- Frequency scale
- Minimum frequency
- Maximum frequency
- Dynamic range
- Queue spectral analysis after upload

The default FFmpeg spectrum parameters are equivalent to:

```text
scale=cbrt
fscale=log
start=30
stop=8000
drange=80
```

The cube-root intensity scale preserves visible structure without exaggerating very quiet noise. The logarithmic frequency scale allocates more vertical space to musically useful low and mid frequencies. A maximum frequency of `0` uses the source Nyquist frequency.

Spectra support:

- Manual generation and regeneration from the clip editor
- Automatic queueing after upload, import, or replacement
- Missing, pending, available, failed, and stale states
- Protected frontend and backend delivery
- Moving playback-position marker
- Click-to-seek interaction in the Featured player

#### Analysis queues and regeneration

The **Audio analyses** section on the Integrity & Maintenance page displays separate waveform and spectral-analysis summaries. Each summary includes:

- Available
- Missing
- Pending
- Failed
- Stale
- Queued jobs
- Disk space occupied by the generated data recorded in the analysis table

Waveform actions include:

- **Queue missing waveforms**
- **Queue stale waveforms**
- **Retry failed waveforms**
- **Regenerate all waveforms**
- **Delete all waveform data**

Spectral-analysis actions include:

- **Queue missing spectral analyses**
- **Queue stale spectral analyses**
- **Retry failed spectral analyses**
- **Regenerate all spectral analyses**
- **Delete all spectral-analysis data**

Both **Regenerate all** actions queue every eligible clip with an available original file through the normal database-backed job system. Duplicate pending or running jobs are skipped. Processing uses the same queue list, progress display, retry handling, and **Process analysis queue** control as all other analysis operations.

The delete-all actions permanently remove the generated files and analysis database records for the selected type, reset every corresponding clip status to missing, and cancel pending or running jobs. Existing job rows are retained as history; active jobs are marked cancelled rather than deleted. Original audio files and the other analysis type are not affected.

The shared **Process analysis queue** action processes jobs incrementally and displays progress. Closing or reloading the page stops the browser loop but does not discard untouched jobs. Reopen the maintenance page and start processing again to continue. Interrupted running jobs are recovered after their processing lock expires and are retried up to the configured maximum attempt count.

Waveform and spectral files are delivered through protected component controllers. Public requests must pass the same publication, category, and access checks as playback. The backend uses a separate authorised endpoint so editors can preview analyses for unpublished or restricted clips.

### Publishing the public archive

Create a Joomla menu item:

1. Open the Joomla menu manager.
2. Create a new menu item.
3. Choose **Audio Archive → Audio Archive** as the menu-item type.
4. Configure its optional introductory text, category or tag restrictions, filters, columns, ordering, pagination, clip-detail settings, and download policy.
5. Set the menu item's Joomla access level as required.
6. Publish the menu item.

Each Archive menu item can override the component defaults. When several Archive menu items exist, clip links retain the appropriate menu context.

### Publishing a tag directory

Audio Archive provides a separate **Tag Directory** menu-item type.

Create one through the Joomla menu manager and choose:

```text
Audio Archive → Tag Directory
```

The Tag Directory can:

- Display optional introductory text above the directory
- Display all accessible Audio Archive tags or only selected tags
- Link each tag to a chosen Archive menu item
- Choose a suitable Archive menu item automatically
- Hide tags without accessible clips
- Order tags alphabetically, by clip count, or in the backend selection order
- Use card, list, or compact presentation
- Show or hide tag descriptions
- Show or hide accessible clip counts

Clip counts respect the current visitor's access and the category or tag restrictions of the target Archive menu item.

### Frontend access control

A Joomla menu item's access level only protects that menu item. Joomla components may also be reached directly through routes such as:

```text
/component/audioarchive
```

Audio Archive therefore has a component-wide **Frontend archive access** option under:

```text
Components → Audio Archive → Options → General
```

The selected Joomla access level is checked before any frontend Audio Archive controller or view is dispatched. It protects:

- Archive views
- Tag Directory views
- Clip detail pages
- Playback streams
- Waveform and spectral-analysis streams
- Original-file downloads
- Play-count requests
- Direct non-menu component URLs

Guests who do not satisfy the configured access level are redirected to Joomla's login page and returned to the originally requested URL after login. Logged-in users without the required access receive HTTP 403.

Menu-item access levels, category access, and individual clip access remain additional restrictions. The visitor must satisfy all applicable rules.

### Frontend clip editing

Frontend editing is available only when Joomla's global **Frontend Editing** option is enabled. Audio Archive then displays **Edit clip** on a detail page only when the current user has effective `core.edit` permission for that clip, or `core.edit.own` for a clip they created.

The frontend form supports:

- Title
- Alias
- Category
- Tags
- Description
- Recording date
- Publication state, access level, and publication dates when `core.edit.state` is granted

The edit controller repeats the global-setting and item-level ACL checks for direct edit requests and saves. It uses Joomla form validation, CSRF protection, checkout and check-in handling, filtered editor content, and internal return-URL validation. Moving a clip to another category additionally requires suitable create or edit permission in the target category.

Frontend editing does not expose original-file replacement, media inspection, waveform or spectrum generation, counters, or other technical maintenance controls. **Save & Close** and **Cancel** return to the clip detail page from which editing was opened; **Apply** saves while keeping the edit form open.

### Public filtering

The public filter form uses HTTP GET, so filtered archive URLs can be bookmarked or shared.

Available filters include:

- Text search
- Category
- Multiple tags using selectable logical AND or OR
- Minimum and maximum duration
- Recording date from and to
- Upload date from and to

The tag list includes compact **AND** and **OR** radio buttons:

- **AND** returns only clips containing every selected tag.
- **OR** returns clips containing at least one selected tag.

The selected mode is retained in filtered URLs, sorting links, and pagination. A **Clear tag filter** action removes the selected visitor tags without resetting the other archive filters. Tag restrictions configured on the Archive menu item remain mandatory in either mode.

Duration values can be entered as seconds or as formatted times:

```text
90
01:30
1:02:30
```

With JavaScript enabled, the duration fields are accompanied by a two-handle slider. The text fields remain the submitted values and continue to work without JavaScript.

Audio Archive stores the visitor's last-used filter values, tag mode, sorting, sort direction, and page size in the Joomla session. Returning to the same Archive menu item restores that state when no explicit filter state is present in the URL. The **Back to the archive** link on clip detail pages includes the canonical filter and sorting query, so the restored archive state remains visible, bookmarkable, and shareable. Guest visitors are supported through Joomla's anonymous session cookie; the state is temporary and tied to that browser session.

State is stored independently for each Archive menu item, so differently configured archive pages do not overwrite one another. The **Reset** action clears the stored state for the current menu item and returns to its configured defaults.

### Tags and tag descriptions

Tags displayed in the Archive, Tag Directory, modules, embedded clips, and clip detail pages link back to the appropriate Archive menu item with the corresponding tag filter applied.

When a Joomla tag has a description, Audio Archive adds that description as the tag link's standard HTML `title` attribute. Browsers therefore display the description as a native tooltip when the pointer hovers over the tag.

### Playback and downloads

Playback, analysis, and download requests pass through component controllers that verify:

- Component-wide frontend access
- Clip publication state and dates
- Category publication and access
- Joomla access levels
- Managed-file availability and path containment
- Download configuration and permitted Joomla access level where applicable

Playback supports byte-range requests for seeking. Downloads use the original filename while keeping the internal managed filename and filesystem path private.

The detail-page download button can be configured globally and overridden by an Archive menu item. It can be hidden completely or limited to the selected Joomla access level.

#### Shared player presentations

Audio Archive has one shared player renderer with four presentations:

| Presentation | Controls |
| --- | --- |
| **Minimal** | Play/pause button only |
| **Compact** | Play/pause, seek bar, current time, and duration |
| **Default** | Compact controls plus mute and volume controls |
| **Featured** | Default controls plus switchable waveform and spectrum views with moving playback position and click-to-seek interaction |

The archive table and mobile archive cards use the Minimal player. Clip detail pages, backend previews, modules, and content-plugin embeds can use any presentation permitted by their settings.

The Featured player can display waveform data, spectral data, or both. When both analyses are available, compact **Waveform** and **Spectrum** controls let the visitor switch views. When only one is available, that view is shown directly. When neither is available, the analysis area is omitted and the controls remain usable.

A preferred default data view can be set globally. If the preferred analysis is unavailable for a clip, the player automatically falls back to the other available analysis.

All shared players use progressive enhancement. The generated HTML contains native browser `<audio controls>` first. JavaScript hides those controls only after the custom player has initialised successfully. Audio therefore remains playable when JavaScript is disabled or fails.

Under **Playback and Downloads → Player style**, global options control:

- Default module and embedded-player presentation
- Preferred default data view: Waveform or Spectrum
- Backend preview presentation
- Player background colour
- Player text colour
- Player control colour
- Unplayed waveform colour
- Played waveform and playhead colour
- Player corner radius
- Play-button size for each presentation
- Featured waveform height

Clip detail pages have their own global presentation setting, which Archive menu items can override.

#### Template overrides and custom styling

The shared player markup can be replaced with a Joomla template override:

```text
templates/<template>/html/layouts/com_audioarchive/player/unified.php
```

The override is used by archive rows and cards, clip detail pages, Audio Archive modules, and content-plugin embeds. When no override exists, Audio Archive uses its bundled component layout.

The administrator clip preview uses the same bundled player renderer, but the site-template override above does not currently replace the backend preview layout.

A structural override should preserve the `data-audioarchive-*` attributes and the essential player class names expected by the bundled JavaScript. Visual changes that do not require different markup can instead be placed in the site's template CSS by targeting classes such as:

```text
.audioarchive-custom-player
.audioarchive-custom-player-toggle
.audioarchive-custom-player-seek
.audioarchive-custom-player-times
.audioarchive-custom-player-volume-controls
.audioarchive-custom-player-analysis
```

The built-in stylesheet also exposes player CSS custom properties, including colours, corner radius, button size, waveform height, and played or unplayed waveform colours. Template CSS can override these globally or within a specific module or page context.

## Using the Audio Archive module

The package contains one configurable clip module:

```text
mod_audioarchive
```

Create an instance through:

```text
Content → Site Modules → New → Audio Archive
```

Depending on the Joomla administrator menu configuration, modules may also be available under **System → Manage → Site Modules**.

### Selection modes

The module can display:

- Latest clips
- Longest clips
- Shortest clips
- Random clips
- A stable clip of the day
- Most-played clips
- Most-downloaded clips
- A specific clip

Longest and shortest mode order eligible clips by duration and use recording date and clip ID as stable tie-breakers. The result can be restricted by category and tags. Multiple selected tags can use logical **ALL** or **ANY**, and the number of displayed clips is configurable where the selected mode permits several results.

### Module layouts

The module's own layout controls how clip metadata is arranged around the player:

```text
default
compact
featured
```

This setting is independent of the player presentation. For example, the Featured module layout can contain a Minimal, Compact, Default, or Featured player.

### Player presentation

When **Show player** is enabled, the module offers:

- **Use component default**
- **Minimal**
- **Compact**
- **Default**
- **Featured**

The inherited value comes from **Playback and Downloads → Player style → Default module and embedded-player presentation**.

The module also provides **Preferred data view**:

- **Use component default**
- **Waveform**
- **Spectrum**

This option affects Featured players only. If the selected analysis is unavailable, the player uses the other available analysis automatically.

Wide players receive their own full-width row in the module layout. Metadata settings such as **Show duration** therefore do not change the player's internal appearance or proportions.

### Display options

The module can independently show or hide:

- Title
- Title link
- Player
- Duration metadata
- Date
- Category
- Tags
- Description
- Play and download counters
- Clip detail link
- Original download link

The module uses the component's protected playback, analysis, and download endpoints, clip access and publication checks, playback counting, shared player JavaScript and styling, and menu-aware SEF routing. Access to links and media endpoints is additionally governed by the component-wide frontend access setting. Tag links lead back to the appropriate Archive menu item, and tag descriptions are available as native hover tooltips. The optional category value is displayed as metadata rather than as a link.

Random mode should normally be used without module caching when a new random selection is expected on each request. Clip-of-the-day mode produces a stable daily selection.

## Using the Audio Archive Tags module

The package also contains:

```text
mod_audioarchive_tags
```

Create an instance through:

```text
Content → Site Modules → New → Audio Archive Tags
```

The module displays Audio Archive tags and links each one to a filtered Archive view.

### Tag selection and archive linking

The module can:

- Display every accessible tag used by Audio Archive clips
- Display only a selected subset of tags
- Choose the target Archive menu item automatically
- Link to a specifically selected Archive menu item
- Hide tags that have no accessible clips in the target Archive
- Order by title, accessible clip count, or backend selection order

Automatic target selection prefers the current Archive context where possible, then an accessible Archive menu item in the current language.

### Tag-module presentation

Available presentations are:

```text
cards
list
compact
```

The module can show or hide:

- Tag descriptions
- Accessible clip counts

Counts include only clips the current visitor can access and respect the restrictions of the target Archive menu item. The module is uncached by default so access-sensitive counts and links remain current.

## Using the Smart Search plugin

The package includes:

```text
plg_finder_audioarchive
```

On a fresh package installation, the **Smart Search - Audio Archive** plugin is enabled automatically. Package updates preserve the administrator's existing enabled or disabled state. It can be reviewed under:

```text
System → Manage → Plugins
```

For the initial index:

1. Open **Components → Smart Search**.
2. Select **Index**.
3. Wait for Joomla to finish indexing the available clips.

The plugin indexes clip information including:

- Title
- Description
- Original filename
- Category
- Tags
- Recording date
- Upload date
- Author
- Language

The index is kept in sync when clips are saved, uploaded, imported, replaced, unpublished, trashed, or deleted. Search results use the component's protected clip detail pages and menu-aware SEF routes. Clip, category, publication, language, and access rules determine index eligibility, while opening a result is additionally subject to the component-wide frontend access setting.

## Using the Quick Icons plugin

The package includes:

```text
plg_quickicon_audioarchive
```

On the first package installation, the plugin is enabled automatically. Its enabled or disabled state is preserved during package updates.

The plugin adds an **Audio Archive** music-icon shortcut to the **Site** group on Joomla's administrator Home Dashboard. The shortcut opens the Audio Archive dashboard.

The icon is only shown to users authorised to manage `com_audioarchive`.

The plugin can be enabled or disabled under:

```text
System → Manage → Plugins
```

## Using the Content plugin

The package includes:

```text
plg_content_audioarchive
```

On the first package installation, the plugin is enabled automatically. It processes placeholders in Joomla articles and other frontend content handled by Joomla's content preparation event.

### Embedding clips

Embed a random clip:

```text
{audioarchive random}
```

Select a player presentation explicitly:

```text
{audioarchive random layout=compact}
```

Embed a specific clip by alias:

```text
{audioarchive clip=some-clip-alias}
```

Embed a specific clip by ID:

```text
{audioarchive clip=123}
```

Embed a routed ID and alias:

```text
{audioarchive clip=123-some-clip-alias}
```

### Longest and shortest clips

Embed the single longest eligible clip:

```text
{audioarchive longest}
```

Embed the single shortest eligible clip:

```text
{audioarchive shortest}
```

Use `count` to display several clips:

```text
{audioarchive longest count=3}
{audioarchive shortest count=5}
```

The value is limited to 1–50 clips and defaults to 1. A player presentation can be selected at the same time:

```text
{audioarchive longest count=3 layout=compact}
{audioarchive shortest count=5 layout=featured}
```

Longest and shortest selections use the same public eligibility checks as other embedded clips. Duration is the primary ordering criterion; recording date and clip ID provide stable tie-breaking.

### Embedded player presentations

The `layout` attribute selects one of the four shared player presentations:

```text
{audioarchive clip=some-clip-alias layout=minimal}
{audioarchive clip=some-clip-alias layout=compact}
{audioarchive clip=some-clip-alias layout=default}
{audioarchive clip=some-clip-alias layout=featured}
```

Supported values are:

```text
minimal
compact
default
featured
```

When `layout` is omitted, the plugin uses its configured presentation. That setting can inherit **Default module and embedded-player presentation** from the component options.

The plugin's surrounding clip metadata layout remains consistent; the `layout` attribute changes the player presentation, not the module template.

For the Featured presentation, `dataview` selects the preferred initial analysis:

```text
{audioarchive random layout=featured dataview=waveform}
{audioarchive random layout=featured dataview=spectrum}
{audioarchive clip=123 layout=featured dataview=spectrum}
{audioarchive longest count=3 layout=featured dataview=spectrum}
```

Supported values are:

```text
waveform
spectrum
spectrogram
```

When `dataview` is omitted, the content plugin uses its own **Preferred data view** option. That option can inherit the component default. If the preferred analysis is unavailable, the player automatically uses the other available analysis.

### Archive clip counts

Insert the total number of eligible clips in the archive:

```text
{audioarchive count}
```

Restrict the count to one or more categories by supplying a comma-separated list of category IDs, aliases, or exact titles:

```text
{audioarchive count category=music,soundfx}
```

The values for all selected categories are combined into one total.

### Archive playtime

Insert the combined formatted duration of all eligible clips:

```text
{audioarchive playtime}
```

Restrict the total playtime to one or more categories:

```text
{audioarchive playtime category=music,soundfx}
```

The durations of clips in the selected categories are summed and inserted as one formatted playtime value.

### Content-plugin behaviour

Random, specific, longest, and shortest embedded clips reuse the module's shared clip-selection and eligibility logic, but use the player presentation and preferred Featured-player data view selected by the placeholder or plugin configuration. They inherit the component's publication, category, clip-access, language, file-availability, routing, playback, protected analysis, download, and counting logic. Links and protected media endpoints are additionally subject to the component-wide frontend access setting.

The plugin has independent options for showing or hiding:

- Title and title link
- Duration metadata
- Date
- Category
- Tags
- Description
- Detail link
- Download link
- Play and download counts

Tag links retain the appropriate Archive menu context, and tag descriptions are available through standard browser tooltips. Category output is plain metadata rather than a link.

Count and playtime placeholders use the same public eligibility rules so that unpublished, inaccessible, or otherwise unavailable clips are not exposed through aggregate values.

Malformed Audio Archive placeholders are left visible so syntax mistakes can be found. When a referenced clip is unavailable, the plugin can either display a translated unavailable message or remove the placeholder silently, according to its plugin settings.

The plugin can be configured under:

```text
System → Manage → Plugins → Content - Audio Archive
```
