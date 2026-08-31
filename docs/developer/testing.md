# Testing

## Static validation

For every release:

- Run `php -l` over PHP files.
- Parse XML files.
- Parse JSON asset manifests.
- Check JavaScript syntax with an available parser/runtime.
- Check CSS structure where tooling is available.
- Confirm English and German language-key parity and duplicate-free keys.
- Test ZIP CRC integrity for the outer package and every nested archive.

## Installation and update

Test both:

- Fresh installation on Joomla! 6
- Update over the previous released version with existing clips and configuration

Verify database migrations, extension enabled states, menu-item forms, and component configuration.

## Administrator workflows

Cover single creation, editing, Batch Apply/Cancel, bulk upload, directory import, individual and bulk replacement, analysis queue processing, integrity checks, cleanup, export, and restore.

## Frontend workflows

Cover Archive filtering/sorting/pagination, Tag Directory, Clip Detail, Previous/Next, Related Clips, ratings, protected downloads, Sound Board, playlists, modules, content placeholders, Smart Search, frontend editing, and routing with multiple menu items.

For Sound Boards, cover Pad Trigger and Chromatic Keyboard modes, pad selection with number keys, the onscreen and computer keyboards, configured polyphony, the board-wide visitor Polyphonic switch, octave changes, MIDI permission/device changes where supported, and mobile audio unlocking. Confirm that the visitor switch is absent when configured polyphony is disabled, switching to monophonic mode immediately stops active voices, subsequent Pad Trigger clips cut each other out, chromatic notes cut each other out, and MIDI Note Off retains documented one-shot playback. Test `mode`, `octave`, `pad`, and `polyphony` in both query strings and shared-board fragments, and confirm generated shared URLs retain the current state. Test Sound Board-to-playlist and playlist-to-Sound Board conversion for ordering, empty/unavailable clips, pad-count limits, occupied-board confirmation, storage failures, and disabled feature switches.

For Clip Detail, test `start`/`t` with integer and decimal seconds, out-of-range values, `pitch` with and without the Featured varispeed control, and Share links generated from live player position and varispeed state.

Test desktop and mobile Safari, Chrome, Firefox, and Edge where practical. iPhone Safari is important for player-control and Web Audio user-gesture regressions.

## Security

Exercise unauthorised direct routes, inaccessible clips/categories, CSRF failures, malformed Range headers, invalid filter input, path traversal, symlink escape, MIME mismatches, and process-argument injection attempts.

## Media fixtures

Maintain representative AAC/M4A, ALAC/M4A, MP3, Ogg Vorbis, Opus, WAV, FLAC, Unicode filenames, duplicates, invalid media, very short clips, and long clips.
