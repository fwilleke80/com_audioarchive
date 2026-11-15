# Punga Audio Archive 0.14

## Embedded archive tables (updated in 0.14.1)

Use the Content – Punga Audio Archive plugin in an article or any content that runs Joomla content preparation:

```text
{audioarchive list}
{audioarchive list search="some clip title or description" length_min=1s length_max=10s tags="funny melodic gnarly" maxresults=10}
{audioarchive list category=music tags="guitar,bass" tag_mode=or sort=duration direction=desc}
{audioarchive list uploaded_from=2026-01-01 uploaded_to=2026-12-31}
```

Every parameter is optional. A bare list shows up to 20 accessible clips, newest upload first. It uses the global archive column and colour settings, including the mini player, ratings and actions. Mobile layouts use the same cards as the archive. Existing player/count/playtime placeholders are unchanged.

| Parameter | Values / meaning |
| --- | --- |
| `search` | Text matched against title, description or original filename |
| `category` | One category ID, alias or exact title (quote titles containing spaces) |
| `owner` | Joomla user ID |
| `length_min`, `length_max` | Inclusive duration bounds; seconds by default, or suffix `ms`, `s`, `m`, `h`; decimals and `MM:SS` / `HH:MM:SS` supported |
| `duration_min`, `duration_max` | Alternative names for the duration bounds |
| `tags` | Space- or comma-separated tag aliases or IDs |
| `tag_mode` | `and` (default) or `or` |
| `recorded_from`, `recorded_to` | Inclusive recording dates, `YYYY-MM-DD`, using the archive's date-filter semantics |
| `uploaded_from`, `uploaded_to` | Inclusive upload dates, `YYYY-MM-DD`, using the archive's date-filter semantics |
| `maxresults` | Maximum total clips included, default **20**, range 1–1000 |
| `perpage` | Clips per page, default **20**, range 1–1000 |
| `sort` | `title`, `duration`, `recorded`, `uploaded` (default), `rating` (likes) |
| `direction` | `asc` or `desc` (default) |

Both `maxresults` and `perpage` default to 20. Pagination appears only when the included results exceed `perpage`.

```text
{audioarchive list maxresults=100 perpage=20}
{audioarchive list maxresults=50 perpage=10}
```

The first example includes up to 100 clips across five pages; the second includes up to 50 clips across five pages. If fewer clips match, pagination reflects that smaller number. A bare `{audioarchive list}` still includes at most 20 clips on one page. With `maxresults=50 perpage=20`, the last page contains at most ten clips.

Sorting applies to all matching clips before the total cap and pagination. Clicking a heading resets that list to page one. Each list has independent sorting and pagination; links preserve the article URL, other lists' state and author-defined filters. Out-of-range pages are clamped to the available range. These controls do not update saved archive filters. Embedded lists always show pagination when needed, regardless of the full archive's pagination-display setting. There is no visitor-facing filter form; use an Archive menu item for that.

Unknown tags/categories match no clips (an unknown OR tag can still match other valid OR terms). Invalid duration/date syntax leaves the placeholder visible rather than silently dropping a restriction. The list always respects publication, access, visibility and available-original checks from the archive.

## Clip detail menu item

Create a menu item under **Punga Audio Archive → Clip detail (selection)**. Choose:

- **Specific clip:** select a published clip, or supply its alias. An alias takes precedence.
- **Newest clip:** choose upload, recording or publication date.
- **Random clip:** choose an eligible clip on each uncached page request.
- **Clip of the day:** deterministic daily selection shared with the module.

Optional categories and tags restrict the eligible clips. Tag matching supports AND/OR. All modes render the normal clip detail page. If no eligible clip exists, the standard clip-not-found response is used.

Module and menu selections share one implementation. The daily calendar date uses the **Joomla website timezone** from Global Configuration, not the browser, PHP server default or individual user's timezone. Selection remains stable during that local date while the eligible collection is unchanged. Since 0.14.2, the module and menu select the same daily clip when their filters and access permissions produce the same eligible clips. The selection ranks eligible clip IDs by a hash of the website-local date and clip ID; module and menu IDs are excluded. Updating from an older version can change the selection once on the update day. Shared links identify the actual clip, rather than changing with tomorrow's selection. Full-page caching can retain old dynamic output; exclude rotating clip pages from external/full-page caches if immediate rollover is needed.

## Bulk upload

Select Files and Start Upload sit above a combined drop area and queue. Drop more files into the same area before starting. Each row keeps its upload progress, analysis status, result details and Edit action together. Analyses run automatically after the upload batch; each row progresses from queued to processing and a processed/failed summary. Rows that finish successfully turn light green.

Clear finished appears once successful rows exist and is enabled after processing stops. It removes only successfully uploaded and analysed rows. Upload failures, cancelled uploads and analysis failures remain visible. Upload Retry remains available for failed/cancelled uploads. Analysis failures remain available for inspection/requeue in the existing analysis queue; a network error leaves unprocessed analysis jobs queued. Processing continues with the next uploaded clip after a per-clip error.

## Page-number URLs (0.14.2)

Archive and My Clips use `page=3` for page three. Existing `start=40` and `limitstart=40` archive links still work; if both formats are present, `page` takes precedence. Changing sorting, filters or page size starts again on page one. Embedded lists use scoped page names such as `aa_list_1_page=3` so navigating one list does not move another. `maxresults` remains the total result cap and `perpage` remains the page size, both defaulting to 20.
