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

Shared links do not overwrite the visitor's personal board automatically.

## Configuration

Global Sound Board settings have a dedicated **Sound Boards** tab with separate **General Sound Board options** and **Sound Board styling** fieldsets.

The global **Enable Sound Board** option controls the page and every **Add to Sound Board** action in Archive lists, Clip Detail, Related Clips, and playlists. General options also control pad count, play recording, polyphony, and the chromatic sampler. A menu item can override introductory text, pad count, play recording, and polyphony. Optional pad colours can be configured globally.

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

Sampler voices are one-shots: releasing a key does not stop an already started clip. Pitching also changes playback duration, just as changing tape speed would.

External MIDI requires browser Web MIDI support, an HTTPS page, visitor permission, and a connected MIDI input. When Web MIDI is unavailable, onscreen and computer-keyboard playback remain usable.

## Playlists

When Playlists are enabled, **Save as playlist** creates a new named playlist from occupied pads in pad order. Empty pads are skipped. Clip IDs are resolved to the stable UUIDs used by playlists; clips that are no longer publicly accessible are omitted.

## Playback and analytics

The Sound Board uses its own direct-audio voice system so several clips can overlap when polyphony is enabled.

When **Record Sound Board plays** and aggregate play counting are enabled, every successful pad or chromatic-note trigger increments the clip and dispatches the configured analytics events, including repeated and overlapping triggers. Chromatic events identify MIDI/onscreen/computer input and include note and velocity values where applicable.
