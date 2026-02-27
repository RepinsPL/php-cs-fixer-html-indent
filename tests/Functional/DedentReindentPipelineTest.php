<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests\Functional;

use RepinsPL\PhpCsFixerHtmlIndent\Tests\AbstractFixerTestCase;

final class DedentReindentPipelineTest extends AbstractFixerTestCase
{
	/** @var array<string, bool> */
	private const PIPELINE_RULES = [
		'RepinsPL/html_context_dedent' => true,
		'RepinsPL/html_context_reindent' => true,
	];

	public function testBasicBlockRoundTrip(): void
	{
		$input = "<div>\n\t<section>\n\t\t<?php\n\t\t\t\$x = 1;\n\t\t\t\$y = 2;\n\t\t?>\n\t</section>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testMultipleBlocksAtDifferentDepths(): void
	{
		$input = "<div>\n\t<?php\n\t\t\$a = 1;\n\t?>\n\t<section>\n\t\t<article>\n\t\t\t<?php\n\t\t\t\t\$b = 2;\n\t\t\t\t\$c = 3;\n\t\t\t?>\n\t\t</article>\n\t</section>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testComplexPhpBlock(): void
	{
		$input = "<div>\n\t<section>\n\t\t<?php\n\t\t\tif (\$condition) {\n\t\t\t\tforeach (\$items as \$item) {\n\t\t\t\t\techo \$item;\n\t\t\t\t}\n\t\t\t} else {\n\t\t\t\t\$data = [\n\t\t\t\t\t'key' => 'value',\n\t\t\t\t\t'foo' => 'bar',\n\t\t\t\t];\n\t\t\t}\n\t\t?>\n\t</section>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testEmptyLinesPreserved(): void
	{
		$input = "<div>\n\t<?php\n\t\t\$a = 1;\n\n\t\t\$b = 2;\n\n\t\t\$c = 3;\n\t?>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testDocCommentBlock(): void
	{
		$input = "<div>\n\t<?php\n\t\t/**\n\t\t * A doc comment.\n\t\t */\n\t\tfunction test(): void\n\t\t{\n\t\t}\n\t?>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testAdjacentBlocks(): void
	{
		$input = "<div>\n\t<?php\n\t\t\$a = 1;\n\t?>\n\t<span>separator</span>\n\t<?php\n\t\t\$b = 2;\n\t?>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testBlockAtColumnZeroIsSkipped(): void
	{
		$input = "<?php\n\$x = 1;\n\$y = 2;\n?>\n<div>content</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testSingleLineTagIsSkipped(): void
	{
		$input = "<div>\n\t<?php echo \$x; ?>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}

	public function testSpaceIndentedBlockIsSkipped(): void
	{
		$input = "<div>\n    <?php\n        \$x = 1;\n    ?>\n</div>\n";

		$result = $this->runPhpCsFixer($input, self::PIPELINE_RULES);

		self::assertSame($input, $result);
	}
}
