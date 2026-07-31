# Initial configuration

Open **Components → Punga Audio Archive → Options**. The same component settings are also available from Joomla Global Configuration.

## General

Configure the default category, access level, publication state, play counts, and download counts for new clips.

The component-wide **Frontend archive access** setting protects direct component routes in addition to ordinary menu-item access levels.

## Ratings, Playlists, and Sound Boards

These features have separate tabs:

- **Ratings** controls whether Like/Dislike voting is enabled and who may vote.
- **Playlists** enables browser-local playlists and controls playlist-table colours.
- **Sound Boards** enables the Sound Board, pad count, play recording, polyphony, and optional pad colours.

## Storage

Configure:

- Original-file storage
- Reserved compatibility-preview storage
- Shared analysis-data storage
- Import inbox
- Recursive import behaviour
- Symbolic-link policy

Store managed media outside the public web root where practical. When it remains inside the web root, Punga Audio Archive still delivers it through protected controllers rather than exposing managed filenames.

## Upload and import

Review permitted extensions and MIME types, size and duration limits, duplicate policy, title generation, recording-date policy, and inbox cleanup behaviour.

## Public Archive

Configure visible filters, list columns, default ordering, page size, compact pagination, and Archive filter/list colours. Individual Archive menu items can override appropriate defaults.

## Playback and downloads

Choose default player presentations and colours, preferred Featured-player analysis view, backend preview presentation, and download access.

## Clip Detail and Related Clips

Configure metadata visibility, player presentation, ratings, sharing, downloads, Previous/Next navigation, and the Related Clips subgroup.

Related Clips options include visibility, result count, minimum shared tags, ranking strategy, visible columns, and Share/Add actions.

## Processing

Enable waveform or spectral-analysis generation and set their detail parameters. When an analysis type is enabled globally, its job is queued automatically whenever a new original is stored or an existing original is replaced.

Configure FFmpeg and optional FFprobe paths, process timeout, and maximum attempts. Paths can be absolute or relative to the Joomla root, but a relative path must remain within that root.

## Uninstallation

Review whether managed media should be retained if the extension is uninstalled. Back up the database and media before uninstalling.
