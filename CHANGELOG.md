# Changelog

Notable changes to Punga Audio Archive are recorded here.

## 0.11.20 — 2026-08-28

- Added **Save as playlist** to the Sound Board, preserving occupied pad order and resolving numeric clip IDs to stable playlist UUIDs.
- Added **Load into Sound Board** to playlists, preserving playlist order, omitting inaccessible clips, respecting the configured pad count, and confirming before replacing an occupied board.
- Made both conversion controls respect the global Playlists and Sound Board feature switches.
- Added analytics events for conversions between playlists and Sound Boards.

## 0.11.19 — 2026-08-28

- Fixed chromatic sampler playback in iPhone Safari by caching decoded buffers and starting ready Web Audio voices synchronously from the user gesture.

## 0.11.18 — 2026-08-28

- Added explicit Web Audio unlocking during chromatic-mode and onscreen-key interactions for stricter mobile browsers.

## 0.11.17 — 2026-08-28

- Added Audio Session handling for chromatic sampler playback on supported mobile browsers.

## 0.11.16 — 2026-08-28

- Replaced per-pad sampler buttons with one Sound Board mode switch for **Pad trigger** and **Chromatic keyboard** modes.
- In chromatic mode, selecting an occupied pad chooses its clip as the sampler sound.

## 0.11.15 — 2026-08-28

- Restored the standard nested fieldsets in the Sound Boards component-options tab, separating general behaviour from styling.
- Made the onscreen keyboard appear automatically in chromatic mode and reduced its height while retaining the full available width.
- Changed number keys 1–9 and 0 to select sampler pads while chromatic mode is active.
- Improved Web MIDI availability, HTTPS, and browser-support messages.

## 0.11.14 — 2026-08-28

- Added a velocity-sensitive polyphonic chromatic sampler to the Sound Board with MIDI note C4 as the original pitch.
- Added Web MIDI input and an onscreen piano with computer-key mappings and octave switching.
- Added a global option to enable or disable sampler functionality independently of the Sound Board.
- Made the global Sound Board switch consistently hide Sound Board pages and Add to Sound Board actions.

## 0.11.13 — 2026-08-22

- Added protected averaged Frequency Profiles generated with the existing FFmpeg/FFT analysis pipeline.
- Integrated Frequency Profiles into automatic queueing, maintenance, Clip editing, archive export/restore, and the unified Featured player.
- Added Frequency Profile generation and colour options, with player controls shown only when profile data exists.

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
