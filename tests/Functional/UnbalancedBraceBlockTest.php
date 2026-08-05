<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class UnbalancedBraceBlockTest extends AbstractFixerTestCase
{
    public function testBlockWithUnclosedBraceIsLeftUntouched(): void
    {
        // The first block opens an "if" brace that isn't closed until the
        // second block, further down, past an HTML island; the second block
        // closes it without having opened it itself. Each block legitimately
        // reaches its own close tag while its own braces are unbalanced.
        // Dedenting the first block's interior while leaving the closing "}"
        // in the second, untouched block would desync statement_indentation's
        // view of that still-open scope, and reindent has no way to correct
        // it back later. Both blocks must be left alone, keeping "echo" at
        // its original 2-tab depth instead of losing a tab.
        $input = <<<'PHP'
            <div>
            	<?php
            	if ($x) {
            		echo 'test';
            		?>
            	<p>shown</p>
            	<?php
            	}
            	?>
            </div>

            PHP;

        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            '@PER-CS' => true,
        ], "\t");

        self::assertSame($input, $result);
    }
}
