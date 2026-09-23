# 0.13.0 local validation — 2026-09-19

- PHP syntax: 136 files passed with PHP 8.5.10 WebAssembly CLI.
- Production access/quota services: 29 regression assertions passed using SQLite and minimal Joomla adapters.
- JavaScript syntax: 9 files passed Node syntax checking.
- XML: 18 files parsed; JSON asset manifest parsed.
- Language files: 28 checked for valid lines and duplicate keys.
- Documentation file links resolve, including the full 0.13 roadmap.
- Package/component/newest schema/README/changelog versions agree on 0.13.0.
- All 6 nested extension ZIPs passed CRC checks; installer and source ZIPs passed CRC checks.
- Source retains pkg_audioarchive/, README.md, docs/, LICENSE, CONTRIBUTING.md, build_package.py, CHANGELOG.md.
- Recorder/player/browser-collection JavaScript and all CSS remain byte-identical to 0.12.4. Only administrator bulk-upload/directory-import JavaScript changed; corresponding asset versions were bumped.

Not exercised: a full Joomla installation/update, browser rendering within Joomla, Joomla event/ACL integration, concurrent MySQL/MariaDB requests, or end-to-end portable restore. See multi-user.md for staging gates. SQLite tests emulate advisory-lock lifecycle, not actual database concurrency.
