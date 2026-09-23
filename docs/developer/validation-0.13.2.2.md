# 0.13.2.2 validation

Production canvas rendering was exercised with mocked canvas/DOM objects: both layers use peak gain, unchanged dimensions/gain reuse cached layers, changed gain redraws, oversized amplitudes are bounded, invalid gain falls back to unity, and original peaks remain immutable.

Run `node docs/developer/tests/waveform-normalization.mjs` from the project root.

PHP, JavaScript, XML, JSON, INI and documentation-link checks passed. Installer and nested ZIP integrity checks passed. Live Joomla/browser visual verification remains necessary.
