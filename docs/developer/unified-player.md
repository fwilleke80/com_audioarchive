# Unified player

The shared player layout is:

```text
com_audioarchive/site/layouts/player/unified.php
```

Registered component assets provide the player JavaScript and CSS.

## Presentations

- **Minimal** — play/pause only
- **Compact** — play/pause, seek bar, current time, duration
- **Default** — Compact plus mute/volume controls
- **Featured** — Default plus waveform and/or spectrum views
- **Playlist** — queue controls, current-item metadata, seeking, mute, and automatic continuation

Archive rows, Archive mobile cards, Related Clips, and playlist list rows use the Minimal control. The Playlists page routes row selection through the Playlist player at the top rather than creating independent queue-less playback.

## Progressive enhancement

The markup includes a native `<audio controls>` fallback. JavaScript hides native controls only after successful custom-player initialisation.

Ordinary unified players coordinate one-player-at-a-time behaviour. Sound Board playback is intentionally separate to support optional polyphony.

## Analyses

Featured players request protected waveform or spectrum data. The preferred view falls back to the available alternative, and the analysis area is omitted when neither derivative exists.

## Styling

Component options are exposed as CSS custom properties for colours, corner radius, button sizes, waveform height, and played/unplayed waveform colours.

Structural template overrides must preserve `data-audioarchive-*` attributes and essential classes used by JavaScript.
