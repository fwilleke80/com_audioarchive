# Package and installer

The outer manifest is `pkg_audioarchive/pkg_audioarchive.xml`.

It declares the release version, package-level language files, `install.php`, and these nested archives:

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

The component and package manifest versions must remain aligned for a release, and the newest SQL filename must carry the same version. Even releases without structural database changes include an empty/comment-only schema marker so Joomla can advance `#__schemas` normally.

The component installer must not manually write release numbers into Joomla's `#__schemas` table. Joomla's schema updater owns that bookkeeping; `build_package.py` rejects a release when the package version, component manifest version, and newest SQL schema version disagree.

## Uninstallation

Package child uninstallation is blocked from the package manifest. Media-retention behaviour is configurable, but database and media backups remain the administrator's responsibility.
