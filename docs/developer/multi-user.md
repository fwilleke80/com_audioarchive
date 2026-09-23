# 0.13.1.1 implementation and validation

## Policy and storage

`ClipAccessService` supplies discovery SQL and direct-record decisions. `PublicMediaService` applies direct authorization before returning metadata, tags, media or analysis. Discovery callers invoke the shared filter in addition to existing menu/language restrictions. Direct preview requires edit/edit-own and privacy access; discovery never includes preview records. Category state and ancestor/access checks remain mandatory for ordinary direct viewing.

`visibility_mode` defaults to `normal`. `created_by` remains canonical. New tables: `#__audioarchive_group_quotas` and `#__audioarchive_user_profiles`. Migration `0.13.0.sql`, installation schema, uninstall schema and installer diagnostics are aligned.

`UserQuotaService` resolves quotas and counts database original sizes plus retained clip rows. MySQL/MariaDB named owner locks are acquired in sorted order and released in finally blocks. Existing original/file transactions commit before upload wrapper locks release; batch transfers and portable restore hold outer owner locks through their transactions. Clip creation reserves count as a retained row before attaching the original. Failed original attachment removes a newly created incomplete clip through the existing cleanup path. Concurrent reassignment is detected and rejected for retry.

Do not bypass the table/upload services when adding contributor APIs. Future writers must use the same locks. Archive restore locks existing users and historical clip owners; configuration changes may leave users over quota by design. MySQL/MariaDB with GET_LOCK support is required. No destructive automatic cleanup is performed.

Private Finder records are removed during indexing. The model also suppresses private Joomla UCM publication after saves/state changes, and restore does so after tag reconstruction. Whole-archive backup/restore requires global private-management permission to prevent a scoped editor from obtaining all private audio.

## Executable tests

Run `php docs/developer/tests/multi-user-policy.php` from the source root. It executes production service code using a minimal Joomla adapter and a real SQLite database. Coverage includes guest/owner/editor/moderator visibility, unpublished preview, trash/scheduled/category/ancestor exclusion, discovery SQL, quota hierarchy, original-only accounting, exact-limit acceptance, over-limit rejection, explicit override warnings and finally-based lock release.

The lock adapter checks lifecycle only; it does not replace a concurrent MySQL integration test. Local PHP lint uses PHP 8.5 WebAssembly. JavaScript syntax, XML/JSON/language integrity, documentation links, package structure, nested archives and CRC are also checked during packaging. Only the two administrator upload/import scripts changed, to submit explicit quota confirmation; their asset versions were bumped. Recorder/player JavaScript and all CSS are unchanged.

## Staging gates

Still required on Joomla 6 + MySQL/MariaDB: install/update migration, backend and frontend rendering, current Joomla event/ACL integration, CSRF rejection, concurrent uploads/replacements/transfers, private generic Tags and Finder results, and portable restore with changed users/groups. Check media endpoints after logout and permission changes. This build has not been run inside a full Joomla installation.

## Next milestones

0.13.1 implements Upload Clip, My Clips and frontend restrictions; 0.13.2 adds account collections and explicit import; 0.13.3 adds profile integration and final portability/polish. Preserve the 0.12.4 recorder mechanism and existing localStorage formats.

## Frontend contribution policy

`ContributionService` resolves an authorized Upload Clip menu and intersects its category/access restrictions with component options. It validates ownership, Create/Edit Own, publication state, category moves, visibility and existing tags. New submissions without Edit State are unpublished regardless of submitted values or menu defaults. Existing state/schedule is preserved without Edit State; moving a published clip into a category where publication is not allowed makes it unpublished.

The Upload controller checks CSRF, authenticates, validates Joomla form input (including Custom Fields), checks actual temporary-file bytes and quota, prepares with `AudioUploadService`, and saves through `ClipModel::savePreparedFile`. The submitted owner is ignored. The workspace query always uses the session identity; it cannot become an all-users list through a URL parameter. Deletion/trash recheck ownership under the owner's quota lock. Permanent deletion requires an already-trashed clip and explicit confirmation.

Metadata editing uses the existing editor and a component-specific switch, not Joomla's global switch. The frontend policy does not expose ownership or administrative file operations. My Clips shows retained private/unpublished records, but actual preview still requires the shared direct-access policy. The original `normal` discovery restriction remains in force for public owner filters.

The executable policy test now has 56 assertions, including forged fields, restricted categories/access/tags, menu narrowing, fixed defaults, guests, Edit Own and category-move moderation. This exercises real policy code and SQL, not a running Joomla application. See [frontend testing instructions](../user/frontend-upload.md) for the remaining browser acceptance checks. `0.13.1.sql` is a no-op marker.

## 0.13.1.1 regression

Run `php docs/developer/tests/archive-state.php` separately from the policy tests. It loads the production Archive model with a minimal Joomla-style lazy-state adapter and rejects recursive initialization before exhausting the stack. The original 0.13.1 model fails at the Owner-filter session write; the corrected model passes 24 checks covering default/disabled owner filtering, explicit owner, remembered owner, negative input, reset and repeated reads. This test does not exercise complete Joomla rendering or database integration.
