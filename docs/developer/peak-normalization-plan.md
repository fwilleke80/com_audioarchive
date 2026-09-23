> Implemented in 0.13.2. See [setup and acceptance tests](../user/0.13.2-testing.md).

# Punga Audio Archive — Peak Analysis and Playback Normalization Plan

## Status

This document is an implementation handoff for a future Punga Audio Archive development conversation.

**Important baseline rule:** do **not** use Punga Audio Archive 0.13.1 as the implementation baseline. It was explicitly reported as buggy after upload. In the implementation conversation, use the corrected/latest source and installer archives supplied there, inspect that baseline first, and increment its version appropriately.

The feature described here is **non-destructive playback normalization**. It must not rewrite, transcode, replace, or otherwise modify the original audio files.

---

# 1. Goal

Add sample-peak information to waveform analysis data and use that information at playback time to normalize clips toward a configurable peak level.

The design should provide:

- automatic global sample-peak analysis whenever waveform data is generated;
- a component-wide option to enable or disable playback normalization;
- a component-wide target normalization level in decibels;
- a per-clip tri-state override:
  - inherit the component option;
  - force normalization on;
  - force normalization off;
- normalization across all Audio Archive playback paths that use the enhanced JavaScript/Web Audio players;
- graceful fallback to ordinary, unnormalized playback whenever the required peak information or Web Audio support is unavailable;
- full backward compatibility with existing waveform files that do not contain global peak information.

The original media files remain untouched.

---

# 2. Terminology and scope

This feature is **sample-peak normalization**, measured in **dBFS**.

It is **not**:

- LUFS / EBU R128 loudness normalization;
- true-peak / dBTP normalization;
- destructive normalization of stored audio;
- transcoding;
- limiting;
- compression.

The UI may use the shorter wording **Normalize playback** and display the target unit as **dB**, but help text/documentation should make clear that the value is a sample-peak target in **dBFS**.

A sensible default target is:

```text
-1.0 dBFS
```

---

# 3. Global peak generation

## 3.1 Generate the peak together with waveform analysis

Whenever Audio Archive generates or regenerates waveform data, it should also calculate the clip's global sample peak.

This should happen quietly as part of the waveform-analysis operation. Do not create a separate user-facing analysis mode or require a separate normalization-analysis job.

Preferred implementation:

- obtain the global peak from the same decoded PCM pass used for waveform generation;
- measure the original channels before any mono downmix used for compact waveform generation.

If the current implementation architecture makes a literally shared PCM pass impractical, the global peak may still be calculated internally as part of the waveform-generation job. The important behavioral requirements are:

- waveform generation produces the peak metadata;
- playback never launches a new peak-analysis job;
- normalization itself never scans or decodes the source file on demand.

## 3.2 Measure the original channels

Do **not** infer the normalization peak from the existing binned mono waveform extrema.

The waveform representation may be generated from mono/downmixed PCM. Downmixing can alter peak amplitude because channels may reinforce or cancel one another.

Instead, determine:

```text
global_peak = maximum absolute sample value across all decoded source channels
```

Then convert to dBFS:

```text
global_peak_dbfs = 20 * log10(global_peak)
```

The value may be above `0 dBFS` for unusual floating-point material; do not assume the result must always be <= 0.

## 3.3 Silence and invalid values

For silence or invalid/unusable measurements:

- do not produce an infinite or NaN dB value;
- either omit the global-peak field or store it as `null`, following the conventions of the existing waveform schema;
- playback normalization must treat the missing/invalid peak as unavailable and perform no normalization.

Do not apply an arbitrarily huge gain to silence or near-zero invalid data.

---

# 4. Waveform data format

Extend the waveform analysis data with an optional global-peak field.

Preferred field:

```json
{
    "global_peak_dbfs": -8.37
}
```

The precise placement should follow the existing waveform JSON/data schema rather than inventing a parallel storage system.

Requirements:

- the field is optional;
- old waveform files remain valid;
- no database migration should be necessary merely to retrofit old waveform artifacts;
- existing waveform files without the field must continue to render normally;
- absence of the field means **normalization is unavailable for that clip**, not an error.

Do not duplicate the derived global peak into the clip table unless the actual current architecture makes that unavoidable. The waveform analysis artifact should remain the authoritative source for this derived analysis value.

Regenerating a waveform naturally recalculates/replaces the global peak.

---

# 5. Component options

Add options under the existing playback-oriented component settings, preferably **Playback and Downloads** rather than the analysis-processing section.

## 5.1 Normalize playback

```text
Normalize playback: Yes / No
```

Suggested default:

```text
No
```

This avoids silently changing playback loudness on existing installations immediately after update.

If the product's established option-default conventions strongly favor enabling new playback enhancements, inspect the current baseline before choosing otherwise; do not silently change this decision without documenting it.

## 5.2 Peak normalization level

```text
Peak normalization level: -1.0 dB
```

Internally this represents dBFS.

Suggested default:

```text
-1.0
```

Validation should reject nonsensical values. In particular, a positive target should not be accepted by default because that intentionally requests sample peaks above digital full scale.

Use existing Joomla form validation conventions and existing component option styling.

Suggested help text:

> Target sample-peak level used for playback normalization. Normalization is applied only when waveform analysis contains global peak information. Original audio files are not modified.

---

# 6. Per-clip normalization override

Add a tri-state clip setting:

```text
Playback normalization

Inherit
Enabled
Disabled
```

Semantics:

- **Inherit** — follow the component-wide **Normalize playback** setting.
- **Enabled** — force normalization for this clip even when globally disabled.
- **Disabled** — never normalize this clip even when globally enabled.

The default for existing and new clips should be:

```text
Inherit
```

Use the component's existing conventions for tri-state/inheritable fields.

The field must work anywhere clips can legitimately be edited in the corrected baseline, including frontend clip editing if that baseline exposes the relevant clip settings there.

Persist the override as clip metadata/database state. Use a compact representation consistent with existing schema conventions, for example:

```text
inherit
enabled
disabled
```

or an equivalent integer enum.

Do not overload unrelated access/publication fields.

---

# 7. Effective normalization decision

For each clip, derive an effective boolean:

```text
if clip override == Enabled:
    normalize = true
else if clip override == Disabled:
    normalize = false
else:
    normalize = component Normalize playback option
```

Normalization may then be applied only if all of these are true:

```text
normalize == true
global_peak_dbfs is present
global_peak_dbfs is finite/valid
Web Audio normalization path initialized successfully
```

If any condition fails, play the clip normally.

There should be no user-facing error merely because normalization cannot be applied.

---

# 8. Gain calculation

Given:

```text
target_peak_dbfs
measured_peak_dbfs
```

calculate:

```text
gain_db = target_peak_dbfs - measured_peak_dbfs
gain_linear = 10 ^ (gain_db / 20)
```

Example:

```text
measured peak: -8.37 dBFS
target peak:   -1.00 dBFS

gain_db = -1.00 - (-8.37)
        = +7.37 dB

gain_linear ~= 2.337
```

Normalization must support both:

- amplification when the clip peak is below the target;
- attenuation when the clip peak is above the target.

Do not use `HTMLMediaElement.volume` as the normalization mechanism, because it is limited to the range 0..1 and therefore cannot provide positive gain.

---

# 9. Shared/unified player architecture

The enhanced shared player should use Web Audio for the normalization stage.

Conceptual signal path:

```text
HTMLMediaElement
      |
      v
MediaElementAudioSourceNode
      |
      v
Normalization GainNode
      |
      v
User-volume gain / existing volume control
      |
      v
AudioContext.destination
```

The exact integration must be adapted to the corrected baseline after inspecting its current JavaScript. Do not blindly replace existing player code from an older version.

## 9.1 Keep normalization gain separate from user volume

Normalization and user volume are different concepts.

For example:

```text
normalization gain: +7.37 dB
user volume:        65%
```

Changing the player's volume slider must not change or destroy the normalization calculation.

The normalization gain is clip-derived and stable for that clip/target. User volume remains interactive presentation state.

## 9.2 Web Audio lifecycle

Implementation must correctly handle browser Web Audio restrictions:

- create/resume the `AudioContext` from a valid user interaction when required;
- do not create multiple `MediaElementAudioSourceNode` objects for the same `<audio>` element;
- reuse/cache the audio graph for an element where appropriate;
- clean up listeners/state when player instances are destroyed or replaced;
- avoid breaking Safari behavior.

The corrected baseline must be inspected because Audio Archive already has Web Audio code in the Sound Board and may already contain reusable helpers.

---

# 10. Progressive-enhancement fallback

Audio Archive already uses native `<audio>` markup as a fallback when enhanced JavaScript is unavailable.

Preserve that behavior.

If:

- JavaScript fails;
- Web Audio is unavailable;
- the `AudioContext` cannot be initialized;
- global peak data is missing;
- global peak data is invalid;

then playback should continue using ordinary unnormalized audio.

**Normalization must never be required for successful playback.**

Do not hide or disable a clip merely because its waveform predates global-peak support.

---

# 11. Playback paths that must honor normalization

The effective normalization policy should be applied consistently wherever Audio Archive itself plays a clip through its enhanced playback code.

At minimum verify:

- archive-row/card shared player;
- clip-detail shared player;
- module players;
- content-plugin embedded players;
- Featured player;
- Compact/Default/Minimal player presentations where JavaScript enhancement is active;
- Playlist player;
- backend clip preview;
- Sound Board pad-trigger mode;
- Sound Board chromatic-keyboard mode;
- Sound Board recording playback if it replays clip voices through the same voice engine.

Where several of these surfaces already share the same renderer/player engine, normalization should be implemented centrally rather than duplicated.

---

# 12. Sound Board behavior

The Sound Board already uses Web Audio and per-voice gain behavior in recent Audio Archive versions.

Apply the clip's normalization gain to each triggered voice.

Conceptually:

```text
decoded/source voice
      |
      v
clip normalization gain
      |
      v
existing per-voice/pad gain
      |
      v
Sound Board master/output
```

Preserve all existing Sound Board behavior:

- polyphony;
- monophonic mode where supported;
- note-on/note-off behavior;
- velocity behavior if present;
- pad gain/volume behavior;
- speed/pitch behavior;
- recording/overdub playback;
- Safari compatibility fixes from the current baseline.

Do not make normalization alter MIDI/note lifecycle semantics.

---

# 13. Polyphony and clipping

Per-clip peak normalization does **not** guarantee that a polyphonic mix remains below the target.

For example, two clips each normalized to `-1 dBFS` may sum above `0 dBFS` when played simultaneously.

That is expected.

Do **not** add a compressor, limiter, automatic master attenuation, or polyphonic loudness compensation as part of this feature unless explicitly requested later.

Document this distinction.

---

# 14. Playlist behavior

Playlist playback must use the same effective normalization logic as ordinary clip playback.

When the playlist advances to a new clip:

1. obtain the new clip's peak metadata;
2. resolve the per-clip normalization override;
3. calculate/apply the new normalization gain;
4. preserve the user's current volume setting.

Do not carry the preceding clip's normalization gain into the next clip.

---

# 15. Backend preview

The backend clip preview should also honor the clip's effective normalization setting when its enhanced player is active.

This is useful because administrators need to hear the same playback-level behavior that visitors will hear.

Native fallback remains unnormalized.

---

# 16. Data delivery to the player

The server-side player renderer must expose enough information to the JavaScript layer to determine normalization without additional media analysis.

Preferred data supplied to the player:

```text
clip normalization override/effective state
global_peak_dbfs
target_peak_dbfs
```

It is acceptable for PHP to resolve the effective enable/disable state and/or precompute `gain_db`/`gain_linear`, provided the architecture remains consistent and avoids duplicating policy logic across every caller.

Prefer one central server-side helper/service for:

- reading the optional global peak from waveform analysis;
- resolving component + clip normalization policy;
- calculating validated playback normalization information.

The player should not make an extra network request solely to obtain normalization metadata if that data can be included in the existing rendered player state.

---

# 17. Caching and stale data

Global peak information belongs to a specific waveform analysis generated from a specific current clip source.

Existing Audio Archive stale-analysis rules should continue to apply.

When an original clip file is replaced:

- existing waveform data should become stale/invalid according to the current analysis lifecycle;
- stale global-peak data must not be treated as authoritative for the replacement audio;
- regenerated waveform data produces the new peak.

Do not add a separate normalization cache with a lifecycle independent from waveform analysis.

---

# 18. Existing installations and old waveform files

This feature must be fully backward compatible.

After updating:

- existing clips continue playing exactly as before unless they have compatible waveform data containing the new global peak;
- old waveform analysis without `global_peak_dbfs` remains usable for waveform rendering;
- normalization silently does nothing for those clips;
- regenerating the waveform adds the global peak and makes normalization available;
- do not automatically enqueue regeneration of the entire archive merely because this feature was installed.

If the administrator wants normalization coverage for older clips, the existing **Regenerate all waveforms** maintenance function can be used.

---

# 19. Database/update work

A schema/update change is expected for the per-clip override field.

Requirements:

- fresh-install SQL contains the new field;
- update SQL/migration adds it to existing installations;
- default value is **Inherit**;
- archive export/restore includes the field if clip records/schema are included there;
- integrity/schema checks recognize the new column;
- uninstall/install behavior stays consistent with existing component policy.

Do not introduce a database column solely for `global_peak_dbfs` unless the actual corrected baseline architecture requires it.

---

# 20. Configuration and form integration

Update all relevant configuration/form definitions:

- component options XML;
- administrator clip edit form;
- frontend clip edit form where applicable;
- any clip table/model binding/validation required for the new override;
- permissions should follow the same edit permissions as the containing clip, with no separate ACL action needed.

Use standard Joomla form controls and the existing Audio Archive visual conventions.

---

# 21. English and German translations

Add complete EN/DE strings for:

- Normalize playback;
- Peak normalization level;
- Playback normalization;
- Inherit;
- Enabled;
- Disabled;
- help/description text explaining dBFS and fallback behavior.

Suggested English wording:

```text
Normalize playback
Peak normalization level
Playback normalization
Inherit
Enabled
Disabled
```

Suggested German wording:

```text
Wiedergabe normalisieren
Pegel für Spitzenwert-Normalisierung
Wiedergabe-Normalisierung
Übernehmen
Aktiviert
Deaktiviert
```

For the tri-state **Inherit**, prefer the terminology already used elsewhere in the component if a standard German translation exists there.

Help text should clarify that normalization affects playback only and does not modify original files.

---

# 22. Documentation

Update at least the relevant current files from the corrected baseline, typically:

```text
README.md
CHANGELOG.md
docs/user/configuration.md
docs/user/sound-board.md
docs/user/playlists.md
docs/developer/architecture.md
docs/developer/database-schema.md
docs/developer/testing.md
```

If waveform/analysis formats are documented elsewhere, update those documents too.

Document:

- sample peak vs LUFS/true peak;
- optional `global_peak_dbfs` waveform field;
- default target;
- global option;
- per-clip override;
- native/Web Audio fallback;
- old waveform compatibility;
- polyphonic Sound Board clipping caveat;
- original files are never modified.

---

# 23. Tests

## 23.1 Peak-analysis tests

Test at least:

1. mono signal with known peak;
2. stereo signal where left and right have different peaks;
3. stereo signal whose mono downmix would cancel or alter the peak;
4. full-scale sample;
5. signal above 0 dBFS if the decoder/source format permits floating overs;
6. digital silence;
7. malformed/failed analysis;
8. regeneration after source replacement.

Verify that the stored global peak comes from the original channels and is not merely the maximum of the downmixed waveform bins.

## 23.2 Gain calculation tests

Examples:

```text
peak -8 dBFS, target -1 dBFS -> +7 dB
peak -1 dBFS, target -1 dBFS ->  0 dB
peak  0 dBFS, target -1 dBFS -> -1 dB
peak +1 dBFS, target -1 dBFS -> -2 dB
```

Verify linear conversion.

## 23.3 Policy tests

Test every combination:

```text
Global OFF + Inherit  -> OFF
Global ON  + Inherit  -> ON
Global OFF + Enabled  -> ON
Global ON  + Enabled  -> ON
Global OFF + Disabled -> OFF
Global ON  + Disabled -> OFF
```

With missing peak data, every `ON` case must still fall back to ordinary playback rather than fail.

## 23.4 Player tests

Verify:

- positive gain works;
- attenuation works;
- volume slider remains independent;
- mute remains correct;
- pause/resume remains correct;
- seeking remains correct;
- changing playlist clips recalculates normalization;
- only one media-element source node is created per audio element;
- no duplicated/doubled audio path exists;
- native fallback still works.

## 23.5 Sound Board tests

Verify normalization in:

- pad-trigger mode;
- chromatic keyboard mode;
- monophonic mode where available;
- polyphonic mode;
- MIDI note-on/note-off;
- overlapping voices;
- recorded Sound Board playback;
- overdub;
- speed/pitch variations.

Pay special attention to Safari because recent Audio Archive development encountered a transient Safari Web Audio failure.

## 23.6 Browser coverage

At minimum test current:

- Safari/macOS;
- Chrome/macOS or Windows;
- Firefox where practical;
- mobile Safari if the Sound Board/player is supported there.

---

# 24. Explicit non-goals

Do not add the following as part of this implementation:

- destructive "Normalize file" processing;
- rewriting WAV/FLAC originals;
- re-encoding MP3/AAC/M4A originals;
- LUFS normalization;
- EBU R128;
- true-peak/dBTP analysis;
- automatic limiting;
- compressors;
- Sound Board master limiter;
- archive-wide automatic waveform regeneration;
- separate normalization-analysis jobs;
- normalization-dependent playback failures.

These can be future features if explicitly requested.

---

# 25. Implementation approach in the next conversation

When implementation begins:

1. obtain the corrected/latest source ZIP and installer ZIP from the user;
2. do **not** use the known-buggy 0.13.1 baseline;
3. inspect the actual current:
   - waveform generator and waveform schema;
   - analysis lifecycle/staleness handling;
   - shared player JavaScript;
   - volume implementation;
   - Playlist player;
   - Sound Board Web Audio graph;
   - backend preview;
   - clip form/table/model;
   - install/update SQL;
   - build/package script;
4. adapt this design to the current architecture rather than transplanting code from an older version;
5. implement the complete feature;
6. update translations and documentation;
7. run static/package/schema checks and targeted behavioral tests;
8. build both:
   - ready-to-install package ZIP;
   - complete source ZIP in the established Audio Archive project structure;
9. validate both archives before delivery.

---

# 26. Definition of done

The feature is complete when all of the following are true:

1. Waveform generation stores an optional global sample peak measured across original source channels.
2. Existing waveform files without that field remain valid.
3. Component options provide:
   - Normalize playback;
   - Peak normalization level.
4. Clips provide Inherit / Enabled / Disabled normalization behavior.
5. Effective normalization policy is resolved consistently.
6. Positive and negative normalization gain works through Web Audio.
7. User volume remains independent from normalization gain.
8. Shared players normalize when supported.
9. Playlist playback normalizes each current clip independently.
10. Sound Board pad and chromatic playback normalize individual clip voices.
11. Backend preview follows the same effective behavior.
12. Missing peak data or failed Web Audio causes ordinary playback, not an error.
13. Native `<audio>` fallback remains functional and unnormalized.
14. Original audio files are never modified.
15. Replacing a source clip cannot leave stale peak metadata active.
16. Fresh install and update install both support the per-clip override.
17. EN/DE translations are complete.
18. Documentation explains the feature and its limitations.
19. Safari/Chrome playback regressions are checked.
20. Installer and source ZIPs follow the established Audio Archive release format.

---

# 27. Short handoff summary

Implement **non-destructive sample-peak playback normalization**.

During waveform generation, calculate and store an optional `global_peak_dbfs` measured across the original audio channels before mono/downmix. Old waveform data without the field remains valid and simply cannot normalize.

Add component options:

```text
Normalize playback
Peak normalization level: -1.0 dBFS by default
```

Add a per-clip override:

```text
Inherit
Enabled
Disabled
```

At playback time:

```text
gain_db = target_peak_dbfs - global_peak_dbfs
gain_linear = 10 ^ (gain_db / 20)
```

Apply that gain through a dedicated Web Audio gain stage, separate from user volume. Use the same effective logic in the unified player, Playlist player, backend preview, and Sound Board voices. If peak metadata or Web Audio is unavailable, play normally without normalization. Never modify or transcode original audio.

**Do not use Audio Archive 0.13.1 as the baseline; use the corrected/latest version supplied in the implementation conversation.**
