# Changelog

Notable changes to Punga Audio Archive are recorded here.

## 0.11.12 — 2026-08-22

- Fixed Clip Detail Previous/Next navigation when the Archive is ordered by rating. The navigation query now orders by the underlying positive-rating expression instead of a SELECT alias that is removed by the compact navigation query.

## 0.11.11 — 2026-08-22

- Fixed the untranslated Actions heading in the Bulk Upload queue.
- Added Archive sorting by positive rating count, including Rating as a configurable default order.
- Replaced the informal Batch Edit Search & Replace placeholders with neutral examples.
- Automatically processes waveform and spectral-analysis jobs queued for newly bulk-uploaded clips.

## 0.11.4 — 2026-07-31

- Changed playlist-row play controls to use the same unified Minimal player control as Archive and Related Clips rows.
- Preserved Playlist queue playback, automatic advancement, and analytics behaviour.
- Reorganised the source archive into one expanded project tree suitable for development and rebuilding.
- Split project documentation into a compact README, user guide, and developer documentation.

## 0.11.3 — 2026-07-31

- Added Related Clips to Clip Detail pages with shared-tag ranking and Archive-style responsive presentation.
- Added global and menu-item settings for Related Clips.
- Reorganised component options into dedicated Ratings, Playlists, and Sound Boards tabs.
- Unified introductory-text terminology and spacing across frontend menu-item views.
- Automatically queues all globally enabled analyses when new or replacement originals are stored.
- Fixed the administrator Batch Edit dialog Cancel action.
- Audited English and German language strings.

## 0.11.2

- Added the first Related Clips implementation to Clip Detail pages.

## Earlier releases

Earlier development history is available in the Git repository and release history.
