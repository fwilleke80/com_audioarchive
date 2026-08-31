# Build and release

## Source prerequisites

The project root must contain:

```text
pkg_audioarchive/
README.md
CHANGELOG.md
CONTRIBUTING.md
LICENSE
build_package.py
docs/
```

Inside `pkg_audioarchive/`, extension directory names must match the stems of nested ZIP names listed in `pkg_audioarchive.xml`.

## Build the installer

Run:

```bash
python3 build_package.py
```

Optional arguments:

```bash
python3 build_package.py --source /path/to/pkg_audioarchive
python3 build_package.py --output /path/to/pkg_audioarchive_vX-Y-Z.zip
```

The script:

1. Parses the package version and nested ZIP names.
2. Confirms that the package version, component manifest version, and newest component SQL schema version are identical.
3. Confirms that the README **Current version** and newest `CHANGELOG.md` release heading match the package version.
4. Collects package language files.
5. Builds and validates each extension ZIP.
6. Builds the outer Joomla package.
7. Verifies required entries and ZIP CRC data.

A release must therefore include `administrator/sql/updates/mysql/<version>.sql` even when no database change is required; in that case the file is a schema marker containing only a comment.

## Release checklist

- Update package and component manifest versions and creation dates.
- Add the release's database schema migration or no-op schema marker so the newest SQL filename matches the release version.
- Update English and German language files together.
- Update the README current version, `CHANGELOG.md`, and affected documentation.
- Run static validation and tests.
- Build the installer.
- Install/update it on a Joomla! 6 test site.
- Validate the installer ZIP and nested ZIPs.
- Create a source archive containing the expanded source tree and documentation; do not include nested extension ZIPs in the source archive.

## Source archive

The source archive is a development snapshot, not the Joomla installer. It contains expanded extension directories under `pkg_audioarchive/`, plus documentation, licence, and build script.
