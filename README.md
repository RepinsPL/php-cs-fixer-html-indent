# PHP-CS-Fixer HTML Indent

Custom fixery dla [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer), które zachowują indentację bloków PHP osadzonych w plikach HTML/Blade.

## Problem

PHP-CS-Fixer formatuje bloki PHP bez uwzględnienia kontekstu HTML, w którym się znajdują. W plikach mieszanych (np. szablonach Blade) prowadzi to do złamania indentacji:

**Przed uruchomieniem php-cs-fixer** — poprawna indentacja w kontekście HTML:
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

**Po uruchomieniu php-cs-fixer** — indentacja kontekstu HTML zostaje utracona:
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

**Z tą biblioteką** — php-cs-fixer formatuje PHP, a indentacja HTML zostaje zachowana:
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

## Instalacja

```bash
composer require --dev repinspl/php-cs-fixer-html-indent
```

## Konfiguracja

W pliku `.php-cs-fixer.dist.php` zarejestruj oba fixery i włącz odpowiadające im reguły:

```php
<?php

$fixers = [
    new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextDedentFixer(),
    new \RepinsPL\PhpCsFixerHtmlIndent\HtmlContextReindentFixer(),
];

return (new PhpCsFixer\Config())
    ->registerCustomFixers($fixers)
    ->setRules([
        // Twoje pozostałe reguły...
        'RepinsPL/html_context_dedent' => true,
        'RepinsPL/html_context_reindent' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
    );
```

> **Ważne:** Oba fixery muszą być włączone razem. Dedent bez reindent usunie indentację bez jej przywrócenia.

## Jak to działa

Biblioteka dostarcza dwa fixery, które opakowują cały pipeline PHP-CS-Fixera:

| Fixer | Priorytet | Rola |
|---|---|---|
| `RepinsPL/html_context_dedent` | `1000` | **Przed** innymi fixerami — usuwa bazową indentację HTML z bloków PHP |
| `RepinsPL/html_context_reindent` | `-1000` | **Po** wszystkich fixerach — przywraca bazową indentację HTML |

Dzięki temu pozostałe fixery (np. `braces`, `indentation_type`) pracują na kodzie PHP bez dodatkowej indentacji i mogą go poprawnie sformatować. Po zakończeniu formatowania indentacja kontekstu HTML zostaje przywrócona.

### Ograniczenia

- Bazowa indentacja jest wykrywana wyłącznie dla **tabulacji**. Bloki PHP poprzedzone spacjami nie są modyfikowane.
- Fixer operuje na wieloliniowych blokach `<?php ... ?>`. Jednoliniowe tagi PHP (bez znaku nowej linii po `<?php`) są pomijane.

## Wymagania

- PHP >= 8.1
- PHP-CS-Fixer ^3.0

## Licencja

[MIT](https://opensource.org/licenses/MIT)
