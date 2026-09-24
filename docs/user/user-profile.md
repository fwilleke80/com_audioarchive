# User profiles and account storage (0.13.3)

Signed-in users always use account playlists and sound boards. Guests always use browser copies. The former component storage-policy switches and user storage choice are no longer used. When browser copies exist, the existing import button is offered. Import is explicit; successful, acknowledged imports remove the corresponding browser copies. Failed or incomplete imports remain available for recovery.

## Personal preferences

Open Joomla’s frontend **Edit profile**, then **Audio Archive**. For an existing account this section offers permitted upload defaults (visibility and category), a default sound board when boards exist, and read-only clip, collection and quota usage. These are private account settings, not a public profile. The current upload menu, category ACL and component settings always take precedence. A removed or no-longer-permitted preference falls back to permitted defaults. Validation-error submissions are preserved.

The **User – Audio Archive** plugin is enabled on its first installation, including an upgrade that adds the plugin. Later updates preserve an administrator’s decision to disable it. Disabling this plugin hides profile fields; it does not disable account collections.

## Per-user quota overrides

In the administrator, open **Users → Manage → an existing user → Audio Archive**. Administrators with user-edit permissions and **Audio Archive → Override quotas** permission can choose **Inherit**, **Custom limit** or **Unlimited**, independently for original-file storage and clip count. Storage uses MiB. Zero is a real zero limit. Inherit restores site/group rules. The relevant component quota must be enabled for a stored override to take effect. Usage includes retained/trash clips and original bytes.

Create a new Joomla account first, then edit it to set Audio Archive preferences or overrides. Registration forms are unaffected.

## Ratings

The existing Nobody / Registered users / Everyone setting and master rating switch remain authoritative. Rating buttons now load each visitor’s actual vote from the server, so signed-in users see their account vote on other browsers and devices. On the first Audio Archive page with rating controls after login, existing server-recorded ratings associated with that browser key transfer to the account if rating permissions allow. Existing account votes win conflicts; guest duplicates are removed transactionally. Repeating the transfer is harmless. Browser-local vote caches are not trusted as votes.

Guest ratings still depend on the browser key. Ratings retain the existing site-secret-based identity format; changing Joomla’s secret or restoring to a different site does not automatically remap historical voter identities.

## Deleted accounts

Clips, profiles and collections are retained for administrator recovery; nothing is automatically reassigned or deleted. Collection shares are revoked, and shared-collection access also checks that the owner account still exists. Private clip access rules remain in force.

## Upgrade checks

1. Install the complete package over 0.13.2.7 and verify that User – Audio Archive is enabled.
2. Test guest collections, then log in and import a browser playlist and board. Reload and verify the import prompt disappears when no copies remain.
3. Edit the Joomla profile, choose upload defaults, and open Create clip. Repeat with a restrictive upload menu and after removing category permission.
4. Set a small per-user quota as administrator; verify the user sees the effective usage and cannot change the quota.
5. Rate as a guest, log in and view the same clip. Test a second browser, conflicting existing account votes, clearing a vote, and each rating-permission setting.
6. Verify Integrity & Maintenance follows Quota rules.
7. Smoke-test playback and the frequency-profile display on the devices used for 0.13.2.7.

Automated checks use PHP/SQLite with Joomla adapters and JavaScript test fixtures. A live Joomla/MySQL upgrade and device-browser smoke test are still required.

### 0.13.3.1 usability update

Visibility preferences offer Site default, Public, Registered users and Private where permitted. The separate access-level preference has been removed and previously stored personal access levels are ignored for new uploads. Read-only statistics appear on the frontend profile display, but not Edit profile. Administrator usage and quota controls remain available. The Clips Owner filter displays names and usernames, including explicit deleted-user labels.

The seven read-only statistics are grouped in a separate **My Audio Archive** section. Joomla’s standard profile display renders these as plain label/value information, not editable inputs.
