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

			if (!str_ends_with($tokens[$index]->getContent(), "\n")) {
				continue;
			}

			$baseIndent = $this->detectBaseIndent($tokens, $index);

			if ($baseIndent === null) {
				IndentRegistry::push(spl_object_id($tokens), null);
				continue;
			}

			$closeIndex = $this->findBlockClose($tokens, $index);
			if ($closeIndex === null) {
				IndentRegistry::push(spl_object_id($tokens), null);
				continue;
			}

			$codeIndent = $this->detectCodeIndent($tokens, $index) ?? $baseIndent;
			$closingDepth = $this->braceDepthBeforeClose($tokens, $index, $closeIndex);

			IndentRegistry::push(spl_object_id($tokens), $baseIndent, $codeIndent, $closingDepth);

			if ($closingDepth !== 0) {
				// Braces opened inside this block (e.g. an `if`/`foreach` wrapping
				// HTML) aren't closed by the time we reach this close tag — their
				// matching closing braces live in a later, unprotected island.
				// Dedenting here would shift statement_indentation's view of that
				// still-open scope for everything downstream, and reindentBlock has
				// no reach into that later island to correct it back. Leave the
				// whole block untouched; stock fixers already handle this case.
				continue;
			}

			$this->stripInlineHtmlIndent($tokens, $index, $baseIndent);
			$this->dedentBlock($tokens, $index, $closeIndex, $codeIndent);
		}
	}

	private function stripInlineHtmlIndent(Tokens $tokens, int $openTagIndex, string $baseIndent): void
	{
		$prevIndex = $openTagIndex - 1;
		if ($prevIndex < 0) {
			return;
		}

		$content = $tokens[$prevIndex]->getContent();
		$tokens[$prevIndex] = new Token([T_INLINE_HTML, substr($content, 0, -strlen($baseIndent))]);
	}

	private function dedentBlock(Tokens $tokens, int $openIndex, int $closeIndex, string $codeIndent): void
	{
		$char = preg_quote($codeIndent[0], '/');
		$count = strlen($codeIndent);

		for ($i = $openIndex + 1; $i < $closeIndex; ++$i) {
			if (!$tokens[$i]->isGivenKind([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				continue;
			}

			$content = $tokens[$i]->getContent();
			$newContent = preg_replace('/\n' . $char . '{1,' . $count . '}/', "\n", $content);

			// First token after T_OPEN_TAG: also strip leading indent (T_OPEN_TAG ends with \n)
			if ($i === $openIndex + 1) {
				$newContent = preg_replace('/^' . $char . '{1,' . $count . '}/', '', $newContent);
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
