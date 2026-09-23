# Ownership, visibility and quotas — 0.13.1

Frontend Upload Clip and My Clips are available. See the [frontend setup and test guide](frontend-upload.md). Account-backed collections and Joomla profile forms remain scheduled for 0.13.2–0.13.3.

## Ownership and visibility

Clips use the existing Created By field as their owner. Existing owners are preserved and old clips remain Normal. Zero means System / unowned; deleted users retain their numeric attribution.

In the administrator Clip editor, choose Normal (existing Joomla Access Level rules) or Private (owner or an authorised private-content manager). Publication state, Access Level and Visibility are separate settings. Owners need normal edit-own permission to edit or preview unpublished clips. A private-content manager still needs edit/state/delete permission for those actions. Trash cannot be previewed.

Private clips are deliberately excluded from public Archive/module/tag/related discovery for everyone. Authorised owners and managers can open them directly. The backend Clips table provides Owner and Visibility filters. Owner reassignment and batch transfer require Change clip ownership. Category-scoped private moderation is supported. Whole-archive maintenance/inspection and full export/restore require component-wide private management; restore also requires ownership-change permission.

## Quotas

Options → Users & Quotas enables original-audio storage and/or clip-count limits. Defaults are Unlimited. Storage limits use MB (1,048,576 bytes). Zero is a valid custom limit. Quota Rules manages group rules; submitting replaces both dimensions of that group's rule. Use site default clears that dimension's group rule.

Per-user overrides take precedence over group rules; among groups, the largest applicable rule wins and Unlimited wins over custom limits. Inherit is ignored when another applicable group supplies a rule. With no group rule, the site default applies. The profile data table supports user overrides; profile editing UI arrives in 0.13.3.

All retained clip rows, including Trash, count. All original-file metadata sizes count, including missing/unavailable originals; generated previews and analyses do not. Permanent deletion releases usage. System/unowned clips are not charged to an ordinary user. Replacing 30 MB with 42 MB requires 12 MB additional capacity. Smaller replacements and metadata-only edits remain possible while over quota.

Quota checks cover ordinary and bulk upload, directory import, original replacement, owner transfer and portable restore. Owner changes are serialized using database advisory locks. Privileged users must explicitly tick the override warning for an operation to exceed quota; permission alone does not bypass it. Existing content is never automatically removed by quota changes.

## Backup and compatibility

Exports retain visibility and include group quota rules/profile data. Restore quota configuration using the existing Restore configuration choice. Group identities use full hierarchy keys and user identities use usernames; unresolved quota/profile identities are skipped with warnings. Unknown clip owners restore as system/unowned, preserving private visibility.

Browser playlists, Sound Boards, recordings and recorder JavaScript remain unchanged. No browser data is migrated in this release. Audio Archive Options → Frontend contribution now has its own Enable frontend editing switch. Joomla’s global frontend-editing switch is no longer used for Audio Archive.

## Installation check

Use a staging copy before updating the live archive. Verify private URLs as guest/owner/moderator, normal archive results, quota-limited uploads and replacements, a simultaneous-upload test, batch ownership transfer, and backup/restore. The package has local syntax and policy tests; a complete Joomla/MySQL integration run was not available during this build.
