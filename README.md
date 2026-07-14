# Joomla! Audio Archive

Audio Archive is a native Joomla! 6 extension package for managing and publishing collections of audio clips.

It is intended for archives ranging from a small collection to several thousand files. Administrators can upload or import clips, organise them with Joomla categories and tags, edit their metadata, control publication and access, and review playback and download statistics. Visitors can search and filter the archive, play clips in the browser, open clip detail pages, and—where permitted—download the protected original files.

> **Current version:** `0.6.14`  
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
  - [Managing clips](#managing-clips)
  - [Publishing the public archive](#publishing-the-public-archive)
  - [Public filtering](#public-filtering)
  - [Tags and tag descriptions](#tags-and-tag-descriptions)
  - [Playback and downloads](#playback-and-downloads)
- [Using the Audio Archive module](#using-the-audio-archive-module)
  - [Selection modes](#selection-modes)
  - [Layouts](#layouts)
  - [Display options](#display-options)
- [Using the Smart Search plugin](#using-the-smart-search-plugin)
- [Using the Quick Icons plugin](#using-the-quick-icons-plugin)
- [Using the Content plugin](#using-the-content-plugin)
  - [Embedding clips](#embedding-clips)
  - [Archive clip counts](#archive-clip-counts)
  - [Archive playtime](#archive-playtime)
  - [Content-plugin behaviour](#content-plugin-behaviour)

## What Audio Archive offers

### Administrator features

- Dashboard with clip, publication, storage, playback, download, and system information
- Dashboard display of the installed component version
- ACL-protected buttons for resetting all play counts or all download counts
- Joomla-style clip management with publication states, access levels, categories, tags, sorting, and filtering
- Single-file upload
- Browser-based bulk upload with per-file progress and results
- Import from a configurable server-side inbox
- Optional recursive import
- Optional conversion of inbox folders into nested Joomla categories
- Automatic extraction of duration, format, codec, file size, embedded title, and recording date where available
- SHA-256 duplicate detection with configurable handling
- Safe replacement of an existing clip's original file
- Protected, styled audio preview in the clip editor
- Batch category assignment
- Batch tag addition, replacement, and clearing
- Searchable tag selection in batch operations
- Joomla ACL and category-based permission inheritance
- English and German administrator interfaces

### Public website features

- Searchable and filterable Archive menu-item type
- Text search across clip metadata
- Category filtering
- Multiple-tag filtering using logical **AND**
- Searchable tag checkbox list
- Minimum and maximum duration filtering
- JavaScript-enhanced duration slider with text-field fallback
- Recording-date and upload-date ranges
- Sortable result columns
- Server-side Joomla pagination
- Configurable page sizes, filters, columns, and detail-page fields
- Responsive desktop table and mobile card presentation
- Mobile cards that preserve readable tag and duration layouts on narrow screens
- Protected inline playback with HTTP byte-range seeking
- Styled players on clip detail pages
- One-player-at-a-time behaviour
- Clean, menu-aware SEF clip detail URLs
- Breadcrumb integration
- Page titles, metadata, canonical routes, and redirects from stale aliases or legacy URLs
- Correct routing when several Audio Archive menu items exist
- Configurable protected downloads of original files
- Optional restriction of detail-page downloads to selected Joomla user groups
- Aggregate play and download counters
- Clickable category and tag links
- Tag descriptions exposed through standard browser hover tooltips
- Joomla publication-date, category, language, and access-level enforcement
- English and German site interfaces

The component keeps original audio files in managed storage and never exposes their filesystem paths. Browser playback support depends on the container and codec supported by the visitor's browser; authorised original files remain downloadable when downloads are enabled for that visitor.

## Package contents

The package installs the following Joomla extensions:

| Extension | Type | Purpose |
| --- | --- | --- |
| `com_audioarchive` | Component | Administration, importing, metadata, public archive, clip pages, playback, downloads, routing, access control, and statistics |
| `mod_audioarchive` | Site module | Displays selected clips using latest, random, daily, popular, downloaded, or specific-clip modes |
| `plg_finder_audioarchive` | Smart Search plugin | Adds eligible Audio Archive clips to Joomla Smart Search |
| `plg_quickicon_audioarchive` | Quick Icons plugin | Adds an Audio Archive shortcut to the administrator Home Dashboard |
| `plg_content_audioarchive` | Content plugin | Embeds clips and inserts aggregate clip counts or playtime into prepared content |

Install the package ZIP rather than installing its individual extension ZIP files separately.

```text
pkg_audioarchive_v0-6-14.zip
```

## Installing and updating the package

To install Audio Archive:

1. Open **System → Install → Extensions** in the Joomla administrator.
2. Upload `pkg_audioarchive_v0-6-14.zip`.
3. Open **Components → Audio Archive**.
4. Review the dashboard and component options before importing files.

Install newer package versions directly over the existing installation. Do not uninstall the existing package as an update procedure, because uninstallation removes component database records.

## Using the Audio Archive component

### Initial configuration

Open:

```text
Components → Audio Archive → Options
```

Review the following settings before importing the archive:

- Default category
- Default access level
- Default publication state
- Original-file storage directory
- Import inbox directory
- Permitted extensions and MIME types
- Maximum file size and duration
- Duplicate policy
- Recording-date policy
- Public filters and result columns
- Playback and download settings
- Clip detail-page fields
- Detail-page download visibility
- Joomla user groups allowed to use detail-page downloads

The same component configuration is also available through Joomla's Global Configuration.

### Dashboard and system check

Open:

```text
Components → Audio Archive
```

The dashboard provides archive statistics and verifies the database, configured directories, PHP capabilities, and optional FFmpeg or FFprobe executables. Where supported, missing managed-storage directories can be created from the system check.

The dashboard also displays the installed Audio Archive version and provides actions for resetting all recorded play counts or all recorded download counts.

### Adding clips

Audio clips can be added in three ways.

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

Existing clips include a protected, styled administrator player. Playback from the editor does not increase public play statistics.

#### Browser bulk upload

Open **Upload** to select or drag several files into the browser.

The upload view processes files individually and supports shared settings for:

- Category
- Tags
- Access level
- Publication state
- Recording-date override

Each file receives its own progress, result, duplicate warning, and edit link.

#### Server inbox import

Place files in the configured import inbox and open **Import**.

The importer can:

- Scan the inbox recursively
- Inspect files before importing them
- Exclude hidden files and symbolic links
- Select individual files
- Apply shared category, tag, access, and publication settings
- Derive nested Joomla categories from the inbox folder structure
- Remove an inbox file after a successful transfer into managed storage

The importer only works inside the configured inbox and does not provide arbitrary filesystem browsing.

### Managing clips

The Clips view uses Joomla's standard publication states:

- Published
- Unpublished
- Archived
- Trashed

Select clips and use **Batch** to move them to another category or add, replace, or clear tags. Permanent deletion is available only while viewing trashed clips.

### Publishing the public archive

Create a Joomla menu item:

1. Open the Joomla menu manager.
2. Create a new menu item.
3. Choose **Audio Archive → Audio Archive** as the menu-item type.
4. Configure its category or tag restrictions, filters, columns, ordering, pagination, clip-detail settings, and download policy.
5. Publish the menu item.

Each Archive menu item can override the component defaults. When several Archive menu items exist, clip links retain the appropriate menu context.

### Public filtering

The public filter form uses HTTP GET, so filtered archive URLs can be bookmarked or shared.

Available filters include:

- Text search
- Category
- Multiple tags using logical AND
- Minimum and maximum duration
- Recording date from and to
- Upload date from and to

Duration values can be entered as seconds or as formatted times:

```text
90
01:30
1:02:30
```

With JavaScript enabled, the duration fields are accompanied by a two-handle slider. The text fields remain the submitted values and continue to work without JavaScript.

### Tags and tag descriptions

Tags displayed in the Archive, modules, embedded clips, and clip detail pages link back to the appropriate Archive menu item with the corresponding tag filter applied.

When a Joomla tag has a description, Audio Archive adds that description as the tag link's standard HTML `title` attribute. Browsers therefore display the description as a native tooltip when the pointer hovers over the tag.

### Playback and downloads

Playback and download requests pass through component controllers that verify:

- Clip publication state and dates
- Category publication and access
- Joomla access levels
- Language eligibility
- Managed-file availability and path containment
- Download configuration and permitted Joomla user groups where applicable

Playback supports byte-range requests for seeking. Downloads use the original filename while keeping the internal managed filename and filesystem path private.

The detail-page download button can be configured globally and overridden by an Archive menu item. It can be hidden completely or limited to selected Joomla user groups.

## Using the Audio Archive module

The package contains one configurable site module:

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

The result can be restricted by category and tags, and the number of displayed clips is configurable where the selected mode permits several results.

### Layouts

The module provides three layouts:

```text
default
compact
featured
```

### Display options

The module can independently show or hide:

- Title
- Player
- Duration
- Date
- Category
- Tags
- Description
- Play and download counters
- Clip detail link
- Original download link

The module uses the component's protected playback and download endpoints, access and publication checks, playback counting, player JavaScript, styling, and menu-aware SEF routing. Category and tag links lead back to the appropriate Archive menu item, and tag descriptions are available as native hover tooltips.

Random mode should normally be used without module caching when a new random selection is expected on each request. Clip-of-the-day mode produces a stable daily selection.

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

The index is kept in sync when clips are saved, uploaded, imported, replaced, unpublished, trashed, or deleted. Search results use the component's protected clip detail pages and menu-aware SEF routes, and Joomla applies the clip's publication and access rules.

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

Select a layout explicitly:

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

The content plugin uses the same layouts as the Audio Archive module:

```text
{audioarchive clip=some-clip-alias layout=default}
{audioarchive clip=some-clip-alias layout=compact}
{audioarchive clip=some-clip-alias layout=featured}
```

Supported layout values are:

```text
default
compact
featured
```

### Archive clip counts

Insert the total number of eligible clips in the archive:

```text
{audioarchive count}
```

Restrict the count to one or more categories by supplying a comma-separated list of category aliases:

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

Embedded clips reuse the module presentation and the component's publication, category, access-level, language, file-availability, routing, playback, download, and counting logic. Category and tag links retain the appropriate Archive menu context, and tag descriptions are available through standard browser tooltips.

Count and playtime placeholders use the same public eligibility rules so that unpublished, inaccessible, or otherwise unavailable clips are not exposed through aggregate values.

Malformed Audio Archive placeholders are left visible so that syntax mistakes can be found. When a referenced clip is unavailable, the plugin can either display a translated unavailable message or remove the placeholder silently, according to its plugin settings.

The plugin can be configured under:

```text
System → Manage → Plugins → Content - Audio Archive
```
