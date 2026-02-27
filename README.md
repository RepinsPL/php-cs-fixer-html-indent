# PHP-CS-Fixer HTML Indent

Custom fixers for [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) that preserve indentation of PHP blocks embedded in HTML/Blade files.

## The problem

PHP-CS-Fixer formats PHP blocks without considering the surrounding HTML context. In mixed files (e.g. Blade templates) this breaks the indentation:

**Before running php-cs-fixer** — correct indentation within the HTML context:
```html
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
```

**After running php-cs-fixer** — HTML context indentation is lost:
```html
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
```

**With this library** — php-cs-fixer formats the PHP while the HTML indentation is preserved:
```html
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
```

## Installation

```bash
composer require --dev repinspl/php-cs-fixer-html-indent
```

## Configuration

Register both fixers and enable their rules in your `.php-cs-fixer.dist.php`:

```php
<?php

$fixers = [
    new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextDedentFixer(),
    new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextReindentFixer(),
];

return (new PhpCsFixer\Config())
    ->registerCustomFixers($fixers)
    ->setRules([
        // Your other rules...
        'RepinsPL/html_context_dedent' => true,
        'RepinsPL/html_context_reindent' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
    );
```

> **Important:** Both fixers must be enabled together. Dedent without reindent will strip the indentation without restoring it.

## How it works

The library provides two fixers that wrap the entire PHP-CS-Fixer pipeline:

| Fixer | Priority | Role |
|---|---|---|
| `RepinsPL/html_context_dedent` | `1000` | **Before** other fixers — strips base HTML indentation from PHP blocks |
| `RepinsPL/html_context_reindent` | `-1000` | **After** all fixers — restores base HTML indentation |

This allows other fixers (e.g. `braces`, `indentation_type`) to work on PHP code without extra indentation and format it correctly. Once formatting is complete, the HTML context indentation is restored.

## Requirements

- PHP >= 8.1
- PHP-CS-Fixer ^3.0

## License

[MIT](https://opensource.org/licenses/MIT)
