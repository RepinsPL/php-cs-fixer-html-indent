<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class HtmlContextReindentFixer extends AbstractFixer
{
	use HtmlContextDetectionTrait;

	public function getName(): string
	{
		return 'RepinsPL/html_context_reindent';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Restores base HTML-context indentation to PHP blocks after other fixers have processed them.',
			[],
		);
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isTokenKindFound(T_CLOSE_TAG);
	}

	public function getPriority(): int
	{
		return -1000;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		for ($index = $tokens->count() - 1; $index >= 0; --$index) {
			if (!$tokens[$index]->isGivenKind(T_OPEN_TAG)) {
				continue;
			}

			if (!str_ends_with($tokens[$index]->getContent(), "\n")) {
				continue;
			}

			$registryEntry = IndentRegistry::shift(spl_object_id($tokens));

			if ($registryEntry !== null) {
				[$baseIndent, $codeIndent] = $registryEntry;
			} else {
				// Fallback: detect from T_INLINE_HTML (for cases without dedent)
				$baseIndent = $this->detectBaseIndent($tokens, $index);
				$codeIndent = null;
			}

			if ($baseIndent === null) {
				$this->fixSpuriousIndentAfterOpenTag($tokens, $index);
				continue;
			}

			$closeIndex = $this->findBlockClose($tokens, $index);
			if ($closeIndex === null) {
				continue;
			}

			$this->restoreInlineHtmlIndent($tokens, $index, $baseIndent);

			$codeIndent ??= $baseIndent . $this->detectIndentUnit($tokens, $index, $closeIndex);
			$this->reindentBlock($tokens, $index, $closeIndex, $codeIndent, $baseIndent);
		}
	}

	/**
	 * Removes spurious indentation added to the first statement of an unprotected
	 * block (one with no HTML base indent). When an earlier block in the file was
	 * dedented to column 0, statement_indentation records an empty "previous line
	 * indent" there; the statement scope it opens for the following inline HTML
	 * stretches past this block's open tag and prefixes the first-statement lines
	 * with the HTML-context indent. That prefix never comes from real code at the
	 * top level, so it is stripped; blocks nested in an unclosed brace or
	 * alternative-syntax scope may be indented legitimately and are left alone.
	 */
	private function fixSpuriousIndentAfterOpenTag(Tokens $tokens, int $openIndex): void
	{
		if (!IndentRegistry::hasPendingDedented(spl_object_id($tokens))) {
			return;
		}

		$wsIndex = $openIndex + 1;
		if ($wsIndex >= $tokens->count() || !$tokens[$wsIndex]->isGivenKind(T_WHITESPACE)) {
			return;
		}

		$content = $tokens[$wsIndex]->getContent();
		$lastNewline = strrpos($content, "\n");
		$spurious = $lastNewline === false ? $content : substr($content, $lastNewline + 1);

		if (!preg_match('/^(\t+| +)$/', $spurious)) {
			return;
		}

		if (!$this->isTopLevel($tokens, $openIndex)) {
			return;
		}

		if ($lastNewline === false) {
			$tokens->clearAt($wsIndex);
		} else {
			$tokens[$wsIndex] = new Token([T_WHITESPACE, substr($content, 0, $lastNewline + 1)]);
		}

		// Continuation lines of the first statement received the same prefix.
		$closeIndex = $this->findBlockClose($tokens, $openIndex) ?? $tokens->count();
		for ($i = $wsIndex + 1; $i < $closeIndex; ++$i) {
			if (!$tokens[$i]->isGivenKind(T_WHITESPACE)) {
				continue;
			}

			$newContent = str_replace("\n" . $spurious, "\n", $tokens[$i]->getContent());
			if ($newContent !== $tokens[$i]->getContent()) {
				$tokens[$i] = new Token([T_WHITESPACE, $newContent]);
			}
		}
	}

	/**
	 * Tells whether the T_OPEN_TAG at $openIndex sits outside any unclosed brace
	 * or alternative-syntax block, i.e. its statements belong at column 0.
	 */
	private function isTopLevel(Tokens $tokens, int $openIndex): bool
	{
		$depth = 0;

		for ($i = 0; $i < $openIndex; ++$i) {
			$token = $tokens[$i];

			if ($token->equals('{')) {
				++$depth;
			} elseif ($token->equals('}')) {
				--$depth;
			} elseif ($token->isGivenKind([T_ENDIF, T_ENDFOR, T_ENDFOREACH, T_ENDWHILE, T_ENDSWITCH, T_ENDDECLARE])) {
				--$depth;
			} elseif (
				// T_ELSEIF is absent on purpose: `elseif (...):` continues the block
				// opened by `if (...):` and shares its `endif`.
				$token->isGivenKind([T_IF, T_FOR, T_FOREACH, T_WHILE, T_SWITCH, T_DECLARE])
				&& $this->opensAlternativeSyntaxBlock($tokens, $i)
			) {
				++$depth;
			}
		}

		return $depth === 0;
	}

	private function opensAlternativeSyntaxBlock(Tokens $tokens, int $index): bool
	{
		$parenIndex = $tokens->getNextMeaningfulToken($index);
		if ($parenIndex === null || !$tokens[$parenIndex]->equals('(')) {
			return false;
		}

		$parenCloseIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $parenIndex);
		$afterParenIndex = $tokens->getNextMeaningfulToken($parenCloseIndex);

		return $afterParenIndex !== null && $tokens[$afterParenIndex]->equals(':');
	}

	/**
	 * Detects the base indentation the formatter (e.g. statement_indentation) applied
	 * to the whole block. When the block sits inside an outer control structure split
	 * across an HTML island, every line is shifted right by this amount; it must be
	 * stripped before re-applying codeIndent so sibling statements line up with the
	 * first one. The first statement is excluded on purpose: statement_indentation
	 * leaves it at column 0 (the indenting newline lives in the T_OPEN_TAG token).
	 */
	private function detectFormatterBaseIndent(Tokens $tokens, int $openIndex, int $closeIndex): string
	{
		$min = null;

		for ($i = $openIndex + 1; $i < $closeIndex; ++$i) {
			if (!$tokens[$i]->isGivenKind(T_WHITESPACE)) {
				continue;
			}

			// Indentation after each newline that precedes real content (skip blank lines).
			if (preg_match_all('/\n([ \t]*)(?=[^\n \t]|$)/', $tokens[$i]->getContent(), $matches)) {
				foreach ($matches[1] as $indent) {
					if ($min === null || strlen($indent) < strlen($min)) {
						$min = $indent;
					}
				}
			}
		}

		return $min ?? '';
	}

	private function detectIndentUnit(Tokens $tokens, int $openIndex, int $closeIndex): string
	{
		$minIndent = null;

		for ($i = $openIndex + 1; $i < $closeIndex; ++$i) {
			if (!$tokens[$i]->isGivenKind(T_WHITESPACE)) {
				continue;
			}

			$content = $tokens[$i]->getContent();
			if (preg_match_all('/\n(\t+| +)/', $content, $matches)) {
				foreach ($matches[1] as $indent) {
					if ($minIndent === null || strlen($indent) < strlen($minIndent)) {
						$minIndent = $indent;
					}
				}
			}
		}

		return $minIndent ?? '    ';
	}

	private function restoreInlineHtmlIndent(Tokens $tokens, int $openTagIndex, string $baseIndent): void
	{
		$prevIndex = $openTagIndex - 1;
		if ($prevIndex < 0 || !$tokens[$prevIndex]->isGivenKind(T_INLINE_HTML)) {
			return;
		}

		$content = $tokens[$prevIndex]->getContent();
		$tokens[$prevIndex] = new Token([T_INLINE_HTML, $content . $baseIndent]);
	}

	private function reindentBlock(Tokens $tokens, int $openIndex, int $closeIndex, string $codeIndent, string $baseIndent): void
	{
		// Base indent the formatter (e.g. statement_indentation) gave the whole block.
		// When the block sits inside an outer control structure split across an HTML
		// island, every line is shifted right by this amount; strip it before adding
		// codeIndent so sibling statements line up, not just the first one.
		$formatterBase = $this->detectFormatterBaseIndent($tokens, $openIndex, $closeIndex);
		$stripPattern = '/\n' . preg_quote($formatterBase, '/') . '(?!\n)/';

		// Handle first token after T_OPEN_TAG — may have been cleared by dedent
		$firstIndex = $openIndex + 1;
		if ($firstIndex < $closeIndex) {
			$firstContent = $tokens[$firstIndex]->getContent();
			if ($firstContent === '') {
				// Cleared token (from dedent) — replace with code indent
				$tokens[$firstIndex] = new Token([T_WHITESPACE, $codeIndent]);
			} elseif (!$tokens[$firstIndex]->isGivenKind([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				// Non-whitespace token right after T_OPEN_TAG — insert code indent
				$tokens->insertAt($firstIndex, new Token([T_WHITESPACE, $codeIndent]));
				$closeIndex++;
			} else {
				// Regular whitespace token — replace leading indent, add to subsequent lines
				$newContent = preg_replace($stripPattern, "\n" . $codeIndent, $firstContent);
				if (!str_starts_with($newContent, "\n")) {
					$newContent = preg_replace('/^[ \t]*/', $codeIndent, $newContent);
				}
				if ($newContent !== $firstContent) {
					$tokens[$firstIndex] = new Token([$tokens[$firstIndex]->getId(), $newContent]);
				}
			}
		}

		// Process remaining whitespace tokens
		for ($i = $openIndex + 2; $i < $closeIndex; ++$i) {
			if (!$tokens[$i]->isGivenKind([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				continue;
			}

			$content = $tokens[$i]->getContent();

			// Last whitespace before T_CLOSE_TAG: align with <?php (baseIndent)
			if ($i === $closeIndex - 1) {
				$lastNewline = strrpos($content, "\n");
				if ($lastNewline !== false) {
					$newContent = substr($content, 0, $lastNewline + 1) . $baseIndent;
				} else {
					$newContent = $content;
				}
			} else {
				$newContent = preg_replace($stripPattern, "\n" . $codeIndent, $content);
			}

			if ($newContent !== $content) {
				$tokens[$i] = new Token([$tokens[$i]->getId(), $newContent]);
			}
		}
	}
}
