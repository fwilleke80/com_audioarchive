# Template overrides and styling

## Unified player override

A site template can replace the shared player markup at:

```text
templates/<template>/html/layouts/com_audioarchive/player/unified.php
```

The override is used by Archive rows/cards, Related Clips, Clip Detail pages, modules, content embeds, and playlist-row controls. The administrator preview continues to use the bundled backend rendering path.

Preserve essential `data-audioarchive-*` attributes and classes expected by the bundled JavaScript.

## Component templates and layouts

Joomla template overrides can also target component views and layouts under the usual `templates/<template>/html/com_audioarchive/` and `templates/<template>/html/layouts/com_audioarchive/` paths.

Keep business logic out of overrides. Models and services should prepare data and access decisions before rendering.

## CSS customisation

Visual changes can target player classes such as:

```text
.audioarchive-custom-player
.audioarchive-custom-player-toggle
.audioarchive-custom-player-seek
.audioarchive-custom-player-times
.audioarchive-custom-player-volume-controls
.audioarchive-custom-player-analysis
```

The built-in stylesheet exposes CSS custom properties derived from component settings. Prefer those or scoped template CSS for colour and spacing changes before replacing structural markup.
