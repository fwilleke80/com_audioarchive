# Source tree

The repository root contains documentation, licence, build script, and one expanded package source directory.

```text
pkg_audioarchive/
├── language/
├── install.php
├── pkg_audioarchive.xml
├── com_audioarchive/
├── mod_audioarchive/
├── mod_audioarchive_tags/
├── plg_content_audioarchive/
├── plg_finder_audioarchive/
└── plg_quickicon_audioarchive/
docs/
README.md
CHANGELOG.md
CONTRIBUTING.md
LICENSE
build_package.py
```

## Component

`com_audioarchive/administrator/` contains backend forms, controllers, models, services, tables, views, templates, SQL, and language files.

`com_audioarchive/site/` contains frontend controllers, dispatcher, helpers, models, services, views, layouts, forms, templates, and language files.

`com_audioarchive/media/` contains registered CSS, JavaScript, and `joomla.asset.json`.

## Extension source directories

Each module or plugin directory is a complete extension source tree. Its directory name matches the stem of the nested ZIP listed in the package manifest.

## Documentation

`docs/user/` is task-oriented Joomla administration documentation. `docs/developer/` documents implementation contracts. `docs/images/` is reserved for real screenshots and diagrams.
