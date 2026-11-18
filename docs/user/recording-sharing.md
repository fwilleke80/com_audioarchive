# Sharing recordings (0.17.0)

Save a recording to your account, then select it in the Sound Board recorder and choose **Actions → Share recording**. A short link appears in a selected, read-only text field. The browser also attempts to copy it; if clipboard access is unavailable, copy the selected text yourself.

Only account-stored recordings can be shared. Browser-local recordings must first be imported into your account. Finish Edit mode with Save changes or Cancel before sharing. The link refers to the saved recording and follows later saved edits; playback previews never publish an unsaved draft.

Anyone with the link can open the recording without signing in. The shared page shows the title, piano roll, Play/Stop and zoom. Click the roll to seek. It has no editing or overdub controls and does not replace the visitor's own sound board or recordings. It does not autoplay.

## Clip visibility

The public response resolves the base board and every overdub layer against current guest access rules. Only published clips accessible to guests, including their category hierarchy and publication dates, are included. Private, registered-only, deleted or otherwise unavailable clips are omitted without their titles, UUIDs or file identities. Their slots remain empty, and events using those slots are skipped. A notice explains when some clips are unavailable. Public clips continue to play using their recorded pitch and layer polyphony.

Clip streaming retains its existing access checks. Sharing a recording never grants extra clip permissions. The guest projection is also used when an owner or administrator opens the shared link.

## Managing links

**Actions → Disable share link** revokes the selected recording's link. Sharing it again creates a new token; the previous link remains invalid. Repeatedly copying an active share keeps its URL stable. Deleting the recording removes its share, so importing another recording with the same ID does not revive the link.

Component Options → Users & Quotas → Personal Collections → sharing controls these links as well as collection sharing. Disabling sharing prevents creation and public retrieval; owners may still revoke existing links. Re-enabling sharing restores links that were not revoked.

Deleted accounts cannot serve shared recordings. Account-deletion cleanup revokes links while retaining archival recording data. Restoring recordings from a portable archive also revokes affected owners' recording shares. Portable exports omit the live tokens.

Revocation prevents future retrieval. It cannot recall already loaded public audio or data from a visitor's browser. Reload the shared page to pick up saved edits and current availability.

The public viewer requires the Sound Board component feature to be enabled. A dedicated Sound Board menu item is not required; links also work with Joomla installations in a subdirectory.
