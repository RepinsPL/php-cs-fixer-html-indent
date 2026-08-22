<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent;

use PhpCsFixer\Tokenizer\Tokens;

trait HtmlContextDetectionTrait
{
	/**
	 * For a T_OPEN_TAG at position $index, detects the base indentation from HTML context.
	 * Returns the indent string (tabs or spaces) or null if the block doesn't need protection.
	 */
	private function detectBaseIndent(Tokens $tokens, int $index): ?string
	{
		$openTag = $tokens[$index];

		// T_OPEN_TAG must end with \n (multi-line block)
		if (!str_ends_with($openTag->getContent(), "\n")) {
			return null;
		}

		$prevIndex = $index - 1;
		if ($prevIndex < 0) {
			return null;
		}

		$prevToken = $tokens[$prevIndex];
		if (!$prevToken->isGivenKind(T_INLINE_HTML)) {
			return null;
		}

		$content = $prevToken->getContent();
		$lastNewline = strrpos($content, "\n");
		if ($lastNewline === false) {
			return null;
		}

		$lastLine = substr($content, $lastNewline + 1);

		// Last line must consist entirely of tabs or spaces (not mixed)
		if ($lastLine === '' || !preg_match('/^(\t+| +)$/', $lastLine)) {
			return null;
		}

		return $lastLine;
	}

	/**
	 * Detects the code indent from the first whitespace token after T_OPEN_TAG.
	 * Returns the indent string or null if no code indent is found.
	 */
	private function detectCodeIndent(Tokens $tokens, int $index): ?string
	{
		$firstIndex = $index + 1;
		if ($firstIndex >= $tokens->count()) {
			return null;
		}

		if (!$tokens[$firstIndex]->isGivenKind(T_WHITESPACE)) {
			return null;
		}

		$content = $tokens[$firstIndex]->getContent();
		$firstNewline = strpos($content, "\n");
		$indent = ($firstNewline !== false) ? substr($content, 0, $firstNewline) : $content;

		if ($indent === '' || !preg_match('/^(\t+| +)$/', $indent)) {
			return null;
		}

		return $indent;
	}

	/**
	 * Finds the T_CLOSE_TAG that closes the PHP block starting at $startIndex,
	 * provided the block stays within the scope the open tag was already in.
	 *
	 * A bare forward scan for the next T_CLOSE_TAG is not safe: if the PHP
	 * between the two tags closes a brace that was opened before $startIndex
	 * (e.g. the enclosing method ends and a sibling method begins before the
	 * next HTML island), that intervening code sits at a different nesting
	 * depth than the block's own contents. Treating it as part of the same
	 * block would apply this block's indent adjustment to lines that don't
	 * belong to it. Depth is tracked relative to $startIndex; hitting a '}'
	 * that isn't matched by a '{' seen after $startIndex means we've walked
	 * out of the scope the open tag lives in, so the search aborts.
	 */
	private function findBlockClose(Tokens $tokens, int $startIndex): ?int
	{
		$count = $tokens->count();
		$depth = 0;

		for ($i = $startIndex + 1; $i < $count; ++$i) {
			$token = $tokens[$i];

			if ($token->isGivenKind(T_CLOSE_TAG)) {
				return $i;
			}

			if ($token->equals('{')) {
				++$depth;
			} elseif ($token->equals('}')) {
				if ($depth === 0) {
					return null;
				}

				--$depth;
			}
		}

		return null;
	}

	/**
	 * Counts how many '{' opened between $openIndex (exclusive) and $closeIndex
	 * (exclusive) are still unmatched by $closeIndex — i.e. how deep inside
	 * control structures opened after $openIndex the close tag sits. findBlockClose()
	 * already guarantees this span never dips below the depth at $openIndex, so a
	 * plain running count is safe here.
	 */
	private function braceDepthBeforeClose(Tokens $tokens, int $openIndex, int $closeIndex): int
	{
		$depth = 0;

		for ($i = $openIndex + 1; $i < $closeIndex; ++$i) {
			if ($tokens[$i]->equals('{')) {
				++$depth;
			} elseif ($tokens[$i]->equals('}')) {
				--$depth;
			}
		}

		return $depth;
	}
}
