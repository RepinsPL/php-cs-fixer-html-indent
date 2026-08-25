# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [0.1.5] - 2026-08-25

### Fixed

- A second (or later) top-level statement in a PHP block gained a spurious extra indentation level when the block's first statement spanned multiple lines (e.g. a call with a multi-line array argument) and the block sat inside a real, still-open brace scope (e.g. a class method). `detectFormatterBaseIndent()` was scanning into the first statement's own lines, whose relative-zero indentation isn't `statement_indentation`'s real-depth shift, which dragged the detected shift down to zero and left the later statement's already-shifted lines with `codeIndent` added on top instead of stripped first ([#3](https://github.com/repinspl/php-cs-fixer-html-indent/issues/3)). Thanks to [@brianlmoon](https://github.com/brianlmoon) for reporting and fixing it ([#4](https://github.com/repinspl/php-cs-fixer-html-indent/pull/4)).
- The same over-indentation in three more shapes of a first statement, where the detected boundary landed inside it: an `if`/`else`, a `try`/`catch`/`finally` (the `}` closing the first branch is not the end of the statement), and alternative syntax such as `if ($a): … endif;` (which opens no brace, so the first `;` of its body was taken as the end).

## [0.1.4] - 2026-08-22

### Fixed

- `findBlockClose()` no longer treats a PHP block as spanning past the brace that closes its enclosing scope (e.g. a method ending before the next HTML island), which mangled the closing brace and the next method's signature ([#1](https://github.com/repinspl/php-cs-fixer-html-indent/issues/1)).
- PHP blocks whose braces are unbalanced at their own close tag (e.g. an `if`/`foreach` split across an HTML island) are now left untouched by both fixers instead of desyncing `statement_indentation` for the rest of the file.

Thanks to [@brianlmoon](https://github.com/brianlmoon) for reporting, fixing and testing both issues ([#2](https://github.com/repinspl/php-cs-fixer-html-indent/pull/2)).

## [0.1.3] - 2026-07-14

### Fixed

- Spurious indentation leaking into an unprotected PHP block (one with no HTML base indent) after an earlier block was dedented to column 0. The reindent fixer now strips that leaked indent from top-level blocks.

## [0.1.2] - 2026-06-05

### Fixed

- Statements after the first one in a PHP block nested in HTML, inside a control structure split across an HTML island (e.g. `if ($x) { ?> ... <?php $a; $b; ?>`), were indented one level too deep.

## [0.1.1] - 2026-03-10

### Fixed

- `IndentRegistry` push/shift desync across multiple PHP blocks, which made HTML-nested blocks lose their indentation and `<script>`-embedded blocks gain a wrong one.
- Inline PHP blocks (`<?php echo ... ?>`) are now consistently skipped by both fixers.

### Added

- Contributing section in README.

## [0.1.0] - 2026-02-28

### Added

- `HtmlContextDedentFixer` (priority 1000) — strips base HTML indentation from PHP blocks before formatting.
- `HtmlContextReindentFixer` (priority -1000) — restores base HTML indentation after all fixers run.
- Support for both tab-based and space-based indentation (auto-detected from HTML context).
- `IndentRegistry` for sharing indent data between dedent and reindent fixers.

[0.1.5]: https://github.com/repinspl/php-cs-fixer-html-indent/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/repinspl/php-cs-fixer-html-indent/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/repinspl/php-cs-fixer-html-indent/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/repinspl/php-cs-fixer-html-indent/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/repinspl/php-cs-fixer-html-indent/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/repinspl/php-cs-fixer-html-indent/releases/tag/v0.1.0
