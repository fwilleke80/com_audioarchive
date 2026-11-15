# Punga Analytics events

Punga Audio Archive optionally dispatches Joomla events named `onPungaAnalyticsRecord`. It has no hard dependency on Punga Analytics and isolates listener failures.

## Event types

| Event | Meaning |
| --- | --- |
| `audio.play` | A protected play-count request accepted the first ordinary play for a clip in the current page view, or an enabled Sound Board trigger was recorded |
| `audio.download` | An authorised GET download is about to be delivered |
| `audioarchive.playlist.created` | A browser-local playlist was created |
| `audioarchive.playlist.created_from_soundboard` | A Sound Board was saved as a playlist |
| `audioarchive.playlist.deleted` | A playlist was deleted |
| `audioarchive.playlist.clip_added` | A clip was added |
| `audioarchive.playlist.clip_removed` | A clip was removed |
| `audioarchive.playlist.play` | Playlist playback started, including automatic continuation |
| `audioarchive.playlist.shared` | Sharing completed |
| `audioarchive.playlist.saved_shared` | A received playlist was saved locally |
| `audioarchive.soundboard.play` | A Sound Board voice started |
| `audioarchive.soundboard.loaded_from_playlist` | A playlist was loaded into the Sound Board |
| `audioarchive.soundboard.shared` | Sound Board sharing completed |

## Clip payload

Clip-related payloads identify at least:

```text
component:  com_audioarchive
view_name:  clip
item_type:  audioarchive.clip
item_id:    stable clip ID
item_title: current clip title
```

## Counting rules

Ordinary unified-player play recording is de-duplicated per clip during the current page view. Sound Board triggers are counted individually when Sound Board recording is enabled, including repeated and overlapping voices. Chromatic sampler events use `play_source` values such as `midi`, `computer_keyboard`, and `onscreen_keyboard`, with validated MIDI note and velocity fields when available.

HEAD requests and playback streams do not create download events. Disabling the corresponding aggregate counter suppresses its standard play/download event.

## External embeds (0.15.0)

`audio.embed` records successful Copy embed code actions. `audio.play_embedded` records actual iframe playback, updating the clip play total without dispatching `audio.play`. Both use the existing clip identity/title payload and `onPungaAnalyticsRecord` bridge. They recheck current embedding eligibility. Preview frames do not count. Play-count configuration also controls embedded playback events. Event signatures bind clip, purpose and expiry, without requiring third-party cookies. Source-site cookies permit the existing short playback throttle; otherwise deduplication is per frame load. See [external embeds](../user/external-embeds.md).
