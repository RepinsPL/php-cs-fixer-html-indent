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

After any significant change, check whether `README.md` needs to be updated (e.g. new features, changed requirements, modified public API or configuration).

**Always update `CHANGELOG.md`** (Keep a Changelog format) together with every user-visible change — bug fixes, new features, changed requirements. Add the entry under the upcoming version heading in the same commit as the change, so it is never left for later. When tagging a release, make sure the heading carries the version and date and that the compare link at the bottom of the file exists. Credit external contributors in the entry (e.g. "Thanks to @user for … (#PR)").

## Development workflow

Follow test-driven development (TDD):

1. **First write a failing test** that describes the expected behavior of the new feature or bug fix.
2. **Then implement** the code until the test passes.
3. **Never modify a test to make it pass artificially** — if a test fails, fix the implementation, not the test.

All tests must be **100% end-to-end**: they invoke the real `php-cs-fixer fix` CLI via `exec()` (see `AbstractFixerTestCase::runPhpCsFixer()`). Do not test fixers by calling their internal methods directly.

No linter or CI yet — the project has no `phpstan.neon` or pipeline.

## Architecture

Four files in `src/`, namespace `RepinsPL\PhpCsFixerHtmlIndent\` (PSR-4):

- **HtmlContextDetectionTrait** — shared logic: `detectBaseIndent()` detects the base indent string (tabs or spaces) from the preceding `T_INLINE_HTML`, `findBlockClose()` finds the matching `T_CLOSE_TAG` without leaving the scope the open tag lives in, `braceDepthBeforeClose()` counts braces opened in the block that are still unclosed at its close tag.
- **HtmlContextDedentFixer** (`RepinsPL/html_context_dedent`, priority 1000) — runs before other fixers, strips base HTML indentation from PHP blocks and T_INLINE_HTML trailing tabs so formatting fixers (like `statement_indentation`) work on "clean" code.
- **HtmlContextReindentFixer** (`RepinsPL/html_context_reindent`, priority -1000) — runs after all fixers, restores base indentation to both PHP blocks and T_INLINE_HTML while preserving the formatting applied in the HTML context.
- **IndentRegistry** — static registry for sharing `[baseIndent, codeIndent, closingDepth]` entries between dedent and reindent fixers. Both fixers iterate tokens in the same reverse order, so push/shift ordering is guaranteed.

### Key technical decisions

- Both fixers iterate tokens **in reverse** (from end to start) so modifications don't shift indices of earlier tokens.
- Dedent strips trailing tabs from `T_INLINE_HTML` before `<?php` and stores the base indent in `IndentRegistry`; reindent reads from the registry and restores the tabs. This ensures fixers like `statement_indentation` don't see the HTML context indent and add unwanted extra indentation.
- `findBlockClose()` tracks brace depth relative to the open tag and returns `null` when it meets a `}` that closes a scope opened *before* the open tag (e.g. the enclosing method ends before the next HTML island). Without this guard a block would be treated as spanning up to an unrelated close tag in a sibling method, and the indent adjustment would mangle everything in between (issue #1).
- Blocks whose braces are unbalanced at their own close tag (`braceDepthBeforeClose() !== 0`, e.g. `if ($x) { … ?> <p>…</p> <?php }`) are **skipped entirely** by both fixers. Dedent still pushes a registry entry (with `closingDepth`) to keep push/shift aligned, and reindent skips the block when `closingDepth !== 0`. Dedenting such a block would desync `statement_indentation`'s view of the still-open scope for the rest of the file, and reindent cannot reach the later island to correct it. Stock fixers handle these blocks correctly on their own.
- Dedent uses `clearAt()` instead of removing tokens. php-cs-fixer's runner calls `Tokens::clearEmptyTokens()` right after each fixer that changed the tokens, so by the time reindent runs the cleared whitespace token is gone and the block's first code or comment token sits directly after `T_OPEN_TAG`. `reindentBlock()` therefore handles the first token by kind: a `T_WHITESPACE` token gets its last line replaced with `codeIndent` outright (its first statement is left at column 0 by `statement_indentation`, so the strip pattern cannot be used there); any other token — code **or comment** — gets a new `T_WHITESPACE` with `codeIndent` inserted in front of it. Never rewrite a comment token's content (see `FirstLineCommentBlockTest`).
- Reindent strips the formatter's base indent before re-applying `codeIndent` (`detectFormatterBaseIndent()` → `/\n{formatterBase}(?!\n)/`). When a block is nested inside an outer control structure split across an HTML island (e.g. `if ($x) { ?> … <?php $a; $b; ?>`), `statement_indentation` shifts every line right by the outer scope depth; without stripping that shift, sibling statements after the first would gain an extra indent level. `formatterBase` is the minimum indent among block lines preceded by a newline. For self-contained blocks `formatterBase` is empty, so the pattern reduces to `/\n(?!\n)/`. The negative lookahead prevents adding indentation before empty lines.
- `reindentBlock()` splits the block at `firstStatementEndIndex()`. `statement_indentation` opens a *statement* scope on the `T_INLINE_HTML` preceding the open tag, and inside it lines keep their relative offsets — after dedent, column 0 — instead of the enclosing scope's indent. That scope covers the block's **whole first statement**, however many lines it spans, so those lines only need `codeIndent` added (`/\n(?!\n)/`); everything past that boundary carries `formatterBase` and needs it stripped first. `formatterBase` is therefore measured past the boundary too — measuring from the first token instead lets the first statement's relative-zero lines drag the minimum to `''` (issue #3).
- `firstStatementEndIndex()` must agree with `statement_indentation` on where that statement ends, or the boundary lands inside it and later statements gain a level anyway. Beyond brace/paren/bracket depth this means: `else`/`elseif`/`catch`/`finally` carry the statement on past a `}` (`continuesStatement()`), alternative-syntax blocks count as a depth level of their own (they open no brace, so the first `;` in their body would otherwise end the scan), and a `while` after `}` only continues the statement when the matching `{` belongs to a `do` — treating every `while` as a do-while swallows a plain `while` loop that merely follows an `if` block.
- Reindent repairs unprotected blocks (no HTML base indent) contaminated by dedenting an earlier block (`fixSpuriousIndentAfterOpenTag()`). Dedenting a block to column 0 makes `statement_indentation` record an empty "previous line indent"; the statement scope it opens for the following inline HTML stretches past the next block's open tag and prefixes its first-statement lines with the HTML-context indent. The repair strips that prefix, guarded by `IndentRegistry::hasPendingDedented()` (an earlier block was actually dedented) and `isTopLevel()` (the block is outside any unclosed brace or alternative-syntax scope, where indentation could be legitimate). Known limitation: the same contamination inside an unclosed outer scope (non-top-level) is not repaired.
- Base indentation detection supports **tabs or spaces** (`^(\t+| +)$`), but not mixed tabs and spaces on the same line.
- Requirements: PHP >= 8.0, php-cs-fixer ^3.0.
- **Never rely on transitive dependencies** — every package used in the code must be explicitly declared in `composer.json` (`require` or `require-dev`).
- **Minimize dependencies** — avoid adding packages unless they are truly essential and provide significant value over native PHP solutions. Prefer built-in PHP functions (`exec`, `proc_open`, etc.) over convenience wrappers.
