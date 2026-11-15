# Modules and plugins

## Audio Archive module

Create **Content → Site Modules → New → Audio Archive**.

Selection modes include latest, longest, shortest, random, clip of the day, most played, most downloaded, and a specific clip. Results can be restricted by category and tags.

Module layouts (`default`, `compact`, and `featured`) control surrounding metadata. Player presentation is configured separately and can inherit the component default or use Minimal, Compact, Default, or Featured.

## Audio Archive Tags module

The Tags module displays all accessible Audio Archive tags or a selected subset. It links to an appropriate Archive menu item, can hide empty tags, show counts and descriptions, and use cards, list, or compact presentation.

## Content plugin

Common placeholders:

```text
{audioarchive random}
{audioarchive clip=some-alias}
{audioarchive clip=123}
{audioarchive longest count=3}
{audioarchive shortest count=5}
{audioarchive count}
{audioarchive playtime}
```

Use `layout=minimal|compact|default|featured` to select a supported embedded player presentation. Featured players can use `dataview=waveform|spectrum|profile`.

Count and playtime placeholders can be restricted with a comma-separated `category` value.

### Embedded clip tables

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

## Smart Search plugin

The Finder plugin indexes eligible clip titles, descriptions, original filenames, categories, tags, dates, author, and language. Rebuild the initial index under **Components → Smart Search**.

## Quick Icons plugin

The Quick Icons plugin adds an Audio Archive shortcut to the administrator Home Dashboard for users authorised to manage the component.

## Version 0.14 additions

See [the 0.14 guide](whats-new-0.14.md) for current list placeholders, selection menu items and the integrated bulk-upload queue.
