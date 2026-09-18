# Sound Board recordings

Sound Board recordings are browser-local performance documents. They do not contain audio data. Instead, they store the Sound Board snapshot and a bounded timestamped event sequence that can reconstruct the performance with the site's current accessible clips.

## Storage

Recordings are stored in:

```text
com_audioarchive.soundboard.recordings.v1
```

The collection is limited to 100 recordings. Each recording is independently bounded to a maximum of 20,000 events and two hours.

## JSON format

Exported files use:

```json
{
  "format": "punga-audioarchive-soundboard-recording",
  "version": 1,
  "name": "Recording 1",
  "created": "2026-09-18T15:30:00.000Z",
  "durationMs": 5240,
  "board": [
    {"id": 12, "uuid": "…", "title": "Kick"},
    null,
    {"id": 18, "uuid": "…", "title": "Voice"}
  ],
  "initialState": {
    "mode": "chromatic",
    "polyphony": true,
    "octave": 4,
    "pad": 0
  },
  "events": []
}
```

The browser-local `id` used to identify a saved recording is deliberately omitted from exported JSON and regenerated on import.

## Events

Version 1 supports:

- `pad`: Pad Trigger activation (`t`, `pad`, optional `layer`).
- `note`: chromatic note activation (`t`, `pad`, `note`, `velocity`, `duration`, `source`, optional `layer`). `note` is the absolute MIDI note number. `duration` records key-down/key-up duration for the piano roll; the current sampler remains one-shot.
- `select`: chromatic sampler source-pad selection (`t`, `pad`, optional `layer`).
- `mode`: Sound Board mode change (`t`, `mode`, optional `layer`).
- `polyphony`: mono/poly state change (`t`, `enabled`, optional `layer`).
- `octave`: visible chromatic keyboard octave change (`t`, `octave`, optional `layer`).

Sampler source-pad selection is a first-class event because the same MIDI note can play a different clip after the visitor selects another occupied Sound Board pad.

`layer` was added compatibly in 0.12.1 for overdubs. Missing values mean layer 0, so 0.12.0 recording JSON remains valid. Each overdub gets a new layer while all events remain in one flat array. Recorded monophony is applied only to backing voices in the same layer, allowing independently overdubbed parts to remain simultaneous even when one take was performed monophonically.

## Playback

Playback temporarily replaces only the in-memory Sound Board with the recording's embedded board. The visitor's personal board in local storage is left untouched. The previous board, mode, polyphony, octave, and selected sampler pad are restored when replay stops or finishes.

Before replay, board IDs are checked against the public Sound Board route-resolution endpoint. When an exported entry includes a UUID, that UUID must still match the public clip returned for the numeric ID. Inaccessible, deleted, or ID-reused clips therefore become empty pads rather than silently resolving to unrelated audio.

Chromatic sample buffers referenced by note events are preloaded before the performance clock starts. Recorded note events address their stored `pad` directly instead of changing the live sampler selection first. Backing `select`, `mode`, and `octave` events therefore never take control of the user's live keyboard while the recording plays. Recorded polyphony is maintained per recording layer and affects only backing voices; live jamming uses the visitor's current Sound Board polyphony independently.

Recorded events are dispatched against a monotonic `performance.now()` playback clock.

## Piano roll

The visualization contains only performance events—no audio waveform data.

Chromatic `note` events use MIDI-note rows and their stored key-down/key-up duration. Pad Trigger events use pad/drum rows and fixed-width trigger markers. A compact Instrument lane above the notes renders chromatic `select` events with the pad number and clip title. Mode, polyphony, and octave events remain metadata rather than pseudo-audio rows.

## Configuration

`enable_soundboard_recordings` is available globally under **Sound Boards → General Sound Board options** and as an inheritable Sound Board menu-item parameter. The resolved menu-item value controls whether recording UI is rendered at all.
