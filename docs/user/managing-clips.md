# Managing clips

Open **Components → Punga Audio Archive → Clips**.

## Clip records

A clip can contain:

- Title and alias
- Description
- Category and Joomla tags
- Recording date
- Access level and publication state/dates
- Original audio file and extracted technical metadata
- Play, download, and rating data
- Waveform and spectral-analysis status

## Creating one clip

Select **New**, upload the original, and complete the metadata form. Audio information such as duration, codec, container, file size, embedded title, and recording date is extracted where available.

If waveform or spectral-analysis generation is enabled globally, the corresponding jobs are queued automatically after the original is stored.

## Editing and replacing

Editing metadata does not rename or move the managed original. Replacing the original preserves the clip ID, route, title, alias, category, tags, counters, and access settings unless explicitly edited.

A replacement recalculates technical metadata and checksum, marks existing derivatives stale, and queues every globally enabled analysis.

## Batch editing

Select clips and choose **Batch** to:

- Move clips to another category
- Add tags
- Replace tags
- Clear tags

The dialog provides explicit Apply and Cancel actions. Permanent deletion is available only while viewing trashed clips.

## Publication and access

The component uses Joomla states: Published, Unpublished, Archived, and Trashed. Frontend eligibility also depends on publication dates, clip and category access levels, category state, and the component-wide frontend access setting.

## Frontend editing

When Joomla frontend editing is enabled and the current user has suitable `core.edit` or `core.edit.own` permission, Clip Detail pages show **Edit clip**.

The frontend form supports public metadata and, with `core.edit.state`, publication fields. Original replacement and analysis controls remain administrator-only.
