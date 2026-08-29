# Waveforms, spectrograms, and frequency profiles

Punga Audio Archive can generate protected waveform data, spectrogram images, and averaged frequency-profile data with FFmpeg.

## Requirements

- FFmpeg must be available and executable.
- PHP must be able to launch external processes through `proc_open()`.
- Analysis storage must be writable.

FFprobe is detected for diagnostics but is not required for production metadata extraction.

## Waveforms

Waveforms are compact minimum/maximum peak data generated from mono PCM. Available detail levels are 256, 512, 1024, 2048, and 4096 peak pairs.

The Featured player uses waveform data for played/unplayed colouring, a moving playhead, and click-to-seek interaction.

## Spectral analysis

Spectral analysis is stored as a protected PNG spectrogram with time horizontally and frequency vertically. Configurable parameters include output detail, intensity scale, frequency scale, start and stop frequency, and dynamic range.

The Featured player adds a playback-position marker and click-to-seek interaction.

## Frequency profiles

A Frequency Profile is an averaged spectrum of the entire clip rather than a time-based spectrogram. It is stored as protected JSON containing normalised frequency bins, peak frequency, and spectral centroid.

Configurable detail levels provide 96, 192, 384, or 512 stored bins. The frequency axis can use linear spacing for measurement-oriented material or logarithmic spacing for speech and music. Minimum/maximum frequency and dynamic range are configurable.

The Featured player draws the profile as a responsive canvas curve with configurable background, fill, line, and grid colours. The Frequency Profile selector is offered only when profile data exists.

## Automatic queueing

When an analysis type is enabled globally, its job is queued automatically after:

- Single clip creation
- Browser bulk upload
- Directory import
- Individual file replacement
- Bulk replacement

The upload or import request does not synchronously generate the derivative.

## Manual and bulk actions

The Clip editor can generate or regenerate one clip. The maintenance page can queue missing or stale analyses, retry failures, regenerate all eligible clips, or delete all data of one analysis type.

Continue with [Integrity and maintenance](maintenance.md) for queue processing.
