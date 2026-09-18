# Changelog

## 0.12.4 — 2026-09-19

- Traced the Chromatic Keyboard regression specifically against 0.12.1 and confirmed that the live sampler engine itself had not changed in 0.12.2.
- Rolled back the 0.12.2 recorder/playback integration changes that routed recorded audio through a private `recordingPlayback.board`; recorded Pad Trigger and chromatic playback now use the proven 0.12.1 temporary-board mechanism again.
- Restored the 0.12.1 `play()`, `playSamplerNoteFromPad()`, recording-board restoration, and sampler playback-state integration exactly, while retaining the 0.12.2 recorder UI, live piano-roll drawing, pad-coloured notes, overdub layers, and stateful transport buttons.
- Kept the live Chromatic Keyboard engine (`setSamplerMode`, sampler selection, AudioContext/buffer loading, `startSamplerVoice`, `playSamplerNote`, and MIDI handling) byte-for-byte equivalent to 0.12.1.
- Moved the temporary internal Pad Trigger transition until after asynchronous recording-board resolution so the user's Chromatic Keyboard mode should not visibly sit in Pad Trigger mode while a recording loads.
- Added regression checks comparing the restored sampler functions directly with the 0.12.1 source baseline.

## 0.12.2 — 2026-09-19

- Removed the piano-roll Instrument/change lane, which became ambiguous once recordings could contain independent overdub layers.
- Assigned deterministic pseudo-random colours to piano-roll note and Pad Trigger events by Sound Board pad, so all events using the same pad share a stable colour across the original take and overdubs.
- Added live piano-roll drawing while recording and overdubbing; new notes appear immediately and held notes grow until key-up.
- Isolated the resolved recording Sound Board into private backing-playback state instead of replacing the live Sound Board during replay.
- Fixed overdub startup so entering Record overdub from Chromatic Keyboard mode no longer temporarily switches the live Sound Board to Pad Trigger mode or resets the current live pad/octave selection.
- Backing playback shutdown no longer restores stale live mode/pad/octave/polyphony state over changes the visitor made while jamming.
- Replaced separate Record/Stop-recording controls with one stateful Record button that becomes Stop recording while active.
- Replaced separate Play/Stop controls with one stateful Play button that becomes Stop during playback; Record overdub likewise becomes Stop recording while overdubbing.
- Updated recording documentation and regression coverage for live drawing, per-pad colours, independent backing/live state, and stateful transport controls.

## 0.12.1 — 2026-09-18

- Made the Sound Board recording piano roll more compact and added an Instrument lane showing recorded chromatic sampler-pad changes with pad number and clip title.
- Renamed the overall recording section to Recorder and added a separate Recordings heading directly above the saved-recording list.
- Added Record overdub and one-level Undo overdub controls for saved Sound Board recordings.
- Kept the recording JSON as one flat event array while adding a backward-compatible optional event `layer` number; 0.12.0 recordings without a layer remain layer 0.
- Isolated recorded monophony per layer so independently overdubbed parts can remain simultaneous, including different sampler pads playing polyphonic melodies over a monophonic original take.
- Changed recorded-note replay to address each event's stored pad directly instead of forcing the live sampler selection to follow the track.
- Backing mode, pad-selection and octave events no longer take over the live Sound Board controls, so visitors can change pads and jam independently while a recording plays.
- Separated backing-track polyphony from live-jam polyphony; monophonic backing events only cut voices in their own recording layer, while live monophonic playing does not cut the backing track.
- Tightened recorder/editor locking during playback and overdub, and updated the playhead geometry for the denser piano roll.

## 0.12.0 — 2026-09-18

- Added optional browser-local Sound Board performance recordings, configurable globally and overridable per Sound Board menu item.
- Added Record/Stop transport, persistent recording library, rename/delete controls, deterministic replay, and JSON import/export with the complete Sound Board snapshot.
- Added a DAW-style piano-roll visualization: chromatic performances show MIDI-note bars with recorded note lengths and velocity metadata, while Pad Trigger performances use pad/drum lanes without waveform rendering.
- Recorded Pad Trigger events, chromatic MIDI/note events, note-release timing, mode changes, polyphony changes, octave changes, and sampler source-pad selections so changing clips during a chromatic performance replays correctly.
- Recording playback temporarily loads the embedded board/state without overwriting the visitor's personal Sound Board and restores the previous board afterwards.
- Recording playback revalidates embedded clip IDs/UUIDs against currently accessible public clips so stale or inaccessible clips are not silently substituted.
- Added bounded/versioned recording-file validation and mobile-responsive recording controls.
- Updated Sound Board polyphony help text to describe its board-wide Pad Trigger and Chromatic Keyboard behavior.

Notable changes to Punga Audio Archive are recorded here.

## 0.11.31 — 2026-08-31

- Audited and refreshed the README plus the complete user/developer documentation against the current 0.11.30 feature set.
- Corrected obsolete documentation that still described the pre-0.11.29 chromatic-only Polyphonic switch; the board-wide control now documents both Pad Trigger and Chromatic Keyboard behaviour.
- Documented Sound Board `mode`, `octave`, `pad`, and `polyphony` state sharing plus Clip Detail `start`/`t` and `pitch` sharing in the user, developer-state, player, and testing documentation.
- Updated Joomla schema-version and release-build documentation to describe mandatory per-release SQL markers and the build-time version consistency check.
- Removed stale hard-coded release numbers from long-lived documentation pages and updated installer examples/current-version references.
- Verified all relative Markdown links in the README and `docs/` tree.

## 0.11.30 — 2026-08-31

- Fixed Pad Trigger monophonic playback: the Sound Board now checks the live Polyphony switch state instead of only the static component/menu configuration.
- Redesigned the board-wide Polyphony control as a larger switch inside the Sound Board Mode panel, with responsive styling for narrow screens.
- Clip Detail Share now captures the player's current playback position as `start` and the current Featured varispeed value as `pitch` when non-default.
- Added the `0.11.30.sql` Joomla schema marker so database-schema bookkeeping remains synchronized with the release version.

## 0.11.29 — 2026-08-31

- Made the Sound Board Polyphony switch a board-wide control: when polyphony is enabled by configuration, the switch is always visible and applies to both Pad trigger and Chromatic keyboard playback.
- Added the `polyphony` Sound Board URL parameter and preserve the current polyphony state in shared Sound Board URLs.
- Added Clip Detail playback URL parameters: `start` (with `t` alias) accepts decimal seconds, and `pitch` accepts a Featured varispeed offset from -2 to +2 octaves when that control is enabled.
- Repaired Joomla database-schema version bookkeeping by adding the `0.11.29.sql` schema marker and removing the component installer's manual `#__schemas` version override.
- Added a package build-time consistency check that refuses releases whose package version, component manifest version, and newest SQL schema version do not match.

## 0.11.28 — 2026-08-29

- Added the optional Sound Board URL parameter `pad`, using a zero-based pad index (`pad=2` selects the third pad) when chromatic keyboard mode is active.
- Shared Sound Board URLs created in chromatic keyboard mode now include the currently selected pad together with `mode=2` and the current `octave`.
- Invalid, out-of-range, or empty-pad `pad` values are ignored safely.

## 0.11.27 — 2026-08-29

- Sound Board sharing now preserves the current chromatic keyboard state: shared URLs created while chromatic keyboard mode is active include `mode=2` and the current `octave` value.
- Pad-trigger mode keeps the existing compact shared URL without additional mode parameters.

## 0.11.26 — 2026-08-29

- Fixed Sound Board capacity detection on archive, clip-detail, and playlist pages: add/load actions now use the actual Sound Board menu item's pad-count override instead of the current page's resolved parameters.
- Prevented clips from being stored in invisible overflow slots when the Sound Board menu item is configured with fewer pads than another frontend context reports.
- Prune legacy browser-local overflow entries when the Sound Board page is opened, so previously hidden entries no longer suppress the full-board warning.
- Supersedes the incomplete 0.11.25 duplicate-detection fix; the underlying issue was capacity/configuration mismatch rather than clip-ID reuse.

## 0.11.25 — 2026-08-29

- Fixed Sound Board duplicate detection for reused/stale numeric clip IDs by using clip UUIDs as stable identity where available.
- Legacy Sound Board entries remain compatible and are upgraded with UUIDs when safely matched.
- Bumped both Sound Board/social and playlist frontend asset versions to ensure browsers load the updated code.

## 0.11.24 — 2026-08-29

- Fixed frontend cache busting for `social.js`; Sound Board JavaScript changes now receive a new asset URL when the extension is updated.
- Restored the reliable full-Sound-Board warning when adding a clip from the archive or clip detail page.
- Ensured the Sound Board `mode` and `octave` URL parameters introduced in 0.11.23 are delivered to browsers instead of being masked by a stale cached script.

## 0.11.23 — 2026-08-29

- Restored a visible warning when **Add to sound board** is used while every configured pad is occupied; the rejected clip is not stored beyond the visible pad count.
- Added optional Sound Board URL parameters: `mode=1` opens Pad trigger mode, while `mode=2` opens Chromatic keyboard mode.
- Added `octave=1` through `octave=7` to choose the initial chromatic keyboard octave.
- Accepted Sound Board state parameters both in the normal query string and after a shared board's `#board=…` fragment, so shared links can be extended directly.

## 0.11.22 — 2026-08-29

- Added an optional continuous **Pitch / speed** varispeed control to the Featured unified-player presentation.
- Added a ±2-octave live range with exponential playback-rate mapping from 0.25× to 4.00× and no semitone quantisation.
- Disabled browser pitch preservation for varispeed playback so pitch and speed move together, including while a clip is already playing.
- Added a centre detent at normal speed plus a one-click reset to exactly 1.00×.
- Added the **Show Featured speed/pitch control** switch under Playback and downloads → Player style.

## 0.11.21 — 2026-08-29

- Added a visitor-facing **Polyphonic** switch to the chromatic sampler when polyphony is permitted by component or menu-item configuration.
- Allowed visitors to use monophonic chromatic playback without changing normal pad-trigger polyphony.
- Stored the chromatic polyphony preference in the current browser and stopped active sampler voices immediately when switching to monophonic mode.

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
