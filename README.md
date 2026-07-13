# Joomla! Audio Archive

Audio Archive is a native Joomla! 6 extension package for managing and publishing collections of audio clips.

It is intended for archives ranging from a small collection to several thousand files. Administrators can upload or import clips, organise them with Joomla categories and tags, edit their metadata, control publication and access, and review playback and download statistics. Visitors can search and filter the archive, play clips in the browser, open clip detail pages, and download the protected original files.

> **Current version:** `0.6.0-dev3`  
> **Package:** `pkg_audioarchive`

## What Audio Archive offers

### Administrator features

- Dashboard with clip, publication, storage, playback, download, and system information
- Buttons for resetting all play counts or all download counts
- Joomla-style clip management with publication states, access levels, categories, tags, sorting, and filtering
- Single-file upload
- Browser-based bulk upload with per-file progress and results
- Import from a configurable server-side inbox
- Optional recursive import
- Optional conversion of inbox folders into nested Joomla categories
- Automatic extraction of duration, format, codec, file size, embedded title, and recording date where available
- SHA-256 duplicate detection with configurable handling
- Safe replacement of an existing clip's original file
- Protected audio preview in the clip editor
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
- Minimum and maximum duration filtering
- Recording-date and upload-date ranges
- Sortable result columns
- Server-side Joomla pagination
- Configurable page sizes, filters, columns, and detail-page fields
- Responsive table and card presentation
- Protected inline playback with HTTP byte-range seeking
- One-player-at-a-time behaviour
- SEF clip detail URLs
- Breadcrumb integration
- Page titles, metadata, and canonical routes
- Correct routing when several Audio Archive menu items exist
- Protected downloads of original files
- Aggregate play and download counters
- Joomla publication-date, category, language, and access-level enforcement
- English and German site interfaces

The component keeps original audio files in managed storage and never exposes their filesystem paths. Browser playback support depends on the container and codec supported by the visitor's browser; authorised original files remain downloadable.

## Package contents

The package installs the following Joomla extensions:

| Extension | Type | Purpose |
| --------- | ---- | ------- |
| `com_audioarchive` | Component | Administration, importing, metadata, public archive, clip pages, playback, downloads, routing, and statistics |
| `mod_audioarchive` | Site module | Displays selected clips using latest, random, daily, popular, downloaded, or specific-clip modes |
| `plg_finder_audioarchive` | Smart Search plugin | Adds Audio Archive clips to Joomla Smart Search |
| `plg_quickicon_audioarchive` | Quick Icons plugin | Adds an Audio Archive shortcut to the administrator Home Dashboard |
| `plg_content_audioarchive` | Content plugin | Embeds random or specified clips in articles and other prepared content |

Install the package ZIP rather than installing its individual extension ZIP files separately.

```text
pkg_audioarchive-0.6.0-dev3.zip
```

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

The same configuration is also available through Joomla's Global Configuration component settings.

### Dashboard and system check

Open:

```text
Components → Audio Archive
```

The dashboard provides archive statistics and verifies the database, configured directories, PHP capabilities, and optional FFmpeg or FFprobe executables. Where supported, missing managed-storage directories can be created from the system check.

The dashboard also provides actions for resetting all recorded play counts or all recorded download counts.

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

Existing clips include a protected administrator player. Playback from the editor does not increase public play statistics.

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
4. Configure its category or tag restrictions, filters, columns, ordering, pagination, and clip-detail settings.
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

Tags shown on clips link back to the Archive with the corresponding tag filter applied.

### Playback and downloads

Playback and download requests pass through component controllers that verify:

- Clip publication state and dates
- Category publication and access
- Joomla access levels
- Language eligibility
- Managed-file availability and path containment

Playback supports byte-range requests for seeking. Downloads use the original filename while keeping the internal managed filename and filesystem path private.

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

The module uses the component's protected playback and download endpoints, access and publication checks, playback counting, player JavaScript, styling, and menu-aware SEF routing. Category and tag links lead back to the appropriate Archive menu item.

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

### Embed a random clip

```text
{audioarchive random}
```

A layout can be selected explicitly:

```text
{audioarchive random layout=compact}
```

### Embed a specific clip by alias

```text
{audioarchive clip=some-clip-alias}
```

### Embed a specific clip by ID

```text
{audioarchive clip=123}
```

### Embed a routed ID and alias

```text
{audioarchive clip=123-some-clip-alias}
```

### Select a layout

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

The plugin reuses the module's presentation and the component's publication, category, access-level, language, file-availability, routing, playback, download, and counting logic.

Malformed Audio Archive placeholders are left visible so that syntax mistakes can be found. When a referenced clip is unavailable, the plugin can either display a translated unavailable message or remove the placeholder silently, according to its plugin settings.

The plugin can be configured under:

```text
System → Manage → Plugins → Content - Audio Archive
```
