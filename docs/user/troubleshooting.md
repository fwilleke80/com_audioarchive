# Troubleshooting

## Newly added options are missing

Clear the Joomla administrator cache and reopen the component options or menu item.

## FFmpeg is not detected

Review the dashboard System Check. Confirm that:

- The configured absolute or Joomla-root-relative path is correct.
- The file exists, is readable, and is executable.
- `proc_open()` is available and not disabled.
- The hosting account may execute the binary.

Punga Audio Archive also searches the executable name through `PATH`, `/usr/bin`, and `/usr/local/bin`.

## Analyses remain pending

Open **Integrity & Maintenance** and run **Process analysis queue**. Closing the page stops browser-driven processing but does not remove pending jobs.

Check the job error, FFmpeg availability, analysis directory permissions, timeout, and maximum attempts.

## Audio does not play on one browser

Browser support depends on the original container and codec. Punga Audio Archive 0.11.4 streams originals and does not generate compatibility playback previews. The authorised original may still be downloadable.

## Seeking does not work

Confirm the playback endpoint is reached directly and that proxies or server rules preserve HTTP Range requests.

## Direct component URLs bypass the menu

Set **Frontend archive access** in the component options. Menu-item access alone does not protect `/component/audioarchive` routes.

## Wrong Archive context or return link

Check that the intended Archive menu item is published and accessible. Clear browser session state when testing changed menu routing. The exact origin stored in `sessionStorage` expires after 24 hours.

## A complete export is unexpectedly small or invalid

Run the integrity check first. Export refuses to silently omit missing requested media. Confirm `ZipArchive`, available temporary storage, response buffering, and server limits.

## Batch dialog Cancel does nothing

This was corrected in 0.11.3. Update to 0.11.3 or later.

## Playlist rows show native play controls on iPhone

This was corrected in 0.11.4. Playlist rows now use the unified Minimal player control.
