# Archive performance — 0.13.7.1

ArchiveModel primes PlaybackNormalizationService once for the visible page IDs. The service deduplicates IDs and caches gains per PHP request, including unity fallbacks. The same gain is reused for mobile and desktop renderings. Standalone callers still resolve on demand. File validation and normalization policy remain enforced, with exceptions isolated per artifact.

Expected normalization work for the duplicated table/card layout (all clips normalized, valid artifacts):

| Clips | Previous queries | New queries | Previous waveform reads | New waveform reads |
|---|---:|---:|---:|---:|
| 25 | 50 | 1 | 50 | 25 |
| 100 | 200 | 1 | 200 | 100 |
| 150 | 300 | 1 | 300 | 150 |

These are operation counts derived from the code, not measured server timings. Other archive queries and HTML generation remain. Both responsive HTML presentations remain for compatibility.

IntersectionObserver defers list-player initialization until near the viewport; keyboard focus and pointer interaction also initialize it. A WeakSet prevents duplicate initialization. Without IntersectionObserver players initialize immediately. List audio already used preload=none; this remains unchanged. Stored duration is available as the time-display fallback.

Validation: JavaScript syntax, deferred-player visibility/focus/fallback tests, playback, frequency-profile and copy-link regression tests, XML/JSON and package validation. PHP runtime/lint and live Joomla measurements were unavailable.

On Joomla, compare 25/100/150 clips with the same filters and login state, cold and warm caches. Record document TTFB, DOMContentLoaded and network requests; check first playback normalization, scrolling and switching desktop/mobile widths. Do not attribute a measured gain until these timings are collected.
