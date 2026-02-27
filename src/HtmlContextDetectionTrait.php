<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent;

use PhpCsFixer\Tokenizer\Tokens;

trait HtmlContextDetectionTrait
{
	/**
	 * For a T_OPEN_TAG at position $index, detects the base indentation from HTML context.
	 * Returns the number of tabs or null if the block doesn't need protection.
	 */
	private function detectBaseIndent(Tokens $tokens, int $index): ?int
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

		// Last line must consist entirely of tabs
		if ($lastLine === '' or !preg_match('/^\t+$/', $lastLine)) {
			return null;
		}

		return strlen($lastLine);
	}

	/**
	 * Finds the T_CLOSE_TAG that closes the PHP block starting at $startIndex.
	 */
	private function findBlockClose(Tokens $tokens, int $startIndex): ?int
	{
		$count = $tokens->count();
		for ($i = $startIndex + 1; $i < $count; ++$i) {
			if ($tokens[$i]->isGivenKind(T_CLOSE_TAG)) {
				return $i;
			}
		}

		return null;
	}
}
