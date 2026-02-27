# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [0.1.0] - 2026-02-28

### Added

- `HtmlContextDedentFixer` (priority 1000) — strips base HTML indentation from PHP blocks before formatting.
- `HtmlContextReindentFixer` (priority -1000) — restores base HTML indentation after all fixers run.
- Support for both tab-based and space-based indentation (auto-detected from HTML context).
- `IndentRegistry` for sharing indent data between dedent and reindent fixers.

[0.1.0]: https://github.com/repinspl/php-cs-fixer-html-indent/releases/tag/v0.1.0
