<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class MultilineFirstStatementInNestedScopeTest extends AbstractFixerTestCase
{
    public function testSecondStatementKeepsIndentWhenFirstStatementIsMultiline(): void
    {
        // The block sits inside class + method braces (real depth 2), and its
        // first statement spans multiple lines (a function call with a
        // multi-line array argument). statement_indentation leaves the first
        // statement at its dedented, relative-zero position but reindents any
        // *subsequent* top-level statement to the real brace depth (here, one
        // indentation level per brace: 8 spaces). detectFormatterBaseIndent()
        // scanned from right after the first token, so it picked up the first
        // statement's own zero-based closing ");" line and used it as the
        // minimum, defeating the shift detection: the second statement's
        // already-shifted lines got baseIndent+codeIndent added on top instead
        // of having the real-depth shift stripped first.
        $input = <<<'PHP'
            <?php

            class Test {

                public function someMethod() {
                    ?>
                    <div id="sticky-element-sidebar-box">
                        <?php
                        SomeClass::render(
                            [
                                'foo' => 1,
                                'bar' => 2,
                            ]
                        );

                        SomeOtherClass::render(
                            [
                                'foo' => 1,
                                'bar' => 2,
                            ]
                        );

                        ?>
                    </div>
                    <?php
                }
            }

            PHP;

        $result = $this->runPhpCsFixer($input, [
            'RepinsPL/html_context_dedent' => true,
            'RepinsPL/html_context_reindent' => true,
            'statement_indentation' => true,
        ]);

        self::assertSame($input, $result);
    }
}
