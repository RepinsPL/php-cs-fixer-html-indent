<?php

declare(strict_types=1);

namespace RepinsPL\PhpCsFixerHtmlIndent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

abstract class AbstractFixerTestCase extends TestCase
{
	private ?string $tempDir = null;

	protected function tearDown(): void
	{
		$this->cleanupTempDir();
		parent::tearDown();
	}

	/**
	 * Runs php-cs-fixer fix via CLI on the given input code with the specified rules.
	 *
	 * @param array<string, mixed> $rules
	 */
	protected function runPhpCsFixer(string $inputCode, array $rules): string
	{
		$this->tempDir = sys_get_temp_dir() . '/phpcsf-test-' . uniqid();
		mkdir($this->tempDir, 0777, true);

		$testFile = $this->tempDir . '/test.php';
		file_put_contents($testFile, $inputCode);

		$configContent = $this->generateConfig($testFile, $rules);
		$configFile = $this->tempDir . '/.php-cs-fixer.dist.php';
		file_put_contents($configFile, $configContent);

		$phpCsFixerBin = dirname(__DIR__) . '/vendor/bin/php-cs-fixer';

		$process = new Process([
			PHP_BINARY,
			$phpCsFixerBin,
			'fix',
			'--config=' . $configFile,
			'--using-cache=no',
			'--allow-risky=yes',
		]);

		$process->setTimeout(30);
		$process->run();

		// php-cs-fixer returns 0 (no changes) or 8 (changes applied) on success
		if (!in_array($process->getExitCode(), [0, 8], true)) {
			$this->fail(sprintf(
				"php-cs-fixer failed with exit code %d.\nSTDOUT: %s\nSTDERR: %s",
				$process->getExitCode(),
				$process->getOutput(),
				$process->getErrorOutput(),
			));
		}

		return file_get_contents($testFile);
	}

	/**
	 * @param array<string, mixed> $rules
	 */
	private function generateConfig(string $testFile, array $rules): string
	{
		$rulesExport = var_export($rules, true);
		$finderDir = var_export(dirname($testFile), true);
		$fileName = var_export(basename($testFile), true);

		return <<<PHP
			<?php

			use PhpCsFixer\Config;
			use PhpCsFixer\Finder;
			use RepinsPL\PhpCsFixerHtmlIndent\HtmlContextDedentFixer;
			use RepinsPL\PhpCsFixerHtmlIndent\HtmlContextReindentFixer;

			\$finder = Finder::create()
				->in({$finderDir})
				->name({$fileName});

			return (new Config())
				->setFinder(\$finder)
				->registerCustomFixers([
					new HtmlContextDedentFixer(),
					new HtmlContextReindentFixer(),
				])
				->setRules({$rulesExport});
			PHP;
	}

	private function cleanupTempDir(): void
	{
		if ($this->tempDir === null || !is_dir($this->tempDir)) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			if ($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}

		rmdir($this->tempDir);
		$this->tempDir = null;
	}
}
