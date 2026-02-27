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

			$baseIndent = IndentRegistry::shift(spl_object_id($tokens));

			if ($baseIndent === null) {
				// Fallback: detect from T_INLINE_HTML (for cases without dedent)
				$baseIndent = $this->detectBaseIndent($tokens, $index);
			}

			if ($baseIndent === null) {
				continue;
			}

			$closeIndex = $this->findBlockClose($tokens, $index);
			if ($closeIndex === null) {
				continue;
			}

			$this->restoreInlineHtmlIndent($tokens, $index, $baseIndent);

			$indentUnit = $this->detectIndentUnit($tokens, $index, $closeIndex);
			$codeIndent = $baseIndent . $indentUnit;
			$this->reindentBlock($tokens, $index, $closeIndex, $codeIndent, $baseIndent);
		}
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
				$newContent = preg_replace('/\n(?!\n)/', "\n" . $codeIndent, $firstContent);
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
				$newContent = preg_replace('/\n(?!\n)/', "\n" . $codeIndent, $content);
			}

			if ($newContent !== $content) {
				$tokens[$i] = new Token([$tokens[$i]->getId(), $newContent]);
			}
		}
	}
}
