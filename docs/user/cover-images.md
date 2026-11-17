# Cover-image uploads (0.15.6)

Clip covers are part of the backend and frontend edit forms, including new clip uploads. Choose a JPEG, PNG or WebP file to see a local preview, then use the form's **Save** or **Apply** action to store it. Selecting a file does not send it to the server.

**Remove cover** marks the cover for removal when the form is saved. **Undo cover change** clears the pending selection/removal. **Cancel** leaves the stored cover unchanged. A failed save also preserves the stored cover; after a failed submission, select the image again because browsers do not repopulate file inputs.

Account playlists use the same pending workflow. Choose a file or mark **Remove cover**, then use the Playlists page's **Save** button to commit names, clip contents and covers together. **Cancel** discards all pending playlist edits. **Undo cover change** affects only the selected playlist's cover. Switching playlists retains pending selections; new playlists can have a cover in their first save. Failed saves preserve the pending selection for retry. Guest browser playlists do not support covers.

Cover images are not yet displayed on public clip pages, playlists or embeds.

Limits: 2 MiB per image, at most 4096 pixels per side and 8,388,608 pixels total. File content is checked server-side; SVG, GIF and other formats are rejected. Covers count toward the entity owner's storage quota. Replacing a cover releases the old image; deleting the clip or playlist permanently removes its cover. Trash retains it.

Images are held privately in the database and included in Audio Archive backup/restore. No public cover URL is exposed in this release, and playlist share/export links do not include the artwork. Database backups also retain covers. The uploader must have permission to edit the clip or own the account playlist.

The richer metadata and public artwork presentation are separate upcoming steps.
