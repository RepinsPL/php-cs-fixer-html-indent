<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class NestedPhpBlockStatementIndentTest extends AbstractFixerTestCase
{
    public function testStatementsAfterFirstKeepIndent(): void
    {
        // A multi-statement PHP block nested in HTML, inside a PHP control
        // structure that is split across an HTML island (opening brace, then a
        // closing PHP tag, some HTML, then a reopening PHP tag), must keep every
        // statement at the same indentation. The input below is already correctly
        // indented, so the fixers should leave it unchanged. They currently shift
        // every statement after the first one one level too deep (here $b becomes
        // three tabs instead of two).
        $input = <<<'PHP'
            <?php
            if ($x) {
            	?>
            	<div class="a">
            		<?php
            		$a = 1;
            		$b = 2;
            		?>
            	</div>
            	<?php
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
