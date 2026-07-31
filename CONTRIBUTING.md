# Contributing

Contributions, bug reports, and focused improvement proposals are welcome.

## Before changing code

- Base work on the latest source release or current repository branch.
- Keep Joomla! 6 and PHP 8.3 compatibility.
- Preserve access checks, publication checks, CSRF protection, and managed-path validation.
- Do not introduce runtime CDN dependencies or external tracking.
- Add interface text to both `en-GB` and `de-DE` language files.
- Prefer Joomla-native facilities over parallel custom implementations.

## Source layout

All installable extensions are expanded below `pkg_audioarchive/`. The package manifest determines which extension directories are converted to nested ZIP files by `build_package.py`.

See [Source tree](docs/developer/source-tree.md) and [Build and release](docs/developer/build-and-release.md).

## Building

From the repository root:

```bash
python3 build_package.py
```

The script reads the version from `pkg_audioarchive/pkg_audioarchive.xml`, builds each nested extension archive, assembles the outer Joomla package, and validates every ZIP.

## Testing changes

At minimum:

- Validate PHP syntax for changed PHP files.
- Parse changed XML and JSON files.
- Check JavaScript syntax where relevant.
- Confirm English and German language-key parity.
- Install or update the package on a Joomla! 6 test site.
- Exercise affected frontend and administrator workflows.

Security-sensitive changes should also test unauthorised requests, invalid CSRF tokens, path traversal attempts, and access-level enforcement.

## Documentation

Update the relevant page under `docs/user/` or `docs/developer/` when behaviour, configuration, extension points, or build steps change. Keep the README limited to project overview and getting started.
