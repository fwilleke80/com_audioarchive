# Frontend contribution — 0.13.1

## Set up the contributor pages

1. Install `pkg_audioarchive_v0-13-1.zip` over the existing installation using Joomla **System → Install → Extensions**. Keep the component installed; do not uninstall to update.
2. Open **Components → Punga Audio Archive → Options → Frontend contribution**. Enable frontend upload and frontend editing. Choose the categories and Access Levels contributors may assign. Empty category restrictions mean all otherwise permitted categories; an empty global Access Level list disables uploading. Enable private clips in the visibility options if you want to test private submissions.
3. Create two published menu items: **Punga Audio Archive → Upload Clip** and **Punga Audio Archive → My Clips**. Give them the Registered Access Level. On Upload Clip, choose a fixed category or allowed category list, allowed Access Levels, visibility choices/default and redirect destination. Menu restrictions can narrow global settings; they cannot widen them. A published Upload Clip menu is required even for a direct submission.
4. Create an ordinary Joomla user in Registered, or a dedicated contributor group. In the test category's Permissions, allow **Create** and **Edit Own** for that group. Leave **Edit State**, **Delete**, **Edit**, **Change clip ownership**, **Manage private clips**, and **Override quota limits** ungranted. No administrator login or file-management permission is needed. Joomla inherited Denied permissions still apply.
5. Sign in on the frontend as that user, open Upload Clip, choose a small supported audio file, enter a title and submit. New content is owned by that signed-in user. Without Edit State it is unpublished and displays an awaiting-publication message. My Clips lists it and its usage; Edit Own permits its direct preview and metadata editing.

Audio Archive's own frontend-editing option replaces its dependency on Joomla's global editing switch. Existing tags may be selected; arbitrary tag creation is deliberately unavailable. Original replacement remains an administrator operation. Normal/private visibility is independent of publication state and Access Level.

## Test the quota through the actual frontend

Use a fresh ordinary contributor account. Set **Options → Users & Quotas → Clip quota** to enabled, Custom, **1**. Ensure no applicable group rule supplies Unlimited or a higher limit, and no user override replaces the default. My Clips shows the effective limit.

- Upload one clip successfully. My Clips should show **1 / 1**.
- Submit a second valid audio file through Upload Clip. It must report that quota would be exceeded and leave only one retained clip.
- For storage testing, disable the clip-count limit, enable Custom storage quota and set a small limit (for example **1 MB**). Submit a valid audio file larger than the remaining storage allowance, while below the component and PHP per-request limits. It must be rejected without consuming additional usage.
- Lower the storage quota below existing usage. My Clips must show an over-quota message; existing clips remain accessible and metadata edits still work.
- Attempt two uploads together from separate tabs when only one clip slot remains. At most one should succeed. This is an integration check for the real MySQL/MariaDB owner locks.

Trash still consumes quota. To reclaim it, an administrator may permanently delete the test clip; do not grant broad publication/deletion powers merely to test quotas. The frontend has no quota override control.

## Check moderation, privacy and isolation

- Upload Normal and Private clips. Neither unpublished submission should enter public Archive/module/tag/Finder discovery. Private clips remain absent from discovery even after publication.
- Copy an eligible private or unpublished preview URL. The owner with Edit Own can preview; a second ordinary user and a guest must not receive that clip's metadata/audio through the link or direct media endpoint.
- Sign in as a second contributor. My Clips must show only their own clips. Changing URL owner/ID filters cannot expose the first user's workspace or permit editing/deletion.
- Publish a Normal submission as an administrator and verify normal public archive/playback behavior. Verify restricted category ancestors and Access Levels still apply to ordinary viewing.
- Edit an owned clip and use Save, Apply and Cancel. Return navigation should lead back to My Clips. Without Edit State, state/schedule fields are unavailable; forged state values must not publish a submission.
- If an appropriately authorized contributor has Edit State, test Trash. If they also have Delete, permanent deletion is offered only for trashed clips and requires confirmation.
- Enable the optional Owner column/filter/detail settings. Public owner choices must come from eligible public clips only. These settings default to off.
- Check Custom Fields, form errors, pagination/filters, German labels, SEF routing, logout and CSRF rejection on your site. Smoke-test existing players, playlists, Sound Boards and recorder behavior.

Local validation covers PHP syntax, XML/JSON/JavaScript/languages, packaging and 56 production-policy assertions. It does not replace the above Joomla 6 + MySQL/MariaDB browser tests; no complete Joomla runtime was available for this build.
