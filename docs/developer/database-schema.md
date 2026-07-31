# Database schema

Punga Audio Archive 0.11.4 uses these component tables:

- `#__audioarchive_clips`
- `#__audioarchive_files`
- `#__audioarchive_waveforms`
- `#__audioarchive_analyses`
- `#__audioarchive_jobs`
- `#__audioarchive_ratings`

Joomla core tables provide categories, tags and tag mappings, assets and ACL, access levels, users, and Custom Fields.

## Clips

The clips table stores public metadata, publication and access fields, duration and dates, stable UUID, aggregate counters, analysis status, technical metadata, and Joomla bookkeeping fields.

## Files

The files table records managed original and optional legacy preview variants, including storage key, extension, MIME type, container, codec, size, duration, checksum, availability, and processing diagnostics.

## Analyses

Waveform data has a compatibility-specific waveform table, while the generic analyses table stores typed derived analysis records such as spectral data. The implementation shares repository and job services across analysis types.

## Jobs

Jobs store type, state, priority, attempts, payload, errors, timestamps, and processing locks. Publication state remains separate from technical job state.

## Ratings

The ratings table stores Like/Dislike choices according to configured guest or registered-user behaviour.

## Schema changes

Use Joomla schema updates rather than editing only the initial install SQL. Update code must preserve existing clips, managed-file references, UUIDs, tag relationships, counters, ratings, and derivatives.
