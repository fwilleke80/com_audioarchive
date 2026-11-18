# Recorder editing (0.16.0)

Select a recording in the Sound Board recorder. Outside Edit mode, click the timeline or piano-roll lanes to position the playhead. Play starts from that position. You can also seek during ordinary playback. Seeking stops existing voices and schedules events from the new position; samples triggered before it are not reconstructed. Overdub recording still begins at the start, and seeking is disabled while recording.

## Edit mode

Choose Edit mode to open a draft. Playback, recording, import and other recording mutations are disabled while editing; zoom remains available.

- Click an event to audition its own recorded clip, pitch and velocity. Pad triggers use the original pitch. Audition uses the stored layer mapping, not the visible live board.
- Drag horizontally to change time. Drag a chromatic note vertically to change its MIDI pitch, or a pad trigger onto another available pad row in its own layer. Moves retain layer, velocity and note-marker length.
- Choose a Layer for newly created events. Choose Source pad for new chromatic notes. Double-click empty space in a note or pad lane to create an event. Pad rows determine the pad directly. New note markers are 250 ms long with velocity 127; playback retains the existing one-shot sampler behaviour.
- Double-click an event to delete it. Single-click audition is briefly delayed to distinguish it from deletion.
- Undo edit reverses up to 100 edits in the current editing session.
- Keyboard: focus an event with Tab; Enter or Space auditions it, Delete/Backspace removes it, and left/right arrows move it by 10 ms.

Save changes persists the draft to the account or browser store. Cancel discards it. A failed save keeps the draft available for retry. Leaving the page with unsaved changes triggers the browser's warning. Changes are not automatically saved when switching zoom or source selectors.

The roll adds surrounding chromatic pitches and available pad rows in Edit mode, including after deleting all events. Event tooltips identify the layer and clip. Saved polyphony/control events are retained and are not directly editable in this version. New events inherit their layer's recorded polyphony at their position.

Edits can extend the timeline up to the existing two-hour limit. Saving extends its end for known sample tails, accounting for chromatic playback speed. This prevents an added final trigger being stopped immediately. A sample cannot extend beyond the two-hour limit. Deleting events does not automatically trim the recording.

Mixed pad/chromatic recordings, JSON import/export and account storage continue to use recording format version 1; no migration is required.

## Verification

Automated fixtures exercise edit bounds, stored layer mappings, seek state, undo, failed-save retention, cancel, audition selection, pointer movement, double-click deletion and production roll generation. Browser interaction and audible Safari/iPhone playback require testing on a running Joomla installation.
