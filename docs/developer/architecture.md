# Architecture

Punga Audio Archive is a Joomla package containing one component, two site modules, and three plugins.

## Main component

`com_audioarchive` owns:

- Administrator CRUD, upload, import, replacement, maintenance, export, and restore
- Database tables and schema migrations
- Managed media and analysis storage
- Public Archive, Tag Directory, Clip Detail, Sound Board, Playlists, and frontend edit views
- Protected playback, download, waveform, and spectrum endpoints
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
