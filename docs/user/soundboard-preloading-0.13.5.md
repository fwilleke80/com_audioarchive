# Sound board preloading (0.13.5)

Component Options → Sound Board → **Preload sound board audio** is enabled by default. Disable it to restore the previous on-demand playback path; normalization metadata continues loading in both modes. Reload an open board after changing the option.

Short clips are downloaded and decoded in the background. A small ellipsis on a pad indicates preparation; pads remain playable while loading. Ready clips use buffer playback with the existing normalization gain. On browsers requiring a user gesture to unlock audio, the first press may still use native playback; subsequent ready presses can use buffers.

## Memory and network limits

- The new speculative decoded cache holds at most 64 MiB, including cached buffers retained by active voices from a previous board.
- Decode only clips with known duration and channel count, at most 30 seconds long, and an estimated allocation no larger than 16 MiB. Estimate float PCM using duration, channels, and a conservative resampling rate before downloading.
- One background download/decode at a time, including across board switches. Encoded downloads are limited to 8 MiB and a 15-second fetch timeout.
- Long clips, missing metadata and clips that do not fit the budget retain streaming playback. At most two native elements receive metadata-only preload hints. Browser handling of these hints varies.
- Board changes cancel obsolete fetches. In-progress browser decoding cannot be cancelled; its result is discarded before the next decode starts. Active voices retain their buffers until they stop.

The 64 MiB limit is a cache budget, not a cap on the browser process. Temporary encoded/decoder memory, native media buffering and existing on-demand chromatic/recording decoding are additional. Inaccurate stored metadata can also cause a larger transient allocation; actual buffer size is checked before caching.

## Verification on the site

1. Open a board with short clips; let the loading ellipses clear, then compare repeated pad triggering with the option off.
2. Try a long stereo recording: it should remain streamed and playable.
3. Switch boards during preparation; press Stop during playback and check that no delayed sound starts.
4. Check normalization, monophonic/polyphonic playback, chromatic notes, recording playback and overdubs on iPhone Safari and desktop Safari/Chrome.

Automated checks cover admission, budget handling, active-buffer retention, stale decode rejection, sequential decoding and buffer pad routing. Live Joomla/browser/audio-device timing must be checked separately.
