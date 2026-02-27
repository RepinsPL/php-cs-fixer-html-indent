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

			$baseIndent = $this->detectBaseIndent($tokens, $index);
			if ($baseIndent === null) {
				continue;
			}

			$closeIndex = $this->findBlockClose($tokens, $index);
			if ($closeIndex === null) {
				continue;
			}

			$this->reindentBlock($tokens, $index, $closeIndex, $baseIndent);
		}
	}

	private function reindentBlock(Tokens $tokens, int $openIndex, int $closeIndex, int $n): void
	{
		$tabs = str_repeat("\t", $n);

		// Handle first token after T_OPEN_TAG — may have been cleared by dedent
		$firstIndex = $openIndex + 1;
		if ($firstIndex < $closeIndex) {
			$firstContent = $tokens[$firstIndex]->getContent();
			if ($firstContent === '') {
				// Cleared token (from dedent) — replace with base indent
				$tokens[$firstIndex] = new Token([T_WHITESPACE, $tabs]);
			} elseif (!$tokens[$firstIndex]->isGivenKind([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				// Non-whitespace token right after T_OPEN_TAG — insert base indent
				$tokens->insertAt($firstIndex, new Token([T_WHITESPACE, $tabs]));
				$closeIndex++;
			} else {
				// Regular whitespace token — add base indent
				$newContent = preg_replace('/\n(?!\n)/', "\n" . $tabs, $firstContent);
				if (!str_starts_with($newContent, "\n")) {
					$newContent = $tabs . $newContent;
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
			$newContent = preg_replace('/\n(?!\n)/', "\n" . $tabs, $content);

			if ($newContent !== $content) {
				$tokens[$i] = new Token([$tokens[$i]->getId(), $newContent]);
			}
		}
	}
}
