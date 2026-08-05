<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class ScopeCrossingBlockCloseTest extends AbstractFixerTestCase
{
    public function testBlockThatNeverClosesBeforeMethodEndIsLeftUntouched(): void
    {
        // The PHP block opened inside bar() never reaches its own T_CLOSE_TAG:
        // it runs straight into the brace that closes bar() itself, with the
        // next close tag only appearing later, inside the unrelated sibling
        // method baz(). A bare forward scan for the next T_CLOSE_TAG (no
        // regard for brace nesting) would treat everything up to baz()'s
        // close tag as one block and dedent/reindent it using bar()'s 3-tab
        // code indent, stripping baz()'s signature and opening brace down
        // from 1 tab to column 0. findBlockClose() must recognize that the
        // brace closing bar() is not matched by any brace seen after the
        // open tag, abort, and leave this block (and therefore baz()'s
        // signature) untouched.
        $input = <<<'PHP'
            <?php

            class Foo
            {
            	public function bar(): void
            	{
            		?>
            		<div>
            			<?php
            			echo 1;
            	}

            	public function baz(): void
            	{
            		?>
            		<div></div>
            		<?php
            	}
            }

            PHP;

        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            '@PER-CS' => true,
        ], "\t");

        self::assertSame($input, $result);
    }
}
