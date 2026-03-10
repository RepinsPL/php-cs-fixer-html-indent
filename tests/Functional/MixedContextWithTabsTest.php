<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class MixedContextWithTabsTest extends AbstractFixerTestCase
{
    public function testPhpBlocksInHtmlAndScriptContexts(): void
    {
        // Multiple PHP blocks in different contexts: top-level, HTML-nested,
        // and inside <script> tags. Tests that IndentRegistry correctly aligns
        // push/shift operations across all blocks.
        $input = <<<'PHP'
            <?php
            $items = ['x', 'y'];
            ?>
            <div>
            	<div>
            		<?php
            		foreach ($items as $item) {
            			echo '<span>' . $item . '</span>';
            		}
            		?>
            	</div>
            </div>
            <div>
            	<div>
            		<div>
            			<div>
            				<?php
            				foreach ($items as $item) {
            					echo '<span>' . $item . '</span>';
            				}
            				?>
            			</div>
            		</div>
            	</div>
            </div>
            <script>
            	var config = {
            		"data": [
            <?php
            foreach ($items as $item) {
            	echo "{'value': '$item'},";
            }
            ?>
            		]
            	};
            </script>
            <script>
            	var config2 = {
            		"data": [
            			<?php
            				foreach ($items as $item) {
            					echo "{'value': '$item'},";
            				}
            			?>
            		]
            	};
            </script>
            <?php
            echo 'end';

            PHP;

        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            '@PER-CS' => true,
        ], "\t");

        self::assertSame($input, $result);
    }
}
