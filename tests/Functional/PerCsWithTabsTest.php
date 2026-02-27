<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class PerCsWithTabsTest extends AbstractFixerTestCase
{
    public function testPerCsWithTabs(): void
    {
        // Without our fixers, @PER-CS converts the tab-based base indent to spaces
        // and strips the extra indent level inside PHP blocks.
        $input = <<<'PHP'
            	<div>
            		<main>
            			<?php
            				$users = DB::table('users')
            					->where('active', true)
            					->get();
            
            				foreach ($users as $user) {
            					echo $user->name;
            				}
            			?>
            		</main>
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
