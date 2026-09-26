# Changelog

## 0.13.4.6

- Match Add to sound board with the existing playlist submenu: retain the parent menu while choosing, reuse nested menu styling and positioning, and dismiss after a successful addition. Preserve default/full/already-added labels and guest direct-add behaviour.

## 0.13.4.5

- Fix the archive opening click immediately dismissing the sound-board chooser. Register outside dismissal in capture phase and remove the matching listener on close. Add an event-phase regression check.

## 0.13.4.4

- Fix the archive combined Add to menu and playlist clip actions bypassing the account sound-board chooser. Share the chooser with standalone clip buttons.

## 0.13.4.3

- Add an account sound-board destination chooser to archive/detail Add to sound board actions. Mark the default, disable full/already-added destinations, preserve board selection and retain the guest direct-add action.

## 0.13.4.2

- Archive and clip-detail Add to sound board actions target the account default board, while the Sound Board page retains its last selected board.

- Share pending sound-board metadata requests between rendering and playback. Retry transient failures up to three times with a ten-second timeout per attempt; later interactions can retry again.
- Wait for normalization metadata before pad playback or sampler decoding. Do not substitute gain 1 while metadata is pending or failed.
- Recover clip-detail icons after transient request failures without reloading the page.

## 0.13.4.1

- Keep Play, Record overdub, Undo overdub and Actions only on the active recording toolbar. List rows select recordings without duplicating controls.

## 0.13.4

- Store signed-in users’ recordings in their accounts with owner isolation, transactional revision checks and explicit browser import. Guests retain browser storage. Import removes only acknowledged unchanged browser copies.
- Play recordings using independent embedded clip mappings without replacing the visible sound board. Overdub layers retain their own board mappings.
- Add per-recording Play, Overdub and Undo controls; move Rename, Export, Load sound board and Delete into Actions menus. Add space above Recorder.
- Explicitly loaded recording boards remain temporary, with Return and Save as new account board controls.
- Include account recordings in full archive backup/restore, remapping users and clips. Retain failed recording saves in memory for retry/export.

## 0.13.3.1

- Remove the redundant profile access-level setting and custom visibility option. Legacy personal access defaults no longer affect uploads.
- Group the seven read-only statistics under My Audio Archive on the profile display; hide them on frontend Edit profile. Retain administrator usage and quota controls.
- Show names and usernames in the backend Owner filter; label deleted owners explicitly.

## 0.13.3

- Guests always use browser collections; signed-in users always use account collections. Existing browser import remains available, with successful imports removing acknowledged browser copies.
- New packaged User – Audio Archive plugin adds personal upload defaults, default sound board, usage summaries and administrator-only per-user quota overrides to existing Joomla user profiles. Defaults obey current category, access and upload-menu restrictions.
- Account deletion preserves archival data, revokes collection share links and makes orphaned collections inaccessible.
- Rating controls read authoritative server votes across browsers. Existing browser ratings transfer to the signed-in account when rating permissions allow, retaining account votes on conflict. Nobody / Registered / Everyone permissions remain intact.
- Integrity & Maintenance is last in the administrator sidebar, after Quota rules.
- Playback, normalization, audio-session and frequency-profile drawing code remains unchanged from 0.13.2.7.


## 0.13.2.7

- Request the iOS media-playback audio session before starting normalised buffer playback, addressing the missing session setup that can silence Web Audio when the phone is in Silent mode. Retain the 0.13.2.6 buffer engine and continuous speed control.
- Coordinate playback-session ownership with the chromatic sampler. Restore the previous session category only when the last owner releases it; preserve existing microphone/call sessions and tolerate unsupported APIs.
- Recheck/resume the audio context if interrupted while a clip downloads or decodes, with the existing cancellation checks before starting it.
- Fix a frequency-profile rendering ReferenceError caused by a stray waveform-gain assignment introduced in 0.13.2.2. Add production-renderer and audio-session regression tests.

## 0.13.2.6

- Replace media-element Web Audio routing for normalised enhanced players with speed controls with decoded-buffer playback to avoid gapped varispeed audio on affected Safari/WebKit versions. Speed, gain and the playback clock now use the same audio engine.
- Preserve pause/resume, seeking, volume/mute, playlist advance and shared-link playback position through a common playback facade. Cancel stale starts when pausing, switching clips or stopping sound-board voices during loading.
- Bound the page-local decoded cache and release inactive buffers. Oversized clips, decode failures and unavailable Web Audio use native streaming without normalisation. First normalised playback waits for download/decode; no server-side audio regeneration is needed.
- Keep fixed-speed sound-board pad streaming, the chromatic sampler and recorder data unchanged. Bump player module URLs to invalidate cached pre-fix code.

## 0.13.2.5

- Fix clip detail return navigation from My clips: restore the originating workspace URL, including filters and pagination, and use its menu title in the back link. Reuse the existing same-tab return navigation used by Archive and Sound Board.

## 0.13.2.4

- Automatically process enabled analyses for successful frontend uploads, with incremental progress and same-session resumption from My clips. The server grants only the exact jobs created for that owned upload; general Process Audio permissions remain unchanged.
- Reuse Archive table styling and its shared inline player in My clips, fix the Actions translation, and collapse processing details behind a short summary.
- Hide and select the category when a frontend upload has only one permitted category.
- Fix All/Any module tag matching and prepared-statement failures; Clip of the day uses the same eligible set. Show a translated empty state and use searchable AJAX tag selection.

## 0.13.2.3

- Remove account-storage status labels and Not now controls from Playlists and Sound Boards; keep useful browser-only notices.
- Show share revocation only for the selected collection with an active link, updating after selection, sharing and revocation.
- Move fully imported browser collections into the account, remove acknowledged browser copies and prevent duplicate retries. Keep incomplete collections in the browser with an explanation.
- Add Public, Registered users and Private frontend visibility choices, using Joomla access levels and preserving allowed-access restrictions. Simplify English and German visibility labels.

## 0.13.2.2

- Rename the Processing tab’s Clip analysis section to Waveform generation (English and German).

- Scale played and unplayed waveform heights using the clip’s effective peak-normalization gain, including per-clip overrides and target level.
- Redraw cached waveform layers when gain changes and constrain drawing to the canvas amplitude range.
- Apply the same behavior to the standalone waveform renderer; stored peaks and original media remain unchanged.

## 0.13.2.1

- Fix hidden normalization options: place the controls in a nested Playback normalization section within Playback and Downloads.

## 0.13.2

- Add account-stored playlists and named Sound Boards, configurable storage policy, explicit browser import, ownership checks and revision conflict protection.
- Add revocable short collection share links with viewer-specific clip access checks and portable archive export/restore.
- Add optional non-destructive peak normalization, per-clip overrides and original-channel peak measurement during waveform generation.
- Make the recorder collapsible and remember its state for the current user in the browser session.
- Hide the unsupported CMS Field insertion button in frontend clip editors.

## 0.13.1.1 — Archive initialization hotfix

- Fix infinite recursion when opening the frontend Archive, caused by reading model state from inside its own lazy initialization while persisting the Owner filter.
- Resolve the owner once and reuse the local value for model/session state, preserving disabled-filter behavior, request filters, session restoration and reset.
- Add an executable regression test using the production Archive model and a Joomla-style lazy-state adapter: the 0.13.1 code reproduces recursive entry; the fixed code passes 24 assertions.
- No feature or database layout changes. Install over 0.13.1; 0.13.2 remains the planned account-collections milestone.

## 0.13.1 — Frontend contribution

- Add Upload Clip and My Clips menu types for authenticated contributors.
- Reuse the existing upload, metadata and processing services; enforce Create permission, menu/category restrictions and quotas server-side.
- Provide moderated creation, normal/private visibility and globally allowed Access Levels narrowed by menu policy. Posted ownership and managed fields cannot override server values.
- Show owned clips, quota usage, filters, processing status, permitted preview/edit actions and permission-checked trash/permanent deletion.
- Refine the existing frontend editor with restricted categories, Access Levels, tags, visibility and preserved publication controls. Audio Archive now has its own frontend-editing option.
- Add opt-in public Owner column, Owner filter and Clip Detail attribution; owner options derive only from eligible public results.
- Add contributor setup and quota-test instructions, 27 additional production-policy assertions, and a no-op schema marker. Full Joomla/MySQL browser and concurrency validation remains a staging gate.

## 0.13.0 — 2026-09-19

- Added normal/private visibility, shared clip policy and permission-based owner previews; protected media and analyses recheck current access.
- Added private-content management, ownership transfer and explicit quota-override ACL actions.
- Added backend Owner/Visibility filters and columns, guarded owner changes and batch transfers.
- Added original-storage and retained-clip quotas with site defaults, additive group rules, user-profile schema, and a Quota Rules page.
- Serialized owner usage changes around upload/replacement, clip creation, ownership transfer and archive restore. Smaller replacements remain permitted when over quota.
- Excluded private clips from public discovery, modules, tag counts, related ranking, Finder indexing and Joomla Tags UCM publication.
- Added portable group quota rules and profile data to exports/restores, preserving clip visibility and unresolved private ownership.
- Corrected recorder documentation while leaving recorder/player JavaScript and browser collection formats unchanged.
- Added executable access/quota regression tests; full Joomla/MySQL staging validation remains required.

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
