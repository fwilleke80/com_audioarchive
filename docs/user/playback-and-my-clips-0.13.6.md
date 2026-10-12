# Playback and My clips — 0.13.6

In Component Options, find **Pause button behaviour**. The default **Pause and resume** preserves position. **Stop and rewind** changes the playing control to a stop square and resets the clip to zero when pressed. This applies to all enhanced player presentations. Native browser controls used when JavaScript is unavailable retain native pause behaviour.

Archive, My clips and Playlists menu items can override the component setting. The archive menu setting also applies to clip detail/edit pages using that menu context. The Audio Archive module and content plugin have their own Use global / Pause and resume / Stop and rewind settings.

For individual content embeds, use `{audioarchive clip=123 pause=stop}` or `{audioarchive clip=123 pause=pause}`. An explicit embed value overrides the plugin setting; inheriting resolves to the component default.

Featured players now have **Rewind to start**. It seeks to zero without changing whether playback is running or paused. Player handovers, seeking and internal pauses do not trigger the Stop setting.

The frontend **Edit Clip** page includes the shared featured player and whichever waveform/spectrum/frequency-profile data are available. Existing media access rules still apply, including private/unpublished owner access; unavailable or trashed clips do not get a playable preview.

**My clips** defaults to **Not trashed**. Choose **Status → Trashed** to see trashed clips. **Restore (unpublished)** is shown when the owner has permission to change the clip state. It restores as unpublished rather than making a clip public immediately. Permanent deletion retains its separate permission and confirmation.

Suggested site checks: compare Pause/Stop in archive rows and featured players; test a module override and an embedded clip override; rewind while playing and paused; trash a clip, select Trashed, restore it, and verify it is unpublished in the normal list. Check the featured editor player for a private clip as its owner.

Since 0.13.6.1, Featured presentation always uses Play/Pause and a separate Rewind button. Other presentations follow the configured Pause button behaviour.
