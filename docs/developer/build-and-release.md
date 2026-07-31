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
python3 build_package.py --output /path/to/pkg_audioarchive_v0-11-4.zip
```

The script:

1. Parses the package version and nested ZIP names.
2. Collects package language files.
3. Builds and validates each extension ZIP.
4. Builds the outer Joomla package.
5. Verifies required entries and ZIP CRC data.

## Release checklist

- Update package and component manifest versions and creation dates.
- Add any database schema migration.
- Update English and German language files together.
- Update `CHANGELOG.md` and affected documentation.
- Run static validation and tests.
- Build the installer.
- Install/update it on a Joomla! 6 test site.
- Validate the installer ZIP and nested ZIPs.
- Create a source archive containing the expanded source tree and documentation; do not include nested extension ZIPs in the source archive.

## Source archive

The source archive is a development snapshot, not the Joomla installer. It contains expanded extension directories under `pkg_audioarchive/`, plus documentation, licence, and build script.
