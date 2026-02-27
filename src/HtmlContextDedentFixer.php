<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class HtmlContextDedentFixer extends AbstractFixer
{
	use HtmlContextDetectionTrait;

	public function getName(): string
	{
		return 'RepinsPL/html_context_dedent';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Removes base HTML-context indentation from PHP blocks before other fixers process them.',
			[],
		);
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isTokenKindFound(T_CLOSE_TAG);
	}

	public function getPriority(): int
	{
		return 1000;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		IndentRegistry::clear(spl_object_id($tokens));

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

			IndentRegistry::push(spl_object_id($tokens), $baseIndent);
			$this->stripInlineHtmlIndent($tokens, $index, $baseIndent);
			$this->dedentBlock($tokens, $index, $closeIndex, $baseIndent);
		}
	}

	private function stripInlineHtmlIndent(Tokens $tokens, int $openTagIndex, int $n): void
	{
		$prevIndex = $openTagIndex - 1;
		if ($prevIndex < 0) {
			return;
		}

		$content = $tokens[$prevIndex]->getContent();
		$tokens[$prevIndex] = new Token([T_INLINE_HTML, substr($content, 0, -$n)]);
	}

	private function dedentBlock(Tokens $tokens, int $openIndex, int $closeIndex, int $n): void
	{
		for ($i = $openIndex + 1; $i < $closeIndex; ++$i) {
			if (!$tokens[$i]->isGivenKind([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				continue;
			}

			$content = $tokens[$i]->getContent();
			$newContent = preg_replace('/\n\t{1,' . $n . '}/', "\n", $content);

			// First token after T_OPEN_TAG: also strip leading tabs (T_OPEN_TAG ends with \n)
			if ($i === $openIndex + 1) {
				$newContent = preg_replace('/^\t{1,' . $n . '}/', '', $newContent);
			}

			if ($newContent !== $content) {
				if ($newContent === '') {
					$tokens->clearAt($i);
				} else {
					$tokens[$i] = new Token([$tokens[$i]->getId(), $newContent]);
				}
			}
		}
	}
}
