# External embeds

Available in 0.15.0. These players can be placed on other websites using a normal HTML iframe. They are separate from the Joomla content-plugin placeholders used inside your own site.

## Enable embedding

1. Open **Component Options → External embeds → Allow external embedding** and select **Yes**. The global default is **No**.
2. Edit a clip and set **Allow embedding** to **Yes** in its details. This field is available in the administrator editor, frontend editor and upload form; it defaults to Yes, but never overrides the global switch.
3. The clip must be published, within its publication dates, use normal (not private) visibility and Public access. The archive, category and ancestor categories must also be Public. Registered-only, Guest-only, private and unpublished content cannot be embedded, even by a signed-in owner or administrator.

Existing clips receive Allow embedding = Yes on update. Embedding remains disabled site-wide until an administrator enables the global option. No original audio is changed.

## Copy a player

Open **Share → Embed**, choose a presentation, inspect the live preview and click **Copy embed code**. Paste the result into a custom HTML block in WordPress, a permitted HTML source area in Joomla, or any other page that accepts iframes. Some editors remove iframe markup according to their HTML permissions; use an authorised custom HTML block.

| Presentation | Contents |
| --- | --- |
| Minimal | Small playback control and duration |
| Compact | Playback and seeking |
| Default | Standard player including mute |
| Featured | Expanded player with available waveform, spectrogram and frequency profile; optional pitch/speed controls follow component settings |

The title links to the original clip page. A download link appears only when anonymous downloads are allowed. Playback starts on click; there is no autoplay. The player uses the existing component player colours and behaviour. Playlist presentation is not offered for a single-clip embed.

Generated markup uses 100% width, lazy loading, an accessible title and a presentation-specific height. Adjust the height for unusually long titles, large configured analysis heights or narrow containers. A frame remains scrollable if its content is taller than the chosen height.

The standalone URL has this structure (use the URL generated on your own site):

```html
<iframe src="https://example.com/index.php?option=com_audioarchive&amp;task=embed.player&amp;format=raw&amp;id=42&amp;presentation=featured" title="My clip" width="100%" height="480" loading="lazy" style="border: 0;"></iframe>
```

The numeric clip ID keeps the link independent of alias changes and archive menu routing.

## Permissions and existing embeds

Player, stream, analysis and event requests recheck global and clip embedding permissions. Changing the clip to private, restricted or unpublished, disabling its embedding, or switching embedding off globally makes subsequent requests unavailable. Bytes already downloaded or buffered by a browser cannot be recalled.

The player does not require third-party cookies or a signed-in Joomla session. Event POSTs use short-lived, clip- and purpose-bound signatures. Tokens expire after 12 hours; reload a long-open frame or archive page to obtain a fresh token.

## Analytics

- **`audio.embed`**: the Copy embed code button successfully copied the code. Opening the dialog, changing presentation and manual copying from the text field do not create this event. This measures code-copy actions, not confirmed installation on another website.
- **`audio.play_embedded`**: actual embedded playback starts. It increments the ordinary clip play counter, but does not also emit `audio.play`. The global play-count option controls this event just as it controls ordinary playback events.
- Preview playback inside the Share dialog is excluded from play counting.

Punga Analytics remains optional. Configure these custom event identifiers there to display them. An embed counts at most once per frame load, with the existing short session throttle also applied when cookies are available. With third-party cookies blocked, cross-frame/session deduplication is not guaranteed. Analytics listener failures never prevent playback or copying.

## Hosting headers

Only the standalone player response permits framing; ordinary Joomla pages retain their existing protection. If a web server, reverse proxy, CDN or security plugin adds `X-Frame-Options: SAMEORIGIN`/`DENY` or a restrictive CSP `frame-ancestors` directive after PHP runs, the host must permit external framing specifically for the `embed.player` endpoint. Do not disable frame protection site-wide. HTTPS sites should embed an HTTPS player to avoid mixed-content blocking.

## Live verification

After installing, test all four presentations on a page from another origin, with third-party cookies disabled as well as enabled. Check play/pause, seeking, available analyses, permitted downloads, successful-copy events and the absence of duplicate ordinary play events. Then disable embedding or make the clip private and confirm fresh player/media requests fail.
