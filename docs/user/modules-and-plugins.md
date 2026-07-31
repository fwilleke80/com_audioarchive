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

Use `layout=minimal|compact|default|featured` to select a supported embedded player presentation. Featured players can use `dataview=waveform|spectrum`.

Count and playtime placeholders can be restricted with a comma-separated `category` value.

## Smart Search plugin

The Finder plugin indexes eligible clip titles, descriptions, original filenames, categories, tags, dates, author, and language. Rebuild the initial index under **Components → Smart Search**.

## Quick Icons plugin

The Quick Icons plugin adds an Audio Archive shortcut to the administrator Home Dashboard for users authorised to manage the component.
