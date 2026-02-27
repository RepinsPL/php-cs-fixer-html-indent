<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent;

/**
 * Static registry for sharing base indent values between DedentFixer and ReindentFixer.
 *
 * Both fixers iterate T_OPEN_TAG tokens in reverse order. Dedent (priority 1000)
 * stores base indent values; Reindent (priority -1000) retrieves them in the same
 * reverse order. This allows Dedent to strip trailing tabs from T_INLINE_HTML
 * (preventing interference from fixers like statement_indentation) while Reindent
 * can still restore the original indentation.
 */
final class IndentRegistry
{
	/** @var array<int, list<string>> Keyed by spl_object_id of Tokens */
	private static array $pendingIndents = [];

	public static function push(int $tokensId, string $indent): void
	{
		self::$pendingIndents[$tokensId][] = $indent;
	}

	public static function shift(int $tokensId): ?string
	{
		if (empty(self::$pendingIndents[$tokensId])) {
			return null;
		}

		return array_shift(self::$pendingIndents[$tokensId]);
	}

	public static function clear(int $tokensId): void
	{
		unset(self::$pendingIndents[$tokensId]);
	}
}
