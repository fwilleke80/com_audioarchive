# 0.13.2 validation

- PHP 8.5 WASM syntax checks: 154 files passed.
- XML: 21 files; asset JSON: 1; JavaScript syntax: 11; language INI: 28. All passed, including duplicate-key and documentation-link checks.
- Production policy/quota/contribution tests: 56 assertions passed.
- Collection service with transactional SQLite adapter: 32 assertions passed, including portable restore, ownership, stale revisions and revocation.
- Archive recursion regression: 24 assertions passed.
- Peak normalization service/parser: 32 assertions passed.
- Native FFmpeg signal tests: 7 cases passed.
- Node browser mocks: collection acknowledgement/rollback and normalization activation/source reuse passed.
- Installer and nested extension ZIP integrity verified; component update schema and new assets present.

The PHP tests use a lightweight Joomla/database adapter, not a complete Joomla/MySQL installation. Browser tests use mocks, not an interactive browser. See the [live-site test guide](../user/0.13.2-testing.md).

Commands from the project root (substitute your PHP CLI):

```sh
php docs/developer/tests/collections.php
php docs/developer/tests/archive-state.php
php docs/developer/tests/normalization.php
python3 docs/developer/tests/peak-analysis.py
node docs/developer/tests/collections.mjs
node docs/developer/tests/normalization.mjs
```
