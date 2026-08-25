<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class FirstStatementBoundaryTest extends AbstractFixerTestCase
{
    #[DataProvider('provideFirstStatementShapes')]
    public function testStatementsAfterTheFirstOneKeepIndent(string $input): void
    {
        // reindentBlock() splits a block at the end of its first statement:
        // statement_indentation leaves that statement at its dedented,
        // relative-zero position, while everything after it is reindented to the
        // real brace depth. firstStatementEndIndex() must therefore agree with
        // statement_indentation on where the first statement ends — a boundary
        // landing too early puts still-relative-zero lines in the "already
        // shifted" region, and the block's later statements gain an extra
        // indentation level. Each input is already correctly indented, so the
        // file must come out unchanged.
        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            '@PER-CS' => true,
        ]);

        self::assertSame($input, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFirstStatementShapes(): iterable
    {
        // The '}' closing the "if" branch is not the end of the statement.
        yield 'if/else' => [
            <<<'PHP'
            <?php

            class Test
            {
                public function m()
                {
                    ?>
                    <div>
                        <?php
                        if ($a) {
                            foo();
                        } else {
                            bar();
                        }

                        baz(
                            1,
                        );
                        ?>
                    </div>
                    <?php
                }
            }

            PHP,
        ];

        // Same for the '}' closing the "try" body.
        yield 'try/catch' => [
            <<<'PHP'
            <?php

            class Test
            {
                public function m()
                {
                    ?>
                    <div>
                        <?php
                        try {
                            foo();
                        } catch (\Throwable $e) {
                            bar();
                        }

                        baz(
                            1,
                        );
                        ?>
                    </div>
                    <?php
                }
            }

            PHP,
        ];

        // Alternative syntax opens no brace, so the ';' of an inner statement
        // must not be mistaken for the end of the enclosing "if".
        yield 'alternative syntax' => [
            <<<'PHP'
            <?php

            class Test
            {
                public function m()
                {
                    ?>
                    <div>
                        <?php
                        if ($a):
                            foo();
                            bar();
                        endif;

                        baz(
                            1,
                        );
                        ?>
                    </div>
                    <?php
                }
            }

            PHP,
        ];

        // Here the trailing "while" does close the statement.
        yield 'do/while' => [
            <<<'PHP'
            <?php

            class Test
            {
                public function m()
                {
                    ?>
                    <div>
                        <?php
                        do {
                            foo();
                        } while ($a);

                        baz(
                            1,
                        );
                        ?>
                    </div>
                    <?php
                }
            }

            PHP,
        ];

        // ...but here it does not: treating every "while" after a '}' as a
        // do-while continuation would swallow this loop into the first
        // statement and push the rest of the block one level too deep.
        yield 'while loop after an if block' => [
            <<<'PHP'
            <?php

            class Test
            {
                public function m()
                {
                    ?>
                    <div>
                        <?php
                        if ($a) {
                            foo();
                        }
                        while ($b) {
                            bar();
                        }

                        baz(
                            1,
                        );
                        ?>
                    </div>
                    <?php
                }
            }

            PHP,
        ];
    }
}
