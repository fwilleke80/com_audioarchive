# Sound Board

Create a menu item of type **Punga Audio Archive → Sound Board**.

The Sound Board stores pad assignments in the current browser. It does not require user accounts or server-side personal storage.

## Features

- Configurable pad count
- Keyboard shortcuts
- Add and remove clips
- Direct Clip Detail links
- JSON export and import
- Copyable and natively shareable links
- Temporary shared boards
- Explicit merge or replacement of a received board
- Optional polyphonic playback

Shared links do not overwrite the visitor's personal board automatically.

## Configuration

Global Sound Board settings have a dedicated **Sound Boards** tab. A menu item can override introductory text, pad count, play recording, and polyphony. Optional pad colours can be configured globally.

## Playback and analytics

The Sound Board uses its own direct-audio voice system so several clips can overlap when polyphony is enabled.

When **Record Sound Board plays** and aggregate play counting are enabled, every successful pad trigger increments the clip and dispatches the configured analytics events, including repeated and overlapping triggers.
