# Architecture

Punga Audio Archive is a Joomla package containing one component, two site modules, and three plugins.

## Main component

`com_audioarchive` owns:

- Administrator CRUD, upload, import, replacement, maintenance, export, and restore
- Database tables and schema migrations
- Managed media and analysis storage
- Public Archive, Tag Directory, Clip Detail, Sound Board, Playlists, and frontend edit views
- Protected playback, download, waveform, spectrogram, and frequency-profile endpoints
- Ratings, interactions, routing, menu resolution, and access enforcement

The component follows Joomla MVC conventions with separate administrator and site trees.

## Modules

- `mod_audioarchive` selects and renders clips using shared component services and player assets.
- `mod_audioarchive_tags` renders access-sensitive tags and Archive links.

## Plugins

- `plg_content_audioarchive` parses placeholders and embeds selected clips or aggregate values.
- `plg_finder_audioarchive` synchronises eligible clips with Joomla Smart Search.
- `plg_quickicon_audioarchive` adds an administrator dashboard shortcut.

## Core design principles

- Joomla-native categories, tags, access levels, ACL, routing, forms, language files, and pagination
- Database-side filtering and pagination
- Original files preserved as managed media
- Public media delivered through authorised controllers
- Optional derivatives never block publication
- Progressive enhancement for player and frontend interactions
- No runtime CDN dependencies
- External analysis performed through bounded, retryable jobs
- Browser-local personal collections and Sound Board performance recordings, with server resolution used only to enforce current clip visibility and stable identity

## Shared selection and embedded lists (0.14)

`ClipSelectionService` owns module/menu eligibility and selection; the module helper remains a compatibility facade. Its daily date is explicitly converted to Joomla Global Configuration `offset`. Daily selection ranks clips by SHA-256 of `date:clipId`, independent of module/menu ID. `PaginationHelper` converts public page numbers to offsets, accepts legacy offsets, and supplies compact page ranges shared by Archive and My Clips. `ArchiveModel::configureEmbed()` configures an `ignore_request` model without session reads/writes. `Archive/HtmlView::renderEmbedded()` reuses the archive templates without page metadata. Sort and page parameters are namespaced by embed instance. `getEmbeddedPage()` caps the matching total, clamps page bounds, and fetches only the requested slice (including a partial final page); shared pagination uses that capped total and the requested page size. Duration maximums in embeds are exact/inclusive; the ordinary archive retains its existing whole-second display semantics.
