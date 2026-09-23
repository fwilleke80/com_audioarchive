> Implementation status: 0.13.2 adds server collections, explicit migration and short sharing links. Collection export/restore was brought forward; dedicated user-profile integration remains scheduled for 0.13.3. See [release testing](../user/0.13.2-testing.md).

> Implementation status: 0.13.0 foundation and 0.13.1 frontend contribution built on 2026-09-19. Local policy and packaging checks pass; Joomla/MySQL staging gates remain. See [implementation notes](multi-user.md) and [frontend test guide](../user/frontend-upload.md).

# Punga Audio Archive 0.13 — Multi-User Architecture and Implementation Plan

**Project:** Punga Audio Archive for Joomla! 6  
**Baseline:** Punga Audio Archive **0.12.4**  
**Plan date:** 2026-09-19  
**Purpose:** Self-contained handoff document for implementing the 0.13 multi-user series in a new ChatGPT conversation.

---

## 1. How to use this document

Upload this document together with the latest **0.12.4 source ZIP** when starting implementation in a new conversation.

Current baseline artifact names:

```text
pkg_audioarchive_v0-12-4.zip
pkg_audioarchive_v0-12-4-source.zip
```

The source ZIP, not the installer ZIP, is the authoritative implementation baseline for development.

The implementation should use the existing 0.12.4 source as the baseline, preserve all current behavior unless explicitly changed here, and produce releases using the established project packaging conventions:

- ready-to-install Joomla package ZIP;
- complete source ZIP;
- source ZIP top-level structure:
  - `pkg_audioarchive/`
  - `README.md`
  - `docs/`
  - `LICENSE`
  - `CONTRIBUTING.md`
  - `build_package.py`
  - `CHANGELOG.md`
- do **not** add `PROJECT_SPECIFICATION.md`;
- every release gets a matching Joomla SQL schema migration or no-op schema marker;
- package manifest, component manifest, newest SQL version, README current version, and newest changelog heading must agree;
- bump affected asset versions when JavaScript/CSS changes;
- run PHP, JavaScript, XML, JSON, language, documentation-link, build, nested-ZIP and CRC checks before delivery.

If 0.12.4 testing reveals a reproducible component bug before 0.13 implementation starts, fix it first and use the resulting release as the actual baseline. Do not knowingly build 0.13 on a broken release. However, do not treat a transient Safari/WebKit Web Audio failure as an Audio Archive regression unless it reproduces after restarting Safari and can be tied to the source; see section 3.7.

---

# 2. Product direction

Punga Audio Archive has reached the point where its next major development should be **multi-user contribution and account-backed personal data**, rather than expanding the Sound Board recorder into a full sequencer.

The 0.12 recorder should be considered functionally complete for now. Future recorder work should be regression fixes and minor polish only unless explicitly requested.

The 0.13 direction is to make Audio Archive suitable for a community, household, tenant group, editorial team, or collaborative site where registered Joomla users can:

- own clips;
- upload clips from the frontend;
- see and manage their own clips;
- edit clips for which they have Joomla ACL permission;
- create private clips as well as normally visible clips;
- have storage and clip-count quotas;
- use account-backed playlists and Sound Boards across browsers/devices;
- retain current browser-local playlists/Sound Boards when appropriate, especially for guests;
- configure personal Audio Archive preferences in a dedicated Joomla user-profile section.

The implementation must remain a **Joomla-native** extension. Use Joomla users, groups, view levels, assets, categories and ACL rather than building a parallel user/role system.

---

# 3. Important facts from the 0.12.4 source audit

Do not assume these features are missing. They already exist and should be extended rather than duplicated.

## 3.1 Clip ownership already exists

`#__audioarchive_clips` already contains:

```text
created_by int unsigned NOT NULL DEFAULT 0
modified_by int unsigned NOT NULL DEFAULT 0
```

There is already an index on `created_by`.

`#__audioarchive_files` also contains `created_by`.

The existing upload service assigns `created_by` to the current Joomla user.

The administrator Clip form already has a `created_by` user field.

Therefore **do not add a second `owner_id` column**. In 0.13, `created_by` is the canonical clip owner.

`created_by = 0` means **system/unowned**. Existing rows should not be arbitrarily reassigned during migration.

## 3.2 Standard Joomla ACL is already declared

The component currently declares:

- `core.admin`
- `core.options`
- `core.manage`
- `core.create`
- `core.delete`
- `core.edit`
- `core.edit.state`
- `core.edit.own`
- `audioarchive.import`
- `audioarchive.process`
- `audioarchive.managefiles`

The category ACL section already includes:

- `core.create`
- `core.delete`
- `core.edit`
- `core.edit.state`
- `core.edit.own`

Use these standard actions wherever they already express the permission correctly.

## 3.3 Frontend editing already exists

0.12.4 already contains:

- `site/src/Controller/EditController.php`
- `site/src/Model/EditModel.php`
- `site/src/View/Edit/HtmlView.php`
- `site/src/Service/FrontendEditingService.php`
- `site/tmpl/edit/edit.php`

`FrontendEditingService::canEdit()` already implements:

- `core.edit` on the clip asset; otherwise
- owner (`created_by == current user`) + `core.edit.own`.

Do not build a second frontend editor. Refine and expose the existing implementation through the new multi-user workflows.

One current limitation is that frontend editing availability is tied to Joomla's global frontend-editing setting. Re-evaluate whether Audio Archive should continue to depend on that setting once My Clips becomes a first-class contributor workspace.

## 3.4 Current personal collections are browser-local

Playlists use:

```text
com_audioarchive.playlists.v1
```

Sound Board uses:

```text
com_audioarchive.soundboard.v1
```

Sound Board polyphony preference uses:

```text
com_audioarchive.soundboard.sampler_polyphony.v1
```

Recordings use:

```text
com_audioarchive.soundboard.recordings.v1
```

These existing browser formats are valuable and must remain backward-compatible.

## 3.5 Current public eligibility is distributed across several paths

0.12.4 currently filters publication/access in multiple places, including:

- `ArchiveModel`
- `PublicMediaService`
- `TagDirectoryService`
- `SoundboardController`
- `PlaylistController`
- Clip view/navigation
- Download/stream services
- modules
- Smart Search
- content integration
- related clips

Private clips make duplicated eligibility rules dangerous. 0.13 should introduce a central visibility/access policy and systematically use it.

## 3.6 Current Sound Board/recorder baseline in 0.12.4

The 0.12 recording work changed significantly after this plan was first written. Treat **0.12.4**, not 0.12.1, as the behavioral baseline.

Current recorder behavior:

- Sound Board recordings remain browser-local in `com_audioarchive.soundboard.recordings.v1`;
- exported recording documents still use format `punga-audioarchive-soundboard-recording`, version `1`;
- overdubs remain one flat event list rather than DAW-style tracks;
- events may carry an optional integer `layer`; legacy recordings without `layer` are layer 0;
- each overdub receives a new layer so recorded monophony can be isolated per take;
- one-level **Undo overdub** restores the exact previous event list and duration;
- the recorder section uses a stateful transport:
  - **Record** becomes **Stop recording** while recording;
  - **Play** becomes **Stop** while playback is active;
  - **Record overdub** becomes **Stop recording** while overdubbing;
- the old Instrument/pad-change lane was removed because it became misleading with overdubs;
- piano-roll note/trigger colors are assigned deterministically from the Sound Board pad so the same pad has the same color across the original take and overdubs;
- new notes are drawn in the piano roll immediately while recording/overdubbing;
- held notes grow horizontally until Note Off and then keep their final duration;
- backing note events use their recorded pad directly, so recorded `select`, `mode`, and `octave` events are metadata and do not continually force the user's live controls to follow the track;
- note release timing is recorded for the piano roll, but sampler voices remain one-shot: MIDI/computer/onscreen Note Off does not cut the playing sample.

The current recording JSON format must remain backward-compatible during 0.13 unless a separately requested migration is designed.

### 0.12.1 → 0.12.4 history that matters

The relevant release history is:

```text
0.12.1
  Added compact recorder piano roll, overdub/undo, optional event layers,
  direct recorded-pad playback, and layer-aware backing/live voice handling.

0.12.2
  Removed the Instrument lane, added deterministic per-pad piano-roll colors,
  live piano-roll drawing, and combined Record/Stop and Play/Stop transport.
  It also changed recording playback to use a separate private
  recordingPlayback.board.

0.12.3
  Attempted AudioContext/Safari hardening after a silent-chromatic report.
  That diagnosis turned out not to be the actual cause and 0.12.3 should not
  be used as a development baseline.

0.12.4
  Built from 0.12.2 and deliberately rolled the sampler/recording-playback
  integration back to the proven 0.12.1 mechanism while retaining the
  0.12.2 UI/recorder improvements.
```

In 0.12.4, the core live sampler functions were explicitly compared with the 0.12.1 source and restored to the 0.12.1 implementations, including:

```text
syncSamplerSelection()
createAudioContext()
unlockSamplerAudio()
getAudioContext()
loadSamplerBuffer()
selectSamplerPad()
setSamplerMode()
startSamplerVoice()
playSamplerNote()
playSamplerNoteFromPad()
handleMidiMessage()
play()
restoreRecordingPlaybackState()
```

Do not casually rewrite these functions as part of unrelated 0.13 work.

0.12.4 currently uses the 0.12.1-style temporary recording-board swap during recording playback and restores the preserved live board/state afterward. The temporary mode change is delayed until after asynchronous recording-board resolution so the interface should not visibly sit in Pad Trigger mode while loading.

There is one documentation cleanup to remember: the 0.12.4 source inherited wording from 0.12.2 in `docs/user/sound-board.md` and `docs/developer/soundboard-recordings.md` describing a completely private backing-playback board that never replaces the live in-memory board. That wording no longer precisely matches the 0.12.4 rollback implementation and should be corrected in the next documentation pass.

## 3.7 Safari/WebKit transient Web Audio failure observed on 2026-09-19

A confusing test incident occurred immediately before this handoff and must not be rediscovered as a false source-code regression.

Observed behavior in the latest Safari on the latest macOS:

- Pad Trigger mode still played normally;
- Chromatic Keyboard mode became completely silent;
- chromatic recording playback moved the playhead but produced no audio;
- the same failure persisted after reinstalling **0.12.1**, even though 0.12.1 had worked earlier in the same session;
- **0.12.4 worked correctly in Chrome**, including Chromatic Keyboard mode and recording;
- quitting and restarting Safari immediately restored Chromatic Keyboard playback.

This strongly identifies the incident as a transient Safari/WebKit Web Audio state failure rather than an Audio Archive version regression.

The distinction matters because:

```text
Pad Trigger             → ordinary HTMLAudioElement playback
Chromatic Keyboard      → Web Audio API / AudioContext / AudioBufferSourceNode
```

If Safari suddenly shows the same symptom again:

1. first test the same build in Chrome or another browser;
2. quit Safari completely and reopen it;
3. retest before changing Audio Archive sampler code;
4. if an old known-good Audio Archive release is also silent in the same Safari session, assume browser/Web Audio state until proven otherwise.

Do not reintroduce the speculative 0.12.3 AudioContext changes solely because of this transient Safari behavior. Add WebKit-specific recovery logic only if there is a reproducible case that survives a Safari restart or can otherwise be isolated reliably.

---

# 4. Core design principles

## 4.1 Ownership is data; ACL is permission

Owning a clip must not automatically mean having every action on it.

Use:

```text
created_by
```

for ownership, and Joomla ACL for what that owner may do.

Examples:

- `core.edit.own` permits editing one's own clip;
- `core.edit` permits editing a clip regardless of ownership when the asset rules allow it;
- `core.edit.state` controls publishing/state changes;
- `core.delete` controls deletion;
- `core.create` controls creation in a component/category.

Ownership is never a substitute for ACL.

## 4.2 Privacy is separate from Joomla Access Level

The existing `access` field answers:

> Which Joomla View Levels may normally view this clip?

It does **not** cleanly answer:

> Is this clip visible only to its individual owner?

Add an independent privacy/visibility mode.

Recommended field:

```sql
visibility_mode varchar(16) NOT NULL DEFAULT 'normal'
```

Initial values:

```text
normal
private
```

Avoid naming the first value `public`, because a normal clip may still use an Access Level such as Registered or Mieter.

Semantics:

### `normal`

Use the existing Joomla rules:

- clip state/publication dates;
- clip Access Level;
- category state/access/ancestor eligibility;
- normal frontend route rules.

### `private`

The clip is not discoverable to normal visitors, even if its Access Level is Public.

It may be viewed by:

- its owner; and
- users explicitly authorised to manage private Audio Archive content.

Private visibility is an **additional restriction**, not a replacement for Joomla ACL.

Do not create one Joomla View Level per user.

## 4.3 Do not leak private metadata

Privacy enforcement must cover more than the Archive table.

A user who may not view a private clip must not learn its:

- title;
- description;
- filename;
- UUID;
- tags;
- audio URL;
- waveform/analysis data;
- rating;
- related-clip presence;
- collection membership details.

Generic placeholders such as “Unavailable clip” are acceptable in the owner’s own collection when an item becomes inaccessible.

## 4.4 Server storage and browser storage are backends, not separate products

The existing playlist/Sound Board UI and interchange formats should remain.

Introduce storage abstractions such as:

```text
BrowserPlaylistStore
ServerPlaylistStore

BrowserSoundboardStore
ServerSoundboardStore
```

UI code should use a common conceptual interface instead of scattering:

```text
if logged in && server storage ...
```

throughout `playlist.js` and `social.js`.

## 4.5 Never silently merge account and browser collections

When a registered user changes to server-backed storage and browser-local data exists, offer an explicit import/copy operation.

Do not continuously synchronize localStorage and the server.

This avoids conflict semantics across multiple devices.

---

# 5. ACL design

Keep the existing Joomla standard actions and add only permissions that represent genuinely different capabilities.

Recommended new actions:

```text
audioarchive.manage.private
audioarchive.change.owner
audioarchive.quota.override
```

Do **not** add dozens of narrow permissions.

## 5.1 `audioarchive.manage.private`

Purpose:

- view private clips owned by other users where the administrator has delegated this capability;
- moderate/edit them when the user also has the appropriate edit permissions.

Declare this at least for component and category assets if Joomla's asset inheritance model can cleanly support category-scoped private moderation.

Super Users/core administrators naturally remain privileged.

This action does not by itself grant edit/delete/state changes. Those still require the corresponding standard ACL action.

## 5.2 `audioarchive.change.owner`

Purpose:

- change `created_by`;
- batch-transfer clip ownership;
- perform ownership reassignment without granting full Joomla User management.

The current administrator model ties editing `created_by` to `core.manage` on `com_users`. Replace that coarse requirement with this Audio Archive-specific action, while still allowing Super Users.

Ordinary frontend users can never change ownership.

## 5.3 `audioarchive.quota.override`

Purpose:

- permit privileged operations that would leave a target owner above quota;
- allow administrative restoration/import/ownership transfer when appropriate.

The UI must still show a clear warning before deliberately creating an over-quota state.

This permission should not silently bypass unrelated validation such as file type/size/security checks.

## 5.4 Existing permissions

Continue using:

- `core.create` for clip creation/upload;
- `core.edit` and `core.edit.own` for editing;
- `core.edit.state` for publication state;
- `core.delete` for deletion;
- `audioarchive.managefiles` for existing file-management operations;
- existing import/process actions for their current purposes.

Do not add a redundant `audioarchive.upload` permission unless implementation reveals a concrete requirement that `core.create` cannot express.

---

# 6. Central clip access and visibility service

This is the most important architectural refactor in 0.13.

Create a central service responsible for determining whether a clip is eligible in a given context. Exact class naming may follow current conventions, for example:

```text
ClipAccessService
ClipVisibilityService
```

The service should expose both:

1. per-record decisions; and
2. query-building helpers so lists can filter efficiently in SQL rather than loading everything and filtering in PHP.

Conceptual responsibilities:

```text
canViewPublic()
canViewAsOwner()
canEdit()
canEditState()
canDelete()
canManagePrivate()
applyPublicVisibilityFilter(query, alias, user)
applyOwnerWorkspaceFilter(...)
```

Avoid creating a service that becomes an unstructured collection of arbitrary SQL fragments. Keep public visibility rules well-defined.

## 6.1 Public visibility

For ordinary Archive/module/search/related/tag counts, an item should normally require:

- published/current state;
- publish-up/down eligibility;
- clip Access Level permitted;
- category and ancestors published/access permitted;
- `visibility_mode = normal`.

Private items should be included only when the current user is the owner or has `audioarchive.manage.private`, depending on the view/context.

## 6.2 Direct Clip Detail/media access

Direct URLs must use the same policy.

Important cases:

- owner opening own private clip;
- authorised moderator opening another user's private clip;
- ordinary user guessing a private clip URL;
- stream/download endpoint called directly;
- waveform/spectrogram/frequency-profile endpoint called directly;
- playlist/Sound Board route resolution;
- recording playback resolution.

Do not protect the HTML page while leaving media endpoints open.

## 6.3 Unpublished owner preview

My Clips needs a sensible way to work with unpublished submissions.

Recommended rule:

A logged-in owner may preview their own unpublished clip if they still have `core.edit.own` for it. Users with `core.edit` may similarly preview eligible unpublished content.

This preview privilege must be explicit in the access service and must not make unpublished content appear in public Archive/search/module queries.

---

# 7. Ownership UX

Because `created_by` already exists, 0.13 primarily needs to expose and consistently use it.

## 7.1 Administrator Clips list

Add:

- optional Owner column;
- Owner filter;
- Owner sort;
- batch ownership transfer for users with `audioarchive.change.owner`.

Use Joomla user's display name (`name`) in normal presentation. Do not expose email addresses.

If an owner account no longer exists, display a clear fallback such as:

```text
Unknown user (#123)
```

or:

```text
System / unowned
```

for `created_by = 0`.

## 7.2 Administrator Clip editor

Owner field:

- visible/editable only when authorised;
- ordinary editors see owner read-only or hidden;
- changing owner must trigger quota impact calculation before save.

## 7.3 Frontend Archive

Add configurable:

```text
archive_column_owner
archive_show_owner_filter
```

Both should be globally configurable and menu-item-overridable, consistent with existing Archive options.

Default them conservatively (probably hidden/off) because publicly exposing contributor names is a site-policy choice.

If owner filtering is exposed publicly, filter by user ID internally, display name in UI, and never expose email.

## 7.4 Clip Detail

Add optional:

```text
detail_show_owner
```

Again default off unless product direction changes.

A future owner-profile link may be added later; 0.13 does not require public user-profile pages.

---

# 8. Private clips

## 8.1 Database

Add to `#__audioarchive_clips`:

```sql
visibility_mode varchar(16) NOT NULL DEFAULT 'normal'
```

Add an index suitable for public/owner filtering, for example:

```text
visibility_mode
created_by + visibility_mode
```

Choose indexes after examining final query shapes.

Existing clips migrate to:

```text
normal
```

## 8.2 Forms

Administrator Clip form:

```text
Visibility
  Normal — use Joomla Access Level
  Private — owner and authorised private-content managers only
```

Frontend Upload/Edit:

- show Private only if component/menu policy allows users to create private clips;
- owner may not use visibility selection to bypass publication/moderation rules;
- if private mode is disallowed, force `normal`.

The Joomla `access` value should remain stored even when private so switching back to normal restores the intended Access Level.

## 8.3 Private clips and search/indexing

Joomla Smart Search is not user-specific enough for owner-only results.

Therefore:

- do not index private clips;
- when a normal clip becomes private, remove/unpublish its Finder record;
- when a private clip becomes normal and otherwise eligible, reindex it.

Likewise private clips must not contribute to public Tag Directory counts or Related Clips ranking for users who cannot see them.

## 8.4 Private clips in personal collections

A user may add a private clip they can view to their own server playlist/Sound Board.

If access later disappears:

- keep the collection item row;
- return only an unavailable placeholder;
- do not expose private metadata;
- do not play it;
- allow the owner of the collection to remove the placeholder.

This preserves collection structure without leaking content.

---

# 9. Quota system

Quotas are required, not optional polish.

Support two independent quota dimensions:

```text
Maximum original-audio storage
Maximum clip count
```

Each can be unlimited.

## 9.1 What counts toward storage quota

Count the stored **original audio file** owned by the clip owner.

Do not charge users for server-generated artifacts such as:

- waveform data;
- spectrograms;
- frequency profiles;
- generated previews/analysis artifacts.

The user controls the original upload; the administrator controls derived processing.

Count original files regardless of normal/unpublished/private/trashed state while the original is still retained.

Permanent deletion frees the quota.

`created_by = 0` is system/unowned and is not assigned to an ordinary user's quota.

The authoritative fast calculation should come from database file metadata:

```text
#__audioarchive_files.file_role = 'original'
#__audioarchive_files.file_size
joined through clip ownership
```

Integrity/maintenance tools should keep the database file record consistent with physical storage.

## 9.2 Replacement semantics

Replacing an existing original must use **delta accounting**:

```text
required additional quota =
max(0, new_original_size - current_original_size)
```

A user replacing 30 MB with 42 MB needs 12 MB free, not 42 MB.

Replacing with a smaller file should always be possible from the quota perspective.

## 9.3 Clip-count semantics

Count all retained clip rows owned by the user, including trashed clips until permanently deleted.

This mirrors the principle that Trash is recoverable storage, not deletion.

## 9.4 Quota hierarchy

Resolve quota in this order:

1. per-user override;
2. applicable Joomla user-group quota rules;
3. component-wide default.

For users in multiple groups:

- use the **largest applicable quota**;
- if any applicable group rule is Unlimited, effective quota is Unlimited.

This matches Joomla's additive group model: membership in a privileged contributor group should grant more capacity rather than being cancelled by ordinary Registered membership.

## 9.5 Global defaults

Component options should include a dedicated **Users & Quotas** section with at least:

```text
Enable storage quotas                     Yes/No
Default storage quota                    [value] [MB/GB] / Unlimited
Enable clip-count quotas                  Yes/No
Default maximum clips                    [value] / Unlimited
Allow users to create private clips       Yes/No
```

Exact UI may use separate enabled/unlimited toggles rather than magic numeric values.

## 9.6 Group quota rules

Do not try to encode an arbitrary group→quota map into static component XML fields.

Create a proper administrator **Quota Rules** view/page.

Each rule:

```text
Joomla user group
Storage quota: inherit / custom / unlimited
Clip-count quota: inherit / custom / unlimited
```

Suggested table:

```sql
#__audioarchive_group_quotas
    id
    group_id
    storage_quota_bytes   signed bigint NULL
    clip_quota            signed int NULL
    created
    modified
```

Semantics:

```text
NULL = no rule for this dimension / inherit
-1   = unlimited
>=0  = exact limit
```

Use a unique key on `group_id`.

## 9.7 Per-user overrides

Store Audio Archive user preferences/overrides in a component-owned table, not Joomla Custom Fields.

Suggested:

```sql
#__audioarchive_user_profiles
    user_id                       PRIMARY KEY
    collection_storage_preference
    default_visibility
    default_category_id
    default_access_id
    browser_import_prompt
    default_soundboard_id
    storage_quota_override_bytes
    clip_quota_override
    created
    modified
```

Quota override semantics:

```text
NULL = use group/default resolution
-1   = unlimited
>=0  = exact limit
```

Only authorised administrators may edit quota override fields.

## 9.8 Quota service

Create one server-side service, e.g.:

```text
UserQuotaService
```

Responsibilities:

```text
getUsage(userId)
getEffectiveQuota(userId)
canAddBytes(userId, bytes)
canCreateClip(userId)
getReplacementDelta(...)
describeEffectiveSource(...)
```

Do not duplicate quota math in controllers.

Client-side size checks are UX only. Every operation must be checked server-side.

## 9.9 Operations that must enforce quota

At minimum:

- frontend upload;
- backend upload when creating content for a quota-limited owner;
- bulk upload where applicable;
- directory import when ownership is assigned;
- replacement;
- ownership transfer.

Privileged import/restore/ownership transfer may exceed quota only with `audioarchive.quota.override`.

Archive restore should not silently fail halfway because a restored user exceeds a newly configured quota. A privileged restore should be able to restore the data and leave the user visibly over quota.

## 9.10 Over-quota behavior

If quota is lowered or ownership is transferred such that a user is already over quota:

- keep all existing clips accessible according to normal ACL;
- do not delete/disable content;
- block operations that increase usage;
- allow operations that reduce usage;
- show the effective over-quota state in My Clips/profile/admin UI.

## 9.11 User-facing quota display

My Clips should optionally show:

```text
Storage
684 MB of 1 GB used

Clips
83 of 250
```

Use an accessible progress presentation.

The profile section should show the same values read-only for ordinary users.

---

# 10. Frontend Upload menu item

Add a first-class Joomla menu item:

```text
Punga Audio Archive → Upload Clip
```

This should reuse/refactor the existing `AudioUploadService`; do not create a second independent upload pipeline.

## 10.1 Authentication and ACL

Guests cannot upload.

The current user must have:

```text
core.create
```

for the selected/fixed Audio Archive category according to normal Joomla inheritance.

Quota must also permit the upload.

## 10.2 Upload form

Initial scope:

- one clip per submission;
- drag/drop or file chooser;
- title;
- description;
- category if menu policy allows selection;
- tags;
- Joomla Custom Fields where appropriate;
- visibility mode;
- Access Level if the site/menu allows the user to select it;
- recording date/other existing metadata that makes sense for frontend contributors.

Do not expose system/internal technical fields.

Bulk contributor upload can be a later feature.

## 10.3 Menu-item options

Recommended menu options:

```text
Intro text

Category mode
  Fixed category
  User chooses from permitted categories

Fixed category
Allowed categories (optional restriction)

Default visibility
  Use user preference
  Normal
  Private

Allow user to choose visibility
  Yes/No

Default Access Level
Allowed Access Levels for frontend uploads

Default publication state
  Unpublished
  Published (only effective for users with core.edit.state)

Allow user to choose publication state
  Yes/No, still ACL-gated

Redirect after save
  New Clip Detail
  My Clips
  Stay on Upload
```

The form must still perform server-side ACL validation even when a menu option hides/fixes a field.

## 10.4 Access Level safety

Do not simply show every Joomla View Level to every frontend contributor.

Joomla View Levels are not themselves “assign permissions.”

Provide an administrator-configured set of Access Levels allowed for frontend uploads, globally and/or menu-item-restricted.

If only one is permitted, hide the field and apply it.

This prevents an ordinary contributor from assigning an inappropriate View Level.

## 10.5 Publication/moderation

If the uploader lacks `core.edit.state`:

- force the new clip to Unpublished;
- do not trust submitted state;
- show a useful message such as “Your clip was uploaded and is awaiting publication.”

If the user has `core.edit.state`, the configured/menu-allowed state controls may be shown.

This naturally creates a moderated-submission workflow using Joomla ACL.

## 10.6 Tags

Allow selecting tags the user is permitted to use.

Do not accidentally let the frontend tag field create arbitrary Joomla tags unless the user has the appropriate Joomla tag-creation permission and the site explicitly wants that behavior.

Default safe behavior: select existing eligible tags.

## 10.7 Processing

After a successful upload, use the existing processing queue exactly as backend upload does:

- metadata;
- waveform;
- spectral analysis;
- frequency profile;

according to component configuration.

No separate frontend processing implementation.

---

# 11. My Clips menu item

Add:

```text
Punga Audio Archive → My Clips
```

This is a dedicated owner workspace, not merely an Archive menu item with a hidden owner filter.

Guests should receive Joomla's normal login/authentication behavior or a controlled “login required” response.

## 11.1 Contents

List clips where:

```text
created_by = current user ID
```

Do not list other users' clips merely because the current user is an editor. This view is literally **My Clips**.

Recommended columns:

```text
Title
Category
Publication status
Visibility
Access Level
Duration
Uploaded date
Original file size
Processing/analysis status
Actions
```

Optional/menu-configurable columns can follow existing Archive UI patterns.

## 11.2 Actions

Per row, based on actual ACL:

- View/Preview;
- Edit;
- Delete if permitted;
- possibly Replace Original later if explicitly exposed and appropriately permission-gated.

Provide a prominent **Upload Clip** action when a suitable Upload menu item/route exists and the user has create permission.

## 11.3 Filters

Useful initial filters:

- text;
- category;
- publication status;
- normal/private visibility;
- processing state;
- ordering.

## 11.4 Quota summary

Display effective quota/usage near the top when quotas are enabled.

If over quota, state clearly that existing content remains available but new/increasing uploads are blocked.

## 11.5 Unpublished/private previews

My Clips must be able to open an owner-authorised preview/detail page for eligible private and unpublished content.

Do not make public Archive queries show these items in order to achieve preview.

---

# 12. Frontend editing refinement

Reuse the existing frontend editor.

Required 0.13 work:

- ensure `core.edit.own` behavior is consistently based on `created_by`;
- integrate visibility field;
- constrain Access Level choices according to frontend policy;
- constrain category moves to categories where the user is allowed to create/edit;
- prevent publication-state changes without `core.edit.state`;
- never expose owner reassignment;
- ensure private/unpublished owner preview and return URLs work from My Clips;
- ensure Custom Fields continue to work.

Decide whether Audio Archive frontend editing should remain dependent on Joomla's global frontend-editing switch. A dedicated My Clips workflow may justify an Audio Archive component option instead. Whatever decision is made, document it and avoid two contradictory switches.

---

# 13. Server-backed playlists

Logged-in users should be able to store playlists in the database so they follow the account across devices.

Existing guest/browser playlists remain supported according to storage policy.

## 13.1 Suggested schema

```sql
#__audioarchive_playlists
    id
    uuid
    user_id
    title
    created
    modified
    params

#__audioarchive_playlist_items
    id
    playlist_id
    clip_id
    ordering
```

Indexes/constraints:

```text
unique playlist UUID
index user_id
index playlist_id
unique (playlist_id, ordering) or robust equivalent
index clip_id
```

Use local `clip_id` for efficient local relations.

For JSON export/import and archive portability, serialize clip **UUIDs**, not database IDs.

## 13.2 Ownership/security

A server playlist is personal to `user_id`.

Every CRUD endpoint must enforce:

```text
playlist.user_id == current user.id
```

Do not trust a playlist/user ID supplied by JavaScript.

When adding a clip, server-side verify that the current user may currently view that clip.

## 13.3 Existing functionality must remain

Preserve:

- playlist creation;
- ordering;
- rename/delete;
- import/export;
- share links;
- playlist player;
- Sound Board ↔ playlist conversion.

The storage backend should be transparent to the main UI.

## 13.4 Public server playlist sharing

Not required for initial 0.13.

Current JSON/URL sharing remains.

Server-side public/shared playlists can be a future feature.

---

# 14. Server-backed Sound Boards

Server storage should allow **multiple named Sound Boards** for a logged-in user.

The existing browser Sound Board remains available according to policy and retains its current JSON/share behavior.

## 14.1 Suggested schema

```sql
#__audioarchive_soundboards
    id
    uuid
    user_id
    title
    pad_count
    created
    modified
    params

#__audioarchive_soundboard_items
    id
    soundboard_id
    pad_index
    clip_id
```

Constraints:

```text
unique board UUID
index user_id
unique (soundboard_id, pad_index)
index clip_id
```

`pad_count` records the board shape at save time but must be safely reconciled with current component/menu pad limits when loaded.

## 14.2 Multiple boards

A logged-in server-storage user should be able to:

- select board;
- create new board;
- rename board;
- delete board;
- choose/set a default board.

Suggested first server board name:

```text
Default
```

Create lazily on first save rather than creating rows for every Joomla user during installation.

## 14.3 Default/active board

Store a user's preferred/default server Sound Board in the Audio Archive user-profile table.

If it is missing/deleted/inaccessible, fall back to the first board or lazily create Default.

## 14.4 Sharing/export

Existing Sound Board JSON and URL-fragment sharing remains backend-independent.

Exported board entries continue to use stable clip identity.

A shared board must never overwrite the recipient's server board automatically.

---

# 15. Collection storage policy

Add a dedicated component section, for example:

```text
Personal Collections
```

Recommended setting:

```text
Collection storage for registered users

  Browser only
  Server
  User choice
```

If `User choice`, add:

```text
Default when user has no preference

  Server
  Browser
```

Guest setting:

```text
Allow browser-stored collections for guests
  Yes/No
```

This covers the required policy without forcing one backend.

## 15.1 Effective backend rules

### Guest

If guest browser collections are allowed:

```text
Playlists   → browser
Sound Board → browser
```

Otherwise personal playlist/Sound Board persistence is disabled for guests.

### Logged-in user, Browser only

```text
Playlists   → browser
Sound Board → browser
```

### Logged-in user, Server

```text
Playlists   → server
Sound Board → server
```

### Logged-in user, User choice

Use the user's Audio Archive profile preference.

If unset, use the component default.

## 15.2 Recordings

Sound Board recordings remain browser-local in the initial 0.13 scope.

Once the playlist/Sound Board persistence abstraction is stable, server-stored recordings are an obvious future extension but are **not required for 0.13**.

---

# 16. Browser-to-server migration UX

When effective storage changes from browser to server and browser-local data exists:

Do not silently merge.

Offer explicit actions such as:

```text
Browser playlists found
[Import to my account] [Not now]

Browser Sound Board found
[Save as server Sound Board] [Not now]
```

For playlists:

- import as new playlists;
- resolve clips using stable UUIDs/current access;
- report unavailable items.

For Sound Board:

- import as a new named board rather than silently replacing Default.

The browser copy should remain until the user explicitly chooses to clear it.

Do not implement bidirectional live synchronization.

---

# 17. User-profile integration

Add a packaged Joomla user plugin, suggested name:

```text
plg_user_audioarchive
```

It should integrate a dedicated **Punga Audio Archive** fieldset/section into supported Joomla user profile/edit forms using the current Joomla 6 user-profile extension mechanism.

Add the plugin as a nested extension in `pkg_audioarchive.xml`, build packaging and translations.

The component owns the data in `#__audioarchive_user_profiles`; the plugin supplies profile-form integration.

## 17.1 Ordinary user fields

Show only choices the site permits.

Candidate fields:

```text
Personal collection storage
  Browser / Server
  (only editable when component policy = User choice)

Default new-clip visibility
  Use site default / Normal / Private
  (Private only if site policy permits)

Default upload category
  optional, validated at upload time against current core.create permission

Default Access Level
  optional, restricted to frontend-allowed levels

Prompt to import browser collections
  Yes/No

Default Sound Board
  server boards owned by current user
```

Do not expose meaningless fields when the component policy fixes the value.

## 17.2 Read-only account information

Show:

```text
Owned clips
Private clips
Storage used / effective quota
Clip count / effective quota
Playlist count
Sound Board count
```

This is useful in both frontend profile and administrator user edit.

## 17.3 Administrator-only user fields

When an authorised administrator edits the user, additionally show:

```text
Storage quota override
  Use group/default
  Custom
  Unlimited

Clip-count quota override
  Use group/default
  Custom
  Unlimited

Effective storage quota
Effective clip quota
Effective quota source
Current usage
```

Ordinary users must never be able to submit quota overrides through manipulated form data.

Server-side save validation is mandatory.

---

# 18. Server collection API/controller design

Implement authenticated Joomla component endpoints rather than direct generic database access from JavaScript.

Conceptual operations:

## Playlists

```text
list
get
create
rename
delete
add clip
remove clip
reorder
replace contents/import
```

## Sound Boards

```text
list
get
create
rename
delete
set default
set pad
clear pad
replace/import board
```

Requirements:

- authentication;
- CSRF token for mutations;
- owner check on every collection;
- clip-visibility check on every added/resolved clip;
- input limits;
- predictable JSON responses;
- no disclosure of inaccessible clip metadata.

Avoid one endpoint that accepts arbitrary “operation” blobs without typed validation.

---

# 19. Collection frontend storage abstraction

Refactor carefully rather than rewriting all playlist/Sound Board logic.

Suggested conceptual JavaScript interface:

```text
load()
save()
list()
create()
rename()
delete()
```

as appropriate to each collection type.

The server adapter is asynchronous, while localStorage is currently synchronous. Therefore the UI layer should become promise/async friendly rather than pretending both backends are synchronous.

Important:

- keep current browser JSON format compatible;
- do not duplicate all UI logic for server mode;
- show useful busy/error states for network operations;
- server mutation failure must not optimistically destroy the visible collection state.

---

# 20. Clip deletion and ownership interactions with collections

Permanent clip deletion must handle server collection references.

Recommended behavior:

- delete collection-item rows referencing the permanently deleted clip; or
- preserve a generic missing placeholder only if there is a strong restoration reason.

For normal permanent deletion, cleaning references is simpler and prevents indefinite dangling database rows.

Trash should **not** permanently delete collection references. A trashed/private/inaccessible clip may temporarily resolve as unavailable.

Ownership transfer of a clip does not automatically transfer playlists/Sound Boards containing it.

Collection membership is independent from clip ownership.

---

# 21. Archive Export / Restore

0.13 adds persistent user-specific data. Complete archive export/restore must not silently omit it.

At minimum update portable archive support for:

- clip `visibility_mode`;
- server playlists and items;
- server Sound Boards and items;
- Audio Archive user-profile preferences/overrides;
- group quota rules if feasible/portable.

## 21.1 Clip references

Export collection item references using clip UUIDs.

On restore, resolve UUID → restored clip ID.

## 21.2 User references

The existing archive code already maps `created_by` using usernames.

Use a similarly explicit user mapping strategy for:

- playlist owner;
- Sound Board owner;
- user profile rows.

If a user cannot be resolved, report it rather than assigning data to the wrong account.

## 21.3 Group quota rules

Joomla group numeric IDs are not portable.

If group quota rules are included, export enough identity to map groups safely (for example group title plus parent/path context). If reliable mapping cannot be guaranteed, skip/review unresolved rules and report them clearly.

Do not map a quota rule to a different group merely because an ID happens to match.

---

# 22. Administrator UI additions

Suggested administrator navigation changes:

```text
Dashboard
Clips
Upload
Bulk Upload
Directory Import
...
Quota Rules
...
Options
```

Exact placement should match the existing admin sidebar structure.

## Dashboard

Potential additions, kept compact:

- users over quota;
- unpublished frontend submissions awaiting review;
- total registered-user original storage.

Do not turn the dashboard into a huge user-management page.

## Clips list

Add:

- Owner column/filter/sort;
- Visibility column/filter;
- My/owner-related batch operations where appropriate;
- ownership transfer action gated by `audioarchive.change.owner`.

## Quota Rules

Dedicated page for Joomla user-group quota rules.

Clearly show:

```text
Group
Storage quota
Clip quota
```

with inherit/unlimited/custom semantics.

---

# 23. Frontend navigation/menu-item types

By the end of the 0.13 series, add menu item types:

```text
Archive                     existing
Playlists                   existing
Sound Board                 existing
Tag Directory               existing

Upload Clip                 new
My Clips                    new
```

Do not require a single monolithic “My Audio” dashboard.

A site owner should be able to place Upload, My Clips, Playlists and Sound Board independently in Joomla menus.

A future optional “My Audio” dashboard can aggregate them later.

---

# 24. Privacy and security audit checklist

This work is security-sensitive. Before calling 0.13 complete, audit all relevant surfaces.

Private/unpublished/inaccessible clips must be tested against:

- Archive list;
- Archive counts/pagination;
- direct Clip Detail URL;
- direct stream route;
- direct download route;
- waveform;
- spectrogram;
- frequency profile;
- Related Clips;
- Previous/Next;
- Tag Directory counts;
- tags;
- modules;
- content plugin;
- Smart Search;
- Sound Board add/resolve/playback;
- playlist add/resolve/playback;
- Sound Board recording embedded-board resolution;
- ratings;
- share actions;
- frontend edit;
- My Clips;
- upload;
- server collection APIs;
- frontend AJAX routes;
- export functions;
- Punga Analytics event recording.

Also test:

- guessed numeric IDs;
- guessed UUIDs;
- stale localStorage IDs;
- stale server collection references;
- changed ownership;
- deleted Joomla users;
- category permission changes;
- group membership changes;
- CSRF failures;
- quota race conditions;
- concurrent uploads.

---

# 25. Quota race/concurrency safety

A simple “check then upload” can allow two simultaneous requests to exceed quota.

For frontend contributor uploads, introduce a server-side strategy robust enough for realistic concurrency.

Possible approaches:

- database transaction/locking around the final quota check and clip/file-row commit;
- a per-user quota reservation table;
- another deterministic locking mechanism suitable for Joomla/MySQL.

At minimum, perform the authoritative quota check immediately before committing the database/file ownership change.

Do not rely on the browser's preflight calculation.

If the service cannot safely reserve before writing the physical file, temporary disk use may exceed quota during transfer, but the completed owned state must not exceed quota unless the actor has override permission.

---

# 26. Ownership transfer rules

When changing owner:

1. calculate the original file's quota contribution;
2. calculate target user's current usage/effective quota;
3. warn if transfer causes over-quota;
4. block unless actor has `audioarchive.quota.override`;
5. update `created_by`;
6. preserve `modified_by` as the actor making the transfer;
7. do not silently rewrite original creation timestamps;
8. do not transfer personal playlists/Sound Boards.

If the source user was over quota, transfer immediately reduces their usage.

---

# 27. Deleted Joomla users

Define deterministic behavior when a Joomla account is deleted while Audio Archive data remains.

Recommended:

- do not automatically delete owned clips;
- keep `created_by` numeric value for historical attribution until an administrator reassigns it;
- treat the clip as having an unavailable/unknown owner for UI;
- private clips with a deleted owner are visible only to authorised private-content managers;
- server playlists/Sound Boards owned by the deleted account should be retained initially for administrator recovery/cleanup, but normal users cannot access them;
- provide maintenance/reporting later if needed.

Do not silently reassign deleted-user data to the current administrator.

---

# 28. User deletion / cleanup policy

The user plugin must **not** automatically erase Audio Archive content just because a Joomla user account is deleted unless a future explicit component option is designed for that.

Audio is archival content; destructive cascading user deletion would be dangerous.

If the Joomla user plugin receives deletion events, safe behavior is:

- preserve clips;
- preserve collections/profile data or mark them orphaned;
- optionally remove purely nonessential preferences only if ownership/recovery is unaffected.

Prefer recoverability over automatic destruction.

---

# 29. Suggested database changes

Exact SQL should be reviewed during implementation, but the expected new structures are approximately:

```text
#__audioarchive_clips
    + visibility_mode

#__audioarchive_group_quotas

#__audioarchive_user_profiles

#__audioarchive_playlists
#__audioarchive_playlist_items

#__audioarchive_soundboards
#__audioarchive_soundboard_items
```

Existing `created_by` remains the owner field.

Do not add redundant owner columns.

Update:

- install SQL;
- update SQL migrations;
- uninstall SQL;
- archive export/restore;
- maintenance/integrity checks where relevant.

No database foreign keys are required if that is inconsistent with the existing Joomla/component style; enforce referential cleanup in application code and indexes.

---

# 30. Suggested 0.13.x implementation sequence

Do not implement all of this as one unreviewable patch.

## 0.13.0 — Ownership/visibility/ACL/quota foundation

Implement:

- central clip access/visibility service;
- `visibility_mode`;
- private clip policy;
- new ACL actions:
  - `audioarchive.manage.private`
  - `audioarchive.change.owner`
  - `audioarchive.quota.override`
- Owner/Visibility backend columns and filters;
- ownership-transfer rules;
- global quota options;
- group quota table/admin view;
- user profile data table;
- quota service and usage calculations;
- integrate quota checks into existing upload/replacement/ownership paths;
- audit direct media access;
- Smart Search/private exclusion;
- documentation/tests.

This release should make the underlying policy correct before adding contributor UI.

## 0.13.1 — Frontend contribution

Implement:

- Upload Clip menu item;
- My Clips menu item;
- quota display in My Clips;
- moderated upload flow;
- private/normal visibility frontend controls;
- frontend Access Level restrictions;
- refine existing frontend edit workflow;
- owner preview of eligible unpublished/private clips;
- optional Owner column/filter/detail metadata in public Archive;
- full frontend ACL testing.

## 0.13.2 — Server playlists and Sound Boards

Implement:

- server playlist tables/API/store;
- server Sound Board tables/API/store;
- multiple named server Sound Boards;
- default board;
- browser/server storage adapters;
- collection storage component policy;
- guest browser-storage policy;
- explicit browser→server migration UX;
- preserve all current JSON/export/share behavior;
- server collection security/access-resolution tests.

## 0.13.3 — User profile integration and portability/polish

Implement/package:

- `plg_user_audioarchive`;
- dedicated Punga Audio Archive user-profile section;
- personal storage preference for User Choice mode;
- default visibility/category/access settings;
- quota readout;
- administrator quota overrides;
- default Sound Board preference;
- archive export/restore coverage for new persistent data;
- orphan/deleted-user handling;
- dashboard moderation/quota attention items if useful;
- final documentation and comprehensive regression pass.

The exact patch numbering can change if defects require hotfix versions, but preserve this dependency order.

---

# 31. Migration/backward compatibility

## Existing clips

- keep current `created_by`;
- `0` stays system/unowned;
- `visibility_mode` defaults to `normal`;
- no existing clip should disappear after update.

## Existing browser playlists

Remain valid and untouched.

When server storage is active, offer explicit import.

## Existing browser Sound Board

Remain valid and untouched.

Offer explicit conversion to a named server board.

## Existing Sound Board recordings

Remain browser-local and backward-compatible.

Current recording documents are format version 1 and may contain optional `layer` values added for overdubs. Missing `layer` means layer 0. Do not force a recording-format bump merely because 0.13 adds account-backed playlists/Sound Boards.

Their embedded Sound Board resolution must respect the new private-clip access policy. Integrate that policy into the existing recording-board resolution path without leaking metadata for newly inaccessible/private clips.

Remember that 0.12.4 restored the 0.12.1-style temporary recording-board playback mechanism. Do not accidentally reintroduce the abandoned 0.12.2 `recordingPlayback.board` architecture while implementing private-clip resolution unless a new design is explicitly requested and tested.

## Existing shared playlist/Sound Board JSON/URLs

Must keep working.

Do not require server storage to receive a shared collection.

---

# 32. UI terminology

Use consistent terminology.

Recommended English:

```text
Owner
Visibility
Normal
Private
My Clips
Upload Clip
Personal Collections
Collection storage
Browser
Server
Quota
Storage quota
Clip quota
Quota Rules
Use group/default quota
Unlimited
```

Recommended conceptual German terminology should be natural rather than literal; preserve the component's existing German tone and capitalization.

Avoid calling Joomla `access` “privacy.”

The form should visually distinguish:

```text
Visibility: Private/Normal
Access: Public/Registered/Mieter/...
Publication status: Published/Unpublished/Trashed
```

These are separate concepts.

---

# 33. Important user journeys

Implementation is not complete until these flows are coherent.

## Guest

1. Opens Archive.
2. Sees only public/eligible normal clips.
3. Cannot discover private clips.
4. May use browser playlist/Sound Board if guest storage is enabled.
5. Cannot upload or open My Clips.

## Registered contributor

1. Logs in.
2. Opens Upload Clip.
3. Sees only permitted categories/options.
4. Upload is prechecked against quota.
5. If lacking `core.edit.state`, submission is forced Unpublished.
6. Clip owner is automatically current user.
7. Opens My Clips and sees submission.
8. Can edit it using `core.edit.own`.
9. Can preview own eligible unpublished/private content.
10. When published by moderator, it appears normally according to visibility/access.

## User with private clip

1. Creates/edits clip as Private when policy permits.
2. Sees it in My Clips.
3. Can play it directly.
4. Other ordinary users cannot find or stream it.
5. Public Smart Search/Tag Directory/Related Clips do not leak it.
6. User may add it to their own server playlist/Sound Board.

## Moderator

1. Has appropriate category/core edit permissions and private-management permission.
2. Can see submissions/private items in administrative workflow.
3. Can publish where `core.edit.state` permits.
4. Cannot change owner unless also given `audioarchive.change.owner`.

## Quota-limited user

1. Sees usage in My Clips/profile.
2. Upload larger than remaining quota is rejected before transfer when size is known.
3. Server checks again authoritatively.
4. Replacing with a smaller file works.
5. Replacing 30 MB with 42 MB requires 12 MB free.
6. Trash does not free quota.
7. Permanent deletion does.

## Logged-in server-collection user

1. Playlists/Sound Boards load from account.
2. Another browser shows the same server collections.
3. Local guest collections do not silently overwrite account data.
4. User may explicitly import browser collections.
5. Multiple named Sound Boards are available.

## Playback while access changes

1. A clip in a server playlist becomes private/inaccessible.
2. Collection remains structurally valid.
3. UI shows an unavailable item/pad without leaking metadata.
4. Playback skips it.
5. If access is restored, it resolves again if the row was intentionally preserved.

---

# 34. Tests required before each release

In addition to the project's existing test checklist:

## ACL matrix

Test at least:

```text
Guest
Registered without create/edit
Owner + core.edit.own
Editor + core.edit
Publisher + core.edit.state
Private-content moderator
Owner-changing administrator
Quota-override administrator
Super User
```

Test permissions at both component and category levels.

## Visibility matrix

Cross:

```text
normal/private
published/unpublished/trashed
owner/non-owner/private-manager
Public/Registered/custom Access Level
accessible/inaccessible category
```

Do not test only happy paths.

## Quotas

Test:

- exact limit;
- one byte over;
- unlimited;
- group rule;
- multiple groups;
- user override;
- over-quota after admin change;
- replacement larger/smaller;
- Trash/permanent delete;
- owner transfer;
- simultaneous upload attempts;
- privileged override.

## Server collections

Test:

- two users cannot access each other's collection IDs;
- guest cannot call server mutation endpoints;
- CSRF failures;
- inaccessible clip addition;
- clip becomes inaccessible after being stored;
- deleted clip;
- browser/server switch;
- migration/import;
- multiple Sound Boards;
- default board deletion/fallback;
- JSON import/export;
- existing shared URL behavior.

## User profile

Test:

- frontend profile;
- administrator user edit;
- user cannot forge quota override fields;
- defaults invalidated by changed category/access permissions fall back safely;
- storage preference only editable in User Choice mode.

## Existing Sound Board and recorder regression

For every 0.13 release that touches frontend access, collection resolution, Sound Board code, routing, or `social.js`, smoke-test the existing 0.12.4 behavior:

- Pad Trigger playback;
- Chromatic Keyboard playback with onscreen keys;
- computer-keyboard chromatic playback;
- MIDI chromatic playback where available;
- mono/polyphony behavior;
- switching sampler pads while in Chromatic Keyboard mode;
- fresh recording;
- live piano-roll note drawing;
- per-pad piano-roll colors;
- playback of Pad Trigger recordings;
- playback of Chromatic Keyboard recordings;
- Record overdub and manual/automatic stop;
- Undo overdub;
- legacy version-1 recording JSON without `layer`;
- current version-1 recording JSON with multiple layers;
- Record ↔ Stop recording and Play ↔ Stop button state transitions.

When diagnosing silent Chromatic Keyboard audio in Safari, use the section 3.7 procedure before editing sampler code. A Safari session in which both the current release and 0.12.1 are silent, while Chrome works, is not evidence of a new Audio Archive regression.

---

# 35. Performance considerations

Do not add per-row user/quota/access queries in loops.

Use joins/batched lookup for:

- owner names;
- server collection clip resolution;
- My Clips;
- quota usage.

Quota usage should be calculable with indexed aggregate queries.

Add appropriate indexes for:

- owner/visibility;
- group quota group ID;
- user profile user ID;
- playlist user ID/items;
- Sound Board user ID/items.

Private visibility filtering must remain SQL-level for Archive pagination/count correctness.

---

# 36. Logging and error handling

Use normal Joomla messages/errors and the component's existing style.

Do not expose internal filesystem paths, SQL, or private metadata to frontend users.

Useful explicit errors:

```text
You do not have permission to upload to this category.
Your storage quota has been reached.
Your clip quota has been reached.
This clip is private.
This collection no longer exists.
One or more collection clips are no longer available.
```

For private inaccessible direct URLs, prefer the same non-disclosing not-found/access behavior used elsewhere rather than confirming the private clip exists.

---

# 37. Documentation to update

At minimum:

```text
README.md
CHANGELOG.md
docs/user/configuration.md
docs/user/installation.md if package contents change
docs/user/playlists.md
docs/user/sound-board.md
new docs/user/my-clips.md
new docs/user/frontend-upload.md
new docs/user/user-profile.md
docs/developer/database-schema.md
docs/developer/frontend-state.md
docs/developer/architecture.md
docs/developer/testing.md
docs/developer/package-and-installer.md
docs/developer/build-and-release.md if new plugin changes build behavior
new developer ACL/visibility/quota documentation
```

Document that `created_by` is ownership.

Document browser/server collection precedence precisely.

Document private vs Access Level vs publication state.

---

# 38. Package/build implications

Adding `plg_user_audioarchive` requires:

- plugin source tree;
- manifest;
- EN/DE translations;
- package manifest nested plugin entry;
- build script inclusion;
- package installer/update handling;
- enabled-state behavior consistent with existing packaged plugins;
- source ZIP documentation;
- CRC validation of the new nested ZIP.

Update package-content tables in README/documentation.

---

# 39. Explicit non-goals for initial 0.13

Avoid scope explosion.

Not required unless separately requested:

- turning Sound Board recordings into a full DAW;
- server-stored recordings;
- collaborative multi-owner clips;
- public user profile pages;
- social following/friends;
- comments;
- direct messages;
- public server-playlist directory;
- public server-Sound-Board directory;
- real-time multi-device synchronization;
- contributor bulk upload;
- per-user filesystem directories as a security model;
- automatic deletion of user content when Joomla accounts are deleted;
- one Joomla View Level per user.

The architecture should not prevent reasonable future additions, but do not implement them preemptively.

---

# 40. Definition of done for the 0.13 series

The 0.13 multi-user work is complete when all of the following are true:

1. `created_by` is consistently treated as clip ownership.
2. Public/private visibility is centrally and securely enforced.
3. Standard Joomla `core.edit.own`/category ACL works predictably.
4. Frontend upload exists and reuses the existing upload/processing pipeline.
5. My Clips provides a coherent owner workspace.
6. Quotas support global defaults, user-group defaults and per-user overrides.
7. Quotas correctly account for original audio, replacements, Trash and ownership transfers.
8. Logged-in users can use server-backed playlists and multiple named Sound Boards.
9. Guest/browser collection behavior remains available according to configuration.
10. Browser→server collection migration is explicit rather than automatic.
11. A dedicated Punga Audio Archive user-profile section exists.
12. Private data does not leak through media endpoints, search, modules, tags, related clips, collections or recordings.
13. Archive export/restore and uninstall/install SQL cover the new persistent structures.
14. English and German UI/documentation are complete.
15. Fresh install and update from the previous release both work.
16. Installer and source ZIPs follow the established Audio Archive release format.
17. Existing 0.12.4 Archive/player/Sound Board/recording behavior has no regressions.

---

# 41. Implementation philosophy for the next conversation

Before editing code:

1. inspect the supplied 0.12.4 source rather than assuming this document perfectly describes every implementation detail;
2. treat `pkg_audioarchive_v0-12-4-source.zip` as the authoritative baseline and do not accidentally continue from the abandoned 0.12.3 experiment;
3. smoke-test Pad Trigger and Chromatic Keyboard playback before touching unrelated multi-user code;
4. if Safari alone becomes silent, apply the section 3.7 browser-restart/cross-browser diagnostic before changing sampler code;
5. map every existing visibility/access path;
6. design the central access service and quota service first;
7. design SQL migrations before frontend UI;
8. keep migrations backward-compatible;
9. implement in small release-sized steps;
10. validate each step before proceeding.

Do not respond to implementation requests with design suggestions only. When the user says to implement a milestone, make the actual source changes, build the installer/source ZIPs, validate them, and provide download links.

The priority order is:

```text
Correct access/security
→ correct ownership/quota semantics
→ contributor UX
→ server collection persistence
→ profile/polish
```

Do not trade security correctness for UI speed.

---

# 42. Short handoff summary

Punga Audio Archive 0.12.4 already has:

- a `created_by` owner field;
- Joomla clip/category ACL including `core.edit.own`;
- frontend clip editing;
- browser-local playlists;
- browser-local Sound Board;
- browser-local Sound Board recordings with overdub/undo and backward-compatible event layers;
- compact per-pad-colored piano-roll visualization with live note drawing;
- stateful Record/Stop, Play/Stop and Overdub/Stop transport controls;
- working Pad Trigger and Chromatic Keyboard modes in 0.12.4; a transient Safari Web Audio failure was confirmed to clear by restarting Safari and was not a release regression;
- protected media routing;
- archive export/restore;
- processing queues;
- current share/import/export behavior.

0.13 should turn those pieces into a coherent multi-user system by adding:

```text
central clip visibility/access policy
private clips
owner UI/filtering
quota service + group/user quotas
Frontend Upload menu item
My Clips menu item
server playlists
multiple server Sound Boards
browser/server storage policy
explicit browser→server import
Punga Audio Archive user-profile plugin/section
portable backup/restore support
comprehensive privacy/ACL testing
```

The implementation must preserve the existing guest/browser workflows and existing 0.12 functionality while making logged-in user workflows substantially more capable.
