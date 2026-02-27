<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class PerCsWithSpacesTest extends AbstractFixerTestCase
{
    public function testPerCsWithSpaces(): void
    {
        // Without our fixers, @PER-CS strips the extra indent level inside PHP blocks,
        // collapsing "foreach" from 12 spaces (8 base + 4 code) to 8 (base only).
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
        ]);

        self::assertSame($input, $result);
    }
}
