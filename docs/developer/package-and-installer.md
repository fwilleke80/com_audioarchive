# Package and installer

The outer manifest is `pkg_audioarchive/pkg_audioarchive.xml`.

It declares package version 0.11.4, package-level language files, `install.php`, and these nested archives:

- `com_audioarchive.zip`
- `mod_audioarchive.zip`
- `mod_audioarchive_tags.zip`
- `plg_content_audioarchive.zip`
- `plg_finder_audioarchive.zip`
- `plg_quickicon_audioarchive.zip`

The source repository stores each extension expanded. `build_package.py` creates the nested ZIPs in a temporary directory and then assembles the Joomla installer package.

## Package install script

The package-level `install.php` handles package install/update integration, including child extension enablement and state-preserving behaviour where implemented.

## Component installer

The component manifest installs database schema, administrator and site files, languages, media assets, and update migrations. The component install script performs component-specific setup and upgrade work.

## Updates

Database changes belong in versioned SQL files under:

```text
pkg_audioarchive/com_audioarchive/administrator/sql/updates/mysql/
```

The component and package manifest versions must remain aligned for a release.

## Uninstallation

Package child uninstallation is blocked from the package manifest. Media-retention behaviour is configurable, but database and media backups remain the administrator's responsibility.
