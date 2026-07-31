# Installation and updates

## Requirements

Punga Audio Archive requires:

- Joomla! 6.x
- PHP 8.3 or later
- MySQL or MariaDB
- PHP Fileinfo
- Writable configured original-storage, analysis-storage, and import directories

Optional requirements:

- FFmpeg for waveform and spectral-analysis generation
- `proc_open()` for launching FFmpeg and diagnostic FFprobe checks
- PHP `ZipArchive` for archive export and restore

Core clip management, public filtering, protected playback, downloads, modules, plugins, and non-generation maintenance checks work without FFmpeg.

## Installing

1. Open **System → Install → Extensions** in Joomla Administrator.
2. Upload the versioned package, such as `pkg_audioarchive_v0-11-4.zip`.
3. Open **Components → Punga Audio Archive**.
4. Review the dashboard System Check.
5. Open **Options** and configure storage, defaults, frontend access, and processing.

The outer package installs the component, two site modules, and three plugins. Do not install the nested extension ZIPs separately unless diagnosing a development build.

## Updating

Install a newer package directly over the existing installation. Joomla schema updates preserve clips, files, categories, tags, ratings, counters, and analysis records.

Do not uninstall the package to update it. Uninstallation removes component data according to the package and configured media-retention behaviour.

After updating, clear the Joomla administrator cache when newly added fields or tabs are not visible.

## First checks

Confirm on the dashboard that:

- The database schema is current.
- Configured directories are valid and writable.
- PHP Fileinfo is available.
- FFmpeg is found when analyses are required.
- `proc_open()` is available when external processes are expected.

Then continue with [Initial configuration](configuration.md).
