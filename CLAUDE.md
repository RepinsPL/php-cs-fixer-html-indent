# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language

This project is conducted entirely in English — all code, comments, commits, documentation, and Claude Code communication.

## Project overview

A PHP library providing two custom PHP-CS-Fixer fixers that preserve base indentation of PHP blocks embedded in HTML. The fixers work in tandem: dedent (priority 1000) strips HTML indentation before formatting, reindent (priority -1000) restores it after.

## Commands

```bash
# Install dependencies
composer install

# Running the fixers (requires configuration in the host project)
# In the project's .php-cs-fixer.dist.php add:
#   ->registerCustomFixers([
#       new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextDedentFixer(),
#       new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextReindentFixer(),
#   ])
#   ->setRules(['RepinsPL/html_context_dedent' => true, 'RepinsPL/html_context_reindent' => true])
```

```bash
# Run tests
composer test
```

Always run `composer test` after making changes to verify nothing is broken.

## Development workflow

Follow test-driven development (TDD):

1. **First write a failing test** that describes the expected behavior of the new feature or bug fix.
2. **Then implement** the code until the test passes.
3. **Never modify a test to make it pass artificially** — if a test fails, fix the implementation, not the test.

All tests must be **100% end-to-end**: they invoke the real `php-cs-fixer fix` CLI via `Symfony\Component\Process\Process` (see `AbstractFixerTestCase::runPhpCsFixer()`). Do not test fixers by calling their internal methods directly.

No linter or CI yet — the project has no `phpstan.neon` or pipeline.

## Architecture

Four files in `src/`, namespace `RepinsPL\PhpCsFixerHtmlIndent\` (PSR-4):

- **HtmlContextDetectionTrait** — shared logic: `detectBaseIndent()` detects the number of tabs from the preceding `T_INLINE_HTML`, `findBlockClose()` finds the matching `T_CLOSE_TAG`.
- **HtmlContextDedentFixer** (`RepinsPL/html_context_dedent`, priority 1000) — runs before other fixers, strips base HTML indentation from PHP blocks and T_INLINE_HTML trailing tabs so formatting fixers (like `statement_indentation`) work on "clean" code.
- **HtmlContextReindentFixer** (`RepinsPL/html_context_reindent`, priority -1000) — runs after all fixers, restores base indentation to both PHP blocks and T_INLINE_HTML while preserving the formatting applied in the HTML context.
- **IndentRegistry** — static registry for sharing base indent values between dedent and reindent fixers. Both fixers iterate tokens in the same reverse order, so push/shift ordering is guaranteed.

### Key technical decisions

- Both fixers iterate tokens **in reverse** (from end to start) so modifications don't shift indices of earlier tokens.
- Dedent strips trailing tabs from `T_INLINE_HTML` before `<?php` and stores the base indent in `IndentRegistry`; reindent reads from the registry and restores the tabs. This ensures fixers like `statement_indentation` don't see the HTML context indent and add unwanted extra indentation.
- Dedent uses `clearAt()` instead of removing tokens — reindent checks for cleared tokens and recreates them as `T_WHITESPACE`.
- Regex `/\n(?!\n)/` in reindent — negative lookahead prevents adding indentation before empty lines.
- Base indentation detection supports **tabs only** (`^\t+$`), not spaces.
- Requirements: PHP >= 8.1, php-cs-fixer ^3.0.
