# Joomla! Audio Archive

## Project Specification

| Property | Value |
| -------- | ----- |
| Project name | Joomla! Audio Archive |
| Working package name | `pkg_audioarchive` |
| Target platform | Joomla! 6.x |
| Primary interface | Joomla administrator and public website |
| Primary use case | Publishing, browsing, filtering, playing, and downloading a large archive of audio clips |
| Expected initial archive size | More than 800 clips |
| Expected future archive size | Hundreds to several thousand clips |
| Storage | Local server filesystem |
| Content languages | Single-language content |
| Specification status | Initial implementation specification |
| Specification version | 1.0 |

---

## 1. Project overview

The Joomla! Audio Archive is a native Joomla! 6 extension package for managing and publishing a large collection of audio clips.

Each audio clip has:

- A public title
- An alias
- An original filename
- A description
- A duration
- A recording date
- An upload date
- A publication date
- A Joomla category
- Zero or more Joomla tags
- A Joomla access level
- A publication state
- An original audio file
- Optional derived playback files
- An optional pre-generated waveform
- Playback and download counters
- Optional Joomla Custom Fields

Visitors may:

- Browse the archive
- Search the archive
- Filter clips
- Sort the result table
- Play clips in the browser
- Open clip detail pages
- Download original files

Visitors may not:

- Upload files
- Edit metadata
- Delete clips
- Change categories or tags
- Publish or unpublish clips

Administration is performed through Joomla's administrator interface and follows Joomla conventions wherever practical.

---

## 2. Project goals

The component shall provide:

1. A reliable archive for hundreds or thousands of audio clips.
2. Bulk import facilities for the existing collection.
3. Automatic extraction of duration and embedded metadata.
4. Database-side filtering, sorting, and pagination.
5. Native integration with Joomla categories, tags, access levels, ACL, Custom Fields, routing, and Smart Search.
6. Inline browser playback where the file format is supported.
7. Optional generation of compatible playback versions.
8. Original-file downloads.
9. Optional pre-generated audio waveforms.
10. Joomla modules for latest and randomly selected clips.
11. Graceful operation when FFmpeg or FFprobe is unavailable.
12. A maintainable architecture that can be extended later.

---

## 3. Non-goals

The first release is not intended to provide:

- Public uploads
- Public metadata editing
- User playlists
- User favourites
- Comments or ratings
- A public REST API
- External application integration
- Live audio streaming
- Continuous playback across page changes
- Playback speed controls
- Automatic transcription
- Full digital asset management
- Complex editorial approval stages
- Mandatory RSS or podcast feeds
- Mandatory metadata export
- Filtering by every arbitrary Joomla Custom Field type
- Automatic tag normalisation or tag synonym management

These features may be considered in later versions.

---

## 4. Design principles

### 4.1 Joomla-native behaviour

The extension shall use standard Joomla functionality wherever appropriate:

- Joomla categories
- Joomla tags
- Joomla access levels
- Joomla ACL
- Joomla publication states
- Joomla Custom Fields
- Joomla routing
- Joomla pagination
- Joomla form definitions
- Joomla language files
- Joomla Smart Search
- Joomla Scheduled Tasks
- Joomla installer and update mechanisms

The extension shall not introduce custom replacements for standard Joomla functionality without a strong technical reason.

### 4.2 Server-side archive queries

Filtering, sorting, access checks, and pagination shall be performed by the server and database.

The complete archive shall never be loaded into JavaScript for client-side filtering.

### 4.3 Progressive enhancement

The public archive shall work without JavaScript for:

- Searching
- Filtering
- Sorting
- Pagination
- Opening details
- Downloading files

JavaScript may enhance:

- Inline playback
- Ensuring that only one clip plays at a time
- Waveform rendering
- Playback counting
- Multiple-file uploads
- Upload progress
- Administrative batch progress
- Searchable tag-selection controls

### 4.4 Original files are preserved

The component shall retain the original uploaded or imported audio file.

The original file shall be used for downloads unless an administrator explicitly configures otherwise.

Derived preview files shall never replace or modify the original.

### 4.5 Graceful degradation

The absence of FFmpeg, FFprobe, shell execution, waveform generation, or preview generation shall not prevent the archive from functioning.

A clip may still be:

- Imported
- Published
- Displayed
- Downloaded
- Played directly when browser-compatible

Missing optional derivatives shall be reported but shall not invalidate the clip.

---

## 5. Extension package structure

The installable package shall initially contain the following extensions.

### 5.1 Main component

```text
com_audioarchive
```

Responsibilities:

- Clip administration
- File imports
- Metadata extraction
- Category and tag integration
- Public archive view
- Public clip detail view
- File streaming
- File downloads
- Playback and download counts
- Configuration
- Processing jobs
- System diagnostics
- Joomla Custom Fields integration
- Routing and SEF URLs

### 5.2 Latest clips module

```text
mod_audioarchive_latest
```

Responsibilities:

- Display the latest eligible clips
- Respect publication state, dates, categories, tags, and access levels
- Include an inline player
- Link to clip detail pages

### 5.3 Random clip module

```text
mod_audioarchive_random
```

Responsibilities:

- Display a random eligible clip
- Support random-per-request mode
- Support stable clip-of-the-day mode
- Support category and tag restrictions
- Include an inline player
- Link to the clip detail page

### 5.4 Smart Search plugin

```text
plg_finder_audioarchive
```

Responsibilities:

- Index published clips
- Update the index when clips are created or changed
- Remove or hide entries when clips are unpublished, trashed, or deleted
- Generate correct detail-page routes
- Respect access levels

### 5.5 Scheduled Tasks plugin

```text
plg_task_audioarchive
```

Responsibilities:

- Process pending technical jobs
- Extract missing metadata
- Generate missing previews
- Generate missing waveforms
- Retry failed jobs according to configuration
- Process a configurable number of jobs per run

The Scheduled Tasks plugin is useful but shall not be required for ordinary frontend operation.

---

## 6. Audio format policy

### 6.1 General policy

The component shall distinguish between:

1. Formats accepted for archiving
2. Formats that can be analysed
3. Formats that can be played directly by a browser
4. Formats that can be converted into a compatible preview

A file may be accepted into the archive even when it cannot be played directly in a browser.

Such a file shall remain downloadable.

### 6.2 Default accepted extensions

The initial default list shall include:

```text
m4a
mp4
aac
mp3
ogg
oga
opus
wav
flac
webm
```

The administrator may change the permitted extension list in the component configuration.

The extension list alone shall not be considered sufficient validation.

### 6.3 M4A handling

Existing M4A files shall be preserved and shall not be converted wholesale.

M4A is a container and may contain different codecs, including:

- AAC
- AAC-LC
- HE-AAC
- ALAC
- Other MPEG-4 audio codecs

The component shall attempt to detect both:

- Container format
- Audio codec

Where an M4A file contains browser-compatible AAC audio, the original may be used for inline playback.

Where browser compatibility is uncertain, an optional preview may be generated when FFmpeg is available.

### 6.4 MP3 preview format

When a compatibility preview is required, MP3 shall be the default preview format.

The preview format and quality shall be configurable.

Possible configuration values include:

```text
Preview format: MP3
Bitrate: 128, 160, 192, 256, or 320 kbit/s
Channels: Preserve, mono, or stereo
Sample rate: Preserve or configured value
```

The default implementation does not require an Ogg preview in addition to MP3.

Support for additional derived formats may be added later.

### 6.5 Original downloads

The public download action shall deliver the original archive file.

Preview files shall normally be used only for browser playback.

---

## 7. Media validation

Uploaded and imported files shall be validated using several signals.

### 7.1 Validation stages

1. Validate filename extension.
2. Inspect MIME type using PHP Fileinfo.
3. Analyse the file using FFprobe when available.
4. Otherwise analyse the file using the bundled PHP metadata library.
5. Reject files that do not appear to be valid supported media files.
6. Record warnings when the extension, MIME type, and detected container disagree.

### 7.2 Validation result

The component shall record:

- Original extension
- Detected MIME type
- Detected container
- Detected audio codec
- Number of audio streams
- Duration
- Sample rate where available
- Channel count where available
- Bitrate where available
- Embedded metadata where available

The public interface does not need to display all technical metadata.

---

## 8. FFmpeg and FFprobe detection

### 8.1 Automatic detection

The component shall include a System Check page that determines whether FFmpeg and FFprobe are available.

The component shall test:

```text
ffprobe
ffmpeg
/usr/bin/ffprobe
/usr/bin/ffmpeg
/usr/local/bin/ffprobe
/usr/local/bin/ffmpeg
```

The administrator may also configure explicit paths.

### 8.2 Process execution checks

The component shall check:

- Whether `proc_open()` exists
- Whether process execution is disabled by PHP configuration
- Whether the configured executable exists
- Whether the configured executable is executable
- Whether the executable returns a successful version response
- Which version is installed
- Whether a test media file can be analysed

### 8.3 Secure process invocation

External processes shall be invoked using argument arrays rather than concatenated shell command strings.

User-controlled filenames shall never be interpolated into a shell command.

Execution shall use:

- Configurable timeouts
- Captured standard output
- Captured standard error
- Exit-code validation
- Sanitised diagnostic messages

### 8.4 System Check display

The System Check page shall display information such as:

```text
PHP Fileinfo:              Available
Process execution:         Available
FFprobe:                   /usr/bin/ffprobe
FFprobe version:           7.x
FFmpeg:                    /usr/bin/ffmpeg
FFmpeg version:            7.x
Metadata extraction:       FFprobe
Preview generation:        Available
Waveform generation:       Available
Original storage path:     Writable
Preview storage path:      Writable
Waveform storage path:     Writable
Import inbox:              Writable
```

If unavailable:

```text
Process execution:         Disabled
FFprobe:                   Not found
FFmpeg:                    Not found
Metadata extraction:       PHP fallback
Preview generation:        Unavailable
Waveform generation:       Unavailable
```

A Retest action shall be provided.

---

## 9. Metadata extraction fallback

### 9.1 Extraction hierarchy

Metadata shall be extracted using this order:

1. FFprobe
2. Bundled pure-PHP metadata analyser
3. Browser-provided duration during administrator upload
4. Manual administrator entry

The expected PHP fallback library is getID3 or an equivalent locally packaged library.

The selected dependency and exact version shall be locked during implementation.

### 9.2 Embedded metadata

The term embedded metadata shall cover format-specific systems such as:

- ID3
- MPEG-4 metadata atoms
- Vorbis comments
- FLAC metadata blocks
- RIFF metadata

The system shall not assume that all files use ID3 tags.

### 9.3 Automatic title creation

The initial public title shall be determined in this order:

1. Embedded title
2. Filename without extension
3. Generic fallback title

Filename cleanup shall:

- Remove the extension
- Replace underscores with spaces
- Collapse repeated whitespace
- Trim leading and trailing whitespace
- Preserve the filename's existing capitalisation
- Preserve meaningful hyphens unless configured otherwise

The generated title remains editable.

### 9.4 Recording date extraction

The initial recording date shall be determined in this order:

1. Embedded media creation or recording date
2. Filesystem modification date
3. Import date

The source shall be recorded internally as:

```text
embedded
filesystem
manual
import
```

The administrator may edit the resulting recording date.

Filesystem dates shall not be treated as unquestionably accurate.

---

## 10. File storage

### 10.1 Configurable paths

The following paths shall be configurable:

- Original-file storage directory
- Preview-file storage directory
- Waveform storage directory
- Import inbox directory

### 10.2 Storage outside the public web root

Where the hosting environment permits it, original and derived files should be stored outside the publicly accessible web root.

Files shall then be delivered through component controllers.

### 10.3 Protected storage inside the web root

If storage outside the web root is unavailable, the component shall:

- Store files in a protected directory
- Install appropriate access-denial rules where supported
- Avoid exposing direct file URLs
- Deliver files through the component

### 10.4 Internal filenames

Stored filenames shall not use the original client filename.

An internally generated identifier shall be used, such as a UUID.

Example:

```text
originals/4f/91/4f91d8e6-9a30-4e32-97ce-50de256f7f23.m4a
previews/4f/91/4f91d8e6-9a30-4e32-97ce-50de256f7f23.mp3
waveforms/4f/91/4f91d8e6-9a30-4e32-97ce-50de256f7f23.json
```

Directory sharding shall prevent very large numbers of files from accumulating in one directory.

### 10.5 Original filename

The original filename shall be stored separately in the database.

Changing a clip title or alias shall not rename or relocate the stored file unless a specific maintenance operation requests it.

### 10.6 Path security

All paths shall be normalised and validated.

The component shall prevent:

- Directory traversal
- Importing files outside the configured inbox
- Following symbolic links unless explicitly enabled
- Writing outside configured storage roots
- Direct use of arbitrary administrator-supplied paths in public requests

Symbolic-link traversal shall be disabled by default.

---

## 11. File size and duration limits

The component configuration shall include:

- Maximum file size
- Maximum audio duration
- Permitted extensions

A value of zero may be used to represent no component-specific limit.

The component-specific upload size cannot exceed PHP and web-server limits.

The administrator System Check shall display:

- `upload_max_filesize`
- `post_max_size`
- `max_file_uploads`
- `max_execution_time`
- Relevant Joomla upload limits

Directory imports are not constrained by browser upload-size limits, but remain subject to component limits and server storage capacity.

---

## 12. Database model

The precise schema may be adjusted during implementation, but the following logical data model is required.

### 12.1 Clips table

Suggested table:

```text
#__audioarchive_clips
```

Required fields:

```text
id
asset_id
uuid
catid
title
alias
description
original_filename
state
access
language
ordering
duration_ms
recorded_at
recorded_date_source
uploaded_at
publish_up
publish_down
created
created_by
modified
modified_by
checked_out
checked_out_time
play_count
download_count
metadata_status
preview_status
waveform_status
technical_metadata
params
```

Recommended field characteristics:

- `id`: unsigned integer primary key
- `asset_id`: reference to Joomla asset table
- `uuid`: globally unique identifier
- `catid`: Joomla category identifier
- `title`: public title
- `alias`: Joomla route alias
- `description`: long-form description
- `duration_ms`: duration stored as integer milliseconds
- `recorded_at`: nullable recording date
- `uploaded_at`: immutable archive upload/import timestamp
- `state`: Joomla publication state
- `access`: Joomla access-level identifier
- `play_count`: aggregate play counter
- `download_count`: aggregate download counter
- `technical_metadata`: structured JSON or equivalent storage
- `params`: per-item options

### 12.2 File variants table

Suggested table:

```text
#__audioarchive_files
```

Required fields:

```text
id
clip_id
file_role
storage_key
file_extension
mime_type
container_format
audio_codec
file_size
duration_ms
checksum_sha256
created
created_by
is_available
processing_error
```

`file_role` values shall initially include:

```text
original
preview
```

The original file record is mandatory.

The preview record is optional.

### 12.3 Waveform table

Suggested table:

```text
#__audioarchive_waveforms
```

Required fields:

```text
id
clip_id
storage_key
data_format
point_count
channel_mode
generated_at
generator
generator_version
is_available
processing_error
```

Only one active waveform is required per clip in the initial release.

### 12.4 Processing jobs table

Suggested table:

```text
#__audioarchive_jobs
```

Required fields:

```text
id
clip_id
job_type
state
priority
attempts
maximum_attempts
payload
last_error
created
started
finished
locked_by
locked_until
```

Supported job types shall initially include:

```text
extract_metadata
generate_preview
generate_waveform
verify_file
```

Supported job states shall include:

```text
pending
running
completed
failed
cancelled
```

These are technical processing states, not publication workflow states.

### 12.5 Joomla tags

Tags shall use Joomla's standard tag system.

The clip content type shall be registered as:

```text
com_audioarchive.clip
```

Tag relationships shall use Joomla's normal tag mapping infrastructure.

Tags are a flat vocabulary for this project. The component shall not implement:

- Tag aliases
- Synonym enforcement
- Duplicate-meaning detection
- Controlled vocabulary restrictions

### 12.6 Categories

Each clip shall have one primary Joomla category.

An `Uncategorised` category may be used as the default.

The default category for new and imported clips shall be configurable.

### 12.7 Custom Fields

The Custom Fields context shall be:

```text
com_audioarchive.clip
```

Custom Fields shall be stored using Joomla's normal field infrastructure.

Core archive properties such as duration, dates, state, category, title, and access shall remain native component columns rather than Custom Fields.

---

## 13. Database indexes

The schema shall include indexes supporting common archive queries.

At minimum:

```text
state
access
catid
duration_ms
recorded_at
uploaded_at
publish_up
publish_down
title
created_by
```

Composite indexes should be considered for:

```text
state + access + uploaded_at
state + access + recorded_at
state + catid + uploaded_at
state + duration_ms
```

The checksum field shall be indexed to support duplicate detection.

Tag filtering shall be implemented using efficient indexed tag-mapping queries.

---

## 14. Duplicate detection

### 14.1 Checksum generation

The component shall calculate SHA-256 for imported original files.

Checksum generation is required because implementation cost is low and the value is useful.

### 14.2 Duplicate policies

The component configuration shall provide:

```text
Ignore duplicate checking
Warn but allow import
Reject exact duplicates
```

Default:

```text
Warn but allow import
```

### 14.3 Duplicate behaviour

When a matching checksum already exists, the administrator shall be shown:

- Existing clip title
- Existing original filename
- Existing category
- Existing upload date
- Existing publication state
- Link to edit the existing clip

The administrator may continue when the configured policy permits it.

Duplicate detection shall not assume that matching filenames represent matching files.

---

## 15. Joomla publication workflow

The component shall use Joomla's standard item states:

```text
Published
Unpublished
Archived
Trashed
```

No additional editorial staging states are required.

Technical processing status shall remain separate from publication state.

A published clip may have:

- No waveform
- No generated preview
- Metadata warnings

Publication shall only be blocked when the original file is missing or invalid.

---

## 16. Joomla ACL and access levels

### 16.1 Standard permissions

The component shall support standard Joomla actions:

```text
core.admin
core.manage
core.create
core.delete
core.edit
core.edit.state
core.edit.own
```

### 16.2 Custom permissions

Custom actions may be added where standard actions are insufficient:

```text
audioarchive.import
audioarchive.process
audioarchive.managefiles
```

### 16.3 Permission hierarchy

Permissions shall follow Joomla inheritance:

```text
Global configuration
    Component
        Category
            Clip
```

### 16.4 Access levels

Every clip shall have a Joomla access level.

Frontend list, detail, playback, and download requests shall verify:

- Clip state
- Publication dates
- Clip access level
- Category publication state
- Category access level
- Current visitor's authorised view levels

The default access level for new clips shall be configurable.

### 16.5 Administrator interface

Administrator actions and toolbar buttons shall only be shown when the current user is authorised to perform the corresponding action.

---

## 17. Administrator interface

### 17.1 Dashboard

The component dashboard should show:

- Total clips
- Published clips
- Unpublished clips
- Trashed clips
- Total storage used
- Missing metadata count
- Missing preview count
- Missing waveform count
- Failed job count
- FFmpeg and FFprobe status
- Storage path status
- Recent imports

### 17.2 Clip manager

The clip manager shall provide a Joomla-style list view.

Columns should include:

- Checkbox
- State
- Title
- Category
- Duration
- Recording date
- Upload date
- Access
- Tags
- Play count
- Download count
- ID

Administrator filters should include:

- Search
- State
- Category
- Access
- Tag
- Recording-date range
- Upload-date range
- Duration range
- Metadata status
- Preview status
- Waveform status

Administrator sorting should include:

- Title
- Duration
- Recording date
- Upload date
- Category
- State
- Play count
- Download count
- ID

### 17.3 Clip edit form

The edit form shall contain sections for:

#### Basic metadata

- Title
- Alias
- Category
- Tags
- Description
- Recording date
- Access
- State
- Publication start
- Publication end

#### File information

- Original filename
- Stored file type
- MIME type
- Container
- Codec
- File size
- Duration
- Checksum
- Preview status
- Waveform status

#### Custom Fields

- Standard Joomla Custom Fields
- Grouped according to Joomla field groups

#### Publishing information

- Created date
- Created by
- Modified date
- Modified by
- Play count
- Download count
- Item ID

#### Technical actions

- Reanalyse metadata
- Regenerate preview
- Regenerate waveform
- Replace original file
- Remove generated preview
- Remove waveform
- Verify stored files

### 17.4 File replacement

Replacing an original file shall:

- Preserve the clip database record
- Preserve the clip ID
- Preserve the title and alias unless manually changed
- Preserve the public route
- Replace the original file record
- Recalculate metadata
- Recalculate checksum
- Mark old preview and waveform derivatives as stale
- Queue derivative regeneration
- Avoid exposing the old storage path

A replacement-history feature is optional for a later version.

---

## 18. Browser bulk upload

### 18.1 Upload interface

The administrator shall be able to select multiple files in the browser.

The upload interface shall provide:

- Multiple file selection
- Drag-and-drop where JavaScript is available
- Per-file progress
- Per-file result status
- Retry of individual failed files
- Cancel support where practical
- Batch metadata settings

### 18.2 Upload strategy

Files shall be uploaded individually or in small controlled groups.

The component shall not place hundreds of files into one large multipart request.

This reduces problems caused by:

- `post_max_size`
- `max_file_uploads`
- Request timeouts
- One failed file invalidating the entire batch

### 18.3 Batch metadata

The administrator shall be able to assign values applied to all files in the batch:

- Category
- Tags
- Access level
- Publication state
- Optional recording date override

Applying tags during bulk upload shall add the selected tags to every uploaded clip.

### 18.4 Per-file result

After each upload, the interface shall show:

- Generated title
- Original filename
- Duration
- Recording date
- Format
- Codec
- Duplicate warning
- Import result
- Link to edit the clip

---

## 19. Server-directory import

### 19.1 Import inbox

The component shall use a configured import inbox directory.

Administrators shall not be allowed to browse arbitrary server directories through the component.

### 19.2 Scan options

The scan interface shall support:

- Scan configured inbox
- Optional recursive scan
- File-extension filtering
- Excluding hidden files
- Excluding symbolic links by default
- Selecting some or all discovered files
- Previewing metadata before import

### 19.3 Scan result

For each discovered file, display:

- Filename
- Relative inbox path
- File size
- Detected MIME type
- Container
- Codec
- Duration
- Embedded title
- Proposed public title
- Proposed recording date
- Duplicate status
- Import eligibility
- Validation warnings

### 19.4 Batch settings

The administrator shall select:

- Category
- Tags
- Access level
- Publication state
- Duplicate policy override where authorised

These values apply to all selected files.

### 19.5 Import process

For each selected file:

1. Validate the source path.
2. Validate file extension.
3. Inspect MIME type.
4. Extract technical metadata.
5. Calculate SHA-256.
6. Check for exact duplicates.
7. Generate initial title.
8. Determine recording date.
9. Create the clip record.
10. Create the original-file record.
11. Move the file into managed storage.
12. Assign category.
13. Assign tags.
14. Apply access level.
15. Apply publication state.
16. Queue preview generation when needed.
17. Queue waveform generation when enabled.
18. Record the result.
19. Remove the original inbox file only after successful managed-storage transfer.

Failed files shall remain in the inbox for review unless explicitly removed.

---

## 20. Processing jobs

### 20.1 Purpose

Technical processing may take longer than a normal web request, especially for hundreds of files.

The job system shall make processing:

- Incremental
- Retryable
- Observable
- Safe from request timeouts

### 20.2 Processing methods

Jobs may be processed by:

1. Administrator batch processing
2. Joomla Scheduled Tasks
3. A future Joomla console command

### 20.3 Administrator processing

The administrator shall be able to run pending jobs in small batches.

The interface may use AJAX for progress, but each request shall process only a limited number of jobs or a limited execution time.

### 20.4 Scheduled Tasks

The Scheduled Tasks plugin shall process a configurable number of pending jobs per invocation.

Cron is optional but recommended for:

- Initial import of hundreds of files
- Large later imports
- Deferred waveform generation
- Deferred preview generation
- Retry of transient failures

Cron is not required for:

- Browsing
- Filtering
- Sorting
- Playback
- Downloads
- Ordinary editing

### 20.5 Retry behaviour

Jobs shall record:

- Attempt count
- Last error
- Start time
- Finish time
- Processing duration

Administrators shall be able to:

- Retry failed jobs
- Cancel pending jobs
- Delete completed job history
- Filter jobs by state and type

---

## 21. Preview generation

### 21.1 Conditions

A preview may be generated when:

- The original format is not considered broadly browser-compatible
- The codec is not considered broadly browser-compatible
- An administrator explicitly requests a preview
- Configuration requires previews for all originals

### 21.2 Default policy

Default:

```text
Generate previews only when required for browser compatibility
```

### 21.3 Preview status

Supported values:

```text
not_required
pending
available
unavailable
failed
stale
```

### 21.4 FFmpeg absence

When FFmpeg is unavailable:

- No preview shall be generated
- The clip may still be published
- The original remains downloadable
- Direct playback shall be attempted when appropriate
- The administrator interface shall display the limitation

---

## 22. Waveforms

### 22.1 Waveform representation

Waveforms shall be stored as pre-generated peak data rather than pre-rendered images.

The initial format should be compact JSON containing normalised sample peaks.

The waveform data should contain enough detail for responsive rendering.

### 22.2 Waveform generation

When FFmpeg is available:

1. Decode the audio.
2. Divide it into a configured number of intervals.
3. Calculate peak values.
4. Optionally calculate separate minimum and maximum values.
5. Normalise the values.
6. Store the resulting data file.
7. Record generator and version information.

### 22.3 Waveform configuration

Options shall include:

- Enable waveform generation
- Number of waveform points
- Mono mixdown or channel handling
- Generate during import
- Queue for later processing
- Display on detail pages
- Display in archive table
- Lazy-load waveform data
- Queue missing waveform when detail page is first viewed

### 22.4 Lazy behaviour

Public visitors shall never synchronously generate a waveform.

When configured, the first detail-page view of a clip with a missing waveform may enqueue a waveform-generation job.

Actual generation occurs later through:

- Administrator processing
- Scheduled Tasks
- A future console command

### 22.5 Waveform absence

A missing or failed waveform shall not prevent:

- Publication
- Playback
- Download
- Detail-page display

The waveform area shall simply be omitted or show a non-intrusive unavailable state.

### 22.6 Frontend renderer

The waveform renderer shall be packaged locally.

A locally bundled WaveSurfer.js version or an equivalent local renderer may be used.

No runtime dependency shall be loaded from a public CDN.

---

## 23. Public archive view

### 23.1 General layout

The archive page shall contain:

1. Page heading
2. Filter form
3. Active-filter summary
4. Reset-filter action
5. Result count
6. Sortable result table
7. Pagination

### 23.2 Filter form method

The filter form shall use HTTP GET.

Submitting the filter shall reload the page.

AJAX filtering is not required.

Filter URLs shall be bookmarkable and shareable.

### 23.3 Filter parameters

The archive shall support:

- Text search
- Category
- Tags
- Minimum duration
- Maximum duration
- Recording date from
- Recording date to
- Upload date from
- Upload date to

Suggested query parameters:

```text
q
category
tags[]
duration_min
duration_max
recorded_from
recorded_to
uploaded_from
uploaded_to
sort
direction
limit
start
```

### 23.4 Text search

The text search shall inspect the component's built-in text fields:

- Title
- Description
- Original filename
- Embedded title where retained

The first release does not require archive-filter searching of arbitrary Custom Field values.

Smart Search may index selected Custom Field values separately.

### 23.5 Tag filtering

Visitors may select multiple tags.

Selected tags shall use logical AND.

A clip is eligible only when it contains every selected tag.

Example:

```text
Selected tags:
- rain
- Berlin
- traffic
```

The result must contain all three tags.

### 23.6 Category filtering

The initial public filter shall support:

- All categories
- One selected category

Support for selecting multiple categories may be added later.

### 23.7 Duration filtering

Duration values shall be converted to milliseconds for database comparison.

The interface may accept:

```text
90
01:30
1:02:30
```

These represent:

- 90 seconds
- 1 minute 30 seconds
- 1 hour 2 minutes 30 seconds

Invalid values shall produce a clear validation message.

### 23.8 Date filtering

Recording-date and upload-date ranges shall be inclusive.

When only a start or end date is supplied, the query shall use the supplied boundary only.

### 23.9 Reset behaviour

A Reset action shall clear archive filters while preserving the current archive menu item.

---

## 24. Archive table

### 24.1 Default columns

The default table shall contain:

```text
Play
Title
Duration
Recording date
Upload date
Tags
```

### 24.2 Optional columns

Administrators may enable:

- Category
- Description excerpt
- Download count
- Play count
- Compact waveform
- File format
- File size
- Selected Custom Fields

### 24.3 Configuration levels

Column visibility shall be configurable through:

1. Component defaults
2. Menu-item overrides

Menu-item options take precedence over component defaults.

### 24.4 Sorting

Clicking a sortable table header shall:

- Sort by that column
- Toggle ascending and descending order
- Preserve active filters
- Preserve the current menu item
- Reset pagination to the first page

Required sortable fields:

- Title
- Duration
- Recording date
- Upload date

Optional sortable fields:

- Category
- Play count
- Download count
- File size

Arbitrary Custom Field columns are not required to be sortable in the first release.

### 24.5 Default sorting

Default:

```text
Upload date descending
```

This displays the newest imported clips first.

### 24.6 Accessibility

Sortable headers shall use:

- Semantic table markup
- Keyboard-accessible links or controls
- Visible direction indicators
- Appropriate `aria-sort` values

---

## 25. Tag result counts

The component configuration shall provide:

```text
Off
Total counts
Contextual counts
```

### 25.1 Total counts

Shows the total number of accessible published clips assigned to each tag.

### 25.2 Contextual counts

Shows how many clips would remain for each tag while respecting the other active filters.

### 25.3 Default

```text
Off
```

Contextual counts may use caching to avoid expensive repeated queries.

---

## 26. Pagination

All archive results shall use server-side Joomla pagination.

Configuration shall include:

- Default items per page
- Allowed page-size values
- Maximum permitted page size

The archive query shall apply filters and access checks before pagination.

The result count shall represent the complete filtered result set.

---

## 27. Inline playback

### 27.1 Table player

Each row shall provide a play control.

The player shall:

- Use `preload="none"` or equivalent conservative loading
- Avoid loading complete files before playback
- Support browser seeking through HTTP range requests
- Stop or pause another clip when a new clip begins
- Display an accessible play/pause state
- Provide a fallback link to the detail page

### 27.2 JavaScript requirement

JavaScript may be used for compact play buttons and one-player-at-a-time behaviour.

Without JavaScript, the visitor shall still be able to:

- Open the clip detail page
- Use native playback where available
- Download the file

### 27.3 Playback source order

The component shall choose playback sources in this order:

1. Browser-compatible original
2. Generated preview
3. Other configured compatible derivative
4. Download-only fallback

Where useful, multiple `<source>` elements may be emitted.

### 27.4 Unsupported formats

When no browser-compatible source exists, show:

```text
This audio format cannot be played directly in your browser.
You can download the original file.
```

---

## 28. Clip detail view

The detail page shall display:

- Title
- Category
- Tags
- Description
- Duration
- Recording date
- Upload date
- Publication information where configured
- Inline player
- Waveform when available and enabled
- Download action
- Selected technical metadata where enabled
- Selected Joomla Custom Fields
- Play count where enabled
- Download count where enabled

Optional display settings shall control:

- Original filename
- File format
- Codec
- File size
- Counts
- Category
- Tags
- Recording date
- Upload date
- Custom Field groups

---

## 29. Routing and URLs

### 29.1 SEF routing

The component shall implement a Joomla router.

Clip routes shall contain the clip ID and alias.

Example conceptual route:

```text
/audio-archive/123-evening-rain-in-berlin
```

The ID guarantees uniqueness.

The alias provides a readable URL.

### 29.2 Alias changes

Changing the alias may change the canonical URL.

Changing only the original file shall not change the URL.

### 29.3 Canonical URLs

The detail view shall emit a canonical route for the current clip.

Filter pages shall preserve query parameters in a predictable order where practical.

---

## 30. File streaming

### 30.1 Playback endpoint

The playback endpoint shall:

- Resolve the clip and selected playable file
- Check publication state
- Check publication dates
- Check category state
- Check access level
- Validate the stored path
- Send the correct MIME type
- Support HTTP byte-range requests
- Support HEAD requests
- Avoid direct path disclosure
- Avoid incrementing the download count

### 30.2 Download endpoint

The download endpoint shall:

- Perform the same access checks
- Deliver the original file
- Use the original filename for `Content-Disposition`
- Avoid exposing the managed storage filename
- Increment the download counter
- Support large-file streaming
- Avoid loading the complete file into PHP memory

### 30.3 Range requests

Range handling is required for:

- Seeking within audio
- Efficient playback
- Large recordings

Range requests used for playback shall not inflate download counts.

---

## 31. Playback counts

### 31.1 Counting rule

A playback shall be counted when playback actually begins.

Loading metadata or opening a page shall not count.

### 31.2 Client reporting

A small JavaScript request may record the first play event for a clip during the current page view.

Repeated pause and resume actions on the same page shall not repeatedly increment the count.

### 31.3 Privacy

The first release shall store only aggregate counts.

It shall not require storing:

- IP addresses
- User-agent history
- Personal listening history

### 31.4 Accuracy

Playback counts are informational and are not intended as tamper-proof analytics.

---

## 32. Download counts

A download shall be counted after:

- The clip has been resolved
- Access has been granted
- The original file has been found
- The download response is about to begin

HEAD requests shall not count.

Playback streaming shall not count as a download.

Reasonable protection against repeated technical requests may be implemented using the Joomla session.

---

## 33. Joomla Custom Fields

### 33.1 Context

The component shall expose:

```text
com_audioarchive.clip
```

as a Joomla Custom Fields context.

### 33.2 Administrator forms

Custom Fields shall appear in the clip edit form according to:

- Assigned category
- Field groups
- Access permissions
- Field publication state

### 33.3 Frontend rendering

Configured Custom Field groups may be rendered on the clip detail page.

Selected Custom Fields may be displayed as archive columns.

### 33.4 Limitations in the first release

The first release does not require:

- Generic filtering by every Custom Field type
- Generic sorting by every Custom Field
- Range queries over arbitrary Custom Fields
- Automatically adding every Custom Field to the archive text search

Support may later be added for explicitly supported field types, such as:

- Text
- Integer
- Decimal
- Date
- List

---

## 34. Smart Search

The Finder plugin shall index:

- Title
- Description
- Original filename
- Category title
- Tags
- Selected plain-text Custom Fields
- Recording date
- Upload date

Finder results shall:

- Link to the clip detail page
- Respect access levels
- Respect publication state
- Respect publication dates
- Be removed or hidden when the clip is no longer public

Reindexing shall be possible through Joomla Smart Search tools.

---

## 35. Latest clips module

### 35.1 Required options

The latest-clips module shall provide:

- Number of clips
- Ordering date:
  - Upload date
  - Recording date
  - Publication date
- Category restriction
- Tag restriction
- Tag combination mode
- Show title
- Show duration
- Show date
- Show category
- Show tags
- Show inline player
- Show detail link
- Module caching options

### 35.2 Default ordering

Default:

```text
Upload date descending
```

### 35.3 Access

The module shall only show clips that the current visitor may view.

---

## 36. Random clip module

### 36.1 Modes

The random module shall support:

```text
Random on each uncached request
Stable clip of the day
```

### 36.2 Clip-of-the-day selection

The daily selection shall be deterministic for:

- Calendar date
- Module instance
- Eligible result set

The implementation should avoid expensive `ORDER BY RAND()` queries over the entire archive.

### 36.3 Restrictions

The module shall support:

- Category restriction
- Tag restriction
- Access checks
- Publication checks
- Optional requirement for a playable source

### 36.4 Display

The module shall support:

- Title
- Duration
- Date
- Category
- Tags
- Inline player
- Detail link
- Download link where enabled

---

## 37. Component configuration

### 37.1 General settings

- Default category
- Default access level
- Default publication state
- Default list order
- Default sort direction
- Default items per page
- Allowed page sizes
- Enable play counts
- Enable download counts
- Enable Custom Fields
- Enable Smart Search integration

### 37.2 Storage settings

- Original-file directory
- Preview-file directory
- Waveform directory
- Import inbox
- Allow recursive inbox scan
- Allow symbolic links
- Directory creation permissions where applicable

### 37.3 Upload settings

- Maximum file size
- Maximum duration
- Permitted file extensions
- Permitted MIME types
- Duplicate policy
- Title-generation policy
- Recording-date policy
- Automatically queue technical processing
- Delete inbox file after successful import

### 37.4 Playback settings

- Prefer original playback
- Generate compatibility preview when required
- Preview format
- Preview bitrate
- Show unsupported-format notice
- Allow original downloads
- Inline-player presentation

### 37.5 Waveform settings

- Enable waveform generation
- Generate during import
- Queue generation
- Number of points
- Display on detail page
- Display in list
- Lazy-load waveform
- Queue missing waveform on first detail view

### 37.6 List settings

- Visible columns
- Sortable columns
- Default ordering
- Show filter form
- Show category filter
- Show tag filter
- Tag count mode
- Show duration filter
- Show recording-date filter
- Show upload-date filter
- Show active-filter summary
- Show result count
- Show description excerpt
- Description excerpt length

### 37.7 Detail-view settings

- Show category
- Show tags
- Show recording date
- Show upload date
- Show original filename
- Show file size
- Show format
- Show codec
- Show play count
- Show download count
- Show Custom Field groups
- Show waveform
- Show download button

### 37.8 Processing settings

- FFprobe path
- FFmpeg path
- Automatic executable detection
- Process timeout
- Jobs per administrator request
- Jobs per scheduled-task run
- Maximum retry attempts
- Retain completed jobs for a configured number of days
- Retain failed-job diagnostics

### 37.9 Menu-item overrides

Archive menu items shall be able to override appropriate component defaults, including:

- Page title
- Category restriction
- Tag restriction
- Visible filters
- Visible columns
- Default order
- Items per page
- Waveform display
- Count display
- Download availability
- Detail-view presentation

---

## 38. Security requirements

### 38.1 Administrator actions

All state-changing administrator requests shall require:

- Valid Joomla session
- CSRF token
- Appropriate ACL permission
- Validated input

### 38.2 Upload security

The component shall:

- Validate extension
- Inspect MIME type
- Analyse file structure
- Generate an internal storage name
- Reject path traversal
- Avoid trusting client-provided MIME values
- Avoid serving uploaded files directly
- Log validation failures

### 38.3 Output security

All frontend output shall be escaped according to context.

Descriptions shall use Joomla's configured editor and content filtering.

Custom Field output shall use Joomla rendering and filtering.

### 38.4 SQL security

Queries shall use Joomla's database query API and bound values.

No user-provided filter value shall be concatenated directly into SQL.

### 38.5 Process security

External process arguments shall be supplied separately.

Only configured administrator-controlled executable paths may be used.

Public users shall never be able to supply executable paths or processing arguments.

### 38.6 Count endpoints

Playback-count requests shall:

- Validate the clip
- Validate a request token where appropriate
- Apply basic rate or session restrictions
- Avoid accepting arbitrary count values from the client

---

## 39. Performance requirements

### 39.1 Archive query

The archive shall remain responsive with several thousand clips.

The query shall:

- Apply state and access restrictions early
- Use indexed duration and date columns
- Use indexed category fields
- Use efficient tag subqueries
- Avoid loading file contents
- Select only fields required by the current view
- Paginate at database level

### 39.2 Tag AND filtering

When multiple tags are selected, the database query shall ensure that each result contains all selected tags.

An implementation may use:

- Grouping and `HAVING COUNT(DISTINCT tag_id)`
- Multiple `EXISTS` subqueries
- An equivalent indexed strategy

The implementation shall be tested with large tag sets.

### 39.3 Random module

The random module should avoid scanning and randomly sorting the entire archive for every request.

Stable daily selection may use a deterministic hash and eligible ID list or another efficient method.

### 39.4 Waveform loading

Waveform data shall be loaded only when required.

List-view waveform loading shall be disabled by default.

### 39.5 File delivery

Audio streaming and downloads shall use chunked file delivery or an equivalent low-memory mechanism.

---

## 40. Accessibility

The frontend should target WCAG 2.2 AA where practical.

Requirements include:

- Properly associated form labels
- Keyboard-operable controls
- Visible focus states
- Semantic table markup
- Accessible sorting state
- Accessible play and pause labels
- Status announcements for player changes
- Sufficient text alternatives for icon-only buttons
- No reliance on colour alone
- Error messages associated with invalid fields
- Native fallback links where JavaScript enhancement fails

Waveforms are decorative unless they provide additional interaction.

Decorative waveforms shall be hidden from assistive technology.

---

## 41. Responsive design

The archive shall support desktop, tablet, and mobile layouts.

On narrow screens, the implementation may:

- Hide optional columns
- Stack metadata
- Convert rows into responsive cards
- Preserve the play control and title
- Keep filters usable without horizontal scrolling

The Joomla template shall retain control over visual styling through layouts and overrides.

---

## 42. Template overrides

The component and modules shall use Joomla layout files that can be overridden by templates.

Separate layouts should be provided for:

- Archive filter
- Archive table
- Archive row
- Clip player
- Clip detail
- Waveform
- Tags
- Latest module
- Random module

Business logic shall not be embedded in template files.

---

## 43. Logging and diagnostics

The component shall use Joomla logging facilities.

Log categories may include:

```text
com_audioarchive.import
com_audioarchive.metadata
com_audioarchive.preview
com_audioarchive.waveform
com_audioarchive.download
com_audioarchive.jobs
```

Logs shall include:

- Clip ID where available
- Job ID where available
- Operation
- Result
- Sanitised error message
- Processing duration

Public error messages shall not expose:

- Filesystem paths
- Executable paths
- Database details
- Stack traces
- Server configuration secrets

---

## 44. Error handling

### 44.1 Import failures

A failed import shall:

- Avoid creating a half-valid published record
- Preserve the inbox source file
- Report the cause
- Allow retry
- Clean up incomplete managed-storage files

### 44.2 Missing original

A clip whose original file is missing shall:

- Be reported prominently in administrator views
- Fail playback and download safely
- Return an appropriate HTTP status
- Never expose the expected filesystem path

### 44.3 Failed derivative

A failed preview or waveform shall:

- Leave the original intact
- Record the error
- Allow retry
- Not block publication

### 44.4 Database transaction use

Database changes and file operations shall be coordinated carefully.

Where full atomicity is impossible, compensating cleanup shall remove incomplete records or files.

---

## 45. Installation and updates

### 45.1 Installation

The package installer shall:

- Install all included extensions
- Create database tables
- Register the Joomla content type
- Register category integration
- Register Custom Fields context support
- Create required media assets
- Create or validate storage directories
- Create an `Uncategorised` audio category where appropriate
- Display a post-installation system check

### 45.2 Updates

Updates shall use Joomla schema versioning.

Database and configuration migrations shall preserve:

- Clips
- Original-file references
- Categories
- Tags
- Custom Field values
- Counts
- Derivative records

### 45.3 Update server

The package should support a Joomla update server when distributed beyond the development site.

### 45.4 Uninstallation

Uninstallation shall never silently delete original audio files.

The administrator shall be warned to back up:

- Database tables
- Original files
- Preview files
- Waveform files

The exact database-retention policy shall be documented before release.

---

## 46. Backup and migration

A complete backup shall include:

- Joomla database
- Original storage directory
- Preview storage directory
- Waveform storage directory
- Component configuration

The component shall avoid storing absolute public URLs in clip records.

Storage paths should be configurable after migration.

A maintenance tool should verify file references after moving the site.

---

## 47. Language handling

The archive content itself does not require Joomla multilingual associations.

The extension interface shall still use Joomla language strings.

The initial package should include:

```text
en-GB
de-DE
```

No interface text shall be hard-coded into PHP or JavaScript.

---

## 48. Third-party dependencies

All runtime dependencies shall be packaged locally.

Potential dependencies include:

- A pure-PHP media metadata library
- A waveform-rendering JavaScript library

Requirements:

- No CDN dependency
- Compatible open-source licence
- Version locked during release
- Licence files included
- Update reviewed before dependency upgrades
- No unnecessary tracking or network calls

FFmpeg and FFprobe are optional server-provided executables and are not packaged with the Joomla extension.

---

## 49. Testing requirements

### 49.1 Unit tests

Unit tests should cover:

- Filename-to-title conversion
- Duration parsing
- Duration formatting
- Date-source precedence
- Checksum comparison
- Storage-key generation
- Path validation
- File-extension validation
- MIME mapping
- Preview decision logic
- Waveform status logic
- Daily random-selection logic
- Query-state parsing

### 49.2 Integration tests

Integration tests should cover:

- Clip creation
- Clip editing
- Standard Joomla states
- ACL inheritance
- Access-level filtering
- Category filtering
- Tag AND filtering
- Duration ranges
- Recording-date ranges
- Upload-date ranges
- Combined filters
- Sorting
- Pagination
- Smart Search indexing
- Custom Field saving
- Soft deletion
- Duplicate detection
- File replacement

### 49.3 Media fixtures

Test fixtures should include:

- AAC in M4A
- ALAC in M4A
- MP3 with ID3 metadata
- MP3 without metadata
- Ogg Vorbis
- Ogg Opus
- WAV
- FLAC
- Invalid audio file with valid extension
- Valid audio file with incorrect extension
- Zero-length file
- Very short file
- Long file
- File with Unicode filename
- Duplicate files with different filenames

### 49.4 Browser tests

Playback and layout should be tested in current versions of:

- Safari
- Chrome
- Firefox
- Edge

Testing should include desktop and mobile layouts.

### 49.5 Security tests

Security tests should include:

- Path traversal attempts
- Invalid MIME uploads
- Unauthorised downloads
- Unauthorised playback
- Unauthorised administrator actions
- CSRF failures
- Malformed range headers
- Invalid filter parameters
- Process argument injection attempts
- Symlink escape attempts

---

## 50. Acceptance criteria

### 50.1 Installation

The package installs successfully on a supported Joomla! 6 installation.

All required extensions are enabled or clearly reported.

### 50.2 Existing archive import

An administrator can import the existing collection of more than 800 M4A files through the configured directory importer in manageable batches.

For each valid file, the system automatically determines where available:

- Duration
- File size
- Format
- Codec
- Embedded title
- Recording date
- Checksum

### 50.3 Metadata management

An authorised administrator can:

- Create clips
- Edit clips
- Assign categories
- Assign tags
- Change access levels
- Publish and unpublish
- Archive and trash
- Restore trashed clips
- Replace files
- Retry failed processing

### 50.4 Public filtering

Visitors can combine:

- Text search
- Category
- Multiple tags using AND
- Minimum duration
- Maximum duration
- Recording-date range
- Upload-date range

The database applies the filters before pagination.

### 50.5 Sorting

Visitors can sort by:

- Title
- Duration
- Recording date
- Upload date

Both ascending and descending ordering work.

Active filters remain applied.

### 50.6 Playback

Visitors can play browser-compatible clips inline.

Starting another clip pauses or stops the current clip when JavaScript is available.

Unsupported formats present a download fallback.

### 50.7 Detail page

Each accessible clip has a detail page containing configured metadata, player, tags, category, Custom Fields, download action, and waveform where available.

### 50.8 Downloads

Visitors can download the original file without seeing its managed server path.

Access restrictions are enforced.

### 50.9 Waveforms

When FFmpeg is available and waveform generation is enabled, waveform data can be generated and displayed.

When FFmpeg is unavailable, the remainder of the component remains functional.

### 50.10 Modules

The latest and random modules:

- Respect access levels
- Respect publication state
- Support configured restrictions
- Include inline playback
- Link to detail pages

### 50.11 Joomla integration

The component works with:

- Categories
- Tags
- Access levels
- ACL
- Custom Fields
- Smart Search
- Joomla routing
- Joomla pagination
- Joomla Scheduled Tasks

---

## 51. Recommended implementation phases

### Phase 1: Foundation

- Package skeleton
- Component installation
- Database schema
- Content-type registration
- Categories
- Tags
- ACL
- Basic administrator clip CRUD
- Component configuration

### Phase 2: File handling

- Managed storage
- Single upload
- Browser bulk upload
- Directory scanning
- File validation
- Metadata extraction
- M4A support
- Duration extraction
- Automatic titles
- Date extraction
- Checksums

### Phase 3: Public archive

- Archive model
- GET filter form
- Text search
- Category filter
- Tag AND filter
- Duration range
- Recording-date range
- Upload-date range
- Sorting
- Pagination
- Configurable columns

### Phase 4: Playback and downloads

- Protected stream endpoint
- Range requests
- Inline player
- Detail page
- Original downloads
- Playback counts
- Download counts

### Phase 5: Joomla integrations

- Custom Fields
- Smart Search
- Menu-item options
- Template overrides
- Language files

### Phase 6: Processing system

- FFmpeg and FFprobe detection
- Job queue
- Administrator job processing
- Scheduled Tasks plugin
- Preview generation
- Technical diagnostics

### Phase 7: Waveforms

- Peak-data generation
- Local waveform renderer
- Detail-page waveform
- Optional compact list waveform
- Lazy waveform loading

### Phase 8: Modules and hardening

- Latest module
- Random and clip-of-the-day module
- Accessibility review
- Performance testing
- Security testing
- Upgrade scripts
- Documentation

---

## 52. Optional later enhancements

Possible future additions include:

- CSV metadata export
- JSON export
- CSV-assisted imports
- Podcast-compatible RSS feed
- Most-played module
- Most-downloaded module
- Related-clips module
- Tag-cloud module
- Custom Field filtering
- Custom Field sorting
- Transcripts
- Transcript search
- User playlists
- User favourites
- Public API
- Object-storage support
- Multiple preview formats
- Browser-based waveform generation fallback
- Replacement history
- Saved archive searches
- Download packages
- Audio licence metadata
- Recording-location metadata
- Creator and contributor metadata

---

## 53. Final agreed defaults

Unless changed before implementation, the following defaults apply:

```text
Original files are always preserved.
M4A files are not converted wholesale.
MP3 is the default generated compatibility-preview format.
Original files are used for downloads.
One Joomla category is assigned to each clip.
An Uncategorised category may be used as the default.
Joomla's standard flat tags are used.
Multiple selected tags use logical AND.
Tag vocabulary is not automatically controlled or normalised.
SHA-256 duplicate checking is enabled.
Duplicate handling warns but permits import.
Standard Joomla publication states are used.
No custom editorial staging workflow is introduced.
Technical processing state is stored separately.
The default access level is configurable.
The default category is configurable.
The default public order is upload date descending.
Filtering uses an ordinary GET form and page reload.
Filtering, sorting, and pagination occur in the database.
The default columns are Play, Title, Duration, Recording date, Upload date, and Tags.
Column visibility is configurable globally and per menu item.
Waveforms appear on detail pages when enabled and available.
List-row waveforms are optional and disabled by default.
Waveforms are pre-generated and stored as peak data.
Public requests never synchronously generate waveforms.
FFmpeg and FFprobe are detected automatically where possible.
A pure-PHP metadata analyser is used when FFprobe is unavailable.
Missing FFmpeg features do not prevent publication.
Modules include inline playback.
Smart Search integration is included.
Custom Fields are supported for clip metadata.
Original files are not silently deleted during component uninstallation.
All third-party runtime JavaScript and PHP libraries are stored locally.
```
