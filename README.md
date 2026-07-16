# Joomla! Audio Archive

Audio Archive is a native Joomla! 6 extension package for managing and publishing collections of audio clips.

It is intended for archives ranging from a small collection to several thousand files. Administrators can upload or import clips, organise them with Joomla categories and tags, edit metadata, replace source files in bulk, inspect archive integrity, generate waveform analysis with the optional FFmpeg support, control publication and access, and review playback and download statistics. Visitors can search and filter the archive, browse a tag directory, use consistent responsive players throughout the site, open clip detail pages, and — where permitted — download the protected original files.

> **Current version:** `0.7.10`  
> **Package:** `pkg_audioarchive`

## Table of contents

- [What Audio Archive offers](#what-audio-archive-offers)
  - [Administrator features](#administrator-features)
  - [Public website features](#public-website-features)
- [Package contents](#package-contents)
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
  - [Audio analysis and waveforms](#audio-analysis-and-waveforms)
  - [Publishing the public archive](#publishing-the-public-archive)
  - [Publishing a tag directory](#publishing-a-tag-directory)
  - [Frontend access control](#frontend-access-control)
  - [Public filtering](#public-filtering)
  - [Tags and tag descriptions](#tags-and-tag-descriptions)
  - [Playback and downloads](#playback-and-downloads)
    - [Shared player presentations](#shared-player-presentations)
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

- Dashboard with clip, publication, storage, playback, download, waveform, and system information
- Dashboard display of the installed component version
- System checks for configured storage paths, PHP capabilities, FFmpeg, and FFprobe
- Absolute or Joomla-root-relative FFmpeg and FFprobe paths, automatic executable detection, version probing, and detailed failure reporting
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
- Manual waveform generation or regeneration from the clip editor
- Database-backed waveform queue with missing, pending, failed, stale, available, and queued status counts
- Incremental FFmpeg waveform processing with visible progress and retry handling
- Generic derived-analysis storage and job infrastructure prepared for future analysis types
- Batch category assignment
- Batch tag addition, replacement, and clearing
- Searchable tag selection in batch operations
- Integrity report with repair and verification actions
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
- Responsive desktop table and mobile card presentation
- Mobile cards that preserve readable tag and duration layouts on narrow screens
- Protected inline playback with HTTP byte-range seeking
- One shared player implementation used by archive rows, clip pages, modules, content embeds, and backend previews
- Four player presentations: Minimal, Compact, Default, and Featured
- Featured player with waveform seeking, played/unplayed colouring, and a moving playhead
- Automatic controls-only fallback when waveform data is unavailable
- Native browser audio controls when JavaScript is disabled or fails
- Configurable player colours, corner radius, button sizes, and waveform height
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
- Clickable category and tag links
- Tag descriptions exposed through standard browser hover tooltips
- An ACL-protected **Edit clip** link on frontend detail pages that opens the backend editor in a new tab
- Joomla publication-date, category, language, and access-level enforcement
- English and German site interfaces

The component keeps original audio files and generated analysis files in managed storage and never exposes their filesystem paths. Browser playback support depends on the container and codec supported by the visitor's browser; authorised original files remain downloadable when downloads are enabled for that visitor.

## Package contents

The package installs the following Joomla extensions:

| Extension | Type | Purpose |
| --- | --- | --- |
| `com_audioarchive` | Component | Administration, importing, replacement, integrity maintenance, FFmpeg system checks, waveform analysis, shared players, public archive, tag directory, clip pages, playback, downloads, routing, access control, and statistics |
| `mod_audioarchive` | Site module | Displays selected clips using latest, random, daily, popular, downloaded, or specific-clip modes |
| `mod_audioarchive_tags` | Site module | Displays Audio Archive tags with descriptions, optional clip counts, and links to a filtered Archive |
| `plg_finder_audioarchive` | Smart Search plugin | Adds eligible Audio Archive clips to Joomla Smart Search |
| `plg_quickicon_audioarchive` | Quick Icons plugin | Adds an Audio Archive shortcut to the administrator Home Dashboard |
| `plg_content_audioarchive` | Content plugin | Embeds random, specific, longest, or shortest eligible clips with any shared player presentation and inserts aggregate clip counts or playtime into prepared content |

Install the package ZIP rather than installing its individual extension ZIP files separately.

```text
pkg_audioarchive_v0-7-10.zip
```

## Installing and updating the package

To install Audio Archive:

1. Open **System → Install → Extensions** in the Joomla administrator.
2. Upload `pkg_audioarchive_v0-7-10.zip`.
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
- Preview-file storage directory
- Waveform and derived-analysis storage directory
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
- FFmpeg and FFprobe paths
- Waveform generation, detail level, automatic queueing, process timeout, and retry limit
- Uninstallation media-retention policy

The **Processing** tab is divided into two groups:

- **Clip analysis** — waveform generation, waveform detail, and automatic waveform queueing after upload, import, or replacement
- **Processing** — explicit FFmpeg and FFprobe paths, external-process timeout, and maximum processing attempts

The same component configuration is also available through Joomla's Global Configuration.

Paths to FFmpeg and FFprobe may be absolute server paths or paths relative to the Joomla root. For example:

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

The dashboard provides archive statistics and verifies the database, configured directories, PHP capabilities, and optional FFmpeg or FFprobe executables. Where supported, missing managed-storage directories can be created from the system check.

For FFmpeg and FFprobe, the system check reports:

- The resolved executable path
- Whether the file exists, is readable, and is executable
- Whether PHP can launch it
- Its reported version
- Whether execute permission was added automatically

Audio Archive checks explicitly configured paths first and can also search common executable locations when automatic detection is enabled. If an uploaded executable lacks Unix execute bits, Audio Archive attempts to add them when the hosting account has sufficient permission. Otherwise, the dashboard reports the permission problem explicitly.

FFmpeg is required for waveform generation. FFprobe is currently diagnosed and available to future media-analysis features.

The dashboard also displays the installed Audio Archive version and provides actions for resetting all recorded play counts or all recorded download counts.

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
- Generated waveform analysis

Existing clips include a protected administrator preview using the shared player presentation selected in the component options. Playback from the editor does not increase public play statistics.

When waveform generation is enabled, the edit form provides **Generate waveform** or **Regenerate waveform**. New uploads can also queue waveform generation automatically.

An existing clip can receive a replacement original file from its edit form. The replacement preserves the clip ID, title, alias, category, tags, counters, access level, and public route. Technical metadata is recalculated, and existing previews and analysis derivatives are marked stale where applicable. When automatic waveform queueing is enabled, a replacement queues a new waveform job.

#### Browser bulk upload

Open **Upload** to select or drag several files into the browser.

The upload view processes files individually and supports shared settings for:

- Category
- Tags
- Access level
- Publication state
- Recording-date override

Each file receives its own progress, result, duplicate warning, and edit link. One failed upload does not stop the remaining queue. When automatic waveform queueing is enabled, each successfully added clip receives a waveform job.

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

The importer only works inside the configured inbox and does not provide arbitrary filesystem browsing. When automatic waveform queueing is enabled, successfully imported clips receive waveform jobs.

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

The old original can either be deleted immediately or retained. Retention is enabled by default because it is safer for large migration runs. Retained originals become unreferenced cleanup candidates on the **Integrity & Maintenance** page. Existing waveform analysis is marked stale, and automatic waveform queueing can schedule regeneration for the replacement.

Audio Archive does not transcode files itself in this workflow. Conversion is performed externally; the resulting files are then placed in the import inbox and assigned through replacement mode.

### Managing clips

The Clips view uses Joomla's standard publication states:

- Published
- Unpublished
- Archived
- Trashed

Select clips and use **Batch** to move them to another category or add, replace, or clear tags. Permanent deletion is available only while viewing trashed clips.

The list supports Joomla access levels and category permissions. Individual clip access is also enforced by archive queries, clip detail pages, playback, analysis delivery, downloads, modules, content placeholders, tag counts, and Smart Search.

On a frontend clip detail page, a user who has `core.edit` permission for the clip—or `core.edit.own` for a clip they created—sees an **Edit clip** button. It opens the Joomla backend clip editor in a new tab, leaving the public page open.

### Integrity and maintenance

Open:

```text
Components → Audio Archive → Integrity & Maintenance
```

The integrity scan is non-destructive. It inspects database relationships, managed paths, file records, lightweight file properties, metadata state, analysis state, and storage consistency without deleting or moving files.

The report can identify issues such as:

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

For selected clips, the maintenance page provides:

- **Verify selected** — checks existence, file size, and SHA-256
- **Reanalyse selected** — refreshes technical media metadata
- **Recalculate checksum & size** — updates the stored checksum and size and marks changed content for reanalysis

Clip repair operations process at most 50 selected clips per request.

The **Original-file codec inventory** groups current originals by:

- Audio codec
- Container format
- File extension
- Number of clips
- Total storage size

Selecting a codec displays every matching clip. This is useful for locating formats such as ALAC before preparing a bulk replacement run.

The stale-file section lists only cleanup candidates, including:

- Stale compatibility previews
- Stale waveform or other analysis files
- Unreferenced managed files
- Abandoned temporary files

Current referenced originals are never eligible for stale-file cleanup. Every selected candidate is regenerated and revalidated immediately before deletion so that files which changed after the page was loaded are not removed.

Large cleanup selections are processed automatically in sequential AJAX batches of at most 200 files. This avoids PHP input limits and oversized single requests while preserving the server-side safety limit and per-batch validation.


### Audio analysis and waveforms

Audio Archive 0.7 introduced a generic derived-analysis subsystem. Waveforms are the first implemented analysis type; future frequency spectra, spectrograms, loudness data, or other analyses can use the same protected storage, database records, job queue, and delivery endpoints.

Waveform generation requires FFmpeg. For each clip, Audio Archive decodes the first audio stream to mono PCM and stores compact minimum/maximum peak data rather than a pre-rendered image. Available waveform detail levels are:

```text
256
512
1024
2048
4096
```

`1024` peak pairs is the default.

The **Waveforms** card on the Integrity & Maintenance page displays:

- Available
- Missing
- Pending
- Failed
- Stale
- Queued jobs

Available actions are:

- **Queue missing waveforms**
- **Queue stale waveforms**
- **Retry failed waveforms**
- **Process waveform queue**

The browser starts one processing request at a time and displays progress. The queue itself is stored in the database. Closing or reloading the page stops the browser loop but does not discard untouched jobs. Reopen the maintenance page and click **Process waveform queue** again to continue. Interrupted running jobs are recovered after their processing lock expires and are retried up to the configured maximum attempt count.

Waveform files are delivered through protected component controllers. Public waveform requests must pass the same publication, category, language, and access checks as playback. The backend uses a separate authorised endpoint so editors can preview waveforms for unpublished or restricted clips.

### Publishing the public archive

Create a Joomla menu item:

1. Open the Joomla menu manager.
2. Create a new menu item.
3. Choose **Audio Archive → Audio Archive** as the menu-item type.
4. Configure its category or tag restrictions, filters, columns, ordering, pagination, clip-detail settings, and download policy.
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
- Original-file downloads
- Play-count requests
- Direct non-menu component URLs

Guests who do not satisfy the configured access level are redirected to Joomla's login page and returned to the originally requested URL after login. Logged-in users without the required access receive HTTP 403.

Menu-item access levels, category access, and individual clip access remain additional restrictions. The visitor must satisfy all applicable rules.

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

### Tags and tag descriptions

Tags displayed in the Archive, Tag Directory, modules, embedded clips, and clip detail pages link back to the appropriate Archive menu item with the corresponding tag filter applied.

When a Joomla tag has a description, Audio Archive adds that description as the tag link's standard HTML `title` attribute. Browsers therefore display the description as a native tooltip when the pointer hovers over the tag.

### Playback and downloads

Playback, analysis, and download requests pass through component controllers that verify:

- Component-wide frontend access
- Clip publication state and dates
- Category publication and access
- Joomla access levels
- Language eligibility
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
| **Featured** | Default controls plus waveform, played-region colouring, moving playhead, and click-to-seek waveform interaction |

The archive table and mobile archive cards use the Minimal player. Clip detail pages, backend previews, modules, and content-plugin embeds can use any presentation permitted by their settings.

The Featured player requests waveform data only when an available waveform exists. If no waveform exists or the data cannot be loaded, its waveform area disappears and the controls remain usable.

All shared players use progressive enhancement. The generated HTML contains native browser `<audio controls>` first. JavaScript hides those controls only after the custom player has initialised successfully. Audio therefore remains playable when JavaScript is disabled or fails.

Under **Playback and Downloads → Player style**, global options control:

- Default module and embedded-player presentation
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
- Random clips
- A stable clip of the day
- Most-played clips
- Most-downloaded clips
- A specific clip

The result can be restricted by category and tags. Multiple selected tags can use logical **ALL** or **ANY**, and the number of displayed clips is configurable where the selected mode permits several results.

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

The module uses the component's protected playback, analysis, and download endpoints, clip access and publication checks, playback counting, shared player JavaScript and styling, and menu-aware SEF routing. Access to links and media endpoints is additionally governed by the component-wide frontend access setting. Category and tag links lead back to the appropriate Archive menu item, and tag descriptions are available as native hover tooltips.

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

Enable it under:

```text
System → Manage → Plugins
```

Search for **Audio Archive** and enable the **Smart Search - Audio Archive** plugin.

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

Random, specific, longest, and shortest embedded clips reuse the module's shared clip-selection and eligibility logic, but use the player presentation selected by the placeholder or plugin configuration. They inherit the component's publication, category, clip-access, language, file-availability, routing, playback, protected analysis, download, and counting logic. Links and protected media endpoints are additionally subject to the component-wide frontend access setting.

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

Category and tag links retain the appropriate Archive menu context, and tag descriptions are available through standard browser tooltips.

Count and playtime placeholders use the same public eligibility rules so that unpublished, inaccessible, or otherwise unavailable clips are not exposed through aggregate values.

Malformed Audio Archive placeholders are left visible so syntax mistakes can be found. When a referenced clip is unavailable, the plugin can either display a translated unavailable message or remove the placeholder silently, according to its plugin settings.

The plugin can be configured under:

```text
System → Manage → Plugins → Content - Audio Archive
```
