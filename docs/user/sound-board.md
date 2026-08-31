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

When polyphony is permitted in the component or menu-item options, the chromatic controls include a **Polyphonic** switch. It defaults to on and the visitor's choice is stored in the current browser. Switching it off immediately stops active sampler voices; each subsequent chromatic note replaces the previous sampler voice. This switch affects only chromatic playback. Ordinary pad playback continues to follow the configured Sound Board polyphony setting. When polyphony is disabled by configuration, the switch is hidden and all Sound Board playback remains monophonic.

Sampler voices are one-shots: releasing a MIDI, computer, or onscreen key does not stop an already started clip. MIDI Note Off updates the visible key state only. Pitching also changes playback duration, just as changing tape speed would.

External MIDI requires browser Web MIDI support, an HTTPS page, visitor permission, and a connected MIDI input. When Web MIDI is unavailable, onscreen and computer-keyboard playback remain usable.

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

For example, `/soundboard?mode=2&octave=3&pad=2` opens the board in chromatic mode at octave 3 with the third pad selected. Shared boards support the same parameters. Because the shared board itself is stored in the URL fragment, parameters may also be appended directly to the shared fragment, for example `#board=…&mode=2&octave=3&pad=2`. Invalid mode, octave, pad, or empty-pad selections are ignored.

The Polyphony switch is shown inside the Sound Board Mode panel whenever polyphony is enabled in the component/menu configuration. Its state applies equally to Pad trigger and Chromatic keyboard playback.

Shared Sound Board URLs retain the current polyphony state. When chromatic keyboard mode is active, the generated URL also includes the current `mode`, `octave`, and selected `pad`.
