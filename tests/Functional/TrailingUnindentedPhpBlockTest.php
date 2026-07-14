<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class TrailingUnindentedPhpBlockTest extends AbstractFixerTestCase
{
    public function testTrailingBlockWithoutBaseIndentStaysUntouched(): void
    {
        // A multi-line PHP block at column 0 (no HTML base indent) placed after
        // an HTML-indented block must not be touched. Dedent strips the nested
        // block's lines to column 0, so statement_indentation records an empty
        // "previous line indent" there; the statement scope it opens for the
        // following inline HTML then stretches into the trailing block and
        // rewrites the newline before its first statement to the HTML-context
        // indent. Reindent skips blocks with no detected base indent, so the
        // spurious indent survives: include('footer.php') gains two tabs.
        // Without the dedent/reindent pair, @PER-CS leaves that line alone.
        $input = <<<'PHP'
            <?php
            $items = [1, 2];
            ?>
            <div>
            	<?php foreach ($items as $item) { ?>
            		<div>
            			<?php
            			echo $item;
            			?>
            		</div>
            	<?php } ?>
            </div>

            <?php
            include('footer.php');
            ?>
            <!-- END -->
            <?php
            include('scripts.php'); ?>

            PHP;

        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            '@PER-CS' => true,
        ], "\t");

        self::assertSame($input, $result);
    }
}
