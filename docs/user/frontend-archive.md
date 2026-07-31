# Public Archive

Create a menu item of type **Punga Audio Archive → Audio Archive**.

## Menu-item options

An Archive menu item can define introductory text, category and tag restrictions, visible filters, columns, ordering, pagination, table colours, Clip Detail behaviour, related clips, and download policy. Menu-item values override component defaults where offered.

## Filters

The GET-based filter form supports:

- Text search
- One category
- Multiple tags using AND or OR
- Minimum and maximum duration
- Recording-date range
- Upload-date range

Filtered URLs can be bookmarked and shared. The **Clear tag filter** action removes selected visitor tags without clearing unrelated filters.

Duration values accept seconds or formatted times such as `90`, `01:30`, or `1:02:30`. JavaScript adds a two-handle slider, while the text fields remain the submitted fallback.

## Result list

Desktop uses a table and narrow screens use responsive cards. Configurable columns include play control, title, category, duration, dates, tags, ratings, and actions.

Archive rows use the unified Minimal player. Starting another ordinary player pauses the previous one.

## State and pagination

The visitor's filters, tag mode, sorting, direction, and page size are stored independently per Archive menu item in the Joomla session. Explicit URL state takes precedence, and **Reset** returns to configured defaults.

Compact pagination retains a configurable number of page links at both ends and inserts ellipses where needed.

## Routing and return links

Clip links retain the active Archive menu context. When JavaScript is available, the exact originating URL is stored in tab-local `sessionStorage`, allowing the Clip Detail return link to restore filters, sorting, and pagination without polluting the canonical clip URL.
