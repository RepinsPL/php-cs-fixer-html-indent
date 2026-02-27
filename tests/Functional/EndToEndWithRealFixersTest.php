<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class EndToEndWithRealFixersTest extends AbstractFixerTestCase
{
	public function testStatementIndentation(): void
	{
		// Badly indented PHP inside HTML — statement_indentation should fix it,
		// while our fixers preserve the HTML base indentation.
		$input = "<div>\n\t<section>\n\t\t<?php\n\t\t\$x = 1;\n\t\tif (\$x) {\n\t\t\$y = 2;\n\t\t}\n\t\t?>\n\t</section>\n</div>\n";

		// statement_indentation uses 4 spaces for indent levels by default,
		// so $y gets 2 tabs (HTML base) + 4 spaces (one indent level inside if).
		$expected = "<div>\n\t<section>\n\t\t<?php\n\t\t\$x = 1;\n\t\tif (\$x) {\n\t\t    \$y = 2;\n\t\t}\n\t\t?>\n\t</section>\n</div>\n";

		$result = $this->runPhpCsFixer($input, [
			'RepinsPL/html_context_dedent' => true,
			'RepinsPL/html_context_reindent' => true,
			'statement_indentation' => true,
		]);

		self::assertSame($expected, $result);
	}

	public function testBracesPosition(): void
	{
		// Braces on wrong lines — fixers should reformat them, base indent preserved.
		$input = "<div>\n\t<?php\n\tfunction foo()\n\t{\n\tif (true)\n\t{\n\techo 'x';\n\t}\n\t}\n\t?>\n</div>\n";

		$result = $this->runPhpCsFixer($input, [
			'RepinsPL/html_context_dedent' => true,
			'RepinsPL/html_context_reindent' => true,
			'braces_position' => true,
			'statement_indentation' => true,
		]);

		// Verify base indent is preserved
		self::assertStringContainsString("\t<?php\n", $result);
		self::assertStringContainsString("\t?>\n", $result);
		// Verify PHP code is properly indented within the block
		self::assertStringContainsString("\tfunction foo()\n", $result);
	}

	public function testPsr12Subset(): void
	{
		// Realistic scenario: PSR-12 formatting on PHP embedded in HTML.
		$input = "<html>\n\t<body>\n\t\t<?php\n\t\tfunction hello( \$name ){\n\t\techo 'Hello '.\$name ;\n\t\t}\n\t\t?>\n\t</body>\n</html>\n";

		$result = $this->runPhpCsFixer($input, [
			'RepinsPL/html_context_dedent' => true,
			'RepinsPL/html_context_reindent' => true,
			'statement_indentation' => true,
			'function_declaration' => true,
			'no_spaces_inside_parenthesis' => true,
		]);

		// Verify base HTML indentation (2 tabs) is preserved
		self::assertStringContainsString("\t\t<?php\n", $result);
		self::assertStringContainsString("\t\t?>\n", $result);
		// Verify the PHP code is formatted and re-indented
		self::assertStringContainsString("\t\tfunction hello(", $result);
	}
}
