# Sound Board

Create a menu item of type **Punga Audio Archive → Sound Board**.

The Sound Board stores pad assignments in the current browser. It does not require user accounts or server-side personal storage.

## Features

- Configurable pad count
- Keyboard shortcuts
- Add and remove clips
- Direct Clip Detail links
- JSON export and import
- Copyable and natively shareable links
- Temporary shared boards
- Explicit merge or replacement of a received board
- Optional polyphonic playback
- Optional chromatic sampler mode with MIDI, onscreen-piano, and computer-keyboard input
- Save occupied pads as a browser-local playlist
- Record, replay, visualize, import, and export Sound Board performances

Shared links do not overwrite the visitor's personal board automatically.

## Configuration

Global Sound Board settings have a dedicated **Sound Boards** tab with separate **General Sound Board options** and **Sound Board styling** fieldsets.

The global **Enable Sound Board** option controls the page and every **Add to Sound Board** action in Archive lists, Clip Detail, Related Clips, and playlists. General options also control pad count, play counting, polyphony, the chromatic sampler, and **Sound Board recordings**. A Sound Board menu item can override introductory text, pad count, play counting, polyphony, and whether recordings are available. Optional pad colours can be configured globally.

## Chromatic keyboard mode

When the chromatic sampler is enabled, use the mode switch above the pads to choose **Pad trigger** or **Chromatic keyboard**.

In chromatic mode:

- Select an occupied pad to use its clip as the sampler sound.
- Number keys 1–9 and 0 select pads 1–10 instead of triggering normal pad playback.
- The onscreen piano appears automatically.
- Computer keys A, S, D, F, G, H, J, K, and L play white keys; W, E, T, Z, U, O, and P play black keys.
- Y and X move the keyboard down or up by one octave.
- MIDI note C4 (60) plays the clip at its original pitch; other notes change its Web Audio playback rate.
- MIDI velocity controls voice volume.

When polyphony is permitted in the component or menu-item options, the **Sound Board Mode** panel includes a board-wide **Polyphonic** switch. It defaults to the visitor's browser-local preference. Switching it off immediately stops active voices and makes both Pad Trigger and Chromatic Keyboard playback monophonic: each new pad or note cuts the previous voice. Switching it on allows overlapping pad and chromatic voices. When polyphony is disabled by configuration, the switch is hidden and all Sound Board playback remains monophonic.

Sampler voices are one-shots: releasing a MIDI, computer, or onscreen key does not stop an already started clip. MIDI Note Off updates the visible key state only. Pitching also changes playback duration, just as changing tape speed would.

External MIDI requires browser Web MIDI support, an HTTPS page, visitor permission, and a connected MIDI input. When Web MIDI is unavailable, onscreen and computer-keyboard playback remain usable.

## Recordings

When **Sound Board recordings** are enabled, the Sound Board can record a performance without recording or duplicating audio files. A recording stores a timestamped event sequence together with a snapshot of the Sound Board that was used.

Press **Record** to start and **Stop recording** to finish. The completed recording is saved immediately in the current browser and appears in the Recordings library. Recordings can be renamed, replayed, deleted, imported from JSON, or exported as JSON.

The recorded event stream includes:

- Pad Trigger activations.
- Chromatic MIDI/note activations with MIDI note number and velocity.
- Note-release timing for MIDI, computer-keyboard, and onscreen-keyboard input, used to draw normal piano-roll note lengths. The sampler itself remains one-shot; Note Off still does not stop the playing audio.
- Changes between Pad Trigger and Chromatic Keyboard modes.
- Board-wide Polyphonic switch changes.
- Chromatic octave changes.
- **Sampler source-pad selections**, so changing from one Sound Board clip to another during a chromatic performance is reproduced at the correct time.

The visualization is deliberately a conventional piano roll rather than an audio waveform. Chromatic notes are shown on MIDI-note rows; Pad Trigger events use pad/drum-style rows. State changes such as pad selection and polyphony are stored for playback but are not drawn as audio data.

During replay, Audio Archive temporarily uses the embedded Sound Board snapshot without overwriting the visitor's personal board. Backing events use their own recorded pad/instrument directly and do not take over the live Sound Board selection, octave, mode, or Polyphonic switch. You can therefore select another pad and jam on the chromatic keyboard while a recording is playing. Exported JSON contains the board entries, initial Sound Board state, timing information, and recorded events. Clip IDs are accompanied by their stable UUIDs; when replaying, Audio Archive checks the current public clip metadata so an inaccessible or mismatched clip is not silently substituted.

### Overdub and Undo

**Record overdub** plays the selected recording from the beginning while recording new Sound Board input against the same clock. The new events are merged into the same flat event sequence when playback finishes or **Stop** is pressed. **Undo overdub** restores the recording to its exact event list and duration from immediately before the most recent overdub.

Each overdub receives an internal numeric `layer`. This is not a separate DAW track: the JSON remains one event list. The layer exists so monophonic behavior is isolated per take. A monophonic original melody can therefore continue underneath a separately recorded overdub instead of the new take cutting voices from the original. Old 0.12.0 recordings without a layer value are treated as layer 0.

The piano roll is compact and includes an **Instrument** lane above the note rows. Chromatic sampler-pad selection changes are displayed there with the pad number and clip title, making changes of sampler source visible as well as audible.

## Playlists

When Playlists are enabled, **Save as playlist** creates a new named playlist from occupied pads in pad order. Empty pads are skipped. Clip IDs are resolved to the stable UUIDs used by playlists; clips that are no longer publicly accessible are omitted.

## Playback and analytics

The Sound Board uses its own direct-audio voice system so several clips can overlap when polyphony is enabled.

When **Record Sound Board plays** and aggregate play counting are enabled, every successful pad or chromatic-note trigger increments the clip and dispatches the configured analytics events, including repeated and overlapping triggers. Chromatic events identify MIDI/onscreen/computer input and include note and velocity values where applicable.

## Opening a Sound Board in a specific mode

The Sound Board accepts optional URL parameters that choose its initial presentation without changing the stored board:

- `mode=1` opens **Pad trigger** mode.
- `mode=2` opens **Chromatic keyboard** mode.
- `octave=1` through `octave=7` chooses the initial keyboard octave when chromatic mode is used.
- `pad=0` through `pad=n-1` selects the initial Sound Board pad in chromatic mode. Pad indexes are zero-based, so `pad=2` selects the third pad.
- `polyphony=1` enables polyphony and `polyphony=0` disables it when polyphony is allowed by the Sound Board configuration.

For example, `/soundboard?mode=2&octave=3&pad=2&polyphony=0` opens the board in chromatic mode at octave 3 with the third pad selected and monophonic playback. Shared boards support the same parameters. Because the shared board itself is stored in the URL fragment, parameters may also be appended directly to the shared fragment, for example `#board=…&polyphony=0&mode=2&octave=3&pad=2`. Invalid mode, octave, pad, or empty-pad selections are ignored.

The Polyphony switch is shown inside the Sound Board Mode panel whenever polyphony is enabled in the component/menu configuration. Its state applies equally to Pad trigger and Chromatic keyboard playback.

Shared Sound Board URLs retain the current polyphony state. When chromatic keyboard mode is active, the generated URL also includes the current `mode`, `octave`, and selected `pad`.
