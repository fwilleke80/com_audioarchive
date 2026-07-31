# Analyses and processing jobs

Administrator analysis services live under:

```text
com_audioarchive/administrator/src/Service/Analysis/
```

The subsystem includes generator interfaces/results, repository access, job management, waveform generation, and spectrogram generation.

## Job lifecycle

Jobs are database-backed and use states such as pending, running, completed, failed, and cancelled. They record attempts, errors, timestamps, and processing locks.

Duplicate pending or running jobs for the same clip and analysis operation are skipped.

## Automatic queueing

Storage paths that create or replace an original call the shared queueing logic. Enabled analysis types are therefore queued consistently across single save, bulk upload, directory import, and replacement workflows.

## Processing

The maintenance controller processes jobs incrementally through AJAX requests. Each invocation is bounded to avoid request timeouts. Interrupted running jobs become recoverable after their lock expires.

## Waveforms

`WaveformGeneratorService` decodes the first stream to mono PCM and produces normalised peak pairs.

## Spectra

`SpectrogramGeneratorService` invokes FFmpeg with configured output dimensions, intensity/frequency scales, frequency range, and dynamic range.

## Failure behaviour

A failed derivative leaves the original intact, records diagnostics, and can be retried. Publication and ordinary playback remain available.
