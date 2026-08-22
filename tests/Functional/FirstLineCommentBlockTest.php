<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class FirstLineCommentBlockTest extends AbstractFixerTestCase
{
    #[DataProvider('provideFirstLineCommentCases')]
    public function testCommentOnFirstLineOfBlockIsPreserved(string $input): void
    {
        // A protected block whose first line is a comment. Dedent strips the
        // whitespace between the open tag and the comment down to an empty
        // token, which php-cs-fixer's runner removes right after the fixer
        // runs (Tokens::clearEmptyTokens()). By the time reindent runs, the
        // comment token therefore sits directly after T_OPEN_TAG and is the
        // "first token" reindentBlock() restores the code indent on. Its text
        // must survive that restoration; the input is already correctly
        // indented, so the file must come out unchanged.
        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            '@PER-CS' => true,
        ], "\t");

        self::assertSame($input, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFirstLineCommentCases(): iterable
    {
        yield 'line comment' => [
            <<<'PHP'
            <div>
            	<?php
            	// Render the item.
            	echo $item;
            	?>
            </div>

            PHP,
        ];

        yield 'doc comment' => [
            <<<'PHP'
            <div>
            	<?php
            	/** @var string $item */
            	echo $item;
            	?>
            </div>

            PHP,
        ];
    }
}
