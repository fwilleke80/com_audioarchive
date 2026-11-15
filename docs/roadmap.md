# Punga Audio Archive roadmap

Agreed with Frank on 15 November 2026. This is the active roadmap and supersedes the priorities in the historical project specification and earlier planning documents.

| Order | Milestone | Scope |
| --- | --- | --- |
| 1 — 0.15.0 | External embeds | Public clips only; global enable switch and per-clip owner control; Minimal, Compact, Default and Featured players; Share → Embed with preview and copyable iframe code. Dispatch `audio.embed` on successful copying and `audio.play_embedded` on actual embedded playback, without also dispatching `audio.play`. |
| 2 | Richer metadata | Licence, creator, contributors, recording location, genre/style, year of creation and lyrics. Keep clip ownership/uploader separate from creative credits; reconcile genre/style with existing tags. |
| 3 | Optional public profiles | Opt-in public user pages displaying public clips and explicitly published user playlists. Always preserve individual clip permissions. |
| 4 | Comments | Global enablement plus per-owner/per-clip permission. Plan author, owner and administrator moderation. |
| 5 — later | MP3 playback previews | Generate full-length playback copies from originals such as FLAC, WAV and AIFF; preserve originals. Short excerpts are a separate potential feature. |

Saved archive searches and user favourites are not on the active roadmap. Existing playlists already cover much of the desired personal organisation.

Multi-user support, ownership, frontend uploads, private clips, quotas and account-stored collections are already implemented. They are not future milestones.

Only external embeds are in scope for 0.15.0. The remaining milestones require their own design and implementation releases.
